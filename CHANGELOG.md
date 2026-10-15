## [2.8.44] — 2026-10-14

### Revertido (con el motivo escrito)
- **Se revierte el mapeo de capacidades de los tipos con datos personales que entró en 2.8.43.** La
  2.8.43 hacía que un `editor` no pudiera listar ni borrar fichas de socios, y **rompía el mecanismo de
  capacidades de WordPress**: al asignar la capacidad propia `gestionar_miembros` al hueco de la
  capacidad meta `edit_post` del tipo de contenido, WordPress la resuelve contra una entrada concreta
  y, cuando se comprueba sin entrada —que es como la comprueban las pantallas propias del plugin—
  devuelve `do_not_allow`. Medido en el sitio:

  ```
  map_meta_cap( 'gestionar_miembros', 2 )  ->  do_not_allow
  map_meta_cap( 'manage_options', 2 )      ->  manage_options
  map_meta_cap( 'gestionar_documentos_voluntariado', 2 ) -> gestionar_documentos_voluntariado
  ```

  Es decir: sólo esa capacidad dejaba de valer, y **para todo el mundo, administrador incluido**, así
  que el gobierno de socios quedaba inaccesible. Un defecto funcional peor que el que se arreglaba,
  que además era latente (ningún sitio tiene usuarios con rol `editor`).
- El defecto original (**un `editor` puede ver el listado de socios y mandar fichas a la papelera**)
  queda **abierto** en su issue, con esta medición como punto de partida y la advertencia de por dónde
  NO va el arreglo.
- Guarda para que no se reintente mal: `tests/Unit/CapacidadesSinColisionTest.php` — ninguna capacidad
  propia puede ocupar el hueco de las meta del núcleo (`edit_post`, `read_post`, `delete_post`), con
  negativo probado.

## [2.8.42] - 2026-10-14

### Arreglado

- **La web del cliente ya no muestra la marca del proveedor** (recorrido a ciegas, criterio 16).
  En el alta de socio, la tarjeta decía «🍁 CONVOCA», el consentimiento «Autorizo a Convoca a
  enviarme comunicaciones» y la bienvenida «¡Bienvenido/a a Convoca!»: quien se hacía socia en la
  web de Los Lugg veía el nombre del software en vez del de la asociación. Ahora es un ajuste
  («Nombre de la entidad») con el nombre del sitio de WordPress como respaldo.
- **`<a href="">` fuera del producto**: «Código Ético» se pintaba con
  `apply_filters( 'convoca_members_alta_document_url', '' )`, cuyo respaldo es la cadena vacía — un
  enlace que no lleva a ninguna parte, en HTML, y que el usuario pulsa. Ahora, si el documento no
  está configurado, se muestra su nombre **sin enlace**.
- **Higiene: tres URLs de Google Drive de la entidad hardcodeadas en las plantillas** (issue #12).
  Dónde apuntan los documentos de una entidad es decisión suya: pasan a ser ajustes del panel.
- **La casilla del RGPD da señal cuando bloquea el avance.** El aviso ya se pintaba, pero el campo
  no se marcaba ni recibía el foco: quien navega con teclado o lector de pantalla oía un mensaje sin
  saber a qué campo se refería (medido con el navegador sobre la demo el 14/10/2026). Ahora el grupo
  se marca (`has-error`), la casilla lleva `aria-invalid` y se le lleva el foco.
- **La tarjeta ya no se inventa el número de socio**: decía «Socio/a #TEMP» mientras no había
  número. El número lo asigna el servidor al confirmar; hasta entonces se dice lo que pasa.

### Añadido

- Cuatro ajustes nuevos en el panel: nombre de la entidad, Código Ético, Programa de Voluntariado y
  Protocolo de prevención del acoso (los tres últimos, como URL).

### Pruebas

- 7 pruebas nuevas (`IdentidadDelSitioTest`), 204 en total. Tres comprobaciones probadas **en
  negativo**: con el filtro del respaldo vacío de vuelta, con la marca del proveedor de vuelta y con
  el número de socio inventado, la prueba falla.

### Nota de instrumento

- Escribir la etiqueta de cierre de PHP dentro de un comentario de línea **cierra el modo PHP**: el
  fichero entero pasa a leerse como HTML y el error que aparece después («Unclosed '{'») no señala a
  esa línea. Costó una vuelta de reloj.

## [2.8.39] - 2026-10-13

### Arreglado

- **`PHP Deprecated` en la tabla de horas**: dos columnas (socio y actividad) pasaban
  `get_edit_post_link()` directo a `esc_url()`; esa función devuelve `null` cuando la ficha no existe
  o no es editable y `esc_url( null )` deja `ltrim( null )` en el registro. Ahora, sin enlace, se
  pinta el nombre sin `<a>`.

### Pruebas

- `EnlacesDeEdicionTest` (probado en negativo: con el defecto repuesto, falla).

## [2.8.38] - 2026-10-13

### Arreglado
- **El acceso al panel de socio, sin JavaScript, también era un callejón sin salida**: su formulario
  no declaraba `method` ni `action`, así que el envío se perdía (GET a la misma página, sin
  procesarse y sin decir nada). Ahora envía por POST con marcador
  (`convoca_sin_js_panel_socio`), avisa con `<noscript>` antes de rellenar y, si la petición llega
  sin JS, dice qué pasa y ofrece el correo de la asociación (filtro `convoca_panel_socio_sin_js_email`).
- Con esto los **cinco** formularios públicos del ecosistema (alta, voluntariado, inscripción,
  panel de reservas y acceso al panel) declaran POST y su degradación: el criterio «móvil y sin
  JavaScript» ya se puede medir entero.
- Prueba añadida a `tests/Unit/SinJavaScriptTest.php` (comprobación en el código del formulario del
  panel), probada **en negativo**.

## [2.8.37] - 2026-10-13

### Arreglado
- **Sin JavaScript, el alta de socio y el voluntariado eran un callejón sin salida.** Medido con
  Playwright sin JavaScript (390x844): los dos formularios no declaraban `method` ni `action`, así
  que el navegador los enviaba por GET a la misma página con los campos en la URL y **nadie los
  procesaba**: el alta se perdía en silencio, sin guardar nada y sin una frase que lo explicara.
  (La primera medición atribuyó al alta un «Página no encontrada» que en realidad era el **buscador
  del tema** — el arnés cogía el primer `form` de la página. El defecto del alta es el de arriba,
  comprobado en el HTML servido y re-medido en el sitio ya desplegado.)
- Ahora envían por **POST a su propia página** con un marcador (`convoca_sin_js_alta` /
  `convoca_sin_js_voluntariado`). Cuando la petición llega sin JavaScript, el template explica la
  situación y ofrece el correo de la asociación (con el teléfono si está configurado); el
  `<noscript>` lo avisa **antes** de rellenar. Los datos de contacto salen de los ajustes del
  plugin (`admin_email`, `telefono`) con filtros `convoca_alta_sin_js_*` y
  `convoca_voluntariado_sin_js_*`: nada escrito a mano en el código.
- Prueba `tests/Unit/SinJavaScriptTest.php` (una parte ejecuta el template con el marcador y
  comprueba que no se pinta el formulario), probada **en negativo** con los templates de HEAD.

## [2.8.36] - 2026-10-13

### Arreglado
- **El alta de socio y el voluntariado respondían «Error desconocido.» cuando la petición fallaba**
  sin mensaje del servidor. El texto lo resuelve ahora la capa común de `convoca-core`
  (`conv.mensajes` / `conv.normalizarError`): dice qué hacer en cada caso (sin conexión, sesión
  caducada, acción no disponible) y traduce el `0` y el `-1` de `admin-ajax`, que antes llegaban
  como un número y acababan en un respaldo inventado por el llamador.
- Los dos botones de la ficha del socio (link de pago y recordatorio) y los dos de RGPD (exportar
  y borrar datos) pasan por `convAdmin.ajaxPost`; el texto del servidor se escapa antes de
  pintarlo.
- Prueba `tests/Unit/MensajesDeErrorTest.php`, probada **en negativo** con el JS de HEAD.

## [2.8.35] - 2026-10-12

### Arreglado
- **Seis llamadas AJAX del JavaScript publicaban acciones que no existen**: el JS seguia usando
  el prefijo viejo `conv_` mientras el plugin registra `convoca_`. Lo encontro el auditor a ciegas
  de la pasada 6 (una) y la comprobacion de contrato que se ha anadido, las otras cinco:
  `conv_get_nonce` (el endpoint del nonce «bypass cache»: muerto, asi que en una pagina cacheada
  el alta respondia «Nonce invalido» sin poder refrescar), `conv_export_csv` y
  `conv_save_csv_columns` (exportar CSV y guardar columnas), `conv_change_state`,
  `conv_log_whatsapp` y `conv_voluntariado_submit`.
- Se anade `tests/Unit/AjaxAccionesTest.php`: cruza las acciones que publica el JS con las que
  registra el PHP, en las TRES formas de publicarlas (ayudante, objeto de jQuery y `action=` dentro
  de una URL, que era la que se colaba). Probado en negativo; sin el arreglo falla y las nombra.

# Changelog — convoca-members

## [2.8.48] - 2026-10-15

### Corregido

- **Importar CSV podía duplicar a un socio que ya existía** (medido al verificar #26): el control de
  duplicados miraba ANTES al usuario de WordPress y, si el correo era de un usuario, devolvía «no hay
  duplicado». Como a casi todos los socios el alta les crea un usuario con su correo, el control no veía a
  ninguno de ellos: subir un CSV con un socio ya existente creaba una ficha repetida y el resumen decía
  «1 importados, 0 errores». Ahora manda la ficha (por correo y por DNI) y el usuario solo se mira cuando
  no hay ninguna.

## [2.8.47] - 2026-10-15

### Corregido

- **Un alta con el nombre y el DNI en blanco se aceptaba** y dejaba el deduplicador envenenado (#23): la
  validación usaba `empty()`, y `empty( '     ' )` es **FALSE** —una cadena de espacios no está vacía—, así
  que el alta pasaba y se saneaba después: quedaba una ficha con el título vacío y el `_convoca_dni`
  vacío. A partir de ahí, cualquier otra persona que dejara el DNI en blanco recibía «Ya existe un miembro
  registrado con este DNI o email.» porque el deduplicador casaba con el DNI vacío de la ficha fantasma.
  Ahora se sanea antes de validar y el deduplicador solo compara contra datos que existen.
- **Importar CSV contaba como importadas las filas que fallaban** (#26): el resumen decía «1 socios
  importados correctamente. 1 errores» de la MISMA fila. Además, la llamada pasaba el contador en el
  parámetro del número de fila, así que el mensaje salía como «Fila 0» en vez de la fila de verdad. Ahora
  solo cuenta lo creado y el número de fila es el de la fila.
- **`convoca_member_sequence` acumulaba filas huérfanas** (#16): nadie borraba la fila al eliminar un
  socio, así que la tabla acabó con 124 de 124 filas huérfanas en la demo y 4 de 4 en Lugg. La fila se
  borra ahora al eliminar la ficha definitivamente (no al enviarla a la papelera: ahí se puede restaurar y
  tiene que conservar su número) y el paso de actualización 2.8.47 limpia lo acumulado. Borrar filas no
  reutiliza números: el número de socio sale del AUTO_INCREMENT.

## [2.8.46] - 2026-10-15

### Corregido

- **Ocho defectos del panel del socio y del alta** (auditoría de navegador):
  - La pestaña de Voluntariado no cargaba nunca: pedía `/activities`, un endpoint que no existía, y su
    `Promise.all` fallaba entero. Se añade el endpoint **y** cada petición lleva su propio respaldo, para
    que un solo fallo no tumbe la pestaña.
  - El certificado se podía descargar **sin haber completado las horas**: el endpoint de descarga no
    comprobaba nada. Ahora aplica la misma regla que la pantalla.
  - Cada descarga **acuñaba un identificador nuevo** y sobrescribía el guardado, así que los
    certificados ya entregados dejaban de verificar. El identificador se emite una sola vez.
  - El carnet abría una pestaña con **el JSON en crudo** en vez de la tarjeta (se escribía la respuesta
    entera en lugar de su campo `html`).
  - La pestaña de pagos mostraba el estado interno **en inglés** (`Paid`): ahora dice «Pagado».
  - El resumen del alta decía «Comunicaciones: No» aunque la casilla estuviera marcada (leía un nombre
    de campo que no existe).
  - El aviso de error del formulario de voluntariado **se quedaba en pantalla** tras un envío correcto.
  - La marca de voluntariado del alta **no llegaba a «Solicitudes Pendientes»**: se quedaba en la ficha
    del socio y nadie la revisaba.

## [2.8.45] - 2026-10-15

### Corregido

- **Mensaje de error del teléfono**: decía «9 dígitos, **mulaiing** con 6-9». Además de la errata, no se
  entendía; ahora dice «9 dígitos, empezando por 6, 7, 8 o 9». Lo cazó la auditoría independiente de la
  pasada 11 del gauntlet y lo ve cualquiera que se equivoque al escribir su teléfono.

## v2.8.34 (2026-10-12)

### Cambiado
- Higiene de datos: el comentario que documenta el `show_ui` de `tipo_miembro` citaba un
  sitio concreto por su nombre. Se conserva el motivo entero (por que WordPress devuelve 500
  y no 403) y se quita el nombre, que no pinta nada en el codigo del producto. Lo caza el
  propio check de higiene de la bateria.

## v2.8.33 (2026-10-12)

### Arreglado
- **La pantalla de terminos de `tipo_miembro` respondia HTTP 500.** La taxonomia se
  registraba con `public => false` y sin `show_ui`; en una taxonomia `show_ui` hereda de
  `public`, asi que se quedaba sin interfaz, y WordPress 7 rechaza `edit-tags.php` de una
  taxonomia sin interfaz con un `wp_die` **sin estado**: al navegador le llega un 500 (y no
  un 403) con «no tienes permisos», con el administrador delante. Reproducido en la demo y
  en Lugg.
- Se declara `show_ui => true` y `show_in_menu => false` (la navegacion de miembros es la
  suya). `public` sigue en `false`: los tipos no exponen URLs.
- Regresion blindada: `tests/Unit/TaxonomiaTipoMiembroTest.php` mira la taxonomia
  registrada de verdad.

## v2.8.31 (2026-10-12)

### Arreglado
- **El alta de socio dejaba al socio sin cuenta** cuando su correo ya pertenecia a un usuario
  de WordPress (el administrador que se da de alta como socio, un monitor con su correo de
  trabajo, o un socio al que se le vuelve a dar de alta). Se intentaba crear otro usuario, el
  correo ya estaba en uso, y el reintento usaba el MISMO correo: fallaba igual, el socio
  quedaba de alta pero **sin poder entrar en Mi Area**, y el log acumulaba dos ERROR por
  intento. Ahora ese caso **enlaza el usuario que ya existe**.
- La decision se extrae a `plan_user_account()` (publica, con las entradas explicitas) y el
  enlace va en un unico sitio, `link_user_to_member()`, en los dos sentidos.
- Prueba `MemberAccountPlanTest`, probada en negativo.

## v2.8.30 (2026-10-12)

### Arreglado
- **PHPCS a 0 avisos y 0 errores** en todo el ecosistema, sin el silenciador
  `ignore_warnings_on_exit` en el CI. Entre los avisos habia defectos reales:
  un `var_dump` alcanzable por URL (`?debug=1`) que volcaba los metadatos de una
  inscripcion sin escapar, una clase `CLI_Webhooks` declarada en dos ficheros
  (fatal si se cargaban ambos) y 30 `wp_redirect` que pasan a `wp_safe_redirect`.
- 11 `@` que ocultaban errores de PHP, sustituidos por comprobaciones reales: un `@`
  no arregla nada, esconde el sintoma y deja el registro de avisos mintiendo.
- 14 `error_log()` que solo escribian en el log del servidor pasan al `Logger` del
  ecosistema, que ademas los muestra en el panel.
- `urlencode()` -> `rawurlencode()` en 4 sitios (en una query string, `urlencode`
  codifica el espacio como `+`), `in_array` con comparacion estricta, comparaciones
  `===`, `current_time('timestamp')` y `serialize()` sustituido por `wp_json_encode`.

### Notas
- Sin cambios de comportamiento en los flujos de usuario: lo arreglado son avisos
  del analizador, seguridad de redirecciones y registro de errores.

## v2.8.29 (2026-10-11)

### Arreglado
- Mismo criterio de **fallbacks encadenados** en el `.btn-primary` del área privada: el sitio que
  declare `naranja-oscuro` en su paleta lo usa (texto blanco, 5.07:1) y el que no conserva el
  naranja de marca, para no romper a los temas que ajustan el color del texto por su cuenta.

## v2.8.28 (2026-10-11)

### Arreglado
- **Contraste de los botones del área privada** (`public/assets/mi-area.css`): `.btn-primary`
  pintaba texto blanco sobre `#ff8700` = **2.41:1** (mínimo 4.5:1) y en `:hover` **aclaraba** a
  `#ffa033`, o sea empeoraba el contraste. Ahora usa `naranja-oscuro` (**5.07:1**) y el hover
  oscurece a `#8f4400` (**7.01:1**). `.btn-link:hover` también pasa a naranja-oscuro.
  Medido con axe: el botón «Entrar» de Mi Área.

## v2.8.27 (2026-10-11)

### Arreglado
- **El botón del paso 1 del alta usaba clases genéricas** (`btn btn-primary`) mientras los pasos
  2, 3 y 4 usaban las del ecosistema (`convoca-btn convoca-btn-primary`): el botón del primer paso
  no seguía el sistema de diseño del producto. Ahora los cuatro son iguales.
  Cubierto por `tests/Unit/BotonPrimarioTest.php`.

## v2.8.26 (2026-10-11)

### Arreglado
- **Contraste del formulario de alta de socio (WCAG 2.1 AA)**, medido con axe en la demo:
  - `.conv-price` (el precio de cada plan) usaba el naranja de marca, **2.41:1** sobre blanco.
    Pasa a `--wp--preset--color--naranja-oscuro` (**5.07:1**).
  - `.conv-alt` («· 15h para renovar sin pagar») usaba `#888`, **3.54:1** siendo texto pequeño
    (exige 4.5:1). Pasa a `--wp--preset--color--gris-texto` (**6.28:1**).
  - Los bordes y los `accent-color` en naranja se dejan: no son texto y no tienen ese requisito.

## v2.8.25 (2026-10-11)

### Corregido
- **El correo de objetivo de voluntariado se enviaba DOS veces.** `Email_Manager` registraba
  el mismo callback en el nombre nuevo (`convoca_members_email_objetivo_voluntariado`) y en el
  retirado (`convoca_email_objetivo_voluntariado`), y `Utils::do_action()` dispara los dos
  cuando hay oyente en el viejo: el voluntario recibía dos copias del correo y cada envío
  dejaba una línea `PHP Deprecated` en el log. Se registra solo el nombre nuevo. Un comentario
  del fichero decía «Legacy hooks removed» mientras la línea seguía ahí; el comentario ahora
  describe lo que hace el código.

## v2.8.24 (2026-10-09)

### Arreglado — el alta del socio: la revisión, el método de pago y el texto del IBAN (cierra #3, #4 y #5)

- **La revisión del paso 4 no mostraba 8 de los 18 datos** (whatsapp, canal, edad, menor, modalidad,
  importe, fecha y comunicaciones): la plantilla definía 10 nodos y el JS rellenaba 18, así que los 8
  que faltaban se escribían sobre nodos inexistentes y **sin dar ningún error**. Añadidas las filas.
- **El método de pago se guardaba en el meta equivocado.** El alta escribía tarjeta/bizum/transferencia
  en `_convoca_forma_pago`, un meta que en el resto del plugin significa cuota vs voluntariado: la
  ficha del socio salía sin selección válida, al guardar se pisaba a «cuota» y el CSV mostraba
  «transferencia» como forma de pago de la cuota. Ahora el método va a `_convoca_metodo_pago` y
  `_convoca_forma_pago` queda en «cuota», coherente con lo que normaliza `Payment_Listener`.
- **El paso 3 prometía el IBAN** y la pasarela volvía a pedir el método: texto corregido para que
  describa lo que pasa de verdad.

### Añadido
- **Los dominios de correo del equipo salen del código y pasan a Ajustes** (campo «Dominios de correo
  del equipo», separados por «|»). El valor por defecto es automático —el dominio del email del propio
  sitio más el del proveedor—, así que cada instalación reconoce los suyos sin que el código lleve el
  nombre de ninguna. El valor se sanea y los puntos se escapan antes de entrar en el patrón.

### Cambiado
- Higiene del producto: retirados el nombre del cliente y el de la asociación de textos y muestras.

### Internamente
- Las pruebas usan las clases reales del núcleo en vez de dobles que se quedaban cortos, y se corrigió
  el bootstrap de la suite unitaria, que falseaba el núcleo.

## v2.8.23 (2026-09-27)

### Arreglado — dos cifras de cara al socio salían con el punto decimal inglés (bloque 9)

El flujo del certificado, revisado de punta a punta (formulario público → `verify()` → resultado),
dejó a la vista un desliz que no era del PDF sino de la página:

- **La verificación pública del certificado** imprimía las horas con `number_format( $horas, 1 )`, o
  sea con los separadores de PHP: **«12.5 h»**. Ahora usa `Texto_PDF::horas()` → **«12,5 h»**.
- **El importe de renovación del panel del socio** (`class-mi-area.php`) igual: `number_format(
  $importe, 2 )` daba **«50.00 €»** → ahora **«50,00 €»**, como ya hacían otras dos partes del
  plugin (`Rest_API` y `GDPR_Tools` sí pasaban los separadores explícitos).

Sin decimales no hay problema (`number_format( $x, 0 )` para euros enteros se queda como está).

Guardado con `FormatoCifrasTest`, que vigila **la clase de fallo** y no solo estos dos sitios:
recorre `includes/`, `public/` y `admin/` y falla si alguna cifra con decimales se formatea sin los
separadores explícitos. Comprobado que el guardián **caza** el fallo (se metió a propósito y lo
señaló con fichero y línea) y que los dos arreglos están en su sitio.

## v2.8.22 (2026-09-27)

### Arreglado — completada la resolución del plan por etiqueta (2.8.21 no bastaba)

La 2.8.21 buscaba el plan por etiqueta, pero comparaba en crudo y **las etiquetas llevan adorno**:
la de la demo es literalmente «🥉 Bronce» y el socio tiene guardado «Bronce» (castellano, con la
clave en inglés: `bronze`). No casaba, así que el carnet seguía saliendo «SOCIO/A».

- La comparación quita ahora todo lo que no sea letra, número o espacio —el emoji, los guiones, los
  adornos— antes de comparar, además de acentos, mayúsculas y espacios de sobra.
- Caso real fijado en `CPTMiembroTest`: clave `bronze`, etiqueta `🥉 Bronce`, socio `Bronce` → el
  carnet muestra la modalidad.

## v2.8.21 (2026-09-27)

### Arreglado — el carnet no distinguía la modalidad del socio (bloque 8)

`CPT_Miembro::get_plan()` buscaba el plan **solo por la clave exacta**. Un socio guardado con la
**etiqueta** en vez de la clave —así están los sembrados de la demo, con «Bronce» donde debería ir
`bronze`— no resolvía, y los tres documentos caían al texto genérico: el carnet salía con
**«SOCIO/A»** en lugar de la modalidad, el acuerdo sin el plan y el certificado sin la etiqueta.

- Ahora, si la clave exacta no existe, se busca también por la **etiqueta** del plan y por la clave
  normalizada (sin acentos, sin distinguir mayúsculas, sin espacios de sobra).
- Si la clave exacta existe, se devuelve como siempre: no cambia nada de lo que ya funcionaba.
- Afecta a los tres documentos (carnet, acuerdo y certificado) y a cualquier consumidor del plan.

Guardado con tres casos en `CPTMiembroTest`: por clave, por etiqueta (con caja y espacios distintos)
y el nulo cuando no existe.

## v2.8.20 (2026-09-27)

### Arreglado — la modalidad larga rompía la cabecera del carnet (bloque 8)

El distintivo de la modalidad se pintaba en la misma línea que el logo. Con las etiquetas cortas
(«Ejemplo», «Deva», las dos activas hoy en Ejemplo) cabía; con una etiqueta larga —p. ej. «Modalidad
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
- Medido con las cuatro modalidades: `ejemplo` y `deva` (una línea, sin cambios), `Modalidad Familiar`
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
  tiene: en Ejemplo, /turnos/ y /mi-perfil/.
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
- **Proveedor de email pejemploable**: `Email_Verifier` (wp_mail por defecto, Mailgun opcional)

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