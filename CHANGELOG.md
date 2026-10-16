## [2.8.101] — 2026-10-16

### Arreglado
- **El voluntario ya puede apuntar el trabajo que no es de ningún proyecto (members#37).** El formulario y su
  ruta ya existían y funcionaban; lo que impedía anotar era que `Hours_Manager::validate_hours_data()`
  **exigía un proyecto** («Debes seleccionar un proyecto») y el selector del formulario iba `required`. El
  trabajo voluntario que sale en el issue —repartir carteles, llevar la contabilidad, arreglar el local— no
  pertenece a ningún proyecto, así que esas horas no se podían apuntar: el certificado y los niveles salían
  cortos por muy voluntario que fuera alguien.
  Ahora el proyecto es **opcional para el socio** (su selector dice «Proyecto (opcional)») y **sigue siendo
  obligatorio para la junta**, cuyo libro tiene que cuadrar por proyectos. Si se elige uno, se comprueba que
  sea un proyecto de verdad.
- El formulario avisa: «Queda pendiente de que la junta lo apruebe: hasta entonces no cuenta en tu total ni en
  tu nivel».

### Corrección de mi diagnóstico
- Un commit anterior (`ab6dce7`) decía que la ruta «solo aceptaba GET». **Era falso**: el `POST` ya estaba, con
  `submit_hours()`. Añadí un `POST` duplicado con otro manejador antes de medirlo, y al medir lo **retiré**:
  un duplicado sombrea al primero y confunde. Por eso esta versión no añade ninguna ruta.
- Cuatro pruebas nuevas (`HorasDelVoluntarioTest`): que el panel y la ruta se entiendan, que el manejador de la
  ruta **exista** (una ruta apuntando a un método inexistente es un 500 esperando), que haya **un solo** POST,
  y que el proyecto sea opcional para el socio y obligatorio para la junta.

