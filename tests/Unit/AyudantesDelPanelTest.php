<?php
/**
 * Contrato de los ayudantes del panel del socio (members#56).
 *
 * El panel llama a `this.esc()` desde cuatro sitios, pero sólo existían `escAttr` y `escHtml`: la
 * pestaña de Pagos reventaba con «TypeError: this.esc is not a function` en cuanto un pago tenía
 * recibo, y el panel entero decía «Error cargando pagos.». Esta prueba vigila que todo `this.X()`
 * que se usa en mi-area.js tenga su definición en el mismo objeto.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

class AyudantesDelPanelTest extends TestCase {

	private function fuente(): string {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/public/assets/mi-area.js' );
	}

	public function test_todo_this_llamado_en_el_panel_esta_definido(): void {
		$fuente = $this->fuente();

		preg_match_all( '/this\.([A-Za-z_$][A-Za-z0-9_$]*)\s*\(/', $fuente, $usos );
		preg_match_all( '/^\s*([A-Za-z_$][A-Za-z0-9_$]*)\s*:\s*function/m', $fuente, $defs );

		$definidos = array_flip( $defs[1] );
		// Propiedades y métodos que vienen de fuera o son de JS.
		$externos = array( 'addEventListener', 'querySelector', 'querySelectorAll', 'forEach', 'map', 'filter',
			'find', 'push', 'slice', 'join', 'replace', 'split', 'click', 'then', 'catch', 'toFixed', 'trim',
			'getElementById', 'appendChild', 'contains', 'focus', 'scrollIntoView', 'removeAttribute',
			'setAttribute', 'closest', 'preventDefault', 'stopPropagation', 'toString', 'toLowerCase', 'includes' );

		$faltan = array();
		foreach ( array_unique( $usos[1] ) as $metodo ) {
			if ( isset( $definidos[ $metodo ] ) || in_array( $metodo, $externos, true ) ) {
				continue;
			}
			$faltan[] = $metodo;
		}

		$this->assertSame( array(), array_values( array_unique( $faltan ) ), 'Se usan sin definir: ' . implode( ', ', array_unique( $faltan ) ) );
	}

	public function test_esc_escapa_lo_minimo(): void {
		$fuente = $this->fuente();
		$this->assertStringContainsString( 'esc: function(s)', $fuente );
		$this->assertMatchesRegularExpression( '/esc:\s*function\(s\)\s*\{\s*return[^}]*replace\(\/&\/g/', $fuente );
	}
}
