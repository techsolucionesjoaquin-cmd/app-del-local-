# KRONOS TECH

Aplicación interna para servicio técnico informático, armados, ventas, inventario y caja. El frontend es HTML/CSS/JavaScript ES; la API usa PHP 8.2+ y PDO con MySQL 8. La vista previa se ejecutó con PHP 8.4 y MySQL 8.4.

## Levantar la demo

### Con Docker Compose (receta de desarrollo)

```bash
export APP_KEY="$(openssl rand -base64 32)"
export DB_PASSWORD="$(openssl rand -hex 24)"
export DB_ROOT_PASSWORD="$(openssl rand -hex 24)"
docker compose up --build
```

Abrí `http://localhost:3000`. El modo demo carga datos ficticios e inicia como **Alex Molina**. Mantener las mismas variables mientras exista el volumen; se pueden guardar en un `.env` local no versionado. Esta receta no es un despliegue público: la demo habilita acceso como administrador sin contraseña explícita. Para revisar otros roles, cerrá la sesión y usá `recepcion` o `tecnico` con la contraseña **solo de demostración** `Kronos-demo-2026`.

### Con PHP y MySQL instalados

1. Creá una base MySQL 8 vacía `kronos` en `utf8mb4`; aplicá, en orden, `database/migrations/*.sql` como usuario de migración. `database/schema.sql` es una alternativa para una base vacía, no se aplica además de las migraciones.
2. Cargá las variables de `backend/.env.example` en el proceso. Definí `APP_KEY` con `openssl rand -base64 32`. Para demo local usá `APP_ENV=development`, `APP_DEMO=1`, `APP_HTTPS=0`, `APP_ORIGIN=http://localhost:3000` y el DSN de tu MySQL. El archivo `.env.example` no se carga automáticamente.
3. Desde la raíz del proyecto:

```bash
php database/seeders/demo.php
PHP_CLI_SERVER_WORKERS=4 php -d upload_max_filesize=5M -d post_max_size=6M \
  -S 0.0.0.0:3000 -t frontend backend/bin/serve.php
```

No usar `php -S` como servidor de producción. El servidor dev, el migrador SQL y el seed se verificaron por separado en el entorno de trabajo. La receta Compose se incluye para facilitar instalación; no se ejecutó en ese entorno.

## Qué podés probar

- Abrir o retomar una orden: cliente, equipo, recepción, fotos, asistencia en cuatro pasos, firma opcional, registro de anticipo y comprobante de recepción.
- Registrar diagnóstico, tareas, notas, cambios de estado, presupuestos y reservas de taller. Un cobro se imputa a la misma cuenta de la orden o del armado.
- Mantener productos, servicios, categorías, proveedores, ubicaciones, ajustes, CSV y solicitudes internas de compra.
- Armar o duplicar un proyecto; editar componentes, verificar datos técnicos, registrar pruebas y presupuestos.
- Confirmar/anular una venta, liquidar efectivo/débito/transferencia, abrir caja, registrar un movimiento y cerrar. Los comprobantes son internos, no tributarios.
- Consultar dashboard, agenda, auditoría y cuentas de usuario. Los roles iniciales son Administrador, Recepción / ventas y Técnico.

## Estado y documentación

- [Arquitectura de la aplicación entregada](docs/arquitectura-app.md) · [Dossier inicial de arquitectura](arquitectura-kronos-tech.html) (diseño previo, disponible sin conexión en el paquete completo).
- [API implementada](docs/api-implementada.md) · [Resultados de verificación](docs/verificacion.md) · [Instalación y producción](docs/instalacion-app.md).
- `frontend/` es público; solo las rutas `/api/v1/` llegan a `backend/public/index.php`. Las pantallas usan navegación hash y módulos ES para compartir el mismo armazón sin PHP en HTML.
- El diseño de referencia quedó en `docs/arquitectura.md`; es el objetivo, no el listado de funciones aprobadas. Esta versión aún no incorpora reportes avanzados, gestión de roles personalizados, ni integración fiscal, pasarela, correo o WhatsApp. El ajuste visual a Figma queda pendiente de recibir sus pantallas. No cargar datos reales en la vista previa.
