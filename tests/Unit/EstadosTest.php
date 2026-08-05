<?php
/**
 * Real behavioral tests for Convoca Members — Estados state machine.
 * Tests state transitions, validation, badge HTML, and history logging.
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;
use Convoca\Members\Estados;

class EstadosTest extends TestCase
{
    private const POST_ID = 123;

    protected function setUp(): void
    {
        parent::setUp();
        \Convoca\Core\Logger::clear();
        \Convoca\Core\Utils::clear_fired();
        $GLOBALS['_wp_stores']['post_meta'] = [];
        $GLOBALS['_wp_stores']['transients'] = [];
        $GLOBALS['_wp_stores']['options'] = [];
        $GLOBALS['_wp_stores']['user_meta'] = [];
        // Load the source class
        $path = dirname(__DIR__, 2) . '/includes/Estados.php';
        if (file_exists($path)) {
            require_once $path;
        }
    }

    // ── Constants ────────────────────────────────────────

    public function test_states_constants_are_valid(): void
    {
        $this->assertIsArray(Estados::STATES);
        $this->assertContains('pendiente_documentacion', Estados::STATES);
        $this->assertContains('pendiente_pago', Estados::STATES);
        $this->assertContains('activo', Estados::STATES);
        $this->assertContains('suspendido', Estados::STATES);
        $this->assertContains('baja_solicitada', Estados::STATES);
        $this->assertContains('baja', Estados::STATES);
    }

    public function test_labels_cover_all_states(): void
    {
        foreach (Estados::STATES as $state) {
            $this->assertArrayHasKey($state, Estados::labels(), "Missing label for state: $state");
            $this->assertNotEmpty(Estados::labels()[$state]);
        }
    }

    public function test_badge_classes_cover_all_states(): void
    {
        foreach (Estados::STATES as $state) {
            $this->assertArrayHasKey($state, Estados::BADGE_CLASSES, "Missing badge class for state: $state");
            $this->assertStringContainsString('convoca-badge', Estados::BADGE_CLASSES[$state]);
        }
    }

    // ── badge_html ──────────────────────────────────────

    public function test_badge_html_returns_valid_markup(): void
    {
        $html = Estados::badge_html('activo');
        $this->assertStringContainsString('<span', $html);
        $this->assertStringContainsString('convoca-badge', $html);
        $this->assertStringContainsString('Activo', $html);
    }

    public function test_badge_html_unknown_state_uses_fallback(): void
    {
        $html = Estados::badge_html('unknown_state_xyz');
        $this->assertStringContainsString('unknown_state_xyz', $html);
        $this->assertStringContainsString('convoca-badge', $html);
    }

    // ── State machine: change() ───────────────────────────

    public function test_change_invalid_state_returns_error(): void
    {
        $result = Estados::change(self::POST_ID, 'estado_inexistente');
        $this->assertInstanceOf(\WP_Error::class, $result);
    }

    public function test_change_invalid_transition_returns_error(): void
    {
        // Set initial state to 'baja' (terminal)
        update_post_meta(self::POST_ID, '_convoca_estado_miembro', 'baja');

        $result = Estados::change(self::POST_ID, 'suspendido');
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('invalid_transition', $result->get_error_code());
    }

    public function test_change_same_state_is_noop(): void
    {
        update_post_meta(self::POST_ID, '_convoca_estado_miembro', 'activo');

        $result = Estados::change(self::POST_ID, 'activo');
        $this->assertTrue($result);
    }

    public function test_change_valid_transition_succeeds(): void
    {
        update_post_meta(self::POST_ID, '_convoca_estado_miembro', 'pendiente_documentacion');

        $result = Estados::change(self::POST_ID, 'pendiente_pago', 'Documentación completa');
        $this->assertTrue($result);
        $this->assertEquals('pendiente_pago', get_post_meta(self::POST_ID, '_convoca_estado_miembro', true));
    }

    public function test_change_concurrent_lock_returns_error(): void
    {
        $uniquePostId = 9999;
        set_transient("convoca_state_change_{$uniquePostId}", 1, 10);

        $result = Estados::change($uniquePostId, 'activo');
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('concurrent_change', $result->get_error_code());
    }

    public function test_change_pendiente_pago_sets_timestamp(): void
    {
        update_post_meta(self::POST_ID, '_convoca_estado_miembro', 'pendiente_documentacion');

        Estados::change(self::POST_ID, 'pendiente_pago');
        $fecha = get_post_meta(self::POST_ID, '_convoca_fecha_pendiente_pago', true);
        $this->assertNotEmpty($fecha);
    }

    public function test_change_full_workflow(): void
    {
        // Simulate full member lifecycle
        update_post_meta(self::POST_ID, '_convoca_estado_miembro', 'pendiente_documentacion');

        // pendiente_documentacion → pendiente_pago
        $this->assertTrue(Estados::change(self::POST_ID, 'pendiente_pago'));
        $this->assertEquals('pendiente_pago', get_post_meta(self::POST_ID, '_convoca_estado_miembro', true));

        // pendiente_pago → activo
        $this->assertTrue(Estados::change(self::POST_ID, 'activo'));
        $this->assertEquals('activo', get_post_meta(self::POST_ID, '_convoca_estado_miembro', true));

        // activo → suspendido
        $this->assertTrue(Estados::change(self::POST_ID, 'suspendido'));
        $this->assertEquals('suspendido', get_post_meta(self::POST_ID, '_convoca_estado_miembro', true));

        // suspendido → activo (reactivation)
        $this->assertTrue(Estados::change(self::POST_ID, 'activo'));
        $this->assertEquals('activo', get_post_meta(self::POST_ID, '_convoca_estado_miembro', true));

        // activo → baja_solicitada
        $this->assertTrue(Estados::change(self::POST_ID, 'baja_solicitada'));
        $this->assertEquals('baja_solicitada', get_post_meta(self::POST_ID, '_convoca_estado_miembro', true));

        // baja_solicitada → baja
        $this->assertTrue(Estados::change(self::POST_ID, 'baja'));
        $this->assertEquals('baja', get_post_meta(self::POST_ID, '_convoca_estado_miembro', true));
    }

    // ── get_history ──────────────────────────────────────

    public function test_get_history_returns_empty_array_for_no_history(): void
    {
        $history = Estados::get_history(self::POST_ID);
        $this->assertIsArray($history);
        $this->assertEmpty($history);
    }

    public function test_get_history_returns_entries_after_changes(): void
    {
        update_post_meta(self::POST_ID, '_convoca_estado_miembro', 'pendiente_documentacion');

        Estados::change(self::POST_ID, 'pendiente_pago', 'First change');
        Estados::change(self::POST_ID, 'activo', 'Second change');

        $history = Estados::get_history(self::POST_ID);
        $this->assertCount(2, $history);

        // Check first entry
        $this->assertEquals('pendiente_documentacion', $history[0]['de'] ?? 'NUEVO');
        $this->assertEquals('pendiente_pago', $history[0]['a']);
        $this->assertEquals('First change', $history[0]['nota']);

        // Check second entry
        $this->assertEquals('pendiente_pago', $history[1]['de']);
        $this->assertEquals('activo', $history[1]['a']);
        $this->assertEquals('Second change', $history[1]['nota']);

        // Verify entry structure
        $this->assertArrayHasKey('fecha', $history[0]);
        $this->assertArrayHasKey('usuario', $history[0]);
    }

    // ── Bail out early cases ────────────────────────────

    public function test_change_empty_old_state_still_succeeds(): void
    {
        // No prior state meta — should still set new state
        $result = Estados::change(self::POST_ID, 'pendiente_documentacion');
        $this->assertTrue($result);
        $this->assertEquals('pendiente_documentacion', get_post_meta(self::POST_ID, '_convoca_estado_miembro', true));
    }
}
