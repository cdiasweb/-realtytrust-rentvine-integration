FROM php:8.2-apache

# Install system dependencies and Composer
RUN apt-get update && apt-get install -y unzip git curl \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Enable Apache rewrite (optional, useful for frameworks)
RUN a2enmod rewrite

# Allow .htaccess overrides
RUN sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

