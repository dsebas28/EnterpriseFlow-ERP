#!/bin/sh
# Container entrypoint for the app, queue and scheduler services.
#
# Only the web process (php-fpm) runs migrations, so they happen once per
# deploy; the queue and scheduler containers wait for it to be healthy.
set -eu

# Generating the key is how APP_KEY gets set in the first place.
case "$*" in
    *key:generate*) exec "$@" ;;
esac

if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY is not set. Generate one with: php artisan key:generate --show" >&2
    exit 1
fi

if [ "${1:-}" = "php-fpm" ]; then
    if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
        php artisan migrate --force --no-interaction
    fi

    if [ "${SEED_DEMO:-false}" = "true" ]; then
        # Idempotent: does nothing if the demo company already exists.
        php artisan db:seed --class='Database\Seeders\DemoSeeder' --force --no-interaction
    fi
fi

# Cache config, routes, views and events with the runtime environment.
php artisan optimize --no-interaction

exec "$@"
