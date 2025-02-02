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
 opcache \
# Enable Apache mod_rewrite
&& a2enmod rewrite \
# set php.ini
&& mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"


# composer php dependencies manager
## allow composer to run and install
ENV COMPOSER_ALLOW_SUPERUSER=1
## intall composer
COPY --from=composer:2.8.5 /usr/bin/composer /usr/bin/composer


# install app dependencies
COPY composer.lock composer.json ./
RUN composer install --no-autoloader --no-dev --no-interaction --no-progress \
 --ignore-platform-req=ext-zip




# production setup
FROM base_image AS production_app

# Copy application code
COPY . .
RUN composer dumpautoload --no-dev --optimize --no-interaction \
&& composer clear-cache

EXPOSE 8080

# Start Apache in the foreground
CMD ["apache2-foreground"]




# for development setup
FROM base_image AS dev_app

# xdebug
RUN pecl channel-update pecl.php.net \
&& pecl install xdebug-3.1.6 \
&& docker-php-ext-enable xdebug \
# include dev dependencies
&& composer install --no-autoloader --no-interaction --no-progress

# Copy application code
COPY . .
RUN composer dumpautoload --no-interaction \
&& mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

## do NOT map nor expose port 9003
## so that host machine can listen to Xdebug inside container
## EXPOSE 9003