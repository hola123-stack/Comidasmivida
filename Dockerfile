FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql mysqli
RUN a2enmod rewrite

COPY . /var/www/html/

RUN mkdir -p /var/www/html/uploads/fotos \
    && chmod -R 755 /var/www/html/uploads

EXPOSE 80