<?php
/**
 * Los avisos del circuito del voluntariado, editables (members#50).
 *
 * Lo que se protege, y por qué:
 *
 * - Las cinco plantillas están declaradas **y** tienen versión de fábrica. Una plantilla que el plugin envía
 *   pero no está en la lista no se puede editar, y el primer guardado del editor la borra de la opción: el
 *   correo deja de salir y nadie se entera.
 * - Cada plantilla trae su propio botón, así que no depende del CTA de respaldo.
 * - Las variables del circuito se sustituyen de verdad: se mide el sustituto, no la intención.
 * - El circuito **dispara** sus avisos, y a la dirección que toca.
 *
 * Dos cosas que aquí NO se miden, y se dicen:
 *
 * - El envío completo: `Email_Manager::send()` recorre el maquetador y `wp_get_object_terms()`, que el arnés
 *   de pruebas no tiene. El correo tal cual llega se mide en la demo, con el envío interceptado.
 * - Que el motor tenga sus oyentes: el arnés no simula los ganchos (`add_action` es un hueco vacío). El
 *   circuito sí se puede comprobar, porque `Convoca\Core\Utils::do_action()` deja registrado cada gancho que
 *   dispara en la opción `convoca_utils_fired`, que es como el propio ecosistema audita sus ganchos.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Core\Utils;
use Convoca\Members\Circuito_Voluntariado;
use Convoca\Members\Email_Manager;
use PHPUnit\Framework\TestCase;

class EmailCircuitoVoluntariadoTest extends TestCase {

	private const ID = 7300;

	/** Los slugs del circuito. */
	private const SLUGS = array(
		'voluntariado_cita',
		'voluntariado_acuerdo',
		'voluntariado_baja_solicitada',
		'voluntariado_desvinculacion',
		'voluntariado_horas_pendientes',
	);

	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['_wp_stores']['post_meta'] = array();
		$GLOBALS['_wp_stores']['user_meta'] = array();
		$GLOBALS['_wp_stores']['options']   = array();
		$GLOBALS['_wp_stores']['emails']    = array();
		$GLOBALS['_wp_stores']['options']['convoca_utils_fired'] = array();

		foreach (
			array(
				dirname( __DIR__, 3 ) . '/convoca-core/includes/Email_Layout.php',
				dirname( __DIR__, 3 ) . '/convoca-core/includes/Utils.php',
				dirname( __DIR__, 2 ) . '/includes/Roles_Miembro.php',
				dirname( __DIR__, 2 ) . '/includes/Admin_Voluntariado.php',
				dirname( __DIR__, 2 ) . '/includes/Email_Manager.php',
				dirname( __DIR__, 2 ) . '/includes/Circuito_Voluntariado.php',
			) as $fichero
		) {
			if ( file_exists( $fichero ) ) {
				require_once $fichero;
			}
		}

		update_option( 'convoca_email_templates', Email_Manager::default_templates() );

		// La ficha, con su persona: los avisos a la persona salen por ahí (sin a quién avisar, no hay aviso).
		update_post_meta( self::ID, '_convoca_user_id', '99' );
		update_post_meta( self::ID, '_convoca_email', 'socia@example.com' );
	}

	/**
	 * ¿Se disparó este gancho?
	 *
	 * @param string $gancho Nombre del gancho.
	 */
	private function se_disparo( string $gancho ): bool {
		return (bool) ( Utils::get_fired()[ $gancho ] ?? false );
	}

	/** Todas las plantillas del circuito están declaradas y tienen texto de fábrica. */
	public function test_las_cinco_plantillas_del_circuito_existen(): void {
		$fabrica = Email_Manager::default_templates();

		foreach ( self::SLUGS as $slug ) {
			$this->assertContains( $slug, Email_Manager::TEMPLATES, "La plantilla {$slug} tiene que estar en la lista" );
			$this->assertArrayHasKey( $slug, $fabrica, 'Y tener su versión de fábrica' );
			$this->assertNotSame( '', (string) $fabrica[ $slug ]['subject'], "{$slug} con asunto" );
			$this->assertStringContainsString( 'email-btn', (string) $fabrica[ $slug ]['body'], "{$slug} con su propio botón" );
		}
	}

	/** **Lo que pidió JC**: el texto es el que ponga el sitio, y las variables se siguen sustituyendo. */
	public function test_el_texto_de_los_avisos_es_editable(): void {
		update_option(
			'convoca_email_templates',
			array(
				'voluntariado_cita' => array(
					'subject' => 'Texto propio del centro',
					'body'    => '<p>Ven el {fecha_cita} a las {hora_cita}.</p>',
				),
			)
		);

		$guardadas = Email_Manager::get_templates();
		$this->assertSame( 'Texto propio del centro', $guardadas['voluntariado_cita']['subject'], 'El sitio manda sobre el texto' );

		// Y las variables se sustituyen: se mide el motor de sustitución, que es el que usa send().
		$metodo = new \ReflectionMethod( Email_Manager::class, 'replace_vars' );
		$texto  = $metodo->invoke(
			new Email_Manager(),
			$guardadas['voluntariado_cita']['body'],
			array(
				'{fecha_cita}' => '01/12/2026',
				'{hora_cita}'  => '10:00',
			)
		);

		$this->assertStringContainsString( 'Ven el 01/12/2026 a las 10:00', $texto );
		$this->assertStringNotContainsString( '{', $texto, 'Sin variables sin resolver' );
	}

	/** Las variables del circuito están en la lista que ve quien edita. */
	public function test_las_variables_del_circuito_estan_declaradas(): void {
		foreach ( array( '{fecha_cita}', '{hora_cita}', '{motivo}', '{causa_baja}', '{horas}', '{fecha_horas}', '{enlace_horas}' ) as $variable ) {
			$this->assertContains( $variable, Email_Manager::VARIABLES );
		}
	}

	/** Citar avisa. */
	public function test_citar_desde_el_circuito_dispara_su_aviso(): void {
		Circuito_Voluntariado::avisar_cita(
			self::ID,
			array(
				'fecha' => '2026-11-05',
				'hora'  => '18:30',
			)
		);

		$this->assertTrue( $this->se_disparo( 'convoca_members_email_voluntariado_cita' ) );
	}

	/** Y la desvinculación también. */
	public function test_la_desvinculacion_dispara_su_aviso(): void {
		Circuito_Voluntariado::avisar_desvinculacion( self::ID, Circuito_Voluntariado::CAUSA_JUNTA );

		$this->assertTrue( $this->se_disparo( 'convoca_members_email_voluntariado_desvinculacion' ) );
	}

	/**
	 * A quién se avisa: al correo del voluntariado y, si no lo hay, al de administración.
	 *
	 * La dirección la decide el circuito (no la plantilla), así que se comprueba donde se decide.
	 */
	public function test_la_junta_se_avisa_donde_toque(): void {
		update_option( 'convoca_members_settings', array( 'voluntariado_email' => 'voluntariado@example.com' ) );
		$this->assertSame( 'voluntariado@example.com', Circuito_Voluntariado::correo_de_la_junta() );

		update_option( 'convoca_members_settings', array() );
		update_option( 'admin_email', 'junta@example.com' );
		$this->assertSame( 'junta@example.com', Circuito_Voluntariado::correo_de_la_junta(), 'Sin correo de voluntariado, el de administración' );

		update_option( 'admin_email', '' );
		$this->assertSame( '', Circuito_Voluntariado::correo_de_la_junta(), 'Y sin ninguna, vacío' );
	}

	/** Sin ninguna dirección no se avisa a nadie: mejor eso que un correo a la nada. */
	public function test_sin_direccion_no_se_avisa_de_la_baja(): void {
		delete_option( 'convoca_members_settings' );
		$anterior = get_option( 'admin_email' );
		update_option( 'admin_email', '' );

		Circuito_Voluntariado::avisar_baja_pedida( self::ID, 'Me voy.' );

		$disparado = $this->se_disparo( 'convoca_members_email_voluntariado_baja_solicitada' );

		if ( false !== $anterior ) {
			update_option( 'admin_email', $anterior );
		}

		$this->assertFalse( $disparado );
	}
}
