<?php
/**
 * Contrato del alta AJAX (members#145).
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Form_Handler;
use PHPUnit\Framework\TestCase;

final class FormHandlerContratoTest extends TestCase {

	/**
	 * El consumidor recibe el identificador real como member_id.
	 */
	public function test_el_resultado_expone_member_id(): void {
		$resultado = Form_Handler::resultado_ajax( array( 'id' => 77, 'nombre' => 'Ana' ) );

		$this->assertSame( 77, $resultado['member_id'] );
		$this->assertSame( 77, $resultado['id'] );
	}

	/**
	 * Sin id no se emite un aviso de índice indefinido.
	 */
	public function test_sin_id_devuelve_cero(): void {
		$resultado = Form_Handler::resultado_ajax( array( 'nombre' => 'Ana' ) );

		$this->assertSame( 0, $resultado['member_id'] );
	}

	/**
	 * El aviso se emite una sola vez, desde Process_Member.
	 */
	public function test_no_repite_el_hook_legado(): void {
		$src = (string) file_get_contents( dirname( __DIR__, 2 ) . '/public/class-form-handler.php' );

		$this->assertStringNotContainsString( "do_action( 'convoca_member_created'", $src );
		$this->assertStringContainsString( 'resultado_ajax', $src );
	}
}
