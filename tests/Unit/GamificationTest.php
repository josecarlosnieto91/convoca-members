<?php
/**
 * Real behavioral tests for Voluntariado_Gamification.
 * Tests track detection, level calculation, progress logic.
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;
use Convoca\Members\Voluntariado_Gamification;

class GamificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \Convoca\Core\Logger::clear();
        \Convoca\Core\Utils::clear_fired();
        $GLOBALS['_wp_stores']['post_meta'] = [];
        $GLOBALS['_wp_stores']['transients'] = [];
        $GLOBALS['_wp_stores']['options'] = [];
        $GLOBALS['_wp_stores']['user_meta'] = [];
        $path = dirname(__DIR__, 2) . '/includes/Voluntariado_Gamification.php';
        if (file_exists($path)) {
            require_once $path;
        }
    }

    // ── TRACKS structure ─────────────────────────────────

    public function test_tracks_defined(): void
    {
        $this->assertArrayHasKey('busgosu', Voluntariado_Gamification::TRACKS);
        $this->assertArrayHasKey('lugg', Voluntariado_Gamification::TRACKS);
        $this->assertArrayHasKey('deva', Voluntariado_Gamification::TRACKS);
    }

    public function test_each_track_has_five_levels(): void
    {
        foreach (Voluntariado_Gamification::TRACKS as $track => $config) {
            $this->assertCount(5, $config['levels'], "Track $track should have 5 levels");
        }
    }

    // ── get_track_for_member ────────────────────────────

    public function test_get_track_for_member_from_sub_plan(): void
    {
        update_post_meta(42, '_convoca_sub_plan', 'fam-busgosu');
        $this->assertEquals('busgosu', Voluntariado_Gamification::get_track_for_member(42));
    }

    public function test_get_track_for_member_from_plan(): void
    {
        update_post_meta(42, '_convoca_plan', 'lugg');
        $this->assertEquals('lugg', Voluntariado_Gamification::get_track_for_member(42));
    }

    public function test_get_track_for_member_sub_plan_takes_precedence(): void
    {
        update_post_meta(42, '_convoca_sub_plan', 'juv-deva');
        update_post_meta(42, '_convoca_plan', 'busgosu');
        $this->assertEquals('deva', Voluntariado_Gamification::get_track_for_member(42));
    }

    public function test_get_track_for_member_no_plan_returns_default(): void
    {
        $this->assertEquals('busgosu', Voluntariado_Gamification::get_track_for_member(42));
    }

    public function test_get_track_for_member_unknown_suffix_returns_default(): void
    {
        update_post_meta(42, '_convoca_plan', 'custom-plan');
        $this->assertEquals('busgosu', Voluntariado_Gamification::get_track_for_member(42));
    }

    // ── get_level ────────────────────────────────────────

    public function test_get_level_zero_hours_returns_first_level(): void
    {
        $level = Voluntariado_Gamification::get_level(0, 'busgosu');
        $this->assertEquals('Semilla', $level['name']);
        $this->assertEquals(0, $level['index']);
        $this->assertArrayHasKey('emoji', $level);
        $this->assertArrayHasKey('color', $level);
    }

    public function test_get_level_at_threshold(): void
    {
        // 10 hours = Brote (index 1)
        $level = Voluntariado_Gamification::get_level(10, 'busgosu');
        $this->assertEquals('Brote', $level['name']);
        $this->assertEquals(1, $level['index']);
    }

    public function test_get_level_above_threshold(): void
    {
        // 30 hours = Árbol (index 2)
        $level = Voluntariado_Gamification::get_level(30, 'busgosu');
        $this->assertEquals('Árbol', $level['name']);
        $this->assertEquals(2, $level['index']);
    }

    public function test_get_level_max_level(): void
    {
        // 200 hours = Ecosistema (index 4)
        $level = Voluntariado_Gamification::get_level(200, 'busgosu');
        $this->assertEquals('Ecosistema', $level['name']);
        $this->assertEquals(4, $level['index']);
    }

    public function test_get_level_with_invalid_track_falls_back(): void
    {
        $level = Voluntariado_Gamification::get_level(5, 'nonexistent');
        $this->assertEquals('Semilla', $level['name']);
    }

    public function test_get_level_empty_track_falls_back(): void
    {
        $level = Voluntariado_Gamification::get_level(5, '');
        $this->assertEquals('Semilla', $level['name']);
    }

    public function test_get_level_lugg_track(): void
    {
        $level = Voluntariado_Gamification::get_level(25, 'lugg');
        $this->assertEquals('Comunidad', $level['name']);
        $this->assertEquals(2, $level['index']);
    }

    public function test_get_level_deva_track(): void
    {
        $level = Voluntariado_Gamification::get_level(50, 'deva');
        $this->assertEquals('Gnomo', $level['name']);
        $this->assertEquals(3, $level['index']);
    }

    // ── get_next_level ───────────────────────────────────

    public function test_get_next_level_returns_second_when_at_first(): void
    {
        $next = Voluntariado_Gamification::get_next_level(0, 'busgosu');
        $this->assertNotNull($next);
        $this->assertEquals('Brote', $next['name']);
        $this->assertEquals(10, $next['hours']);
    }

    public function test_get_next_level_at_max_returns_null(): void
    {
        $next = Voluntariado_Gamification::get_next_level(200, 'busgosu');
        $this->assertNull($next);
    }

    public function test_get_next_level_between_levels(): void
    {
        // 15 hours: current = Brote (10h), next = Árbol (25h)
        $next = Voluntariado_Gamification::get_next_level(15, 'busgosu');
        $this->assertNotNull($next);
        $this->assertEquals('Árbol', $next['name']);
    }

    // ── get_progress ─────────────────────────────────────

    public function test_get_progress_at_zero(): void
    {
        $progress = Voluntariado_Gamification::get_progress(0, 'busgosu');
        $this->assertEquals('Semilla', $progress['current']['name']);
        $this->assertEquals('Brote', $progress['next']['name']);
        $this->assertEquals(0.0, $progress['progress_percent']);
        $this->assertEquals(10.0, $progress['hours_to_next']);
    }

    public function test_get_progress_halfway(): void
    {
        // 5 hours of 10 needed for Brote → 50%
        $progress = Voluntariado_Gamification::get_progress(5, 'busgosu');
        $this->assertEquals('Semilla', $progress['current']['name']);
        $this->assertEquals(50.0, $progress['progress_percent']);
        $this->assertEquals(5.0, $progress['hours_to_next']);
    }

    public function test_get_progress_at_max_level(): void
    {
        $progress = Voluntariado_Gamification::get_progress(200, 'busgosu');
        $this->assertEquals('Ecosistema', $progress['current']['name']);
        $this->assertNull($progress['next']);
        $this->assertEquals(100.0, $progress['progress_percent']);
        $this->assertEquals(0.0, $progress['hours_to_next']);
    }

    public function test_get_progress_negative_clamps_to_zero(): void
    {
        $progress = Voluntariado_Gamification::get_progress(-5, 'busgosu');
        $this->assertEquals(0.0, $progress['progress_percent']);
        $this->assertEquals(15.0, $progress['hours_to_next']); // -5 → 10 = 15 hours to go
    }

    // ── get_tracks_config ───────────────────────────────

    public function test_get_tracks_config_returns_defaults_when_no_saved(): void
    {
        $config = Voluntariado_Gamification::get_tracks_config();
        $this->assertEquals(Voluntariado_Gamification::TRACKS, $config);
    }

    public function test_get_tracks_config_merges_saved_overrides(): void
    {
        update_option('convoca_gamification_tracks', [
            'busgosu' => [
                'label'  => 'Custom Busgosu',
                'levels' => [
                    0 => ['name' => 'Custom Seed'],
                ],
            ],
        ]);

        $config = Voluntariado_Gamification::get_tracks_config();
        $this->assertEquals('Custom Busgosu', $config['busgosu']['label']);
        // Level 0 should be overridden but other keys preserved
        $this->assertEquals('Custom Seed', $config['busgosu']['levels'][0]['name']);
    }
}
