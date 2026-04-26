#!/bin/sh
set -eu

APP_DIR=/app
NODE_ENV="${NODE_ENV:-production}"

if [ "$NODE_ENV" = "production" ]; then
  exec node server.js
else
  exec npm run dev -- --webpack
fi