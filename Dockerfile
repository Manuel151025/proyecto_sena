FROM php:8.2-apache

# Composer desde la imagen oficial (sin instalarlo a mano)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Dependencias del sistema y extensiones de PHP necesarias
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo pdo_mysql zip

# Habilitar mod_rewrite (por si se usa en el futuro)
RUN a2enmod rewrite

WORKDIR /var/www/html

COPY . /var/www/html/

# Generar el autoloader PSR-4 (crea vendor/autoload.php).
# --no-dev porque no hay dependencias de desarrollo; --no-interaction para el build.
RUN composer install --no-dev --optimize-autoloader --no-interaction

RUN chown -R www-data:www-data /var/www/html