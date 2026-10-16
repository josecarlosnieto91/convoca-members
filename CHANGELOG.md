## [2.8.104] — 2026-10-16

### Arreglado
- **Una ficha con la cuota pagada y al día ya no puede quedarse sin la condición de socio (members#39).** Era el
  descuadre que medía el informe: un pago cobrado, carnet emitido y cuota al día, y la ficha diciendo
  «Voluntario» mientras el panel mostraba la cuota pagada. Con eso, el recuento de socios para una asamblea o
  una subvención no servía, y la junta no tenía forma de enterarse.
  Ahora: la ficha se **reconcilia** con sus datos —cuota pagada y renovación en vigor ⇒ condición de socio—, el
  listado **avisa** con un ⚠ en la columna «Tipo» cuando encuentra un descuadre, y hay un botón
  **«Reconciliar»** en la fila para arreglarlo sin entrar a la ficha. Lo que cambia queda en el registro.

### Añadido
- **Aviso por correo cuando un socio apunta horas de voluntariado.** Antes no avisaba nadie: la solicitud
  esperaba a que alguien se acordara de mirar la pantalla. Ahora sale un correo con el nombre, las horas, la
  fecha y el enlace a donde se aprueban, al **correo del voluntariado** que se configure en los ajustes y, si
  está vacío, al de administración (donde ya llegan los demás avisos). Nunca a una dirección inventada: sin
  ninguna configurada, no se manda nada.
- Nuevo ajuste **«Correo del voluntariado»**.

### Notas
- Ocho pruebas nuevas (`ReconciliacionYHorasTest`): la cuota al día cuenta y la caducada no, el descuadre se
  arregla y reconciliar lo que ya está bien no toca nada, el aviso de horas va al correo configurado, cae al de
  administración si no lo hay, y sin direcciones no se intenta nada. Los correos se comprueban con el doble del
  arnés: no sale ningún correo de verdad.

