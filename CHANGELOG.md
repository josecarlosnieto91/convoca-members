## [2.8.86] — 2026-10-16

### Arreglado
- **El botón de registrar el consentimiento se veía pero no guardaba nada (members#41).** El gancho lo
  registraba el constructor de la clase del listado, y esa clase **solo se instancia al pintar su
  pantalla**: en `admin-post.php` —la petición que dispara el botón— no llegaba a existir, así que la
  petición se perdía sin error. Medido en la demo: el clic no cambiaba nada y no salía ningún aviso. Ahora
  el manejador es **estático** y se registra en el arranque del plugin, junto al resto de la carga del
  panel.

