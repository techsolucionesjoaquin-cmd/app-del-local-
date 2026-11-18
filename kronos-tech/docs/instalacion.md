# Instalación y despliegue · diseño previsto

Este documento define requisitos de operación para la implementación. **Todavía no hay una aplicación, migraciones ni comandos de proyecto para ejecutar.**

## Entorno previsto

| Componente | Requisito de diseño |
|---|---|
| Web | Nginx o Apache, TLS en producción. Publicar exclusivamente `frontend/` y mapear `/api/v1/*` al front controller; no exponer el árbol completo del proyecto. |
| Aplicación | PHP 8.2+ con sesiones, `pdo_mysql`, `mbstring`, `bcmath`, `sodium`, `fileinfo` y decodificación segura de los formatos aprobados (p. ej. GD con JPEG, PNG y WebP). Composer para autoload y dependencias de desarrollo; sin entorno Node de ejecución. |
| Datos | MySQL 8 con InnoDB, `utf8mb4`, sesión SQL UTC, estricto y claves foráneas. Separar usuario de aplicación, migración y backups. |
| Archivos | Directorios privados de uploads, logs y exports; escritura solo para el proceso autorizado, no permiso de ejecución. HTTPS para sesiones de producción. |

## Configuración a documentar al implementar

- `.env.example`: nombres, valores de ejemplo inocuos y requerimientos de `APP_ENV`, `APP_ORIGIN`, conexión `DB_*`, almacenamiento privado, límites de sesión/subida, versión y referencia a claves externas. Secretos de MySQL, cifrado y HMAC no van en el frontend ni en el control de versiones. El entorno productivo valida requisitos y falla de forma segura si falta configuración.
- Configuración inicial de negocio: `America/Santiago`, `CLP` escala 0, IVA 19 % incluido por defecto, políticas de recepción y catálogo de roles. Configuración operativa en `settings` según allowlist; no confundir con secretos de infraestructura.
- En desarrollo, mantener mismo origen con configuración del servidor o enrutador solo local. Base de prueba aislada, fixtures ficticios opt-in, administrador inicial creado con comando de bootstrap autenticado o acceso administrativo al host; no habilitar instaladores ni credenciales demo por HTTP en producción.

## Secuencia operativa prevista

1. Crear base y usuarios; configurar variables, directorios y almacenamiento de claves. Aplicar **solo** migraciones a una base vacía; verificar versión y checksum.
2. Publicar el frontend y registrar ruta PHP-FPM para API. Probar que `.env`, `backend/storage`, logs y archivos de migración no sean accesibles por HTTP.
3. Dar de alta administrador, cajas y catálogo inicial; validar el flujo de sesiones y el rango horario local. Cargar fixtures únicamente en un entorno de demo/pruebas.
4. Programar limpieza de credenciales expiradas, archivos temporales/lotes y revisión periódica de conciliación. Supervisar errores, rechazos de sesión, espacio y fallos de transacción sin capturar contenidos sensibles.
5. Respaldar MySQL y almacenamiento privado de forma coordinada, cifrar copias, versionar claves con acceso separado y probar restauración antes de operar con datos reales. Definir retención y recuperación con el negocio.

Antes del primer despliegue con datos reales se debe confirmar privacidad, datos de prueba, reglas comerciales y manejo de documentos. La integración fiscal no se activa para simular una emisión; requiere una habilitación independiente.
