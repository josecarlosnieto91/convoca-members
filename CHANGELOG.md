## [2.9.3] — 2026-10-16

### Arreglado
- **Las fechas de los avisos salen en el formato del sitio.** Medido en la demo: el correo de la cita decía
  «2026-11-05» —la fecha de la ficha, en ISO, que es como se guarda— y eso no es lo que lee una persona. Ahora
  va en el formato configurado en el sitio («05/11/2026»). Y la conversión se hace a mediodía, no a medianoche:
  una fecha a las 00:00 se va al día anterior en cuanto el huso del sitio va por detrás (medido: salía «04/11»
  con una fecha del 5).

