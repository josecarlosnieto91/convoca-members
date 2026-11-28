<?php
/**
 * Las pestañas de Mi Área se usan con teclado (members#129).
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

final class MiAreaTabsTest extends TestCase {

	/**
	 * Marcado accesible: tablist, tabs y tabpanel.
	 */
	public function test_el_marcado_es_de_pestanas(): void {
		$php = (string) file_get_contents( dirname( __DIR__, 2 ) . '/public/class-mi-area.php' );

		$this->assertStringContainsString( '<ul role="tablist"', $php );
		$this->assertSame( 9, substr_count( $php, 'role="tab"' ) );
		$this->assertStringContainsString( 'aria-selected="true"', $php );
		$this->assertStringContainsString( 'aria-controls="conv-main-content"', $php );
		$this->assertStringContainsString( 'id="conv-main-content" role="tabpanel"', $php );
	}

	/**
	 * Teclado: flechas mueven el foco; Intro y Espacio activan.
	 */
	public function test_el_teclado_cambia_de_pestana(): void {
		$js = (string) file_get_contents( dirname( __DIR__, 2 ) . '/public/assets/mi-area.js' );

		$this->assertStringContainsString( 'activarPestana: function(tabEl, focus)', $js );
		$this->assertStringContainsString( "setAttribute('aria-selected', activa ? 'true' : 'false')", $js );
		$this->assertStringContainsString( "'ArrowRight' === e.key", $js );
		$this->assertStringContainsString( "'ArrowLeft' === e.key", $js );
		$this->assertStringContainsString( "'Enter' === e.key || ' ' === e.key", $js );
	}
}
