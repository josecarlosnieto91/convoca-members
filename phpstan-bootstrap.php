<?php
/**
 * PHPStan bootstrap: constantes WP y de plugin de runtime (las define wp-load o
 * el propio plugin en producción) que no están disponibles en el harness de CI.
 *
 * ABSPATH apunta a ./phpstan-stubs/wp/ para que los require_once de ficheros de
 * wp-admin (class-wp-list-table.php, upgrade.php) resuelvan a stubs vacíos
 * commitetados — en producción esos ficheros existen de verdad, aquí solo hace
 * falta que la ruta exista para que PHPStan no reporte fileNotFound.
 */
define( 'ABSPATH', __DIR__ . '/phpstan-stubs/wp/' );
define( 'WP_PLUGIN_DIR', ABSPATH . 'wp-content/plugins' );
define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'MONTH_IN_SECONDS', 30 * DAY_IN_SECONDS );
define( 'WEEK_IN_SECONDS', 7 * DAY_IN_SECONDS );
define( 'YEAR_IN_SECONDS', 365 * DAY_IN_SECONDS );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'OBJECT', 'OBJECT' );
define( 'OBJECT_K', 'OBJECT_K' );
define( 'WP_DEBUG', true );
define( 'COOKIEPATH', '/' );
define( 'COOKIE_DOMAIN', '' );

// Constantes definidas por convoca-core en runtime.
define( 'CONVOCA_COMMON_URL', 'https://example.com' );
define( 'CONVOCA_COMMON_VERSION', '1.0.0' );
define( 'CONVOCA_IMAGES_URL', 'https://example.com/images' );

// Constantes de convoca-members definidas en convoca-members.php; se redeclaran
// aquí porque PHPStan no las propaga de forma fiable a los demás ficheros.
define( 'CONVOCA_MEMBERS_DIR', '/tmp/convoca-members/' );
define( 'CONVOCA_MEMBERS_URL', 'https://example.com/convoca-members/' );
