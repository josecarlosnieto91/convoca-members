<?php
/**
 * Enlace de justificante: firma y caducidad de 3 días (members#86).
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Justificante;
use PHPUnit\Framework\TestCase;

/**
 * El enlace no puede servir para siempre ni para cualquiera.
 */
final class JustificanteTest extends TestCase {

	/**
	 * Un token recién emitido vale.
	 */
	public function test_el_token_recien_emitido_vale(): void {
		$token = Justificante::token( 42 );

		$this->assertNotSame( '', $token );
		$this->assertTrue( Justificante::token_valido( 42, $token ) );
	}

	/**
	 * El token de un socio no vale para otro.
	 */
	public function test_el_token_no_sirve_para_otro_socio(): void {
		$token = Justificante::token( 42 );

		$this->assertFalse( Justificante::token_valido( 43, $token ) );
	}

	/**
	 * Un token manipulado no vale.
	 */
	public function test_un_token_manipulado_no_vale(): void {
		$token = Justificante::token( 42 );
		$partes = explode( '.', $token );

		$this->assertFalse( Justificante::token_valido( 42, ( (int) $partes[0] + 3600 ) . '.' . $partes[1] ) );
		$this->assertFalse( Justificante::token_valido( 42, $partes[0] . '.' . str_repeat( 'a', 64 ) ) );
	}

	/**
	 * Un token caducado no vale.
	 */
	public function test_un_token_caducado_no_vale(): void {
		$expira = time() - 60;
		$token  = $expira . '.' . hash_hmac( 'sha256', '42|' . $expira, wp_salt( 'auth' ) );

		$this->assertFalse( Justificante::token_valido( 42, $token ) );
	}

	/**
	 * La vigencia declarada son tres días.
	 */
	public function test_la_vigencia_es_de_tres_dias(): void {
		$this->assertSame( 259200, Justificante::VIDA );
	}

	/**
	 * Sin token, el formulario no se pinta.
	 */
	public function test_sin_token_no_hay_formulario(): void {
		$_GET = array();

		$html = Justificante::render();

		$this->assertStringContainsString( 'no es válido o ha caducado', $html );
		$this->assertStringNotContainsString( '<form', $html );
	}

	/**
	 * El justificante admite PDF e imágenes y nada más (#108).
	 */
	public function test_solo_pdf_e_imagenes(): void {
		foreach ( array( 'justificante.pdf', 'foto.JPG', 'captura.png', 'escaneo.jpeg' ) as $bueno ) {
			$this->assertTrue(
				\Convoca\Members\Justificante::revisar_fichero( array( 'name' => $bueno, 'size' => 1024, 'type' => '' ) ),
				'debería admitir ' . $bueno
			);
		}

		foreach ( array( 'hoja.docx', 'datos.xlsx', 'programa.exe', 'video.mp4', 'sin-extension' ) as $malo ) {
			$this->assertInstanceOf(
				\WP_Error::class,
				\Convoca\Members\Justificante::revisar_fichero( array( 'name' => $malo, 'size' => 1024, 'type' => '' ) ),
				'debería rechazar ' . $malo
			);
		}
	}

	/**
	 * Un fichero que se declara PDF pero dice ser otra cosa no pasa.
	 */
	public function test_el_tipo_declarado_tiene_que_ser_de_los_nuestros(): void {
		$this->assertInstanceOf(
			\WP_Error::class,
			\Convoca\Members\Justificante::revisar_fichero( array( 'name' => 'justificante.pdf', 'size' => 1024, 'type' => 'application/zip' ) )
		);
	}

	/**
	 * El tope son 5 MB, y se mira antes de tocar la biblioteca.
	 */
	public function test_el_tamano_maximo(): void {
		$this->assertTrue(
			\Convoca\Members\Justificante::revisar_fichero( array( 'name' => 'j.pdf', 'size' => \Convoca\Members\Justificante::TAMANO_MAX, 'type' => 'application/pdf' ) ),
			'5 MB justos pasan'
		);
		$this->assertInstanceOf(
			\WP_Error::class,
			\Convoca\Members\Justificante::revisar_fichero( array( 'name' => 'j.pdf', 'size' => \Convoca\Members\Justificante::TAMANO_MAX + 1, 'type' => 'application/pdf' ) )
		);
	}

	/**
	 * Subir el justificante NO da la condición de socio: la ficha queda pendiente de revisión (#107).
	 */
	public function test_el_justificante_no_activa_la_ficha(): void {
		$fuente = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Justificante.php' );

		$this->assertStringNotContainsString( "Estados::change( $member_id, 'activo'", $fuente, 'subir un fichero no puede dar el alta' );
		$this->assertStringContainsString( "_convoca_justificante_estado', 'pendiente'", $fuente );
		$this->assertStringContainsString( 'avisar_revision(', $fuente, 'administración se entera' );
	}
}
