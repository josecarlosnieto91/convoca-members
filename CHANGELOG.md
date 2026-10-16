## [2.8.85] — 2026-10-16

### Arreglado
- **El botón de registrar el consentimiento no aparecía (members#41).** Lo había puesto en
  `post_row_actions`, y medido en la demo **no se pinta**: el listado de socios es la pantalla propia del
  plugin, no la tabla de `edit.php`, así que ese gancho no llega. Ahora el enlace va **dentro de la propia
  columna** «Consentimiento» —que es donde se ve— y se retiró el gancho que no hacía nada.

