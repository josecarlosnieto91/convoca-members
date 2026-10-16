## [2.8.97] — 2026-10-16

### Arreglado
- **El botón «Desbloquear» terminaba en un 403.** La redirección apuntaba a `page=convoca-members-settings` y
  el slug real de la pantalla es `conv-members-settings`: el bloqueo se levantaba (medido: el contador quedaba
  a cero) pero acto seguido WordPress respondía «No tienes permiso para acceder a esta página». Cazado midiendo
  el botón en Lugg y en la demo, no leyendo el código. La prueba nueva compara los dos slugs.

