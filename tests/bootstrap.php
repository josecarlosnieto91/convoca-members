<?php
define('WP_DEBUG', true);
define('ABSPATH', dirname(__DIR__) . '/');

// WordPress function stubs for unit tests
if (!function_exists('get_option')) {
    function get_option($key, $default = false) { return $default; }
    function update_option($key, $value, $autoload = null) { return true; }
    function __($s, $domain) { return $s; }
    function esc_html($s) { return $s; }
    function esc_attr($s) { return $s; }
    function esc_url($s) { return $s; }
    function home_url($path = '') { return "https://example.com$path"; }
    function admin_url($path = '') { return "/wp-admin/$path"; }
    function wp_next_scheduled($hook) { return false; }
    function wp_schedule_event($ts, $recurrence, $hook, $args = []) { return true; }
}
// Mock $wpdb global
if (!isset($GLOBALS['wpdb'])) {
    $GLOBALS['wpdb'] = new class {
        public $prefix = 'wp_';
        public function get_var($q) { return '10'; }
        public function get_results($q) { return []; }
        public function query($q) { return true; }
        public function prepare($q, ...$args) { return $q; }
    };
}



if (file_exists(dirname(__DIR__) . '/vendor/autoload.php')) {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
}
