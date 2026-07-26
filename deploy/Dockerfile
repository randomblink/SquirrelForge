FROM composer:2 AS dependencies

WORKDIR /app
COPY composer.json composer.lock ./
COPY src ./src
RUN composer install --no-dev --classmap-authoritative --no-interaction --no-progress

FROM php:8.3-cli-alpine

RUN apk add --no-cache sqlite-dev \
    && docker-php-ext-install pdo_sqlite

WORKDIR /app
COPY --from=dependencies /app/vendor ./vendor
COPY . .

RUN mkdir -p /app/var \
    && chown -R www-data:www-data /app/var \
    && chmod +x /app/deploy/entrypoint.sh /app/bin/runtime-preflight.php /app/bin/production-smoke-test.php

USER www-data
EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=10s --start-period=20s --retries=3 \
  CMD php -r '$r=@file_get_contents("http://127.0.0.1:8080/v1/health/providers"); exit($r!==false && (json_decode($r,true)["ready"]??false)===true ? 0 : 1);'

ENTRYPOINT ["/app/deploy/entrypoint.sh"]
