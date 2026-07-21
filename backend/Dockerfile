FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql

RUN a2enmod rewrite

# =====================================================
# HARDENING DE SEGURIDAD (nuevo)
# Resuelve alerta ZAP: "El servidor filtra información
# de versión a través del campo Server del encabezado"
# =====================================================
RUN echo "ServerTokens Prod" >> /etc/apache2/apache2.conf \
    && echo "ServerSignature Off" >> /etc/apache2/apache2.conf \
    && echo "expose_php = Off" >> /usr/local/etc/php/conf.d/hardening.ini

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

RUN echo '<Directory /var/www/html>\n    AllowOverride All\n</Directory>' \
    >> /etc/apache2/apache2.conf

EXPOSE 80
