<?php
/**
 * Integration tests for Estados — state transitions.
 * Requires WordPress (tested in CI).
 */

namespace Convoca\Members\Tests;

class EstadosIntegrationTest extends \WP_UnitTestCase
{
    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists('Convoca\Members\Estados'));
    }

    public function test_states_constant_is_array(): void
    {
        $this->assertIsArray(\Convoca\Members\Estados::STATES);
        $this->assertNotEmpty(\Convoca\Members\Estados::STATES);
    }

    public function test_transitions_constant_is_array(): void
    {
        $this->assertIsArray(\Convoca\Members\Estados::TRANSITIONS);
    }

    public function test_all_state_keys_have_labels(): void
    {
        foreach (\Convoca\Members\Estados::STATES as $key => $label) {
            $this->assertArrayHasKey($key, \Convoca\Members\Estados::labels());
            $this->assertArrayHasKey($key, \Convoca\Members\Estados::BADGE_CLASSES);
        }
    }
}
