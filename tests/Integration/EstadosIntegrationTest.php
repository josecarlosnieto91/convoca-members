<?php
/**
 * Integration tests for Estados — state transitions.
 * Requires WordPress (tested in CI).
 */

namespace Convoca\Members\Tests;

class EstadosIntegrationTest extends \PHPUnit\Framework\TestCase
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
        // STATES es una lista (claves 0,1,2...) y labels()/BADGE_CLASSES son mapas
        // por nombre de estado: la clave que hay que buscar es el VALOR, no el indice.
        foreach (\Convoca\Members\Estados::STATES as $estado) {
            $this->assertArrayHasKey($estado, \Convoca\Members\Estados::labels());
            $this->assertArrayHasKey($estado, \Convoca\Members\Estados::BADGE_CLASSES);
        }
    }
}
