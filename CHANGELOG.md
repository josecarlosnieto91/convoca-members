# Changelog — convoca-members

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
