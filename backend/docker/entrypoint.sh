#!/bin/sh
# Container entrypoint for the Laravel app: makes a fresh clone work with
# nothing more than `docker compose up --build -d`, and is safe to re-run on
# every restart.
set -e

cd /var/www/html
log() { echo "[entrypoint] $*"; }

# 1. Dependencies. The image installs vendor/ at build time and docker-compose
#    keeps it in an anonymous volume (the ./backend bind mount would otherwise
#    hide it). That volume survives container recreation, so compare a
#    composer.lock hash rather than just checking vendor/ exists — otherwise a
#    dependency change + `up --build` would silently keep the old packages.
lock_hash=$(sha1sum composer.lock | cut -d' ' -f1)
if [ ! -f vendor/autoload.php ] || [ "$(cat vendor/.composer-lock-hash 2>/dev/null)" != "$lock_hash" ]; then
    log "vendor/ missing or out of date with composer.lock; running composer install"
    composer install --no-interaction --prefer-dist --optimize-autoloader
    echo "$lock_hash" > vendor/.composer-lock-hash
fi

# 2. Environment file (git-ignored, so absent on a fresh clone).
if [ ! -f .env ]; then
    log "no .env; copying .env.example"
    cp .env.example .env
fi

# A stale cached config would pin old settings (bit us once already) —
# clear it before anything reads config.
php artisan config:clear >/dev/null

# 3. App key: generate once, persisted in .env on the bind mount.
if [ -z "${APP_KEY:-}" ] && ! grep -qE '^APP_KEY=.+' .env; then
    log "no APP_KEY; generating one"
    php artisan key:generate --force
fi

# Database bootstrap only when starting the server; one-off commands
# (`docker compose run --rm app php artisan ...`) skip straight to step 7.
if [ "$1 $2 $3" = "php artisan serve" ]; then
    # 4. Healthy isn't always "ready for this app's connection" — retry for real.
    php artisan app:wait-for-database --timeout=90

    # 5. Idempotent: already-applied migrations are skipped.
    php artisan migrate --force

    # 6. Seed demo data only into an empty database, never on a restart.
    php artisan app:seed-if-empty
fi

# 7. Hand over PID 1 so the server receives signals (clean `docker stop`).
log "starting: $*"
exec "$@"
