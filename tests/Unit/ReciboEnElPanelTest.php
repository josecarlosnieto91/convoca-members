<?php
/**
 * El socio puede descargar el recibo de su cuota desde su panel (gateway#8).
 *
 * MEDIDO EN LA DEMO (informe del issue): en «Pagos y Cuotas» el socio veía fecha, concepto, importe y estado,
 * y **ningún recibo**: ni botón, ni número, ni enlace. Y el recibo existe —el gateway guarda
 * `_convoca_receipt_number` por pago y tiene una URL pública con clave—, así que cuando el correo se
 * perdía había que pedírselo a la junta por WhatsApp: un trámite de dos minutos que costaba dos días.
 *
 * Lo que fija esta prueba:
 *  · el número de recibo viaja al panel;
 *  · el enlace lo construye el gateway (que es quien firma y comprueba la clave), y **solo para pagos
 *    cobrados**: al gateway se le pide por `paid`, porque para un pago pendiente el enlace daría error;
 *  · sin el gateway instalado, el panel no ofrece enlace y no se rompe nada;
 *  · y la tabla del panel tiene su columna con el enlace.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

class ReciboEnElPanelTest extends TestCase {

	protected function setUp(): void {
		foreach ( array( 'Logger', 'Rest_API' ) as $clase ) {
			$ruta = dirname( __DIR__, 2 ) . '/includes/' . $clase . '.php';
			if ( file_exists( $ruta ) ) {
				require_once $ruta;
			}
		}
	}

	/** El número y el enlace viajan en la respuesta de «Pagos y Cuotas». */
	public function test_la_respuesta_trae_el_recibo(): void {
		$api = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Rest_API.php' );

		$this->assertStringContainsString( "'_convoca_receipt_number'", $api, 'El número de recibo' );
		$this->assertStringContainsString( "'recibo'     => \$recibo", $api, 'Y viaja al panel' );
		$this->assertStringContainsString( "'recibo_url' => \$recibo_url", $api, 'Con su enlace' );
	}

	/** El enlace solo para pagos COBRADOS: en uno pendiente, el gateway responde 404. */
	public function test_el_enlace_solo_para_pagos_cobrados(): void {
		$api = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Rest_API.php' );

		$this->assertStringContainsString( "\$recibo_url = ( 'paid' === \$status ) ? self::url_recibo(", $api );
	}

	/** Y el enlace lo pide al gateway, con guarda por si no está: sin gateway no hay enlace, no un fatal. */
	public function test_el_enlace_lo_construye_el_gateway(): void {
		$api = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Rest_API.php' );

		$this->assertMatchesRegularExpression( '/class_exists\(.*Receipt_Generator/', $api, 'Comprueba que el gateway está' );
		$this->assertStringContainsString( "Receipt_Generator::receipt_url( \$pago_id )", $api, 'Y le pide el enlace a él' );
	}

	/** La tabla del panel tiene su columna y su enlace. */
	public function test_el_panel_ensena_la_columna_del_recibo(): void {
		$js = (string) file_get_contents( dirname( __DIR__, 2 ) . '/public/assets/mi-area.js' );

		$this->assertStringContainsString( '<th>Recibo</th>', $js, 'Columna de recibo' );
		$this->assertStringContainsString( 'item.recibo_url', $js, 'Que usa el enlace' );
		$this->assertStringContainsString( 'target="_blank"', $js, 'Y se abre aparte, sin sacar al socio de su panel' );
	}
}
