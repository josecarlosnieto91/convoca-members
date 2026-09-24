<?php
/**
 * Reglas del modelo 2026-09: la cuota del primer año se abona siempre y la
 * renovación por horas es una vía alternativa a partir del SEGUNDO ciclo.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;
use Convoca\Members\CPT_Miembro;

class CuotaPrimerAnoTest extends TestCase {

	private const ID = 77;

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['_wp_stores']['post_meta']  = array();
		$GLOBALS['_wp_stores']['options']    = array();
		$GLOBALS['_wp_stores']['transients'] = array();
		foreach ( array( '/includes/CPT_Miembro.php', '/includes/Estados.php' ) as $f ) {
			$path = dirname( __DIR__, 2 ) . $f;
			if ( file_exists( $path ) ) {
				require_once $path;
			}
		}
	}

	/** `puede_renovar_por_horas()` es privada: es la regla, así que se prueba directamente. */
	private function puede( int $id, string $estado, string $vencimiento ): bool {
		$m = new \ReflectionMethod( CPT_Miembro::class, 'puede_renovar_por_horas' );
		$m->setAccessible( true );
		return (bool) $m->invoke( null, $id, $estado, $vencimiento );
	}

	/** Prepara un socio con el ciclo vencido: `meses_alta` marca cuándo se dio de alta. */
	private function socio_vencido( string $meses_alta, string $plan, string $estado = 'activo' ): string {
		$vencimiento = gmdate( 'Y-m-d', strtotime( '-1 day' ) );
		update_post_meta( self::ID, '_convoca_fecha_alta', gmdate( 'Y-m-d', strtotime( "-{$meses_alta} months" ) ) );
		update_post_meta( self::ID, '_convoca_fecha_renovacion', $vencimiento );
		update_post_meta( self::ID, '_convoca_plan', $plan );
		update_post_meta( self::ID, '_convoca_estado_miembro', $estado );
		update_post_meta( self::ID, '_convoca_estado_cuota', 'activa' );
		return $vencimiento;
	}

	/** El primer ciclo se paga: no cabe renovar por horas. */
	public function test_primer_ciclo_no_admite_horas(): void {
		$venc = $this->socio_vencido( '12', 'gold' );
		$this->assertFalse(
			$this->puede( self::ID, 'activo', $venc ),
			'El vencimiento del primer año debe exigir la cuota, no horas.'
		);
	}

	/** A partir del segundo ciclo, las horas son una vía válida. */
	public function test_segundo_ciclo_admite_horas(): void {
		$venc = $this->socio_vencido( '30', 'gold' );
		$this->assertTrue(
			$this->puede( self::ID, 'activo', $venc ),
			'Un socio con más de un ciclo debe poder renovar por horas.'
		);
	}

	/** Un plan sin objetivo de horas solo se renueva pagando. */
	public function test_plan_sin_horas_no_admite_la_via_de_voluntariado(): void {
		$venc = $this->socio_vencido( '30', 'familiar' );
		$this->assertFalse( $this->puede( self::ID, 'activo', $venc ) );
	}

	/** El mínimo sale del plan, no de un valor fijo en el código. */
	public function test_el_minimo_sale_del_plan(): void {
		$venc = $this->socio_vencido( '30', 'gold' );
		$this->assertTrue( $this->puede( self::ID, 'activo', $venc ) );
		$planes = CPT_Miembro::get_plans();
		$this->assertSame( 50.0, (float) $planes['gold']['hours'], 'El plan de 100 € exige 50 h.' );
		$this->assertSame( 15.0, (float) $planes['bronze']['hours'], 'El plan de 30 € exige 15 h.' );
	}

	/** Solo se evalúa a quien está activo y con el ciclo ya vencido. */
	public function test_no_aplica_a_quien_no_esta_activo_ni_antes_del_vencimiento(): void {
		$venc = $this->socio_vencido( '30', 'gold', 'suspendido' );
		$this->assertFalse( $this->puede( self::ID, 'suspendido', $venc ) );

		$futuro = gmdate( 'Y-m-d', strtotime( '+1 year' ) );
		update_post_meta( self::ID, '_convoca_estado_miembro', 'activo' );
		update_post_meta( self::ID, '_convoca_fecha_alta', gmdate( 'Y-m-d', strtotime( '-30 months' ) ) );
		$this->assertFalse(
			$this->puede( self::ID, 'activo', $futuro ),
			'Un ciclo que aún no ha vencido no se evalúa.'
		);
	}
}
