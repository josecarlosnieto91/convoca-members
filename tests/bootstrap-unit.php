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
