# Changelog — convoca-members

## v2.8.4 (2026-09-11)

### ✨ New features
- El pago recibe el correo del socio al crearse

### ⚙️ Changes
- Traducción completa del plugin y de las plantillas al inglés (en_US)

## v2.8.3 (2026-09-10)

### 🔧 Fixes
- La desinstalación ya no deja tablas, opciones ni tareas programadas huérfanas

### ⚙️ Changes
- «Mi área» solo carga sus recursos donde se muestra, mejorando el rendimiento del resto del sitio

## v2.8.2 (2026-09-10)

### ✨ New features
- Páginas de editor en el menú de administración y paginación en el listado de registros

## v2.8.1 (2026-09-10)

### ✨ New features
- Emails con CTA automático, emails del sitio y enlaces de plantillas

### 🔧 Fixes
- Corregido el bloque PRO de administración, que se mostraba como texto

## v2.8.0 (2026-09-10)

### ✨ New features
- Ciclo de vida de la membresía: periodo de gracia, renovación y re-alta
- Renovación automática con cargo real mediante token, con periodo de gracia y reintentos
- Gestión de voluntarios integrada en Members: nueva página de administración y migración de los datos de solicitud
- Carnet y certificado con tema claro/oscuro según una opción global, con selector en Ajustes
- Certificados con validez de 1 año y regeneración automática al aprobar horas de voluntariado
- Insignia de antigüedad en el carnet

### 🔧 Fixes
- El alta y la edición de socios desde el backend numeran y activan al socio; QR del carnet generado localmente
- El miembro se activa automáticamente tras el pago con Redsys
- Carnet servido como HTML para imprimir desde el navegador y certificado con layout de tablas compatible con el PDF
- Corregido el cacheo del estado del socio en las rutas /me
- Corregido el botón «Horas de voluntariado» (daba error 403) y la exportación a PDF de miembros (daba error 500)
- Unificada la sección de voluntarios, el aviso PRO y las plantillas de email

### 📦 Infrastructure
- Preparación para el directorio de WordPress.org y compatibilidad declarada hasta WordPress 7.1

## v2.7.2 (2026-09-05)

### 🔧 Fixes
- Endpoints REST protegidos y bloqueos en base de datos para evitar duplicados

## v2.7.1 (2026-09-05)

### 🔧 Fixes
- Los planes respetan el estado «activo» en el alta y el registro, y se valida la modalidad juvenil

## v2.7.0 (2026-08-07)

### ✨ New features
- **Renovación manual**: botón "Renovar membresía" en el panel (Pagos y Cuotas) + shortcode `[convoca_renovar]` + página `/renovar/`
- **Edición de perfil** desde el panel del socio: dirección, teléfono, email y cumpleaños
- **Verificación de email y teléfono** por enlace enviado al email (doble opt-in para email; enlace de confirmación para teléfono)
- **Proveedor de email pluggable**: `Email_Verifier` (wp_mail por defecto, Mailgun opcional)

### ⚙️ Changes
- El envío de emails pasa por `Email_Verifier::send()` (provider configurable)
- `get_profile` expone `email_pendiente` y `telefono_verificado`

### 🔧 Fixes
- Corregido namespace REST del panel del socio (`convoca/v1` → `convoca-members/v1`) — el panel no funcionaba vía REST
- `MAX(member_number)` → `MAX(id)` en la secuencia de socios (error SQL en altas con pago)

---

## v2.6.2 (2026-06-24)

### 🐛 Fixes
- Corregidos status counts en listado de miembros (conteo correcto por estado)
- CSS de tabla de miembros mejorado para visualización consistente
- Fix en filas alternas (zebra striping) en listados

### ✨ Improvements
- Nuevas capabilities para gestión granular de permisos
- Mejoras en emails de notificación y plantillas

### 📦 Infrastructure
- Updated release ZIPs on getconvoca.app
- Demo environment synchronized

---

*Las entradas de las versiones v2.7.1 a v2.8.4 se reconstruyeron a partir del historial de git.*
