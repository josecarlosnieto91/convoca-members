## [2.8.94] — 2026-10-16

### Arreglado
- **El aviso de solicitud de supresión dejaba una línea coja.** El correo a la junta montaba el enlace a la
  ficha con `get_edit_post_link()`, que devuelve **vacío** fuera del escritorio: el aviso salía con un
  «Ficha: » colgando (medido capturando el correo con un filtro). Ahora el cuerpo se monta por líneas y la
  de la ficha solo aparece si hay enlace. De paso, el texto pasa a cadenas traducibles con sus
  posiciones, para que el catálogo no se rompa si alguien reordena los datos.

