## [2.8.91] — 2026-10-16

### Arreglado
- **El paso 2 del alta se quedaba parado sin decir qué faltaba cuando quien se apunta es menor de edad
  (members#43).** Medido en la demo con el asistente real: con el bloque del tutor visible y vacío, al pulsar
  «Siguiente» **no se marcaba ningún campo**, no había ningún aviso junto al tutor y el foco no se movía
  (`camposMarcados: []`, `mensajesVisibles: []`). El asistente se quedaba parado sin señalar nada.
  **Causa**: los campos del tutor no eran `required` ni tenían aviso propio, así que la validación los
  saltaba y solo ponía el paso en «no válido».
  Ahora cada fallo —tutor incluido— **se marca en su campo** (`has-error`, con su aviso visible),
  **se anuncia** (`aria-invalid`) y **el foco va al primero** que falta. Los campos del tutor **no** se
  marcan `required` a propósito: solo hacen falta si es menor, y marcarlos obligatorios bloquearía el alta
  de cualquier adulto.
- El aviso general pasa a decir «Revisa los campos marcados: falta algún dato obligatorio.», que apunta a lo
  que hay que mirar en vez de a «rellena todos».
- **El caso adulto (un campo vacío) ya funcionaba** y se ha dejado como estaba: los 7 campos obligatorios se
  marcaban bien, con su mensaje y el aviso general. El informe del issue no reproducía en ese camino; la
  medición está en el issue con lo que sí y lo que no.
- Tres pruebas nuevas (`ValidacionPaso2Test`). El JS del formulario no tiene arnés en este repo —se mide en
  el navegador—, así que la prueba vigila el contrato de los dos ficheros.

