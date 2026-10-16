## [2.8.92] — 2026-10-16

### Arreglado
- **La importación avisaba en verde aunque hubiera errores, y no traía los apellidos (members#44).** Medido en
  la demo con un CSV de tres filas mezcladas (una buena, una con un DNI que ya existe y una sin email):
  el resumen salía en un cartel **verde de éxito** con el texto «1 socios importados correctamente. 2
  errores: …». Para quien no es técnico, verde es «todo bien», así que se va sin leer los errores — que es
  justo donde está la fila que no entró. Ahora:
  - **Verde** solo si no hubo ni un error; **aviso (⚠️)** si hubo filas fallidas; **rojo (❌)** si no se
    importó ninguna. El color va con un icono y `role="alert"`.
  - **«1 socio importado correctamente»** (singular y plural de verdad) y «1 fila con errores» en vez de
    «1 errores».
  Lo que ya estaba bien se ha dejado como estaba: importa las filas buenas, sigue con las malas y dice
  **qué fila** falla y **por qué**.
- **Los apellidos se pueden importar.** El mapeo ofrecía siete campos y **no había campo de apellidos**,
  aunque la ficha los tiene (`_convoca_apellidos`) y el carnet y los documentos los usan: un censo con la
  columna «apellidos» no tenía dónde meterlos. Ahora están en el mapeo (y se autoasignan por nombre de
  columna), en las columnas esperadas, en lo que se guarda y en la ayuda de la pantalla.
- Tres pruebas nuevas (`ImportacionAvisoYApellidosTest`).

