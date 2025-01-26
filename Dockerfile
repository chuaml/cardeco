FROM php:7.4-apache AS base_image
WORKDIR /var/www/html

# install system dependencies
RUN apt-get update && apt-get install -y \
&& apt-get install zip zlib1g-dev curl libonig-dev libpng-dev libjpeg-dev libfreetype6-dev zlib1g-dev -y \
## install necessary PHP extensions
&& docker-php-ext-install mysqli \
&& docker-php-ext-configure gd --with-freetype --with-jpeg \
&& docker-php-ext-install gd

# composer php dependencies manager
## allow composer to run and install
ENV COMPOSER_ALLOW_SUPERUSER=1
## intall composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
&& php -r "if (hash_file('sha384', 'composer-setup.php') === 'dac665fdc30fdd8ec78b38b9800061b4150413ff2e3b6f88543c636f7cd84f6db9189d43a81e5503cda447da73c7e5b6') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" \
&& php composer-setup.php \
&& php -r "unlink('composer-setup.php');" 


# production setup
FROM base_image AS production_app

# COPY composer.lock composer.json ./
# Copy your application code
COPY . .

## install app dependencies
RUN php composer.phar install --ignore-platform-req=ext-zip \
&& php composer.phar dumpautoload --optimize \
# Enable Apache mod_rewrite
&& a2enmod rewrite \
# set php.ini
&& mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"



# Configure Apache (if needed, e.g., virtual hosts)
# COPY apache-config /etc/apache2/sites-available/000-default.conf

EXPOSE 8080

# Start Apache in the foreground
CMD ["apache2-foreground"]




# for development setup
FROM production_app AS dev_app
RUN mv "php.ini-development" "$PHP_INI_DIR/php.ini"

# xdebug
RUN pecl channel-update pecl.php.net \
&& pecl install xdebug-3.1.6 \
&& docker-php-ext-enable xdebug


EXPOSE 9003