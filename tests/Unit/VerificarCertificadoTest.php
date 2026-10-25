<?php
/**
 * La página pública de verificación de certificados.
 *
 * `public/class-verificar-certificado.php` estaba al 0 % (48 statements). Es la única pantalla de members
 * que ve **cualquiera** con un código, y enseña el nombre de una persona: lo que se fija aquí es que sin
 * código válido no se enseñe nada, que el caducado se distinga del vigente, que la fecha de validez se
 * calcule aunque el certificado sea anterior a la política D13 y que el nombre vaya escapado.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Verificar_Certificado;
use PHPUnit\Framework\TestCase;

class VerificarCertificadoTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		require_once dirname( __DIR__, 2 ) . '/includes/Texto_PDF.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Voluntariado_Manager.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Certificate_Generator.php';
		require_once dirname( __DIR__, 2 ) . '/public/class-verificar-certificado.php';

		$GLOBALS['_wp_stores']         = array( 'options' => array(), 'transients' => array(), 'acciones' => array(), 'shortcodes' => array() );
		$GLOBALS['_wp_shortcodes']     = array();
		$GLOBALS['convoca_test_posts'] = array();
		$GLOBALS['convoca_test_meta']  = array();
		$_GET                          = array();
		$_POST                         = array();
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores'] = array();
		unset( $GLOBALS['convoca_test_posts'], $GLOBALS['convoca_test_meta'], $_GET, $_POST );
	}

	/**
	 * Pinta el shortcode y devuelve su HTML.
	 *
	 * @return string
	 */
	private function pintar(): string {
		$vista = new Verificar_Certificado();

		ob_start();
		echo $vista->render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya viene escapado del producto.
		return (string) ob_get_clean();
	}

	/**
	 * Siembra un certificado emitido a un socio.
	 *
	 * @param string $cert_id      Código del certificado.
	 * @param string $nombre       Nombre del socio.
	 * @param string $valido_hasta Fecha de validez ('' para certificado antiguo sin fecha).
	 * @param string $emitido      Fecha de emisión.
	 */
	private function certificado( string $cert_id, string $nombre, string $valido_hasta = '', string $emitido = '2025-01-15 10:00:00' ): void {
		$id = 77;

		// `get_post()`/`get_post_meta()` son los del arnés del NÚCLEO (members carga el suyo primero),
		// así que se siembran SUS almacenes, no los de este fichero.
		$GLOBALS['_wp_stores']['posts'][ $id ] = (object) array(
			'ID'           => $id,
			'post_type'    => 'convoca_miembro',
			'post_status'  => 'publish',
			'post_title'   => $nombre,
			'post_content' => '',
		);
		$GLOBALS['_wp_stores']['post_meta'][ $id ]['_convoca_certificado_id']            = $cert_id;
		$GLOBALS['_wp_stores']['post_meta'][ $id ]['_convoca_certificado_emitido']       = $emitido;
		if ( '' !== $valido_hasta ) {
			$GLOBALS['_wp_stores']['post_meta'][ $id ]['_convoca_certificado_valido_hasta'] = $valido_hasta;
		}

		// `verify()` busca el socio por la meta del certificado (devuelve el id) y las horas por SQL
		// (una suma aparte, que se responde por el mapa para no devolver el id como si fueran horas).
		$GLOBALS['_wp_stores']['get_var']      = (string) $id;
		$GLOBALS['_wp_stores']['get_var_mapa'] = array( 'SUM(CAST(meta_value' => '12.5' );
	}

	/**
	 * El formulario se pinta siempre: es la forma de pedir el código.
	 */
	public function test_el_formulario_se_pinta_siempre(): void {
		$html = $this->pintar();

		$this->assertStringContainsString( 'conv-verify-container', $html );
		$this->assertStringContainsString( 'name="certificado_id"', $html, 'el campo para el código está' );
		$this->assertStringContainsString( 'Verificar Ahora', $html );
		$this->assertStringNotContainsString( 'Certificado Válido', $html, 'sin código no se afirma nada' );
		$this->assertStringNotContainsString( 'Certificado Caducado', $html );
	}

	/**
	 * Un código que no existe no enseña ningún dato.
	 */
	public function test_un_codigo_inexistente_no_enseña_datos(): void {
		$_GET['id'] = 'VOL-2025-NOEXISTE';

		$html = $this->pintar();

		$this->assertStringContainsString( 'Certificado No Encontrado', $html );
		$this->assertStringNotContainsString( 'Certificado Válido', $html );
		$this->assertStringNotContainsString( '12,5', $html, 'sin certificado no se enseña ni las horas' );
	}

	/**
	 * Un certificado vigente enseña nombre, horas, emisión y validez.
	 */
	public function test_un_certificado_vigente_enseña_sus_datos(): void {
		$this->certificado( 'VOL-2025-AAAAA', 'Ana Pérez', gmdate( 'Y-m-d H:i:s', time() + 86400 * 30 ) );
		$_POST['certificado_id'] = 'VOL-2025-AAAAA';

		$html = $this->pintar();

		$this->assertStringContainsString( 'Certificado Válido', $html );
		$this->assertStringContainsString( 'Ana Pérez', $html );
		$this->assertStringContainsString( 'VOL-2025-AAAAA', $html, 'se repite el código para que se pueda comprobar' );
		$this->assertStringContainsString( 'Válido hasta:', $html, 'con fecha de validez se enseña el plazo' );
		$this->assertStringContainsString( 'convoca-alert--ok', $html );
	}

	/**
	 * Un certificado caducado se distingue del vigente.
	 */
	public function test_un_certificado_caducado_se_avisa(): void {
		$this->certificado( 'VOL-2025-BBBBB', 'Luis Gómez', '2020-01-01 00:00:00' );
		$_GET['id'] = 'VOL-2025-BBBBB';

		$html = $this->pintar();

		$this->assertStringContainsString( 'Certificado Caducado', $html );
		$this->assertStringContainsString( 'convoca-alert--warning', $html );
		$this->assertStringNotContainsString( 'Certificado Válido', $html );
	}

	/**
	 * Un certificado antiguo sin fecha de validez se calcula desde la emisión (política D13).
	 */
	public function test_un_certificado_antiguo_calcula_su_validez(): void {
		// Emitido hace un año y sin meta de validez: sigue vigente y se enseña el plazo calculado.
		$this->certificado( 'VOL-2025-CCCCC', 'Marta Ruiz', '', gmdate( 'Y-m-d H:i:s', time() - 86400 * 30 ) );
		$_GET['id'] = 'VOL-2025-CCCCC';

		$html = $this->pintar();

		$this->assertStringContainsString( 'Certificado Válido', $html );
		$this->assertStringContainsString( 'Válido hasta:', $html, 'la fecha se calcula aunque no esté guardada' );
	}

	/**
	 * El nombre de la persona va escapado: es texto que escribe un tercero.
	 */
	public function test_el_nombre_va_escapado(): void {
		$this->certificado( 'VOL-2025-DDDDD', '<script>alert(1)</script>' );
		$_GET['id'] = 'VOL-2025-DDDDD';

		$html = $this->pintar();

		$this->assertStringNotContainsString( '<script>alert(1)</script>', $html, 'un nombre no puede inyectar HTML' );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	/**
	 * El código buscado se recuerda en el campo.
	 */
	public function test_el_codigo_buscado_se_recuerda_en_el_campo(): void {
		$_GET['id'] = 'VOL-2025-EEEEE';

		$html = $this->pintar();

		$this->assertMatchesRegularExpression( '/name="certificado_id"[^>]*value="VOL-2025-EEEEE"/s', $html );
	}
}
