#!/usr/bin/env bash
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