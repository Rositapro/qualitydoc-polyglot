FROM php:8.2-apache
# Instalamos las librerías necesarias para que PHP pueda hablar con PostgreSQL
# Se agregó 'pgsql' a la lista de instalaciones
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql pgsql
# Habilitamos el módulo de reescritura de Apache
RUN a2enmod rewrite
# Copiamos todos tus archivos PHP al servidor dentro del contenedor
COPY . /var/www/html/
# Le damos permisos al servidor para que pueda leer tus archivos
RUN chown -R www-data:www-data /var/www/html
# Exponemos el puerto 80 para poder ver tu página en el navegador
EXPOSE 80