<?php
/**
 * El DNI es configurable: obligatorio, opcional o no pedirlo (issue #90).
 *
 * Antes venía exigido a pelo en las dos plantillas, en el alta de socio y en el voluntariado. Una
 * asociación que no necesita el dato no podía cambiarlo sin tocar código. Lo que se fija aquí:
 * el valor por defecto sigue siendo «obligatorio», el formulario se adapta a los tres modos y el
 * servidor deja de exigirlo (y de guardarlo) cuando no se pide.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Opciones;
use Convoca\Members\Process_Member;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Opciones.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Miembro.php';
require_once dirname( __DIR__, 2 ) . '/includes/Process_Member.php';

final class DniModoTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['_wp_stores'] = array(
			'options'    => array(),
			'transients' => array(),
			'cache'      => array(),
		);
		unset( $GLOBALS['_wp_cache'], $GLOBALS['_wp_cache_objects'] );
		$GLOBALS['convoca_test_posts'] = array();
		$GLOBALS['convoca_test_meta']  = array();
	}

	private function con_modo( string $modo ): void {
		$GLOBALS['_wp_stores']['options'][ Opciones::OPCION ] = array( 'dni_modo' => $modo );
	}

	public function test_por_defecto_es_obligatorio(): void {
		$this->assertSame( 'obligatorio', Opciones::dni_modo() );
		$this->assertTrue( Opciones::dni_obligatorio() );
		$this->assertTrue( Opciones::dni_se_pide() );
	}

	public function test_un_valor_desconocido_cae_en_obligatorio(): void {
		$this->con_modo( 'lo-que-sea' );

		$this->assertSame( 'obligatorio', Opciones::dni_modo() );
	}

	public function test_no_pedirlo_no_pinta_el_campo(): void {
		$this->con_modo( 'no' );

		$this->assertFalse( Opciones::dni_se_pide() );
		$this->assertSame( '', Opciones::campo_dni( 'conv-dni' ) );
	}

	public function test_obligatorio_pinta_required(): void {
		$this->con_modo( 'obligatorio' );
		$html = Opciones::campo_dni( 'conv-dni' );

		$this->assertStringContainsString( 'id="conv-dni"', $html );
		$this->assertStringContainsString( 'required', $html );
		$this->assertStringContainsString( 'DNI / NIE *', $html );
	}

	public function test_opcional_pinta_el_campo_sin_required(): void {
		$this->con_modo( 'opcional' );
		$html = Opciones::campo_dni( 'conv-vol-dni' );

		$this->assertStringContainsString( 'id="conv-vol-dni"', $html );
		$this->assertStringNotContainsString( 'required', $html );
		$this->assertStringContainsString( '(opcional)', $html );
	}

	public function test_el_alta_no_exige_el_dni_cuando_no_se_pide(): void {
		$this->con_modo( 'no' );

		$resultado = Process_Member::validate( $this->datos( '' ) );

		$this->assertNotInstanceOf( \WP_Error::class, $resultado, 'Con «no pedirlo» el alta no puede fallar por el DNI: ' . ( $resultado instanceof \WP_Error ? $resultado->get_error_message() : '' ) );
	}

	public function test_el_alta_sigue_exigiendolo_por_defecto(): void {
		$resultado = Process_Member::validate( $this->datos( '' ) );

		$this->assertInstanceOf( \WP_Error::class, $resultado );
		$this->assertStringContainsString( 'DNI/NIE es obligatorio', $resultado->get_error_message() );
	}

	public function test_el_alta_acepta_el_dni_vacio_si_es_opcional(): void {
		$this->con_modo( 'opcional' );

		$resultado = Process_Member::validate( $this->datos( '' ) );

		$this->assertNotInstanceOf( \WP_Error::class, $resultado, 'Opcional y vacío tiene que pasar: ' . ( $resultado instanceof \WP_Error ? $resultado->get_error_message() : '' ) );
	}

	/**
	 * Datos mínimos de un alta válida, con el DNI que se pida.
	 *
	 * @param string $dni DNI o cadena vacía.
	 * @return array<string, mixed>
	 */
	private function datos( string $dni ): array {
		return array(
			'nombre'           => 'Ana Prueba',
			'dni'              => $dni,
			'email'            => 'ana@example.test',
			'fecha_nacimiento' => '1990-01-01',
			'telefono'         => '600000000',
			'direccion'        => 'Calle Prueba 1',
			'municipio'        => 'Oviedo',
			'forma_pago'       => 'transferencia',
			'rgpd'             => '1',
			'plan'             => 'ordinario',
		);
	}
}
