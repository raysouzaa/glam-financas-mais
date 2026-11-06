FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json postcss.config.js tailwind.config.js vite.config.ts ./
COPY resources/ resources/
RUN npm install --no-audit --no-fund && npm run build
FROM php:8.4-fpm-alpine
RUN apk add --no-cache nginx supervisor libpq-dev icu-dev libzip-dev oniguruma-dev zip unzip bash curl && docker-php-ext-install pdo_pgsql intl opcache mbstring zip
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY . .
COPY --from=assets /app/public/build /var/www/html/public/build
RUN mkdir -p bootstrap/cache storage/framework/cache/data storage/framework/views storage/framework/sessions && composer install --no-interaction --prefer-dist --optimize-autoloader && mkdir -p /run/nginx /var/log/supervisor && chown -R www-data:www-data storage bootstrap/cache
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf
EXPOSE 80
CMD ["/usr/bin/supervisord","-c","/etc/supervisord.conf"]
