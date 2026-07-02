# ShowtimeSneakers — PHP + Apache image for Render (or any Docker host).
FROM php:8.2-apache

# MySQL driver for PDO.
RUN docker-php-ext-install pdo pdo_mysql

# Copy the site into Apache's web root.
COPY . /var/www/html/

# Render (and many hosts) inject a $PORT to listen on. Apache defaults to 80,
# so we rewrite its listen port at container start.
RUN a2enmod rewrite

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
