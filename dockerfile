# Use official PHP 8.1 with Apache
FROM php:8.1-apache

# Install system dependencies + Postfix + OpenDKIM
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    postfix \
    libsasl2-modules \
    opendkim \
    opendkim-tools \
    mailutils \
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

# Configure Postfix
RUN postconf -e "myhostname = unilis.jhubafrica.com" \
    && postconf -e "mydomain = jhubafrica.com" \
    && postconf -e "myorigin = unilis.jhubafrica.com" \
    && postconf -e "inet_interfaces = all" \
    && postconf -e "inet_protocols = ipv4" \
    && postconf -e "mydestination = unilis.jhubafrica.com, localhost" \
    && postconf -e "relayhost =" \
    && postconf -e "smtpd_use_tls = yes" \
    && postconf -e "smtp_tls_security_level = may" \
    && postconf -e "smtpd_tls_security_level = may"

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

# Fix permissions for the whole app
RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && find /var/www/html -type f -exec chmod 644 {} \;

# Create required folders and set correct permissions
RUN mkdir -p /var/www/html/assets/uploads \
    /var/www/html/assets/assignments \
    /var/www/html/assets/meetings \
    && chown -R www-data:www-data /var/www/html/assets/uploads \
    /var/www/html/assets/assignments \
    /var/www/html/assets/meetings \
    && chmod -R 776 /var/www/html/assets/uploads \
    /var/www/html/assets/assignments \
    /var/www/html/assets/meetings

# Make PHP errors visible in docker logs
RUN echo "error_log = /dev/stderr" >> /usr/local/etc/php/php.ini \
    && echo "log_errors = On" >> /usr/local/etc/php/php.ini \
    && echo "display_errors = On" >> /usr/local/etc/php/php.ini \
    && echo "upload_max_filesize = 50M" >> /usr/local/etc/php/php.ini \
    && echo "post_max_size = 50M" >> /usr/local/etc/php/php.ini \
    && echo "max_execution_time = 300" >> /usr/local/etc/php/php.ini \
    && echo "max_input_time = 300" >> /usr/local/etc/php/php.ini \
    && echo "memory_limit = 256M" >> /usr/local/etc/php/php.ini

# Copy startup script
COPY start.sh /start.sh
RUN chmod +x /start.sh

# Expose ports
EXPOSE 80 25 587

# Start both Postfix and Apache
CMD ["/start.sh"]