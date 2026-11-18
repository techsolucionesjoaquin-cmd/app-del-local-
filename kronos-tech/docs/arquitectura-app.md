# KRONOS TECH · Arquitectura de la aplicación entregada

Esta guía describe la versión incluida en el ZIP. El [dossier inicial](../arquitectura-kronos-tech.html) conserva el diseño previo a la implementación. Para instalar o probar el sistema, empezá por el [README](../README.md).

## Componentes y recorrido de una solicitud

```text
Navegador ───── HTML, CSS y JavaScript ES ───── frontend/
    │
    │ fetch /api/v1 (mismo origen; cookie + CSRF)
    ▼
backend/public/index.php → Router + Auth → backend/routes/api.php
    │
    ▼
Servicios: Desk · Inventory · Projects · Quotes · Commerce · Overview
    │
    ▼
Repositories/Db.php (PDO, transacciones, idempotencia) → MySQL 8 / InnoDB
```

- `frontend/index.html` carga la interfaz. `frontend/assets/js/modules/` contiene las pantallas y sus flujos; la navegación por hash comparte un armazón sin mezclar PHP con HTML.
- La API verifica sesión, permisos, alcance de recurso y token CSRF antes de ejecutar comandos. Los servicios concentran las reglas de negocio y usan PDO con parámetros preparados a través de `Db`.
- Las fotos se guardan en `backend/storage/uploads/` fuera de la raíz web. Las firmas se almacenan como imágenes en la base. Las descargas pasan por la API autenticada. Las credenciales temporales de equipos se cifran con una clave externa a la base y no están en el comprobante ni en los listados.
- `database/migrations/` contiene el esquema que se instala; `database/schema.sql` es su versión consolidada. `database/seeders/demo.php` carga datos ficticios únicamente en el modo de demostración habilitado.

## Relaciones y reglas que conectan los módulos

| Flujo | Fuente de verdad y efecto |
|---|---|
| Recepción | Cliente → equipo → orden. Confirmar conserva una instantánea de recepción y abre una cuenta de cobro. El borrador no crea pagos ni movimientos de stock. |
| Presupuestos y pagos | Cada presupuesto pertenece a una orden o a un armado. Aprobar fija una revisión del importe de la misma cuenta. Los pagos se imputan a esa cuenta y a su turno; no generan otra venta. |
| Inventario | Las posiciones conservan físico y reservado. Una reserva afecta solo lo disponible; un consumo reduce físico y reservado con un movimiento vinculado. |
| Punto de venta | La confirmación revalida precio y stock, registra venta, pagos, movimientos y auditoría en una transacción. Cada comando crítico admite reintentos idempotentes. |
| Caja | Los pagos y movimientos manuales son fuentes distintas. Efectivo neto, contando devoluciones, entra al arqueo; débito y transferencia se informan separados. |

## Documentos de referencia

- [Contrato de la API implementada](api-implementada.md), [instalación y puesta en servicio](instalacion-app.md) y [verificaciones realmente ejecutadas](verificacion.md).
- Diagramas del diseño: [componentes](diagramas/arquitectura.svg), [operación](diagramas/er-operacion.svg), [inventario](diagramas/er-inventario.svg) y [finanzas](diagramas/er-finanzas.svg). Para conocer los nombres y restricciones SQL exactos, consultá `database/schema.sql`.
- La fidelidad a Figma no se validó. La app registra cobros informados, pero no procesa pagos externos ni emite documentos tributarios. El README distingue el alcance implementado y lo pendiente.
