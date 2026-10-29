# StockFlow ERP — arquitectura y verificación

## Plataforma
Laravel 13, PHP 8.5 (>=8.3), MySQL 8.4, Blade, Livewire, Alpine,
Tailwind, Chart.js, Lucide y DomPDF. Zona horaria America/Guayaquil; USD.
Los comprobantes son internos/proformas, sin autorización electrónica del SRI.

## Capas
- Rutas autenticadas y Policies para autorización en el servidor.
- Form Requests para validar datos de entrada.
- Servicios para inventario, recepciones, ventas, anulaciones y reportes.
- Eloquent y migraciones con claves externas, índices y unicidad.
- Blade/Livewire para formularios, búsqueda, filtros y paginación.

## Relaciones e invariantes
Usuarios pertenecen a roles; roles tienen permisos. Categorías y proveedores
agrupan productos. Clientes tienen ventas; proveedores tienen compras. Cada
compra/venta contiene líneas inmutables con cantidades, precios, impuestos y
costos históricos. Ventas tienen pagos y documentos internos. Movimientos de
inventario pertenecen a producto y usuario, conservan origen, motivo y saldos.
La configuración empresarial y el registro de auditoría son independientes.

El stock sólo cambia mediante movimientos transaccionales. Las confirmaciones
bloquean productos en orden de ID y usan claves de idempotencia únicas. Una
anulación crea movimientos compensatorios y conserva los registros originales.
Importes se guardan como DECIMAL y se operan en unidades menores o aritmética
decimal exacta. Ingreso neto, impuestos, cobros y utilidad se reportan por separado.
Los costos de venta se congelan al confirmar para preservar utilidad histórica.

## Fases y criterios de salida
1. Base, autenticación oficial, roles, perfil, diseño responsive y dashboard
   con consultas reales. Validar MySQL, migraciones, frontend y autenticación.
2. Productos, categorías, proveedores, clientes y CSV validado. Validar CRUD,
   precios, unicidad y autorización de cada rol.
3. Compras y movimientos. Validar recepción idempotente, saldos y auditoría.
4. POS y pagos. Validar stock insuficiente, concurrencia MySQL, descuentos,
   cambio, confirmación idempotente y reversión.
5. Documentos internos PDF y configuración. Validar totales históricos,
   autorización de descargas y aclaración de no autorización SRI.
6. Reportes, exportaciones y regresión. Validar rangos de fechas, agregados,
   costos, ingresos y utilidad con datos conocidos.

No sustituir pruebas MySQL de concurrencia por SQLite. No avanzar una fase
sin registrar el resultado de su verificación. El envío real de recuperación
de contraseña requiere configurar un transporte de correo; en desarrollo
puede inspeccionarse el transporte log, sin exponer enlaces ni tokens.
