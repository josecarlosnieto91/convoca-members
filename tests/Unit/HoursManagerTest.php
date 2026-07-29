<?php
/**
 * Real behavioral tests for Hours_Manager.
 * Tests validation, approval processing, and record saving logic.
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;
use Convoca\Members\Hours_Manager;

class HoursManagerTest extends TestCase
{
    private const MEMBER_ID = 42;
    private const HOURS_POST_ID = 100;

    protected function setUp(): void
    {
        parent::setUp();
        \Convoca\Core\Logger::clear();
        \Convoca\Core\Utils::clear_fired();
        $GLOBALS['_wp_stores']['post_meta'] = [];
        $GLOBALS['_wp_stores']['transients'] = [];
        $GLOBALS['_wp_stores']['options'] = [];
        $path = dirname(__DIR__, 2) . '/includes/Hours_Manager.php';
        if (file_exists($path)) {
            require_once $path;
        }
    }

    // ── validate_hours_data ─────────────────────────────

    public function test_validate_valid_data_returns_true(): void
    {
        // Set up a valid proyecto post
        $proyecto = new \stdClass();
        $proyecto->ID = 10;
        $proyecto->post_title = 'Proyecto Test';
        $proyecto->post_type = 'proyecto';
        $proyecto->post_status = 'publish';
        $GLOBALS['_test_posts'][10] = $proyecto;

        $data = [
            'miembro_id'  => 1,
            'fecha'       => '2026-06-15',
            'horas'       => '5',
            'proyecto_id' => 10,
            'tareas'      => 'Limpieza del centro',
        ];

        $result = Hours_Manager::validate_hours_data($data);
        $this->assertTrue($result);
    }

    public function test_validate_missing_miembro_returns_error(): void
    {
        $data = [
            'miembro_id'  => 0,
            'fecha'       => '2026-06-15',
            'horas'       => '5',
            'proyecto_id' => 10,
            'tareas'      => 'Tareas',
        ];

        $result = Hours_Manager::validate_hours_data($data);
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('missing_miembro', $result->get_error_code());
    }

    public function test_validate_missing_fecha_returns_error(): void
    {
        $data = [
            'miembro_id'  => 1,
            'fecha'       => '',
            'horas'       => '5',
            'proyecto_id' => 10,
            'tareas'      => 'Tareas',
        ];

        $result = Hours_Manager::validate_hours_data($data);
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('missing_fecha', $result->get_error_code());
    }

    public function test_validate_zero_hours_returns_error(): void
    {
        $data = [
            'miembro_id'  => 1,
            'fecha'       => '2026-06-15',
            'horas'       => '0',
            'proyecto_id' => 10,
            'tareas'      => 'Tareas',
        ];

        $result = Hours_Manager::validate_hours_data($data);
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('invalid_horas', $result->get_error_code());
    }

    public function test_validate_negative_hours_returns_error(): void
    {
        $data = [
            'miembro_id'  => 1,
            'fecha'       => '2026-06-15',
            'horas'       => '-3',
            'proyecto_id' => 10,
            'tareas'      => 'Tareas',
        ];

        $result = Hours_Manager::validate_hours_data($data);
        $this->assertInstanceOf(\WP_Error::class, $result);
    }

    public function test_validate_missing_proyecto_returns_error(): void
    {
        $data = [
            'miembro_id'  => 1,
            'fecha'       => '2026-06-15',
            'horas'       => '5',
            'proyecto_id' => 0,
            'tareas'      => 'Tareas',
        ];

        $result = Hours_Manager::validate_hours_data($data);
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('missing_proyecto', $result->get_error_code());
    }

    public function test_validate_missing_tareas_returns_error(): void
    {
        $data = [
            'miembro_id'  => 1,
            'fecha'       => '2026-06-15',
            'horas'       => '5',
            'proyecto_id' => 10,
            'tareas'      => '',
        ];

        $result = Hours_Manager::validate_hours_data($data);
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('missing_tareas', $result->get_error_code());
    }

    public function test_validate_invalid_proyecto_returns_error(): void
    {
        $data = [
            'miembro_id'  => 1,
            'fecha'       => '2026-06-15',
            'horas'       => '5',
            'proyecto_id' => 999,
            'tareas'      => 'Tareas',
        ];

        // 999 is a regular post, not a proyecto
        $result = Hours_Manager::validate_hours_data($data);
        $this->assertInstanceOf(\WP_Error::class, $result);
    }

    // ── process_approval ────────────────────────────────

    public function test_process_approval_approves_hour_record(): void
    {
        update_post_meta(self::HOURS_POST_ID, '_convoca_member_id', self::MEMBER_ID);
        update_post_meta(self::HOURS_POST_ID, '_convoca_estado', 'pendiente');

        $result = Hours_Manager::process_approval(self::HOURS_POST_ID, 'aprobada', 1);
        $this->assertTrue($result);
        $this->assertEquals('aprobada', get_post_meta(self::HOURS_POST_ID, '_convoca_estado', true));
        $this->assertEquals(1, get_post_meta(self::HOURS_POST_ID, '_convoca_aprobada_por', true));
    }

    public function test_process_approval_rejects_record(): void
    {
        update_post_meta(self::HOURS_POST_ID, '_convoca_member_id', self::MEMBER_ID);
        update_post_meta(self::HOURS_POST_ID, '_convoca_estado', 'pendiente');

        $result = Hours_Manager::process_approval(self::HOURS_POST_ID, 'rechazada', 1);
        $this->assertTrue($result);
        $this->assertEquals('rechazada', get_post_meta(self::HOURS_POST_ID, '_convoca_estado', true));
    }

    public function test_process_approval_with_bool_true(): void
    {
        update_post_meta(self::HOURS_POST_ID, '_convoca_member_id', self::MEMBER_ID);
        update_post_meta(self::HOURS_POST_ID, '_convoca_estado', 'pendiente');

        $result = Hours_Manager::process_approval(self::HOURS_POST_ID, true, 1);
        $this->assertTrue($result);
        $this->assertEquals('aprobada', get_post_meta(self::HOURS_POST_ID, '_convoca_estado', true));
    }

    public function test_process_approval_with_bool_false(): void
    {
        update_post_meta(self::HOURS_POST_ID, '_convoca_member_id', self::MEMBER_ID);
        update_post_meta(self::HOURS_POST_ID, '_convoca_estado', 'pendiente');

        $result = Hours_Manager::process_approval(self::HOURS_POST_ID, false, 1);
        $this->assertTrue($result);
        $this->assertEquals('rechazada', get_post_meta(self::HOURS_POST_ID, '_convoca_estado', true));
    }

    public function test_process_approval_no_member_id_returns_false(): void
    {
        $result = Hours_Manager::process_approval(999, 'aprobada', 1);
        $this->assertFalse($result);
    }

    public function test_process_approval_same_status_is_noop(): void
    {
        update_post_meta(self::HOURS_POST_ID, '_convoca_member_id', self::MEMBER_ID);
        update_post_meta(self::HOURS_POST_ID, '_convoca_estado', 'aprobada');

        $result = Hours_Manager::process_approval(self::HOURS_POST_ID, 'aprobada', 1);
        $this->assertTrue($result);
        $this->assertEquals('aprobada', get_post_meta(self::HOURS_POST_ID, '_convoca_estado', true));
    }
}
