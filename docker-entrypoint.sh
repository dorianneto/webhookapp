#!/bin/sh
set -e

if [ "$APP_ENV" = "prod" ]; then
    composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader
else
    composer install --no-interaction --prefer-dist
fi
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
php bin/console messenger:setup-transports --no-interaction

exec "$@"
