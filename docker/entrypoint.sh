#!/bin/sh
set -e

# 1. Configurar puerto dinámico para Nginx (Render asigna la variable $PORT)
TARGET_PORT=${PORT:-10000}
echo "=> Configurando Nginx para escuchar en el puerto ${TARGET_PORT}..."
sed -i "s/__PORT__/${TARGET_PORT}/g" /etc/nginx/http.d/default.conf

# 2. Asegurar existencia de directorios de almacenamiento y caché
mkdir -p /var/www/html/storage/framework/cache/data
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/bootstrap/cache

# 3. Asignar permisos al usuario de servidor web (www-data)
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# 4. Enlace simbólico de storage
echo "=> Verificando enlace simbólico de storage..."
php artisan storage:link --force || true

# 5. Descubrimiento de paquetes y Filament
echo "=> Ejecutando package:discover y filament:upgrade..."
php artisan package:discover --ansi || true
php artisan filament:upgrade || true
php artisan filament:assets || true

# 6. Limpieza preventiva de cachés
php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true

# 7. Migraciones y Seeders si la base de datos está configurada
if [ -n "$DB_HOST" ]; then
    echo "=> Base de datos detectada ($DB_HOST). Ejecutando migraciones..."
    php artisan migrate --force || echo "Aviso: No se pudieron ejecutar migraciones, verificar credenciales de BD."

    if [ "$RUN_SEEDER" = "true" ]; then
        echo "=> RUN_SEEDER=true detectado. Ejecutando seeders iniciales..."
        php artisan db:seed --force || echo "Aviso: Error durante la ejecución de seeders."
    fi
fi

# 8. Optimización de rutas, vistas y configuración para producción
echo "=> Optimizando configuración y vistas de Laravel..."
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# 9. Iniciar Supervisor (PHP-FPM + Nginx)
echo "=> Iniciando servicios web (PHP-FPM + Nginx)..."
exec /usr/bin/supervisord -c /etc/supervisord.conf
