FROM php:8.2-apache

# Enable Apache rewrite rules
RUN a2enmod rewrite

# Install system CA certificates for secure MySQL/Aiven connections
RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates \
    && update-ca-certificates \
    && rm -rf /var/lib/apt/lists/*

# Copy the application into Apache's web root
COPY . /var/www/html/

# Allow Apache/PHP to write to the uploads directory
RUN mkdir -p /var/www/html/storage/uploads \
    && chown -R www-data:www-data /var/www/html/storage

# Render provides the PORT environment variable.
# Apache listens on 80 by default, so configure it to use Render's port.
RUN sed -i 's/Listen 80/Listen 10000/' /etc/apache2/ports.conf \
    && sed -i 's/:80>/:10000>/g' /etc/apache2/sites-available/000-default.conf

EXPOSE 10000

CMD ["apache2-foreground"]