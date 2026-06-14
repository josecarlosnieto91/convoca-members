# Changelog - Convoca Members

## 2.6.1
- **Fix:** Eliminado botón "Descargar Carnet Digital" (generaba PDF mal formado). Usar "Tarjeta" en acciones.
- **Fix:** Logo en tarjeta de socio demasiado grande: ahora respeta el style inline (height: 45px).
- **Fix:** Acciones del listado (Ver ficha, Aprobar alta, Tarjeta, Eliminar) ya no se superponen gracias a CSS flexbox.
- **Fix:** Se añaden mensajes de feedback (notice) después de aprobar, eliminar o si hay error en admin-post.
- **Fix:** Texto "Sin asignar" en columna Nº para miembros sin número de socio (traducido).
- **Nuevo:** Asignación masiva de números de socio a miembros existentes (10 socios numerados).

## 2.6.0
- Seguridad: Dedup email con INSERT ON DUPLICATE KEY UPDATE (atómico)
- Corrección: Email queue lock liberado correctamente (delete_transient → Utils::release_lock)
- Corrección: Webhook dispatcher lock → acquire_lock/release_lock
- Corrección: Fechas date() → wp_date() en todos los módulos
- Corrección: Consultas de renovación con IS NOT NULL AND != ''
- Rendimiento: Per-member lock en recordatorios de pago (try/finally)
- Mejora: Creación automática de la tabla bdv_member_sequence (upgrade 1.0.3) para numeración de socios sin bloqueos
- Actualización: Documentación sincronizada (versión 2.6.0)

## 2.5.1
- **Seguridad:** CSRF vulnerability in export CSV (monitor CRM) - added nonce verification.
- **Seguridad:** Webhooks admin page now requires `manage_options` capability (previously `gestionar_miembros`).
- **Fix:** Invalid state validation in manual inscription creation - now validates against `CPT_Inscripcion::STATES`.
- **Fix:** False payment reminders when no pending payment date exists - removed fallback to post_date.
- **Fix:** Improved HTML email detection - uses `strip_tags()` to detect actual HTML tags.

## 2.5.0
- **Centralización:** Migración total del flujo de transferencia bancaria al Convoca Gateway. Eliminado el campo de subida local en el formulario de registro para evitar duplicidad.
- **Mejora:** Rediseño Premium de la tarjeta de socio (PDF) con degradados vibrantes Lila y Naranja alineados con la nueva identidad visual.
- **Mejora:** Pestaña de **Estado** en ajustes ahora incluye la verificación de la página "Panel de Reservas" necesaria para el QR.
- **Fix:** Consistencia de versiones entre cabecera y constantes internas.

## 2.4.0
- **Nuevo:** Pestaña de **Estado** en ajustes para diagnóstico del sistema y verificación de dependencias/páginas.
- **Mejora:** Rediseño visual de la tarjeta de socio (colores Lila y Naranja) para alinearse con el tema.
- **UX:** Optimización de listados administrativos (botones compactos en fila).
- **Fix:** Enlace del código QR corregido para dirigir a `/panel-reservas/`.

## 2.3.0
- **Mejora:** Corregidos los estilos de los bloques "Mi Área", "Alta" y "Voluntariado" (ahora se cargan correctamente en el editor).
- **Mejora:** Rediseño completo del bloque de "Verificar Certificado" para ajustarse a la guía de estilos de Convoca.
- **Mejora:** Asegurada la accesibilidad administrativa a todos los tipos de contenido de socios.
- **Mejora:** Eliminación de estilos inline en favor de clases del sistema de diseño del tema.

## 1.5.0
- **Nuevo:** Sistema de gestión de Voluntariado expandido (separación clara entre Socios y Voluntarios).
- **Nuevo:** Generación automática del "Acuerdo de Incorporación de Voluntariado" mediante `BDV_Signature`.
- **Nuevo:** Registro de datos técnicos de aceptación (IP, timestamp, hash) en el PDF del acuerdo.
- **Mejora:** Campos personalizables en el formulario `[convoca_voluntariado]` (Teléfono, DNI, Dirección, etc.).
- **Mejora:** Lógica de asignación de roles y capacidades (`voluntario_aprobado`) centralizada.

## 1.4.0
- **Mejora:** Refactorizado el sistema de generación de Acuerdos para usar el motor centralizado `BDV_Signature`.
- **Nuevo:** Sistema de auditoría completo — registra cambios de estado, borrados lógicos, emails enviados, contacto vía WhatsApp y todas las acciones administrativas.
- **Nuevo:** Bloques de Gutenberg nativos para todos los shortcodes (`convoca/alta-socio`, `convoca/mi-area`, `convoca/voluntariado`).
- **Nuevo:** Botones AJAX corregidos en el metabox de socio (Generar Link de Pago, Enviar Recordatorio RGPD).
- **Fix:** Migración completa de `date()` → `wp_date()` en 7 archivos para consistencia de zona horaria (Europe/Madrid).
- **Fix:** Número de socio ahora se muestra correctamente en la tarjeta de socio.
- **Fix:** Checkbox "Renovación automática anual" redimensionado.
- **Fix:** Estado de cuota se calcula dinámicamente al crear el miembro.
- **Fix:** Botones superpuestos en el listado de miembros.
- **Fix:** Error JS `Cannot set properties of null` en el metabox de acciones.

## 1.2.1
- **Fix:** Corregidas meta keys RGPD para horas de voluntariado.
- **Fix:** Sincronizada constante BDV_MEMBERS_VERSION con versión del header.

## 1.2.0
- **Nuevo:** Sistema de versionado de base de datos con Members_Upgrade_Manager.
- **Nuevo:** Herramientas RGPD en metabox de miembro (exportar/eliminar datos JSON).

## 1.1.0
- **Nuevo:** Sistema completo de voluntariado con CPT Proyectos.
- **Nuevo:** Generación de certificados PDF con Dompdf.

## 1.0.2
- Primera versión: CPT, formularios, emails, admin, REST API.
