<?php
/**
 * El voluntario puede apuntar sus propias horas (members#37).
 *
 * CAUSA RAÍZ MEDIDA (16/10/2026): el formulario «Añadir Nuevo Registro» **ya existía** en la pestaña de
 * Voluntariado y enviaba un `POST` a `/me/horas`, pero esa ruta solo aceptaba `GET`: el `fetch` no encontraba
 * a quién llamar y el voluntario no podía anotar nada. De ahí que el issue lo viera como «ni un botón»: la
 * pantalla estaba, la puerta no.
 *
 * Lo que fija esta prueba:
 *  · el envío del panel y los métodos de la ruta **coinciden** (era el defecto: GET en el servidor, POST en
 *    el navegador);
 *  · las horas entran por `Hours_Manager::save_hours_record( $datos, false )`, o sea **pendientes** —el mismo
 *    camino que usa la JD, sin duplicar la creación—;
 *  · la ficha que se apunta es la del socio con sesión, nunca una que venga en la petición;
 *  · y el formulario avisa de que no cuentan hasta que la junta las apruebe.
 *
 * El circuito completo (2 h apuntadas → la JD aprueba → el total y el nivel suben) se mide **en vivo**: está
 * en el issue.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

class HorasDelVoluntarioTest extends TestCase {

	private function leer( string $rel ): string {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/' . $rel );
	}

	/** El defecto: el navegador enviaba un POST a una ruta que solo tenía GET. */
	public function test_el_panel_y_la_ruta_se_entienden(): void {
		$js  = $this->leer( 'public/assets/mi-area.js' );
		$api = $this->leer( 'includes/Rest_API.php' );

		$this->assertStringContainsString( "+ '/me/horas', {\n        method: 'POST'", $js, 'El panel envía un POST a /me/horas' );

		// La ruta tiene que aceptar POST, no solo GET.
		$pos = strpos( $api, "'/me/horas'," );
		$this->assertNotFalse( $pos );
		$bloque = substr( $api, $pos, 900 );
		$this->assertStringContainsString( "'methods'             => 'POST'", $bloque, 'La ruta /me/horas acepta POST: si solo acepta GET, el formulario del panel no tiene a quién llamar' );
		$this->assertStringContainsString( 'apuntar_horas', $bloque, 'Y llama al manejador del voluntario' );
	}

	/** Las horas entran pendientes, por el mismo camino que usa la JD. */
	public function test_las_horas_entran_pendientes(): void {
		$api = $this->leer( 'includes/Rest_API.php' );

		$this->assertStringContainsString( 'Hours_Manager::save_hours_record( $datos, false )', $api, '`false` = no es la JD: el estado sale pendiente' );
		$this->assertStringContainsString( "'miembro_id'  => \$member_id,", $api, 'La ficha es la del socio con sesión' );
		$this->assertStringNotContainsString( "\$request->get_param( 'miembro_id' )", $api, 'Y nunca la que venga en la petición' );
		$this->assertStringContainsString( 'Quedan pendientes de que la junta las apruebe', $api, 'Se lo decimos al socio' );
	}

	/** El circuito de aprobación de la JD ya existía y se reutiliza (no se duplica). */
	public function test_la_aprobacion_es_la_de_siempre(): void {
		$manager = $this->leer( 'includes/Hours_Manager.php' );

		$this->assertStringContainsString( 'public static function save_hours_record( array $data, bool $is_admin = false )', $manager );
		$this->assertStringContainsString( "'pendiente'", $manager, 'Sin ser la JD, el estado por defecto es pendiente' );
		$this->assertStringContainsString( 'public static function process_approval(', $manager, 'La aprobación la sigue haciendo la JD desde su pantalla' );
	}

	/** Y el formulario lo dice claro. */
	public function test_el_formulario_avisa(): void {
		$js = $this->leer( 'public/assets/mi-area.js' );

		$this->assertStringContainsString( '<strong>Queda pendiente de que la junta lo apruebe</strong>', $js );
		$this->assertStringContainsString( 'hasta entonces no cuenta en tu total ni en tu nivel', $js );
		$this->assertStringContainsString( 'id="conv-hours-form"', $js, 'Y el formulario que ya existía sigue siendo el que se usa' );
	}
}
