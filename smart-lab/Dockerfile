# Use PHP 8.1 Apache image
FROM php:8.1-apache

# Set working directory
WORKDIR /var/www/html

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    curl \
    git \
    mariadb-client \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_mysql \
    mysqli \
    gd \
    zip \
    mbstring \
    xml \
    bcmath \
    curl

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Enable Apache modules
RUN a2enmod rewrite headers

# Configure PHP
RUN echo "memory_limit=512M" > /usr/local/etc/php/conf.d/memory-limit.ini \
    && echo "upload_max_filesize=64M" > /usr/local/etc/php/conf.d/upload-limit.ini \
    && echo "post_max_size=64M" >> /usr/local/etc/php/conf.d/upload-limit.ini \
    && echo "max_execution_time=300" > /usr/local/etc/php/conf.d/execution-time.ini

# Copy composer files first (layer cache: only re-runs install when composer files change)
COPY composer.json composer.lock* /var/www/html/

# Install PHP dependencies (no dev packages, no scripts that need a full app)
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --optimize-autoloader

# Copy remaining application files
COPY . /var/www/html/

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/logs \
    && chmod -R 777 /var/www/html/uploads \
    && chmod -R 777 /var/www/html/assets

# Create necessary directories
RUN mkdir -p /var/www/html/logs /var/www/html/uploads /var/www/html/assets/datasheets /var/www/html/assets/qrcodes

# Configure Apache
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
    && echo "ServerName smartlab.jhubafrica.com" >> /etc/apache2/apache2.conf

# Expose port 80
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
