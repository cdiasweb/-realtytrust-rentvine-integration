FROM php:8.2-apache

# Install system dependencies, Redis, and Composer
RUN apt-get update && apt-get install -y unzip git curl \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    # Install the Redis extension
    && pecl install redis \
    && docker-php-ext-enable redis

# Enable Apache mod_rewrite (optional, useful for frameworks)
RUN a2enmod rewrite

# Allow .htaccess overrides
RUN sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Expose port 80
EXPOSE 80
