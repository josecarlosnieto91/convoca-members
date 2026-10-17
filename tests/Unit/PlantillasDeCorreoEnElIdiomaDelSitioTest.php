<?php
/**
 * Las plantillas de correo no pueden quedarse en el idioma en que se sembraron (members#60).
 *
 * Las plantillas de fábrica son texto ya traducido que se guarda en una opción, así que la siembra las
 * congela en el idioma que hubiera en ese momento. Medido el 17/10/2026 en la demo: el correo de
 * confirmación del cambio de email salía **entero en inglés** («Hi … You have requested to change your
 * contact email…») en un sitio en castellano, con el pie en castellano porque viene de otro sitio.
 *
 * `maybe_relocalize()` las pone en el idioma del sitio, pero **solo las que siguen siendo las de
 * fábrica**: las que el admin haya personalizado se respetan, que es lo que no puede romperse.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Email_Manager;
use PHPUnit\Framework\TestCase;

class PlantillasDeCorreoEnElIdiomaDelSitioTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		delete_option( Email_Manager::TEMPLATES_VERSION_OPTION );
		delete_option( 'convoca_email_templates' );
		delete_option( 'convoca_email_templates_locale' );
	}

	public function test_sin_idioma_guardado_solo_toma_nota_y_no_toca_nada(): void {
		$plantillas = array( 'confirm_email' => array( 'subject' => 'Lo que sea', 'body' => 'Cuerpo' ) );
		update_option( 'convoca_email_templates', $plantillas );

		Email_Manager::maybe_relocalize();

		$this->assertSame( $plantillas, get_option( 'convoca_email_templates' ), 'No debe tocar plantillas de un sitio que no ha pasado por la siembra nueva.' );
		$this->assertNotSame( '', (string) get_option( 'convoca_email_templates_locale' ), 'Debe anotar el idioma actual.' );
	}

	public function test_con_el_mismo_idioma_no_hace_nada(): void {
		$plantillas = array( 'confirm_email' => array( 'subject' => 'Mío', 'body' => 'Mío' ) );
		update_option( 'convoca_email_templates', $plantillas );
		update_option( 'convoca_email_templates_locale', determine_locale() );

		Email_Manager::maybe_relocalize();

		$this->assertSame( $plantillas, get_option( 'convoca_email_templates' ) );
	}

	public function test_una_plantilla_personalizada_se_respeta_al_cambiar_el_idioma(): void {
		$personalizada = array( 'subject' => 'Asunto mío, escrito a mano', 'body' => 'Cuerpo mío' );
		$plantillas    = array_merge(
			Email_Manager::default_templates(),
			array( 'confirm_email' => $personalizada )
		);
		update_option( 'convoca_email_templates', $plantillas );
		update_option( 'convoca_email_templates_locale', 'en_US' );

		Email_Manager::maybe_relocalize();

		$this->assertSame( $personalizada, get_option( 'convoca_email_templates' )['confirm_email'], 'Lo que el admin escribió no se toca.' );
		$this->assertSame( determine_locale(), get_option( 'convoca_email_templates_locale' ), 'El idioma anotado pasa al actual.' );
	}

	public function test_la_correccion_esta_enganchada(): void {
		$fuente = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Email_Manager.php' );
		$this->assertStringContainsString( "add_action( 'admin_init', array( __CLASS__, 'maybe_relocalize' )", $fuente );
	}

	public function test_la_siembra_anota_el_idioma(): void {
		Email_Manager::install_defaults();
		$this->assertNotSame( '', (string) get_option( 'convoca_email_templates_locale' ) );
		$this->assertNotEmpty( get_option( 'convoca_email_templates' ) );
	}
}
