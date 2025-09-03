# Dockerfile
FROM php:8.1-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    zip \
    unzip \
    && docker-php-ext-install mysqli pdo pdo_mysql mbstring gd

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Copy app source
COPY . /var/www/html/

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader || true

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Expose port 80
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
