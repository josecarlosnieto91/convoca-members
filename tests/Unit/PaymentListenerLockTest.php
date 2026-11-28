<?php
/**
 * El cobro y el cron comparten el cerrojo de la ficha (members#136).
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Payment_Listener;
use PHPUnit\Framework\TestCase;

final class PaymentListenerLockTest extends TestCase {

	/**
	 * Prepara la ficha.
	 */
	protected function setUp(): void {
		$GLOBALS['_wp_cron']['agendados'] = array();
		$ficha                            = new \WP_Post();
		$ficha->ID                        = 55;
		$ficha->post_type                 = 'miembro';
		$ficha->post_status               = 'publish';
		$GLOBALS['_wp_stores']['posts'][55] = $ficha;
	}

	/**
	 * Si el cron tiene el cerrojo, el pago se reprograma y no se aplica a medias.
	 */
	public function test_si_el_cron_tiene_la_ficha_el_pago_se_reintenta(): void {
		$this->assertNotFalse( \Convoca\Core\Utils::acquire_lock( 'convoca_autorenewal_55', 300 ) );

		$listener = new Payment_Listener();
		$listener->on_payment_completed( 900, 'members', 55, array( 'method' => 'tarjeta' ) );

		$hooks = array_column( $GLOBALS['_wp_cron']['agendados'], 'hook' );
		$this->assertContains( 'convoca_members_reintentar_pago', $hooks, 'no se pierde el pago' );
		$this->assertSame( '', (string) get_post_meta( 55, '_convoca_pago_aplicado_id', true ), 'no se aplica a medias' );

		\Convoca\Core\Utils::release_lock( 'convoca_autorenewal_55' );
	}

	/**
	 * El cerrojo se libera incluso si el procesamiento termina por una salida temprana.
	 */
	public function test_el_cerrojo_se_libera_en_finally(): void {
		$src = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Payment_Listener.php' );

		$this->assertStringContainsString( "'convoca_autorenewal_' . (int) \$origin_id", $src );
		$this->assertStringContainsString( '} finally {', $src );
		$this->assertStringContainsString( 'release_lock( $member_lock )', $src );
	}
}
