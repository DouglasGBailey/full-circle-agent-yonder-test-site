# Saltmarsh Hotel test site: one container, Apache + PHP 8.4, behind chrisweb's nginx.
# Build from the repository root:  docker build -f deploy/Dockerfile -t yonder-test-site .
# Secrets never enter the image: .env is mounted by docker-compose (env_file).

# Stage 1: production Composer dependencies only (no dev tools in the image).
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist

# Stage 2: the runtime.
FROM php:8.4-apache

# Hide versions, serve only public/, allow .htaccess rewrites.
RUN a2enmod rewrite headers \
 && printf 'ServerName localhost\nServerTokens Prod\nServerSignature Off\nTraceEnable Off\n' > /etc/apache2/conf-available/zz-hardening.conf \
 && a2enconf zz-hardening \
 && printf 'expose_php = Off\nmemory_limit = 256M\nupload_max_filesize = 2M\npost_max_size = 2M\nopcache.enable = 1\nopcache.validate_timestamps = 0\n' > /usr/local/etc/php/conf.d/zz-app.ini \
 && docker-php-ext-install opcache
COPY deploy/apache-vhost.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY --from=vendor /app/vendor ./vendor
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-interaction \
 && mkdir -p /var/www/data storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache /var/www/data

COPY deploy/entrypoint.sh /usr/local/bin/yonder-entrypoint
RUN chmod 755 /usr/local/bin/yonder-entrypoint

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --retries=3 CMD curl -fsS http://127.0.0.1/up >/dev/null || exit 1
ENTRYPOINT ["yonder-entrypoint"]
CMD ["apache2-foreground"]
