## [2.8.83] — 2026-10-16

### Arreglado
- **El borrado de datos personales dejaba datos fuera: los del usuario de WordPress y la foto del DNI
  (members#40).** El borrador anonimizaba la ficha —bien, con los pagos retenidos por obligación fiscal—,
  pero **el alta deja otra copia de los datos en el usuario** y el borrador no la tocaba. Medido con el
  borrador real sobre un socio de prueba: la ficha quedaba con `[Eliminado]` y el usuario **conservaba**
  `_convoca_dni`, `_convoca_telefono` y `_convoca_voluntario_direccion`, con la cuenta todavía dentro y con
  su rol. Y no era cosmético: **el certificado de voluntariado lee el DNI del usuario**
  (`PDF_Document.php`), así que el documento seguía saliendo con el DNI de quien había pedido ser olvidado.
  Ahora se borran los siete campos que el alta (o las direcciones de voluntariado) copian al usuario, se
  retiran **solo** los roles de este módulo —socio y voluntario aprobado, no otros que la cuenta pudiera
  tener— y la marca de voluntariado queda revocada. La cuenta no se borra: eso es una decisión de producto
  y se deja dicho en el issue.
- **La foto del documento de identidad se borra de verdad.** `_convoca_foto_id` no estaba en la lista: el
  adjunto se quedaba en la biblioteca de medios y el carné (`PDF_Card.php`) lo seguía insertando.
- Cinco pruebas nuevas (`BorradoRGPDUsuarioTest`), comprobadas sensibles: sin el arreglo caen cuatro con
  error y una con fallo. De paso se completaron dos huecos del arnés: al doble de `WP_User` le faltaban
  `add_role`/`remove_role`/`set_role` —que la clase real sí tiene— y faltaba `wp_delete_attachment()`.

