<?php
/**
 * Correos que se veían mal con los datos de verdad.
 *
 * Dos contratos:
 *
 * 1. **Bienvenida**: el asunto y la primera línea del cuerpo no pueden ser la
 *    misma frase. El nombre del sitio entraba en las dos, así que el correo
 *    empezaba repitiendo su propio asunto. La migración de plantillas tiene que
 *    reescribir el saludo guardado de fábrica y **respetar** el que el sitio
 *    haya personalizado.
 *
 * 2. **Objetivo de voluntariado**: sin horas acreditadas no se envía — el
 *    correo felicitaría por «completar 0h» y ofrecería un certificado que no
 *    existe.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;
use Convoca\Members\Email_Manager;

class EmailBienvenidaObjetivoTest extends TestCase {

	private const MIEMBRO = 750;

	/** El $wpdb de los stubs, para devolverlo intacto al terminar el test. */
	private $wpdb_original;

	protected function setUp(): void {
		parent::setUp();

		\Convoca\Core\Logger::clear();
		$GLOBALS['_wp_stores']['options']   = array();
		$GLOBALS['_wp_stores']['post_meta'] = array();
		$this->wpdb_original                = $GLOBALS['wpdb'] ?? null;

		foreach (
			array(
				dirname( __DIR__, 3 ) . '/convoca-core/includes/Email_Layout.php',
				dirname( __DIR__, 2 ) . '/includes/Voluntariado_Manager.php',
				dirname( __DIR__, 2 ) . '/includes/Email_Manager.php',
			) as $file
		) {
			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}
	}

	/**
	 * Fija las horas acreditadas que devuelve la consulta del voluntariado.
	 *
	 * La comprobación del objetivo es una suma en SQL, así que se controla el
	 * resultado de la consulta en lugar de intentar simular la tabla.
	 */
	private function horas_acreditadas( float $horas ): void {
		$GLOBALS['wpdb'] = new class( $horas ) {
			public $prefix    = 'wp_';
			public $posts     = 'wp_posts';
			public $postmeta  = 'wp_postmeta';

			private float $horas;

			public function __construct( float $horas ) {
				$this->horas = $horas;
			}

			public function prepare( $q, ...$args ) {
				return $q;
			}

			public function get_var( $q = null, $x = 0, $y = 0 ) {
				return $this->horas;
			}
		};
	}

	/**
	 * Devuelve el $wpdb de los stubs: los tests corren en el mismo proceso y
	 * dejar aquí un doble propio rompe a los que vienen después (los de
	 * Hours_Manager usan `query()`).
	 */
	protected function tearDown(): void {
		$GLOBALS['wpdb'] = $this->wpdb_original;
		parent::tearDown();
	}

	// ── Bienvenida: plantilla por defecto ─────────────────────────────
	public function test_el_asunto_y_la_primera_linea_no_son_la_misma_frase(): void {
		Email_Manager::install_defaults();
		$tpl = get_option( 'convoca_email_templates' )['bienvenida'];

		$this->assertStringContainsString( '{nombre}', $tpl['subject'] );
		$this->assertStringContainsString( '{nombre}', $tpl['body'] );

		// El cuerpo empieza saludando sin el nombre del sitio, que sí va en el
		// asunto: así el destinatario no lee dos veces el mismo texto.
		$this->assertStringContainsString( '<h1>¡Bienvenido/a, {nombre}!', $tpl['body'] );
		$this->assertStringNotContainsString( '<h1>¡Bienvenido/a a ', $tpl['body'] );
	}

	// ── Bienvenida: migración de lo ya guardado ───────────────────────

	/** El saludo de fábrica que quedó en los sitios que ya existían. */
	private function plantilla_guardada(): string {
		return '<h1>¡Bienvenido/a a Sitio de Prueba, {nombre}! 🎉</h1>'
			. '<p>Tu alta como <strong>{tipo_miembro}</strong> ha sido confirmada.</p>';
	}

	private function guardar_plantilla( string $subject, string $body ): void {
		update_option(
			'convoca_email_templates',
			array(
				'bienvenida' => array( 'subject' => $subject, 'body' => $body ),
			)
		);
	}

	public function test_la_migracion_corrige_el_saludo_guardado(): void {
		$this->guardar_plantilla( '¡Bienvenido/a a Sitio de Prueba, {nombre}!', $this->plantilla_guardada() );
		update_option( 'convoca_email_templates_version', '2026-09-10-2' );

		Email_Manager::maybe_migrate();

		$tpl = get_option( 'convoca_email_templates' )['bienvenida'];

		$this->assertSame( '¡Bienvenido/a, {nombre}! Ya formas parte de Sitio de Prueba', $tpl['subject'] );
		$this->assertStringContainsString( '<h1>¡Bienvenido/a, {nombre}! 🎉</h1>', $tpl['body'] );
		$this->assertStringNotContainsString( '¡Bienvenido/a a Sitio de Prueba', $tpl['body'] );
	}

	public function test_la_migracion_no_toca_un_saludo_personalizado(): void {
		$this->guardar_plantilla( 'Qué alegría tenerte aquí, {nombre}', '<h1>¡Hola, {nombre}! Bienvenido a la casa.</h1>' );
		update_option( 'convoca_email_templates_version', '2026-09-10-2' );

		Email_Manager::maybe_migrate();

		$tpl = get_option( 'convoca_email_templates' )['bienvenida'];

		$this->assertSame( 'Qué alegría tenerte aquí, {nombre}', $tpl['subject'] );
		$this->assertSame( '<h1>¡Hola, {nombre}! Bienvenido a la casa.</h1>', $tpl['body'] );
	}

	public function test_la_migracion_solo_corre_una_vez(): void {
		$this->guardar_plantilla( '¡Bienvenido/a a Sitio de Prueba, {nombre}!', $this->plantilla_guardada() );

		Email_Manager::maybe_migrate();
		$primera = get_option( 'convoca_email_templates' )['bienvenida'];

		// Un saludo personalizado escrito después no puede ser pisado por una
		// segunda pasada de la migración.
		$this->guardar_plantilla( 'Saludo propio, {nombre}', '<h1>Propio</h1>' );
		Email_Manager::maybe_migrate();

		$this->assertNotSame( $primera, get_option( 'convoca_email_templates' )['bienvenida'] );
		$this->assertSame( 'Saludo propio, {nombre}', get_option( 'convoca_email_templates' )['bienvenida']['subject'] );
	}

	// ── Objetivo de voluntariado ─────────────────────────────────────

	public function test_sin_horas_acreditadas_no_hay_correo_de_objetivo(): void {
		$this->horas_acreditadas( 0 );

		$this->assertFalse( Email_Manager::objetivo_tiene_horas( self::MIEMBRO ) );

		$manager = new Email_Manager();
		$manager->send_objetivo_voluntariado( self::MIEMBRO );

		$avisos = array_values(
			array_filter(
				\Convoca\Core\Logger::$logs,
				static fn( array $l ): bool => 'warning' === $l['level']
			)
		);

		$this->assertCount( 1, $avisos, 'Debería quedar un aviso en el log y ningún envío.' );
		$this->assertStringContainsString( 'no tiene horas acreditadas', $avisos[0]['msg'] );
	}

	public function test_con_horas_acreditadas_el_correo_se_considera_justificado(): void {
		$this->horas_acreditadas( 25 );

		$this->assertTrue( Email_Manager::objetivo_tiene_horas( self::MIEMBRO ) );
	}

	public function test_media_hora_cuenta_como_hora(): void {
		$this->horas_acreditadas( 0.5 );

		$this->assertTrue( Email_Manager::objetivo_tiene_horas( self::MIEMBRO ) );
	}

	// ── La lista de plantillas es un contrato ────────────────────────

	/**
	 * El editor del admin pinta `TEMPLATES` y su guardado **sobreescribe** la
	 * opción. Una plantilla que el plugin envía pero no está en la lista no se
	 * puede editar y desaparece en el primer guardado, sin aviso: el correo deja
	 * de salir y nadie se entera.
	 */
	public function test_las_plantillas_que_el_plugin_envia_estan_en_la_lista(): void {
		$this->assertContains( 'confirm_email', Email_Manager::TEMPLATES );
		$this->assertContains( 'verify_phone', Email_Manager::TEMPLATES );
	}

	/** Toda plantilla declarada tiene su versión de fábrica (y al revés). */
	public function test_los_slugs_declarados_y_los_de_fabrica_coinciden(): void {
		$declarados = Email_Manager::TEMPLATES;
		$fabrica    = array_keys( Email_Manager::default_templates() );

		sort( $declarados );
		sort( $fabrica );

		$this->assertSame( $declarados, $fabrica );
	}

	public function test_la_migracion_repone_una_plantilla_que_falta_en_el_sitio(): void {
		// Un sitio cuyo guardado del admin es anterior a la plantilla.
		$this->guardar_plantilla( 'Saludo propio, {nombre}', '<h1>Propio</h1>' );
		$templates = get_option( 'convoca_email_templates' );
		$this->assertArrayNotHasKey( 'confirm_email', $templates );

		update_option( 'convoca_email_templates_version', '2026-09-10-2' );
		Email_Manager::maybe_migrate();

		$templates = get_option( 'convoca_email_templates' );

		$this->assertArrayHasKey( 'confirm_email', $templates );
		$this->assertArrayHasKey( 'verify_phone', $templates );
		$this->assertStringContainsString( '{link_confirmacion}', $templates['confirm_email']['body'] );
		// Lo que el sitio tenía se respeta.
		$this->assertSame( 'Saludo propio, {nombre}', $templates['bienvenida']['subject'] );
		$this->assertSame( '<h1>Propio</h1>', $templates['bienvenida']['body'] );
	}

	public function test_un_sitio_sin_plantillas_las_recibe_enteras(): void {
		delete_option( 'convoca_email_templates' );
		update_option( 'convoca_email_templates_version', '2026-09-10-2' );

		Email_Manager::maybe_migrate();

		$this->assertSame(
			array_keys( Email_Manager::default_templates() ),
			array_keys( get_option( 'convoca_email_templates' ) )
		);
	}
}
