## [2.8.103] — 2026-10-16

### Añadido
- **«Mis turnos» en el panel del socio (shifts#19).** El panel tenía siete secciones y ninguna de turnos, así
  que quien quería apuntarse tenía que encontrar la página del cuadrante por su cuenta, y la suscripción a su
  calendario `.ics` solo vivía en una caja de una página suelta (ver shifts#16).
  La pestaña nueva trae **sus próximos turnos**, el **cuadrante** del centro para ver los huecos libres y la
  **caja de suscripción a su calendario**, y todo ello **delegando en los shortcodes del plugin de turnos**
  (`convoca_proximos_turnos`, `convoca_calendario`, `convoca_suscripcion_turnos`): es él quien sabe de turnos y
  copiar su lógica aquí sería tener dos versiones de la verdad. Sin el plugin de turnos, la pestaña no se pinta
  y el endpoint contesta que los turnos no están disponibles, en vez de romperse.
- Cuatro pruebas nuevas (`SeccionMisTurnosTest`), una de ellas comprobando de verdad el caso «sin plugin de
  turnos» contra el endpoint.

