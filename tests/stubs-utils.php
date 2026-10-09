<?php
/**
 * Stub de Convoca\Core\Utils para los tests unitarios de Members.
 *
 * El acquire_lock real adquiere el lock en base de datos (tabla de locks o
 * wp_options) y con el $wpdb simulado siempre falla, así que Estados::change()
 * devolvía 'concurrent_change' en vez de la transición esperada.
 *
 * Aquí el lock va por transients —el store que los tests limpian en su setUp—
 * igual que hace tests/bootstrap.php. Se carga ANTES que el bootstrap de core:
 * al existir la clase, el autoloader ya no carga la real (Members no prueba
 * Utils; eso es de convoca-core).
 */

namespace Convoca\Core;

if (!class_exists('\Convoca\Core\Utils')) {
    class Utils {
        public static $actions_fired = [];

        public static function do_action(string $native_hook, string $backcompat_hook, ...$args): void {
            self::$actions_fired[] = ['hook' => $native_hook, 'callback' => $backcompat_hook, 'args' => $args];
            if (\function_exists('do_action')) {
                \do_action($native_hook, ...$args);
            }
        }

        public static function format_date(string $modify, string $format = 'Y-m-d'): string {
            return \gmdate($format, \strtotime($modify));
        }

        public static function acquire_lock(string $key, int $ttl = 60): bool {
            if (\function_exists('get_transient') && false !== \get_transient($key)) {
                return false;
            }
            if (\function_exists('set_transient')) {
                \set_transient($key, 1, $ttl);
            }
            return true;
        }

        public static function release_lock(string $key): bool {
            if (\function_exists('delete_transient')) {
                \delete_transient($key);
            }
            return true;
        }

        /** Copiado de Convoca\Core\Utils: el doble debe ofrecer lo mismo que el original. */
        public static function get_logo_url( string $filter_suffix = 'common' ): string {
            $logo_id  = get_theme_mod( 'custom_logo' );
            $logo_url = '';
            if ( $logo_id ) {
                $src = wp_get_attachment_image_src( $logo_id, 'full' );
                if ( $src ) { $logo_url = $src[0]; }
            }
            return (string) apply_filters( "convoca_{$filter_suffix}_logo_url", $logo_url );
        }

        /** Copiado de Convoca\Core\Utils::get_branding_html(). */
        public static function get_branding_html( string $filter_suffix = 'common', string $css_class = '', string $style = 'color:#ffffff;margin:0;font-size:24px;' ): string {
            $logo_url  = self::get_logo_url( $filter_suffix );
            $site_name = get_bloginfo( 'name' );
            if ( ! empty( $logo_url ) ) {
                $class_attr = $css_class ? ' class="' . esc_attr( $css_class ) . '"' : '';
                $style_attr = $style ? ' style="' . esc_attr( $style ) . '"' : '';
                return '<img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( $site_name ) . '"' . $class_attr . $style_attr . '>';
            }
            return '<h1 style="' . esc_attr( $style ) . '">' . esc_html( $site_name ) . '</h1>';
        }

        public static function clear_fired(): void {
            self::$actions_fired = [];
        }
    }
}
