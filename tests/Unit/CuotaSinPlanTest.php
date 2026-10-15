<?php
/**
 * Una cuota pagada no puede dejar la ficha «a medias» (members#39).
 *
 * DEFECTO QUE FIJA ESTA PRUEBA (medido en la demo, 16/10/2026):
 *
 * El listener marcaba la cuota como «activa» —y guardaba método, pago y forma— **antes** de comprobar
 * que el plan de la ficha existe. Con un plan inexistente (ficha sembrada a mano, plan renombrado,
 * alta antigua) salía por ahí y dejaba la ficha con la cuota pagada, **sin fecha de renovación y sin
 * la condición de socio**, y en silencio: el único rastro era una línea del registro.
 *
 * Estado reproducido en la demo antes del arreglo, con una ficha de plan inexistente:
 * `cuota='activa'` · `renovación=''` · `es_socio=false`.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;
use Convoca\Members\Payment_Listener;

class CuotaSinPlanTest extends TestCase {

	private const ID = 4242;

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['_wp_stores']['post_meta']  = array();
		$GLOBALS['_wp_stores']['options']    = array();
		$GLOBALS['_wp_stores']['transients'] = array();
		$GLOBALS['_test_posts']              = array();

		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Miembro.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Payment_Listener.php';

		$ficha               = new \stdClass();
		$ficha->ID           = self::ID;
		$ficha->post_type    = 'miembro';
		$ficha->post_status  = 'publish';
		$ficha->post_title   = 'Ficha de prueba';

		$GLOBALS['_test_posts'][ self::ID ] = $ficha;
	}

	private function paga( int $pago_id ): void {
		( new Payment_Listener() )->on_payment_completed( $pago_id, 'members', self::ID, array( 'method' => 'prueba' ) );
	}

	/** Plan inexistente: la cuota NO se marca, no hay renovación y no se da por aplicado el pago. */
	public function test_un_plan_inexistente_no_deja_la_ficha_a_medias(): void {
		update_post_meta( self::ID, '_convoca_plan', 'plan-que-no-existe' );
		update_post_meta( self::ID, '_convoca_estado_miembro', 'activo' );

		$this->paga( 9999992 );

		$this->assertSame(
			'',
			(string) get_post_meta( self::ID, '_convoca_estado_cuota', true ),
			'La cuota no puede quedar «activa» si no se ha podido aplicar el pago'
		);
		$this->assertSame(
			'',
			(string) get_post_meta( self::ID, '_convoca_fecha_renovacion', true ),
			'Sin renovación aplicada no puede quedar una fecha'
		);
		$this->assertSame(
			'',
			(string) get_post_meta( self::ID, '_convoca_pago_aplicado_id', true ),
			'Un pago que no se ha aplicado no puede figurar como aplicado'
		);
	}

	/** Y queda anotado donde la junta lo lee: el campo «Incidencias» de la ficha. */
	public function test_la_cuota_sin_plan_queda_anotada_en_la_ficha(): void {
		update_post_meta( self::ID, '_convoca_plan', 'plan-que-no-existe' );

		$this->paga( 9999994 );

		$incidencia = (string) get_post_meta( self::ID, 'incidencias', true );
		$this->assertStringContainsString( 'plan-que-no-existe', $incidencia, 'El aviso tiene que decir QUÉ plan falta' );
		$this->assertStringContainsString( '9999994', $incidencia, 'Y con qué pago, para poder rastrearlo' );
		$this->assertStringContainsString( 'cuota', $incidencia );
	}

	/** El aviso se AÑADE: no pisa lo que haya escrito una persona en ese campo. */
	public function test_la_incidencia_se_anade_sin_pisar_lo_escrito(): void {
		$previo = 'Hablé con la socia el 3/10: prefiere pagar en efectivo en la sede.';
		update_post_meta( self::ID, '_convoca_plan', 'plan-que-no-existe' );
		update_post_meta( self::ID, 'incidencias', $previo );

		$this->paga( 9999995 );

		$incidencia = (string) get_post_meta( self::ID, 'incidencias', true );
		$this->assertStringContainsString( $previo, $incidencia, 'Lo que había escrito una persona no se pisa' );
		$this->assertNotSame( $previo, $incidencia, 'Y el aviso nuevo tiene que quedar añadido' );
	}
}
