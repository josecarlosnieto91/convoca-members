<?php
/**
 * Los formularios no saltan de nivel de encabezado (issue #13).
 *
 * Medido con axe-core en la demo: en el alta de socio los planes iban en `<h4>` dentro de un `<h2>`, así que se
 * saltaba el `h3`; en el formulario de voluntariado se empezaba en `<h3>` sin `h2` antes. Es una violación
 * **moderada**: no rompe el uso, pero desorienta a quien navega con lector de pantalla — que es exactamente el
 * motivo de que existan los niveles.
 *
 * Se comprueba sobre las **plantillas**: los niveles tienen que subir de uno en uno. Bajar no es un salto (una
 * sección nueva puede empezar en un nivel más alto), así que solo se vigilan las subidas.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

class EncabezadosSinSaltosTest extends TestCase {

	/** @return array<int, string> */
	private function plantillas(): array {
		return array( 'templates/form-alta.php', 'templates/form-voluntariado.php' );
	}

	public function test_los_niveles_suben_de_uno_en_uno(): void {
		$raiz = dirname( __DIR__, 2 );
		$malos = array();

		foreach ( $this->plantillas() as $ruta ) {
			$html     = (string) file_get_contents( $raiz . '/' . $ruta );
			$niveles  = array();
			$anterior = null;

			if ( preg_match_all( '/<h([1-6])\b/i', $html, $m ) ) {
				foreach ( $m[1] as $linea => $nivel ) {
					$nivel = (int) $nivel;
					if ( null !== $anterior && $nivel > $anterior + 1 ) {
						$malos[] = "{$ruta}: «h{$anterior}» -> «h{$nivel}»";
					}
					$anterior = $nivel;
					$niveles[] = $nivel;
				}
			}

			$this->assertNotEmpty( $niveles, "{$ruta}: no se ha encontrado ningún encabezado" );
		}

		$this->assertSame( array(), $malos, 'Encabezados que saltan de nivel: ' . implode( ' · ', $malos ) );
	}

	public function test_ningun_encabezado_queda_en_h4_o_mas(): void {
		$raiz = dirname( __DIR__, 2 );
		foreach ( $this->plantillas() as $ruta ) {
			$html = (string) file_get_contents( $raiz . '/' . $ruta );
			$this->assertSame( 0, preg_match_all( '/<h[456]\b/i', $html ), "{$ruta}: con una jerarquía de dos niveles no hacen falta encabezados más profundos" );
		}
	}
}
