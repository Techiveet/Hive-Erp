#!/usr/bin/env bash

set -Eeuo pipefail

COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.prod.yml}"
LOG_SERVICES="${LOG_SERVICES:-caddy frontend backend queue reverb db redis meilisearch}"

compose() {
  docker compose -f "${COMPOSE_FILE}" "$@"
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

compose build
compose up -d --remove-orphans

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

compose ps
