<?php
/**
 * «Mis turnos» dentro del panel del socio (shifts#19, propuesta 3).
 *
 * El panel tenía siete secciones y ninguna de turnos: quien quería apuntarse tenía que encontrar la página del
 * cuadrante por su cuenta, y la suscripción a su calendario `.ics` solo vivía en una caja de una página suelta
 * (shifts#16). Aquí se trae todo al panel, **delegando en los shortcodes del plugin de turnos**: es él quien
 * sabe de turnos, y copiar aquí su lógica sería tener dos versiones de la verdad.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

class SeccionMisTurnosTest extends TestCase {

	private function leer( string $rel ): string {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/' . $rel );
	}

	/** La pestaña está… y solo si hay plugin de turnos. */
	public function test_la_pestana_esta_y_depende_del_plugin(): void {
		$pantalla = $this->leer( 'public/class-mi-area.php' );

		$this->assertStringContainsString( 'data-tab="turnos"', $pantalla );
		$this->assertStringContainsString( "shortcode_exists( 'convoca_proximos_turnos' )", $pantalla, 'Sin plugin de turnos, la pestaña no se pinta' );
	}

	/** El panel delega en el plugin de turnos en vez de reescribirlo. */
	public function test_el_panel_delega_en_el_plugin_de_turnos(): void {
		$api = $this->leer( 'includes/Rest_API.php' );

		$this->assertStringContainsString( "'/me/turnos'", $api );
		$this->assertStringContainsString( 'do_shortcode( \'[convoca_proximos_turnos]\' )', $api );
		$this->assertStringContainsString( "do_shortcode( '[convoca_calendario]' )", $api, 'El cuadrante, para ver los huecos libres' );
		$this->assertStringContainsString( "do_shortcode( '[convoca_suscripcion_turnos]' )", $api, 'Y su calendario, para suscribirlo desde aquí' );
	}

	/** Sin el plugin de turnos dice que no hay nada, en vez de romperse. */
	public function test_sin_plugin_de_turnos_no_revienta(): void {
		$clase = '\\Convoca\\Members\\Rest_API';
		if ( ! class_exists( $clase ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/Rest_API.php';
		}
		if ( ! class_exists( $clase ) ) {
			$this->markTestSkipped( 'No se ha podido cargar Rest_API en el arnés.' );
		}

		$metodo = new \ReflectionMethod( $clase, 'get_turnos' );

		// En el arnés no hay ningún shortcode registrado: es exactamente el caso «sin plugin de turnos».
		$this->assertFalse( shortcode_exists( 'convoca_proximos_turnos' ) );
		$respuesta = $metodo->invoke( new $clase() );
		$datos     = $respuesta->get_data();

		$this->assertSame( 200, $respuesta->get_status() );
		$this->assertFalse( $datos['turnos'], 'Sin plugin de turnos la sección lo dice' );
		$this->assertStringContainsString( 'Los turnos no están disponibles', $datos['html'] );
	}

	/** Y el panel la carga. */
	public function test_el_panel_la_carga(): void {
		$js = $this->leer( 'public/assets/mi-area.js' );

		$this->assertStringContainsString( "case 'turnos': this.fetchTurnos(); break;", $js );
		$this->assertStringContainsString( 'fetchTurnos: function()', $js );
		$this->assertStringContainsString( "+ '/me/turnos'", $js );
	}
}
