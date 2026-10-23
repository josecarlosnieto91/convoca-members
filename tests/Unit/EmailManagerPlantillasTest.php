<?php
/**
 * Las plantillas de correo: valores por defecto, vista previa y migraciones.
 *
 * Lo que se rompe aquí no da error: un correo sale con `{nombre}` en crudo, o con el texto de otro idioma
 * porque el sitio cambió de idioma, o con `\u00a1` en vez de `¡`. Y lo peor: al re-sembrar las plantillas se
 * puede **pisar lo que la asociación escribió a mano**. Por eso lo que se prueba es eso: que sólo se
 * reemplaza lo que sigue siendo el texto de fábrica, y que lo editado se queda como está.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Email_Manager;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Email_Manager.php';

/**
 * Plantillas, vista previa y migraciones.
 */
final class EmailManagerPlantillasTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->limpiar();
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$this->limpiar();
	}

	/**
	 * Deja el arnés como estaba.
	 */
	private function limpiar(): void {
		foreach ( array( '_wp_stores', '_wp_site' ) as $clave ) {
			unset( $GLOBALS[ $clave ] );
		}

		$GLOBALS['_wp_stores'] = array(
			'options'    => array(),
			'post_meta'  => array(),
			'transients' => array(),
			'user_meta'  => array(),
		);
	}

	/**
	 * La primera instalación siembra las plantillas y anota el idioma.
	 */
	public function test_la_primera_instalacion_siembra_las_plantillas(): void {
		Email_Manager::install_defaults();

		$guardadas = get_option( 'convoca_email_templates' );
		$this->assertIsArray( $guardadas );
		$this->assertNotEmpty( $guardadas, 'hay plantillas' );
		$this->assertNotFalse( get_option( 'convoca_email_templates_locale' ), 'y se anota el idioma' );
	}

	/**
	 * Y no pisa lo que ya hay: reinstalar no borra lo escrito.
	 */
	public function test_no_pisa_las_plantillas_existentes(): void {
		update_option( 'convoca_email_templates', array( 'bienvenida' => array( 'subject' => 'Mío', 'body' => 'Mío' ) ) );

		Email_Manager::install_defaults();

		$this->assertSame(
			'Mío',
			get_option( 'convoca_email_templates' )['bienvenida']['subject'],
			'lo que hay se queda'
		);
	}

	/**
	 * Guardar y leer plantillas.
	 */
	public function test_guardar_y_leer(): void {
		$plantillas = array( 'bienvenida' => array( 'subject' => 'Hola', 'body' => 'Cuerpo' ) );

		Email_Manager::save_templates( $plantillas );

		$this->assertSame( $plantillas, Email_Manager::get_templates() );
	}

	/**
	 * Los valores de la vista previa cubren las variables que usan las plantillas.
	 */
	public function test_los_valores_de_ejemplo(): void {
		$vars = Email_Manager::preview_vars();

		foreach ( array( '{nombre}', '{plan}', '{numero_socio}', '{login_url}', '{link_pago}', '{horas_totales}' ) as $variable ) {
			$this->assertArrayHasKey( $variable, $vars, 'falta ' . $variable );
			$this->assertNotSame( '', (string) $vars[ $variable ] );
		}
	}

	/**
	 * La vista previa sustituye las variables y añade el pie con el nombre del sitio.
	 */
	public function test_la_vista_previa(): void {
		$GLOBALS['_wp_site']['name'] = 'Centro de pruebas';
		Email_Manager::save_templates(
			array(
				'bienvenida' => array(
					'subject' => 'Hola {nombre}',
					'body'    => '<p>Bienvenida, {nombre}</p>',
				),
			)
		);

		$html = Email_Manager::preview_html( 'bienvenida' );

		$this->assertStringContainsString( 'María García López', $html, 'el nombre de ejemplo' );
		$this->assertStringNotContainsString( '{nombre}', $html, 'sin variables en crudo' );
		$this->assertStringContainsString( 'Has recibido este email porque eres miembro de Centro de pruebas', $html, 'el pie explicando por qué lo recibe' );
	}

	/**
	 * Si la plantilla no trae botón, la vista previa enseña el que se añadirá de verdad.
	 */
	public function test_la_vista_previa_anade_el_boton(): void {
		Email_Manager::save_templates(
			array(
				'bienvenida' => array(
					'subject' => 'Hola',
					'body'    => '<p>Sin botón</p>',
				),
			)
		);

		$html = Email_Manager::preview_html( 'bienvenida' );

		$this->assertStringContainsString( 'Acceder a Mi Área', $html, 'el CTA por defecto se enseña con su etiqueta' );
		$this->assertStringContainsString( 'https://ejemplo.test/mi-area/', $html, 'y apunta al área de socio' );

		// Una plantilla sin CTA por defecto no lleva botón: ahí se ve si el código lo añade o no.
		Email_Manager::save_templates( array( 'sin_cta' => array( 'subject' => 'Hola', 'body' => '<p>Sin botón</p>' ) ) );
		$this->assertStringNotContainsString( 'Acceder a Mi Área', Email_Manager::preview_html( 'sin_cta' ) );
	}

	/**
	 * Una plantilla que ya trae su botón no se toca.
	 */
	public function test_la_vista_previa_respeta_el_boton_propio(): void {
		Email_Manager::save_templates(
			array(
				'bienvenida' => array(
					'subject' => 'Hola',
					'body'    => '<a class="email-btn" href="https://example.test/mio">El mío</a>',
				),
			)
		);

		$html = Email_Manager::preview_html( 'bienvenida' );

		$this->assertStringContainsString( 'https://example.test/mio', $html );
		$this->assertStringNotContainsString( 'https://ejemplo.test/mi-area/', $html, 'no se añade otro' );
	}

	/**
	 * Una plantilla que no existe no revienta la vista previa.
	 */
	public function test_la_vista_previa_de_algo_que_no_existe(): void {
		Email_Manager::save_templates( array() );

		$html = Email_Manager::preview_html( 'no_existe' );

		$this->assertIsString( $html );
	}

	/**
	 * Re-sembrar por cambio de idioma **no pisa** lo escrito a mano.
	 */
	public function test_la_resiembra_no_pisa_lo_editado(): void {
		$defecto = Email_Manager::default_templates();
		$slug    = array_key_first( $defecto );

		$plantillas          = $defecto;
		$plantillas[ $slug ] = array(
			'subject' => 'Lo que escribió la asociación',
			'body'    => '<p>Texto propio</p>',
		);
		Email_Manager::save_templates( $plantillas );
		update_option( 'convoca_email_templates_locale', 'otro_idioma' );

		Email_Manager::maybe_relocalize();

		$this->assertSame(
			'Lo que escribió la asociación',
			Email_Manager::get_templates()[ $slug ]['subject'],
			'lo editado se queda'
		);
	}

	/**
	 * Sin plantillas guardadas, la resiembra no hace nada.
	 */
	public function test_la_resiembra_sin_plantillas(): void {
		Email_Manager::maybe_relocalize();

		$this->assertFalse( get_option( 'convoca_email_templates' ), 'no siembra por su cuenta' );
	}

	/**
	 * Las guardas de la resiembra (sin plantillas, o mismo idioma) son **redundantes** con el bucle: si una
	 * plantilla ya es la de fábrica no se toca, y sin plantillas no hay nada que recorrer. Se dejan porque
	 * dicen la intención, pero no se finge que se puedan distinguir.
	 */

	/**
	 * La migración corre una vez y deja su marca.
	 */
	public function test_la_migracion(): void {
		Email_Manager::save_templates(
			array(
				'bienvenida' => array(
					'subject' => 'Hola',
					'body'    => '<h1>\u00a1Bienvenido/a</h1>',
				),
			)
		);

		Email_Manager::maybe_migrate();

		$this->assertSame(
			Email_Manager::TEMPLATES_VERSION,
			get_option( 'convoca_email_templates_version' ),
			'queda la marca de versión'
		);
		$this->assertStringContainsString(
			'¡',
			Email_Manager::get_templates()['bienvenida']['body'],
			'el escape Unicode se convierte en el carácter'
		);
		$this->assertStringNotContainsString( '\u00a1', Email_Manager::get_templates()['bienvenida']['body'] );
	}

	/**
	 * Y no vuelve a correr: la marca la frena.
	 */
	public function test_la_migracion_no_corre_dos_veces(): void {
		update_option( 'convoca_email_templates_version', Email_Manager::TEMPLATES_VERSION );
		Email_Manager::save_templates( array( 'bienvenida' => array( 'subject' => 'x', 'body' => '\u00a1' ) ) );

		Email_Manager::maybe_migrate();

		$this->assertStringContainsString( '\u00a1', Email_Manager::get_templates()['bienvenida']['body'], 'no se tocó' );
	}
}
