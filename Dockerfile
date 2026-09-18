# =========================================================
# ETAPA 1: Compilación de Assets con Node.js y Vite
# =========================================================
FROM node:20-alpine AS frontend-builder
WORKDIR /app

# Instalar dependencias de Node
COPY package*.json ./
RUN npm install

# Copiar archivos fuente necesarios para el empaquetado
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public

# Compilar assets de producción (Tailwind + Filament/Livewire)
RUN npm run build

# =========================================================
# ETAPA 2: Contenedor de Producción PHP 8.4 + Nginx + Supervisor
# =========================================================
FROM php:8.4-fpm-alpine

WORKDIR /var/www/html

# Instalar Nginx, Supervisord y paquetes del sistema
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libwebp-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    oniguruma-dev \
    libzip-dev \
    icu-dev \
    postgresql-dev \
    linux-headers

# Configurar e instalar extensiones de PHP requeridas
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        pgsql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        intl \
        zip \
        opcache

# Instalar Composer desde imagen oficial
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

# Copiar el código de la aplicación
COPY . /var/www/html

# Copiar los assets compilados desde la Etapa 1
COPY --from=frontend-builder /app/public/build /var/www/html/public/build

# Instalar dependencias PHP optimizadas para producción (sin scripts de dev)
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

# Configurar Nginx y Supervisor
RUN mkdir -p /run/nginx
COPY docker/nginx-render.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf

# Configurar script de inicio
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Asegurar permisos en carpetas de almacenamiento y caché
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Render expone la aplicación por defecto en el puerto 10000
EXPOSE 10000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
