<?php
/**
 * La condición de monitor se concede desde el editor de la ficha (issue #30, fase 4).
 *
 * El monitor no sale de ningún plan ni de una inscripción: es la única condición que se reparte a mano, así
 * que tiene que haber una vía en la interfaz y tiene que pasar por `Roles_Miembro` (que es quien mantiene el
 * espejo del rol de WordPress sumando, no reemplazando).
 *
 * Se comprueba sobre el código porque el arnés no renderiza pantallas de administración; la comprobación de
 * verdad es la de la demo (marcar la casilla y ver la condición puesta y el rol espejado).
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

class MonitorDesdeEditorTest extends TestCase {

	private function codigo(): string {
		$ruta = dirname( __DIR__, 2 ) . '/admin/class-admin-member-editor.php';
		$this->assertFileExists( $ruta );

		return file_get_contents( $ruta );
	}

	private function handle_save(): string {
		$codigo = $this->codigo();
		$ini    = strpos( $codigo, 'public function handle_save(' );
		$this->assertNotFalse( $ini );

		return substr( $codigo, $ini );
	}

	public function test_el_formulario_trae_la_casilla_y_su_marca_de_presencia(): void {
		$codigo = $this->codigo();
		$this->assertStringContainsString( 'name="convoca_es_monitor"', $codigo, 'Falta la casilla de monitor' );
		$this->assertStringContainsString(
			'name="convoca_monitor_control"',
			$codigo,
			'Sin marca de presencia, un formulario que no traiga la casilla quitaría la condición'
		);
	}

	public function test_guardar_concede_la_condicion_por_roles_miembro(): void {
		$this->assertStringContainsString(
			'Roles_Miembro::anadir( $post_id, Roles_Miembro::MONITOR )',
			$this->handle_save(),
			'La condición tiene que pasar por Roles_Miembro: es quien espeja el rol de WordPress'
		);
	}

	public function test_solo_un_administrador_puede_concederla(): void {
		$this->assertStringContainsString(
			"current_user_can( 'manage_options' )",
			$this->handle_save(),
			'Un monitor no reparte su propio rol'
		);
	}

	public function test_sin_la_marca_no_se_toca_la_condicion(): void {
		$this->assertStringContainsString(
			"! empty( \$data['convoca_monitor_control'] )",
			$this->handle_save(),
			'La marca de presencia tiene que condicionar el cambio'
		);
	}
}
