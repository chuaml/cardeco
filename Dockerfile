# PHP_VERSION selects the PHP runtime of every stage. The default stays 7.4 so that
# `docker build --target production_app .` (setup.production.bat / setup.staging.bat) builds the same image as before.
#   dev container (compose.yml) .......... PHP_VERSION=8.3
#   test matrix (compose.test.yml) ....... PHP_VERSION=7.3 / 7.4 / 8.3
ARG PHP_VERSION=7.4
FROM php:${PHP_VERSION}-apache AS base_image
WORKDIR /var/www/html

# Install system dependencies.
# php:7.3 / php:7.4 are built on Debian 11 (Bullseye, EOL): point its repositories to archive.debian.org.
# php:8.3 is built on Debian 13 (Trixie, deb822 sources, no /etc/apt/sources.list) and needs no change.
RUN . /etc/os-release \
&& case "${VERSION_CODENAME:-}" in \
     buster|bullseye) \
       sed -i 's|http://deb.debian.org|http://archive.debian.org|g; s|http://security.debian.org|http://archive.debian.org|g; /-updates/d' /etc/apt/sources.list \
       && echo 'Acquire::Check-Valid-Until "false";' > /etc/apt/apt.conf.d/99no-check-valid-until ;; \
   esac \
&& apt-get update && apt-get install -y --no-install-recommends \
 zlib1g-dev curl git ca-certificates libonig-dev libpng-dev libjpeg-dev libfreetype-dev \
 libzip-dev \
&& apt-get clean \
&& rm -rf /var/lib/apt/lists/*

# install necessary PHP extensions
# - the gd configure flags were renamed in PHP 7.4
# - mbstring (every image) and opcache (PHP 8.3 image) are already built in; install them only when missing
RUN docker-php-ext-install mysqli \
&& if php -r 'exit(PHP_VERSION_ID < 70400 ? 0 : 1);'; then \
     docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/; \
   else \
     docker-php-ext-configure gd --with-freetype --with-jpeg; \
   fi \
&& docker-php-ext-install gd \
&& docker-php-ext-install zip \
&& { php -m | grep -qix 'mbstring' || docker-php-ext-install mbstring; } \
&& { php -m | grep -qi 'opcache' || docker-php-ext-install opcache; } \
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

EXPOSE 80

# Start Apache in the foreground
CMD ["apache2-foreground"]




# for development setup
FROM base_image AS dev_app

# xdebug: 3.1.6 is the last release that supports PHP 7.x; 3.4.x covers PHP 8.0 - 8.4
RUN pecl channel-update pecl.php.net \
&& if php -r 'exit(PHP_MAJOR_VERSION < 8 ? 0 : 1);'; then \
     pecl install xdebug-3.1.6; \
   else \
     pecl install xdebug-3.4.7; \
   fi \
&& docker-php-ext-enable xdebug \
## if xdebug enabled, `composer` be stucked when port 9003 is not being mapped to host's port AND not being used at all
## this alias default to disable xdebug specifically for the php `composer` command; 
## to prevent `composer` and any command its spawn being debugged by default, which would be slow down or even stucked
&& echo "alias composer='XDEBUG_MODE=off composer'" >> /etc/bash.bashrc

# Optional second CLI: the production-floor PHP next to the PHP 8 runtime, e.g. FLOOR_PHP_CLI=7.3 adds /usr/bin/php7.3
# for `composer run lint:php73` and `php7.3 vendor/bin/phpunit --testsuite unit`. Empty (default) = skip.
# The packages come from deb.sury.org. The official php image pins every php* apt package to priority -1
# (/etc/apt/preferences.d/no-debian-php), so the floor packages need an explicit, earlier pin.
ARG FLOOR_PHP_CLI=""
RUN if [ -n "$FLOOR_PHP_CLI" ] && php -r 'exit(PHP_MAJOR_VERSION >= 8 ? 0 : 1);'; then \
      curl -fsSLo /tmp/debsuryorg-archive-keyring.deb https://packages.sury.org/debsuryorg-archive-keyring.deb \
      && dpkg -i /tmp/debsuryorg-archive-keyring.deb && rm /tmp/debsuryorg-archive-keyring.deb \
      && . /etc/os-release \
      && echo "deb [signed-by=/usr/share/keyrings/debsuryorg-archive-keyring.gpg] https://packages.sury.org/php/ ${VERSION_CODENAME} main" > /etc/apt/sources.list.d/sury-php.list \
      && printf 'Package: php%s* php-common\nPin: origin packages.sury.org\nPin-Priority: 600\n' "$FLOOR_PHP_CLI" > /etc/apt/preferences.d/00-sury-floor-php \
      && apt-get update && apt-get install -y --no-install-recommends \
         "php${FLOOR_PHP_CLI}-cli" "php${FLOOR_PHP_CLI}-mbstring" "php${FLOOR_PHP_CLI}-xml" "php${FLOOR_PHP_CLI}-mysql" \
         "php${FLOOR_PHP_CLI}-zip" "php${FLOOR_PHP_CLI}-gd" "php${FLOOR_PHP_CLI}-curl" \
      && apt-get clean \
      && rm -rf /var/lib/apt/lists/*; \
    fi

# install dev and app dependencies
COPY composer.lock composer.json ./
RUN XDEBUG_MODE=off composer install --no-autoloader --no-interaction --no-progress \
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
