<?php
/**
 * El carnet con foto (issue #31).
 *
 * La web prometía «Carnets Digitales: generación de carnets **con foto**, código QR y datos del miembro». El
 * carnet y el QR estaban y funcionaban; la foto no existía por ninguna parte —ni campo en la ficha ni nada que
 * pintara un adjunto—.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\PDF_Card;
use PHPUnit\Framework\TestCase;

class CarnetConFotoTest extends TestCase {

	private function carnet(): string {
		$ruta = dirname( __DIR__, 2 ) . '/includes/PDF_Card.php';
		$this->assertFileExists( $ruta );

		return (string) file_get_contents( $ruta );
	}

	public function test_sin_foto_no_hay_nada_que_imprimir(): void {
		// La ficha sin foto no puede sacar un hueco ni un icono roto: cadena vacía y santas pascuas.
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', '/tmp/wp/' );
		}
		require_once dirname( __DIR__, 2 ) . '/includes/PDF_Card.php';

		$this->assertSame( '', \Convoca\Members\PDF_Card::foto( 0 ) );
		$this->assertSame( '', \Convoca\Members\PDF_Card::foto( 424242 ) );
	}

	public function test_el_carnet_inserta_la_foto(): void {
		$codigo = $this->carnet();
		$this->assertStringContainsString( 'self::foto( $post_id )', $codigo, 'El carnet tiene que pintarla' );
		$this->assertMatchesRegularExpression( '/<div class="body">\s*\'\s*\.\s*self::foto\( \$post_id \)/', $codigo, 'Y dentro del cuerpo, junto al número' );
	}

	public function test_la_foto_se_embebe_y_solo_si_es_imagen(): void {
		$codigo = $this->carnet();
		$this->assertStringContainsString( "'_convoca_foto_id'", $codigo, 'Se lee el campo de la ficha' );
		$this->assertStringContainsString(
			'base64_encode',
			$codigo,
			'Va en línea (data URI) como el QR: el PDF no puede ir a buscar una URL'
		);
		$this->assertStringContainsString( 'image/jpeg', $codigo );
		$this->assertStringContainsString( 'in_array( $tipo, $validos, true )', $codigo, 'Solo se imprimen imágenes' );
		$this->assertStringContainsString( 'wp_get_image_editor', $codigo, 'Se reduce antes de meterla en el PDF' );
	}

	public function test_la_ficha_tiene_el_campo_y_se_guarda(): void {
		$editor = (string) file_get_contents( dirname( __DIR__, 2 ) . '/admin/class-admin-member-editor.php' );
		$this->assertStringContainsString( 'name="convoca_foto_id"', $editor, 'Falta el campo en la ficha' );
		$this->assertStringContainsString( 'wp_enqueue_media()', $editor, 'El selector necesita la biblioteca de medios' );
		$this->assertStringContainsString( 'wp.media(', $editor, 'Y el selector' );
		$this->assertStringContainsString( "'foto_id'           => 'absint'", $editor, 'Y guardarse al guardar la ficha' );
	}
}
