#!/bin/sh
set -e

# One image, three roles. Anything else is run as given, for example:
#   docker compose exec app php artisan tidyorg:install

case "${1:-web}" in
    web)
        php artisan migrate --force
        php artisan optimize
        exec frankenphp run --config /etc/frankenphp/Caddyfile
        ;;
    queue)
        exec php artisan queue:work --tries=3 --max-time=3600
        ;;
    scheduler)
        exec php artisan schedule:work
        ;;
    *)
        exec "$@"
        ;;
esac
