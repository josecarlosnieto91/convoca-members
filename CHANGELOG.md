## [2.9.2] — 2026-10-16

### Añadido
- **Los avisos del voluntariado se pueden editar**, como los demás correos del plugin. Cinco plantillas nuevas
  en el editor de siempre (Miembros → Correos), con sus variables:
  - **Cita para la entrevista** — a la persona, con el día y la hora (`{fecha_cita}`, `{hora_cita}`).
  - **Acuerdo para firmar** — a la persona, con el enlace a su área.
  - **Baja pedida** — a quien la confirma, con lo que haya contado la persona (`{motivo}`).
  - **Desvinculación aplicada** — a la persona, con su causa (`{causa_baja}`) y sus horas (`{horas}`).
  - **Horas pendientes de aprobar** — a quien aprueba (`{horas}`, `{fecha_horas}`, `{enlace_horas}`).
- Los textos de fábrica vienen escritos y son los que ya se mandaban; el sitio puede cambiarlos sin tocar
  código. A quién se avisa sigue siendo configuración: el correo del voluntariado y, si no lo hay, el de
  administración.

### Cambiado
- Los avisos del circuito y el de horas pendientes dejan de llevar su texto escrito en el código: ahora salen
  por las plantillas. Es lo que permite editarlos.

### Notas
- Los sitios que ya están en marcha reciben las cinco plantillas con la migración de plantillas (repone las de
  fábrica que falten y no toca las que el sitio tenga escritas).
- Pruebas: las del arnés no pueden ejercer el envío completo —no tiene el aparato de correo de WordPress—, así
  que comprueban lo que sí se puede medir (plantillas declaradas, texto editable, variables sustituidas y que
  el aviso sale con su dirección); el correo tal cual llega se mide en la demo con el envío interceptado. Total:
  396 pruebas.

