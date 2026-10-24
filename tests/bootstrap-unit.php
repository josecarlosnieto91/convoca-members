<?php
/**
 * Unit test bootstrap — standalone, no WordPress needed.
 *
 * Members depende de Convoca Core (Logger, Utils). Reutilizamos el
 * bootstrap-unit de Core (que mockea las funciones WP) y luego cargamos
 * los autoloaders de Core y Members para que las clases reales funcionen
 * sobre los mocks.
 */
define('ABSPATH', dirname(__DIR__) . '/');

// 0. Sin doble de Convoca\Core\Utils: el bootstrap del nucleo simula los bloqueos, asi
//    que la clase real funciona y no hay que mantener un doble que se queda atras.

// 1. Mocks de WordPress (stubs) del core — deben cargarse ANTES del autoloader.
/*
 * Los dobles de `register_block_type` tienen que ser UNO SOLO. El bootstrap de core se carga primero y
 * define el suyo escribiendo en `_wp_stores['blocks']`; el de members lo declara más abajo con
 * `function_exists()`, así que nunca entraba y los tests de bloques leían `_cm_bloques` vacío: 12 fallos
 * que en el CI no aparecían porque allí no hay clon de core al lado.
 *
 * Se declara aquí, antes del bootstrap de core, y escribe en los dos almacenes.
 */
if ( ! function_exists( 'register_block_type' ) ) {
    function register_block_type( $name, $args = array() ) {
        $GLOBALS['_wp_stores']['blocks'][ (string) $name ] = $args;
        $GLOBALS['_cm_bloques'][ (string) $name ]          = $args;
        return true;
    }
}

$core_bootstrap = dirname(__DIR__, 2) . '/convoca-core/tests/bootstrap-unit.php';
if (file_exists($core_bootstrap)) {
    require_once $core_bootstrap;
} else {
    // Fallback: stubs mínimos inline si el core no está clonado.
    require_once __DIR__ . '/stubs-wp.php';
}

// 1 bis. Autenticación de WordPress y captura de cookies: `Member_Auth` no se podía probar sin ellas
// (el bootstrap del núcleo no trae `wp_authenticate` ni deja ver una cookie). No va en el bootstrap de
// integración: allí las cookies son las de verdad.
require_once __DIR__ . '/stubs-auth.php';

// 1 ter. El `Logger` limita los avisos por proceso (50 en 60 s) y la suite entera comparte proceso: un fichero
// que registra mucho agota la cuota y las pruebas siguientes dejan de ver **sus** avisos (una de ellas, la del
// inicio de sesión, comprueba que el aviso existe). Se sube el tope para medir; el límite de producción sigue en
// 50 y el filtro es la vía prevista para cambiarlo.
add_filter(
	'convoca_logger_rate_limit_max',
	static function () {
		return PHP_INT_MAX;
	}
);

// 2. Autoloader propio (las clases del core ya quedaron registradas por el
//    require del bootstrap de core, pero cargamos también el nuestro para
//    resolver las clases de members).
if (file_exists(dirname(__DIR__) . '/vendor/autoload.php')) {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
}

/*
 * Idioma. El código real usa determine_locale()/switch_to_locale()/restore_previous_locale() para
 * poner las plantillas de correo en el idioma del sitio (members#60), y el bootstrap del núcleo no
 * los define: sin estos dobles, PHPUnit se cae con «Call to undefined function».
 */
if (!function_exists('determine_locale')) {
    function determine_locale() {
        return $GLOBALS['_wp_stores']['locale'] ?? 'es_ES';
    }
}
if (!function_exists('switch_to_locale')) {
    function switch_to_locale($locale) {
        $GLOBALS['_wp_stores']['locale_prev'] = determine_locale();
        $GLOBALS['_wp_stores']['locale'] = $locale;
        return true;
    }
}
if (!function_exists('restore_previous_locale')) {
    function restore_previous_locale() {
        if (isset($GLOBALS['_wp_stores']['locale_prev'])) {
            $GLOBALS['_wp_stores']['locale'] = $GLOBALS['_wp_stores']['locale_prev'];
        }
        return true;
    }
}

/*
 * Bloques de Gutenberg: el bootstrap del núcleo no trae doble de register_block_type ni de
 * wp_register_style, y sin ellos el registro de bloques de members no se puede ni mirar.
 */
// (el doble de `register_block_type` vive arriba, antes del bootstrap de core: uno solo)
if (!function_exists('wp_register_style')) {
    function wp_register_style($handle, $src = '', $deps = [], $ver = false) {
        $GLOBALS['_cm_estilos'][$handle] = compact('src', 'deps', 'ver');
        return true;
    }
}
