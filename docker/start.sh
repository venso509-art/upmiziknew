#!/bin/sh
set -e

# Asire dosye uploads yo gen bon pèmisyon
mkdir -p /var/www/html/backend/uploads/music \
         /var/www/html/backend/uploads/covers \
         /var/www/html/backend/uploads/proofs \
         /var/www/html/backend/uploads/avatars \
         /var/www/html/backend/uploads/banners \
         /var/www/html/backend/uploads/media \
         /var/www/html/backend/uploads/general

chown -R www-data:www-data /var/www/html/backend/uploads
chmod -R 775 /var/www/html/backend/uploads

# Asire PHP-FPM pa efase varyab anviwònman Docker yo (clear_env = no)
if [ -d "/usr/local/etc/php-fpm.d" ]; then
    echo "[www]" > /usr/local/etc/php-fpm.d/zz-docker-env.conf
    echo "clear_env = no" >> /usr/local/etc/php-fpm.d/zz-docker-env.conf
fi

# Ekspòte varyab anviwònman Docker yo nan fichye .env pou PHP ka li yo dirèkteman
printenv | grep -E '^(DB_|SITE_|APP_|COOLIFY_|PORT)' > /var/www/html/.env || true
chown www-data:www-data /var/www/html/.env || true
chmod 644 /var/www/html/.env || true

echo "🚀 Lanse PHP-FPM..."
php-fpm -D

echo "🚀 Lanse Nginx..."
nginx -g "daemon off;"
