# Reglas de negocio

## 1. Clientes, recepción y entrega

- Validar RUT chileno solo al seleccionar documento RUT: normalizar puntos, guion y `K`, y validar dígito con módulo 11. No verifica identidad oficial. Admitir otros tipos y emisores sin ese algoritmo. Normalizar teléfonos válidos a formato internacional, con `+56` como prefijo sugerido y sin rechazar números extranjeros por defecto. Bloquear alta de documento normalizado idéntico en el mismo tipo/emisor; alertar similitud por teléfono, correo o nombre sin fusionar automáticamente.
- El asistente guarda un borrador incompleto, visible para su autor o administración. Crear un cliente o un equipo desde el asistente es un alta explícita y validada, no una consecuencia silenciosa de guardar el borrador. Fotos y accesorios pueden prepararse con el ID del borrador; sin movimientos, reservas ni pagos.
- Confirmar valida equipo/propietario, falla, conformidad con política versionada, autorización de diagnóstico y accesorios revisados. Registra firma si la política del tipo de recepción la exige. Fija el comprobante, crea la cuenta y el primer evento de estado. Con anticipo, exige estimación y conformidad registradas y turno válido, y todo se confirma junto. La estimación se calcula con las líneas registradas o se valida como total preliminar explícito; una vez aceptada queda como revisión `estimate`. Sin anticipo, no exige una caja abierta. La credencial opcional se cifra al confirmar; si se abandona el asistente, se vuelve a ingresar y no se recupera desde el borrador.
- Al asignar técnico, comprobar que esté activo y habilitado. Registrar reasignación y retirar acceso al anterior en solicitudes posteriores. En órdenes confirmadas no se reescribe el comprobante de ingreso; nuevos hallazgos o correcciones quedan como eventos o notas con motivo.

### Máquina de estados de órdenes

Solo se permiten las flechas y causas de esta tabla. Cotizaciones, aprobaciones, consumos y cobros se registran con comandos propios, no con `PATCH state`.

| Desde | Hacia | Guarda mínima |
|---|---|---|
| `borrador` | `recibido` | Comando de recepción; datos, conformidad y anticipo válido si se declara. |
| `recibido` | `diagnostico` | Técnico asignado autorizado; notificar cambio en historial, no por mensajería externa. |
| `diagnostico` | `presupuesto_enviado`, `espera_repuesto`, `en_reparacion` | Envío externo ya registrado para la primera opción; diagnóstico y autorización de trabajo vigente para iniciar la reparación. Esperar repuesto puede documentarse sin forzar una compra. |
| `presupuesto_enviado` | `espera_aprobacion`, `diagnostico`, `espera_repuesto`, `en_reparacion` | Confirmar que se espera decisión, reevaluar por motivo, o avanzar tras aprobación. |
| `espera_aprobacion` | `diagnostico`, `espera_repuesto`, `en_reparacion` | Nuevo diagnóstico si se rechaza o revisa; aprobación vigente para avanzar. |
| `espera_repuesto` | `diagnostico`, `en_reparacion` | Motivo de retorno; disponibilidad verificable y trabajo autorizado antes de reparar. |
| `en_reparacion` | `diagnostico`, `espera_repuesto`, `listo_entrega` | Motivo en retroceso; para estar lista, tareas obligatorias completadas, repuestos conciliados y pruebas registradas. |
| `listo_entrega` | `en_reparacion`, `entregado` | Reapertura técnica con motivo; entrega registra fecha real, responsable, constancia y verifica saldo firme liquidado y sin reservas activas. |
| Confirmada no terminal | `cancelado` | Administración, motivo, pagos netos en cero y liberación de reservas. El consumo ya ejecutado no se deshace sin devolución física registrada. |

`entregado` y `cancelado` son terminales. La entrega elimina el acceso temporal; la cancelación también. El descarte de un borrador no es una cancelación comercial. Trabajos sin cargo (p. ej. garantía) usan una base firme explícita de cero y su conformidad; no se inventa un pago. La entrega se bloquea también si hay exceso de lo cobrado por resolver.

## 2. Inventario y reservas

**Disponible = físico − reservado activo.** La alerta de reposición usa disponibilidad; el historial de stock físico se obtiene de los movimientos. Una entrada o ajuste requiere motivo y usuario; ningún formulario modifica el saldo directamente.

| Operación | Físico | Reservado | Validación principal |
|---|---:|---:|---|
| Entrada / devolución física | `+q` | `0` | Una devolución referencia la salida original y respeta su cantidad pendiente de devolver. |
| Reserva | `0` | `+q` | `q ≤ disponible`, ítem/ubicación correctos, y la cantidad faltante del ítem no se excede. |
| Liberación / expiración | `0` | `−q` | `q ≤ cantidad activa`, conserva el evento y motivo. |
| Consumo de reserva | `−q` | `−q` | `q ≤ cantidad activa`; un movimiento por evento, solo para trabajo en curso ya autorizado. |
| Venta POS | `−q` | `0` | `q ≤ disponible`; no usa reservas de taller. |
| Ajuste | `±q` | `0` | Motivo y permiso; físico nunca menor que reservado. |
| Reversión POS | `+q` | `0` | Compensa una salida por venta una sola vez, desde el comando de anulación. |

Ejemplo comprobable: con 5 unidades, reservar 3 deja 5 físicas, 3 reservadas y 2 disponibles. Vender 2 deja 3/3/0. Consumir 2 reservadas deja 1/1/0; liberar la restante deja 1/0/1. Consumir no rebaja la disponibilidad por segunda vez. La cantidad activa, liberada y consumida se conserva por reserva; después de cada evento se actualiza la posición y se escribe el historial en una transacción.

Las reservas entran solo en órdenes confirmadas no terminales y proyectos aprobados/en curso. No se pueden quitar ítems ni reducir cantidades por debajo de lo reservado más lo consumido neto. En cancelación se liberan compromisos activos; si se recupera un componente realmente consumido, se devuelve con referencia al movimiento, sin reactivar su reserva. Las solicitudes internas de compra registran cantidades objetivo y su estado, no alteran existencias. En CSV se previsualizan errores por fila, se revalida al confirmar y se importa como lote atómico; los saldos nunca se importan como columnas editables.

## 3. Armados, presupuesto y compatibilidad

- Flujo normal: `solicitud → cotizacion → espera_cliente → aprobado → en_armado → pruebas → listo_entrega → entregado`. Registrar emisión y envío manual lleva a `espera_cliente`; aprobación válida lleva a `aprobado`. Rechazo o revisión devuelve a `cotizacion`; `pruebas → en_armado` y `listo_entrega → pruebas` son retrocesos técnicos con motivo. Volver de un proyecto aprobado/en curso a cotización requiere un cambio de alcance explícito, conciliación de reservas afectadas y no borra cobros, consumos o conformidades previas. Administración puede cancelar desde un estado no terminal con las mismas guardas financieras y de reserva; `archived_at` solo después de entrega o cancelación, sin deuda/compromisos.
- Toda cotización nace `draft`; al emitir/registrar envío se congela y pasa a `sent`. Puede pasar a `approved`, `rejected`, `expired` o `superseded`. El servicio rechaza aprobación vencida o de una revisión desplazada aunque el proceso de expiración no haya corrido todavía. Aprobar exige registrar canal, fecha, actor y evidencia o referencia revisada, sin que el sistema simule el contacto. Validar que el destino no esté en estado terminal y que la revisión corresponda al alcance que se va a ejecutar. Si cambian componentes, cantidades o servicios de una versión emitida, se necesita una nueva revisión y no se autoriza el alcance cambiado con la anterior. Solo la última aprobación económicamente vigente modifica la cuenta. Emitir una nueva revisión desplaza la anterior pendiente, no borra una aprobación histórica ni cambia el saldo hasta la nueva aprobación.
- Duplicar arma un nuevo proyecto en solicitud con copia reutilizable de componentes y mano de obra; recalcular precios y compatibilidad. No copiar aprobación, pagos, reservas, movimientos, pruebas ni avance de estado. Generar presupuesto imprimible desde la revisión fija; no mostrar costos internos al cliente.

| Chequeo | Datos mínimos y señal |
|---|---|
| CPU / placa | Tipo de socket normalizado en ambos lados; además evidencia del modelo/revisión y soporte de CPU para declarar la verificación de BIOS. |
| Memoria | Generación, módulos por kit, cantidad de ranuras usadas, máximo total y por ranura cuando aplique. |
| Gabinete / placa | Formato real de placa dentro de los formatos admitidos por el gabinete. |
| GPU / espacio | Longitud en mm y holgura real de la configuración (p. ej. radiador); si la variante no se conoce, no aprobar el chequeo por omisión. |
| Potencia y conectores | Suma estimada verificable más factor de margen configurable (propuesto 1,25) comparada con la potencia nominal; cotejar tipos/cantidad de conectores de fuente, placa y GPU. No es medición de consumo real. |
| BIOS en un armado o mejora | Fuente verificable para revisión específica y versión real o versión con la que se entregará tras actualización documentada. Sin evidencia suficiente: requiere verificación. |

Resultado por regla y consolidado: **incompatible** si hay contradicción comprobada; **requiere verificación** si falta cualquier dato necesario o las fuentes no son concluyentes; **compatible según datos disponibles** solo si pasan todas las reglas aplicables. Registrar reglas, entradas, fuentes y fecha usadas para aprobar, e invalidar la evaluación si cambia el armado. La incompatibilidad conocida bloquea aprobación; una incertidumbre requiere verificación documentada por un técnico antes de aprobación o avance, sin cambiar automáticamente la etiqueta a «compatible» si todavía faltan datos del catálogo. En una mejora, los componentes ya instalados son parte de la evaluación.

## 4. Dinero, descuentos e IVA

Configuración inicial: Chile, `CLP`, escala 0, `America/Santiago`, IVA `1900` puntos básicos (19 %) y precios de lista al público **con IVA incluido**. Modo y tasas por tipo de catálogo se congelan por línea; IVA exento usa tasa cero solo si está configurado y autorizado. Antes de guardar, representar montos como cadenas de unidades menores y operar con enteros de precisión arbitraria (BCMath), nunca `float`. Rechazar montos fuera de escala/rango; redondear a la unidad menor, mitad hacia arriba, solo en los pasos especificados. La moneda base y escala se pueden configurar antes de registrar historia monetaria; una conversión posterior requiere una migración comercial explícita, sin reescribir documentos.

1. Calcular los extendidos `unitario × cantidad`. El descuento global autorizado como monto (o porcentaje convertido a monto una vez) no puede superar la suma de líneas. El permiso y el motivo los valida el servidor; no se confía en un total editable. En V1, la confirmación que aplica un descuento se realiza con la sesión del administrador; si el turno es de otra persona, el administrador interviene con motivo explícito. No se comparten credenciales.
2. Prorratear el descuento en las líneas por sus extendidos: truncar a unidades menores y asignar sobrantes según restos mayores con desempate por orden estable de línea. La suma distribuida es igual al descuento. Si todas las líneas valen cero, solo se permite descuento cero y se omite el prorrateo.
3. En precios con IVA incluido, `total_línea = extendido − descuento_línea`, `neto = redondear(total_línea × 10000 / (10000 + tasa_bps))`, `IVA = total_línea − neto`. Si una instalación elige precios netos, primero descontar sobre el neto y calcular `IVA = redondear(neto × tasa_bps / 10000)` por línea. No mezclar ambos modos en un mismo documento V1.
4. Sumar los netos, impuestos y totales de línea. Persistir unitarios, tasas, costo y resultados; aplicar el mismo motor a cotización y POS. Una reversión usa importes originales exactos, no redondea nuevamente el documento a tasas del día.

Ejemplo CLP con IVA incluido: dos líneas de $11.900 y $23.800, descuento autorizado de $3.570. Se asignan $1.190 y $2.380. Neto total $27.000, IVA $5.130 y total $32.130. Si el costo interno neto de impuestos recuperables es $18.000, la utilidad bruta es $9.000. **Margen bruto** = utilidad ÷ neto de venta = 33,33 %; **recargo** = utilidad ÷ costo = 50 %. Si el denominador no es positivo, el indicador no aplica.

## 5. Cuenta de cobro, anticipos y devoluciones

1. Una venta POS tiene una base firme. Una orden confirmada tiene una cuenta desde la recepción; puede tener una estimación aceptada o ninguna base hasta la aprobación. Un armado abre cuenta cuando su cotización se aprueba. El POS permite buscar y cobrar una cuenta de orden/armado sin registrar otra venta o salida de productos.
2. El anticipo se imputa una vez a esa cuenta. En una orden aún provisional se permite hasta el monto preliminar explícito aceptado; se presenta como anticipo y, si aplica, con saldo estimado, no como deuda firme. Los pagos sobre una base firme no pueden exceder el saldo al confirmar. Las revisiones pueden producir un excedente si bajan el total luego de un anticipo; se muestra «a favor» y se resuelve con devolución autorizada, no con descuento ficticio.
3. `pagado_neto = cobros − devoluciones`; `saldo_pendiente = max(base_firme − pagado_neto, 0)` y `a_favor = max(pagado_neto − base_firme, 0)` si hay base firme. Con anticipo de $10.000 y aprobación por $32.130, el saldo es $22.130; los $10.000 ya están en el turno original y no se repostean.
4. Cada medio de un cobro crea un pago e imputación completa. Reembolso con permiso, turno abierto, importe positivo, motivo, mismo medio y referencia explícita al cobro original; no superar el remanente reembolsable. Débito/transferencia reflejan un movimiento ya ejecutado externamente, con referencia, no una instrucción que el sistema envía. En V1 los ajustes financieros de orden/armado se resuelven antes de la entrega o cancelación; no se reabre un estado terminal para devolver. Una orden/armado con fondos no se cancela hasta saldar las devoluciones; la cancelación vuelve su base vigente a cero sin alterar el historial.

## 6. Venta y caja

**POS.** Preparar carrito y cotización del total sin reservar stock. Al confirmar: autorizar descuento y turno presente, validar catálogo y precios actuales (si cambiaron, pedir revisar), verificar disponibilidad, grabar venta/detalles/cuenta/pagos, descontar stock y auditar, todo en una transacción. POS V1 exige total positivo totalmente cubierto. Si hay efectivo, el monto recibido debe cubrir la porción asignada a efectivo; `cambio = recibido − efectivo_aplicado`, y caja suma solo efectivo aplicado. Para anular una venta completa, exigir administrador, motivo, turno válido y devolución física de lo vendido cuando corresponda. Generar contramovimientos por las posiciones originales y revertir cada pago en su medio, una vez, en el turno de devolución. Conservar venta y turno originales. No se altera el arqueo de un turno ya cerrado.

**Caja.** Abrir con saldo no negativo; ingresos/retiros manuales son efectivo, requieren motivo y no pueden usarse como sustituto de un cobro. Cada turno tiene un responsable. El arqueo parcial no cierra: guarda un corte trazable. El cierre exige monto contado y observación si hay diferencia; no se reabre ni se permiten nuevas imputaciones. No autorizar un retiro o devolución que exceda el efectivo esperado del turno con el criterio de esta V1. Los pagos electrónicos se muestran por medio y no cambian el efectivo esperado.

**Efectivo esperado = saldo inicial + cobros efectivos + ingresos manuales − retiros − devoluciones efectivas.** La apertura no es un movimiento manual; el pago no se copia como ingreso. `diferencia = contado − esperado`. Si un total de $32.130 se paga con $20.000 por transferencia y $15.000 en billetes, se aplican $12.130 en efectivo y el cambio es $2.870. Ejemplo de turno con esos cobros: apertura $50.000, ingresos manuales $8.000, retiros $5.000, otras devoluciones en efectivo por $2.000; esperado $63.130. Con $63.000 contados, diferencia −$130. Transferencia queda por separado.

**Fiscal.** Imprimir solo documentos internos rotulados como tales. Definir un puerto `FiscalIssuer` para solicitar emisión y consultar resultado con un proveedor autorizado cuando se disponga de requisitos/credenciales. Una futura integración se despacha después del commit, con referencia única y estado externo verificable. En V1 no se generan folios fiscales ficticios ni se marca «emitido» por haber impreso el recibo interno.

## 7. Atomicidad, concurrencia y reintentos

- Una conexión PDO por unidad de trabajo: `READ COMMITTED` en los comandos y `SELECT … FOR UPDATE` por PK/clave única. No es suficiente leer disponible en un carrito, sumar pagos fuera de la transacción o confiar en el bloqueo de una sesión PHP.
- **Orden de bloqueo común:** clave de idempotencia → raíz del documento y sus hijos editables → caja y turno (si aplica) → cuenta y pagos de origen → catálogo y posiciones ordenadas por `(product_id,location_id)` → reservas/movimientos de origen → eventos y auditoría. Bloquear cada grupo por clave ascendente; si se necesita descubrir IDs, leer primero y revalidar tras tomar los bloqueos. Los demás casos (p. ej. ajuste o cierre) toman solo el subconjunto que usan, en ese mismo orden.
- Apertura, cobros, retiros, devoluciones y cierre coordinan sobre las mismas filas de caja/turno. Al cerrar, releer los totales bajo ese bloqueo y guardar foto del corte en la misma transacción. Un cobro concurrente entra completo antes del corte o se rechaza como turno cerrado; no migra silenciosamente al turno siguiente. Al reservar y vender se compite por la misma posición. En la última unidad, un solo comando puede conseguirla.
- Los comandos críticos reciben `Idempotency-Key`. La clave es por usuario/comando y la huella cubre método, destino y solicitud normalizada. Una clave repetida con igual huella recupera el resultado confirmado, después de autenticar/autorizar, sin volver a calcular stock, turno ni saldo. Una clave igual con otra solicitud da conflicto. La marca de éxito coincide con el commit de los efectos; un rollback no deja un éxito aparente. No caducar claves financieras de manera que un reintento antiguo pueda repetir el cobro. Usar reintentos acotados de transacción ante deadlock y devolver error recuperable si persiste; conservar la misma clave al reintentar el mismo comando.

## 8. Indicadores, agenda y auditoría

| Indicador | Definición y período |
|---|---|
| Órdenes abiertas / en reparación | Abiertas: confirmadas no terminales, excluye borradores. En reparación: exactamente `en_reparacion`. Fecha de corte actual. |
| Ventas del día | Cantidad y total bruto de ventas POS confirmadas hoy y aún vigentes. Anulaciones ocurridas hoy se muestran separadas por fecha de anulación. No son cobros de órdenes ni efectivo del turno. |
| Stock crítico | Productos activos con disponible agregado en ubicaciones operativas `≤ mínimo`; separar agotados (`= 0`), bajo mínimo y en el umbral. |
| Cobros pendientes | Suma de saldos firmes positivos de cuentas vigentes. Mostrar anticipos provisionales y montos a favor por separado. |
| Agenda del día | Recepciones, entregas y llamadas entre inicio del día local e inicio del siguiente; cliente, orden opcional, responsable, hora y estado. |
| Alertas y actividad | Presupuestos enviados sin decisión, próximos a vencer o vencidos según fecha real; entrega estimada en próximas 24 h o vencida en órdenes no terminales; toda actividad con alcance, sin secretos. |

El tablero indica período y zona; usa límites locales `America/Santiago` convertidos a UTC, incluso en cambios de horario. Órdenes recientes se ordenan por ingreso más ID. No publica % comparativos sin período y base suficientes. Cada evento sensible identifica actor, fecha, entidad y cambio concreto; guarda motivo en anulaciones, reembolsos y ajustes, y no copia formularios completos.
