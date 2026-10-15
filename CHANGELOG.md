## [2.8.72] — 2026-10-15

### Cambiado
- **Los tres nombres de pago dejan de confundirse (issue convoca-core#16).** Tres cosas distintas se llamaban
  `get_payment_link()`: en core **crea** el pago, en la pasarela **construye la URL** de uno que ya existe, y la
  privada de `Cron_Manager` **asegura el token persistente** y delega. Ya no: la de `Cron_Manager` se llama
  `signed_payment_url()` —el token firmado del pago concreto, distinguida de `payment_page_url()`, que es la
  página de pago a secas— y las llamadas a la pasarela usan `build_payment_url()`. Sin cambios de comportamiento.

## [2.8.71] — 2026-10-15

### Añadido
- **El catálogo inglés queda completo, 854 de 854 (issue #10, la parte del idioma).** Estaba en 786: faltaban
  **66 cadenas**, y son justo las que salían en castellano dentro del área de socio en inglés. Medido en vivo en
  `/en/mi-cuenta-2/`, donde el aviso del JavaScript y los mensajes del formulario salían en español mientras el
  resto de la página estaba traducido.
  - El `.po` inglés era del 23/09 y el código había cambiado desde entonces: `wp i18n update-po` con el `.pot`
    actual destapa el hueco (las cadenas nuevas no existen para nadie hasta que se traducen).
  - Traducidas a mano, una por una, siguiendo la terminología que ya usaba el catálogo (*member*, *dues*,
    *signup*, *volunteer hours*). No hay clave de DeepL configurada en el taller, así que no se usó.
  - `.mo` recompilado: 854 cadenas.

## [2.8.70] — 2026-10-15

### Arreglado
- **PCP marcaba como no preparada la consulta del deduplicador del alta (issue #33).** El `WHERE` se armaba por
  concatenación y el resultado se le pasaba a `prepare()` por una variable. La consulta **era segura** (los
  literales eran nuestros y el valor viajaba preparado), pero los analizadores de WordPress.org no pueden
  comprobar una cadena armada a trozos y marcaban la consulta entera
  (`WordPress.DB.PreparedSQL.NotPrepared` y `PluginCheck.Security.DirectDB.UnescapedDBParameter`), así que el CI
  de `main` salía en rojo por código correcto.
  - Ahora hay **una consulta literal por dato**, sin concatenación. Cuando el DNI y el correo apuntan a fichas
    distintas decide el **DNI**: antes decidía el orden que quisiera MySQL, porque el `LIMIT 1` no llevaba
    `ORDER BY`.
- El **`Stable tag`** del `readme.txt` vuelve a coincidir con la cabecera (venía de 2.8.48 con el plugin ya en
  2.8.69, y eso tenía el trabajo de PCP en rojo).

### Pruebas
- `SqlPreparadoConMotivoTest`: el SQL que va a `prepare()` o es un literal, o va anotado con su motivo **en la
  misma línea**. **Negativo probado**: con el fichero anterior la prueba falla señalando
  `includes/Process_Member.php:325`.
- Verificado con **Plugin Check 2.1.0** en local sobre el paquete de distribución: 0 errores en
  `plugin_repo` + `security`.

## [2.8.69] — 2026-10-15

### Arreglado
- **Las URLs crudas apagadas devolvían un 500 (issue #15, medido al verificar el cambio anterior).** Apagar
  `show_ui` en `miembro`, `proyecto` y `registro_hora` quitó las pantallas crudas, pero **la URL sigue
  existiendo** y WordPress la resuelve de la peor forma posible: `edit.php?post_type=miembro` respondía **500 sin
  mensaje**. Es el mismo comportamiento que ya estaba documentado con `edit-tags.php` en una taxonomía sin
  interfaz: un marcador guardado en el navegador se convierte en un error de servidor.
  - Un redirector en `admin_init` lleva `edit.php` y `post-new.php` de esos tres tipos a **su pantalla propia**
    (`conv-members`, `conv-proyectos`, `conv-horas`). `post.php?post=N` no se toca: `CPT_Miembro` ya lo redirige
    a su editor propio, que es mejor destino.

### Pruebas
- `PantallasCrudasApagadasTest` gana la comprobación del redirector: que esté, y que los tres tipos vayan a su
  pantalla. **Negativo probado**: quitándolo, cae una prueba.
- Suite 284 en verde, PHPCS 0, PHPStan `[OK]`.

## [2.8.68] — 2026-10-15

### Cambiado
- **Los tipos con pantalla propia ya no ofrecen además su pantalla cruda de WordPress (issue #15).** Con
  `show_ui => true`, WordPress crea `edit.php?post_type=X`, `post-new.php?post_type=X` y `post.php?post=N`; si el
  plugin registra además su propia página, quedan **dos pantallas para lo mismo** — y `show_in_menu => false` solo
  esconde una del menú: **la pantalla sigue viva y responde** por URL. Ahora `miembro`, `proyecto` y
  `registro_hora` llevan `show_ui => false`, que es el patrón que ya usaban `inscripcion`, `pago` y
  `convoca_documento`.
  - En socios, además, la lista cruda ofrecía «Mover a la papelera» en cada fila **y en lote** sin que nada la
    vaciara: el tipo no declara soporte de papelera y el sitio tiene `EMPTY_TRASH_DAYS`. Con la pantalla apagada,
    ese camino desaparece.
  - Las pantallas propias (`conv-members`, `conv-proyectos`, `conv-horas` y el editor de ficha) siguen donde
    estaban: el plugin ya redirigía `post.php` a su propio editor, así que no se pierde nada.

### Pruebas
- `tests/Unit/PantallasCrudasApagadasTest.php`: que la cruda esté apagada **y que la pantalla propia siga
  existiendo** (apagar la cruda sin tener la propia dejaría al plugin sin interfaz). **Negativos probados**:
  volviendo a encender una cruda, cae una prueba; quitando la pantalla propia, caen dos.
  - Nota: la primera versión de la prueba exigía que no quedara **ningún** `show_ui => true` en el fichero, y se
    tropezaba con la **taxonomía** de `miembro`, donde ese `true` es legítimo (sin él no hay pantalla de
    términos). El test estaba mal, no el código.
- Suite 283 en verde, PHPCS 0, PHPStan `[OK]`.

## [2.8.67] — 2026-10-15

### Arreglado
- **Los formularios saltaban de nivel de encabezado (issue #13, axe moderada).** En el alta de socio, los planes
  y las formas de pago iban en `<h4>` dentro de un `<h2>`, así que se saltaba el `<h3>`; en el formulario de
  voluntariado se empezaba en `<h3>` sin `<h2>` antes. No rompe el uso, pero desorienta a quien navega con lector
  de pantalla — que es justo el motivo de que existan los niveles. Los subniveles pasan a `<h3>` (7 en el alta) y
  las secciones del voluntariado a `<h2>` (5), de forma que los niveles suben **de uno en uno**.

### Pruebas
- `tests/Unit/EncabezadosSinSaltosTest.php`: recorre los encabezados de las plantillas **en orden** y comprueba
  que las subidas son de uno en uno (bajar no es un salto) y que no queda ningún `h4` o más profundo donde la
  jerarquía tiene dos niveles. **Negativo probado**: devolviendo un solo `<h4>`, caen dos pruebas.
- Suite 281 en verde, PHPCS 0, PHPStan `[OK]`.

## [2.8.66] — 2026-10-15

### Arreglado
- **Un solo limitador para el acceso (issue #14).** Había **dos**, con la misma clave `login` y umbrales
  distintos (10 intentos / 300 s en el endpoint, 5 / 900 s en la autenticación), y los dos se llamaban en la
  misma petición: un intento contaba doble, el umbral real no era ninguno de los dos, y el mismo endpoint
  respondía alternando dos esperas distintas («15 minutos» y «5 minutos»). El límite vive ahora **solo** en
  `Member_Auth`; el endpoint no lleva el suyo, y queda escrito allí dónde está para que nadie lo reponga.

### Pruebas
- `tests/Unit/UnSoloLimitadorDeAccesoTest.php`: que haya **un solo** limitador para el acceso, que el mensaje y el
  bloqueo digan lo mismo, y que el endpoint no vuelva a poner el suyo.
  - Nota de método: la primera versión de la prueba **daba un falso positivo**. El bloqueo se escribe
    `15 * MINUTE_IN_SECONDS` y mi expresión regular leía el `15` suelto, así que se quejaba de que 15 no eran 900.
    Lo cazó la suite antes de llegar al commit, y de paso impidió que este CHANGELOG afirmara un cambio que no se
    había hecho. **Negativos probados**: devolviendo el limitador al endpoint caen dos pruebas.

## [2.8.66] — 2026-10-15

### Arreglado
- **Un solo limitador para el acceso, y su mensaje dice la verdad (issue #14).** Había **dos**, con la misma
  clave `login` y umbrales distintos (10 intentos / 300 s en el endpoint, 5 / 900 s en la autenticación), y los
  dos se llamaban en la misma petición: un intento contaba doble, el umbral real no era ninguno de los dos, y el
  mismo endpoint respondía alternando dos esperas distintas («15 minutos» y «5 minutos»). El límite vive ahora
  **solo** en `Member_Auth`; el endpoint no lleva el suyo.
- **Y el bloqueo estaba en 15 SEGUNDOS.** La constante `LOGIN_LOCKOUT_SECONDS` valía `15` mientras el mensaje
  anunciaba «15 minutos»: ni el mensaje era verdad, ni la protección contra fuerza bruta servía de mucho (cinco
  intentos cada quince segundos es, en la práctica, ninguno). Ahora son 900 s, que es lo que dice el mensaje.

### Pruebas
- `tests/Unit/UnSoloLimitadorDeAccesoTest.php`: que haya **un solo** limitador para el acceso, que el mensaje y el
  bloqueo digan lo mismo, y que el endpoint no vuelva a poner el suyo. **Negativos probados**: devolviendo el
  limitador al endpoint caen dos pruebas; dejando el bloqueo en 15 segundos, cae una.
- Suite 277 en verde, PHPCS 0, PHPStan `[OK]`.

## [2.8.65] — 2026-10-15

### Arreglado
- **La desinstalación no borraba todas las opciones del plugin (issue #10).** Faltaban
  `convoca_volunteer_fields` y `convoca_volunteer_legal_text` (los campos y el texto legal del formulario de
  voluntariado, que se guardan como opciones). Las ha encontrado la **prueba de contrato** que lee las claves del
  propio código en vez de fiarse de una lista escrita a mano.
  - Y esa prueba tenía un defecto propio que he corregido: contaba como opciones los **grupos** de
    `register_setting( 'grupo', 'opcion' )`, con lo que vigilaba lo que no debía y no veía lo que sí. Al
    arreglarla aparecieron estas dos.

### Pruebas
- `tests/Unit/OpcionesSeBorranAlDesinstalarTest.php`, hermano del de ganchos programados. **Negativo probado**:
  quitando una opción del desinstalador, la prueba cae y **la nombra**.
## [2.8.64] — 2026-10-15

### Arreglado
- **La concesión de capacidades no llegaba a ejecutarse (issue #15).** Medido en la demo: el administrador seguía
  sin las capacidades derivadas. El método estaba, la llamada estaba, y no se ejecutaba: el bootstrap declara
  **espacio de nombres**, así que una función suelta declarada allí no existe para quien la llame por su nombre
  sin cualificar — y `function_exists()` devuelve `false` **sin avisar**, que es la misma trampa silenciosa del
  `class_exists()` con barras mal escritas. La composición pasa a ser un **método de la clase** que la usa
  (`Roles_Miembro::capacidades_de_los_tipos()`), sin `function_exists` de por medio.

## [2.8.63] — 2026-10-15

### Arreglado
- **Las capacidades de los tipos propios se conceden en `init`, no en la activación (issue #15).** Medido
  verificando el arreglo anterior en la demo: con la concesión dentro del gancho de activación, el
  **administrador** se quedaba fuera de `edit.php?post_type=miembro` (403) — una instalación que se actualiza
  **no pasa por la activación**, que es el mismo motivo por el que los roles se crean en `init` desde #30. La
  prueba se ha reforzado para vigilar **dónde** está la concesión, no solo que exista: es lo que este defecto
  tenía de invisible. **Negativo probado**: quitándola de `crear_roles()`, cae.

## [2.8.62] — 2026-10-15

### Arreglado
- **Un editor podía listar y borrar el censo de socios (issue #15, datos personales).** Los tipos `miembro`,
  `registro_hora` y `proyecto` se registraban sin tipo de capacidad propio —o con el de `post`—, así que
  WordPress traducía sus capacidades a las primitivas de `post`, **que el rol `editor` tiene por defecto**. Con
  eso, un usuario con rol `editor`, sin ninguna capacidad de este plugin: abría `edit.php?post_type=miembro`
  (200), veía el listado con nombres y apellidos, y podía mandar fichas a la papelera —con sus horas y sus
  proyectos—. Las pantallas propias del plugin siempre comprobaron `gestionar_miembros`; las del tipo de
  contenido, que existen porque `show_ui` está en `true`, no comprobaban nada.
  - Cada uno de los tres tipos declara ahora **su propio par** (`convoca_miembro`/`convoca_miembros`,
    `convoca_hora`/`convoca_horas`, `convoca_proyecto`/`convoca_proyectos`) y las **trece** capacidades que
    WordPress deriva de cada par se conceden **solo a administración**, que es la decisión de producto: las
    pantallas propias siguen gobernadas por `gestionar_miembros` y no cambian para nadie.
  - Las derivadas **se componen** en el código a partir del par, no se escriben a mano: una sola que se quedara
    fuera sería una puerta abierta.

### Pruebas
- `tests/Unit/CapacidadesSociosTest.php`: que los tres tipos tengan par propio, que ninguno cuelgue ya de
  `post`, que las trece capacidades se compongan una por una, que el helper derive del **mismo** par que declara
  el tipo y que la concesión no incluya a `editor`. **Negativos probados**: devolviendo un tipo a `post` caen
  dos pruebas; borrando una sola capacidad derivada, cae una.
- Suite 274 en verde, PHPCS 0, PHPStan `[OK]`.

## [2.8.61] — 2026-10-15

### Añadido
- **Top de voluntarios (issue #31).** La web prometía «Tracking de Horas: informes, **top voluntarios** y
  exportación a CSV/PDF» y el top no existía (cero apariciones de `ranking`, `leaderboard` o `top_voluntario`).
  Ahora hay un ranking por horas aprobadas, con su shortcode `[convoca_top_voluntarios]` (`limite`, `desde`,
  `publico`, `titulo`).
  - Cuenta **las mismas horas que la renovación** (`Voluntariado_Manager::get_horas_aprobadas_desde()`), no el
    agregado heredado: si el ranking y la renovación usaran fuentes distintas, dirían cosas distintas del mismo
    voluntariado.
  - Ordena de más a menos horas y, en empate, **alfabético**: nadie sale antes por casualidad del orden en que
    la consulta devuelva las fichas. El límite tiene techo (100).
  - **Prudente por defecto**: el ranking solo se pinta para quien tiene sesión, porque son nombres y horas de
    personas. Con `publico="1"` se enseña a todo el mundo, que es una decisión de la asociación y no del
    plugin.

### Pruebas
- `tests/Unit/TopVoluntariosTest.php`: que la fuente de horas sea la de la renovación (y no el agregado), el
  orden y el desempate, el techo del límite, el shortcode enganchado y su defecto prudente —y, de verdad, que
  **sin sesión no sale ni un nombre**—. **Negativos probados**: invirtiendo el orden cae una prueba, y dejando
  el ranking abierto por defecto caen dos.
- Suite 271 en verde, PHPCS 0, PHPStan `[OK]`.

## [2.8.60] — 2026-10-15

### Añadido
- **Control de vigencia de los documentos (issue #31).** La web vendía «Documentos: … Control de vigencia» y el
  tipo `convoca_documento` no tenía ni noción de caducidad (lo que caduca son los certificados, que es otra
  cosa). Ahora cada documento puede llevar su **fecha de caducidad** y el sistema lo dice en cuatro estados:
  vigente, **caduca pronto** (dentro de 30 días, ajustable con un filtro), **caducado** —diciendo cuántos días
  hace— y sin fecha.
  - El control vive **en la ficha del socio**, que es donde la asociación mira los documentos: el tipo no tiene
    pantalla propia (`show_ui` está en falso y se generan por código), así que un campo escondido en un editor
    que no existe no habría servido de nada. Junto a cada documento salen su estado y un formulario para
    ponerle o cambiarle la fecha; solo con permiso de gestión del voluntariado: un socio no decide cuándo caduca
    su propio DNI.
  - La regla vive en un solo sitio (`CPT_Documento::estado_caducidad()`), para que la ficha, los informes y
    cualquier aviso digan lo mismo; y `caducado()` es el atajo para el sí o el no.
  - La meta va registrada (`register_post_meta`, visible en REST) y con saneado: lo que no sea una fecha no
    entra. El día de la caducidad **todavía vale**: caduca al terminar el día, no al empezar.

### Pruebas
- `tests/Unit/DocumentoVigenciaTest.php`: los cuatro estados, la fecha ilegible, el día de la caducidad, el
  atajo y el contrato del control (estado en la ficha, formulario, nonce, permiso, meta saneada). **Negativo
  probado**: retrasando el aviso de caducado cien días caen dos pruebas.
- Suite 266 en verde, PHPCS 0, PHPStan `[OK]`.

## [2.8.59] — 2026-10-15

### Añadido
- **La foto del carnet (issue #31).** La web vendía «carnets digitales **con foto**» y no había foto por ninguna
  parte: ni campo en la ficha ni nada que pintara un adjunto. Ahora la ficha tiene **«Foto para el carnet»** con
  el selector de la biblioteca de medios de WordPress (y su botón para quitarla), y el carnet la imprime.
  - Se inserta **en línea, como data URI**, igual que el QR: el PDF no puede ir a buscar una URL (Dompdf no
    tiene acceso remoto) y así el carnet sale entero de la base, sin depender de nada de fuera.
  - Se manda **reducida** a 300 px por lado para no llevar dentro la foto original de varios megas; si el editor
    de imágenes no está disponible, se manda la original, que es mejor que quedarse sin foto.
  - Solo imágenes (`jpeg`, `png`, `gif`, `webp`): un adjunto que no sea imagen no se imprime.
  - Los estilos van en línea a propósito: los bloques de CSS del carnet son distintos para el navegador y para
    el PDF, y un `float` en línea sale igual en los dos. Sin foto, no se pinta **nada** (ni un hueco).

### Pruebas
- `tests/Unit/CarnetConFotoTest.php`: que el carnet inserte la foto en el cuerpo, que se embeba y solo si es
  imagen, que la ficha tenga el campo con su selector y que se guarde; y que **sin foto no salga nada**.
  **Negativo probado**: quitando la inserción, la prueba cae.
- Suite 258 en verde, PHPCS 0, PHPStan `[OK]`.

## [2.8.58] — 2026-10-15

### Añadido
- **Conceder la condición de monitor desde la ficha (fase 4 de #30).** El monitor es la única condición que no
  sale de ningún plan ni de una inscripción —las otras dos se ganan con el plan y con la aprobación del
  voluntariado—, así que ahora tiene su vía en la interfaz: una casilla en el editor de la ficha que **suma**
  la condición (no reemplaza las demás) y espeja el rol de WordPress por `Roles_Miembro`. Solo puede
  concederla un administrador: un monitor no reparte su propio rol.
- La casilla viaja con una **marca de presencia**: un formulario que no la traiga (el editor rápido, una
  importación) **no** quita la condición sin querer. El texto de ayuda deja claro qué supone: aprobar horas,
  impartir actividades y sumar horas sin estar inscrito.

### Pruebas
- `tests/Unit/MonitorDesdeEditorTest.php`: la casilla y su marca, que el guardado pase por `Roles_Miembro`,
  que exija `manage_options` y que sin la marca no se toque nada. **Negativo probado**: quitando la marca, la
  prueba cae. Suite 254 en verde, PHPCS 0, PHPStan `[OK]`.

## [2.8.57] — 2026-10-15

### Añadido
- **El panel del socio dice qué es y qué le supone (fase 3 de #30).** En la cabecera, en servidor —es lo
  primero que ve al entrar y no depende de que cargue el JavaScript—: sus condiciones («Tu ficha: Socio ·
  Voluntario») y, si es socio, que **sus inscripciones van con tarifa de socio**. El descuento deja de ser
  algo que hay que creerse.
- **Un solo sitio para las etiquetas.** `Roles_Miembro::etiqueta()` es la única que traduce las condiciones, y
  el listado de socios delega en ella: así el listado y el panel no pueden decir cosas distintas de la misma
  ficha.

### Pruebas
- `RolSocioMonitorTest`: las tres etiquetas y que una condición desconocida se devuelve tal cual. Suite 250
  en verde, PHPCS 0, PHPStan `[OK]`.

## [2.8.56] — 2026-10-15

### Arreglado
- **Las fichas antiguas ya no pierden el rol de voluntariado al concederles otra condición.** Lo destapó la
  verificación en vivo de la aprobación de horas: al conceder la condición de socio, el espejo quitaba el rol
  `voluntario_aprobado` de quien **no tenía la marca** en la ficha. En una ficha aprobada antes de que
  existiera esa marca, el rol de WordPress es la única prueba que hay, y **Enroll y Shifts lo miran**: se
  habría dejado sin permisos a voluntariado que sí está aprobado. Ahora la ausencia de marca no toca nada, y
  quitar una condición escribe una marca explícita (`0`) en vez de borrar la meta, para poder distinguir
  «nunca se le puso» de «se le quitó».

### Pruebas
- Dos casos nuevos: una ficha antigua conserva su rol al concederle otra condición, y quitar de verdad sigue
  quitando (y deja la marca). **Negativo probado**: volviendo a quitar cuando no hay marca, la prueba cae.
- Suite 249 en verde, PHPCS 0, PHPStan `[OK]`.

## [2.8.55] — 2026-10-15

### Arreglado
- **El listado de horas sale con las filas vacías (#32).** `prepare_items()` fijaba los registros y la
  paginación, pero **no** `_column_headers`, así que `WP_List_Table` recorría **cero columnas**: la pantalla
  de «Horas de Voluntariado» decía «9 elementos» y no pintaba ni una celda —ni en la cabecera—. Medido en la
  demo: `0` etiquetas `<td>` en toda la página y nueve `<tr></tr>` vacías. Ahí vive el botón **Aprobar** de
  las horas que el personal registra desde su panel, así que sin celdas la aprobación era **inalcanzable**
  desde la interfaz aunque el resto de la cadena funcionara.

### Pruebas
- `tests/Unit/ListadoHorasColumnasTest.php`: obliga a fijar `_column_headers` y comprueba que cada columna
  declarada tiene su `column_*` (una columna sin render es una celda en blanco). **Negativo probado**:
  quitando la línea, la prueba cae.

## [2.8.54] — 2026-10-15

### Añadido
- **Un monitor de actividad puede acreditar horas (fase 3 de #30).** `puede_acreditar_horas()` solo admitía
  al voluntariado aprobado, así que quien está delante de la actividad —el monitor— no podía cerrar la
  acreditación de nadie, y las horas extra que registre una persona desde su panel se quedaban sin quien las
  apruebe. Ahora también pueden el **monitor** y quien **administra** el sitio. Un socio no: ser socio no da
  permiso para acreditar horas de otras personas.

### Pruebas
- `tests/Unit/PuedeAcreditarHorasTest.php` (4 casos): monitor sí, voluntariado aprobado sí, socio corriente
  no, sin usuario no. **Negativo probado**: quitando el monitor, la prueba cae.

## [2.8.53] — 2026-10-15

### Añadido
- **Renovar por horas o por cuota (fase 2 de #30).** El umbral de horas sale **del plan de cada socio**, no
  de una cifra global (decisión de JC, 15/10/2026): bronce 15, plata 25, oro 50, voluntariado 20. Se extrae a
  `Voluntariado_Manager::horas_objetivo()`, que es el único sitio que decide cuántas horas pide un ciclo, y
  queda medible. Si el plan no está en la configuración se usa un objetivo por defecto filtrable
  (`convoca_members_horas_objetivo_defecto`); sin plan no hay ciclo que cumplir.
- **El plan de voluntariado entra siempre en la configuración.** No estaba garantizado: `get_plan()` devolvía
  `null` para él y sus horas no se evaluaban nunca, así que un voluntario no podía cumplir el ciclo.
- **Cumplir las horas concede la condición de socio** y deja constancia de la vía, sin quitar la de
  voluntario: las dos conviven. Y **pagar la cuota** concede (o confirma) la de socio; si es una renovación,
  el ciclo anterior se cierra y empieza uno nuevo.

### Pruebas
- `tests/Unit/HorasObjetivoPorPlanTest.php` (3 casos, 15 aserciones): recorre los planes **reales** y comprueba
  que el umbral de cada ficha es el de su plan. **Negativo probado**: con una cifra global, la prueba falla y
  nombra el plan que no cuadra («bronce» pide 15 y el umbral devuelve 20).
- El arnés del núcleo gana `remove_filter()`: faltaba, y sin él una prueba que añade un filtro lo dejaba
  puesto para las siguientes.

## [2.8.52] — 2026-10-15

### Arreglado
- **El rol de socio se creaba solo al activar el plugin.** Medido en la demo tras desplegar la 2.8.51: el rol
  no existía (`wp role list` no lo listaba), porque la llamada quedó dentro del bloque de activación y una
  instalación que **se actualiza** no pasa por ahí. Ahora se asegura en cada carga (`init`), que es
  idempotente y cuesta un `get_role()`.

## [2.8.51] — 2026-10-15

### Añadido
- **El rol de socio, de verdad (`socio`).** No existía: los socios eran `subscriber` y su condición vivía en
  una meta, así que no había forma de conceder nada a «los socios» sin mirar la ficha una a una. Se crea el
  rol al cargar el plugin, idempotente, y se refleja en la ficha (`_convoca_es_socio`) y en la taxonomía
  `tipo_miembro`.
- **Tres condiciones acumulables: socio, voluntario y monitor.** `Roles_Miembro` decide las tres con la misma
  regla: `anadir()` nunca quita las otras y `quitar()` solo quita la suya. El espejo al usuario de WordPress
  va con `add_role()`/`remove_role()` y **jamás** con `set_role()`, que es lo que hacía imposible el doble rol
  (aprobaba el voluntariado y borraba la condición de socio).

### Arreglado
- **La columna «Tipo» del listado ya no sale vacía (#28).** Leía la taxonomía `tipo_miembro`, que estaba
  registrada y sin un solo término. Ahora mira, por este orden: la taxonomía, las condiciones de la ficha y,
  si aún no hay nada, el plan —que es el dato que existe desde siempre—. Dice algo con los datos de hoy y
  dirá lo correcto cuando las fichas tengan sus condiciones.
- **Aprobar o revocar el voluntariado de un socio ya no le quita su condición de socio.** Los tres sitios que
  usaban `set_role()` pasan por `Roles_Miembro`.

### Pruebas
- `tests/Unit/RolesMiembroTest.php` (13 casos) y `tests/Unit/RolSocioMonitorTest.php` (6 casos, nuevos). Con
  los negativos probados: si el espejo vuelve a `set_role()`, caen tres; si el rol de socio deja de llamarse
  `socio`, caen dos.

## [2.8.50] — 2026-10-15

### Arreglado
- **El carnet digital vuelve al plan gratuito (#core17).** La web lo anuncia entre los ganchos del plan
  gratis y el código lo servía detrás de `has_pro( 'pdf_memories' )`, que es la clave de las **memorias
  PDF**: quien instalaba la versión gratuita no veía el carnet, justo lo que se le promete. Ahora se sirve
  sin licencia. Las memorias PDF —esas sí PRO— siguen con su puerta, y la exportación de informes también.
- Prueba nueva `tests/Unit/CarnetSinLicenciaTest.php`, que fija las dos caras: el carnet sin licencia y las
  memorias con ella.

## [2.8.49] — 2026-10-15

### Arreglado
- **Dar de baja a un socio ya no lo borra para siempre (#27).** Había dos maneras de quitarlo y las dos
  estaban mal: el enlace «Eliminar» del listado llamaba a `wp_trash_post()` sobre un tipo que **no admite
  papelera**, así que respondía 200, volvía al listado y **no hacía nada** sin decir por qué (un botón muerto
  que además despistaba sobre cómo dar de baja); y el borrado de verdad (`wp_delete_post( $id, true )`) se
  llevaba ficha, cuotas, horas e inscripciones de un clic, sin vuelta atrás y dejando el histórico apuntando a
  un socio que ya no existe. Ahora:
  - **Dar de baja** es un cambio de estado `baja`: la ficha se conserva, deja de contar como socio activo, el
    número de socio no se reutiliza y se puede reactivar. Está en la fila y en el desplegable de lote.
  - **Eliminar definitivamente** existe aparte, con un aviso que dice qué se pierde en vez de un «¿seguro?».
    No se ofrece en lote a propósito.
  - **La papelera desaparece de este tipo** (acción de fila y de lote, también en la pantalla cruda de
    WordPress), que era un botón muerto.
- Prueba nueva `tests/Unit/BajaLogicaTest.php`: cruza los enlaces del listado con los manejadores del editor,
  vigila que la baja no borre, que el borrado definitivo sí borre y que nadie vuelva a mandar fichas a una
  papelera que el tipo no admite. Cada aserción con su negativo probado.

## [2.8.44] — 2026-10-14

### Revertido (con el motivo escrito)
- **Se revierte el mapeo de capacidades de los tipos con datos personales que entró en 2.8.43.** La
  2.8.43 hacía que un `editor` no pudiera listar ni borrar fichas de socios, y **rompía el mecanismo de
  capacidades de WordPress**: al asignar la capacidad propia `gestionar_miembros` al hueco de la
  capacidad meta `edit_post` del tipo de contenido, WordPress la resuelve contra una entrada concreta
  y, cuando se comprueba sin entrada —que es como la comprueban las pantallas propias del plugin—
  devuelve `do_not_allow`. Medido en el sitio:

  ```
  map_meta_cap( 'gestionar_miembros', 2 )  ->  do_not_allow
  map_meta_cap( 'manage_options', 2 )      ->  manage_options
  map_meta_cap( 'gestionar_documentos_voluntariado', 2 ) -> gestionar_documentos_voluntariado
  ```

  Es decir: sólo esa capacidad dejaba de valer, y **para todo el mundo, administrador incluido**, así
  que el gobierno de socios quedaba inaccesible. Un defecto funcional peor que el que se arreglaba,
  que además era latente (ningún sitio tiene usuarios con rol `editor`).
- El defecto original (**un `editor` puede ver el listado de socios y mandar fichas a la papelera**)
  queda **abierto** en su issue, con esta medición como punto de partida y la advertencia de por dónde
  NO va el arreglo.
- Guarda para que no se reintente mal: `tests/Unit/CapacidadesSinColisionTest.php` — ninguna capacidad
  propia puede ocupar el hueco de las meta del núcleo (`edit_post`, `read_post`, `delete_post`), con
  negativo probado.

## [2.8.42] - 2026-10-14

### Arreglado

- **La web del cliente ya no muestra la marca del proveedor** (recorrido a ciegas, criterio 16).
  En el alta de socio, la tarjeta decía «🍁 CONVOCA», el consentimiento «Autorizo a Convoca a
  enviarme comunicaciones» y la bienvenida «¡Bienvenido/a a Convoca!»: quien se hacía socia en la
  web de Los Lugg veía el nombre del software en vez del de la asociación. Ahora es un ajuste
  («Nombre de la entidad») con el nombre del sitio de WordPress como respaldo.
- **`<a href="">` fuera del producto**: «Código Ético» se pintaba con
  `apply_filters( 'convoca_members_alta_document_url', '' )`, cuyo respaldo es la cadena vacía — un
  enlace que no lleva a ninguna parte, en HTML, y que el usuario pulsa. Ahora, si el documento no
  está configurado, se muestra su nombre **sin enlace**.
- **Higiene: tres URLs de Google Drive de la entidad hardcodeadas en las plantillas** (issue #12).
  Dónde apuntan los documentos de una entidad es decisión suya: pasan a ser ajustes del panel.
- **La casilla del RGPD da señal cuando bloquea el avance.** El aviso ya se pintaba, pero el campo
  no se marcaba ni recibía el foco: quien navega con teclado o lector de pantalla oía un mensaje sin
  saber a qué campo se refería (medido con el navegador sobre la demo el 14/10/2026). Ahora el grupo
  se marca (`has-error`), la casilla lleva `aria-invalid` y se le lleva el foco.
- **La tarjeta ya no se inventa el número de socio**: decía «Socio/a #TEMP» mientras no había
  número. El número lo asigna el servidor al confirmar; hasta entonces se dice lo que pasa.

### Añadido

- Cuatro ajustes nuevos en el panel: nombre de la entidad, Código Ético, Programa de Voluntariado y
  Protocolo de prevención del acoso (los tres últimos, como URL).

### Pruebas

- 7 pruebas nuevas (`IdentidadDelSitioTest`), 204 en total. Tres comprobaciones probadas **en
  negativo**: con el filtro del respaldo vacío de vuelta, con la marca del proveedor de vuelta y con
  el número de socio inventado, la prueba falla.

### Nota de instrumento

- Escribir la etiqueta de cierre de PHP dentro de un comentario de línea **cierra el modo PHP**: el
  fichero entero pasa a leerse como HTML y el error que aparece después («Unclosed '{'») no señala a
  esa línea. Costó una vuelta de reloj.

## [2.8.39] - 2026-10-13

### Arreglado

- **`PHP Deprecated` en la tabla de horas**: dos columnas (socio y actividad) pasaban
  `get_edit_post_link()` directo a `esc_url()`; esa función devuelve `null` cuando la ficha no existe
  o no es editable y `esc_url( null )` deja `ltrim( null )` en el registro. Ahora, sin enlace, se
  pinta el nombre sin `<a>`.

### Pruebas

- `EnlacesDeEdicionTest` (probado en negativo: con el defecto repuesto, falla).

## [2.8.38] - 2026-10-13

### Arreglado
- **El acceso al panel de socio, sin JavaScript, también era un callejón sin salida**: su formulario
  no declaraba `method` ni `action`, así que el envío se perdía (GET a la misma página, sin
  procesarse y sin decir nada). Ahora envía por POST con marcador
  (`convoca_sin_js_panel_socio`), avisa con `<noscript>` antes de rellenar y, si la petición llega
  sin JS, dice qué pasa y ofrece el correo de la asociación (filtro `convoca_panel_socio_sin_js_email`).
- Con esto los **cinco** formularios públicos del ecosistema (alta, voluntariado, inscripción,
  panel de reservas y acceso al panel) declaran POST y su degradación: el criterio «móvil y sin
  JavaScript» ya se puede medir entero.
- Prueba añadida a `tests/Unit/SinJavaScriptTest.php` (comprobación en el código del formulario del
  panel), probada **en negativo**.

## [2.8.37] - 2026-10-13

### Arreglado
- **Sin JavaScript, el alta de socio y el voluntariado eran un callejón sin salida.** Medido con
  Playwright sin JavaScript (390x844): los dos formularios no declaraban `method` ni `action`, así
  que el navegador los enviaba por GET a la misma página con los campos en la URL y **nadie los
  procesaba**: el alta se perdía en silencio, sin guardar nada y sin una frase que lo explicara.
  (La primera medición atribuyó al alta un «Página no encontrada» que en realidad era el **buscador
  del tema** — el arnés cogía el primer `form` de la página. El defecto del alta es el de arriba,
  comprobado en el HTML servido y re-medido en el sitio ya desplegado.)
- Ahora envían por **POST a su propia página** con un marcador (`convoca_sin_js_alta` /
  `convoca_sin_js_voluntariado`). Cuando la petición llega sin JavaScript, el template explica la
  situación y ofrece el correo de la asociación (con el teléfono si está configurado); el
  `<noscript>` lo avisa **antes** de rellenar. Los datos de contacto salen de los ajustes del
  plugin (`admin_email`, `telefono`) con filtros `convoca_alta_sin_js_*` y
  `convoca_voluntariado_sin_js_*`: nada escrito a mano en el código.
- Prueba `tests/Unit/SinJavaScriptTest.php` (una parte ejecuta el template con el marcador y
  comprueba que no se pinta el formulario), probada **en negativo** con los templates de HEAD.

## [2.8.36] - 2026-10-13

### Arreglado
- **El alta de socio y el voluntariado respondían «Error desconocido.» cuando la petición fallaba**
  sin mensaje del servidor. El texto lo resuelve ahora la capa común de `convoca-core`
  (`conv.mensajes` / `conv.normalizarError`): dice qué hacer en cada caso (sin conexión, sesión
  caducada, acción no disponible) y traduce el `0` y el `-1` de `admin-ajax`, que antes llegaban
  como un número y acababan en un respaldo inventado por el llamador.
- Los dos botones de la ficha del socio (link de pago y recordatorio) y los dos de RGPD (exportar
  y borrar datos) pasan por `convAdmin.ajaxPost`; el texto del servidor se escapa antes de
  pintarlo.
- Prueba `tests/Unit/MensajesDeErrorTest.php`, probada **en negativo** con el JS de HEAD.

## [2.8.35] - 2026-10-12

### Arreglado
- **Seis llamadas AJAX del JavaScript publicaban acciones que no existen**: el JS seguia usando
  el prefijo viejo `conv_` mientras el plugin registra `convoca_`. Lo encontro el auditor a ciegas
  de la pasada 6 (una) y la comprobacion de contrato que se ha anadido, las otras cinco:
  `conv_get_nonce` (el endpoint del nonce «bypass cache»: muerto, asi que en una pagina cacheada
  el alta respondia «Nonce invalido» sin poder refrescar), `conv_export_csv` y
  `conv_save_csv_columns` (exportar CSV y guardar columnas), `conv_change_state`,
  `conv_log_whatsapp` y `conv_voluntariado_submit`.
- Se anade `tests/Unit/AjaxAccionesTest.php`: cruza las acciones que publica el JS con las que
  registra el PHP, en las TRES formas de publicarlas (ayudante, objeto de jQuery y `action=` dentro
  de una URL, que era la que se colaba). Probado en negativo; sin el arreglo falla y las nombra.

# Changelog — convoca-members

## [2.8.48] - 2026-10-15

### Corregido

- **Importar CSV podía duplicar a un socio que ya existía** (medido al verificar #26): el control de
  duplicados miraba ANTES al usuario de WordPress y, si el correo era de un usuario, devolvía «no hay
  duplicado». Como a casi todos los socios el alta les crea un usuario con su correo, el control no veía a
  ninguno de ellos: subir un CSV con un socio ya existente creaba una ficha repetida y el resumen decía
  «1 importados, 0 errores». Ahora manda la ficha (por correo y por DNI) y el usuario solo se mira cuando
  no hay ninguna.

## [2.8.47] - 2026-10-15

### Corregido

- **Un alta con el nombre y el DNI en blanco se aceptaba** y dejaba el deduplicador envenenado (#23): la
  validación usaba `empty()`, y `empty( '     ' )` es **FALSE** —una cadena de espacios no está vacía—, así
  que el alta pasaba y se saneaba después: quedaba una ficha con el título vacío y el `_convoca_dni`
  vacío. A partir de ahí, cualquier otra persona que dejara el DNI en blanco recibía «Ya existe un miembro
  registrado con este DNI o email.» porque el deduplicador casaba con el DNI vacío de la ficha fantasma.
  Ahora se sanea antes de validar y el deduplicador solo compara contra datos que existen.
- **Importar CSV contaba como importadas las filas que fallaban** (#26): el resumen decía «1 socios
  importados correctamente. 1 errores» de la MISMA fila. Además, la llamada pasaba el contador en el
  parámetro del número de fila, así que el mensaje salía como «Fila 0» en vez de la fila de verdad. Ahora
  solo cuenta lo creado y el número de fila es el de la fila.
- **`convoca_member_sequence` acumulaba filas huérfanas** (#16): nadie borraba la fila al eliminar un
  socio, así que la tabla acabó con 124 de 124 filas huérfanas en la demo y 4 de 4 en Lugg. La fila se
  borra ahora al eliminar la ficha definitivamente (no al enviarla a la papelera: ahí se puede restaurar y
  tiene que conservar su número) y el paso de actualización 2.8.47 limpia lo acumulado. Borrar filas no
  reutiliza números: el número de socio sale del AUTO_INCREMENT.

## [2.8.46] - 2026-10-15

### Corregido

- **Ocho defectos del panel del socio y del alta** (auditoría de navegador):
  - La pestaña de Voluntariado no cargaba nunca: pedía `/activities`, un endpoint que no existía, y su
    `Promise.all` fallaba entero. Se añade el endpoint **y** cada petición lleva su propio respaldo, para
    que un solo fallo no tumbe la pestaña.
  - El certificado se podía descargar **sin haber completado las horas**: el endpoint de descarga no
    comprobaba nada. Ahora aplica la misma regla que la pantalla.
  - Cada descarga **acuñaba un identificador nuevo** y sobrescribía el guardado, así que los
    certificados ya entregados dejaban de verificar. El identificador se emite una sola vez.
  - El carnet abría una pestaña con **el JSON en crudo** en vez de la tarjeta (se escribía la respuesta
    entera en lugar de su campo `html`).
  - La pestaña de pagos mostraba el estado interno **en inglés** (`Paid`): ahora dice «Pagado».
  - El resumen del alta decía «Comunicaciones: No» aunque la casilla estuviera marcada (leía un nombre
    de campo que no existe).
  - El aviso de error del formulario de voluntariado **se quedaba en pantalla** tras un envío correcto.
  - La marca de voluntariado del alta **no llegaba a «Solicitudes Pendientes»**: se quedaba en la ficha
    del socio y nadie la revisaba.

## [2.8.45] - 2026-10-15

### Corregido

- **Mensaje de error del teléfono**: decía «9 dígitos, **mulaiing** con 6-9». Además de la errata, no se
  entendía; ahora dice «9 dígitos, empezando por 6, 7, 8 o 9». Lo cazó la auditoría independiente de la
  pasada 11 del gauntlet y lo ve cualquiera que se equivoque al escribir su teléfono.

## v2.8.34 (2026-10-12)

### Cambiado
- Higiene de datos: el comentario que documenta el `show_ui` de `tipo_miembro` citaba un
  sitio concreto por su nombre. Se conserva el motivo entero (por que WordPress devuelve 500
  y no 403) y se quita el nombre, que no pinta nada en el codigo del producto. Lo caza el
  propio check de higiene de la bateria.

## v2.8.33 (2026-10-12)

### Arreglado
- **La pantalla de terminos de `tipo_miembro` respondia HTTP 500.** La taxonomia se
  registraba con `public => false` y sin `show_ui`; en una taxonomia `show_ui` hereda de
  `public`, asi que se quedaba sin interfaz, y WordPress 7 rechaza `edit-tags.php` de una
  taxonomia sin interfaz con un `wp_die` **sin estado**: al navegador le llega un 500 (y no
  un 403) con «no tienes permisos», con el administrador delante. Reproducido en la demo y
  en Lugg.
- Se declara `show_ui => true` y `show_in_menu => false` (la navegacion de miembros es la
  suya). `public` sigue en `false`: los tipos no exponen URLs.
- Regresion blindada: `tests/Unit/TaxonomiaTipoMiembroTest.php` mira la taxonomia
  registrada de verdad.

## v2.8.31 (2026-10-12)

### Arreglado
- **El alta de socio dejaba al socio sin cuenta** cuando su correo ya pertenecia a un usuario
  de WordPress (el administrador que se da de alta como socio, un monitor con su correo de
  trabajo, o un socio al que se le vuelve a dar de alta). Se intentaba crear otro usuario, el
  correo ya estaba en uso, y el reintento usaba el MISMO correo: fallaba igual, el socio
  quedaba de alta pero **sin poder entrar en Mi Area**, y el log acumulaba dos ERROR por
  intento. Ahora ese caso **enlaza el usuario que ya existe**.
- La decision se extrae a `plan_user_account()` (publica, con las entradas explicitas) y el
  enlace va en un unico sitio, `link_user_to_member()`, en los dos sentidos.
- Prueba `MemberAccountPlanTest`, probada en negativo.

## v2.8.30 (2026-10-12)

### Arreglado
- **PHPCS a 0 avisos y 0 errores** en todo el ecosistema, sin el silenciador
  `ignore_warnings_on_exit` en el CI. Entre los avisos habia defectos reales:
  un `var_dump` alcanzable por URL (`?debug=1`) que volcaba los metadatos de una
  inscripcion sin escapar, una clase `CLI_Webhooks` declarada en dos ficheros
  (fatal si se cargaban ambos) y 30 `wp_redirect` que pasan a `wp_safe_redirect`.
- 11 `@` que ocultaban errores de PHP, sustituidos por comprobaciones reales: un `@`
  no arregla nada, esconde el sintoma y deja el registro de avisos mintiendo.
- 14 `error_log()` que solo escribian en el log del servidor pasan al `Logger` del
  ecosistema, que ademas los muestra en el panel.
- `urlencode()` -> `rawurlencode()` en 4 sitios (en una query string, `urlencode`
  codifica el espacio como `+`), `in_array` con comparacion estricta, comparaciones
  `===`, `current_time('timestamp')` y `serialize()` sustituido por `wp_json_encode`.

### Notas
- Sin cambios de comportamiento en los flujos de usuario: lo arreglado son avisos
  del analizador, seguridad de redirecciones y registro de errores.

## v2.8.29 (2026-10-11)

### Arreglado
- Mismo criterio de **fallbacks encadenados** en el `.btn-primary` del área privada: el sitio que
  declare `naranja-oscuro` en su paleta lo usa (texto blanco, 5.07:1) y el que no conserva el
  naranja de marca, para no romper a los temas que ajustan el color del texto por su cuenta.

## v2.8.28 (2026-10-11)

### Arreglado
- **Contraste de los botones del área privada** (`public/assets/mi-area.css`): `.btn-primary`
  pintaba texto blanco sobre `#ff8700` = **2.41:1** (mínimo 4.5:1) y en `:hover` **aclaraba** a
  `#ffa033`, o sea empeoraba el contraste. Ahora usa `naranja-oscuro` (**5.07:1**) y el hover
  oscurece a `#8f4400` (**7.01:1**). `.btn-link:hover` también pasa a naranja-oscuro.
  Medido con axe: el botón «Entrar» de Mi Área.

## v2.8.27 (2026-10-11)

### Arreglado
- **El botón del paso 1 del alta usaba clases genéricas** (`btn btn-primary`) mientras los pasos
  2, 3 y 4 usaban las del ecosistema (`convoca-btn convoca-btn-primary`): el botón del primer paso
  no seguía el sistema de diseño del producto. Ahora los cuatro son iguales.
  Cubierto por `tests/Unit/BotonPrimarioTest.php`.

## v2.8.26 (2026-10-11)

### Arreglado
- **Contraste del formulario de alta de socio (WCAG 2.1 AA)**, medido con axe en la demo:
  - `.conv-price` (el precio de cada plan) usaba el naranja de marca, **2.41:1** sobre blanco.
    Pasa a `--wp--preset--color--naranja-oscuro` (**5.07:1**).
  - `.conv-alt` («· 15h para renovar sin pagar») usaba `#888`, **3.54:1** siendo texto pequeño
    (exige 4.5:1). Pasa a `--wp--preset--color--gris-texto` (**6.28:1**).
  - Los bordes y los `accent-color` en naranja se dejan: no son texto y no tienen ese requisito.

## v2.8.25 (2026-10-11)

### Corregido
- **El correo de objetivo de voluntariado se enviaba DOS veces.** `Email_Manager` registraba
  el mismo callback en el nombre nuevo (`convoca_members_email_objetivo_voluntariado`) y en el
  retirado (`convoca_email_objetivo_voluntariado`), y `Utils::do_action()` dispara los dos
  cuando hay oyente en el viejo: el voluntario recibía dos copias del correo y cada envío
  dejaba una línea `PHP Deprecated` en el log. Se registra solo el nombre nuevo. Un comentario
  del fichero decía «Legacy hooks removed» mientras la línea seguía ahí; el comentario ahora
  describe lo que hace el código.

## v2.8.24 (2026-10-09)

### Arreglado — el alta del socio: la revisión, el método de pago y el texto del IBAN (cierra #3, #4 y #5)

- **La revisión del paso 4 no mostraba 8 de los 18 datos** (whatsapp, canal, edad, menor, modalidad,
  importe, fecha y comunicaciones): la plantilla definía 10 nodos y el JS rellenaba 18, así que los 8
  que faltaban se escribían sobre nodos inexistentes y **sin dar ningún error**. Añadidas las filas.
- **El método de pago se guardaba en el meta equivocado.** El alta escribía tarjeta/bizum/transferencia
  en `_convoca_forma_pago`, un meta que en el resto del plugin significa cuota vs voluntariado: la
  ficha del socio salía sin selección válida, al guardar se pisaba a «cuota» y el CSV mostraba
  «transferencia» como forma de pago de la cuota. Ahora el método va a `_convoca_metodo_pago` y
  `_convoca_forma_pago` queda en «cuota», coherente con lo que normaliza `Payment_Listener`.
- **El paso 3 prometía el IBAN** y la pasarela volvía a pedir el método: texto corregido para que
  describa lo que pasa de verdad.

### Añadido
- **Los dominios de correo del equipo salen del código y pasan a Ajustes** (campo «Dominios de correo
  del equipo», separados por «|»). El valor por defecto es automático —el dominio del email del propio
  sitio más el del proveedor—, así que cada instalación reconoce los suyos sin que el código lleve el
  nombre de ninguna. El valor se sanea y los puntos se escapan antes de entrar en el patrón.

### Cambiado
- Higiene del producto: retirados el nombre del cliente y el de la asociación de textos y muestras.

### Internamente
- Las pruebas usan las clases reales del núcleo en vez de dobles que se quedaban cortos, y se corrigió
  el bootstrap de la suite unitaria, que falseaba el núcleo.

## v2.8.23 (2026-09-27)

### Arreglado — dos cifras de cara al socio salían con el punto decimal inglés (bloque 9)

El flujo del certificado, revisado de punta a punta (formulario público → `verify()` → resultado),
dejó a la vista un desliz que no era del PDF sino de la página:

- **La verificación pública del certificado** imprimía las horas con `number_format( $horas, 1 )`, o
  sea con los separadores de PHP: **«12.5 h»**. Ahora usa `Texto_PDF::horas()` → **«12,5 h»**.
- **El importe de renovación del panel del socio** (`class-mi-area.php`) igual: `number_format(
  $importe, 2 )` daba **«50.00 €»** → ahora **«50,00 €»**, como ya hacían otras dos partes del
  plugin (`Rest_API` y `GDPR_Tools` sí pasaban los separadores explícitos).

Sin decimales no hay problema (`number_format( $x, 0 )` para euros enteros se queda como está).

Guardado con `FormatoCifrasTest`, que vigila **la clase de fallo** y no solo estos dos sitios:
recorre `includes/`, `public/` y `admin/` y falla si alguna cifra con decimales se formatea sin los
separadores explícitos. Comprobado que el guardián **caza** el fallo (se metió a propósito y lo
señaló con fichero y línea) y que los dos arreglos están en su sitio.

## v2.8.22 (2026-09-27)

### Arreglado — completada la resolución del plan por etiqueta (2.8.21 no bastaba)

La 2.8.21 buscaba el plan por etiqueta, pero comparaba en crudo y **las etiquetas llevan adorno**:
la de la demo es literalmente «🥉 Bronce» y el socio tiene guardado «Bronce» (castellano, con la
clave en inglés: `bronze`). No casaba, así que el carnet seguía saliendo «SOCIO/A».

- La comparación quita ahora todo lo que no sea letra, número o espacio —el emoji, los guiones, los
  adornos— antes de comparar, además de acentos, mayúsculas y espacios de sobra.
- Caso real fijado en `CPTMiembroTest`: clave `bronze`, etiqueta `🥉 Bronce`, socio `Bronce` → el
  carnet muestra la modalidad.

## v2.8.21 (2026-09-27)

### Arreglado — el carnet no distinguía la modalidad del socio (bloque 8)

`CPT_Miembro::get_plan()` buscaba el plan **solo por la clave exacta**. Un socio guardado con la
**etiqueta** en vez de la clave —así están los sembrados de la demo, con «Bronce» donde debería ir
`bronze`— no resolvía, y los tres documentos caían al texto genérico: el carnet salía con
**«SOCIO/A»** en lugar de la modalidad, el acuerdo sin el plan y el certificado sin la etiqueta.

- Ahora, si la clave exacta no existe, se busca también por la **etiqueta** del plan y por la clave
  normalizada (sin acentos, sin distinguir mayúsculas, sin espacios de sobra).
- Si la clave exacta existe, se devuelve como siempre: no cambia nada de lo que ya funcionaba.
- Afecta a los tres documentos (carnet, acuerdo y certificado) y a cualquier consumidor del plan.

Guardado con tres casos en `CPTMiembroTest`: por clave, por etiqueta (con caja y espacios distintos)
y el nulo cuando no existe.

## v2.8.20 (2026-09-27)

### Arreglado — la modalidad larga rompía la cabecera del carnet (bloque 8)

El distintivo de la modalidad se pintaba en la misma línea que el logo. Con las etiquetas cortas
(«Ejemplo», «Deva», las dos activas hoy en Ejemplo) cabía; con una etiqueta larga —p. ej. «Modalidad
Familiar Juvenil de Busgosu», 37 caracteres— **no cabía y la descomponía**: con Dompdf el grupo de
insignias se metía encima del nombre de la organización y lo tapaba, y probando alternativas (tabla,
posiciones absolutas dentro de la cabecera) las insignias caían en la línea de los datos o el logo
se partía en dos líneas.

Solución: en el PDF, si la etiqueta de la modalidad pasa de 15 caracteres **ocupa su propia línea**
y el cuerpo y el pie bajan (hay sitio de sobra en los 280 px del folio). Queda en dos líneas, sin
solapes y legible; comprobado mirando el PDF generado. Las etiquetas cortas se quedan exactamente
como estaban.

- La decisión va en una sola clase en la tarjeta (`card--plan-largo`), solo en el PDF: en el
  navegador la cabecera la reparte el flexbox y funciona con cualquier etiqueta.
- Medido con las cuatro modalidades: `ejemplo` y `deva` (una línea, sin cambios), `Modalidad Familiar`
  y la larga (dos líneas, cuerpo a 112 px y pie a 192 px, sin tocarse).

## v2.8.19 (2026-09-27)

### Añadido — base de estilos común de los documentos (bloque 8)

Los tres documentos (carnet, acuerdo y certificado) usaban la misma tipografía y los mismos colores
de marca, cada uno por su cuenta, y todos se dibujan con Dompdf, que no es un navegador.

- `Estilos_PDF` reúne **la tipografía y los colores** en un solo sitio (constantes de PHP, porque
  Dompdf no soporta variables CSS) y trae los resets neutros que comparten (margen del cuerpo,
  imágenes al 100%, tablas sin doble borde).
- Y sobre todo **escribe las trampas de Dompdf** que se fueron midiendo una a una, para que el
  próximo documento no vuelva a tropezar: no hay flexbox (se coloca con flotantes y `clear`),
  `overflow: hidden` **recorta** los contenedores con flotantes dentro (altura cero, y el texto sigue
  en el fichero, así que `pdftotext` no lo delata), `box-sizing: border-box` no se respeta (suma
  padding y borde), `@media print` no existe, la tipografía no tiene glifos de emoji y no hay
  variables CSS.
- La base se **antepone** a la hoja de cada documento: cada uno manda en lo suyo. Comprobado antes y
  después con los tres documentos generados: **mismo número de páginas, mismo tamaño de folio y el
  mismo texto**. En el carnet el texto extraído es idéntico byte a byte; en el acuerdo y el
  certificado solo cambian la fecha de firma y el ID, que son nuevos en cada generación.
- La base **no fija el color del texto** a propósito: la plantilla del acuerdo la edita el sitio y,
  por ir después en la hoja, un color puesto en la base se le impondría al suyo.

### Pruebas
- `EstilosPdfTest` (4 casos): la base trae lo que debe, no se impone al color de cada documento, el
  carnet la incluye y los tres documentos la llaman — un documento nuevo que la olvide falla aquí.

## v2.8.18 (2026-09-27)

### Corregido — los textos del certificado (bloque 9)

Salieron de **mirar el certificado generado** (y de comparar sus horas con el libro de horas):

- **Las horas salían con punto fijo**: «18.5 horas». `number_format()` no mira nada, así que un
  documento en castellano salía con punto. Ahora se escriben **«18,5 horas»** (y «1.234,5» si algún
  día hay muchas horas).
  Se probó primero con `number_format_i18n()` y **no vale**: medido en la demo —locale `es_ES` y las
  traducciones cargadas—, WordPress devolvía `decimal_point = '.'` y `thousands_sep = ','`, o sea el
  formato inglés, así que el certificado salía con las letras en castellano y **las cifras en
  inglés**. Los documentos de Convoca están escritos en castellano: sus cifras también. Y para quien
  quiera otra convención hay un filtro, `convoca_documento_horas`.
- **La etiqueta del plan podía llevar emoji y salía como «?»**: los planes de la demo se llaman
  «🥉 Bronce» y en el PDF se imprimía «? Bronce» (la tipografía del PDF no tiene ese glifo), igual
  que pasaba en el carnet. Se quita el emoji y se conserva el texto.

### Añadido
- `Texto_PDF` reúne las dos ayudas de texto de los PDF (quitar emojis y dar formato a las horas) en
  un solo sitio, en vez de repetirse en cada documento. El carnet pasa a usarlas también.

### Verificado
- **Las horas certificadas salen del libro de horas**: un socio con 8 + 6 + 4,5 h aprobadas y un
  registro **anulado** de 10 h recibe un certificado de **18,50 h** — el anulado no cuenta.
- El QR es **local** (chillerlan, sin API externa) y el documento sale en **una página** A4.

## v2.8.17 (2026-09-27)

### Corregido — los PDFs de tarjeta y acuerdo (bloque 8)

Todo esto salió de **medir los PDF de verdad** (pdfinfo, pdftotext, el contador de páginas de
Dompdf), no de leer el código. El acuerdo se iba a dos páginas y la tarjeta a dos y con el botón
de imprimir dentro.

- **El acuerdo cabe en una página.** El sello de aceptación se iba a la segunda página. Ahora el
  documento se aprieta lo justo (márgenes del folio, espaciados y el sello del core más cerca) y
  entra en una.
- **El marcador de la firma se acepta con los dos nombres de clase.** El código buscaba
  `<!-- … POR LA CLASE Signature -->`, pero las plantillas guardadas —como la de este sitio— dicen
  `BDV_Signature` (el nombre antiguo). Al no reconocerlo, el sello **siempre** acababa al final del
  documento en vez de donde el sitio lo había puesto. Ahora se reconoce cualquiera de los dos.
- **La tarjeta del PDF no lleva el botón «IMPRIMIR / GUARDAR PDF».** Dompdf ignora `@media print`,
  así que el botón —marcado como `no-print`— se colaba dentro del PDF. En modo PDF no se pinta; en
  el navegador sigue estando.
- **La página del PDF es la tarjeta** (450x280 px = 119x74 mm), no una A4 con la tarjeta flotando
  en medio del folio.
- **La tarjeta ya no sale en dos páginas.** Fueron tres causas, medidas una a una:
  1. Dompdf **no respeta `box-sizing: border-box`**: le suma el padding (30x2) y el borde (1x2) a
     las medidas, así que la tarjeta se le iba a 510x340 y no cabía en un folio de 450x280. En PDF
     se le dan las medidas del contenido.
  2. Dompdf **no sabe hacer flexbox**: las tres zonas de la tarjeta (cabecera, datos, pie) se
     apilaban en vertical y el QR de 75 px se salía de los 280 px de alto. En PDF se colocan con
     flotantes y `clear`.
  3. Los **adornos decorativos** del fondo (200 px en `top:-60px`) sobresalen del folio y Dompdf los
     cuenta. En PDF no se pintan.
- **Un emoji en la etiqueta del plan salía como «?»** (Helvetica no tiene el glifo): «🏅 Bronce» se
  imprimía «? BRONCE». En el PDF se quita el emoji y se conserva el texto.
- **El carnet se imprimía recortado**: en la página solo salían dos líneas. La causa era
  `overflow: hidden` en la cabecera y el pie —puesto para contener los flotantes—, porque Dompdf
  calcula esos contenedores con altura CERO y **recorta** su contenido: desaparecían el logo, las
  insignias, la fecha y el QR. El texto seguía en la capa del PDF, así que `pdftotext` no lo veía;
  se descubrió **mirando el PDF**. Y las tres zonas se reparten ahora con posiciones absolutas,
  porque el `justify-content: space-between` del navegador no existe en Dompdf y el contenido se
  apelotonaba arriba dejando un tercio del carnet vacío.
- **Un nombre largo se cruzaba con el pie del carnet**: se partía en tres líneas, se salía del
  bloque y sus líneas salían intercaladas con la fecha y el dominio. El nombre ajusta ahora su
  tamaño por longitud (20 / 16 / 13 px), también en el navegador. Probado con 107 caracteres.
- **La tarjeta ya se puede generar fuera del escritorio.** Usaba `wp_tempnam()`, que vive en
  `wp-admin/includes/file.php` y no está cargado en cron ni en la web. Un correo que adjuntase la
  tarjeta desde cron moría con «Call to undefined function wp_tempnam()». Ahora usa
  `get_temp_dir()`, del core, que está siempre disponible.

### Pruebas
- `tests/Unit/TarjetaPdfTest.php` (6 casos) fija lo anterior: el navegador conserva su botón y su
  flexbox, el PDF no lleva botón, mide la tarjeta, no usa flexbox, quita el emoji del plan.
- El arnés de pruebas gana lo que faltaba para poder probar la tarjeta: el tema de documentos, la
  cabecera de marca y la sal persistente en el stub de `Utils`, las traducciones con plural, la
  fecha del post, `wp_parse_url` y las constantes de tiempo de WordPress.
- Se quita la deprecación de `ReflectionMethod::setAccessible()` en `CuotaPrimerAnoTest` (no hace
  nada desde PHP 8.1 y está deprecado desde 8.5).

## v2.8.16 (2026-09-26)

### Añadido — el panel de socio admite enlaces del sitio
- El panel pinta ahora, bajo las pestañas, los enlaces que cada sitio declare con el filtro
  **`convoca_mi_area_links`** (`array( 'url' => …, 'label' => …, 'icon' => … )`). El plugin no
  lleva a mano páginas de un sitio concreto —no existen en otro—, así que las aporta quien las
  tiene: en Ejemplo, /turnos/ y /mi-perfil/.
- Una entrada a la que le falte la URL o el texto no se pinta, y no rompe el panel.

### Corregido — el bootstrap de pruebas ya no daba por bueno cualquier filtro
- `apply_filters` devolvía el valor tal cual y `add_filter` no hacía nada, así que cualquier
  contrato basado en filtros pasaba sin comprobar nada. Mismo defecto que se corrigió en
  convoca-core y convoca-enroll. Añadidos además los stubs que faltaban para poder pintar el
  panel en una prueba (`esc_html_e`, `esc_attr_e`, `add_shortcode`).

### Pruebas
- `MiAreaLinksTest`: sin filtro no aparece la lista; con filtro aparecen los enlaces; una entrada
  incompleta se ignora. Members 144/144.

## v2.8.15 (2026-09-26)

### Corregido
- **La migración de plantillas vuelve a correr** para recuperar los enlaces al panel escritos a mano
  (`/mi-area/`): la versión de plantillas no había subido, así que `maybe_migrate()` salía por el
  atajo y el cambio no llegaba a los sitios. Subir esa constante es OBLIGATORIO al añadir una regla
  de migración; sin ello el arreglo se queda en el repositorio.


## v2.8.14 (2026-09-26)

### Corregido
- **`{login_url}` y el aviso de horas ya no apuntan a `/mi-area/` a mano.** El CTA «Acceder a Mi Área»
  de los correos, el enlace del pie y el texto del recordatorio de voluntariado llevaban la ruta
  escrita; en un sitio cuyo panel se llama `/panel-socio/` eso es un 404. Ahora salen de
  `Convoca\Core\Email_Links`, que resuelve la página real.
- El recordatorio de voluntariado guarda `href="{login_url}"` (se resuelve en cada envío) y la
  migración de plantillas recupera los enlaces al panel ya escritos a mano.


## v2.8.13 (2026-09-26)

### Corregido
- **Reparación de los botones ya guardados.** La migración de plantillas recupera los enlaces que
  `esc_url()` dejó destruidos (`http://link_pago` → `{link_pago}`, y lo mismo con `login_url`,
  `link_confirmacion`, `panel_reservas` y `certificado_url_verificacion`), en vez de taparlos de uno en
  uno. Antes solo estaban contemplados dos de los cinco casos.


## v2.8.12 (2026-09-26)

### Corregido
- **Dos correos que no salían nunca.** `confirm_email` (confirmar el cambio de email) y
  `verify_phone` (verificar el teléfono) se envían desde el plugin, pero no estaban en
  `Email_Manager::TEMPLATES`. Consecuencias encadenadas: el editor de plantillas del admin no las
  pintaba, su guardado **sobreescribe** la opción con las de la lista, y `send()` descarta en
  silencio una plantilla que no existe. Resultado: cambiar el email desde el perfil no llegaba a
  confirmarse nunca, porque el correo con el enlace no se mandaba.
  Ahora están en la lista y `maybe_migrate()` **repone** las plantillas de fábrica que falten sin
  tocar las que el sitio tenga (antes solo sustituía cadenas en las que ya estaban).
- Los botones de esos dos correos usan `Email_Layout::button_html()`, como el resto del ecosistema,
  en vez de estilos sueltos metidos a mano.


## v2.8.11 (2026-09-26)

### Corregido
- **Correo de bienvenida**: el asunto y la primera línea del cuerpo eran la misma frase (el nombre
  del sitio entraba en las dos), así que el correo empezaba repitiendo su propio asunto. El asunto
  pasa a presentar («¡Bienvenido/a, {nombre}! Ya formas parte de {sitio}») y el cuerpo saluda sin
  repetirlo. La migración de plantillas reescribe el saludo guardado **solo** si conserva el de
  fábrica; si el sitio lo personalizó, se respeta.
- **Objetivo de voluntariado completado**: no se envía si el miembro no tiene horas acreditadas.
  El correo felicitaba por «completar 0h» y ofrecía un certificado inexistente; queda registrado en
  el log en lugar de mandarse.

## v2.8.10 (2026-09-25)

### Cambiado
- **Regla única de voluntariado**: `Admin_Voluntariado::puede_acreditar_horas()` decide quién puede
  registrar horas (rol `voluntario_aprobado` o `_convoca_voluntario_aprobado = 1`). El compromiso del
  alta es una **solicitud**, no un permiso: antes el formulario prometía renovación sin cuota por
  marcarlo y el motor de horas exigía estar aprobado.
- La aprobación **sincroniza la solicitud en la ficha** del socio (el permiso sigue viviendo en el
  usuario, que es donde escribe la aprobación).
- Texto del formulario de alta: dice lo que el sistema hace — se solicita, la asociación aprueba y
  con las horas aprobadas se renueva sin cuota.

### Añadido
- 9 casos de regresión del permiso (socio normal, compromiso sin aprobar, pendiente, aprobado,
  revocado, sin cuenta, antes/después de aprobar, independencia de la cuota).


## v2.8.9 (2026-09-25)

### Corregido
- El aviso a la administración salía hacia `$system_email` (el propio remitente) y solo en las altas,
  así que **no llegaba a la asociación**. Sustituido por la copia real.

### Cambiado
- Todos los correos de socio (alta, bienvenida, credenciales, recordatorios de pago, renovación,
  certificado, tarjeta, voluntariado) se copian ahora al administrador con `Convoca\Core\Email_Copy`,
  por el **mismo canal** que el correo original (incluido el proveedor configurado).
- Ajustes → General: interruptor **«Enviar copia de los correos a la administración»** (marcado por
  defecto) junto al correo administrador, que ahora describe su uso real.


## v2.8.7 (2026-09-24)

### Cambiado
- El alta ya no admite el voluntariado como forma de no pagar: la cuota del primer año es obligatoria.
  `_convoca_es_voluntario` pasa a reflejar el compromiso (casilla del formulario), no la forma de pago.
- La renovación por horas es una vía alternativa al pago a partir del **segundo** ciclo; el mínimo es el `hours` del plan.
- Si no se alcanzan las horas, no se degrada al socio: se le exige la cuota y siguen los días de gracia.

### Corregido
- El formulario de alta no mostraba los métodos de pago (contenedor oculto sin lógica que lo mostrara): el alta no se podía completar.

### Pruebas
- `CuotaPrimerAnoTest`: primer ciclo sin vía de horas, segundo ciclo con vía de horas, plan sin horas, mínimo desde el plan y quién no se evalúa. PHPUnit 115.

## v2.8.5 (2026-09-23)

### 🐛 Correcciones
- Ajustes → Salud: la comprobación de la página de alta buscaba el shortcode `[convoca_alta]`, que no existe en el plugin, y avisaba de un fallo que el admin no podía arreglar creando la página por ese nombre. Ahora busca el real, `[convoca_alta_socio]`.

## v2.8.4 (2026-09-11)

### ✨ New features
- El pago recibe el correo del socio al crearse

### ⚙️ Changes
- Traducción completa del plugin y de las plantillas al inglés (en_US)

## v2.8.3 (2026-09-10)

### 🔧 Fixes
- La desinstalación ya no deja tablas, opciones ni tareas programadas huérfanas

### ⚙️ Changes
- «Mi área» solo carga sus recursos donde se muestra, mejorando el rendimiento del resto del sitio

## v2.8.2 (2026-09-10)

### ✨ New features
- Páginas de editor en el menú de administración y paginación en el listado de registros

## v2.8.1 (2026-09-10)

### ✨ New features
- Emails con CTA automático, emails del sitio y enlaces de plantillas

### 🔧 Fixes
- Corregido el bloque PRO de administración, que se mostraba como texto

## v2.8.0 (2026-09-10)

### ✨ New features
- Ciclo de vida de la membresía: periodo de gracia, renovación y re-alta
- Renovación automática con cargo real mediante token, con periodo de gracia y reintentos
- Gestión de voluntarios integrada en Members: nueva página de administración y migración de los datos de solicitud
- Carnet y certificado con tema claro/oscuro según una opción global, con selector en Ajustes
- Certificados con validez de 1 año y regeneración automática al aprobar horas de voluntariado
- Insignia de antigüedad en el carnet

### 🔧 Fixes
- El alta y la edición de socios desde el backend numeran y activan al socio; QR del carnet generado localmente
- El miembro se activa automáticamente tras el pago con Redsys
- Carnet servido como HTML para imprimir desde el navegador y certificado con layout de tablas compatible con el PDF
- Corregido el cacheo del estado del socio en las rutas /me
- Corregido el botón «Horas de voluntariado» (daba error 403) y la exportación a PDF de miembros (daba error 500)
- Unificada la sección de voluntarios, el aviso PRO y las plantillas de email

### 📦 Infrastructure
- Preparación para el directorio de WordPress.org y compatibilidad declarada hasta WordPress 7.1

## v2.7.2 (2026-09-05)

### 🔧 Fixes
- Endpoints REST protegidos y bloqueos en base de datos para evitar duplicados

## v2.7.1 (2026-09-05)

### 🔧 Fixes
- Los planes respetan el estado «activo» en el alta y el registro, y se valida la modalidad juvenil

## v2.7.0 (2026-08-07)

### ✨ New features
- **Renovación manual**: botón "Renovar membresía" en el panel (Pagos y Cuotas) + shortcode `[convoca_renovar]` + página `/renovar/`
- **Edición de perfil** desde el panel del socio: dirección, teléfono, email y cumpleaños
- **Verificación de email y teléfono** por enlace enviado al email (doble opt-in para email; enlace de confirmación para teléfono)
- **Proveedor de email pejemploable**: `Email_Verifier` (wp_mail por defecto, Mailgun opcional)

### ⚙️ Changes
- El envío de emails pasa por `Email_Verifier::send()` (provider configurable)
- `get_profile` expone `email_pendiente` y `telefono_verificado`

### 🔧 Fixes
- Corregido namespace REST del panel del socio (`convoca/v1` → `convoca-members/v1`) — el panel no funcionaba vía REST
- `MAX(member_number)` → `MAX(id)` en la secuencia de socios (error SQL en altas con pago)

---

## v2.6.2 (2026-06-24)

### 🐛 Fixes
- Corregidos status counts en listado de miembros (conteo correcto por estado)
- CSS de tabla de miembros mejorado para visualización consistente
- Fix en filas alternas (zebra striping) en listados

### ✨ Improvements
- Nuevas capabilities para gestión granular de permisos
- Mejoras en emails de notificación y plantillas

### 📦 Infrastructure
- Updated release ZIPs on getconvoca.app
- Demo environment synchronized

---

*Las entradas de las versiones v2.7.1 a v2.8.4 se reconstruyeron a partir del historial de git.*