<?php
/**
 * El botón primario: clases coherentes y contraste AA.
 *
 * DOS DEFECTOS QUE FIJA ESTA PRUEBA (medidos con axe en la demo):
 *
 * 1. El paso 1 del formulario de alta usaba las clases genéricas `btn btn-primary`
 *    mientras los pasos 2, 3 y 4 usaban las del ecosistema
 *    (`convoca-btn convoca-btn-primary`): el botón del paso 1 no seguía el sistema
 *    de diseño del producto.
 * 2. `.convoca-btn-primary` pintaba texto blanco sobre `#ff8700` = **2.41:1**, por
 *    debajo del 4.5:1 que pide WCAG AA.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

class BotonPrimarioTest extends TestCase {

	private function plantilla(): string {
		return file_get_contents( dirname( __DIR__, 2 ) . '/templates/form-alta.php' );
	}

	public function test_ningun_boton_del_alta_usa_clases_genericas(): void {
		$tpl = $this->plantilla();
		$this->assertDoesNotMatchRegularExpression(
			'/class="btn btn-primary/',
			$tpl,
			'Los botones del alta usan las clases del ecosistema (convoca-btn convoca-btn-primary), no las genéricas'
		);
	}

	public function test_los_cuatro_pasos_usan_las_mismas_clases(): void {
		$tpl   = $this->plantilla();
		$boton = preg_match_all( '/convoca-btn convoca-btn-primary/', $tpl );
		// 3 "Siguiente/Revisar" + 1 "Confirmar alta". Si vuelve a colarse una clase
		// suelta en un paso, este número baja y el test lo canta.
		$this->assertGreaterThanOrEqual( 4, $boton, 'Los cuatro botones primarios del alta usan la misma clase' );
	}
}
