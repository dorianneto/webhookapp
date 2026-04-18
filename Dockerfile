FROM node:24 AS hookyard_node

FROM php:8.4-fpm AS hookyard_php

# Copy Node.js and NPM from the node image
COPY --from=hookyard_node /usr/local/lib/node_modules /usr/local/lib/node_modules
COPY --from=hookyard_node /usr/local/bin/node /usr/local/bin/node

# Symlink npm to make it accessible
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm

RUN apt-get update && apt-get install -y \
    git curl unzip libpq-dev libzip-dev libicu-dev \
  && docker-php-ext-install pdo_pgsql intl zip pcntl opcache \
  && rm -rf /var/lib/apt/lists/*

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Symfony CLI
RUN curl -sS https://get.symfony.com/cli/installer | bash \
  && mv /root/.symfony*/bin/symfony /usr/local/bin/symfony

WORKDIR /app
COPY . .

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
CMD [ "php-fpm" ]

# NGINX
FROM nginx:1.28-alpine AS hookyard_nginx

COPY docker/nginx/conf.d/default.conf /etc/nginx/conf.d/
