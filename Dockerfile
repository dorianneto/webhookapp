FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
    git curl unzip libpq-dev libzip-dev libicu-dev \
  && docker-php-ext-install pdo_pgsql intl zip pcntl opcache \
  && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Symfony CLI
RUN curl -sS https://get.symfony.com/cli/installer | bash \
  && mv /root/.symfony*/bin/symfony /usr/local/bin/symfony

WORKDIR /app
COPY . .
RUN composer install --no-interaction --prefer-dist

EXPOSE 8000
CMD ["symfony", "server:start", "--no-tls", "--allow-http"]
