# Changelog — convoca-members

## v2.8.9 (2026-09-25)

### Corregido
- El aviso a la administración salía hacia `$system_email` (el propio remitente) y solo en las altas,
  así que **no llegaba a la asociación**. Sustituido por la copia real.

### Cambiado
- Todos los correos de socio (alta, bienvenida, credenciales, recordatorios de pago, renovación,
  certificado, tarjeta, voluntariado) se copian ahora al administrador con `Convoca\Core\Email_Copy`,
  por el **mismo canal** que el correo original (incluido el proveedor configurado).
- Ajustes → General: interruptor **«Enviar copia de los correos a la administración»** (marcado por
  defecto) junto al correo administrador, que ahora describe su uso real.


## v2.8.7 (2026-09-24)

### Cambiado
- El alta ya no admite el voluntariado como forma de no pagar: la cuota del primer año es obligatoria.
  `_convoca_es_voluntario` pasa a reflejar el compromiso (casilla del formulario), no la forma de pago.
- La renovación por horas es una vía alternativa al pago a partir del **segundo** ciclo; el mínimo es el `hours` del plan.
- Si no se alcanzan las horas, no se degrada al socio: se le exige la cuota y siguen los días de gracia.

### Corregido
- El formulario de alta no mostraba los métodos de pago (contenedor oculto sin lógica que lo mostrara): el alta no se podía completar.

### Pruebas
- `CuotaPrimerAnoTest`: primer ciclo sin vía de horas, segundo ciclo con vía de horas, plan sin horas, mínimo desde el plan y quién no se evalúa. PHPUnit 115.

## v2.8.5 (2026-09-23)

### 🐛 Correcciones
- Ajustes → Salud: la comprobación de la página de alta buscaba el shortcode `[convoca_alta]`, que no existe en el plugin, y avisaba de un fallo que el admin no podía arreglar creando la página por ese nombre. Ahora busca el real, `[convoca_alta_socio]`.

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
