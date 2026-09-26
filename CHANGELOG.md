# Changelog — convoca-members

## v2.8.20 (2026-09-27)

### Arreglado — la modalidad larga rompía la cabecera del carnet (bloque 8)

El distintivo de la modalidad se pintaba en la misma línea que el logo. Con las etiquetas cortas
(«Lugg», «Deva», las dos activas hoy en Lugg) cabía; con una etiqueta larga —p. ej. «Modalidad
Familiar Juvenil de Busgosu», 37 caracteres— **no cabía y la descomponía**: con Dompdf el grupo de
insignias se metía encima del nombre de la organización y lo tapaba, y probando alternativas (tabla,
posiciones absolutas dentro de la cabecera) las insignias caían en la línea de los datos o el logo
se partía en dos líneas.

Solución: en el PDF, si la etiqueta de la modalidad pasa de 15 caracteres **ocupa su propia línea**
y el cuerpo y el pie bajan (hay sitio de sobra en los 280 px del folio). Queda en dos líneas, sin
solapes y legible; comprobado mirando el PDF generado. Las etiquetas cortas se quedan exactamente
como estaban.

- La decisión va en una sola clase en la tarjeta (`card--plan-largo`), solo en el PDF: en el
  navegador la cabecera la reparte el flexbox y funciona con cualquier etiqueta.
- Medido con las cuatro modalidades: `lugg` y `deva` (una línea, sin cambios), `Modalidad Familiar`
  y la larga (dos líneas, cuerpo a 112 px y pie a 192 px, sin tocarse).

## v2.8.19 (2026-09-27)

### Añadido — base de estilos común de los documentos (bloque 8)

Los tres documentos (carnet, acuerdo y certificado) usaban la misma tipografía y los mismos colores
de marca, cada uno por su cuenta, y todos se dibujan con Dompdf, que no es un navegador.

- `Estilos_PDF` reúne **la tipografía y los colores** en un solo sitio (constantes de PHP, porque
  Dompdf no soporta variables CSS) y trae los resets neutros que comparten (margen del cuerpo,
  imágenes al 100%, tablas sin doble borde).
- Y sobre todo **escribe las trampas de Dompdf** que se fueron midiendo una a una, para que el
  próximo documento no vuelva a tropezar: no hay flexbox (se coloca con flotantes y `clear`),
  `overflow: hidden` **recorta** los contenedores con flotantes dentro (altura cero, y el texto sigue
  en el fichero, así que `pdftotext` no lo delata), `box-sizing: border-box` no se respeta (suma
  padding y borde), `@media print` no existe, la tipografía no tiene glifos de emoji y no hay
  variables CSS.
- La base se **antepone** a la hoja de cada documento: cada uno manda en lo suyo. Comprobado antes y
  después con los tres documentos generados: **mismo número de páginas, mismo tamaño de folio y el
  mismo texto**. En el carnet el texto extraído es idéntico byte a byte; en el acuerdo y el
  certificado solo cambian la fecha de firma y el ID, que son nuevos en cada generación.
- La base **no fija el color del texto** a propósito: la plantilla del acuerdo la edita el sitio y,
  por ir después en la hoja, un color puesto en la base se le impondría al suyo.

### Pruebas
- `EstilosPdfTest` (4 casos): la base trae lo que debe, no se impone al color de cada documento, el
  carnet la incluye y los tres documentos la llaman — un documento nuevo que la olvide falla aquí.

## v2.8.18 (2026-09-27)

### Corregido — los textos del certificado (bloque 9)

Salieron de **mirar el certificado generado** (y de comparar sus horas con el libro de horas):

- **Las horas salían con punto fijo**: «18.5 horas». `number_format()` no mira nada, así que un
  documento en castellano salía con punto. Ahora se escriben **«18,5 horas»** (y «1.234,5» si algún
  día hay muchas horas).
  Se probó primero con `number_format_i18n()` y **no vale**: medido en la demo —locale `es_ES` y las
  traducciones cargadas—, WordPress devolvía `decimal_point = '.'` y `thousands_sep = ','`, o sea el
  formato inglés, así que el certificado salía con las letras en castellano y **las cifras en
  inglés**. Los documentos de Convoca están escritos en castellano: sus cifras también. Y para quien
  quiera otra convención hay un filtro, `convoca_documento_horas`.
- **La etiqueta del plan podía llevar emoji y salía como «?»**: los planes de la demo se llaman
  «🥉 Bronce» y en el PDF se imprimía «? Bronce» (la tipografía del PDF no tiene ese glifo), igual
  que pasaba en el carnet. Se quita el emoji y se conserva el texto.

### Añadido
- `Texto_PDF` reúne las dos ayudas de texto de los PDF (quitar emojis y dar formato a las horas) en
  un solo sitio, en vez de repetirse en cada documento. El carnet pasa a usarlas también.

### Verificado
- **Las horas certificadas salen del libro de horas**: un socio con 8 + 6 + 4,5 h aprobadas y un
  registro **anulado** de 10 h recibe un certificado de **18,50 h** — el anulado no cuenta.
- El QR es **local** (chillerlan, sin API externa) y el documento sale en **una página** A4.

## v2.8.17 (2026-09-27)

### Corregido — los PDFs de tarjeta y acuerdo (bloque 8)

Todo esto salió de **medir los PDF de verdad** (pdfinfo, pdftotext, el contador de páginas de
Dompdf), no de leer el código. El acuerdo se iba a dos páginas y la tarjeta a dos y con el botón
de imprimir dentro.

- **El acuerdo cabe en una página.** El sello de aceptación se iba a la segunda página. Ahora el
  documento se aprieta lo justo (márgenes del folio, espaciados y el sello del core más cerca) y
  entra en una.
- **El marcador de la firma se acepta con los dos nombres de clase.** El código buscaba
  `<!-- … POR LA CLASE Signature -->`, pero las plantillas guardadas —como la de este sitio— dicen
  `BDV_Signature` (el nombre antiguo). Al no reconocerlo, el sello **siempre** acababa al final del
  documento en vez de donde el sitio lo había puesto. Ahora se reconoce cualquiera de los dos.
- **La tarjeta del PDF no lleva el botón «IMPRIMIR / GUARDAR PDF».** Dompdf ignora `@media print`,
  así que el botón —marcado como `no-print`— se colaba dentro del PDF. En modo PDF no se pinta; en
  el navegador sigue estando.
- **La página del PDF es la tarjeta** (450x280 px = 119x74 mm), no una A4 con la tarjeta flotando
  en medio del folio.
- **La tarjeta ya no sale en dos páginas.** Fueron tres causas, medidas una a una:
  1. Dompdf **no respeta `box-sizing: border-box`**: le suma el padding (30x2) y el borde (1x2) a
     las medidas, así que la tarjeta se le iba a 510x340 y no cabía en un folio de 450x280. En PDF
     se le dan las medidas del contenido.
  2. Dompdf **no sabe hacer flexbox**: las tres zonas de la tarjeta (cabecera, datos, pie) se
     apilaban en vertical y el QR de 75 px se salía de los 280 px de alto. En PDF se colocan con
     flotantes y `clear`.
  3. Los **adornos decorativos** del fondo (200 px en `top:-60px`) sobresalen del folio y Dompdf los
     cuenta. En PDF no se pintan.
- **Un emoji en la etiqueta del plan salía como «?»** (Helvetica no tiene el glifo): «🏅 Bronce» se
  imprimía «? BRONCE». En el PDF se quita el emoji y se conserva el texto.
- **El carnet se imprimía recortado**: en la página solo salían dos líneas. La causa era
  `overflow: hidden` en la cabecera y el pie —puesto para contener los flotantes—, porque Dompdf
  calcula esos contenedores con altura CERO y **recorta** su contenido: desaparecían el logo, las
  insignias, la fecha y el QR. El texto seguía en la capa del PDF, así que `pdftotext` no lo veía;
  se descubrió **mirando el PDF**. Y las tres zonas se reparten ahora con posiciones absolutas,
  porque el `justify-content: space-between` del navegador no existe en Dompdf y el contenido se
  apelotonaba arriba dejando un tercio del carnet vacío.
- **Un nombre largo se cruzaba con el pie del carnet**: se partía en tres líneas, se salía del
  bloque y sus líneas salían intercaladas con la fecha y el dominio. El nombre ajusta ahora su
  tamaño por longitud (20 / 16 / 13 px), también en el navegador. Probado con 107 caracteres.
- **La tarjeta ya se puede generar fuera del escritorio.** Usaba `wp_tempnam()`, que vive en
  `wp-admin/includes/file.php` y no está cargado en cron ni en la web. Un correo que adjuntase la
  tarjeta desde cron moría con «Call to undefined function wp_tempnam()». Ahora usa
  `get_temp_dir()`, del core, que está siempre disponible.

### Pruebas
- `tests/Unit/TarjetaPdfTest.php` (6 casos) fija lo anterior: el navegador conserva su botón y su
  flexbox, el PDF no lleva botón, mide la tarjeta, no usa flexbox, quita el emoji del plan.
- El arnés de pruebas gana lo que faltaba para poder probar la tarjeta: el tema de documentos, la
  cabecera de marca y la sal persistente en el stub de `Utils`, las traducciones con plural, la
  fecha del post, `wp_parse_url` y las constantes de tiempo de WordPress.
- Se quita la deprecación de `ReflectionMethod::setAccessible()` en `CuotaPrimerAnoTest` (no hace
  nada desde PHP 8.1 y está deprecado desde 8.5).

## v2.8.16 (2026-09-26)

### Añadido — el panel de socio admite enlaces del sitio
- El panel pinta ahora, bajo las pestañas, los enlaces que cada sitio declare con el filtro
  **`convoca_mi_area_links`** (`array( 'url' => …, 'label' => …, 'icon' => … )`). El plugin no
  lleva a mano páginas de un sitio concreto —no existen en otro—, así que las aporta quien las
  tiene: en Lugg, /turnos/ y /mi-perfil/.
- Una entrada a la que le falte la URL o el texto no se pinta, y no rompe el panel.

### Corregido — el bootstrap de pruebas ya no daba por bueno cualquier filtro
- `apply_filters` devolvía el valor tal cual y `add_filter` no hacía nada, así que cualquier
  contrato basado en filtros pasaba sin comprobar nada. Mismo defecto que se corrigió en
  convoca-core y convoca-enroll. Añadidos además los stubs que faltaban para poder pintar el
  panel en una prueba (`esc_html_e`, `esc_attr_e`, `add_shortcode`).

### Pruebas
- `MiAreaLinksTest`: sin filtro no aparece la lista; con filtro aparecen los enlaces; una entrada
  incompleta se ignora. Members 144/144.

## v2.8.15 (2026-09-26)

### Corregido
- **La migración de plantillas vuelve a correr** para recuperar los enlaces al panel escritos a mano
  (`/mi-area/`): la versión de plantillas no había subido, así que `maybe_migrate()` salía por el
  atajo y el cambio no llegaba a los sitios. Subir esa constante es OBLIGATORIO al añadir una regla
  de migración; sin ello el arreglo se queda en el repositorio.


## v2.8.14 (2026-09-26)

### Corregido
- **`{login_url}` y el aviso de horas ya no apuntan a `/mi-area/` a mano.** El CTA «Acceder a Mi Área»
  de los correos, el enlace del pie y el texto del recordatorio de voluntariado llevaban la ruta
  escrita; en un sitio cuyo panel se llama `/panel-socio/` eso es un 404. Ahora salen de
  `Convoca\Core\Email_Links`, que resuelve la página real.
- El recordatorio de voluntariado guarda `href="{login_url}"` (se resuelve en cada envío) y la
  migración de plantillas recupera los enlaces al panel ya escritos a mano.


## v2.8.13 (2026-09-26)

### Corregido
- **Reparación de los botones ya guardados.** La migración de plantillas recupera los enlaces que
  `esc_url()` dejó destruidos (`http://link_pago` → `{link_pago}`, y lo mismo con `login_url`,
  `link_confirmacion`, `panel_reservas` y `certificado_url_verificacion`), en vez de taparlos de uno en
  uno. Antes solo estaban contemplados dos de los cinco casos.


## v2.8.12 (2026-09-26)

### Corregido
- **Dos correos que no salían nunca.** `confirm_email` (confirmar el cambio de email) y
  `verify_phone` (verificar el teléfono) se envían desde el plugin, pero no estaban en
  `Email_Manager::TEMPLATES`. Consecuencias encadenadas: el editor de plantillas del admin no las
  pintaba, su guardado **sobreescribe** la opción con las de la lista, y `send()` descarta en
  silencio una plantilla que no existe. Resultado: cambiar el email desde el perfil no llegaba a
  confirmarse nunca, porque el correo con el enlace no se mandaba.
  Ahora están en la lista y `maybe_migrate()` **repone** las plantillas de fábrica que falten sin
  tocar las que el sitio tenga (antes solo sustituía cadenas en las que ya estaban).
- Los botones de esos dos correos usan `Email_Layout::button_html()`, como el resto del ecosistema,
  en vez de estilos sueltos metidos a mano.


## v2.8.11 (2026-09-26)

### Corregido
- **Correo de bienvenida**: el asunto y la primera línea del cuerpo eran la misma frase (el nombre
  del sitio entraba en las dos), así que el correo empezaba repitiendo su propio asunto. El asunto
  pasa a presentar («¡Bienvenido/a, {nombre}! Ya formas parte de {sitio}») y el cuerpo saluda sin
  repetirlo. La migración de plantillas reescribe el saludo guardado **solo** si conserva el de
  fábrica; si el sitio lo personalizó, se respeta.
- **Objetivo de voluntariado completado**: no se envía si el miembro no tiene horas acreditadas.
  El correo felicitaba por «completar 0h» y ofrecía un certificado inexistente; queda registrado en
  el log en lugar de mandarse.

## v2.8.10 (2026-09-25)

### Cambiado
- **Regla única de voluntariado**: `Admin_Voluntariado::puede_acreditar_horas()` decide quién puede
  registrar horas (rol `voluntario_aprobado` o `_convoca_voluntario_aprobado = 1`). El compromiso del
  alta es una **solicitud**, no un permiso: antes el formulario prometía renovación sin cuota por
  marcarlo y el motor de horas exigía estar aprobado.
- La aprobación **sincroniza la solicitud en la ficha** del socio (el permiso sigue viviendo en el
  usuario, que es donde escribe la aprobación).
- Texto del formulario de alta: dice lo que el sistema hace — se solicita, la asociación aprueba y
  con las horas aprobadas se renueva sin cuota.

### Añadido
- 9 casos de regresión del permiso (socio normal, compromiso sin aprobar, pendiente, aprobado,
  revocado, sin cuenta, antes/después de aprobar, independencia de la cuota).


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