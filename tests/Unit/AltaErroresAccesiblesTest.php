<?php
/**
 * Errores del alta asociados a su campo y foco al cambiar de paso (members#130).
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

final class AltaErroresAccesiblesTest extends TestCase {

	/**
	 * Cada paso tiene título enfocable y una región que anuncia el progreso.
	 */
	public function test_los_pasos_tienen_titulo_y_estado(): void {
		$tpl = (string) file_get_contents( dirname( __DIR__, 2 ) . '/templates/form-alta.php' );
		$js  = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/js/convoca-members-public.js' );

		for ( $n = 1; $n <= 4; $n++ ) {
			$this->assertStringContainsString( 'id="conv-step-title-' . $n . '" tabindex="-1"', $tpl );
		}
		$this->assertStringContainsString( 'id="conv-step-status"', $tpl );
		$this->assertStringContainsString( "'Paso ' + n + ' de ' + steps.length", $js );
		$this->assertStringContainsString( "conv.\$('#conv-step-title-' + n", $js );
	}

	/**
	 * El aviso es único y se enlaza con aria-describedby.
	 */
	public function test_el_aviso_se_vincula_al_campo(): void {
		$js = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/js/convoca-members-public.js' );

		$this->assertStringContainsString( 'function vincularAviso', $js );
		$this->assertStringContainsString( "setAttribute('aria-describedby', aviso.id)", $js );
		$this->assertStringContainsString( ".querySelectorAll('.convoca-field-error').forEach(n => n.remove())", $js );
		$this->assertStringNotContainsString( "aviso.className = 'convoca-field-error'", $js );
	}
}
