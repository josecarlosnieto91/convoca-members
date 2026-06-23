<?php
/**
 * Unit tests for Convoca Members — structural tests (no WP needed).
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

class MembersStructureTest extends TestCase
{
    private function loadClass(string $file): void
    {
        $path = dirname(__DIR__, 2) . "/includes/$file";
        if (file_exists($path)) {
            require_once $path;
        }
    }

    public function test_estados_class_loads(): void
    {
        $this->loadClass('Estados.php');
        $this->assertTrue(class_exists('Convoca\Members\Estados'));
    }

    public function test_estados_has_required_methods(): void
    {
        $this->loadClass('Estados.php');
        $this->assertTrue(method_exists('Convoca\Members\Estados', 'badge_html'));
        $this->assertTrue(method_exists('Convoca\Members\Estados', 'change'));
        $this->assertTrue(method_exists('Convoca\Members\Estados', 'get_history'));
    }

    public function test_process_member_class_loads(): void
    {
        $this->loadClass('Process_Member.php');
        $this->assertTrue(class_exists('Convoca\Members\Process_Member'));
    }

    public function test_hours_manager_class_loads(): void
    {
        $this->loadClass('Hours_Manager.php');
        $this->assertTrue(class_exists('Convoca\Members\Hours_Manager'));
    }

    public function test_certificate_generator_class_loads(): void
    {
        $this->loadClass('Certificate_Generator.php');
        $this->assertTrue(class_exists('Convoca\Members\Certificate_Generator'));
    }

    public function test_voluntariado_manager_class_loads(): void
    {
        $this->loadClass('Voluntariado_Manager.php');
        $this->assertTrue(class_exists('Convoca\Members\Voluntariado_Manager'));
    }

    public function test_gdpr_tools_class_loads(): void
    {
        $this->loadClass('GDPR_Tools.php');
        $this->assertTrue(class_exists('Convoca\Members\GDPR_Tools'));
    }

    public function test_email_manager_class_loads(): void
    {
        $this->loadClass('Email_Manager.php');
        $this->assertTrue(class_exists('Convoca\Members\Email_Manager'));
    }

    public function test_audit_logger_class_loads(): void
    {
        $this->loadClass('Audit_Logger.php');
        $this->assertTrue(class_exists('Convoca\Members\Audit_Logger'));
    }
}
