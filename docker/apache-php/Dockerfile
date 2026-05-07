FROM php:8.2-apache

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Paquetes para compilar extensiones PECL
RUN apt-get update && apt-get install -y \
    git unzip curl ca-certificates \
    libxml2-dev libzip-dev zlib1g-dev \
    libicu-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    autoconf make g++ pkg-config libssl-dev \
 && rm -rf /var/lib/apt/lists/*

# Extensiones core
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql soap intl zip gd bcmath opcache

# Redis vía PECL + habilitar + verificación
RUN yes "" | pecl install redis \
 && docker-php-ext-enable redis \
 && php -m | grep -i redis

# Ajustes PHP
RUN echo "soap.wsdl_cache_enabled=0" > /usr/local/etc/php/conf.d/soap.ini \
 && echo "date.timezone=America/La_Paz" > /usr/local/etc/php/conf.d/timezone.ini

# Apache
RUN a2enmod rewrite headers

# DocumentRoot -> /public
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf

# AllowOverride para Laravel
RUN printf "%s\n" \
  "<Directory /var/www/html/public>" \
  "    AllowOverride All" \
  "    Options FollowSymLinks" \
  "    Require all granted" \
  "</Directory>" \
  > /etc/apache2/conf-available/laravel.conf \
 && a2enconf laravel

WORKDIR /var/www/html
