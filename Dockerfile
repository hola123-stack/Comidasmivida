FROM ubuntu:22.04

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y \
    apache2 \
    php8.1 \
    php8.1-mysql \
    libapache2-mod-php8.1 \
    && apt-get clean

RUN a2enmod rewrite php8.1 headers

# Suprime el warning de ServerName
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Fuerza no-caché en HTML, CSS y JS para todos los dispositivos
RUN echo '<Directory /var/www/html>\n\
    <FilesMatch "\.(html|css|js)$">\n\
        Header set Cache-Control "no-cache, no-store, must-revalidate"\n\
        Header set Pragma "no-cache"\n\
        Header set Expires "0"\n\
    </FilesMatch>\n\
</Directory>' >> /etc/apache2/apache2.conf

COPY . /var/www/html/
RUN rm -f /var/www/html/index.html

RUN mkdir -p /var/www/html/uploads/fotos \
    && chmod -R 755 /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2ctl", "-D", "FOREGROUND"]