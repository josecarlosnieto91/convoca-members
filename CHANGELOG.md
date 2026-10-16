## [2.8.95] — 2026-10-16

### Añadido
- **El socio puede descargar el recibo de su cuota desde su panel (gateway#8).** En «Pagos y Cuotas» se veía
  fecha, concepto, importe y estado, y **ningún recibo**: ni botón, ni número, ni enlace. Cuando el correo se
  perdía —o cuando el socio necesitaba el justificante para su empresa, una subvención o una ayuda— había que
  pedírselo a la junta por WhatsApp y alguien tenía que buscarlo: un trámite de dos minutos que costaba dos
  días. Ahora la tabla lleva una columna **«Recibo»** con el número (el que el gateway ya guardaba) y un
  enlace para verlo o imprimirlo, que se abre aparte para no sacar al socio de su panel.
- El enlace lo construye **el gateway** (es quien firma la clave y comprueba que el recibo es de quien lo
  pide) y solo se ofrece en **pagos cobrados**: en uno pendiente el recibo todavía no existe y el enlace
  responde 404. Sin el gateway instalado, el panel no ofrece enlace y no se rompe nada.
- El enlace directo desde el correo del recibo **ya existía** (`{recibo_url}`): lo que faltaba era el panel.
- Cuatro pruebas nuevas (`ReciboEnElPanelTest`).

