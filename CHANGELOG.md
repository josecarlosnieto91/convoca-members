## [2.9.11] — 2026-10-17

### Arreglado
- **El correo del cambio de email llegaba entero en inglés (issue #60).** No era el catálogo: las
  plantillas de correo de fábrica son texto **ya traducido** que se guarda en la opción
  `convoca_email_templates`, así que quedan **congeladas** en el idioma en que se sembraron. En la demo
  la siembra ocurrió con el catálogo en inglés y desde entonces los correos salían en inglés aunque el
  sitio esté en castellano — y el pie en castellano, porque viene de otro sitio. Medido en un envío real
  (capturado por el mailcatcher de la demo):

  ```
  Asunto: Confirm your new email — Convoca Demo
  Cuerpo: Hi Mika QA Demo, You have requested to change your contact email to …
  ```

  El arreglo no es traducir al enviar (las plantillas las edita el admin): ahora se **anota el idioma de
  siembra** y, si deja de coincidir con el del sitio, se re-siembran **solo las plantillas que siguen
  siendo las de fábrica de su idioma de origen**. Lo que el admin haya escrito no coincide con ninguna
  de fábrica y se respeta. Cubre también los sitios sembrados antes de existir esta marca.

### Pruebas
- `PlantillasDeCorreoEnElIdiomaDelSitioTest` (4 casos: sin marca, mismo idioma, personalizada respetada y
  enganche) y dobles de `determine_locale`/`switch_to_locale`/`restore_previous_locale` en el bootstrap
  unitario, que el del núcleo no trae. 418 tests, 1600 aserciones.

## [2.9.10] — 2026-10-17

### Arreglado
- **El panel ofrecía el área de horas del voluntariado a quien no es voluntario (issue #55).** Con una
  cuenta sin voluntariado se veía todo: «Mis Horas de Voluntariado», los niveles, el formulario «Añadir
  Nuevo Registro» con la lista de proyectos y «Progreso hacia el certificado». Ahora el panel lo sabe
  porque `/me/horas` devuelve el estado del voluntariado, y a quien no lo es se le explica cómo pedirlo
  en vez de ofrecerle un formulario que no le corresponde.

  El detalle que costaba: `estado()` devuelve **«solicitud» por defecto**, también para quien no ha
  pedido nada, así que «no es voluntario» no es «estado vacío». Lo que distingue es **haber pasado el
  circuito** (`entrevista`, `activo`, `desvinculado`) **o** tener horas registradas — quien se desvinculó
  sigue viendo lo suyo, que son sus datos y su certificado.
- **El progreso del certificado imprimía `undefined / 15 horas (undefined%)` (issue #55).** El arreglo
  anterior cubrió el porcentaje y dejó el numerador: ahora usa el total ya calculado.

### Pruebas
- `AreaDeHorasDelPanelTest`: el estado del voluntariado viaja en `/me/horas`, el panel lo comprueba antes
  de ofrecer el área y el progreso no imprime `undefined`. 413 tests, 1592 aserciones.

## [2.9.9] — 2026-10-17

### Arreglado
- **«Error cargando pagos» en la pestaña de Pagos y Cuotas (issue #56).** El panel llamaba a
  `this.esc()` desde cuatro sitios, pero en el objeto sólo existían `escAttr` y `escHtml`: `esc` **no
  estaba**. La rama del recibo es la única que lo usa dentro de Pagos, por eso la pestaña cargaba bien
  con un pago pendiente (sin recibo) y reventaba con un pago pagado y su recibo. Medido en la demo con
  la ficha 2215, con la consola abierta:

  ```
  convoca-members: error cargando pagos TypeError: this.esc is not a function
    at mi-area.js:665  (rama del recibo)
  ```

  Se añade `esc()` (escape de contenido). Los otros dos usos que estaban roto por lo mismo —la línea de
  la política aceptada en «Mis datos y privacidad» y el mensaje de respuesta al renovar por horas—
  quedan arreglados con el mismo cambio.

### Pruebas
- `AyudantesDelPanelTest`: comprueba que **todo** `this.X()` que usa el panel tiene su definición. Es la
  comprobación que habría cazado esto el primer día. 410 tests, 1587 aserciones.

## [2.9.8] — 2026-10-16

### Arreglado
- **Al aceptar el acuerdo no se generaba el documento: la ficha señalaba uno ajeno y caducado
  (issue #58).** `PDF_Document::generate_volunteer_agreement()` comprobaba antes si el socio «ya tenía»
  documento, pero esa comprobación filtraba **sólo por `_convoca_usuario_id`**: el primero que
  apareciera valía. Con un DNI caducado de otra prueba en la ficha, devolvía **ese** documento
  (2678, «QA DNI caducado», sin huella y con fecha de caducidad pasada) y no generaba nada. Ahora el
  documento del acuerdo se marca con `_convoca_es_acuerdo_voluntariado` y las **dos** consultas (la
  previa y la que va con bloqueo) exigen esa marca, así que un acuerdo nuevo se genera de verdad.

### Pruebas
- `AcuerdoDeVoluntariadoTest`: vigila que las dos consultas lleven su join con la marca y que al crear
  el documento se ponga. 408 tests, 1584 aserciones.

## [2.9.7] — 2026-10-16

### Arreglado
- **El tablero de voluntariado cambiaba estado por GET (issue #63).** «Desvincular» y el formulario de
  citar eran `method="get"`, y confirmar la baja o dar la entrevista por hecha eran enlaces `<a href>`:
  la acción viajaba en la URL. Con el nonce delante no era un agujero, pero desvincular a alguien no se
  debe disparar con un prefetch, un escáner o un clic accidental. Ahora:
  - la **acción se lee sólo de `$_POST`** (un GET no cambia nada),
  - los dos formularios van por `post`,
  - los tres enlaces de acción pasan a formularios con botón (`boton_post()`), con su nonce dentro,
  - los datos (ficha, fecha, hora, causa, motivo) se leen con POST primero y GET como respaldo, para que
    un enlace antiguo no rompa la lectura.

  Y de paso: un POST que antes devolvía 200 con el cuerpo vacío y no hacía nada ahora es el camino
  normal de la acción.

## [2.9.6] — 2026-10-16

### Corregido
- **La baja pedida seguía quedando con autor 0 (issue #59, el arreglo de 2.9.5 no bastaba).** Se usaba
  el `_convoca_usuario_id` en crudo, que en esa ficha está **vacío**; el ayudante que ya existía,
  `usuario_de_ficha()`, lo resuelve y devuelve el 145. Medido en la demo: `usuario_de_ficha(2215)` = 145.
  Sin duplicar código: se retira el ayudante que había añadido y se usa el de la casa.

## [2.9.5] — 2026-10-16

### Arreglado
- **El botón «Añadir» del registro de horas no hacía nada (issue #57).** El manejador llamaba a
  `conv.form.validate()`, que **no existe** en el JS del panel: lanzaba un TypeError que se tragaba el
  listener, así que no había ni mensaje ni error ni registro — y por el camino del producto
  (`POST /me/horas`) sí funcionaba, que es lo que despistaba. Ahora valida con el navegador
  (`checkValidity`).
- **La baja pedida por el socio quedaba con autor 0 (issue #59).** `registrar()` tomaba
  `get_current_user_id()`, que en el contexto de la petición del panel llega vacío: el historial decía
  «baja_solicitada, autor 0» y no quién la pidió, justo en el paso donde el relevo tiene que quedar
  claro. `registrar()` acepta ahora el autor explícito y la petición de baja resuelve el dueño de la
  ficha cuando no hay sesión en el contexto.
- **«Error cargando pagos» sin ninguna pista (issue #56, diagnóstico).** El `catch` se tragaba el error
  real. Ahora queda en la consola con su traza, para poder medirlo en la demo.
- **El progreso del certificado imprimía `undefined / 15 horas (undefined%)` (issue #55, parte).** Con
  0 h la API no manda el porcentaje: se calcula, y si no hay objetivo, 0.
- **Tres deprecaciones por `ReflectionMethod::setAccessible()` (issue #61).** No hace nada desde PHP 8.1
  y está anunciada como deprecada para 8.5; las pruebas leen la propiedad reflejada sin ella. La suite
  pasa de «3 deprecaciones» a **ninguna**.

### Añadido
- Contrato `FormularioDeHorasDelPanelTest`: vigila que el panel no vuelva a llamar a un validador
  inexistente, que el formulario de horas se maneje por delegación y que el progreso no imprima
  `undefined`. Lee el JS **sin comentarios** (el arreglo explica en un comentario qué se llamaba antes).

## [2.9.4] — 2026-10-16

### Arreglado
- **A quien no es voluntariado no se le ofrecía el circuito** (members#51). El paso «en activo» se derivaba solo
  de la marca de aprobación, y con la marca ausente se daba por aprobada: una socia que nunca pidió ser
  voluntaria veía «Estás en: Voluntariado en activo» y los botones de aceptar el acuerdo y darse de baja. Ahora
  «en activo» exige ser voluntariado **y** estar aprobado, y el bloque solo se pinta a quien está en el circuito
  —es voluntariado, o alguna vez lo fue—. Un desvinculado sigue viendo el cierre de su etapa.
- **Un cuestionario en blanco cerraba la entrevista** (members#52): daba las gracias y pasaba a la persona a
  activo sin haber contestado nada. Ahora, con cuestionario configurado, hace falta al menos una respuesta; el
  panel lo dice y no cambia el paso. La junta sí puede dar la entrevista por hecha sin cuestionario desde el
  tablero: es su decisión, no un descuido.
- **Una marca de monitor escrita a mano sobrevivía al rechazo** (members#53). El rol de WordPress se negaba,
  pero `_convoca_es_monitor` se quedaba y `es_monitor()` contestaba que sí. Ahora `es_monitor()` exige el
  voluntariado, y el rechazo retira la marca.
- **El repaso diario de caducidad no veía a los voluntariados sin la marca de paso** (members#54): la consulta
  filtraba por `_convoca_voluntariado_estado != 'desvinculado'`, que es un INNER JOIN, así que las fichas
  anteriores al circuito —las que no tienen esa marca— quedaban fuera y no se revisaban nunca, aunque el
  tablero las enseñara «en activo». Ahora la consulta usa la misma vara que el tablero.

### Notas
- Cuatro pruebas nuevas: el circuito no se ofrece a quien no es voluntariado (y sí al desvinculado, para ver su
  cierre), el cuestionario en blanco no cierra la entrevista (y la junta sí puede darla por hecha), y la marca
  de monitor escrita a mano no vale. Total: 401 pruebas.

