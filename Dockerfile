# 1. Build Frontend React / Vite
FROM node:20-alpine AS frontend-builder
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build

# 2. Production Server: Nginx + PHP-FPM
FROM php:8.2-fpm-alpine

# Enstale Nginx, zouti MySQL ak ekstansyon PHP nesesè
RUN apk add --no-cache nginx mysql-client libpng-dev libjpeg-turbo-dev freetype-dev libzip-dev zip unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_mysql mysqli zip

# Kreye dosye travay
WORKDIR /var/www/html

# Kopye Nginx configuration
COPY docker/nginx.conf /etc/nginx/nginx.conf

# Kopye kòd backend PHP a
COPY backend /var/www/html/backend

# Kopye Frontend ki bati soti nan etap 1
COPY --from=frontend-builder /app/dist /var/www/html/dist

# Kreye dosye uploads epi bay pèmisyon
RUN mkdir -p /var/www/html/backend/uploads/music \
             /var/www/html/backend/uploads/covers \
             /var/www/html/backend/uploads/proofs \
             /var/www/html/backend/uploads/avatars \
             /var/www/html/backend/uploads/banners \
             /var/www/html/backend/uploads/media \
             /var/www/html/backend/uploads/general \
             /var/www/html/backend/logs \
             /var/www/html/backend/backups \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/backend/uploads

# Kopye startup script
COPY docker/start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 80

CMD ["/start.sh"]
