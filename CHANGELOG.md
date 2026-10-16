## [2.8.88] — 2026-10-16

### Arreglado
- **El panel enseñaba el botón de renovar sin decir hasta cuándo estaba pagada la cuota (members#38).** Es el
  escenario con el que tropieza cualquier asociación: un socio al día renueva en octubre creyendo que le
  toca y **se le cobra otra vez un año ya cubierto**. Medido con la cuota pagada hasta 14/10/2027: la
  pantalla donde está el botón no decía nada de esa fecha (estaba en «Mis Datos», no ahí). Ahora:
  - **«Estás al día hasta el DD/MM/AAAA»** delante del botón, y si renueva se le dice que **se amplía un año
    más** (con la fecha que quedaría): no se cobra dos veces el mismo periodo. El servidor ya lo hacía así
    —suma un año al vencimiento anterior, nunca desde hoy—; ahora el socio lo ve antes de pagar.
  - Si la cuota **está vencida**, se dice y con el botón de siempre.
  - **Si ya hay un pago pendiente**, el panel enseña ESE (con enlace para terminarlo) en lugar de generar
    otro: dos enlaces abiertos para el mismo periodo es la otra forma de cobrar dos veces sin querer.
  - El botón avisa con una confirmación antes de crear el pago cuando la cuota está al día.
- `estado_cuota_panel()` es ahora una sola pieza para los dos endpoints («Mis Datos» y «Pagos y Cuotas»): la
  fecha y el estado no pueden divergir entre pantallas.
- De paso: los estados `pending` y `refunded` que guarda el gateway salían en el panel **sin color ni
  traducción** (el mapa de etiquetas no los tenía).
- Cinco pruebas nuevas (`EstadoCuotaPanelTest`).

