<?php
/**
 * Unit tests for Convoca Members — CPT_Miembro structure.
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

class CPTMiembroTest extends TestCase
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

    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists('Convoca\Members\CPT_Miembro'));
    }

    public function test_has_register_method(): void
    {
        $this->assertTrue(method_exists('Convoca\Members\CPT_Miembro', 'register'));
    }

    public function test_has_get_next_member_number_method(): void
    {
        $this->assertTrue(method_exists('Convoca\Members\CPT_Miembro', 'get_next_member_number'));
    }

    public function test_has_approve_member_method(): void
    {
        $this->assertTrue(method_exists('Convoca\Members\CPT_Miembro', 'approve_member'));
    }

    public function test_has_check_member_status_method(): void
    {
        $this->assertTrue(method_exists('Convoca\Members\CPT_Miembro', 'check_member_status'));
    }

    public function test_has_get_plans_method(): void
    {
        $this->assertTrue(method_exists('Convoca\Members\CPT_Miembro', 'get_plans'));
    }

    public function test_has_whatsapp_link_method(): void
    {
        $this->assertTrue(method_exists('Convoca\Members\CPT_Miembro', 'whatsapp_link'));
    }

    public function test_has_get_plan_method(): void
    {
        $this->assertTrue(method_exists('Convoca\Members\CPT_Miembro', 'get_plan'));
    }

    /**
     * La política de gracia con reintentos (2026-09) se implementa en
     * check_member_status: consulta pago_recurrente y el contador de intentos.
     */
    public function test_check_member_status_references_renew_credit_meta(): void
    {
        $src = file_get_contents(dirname(__DIR__, 2) . '/includes/CPT_Miembro.php');

        $this->assertStringContainsString('_convoca_pago_recurrente', $src);
        $this->assertStringContainsString('_convoca_autorenew_attempts', $src);
        $this->assertStringContainsString('auto_renew_max_attempts', $src);
        // El crédito de reintentos debe impedir suspender/bajar (condición negada).
        $this->assertStringContainsString('! $has_renew_credit', $src);
    }

    public function test_cron_manager_has_auto_renew_charge_branch(): void
    {
        $src = file_get_contents(dirname(__DIR__, 2) . '/includes/Cron_Manager.php');

        // El cron debe intentar el cargo automático real con token…
        $this->assertStringContainsString('auto_renew_charge', $src);
        // …gestionar un contador de reintentos…
        $this->assertStringContainsString('_convoca_autorenew_attempts', $src);
        // …y solo caer a pago manual cuando se agotan.
        $this->assertStringContainsString('auto_renew_max_attempts', $src);
        $this->assertStringContainsString('$attempts < $max_attempts', $src);
    }
}
