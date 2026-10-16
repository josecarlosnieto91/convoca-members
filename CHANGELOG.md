## [2.9.1] — 2026-10-16

### Añadido
- **El circuito del voluntariado, en el panel del socio.** La pestaña «Voluntariado» encabeza ahora con el
  bloque que dice en qué paso está la persona y **qué le toca a ella**: si está citada, contesta el
  cuestionario de la entrevista desde ahí; si le falta el acuerdo, lo acepta (y se le genera firmado, con su
  fecha); y si quiere irse, pide la desvinculación contando lo que quiera contar. Solo lo que decide la
  persona: lo que decide la junta —dar el paso a voluntariado, confirmar la baja— no se ofrece desde el panel.
- Rutas nuevas del panel: `GET`/`POST /me/voluntariado`. El bloque se pide aparte del resto de la pestaña, para
  que un fallo suyo no deje sin ver las horas ni los proyectos.

### Notas
- El bloque va montado en el servidor, así que los textos, los formularios y sus protecciones no dependen del
  JavaScript: el JS solo lo pinta y manda los pasos.

