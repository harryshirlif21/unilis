# Use PHP with Apache
FROM php:8.1-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    zip \
    unzip \
    git \
    && docker-php-ext-install mysqli pdo pdo_mysql mbstring gd

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy composer from official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy composer files first for caching
COPY composer.json composer.lock ./

# Install PHP dependencies inside container
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Copy the rest of the application
COPY . .

# Set correct permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Expose port 80
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
