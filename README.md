# Convoca Members

Gestión de socios, voluntariado y membresías para la Asociación Convoca.

## Requirements

- WordPress 6.4+
- PHP 8.1+
- convoca-core plugin active

## Main Features

- Miembro CPT
- Registro de Horas CPT
- Proyecto CPT
- Planes de membresía configurables
- Voluntariado con conversión automática a socio
- Certificados PDF con verificación
- Área de socio (Mi Área)
- Email automation con dedup
- Cron con per-member lock
- GDPR tools
- REST API
- CSV export de miembros (admin-ajax, columnas: ID, Nombre, Email, DNI, Teléfono, Estado, Plan, etc.)
- CSV export de proyectos (admin-post, columnas: Título, Inicio, Fin, Responsable, Activo)
- CSV export de horas voluntariado (admin-post, columnas: Fecha, Socio, Proyecto, Tareas, Horas, Estado)
- Import CSV de socios (mapeo de columnas, validación DNI, email único)
- Webhooks (13 eventos con firma HMAC-SHA256 y reintentos)

## Dependencies

convoca-core, WordPress 6.4+, PHP 8.1+, Dompdf (optional)

## Version

2.6.0

### 2.6.0
- Seguridad: Dedup email con INSERT ON DUPLICATE KEY UPDATE
- Corrección: Email queue lock liberado correctamente (Utils::release_lock)
- Webhook dispatcher lock con acquire_lock/release_lock
- Fechas date() → wp_date()
- Per-member lock en recordatorios de pago

### 2.4.0
- Added voluntary-to-member automatic conversion
- Added certificate PDF generation with online verification
- New REST API endpoints for volunteer hours

### 2.3.0
- Added digital member card PDF
- Added Mi Área shortcode with email+code auth
- Added Gutenberg blocks
