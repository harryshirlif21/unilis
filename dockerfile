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

# Copy Composer from official image (faster & more reliable)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy only composer files first → better Docker layer caching
COPY composer.json composer.lock* ./

# Install dependencies (including PHPMailer)
# If composer.json doesn't exist yet → create a minimal one automatically
RUN if [ -f composer.json ]; then \
        composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist; \
    else \
        composer require phpmailer/phpmailer --no-interaction --prefer-dist; \
    fi

# Now copy the full application
COPY . .

# Ensure vendor/autoload.php exists even if composer.json was missing
RUN composer dump-autoload --optimize --classmap-authoritative || true

# Fix permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
# Make PHP errors visible in docker logs
RUN echo "error_log = /dev/stderr" >> /usr/local/etc/php/php.ini \
    && echo "log_errors = On" >> /usr/local/etc/php/php.ini \
    && echo "display_errors = On" >> /usr/local/etc/php/php.ini
# Expose port
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]