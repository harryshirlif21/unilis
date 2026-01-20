# Use official PHP 8.1 with Apache
FROM php:8.1-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    && docker-php-ext-install \
        mysqli \
        pdo \
        pdo_mysql \
        mbstring \
        gd \
        exif \
        zip \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy Composer from official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy only composer files first (for caching)
COPY composer.json composer.lock* ./

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Copy full application
COPY . .

# Ensure vendor exists
RUN composer dump-autoload --optimize --classmap-authoritative || true

# Create required folders and set correct permissions
RUN mkdir -p /var/www/html/assets/uploads \
    /var/www/html/assets/assignments \
    /var/www/html/assets/meetings \
    && chown -R www-data:www-data /var/www/html/assets/uploads \
    /var/www/html/assets/assignments \
    /var/www/html/assets/meetings \
    && chmod -R 775 /var/www/html/assets/uploads \
    /var/www/html/assets/assignments \
    /var/www/html/assets/meetings

# Fix permissions for the whole app
RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && find /var/www/html -type f -exec chmod 644 {} \;

# Make PHP errors visible in docker logs
RUN echo "error_log = /dev/stderr" >> /usr/local/etc/php/php.ini \
    && echo "log_errors = On" >> /usr/local/etc/php/php.ini \
    && echo "display_errors = On" >> /usr/local/etc/php/php.ini

# Expose port
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
