#!/bin/sh
set -eu

APP_DIR=/app

if [ ! -f "$APP_DIR/package.json" ]; then
  echo "package.json not found in $APP_DIR" >&2
  exit 1
fi

NODE_ENV="${NODE_ENV:-production}"

if [ "$NODE_ENV" = "production" ]; then
  exec node server.js
else
  cd "$APP_DIR"

  NODE_MODULES_DIR="$APP_DIR/node_modules"
  LOCKFILE="$APP_DIR/package-lock.json"
  STAMP_FILE="$NODE_MODULES_DIR/.package-lock.sha256"

  mkdir -p "$NODE_MODULES_DIR"

  current_hash="$(sha256sum "$LOCKFILE" | awk '{ print $1 }')"
  stored_hash=""

  if [ -f "$STAMP_FILE" ]; then
    stored_hash="$(cat "$STAMP_FILE")"
  fi

  needs_install=0

  if [ ! -x "$NODE_MODULES_DIR/.bin/next" ]; then
    needs_install=1
  fi

  if [ "$stored_hash" != "$current_hash" ]; then
    needs_install=1
  fi

  if [ "$needs_install" -eq 1 ]; then
    echo "Refreshing frontend dependencies..."
    find "$NODE_MODULES_DIR" -mindepth 1 -maxdepth 1 -exec rm -rf {} \;
    npm ci --legacy-peer-deps
    printf '%s' "$current_hash" > "$STAMP_FILE"
  fi

  if [ -d "$APP_DIR/.next" ] || [ -d "$APP_DIR/.next-dev" ]; then
    echo "Resetting Next.js dev cache for Docker startup..."
    rm -rf \
      "$APP_DIR/.next/dev" \
      "$APP_DIR/.next/cache/turbopack" \
      "$APP_DIR/.next/turbopack" \
      "$APP_DIR/.next-dev"
  fi

  exec npm run dev -- --webpack
fi