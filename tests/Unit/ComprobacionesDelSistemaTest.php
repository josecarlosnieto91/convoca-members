<?php
/**
 * Las comprobaciones del sistema (pantalla de ajustes).
 *
 * `get_system_checks()` mira tres cosas: que los plugins del ecosistema estén activos, que existan las seis
 * páginas con sus shortcodes, y que la base de datos esté al día. Y cachea el resultado.
 *
 * Lo que no da error si se rompe:
 *
 *  - **la caché**: si no caduca, la pantalla sigue diciendo que todo está bien cuando ya no lo está, y es la
 *    pantalla a la que se mira cuando algo va mal;
 *  - **la severidad**: que falte Core es un `error` (sin él no funciona nada), que falten Enroll o Gateway es un
 *    `warning` (el plugin es opcional). Al revés, el operador se lleva un susto o se lo pierde;
 *  - **el `fix`**: un aviso sin decir qué hacer no sirve de nada a quien lo lee.
 *
 * Excepción escrita: la rama «falta Convoca Core» no se mide. Se decide con `class_exists( '\Convoca\Core\Utils' )`
 * y en esta suite esa clase **siempre** existe (el arnés la carga), así que no hay forma de ponerla en falso sin
 * tocar el arnés de todo el mundo. Se mide el caso contrario, que es el que ocurre de verdad.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Admin_Settings;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/admin/class-admin-settings.php';

/**
 * Comprobaciones del sistema.
 */
final class ComprobacionesDelSistemaTest extends TestCase {

	/**
	 * Valores previos.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Ajustes.
	 *
	 * @var Admin_Settings
	 */
	private Admin_Settings $ajustes;

	/**
	 * Monta el escenario.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->previo = array(
			'opts'   => $GLOBALS['_wp_stores']['options'] ?? array(),
			'gvmapa' => $GLOBALS['_wp_stores']['get_var_mapa'] ?? null,
			'posts'  => $GLOBALS['_wp_stores']['posts'] ?? array(),
			'errlog' => ini_get( 'error_log' ),
		);

		// Las define el arranque del plugin y WordPress; la suite no carga ninguno de los dos.
		if ( ! defined( 'CONVOCA_MEMBERS_DB_VERSION' ) ) {
			define( 'CONVOCA_MEMBERS_DB_VERSION', '2.9.18' );
		}
		if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
			define( 'HOUR_IN_SECONDS', 3600 );
		}

		$GLOBALS['_wp_stores']['options'] = array();

		$GLOBALS['_errlog_tmp'] = sys_get_temp_dir() . '/convoca-checks-test.log';
		ini_set( 'error_log', $GLOBALS['_errlog_tmp'] );

		$this->ajustes = ( new \ReflectionClass( Admin_Settings::class ) )->newInstanceWithoutConstructor();
	}

	/**
	 * Devuelve el escenario.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']['options'] = $this->previo['opts'];
		$GLOBALS['_wp_stores']['posts']   = $this->previo['posts'];

		if ( null === $this->previo['gvmapa'] ) {
			unset( $GLOBALS['_wp_stores']['get_var_mapa'] );
		} else {
			$GLOBALS['_wp_stores']['get_var_mapa'] = $this->previo['gvmapa'];
		}

		ini_set( 'error_log', $this->previo['errlog'] ?: '' );
		if ( is_file( (string) ( $GLOBALS['_errlog_tmp'] ?? '' ) ) ) {
			unlink( (string) $GLOBALS['_errlog_tmp'] );
		}
	}

	/**
	 * Llama al método privado.
	 *
	 * @param bool $force Forzar el recálculo.
	 * @return array
	 */
	private function checks( bool $force = false ): array {
		$m = new \ReflectionMethod( Admin_Settings::class, 'get_system_checks' );

		return (array) $m->invoke( $this->ajustes, $force );
	}

	/**
	 * Busca una comprobación por su título.
	 *
	 * @param array  $checks Comprobaciones.
	 * @param string $titulo Título.
	 * @return array|null
	 */
	private function por_titulo( array $checks, string $titulo ): ?array {
		foreach ( $checks as $check ) {
			if ( isset( $check['title'] ) && $titulo === $check['title'] ) {
				return $check;
			}
		}

		return null;
	}

	/* ── Caché ───────────────────────────────────────────────────────── */

	/**
	 * **Una caché válida se usa tal cual.**
	 *
	 * Es lo que evita repetir las comprobaciones en cada carga de la pantalla.
	 */
	public function test_una_cache_valida_se_devuelve_tal_cual(): void {
		$GLOBALS['_wp_stores']['options']['convoca_members_diagnostic_cache'] = array(
			'expires' => time() + 3600,
			'results' => array(
				array(
					'title'   => 'De la caché',
					'status'  => 'ok',
					'message' => 'servido desde la caché',
					'fix'     => '',
				),
			),
		);

		$checks = $this->checks();

		$this->assertCount( 1, $checks, 'no se recalcula' );
		$this->assertSame( 'De la caché', $checks[0]['title'] );
	}

	/**
	 * **Una caché caducada se ignora.**
	 *
	 * Si no caducara, la pantalla seguiría diciendo que todo está bien cuando ya no lo está — y es justo la
	 * pantalla que se mira cuando algo va mal.
	 */
	public function test_una_cache_caducada_se_ignora(): void {
		$GLOBALS['_wp_stores']['options']['convoca_members_diagnostic_cache'] = array(
			'expires' => time() - 10,
			'results' => array(
				array(
					'title'   => 'Vieja',
					'status'  => 'ok',
					'message' => '',
					'fix'     => '',
				),
			),
		);

		$checks = $this->checks();

		$this->assertGreaterThan( 1, count( $checks ), 'se recalcula' );
		$this->assertNull( $this->por_titulo( $checks, 'Vieja' ), 'la vieja no aparece' );
	}

	/**
	 * **Con `$force` se recalcula aunque la caché esté fresca.**
	 *
	 * Es el botón de «volver a comprobar»: si no forzara, el operador pulsaría y no pasaría nada.
	 */
	public function test_con_force_se_recalcula_aunque_la_cache_este_fresca(): void {
		$GLOBALS['_wp_stores']['options']['convoca_members_diagnostic_cache'] = array(
			'expires' => time() + 3600,
			'results' => array(
				array(
					'title'   => 'De la caché',
					'status'  => 'ok',
					'message' => '',
					'fix'     => '',
				),
			),
		);

		$checks = $this->checks( true );

		$this->assertGreaterThan( 1, count( $checks ) );
		$this->assertNull( $this->por_titulo( $checks, 'De la caché' ) );
	}

	/**
	 * El resultado se guarda para la siguiente carga.
	 */
	public function test_el_resultado_queda_en_cache(): void {
		$this->checks( true );

		$cache = $GLOBALS['_wp_stores']['options']['convoca_members_diagnostic_cache'] ?? null;

		$this->assertIsArray( $cache, 'se guardó la caché' );
		$this->assertArrayHasKey( 'expires', $cache );
		$this->assertArrayHasKey( 'results', $cache );
		$this->assertGreaterThan( time(), (int) $cache['expires'], 'y con fecha de caducidad futura' );
	}

	/* ── Severidades ─────────────────────────────────────────────────── */

	/**
	 * **Que falte un plugin opcional es un aviso, no un error.**
	 *
	 * En esta suite Core carga y Enroll y Gateway no, así que es el caso real: el operador tiene que ver «aviso» y
	 * no una pantalla en rojo.
	 */
	public function test_un_plugin_opcional_ausente_es_un_aviso(): void {
		$checks = $this->checks( true );

		$core = $this->por_titulo( $checks, 'Plugin: Convoca Core' );
		$this->assertNotNull( $core );
		$this->assertSame( 'ok', $core['status'], 'Core está cargado' );

		foreach ( array( 'Plugin: Convoca Enroll', 'Plugin: Convoca Gateway' ) as $titulo ) {
			$check = $this->por_titulo( $checks, $titulo );
			$this->assertNotNull( $check, 'falta la comprobación de ' . $titulo );
			$this->assertSame( 'warning', $check['status'], $titulo . ' es opcional' );
			$this->assertNotSame( '', (string) $check['fix'], 'y dice qué hacer' );
			$this->assertStringContainsString( 'Instala y activa', (string) $check['fix'] );
		}
	}

	/* ── Base de datos ───────────────────────────────────────────────── */

	/**
	 * Con la base al día, la comprobación sale en `ok`.
	 */
	public function test_la_base_de_datos_al_dia_sale_en_ok(): void {
		$GLOBALS['_wp_stores']['options']['convoca_members_db_version'] = CONVOCA_MEMBERS_DB_VERSION;

		$check = $this->por_titulo( $this->checks( true ), 'Base de Datos' );

		$this->assertNotNull( $check );
		$this->assertSame( 'ok', $check['status'] );
		$this->assertSame( '', (string) $check['fix'], 'no hay nada que arreglar' );
	}

	/**
	 * **Con la base atrasada sale un aviso, no un «todo bien».**
	 *
	 * Es el caso que se da tras actualizar el plugin sin pasar por el instalador: la pantalla tiene que decir que
	 * hay que reactivar.
	 */
	public function test_una_base_de_datos_atrasada_avisa(): void {
		$GLOBALS['_wp_stores']['options']['convoca_members_db_version'] = '1.0';

		$check = $this->por_titulo( $this->checks( true ), 'Base de Datos' );

		$this->assertNotNull( $check );
		$this->assertSame( 'warning', $check['status'] );
		$this->assertStringContainsString( 'vuelve a activar', strtolower( (string) $check['fix'] ) );
	}

	/* ── Páginas ─────────────────────────────────────────────────────── */

	/**
	 * **Sin las páginas, cada una sale en error y con su shortcode escrito en el arreglo.**
	 *
	 * El `fix` es lo único útil de ese aviso: quien lo lee tiene que saber exactamente qué shortcode pegar.
	 */
	public function test_sin_paginas_cada_una_dice_que_shortcode_falta(): void {
		$checks = $this->checks( true );

		$esperados = array(
			'Página: Alta de Socio'             => '[convoca_alta_socio]',
			'Página: Registro Voluntariado'     => '[convoca_voluntariado]',
			'Página: Área de Miembro'           => '[convoca_mi_area]',
			'Página: Verificación de Certificados' => '[convoca_verificar_certificado]',
			'Página: Renovar membresía'         => '[convoca_renovar]',
			'Página: Panel de Reservas'         => '[convoca_panel_reservas]',
		);

		foreach ( $esperados as $titulo => $shortcode ) {
			$check = $this->por_titulo( $checks, $titulo );
			$this->assertNotNull( $check, 'falta la comprobación: ' . $titulo );
			$this->assertSame( 'error', $check['status'], $titulo . ' sin página es un error' );
			$this->assertStringContainsString( $shortcode, (string) $check['fix'], 'el arreglo tiene que nombrar el shortcode' );
		}
	}

	/**
	 * Con la página creada, esa sale en `ok` y dice cuál es.
	 */
	public function test_una_pagina_creada_sale_en_ok_y_con_su_titulo(): void {
		$GLOBALS['_wp_stores']['get_var_mapa'] = array( 'convoca_alta_socio' => '321' );
		$GLOBALS['_wp_stores']['posts'][321]   = (object) array(
			'ID'           => 321,
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Hazte socio',
			'post_content' => '[convoca_alta_socio]',
		);

		$checks = $this->checks( true );
		$check  = $this->por_titulo( $checks, 'Página: Alta de Socio' );

		$this->assertNotNull( $check );
		$this->assertSame( 'ok', $check['status'] );
		$this->assertSame( '', (string) $check['fix'], 'si está, no hay nada que arreglar' );
		$this->assertStringContainsString( 'Hazte socio', (string) $check['message'], 'y se dice cuál es' );
	}

	/**
	 * Todas las comprobaciones traen las cuatro claves: la pantalla las pinta sin comprobar nada.
	 */
	public function test_todas_las_comprobaciones_traen_sus_cuatro_claves(): void {
		$checks = $this->checks( true );

		$this->assertNotEmpty( $checks );

		foreach ( $checks as $check ) {
			foreach ( array( 'title', 'status', 'message', 'fix' ) as $clave ) {
				$this->assertArrayHasKey( $clave, (array) $check, 'una comprobación sin ' . $clave );
			}
			$this->assertContains( $check['status'], array( 'ok', 'warning', 'error' ) );
		}
	}
}
