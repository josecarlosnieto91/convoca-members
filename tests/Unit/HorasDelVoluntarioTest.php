<?php
/**
 * El voluntario puede apuntar sus horas y no le exigen un proyecto que no existe (members#37).
 *
 * CAUSA RAÍZ MEDIDA (16/10/2026, tras un diagnóstico mío equivocado que dejo escrito aquí): el formulario
 * «Añadir Nuevo Registro» y su ruta (`POST /me/horas` → `Rest_API::submit_hours()`) **ya existían y
 * funcionaban**. Lo que impedía anotar era que `Hours_Manager::validate_hours_data()` **exigía un proyecto**
 * («Debes seleccionar un proyecto»), y el trabajo voluntario del issue —repartir carteles, llevar la
 * contabilidad, arreglar el local— no pertenece a ningún proyecto: el selector del formulario iba `required`
 * y sin proyecto no había forma humana de apuntar nada.
 *
 * (Primero di por hecho que la ruta solo aceptaba GET y añadí un POST duplicado con otro manejador. Al medir
 * resultó que `submit_hours` ya estaba: el duplicado se retiró. Por eso esta prueba fija lo que de verdad
 * estaba mal, y también que el manejador de la ruta EXISTE: era la otra trampa posible.)
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

class HorasDelVoluntarioTest extends TestCase {

	private function leer( string $rel ): string {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/' . $rel );
	}

	/** El panel y la ruta se entienden… y el manejador de la ruta existe. */
	public function test_el_panel_la_ruta_y_el_manejador_se_entienden(): void {
		$js  = $this->leer( 'public/assets/mi-area.js' );
		$api = $this->leer( 'includes/Rest_API.php' );

		$this->assertStringContainsString( "+ '/me/horas', {\n        method: 'POST'", $js, 'El panel envía un POST a /me/horas' );

		$pos = strpos( $api, "'/me/horas'," );
		$this->assertNotFalse( $pos );
		$bloque = substr( $api, $pos, 900 );
		$this->assertStringContainsString( "'methods'             => 'POST'", $bloque, 'La ruta acepta POST' );
		$this->assertStringContainsString( 'submit_hours', $bloque, 'Y llama a un manejador que existe' );
		$this->assertStringContainsString( 'public function submit_hours( \\WP_REST_Request $request )', $api, 'El manejador está definido: una ruta que apunta a un método inexistente es un 500 esperando' );
		$this->assertSame( 1, substr_count( $bloque, "'methods'             => 'POST'" ), 'Un solo POST en la ruta: un duplicado sombrea al primero y confunde' );
	}

	/** El proyecto es opcional para el socio y obligatorio para la junta. */
	public function test_el_proyecto_es_opcional_para_el_socio(): void {
		$manager = $this->leer( 'includes/Hours_Manager.php' );

		$this->assertStringContainsString( 'public static function save_hours_record( array $data, bool $is_admin = false )', $manager );
		$this->assertStringContainsString( '$validation = self::validate_hours_data( $data, ! $is_admin );', $manager, 'El socio (is_admin = false) puede ir sin proyecto' );
		$this->assertStringContainsString( 'public static function validate_hours_data( array $data, bool $proyecto_opcional = false )', $manager );
		$this->assertStringContainsString( "if ( ! \$proyecto_opcional && empty( \$data['proyecto_id'] ) ) {", $manager, 'La junta sigue exigiéndolo' );
	}

	/** Y el formulario ya no obliga a elegir proyecto. */
	public function test_el_formulario_no_obliga_a_elegir_proyecto(): void {
		$js = $this->leer( 'public/assets/mi-area.js' );

		$this->assertStringNotContainsString( '<select name="proyecto_id" required>', $js, 'El selector ya no es obligatorio' );
		$this->assertStringContainsString( '<option value="">Proyecto (opcional)</option>', $js );
		$this->assertStringContainsString( '<option value="">Selecciona actividad (opcional)</option>', $js, 'La actividad ya se ofrecía como opcional' );
	}

	/** Y se le dice claro que no cuentan hasta que la junta apruebe. */
	public function test_el_formulario_avisa(): void {
		$js = $this->leer( 'public/assets/mi-area.js' );

		$this->assertStringContainsString( '<strong>Queda pendiente de que la junta lo apruebe</strong>', $js );
		$this->assertStringContainsString( 'hasta entonces no cuenta en tu total ni en tu nivel', $js );
		$this->assertStringContainsString( 'id="conv-hours-form"', $js );
	}
}
