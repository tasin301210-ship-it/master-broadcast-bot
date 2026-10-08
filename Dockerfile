FROM php:8.3-apache-bookworm

RUN a2dismod mpm_event mpm_worker mpm_prefork || true
RUN a2enmod mpm_prefork rewrite

RUN docker-php-ext-install pdo_mysql

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
