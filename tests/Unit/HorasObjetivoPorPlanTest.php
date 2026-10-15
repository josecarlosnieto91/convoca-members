<?php
/**
 * El umbral de horas para renovar sale del PLAN de cada socio (issue #30).
 *
 * Decisión de JC (15/10/2026): «depende del plan elegido, cada uno debe tener unas horas de voluntariado
 * asociadas». Antes de esto, el umbral podía quedarse sin comprobar cuando el plan no estaba en la
 * configuración, y no había forma de saber qué horas pide cada plan sin leer el código.
 *
 * La prueba recorre los planes REALES (no una lista escrita a mano) y comprueba que el umbral de cada ficha
 * es el de su plan: así, si alguien vuelve a poner una cifra global, cae sola.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;
use Convoca\Members\CPT_Miembro;
use Convoca\Members\Voluntariado_Manager;

class HorasObjetivoPorPlanTest extends TestCase {

	private const ID = 6161;

	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['_wp_stores']['post_meta'] = array();
		$GLOBALS['_wp_stores']['user_meta'] = array();
		$GLOBALS['_wp_stores']['options']   = array();
	}

	/**
	 * Deja la ficha con un plan.
	 *
	 * @param string $plan Clave del plan.
	 * @return void
	 */
	private function con_plan( string $plan ): void {
		\update_post_meta( self::ID, '_convoca_plan', $plan );
	}

	public function test_el_umbral_es_el_de_cada_plan(): void {
		$planes = CPT_Miembro::get_plans();
		$this->assertNotEmpty( $planes, 'No hay planes: la prueba no está midiendo nada' );

		$comparados = 0;
		foreach ( $planes as $clave => $plan ) {
			$horas = isset( $plan['hours'] ) ? (float) $plan['hours'] : 0.0;
			if ( $horas <= 0 ) {
				continue;
			}

			$this->con_plan( (string) $clave );
			$this->assertSame(
				$horas,
				Voluntariado_Manager::horas_objetivo( self::ID ),
				sprintf( 'El plan «%s» pide %s horas y el umbral devuelve otra cosa', (string) $clave, (string) $horas )
			);
			++$comparados;
		}

		$this->assertGreaterThan( 0, $comparados, 'Ningún plan trae horas: no se ha comprobado nada' );
	}

	public function test_un_plan_desconocido_usa_el_objetivo_por_defecto(): void {
		$this->con_plan( 'plan-que-no-existe' );
		$this->assertSame( 20.0, Voluntariado_Manager::horas_objetivo( self::ID ) );

		$otro = static function (): float {
			return 7.5;
		};
		add_filter( 'convoca_members_horas_objetivo_defecto', $otro );
		$this->assertSame( 7.5, Voluntariado_Manager::horas_objetivo( self::ID ) );
		remove_filter( 'convoca_members_horas_objetivo_defecto', $otro );
	}

	public function test_sin_plan_no_hay_ciclo_que_cumplir(): void {
		$this->assertSame( 0.0, Voluntariado_Manager::horas_objetivo( self::ID ) );
	}
}
