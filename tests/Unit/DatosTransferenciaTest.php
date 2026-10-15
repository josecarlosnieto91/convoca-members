<?php
/**
 * Los datos para transferir, en el correo del alta (gateway#9).
 *
 * DEFECTO QUE FIJA ESTA PRUEBA (medido el 15-16/10/2026): quien elegía «Transferencia bancaria» en el
 * alta recibía un correo de «hemos recibido tu solicitud» **sin ningún dato para transferir** (0 líneas
 * con IBAN en el registro de correos de la demo) y una página de pago que solo ofrecía tarjeta y Bizum.
 * Resultado: la persona no sabía a qué cuenta ingresar y la junta acababa mandando el número por
 * WhatsApp, sin nada que enlazara la transferencia con el alta.
 *
 * El bloque se construye solo cuando la ficha va por transferencia **y** hay IBAN configurado: sin IBAN
 * no se inventa nada (el aviso de que falta va en la pantalla de pago).
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;
use Convoca\Members\Email_Manager;

class DatosTransferenciaTest extends TestCase {

	private const FICHA = 5150;
	private const PAGO  = 5151;

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['_wp_stores']['post_meta']  = array();
		$GLOBALS['_wp_stores']['options']    = array();
		$GLOBALS['_wp_stores']['transients'] = array();
		$GLOBALS['_test_posts']              = array();

		foreach ( array( '/includes/CPT_Miembro.php', '/includes/Email_Manager.php' ) as $f ) {
			$path = dirname( __DIR__, 2 ) . $f;
			if ( file_exists( $path ) ) {
				require_once $path;
			}
		}
	}

	/** El método es privado: se prueba directamente, que es donde está la regla. */
	private function datos( int $id ): string {
		$m = new \ReflectionMethod( Email_Manager::class, 'datos_transferencia' );
		return (string) $m->invoke( null, $id );
	}

	/** Con un pago y el IBAN configurado, el correo lleva a dónde transferir y con qué concepto. */
	public function test_con_transferencia_e_iban_salen_los_datos(): void {
		update_option(
			'convoca_gateway_settings',
			array(
				'iban'        => 'ES91 2100 0418 4502 0005 1332',
				'beneficiary' => 'Asociación de Ejemplo',
			)
		);
		update_post_meta( self::FICHA, '_convoca_metodo_pago', 'transferencia' );
		update_post_meta( self::FICHA, '_convoca_pago_id', self::PAGO );
		update_post_meta( self::PAGO, '_convoca_order_id', '5150515ABC' );

		$bloque = $this->datos( self::FICHA );

		$this->assertStringContainsString( 'ES91 2100 0418 4502 0005 1332', $bloque, 'El IBAN es lo que hace falta para poder ingresar' );
		$this->assertStringContainsString( 'Asociación de Ejemplo', $bloque );
		$this->assertStringContainsString( '5150515ABC', $bloque, 'El concepto tiene que permitir enlazar la transferencia con el alta' );
		$this->assertStringContainsString( 'justificante', $bloque, 'Y decir que se puede adjuntar' );
	}

	/** Si la ficha no va por transferencia, el correo no dice nada de transferencias. */
	public function test_una_ficha_que_no_va_por_transferencia_no_lleva_el_bloque(): void {
		update_option( 'convoca_gateway_settings', array( 'iban' => 'ES91 2100 0418 4502 0005 1332' ) );
		update_post_meta( self::FICHA, '_convoca_metodo_pago', 'tarjeta' );

		$this->assertSame( '', $this->datos( self::FICHA ) );
	}

	/** Sin IBAN configurado NO se inventa: el aviso de que falta va en la pantalla de pago. */
	public function test_sin_iban_no_se_inventa_nada(): void {
		update_option( 'convoca_gateway_settings', array() );
		update_post_meta( self::FICHA, '_convoca_metodo_pago', 'transferencia' );

		$this->assertSame( '', $this->datos( self::FICHA ) );
	}

	/** El correo por defecto ya trae el token, así que una instalación nueva lo tiene. */
	public function test_la_plantilla_por_defecto_lleva_el_token(): void {
		$plantillas = Email_Manager::default_templates();

		$this->assertStringContainsString(
			'{datos_transferencia}',
			(string) ( $plantillas['solicitud_recibida']['body'] ?? '' ),
			'Sin el token en la plantilla, el bloque no se pintaría aunque estuviera calculado'
		);
	}
}
