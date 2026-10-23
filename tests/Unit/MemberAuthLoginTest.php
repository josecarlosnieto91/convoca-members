<?php
/**
 * La sesión del socio: entrar, salir y quién está dentro.
 *
 * `Member_Auth` es la puerta del panel del socio. Un fallo aquí no da error: deja entrar a quien no debe
 * (un socio dado de baja con una sesión vieja), o deja fuera a quien sí (una sesión que expira antes de
 * tiempo), o abre la puerta a probar contraseñas sin freno. Por eso se prueba el recorrido entero: las
 * credenciales, el vínculo con la ficha de socio, el estado, el testigo de sesión y su renovación.
 *
 * El fichero vecino (`MemberAuthTest`) cubre `logout_member_sessions`; aquí va lo demás, que estaba a cero.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Member_Auth;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Member_Auth.php';

/**
 * Entrada, salida y lectura de la sesión del socio.
 */
final class MemberAuthLoginTest extends TestCase {

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
		unset( $_COOKIE['convoca_member_session'], $_SERVER['HTTP_X_CONVOCA_AUTH'], $_SERVER['REMOTE_ADDR'] );
	}

	/**
	 * Deja el arnés como estaba.
	 */
	private function limpiar(): void {
		foreach ( array( '_wp_stores', '_wp_cookies', '_wp_auth', '_wp_auth_intentos', '_wp_auth_cookies', '_wp_usuario_actual', '_wp_logged_in', '_test_posts', '_wp_cache' ) as $clave ) {
			unset( $GLOBALS[ $clave ] );
		}

		$GLOBALS['_wp_stores'] = array(
			'options'    => array(),
			'post_meta'  => array(),
			'transients' => array(),
			'user_meta'  => array(),
			'posts'      => array(),
		);
		\Convoca\Core\Logger::clear();
	}

	/**
	 * Sin usuario o sin contraseña no se intenta nada (y no se llama a WordPress).
	 */
	public function test_faltan_datos(): void {
		$this->assertSame( 'missing_params', Member_Auth::login( '', 'x' )->get_error_code() );
		$this->assertSame( 'missing_params', Member_Auth::login( 'ada', '' )->get_error_code() );
		$this->assertSame( array(), $GLOBALS['_wp_auth_intentos'] ?? array(), 'no se autentica a ciegas' );
	}

	/**
	 * Credenciales incorrectas: no se dice cuál falla.
	 */
	public function test_credenciales_incorrectas(): void {
		$GLOBALS['_wp_auth'] = new \WP_Error( 'invalid', 'no' );

		$r = Member_Auth::login( 'ada', 'mala' );

		$this->assertSame( 'login_failed', $r->get_error_code() );
		$this->assertSame( 'Usuario o contraseña incorrectos.', $r->get_error_message() );
		$this->assertSame( 'warning', $this->ultimo_aviso()['level'] ?? '', 'queda apuntado como aviso' );
	}

	/**
	 * Con la puerta cerrada por intentos, no se prueba la contraseña.
	 *
	 * El limitador vive en el núcleo y cuenta en la base (`INSERT … ON DUPLICATE KEY UPDATE`), que en una
	 * prueba unitaria no acumula: lo que sí se puede fijar es su atajo de caché —un 0 significa «bloqueado»—
	 * y con él la clave que usa, que es **usuario + conexión**. Eso es lo que se afirma aquí: que la puerta
	 * se cierra para esa identidad y no para las demás.
	 */
	public function test_con_la_puerta_cerrada_no_se_prueba_la_contrasena(): void {
		$GLOBALS['_wp_auth']    = new \WP_Error( 'invalid', 'no' );
		$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
		$this->cerrar_la_puerta( 'ada', '203.0.113.7' );

		$r = Member_Auth::login( 'ada', 'otra' );

		$this->assertSame( 'rate_limited', $r->get_error_code() );
		$this->assertStringContainsString( '15 minutos', $r->get_error_message() );
		$this->assertSame( array(), $GLOBALS['_wp_auth_intentos'] ?? array(), 'no llega a probar la contraseña' );
	}

	/**
	 * La puerta se cierra por persona **y** conexión: otra persona desde la misma línea pasa.
	 */
	public function test_la_puerta_no_se_cierra_para_los_demas(): void {
		$GLOBALS['_wp_auth']    = new \WP_Error( 'invalid', 'no' );
		$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
		$this->cerrar_la_puerta( 'ada', '203.0.113.7' );

		$this->assertSame( 'rate_limited', Member_Auth::login( 'ada', 'otra' )->get_error_code() );
		$this->assertSame( 'login_failed', Member_Auth::login( 'grace', 'otra' )->get_error_code(), 'otra identidad pasa' );
	}

	/**
	 * Un usuario sin ficha de socio vinculada no entra.
	 */
	public function test_usuario_sin_ficha_vinculada(): void {
		$this->autenticado( 7 );

		$r = Member_Auth::login( 'ada', 'buena' );

		$this->assertSame( 'not_found', $r->get_error_code() );
		$this->assertStringContainsString( 'no está vinculado', $r->get_error_message() );
		$this->assertSame( array(), $GLOBALS['_wp_cookies'] ?? array(), 'no se manda cookie de sesión' );
	}

	/**
	 * Y si el id vinculado apunta a otra cosa que no es un socio, tampoco.
	 */
	public function test_ficha_que_no_es_un_socio(): void {
		$this->autenticado( 7 );
		$GLOBALS['_wp_stores']['user_meta'][7]['_convoca_member_id'] = 55;
		$GLOBALS['_test_posts'][55]                                  = (object) array(
			'ID'         => 55,
			'post_type'  => 'page',
			'post_title' => 'No es un socio',
		);

		$this->assertSame( 'not_found', Member_Auth::login( 'ada', 'buena' )->get_error_code() );
	}

	/**
	 * Un socio dado de baja no entra, y se apunta como información (no como error del sistema).
	 */
	public function test_socio_de_baja_no_entra(): void {
		$this->socio( 55, 'baja' );
		$this->autenticado( 7 );
		$GLOBALS['_wp_stores']['user_meta'][7]['_convoca_member_id'] = 55;

		$r = Member_Auth::login( 'ada', 'buena' );

		$this->assertSame( 'member_baja', $r->get_error_code() );
		$this->assertSame( array(), $GLOBALS['_wp_cookies'] ?? array() );
		$this->assertSame( 'info', $this->ultimo_aviso()['level'] ?? '' );
	}

	/**
	 * El camino bueno: testigo de 32 caracteres, su sesión guardada y la cookie cerrada.
	 */
	public function test_entrar(): void {
		$this->socio( 55 );
		$this->autenticado( 7 );
		$GLOBALS['_wp_stores']['user_meta'][7]['_convoca_member_id'] = 55;

		$token = Member_Auth::login( 'ada', 'buena' );

		$this->assertIsString( $token );
		$this->assertSame( 32, strlen( $token ), 'testigo largo, no adivinable' );

		$sesion = get_transient( 'convoca_member_session_' . $token );
		$this->assertSame( 55, $sesion['id'] );
		$this->assertSame( 7, $sesion['wp_user_id'] );
		$this->assertFalse( $sesion['pending_cookie'] );

		$cookie = $this->cookie();
		$this->assertSame( 'convoca_member_session', $cookie['nombre'] );
		$this->assertSame( $token, $cookie['valor'] );
		$this->assertTrue( $cookie['opciones']['httponly'], 'la cookie no la lee el JavaScript' );
		$this->assertSame( 'Lax', $cookie['opciones']['samesite'] );
		$this->assertGreaterThan( time(), $cookie['opciones']['expires'] );

		$this->assertSame( array( 7 ), array_column( $GLOBALS['_wp_auth_cookies'] ?? array(), 'usuario' ), 'una sola entrada sirve también para WordPress' );
		$this->assertSame( 'info', $this->ultimo_aviso()['level'] ?? '' );
	}

	/**
	 * Si la sesión de WordPress ya estaba abierta, no se vuelve a abrir.
	 */
	public function test_no_reabre_la_sesion_de_wordpress(): void {
		$this->socio( 55 );
		$this->autenticado( 7 );
		$GLOBALS['_wp_stores']['user_meta'][7]['_convoca_member_id'] = 55;
		$GLOBALS['_wp_logged_in']                                   = true;

		Member_Auth::login( 'ada', 'buena' );

		$this->assertSame( array(), $GLOBALS['_wp_auth_cookies'] ?? array() );
	}

	/**
	 * Salir borra la sesión y la cookie.
	 */
	public function test_salir(): void {
		$_COOKIE['convoca_member_session'] = 'testigo';
		set_transient( 'convoca_member_session_testigo', array( 'id' => 55 ) );

		Member_Auth::logout();

		$this->assertFalse( get_transient( 'convoca_member_session_testigo' ) );
		$cookie = $this->cookie();
		$this->assertSame( '', $cookie['valor'], 'se manda vacía' );
		$this->assertLessThan( time(), $cookie['opciones']['expires'], 'y ya caducada' );
	}

	/**
	 * Salir sin sesión no borra nada ajeno.
	 */
	public function test_salir_sin_sesion(): void {
		set_transient( 'convoca_member_session_otro', array( 'id' => 99 ) );

		Member_Auth::logout();

		$this->assertNotFalse( get_transient( 'convoca_member_session_otro' ) );
	}

	/**
	 * El testigo se lee de la cookie; y de la cabecera cuando no hay cookie.
	 */
	public function test_de_donde_sale_el_testigo(): void {
		$this->assertSame( '', Member_Auth::get_current_token() );

		$_COOKIE['convoca_member_session'] = 'desde-cookie';
		$this->assertSame( 'desde-cookie', Member_Auth::get_current_token() );

		unset( $_COOKIE['convoca_member_session'] );
		$_SERVER['HTTP_X_CONVOCA_AUTH'] = 'desde-cabecera';
		$this->assertSame( 'desde-cabecera', Member_Auth::get_current_token() );
	}

	/**
	 * Con una sesión buena se sabe quién es.
	 */
	public function test_quien_esta_dentro(): void {
		$this->socio( 55, 'activo' );
		$this->sesion( 'testigo', 55 );

		$this->assertSame( 55, Member_Auth::get_current_member_id() );
		$this->assertTrue( Member_Auth::is_authenticated() );
		$this->assertTrue( Member_Auth::is_active() );
	}

	/**
	 * Un socio dado de baja con una sesión vieja queda fuera, y la sesión se tira.
	 */
	public function test_una_sesion_vieja_de_un_socio_de_baja_no_vale(): void {
		$this->socio( 55, 'baja' );
		$this->sesion( 'testigo', 55 );

		$this->assertSame( 0, Member_Auth::get_current_member_id() );
		$this->assertFalse( Member_Auth::is_authenticated() );
		$this->assertFalse( get_transient( 'convoca_member_session_testigo' ), 'y se borra' );
	}

	/**
	 * Estar dentro no es estar activo: un socio pendiente ve su panel pero no cuenta como activo.
	 */
	public function test_estar_dentro_no_es_estar_activo(): void {
		$this->socio( 55, 'pendiente' );
		$this->sesion( 'testigo', 55 );

		$this->assertSame( 55, Member_Auth::get_current_member_id() );
		$this->assertTrue( Member_Auth::is_authenticated() );
		$this->assertFalse( Member_Auth::is_active(), 'pendiente no es activo' );
	}

	/**
	 * Sin sesión, sin testigo o con una sesión sin socio, no hay nadie dentro.
	 *
	 * La guarda `if ( ! $member_id ) return 0;` es redundante con el `return $member_id` del final: con
	 * socio 0 las dos salidas dan 0. Se dice, no se finge que se pueda distinguir.
	 */
	public function test_sin_sesion_no_hay_nadie(): void {
		$this->assertSame( 0, Member_Auth::get_current_member_id() );

		$_COOKIE['convoca_member_session'] = 'inventado';
		$this->assertSame( 0, Member_Auth::get_current_member_id(), 'sesión que no existe' );

		set_transient( 'convoca_member_session_sinid', array( 'last_renewal' => time() ) );
		$_COOKIE['convoca_member_session'] = 'sinid';
		$this->assertSame( 0, Member_Auth::get_current_member_id(), 'sesión sin socio' );
	}

	/**
	 * La sesión se renueva sola: pasado el cuarto de hora se vuelve a guardar con la marca al día.
	 */
	public function test_la_sesion_se_renueva(): void {
		$this->socio( 55, 'activo' );
		$viejo = time() - ( 20 * MINUTE_IN_SECONDS );
		set_transient(
			'convoca_member_session_testigo',
			array(
				'id'             => 55,
				'wp_user_id'     => 7,
				'last_renewal'   => $viejo,
				'pending_cookie' => false,
			)
		);
		$_COOKIE['convoca_member_session'] = 'testigo';

		Member_Auth::get_current_member_id();

		$sesion = get_transient( 'convoca_member_session_testigo' );
		$this->assertGreaterThan( $viejo, $sesion['last_renewal'], 'la marca se pone al día' );
	}

	/**
	 * Los datos del socio que ve el panel.
	 */
	public function test_los_datos_del_socio(): void {
		$this->socio( 55, 'activo' );
		$GLOBALS['_wp_stores']['post_meta'][55]['_convoca_email']          = 'ada@example.test';
		$GLOBALS['_wp_stores']['post_meta'][55]['_convoca_telefono']        = '600000000';
		$GLOBALS['_wp_stores']['post_meta'][55]['_convoca_dni']             = '00000000T';
		$this->sesion( 'testigo', 55 );

		$datos = Member_Auth::get_current_member_data();

		$this->assertSame( 55, $datos['id'] );
		$this->assertSame( 'Ada Lovelace', $datos['name'] );
		$this->assertSame( 'ada@example.test', $datos['email'] );
		$this->assertSame( 'activo', $datos['member_status'] );
	}

	/**
	 * Y sin sesión, nada.
	 */
	public function test_sin_sesion_no_hay_datos(): void {
		$this->assertNull( Member_Auth::get_current_member_data() );
	}

	/**
	 * Cierra la puerta del limitador para una identidad (usuario + conexión).
	 *
	 * Se toca el atajo de caché del núcleo, donde un 0 significa «bloqueado». La clave es la misma que
	 * calcula `Utils::check_rate_limit()`: `convoca_rl_<acción>_id_<md5(identidad)>`.
	 *
	 * @param string $usuario Usuario.
	 * @param string $ip      Conexión.
	 */
	private function cerrar_la_puerta( string $usuario, string $ip ): void {
		$identidad = strtolower( $usuario . '@' . $ip );

		$GLOBALS['_wp_cache']['convoca_rate_limits'][ 'convoca_rl_login_id_' . md5( $identidad ) ] = 0;
	}

	/**
	 * El último aviso que se escribió en el registro.
	 *
	 * El `Logger` real escribe en la tabla de logs: en una prueba unitaria no hay buffer en memoria, así
	 * que lo observable es la fila insertada.
	 *
	 * @return array
	 */
	private function ultimo_aviso(): array {
		$inserts = $GLOBALS['_wp_stores']['db_inserts'] ?? array();
		foreach ( array_reverse( $inserts ) as $insert ) {
			if ( false !== strpos( (string) $insert['table'], 'convoca_logs' ) ) {
				return (array) $insert['data'];
			}
		}

		return array();
	}

	/**
	 * La última cookie que se mandó.
	 *
	 * @return array
	 */
	private function cookie(): array {
		$cookies = $GLOBALS['_wp_cookies'] ?? array();
		$this->assertNotEmpty( $cookies, 'no se mandó ninguna cookie' );

		return end( $cookies );
	}

	/**
	 * Deja un socio en el arnés.
	 *
	 * @param int    $id     Socio.
	 * @param string $estado Estado de la membresía.
	 */
	private function socio( int $id, string $estado = 'activo' ): void {
		// Convención del arnés del núcleo: los posts se siembran en `_test_posts`.
		$GLOBALS['_test_posts'][ $id ] = (object) array(
			'ID'          => $id,
			'post_type'   => 'miembro',
			'post_status' => 'publish',
			'post_title'  => 'Ada Lovelace',
		);
		$GLOBALS['_wp_stores']['post_meta'][ $id ]['_convoca_estado_miembro'] = $estado;
	}

	/**
	 * Deja una sesión abierta.
	 *
	 * @param string $token Testigo.
	 * @param int    $socio Socio.
	 */
	private function sesion( string $token, int $socio ): void {
		set_transient(
			'convoca_member_session_' . $token,
			array(
				'id'             => $socio,
				'wp_user_id'     => 7,
				'last_renewal'   => time(),
				'pending_cookie' => false,
			)
		);
		$_COOKIE['convoca_member_session'] = $token;
	}

	/**
	 * WordPress autentica al usuario.
	 *
	 * @param int $id Usuario.
	 */
	private function autenticado( int $id ): void {
		$GLOBALS['_wp_auth'] = (object) array(
			'ID'         => $id,
			'user_login' => 'ada',
		);
	}
}
