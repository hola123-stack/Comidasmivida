FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql mysqli
RUN a2dismod mpm_event mpm_worker || true
RUN a2enmod mpm_prefork rewrite

COPY . /var/www/html/

RUN mkdir -p /var/www/html/uploads/fotos \
    && chmod -R 755 /var/www/html/uploads

EXPOSE 80