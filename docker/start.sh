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

echo "🚀 Lanse PHP-FPM..."
php-fpm -D

echo "🚀 Lanse Nginx..."
nginx -g "daemon off;"
