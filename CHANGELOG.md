## [2.8.84] — 2026-10-16

### Añadido
- **El consentimiento se puede ver, filtrar y registrar después del alta (members#41).** Se guardaba bien
  —fecha, versión, texto e IP— pero **solo había una puerta de entrada**: `log_consent()` se llamaba
  únicamente desde el formulario de alta. Medido en la demo: de 13 fichas, solo 6 tenían constancia, y no
  había ni forma de saber quién faltaba ni de apuntar el consentimiento de quien lo dio en papel o por
  correo. El RGPD pide poder **demostrarlo**, así que esto no es un adorno:
  - Una columna **«Consentimiento»** en el listado de socios, con la fecha y la versión del texto aceptado;
    si no hay constancia, dice «Sin registrar» en rojo.
  - Un **filtro** «Con consentimiento / Sin consentimiento» y un **contador** arriba («N socios activos sin
    consentimiento registrado») con enlace directo a los que faltan.
  - Un **botón en cada fila** para registrarlo a mano (con nonce, comprobación de permiso y una nota que
    deja dicho que se registró desde el listado), y el aviso de que se ha guardado.
  - Un **aviso en el escritorio** mientras haya socios activos sin constancia, que lleva al listado ya
    filtrado.
- `GDPR_Tools` gana dos piezas reutilizables: `contar_sin_consentimiento()` y `etiqueta_consentimiento()`,
  para que el listado y el escritorio cuenten y pinten lo mismo.
- Cinco pruebas nuevas (`ConsentimientoListadoTest`).

