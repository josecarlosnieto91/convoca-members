<?php
/**
 * El código de acceso legado no se presenta como credencial (members#143).
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

final class CodigoAccesoLegadoTest extends TestCase {

	/**
	 * El panel ya no muestra el código y el perfil no lo devuelve.
	 */
	public function test_el_panel_no_presenta_el_codigo_legado(): void {
		$js  = (string) file_get_contents( dirname( __DIR__, 2 ) . '/public/assets/mi-area.js' );
		$api = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Rest_API.php' );

		$this->assertStringNotContainsString( 'Código de Acceso', $js );
		$this->assertStringNotContainsString( "'codigo'", $api );
	}
}
