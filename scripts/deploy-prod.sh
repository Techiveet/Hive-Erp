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

set_env_value() {
  local key="$1"
  local value="$2"

  if grep -q -E "^${key}=" .env; then
    sed -i "s#^${key}=.*#${key}=${value}#" .env
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
compose exec -T backend php artisan optimize
compose up -d queue reverb frontend caddy

compose ps
