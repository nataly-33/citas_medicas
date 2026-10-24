FROM php:8.2-apache

# Actualizar e instalar dependencias del sistema y biblioteca PostgreSQL
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Instalar y habilitar extensiones PHP requeridas (pdo_pgsql, pgsql)
RUN docker-php-ext-install pdo pdo_pgsql pgsql

# Habilitar mod_rewrite de Apache para URLs limpias
RUN a2enmod rewrite

# Copiar el código del proyecto al directorio raíz del servidor web
COPY . /var/www/html/

# Ajustar permisos para el usuario www-data
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Exponer el puerto estándar HTTP
EXPOSE 80

# Iniciar Apache en primer plano
CMD ["apache2-foreground"]
