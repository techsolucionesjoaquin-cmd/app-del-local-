# Instalación y puesta en servicio

Las instrucciones para reproducir la demo están en el [README](../README.md). La vista previa no debe utilizarse en producción.

## Producción

1. Instalar PHP 8.2+ con PDO MySQL, BCMath, mbstring, Sodium, Fileinfo, sesiones y GD con JPEG/PNG/WebP; MySQL 8 con InnoDB. Aplicar las migraciones SQL en orden a una base vacía con una cuenta DDL, y usar otra cuenta DML para la aplicación. Respaldar antes de actualizar. No cargar datos demo.
2. Proveer por entorno de ejecución `APP_ENV=production`, `APP_DEMO=0`, `APP_HTTPS=1`, `APP_ORIGIN` con la URL exacta HTTPS, `APP_KEY` aleatoria de 32 bytes codificada en base64, `DB_DSN`, `DB_USER` y `DB_PASSWORD`. Los valores iniciales son Chile, `CLP`, escala 0 e IVA por defecto de 1900 puntos básicos. Configurar moneda/escala antes de registrar operaciones, sin mezclar documentos de instalaciones con monedas distintas.
3. Crear el administrador desde CLI con el ambiente de la app cargado: `php backend/bin/create-admin.php administrador "Nombre Apellido"`. Proveer `ADMIN_PASSWORD` por un método seguro y retirarla del entorno al finalizar. Se inicializan los roles base, una ubicación y una caja. No hay bootstrap accesible por HTTP.
4. Dar permisos de escritura al proceso PHP solo donde corresponda, en particular `backend/storage/uploads`. El frontend es la única raíz pública. Configurar TLS y enrutar a PHP-FPM únicamente `/api/v1/*`; nunca servir `.env`, `database/`, `docs/` o `backend/storage/`.
5. Agendar `php backend/bin/purge.php` para eliminar credenciales temporales vencidas y contadores de intentos. Respaldar MySQL junto con los archivos privados y custodiar la clave de cifrado de forma separada. Validar restauración, retención, privacidad, monitoreo y condiciones de red antes de autorizar carga real.

Ejemplo de bloques para el vhost HTTPS (ajustar rutas y socket al host, y configurar los encabezados con la política de producción):

```nginx
root /srv/kronos-tech/frontend;
index index.html;
client_max_body_size 6m;
location / { try_files $uri $uri/ =404; }
location ^~ /api/v1/ {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME /srv/kronos-tech/backend/public/index.php;
    fastcgi_param HTTPS on;
    fastcgi_pass unix:/run/php/php8.4-fpm.sock;
}
location ~ /\. { deny all; }
location ~ \.php$ { return 404; }
```

Los comprobantes se imprimen localmente. Habilitar un proveedor fiscal, banco o mensajería exige integración independiente, credenciales y validación; no hay emisiones ni cobros electrónicos simulados.
