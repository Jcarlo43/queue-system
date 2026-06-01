FROM php:8.2-apache

# Install PostgreSQL driver for PHP
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy all files
COPY . /var/www/html/

# Move backend files to the right location for Apache
RUN mv /var/www/html/backend/* /var/www/html/ && \
    rmdir /var/www/html/backend

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Configure Apache to handle PHP files
RUN echo "DirectoryIndex index.html status.html admin.html" >> /etc/apache2/apache2.conf

# Expose port 80
EXPOSE 80