<?php
/**
 * El paso 2 del alta señala QUÉ falta (members#43), incluido el caso del tutor/a legal.
 *
 * MEDIDO EN LA DEMO el 16/10/2026, con el asistente real en el navegador:
 *
 *  · Caso adulto (un campo vacío, el DNI): ya funcionaba — el campo se marcaba, su mensaje se veía y
 *    salía el aviso general. **El informe del issue no reproducía** en este camino.
 *  · Caso MENOR DE EDAD (bloque del tutor visible y vacío): NO se marcaba ningún campo, no había ningún
 *    aviso junto al tutor y no se movía el foco. Medido:
 *        bloqueTutorVisible: block · camposTutor: vacíos
 *        camposMarcados: [] · mensajesVisibles: [] · «NO hay aviso junto al tutor»
 *    El asistente se quedaba parado sin decir nada de lo que faltaba, que es exactamente lo que describe
 *    el issue. **Causa**: los campos del tutor no eran `required` ni tenían aviso propio, así que la
 *    validación los saltaba y solo ponía `ok = false`.
 *
 * El JS del formulario no tiene arnés de pruebas en este repo (se mide en el navegador); aquí se vigila el
 * contrato de los dos ficheros para que no vuelva a perderse el aviso del tutor.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

class ValidacionPaso2Test extends TestCase {

	private function plantilla(): string {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/templates/form-alta.php' );
	}

	private function js(): string {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/js/convoca-members-public.js' );
	}

	/** Los campos del tutor tienen su propio aviso (es lo que faltaba para poder marcarlos). */
	public function test_los_campos_del_tutor_tienen_aviso(): void {
		$html = $this->plantilla();

		$this->assertStringContainsString( 'Hace falta el nombre de tu tutor/a legal.', $html );
		$this->assertStringContainsString( 'Hace falta el DNI de tu tutor/a legal.', $html );

		// Y NO son `required`: solo hacen falta si quien se apunta es menor. Marcarlos obligatorios
		// bloquearía el alta de cualquier adulto (el navegador no dejaría enviar el formulario).
		$this->assertDoesNotMatchRegularExpression(
			'/id="conv-tutor-(nombre|dni)"[^>]*required/',
			$html,
			'Los campos del tutor no pueden ser `required` en el HTML'
		);
	}

	/** La validación del paso 2 mete los campos del tutor en la lista cuando es menor. */
	public function test_el_tutor_entra_en_la_validacion_cuando_es_menor(): void {
		$js = $this->js();

		$this->assertStringContainsString( "Array.from(conv.\$\$('input[required]', stepEl))", $js );
		$this->assertStringContainsString( "obligatorios.push(tutorNombre);", $js );
		$this->assertStringContainsString( "obligatorios.push(tutorDni);", $js );
		$this->assertStringContainsString( 'if (age !== null && age < 18) {', $js, 'Solo se les pide a los menores' );
	}

	/** Y cada fallo se marca, se anuncia y lleva el foco al primero (lo que el issue pedía). */
	public function test_marca_avisa_y_lleva_el_foco(): void {
		$js = $this->js();

		$this->assertStringContainsString( "field.classList.toggle('has-error', falta)", $js, 'El campo se marca en rojo (y su aviso se ve)' );
		$this->assertStringContainsString( "f.setAttribute('aria-invalid', 'true')", $js, 'Y se anuncia a quien usa lector de pantalla' );
		$this->assertStringContainsString( 'primerFallo.focus()', $js, 'Con el foco en el primero que falta' );
		$this->assertStringContainsString( 'Revisa los campos marcados', $js, 'Y un aviso que dice qué mirar' );
	}
}
