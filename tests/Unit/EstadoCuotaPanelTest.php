<?php
/**
 * El estado de la cuota viaja al panel para que el socio sepa hasta cuándo está pagado (members#38).
 *
 * DEFECTO QUE FIJA ESTA PRUEBA (medido el 15/10/2026 con la sesión del socio 2215, cuota pagada hasta
 * 14/10/2027): en «Pagos y Cuotas», la pantalla donde está el botón de renovar, **no había nada que
 * dijera hasta cuándo estaba pagada la cuota**. El socio entusiasta renovaba en octubre creyendo que le
 * tocaba y se le cobraba otra vez un año ya cubierto (o la junta tenía que devolverlo a mano).
 *
 * La fecha y el estado ya existían, pero en «Mis Datos»: la pantalla del botón no los tenía. Ahora los
 * sirve una sola pieza (`estado_cuota_panel`), que usan los dos endpoints.
 *
 * Y `pago_pendiente`: si ya hay un pago esperando, el panel enseña ESE en lugar de generar otro enlace.
 * Dos enlaces abiertos para el mismo periodo es la otra forma de cobrar dos veces sin querer.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

class EstadoCuotaPanelTest extends TestCase {

	private const FICHA = 7901;

	protected function setUp(): void {
		foreach ( array( 'Logger', 'Rest_API' ) as $clase ) {
			$ruta = dirname( __DIR__, 2 ) . '/includes/' . $clase . '.php';
			if ( file_exists( $ruta ) ) {
				require_once $ruta;
			}
		}
		$GLOBALS['_wp_stores']['post_meta'] = array();
	}

	private function ficha( array $metas ): void {
		$GLOBALS['_wp_stores']['post_meta'][ self::FICHA ] = $metas;
	}

	/** Cuota pagada por delante: el panel tiene que poder decir «al día hasta…». */
	public function test_cuota_al_dia(): void {
		$this->ficha(
			array(
				'_convoca_estado_miembro'  => 'activo',
				'_convoca_fecha_renovacion' => gmdate( 'Y-m-d', strtotime( '+1 year' ) ),
				'_convoca_forma_pago'      => 'cuota',
			)
		);

		$cuota = \Convoca\Members\Rest_API::estado_cuota_panel( self::FICHA );

		$this->assertFalse( $cuota['cuota_vencida'], 'Una cuota pagada hasta el año que viene no está vencida' );
		$this->assertNotSame( '', $cuota['fecha_renovacion'], 'Y la fecha viaja al panel para poder enseñarla' );
		$this->assertTrue( $cuota['renovable'] );
	}

	/** Cuota vencida: se dice, y la renovación arranca del vencimiento, no de hoy. */
	public function test_cuota_vencida(): void {
		$this->ficha(
			array(
				'_convoca_estado_miembro'   => 'activo',
				'_convoca_fecha_renovacion' => gmdate( 'Y-m-d', strtotime( '-10 days' ) ),
			)
		);

		$this->assertTrue( \Convoca\Members\Rest_API::estado_cuota_panel( self::FICHA )['cuota_vencida'] );
	}

	/** Sin fecha (socio nuevo, o forma de pago voluntariado): no se inventa un vencimiento. */
	public function test_sin_fecha_no_se_inventa_vencimiento(): void {
		$this->ficha( array( '_convoca_estado_miembro' => 'activo' ) );

		$cuota = \Convoca\Members\Rest_API::estado_cuota_panel( self::FICHA );

		$this->assertSame( '', $cuota['fecha_renovacion'] );
		$this->assertFalse( $cuota['cuota_vencida'] );
	}

	/** Quien colabora sin cuota no renueva: el panel no le ofrece el botón. */
	public function test_voluntariado_no_renueva(): void {
		$this->ficha(
			array(
				'_convoca_estado_miembro'   => 'activo',
				'_convoca_fecha_renovacion' => gmdate( 'Y-m-d', strtotime( '+1 month' ) ),
				'_convoca_forma_pago'       => 'voluntariado',
			)
		);

		$this->assertFalse( \Convoca\Members\Rest_API::estado_cuota_panel( self::FICHA )['renovable'] );
	}

	/** Y los dos endpoints y el panel usan esa pieza (el defecto era que la pantalla del botón no la tenía). */
	public function test_los_dos_endpoints_y_el_panel_la_usan(): void {
		$api = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Rest_API.php' );
		$this->assertGreaterThanOrEqual( 2, substr_count( $api, 'self::estado_cuota_panel(' ), 'La usan «Mis Datos» y «Pagos y Cuotas»' );
		$this->assertStringContainsString( "'cuota' => self::estado_cuota_panel(", $api, 'El endpoint de pagos la devuelve' );

		$js = (string) file_get_contents( dirname( __DIR__, 2 ) . '/public/assets/mi-area.js' );
		$this->assertStringContainsString( 'cuota.fecha_renovacion', $js, 'El panel la pinta' );
		$this->assertStringContainsString( 'Estás al día hasta el', $js, 'Diciendo hasta cuándo está pagado' );
		$this->assertStringContainsString( 'Renovar por adelantado', $js, 'Y que renovar amplía' );
		$this->assertStringContainsString( 'data-amplia', $js, 'Con aviso antes de crear el pago' );
		$this->assertStringContainsString( 'pago_pendiente', $js, 'Y sin generar otro enlace si ya hay un pago esperando' );
	}
}
