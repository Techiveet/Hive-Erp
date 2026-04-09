#!/usr/bin/env bash

set -Eeuo pipefail

COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.prod.yml}"
LOG_SERVICES="${LOG_SERVICES:-caddy frontend backend queue reverb db redis meilisearch}"

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

ensure_domain_defaults() {
  local root_domain frontend_domain backend_domain reverb_domain reverb_app_key

  root_domain="$(get_env_value ROOT_DOMAIN)"

  if [ -z "${root_domain}" ]; then
    root_domain="techiveet.com"
    set_env_value ROOT_DOMAIN "${root_domain}"
  fi

  ensure_env_value FRONTEND_DOMAIN "hive.${root_domain}"
  ensure_env_value BACKEND_DOMAIN "hive-backend.${root_domain}"
  ensure_env_value REVERB_DOMAIN "hive-ws.${root_domain}"
  ensure_env_value HORIZON_DOMAIN "hive-queue.${root_domain}"
  ensure_env_value MEILISEARCH_DOMAIN "hive-search.${root_domain}"
  ensure_env_value REMBG_DOMAIN "hive-rembg.${root_domain}"
  ensure_env_value GOTENBERG_DOMAIN "hive-docs.${root_domain}"
  ensure_env_value REDIS_DOMAIN "hive-redis.${root_domain}"
  ensure_env_value DB_DOMAIN "hive-db.${root_domain}"

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

  ensure_env_value APP_URL "https://${backend_domain}"
  ensure_env_value FRONTEND_URL "https://${frontend_domain}"
  ensure_env_value TENANCY_CENTRAL_DOMAINS "${frontend_domain},${backend_domain}"
  ensure_env_value SESSION_DOMAIN ".${root_domain}"
  ensure_env_value SANCTUM_STATEFUL_DOMAINS "${frontend_domain},${backend_domain}"
  ensure_env_value CORS_ALLOWED_ORIGINS "https://${frontend_domain}"

  ensure_env_value NEXT_PUBLIC_API_URL "https://${backend_domain}/api/v1"
  ensure_env_value NEXT_PUBLIC_APP_URL "https://${frontend_domain}"
  ensure_env_value NEXT_PUBLIC_CENTRAL_DOMAINS "${frontend_domain},${backend_domain}"
  ensure_env_value NEXT_PUBLIC_ROOT_DOMAIN "${root_domain}"
  ensure_env_value NEXT_PUBLIC_FRONTEND_DOMAIN "${frontend_domain}"
  ensure_env_value NEXT_PUBLIC_BACKEND_DOMAIN "${backend_domain}"
  ensure_env_value NEXT_PUBLIC_REVERB_DOMAIN "${reverb_domain}"
  ensure_env_value NEXT_PUBLIC_REVERB_HOST "${reverb_domain}"
  ensure_env_value NEXT_PUBLIC_REVERB_PORT "443"
  ensure_env_value NEXT_PUBLIC_REVERB_SCHEME "https"
  ensure_env_value INTERNAL_API_URL "http://backend:8000/api/v1"

  reverb_app_key="$(get_env_value REVERB_APP_KEY)"

  if [ -n "${reverb_app_key}" ] && [ -z "$(get_env_value NEXT_PUBLIC_REVERB_APP_KEY)" ]; then
    set_env_value NEXT_PUBLIC_REVERB_APP_KEY "${reverb_app_key}"
  fi
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

ensure_app_key
ensure_domain_defaults

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
compose exec -T backend php artisan config:cache
compose exec -T backend php artisan view:cache
compose up -d queue reverb frontend caddy

compose ps
