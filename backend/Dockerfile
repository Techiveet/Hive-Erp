# backend/Dockerfile
FROM php:8.4-fpm

# 1. Install System Dependencies & Nginx
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    libpq-dev \
    librdkafka-dev \
    nginx \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# 2. Install PHP Extensions (including your requested ones)
RUN docker-php-ext-install pdo_pgsql mbstring exif pcntl bcmath gd sockets opcache zip

# 3. Install PECL Extensions (Redis & Kafka)
RUN pecl install redis rdkafka \
    && docker-php-ext-enable redis rdkafka

# 4. Get Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 5. Set Working Directory
WORKDIR /var/www

# 6. Copy Application Code (You were missing this!)
COPY . /var/www

# 7. Install Dependencies
RUN composer install --optimize-autoloader --no-dev

# 8. Set Permissions
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# 9. Configure Nginx (Embedded Config)
RUN echo "server { \
    listen 80; \
    index index.php index.html; \
    root /var/www/public; \
    location / { \
        try_files \$uri \$uri/ /index.php?\$query_string; \
    } \
    location ~ \.php$ { \
        fastcgi_pass 127.0.0.1:9000; \
        fastcgi_index index.php; \
        include fastcgi_params; \
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name; \
    } \
}" > /etc/nginx/sites-available/default

# 10. Start Nginx & PHP
EXPOSE 80
CMD service nginx start && php-fpm
