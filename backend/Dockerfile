FROM php:8.2-apache

ENV APACHE_DOCUMENT_ROOT='/var/www/html/public'
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf

RUN a2enmod rewrite

RUN docker-php-ext-install pdo_mysql

WORKDIR /var/www/html

EXPOSE 80
