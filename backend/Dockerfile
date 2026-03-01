# backend/Dockerfile
FROM php:8.4-cli-alpine

# 1. Install system dependencies
# Includes libraries for Postgres, ZIP, GD (Images), and build tools for PECL
RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    unzip \
    git \
    curl \
    linux-headers \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    zlib-dev \
    $PHPIZE_DEPS

# 2. Configure and Install PHP extensions
# We configure GD with freetype and jpeg support for DomPDF/Excel
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql zip pcntl sockets opcache gd exif

# 3. Performance & Resource Tuning
# Increase memory limit to 2GB to prevent Composer "Memory Exhausted" errors
RUN echo "memory_limit=2G" >> /usr/local/etc/php/conf.d/docker-php-ext-custom.ini \
    && echo "opcache.enable_cli=1" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini \
    && echo "opcache.jit_buffer_size=100M" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini \
    && echo "opcache.jit=tracing" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini

# 4. Install Redis extension for high-speed caching
RUN pecl install redis && docker-php-ext-enable redis

# 5. Bring in external binaries
# Download RoadRunner (High-performance server)
COPY --from=spiralscout/roadrunner:latest /usr/bin/rr /usr/bin/rr
# Download Composer (Dependency manager)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 6. Final Environment Setup
WORKDIR /var/www/html

EXPOSE 8000