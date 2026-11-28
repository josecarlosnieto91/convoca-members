<?php
/**
 * La renovación manual reutiliza el pago pendiente y respeta los métodos del plan (members#128).
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

final class RenewalPaymentPolicyTest extends TestCase {

	/**
	 * Una sola llamada a crear pago, después de buscar el pendiente.
	 */
	public function test_reutiliza_el_pago_pendiente(): void {
		$src = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Rest_API.php' );

		$this->assertStringContainsString( '$pendiente = $this->pago_pendiente_renovacion( (int) $member_id )', $src );
		$this->assertStringContainsString( "'reused'      => true", $src );
		$this->assertStringContainsString( 'self::url_pago_pendiente( $pendiente )', $src );
	}

	/**
	 * El método no está fijado a tarjeta: sale de `payment_methods`.
	 */
	public function test_el_metodo_sale_del_plan(): void {
		$src = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Rest_API.php' );

		$this->assertStringContainsString( "(array) ( \$plan_data['payment_methods'] ?? array() )", $src );
		$this->assertStringContainsString( "in_array( 'tarjeta', \$metodos, true ) ? 'tarjeta' : ( \$metodos[0] ?? 'tarjeta' )", $src );
		$this->assertStringContainsString( "'method'       => \$metodo", $src );
	}
}
