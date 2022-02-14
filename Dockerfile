FROM php:8.0-apache
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli
RUN apt-get update && apt-get upgrade -y
RUN a2enmod rewrite
RUN a2enmod headers
RUN mysql -u root -pBBO2022_ROOT_PASSWORD -D BBO2022_DATABASE < ./sql/bcoem_baseline_2.3.X.sql
COPY ./ /var/www/html/
ADD ./ /var/www/html