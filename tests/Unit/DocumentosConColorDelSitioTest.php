<?php
/**
 * Los documentos (carnet y certificado) usan el color del sitio (issue #92).
 *
 * Los PDF no pueden leer variables CSS: el color viaja en hexadecimal dentro del HTML. Antes estaba
 * clavado en `#320028` y `#ff8700`, la paleta de un cliente. Ahora se sustituye al final, en un solo
 * sitio (`Estilos_PDF::aplicar()`), para no tocar los constructores de HTML.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Estilos_PDF;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Estilos_PDF.php';

final class DocumentosConColorDelSitioTest extends TestCase {

	/**
	 * Estado limpio.
	 */
	protected function setUp(): void {
		$GLOBALS['_wp_stores']['options'] = array();
	}

	/**
	 * Sin ajuste, el documento conserva los colores de la marca.
	 */
	public function test_sin_ajuste_se_quedan_los_colores_de_la_marca(): void {
		$this->assertSame( '#320028', Estilos_PDF::uva() );
		$this->assertSame( '#ff8700', Estilos_PDF::naranja() );
	}

	/**
	 * Con ajuste, el documento sale con el color del sitio.
	 */
	public function test_con_ajuste_manda_el_color_del_sitio(): void {
		$GLOBALS['_wp_stores']['options']['convoca_site_settings'] = array(
			'color_acento'        => '#123456',
			'color_acento_fuerte' => '#654321',
		);

		$html = Estilos_PDF::aplicar( '<style>.a{color:#320028}.b{color:#ff8700}</style>' );

		$this->assertStringContainsString( '#654321', $html );
		$this->assertStringContainsString( '#123456', $html );
		$this->assertStringNotContainsString( '#320028', $html, 'no puede quedar el color de la marca' );
		$this->assertStringNotContainsString( '#ff8700', $html, 'no puede quedar el color de la marca' );
	}

	/**
	 * Sin ajuste, aplicar no cambia nada.
	 */
	public function test_sin_ajuste_aplicar_no_cambia_el_documento(): void {
		$html = '<style>.a{color:#320028}</style>';

		$this->assertSame( $html, Estilos_PDF::aplicar( $html ) );
	}
}
