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

