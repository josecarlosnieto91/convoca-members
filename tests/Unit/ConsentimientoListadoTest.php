<?php
/**
 * El consentimiento se puede ver, filtrar y registrar después del alta (members#41).
 *
 * DEFECTO QUE FIJA ESTA PRUEBA (medido en la demo el 15/10/2026): el consentimiento se guardaba bien
 * —fecha, versión, texto e IP— pero **solo había una puerta de entrada**, `GDPR_Tools::log_consent()`
 * llamada únicamente desde el alta. De 13 fichas, solo 6 tenían constancia, y no había ni forma de saber
 * quién faltaba ni de apuntar el consentimiento de quien lo dio en papel o por correo. El RGPD pide
 * poder **demostrarlo**.
 *
 * Se prueban las dos piezas que se pueden probar sin WordPress de verdad —la etiqueta que se enseña y la
 * cuenta de quién falta— y se vigila el cableado del resto sobre la fuente: la columna, el filtro, el
 * botón con su nonce y su comprobación de permiso, y el aviso del escritorio. La prueba funcional en vivo
 * está medida en el issue.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

class ConsentimientoListadoTest extends TestCase {

	private const FICHA = 7801;

	protected function setUp(): void {
		foreach ( array( 'Logger', 'GDPR_Tools' ) as $clase ) {
			$ruta = dirname( __DIR__, 2 ) . '/includes/' . $clase . '.php';
			if ( file_exists( $ruta ) ) {
				require_once $ruta;
			}
		}
		$GLOBALS['_wp_stores']['post_meta'] = array();
	}

	/** Sin constancia: se dice que no, no se inventa una fecha. */
	public function test_sin_consentimiento_la_etiqueta_lo_dice(): void {
		$etiqueta = \Convoca\Members\GDPR_Tools::etiqueta_consentimiento( self::FICHA );

		$this->assertFalse( $etiqueta['registrado'] );
		$this->assertSame( '', $etiqueta['fecha'] );
		$this->assertSame( '', $etiqueta['version'] );
	}

	/** Con constancia: la fecha y la versión del texto que aceptó. */
	public function test_con_consentimiento_la_etiqueta_trae_fecha_y_version(): void {
		$GLOBALS['_wp_stores']['post_meta'][ self::FICHA ] = array(
			'_convoca_consent_timestamp' => '2026-10-15 21:55:51',
			'_convoca_consent_version'   => '1.2',
		);

		$etiqueta = \Convoca\Members\GDPR_Tools::etiqueta_consentimiento( self::FICHA );

		$this->assertTrue( $etiqueta['registrado'] );
		$this->assertSame( '2026-10-15 21:55:51', $etiqueta['fecha'] );
		$this->assertSame( '1.2', $etiqueta['version'] );
	}

	/** La cuenta de quién falta mira solo a los ACTIVOS y solo a los que no tienen constancia. */
	public function test_la_cuenta_de_quien_falta(): void {
		$fuente = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/GDPR_Tools.php' );

		$this->assertStringContainsString( "'_convoca_estado_miembro'", $fuente );
		$this->assertStringContainsString( "'_convoca_consent_timestamp'", $fuente );
		$this->assertStringContainsString( "'compare' => 'NOT EXISTS'", $fuente, 'Los que faltan son los que no tienen la marca de consentimiento' );
	}

	/** El listado tiene la columna, el filtro y el botón, y el botón va con nonce y permiso. */
	public function test_el_listado_ensena_y_registra(): void {
		$fuente = (string) file_get_contents( dirname( __DIR__, 2 ) . '/admin/class-admin-list.php' );

		$this->assertStringContainsString( "'consentimiento' => __( 'Consentimiento', 'convoca-members' )", $fuente, 'Tiene que haber columna' );
		$this->assertStringContainsString( 'column_consentimiento', $fuente, 'Y su renderizador' );
		$this->assertStringContainsString( 'name="consentimiento_filter"', $fuente, 'Y filtro' );
		$this->assertStringContainsString( '\'compare\' => ( \'con\' === $consentimiento_filter ) ? \'EXISTS\' : \'NOT EXISTS\'', $fuente, 'El filtro separa con/sin' );
		$this->assertStringContainsString( 'wp_nonce_url(', $fuente, 'El botón lleva nonce' );
		$this->assertStringContainsString( 'current_user_can( \'edit_post\', $miembro )', $fuente, 'Y comprueba permiso antes de escribir' );
		$this->assertStringContainsString( 'check_admin_referer( \'convoca_consentimiento_\' . $miembro )', $fuente, 'Y lo verifica al guardar' );
		$this->assertStringContainsString( 'log_consent(', $fuente, 'El botón escribe el consentimiento con la misma función del alta' );
	}

	/** Y el escritorio avisa mientras haya socios sin constancia. */
	public function test_el_escritorio_avisa(): void {
		$fuente = (string) file_get_contents( dirname( __DIR__, 2 ) . '/admin/class-admin-dashboard.php' );

		$this->assertStringContainsString( 'contar_sin_consentimiento()', $fuente );
		$this->assertStringContainsString( 'consentimiento_filter=sin', $fuente, 'El aviso lleva al listado ya filtrado' );
	}
}
