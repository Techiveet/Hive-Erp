#!/usr/bin/env bash

set -Eeuo pipefail

COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.prod.yml}"
LOG_SERVICES="${LOG_SERVICES:-caddy frontend backend queue reverb db redis meilisearch}"
REQUESTED_ROOT_DOMAIN=""
REQUESTED_SERVER_IP=""
REQUESTED_TLS_MODE=""
SKIP_FALLBACK_DOMAIN_SYNC=0

print_usage() {
  cat <<'EOF'
Usage: bash scripts/deploy-prod.sh [options]

Options:
  --root-domain DOMAIN           Update ROOT_DOMAIN and refresh default derived hostnames.
  --server-ip IP                 Update SERVER_IP and refresh public DNS hints in the UI.
  --tls-mode MODE                Set CADDY_TLS_MODE to on_demand, cloudflare, or auto.
  --skip-fallback-domain-sync    Skip syncing generated tenant fallback domains after deploy.
  -h, --help                     Show this help message.

Examples:
  bash scripts/deploy-prod.sh
  bash scripts/deploy-prod.sh --root-domain gulfingot.com --server-ip 49.13.211.106
  bash scripts/deploy-prod.sh --tls-mode on_demand
EOF
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

compose() {
  docker compose -f "${COMPOSE_FILE}" "$@"
}

get_env_value() {
  local key="$1"
  local line

  line="$(grep -E "^${key}=" .env | tail -n 1 || true)"
  printf '%s' "${line#*=}"
}

ensure_env_value() {
  local key="$1"
  local fallback="$2"
  local current_value

  current_value="$(get_env_value "${key}")"

  if [ -n "${current_value}" ]; then
    return
  fi

  set_env_value "${key}" "${fallback}"
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
  local current_value
  current_value="$(get_env_value APP_KEY)"

  if [ -n "${current_value}" ]; then
    return
  fi

  if ! command -v openssl >/dev/null 2>&1; then
    echo "APP_KEY is empty and openssl is unavailable. Set APP_KEY in .env before deploying." >&2
    exit 1
  fi

  local generated_key
  generated_key="base64:$(openssl rand -base64 32 | tr -d '\r\n')"
  set_env_value APP_KEY "${generated_key}"
  echo "Generated missing APP_KEY in .env"
}

ensure_reverb_credentials() {
  local reverb_app_id reverb_app_key reverb_app_secret

  ensure_env_value BROADCAST_CONNECTION "reverb"

  reverb_app_id="$(get_env_value REVERB_APP_ID)"
  reverb_app_key="$(get_env_value REVERB_APP_KEY)"
  reverb_app_secret="$(get_env_value REVERB_APP_SECRET)"

  if [ -n "${reverb_app_id}" ] && [ -n "${reverb_app_key}" ] && [ -n "${reverb_app_secret}" ]; then
    return
  fi

  if ! command -v openssl >/dev/null 2>&1; then
    echo "Reverb credentials are incomplete and openssl is unavailable. Set REVERB_APP_ID, REVERB_APP_KEY, and REVERB_APP_SECRET in .env before deploying." >&2
    exit 1
  fi

  if [ -z "${reverb_app_id}" ]; then
    reverb_app_id="$(date +%s)"
    set_env_value REVERB_APP_ID "${reverb_app_id}"
  fi

  if [ -z "${reverb_app_key}" ]; then
    reverb_app_key="$(openssl rand -hex 16)"
    set_env_value REVERB_APP_KEY "${reverb_app_key}"
  fi

  if [ -z "${reverb_app_secret}" ]; then
    reverb_app_secret="$(openssl rand -hex 32)"
    set_env_value REVERB_APP_SECRET "${reverb_app_secret}"
  fi

  set_env_value NEXT_PUBLIC_REVERB_APP_KEY "${reverb_app_key}"
  echo "Generated missing Reverb credentials in .env"
}

ensure_domain_defaults() {
  local root_domain previous_root_domain
  local frontend_domain backend_domain reverb_domain reverb_app_key server_ip
  local old_frontend_domain old_backend_domain old_reverb_domain old_horizon_domain
  local old_meilisearch_domain old_rembg_domain old_gotenberg_domain old_redis_domain old_db_domain

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

  frontend_domain="$(get_env_value FRONTEND_DOMAIN)"
  backend_domain="$(get_env_value BACKEND_DOMAIN)"
  reverb_domain="$(get_env_value REVERB_DOMAIN)"
  server_ip="$(get_env_value SERVER_IP)"

  sync_env_if_default APP_URL "https://${backend_domain}" "https://${old_backend_domain}"
  sync_env_if_default FRONTEND_URL "https://${frontend_domain}" "https://${old_frontend_domain}"
  sync_env_if_default TENANCY_CENTRAL_DOMAINS "${frontend_domain},${backend_domain}" "${old_frontend_domain},${old_backend_domain}"
  sync_env_if_default SESSION_DOMAIN ".${root_domain}" ".${previous_root_domain}"
  sync_env_if_default SANCTUM_STATEFUL_DOMAINS "${frontend_domain},${backend_domain}" "${old_frontend_domain},${old_backend_domain}"
  sync_env_if_default CORS_ALLOWED_ORIGINS "https://${frontend_domain}" "https://${old_frontend_domain}"

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

  if [ -n "${reverb_app_key}" ] && [ "$(get_env_value NEXT_PUBLIC_REVERB_APP_KEY)" != "${reverb_app_key}" ]; then
    set_env_value NEXT_PUBLIC_REVERB_APP_KEY "${reverb_app_key}"
  fi
}

ensure_cloudflare_wildcard_tls() {
  local root_domain cf_api_token

  root_domain="$(get_env_value ROOT_DOMAIN)"

  case "${root_domain}" in
    ""|"localhost"|"127.0.0.1")
      return
      ;;
  esac

  cf_api_token="$(get_env_value CF_API_TOKEN)"

  if [ -z "${cf_api_token}" ]; then
    cf_api_token="$(get_env_value CLOUDFLARE_API_TOKEN)"

    if [ -n "${cf_api_token}" ]; then
      set_env_value CF_API_TOKEN "${cf_api_token}"
    fi
  fi

  if [ -z "$(get_env_value CF_API_TOKEN)" ]; then
    echo "CF_API_TOKEN is required for Cloudflare wildcard TLS." >&2
    echo "Create a scoped Cloudflare token with Zone.Zone:Read and Zone.DNS:Edit for ${root_domain}, then set CF_API_TOKEN in .env before deploying." >&2
    exit 1
  fi
}

configure_caddy_runtime() {
  local tls_mode runtime_dir source_file

  tls_mode="$(get_env_value CADDY_TLS_MODE)"

  if [ -z "${tls_mode}" ] || [ "${tls_mode}" = "auto" ]; then
    if [ -n "$(get_env_value CF_API_TOKEN)" ] || [ -n "$(get_env_value CLOUDFLARE_API_TOKEN)" ]; then
      tls_mode="cloudflare"
    else
      tls_mode="on_demand"
    fi
  fi

  runtime_dir="storage/caddy_runtime"
  mkdir -p "${runtime_dir}"

  case "${tls_mode}" in
    cloudflare)
      ensure_cloudflare_wildcard_tls
      source_file="Caddyfile.cloudflare"
      ;;
    on_demand)
      source_file="Caddyfile"
      ;;
    *)
      echo "Unsupported CADDY_TLS_MODE '${tls_mode}'. Use 'on_demand', 'cloudflare', or leave it empty for auto." >&2
      exit 1
      ;;
  esac

  cp "${source_file}" "${runtime_dir}/Caddyfile"
  echo "Configured Caddy TLS mode: ${tls_mode}"
}

show_failure_context() {
  echo "Deployment failed. Current compose status:" >&2
  compose ps || true

  echo "Recent container logs:" >&2
  compose logs --tail=200 ${LOG_SERVICES} || true
}

trap show_failure_context ERR

if [ ! -f ".env" ]; then
  echo "Missing .env in $(pwd)." >&2
  echo "Copy .env.prod-example to .env and fill in the production secrets before deploying." >&2
  exit 1
fi

apply_requested_overrides
ensure_app_key
ensure_reverb_credentials
ensure_domain_defaults
configure_caddy_runtime

compose build
compose up -d --remove-orphans redis db meilisearch rembg gotenberg
compose up -d backend

attempt=0
until compose exec -T backend php artisan about >/dev/null 2>&1; do
  attempt=$((attempt + 1))

  if [ "${attempt}" -ge 24 ]; then
    echo "Backend container did not become ready in time." >&2
    exit 1
  fi

  sleep 5
done

compose exec -T backend php artisan storage:link || true
compose exec -T backend php artisan optimize:clear
compose exec -T backend php artisan migrate --force
if [ "${SKIP_FALLBACK_DOMAIN_SYNC}" -eq 0 ]; then
  compose exec -T backend php artisan hive:sync-fallback-domains
fi
compose exec -T backend php artisan config:cache
compose exec -T backend php artisan view:cache
compose up -d queue reverb frontend caddy

compose ps
