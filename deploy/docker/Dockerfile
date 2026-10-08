# PHP-FPM image for the app. No Node, no Composer — the project has no build step.
FROM php:8.3-fpm-alpine

RUN apk add --no-cache libzip-dev libxml2-dev curl-dev oniguruma-dev \
 && docker-php-ext-install pdo_mysql zip dom mbstring curl \
 && { \
      echo 'expose_php=Off'; \
      echo 'upload_max_filesize=20M'; \
      echo 'post_max_size=24M'; \
      echo 'memory_limit=256M'; \
      echo 'session.use_strict_mode=1'; \
      echo 'opcache.enable=1'; \
      echo 'opcache.validate_timestamps=0'; \
    } > /usr/local/etc/php/conf.d/app.ini \
 && docker-php-ext-install opcache

WORKDIR /var/www/dropboxphp
COPY --chown=www-data:www-data . .
RUN mkdir -p storage/uploads storage/backups storage/logs storage/cache \
 && chown -R www-data:www-data storage

USER www-data
