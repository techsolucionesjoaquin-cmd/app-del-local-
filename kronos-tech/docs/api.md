# API REST · Contrato inicial

## 1. Convenciones HTTP

Base `/api/v1`. Las rutas de las tablas son relativas a esa base. JSON en UTF-8 y cookies de sesión del mismo origen. `fetch` incluye `credentials: 'same-origin'` y `X-CSRF-Token` en mutaciones. Todas las rutas requieren sesión excepto `GET /auth/csrf` y `POST /auth/login`; este último todavía exige CSRF. Autorización por permiso, entidad y campos según la [matriz de roles](arquitectura.md#5-matriz-de-roles-y-permisos). Los cuerpos mencionados son el contrato inicial, no endpoints desplegados.

- `GET` no cambia estado de negocio. Cuerpos JSON salvo subidas `multipart/form-data`. Las descargas de CSV y archivos privados son las únicas respuestas de recurso no JSON; ante fallo, el error sigue siendo JSON. Los comprobantes internos devuelven datos autorizados para imprimir una vista HTML estática.
- ID y dinero se representan como cadenas. Importes no admiten símbolos, separadores de miles ni exponente; se valida escala de moneda. Las cantidades y parámetros de paginación son enteros acotados. Timestamps en RFC 3339 con offset explícito; los rangos son `[from,to)`.
- Listas: `page=1`, `per_page=25` por defecto, máximo 100; `sort` con campos permitidos y desempate estable por ID. `q` acotado; rangos se traducen a UTC. `meta` de listas contiene `page`, `per_page`, `total` y `total_pages` con el mismo alcance; demás respuestas pueden usar `meta: null`. Las exportaciones usan filtros equivalentes, límites y neutralización de celdas interpretables como fórmulas.
- **`K`** en tablas = encabezado `Idempotency-Key` obligatorio. Se recomienda una clave aleatoria por intención; persistir la misma en reintentos de la misma solicitud. Autorizar antes de replay; ventana de reintentos duradera en comandos financieros. Un replay devuelve la respuesta mínima original y `Idempotency-Replayed: true` sin repetir efectos, aun cuando el turno original haya cerrado.
- **`V`** = `If-Match` obligatorio con ETag de la versión recuperada, por ejemplo `"order-482-v3"`. En comandos K se verifica replay antes de reevaluar precondiciones del negocio. Una diferencia de versión impide sobrescribir trabajo de otra persona. El servidor no acepta campos desconocidos en cuerpos de mutación.
- `X-Request-Id` identifica la solicitud para soporte. Los errores exponen códigos estables, no SQL, rutas internas ni secretos. El cliente conserva el formulario no secreto ante un 422; tras 401 vuelve a login; ante 409 o 412 ofrece refrescar/revisar, no reenvía con una clave nueva automáticamente.

```json
{
  "data": [{"id":"482","number":"OT-000482","state":"diagnostico"}],
  "message": null,
  "errors": [],
  "meta": {"page":1,"per_page":25,"total":1,"total_pages":1}
}
```

```json
{
  "data": null,
  "message": "No hay disponibilidad para la cantidad solicitada.",
  "errors": [{"field":"items[0].quantity","code":"insufficient_stock","detail":"Revisá la cantidad o la ubicación."}],
  "meta": null
}
```

| HTTP | Uso previsto |
|---|---|
| `200`, `201` | Lectura/comando correcto; creación con `Location` cuando hay recurso. `200` sin turno devuelve `data: null`. |
| `400`, `413`, `415` | JSON ilegible, cuerpo demasiado grande, tipo de contenido no soportado. |
| `401`, `403`, `404` | Sin sesión o vencida; permiso/CSRF inválido; inexistente o fuera de alcance para no filtrar existencia. |
| `409` | Stock insuficiente, turno cerrado, estado incompatible, duplicado exacto, precio cambiado o clave idempotente reutilizada con otro contenido. |
| `412`, `428` | ETag desactualizado o ausente en una operación que lo exige. |
| `422` | Validación de campos, importes, RUT, firma o archivo inválido; incluye detalle por campo/fila. |
| `429`, `500`, `503` | Límite temporal (con `Retry-After`); fallo inesperado; comando temporalmente ocupado tras reintentos acotados (reintentar con la misma clave). |

## 2. Identidad, clientes y equipos

| Método y ruta | Solicitud y respuesta `data` | Permiso y efectos |
|---|---|---|
| `GET /auth/csrf` | Devuelve `{csrf_token}` de la sesión anónima o activa; `no-store`. | Público; sin efecto de negocio. |
| `POST /auth/login` | `{login,password}` → identidad, roles, permisos, nuevo token CSRF y turno visible o `null`. | CSRF anónimo, límite de intentos, sesión regenerada; `401` genérico. |
| `POST /auth/logout` · `GET /auth/me` | Cerrar e invalidar sesión → confirmación. Leer → identidad, permisos, token y turno activo visible. | Usuario activo. Logout no cierra caja; un turno es distinto a una sesión web. |
| `GET /customers` · `POST /customers` | `q`, paginación → clientes y señales de coincidencia. Alta con nombre, tipo/emisor/documento, teléfono, email, dirección/comuna y observaciones. | `customers.read/write`; documento exacto repetido → `409`, RUT inválido → `422`. Sin pagos/stock. |
| `GET /customers/{id}` · `PATCH /customers/{id}` **V** | Perfil y vínculos paginados por separado (`GET /customers/{id}/{devices,service-orders,quotes,sales,receivables}`); cambios permitidos → perfil. | Permiso de clientes más permiso del módulo relacionado; aplicar alcance a cada colección. |
| `GET /devices` · `GET /devices/{id}` · `POST /devices` · `PATCH /devices/{id}` **V** | `customer_id`, `q`; crear/editar tipo, marca, modelo, serie, SO y observaciones. Devuelve equipo. | `devices.read/write`; sin reasignar propietario desde la API inicial. |
| `GET /technicians` | Lista mínima de técnicos habilitados (ID y nombre); `q`, paginación. | `orders.assign` o `projects.manage`; no expone datos administrativos. |

## 3. Órdenes y evidencia

| Método y ruta | Solicitud y respuesta `data` | Permiso, conflicto o efecto |
|---|---|---|
| `GET /service-orders` · `GET /service-orders/export` | `q` (número, cliente, equipo o serie), `state`, `priority`, `technician_id`, `from`, `to`; lista paginada o CSV. | `orders.read` / `orders.export`; borradores propios o admin y alcance por asignación. |
| `POST /service-orders` **K** · `PATCH /service-orders/{id}` **V** | Crea borrador con campos parciales; edita campos permitidos por fase (`customer_id`, `device_id`, falla, recepción, `items`, prioridad, técnico, estimación, fecha, etc.). Devuelve ID/versión y ETag. | `orders.receive` o permiso específico del campo tras confirmar (`orders.assign`, `orders.tech`); nunca se escribe saldo, estado ni contraseña por PATCH. Borrador no toca stock ni caja. |
| `DELETE /service-orders/{id}` **V** | Descarta un borrador propio o administrado; responde con estado del descarte. | `orders.receive` más alcance; no descarta una OT confirmada. Los archivos de borrador se limpian según política. |
| `GET /service-orders/{id}` · `GET /service-orders/{id}/receipt` | Detalle con estado, tareas/notas, accesorios/archivos y resumen histórico según permiso; comprobante desde instantánea de ingreso. | `orders.read` y proyección de rol. Técnicos no reciben pagos ni costos; nunca devuelve el secreto por estas rutas. |
| `POST /service-orders/{id}/confirm` **K V** | `consents[]` con tipo, decisión y versión cuando aplique; `signature_file_id` si se exige; `advance` opcional con `cash_session_id` y `payments[]`; `temporary_access` opcional. Devuelve OT, estado, cuenta y comprobante. | `orders.receive` y permisos de cobro/acceso si se usan. `409` por turno/estado; `422` por conformidad incompleta. Si hay anticipo, se imputa en el mismo commit. Sin movimiento de stock. |
| `POST /service-orders/{id}/transitions` **K** | `{to,reason,delivery_evidence?}` → estado y evento. Recepción y envío de presupuesto se manejan con sus comandos específicos. | `orders.tech` / `orders.receive` / `orders.deliver` / `orders.cancel` según transición. Cancelación libera reservas, no devuelve dinero ni consumo automáticamente. |
| `POST /service-orders/{id}/tasks` · `PATCH /service-orders/{id}/tasks/{task_id}` **V** · `POST /service-orders/{id}/notes` | Crear/editar tarea con responsable, estado, progreso y prueba; nota `{text}` → elemento con autor/fecha. | `orders.tech` asignado en tareas; `orders.tech` u `orders.receive` según nota y alcance. No genera movimientos. |
| `POST /service-orders/{id}/photos` · `GET /service-orders/{id}/photos/{photo_id}/content` | Cargar `file` y fase/descripción → metadatos; descargar contenido autorizado. | `orders.receive` (ingreso) o `orders.tech` asignado (pruebas); lectura con `orders.read` y alcance. `413`/`422` según el archivo. |
| `POST /service-orders/{id}/signatures` · `GET /service-orders/{id}/signatures/{file_id}/content` | Subir firma PNG privada → `file_id` para usar en confirmación; descargar por finalidad. | Escritura y lectura ordinaria solo para `orders.receive` con alcance; admin incluido. Solo firma de la misma orden. |
| `PUT /service-orders/{id}/temporary-access` · `POST /service-orders/{id}/temporary-access/reveal` · `DELETE /service-orders/{id}/temporary-access` | Establecer `{secret}` o retirar → confirmación sin valor; revelar con `{reauth_password,reason}` → valor puntual con `Cache-Control: no-store`. | `orders.access.write` / `orders.access.reveal` más alcance, sesión válida y reautenticación en revelado. Solo órdenes confirmadas no terminales. No guardar campos sensibles en auditoría ni respuestas reutilizables. |

## 4. Inventario y catálogo

| Método y ruta | Solicitud y respuesta `data` | Permiso y efecto |
|---|---|---|
| `GET /products` · `GET /products/{id}` · `POST /products` · `PATCH /products/{id}` **V** | `q`, `category_id`, `location_id`, `active`, `stock_alert`; ficha, disponibilidad y catálogo. Escritura con SKU, referencias, precios, mínimo y estado. | `catalog.read` / `catalog.manage`, `costs.read` para costos; no acepta cantidad física. |
| `GET /{categories,suppliers,locations,service-catalog}` · `POST` en la colección · `PATCH …/{id}` **V** | Lectura y mantenimiento por código/nombre; en catálogo de servicios, precio/impuesto y activo. Especificaciones por producto en `GET/PUT /products/{id}/technical-specs` (**V** en PUT). | Lectura de catálogo según proyección; edición `catalog.manage`. |
| `GET /inventory/alerts` · `GET /stock-movements` · `POST /stock-movements` **K** | Alertas/historial por producto/ubicación/tipo/fechas. Escribir `{type,product_id,location_id,quantity,reason,returned_from_id?}` → movimiento y posición. | `stock.read` / `stock.adjust`. API directa solo admite entrada, ajuste y devolución autorizada de taller; venta/consumo/reversión por comandos propios. |
| `GET /stock-reservations` · `POST /stock-reservations` **K** | Filtros por orden, proyecto, producto y estado. Crear con un ítem tipado, ubicación y cantidad → reserva. | `stock.read` según alcance / `stock.reserve`; aumenta reservado, no físico. `409` si falta disponibilidad. |
| `POST /stock-reservations/{id}/release` · `POST /stock-reservations/{id}/consume` **K en ambos** | `{quantity,reason}` → evento, reserva y posición actualizadas. | `stock.reserve` / `stock.consume` y alcance asignado. Liberar no toca físico; consumir descuenta físico y reservado. |
| `GET /products/export` · `POST /products/imports/preview` · `POST /products/imports/{id}/commit` **K en commit** | CSV con filtros y columnas autorizadas. Preview recibe archivo → lote, filas válidas/errores; commit revalida y devuelve reporte final. | `catalog.read` / `stock.import`; no incorpora existencias sin movimiento. |
| `GET /purchase-requests` · `POST /purchase-requests` **K** · `PATCH /purchase-requests/{id}` **V** | Líneas `{product_id,quantity}` con motivo → solicitud; gestión de estado. | `purchase.request` lectura propia/alta, `purchase.manage` gestión; sin correo a proveedores ni cambio de stock. |

## 5. Armados y presupuestos

| Método y ruta | Solicitud y respuesta `data` | Permiso y efecto |
|---|---|---|
| `GET /pc-projects` · `GET /pc-projects/{id}` · `POST /pc-projects` **K** · `PATCH /pc-projects/{id}` **V** | Filtros por cliente/estado/técnico/fecha. Alta/edición de tipo, equipo base, uso, técnico e ítems. Devuelve proyecto y versión. | `projects.read/manage`, o `projects.tech` para campos técnicos autorizados en recurso asignado; no copia costos a técnicos ni crea pagos. |
| `GET /pc-projects/{id}/compatibility` · `POST /pc-projects/{id}/updates` | Evaluación por regla y evidencia; agregar nota, prueba o avance. | `projects.read` / `projects.tech` asignado; sin movimiento automático. |
| `POST /pc-projects/{id}/{transitions,duplicate,archive}` **K cada ruta** | Estado y motivo, nueva solicitud duplicada, o registro de archivo. | `projects.tech/manage/deliver/cancel` según acción. Duplicar no transfiere compromisos; cancelar libera reservas; entregar exige liquidación. |
| `GET /quotes` · `GET /quotes/{id}` · `POST /quotes` **K** · `PATCH /quotes/{id}` **V** | Filtros por destino/estado/vencimiento. Alta con un destino, líneas catalogadas, vigencia, descuento autorizado → revisión. | `quotes.read/manage`; costos con `costs.read`, descuento con `pricing.discount`. Solo el borrador se edita. No genera saldo por crear. |
| `POST /quotes/{id}/send` **K** · `GET /quotes/{id}/print-data` | Registra envío manual real `{channel,occurred_at,reference}`; datos de impresión sin costos desde revisión emitida. | `quotes.manage` / `quotes.read`; no ejecuta envío externo. Actualiza estado del destino cuando corresponde. |
| `POST /quotes/{id}/approvals` **K** | `{decision,channel,occurred_at,reference}` → decisión y cuenta/resumen si se aprueba. | `quotes.approve`; `409` si venció, se desplazó o no cumple guardas. Aprobación fija base firme; no cobra ni consume stock. |

## 6. POS, pagos y caja

| Método y ruta | Solicitud y respuesta `data` | Permiso y efecto |
|---|---|---|
| `GET /sales` · `GET /sales/{id}` · `GET /sales/{id}/receipt` | Historial `from`, `to`, `customer_id`, `state`; detalle y datos imprimibles. | `sales.read`; sin datos de medio sensibles. |
| `POST /sales/preview` | Ítems producto/servicio, cantidad, ubicación y descuento opcional → desglose, `pricing_fingerprint` y disponibilidad indicativa. | `sales.create`; `pricing.discount` si solicita descuento. No reserva, cobra ni fija un precio definitivo. |
| `POST /sales` **K** | Carrito, `pricing_fingerprint`, `expected_total`, turno explícito, cliente o `null`, `payments[]` → venta, total y cambio. | `sales.create`, `payments.post`, turno válido y descuento autorizado. Revalida huella y disponibilidad; graba venta, pagos y salida juntos. |
| `POST /sales/{id}/void` **K** | `{cash_session_id,reason,refund_references?,return_confirmed}` → venta anulada y reversión. | `sales.void`, `payments.refund`; devuelve stock vendido y compensa cobros en turno vigente. No reabre el antiguo. |
| `GET /receivables` · `GET /receivables/{id}` | Búsqueda por cliente/número y filtro de saldo; base vigente, neto, pendiente/a favor, medios. | `payments.post` o `reports.read` más alcance del documento; solo roles con proyección financiera. |
| `POST /payments` **K** | `{receivable_id,cash_session_id,payments:[…]}` → pagos y saldo. Efectivo `{method:"cash",amount,tendered}`; débito/transferencia `{method,amount,reference}`. | `payments.post`; solo cuenta de orden o armado vigente no terminal. POS y anticipo de recepción usan sus propios comandos compuestos. No duplica venta. |
| `POST /payments/{id}/refunds` **K** | `{cash_session_id,amount,reason,reference?}` → devolución imputada a la cuenta original. | `payments.refund`; solo pagos de servicio/armado no terminal, para POS usar anulación. Valida remanente, turno y medio. |
| `GET /cash-registers` · `GET /cash-sessions/current` · `GET /cash-sessions` · `GET /cash-sessions/{id}` | Cajas habilitadas, turno propio, historial por caja/fechas, detalle por medio y arqueos. | `cash.read` con alcance; `current` no cambia turno. |
| `POST /cash-registers` · `PATCH /cash-registers/{id}` **V** | Código, nombre y estado → caja. | `settings.manage`; no deshabilitar con un turno abierto. |
| `POST /cash-sessions` **K** | `{register_id,opening_amount}` → nuevo turno. | `cash.operate`; evita duplicar turno por caja o responsable. Apertura no crea pago. |
| `POST /cash-sessions/{id}/movements` **K** | `{direction,amount,reason}` → movimiento manual y resumen. | `cash.operate` y turno propio o `cash.override` con motivo. Solo efectivo, no imputable a ventas. |
| `POST /cash-sessions/{id}/counts` · `POST /cash-sessions/{id}/close` **K en ambos** | `{counted_amount,observations?}` → esperado, contado, diferencia, totales por método y hora de corte; close finaliza. | `cash.operate` con alcance (override explícito si aplica). Exige observación si hay diferencia de cierre. No admite nuevos cobros tras cerrar. |

Ejemplo de confirmación POS: el `pricing_fingerprint` se toma de la previsualización del mismo carrito; si cambia el catálogo o la política relevante, no se confirma hasta revisarlo. Si un administrador actúa en un turno ajeno, el comando declara ese `cash_session_id` (o ruta de turno) y `override_reason` de forma explícita. Métodos: `cash`, `debit`, `transfer`. Un medio a lo sumo una vez por comando. Se exige referencia no sensible de débito/transferencia, sin número de tarjeta ni datos de cuenta.

```http
POST /api/v1/sales
Content-Type: application/json
X-CSRF-Token: <token-de-sesion>
Idempotency-Key: <clave-de-esta-confirmacion>
```

```json
{
  "cash_session_id":"14",
  "customer_id":null,
  "items":[
    {"product_id":"53","location_id":"2","quantity":1},
    {"service_id":"9","quantity":1}
  ],
  "discount":{"amount":"3570","reason":"Descuento autorizado"},
  "pricing_fingerprint":"<huella-del-preview>",
  "expected_total":"32130",
  "payments":[
    {"method":"transfer","amount":"20000","reference":"REF-DEMO-14"},
    {"method":"cash","amount":"12130","tendered":"15000"}
  ]
}
```

```json
{
  "data":{"id":"721","number":"VTA-000721","currency":"CLP","subtotal_net":"27000","tax":"5130","total":"32130","cash_change":"2870","state":"confirmed"},
  "message":"Venta confirmada.",
  "errors":[],
  "meta":null
}
```

## 7. Dashboard, agenda y administración

| Método y ruta | Solicitud y respuesta `data` | Permiso y efecto |
|---|---|---|
| `GET /dashboard` | Zona y período del día, indicadores, agenda, órdenes recientes y alertas permitidas. | `dashboard.read`; restringe alcance y datos por rol. |
| `GET /appointments` · `POST /appointments` **K** · `PATCH /appointments/{id}` **V** | Filtros `from`, `to`, `kind`, `status`, cliente/orden. Alta/edición con hora, tipo, cliente, orden opcional, responsable, estado. | `agenda.read/write`, FK y alcance según cliente/orden. Sin envío de recordatorios. |
| `GET /audit-logs` · `GET /reports/{sales,stock,cash,receivables}` | Historial o agregados por fecha, actor/entidad y filtros válidos por reporte. | `audit.read` / `reports.read`; no hay API de borrado de auditoría. |
| `GET/POST /users` · `PATCH /users/{id}` **V** · `PUT /users/{id}/roles` **V** · `GET/POST /roles` · `PATCH /roles/{id}` **V** · `GET /permissions` · `PUT /roles/{id}/permissions` **V** | Alta/desactivación y actualización de acceso; permisos/roles por IDs autorizados. | `users.manage`; audita modificaciones y no permite dejar sin administrador. Hash y claves nunca en respuestas. |
| `GET /settings` · `PATCH /settings` **V** | Parámetros operativos y versión; cambios con allowlist. | `settings.manage`. No retorna ni recibe secrets de infraestructura. |

Notación con llaves separadas por comas indica rutas distintas, no un valor literal del parámetro. Las rutas fijas (`export`, `current`, `preview`) se resuelven antes de las rutas con ID. Para controles `V` en recursos secundarios, su lectura de detalle o la representación del recurso padre incluye la versión del elemento a editar. La especificación OpenAPI con esquemas exhaustivos se mantiene junto con los casos implementados en las etapas posteriores; este contrato fija rutas, autoridades, motivos de rechazo y efectos esperados.
