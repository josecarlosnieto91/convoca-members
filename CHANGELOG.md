## [2.8.96] — 2026-10-16

### Arreglado
- **Los intentos repetidos ya no cierran la puerta a toda la sede, y la junta puede levantar el bloqueo
  (members#46).** El contador era solo por IP (ver el arreglo del núcleo, 2.3.37): ahora **el alta y el
  voluntariado cuentan por el correo de quien lo intenta**, y **el acceso por usuario + conexión** —así las
  erratas de una persona no dejan fuera a sus compañeros de línea, y nadie puede dejar fuera la cuenta de otro
  probando desde su propia conexión.
- **El aviso dice qué hacer**: «Se han hecho varios intentos de registro desde tu conexión. Espera unos
  minutos y vuelve a probarlo. Si no eras tú, escríbenos a <correo de contacto>». El mensaje viejo
  («Demasiados intentos de registro. Inténtalo de nuevo en una hora.») dejaba a la persona sin saber si era
  culpa suya ni adónde ir.
- **Pestaña «🔒 Intentos bloqueados»** en los ajustes, con el número delante cuando hay algo que mirar: quién
  está cortado (correo o conexión), en qué formulario, cuántos intentos y hasta cuándo, con un botón
  **«Desbloquear»** (con nonce y permiso) que lo levanta sin tocar la base de datos. Antes había que editar
  `convoca_rl_alta_socio_<hash>` a mano.
- Seis pruebas nuevas (`BloqueoPorPersonaTest`). El limitador se mide **en vivo** (su contador vive en
  `wp_options` con SQL directo y el arnés unitario no tiene base de datos): está en el issue.

