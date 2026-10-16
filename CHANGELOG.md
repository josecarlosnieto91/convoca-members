## [2.8.102] — 2026-10-16

### Arreglado
- **Entrar por el panel abre también la sesión de WordPress (shifts#19).** Medido en la misma página con
  cuatro estados: un socio con su **sesión de panel** abierta leía «Si eres voluntario/a, inicia sesión para
  apuntarte», porque `Member_Auth::login()` crea su propia sesión (`convoca_member_session`) y **no abría la de
  WordPress**: para WordPress seguía siendo un visitante anónimo y todos los shortcodes de turnos le contestaban
  que entrara. Tenía que descubrir un **segundo formulario de acceso**, con las mismas credenciales y sin que
  nadie se lo dijera.
  Ahora el login del panel establece también la sesión de WordPress del usuario vinculado a la ficha
  (`wp_set_auth_cookie()`), así que una sola entrada sirve para todo: panel, turnos e inscripciones.

