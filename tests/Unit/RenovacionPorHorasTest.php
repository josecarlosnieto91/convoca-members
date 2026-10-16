<?php
/**
 * El socio ve y usa la renovación por horas (members#45).
 *
 * MEDIDO EN LA DEMO (ficha 2215): 20 h aprobadas con plan Bronce (15 h) — su próxima renovación YA le salía
 * gratis — y su panel solo le ofrecía pagar 30 €. La regla estaba bien implementada en el cron, pero vivía
 * dentro de `check_volunteer_cycle()`, así que el panel no podía contar nada sin repetirla.
 *
 * Lo que fija esta prueba:
 *  · una sola fuente del ciclo (`CPT_Miembro::ciclo_voluntariado()`), usada por el cron Y por el panel;
 *  · el panel recibe las horas del plan y del ciclo, y si ya cumple;
 *  · renovar sin pago vuelve a comprobar las horas **en el servidor** antes de escribir;
 *  · y la junta lo ve en su listado.
 *
 * El comportamiento se mide **en vivo** (necesita registros de horas reales y fechas): está en el issue.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

class RenovacionPorHorasTest extends TestCase {

	private function leer( string $rel ): string {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/' . $rel );
	}

	/** Una sola fuente: la regla no puede volver a vivir en dos sitios. */
	public function test_la_regla_del_ciclo_tiene_una_sola_fuente(): void {
		$cpt = $this->leer( 'includes/CPT_Miembro.php' );

		$this->assertStringContainsString( 'public static function ciclo_voluntariado( int $post_id ): array', $cpt );
		$this->assertStringContainsString( 'public static function renovar_por_horas( int $post_id, string $origen', $cpt );
		$this->assertStringContainsString( '$ciclo = self::ciclo_voluntariado( $post_id );', $cpt, 'El cron usa la fuente común' );
		$this->assertStringContainsString( "return self::renovar_por_horas( \$post_id, 'cron' );", $cpt, 'Y renueva por el mismo camino' );
		$this->assertSame(
			1,
			substr_count( $cpt, "get_horas_aprobadas_desde( \$post_id" ),
			'Las horas se cuentan en UN solo sitio (ciclo_voluntariado): si aparece otra vez, la regla se ha duplicado'
		);
	}

	/** El panel recibe las horas; sin esto el socio no puede saber nada. */
	public function test_el_panel_recibe_las_horas(): void {
		$api = $this->leer( 'includes/Rest_API.php' );

		$this->assertStringContainsString( "'horas_plan'         => \$ciclo['objetivo'],", $api );
		$this->assertStringContainsString( "'horas_ciclo'        => \$ciclo['horas'],", $api );
		$this->assertStringContainsString( "'horas_faltan'       => \$ciclo['faltan'],", $api );
		$this->assertStringContainsString( "'renueva_por_horas'  => \$ciclo['cumple'],", $api );
		$this->assertStringContainsString( 'CPT_Miembro::ciclo_voluntariado( $member_id )', $api );
	}

	/** Renovar sin pago no se fía del navegador: vuelve a contar las horas. */
	public function test_renovar_sin_pago_comprueba_las_horas_en_el_servidor(): void {
		$api = $this->leer( 'includes/Rest_API.php' );

		$this->assertStringContainsString( "'/me/renovar-por-horas'", $api );
		$this->assertStringContainsString( "CPT_Miembro::renovar_por_horas( \$member_id, 'panel' )", $api );
		$this->assertStringContainsString( "Todavía no has llegado a las horas de tu plan", $api, 'Y lo dice claro cuando no llega' );

		$cpt = $this->leer( 'includes/CPT_Miembro.php' );
		$this->assertStringContainsString( "if ( ! \$ciclo['cumple'] ) {\n\t\t\treturn false;", str_replace( "\r", "", $cpt ), 'La guarda vive en el método que renueva, no en el botón' );
	}

	/** Y el panel lo enseña: barra, mensaje y botón. */
	public function test_el_panel_lo_ensena(): void {
		$js = $this->leer( 'public/assets/mi-area.js' );

		$this->assertStringContainsString( 'cuota.horas_plan > 0', $js );
		$this->assertStringContainsString( 'Llevas ${cuota.horas_ciclo} h de las ${cuota.horas_plan} h de tu plan: tu próxima renovación es sin pagar.', $js );
		$this->assertStringContainsString( 'Te faltan ${cuota.horas_faltan} h para que tu próxima renovación sea sin pagar', $js );
		$this->assertStringContainsString( "id=\"conv-btn-renovar-horas\"", $js );
		$this->assertStringContainsString( "renovarPorHoras: function(btn)", $js );
		$this->assertStringContainsString( "+ '/me/renovar-por-horas'", $js );
	}

	/** La junta lo ve en su listado. */
	public function test_la_junta_lo_ve_en_el_listado(): void {
		$lista = $this->leer( 'admin/class-admin-list.php' );

		$this->assertStringContainsString( "'renueva_horas'  => __( 'Renueva por horas'", $lista );
		$this->assertStringContainsString( 'public function column_renueva_horas( $item ): string', $lista );
		$this->assertStringContainsString( 'CPT_Miembro::ciclo_voluntariado( (int) $item->ID )', $lista );
	}
}
