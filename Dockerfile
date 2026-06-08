FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    && docker-php-ext-install pdo pdo_mysql mysqli \
    && a2enmod rewrite \
    && sed -i 's/^#LoadModule mpm_prefork/LoadModule mpm_prefork/' /etc/apache2/mods-available/mpm_prefork.load 2>/dev/null || true \
    && a2dismod mpm_event 2>/dev/null || true

COPY . /var/www/html/

RUN mkdir -p /var/www/html/uploads/fotos \
    && chmod -R 755 /var/www/html/uploads

EXPOSE 80