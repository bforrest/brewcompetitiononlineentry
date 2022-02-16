FROM php:7.3-apache
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli
RUN docker-php-ext-install mysqli pdo pdo_mysql
RUN apt-get update && apt-get upgrade -y
RUN a2ensite default-ssl
RUN a2enmod rewrite
RUN a2enmod headers
COPY ./ /var/www/html/

