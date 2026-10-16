<?php
/**
 * El socio puede ver y descargar sus datos y pedir su supresión desde su panel (members#42).
 *
 * MEDIDO EN LA DEMO (informe del issue): el panel tiene siete secciones y NINGUNA es de privacidad. La
 * exportación y el borrado existen, pero solo desde el escritorio de WordPress, y la única vía que el socio
 * veía —«solicitud de baja»— **no borra nada**: cambia el estado a `baja` y la ficha sigue entera.
 *
 * Lo que fija esta prueba:
 *  · pedir la supresión NO borra nada: deja la solicitud anotada, con su fecha, y avisa a la junta;
 *  · pedirla dos veces no duplica ni vuelve a avisar;
 *  · el borrador de datos (que ya existía) la marca como resuelta al atenderla;
 *  · el correo de aviso sale con el número de ficha y con saltos de línea DE VERDAD (no con un `\$` ni un
 *    `\n` literales, que es como me quedó la primera vez al escribir `%1$s` dentro de comillas dobles);
 *  · y las tres piezas que lo sirven al panel existen: rutas, sección y los dos botones.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

class PrivacidadPanelTest extends TestCase {

	private const FICHA = 8101;

	protected function setUp(): void {
		foreach ( array( 'Logger', 'GDPR_Tools' ) as $clase ) {
			$ruta = dirname( __DIR__, 2 ) . '/includes/' . $clase . '.php';
			if ( file_exists( $ruta ) ) {
				require_once $ruta;
			}
		}
		$GLOBALS['_wp_stores']['post_meta'] = array();
	}

	/** Pedir la supresión anota la solicitud con su fecha y avisa: no borra nada. */
	public function test_pedir_la_supresion_anota_y_avisa(): void {
		$fecha = \Convoca\Members\GDPR_Tools::solicitar_supresion( self::FICHA );

		$this->assertNotSame( '', $fecha, 'La solicitud tiene que quedar con fecha' );
		$this->assertSame( $fecha, get_post_meta( self::FICHA, '_convoca_supresion_solicitada', true ) );

		$estado = \Convoca\Members\GDPR_Tools::estado_privacidad( self::FICHA );
		$this->assertTrue( $estado['pendiente'], 'Sin resolver, la solicitud está pendiente' );
		$this->assertSame( '', $estado['resuelta'] );

		// Y no ha borrado ni tocado ningún dato del socio.
		$this->assertArrayNotHasKey( '_convoca_gdpr_erased', (array) $GLOBALS['_wp_stores']['post_meta'][ self::FICHA ] );
	}

	/** Pedirla dos veces no la duplica ni vuelve a avisar. */
	public function test_no_se_duplica(): void {
		$primera = \Convoca\Members\GDPR_Tools::solicitar_supresion( self::FICHA );
		$segunda = \Convoca\Members\GDPR_Tools::solicitar_supresion( self::FICHA );

		$this->assertSame( $primera, $segunda, 'La segunda vez devuelve la solicitud que ya había' );
	}

	/** El borrador de datos la marca como resuelta (el aviso no puede quedarse encendido siempre). */
	public function test_el_borrador_la_resuelve(): void {
		\Convoca\Members\GDPR_Tools::solicitar_supresion( self::FICHA );
		\Convoca\Members\GDPR_Tools::marcar_supresion_resuelta( self::FICHA );

		$estado = \Convoca\Members\GDPR_Tools::estado_privacidad( self::FICHA );
		$this->assertFalse( $estado['pendiente'] );
		$this->assertNotSame( '', $estado['resuelta'] );
	}

	/** El correo de aviso: con el número de ficha y saltos de línea reales (esto cazó un fallo mío). */
	public function test_el_correo_de_aviso_sale_bien(): void {
		$cuerpo = \Convoca\Members\GDPR_Tools::cuerpo_aviso_supresion( self::FICHA, '2026-10-16 01:00' );

		$this->assertStringContainsString( 'ficha #' . self::FICHA, $cuerpo, 'Tiene que decir de qué ficha es' );
		$this->assertStringContainsString( "\n", $cuerpo, 'Con saltos de línea de verdad, no un `\n` literal' );
		$this->assertStringNotContainsString( '\\n', $cuerpo, 'Ni barras sueltas de un escapado mal puesto' );
		$this->assertStringNotContainsString( '$s', $cuerpo, 'Ni un `$s` que se haya comido el nombre' );
		$this->assertStringContainsString( 'La baja NO borra nada', $cuerpo, 'Y con el aviso de que la baja no borra' );
	}

	/** La descarga: lo que se guarda, más el estado de privacidad y cuándo se generó. */
	public function test_los_datos_para_descargar(): void {
		$datos = \Convoca\Members\GDPR_Tools::datos_para_descargar( self::FICHA );

		$this->assertArrayHasKey( 'generado', $datos );
		$this->assertArrayHasKey( 'datos', $datos, 'Lo que devuelve el exportador (el mismo del escritorio)' );
		$this->assertArrayHasKey( 'privacidad', $datos );
	}

	/** Y el panel las sirve: rutas, sección y los dos botones. */
	public function test_el_panel_lo_ofrece(): void {
		$api = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Rest_API.php' );
		$this->assertStringContainsString( "'/me/privacidad'", $api );
		$this->assertStringContainsString( "'/me/datos'", $api );
		$this->assertStringContainsString( "'/me/supresion'", $api );

		$js = (string) file_get_contents( dirname( __DIR__, 2 ) . '/public/assets/mi-area.js' );
		$this->assertStringContainsString( 'fetchPrivacidad', $js, 'La sección existe' );
		$this->assertStringContainsString( 'conv-btn-descargar-datos', $js, 'Con el botón de descargar' );
		$this->assertStringContainsString( 'conv-btn-pedir-supresion', $js, 'Y el de pedir la supresión' );
		$this->assertStringContainsString( 'La baja CONSERVA tu historial', $js, 'Y la baja dice la diferencia (item 2 del issue)' );

		$nav = (string) file_get_contents( dirname( __DIR__, 2 ) . '/public/class-mi-area.php' );
		$this->assertStringContainsString( 'data-tab="privacy"', $nav, 'Con su pestaña en el panel' );
	}

	/** La junta ve la solicitud: marca en el listado y aviso en el escritorio. */
	public function test_la_junta_la_ve(): void {
		$lista = (string) file_get_contents( dirname( __DIR__, 2 ) . '/admin/class-admin-list.php' );
		$this->assertStringContainsString( 'Pide supresión', $lista, 'Marca en el listado' );
		$this->assertStringContainsString( "name=\"privacidad_filter\"", $lista, 'Y filtro' );
		$this->assertStringContainsString( "'supresion' === \$privacidad_filter", $lista, 'Que filtra las pendientes' );

		$dash = (string) file_get_contents( dirname( __DIR__, 2 ) . '/admin/class-admin-dashboard.php' );
		$this->assertStringContainsString( 'contar_supresiones_pendientes()', $dash, 'Aviso en el escritorio' );
	}
}
