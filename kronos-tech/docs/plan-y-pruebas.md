# Plan de desarrollo y validación

## 1. Etapas y salidas verificables

Cada etapa entrega interfaz HTML/CSS/JS → API PHP → MySQL para el caso que incorpora. Incluye migraciones aplicables, fixture opt-in, actualización de documentación y pasos reproducibles para verificarla. Una capacidad pendiente se oculta o se indica con claridad; no se sustituye por datos falsos ni resultados simulados.

| Etapa | Entrega vertical | Criterio de salida y dependencias |
|---|---|---|
| **1 · Base y autenticación** | Raíz estática, enrutamiento `/api/v1`, PDO, migrador, roles base, login, logout y shell según sesión. | Instalar desde base vacía; sesión y CSRF verificables; usuario sin permiso rechazado por API. Sin necesidad de pantallas de Figma para la API; sí para cerrar la UI visual. |
| **2 · Clientes, equipos y órdenes** | CRUD pertinente, deduplicación, asistente en cuatro pasos, reanudación, fotos/consentimientos, orden confirmada sin cobro, comprobante y flujo de taller. | Recepción sin anticipo funciona de extremo a extremo. Anticipo desactivado hasta Caja; cambios de estado y asignación trazables. Listado, filtros, CSV y detalle. |
| **3 · Inventario y reservas** | Catálogos, posiciones, entradas/ajustes, reserva/liberación/consumo desde una orden, alertas, CSV con validación y compra interna. | Reconciliar escenarios físicos y reservados; no hay stock negativo ni importación de cantidades directas. Venta se conecta en etapa 5. |
| **4 · Presupuestos y armados** | Proyectos, fichas técnicas, validaciones de compatibilidad, versiones/aprobación, impresión, reservas de componentes y pruebas. | Los datos incompletos no resultan «compatible»; la duplicación crea una solicitud limpia. La cuenta se puede preparar sin simular que se cobró. |
| **5 · Ventas, pagos y caja** | POS, cobro de servicio/proyecto, anticipos reales al confirmar recepción, anulaciones y arqueos. | Pruebas de transacciones, anulaciones, reintentos, última unidad y cierre concurrente; cuadrar stock, caja y deudas contra los mayores. |
| **6 · Dashboard y agenda** | Métricas conectadas, órdenes recientes, alertas, agenda usable y actividad con alcance. | Fechas y filtros comprobados con fixtures; controles de proyección por rol; sin comparaciones inventadas. |
| **7 · Reportes y despliegue** | Reportes, revisión de seguridad, ajuste responsive con Figma, prueba de recuperación, operación y guía de instalación/despliegue. | Verificaciones automatizadas y manuales registradas; recorrido en desktop/tablet/móvil; instalar de cero y restaurar. Emisión fiscal, comunicaciones y pagos electrónicos reportados como pendientes si no están integrados. |

**Flujo de cambios.** Migraciones append-only con versión/checksum y bloqueo de migrador. Verificar desde base vacía y desde la versión anterior; MySQL puede hacer commit implícito en DDL, por lo que el plan de reversión combina cambios compatibles y copias/restore verificado, no una promesa de rollback DDL global. Un esquema de referencia se regenera a partir de migraciones. No poner credenciales reales en seeds ni en `.env.example`.

## 2. Estrategia para pruebas

- **Unitarias:** dinero sin flotantes, asignación de descuentos, validaciones de documentos, compatibilidad faltante, reglas de estados y saldo.
- **Integración MySQL real:** migraciones, `CHECK`, FK, aislamiento, unicidad y bloqueos. No probar concurrencia de inventario solamente con mocks o SQLite. Para carreras, usar conexiones independientes y coordinación reproducible: dos peticiones en paralelo y datos equivalentes, con roles/turnos válidos.
- **HTTP y navegador:** login y CSRF, permisos directos a la API, archivos, exportación, recorridos con teclado y varios tamaños. Probar 360, 768 y 1440 px como puntos iniciales, y ajustar a los frames reales de Figma. Contrastar cada métrica del tablero contra consultas de datos autorizadas.
- **Fixtures:** escenarios ficticios y deterministas, fecha de referencia parametrizada, email `example.test`. Cargar movimientos iniciales y reservas coherentes, no repartir saldos al azar. Para caja y descuentos usar un carrito de $11.900 + $23.800 y un descuento de $3.570; debe dar $32.130. Si un fixture registra pagos, debe incluir turno e imputación correctos.

## 3. Matriz de aceptación

**Estado de estas pruebas: diseñadas, no ejecutadas.** No hay aplicación ni migraciones implementadas en esta entrega. El resultado de cada caso se registrará con etapa, entorno, datos de entrada y evidencia de ejecución.

| ID | Escenario | Resultado verificable |
|---|---|---|
| T01 | Login por usuario/correo, logout y vencimiento. | Cookie regenerada, token nuevo, `401` tras cierre/vencimiento, sin cerrar automáticamente caja. |
| T02 | Roles, alcance y CSRF. | Técnico no modifica caja, no consulta órdenes ajenas ni costos, exportación no evade alcance; mutación sin CSRF falla; permisos revocados surten efecto. |
| T03 | Alta de cliente y equipo. | RUT válido aceptado, inválido rechazado solo como RUT; duplicado controlado; equipo ajeno no puede confirmarse en OT. |
| T04 | Guardar y retomar borrador, incluso en otra sesión autorizada. | Datos no secretos conservados, sin pagos/reservas; edición concurrente obsoleta obtiene `412`. |
| T05 | Confirmación de recepción y comprobante. | Datos/consentimientos requeridos; una OT y una instantánea; reintento no crea otra. |
| T06 | Cambios de estado. | Solo transiciones permitidas, con actor y fecha; terminales no reabren ni conservan credencial temporal. |
| T07 | Reservar, liberar y consumir. | Desde 5 físicas, reservar 3 deja 2 disponibles; consumir 2 reduce físico/reservado en 2, no disponible de nuevo; un movimiento por consumo. |
| T08 | Stock insuficiente y ajuste inválido. | Cantidades/resultados nunca negativos ni físico por debajo de reservado; rechazo sin filas parciales. |
| T09 | Dos ventas simultáneas por la última unidad. | Dos usuarios/turnos distintos y disponibilidad inicial 1; una venta confirmada, una `409`; solo una salida y un conjunto de pagos. |
| T10 | Anticipo y reintento antes/después de aprobación. | Anticipo $10.000 sobre cuenta; aprobación $32.130 → $22.130 pendientes, un pago en el turno original, sin duplicación. |
| T11 | Presupuestos, revisiones y aprobaciones. | Emitido inmutable; expirado/desplazado no se aprueba; revisión aprobada sustituye base, no crea otra deuda. |
| T12 | Compatibilidad con ficha incompleta y conflicto conocido. | Falta BIOS → «requiere verificación»; socket distinto → «incompatible»; duplicado sin aprobaciones, pagos ni reservas. |
| T13 | Venta con descuento e IVA. | Descuento prorrateado $1.190/$2.380, neto $27.000, IVA $5.130, total $32.130; descuento no autorizado falla. |
| T14 | Cambio y validación de efectivo. | Transferencia $20.000 + efectivo aplicado $12.130 con $15.000 entregados: cambio $2.870; recibido insuficiente se rechaza. |
| T15 | Reintento de venta tras perder la respuesta, también tras cierre. | Misma clave/cuerpo → misma venta; distinta huella → `409`; no se duplica stock ni caja. |
| T16 | Anular venta completa. | Venta histórica intacta; una reversión por salida/pago; turno original no cambia si estaba cerrado; reintento no repite. |
| T17 | Apertura, arqueo parcial y cierre concurrente con cobro. | Un turno por caja/responsable; suma cierra de forma consistente; cobro posterior no entra ni se traslada de turno. |
| T18 | Caja con medios mixtos y devolución en efectivo. | Esperado $63.130, contado $63.000 y diferencia −$130 en el fixture; débito/transferencia no elevan efectivo; sin asiento manual espejo. |
| T19 | Subida y acceso a archivos. | MIME falso, SVG o tamaño inválido rechazados; archivo válido privado; otro recurso/rol no lo obtiene conociendo ID. |
| T20 | Responsive y accesibilidad. | Flujos completos en móvil/tablet/escritorio, con teclado, sin tablas cortadas sin alternativa, foco y errores visibles/legibles. |

Pruebas adicionales: reservar vs. vender, doble apertura en distintas cajas para el mismo responsable, replay paralelo con misma clave, error forzado a mitad de confirmación con rollback, devolución parcial repetida sobre un pago de servicio, conciliación de importaciones y stock, límites de medianoche y hora de verano, clave temporal expirada, CSV con contenido interpretable como fórmula y restauración de backup.

## 4. Cierre de implementación futura

La entrega final incluirá código separado, migraciones/datos de demostración, documentación de API y deployment, `.env.example` sin secretos, pruebas con resultados reales y lista de integraciones pendientes. Criterios mínimos: `ventas/pagos/stock/caja` cuadran, los permisos no dependen del frontend y un tercero puede instalar y reproducir los casos desde la documentación. Esta entrega solo aprueba el diseño o permite corregirlo antes de programar.
