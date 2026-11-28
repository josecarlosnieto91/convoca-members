<?php
/**
 * Socios y voluntarios reconocen el mismo DNI en el mismo sitio (members#135).
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Datos_Socio;
use PHPUnit\Framework\TestCase;

final class DniEntreCircuitosTest extends TestCase {

	/**
	 * El índice del DNI vive en la ficha, no en el usuario.
	 */
	public function test_el_alta_encuentra_el_dni_en_la_ficha(): void {
		$GLOBALS['_wp_stores']['posts'] = array();
		$p                              = new \WP_Post();
		$p->ID                         = 55;
		$p->post_type                  = 'miembro';
		$p->post_status                = 'publish';
		$p->post_title                 = 'Ada';
		$GLOBALS['_wp_stores']['posts'][55] = $p;
		$GLOBALS['_wp_stores']['post_meta'][55] = array(
			'_convoca_dni_idx' => Datos_Socio::indice( '11111111H' ),
		);

		$this->assertSame( 55, Datos_Socio::ficha_por_dni( '11111111H' ) );
		$this->assertSame( 0, Datos_Socio::ficha_por_dni( '22222222J' ), 'otro DNI no casa' );
	}

	/**
	 * El alta de voluntariado usa la ficha, no la meta de usuario.
	 */
	public function test_el_voluntariado_consulta_la_ficha(): void {
		$src = (string) file_get_contents( dirname( __DIR__, 2 ) . '/public/class-form-voluntariado.php' );

		$this->assertStringContainsString( 'Datos_Socio::ficha_por_dni( $dni )', $src );
	}
}
