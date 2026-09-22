#!/bin/sh
# Container entrypoint for the Vite dev server: makes a fresh clone work with
# nothing more than `docker compose up --build -d`.
set -e

cd /app
log() { echo "[entrypoint] $*"; }

# The image installs node_modules at build time and docker-compose keeps it
# in an anonymous volume (the ./frontend bind mount would otherwise hide it).
# That volume survives container recreation, so compare a package-lock.json
# hash rather than just checking node_modules exists — otherwise a dependency
# change + `up --build` would silently keep the old packages.
lock_hash=$(sha1sum package-lock.json | cut -d' ' -f1)
if [ ! -d node_modules/.bin ] || [ "$(cat node_modules/.package-lock-hash 2>/dev/null)" != "$lock_hash" ]; then
    log "node_modules missing or out of date with package-lock.json; running npm ci"
    npm ci --no-audit --no-fund
    echo "$lock_hash" > node_modules/.package-lock-hash
fi

# Hand over PID 1 so the dev server receives signals (clean `docker stop`).
log "starting: $*"
exec "$@"
