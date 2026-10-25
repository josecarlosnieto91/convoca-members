<?php
/**
 * Pasarela de mentira para las pruebas del cron de socios.
 *
 * `Features::is_gateway_active()` mira `class_exists( '\Convoca\Gateway\Payment_Handler' )`, y una clase declarada en
 * PHP **no se descarga**. Por eso este fichero se carga con `require_once` **dentro** de una prueba marcada
 * `#[RunInSeparateProcess]`: el hijo vive con la pasarela activa y el resto de la suite no se entera.
 *
 * Los dobles copian el contrato real:
 *  - `build_payment_url( int $pago_id, string $token = '', ?int $expires_ts = null ): string`
 *  - `convoca_gateway_create_payment( array $args ): array{ pago_id: int }` o `WP_Error`
 *
 * Lo que se apunta está en `$GLOBALS['_gw_urls']` y `$GLOBALS['_gw_creados']`.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Gateway;

/**
 * Manejador de pagos de mentira.
 */
class Payment_Handler {

	/**
	 * URL de pago.
	 *
	 * @param int         $pago_id    ID del pago.
	 * @param string      $token      Token del enlace.
	 * @param int|null    $expires_ts Caducidad.
	 * @return string
	 */
	public static function build_payment_url( int $pago_id, string $token = '', ?int $expires_ts = null ): string {
		$GLOBALS['_gw_urls'][] = array(
			'pago_id' => $pago_id,
			'token'   => $token,
			'expires' => $expires_ts,
		);

		return 'https://pago.example.test/' . $pago_id . '?k=' . $token;
	}
}

/**
 * Crea un pago (doble del servicio real).
 *
 * @param array $args Argumentos del pago.
 * @return array|\WP_Error
 */
function convoca_gateway_create_payment( array $args ) {
	$GLOBALS['_gw_creados'][] = $args;

	return array( 'pago_id' => 900 + count( $GLOBALS['_gw_creados'] ) );
}
