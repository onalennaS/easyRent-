FROM php:8.2-apache

# Install PHP extensions needed by this app
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install mysqli pdo pdo_mysql gd \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY . /var/www/html

# Ensure upload directories exist and are writable
RUN mkdir -p /var/www/html/uploads/properties \
    /var/www/html/uploads/profiles \
    /var/www/html/uploads/documents \
    /var/www/html/uploads/tenant_profiles \
    /var/www/html/uploads/tenant_documents \
    /var/www/html/uploads/maintenance \
    && chown -R www-data:www-data /var/www/html/uploads \
    && chmod -R 775 /var/www/html/uploads

EXPOSE 80
