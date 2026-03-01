FROM php:8.3-apache

RUN apt-get update && apt-get install -y libcurl4-openssl-dev \
    && docker-php-ext-install curl \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

RUN echo "session.save_handler = files" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "session.save_path = /tmp/sessions" >> /usr/local/etc/php/conf.d/custom.ini \
    && mkdir -p /tmp/sessions && chown www-data:www-data /tmp/sessions

COPY apache.conf /etc/apache2/sites-available/000-default.conf
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80