FROM php:8.2-apache

RUN docker-php-ext-install pdo_mysql && a2enmod rewrite

RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf
RUN sed -ri -e "s!/var/www/html!/var/www/html/public!g" /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
RUN printf '%s\n' \
    '<Directory /var/www/html/public>' \
    '    Options Indexes FollowSymLinks' \
    '    AllowOverride All' \
    '    Require all granted' \
    '    RewriteEngine On' \
    '    RewriteCond %{REQUEST_FILENAME} !-f' \
    '    RewriteCond %{REQUEST_FILENAME} !-d' \
    '    RewriteRule ^ index.php [QSA,L]' \
    '</Directory>' \
    > /etc/apache2/conf-available/php-analytics.conf \
    && a2enconf php-analytics

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
