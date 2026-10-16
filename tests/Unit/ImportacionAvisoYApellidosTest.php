<?php
/**
 * La importación avisa según lo que pasó de verdad y trae los apellidos (members#44).
 *
 * MEDIDO EN LA DEMO el 15/10/2026 con un CSV de tres filas mezcladas (una buena, una con un DNI que ya
 * existe y una sin email), por la pantalla real:
 *
 *   1 socios importados correctamente. 2 errores: …      ← cartel VERDE de éxito
 *
 * Lo importante ya estaba bien: importa las filas buenas, sigue con las malas y dice qué fila falla y por
 * qué. Pero **verde significa «todo bien»** para quien no es técnico, así que se va sin leer los errores.
 * Y «1 socios» no es castellano.
 *
 * Lo segundo: el mapeo ofrece siete campos (nombre, email, dni, plan, teléfono, dirección, municipio) y
 * **no había campo de apellidos**, aunque la ficha los tiene (`_convoca_apellidos`) y el carnet y los
 * documentos los usan: un censo con la columna «apellidos» no tenía dónde meterlos.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

class ImportacionAvisoYApellidosTest extends TestCase {

	private function fuente(): string {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/admin/class-admin-import-csv.php' );
	}

	/** El cartel: verde solo si no hubo errores; aviso si hubo filas fallidas; rojo si no entró ninguna. */
	public function test_el_cartel_depende_del_resultado(): void {
		$f = $this->fuente();

		$this->assertStringContainsString( "\$estado   = ( \$imported > 0 ) ? 'parcial' : 'error';", $f );
		$this->assertStringContainsString( "if ( empty( \$errors ) ) {", $f );
		$this->assertStringContainsString( "\$estado = 'ok';", $f );
		$this->assertStringContainsString( "'convoca-alert--warning'", $f, 'Con filas fallidas es un aviso, no un éxito' );
		$this->assertStringContainsString( "'convoca-alert--danger'", $f, 'Sin nada importado, rojo' );
		$this->assertStringContainsString( "&import_status=", $f, 'El estado viaja con el mensaje' );
	}

	/** Y el texto: singular/plural de verdad, y los errores contados como filas. */
	public function test_el_texto_concuerda(): void {
		$f = $this->fuente();

		$this->assertStringContainsString( "_n( '%d socio importado correctamente.', '%d socios importados correctamente.', \$imported", $f );
		$this->assertStringContainsString( "_n( '%d fila con errores:', '%d filas con errores:', count( \$errors )", $f );
		$this->assertStringNotContainsString( "__ns( '%d socios importados", $f );
	}

	/** Los apellidos: en el mapeo, en las cabeceras, en lo que se guarda y en la ayuda. */
	public function test_los_apellidos_se_pueden_importar(): void {
		$f = $this->fuente();

		$this->assertStringContainsString( "'apellidos' => 'Apellidos'", $f, 'El mapeo tiene que ofrecerlo' );
		$this->assertStringContainsString( "\$headers = array( 'nombre', 'apellidos', ", $f, 'Y estar entre las columnas esperadas' );
		$this->assertStringContainsString( "\$meta_map = array( 'apellidos', ", $f, 'Y guardarse como `_convoca_apellidos`' );
		$this->assertStringContainsString( 'nombre, apellidos, email, dni', $f, 'Y decirlo en la ayuda de la pantalla' );
	}
}
