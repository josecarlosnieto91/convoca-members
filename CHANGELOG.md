## [2.9.0] — 2026-10-16

### Añadido
- **El circuito del voluntariado**, con el tablero que dice en qué paso está cada persona: solicitud,
  entrevista inicial, voluntariado en activo y desvinculación (`docs/spec-circuito-voluntariado.md`, decidido
  con JC el 16/10/2026). Antes el sistema sabía si alguien estaba aprobado o no, pero no en qué punto del
  camino estaba: eso se llevaba en la cabeza de quien coordina, y se perdía cuando esa persona cambiaba.
  - **La entrevista se cita con fecha y hora**, y se resuelve de dos maneras: con el **cuestionario** que
    contesta la persona (las preguntas las pone cada organización en los ajustes, una por línea) o marcándola
    como hecha desde la administración. A partir de ahí la persona **es** voluntaria.
  - **«Voluntario y/o monitor, pero nunca monitor solo»**: la condición de monitor exige la de voluntario, y
    la regla vive en `Roles_Miembro::anadir()` —donde nace la condición— y se refuerza al escribir los roles
    de WordPress. Quitar el voluntariado quita también el monitorado.
  - **El acuerdo lo acepta la persona**, y queda con su fecha y su huella; el documento se genera firmado con
    el mismo camino que los demás documentos de Convoca.
  - **La desvinculación** guarda su causa —lo pide el voluntario, caduca su membresía, o fuerza mayor de la
    junta— y su fecha. Sus **horas se quedan como están**, su certificado se sigue descargando y validando, y
    lo aprobado hasta esa fecha sigue contando. La baja que pide la propia persona no la da de baja: la
    confirma la junta.
  - **Cada paso queda en el historial**, con quién lo movió: para poder responder «¿por qué esta persona es
    voluntaria hoy y ayer no?».
- **Avisos por correo de cada paso**: la cita a la persona, la baja pedida a quien la confirma y la
  desvinculación a la persona. Al correo del voluntariado y, si no lo hay, al de administración.
- Nuevo ajuste **«Preguntas de la entrevista inicial»**.

### Notas
- Doce pruebas nuevas (`CircuitoVoluntariadoTest`), y actualizada la de condiciones que ahora chocaba con la
  regla del monitor: su caso pasa a socio + voluntario, y se añade la prueba de que el monitor no se concede
  solo. Total: 390 pruebas.

