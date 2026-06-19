FROM php:8.2-apache

# Instalar dependencias del sistema y extensiones de PHP necesarias
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo pdo_mysql zip

# Habilitar mod_rewrite (por si acaso se usa en el futuro)
RUN a2enmod rewrite

# Establecer directorio de trabajo
WORKDIR /var/www/html
