## [2.8.90] — 2026-10-16

### Arreglado
- **La cuota no llegaba al panel: la puse en el endpoint equivocado.** En 2.8.88 metí el estado de la cuota
  en el primer `return … 'items' => $items` que encontré y ese era el de **inscripciones**, no el de
  **pagos**: el panel seguía sin recibir nada. Medido en la demo con una sesión de socio real: la respuesta
  de `/me/pagos` traía los pagos y **ningún** `cuota`. Ahora va donde tiene que ir —`get_payments`— y la
  prueba comprueba **la posición**, no solo que la cadena exista: dentro de ese método y antes del
  siguiente.

