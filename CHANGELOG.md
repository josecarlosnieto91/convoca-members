## [2.8.99] — 2026-10-16

### Añadido
- **El socio ve su renovación por horas y puede usarla sin pagar (members#45).** La regla estaba bien
  implementada —el cron renueva el ciclo sin pago cuando el socio llega a las horas de su plan— pero vivía
  **dentro** de `check_volunteer_cycle()`, así que el panel no podía contar nada sin repetirla. Medido en la
  demo con la ficha 2215 (20 h aprobadas, plan Bronce de 15 h): su próxima renovación **ya le salía gratis** y
  el panel solo le ofrecía pagar 30 €.
  Ahora `CPT_Miembro::ciclo_voluntariado()` es la **única fuente** del ciclo (la usan el cron y el panel) y
  `renovar_por_horas()` el único camino que renueva (cron y botón del socio).
  En «Mis Pagos»: «Llevas 20 h de las 15 h de tu plan: tu próxima renovación es sin pagar», con barra de
  progreso, la fecha que quedaría cubierta y un botón **«🌱 Renovar ahora sin pago»** — no hace falta esperar al
  vencimiento ni pasar por caja—. Quien no llega ve cuántas horas le faltan. El servidor vuelve a contar las
  horas antes de renovar: el botón no da nada que no esté ganado.
- Columna **«Renueva por horas»** en el listado de socios, para que la junta sepa a quién no hay que cobrar.
  Sin filtro a propósito: las horas se calculan (plan + horas aprobadas del ciclo), no son una meta consultable
  en SQL y un filtro obligaría a recalcular ficha a ficha en cada página.

### Notas
- Cinco pruebas nuevas (`RenovacionPorHorasTest`). El comportamiento se mide en vivo (necesita registros de
  horas y fechas): está en el issue.
- Los correos de aviso de la propuesta 3 (avisar al socio cuando cumple horas, y recordar un mes antes del
  vencimiento si le faltan) **no se implementan**: notificar por correo a todos los socios es una decisión de
  producto, no técnica.

