FROM php:8.2-apache

RUN apt-get update \
	&& apt-get install -y --no-install-recommends \
		default-mysql-client \
		git \
		openssh-client \
		python3 \
		unzip \
		libzip-dev \
	&& docker-php-ext-install mysqli pdo pdo_mysql zip \
	&& a2enmod rewrite headers \
	&& rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader

COPY . .
COPY .docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY .docker/php/server-monitoring.ini /usr/local/etc/php/conf.d/server-monitoring.ini

RUN mkdir -p application/cache application/logs uploads/backup/database uploads/backup/system uploads/files uploads/tmp \
	&& chown -R www-data:www-data application/cache application/logs uploads

EXPOSE 80
