#!/usr/bin/env bash

set -Eeuo pipefail

if [ "${TRACE_DEPLOY:-0}" = "1" ]; then
  set -x
fi

COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.prod.yml}"
MIN_FREE_DISK_MB="${MIN_FREE_DISK_MB:-5120}"
RUN_SCOUT_IMPORT="${RUN_SCOUT_IMPORT:-0}"
SKIP_FALLBACK_DOMAIN_SYNC="${SKIP_FALLBACK_DOMAIN_SYNC:-0}"
PRUNE_DOCKER_BEFORE_DEPLOY="${PRUNE_DOCKER_BEFORE_DEPLOY:-1}"
DEPLOY_STEP="initializing"
DEPLOY_COMPOSE_COMMAND=""

compose() {
  DEPLOY_COMPOSE_COMMAND="docker compose -f ${COMPOSE_FILE} $*"
  echo "Running: ${DEPLOY_COMPOSE_COMMAND}" >&2
  docker compose -f "${COMPOSE_FILE}" "$@"
}

build_image() {
  local service="$1"

  DEPLOY_STEP="Building production image: ${service}"
  echo "Building production image: ${service}"
  compose build --progress plain "${service}"
}

log_service() {
  local service="$1"
  local lines="${2:-100}"

  echo "${service} logs:" >&2
  compose logs --tail="${lines}" "${service}" >&2 || true
}

fail() {
  local code="$1"
  local line="$2"
  local cmd="$3"
  local failed_step="${DEPLOY_STEP:-unknown}"
  local failed_compose_command="${DEPLOY_COMPOSE_COMMAND:-}"

  set +e

  echo "Deployment step: ${failed_step}" >&2
  if [ -n "${failed_compose_command}" ]; then
    echo "Last Docker Compose command: ${failed_compose_command}" >&2
  fi
  echo "Deployment failed at line ${line}: ${cmd}" >&2
  echo "Exit code: ${code}" >&2
  echo "Working directory: $(pwd)" >&2

  echo "Docker disk usage:" >&2
  docker system df >&2 || true

  echo "Compose status:" >&2
  compose ps -a >&2 || true

  for service in backend frontend queue reverb caddy ffmpeg seaweedfs-bootstrap meilisearch; do
    log_service "${service}" 100
  done

  echo "Deployment step: ${failed_step}" >&2
  if [ -n "${failed_compose_command}" ]; then
    echo "Last Docker Compose command: ${failed_compose_command}" >&2
  fi
  echo "Deployment failed at line ${line}: ${cmd}" >&2
  echo "Exit code: ${code}" >&2

  exit "${code}"
}

trap 'fail "$?" "$LINENO" "$BASH_COMMAND"' ERR

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
    printf '\n%s=%s\n' "${key}" "${value}" >> .env
  fi
}

ensure_env_value() {
  local key="$1"
  local fallback="$2"

  if [ -z "$(get_env_value "${key}")" ]; then
    set_env_value "${key}" "${fallback}"
  fi
}

ensure_env_not_value() {
  local key="$1"
  local blocked="$2"
  local fallback="$3"

  if [ "$(get_env_value "${key}")" = "${blocked}" ]; then
    set_env_value "${key}" "${fallback}"
  fi
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

free_disk_mb() {
  df -Pm . | awk 'NR==2 {print $4}'
}

prune_unused_docker_resources() {
  docker builder prune -af || true
  docker image prune -af || true
  docker container prune -f || true
}

ensure_disk_space() {
  local free_mb
  free_mb="$(free_disk_mb)"

  echo "Free disk before deploy: ${free_mb} MB"

  if [ "${PRUNE_DOCKER_BEFORE_DEPLOY}" = "1" ]; then
    echo "Pruning unused Docker resources before image builds..."
    prune_unused_docker_resources
  elif [ "${free_mb}" -lt "${MIN_FREE_DISK_MB}" ]; then
    echo "Low disk space. Pruning unused Docker resources..."
    prune_unused_docker_resources
  fi

  free_mb="$(free_disk_mb)"
  echo "Free disk after preflight: ${free_mb} MB"

  if [ "${free_mb}" -lt "${MIN_FREE_DISK_MB}" ]; then
    echo "Insufficient disk space. Need ${MIN_FREE_DISK_MB} MB, found ${free_mb} MB." >&2
    docker system df >&2 || true
    exit 1
  fi
}

ensure_post_build_disk_space() {
  local free_mb
  free_mb="$(free_disk_mb)"

  echo "Free disk after image builds: ${free_mb} MB"

  if [ "${free_mb}" -lt "${MIN_FREE_DISK_MB}" ]; then
    echo "Low disk space after image builds. Pruning Docker build cache..."
    docker builder prune -af || true
  fi

  free_mb="$(free_disk_mb)"
  echo "Free disk after post-build cache prune: ${free_mb} MB"

  if [ "${free_mb}" -lt "${MIN_FREE_DISK_MB}" ]; then
    echo "Insufficient disk space after image builds. Need ${MIN_FREE_DISK_MB} MB, found ${free_mb} MB." >&2
    echo "Docker build cache was pruned, but deploy still needs more free disk before services restart." >&2
    docker system df >&2 || true
    exit 1
  fi
}

ensure_required_files() {
  local missing=0

  for file in \
    ".env" \
    "${COMPOSE_FILE}" \
    "Dockerfile.caddy" \
    "Caddyfile" \
    "backend/Dockerfile.prod" \
    "frontend/Dockerfile.prod" \
    "ffmpeg-api/Dockerfile"
  do
    if [ ! -f "${file}" ]; then
      echo "Missing required file: ${file}" >&2
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
    exit 1
  fi
}

ensure_runtime_env() {
  ensure_env_value ROOT_DOMAIN "techiveet.com"
  ensure_env_value FRONTEND_DOMAIN "hive.$(get_env_value ROOT_DOMAIN)"
  ensure_env_value BACKEND_DOMAIN "hive-backend.$(get_env_value ROOT_DOMAIN)"
  ensure_env_value REVERB_DOMAIN "hive-ws.$(get_env_value ROOT_DOMAIN)"
  ensure_env_value HORIZON_DOMAIN "hive-queue.$(get_env_value ROOT_DOMAIN)"
  ensure_env_value MEILISEARCH_DOMAIN "hive-search.$(get_env_value ROOT_DOMAIN)"
  ensure_env_value REMBG_DOMAIN "hive-rembg.$(get_env_value ROOT_DOMAIN)"
  ensure_env_value GOTENBERG_DOMAIN "hive-docs.$(get_env_value ROOT_DOMAIN)"

  ensure_env_value BACKEND_INTERNAL_URL "http://backend:8000"
  ensure_env_value FRONTEND_INTERNAL_URL "http://frontend:3000"
  ensure_env_value REVERB_INTERNAL_URL "http://reverb:9000"
  ensure_env_value DB_INTERNAL_HOST "db"
  ensure_env_value REDIS_INTERNAL_HOST "redis"
  ensure_env_value REDIS_CLIENT "predis"
  ensure_env_not_value REDIS_CLIENT "phpredis" "predis"
  ensure_env_value MEILISEARCH_INTERNAL_URL "http://meilisearch:7700"
  ensure_env_value REMBG_INTERNAL_URL "http://rembg:5000"
  ensure_env_value GOTENBERG_INTERNAL_URL "http://gotenberg:3000"
  ensure_env_value FFMPEG_INTERNAL_URL "http://ffmpeg:9090"

  ensure_env_value APP_URL "https://$(get_env_value BACKEND_DOMAIN)"
  ensure_env_value FRONTEND_URL "https://$(get_env_value FRONTEND_DOMAIN)"
  ensure_env_value NEXT_PUBLIC_API_URL "https://$(get_env_value BACKEND_DOMAIN)/api/v1"
  ensure_env_value NEXT_PUBLIC_APP_URL "https://$(get_env_value FRONTEND_DOMAIN)"
  ensure_env_value NEXT_PUBLIC_ROOT_DOMAIN "$(get_env_value ROOT_DOMAIN)"
  ensure_env_value NEXT_PUBLIC_FRONTEND_DOMAIN "$(get_env_value FRONTEND_DOMAIN)"
  ensure_env_value NEXT_PUBLIC_BACKEND_DOMAIN "$(get_env_value BACKEND_DOMAIN)"
  ensure_env_value NEXT_PUBLIC_REVERB_DOMAIN "$(get_env_value REVERB_DOMAIN)"
  ensure_env_value NEXT_PUBLIC_REVERB_HOST "$(get_env_value REVERB_DOMAIN)"
  ensure_env_value NEXT_PUBLIC_REVERB_PORT "443"
  ensure_env_value NEXT_PUBLIC_REVERB_SCHEME "https"
  ensure_env_value INTERNAL_API_URL "http://backend:8000/api/v1"

  if [ -z "$(get_env_value TENANCY_CENTRAL_DOMAINS)" ]; then
    set_env_value TENANCY_CENTRAL_DOMAINS "$(get_env_value FRONTEND_DOMAIN),$(get_env_value BACKEND_DOMAIN),$(get_env_value HORIZON_DOMAIN)"
  fi

  if [ -z "$(get_env_value SANCTUM_STATEFUL_DOMAINS)" ]; then
    set_env_value SANCTUM_STATEFUL_DOMAINS "$(get_env_value FRONTEND_DOMAIN),$(get_env_value BACKEND_DOMAIN),$(get_env_value HORIZON_DOMAIN)"
  fi

  if [ -z "$(get_env_value SESSION_DOMAIN)" ]; then
    set_env_value SESSION_DOMAIN ".$(get_env_value ROOT_DOMAIN)"
  fi

  if [ -z "$(get_env_value APP_KEY)" ]; then
    set_env_value APP_KEY "base64:$(openssl rand -base64 32 | tr -d '\r\n')"
  fi

  local reverb_key
  reverb_key="$(get_env_value REVERB_APP_KEY)"

  if is_placeholder_secret "${reverb_key}"; then
    reverb_key="$(openssl rand -hex 16)"
    set_env_value REVERB_APP_KEY "${reverb_key}"
  fi

  if is_placeholder_secret "$(get_env_value REVERB_APP_SECRET)"; then
    set_env_value REVERB_APP_SECRET "$(openssl rand -hex 32)"
  fi

  ensure_env_value REVERB_APP_ID "$(date +%s)"
  set_env_value NEXT_PUBLIC_REVERB_APP_KEY "${reverb_key}"

  if is_placeholder_secret "$(get_env_value MEILISEARCH_KEY)"; then
    set_env_value MEILISEARCH_KEY "$(openssl rand -hex 24)"
  fi
}

configure_caddy_runtime() {
  local tls_mode
  local cf_token
  local source_file

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
      if is_placeholder_secret "${cf_token}"; then
        echo "CF_API_TOKEN is required for cloudflare TLS mode." >&2
        exit 1
      fi
      source_file="Caddyfile.cloudflare"
      ;;
    on_demand)
      source_file="Caddyfile"
      ;;
    *)
      echo "Unsupported CADDY_TLS_MODE=${tls_mode}. Use on_demand, cloudflare, or auto." >&2
      exit 1
      ;;
  esac

  if [ ! -f "${source_file}" ]; then
    echo "Missing ${source_file}" >&2
    exit 1
  fi

  mkdir -p storage/caddy_runtime storage/caddy_data storage/caddy_config
  rm -rf storage/caddy_runtime/Caddyfile
  cp "${source_file}" storage/caddy_runtime/Caddyfile
  test -f storage/caddy_runtime/Caddyfile

  echo "Configured Caddy TLS mode: ${tls_mode}"
}

wait_for_service() {
  local service="$1"
  local attempts="${2:-36}"
  local attempt=0
  local container_id=""
  local status=""

  while true; do
    container_id="$(compose ps -q "${service}" 2>/dev/null | head -n 1 || true)"

    if [ -n "${container_id}" ]; then
      status="$(docker inspect --format='{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "${container_id}" 2>/dev/null || true)"
    else
      status="missing"
    fi

    if [ "${status}" = "healthy" ] || [ "${status}" = "running" ]; then
      echo "${service} is ${status}"
      return
    fi

    attempt=$((attempt + 1))

    if [ "${attempt}" -ge "${attempts}" ]; then
      echo "${service} did not become ready. Last status: ${status}" >&2
      compose logs --tail=150 "${service}" >&2 || true
      exit 1
    fi

    sleep 5
  done
}

wait_for_completed_service() {
  local service="$1"
  local attempts="${2:-36}"
  local attempt=0
  local container_id=""
  local status=""
  local exit_code=""

  while true; do
    container_id="$(compose ps -q "${service}" 2>/dev/null | head -n 1 || true)"

    if [ -n "${container_id}" ]; then
      status="$(docker inspect --format='{{.State.Status}}' "${container_id}" 2>/dev/null || true)"
      exit_code="$(docker inspect --format='{{.State.ExitCode}}' "${container_id}" 2>/dev/null || true)"
    else
      status="missing"
      exit_code=""
    fi

    if [ "${status}" = "exited" ] && [ "${exit_code}" = "0" ]; then
      echo "${service} completed successfully"
      return
    fi

    if [ "${status}" = "exited" ] && [ "${exit_code}" != "0" ]; then
      echo "${service} failed with exit code ${exit_code}" >&2
      compose logs --tail=150 "${service}" >&2 || true
      exit 1
    fi

    attempt=$((attempt + 1))

    if [ "${attempt}" -ge "${attempts}" ]; then
      echo "${service} did not complete. Last status: ${status}, exit code: ${exit_code}" >&2
      compose logs --tail=150 "${service}" >&2 || true
      exit 1
    fi

    sleep 5
  done
}

ensure_required_files
ensure_disk_space
ensure_runtime_env
configure_caddy_runtime

echo "Validating Docker Compose config..."
DEPLOY_STEP="Validating Docker Compose config"
compose config >/tmp/hive-compose-config.yml

echo "Building production images..."
for service in caddy backend frontend ffmpeg; do
  build_image "${service}"
done
DEPLOY_STEP="Checking free disk after image builds"
ensure_post_build_disk_space

echo "Starting dependencies..."
DEPLOY_STEP="Starting dependency services"
compose up -d --remove-orphans redis db seaweedfs seaweedfs-bootstrap meilisearch rembg gotenberg ffmpeg

DEPLOY_STEP="Waiting for redis"
wait_for_service redis
DEPLOY_STEP="Waiting for database"
wait_for_service db
DEPLOY_STEP="Waiting for meilisearch"
wait_for_service meilisearch
DEPLOY_STEP="Waiting for ffmpeg"
wait_for_service ffmpeg
DEPLOY_STEP="Waiting for object storage bootstrap"
wait_for_completed_service seaweedfs-bootstrap

echo "Starting backend..."
DEPLOY_STEP="Starting backend"
compose up -d backend
DEPLOY_STEP="Waiting for backend"
wait_for_service backend

echo "Running Laravel deploy commands..."
DEPLOY_STEP="Linking Laravel storage"
compose exec -T backend php artisan storage:link || true
DEPLOY_STEP="Clearing Laravel caches"
compose exec -T backend php artisan optimize:clear
DEPLOY_STEP="Running central migrations"
compose exec -T backend php artisan migrate --force
DEPLOY_STEP="Running tenant migrations"
compose exec -T backend php artisan tenants:migrate --force
DEPLOY_STEP="Syncing system access"
compose exec -T backend php artisan hive:sync-system-access --force

if [ "${SKIP_FALLBACK_DOMAIN_SYNC}" -eq 0 ]; then
  DEPLOY_STEP="Syncing fallback domains"
  compose exec -T backend php artisan hive:sync-fallback-domains
fi

DEPLOY_STEP="Caching Laravel config"
compose exec -T backend php artisan config:cache
DEPLOY_STEP="Caching Laravel views"
compose exec -T backend php artisan view:cache

if [ "$(get_env_value SCOUT_DRIVER)" = "meilisearch" ]; then
  echo "Meilisearch is enabled."

  if [ "${RUN_SCOUT_IMPORT}" = "1" ]; then
    DEPLOY_STEP="Importing Scout indexes"
    compose exec -T backend php artisan scout:import-all
  else
    echo "Skipping scout:import-all. Set RUN_SCOUT_IMPORT=1 to run it."
  fi
fi

echo "Starting app services..."
DEPLOY_STEP="Starting app services"
compose up -d queue reverb frontend caddy

DEPLOY_STEP="Waiting for queue"
wait_for_service queue
DEPLOY_STEP="Waiting for reverb"
wait_for_service reverb
DEPLOY_STEP="Waiting for frontend"
wait_for_service frontend
DEPLOY_STEP="Waiting for caddy"
wait_for_service caddy

DEPLOY_STEP="Validating Caddy config"
compose exec -T caddy caddy validate --config /etc/caddy/Caddyfile

DEPLOY_STEP="Listing Compose services"
compose ps

echo "Deployment completed successfully."
