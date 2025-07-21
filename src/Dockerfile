FROM php:8.2-apache

# Install system dependencies including Tesseract, ImageMagick, and build tools
RUN apt-get update && apt-get install -y \
    unzip git curl \
    libmagickwand-dev \
    tesseract-ocr \
    imagemagick \
    ghostscript \
    fonts-dejavu-core fonts-liberation \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && pecl install imagick \
    && docker-php-ext-enable imagick

# Copy custom ImageMagick policy file
COPY ./policy.xml /etc/ImageMagick-6/policy.xml

# Enable Apache mod_rewrite (optional, useful for frameworks)
RUN a2enmod rewrite

# Allow .htaccess overrides
RUN sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Expose port 80
EXPOSE 80
