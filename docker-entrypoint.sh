#!/bin/sh
set -e

composer install --no-interaction --prefer-dist
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
php bin/console messenger:setup-transports --no-interaction

exec "$@"
