<?php
/**
 * Bootstrap for convoca-members unit tests.
 * Provides comprehensive WordPress function stubs plus Convoca Core stubs.
 * All namespace declarations must come first.
 */

namespace Convoca\Core {

    if (!class_exists('Utils')) {
        class Utils {
            public static $actions_fired = [];

            public static function do_action(string $native_hook, string $backcompat_hook, ...$args): void {
                self::$actions_fired[] = ['hook' => $native_hook, 'callback' => $backcompat_hook, 'args' => $args];
                if (\function_exists('\\do_action')) {
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

            public static function clear_fired(): void { self::$actions_fired = []; }
        }
    }

    if (!class_exists('Logger')) {
        class Logger {
            public static $logs = [];
            public static function info(string $msg, string $context = '', int $oid = 0): void { self::$logs[] = ['level' => 'info', 'msg' => $msg]; }
            public static function warning(string $msg, string $context = '', int $oid = 0): void { self::$logs[] = ['level' => 'warning', 'msg' => $msg]; }
            public static function error(string $msg, string $context = '', int $oid = 0): void { self::$logs[] = ['level' => 'error', 'msg' => $msg]; }
            public static function clear(): void { self::$logs = []; }
        }
    }

    if (!class_exists('Installer')) {
        class Installer {
            public static function db_init(): void {}
        }
    }
}

namespace {

    \define('WP_DEBUG', true);
    \define('ABSPATH', \dirname(__DIR__) . '/');
    \define('OBJECT', 'OBJECT');

    // ─── Shared stores for WP option/meta stubs ─────────────

    $GLOBALS['_wp_stores'] = [
        'options'     => [],
        'post_meta'   => [],
        'transients'  => [],
        'user_meta'   => [],
    ];

    if (!\function_exists('get_option')) {
        function get_option($key, $default = false) {
            $s = &$GLOBALS['_wp_stores']['options'];
            return \array_key_exists($key, $s) ? $s[$key] : $default;
        }
        function update_option($key, $value, $autoload = null) {
            $GLOBALS['_wp_stores']['options'][$key] = $value;
            return true;
        }
        function delete_option($key) {
            unset($GLOBALS['_wp_stores']['options'][$key]);
            return true;
        }
    }

    if (!\function_exists('get_post_meta')) {
        function get_post_meta($id, $key, $single = false) {
            $s = &$GLOBALS['_wp_stores']['post_meta'];
            $v = $s[$id][$key] ?? null;
            if ($v === null) return $single ? '' : [];
            if ($single) return $v;
            return \is_array($v) ? $v : [$v];
        }
        function update_post_meta($id, $key, $value) { $GLOBALS['_wp_stores']['post_meta'][$id][$key] = $value; return true; }
        function delete_post_meta($id, $key) { unset($GLOBALS['_wp_stores']['post_meta'][$id][$key]); return true; }
    }

    if (!\function_exists('get_transient')) {
        function get_transient($key) { return $GLOBALS['_wp_stores']['transients'][$key] ?? false; }
        function set_transient($key, $value, $exp = 0) { $GLOBALS['_wp_stores']['transients'][$key] = $value; return true; }
        function delete_transient($key) { unset($GLOBALS['_wp_stores']['transients'][$key]); return true; }
    }

    if (!\function_exists('get_userdata')) {
        function get_userdata($id) {
            if ($id <= 0) return false;
            $u = new \stdClass();
            $u->ID = $id; $u->display_name = "User $id"; $u->first_name = "First$id";
            $u->user_email = "user$id@example.com"; $u->roles = ['voluntario_aprobado'];
            return $u;
        }
    }

    if (!\function_exists('get_user_meta')) {
        function get_user_meta($id, $key, $single = false) {
            $s = $GLOBALS['_wp_stores']['user_meta'];
            $v = $s[$id][$key] ?? '';
            return $single ? $v : ($v !== '' ? [$v] : []);
        }
        function update_user_meta($id, $key, $value) { $GLOBALS['_wp_stores']['user_meta'][$id][$key] = $value; return true; }
    }

    if (!\function_exists('current_time')) {
        function current_time($type = 'mysql') {
            if ($type === 'mysql') return \date('Y-m-d H:i:s');
            if ($type === 'Y-m-d') return \date('Y-m-d');
            if ($type === 'timestamp') return \time();
            return \date($type);
        }
    }

    if (!\function_exists('wp_date')) {
        function wp_date($format, $ts = null) { return \date($format, $ts ?? \time()); }
    }

    if (!\function_exists('get_the_title')) {
        function get_the_title($id) { return "Title #$id"; }
    }

    if (!\function_exists('get_post')) {
        function get_post($id = null) {
            if ($id === null) return null;
            if (isset($GLOBALS['_test_posts'][$id])) return $GLOBALS['_test_posts'][$id];
            $p = new \stdClass();
            $p->ID = $id; $p->post_title = "Post $id"; $p->post_type = 'post';
            $p->post_status = 'publish'; $p->post_date = '2026-06-01 10:00:00';
            return $p;
        }
    }

    if (!\function_exists('get_post_status')) {
        function get_post_status($id) { return 'publish'; }
    }

    if (!\function_exists('wp_insert_post')) {
        function wp_insert_post($data) {
            static $c = 100; $c++;
            update_post_meta($c, '_wp_insert_data', $data);
            return $c;
        }
    }

    if (!\function_exists('wp_update_post')) {
        function wp_update_post($data) {
            if (isset($data['ID'])) { update_post_meta($data['ID'], '_wp_update_data', $data); return $data['ID']; }
            return wp_insert_post($data);
        }
    }

    if (!\function_exists('current_user_can')) {
        function current_user_can($cap, ...$a) { return true; }
    }

    if (!\function_exists('get_current_user_id')) {
        function get_current_user_id() { return 1; }
    }

    if (!\function_exists('wp_verify_nonce')) {
        function wp_verify_nonce($n, $a) { return true; }
    }

    if (!\function_exists('__')) { function __($s, $d = 'default') { return $s; } }
    if (!\function_exists('_x')) { function _x($s, $c, $d = 'default') { return $s; } }
    if (!\function_exists('esc_html__')) { function esc_html__($s, $d = 'default') { return $s; } }
    if (!\function_exists('esc_attr__')) { function esc_attr__($s, $d = 'default') { return $s; } }
    if (!\function_exists('esc_html')) { function esc_html($s) { return \htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
    if (!\function_exists('esc_attr')) { function esc_attr($s) { return \htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
    if (!\function_exists('esc_url')) { function esc_url($s) { return $s; } }
    if (!\function_exists('sanitize_text_field')) { function sanitize_text_field($s) { return \is_string($s) ? \trim($s) : ''; } }
    if (!\function_exists('sanitize_textarea_field')) { function sanitize_textarea_field($s) { return \is_string($s) ? \trim($s) : ''; } }
    if (!\function_exists('absint')) { function absint($v) { return \abs((int)$v); } }
    if (!\function_exists('wp_unslash')) { function wp_unslash($s) { return \is_string($s) ? \stripslashes($s) : $s; } }
    if (!\function_exists('wp_kses_post')) { function wp_kses_post($s) { return $s; } }
    if (!\function_exists('home_url')) { function home_url($p = '') { return "https://example.com$p"; } }
    if (!\function_exists('admin_url')) { function admin_url($p = '') { return "/wp-admin/$p"; } }
    if (!\function_exists('wp_next_scheduled')) { function wp_next_scheduled($h) { return false; } }
    if (!\function_exists('wp_schedule_event')) { function wp_schedule_event($ts, $r, $h, $a = []) { return true; } }

    if (!\function_exists('do_action')) {
        function do_action($hook, ...$args) {
            \Convoca\Core\Utils::$actions_fired[] = ['hook' => $hook, 'args' => $args];
        }
    }

    if (!\function_exists('add_action')) { function add_action($h, $c, $p = 10, $a = 1) { return true; } }
    if (!\function_exists('add_filter')) { function add_filter($h, $c, $p = 10, $a = 1) { return true; } }
    if (!\function_exists('apply_filters')) { function apply_filters($h, $v, ...$a) { return $v; } }
    if (!\function_exists('register_post_type')) { function register_post_type($s, $a) { return null; } }
    if (!\function_exists('register_post_meta')) { function register_post_meta($t, $k, $a) { return true; } }
    if (!\function_exists('register_taxonomy')) { function register_taxonomy($s, $t, $a) { return null; } }
    if (!\function_exists('register_rest_route')) { function register_rest_route($n, $r, $a) { return true; } }
    if (!\function_exists('register_activation_hook')) { function register_activation_hook($f, $c) { return true; } }
    if (!\function_exists('flush_rewrite_rules')) { function flush_rewrite_rules() {} }
    if (!\function_exists('wp_redirect')) { function wp_redirect($u) {} }
    if (!\function_exists('wp_die')) { function wp_die($m = '', $t = '', $a = []) {} }
    if (!\function_exists('wp_cache_delete')) { function wp_cache_delete($k, $g = '') { return true; } }

    if (!\function_exists('wp_get_post_terms')) {
        function wp_get_post_terms($id, $tax) {
            if ($tax === 'convoca_shifts_actividad') return [ (object)['name' => 'Actividad Test', 'term_id' => 1] ];
            return [];
        }
    }

    if (!\function_exists('post_type_exists')) {
        function post_type_exists($t) { return \in_array($t, ['registro_hora', 'miembro', 'post', 'page', 'centro_turno', 'proyecto'], true); }
    }

    if (!\function_exists('get_posts')) {
        function get_posts($args) {
            if (isset($args['meta_value']) && $args['meta_value'] === 'exists@example.com') return [ (object)['ID' => 42] ];
            return [];
        }
    }

    if (!\function_exists('register_shutdown_function')) { function register_shutdown_function($c) {} }
    if (!\function_exists('deactivate_plugins')) { function deactivate_plugins($p, $s = false) {} }
    if (!\function_exists('plugin_basename')) { function plugin_basename($f) { return \basename($f); } }
    if (!\function_exists('plugin_dir_path')) { function plugin_dir_path($f) { return \dirname($f) . '/'; } }
    if (!\function_exists('plugin_dir_url')) { function plugin_dir_url($f) { return 'https://example.com/wp-content/plugins/' . \basename(\dirname($f)) . '/'; } }
    if (!\function_exists('get_current_screen')) { function get_current_screen() { return null; } }
    if (!\function_exists('wp_enqueue_style')) { function wp_enqueue_style($h, $s = '', $d = [], $v = '', $m = 'all') {} }
    if (!\function_exists('wp_register_style')) { function wp_register_style($h, $s, $d = [], $v = '', $m = 'all') { return true; } }
    if (!\function_exists('wp_set_script_translations')) { function wp_set_script_translations($h, $d, $p) {} }
    if (!\function_exists('load_plugin_textdomain')) { function load_plugin_textdomain($d, $dep, $p) {} }
    if (!\function_exists('remove_action')) { function remove_action($h, $c, $p = 10) { return true; } }
    if (!\function_exists('get_user_by')) { function get_user_by($field, $value) { return false; } }
    if (!\function_exists('delete_post_meta_by_key')) { function delete_post_meta_by_key($key) { return true; } }
    if (!\function_exists('get_users')) { function get_users($args = []) { return []; } }
    if (!\function_exists('wp_list_pluck')) { function wp_list_pluck($list, $field) { $r = []; foreach ($list as $item) { if (\is_object($item) && isset($item->$field)) $r[] = $item->$field; } return $r; } }

    // ─── WP_Error class ───────────────────────────────────

    if (!\class_exists('WP_Error')) {
        class WP_Error {
            private $errors = []; private $error_data = [];
            public function __construct($code = '', $message = '', $data = '') {
                if ($code) { $this->errors[$code] = [$message]; $this->error_data[$code] = $data; }
            }
            public function get_error_code() { return \key($this->errors); }
            public function get_error_message($code = '') {
                if (!$code) $code = $this->get_error_code();
                return $this->errors[$code][0] ?? '';
            }
        }
    }

    // ─── WP_REST_Response class ──────────────────────────

    if (!\class_exists('WP_REST_Response')) {
        class WP_REST_Response {
            private $data; private $status;
            public function __construct($data = null, $status = 200) { $this->data = $data; $this->status = $status; }
            public function get_data() { return $this->data; }
            public function get_status() { return $this->status; }
        }
    }

    if (!\class_exists('WP_REST_Request')) {
        class WP_REST_Request {
            private $params = [];
            public function __construct($m = 'GET', $r = '') {}
            public function get_param($k) { return $this->params[$k] ?? null; }
            public function set_param($k, $v) { $this->params[$k] = $v; }
        }
    }

    // ─── $wpdb global ────────────────────────────────────

    if (!isset($GLOBALS['wpdb'])) {
        $GLOBALS['wpdb'] = new class {
            public $prefix = 'wp_';
            public $posts = 'wp_posts';
            public $postmeta = 'wp_postmeta';
            public $options = 'wp_options';
            public $usermeta = 'wp_usermeta';
            public $insert_id = 42;

            public function get_var($q = null, $x = 0, $y = 0) {
                $qs = (string)$q;
                if (\strpos($qs, 'SHOW TABLES') !== false) return 'wp_convoca_member_sequence';
                if (\strpos($qs, 'SELECT MAX(member_number)') !== false) return '0';
                if (\strpos($qs, 'SELECT CAST(option_value AS UNSIGNED)') !== false) return '42';
                if (\strpos($qs, 'SELECT meta_value') !== false) return '0';
                if (\strpos($qs, 'SELECT ID FROM') !== false) return '1';
                return '10';
            }

            public function get_results($q = null, $o = 'OBJECT') { return []; }

            public function query($q) {
                $qs = (string)$q;
                // Emular el UPDATE atómico de postmeta que hace Hours_Manager::process_approval,
                // aplicando el cambio al almacén en memoria para que get_post_meta lo refleje.
                if (preg_match('/UPDATE\s+.*?postmeta\s+SET\s+meta_value\s*=\s*([^\s]+).*?post_id\s*=\s*(\d+).*?meta_key\s*=\s*\'([^\']+)\'/is', $qs, $m)) {
                    $GLOBALS['_wp_stores']['post_meta'][(int)$m[2]][$m[3]] = $m[1];
                    return 1;
                }
                return 1;
            }

            public function insert($t, $d, $f = []) { $this->insert_id = 42; return 1; }

            public function prepare($q, ...$args) {
                if (empty($args)) return $q;
                $sql = $q;
                foreach ($args as $arg) {
                    $p = \strpos($sql, '%');
                    if ($p !== false) $sql = \substr_replace($sql, (string)$arg, $p, 2);
                }
                return $sql;
            }

            public function get_charset_collate() { return 'DEFAULT CHARSET=utf8mb4'; }
        };
    }

    // Load Composer autoload
    $autoload = \dirname(__DIR__) . '/vendor/autoload.php';
    if (\file_exists($autoload)) {
        require_once $autoload;
    }
}
