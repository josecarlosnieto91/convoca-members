<?php
/**
 * El widget del escritorio con las cifras y los avisos de socios.
 *
 * `admin/class-admin-dashboard.php` estaba al 0 % (203 statements). Es lo primero que ve la junta al
 * entrar: cuántos socios activos hay, quién debe la cuota, qué vence y —lo que de verdad importa— los
 * avisos de RGPD (supresiones y consentimientos sin registrar). Un dashboard que miente es peor que no
 * tenerlo: si dice «todo en orden» cuando hay socios sin consentimiento, nadie lo mira.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Admin_Dashboard;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class DashboardPanelTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		require_once dirname( __DIR__, 2 ) . '/admin/class-admin-dashboard.php';

		$GLOBALS['_wp_stores'] = array(
			'options'      => array(),
			'acciones'     => array(),
			'widgets'      => array(),
			'get_var_mapa' => array(),
		);
		$GLOBALS['_test_caps'] = array( 'edit_posts' => true );
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores'] = array();
		unset( $GLOBALS['_test_caps'], $GLOBALS['_test_screen'], $GLOBALS['_wp_dashboard_widgets'] );
	}

	/**
	 * Invoca un método privado del widget.
	 *
	 * @param string $metodo   Método.
	 * @param array  $argumentos Argumentos.
	 * @return mixed
	 */
	private function privado( string $metodo, array $argumentos = array() ) {
		$reflejo = new ReflectionMethod( Admin_Dashboard::class, $metodo );
		return $reflejo->invokeArgs( new Admin_Dashboard(), $argumentos );
	}

	/**
	 * Pinta el widget y devuelve su HTML.
	 *
	 * @return string
	 */
	private function pintarWidget(): string {
		$panel = new Admin_Dashboard();
		ob_start();
		$panel->render_widget();
		return (string) ob_get_clean();
	}

	/**
	 * Se engancha al escritorio y a la cabecera del panel.
	 */
	public function test_se_engancha_al_escritorio(): void {
		new Admin_Dashboard();

		$this->assertArrayHasKey( 'wp_dashboard_setup', $GLOBALS['_wp_hooks'], 'sin esto el widget no existe' );
		$this->assertArrayHasKey( 'admin_head', $GLOBALS['_wp_hooks'] );
	}

	/**
	 * Sin permiso no se registra el widget.
	 */
	public function test_sin_permiso_no_se_registra(): void {
		$GLOBALS['_test_caps']['edit_posts'] = false;
		$GLOBALS['_wp_dashboard_widgets'] = array();

		( new Admin_Dashboard() )->register_widget();

		$this->assertSame( array(), $GLOBALS['_wp_dashboard_widgets'], 'quien no edita no ve el panel' );
	}

	/**
	 * Con permiso el widget se registra con el nombre del sitio.
	 */
	public function test_con_permiso_se_registra_con_el_nombre_del_sitio(): void {
		$GLOBALS['_wp_stores']['options']['blogname'] = 'Asociación de prueba';
		$GLOBALS['_wp_dashboard_widgets']             = array();

		( new Admin_Dashboard() )->register_widget();

		$this->assertArrayHasKey( 'convoca_dashboard_alerts', $GLOBALS['_wp_dashboard_widgets'], 'el widget del núcleo se registra por su id' );
		$this->assertStringContainsString(
			get_bloginfo( 'name' ),
			$GLOBALS['_wp_dashboard_widgets']['convoca_dashboard_alerts']['titulo'],
			'el título lleva el nombre del sitio'
		);
	}

	/**
	 * Las cuatro cifras se pintan siempre, aunque valgan cero.
	 */
	public function test_las_cuatro_cifras_se_pintan(): void {
		$html = $this->pintarWidget();

		$this->assertSame( 4, substr_count( $html, 'conv-dash-stat__value' ), 'activos, pendientes, vencen y horas' );
		$this->assertStringContainsString( 'Activos', $html );
		$this->assertStringContainsString( 'Pend. pago', $html );
		$this->assertStringContainsString( 'Vencen pronto', $html );
	}

	/**
	 * Sin avisos se dice que todo está en orden (y no se deja la sección muda).
	 */
	public function test_sin_avisos_se_dice_que_todo_esta_en_orden(): void {
		$html = $this->pintarWidget();

		$this->assertStringContainsString( 'Todo en orden', $html );
		$this->assertStringNotContainsString( 'Requiere atención', $html );
	}

	/**
	 * Una membresía que vence en siete días genera su aviso con enlace.
	 */
	public function test_una_membresia_que_vence_avisa(): void {
		$stats = array(
			'total_active'    => 10,
			'pending_payment' => 2,
			'expiring_soon'   => 3,
			'hours_month'     => 12.5,
			'new_month'       => 1,
			'pending_docs'    => 0,
			'recent_errors'   => 0,
		);

		$avisos = $this->privado( 'get_alerts', array( $stats ) );

		$mensajes = array_column( $avisos, 'message' );
		$this->assertNotEmpty( $avisos );
		$this->assertStringContainsString( '3 membresía(s) vencen', implode( ' | ', $mensajes ) );
		$this->assertStringContainsString( '2 socio(s) con pago pendiente', implode( ' | ', $mensajes ) );

		$enlaces = array_filter( array_column( $avisos, 'link' ) );
		$this->assertNotEmpty( $enlaces, 'cada aviso lleva a donde se resuelve' );
	}

	/**
	 * Los errores del sistema salen con la severidad más alta.
	 */
	public function test_los_errores_del_sistema_avisan_con_severidad_maxima(): void {
		$stats = array(
			'total_active'    => 0,
			'pending_payment' => 0,
			'expiring_soon'   => 0,
			'hours_month'     => 0,
			'new_month'       => 0,
			'pending_docs'    => 0,
			'recent_errors'   => 4,
		);

		$avisos = $this->privado( 'get_alerts', array( $stats ) );

		$this->assertSame( 'error', $avisos[0]['severity'], 'un error del sistema no es un aviso informativo' );
		$this->assertStringContainsString( '4 error(es)', $avisos[0]['message'] );
	}

	/**
	 * Las cifras salen de sus consultas y la tabla de logs ausente no rompe el panel.
	 */
	public function test_las_cifras_salen_de_sus_consultas(): void {
		// El mapa casa por la PRIMERA aguja que aparece en el SQL, así que las consultas que comparten
		// texto (`meta_value = 'activo'` la usan activos y «vencen pronto») se distinguen por lo suyo:
		// `BETWEEN` y `SHOW TABLES` van antes que el resto.
		$GLOBALS['_wp_stores']['get_var_mapa'] = array(
			'SHOW TABLES'                            => 'otra_tabla',
			'BETWEEN'                                => '5',
			'COALESCE(SUM'                           => '18.5',
			"meta_value = 'pendiente_documentacion'" => '6',
			"meta_value = 'pendiente_pago'"          => '3',
			"meta_value = 'activo'"                  => '24',
			'post_date >= '                          => '2',
		);

		$stats = $this->privado( 'get_stats' );

		$this->assertSame( 24, $stats['total_active'] );
		$this->assertSame( 3, $stats['pending_payment'] );
		$this->assertSame( 5, $stats['expiring_soon'] );
		$this->assertSame( 18.5, $stats['hours_month'] );
		$this->assertSame( 0, $stats['recent_errors'], 'sin tabla de logs no se inventan errores' );
	}

	/**
	 * Con la tabla de logs, los errores de las últimas 24 h se cuentan.
	 */
	public function test_con_tabla_de_logs_se_cuentan_los_errores(): void {
		$GLOBALS['_wp_stores']['get_var_mapa'] = array(
			'SHOW TABLES'  => 'wp_convoca_logs',
			"level = 'error'" => '7',
			'COALESCE(SUM' => '0',
		);

		$stats = $this->privado( 'get_stats' );

		$this->assertSame( 7, $stats['recent_errors'] );
	}

	/**
	 * Los estilos solo se inyectan en el escritorio.
	 */
	public function test_los_estilos_solo_van_en_el_escritorio(): void {
		$panel = new Admin_Dashboard();

		ob_start();
		$panel->widget_styles();
		$fuera = (string) ob_get_clean();
		$this->assertSame( '', $fuera, 'en otras pantallas no se inyecta CSS' );

		$GLOBALS['_test_screen'] = (object) array( 'id' => 'dashboard', 'base' => 'dashboard' );
		ob_start();
		$panel->widget_styles();
		$dentro = (string) ob_get_clean();
		$this->assertStringContainsString( '<style>', $dentro );
		$this->assertStringContainsString( '.conv-dash-stats', $dentro, 'con su rejilla de cuatro columnas' );
	}
}
