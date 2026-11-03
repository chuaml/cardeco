ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-apache AS base_image
WORKDIR /var/www/html

# install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
 zlib1g-dev curl libonig-dev libpng-dev libjpeg-dev libfreetype6-dev zlib1g-dev \ 
 libzip-dev \
&& apt-get clean \
&& rm -rf /var/lib/apt/lists/*

# install necessary PHP extensions
RUN docker-php-ext-install mysqli \
&& docker-php-ext-configure gd --with-freetype --with-jpeg \
&& docker-php-ext-install gd \
&& docker-php-ext-install \
 zip \
 mbstring \
 opcache \
# Enable Apache httpd module: mod_rewrite, mod_headers
&& a2enmod rewrite headers \
# set php.ini
&& mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"


# composer php dependencies manager
## allow composer to run and install
ENV COMPOSER_ALLOW_SUPERUSER=1
## intall composer
COPY --from=composer:2.8.5 /usr/bin/composer /usr/bin/composer




# production setup
FROM base_image AS production_app

# install app dependencies
COPY composer.lock composer.json ./
RUN composer install --no-autoloader --no-dev --no-interaction --no-progress \
 --ignore-platform-req=ext-zip \
&& composer clear-cache

# Copy application code
COPY . .
RUN composer dumpautoload --no-dev --optimize --no-interaction \
&& composer clear-cache

COPY "./php-production-override.ini" "$PHP_INI_DIR/conf.d/php-production-override.ini"

EXPOSE 8080

# Start Apache in the foreground
CMD ["apache2-foreground"]




# for development setup
FROM base_image AS dev_app

# xdebug (3.1.6 for PHP 7.4, 3.3.2 for PHP 8.x)
RUN pecl channel-update pecl.php.net \
&& if php -r 'exit(PHP_MAJOR_VERSION < 8 ? 0 : 1);'; then \
     pecl install xdebug-3.1.6; \
   else \
     pecl install xdebug-3.3.2; \
   fi \
&& docker-php-ext-enable xdebug

# install dev and app dependencies
COPY composer.lock composer.json ./
RUN composer install --no-autoloader --no-interaction --no-progress \
 --ignore-platform-req=ext-zip \
&& composer clear-cache \
&& mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

## no need copy application code, we gonna bind-mount the project from host dir into container 
# COPY . .
# RUN composer dumpautoload --no-interaction  ## no need to generate autoload, wait for ignored dir to be mounted in dev env first

# Start Apache in the foreground
# CMD ["apache2-foreground"]  ## default command to run in container, may be overriden by other
## ENTRYPOINT apache2-foreground  # do not use entrypoint as unchangable default command


## do NOT map nor expose port 9003
## so that host machine can listen to Xdebug inside container
## EXPOSE 9003

