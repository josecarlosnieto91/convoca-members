## [2.8.98] — 2026-10-16

### Arreglado
- **El censo de la importación ya no pasa por el `/tmp` compartido ni lo abre una ruta que mande el navegador
  (members#48).** Durante los dos pasos de la importación, el CSV con nombres, apellidos, DNI, correo, teléfono
  y dirección se copiaba a `/tmp` (modo 1777, visible para cualquier otro usuario de la máquina) y el segundo
  paso abría `sys_get_temp_dir() . '/' . $_POST['filename']`: con un `filename` a mano, la importación leía
  **cualquier** fichero de ese directorio.
  Ahora vive en un subdirectorio propio del sitio, **creado ya en 0700** (nadie de fuera puede ni entrar) y
  fuera de la raíz web, el fichero se llama con una **ficha aleatoria** y se crea en 0600, y el segundo paso
  manda la **ficha**: la ruta la resuelve el servidor y `ruta_por_ficha()` rechaza lo que se salga de la carpeta
  aunque la ficha sea válida. El XLSX temporal también sale del `/tmp` compartido.
  Tres pruebas nuevas (`ImportacionSinRutaDelClienteTest`), con control real: una ficha válida que apunte a
  `/etc/passwd` se rechaza, y un fichero de la carpeta con su ficha se resuelve.

