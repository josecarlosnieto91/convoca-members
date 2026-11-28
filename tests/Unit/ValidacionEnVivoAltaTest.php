<?php
/**
 * La validación en vivo escucha los campos reales del alta (members#131).
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

final class ValidacionEnVivoAltaTest extends TestCase {

	/**
	 * Los cinco selectores existen en la plantilla.
	 */
	public function test_los_selectores_criticos_son_los_del_formulario(): void {
		$js = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/js/convoca-members-public.js' );
		$tpl = (string) file_get_contents( dirname( __DIR__, 2 ) . '/templates/form-alta.php' );

		$this->assertStringNotContainsString( '#conv-alta-dni', $js );
		$this->assertStringContainsString( 'no puede ser futura', $js, 'la fecha también se valida en vivo' );
		foreach ( array( 'conv-dni', 'conv-email', 'conv-telefono', 'conv-nombre', 'conv-fechanac' ) as $id ) {
			$this->assertStringContainsString( '#' . $id, $js, "el JS escucha #{$id}" );
			if ( 'conv-dni' === $id ) {
				$this->assertStringContainsString( "campo_dni( 'conv-dni' )", $tpl, 'la plantilla genera #conv-dni' );
			} else {
				$this->assertStringContainsString( 'id="' . $id . '"', $tpl, "la plantilla pinta #{$id}" );
			}
		}
	}
}
