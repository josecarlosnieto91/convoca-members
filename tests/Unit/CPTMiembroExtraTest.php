<?php
/**
 * Unit tests for CPT_Miembro — plans and member number logic.
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

class CPTMiembroExtraTest extends TestCase
{
    private function loadClass(): void
    {
        $path = dirname(__DIR__, 2) . '/includes/CPT_Miembro.php';
        if (file_exists($path)) {
            require_once $path;
        }
    }

    protected function setUp(): void
    {
        $this->loadClass();
    }

    // ── get_plans ──────────────────────────────────

    public function test_get_plans_returns_array(): void
    {
        $plans = \Convoca\Members\CPT_Miembro::get_plans();
        $this->assertIsArray($plans);
        $this->assertNotEmpty($plans);
    }

    public function test_get_plans_has_expected_structure(): void
    {
        $plans = \Convoca\Members\CPT_Miembro::get_plans();
        foreach ($plans as $key => $plan) {
            $this->assertIsString($key);
            $this->assertIsArray($plan);
            $this->assertArrayHasKey('label', $plan);
            $this->assertArrayHasKey('price', $plan);
        }
    }

    // ── get_plan ───────────────────────────────────

    public function test_get_plan_valid_key(): void
    {
        $plans = \Convoca\Members\CPT_Miembro::get_plans();
        $firstKey = array_key_first($plans);
        $plan = \Convoca\Members\CPT_Miembro::get_plan($firstKey);
        $this->assertIsArray($plan);
        $this->assertArrayHasKey('label', $plan);
    }

    public function test_get_plan_invalid_key_returns_null(): void
    {
        $plan = \Convoca\Members\CPT_Miembro::get_plan('nonexistent_plan_key_xyz');
        $this->assertNull($plan);
    }

    public function test_get_plan_empty_string(): void
    {
        $plan = \Convoca\Members\CPT_Miembro::get_plan('');
        $this->assertNull($plan);
    }

    // ── get_next_member_number (WP-only, skipped) ──
    // These require Convoca\Core\Logger which needs WordPress.
    // Tested in integration test suite (CI).

    // ── Structure ──────────────────────────────────

    public function test_has_register_method(): void
    {
        $this->assertTrue(method_exists('Convoca\Members\CPT_Miembro', 'register'));
    }

    public function test_has_approve_member_method(): void
    {
        $this->assertTrue(method_exists('Convoca\Members\CPT_Miembro', 'approve_member'));
    }

    public function test_has_whatsapp_link_method(): void
    {
        $this->assertTrue(method_exists('Convoca\Members\CPT_Miembro', 'whatsapp_link'));
    }

    public function test_has_check_member_status_method(): void
    {
        $this->assertTrue(method_exists('Convoca\Members\CPT_Miembro', 'check_member_status'));
    }
}
