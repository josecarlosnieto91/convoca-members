<?php
/**
 * Unit tests for Convoca Members — Estados and validation (no WP needed).
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

class EstadosTest extends TestCase
{
    private function loadClass(): void
    {
        $path = dirname(__DIR__, 2) . '/includes/Estados.php';
        if (file_exists($path)) {
            require_once $path;
        }
    }

    protected function setUp(): void
    {
        $this->loadClass();
    }

    // ── Constants ──────────────────────────────────

    public function test_has_labels_constants(): void
    {
        $this->assertIsArray(\Convoca\Members\Estados::LABELS);
        $this->assertNotEmpty(\Convoca\Members\Estados::LABELS);
    }

    public function test_has_badge_classes_constants(): void
    {
        $this->assertIsArray(\Convoca\Members\Estados::BADGE_CLASSES);
        $this->assertNotEmpty(\Convoca\Members\Estados::BADGE_CLASSES);
    }

    public function test_has_states_constants(): void
    {
        $this->assertIsArray(\Convoca\Members\Estados::STATES);
        $this->assertNotEmpty(\Convoca\Members\Estados::STATES);
    }

    public function test_has_transitions_constants(): void
    {
        $this->assertIsArray(\Convoca\Members\Estados::TRANSITIONS);
        $this->assertNotEmpty(\Convoca\Members\Estados::TRANSITIONS);
    }

    // ── Methods ────────────────────────────────────

    public function test_has_change_method(): void
    {
        $this->assertTrue(method_exists('Convoca\Members\Estados', 'change'));
    }

    public function test_has_get_history_method(): void
    {
        $this->assertTrue(method_exists('Convoca\Members\Estados', 'get_history'));
    }

    public function test_has_badge_html_method(): void
    {
        $this->assertTrue(method_exists('Convoca\Members\Estados', 'badge_html'));
    }
}
