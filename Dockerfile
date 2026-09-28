FROM php:8.2-apache

# Composer desde la imagen oficial (sin instalarlo a mano)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Dependencias del sistema y extensiones de PHP necesarias.
# zip: lectura de .xlsx y generación de las exportaciones a Excel.
RUN apt-get update && apt-get install -y --no-install-recommends \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

# Apache endurecido.
#
# La imagen oficial trae `AllowOverride None` para /var/www/, y con eso
# Apache IGNORA todos los .htaccess del proyecto: el que cierra .env, .git
# y las carpetas internas, el que impide ejecutar PHP en uploads/ y el que
# protege logs/. En producción no protegían nada. Se habilitan aquí.
RUN a2enmod rewrite headers \
    && { \
        echo '<Directory /var/www/html>'; \
        echo '    AllowOverride All'; \
        echo '    Options -Indexes'; \
        echo '</Directory>'; \
        echo 'ServerTokens Prod'; \
        echo 'ServerSignature Off'; \
        echo 'TraceEnable Off'; \
    } > /etc/apache2/conf-available/sena.conf \
    && a2enconf sena

# PHP de producción: sin versión en las cabeceras, sin errores en pantalla
# y con límites de subida coherentes con los que valida la aplicación.
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && { \
        echo 'expose_php = Off'; \
        echo 'display_errors = Off'; \
        echo 'log_errors = On'; \
        echo 'upload_max_filesize = 10M'; \
        echo 'post_max_size = 12M'; \
        echo 'max_file_uploads = 5'; \
        echo 'memory_limit = 256M'; \
        echo 'session.cookie_httponly = 1'; \
        echo 'session.use_strict_mode = 1'; \
    } > "$PHP_INI_DIR/conf.d/sena.ini"

WORKDIR /var/www/html

COPY . /var/www/html/

# Autoloader PSR-4 y dependencias de producción. El script post-autoload-dump
# (bin/proteger-carpetas.php) deja un .htaccess de denegación en vendor/ y cache/.
RUN composer install --no-dev --optimize-autoloader --no-interaction

RUN mkdir -p uploads logs cache \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 750 uploads logs cache
