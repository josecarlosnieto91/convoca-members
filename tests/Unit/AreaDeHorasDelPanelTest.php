<?php
/**
 * Contrato del área de horas del panel (members#55).
 *
 * A una cuenta sin voluntariado se le ofrecía el área entera: niveles, formulario e historial. Y el
 * progreso del certificado imprimía «undefined / 15 horas (undefined%)».
 *
 * La señal para saber si alguien es voluntario no es «estado vacío»: `estado()` devuelve «solicitud»
 * por defecto incluso para quien no ha pedido nada. Lo que distingue es haber pasado el circuito
 * (entrevista/activo/desvinculado) o tener horas registradas.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

class AreaDeHorasDelPanelTest extends TestCase {

	private function js(): string {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/public/assets/mi-area.js' );
	}

	private function rest(): string {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Rest_API.php' );
	}

	public function test_las_horas_dicen_el_estado_del_voluntariado(): void {
		$this->assertStringContainsString( "'voluntariado' => \\Convoca\\Members\\Circuito_Voluntariado::estado( \$member_id )", $this->rest() );
	}

	public function test_el_panel_comprueba_el_estado_antes_de_ofrecer_el_area(): void {
		$js = $this->js();
		$this->assertStringContainsString( 'estadosDeVoluntario', $js );
		$this->assertStringContainsString( "'entrevista', 'activo', 'desvinculado'", $js );
		$this->assertStringContainsString( 'Todavía no eres voluntario/a', $js );
	}

	public function test_el_progreso_del_certificado_no_imprime_undefined(): void {
		$js = $this->js();
		$this->assertStringNotContainsString( "cert.progress.total + ' / '", $js, 'El total venía sin definir: hay que usar el valor ya calculado.' );
	}
}
