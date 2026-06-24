<?php
/**
 * Unit tests for Voluntariado_Gamification — gamification engine structure.
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

class GamificationTest extends TestCase
{
    private function loadClass(): void
    {
        $path = dirname(__DIR__, 2) . '/includes/Voluntariado_Gamification.php';
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
        $this->assertTrue(class_exists('Convoca\Members\Voluntariado_Gamification'));
    }

    public function test_has_init_method(): void
    {
        $this->assertTrue(method_exists('Convoca\Members\Voluntariado_Gamification', 'init'));
    }

    public function test_has_get_track_for_member_method(): void
    {
        $this->assertTrue(method_exists('Convoca\Members\Voluntariado_Gamification', 'get_track_for_member'));
    }

    public function test_has_get_tracks_config_method(): void
    {
        $this->assertTrue(method_exists('Convoca\Members\Voluntariado_Gamification', 'get_tracks_config'));
    }

    public function test_has_get_level_method(): void
    {
        $this->assertTrue(method_exists('Convoca\Members\Voluntariado_Gamification', 'get_level'));
    }

    public function test_has_get_next_level_method(): void
    {
        $this->assertTrue(method_exists('Convoca\Members\Voluntariado_Gamification', 'get_next_level'));
    }

    public function test_has_get_progress_method(): void
    {
        $this->assertTrue(method_exists('Convoca\Members\Voluntariado_Gamification', 'get_progress'));
    }
}
