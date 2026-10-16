## [2.8.100] — 2026-10-16

### Arreglado
- **El voluntario ya puede apuntar sus propias horas (members#37).** El formulario «Añadir Nuevo Registro» de
  la pestaña de Voluntariado **ya existía** y enviaba un `POST` a `/me/horas`, pero esa ruta solo aceptaba
  `GET`: el `fetch` no encontraba a quién llamar y no había forma de anotar «hoy he ido a limpiar el río».
  Ahora la ruta acepta el `POST` y las horas entran por `Hours_Manager::save_hours_record( $datos, false )` —el
  mismo camino que usa la junta, sin duplicar nada—, en estado **pendiente**: no cuentan para el total ni para
  el nivel hasta que la JD las apruebe. La ficha es siempre la del socio con sesión.
- El formulario lo avisa: «Queda pendiente de que la junta lo apruebe: hasta entonces no cuenta en tu total ni
  en tu nivel», y se guarda también la actividad opcional.
- Cuatro pruebas nuevas (`HorasDelVoluntarioTest`), una de ellas comparando **el envío del panel con los
  métodos de la ruta**: ese desajuste (POST en el navegador, GET en el servidor) era el defecto completo.

