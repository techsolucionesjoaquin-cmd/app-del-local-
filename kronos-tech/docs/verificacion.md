# Verificación ejecutada

Entorno: PHP 8.4.27, MySQL Community Server 8.4.9, Chromium con Playwright. Se usó una base MySQL desechable, separada de los datos que muestra la vista previa.

| Verificación | Resultado observado |
|---|---|
| Sintaxis PHP y módulos ES | Correcta (`php -l`, `node --check` en modo ESM). |
| Login, CSRF, logout y permisos | Login rotó token; mutación sin CSRF rechazada, técnico excluido de caja/costos y de orden ajena, logout invalidó sesión. |
| Recepción | Borrador versionado, versión obsoleta rechazada; confirmación y reintento produjeron un solo anticipo. Foto con MIME incorrecto rechazada y foto PNG válida aceptada. |
| Cotización y conciliación previa al cobro | $27.000 netos + $5.130 de IVA → $32.130; anticipo de $10.000 → saldo $22.130. |
| Inventario | Entrada, reserva, liberación y consumo cuadraron; un ajuste que infringía la reserva fue rechazado. CSV previsualizado e importado sin escribir existencias. |
| POS y caja | Cobro mixto devolvió $2.870 de cambio; mismo comando recuperó una sola venta; anulación revirtió stock y cobros. Arqueo, cierre y rechazo de operación en turno cerrado. |
| Carrera real de última unidad | Dos sesiones de usuario, dos cajas y peticiones concurrentes: una respuesta `201` y una `409`; disponible final igual a cero. |
| PC | Datos incompletos dieron «Requiere verificación»; BIOS con correspondencia exacta tuvo veredicto individual sin ocultar datos faltantes. Duplicación no copió reservas, pagos ni aprobaciones. |
| Navegador | Navegación por 10 pantallas, cambio de período del gráfico, recepción con firma imprimible y venta con comprobante. Vista de 390 px sin desplazamiento horizontal general del dashboard y menú móvil funcional. |

Reproducir HTTP: `KRONOS_TEST_URL=http://127.0.0.1:3000 python3 backend/tests/smoke.py`. Para navegador, usar Playwright de desarrollo con Chromium y `KRONOS_TEST_URL=… node backend/tests/browser.cjs`. Ejecutar únicamente sobre una instancia demo descartable; ambas pruebas crean registros. Las pruebas concurrentes requieren un servidor que procese varias solicitudes en paralelo (por ejemplo `PHP_CLI_SERVER_WORKERS=4` en Linux).

No se validó el despliegue Docker/producción, la fidelidad a Figma ni integraciones externas. Tampoco es una auditoría de seguridad, fiscal o legal.
