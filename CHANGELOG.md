## [2.8.87] — 2026-10-16

### Arreglado
- **El aviso de «consentimiento registrado» podía no salir (members#41).** Tras guardar, la vuelta a la
  pantalla usaba `edit.php?post_type=miembro` como destino de respaldo, y esa URL **redirige** a la pantalla
  propia del plugin por el camino del andamiaje, así que el aviso se perdía. Ahora el respaldo apunta
  directamente a `admin.php?page=conv-members`.

