<?php
/**
 * El ajuste de enlaces del panel: de texto a lista y de vuelta.
 *
 * El formulario guarda una línea por enlace («Etiqueta | URL»). Lo que se prueba aquí es que una línea
 * incompleta no se guarda, que una URL inválida no entra y que lo guardado se vuelve a pintar en el
 * formulario tal cual, sin perder nada.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Admin_Settings;
use PHPUnit\Framework\TestCase;

/**
 * Contrato del ajuste de enlaces del panel.
 */
class PanelLinksSettingTest extends TestCase {

	/**
	 * Carga la clase de ajustes.
	 */
	protected function setUp(): void {
		require_once dirname( __DIR__, 2 ) . '/admin/class-admin-settings.php';
	}

	/**
	 * Invoca un método privado de los ajustes.
	 *
	 * @param string $metodo Nombre.
	 * @param mixed  $arg    Argumento.
	 * @return mixed
	 */
	private function privado( string $metodo, mixed $arg ): mixed {
		$reflejo = new \ReflectionMethod( Admin_Settings::class, $metodo );

		return $reflejo->invoke( null, $arg );
	}

	/**
	 * Las líneas válidas se convierten en enlaces; las incompletas se descartan.
	 */
	public function test_convierte_lineas_en_enlaces_y_descarta_lo_incompleto(): void {
		$texto = "Mis turnos | https://ejemplo.test/turnos/\n"
			. "Mi perfil | https://ejemplo.test/mi-perfil/\n"
			. "sin separador\n"
			. " | https://ejemplo.test/sin-etiqueta/\n"
			. "Sin URL |\n";

		$enlaces = $this->privado( 'parse_panel_links', $texto );

		$this->assertCount( 2, $enlaces );
		$this->assertSame( 'Mis turnos', $enlaces[0]['label'] );
		$this->assertSame( 'https://ejemplo.test/turnos/', $enlaces[0]['url'] );
	}

	/**
	 * Una URL que no es URL no entra.
	 */
	public function test_una_url_invalida_no_se_guarda(): void {
		$enlaces = $this->privado( 'parse_panel_links', 'Cosa | javascript:alert(1)' );

		$this->assertSame( array(), $enlaces );
	}

	/**
	 * Lo guardado vuelve al formulario en el mismo formato.
	 */
	public function test_lo_guardado_vuelve_al_formulario(): void {
		$texto = $this->privado(
			'panel_links_a_texto',
			array(
				array( 'label' => 'Turnos', 'url' => 'https://ejemplo.test/turnos/' ),
				array( 'label' => 'Perfil', 'url' => 'https://ejemplo.test/perfil/' ),
			)
		);

		$this->assertSame( "Turnos | https://ejemplo.test/turnos/\nPerfil | https://ejemplo.test/perfil/", $texto );
	}

	/**
	 * Sin enlaces guardados el formulario sale vacío, no con la palabra «Array».
	 */
	public function test_sin_enlaces_el_formulario_sale_vacio(): void {
		$this->assertSame( '', $this->privado( 'panel_links_a_texto', array() ) );
	}
}
