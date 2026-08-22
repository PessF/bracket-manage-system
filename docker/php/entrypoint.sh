#!/bin/sh

set -eu

if [ -f artisan ] && [ -f vendor/autoload.php ]; then
    php artisan migrate --force --no-interaction
fi

exec docker-php-entrypoint "$@"
