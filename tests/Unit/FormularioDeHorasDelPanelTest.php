<?php
/**
 * Contrato del formulario de horas del panel (members#57).
 *
 * El botón «Añadir» del registro de horas estaba muerto: el manejador llamaba a `conv.form.validate()`,
 * que no existe en el JS del panel, así que lanzaba un TypeError que se tragaba el listener — sin
 * mensaje, sin error y sin registro. Se comprueba en la fuente para que nadie lo reintroduzca.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

class FormularioDeHorasDelPanelTest extends TestCase {

	/**
	 * El JS sin comentarios: el propio arreglo explica en un comentario qué se llamaba antes, y si no se
	 * quitan, la prueba se pone roja contra su propia explicación.
	 */
	private function js_sin_comentarios(): string {
		$js = (string) file_get_contents( dirname( __DIR__, 2 ) . '/public/assets/mi-area.js' );
		$js = (string) preg_replace( '#/\*.*?\*/#s', '', $js );
		return (string) preg_replace( '#^\s*//.*$#m', '', $js );
	}

	public function test_el_panel_no_llama_a_un_validador_que_no_existe(): void {
		$js = $this->js_sin_comentarios();
		$this->assertStringNotContainsString( 'conv.form.validate', $js, 'Ese validador no existe: lanza TypeError y deja el botón muerto.' );
	}

	public function test_el_formulario_de_horas_valida_con_el_navegador(): void {
		$js = $this->js_sin_comentarios();
		$this->assertStringContainsString( 'checkValidity', $js );
		$this->assertStringContainsString( "e.target.id === 'conv-hours-form'", $js, 'El formulario de horas se maneja por delegación: si se renombra, revisar el listener.' );
	}

	public function test_el_progreso_del_certificado_no_imprime_undefined(): void {
		$js = (string) file_get_contents( dirname( __DIR__, 2 ) . '/public/assets/mi-area.js' );
		$this->assertStringNotContainsString( "' / ' + cert.progress.objetivo + ' horas (' + pct + '%)'", $js, 'Sin horas la API no manda porcentaje: hay que calcularlo o poner 0.' );
	}
}
