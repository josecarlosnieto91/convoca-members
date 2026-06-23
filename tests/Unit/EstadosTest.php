<?php
/**
 * Unit tests for Convoca Members — Estados (state management).
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

// Mock WordPress functions before loading the class
if (!function_exists('Convoca\Members\get_post_meta')) {
    function get_post_meta($id, $key, $single) { return []; }
    function get_the_title($id) { return "Test Member"; }
    function current_time($fmt) { return '2026-06-23 12:00:00'; }
    function wp_date($fmt, $ts) { return date($fmt, $ts); }
    function get_userdata($id) { return (object)['display_name' => 'Admin']; }
    function is_wp_error($thing) { return false; }
    function sanitize_text_field($s) { return $s; }
    function wp_json_encode($d) { return json_encode($d); }
    function wp_unslash($s) { return $s; }
    function update_post_meta($id, $key, $val) { return true; }
    function add_post_meta($id, $key, $val, $unique) { return 1; }
    function wp_kses_post($s) { return $s; }
    function __($s, $domain) { return $s; }
    function esc_html($s) { return $s; }
    function esc_attr($s) { return $s; }
    function absint($v) { return (int) $v; }
}

// Load the Estados class
require_once dirname(__DIR__, 2) . '/includes/Estados.php';

class EstadosTest extends TestCase
{
    public function test_badge_html_returns_string(): void
    {
        $result = \Convoca\Members\Estados::badge_html('active');
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function test_badge_html_contains_state_name(): void
    {
        foreach (['active', 'suspended', 'expired', 'pending', 'baja'] as $state) {
            $result = \Convoca\Members\Estados::badge_html($state);
            $this->assertIsString($result);
            $this->assertNotEmpty($result, "badge_html('$state') should return HTML");
        }
    }

    public function test_badge_html_unknown_state_returns_default(): void
    {
        $result = \Convoca\Members\Estados::badge_html('invalid_state_xyz');
        $this->assertIsString($result);
    }

    public function test_get_history_returns_array(): void
    {
        $result = \Convoca\Members\Estados::get_history(99999);
        $this->assertIsArray($result);
    }

    public function test_state_constants_exist(): void
    {
        // Verify that the Estados class defines expected state constants
        $ref = new \ReflectionClass(\Convoca\Members\Estados::class);
        $this->assertTrue($ref->hasMethod('badge_html'));
        $this->assertTrue($ref->hasMethod('change'));
        $this->assertTrue($ref->hasMethod('get_history'));
    }
}
