FROM php:7.4-apache AS base_image
WORKDIR /var/www/html

# install system dependencies
RUN apt-get update && apt-get install -y \
&& apt-get install zlib1g-dev curl libonig-dev libpng-dev libjpeg-dev libfreetype6-dev zlib1g-dev \ 
 libzip-dev \
 -y
# install necessary PHP extensions
RUN docker-php-ext-install mysqli \
&& docker-php-ext-configure gd --with-freetype --with-jpeg \
&& docker-php-ext-install gd \
&& docker-php-ext-install \
 zip \
 mbstring \
 opcache


# composer php dependencies manager
## allow composer to run and install
ENV COMPOSER_ALLOW_SUPERUSER=1
## intall composer
COPY --from=composer:2.8.5 /usr/bin/composer /usr/bin/composer


# production setup
FROM base_image AS production_app

# COPY composer.lock composer.json ./
# Copy your application code
COPY . .

## install app dependencies
RUN composer install --ignore-platform-req=ext-zip \
&& composer dumpautoload --optimize \
# Enable Apache mod_rewrite
&& a2enmod rewrite \
# set php.ini
&& mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"



# Configure Apache (if needed, e.g., virtual hosts)
# COPY apache-config /etc/apache2/sites-available/000-default.conf

EXPOSE 8080

# Start Apache in the foreground
CMD ["apache2-foreground"]




# for development setup
FROM production_app AS dev_app

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini" \
# xdebug
&& pecl channel-update pecl.php.net \
&& pecl install xdebug-3.1.6 \
&& docker-php-ext-enable xdebug


## do NOT map nor expose port 9003
## so that host machine can listen to Xdebug inside container
## EXPOSE 9003