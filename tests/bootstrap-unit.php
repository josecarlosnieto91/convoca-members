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
$core_bootstrap = dirname(__DIR__, 2) . '/convoca-core/tests/bootstrap-unit.php';
if (file_exists($core_bootstrap)) {
    require_once $core_bootstrap;
} else {
    // Fallback: stubs mínimos inline si el core no está clonado.
    require_once __DIR__ . '/stubs-wp.php';
}

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
if (!function_exists('register_block_type')) {
    function register_block_type($name, $args = []) {
        $GLOBALS['_cm_bloques'][$name] = $args;
        return true;
    }
}
if (!function_exists('wp_register_style')) {
    function wp_register_style($handle, $src = '', $deps = [], $ver = false) {
        $GLOBALS['_cm_estilos'][$handle] = compact('src', 'deps', 'ver');
        return true;
    }
}
