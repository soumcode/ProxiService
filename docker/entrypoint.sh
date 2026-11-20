#!/bin/sh

# Stop on errors and undefined variables so failed initialization is visible.
set -eu

# Run all Laravel commands from the project root.
cd /var/www/html

# Ensure Laravel's writable paths exist even when a named volume is initially empty.
mkdir -p \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

# Initialize the application once in the web service, not in every worker.
if [ "${RUN_APP_INIT:-false}" = "true" ]; then
    # Initialize the shared upload volume's ownership before workers use it.
    chown -R www-data:www-data storage bootstrap/cache
    chmod -R ug+rwX storage bootstrap/cache

    # Refuse to boot with an empty encryption key rather than serve insecurely.
    if [ -z "${APP_KEY:-}" ]; then
        echo "APP_KEY must be configured before production startup." >&2
        exit 1
    fi

    # Apply database migrations before accepting web requests.
    php artisan migrate --force

    # Publish the public upload symlink used by Laravel's public disk.
    php artisan storage:link --force

    # Cache configuration, routes, and views for production requests.
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
else
    # Keep worker startup quick without recursively changing ownership of uploads.
    chown www-data:www-data storage bootstrap/cache storage/app storage/framework storage/logs
    chmod ug+rwX storage bootstrap/cache storage/app storage/framework storage/logs
fi

# Replace the entrypoint process with Nginx, queue:work, or schedule:work.
exec "$@"
