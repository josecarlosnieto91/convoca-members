<?php
/**
 * Real behavioral tests for CPT_Miembro — plans, whatsapp_link, structure.
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;
use Convoca\Members\CPT_Miembro;

class CPTMiembroExtraTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \Convoca\Core\Logger::clear();
        \Convoca\Core\Utils::clear_fired();
        $GLOBALS['_wp_stores']['post_meta'] = [];
        $GLOBALS['_wp_stores']['transients'] = [];
        $GLOBALS['_wp_stores']['options'] = [];
        $path = dirname(__DIR__, 2) . '/includes/CPT_Miembro.php';
        if (file_exists($path)) {
            require_once $path;
        }
    }

    // ── get_plans ──────────────────────────────────────

    public function test_get_plans_returns_array(): void
    {
        $plans = CPT_Miembro::get_plans();
        $this->assertIsArray($plans);
        $this->assertNotEmpty($plans);
    }

    public function test_get_plans_has_expected_keys(): void
    {
        $plans = CPT_Miembro::get_plans();
        foreach ($plans as $key => $plan) {
            $this->assertArrayHasKey('label', $plan, "Plan $key missing label");
            $this->assertArrayHasKey('price', $plan, "Plan $key missing price");
            $this->assertArrayHasKey('hours', $plan, "Plan $key missing hours");
            $this->assertArrayHasKey('payment_methods', $plan, "Plan $key missing payment_methods");
        }
    }

    public function test_get_plans_normalizes_missing_fields(): void
    {
        // Simulate DB plans with missing fields
        update_option('convoca_members_plans', [
            'busgosu' => ['label' => 'Custom'],
        ]);

        $plans = CPT_Miembro::get_plans();
        $this->assertEquals('Custom', $plans['busgosu']['label']);
        $this->assertNotEmpty($plans['busgosu']['payment_methods']);
        $this->assertIsFloat($plans['busgosu']['price'] * 1.0);
    }

    // ── get_plan ───────────────────────────────────────

    public function test_get_plan_valid_key(): void
    {
        $plan = CPT_Miembro::get_plan('busgosu');
        $this->assertIsArray($plan);
        $this->assertEquals('🍁 Busgosu', $plan['label']);
        $this->assertEquals(30, $plan['price']);
    }

    public function test_get_plan_invalid_key_returns_null(): void
    {
        $this->assertNull(CPT_Miembro::get_plan('nonexistent_plan'));
    }

    public function test_get_plan_empty_string(): void
    {
        $this->assertNull(CPT_Miembro::get_plan(''));
    }

    // ── whatsapp_link ────────────────────────────────────

    public function test_whatsapp_link_no_phone_returns_null(): void
    {
        $result = CPT_Miembro::whatsapp_link(42);
        $this->assertNull($result);
    }

    public function test_whatsapp_link_whatsapp_disabled_returns_null(): void
    {
        update_post_meta(42, '_convoca_telefono', '612345678');
        update_post_meta(42, '_convoca_whatsapp', 'no');
        $this->assertNull(CPT_Miembro::whatsapp_link(42));
    }

    public function test_whatsapp_link_basic(): void
    {
        update_post_meta(42, '_convoca_telefono', '612345678');
        update_post_meta(42, '_convoca_whatsapp', 'si');
        $url = CPT_Miembro::whatsapp_link(42);
        $this->assertNotNull($url);
        $this->assertStringStartsWith('https://wa.me/', $url);
        // Should add 34 prefix
        $this->assertStringContainsString('34612345678', $url);
    }

    public function test_whatsapp_link_with_country_code(): void
    {
        update_post_meta(42, '_convoca_telefono', '34612345678');
        update_post_meta(42, '_convoca_whatsapp', 'si');
        $url = CPT_Miembro::whatsapp_link(42);
        $this->assertNotNull($url);
        $this->assertStringContainsString('34612345678', $url);
    }

    public function test_whatsapp_link_with_plus(): void
    {
        update_post_meta(42, '_convoca_telefono', '+34612345678');
        update_post_meta(42, '_convoca_whatsapp', 'si');
        $url = CPT_Miembro::whatsapp_link(42);
        $this->assertNotNull($url);
        $this->assertStringContainsString('34612345678', $url);
    }

    public function test_whatsapp_link_with_message(): void
    {
        update_post_meta(42, '_convoca_telefono', '612345678');
        update_post_meta(42, '_convoca_whatsapp', 'si');
        $url = CPT_Miembro::whatsapp_link(42, 'Hola {nombre}, bienvenido!');
        $this->assertStringContainsString('?text=', $url);
        $this->assertStringContainsString(rawurlencode('Hola'), $url);
    }

    public function test_whatsapp_link_normalizes_phone(): void
    {
        update_post_meta(42, '_convoca_telefono', '612-345-678');
        update_post_meta(42, '_convoca_whatsapp', 'si');
        $url = CPT_Miembro::whatsapp_link(42);
        $this->assertStringContainsString('34612345678', $url);
    }

    // ── Structure checks ─────────────────────────────────

    public function test_has_register_method(): void
    {
        $this->assertTrue(method_exists(CPT_Miembro::class, 'register'));
    }

    public function test_has_approve_member_method(): void
    {
        $this->assertTrue(method_exists(CPT_Miembro::class, 'approve_member'));
    }

    public function test_meta_keys_defined(): void
    {
        $this->assertIsArray(CPT_Miembro::META_KEYS);
        $this->assertContains('email', CPT_Miembro::META_KEYS);
        $this->assertContains('dni', CPT_Miembro::META_KEYS);
        $this->assertContains('plan', CPT_Miembro::META_KEYS);
    }
}
