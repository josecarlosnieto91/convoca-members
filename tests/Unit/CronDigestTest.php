<?php
/**
 * El resumen semanal: a quién le llega y qué dice.
 *
 * Es el único correo que la asociación lee para saber cómo va el mes. Si no sale porque el correo de
 * administración está vacío, si se manda a una dirección mal escrita, o si el cuerpo no trae las cifras, nadie
 * se entera hasta que alguien pregunta «¿y cuántos socios tenemos?».
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Cron_Manager;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Email_Manager.php';
require_once dirname( __DIR__, 2 ) . '/includes/Cron_Manager.php';

/**
 * Resumen semanal del panel.
 */
final class CronDigestTest extends TestCase {

	/**
	 * Gestor del cron.
	 *
	 * @var Cron_Manager
	 */
	private Cron_Manager $cron;

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->limpiar();
		$this->cron = new Cron_Manager();
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
		// `_wp_site` también: el nombre del sitio es global y dejarlo fijado hace que las pruebas siguientes
		// lean otro nombre («Centro de pruebas» en vez del de siempre). Contaminación, no comportamiento.
		unset( $GLOBALS['_wp_stores'], $GLOBALS['_wp_actions'], $GLOBALS['_wp_site'] );

		$GLOBALS['_wp_stores'] = array(
			'options'      => array(),
			'post_meta'    => array(),
			'transients'   => array(),
			'user_meta'    => array(),
			'emails'       => array(),
			'posts'        => array(),
			'site_options' => array(),
			'locks'        => array(),
		);

		$reflejo = new \ReflectionClass( \Convoca\Core\Logger::class );
		foreach ( array( 'log_depth' => 0, 'log_timestamps' => array() ) as $propiedad => $valor ) {
			if ( $reflejo->hasProperty( $propiedad ) ) {
				$reflejo->getProperty( $propiedad )->setValue( null, $valor );
			}
		}
	}

	/**
	 * Invoca un método privado.
	 *
	 * @param string $metodo Método.
	 * @param array  $args   Argumentos.
	 * @return mixed
	 */
	private function privado( string $metodo, array $args = array() ) {
		return ( new \ReflectionMethod( Cron_Manager::class, $metodo ) )->invoke( $this->cron, ...$args );
	}

	/**
	 * Correos enviados.
	 *
	 * @return array<int, array>
	 */
	private function correos(): array {
		return $GLOBALS['_wp_stores']['emails'] ?? array();
	}

	/**
	 * Sin correo de administración en ningún sitio no se manda nada: y se avisa en el registro.
	 */
	public function test_sin_correo_de_administracion_no_se_manda(): void {
		$this->assertSame( array(), $this->correos() );

		$this->privado( 'send_admin_digest' );

		$this->assertSame( array(), $this->correos(), 'no hay a quién mandarlo' );
	}

	/**
	 * El correo sale del ajuste del módulo, y si no está, del sitio.
	 */
	public function test_de_donde_sale_el_destino(): void {
		update_option( 'convoca_members_settings', array( 'admin_email' => 'junta@example.test' ) );
		$this->privado( 'send_admin_digest' );

		$this->assertCount( 1, $this->correos() );
		$this->assertSame( 'junta@example.test', $this->correos()[0]['to'], 'el del módulo manda' );

		$this->limpiar();
		update_option( 'admin_email', 'sitio@example.test' );
		$this->privado( 'send_admin_digest' );

		$this->assertSame( 'sitio@example.test', $this->correos()[0]['to'], 'si el módulo no dice nada, el del sitio' );
	}

	/**
	 * Los destinatarios extra: los válidos reciben, los mal escritos no (ni cortan el envío).
	 */
	public function test_los_destinatarios_extra(): void {
		update_option(
			'convoca_members_settings',
			array(
				'admin_email'         => 'junta@example.test',
				'digest_extra_emails' => 'tesoreria@example.test, esto-no-es-un-correo, vocal@example.test',
			)
		);

		$this->privado( 'send_admin_digest' );

		$destinos = array_column( $this->correos(), 'to' );
		$this->assertSame(
			array( 'junta@example.test', 'tesoreria@example.test', 'vocal@example.test' ),
			$destinos,
			'un correo por dirección válida'
		);
	}

	/**
	 * El asunto lleva el nombre del sitio para que no se pierda entre correos.
	 */
	public function test_el_asunto(): void {
		$GLOBALS['_wp_site']['name'] = 'Centro de pruebas';
		update_option( 'convoca_members_settings', array( 'admin_email' => 'junta@example.test' ) );

		$this->privado( 'send_admin_digest' );

		$correo = $this->correos()[0];
		$this->assertStringContainsString( 'Centro de pruebas', $correo['subject'] );
		$this->assertStringContainsString( 'Resumen semanal', $correo['subject'] );
		$this->assertContains( 'Content-Type: text/html; charset=UTF-8', (array) $correo['headers'], 'se manda en HTML' );
	}

	/**
	 * El cuerpo trae las cifras de verdad, no sólo el saludo.
	 */
	public function test_el_cuerpo_con_las_cifras(): void {
		// Números raros a propósito: un «42» suelto puede aparecer en cualquier parte del HTML y la aserción
		// pasaría sin que la cifra estuviera donde tiene que estar.
		$stats = array(
			'total_active'     => 4231,
			'new_members'      => 17,
			'hours_logged'     => 999,
			'expiring_soon'    => 1,
			'pending_payments' => 2,
			'recent_errors'    => 0,
			'status_counts'    => array(),
		);

		$cuerpo = $this->privado( 'build_digest_body', array( $stats ) );

		foreach ( array( 'Socios Activos' => '4231', 'Nuevos esta semana' => '17', 'Horas Registradas' => '999h' ) as $etiqueta => $cifra ) {
			// La celda pinta la cifra y la etiqueta seguidas, en ese orden o al revés: lo que importa es que
			// vayan juntas, no el orden del marcado.
			$this->assertMatchesRegularExpression(
				'/(' . preg_quote( $etiqueta, '/' ) . '.{0,200}?' . preg_quote( $cifra, '/' )
					. '|' . preg_quote( $cifra, '/' ) . '.{0,200}?' . preg_quote( $etiqueta, '/' ) . ')/s',
				$cuerpo,
				'la cifra ' . $cifra . ' tiene que ir con su etiqueta «' . $etiqueta . '»'
			);
		}
		$this->assertStringContainsString( 'Requiere atención', $cuerpo, 'con avisos pendientes hay aviso' );
		$this->assertStringContainsString( 'conv-members', $cuerpo, 'y enlace al panel' );
	}

	/**
	 * Un resumen sin nada que avisar no lleva el bloque de atención: un correo lleno de avisos falsos deja de
	 * leerse.
	 */
	public function test_el_cuerpo_sin_avisos(): void {
		$stats = array(
			'total_active'     => 42,
			'new_members'      => 0,
			'hours_logged'     => 0,
			'expiring_soon'    => 0,
			'pending_payments' => 0,
			'recent_errors'    => 0,
			'status_counts'    => array(),
		);

		$this->assertStringNotContainsString( 'Requiere atención', $this->privado( 'build_digest_body', array( $stats ) ) );
	}

	/**
	 * Una celda de cifra se pinta con su etiqueta y su número.
	 */
	public function test_las_celdas_de_cifras(): void {
		$celda = $this->privado( 'digest_metric_cell', array( 'Socios Activos', 42, '#2c5e3e' ) );

		$this->assertStringContainsString( 'Socios Activos', $celda );
		$this->assertStringContainsString( '42', $celda );
	}
}
