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
- Área de socio (Mi Área) con edición de perfil (dirección, teléfono, email, cumpleaños)
- Renovación de membresía manual (botón en el panel + shortcode `[convoca_renovar]`)
- Verificación de email y teléfono por enlace enviado al email
- Proveedor de email pluggable (wp_mail / Mailgun)
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

2.8.2

### 2.6.2
- docs: add MANUAL_USUARIO.md with 15-section admin guide
- dev: add phpstan.neon (level 5) for static analysis

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
## 📖 Documentación

La documentación completa (manual de usuario, API REST, hooks, instalación) vive en la wiki:

👉 **[docs.getconvoca.app/plugins/convoca-members](https://docs.getconvoca.app/plugins/convoca-members/)**

## 🧪 Demo

Prueba Convoca sin instalar nada:

👉 **[demo.getconvoca.app](https://demo.getconvoca.app)**

## 📸 Capturas

| Socios | Actividades | Turnos | Inscripciones |
|--------|-------------|--------|---------------|
| ![Socios](https://getconvoca.app/wp-content/uploads/2026/06/convoca-miembros-v4.png) | ![Actividades](https://getconvoca.app/wp-content/uploads/2026/06/convoca-actividades-v4.png) | ![Turnos](https://getconvoca.app/wp-content/uploads/2026/06/convoca-turnos-v4.png) | ![Inscripciones](https://getconvoca.app/wp-content/uploads/2026/06/convoca-inscripciones-v4.png) |

## 🔗 Ecosistema

- [Convoca Core](https://github.com/josecarlosnieto91/convoca-core)
- [Convoca Members](https://github.com/josecarlosnieto91/convoca-members)
- [Convoca Enroll](https://github.com/josecarlosnieto91/convoca-enroll)
- [Convoca Gateway](https://github.com/josecarlosnieto91/convoca-gateway)
- [Convoca Shifts](https://github.com/josecarlosnieto91/convoca-shifts)
- [Convoca Publisher](https://github.com/josecarlosnieto91/convoca-publisher)

## 🧑‍💻 Developer Guide — Hooks & Filters

La API pública de Convoca para desarrolladores son los **hooks y filtros** que emiten los plugins. La referencia completa, generada desde el código, vive en [`convoca-core/HOOKS.md`](https://github.com/josecarlosnieto91/convoca-core/blob/main/HOOKS.md).

### Acciones principales

| Hook | Descripción |
|------|-------------|
| `convoca_members_created` | Se dispara al crear un miembro (alta o importación CSV). |
| `convoca_members_estado_changed` | Cambio de estado de un miembro (activo, suspendido, baja, etc.). |
| `convoca_members_cuota_pagada` | Tras confirmar el pago de una cuota en la pasarela. |
| `convoca_members_hours_submitted` | Un voluntario registra horas desde su panel. |
| `convoca_members_hora_aprobada` | Un administrador aprueba un registro de horas. |
| `convoca_members_hora_rechazada` | Un administrador rechaza un registro de horas. |
| `convoca_members_unsubscribe_request` | Un miembro solicita la baja desde su panel. |
| `convoca_members_membership_expired` | Una membresía vence por falta de pago o renovación. |
| `convoca_members_auto_renewal_completed` | Se completa una renovación automática con cargo. |

### Filtros

| Filtro | Descripción |
|--------|-------------|
| `convoca_members_plans` | Modifica los planes de membresía disponibles. |
| `convoca_members_interest_areas` | Personaliza las áreas de interés del voluntariado. |
| `convoca_email_providers` | Registra proveedores de email (wp_mail, Mailgun…). |

## Pruebas

```bash
composer install
composer test          # phpcs + phpstan + phpunit
vendor/bin/phpunit     # solo unit tests
```

