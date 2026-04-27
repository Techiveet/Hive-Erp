#!/usr/bin/env bash

set -Eeuo pipefail

if [ "${TRACE_DEPLOY:-0}" = "1" ]; then
  set -x
fi

COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.prod.yml}"
LOG_SERVICES="${LOG_SERVICES:-caddy frontend backend queue reverb db redis meilisearch seaweedfs seaweedfs-bootstrap rembg gotenberg ffmpeg}"
REQUESTED_ROOT_DOMAIN=""
REQUESTED_SERVER_IP=""
REQUESTED_TLS_MODE=""
SKIP_FALLBACK_DOMAIN_SYNC=0
MIN_FREE_DISK_MB="${MIN_FREE_DISK_MB:-5120}"
DOCKER_PRUNE_BEFORE_BUILD="${DOCKER_PRUNE_BEFORE_BUILD:-1}"

compose() {
  docker compose -f "${COMPOSE_FILE}" "$@"
}

show_failure_context() {
  local exit_code="$1"
  local line_no="$2"
  local failed_command="$3"

  set +e

  echo "Deployment failed at line ${line_no}: ${failed_command}" >&2
  echo "Exit code: ${exit_code}" >&2
  echo "Working directory: $(pwd)" >&2

  echo "Docker Compose status:" >&2
  compose ps >&2 || true

  echo "Recent container logs:" >&2
  compose logs --tail=200 ${LOG_SERVICES} >&2 || true

  exit "${exit_code}"
}

trap 'show_failure_context "$?" "$LINENO" "$BASH_COMMAND"' ERR

print_usage() {
  cat <<'USAGE'
Usage: bash scripts/deploy-prod.sh [options]

Options:
  --root-domain DOMAIN           Update ROOT_DOMAIN and refresh default derived hostnames.
  --server-ip IP                 Update SERVER_IP and refresh public DNS hints in the UI.
  --tls-mode MODE                Set CADDY_TLS_MODE to on_demand, cloudflare, or auto.
  --skip-fallback-domain-sync    Skip syncing generated tenant fallback domains after deploy.
  -h, --help                     Show this help message.
USAGE
}

require_option_value() {
  local option_name="$1"
  local option_value="${2:-}"

  if [ -z "${option_value}" ]; then
    echo "Missing value for ${option_name}." >&2
    print_usage >&2
    exit 1
  fi
}

while [ "$#" -gt 0 ]; do
  case "$1" in
    --root-domain)
      require_option_value "$1" "${2:-}"
      REQUESTED_ROOT_DOMAIN="$2"
      shift 2
      ;;
    --root-domain=*)
      REQUESTED_ROOT_DOMAIN="${1#*=}"
      require_option_value "--root-domain" "${REQUESTED_ROOT_DOMAIN}"
      shift
      ;;
    --server-ip)
      require_option_value "$1" "${2:-}"
      REQUESTED_SERVER_IP="$2"
      shift 2
      ;;
    --server-ip=*)
      REQUESTED_SERVER_IP="${1#*=}"
      require_option_value "--server-ip" "${REQUESTED_SERVER_IP}"
      shift
      ;;
    --tls-mode)
      require_option_value "$1" "${2:-}"
      REQUESTED_TLS_MODE="$2"
      shift 2
      ;;
    --tls-mode=*)
      REQUESTED_TLS_MODE="${1#*=}"
      require_option_value "--tls-mode" "${REQUESTED_TLS_MODE}"
      shift
      ;;
    --skip-fallback-domain-sync)
      SKIP_FALLBACK_DOMAIN_SYNC=1
      shift
      ;;
    -h|--help)
      print_usage
      exit 0
      ;;
    *)
      echo "Unknown option: $1" >&2
      print_usage >&2
      exit 1
      ;;
  esac
done

free_disk_mb() {
  df -Pm . | awk 'NR==2 {print $4}'
}

show_disk_usage() {
  echo "Disk free space: $(free_disk_mb) MB"
  docker system df || true
}

cleanup_docker_build_cache() {
  echo "Pruning Docker build cache and dangling resources before build..."
  docker builder prune -af || true
  docker image prune -af || true
  docker container prune -f || true
}

ensure_disk_space_for_build() {
  local free_mb_before free_mb_after
  free_mb_before="$(free_disk_mb)"
  echo "Free disk before build preflight: ${free_mb_before} MB (required >= ${MIN_FREE_DISK_MB} MB)"

  if [ "${free_mb_before}" -ge "${MIN_FREE_DISK_MB}" ]; then
    return
  fi

  if [ "${DOCKER_PRUNE_BEFORE_BUILD}" = "1" ]; then
    cleanup_docker_build_cache
    free_mb_after="$(free_disk_mb)"
    echo "Free disk after Docker prune: ${free_mb_after} MB"
  else
    free_mb_after="${free_mb_before}"
  fi

  if [ "${free_mb_after}" -lt "${MIN_FREE_DISK_MB}" ]; then
    echo "Insufficient disk space for deployment build." >&2
    echo "Need at least ${MIN_FREE_DISK_MB} MB free, found ${free_mb_after} MB." >&2
    show_disk_usage >&2
    exit 1
  fi
}

cleanup_stale_recreate_containers() {
  local stale_names=()
  local line

  while IFS= read -r line; do
    [ -n "${line}" ] && stale_names+=("${line}")
  done < <(
    docker ps -a --format '{{.Names}}' \
      | grep -E '^[0-9a-f]{12,}_(hive-(caddy|redis|db|search|ai-rembg|gotenberg|backend|queue|reverb|frontend|ffmpeg|seaweedfs|seaweedfs-bootstrap))$' \
      || true
  )

  if [ "${#stale_names[@]}" -eq 0 ]; then
    return
  fi

  echo "Removing stale Compose recreate containers: ${stale_names[*]}"
  docker rm -f "${stale_names[@]}" >/dev/null
}

get_env_value() {
  local key="$1"
  local line

  line="$(grep -E "^${key}=" .env | tail -n 1 || true)"
  printf '%s' "${line#*=}"
}

set_env_value() {
  local key="$1"
  local value="$2"
  local escaped_value

  escaped_value="$(printf '%s' "${value}" | sed -e 's/[&#]/\\&/g')"

  if grep -q -E "^${key}=" .env; then
    sed -i "s#^${key}=.*#${key}=${escaped_value}#" .env
  else
    printf '\n%s=%s\n' "$key" "$value" >> .env
  fi
}

ensure_env_value() {
  local key="$1"
  local fallback="$2"

  if [ -n "$(get_env_value "${key}")" ]; then
    return
  fi

  set_env_value "${key}" "${fallback}"
}

is_placeholder_secret() {
  local value="$1"

  case "${value}" in
    ""|YOUR_*|REPLACE_WITH_*|change-this-*|masterKey|password)
      return 0
      ;;
    *)
      return 1
      ;;
  esac
}

sync_env_if_default() {
  local key="$1"
  local new_value="$2"
  local previous_default="$3"
  local current_value

  current_value="$(get_env_value "${key}")"

  if [ -z "${current_value}" ] || [ "${current_value}" = "${previous_default}" ]; then
    set_env_value "${key}" "${new_value}"
  fi
}

infer_previous_template_root() {
  local tracked_root frontend_domain

  tracked_root="$(get_env_value DOMAIN_TEMPLATE_ROOT)"
  if [ -n "${tracked_root}" ]; then
    printf '%s' "${tracked_root}"
    return
  fi

  frontend_domain="$(get_env_value FRONTEND_DOMAIN)"
  if [[ "${frontend_domain}" == hive.* ]]; then
    printf '%s' "${frontend_domain#hive.}"
    return
  fi

  printf '%s' "$(get_env_value ROOT_DOMAIN)"
}

apply_requested_overrides() {
  if [ -n "${REQUESTED_ROOT_DOMAIN}" ]; then
    set_env_value ROOT_DOMAIN "${REQUESTED_ROOT_DOMAIN}"
  fi

  if [ -n "${REQUESTED_SERVER_IP}" ]; then
    set_env_value SERVER_IP "${REQUESTED_SERVER_IP}"
  fi

  if [ -n "${REQUESTED_TLS_MODE}" ]; then
    set_env_value CADDY_TLS_MODE "${REQUESTED_TLS_MODE}"
  fi
}

ensure_app_key() {
  if [ -n "$(get_env_value APP_KEY)" ]; then
    return
  fi

  if ! command -v openssl >/dev/null 2>&1; then
    echo "APP_KEY is empty and openssl is unavailable. Set APP_KEY in .env before deploying." >&2
    exit 1
  fi

  set_env_value APP_KEY "base64:$(openssl rand -base64 32 | tr -d '\r\n')"
  echo "Generated missing APP_KEY in .env"
}

ensure_reverb_credentials() {
  local reverb_app_id reverb_app_key reverb_app_secret

  ensure_env_value BROADCAST_CONNECTION "reverb"

  reverb_app_id="$(get_env_value REVERB_APP_ID)"
  reverb_app_key="$(get_env_value REVERB_APP_KEY)"
  reverb_app_secret="$(get_env_value REVERB_APP_SECRET)"

  if [ -z "${reverb_app_id}" ]; then
    reverb_app_id="$(date +%s)"
    set_env_value REVERB_APP_ID "${reverb_app_id}"
  fi

  if [ -z "${reverb_app_key}" ] || is_placeholder_secret "${reverb_app_key}"; then
    reverb_app_key="$(openssl rand -hex 16)"
    set_env_value REVERB_APP_KEY "${reverb_app_key}"
  fi

  if [ -z "${reverb_app_secret}" ] || is_placeholder_secret "${reverb_app_secret}"; then
    reverb_app_secret="$(openssl rand -hex 32)"
    set_env_value REVERB_APP_SECRET "${reverb_app_secret}"
  fi

  set_env_value NEXT_PUBLIC_REVERB_APP_KEY "${reverb_app_key}"
}

ensure_meilisearch_key() {
  local key

  ensure_env_value SCOUT_DRIVER "meilisearch"
  key="$(get_env_value MEILISEARCH_KEY)"

  if [ -n "${key}" ] && ! is_placeholder_secret "${key}"; then
    return
  fi

  if ! command -v openssl >/dev/null 2>&1; then
    echo "MEILISEARCH_KEY is empty and openssl is unavailable. Set MEILISEARCH_KEY in .env before deploying." >&2
    exit 1
  fi

  set_env_value MEILISEARCH_KEY "$(openssl rand -hex 24)"
  echo "Generated missing MEILISEARCH_KEY in .env"
}

ensure_domain_defaults() {
  local root_domain previous_root_domain
  local frontend_domain backend_domain reverb_domain horizon_domain meilisearch_domain server_ip reverb_app_key
  local old_frontend_domain old_backend_domain old_reverb_domain old_horizon_domain old_meilisearch_domain
  local old_rembg_domain old_gotenberg_domain old_redis_domain old_db_domain
  local current_central_domains current_stateful_domains

  root_domain="$(get_env_value ROOT_DOMAIN)"

  if [ -z "${root_domain}" ]; then
    root_domain="techiveet.com"
    set_env_value ROOT_DOMAIN "${root_domain}"
  fi

  previous_root_domain="$(infer_previous_template_root)"
  if [ -z "${previous_root_domain}" ]; then
    previous_root_domain="${root_domain}"
  fi

  old_frontend_domain="hive.${previous_root_domain}"
  old_backend_domain="hive-backend.${previous_root_domain}"
  old_reverb_domain="hive-ws.${previous_root_domain}"
  old_horizon_domain="hive-queue.${previous_root_domain}"
  old_meilisearch_domain="hive-search.${previous_root_domain}"
  old_rembg_domain="hive-rembg.${previous_root_domain}"
  old_gotenberg_domain="hive-docs.${previous_root_domain}"
  old_redis_domain="hive-redis.${previous_root_domain}"
  old_db_domain="hive-db.${previous_root_domain}"

  sync_env_if_default FRONTEND_DOMAIN "hive.${root_domain}" "${old_frontend_domain}"
  sync_env_if_default BACKEND_DOMAIN "hive-backend.${root_domain}" "${old_backend_domain}"
  sync_env_if_default REVERB_DOMAIN "hive-ws.${root_domain}" "${old_reverb_domain}"
  sync_env_if_default HORIZON_DOMAIN "hive-queue.${root_domain}" "${old_horizon_domain}"
  sync_env_if_default MEILISEARCH_DOMAIN "hive-search.${root_domain}" "${old_meilisearch_domain}"
  sync_env_if_default REMBG_DOMAIN "hive-rembg.${root_domain}" "${old_rembg_domain}"
  sync_env_if_default GOTENBERG_DOMAIN "hive-docs.${root_domain}" "${old_gotenberg_domain}"
  sync_env_if_default REDIS_DOMAIN "hive-redis.${root_domain}" "${old_redis_domain}"
  sync_env_if_default DB_DOMAIN "hive-db.${root_domain}" "${old_db_domain}"

  ensure_env_value BACKEND_INTERNAL_URL "http://backend:8000"
  ensure_env_value FRONTEND_INTERNAL_URL "http://frontend:3000"
  ensure_env_value REVERB_INTERNAL_URL "http://reverb:9000"
  ensure_env_value QUEUE_INTERNAL_HOST "queue"
  ensure_env_value DB_INTERNAL_HOST "db"
  ensure_env_value REDIS_INTERNAL_HOST "redis"
  ensure_env_value MEILISEARCH_INTERNAL_URL "http://meilisearch:7700"
  ensure_env_value REMBG_INTERNAL_URL "http://rembg:5000"
  ensure_env_value GOTENBERG_INTERNAL_URL "http://gotenberg:3000"
  ensure_env_value FFMPEG_INTERNAL_URL "http://ffmpeg:9090"

  frontend_domain="$(get_env_value FRONTEND_DOMAIN)"
  backend_domain="$(get_env_value BACKEND_DOMAIN)"
  reverb_domain="$(get_env_value REVERB_DOMAIN)"
  horizon_domain="$(get_env_value HORIZON_DOMAIN)"
  meilisearch_domain="$(get_env_value MEILISEARCH_DOMAIN)"
  server_ip="$(get_env_value SERVER_IP)"

  sync_env_if_default APP_URL "https://${backend_domain}" "https://${old_backend_domain}"
  sync_env_if_default FRONTEND_URL "https://${frontend_domain}" "https://${old_frontend_domain}"

  current_central_domains="$(get_env_value TENANCY_CENTRAL_DOMAINS)"
  if [ -z "${current_central_domains}" ] || [ "${current_central_domains}" = "${old_frontend_domain},${old_backend_domain}" ] || [ "${current_central_domains}" = "${old_frontend_domain},${old_backend_domain},${old_horizon_domain}" ]; then
    set_env_value TENANCY_CENTRAL_DOMAINS "${frontend_domain},${backend_domain},${horizon_domain}"
  fi

  sync_env_if_default SESSION_DOMAIN ".${root_domain}" ".${previous_root_domain}"

  current_stateful_domains="$(get_env_value SANCTUM_STATEFUL_DOMAINS)"
  if [ -z "${current_stateful_domains}" ] || [ "${current_stateful_domains}" = "${old_frontend_domain},${old_backend_domain}" ] || [ "${current_stateful_domains}" = "${old_frontend_domain},${old_backend_domain},${old_horizon_domain}" ]; then
    set_env_value SANCTUM_STATEFUL_DOMAINS "${frontend_domain},${backend_domain},${horizon_domain}"
  fi

  sync_env_if_default CORS_ALLOWED_ORIGINS "https://${frontend_domain}" "https://${old_frontend_domain}"
  sync_env_if_default QUEUE_DASHBOARD_URL "https://${horizon_domain}/horizon" "https://${old_horizon_domain}/horizon"
  sync_env_if_default SEARCH_DASHBOARD_URL "https://${meilisearch_domain}" "https://${old_meilisearch_domain}"
  sync_env_if_default NEXT_PUBLIC_API_URL "https://${backend_domain}/api/v1" "https://${old_backend_domain}/api/v1"
  sync_env_if_default NEXT_PUBLIC_APP_URL "https://${frontend_domain}" "https://${old_frontend_domain}"
  sync_env_if_default NEXT_PUBLIC_CENTRAL_DOMAINS "${frontend_domain},${backend_domain}" "${old_frontend_domain},${old_backend_domain}"
  sync_env_if_default NEXT_PUBLIC_ROOT_DOMAIN "${root_domain}" "${previous_root_domain}"
  sync_env_if_default NEXT_PUBLIC_FRONTEND_DOMAIN "${frontend_domain}" "${old_frontend_domain}"
  sync_env_if_default NEXT_PUBLIC_BACKEND_DOMAIN "${backend_domain}" "${old_backend_domain}"
  sync_env_if_default NEXT_PUBLIC_REVERB_DOMAIN "${reverb_domain}" "${old_reverb_domain}"
  sync_env_if_default NEXT_PUBLIC_REVERB_HOST "${reverb_domain}" "${old_reverb_domain}"
  ensure_env_value NEXT_PUBLIC_REVERB_PORT "443"
  ensure_env_value NEXT_PUBLIC_REVERB_SCHEME "https"
  ensure_env_value INTERNAL_API_URL "http://backend:8000/api/v1"
  set_env_value NEXT_PUBLIC_SERVER_IP "${server_ip}"
  set_env_value DOMAIN_TEMPLATE_ROOT "${root_domain}"

  reverb_app_key="$(get_env_value REVERB_APP_KEY)"
  if [ -n "${reverb_app_key}" ]; then
    set_env_value NEXT_PUBLIC_REVERB_APP_KEY "${reverb_app_key}"
  fi
}

ensure_cloudflare_wildcard_tls() {
  local root_domain cf_api_token

  root_domain="$(get_env_value ROOT_DOMAIN)"
  cf_api_token="$(get_env_value CF_API_TOKEN)"

  if [ -z "${cf_api_token}" ]; then
    cf_api_token="$(get_env_value CLOUDFLARE_API_TOKEN)"
    if [ -n "${cf_api_token}" ]; then
      set_env_value CF_API_TOKEN "${cf_api_token}"
    fi
  fi

  if [ -z "$(get_env_value CF_API_TOKEN)" ] || is_placeholder_secret "$(get_env_value CF_API_TOKEN)"; then
    echo "CF_API_TOKEN is required for Cloudflare wildcard TLS for ${root_domain}." >&2
    exit 1
  fi
}

configure_caddy_runtime() {
  local tls_mode runtime_dir source_file cf_token

  tls_mode="$(get_env_value CADDY_TLS_MODE)"
  cf_token="$(get_env_value CF_API_TOKEN)"

  if [ -z "${tls_mode}" ] || [ "${tls_mode}" = "auto" ]; then
    if [ -n "${cf_token}" ] && ! is_placeholder_secret "${cf_token}"; then
      tls_mode="cloudflare"
    else
      tls_mode="on_demand"
    fi
  fi

  case "${tls_mode}" in
    cloudflare)
      ensure_cloudflare_wildcard_tls
      source_file="Caddyfile.cloudflare"
      ;;
    on_demand)
      source_file="Caddyfile"
      ;;
    *)
      echo "Unsupported CADDY_TLS_MODE '${tls_mode}'. Use 'on_demand', 'cloudflare', or auto." >&2
      exit 1
      ;;
  esac

  if [ ! -f "${source_file}" ]; then
    echo "Missing ${source_file}. Cannot configure Caddy." >&2
    exit 1
  fi

  runtime_dir="storage/caddy_runtime"
  mkdir -p "${runtime_dir}" storage/caddy_data storage/caddy_config
  rm -rf "${runtime_dir}/Caddyfile"
  cp "${source_file}" "${runtime_dir}/Caddyfile"
  test -f "${runtime_dir}/Caddyfile"

  echo "Configured Caddy TLS mode: ${tls_mode}"
}

validate_build_contexts() {
  local missing=0

  for path in \
    "Dockerfile.caddy" \
    "backend/Dockerfile.prod" \
    "frontend/Dockerfile.prod" \
    "ffmpeg-api/Dockerfile"
  do
    if [ ! -f "${path}" ]; then
      echo "Missing required build file: ${path}" >&2
      missing=1
    fi
  done

  for dir in backend frontend ffmpeg-api storage; do
    if [ ! -d "${dir}" ]; then
      echo "Missing required directory: ${dir}" >&2
      missing=1
    fi
  done

  if [ "${missing}" -eq 1 ]; then
    echo "Docker Compose build cannot continue because required production files are missing." >&2
    exit 1
  fi
}

validate_compose_config() {
  echo "Validating Docker Compose config..."
  compose config >/tmp/hive-compose-config.yml
}

wait_for_service_healthy() {
  local service="$1"
  local attempt=0
  local status=""
  local container_id=""

  while true; do
    container_id="$(compose ps -q "${service}" 2>/dev/null | head -n 1 || true)"

    if [ -n "${container_id}" ]; then
      status="$(docker inspect --format='{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "${container_id}" 2>/dev/null || true)"
    else
      status="missing"
    fi

    if [ "${status}" = "healthy" ] || [ "${status}" = "running" ]; then
      return
    fi

    attempt=$((attempt + 1))
    if [ "${attempt}" -ge 36 ]; then
      echo "Service ${service} did not become healthy. Last status: ${status}" >&2
      compose logs --tail=150 "${service}" >&2 || true
      exit 1
    fi

    sleep 5
  done
}

wait_for_meilisearch() {
  local attempt=0

  until compose exec -T meilisearch curl -fsS http://127.0.0.1:7700/health >/dev/null 2>&1; do
    attempt=$((attempt + 1))
    if [ "${attempt}" -ge 24 ]; then
      echo "Meilisearch did not become ready in time." >&2
      exit 1
    fi
    sleep 5
  done
}

wait_for_horizon() {
  local attempt=0
  local status_output=""

  until status_output="$(compose exec -T backend php artisan horizon:status 2>&1)" && echo "${status_output}" | grep -qi "running"; do
    attempt=$((attempt + 1))

    if [ "${attempt}" -ge 24 ]; then
      echo "Horizon did not report a running state in time." >&2
      echo "${status_output}" >&2
      compose logs --tail=150 queue >&2 || true
      exit 1
    fi

    sleep 5
  done

  echo "${status_output}"
}

is_meilisearch_enabled() {
  [ "$(get_env_value SCOUT_DRIVER)" = "meilisearch" ]
}

if [ ! -f "${COMPOSE_FILE}" ]; then
  echo "Missing ${COMPOSE_FILE} in $(pwd)." >&2
  exit 1
fi

if [ ! -f ".env" ]; then
  echo "Missing .env in $(pwd)." >&2
  echo "Copy .env.prod-example to .env and fill in the production secrets before deploying." >&2
  exit 1
fi

apply_requested_overrides
cleanup_stale_recreate_containers
ensure_disk_space_for_build
ensure_app_key
ensure_reverb_credentials
ensure_meilisearch_key
ensure_domain_defaults
configure_caddy_runtime
validate_build_contexts
validate_compose_config

compose build --progress plain caddy backend queue reverb frontend ffmpeg

compose up -d --remove-orphans redis db seaweedfs seaweedfs-bootstrap meilisearch rembg gotenberg ffmpeg
wait_for_service_healthy redis
wait_for_service_healthy db
wait_for_service_healthy meilisearch
wait_for_service_healthy ffmpeg

compose up -d backend
wait_for_service_healthy backend

compose exec -T backend php artisan storage:link || true
compose exec -T backend php artisan optimize:clear
compose exec -T backend php artisan migrate --force
compose exec -T backend php artisan tenants:migrate --force
compose exec -T backend php artisan hive:sync-system-access --force

if [ "${SKIP_FALLBACK_DOMAIN_SYNC}" -eq 0 ]; then
  compose exec -T backend php artisan hive:sync-fallback-domains
fi

compose exec -T backend php artisan config:cache
compose exec -T backend php artisan view:cache

if is_meilisearch_enabled; then
  wait_for_meilisearch
  compose exec -T backend php artisan scout:import-all
fi

compose up -d queue reverb frontend caddy
wait_for_horizon
compose exec -T caddy caddy validate --config /etc/caddy/Caddyfile

compose ps
