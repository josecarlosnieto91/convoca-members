<?php
/**
 * Las piezas deterministas del certificado de horas.
 *
 * El certificado se genera una vez y se manda por correo: lo que aquí se prueba es lo que **no** se ve hasta
 * que alguien abre el PDF —cuántos años vale, cómo se escribe la validez, qué proyectos entran, y qué pasa si
 * el QR no se puede generar—, más el aviso de error que se le enseña a quien lo intentó. El PDF en sí
 * (`render_pdf_to_buffer`) depende de la librería del sitio y no se toca aquí.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Certificate_Generator;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Certificate_Generator.php';

/**
 * Validez, QR y HTML del certificado.
 */
final class CertificateGeneratorPiezasTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->limpiar();
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$this->limpiar();
		unset( $GLOBALS['__members_filtros'] );
	}

	/**
	 * Deja el arnés como estaba.
	 */
	private function limpiar(): void {
		foreach ( array( '_wp_stores', '_test_posts', '__members_filtros' ) as $clave ) {
			unset( $GLOBALS[ $clave ] );
		}

		$GLOBALS['_wp_stores'] = array(
			'options'    => array(),
			'post_meta'  => array(),
			'transients' => array(),
			'user_meta'  => array(),
		);
	}

	/**
	 * La validez por defecto es un año, y nunca menos.
	 */
	public function test_la_validez(): void {
		$this->assertSame( 1, Certificate_Generator::validity_years(), 'un año por defecto (D13)' );

		add_filter( 'convoca_members_certificado_validez_anios', static fn() => 3 );
		$this->assertSame( 3, Certificate_Generator::validity_years(), 'el sitio puede pedir más' );
		remove_all_filters( 'convoca_members_certificado_validez_anios' );

		add_filter( 'convoca_members_certificado_validez_anios', static fn() => 0 );
		$this->assertSame( 1, Certificate_Generator::validity_years(), 'cero o menos no vale: un año' );
		remove_all_filters( 'convoca_members_certificado_validez_anios' );
	}

	/**
	 * La fecha de caducidad se calcula desde hoy, no desde el certificado anterior.
	 */
	public function test_la_caducidad(): void {
		$caduca = $this->llamar( 'validity_expiry_mysql' );
		$esperado = gmdate( 'Y', time() + YEAR_IN_SECONDS );

		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $caduca );
		$this->assertStringContainsString( $esperado, $caduca, 'un año por delante' );
	}

	/**
	 * Aprobar horas nuevas renueva la validez **sólo si ya había certificado**.
	 */
	public function test_renovar_la_validez_al_aprobar_horas(): void {
		$GLOBALS['_test_posts'][55] = (object) array(
			'ID'         => 55,
			'post_type'  => 'miembro',
			'post_title' => 'Ada Lovelace',
		);

		$GLOBALS['_wp_stores']['post_meta'][55] = array();

		Certificate_Generator::refresh_validity_on_new_hours( 9, 55 );
		$this->assertArrayNotHasKey( '_convoca_certificado_valido_hasta', $GLOBALS['_wp_stores']['post_meta'][55], 'sin certificado no hay nada que renovar' );

		$GLOBALS['_wp_stores']['post_meta'][55]['_convoca_certificado_id'] = 'CERT-2026-0001';
		Certificate_Generator::refresh_validity_on_new_hours( 9, 55 );

		$this->assertArrayHasKey( '_convoca_certificado_emitido', $GLOBALS['_wp_stores']['post_meta'][55] );
		$this->assertArrayHasKey( '_convoca_certificado_valido_hasta', $GLOBALS['_wp_stores']['post_meta'][55] );
	}

	/**
	 * El aviso de error se enseña una vez y se borra (no se queda pegado en todas las pantallas).
	 */
	public function test_el_aviso_de_error(): void {
		$this->assertSame( '', $this->pintar_aviso(), 'sin error no se pinta nada' );

		set_transient( 'convoca_cert_gen_error_' . get_current_user_id(), 'La librería de PDF no responde' );

		$html = $this->pintar_aviso();
		$this->assertStringContainsString( 'Error en la generación del Certificado', $html );
		$this->assertStringContainsString( 'La librería de PDF no responde', $html );
		$this->assertStringContainsString( 'convoca-alert--danger', $html );

		$this->assertSame( '', $this->pintar_aviso(), 'y a la segunda ya no está' );
	}

	/**
	 * El QR se genera local (sin servicio externo) y si no se puede, cae en un texto legible.
	 */
	public function test_el_qr(): void {
		$svg = $this->llamar( 'build_qr_svg', 'https://example.test/verificar/abc' );

		if ( class_exists( '\chillerlan\QRCode\QRCode' ) ) {
			$this->assertStringStartsWith( '<img src="data:image/png;base64,', $svg, 'QR local en el propio HTML' );
			$this->assertStringNotContainsString( 'chillerlan', $svg, 'sin depender de una API externa' );
		} else {
			$this->assertStringContainsString( 'https://example.test/verificar/abc', $svg, 'fallback: la URL legible' );
		}
	}

	/**
	 * El QR lleva la URL de verificación tal cual (no un id suelto).
	 */
	public function test_los_datos_del_qr(): void {
		$this->assertSame( 'https://example.test/v/1', $this->llamar( 'generate_qr_data', 'https://example.test/v/1' ) );
	}

	/**
	 * El HTML del certificado: nombre, horas, proyectos y la validez escrita.
	 */
	public function test_el_html_del_certificado(): void {
		$html = $this->llamar(
			'build_html',
			'Ada Lovelace',
			12.5,
			'Socia',
			array(
				array(
					'titulo' => 'Huerto comunitario',
					'horas'  => 8.5,
					'tareas' => array( 'Siembra', 'Riego' ),
				),
				array(
					'titulo' => 'Sin tareas',
					'horas'  => 4,
					'tareas' => array(),
				),
			),
			'CERT-2026-0001',
			'https://example.test/v/1',
			'https://example.test/verificar/CERT-2026-0001',
			'2027-11-05 10:00:00',
			'light'
		);

		$this->assertStringContainsString( 'Ada Lovelace', $html );
		$this->assertStringContainsString( 'Huerto comunitario', $html );
		$this->assertStringContainsString( 'Siembra. Riego', $html, 'las tareas se resumen en una línea' );
		$this->assertStringContainsString( 'Sin descripción', $html, 'un proyecto sin tareas lo dice' );
		$this->assertStringContainsString( 'CERT-2026-0001', $html, 'el identificador del certificado' );
		$this->assertStringContainsString( 'class="qr"', $html, 'y su hueco para el QR' );
	}

	/**
	 * El tema se valida: lo que no sea `light` o `dark` no entra.
	 */
	public function test_el_tema_del_certificado(): void {
		update_option( 'convoca_document_theme', 'light' );
		$render = fn( string $tema ): string => $this->llamar( 'build_html', 'Ada', 1.0, 'Socia', array(), 'C-1', 'q', 'u', '', $tema );

		$claro  = $render( 'light' );
		$oscuro = $render( 'dark' );

		$this->assertNotSame( $claro, $oscuro, 'el tema cambia los estilos' );
		$this->assertSame( $claro, $render( '<script>' ), 'un tema inventado cae en el del sitio' );
		$this->assertStringNotContainsString( '<script>', $render( '<script>' ), 'y no se cuela en el HTML' );
	}

	/**
	 * Llama a un método privado del generador.
	 *
	 * @param string $metodo     Método.
	 * @param mixed  ...$args    Argumentos.
	 * @return mixed
	 */
	private function llamar( string $metodo, ...$args ) {
		$reflejo = new \ReflectionMethod( Certificate_Generator::class, $metodo );

		return $reflejo->invoke( null, ...$args );
	}

	/**
	 * Pinta los avisos del panel y devuelve el HTML.
	 *
	 * @return string
	 */
	private function pintar_aviso(): string {
		ob_start();
		Certificate_Generator::show_pdf_error_notice();

		return (string) ob_get_clean();
	}
}
