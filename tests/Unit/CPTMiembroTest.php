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
}
