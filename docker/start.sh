#!/bin/sh
# Entrypoint for the local development container (see compose.yaml).
set -e

[ -f vendor/autoload.php ] || composer install --no-interaction

if [ ! -f public/build/manifest.json ]; then
    echo "WARNING: public/build is missing. Run 'npm ci && npm run build' on the host or the admin panel will not render." >&2
fi

if [ ! -s "$DB_DATABASE" ]; then
    # First boot: fresh database with the demo company, a login per role and
    # three years of trading history.
    touch "$DB_DATABASE"
    php artisan migrate --force
    php artisan db:seed --class=DemoCompanySeeder --force
    php docker/demo-users.php
    php artisan db:seed --class=HistoricalDataSeeder --force
else
    php artisan migrate --force
fi

# --no-reload lets PHP_CLI_SERVER_WORKERS (compose.yaml) take effect.
exec php artisan serve --host=0.0.0.0 --port=8000 --no-reload
