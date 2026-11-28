<?php
/**
 * La misma tarifa al renovar desde el panel y desde el cron (members#137).
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\CPT_Miembro;
use PHPUnit\Framework\TestCase;

final class PrecioRenovacionTest extends TestCase {

	/**
	 * Prepara planes y ficha.
	 */
	protected function setUp(): void {
		$GLOBALS['_wp_stores']['options']['convoca_members_plans'] = array(
			'bronze' => array(
				'label' => 'Bronce',
				'price' => 30.0,
				'hours' => 15,
			),
		);
		$GLOBALS['_wp_stores']['post_meta'][55] = array(
			'_convoca_plan'          => 'bronze',
			'_convoca_importe_cuota' => 10.0,
		);
	}

	/**
	 * Se cobra la tarifa vigente, no el importe viejo de la ficha.
	 */
	public function test_la_subida_de_tarifa_llega_a_las_dos_vias(): void {
		$this->assertSame( 30.0, CPT_Miembro::precio_renovacion( 55 ) );
	}

	/**
	 * Un sitio puede conservar una tarifa con el filtro.
	 */
	public function test_el_filtro_permite_una_diferencia_expresa(): void {
		$filtro = static function () {
			return 12.5;
		};
		add_filter( 'convoca_members_precio_renovacion', $filtro );

		$this->assertSame( 12.5, CPT_Miembro::precio_renovacion( 55 ) );

		remove_filter( 'convoca_members_precio_renovacion', $filtro );
	}

	/**
	 * Panel y cron usan la misma fuente.
	 */
	public function test_panel_y_cron_usan_la_misma_fuente(): void {
		$rest  = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Rest_API.php' );
		$cron  = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Cron_Manager.php' );
		$panel = (string) file_get_contents( dirname( __DIR__, 2 ) . '/public/class-mi-area.php' );

		$this->assertStringContainsString( 'CPT_Miembro::precio_renovacion( (int) $member_id )', $rest );
		$this->assertStringContainsString( 'CPT_Miembro::precio_renovacion( $member_id )', $cron );
		$this->assertStringContainsString( 'CPT_Miembro::precio_renovacion( (int) $member_id )', $panel );
	}
}
