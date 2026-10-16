## [2.8.93] — 2026-10-16

### Añadido
- **«Mis datos y privacidad»: el socio puede ver, descargar y pedir la supresión de sus datos desde su panel
  (members#42).** El panel tenía siete secciones y ninguna era de privacidad: para ejercer sus derechos había
  que escribir un correo y esperar a que alguien de la junta supiera dónde estaba la herramienta del
  escritorio. Y quien pedía la baja creía que con eso desaparecían sus datos, y no: la baja es una etiqueta.
  - **Qué guardamos y por qué**, en castellano llano, con la versión de la política aceptada y su fecha (o el
    aviso de que no hay constancia, para que la junta pueda registrarlo — eso llegó en 2.8.84).
  - **Descargar mis datos** en un fichero, con el **mismo exportador** que usa Herramientas → Exportar datos
    personales: así recibe exactamente lo que la asociación guarda, sin una segunda lista de campos que se
    quede atrás.
  - **Pedir la supresión** de los datos personales. **No borra nada al momento**: deja la solicitud anotada
    en la ficha con su fecha, **avisa a la junta por correo** (con enlace a la ficha y a la herramienta) y la
    resuelve una persona con el borrador que ya existe. Cuando el borrador la atiende, queda marcada como
    resuelta, así que el aviso no se queda encendido para siempre.
  - La **baja** dice ahora la diferencia en el propio aviso de confirmación: «la baja CONSERVA tu historial;
    si además quieres que borremos tus datos personales, pídelo en Mis datos y privacidad».
  - **La junta la ve**: aviso en el escritorio («N socios han pedido la supresión…») que lleva al listado ya
    filtrado, marca «🗑️ Pide supresión» junto al estado de la ficha y filtro «Con supresión pedida».
- `GDPR_Tools` gana las piezas reutilizables: `solicitar_supresion()`, `estado_privacidad()`,
  `contar_supresiones_pendientes()`, `datos_para_descargar()`, `marcar_supresion_resuelta()` y
  `cuerpo_aviso_supresion()`.
- Siete pruebas nuevas (`PrivacidadPanelTest`), una de ellas sobre el texto del correo: cazó un fallo mío al
  escribir `%1$s` dentro de comillas dobles (PHP se comía el nombre del socio y los saltos de línea).

