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

