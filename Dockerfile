FROM php:8.2-apache

# MySQL PDO driver needed by db.php
RUN docker-php-ext-install pdo pdo_mysql

# Copy the project into Apache's web root
COPY . /var/www/html/

# Render provides the port via $PORT; make Apache listen on it
RUN sed -i 's/80/${PORT}/g' /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf

EXPOSE ${PORT}
