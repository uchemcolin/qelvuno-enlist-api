# ============================================================
# DOCKERFILE FOR LARAVEL 12 APPLICATION
# ============================================================
# This file defines how to build the Docker image for your app

FROM php:8.3-fpm-alpine

# ============================================================
# INSTALL SYSTEM DEPENDENCIES
# ============================================================
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    zip \
    unzip \
    nodejs \
    npm \
    mysql-client \
    oniguruma-dev \
    libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd

# ============================================================
# INSTALL COMPOSER
# ============================================================
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

# ============================================================
# INSTALL ADDITIONAL TOOLS (for better file handling)
# ============================================================
RUN apk add --no-cache \
    shadow \
    bash \
    && usermod -u 1000 www-data

# ============================================================
# SET WORKING DIRECTORY
# ============================================================
WORKDIR /var/www/html

# ============================================================
# COPY APPLICATION FILES
# ============================================================
COPY . .

# ============================================================
# INSTALL PHP DEPENDENCIES (INCLUDING DEV FOR LOCAL)
# ============================================================
RUN composer install --no-interaction --optimize-autoloader

# ============================================================
# INSTALL NODE DEPENDENCIES & BUILD FRONTEND
# ============================================================
RUN npm install && npm run build

# ============================================================
# CREATE STORAGE STRUCTURE (ensures directories exist)
# ============================================================
RUN mkdir -p /var/www/html/storage/app/public \
    && mkdir -p /var/www/html/storage/framework/cache \
    && mkdir -p /var/www/html/storage/framework/sessions \
    && mkdir -p /var/www/html/storage/framework/views \
    && mkdir -p /var/www/html/storage/logs

# ============================================================
# SET PERMISSIONS
# ============================================================
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/public

# ============================================================
# EXPOSE PORT
# ============================================================
EXPOSE 8000

# ============================================================
# HEALTHCHECK (ensures app is running properly)
# ============================================================
HEALTHCHECK --interval=30s --timeout=3s --start-period=10s --retries=3 \
    CMD php artisan storage:link --force || exit 1

# ============================================================
# STARTUP COMMAND - Handles storage setup and runs the server
# ============================================================
CMD sh -c "mkdir -p /var/www/html/storage/app/public && \
           mkdir -p /var/www/html/storage/framework/cache && \
           mkdir -p /var/www/html/storage/framework/sessions && \
           mkdir -p /var/www/html/storage/framework/views && \
           mkdir -p /var/www/html/storage/logs && \
           chown -R www-data:www-data /var/www/html/storage && \
           chmod -R 775 /var/www/html/storage && \
           chmod -R 775 /var/www/html/bootstrap/cache && \
           php artisan storage:link --force && \
           php artisan serve --host=0.0.0.0 --port=8000"