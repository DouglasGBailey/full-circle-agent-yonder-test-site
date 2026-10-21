#!/bin/sh
# Prepares the mounted data and log folders, migrates, caches config, then runs Apache.
set -eu
cd /var/www/html

DB="${DB_DATABASE:-/var/www/data/database.sqlite}"
mkdir -p "$(dirname "$DB")" storage/logs
[ -f "$DB" ] || touch "$DB"
chown -R www-data:www-data "$(dirname "$DB")" storage bootstrap/cache
chmod 750 "$(dirname "$DB")"
chmod 640 "$DB"

# Run as www-data so every file artisan creates stays writable by Apache.
su -s /bin/sh www-data -c "php artisan migrate --force --no-interaction"
su -s /bin/sh www-data -c "php artisan config:cache && php artisan route:cache && php artisan view:cache"

exec "$@"
