<?php
/**
 * Las escrituras del área de socio: confirmar el correo nuevo, verificar el teléfono, pedir la baja y la supresión.
 *
 * Son cuatro puertas con token o con estado detrás. Si el token no caduca, un enlace viejo sigue cambiando el
 * correo de una persona; si la baja no queda registrada, la junta no se entera; y si la supresión se da por hecha
 * sin fecha, no hay nada que enseñar si alguien pregunta.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Estados;
use Convoca\Members\Rest_API;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Estados.php';
require_once dirname( __DIR__, 2 ) . '/includes/Member_Auth.php';
require_once dirname( __DIR__, 2 ) . '/includes/GDPR_Tools.php';
require_once dirname( __DIR__, 2 ) . '/includes/Rest_API.php';

/**
 * Confirmaciones y solicitudes.
 */
final class RestApiAltasBajasTest extends TestCase {

	/**
	 * API.
	 *
	 * @var Rest_API
	 */
	private Rest_API $api;

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->limpiar();
		$this->api = new Rest_API();
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
		unset( $GLOBALS['_wp_stores'], $GLOBALS['_test_posts'], $GLOBALS['_wp_actions'], $_COOKIE['convoca_member_session'] );

		$GLOBALS['_wp_stores'] = array(
			'options'    => array(),
			'post_meta'  => array(),
			'transients' => array(),
			'user_meta'  => array(),
			'emails'     => array(),
			'posts'      => array(),
			'usuarios'   => array(),
			'locks'      => array(),
			'logs'       => array(),
			'db_inserts' => array(),
		);
	}

	/**
	 * Abre la sesión de un socio con esos metadatos.
	 *
	 * @param int   $id   Socio.
	 * @param array $meta Metadatos.
	 */
	private function con_sesion( int $id, array $meta = array() ): void {
		$testigo = 'testigo-de-prueba';

		$GLOBALS['_wp_stores']['transients'][ 'convoca_member_session_' . $testigo ] = array(
			'id'             => $id,
			'wp_user_id'     => 900 + $id,
			'last_renewal'   => time(),
			'pending_cookie' => '',
		);
		$_COOKIE['convoca_member_session'] = $testigo;

		$ficha                                 = (object) array(
			'ID'          => $id,
			'post_type'   => 'miembro',
			'post_status' => 'publish',
			'post_title'  => 'Ada Lovelace',
		);
		$GLOBALS['_test_posts'][ $id ]          = $ficha;
		$GLOBALS['_wp_stores']['posts'][ $id ]  = $ficha;
		$GLOBALS['_wp_stores']['post_meta'][ $id ] = array_merge(
			array(
				'_convoca_estado_miembro' => 'activo',
				'_convoca_email'          => 'ada@example.test',
			),
			$meta
		);
	}

	/**
	 * Confirmar el correo: sin token, con token que no es y con token caducado no se cambia nada.
	 *
	 * La guarda `empty( $token )` es **redundante** con `hash_equals()` (una cadena vacía nunca es igual a un
	 * testigo guardado), pero se deja porque dice la intención; lo que se afirma es el efecto: 400 y sin tocar
	 * el correo.
	 */
	public function test_confirmar_el_correo_no_acepta_lo_que_no_vale(): void {
		$this->con_sesion(
			55,
			array(
				'_convoca_email_pendiente'  => 'nueva@example.test',
				'_convoca_email_token'      => 'bueno',
				'_convoca_email_token_exp'  => time() + 3600,
			)
		);

		$sin = $this->api->confirm_email( new \WP_REST_Request() );
		$this->assertSame( 400, $sin->get_status(), 'sin token no se confirma' );

		$otro = $this->api->confirm_email( new \WP_REST_Request( array( 'token' => 'falso' ) ) );
		$this->assertSame( 400, $otro->get_status(), 'con otro token tampoco' );

		$this->assertSame( 'ada@example.test', get_post_meta( 55, '_convoca_email', true ), 'el correo no se ha tocado' );

		// Caducado.
		$GLOBALS['_wp_stores']['post_meta'][55]['_convoca_email_token_exp'] = time() - 10;
		$caducado = $this->api->confirm_email( new \WP_REST_Request( array( 'token' => 'bueno' ) ) );

		$this->assertSame( 400, $caducado->get_status() );
		$this->assertStringContainsString( 'caducado', $caducado->get_data()['error'] );
	}

	/**
	 * Con token bueno se aplica el correo pendiente, se limpian los testigos y se avisa.
	 */
	public function test_confirmar_el_correo_bueno(): void {
		$this->con_sesion(
			55,
			array(
				'_convoca_email_pendiente' => 'nueva@example.test',
				'_convoca_email_token'     => 'bueno',
				'_convoca_email_token_exp' => time() + 3600,
			)
		);

		$respuesta = $this->api->confirm_email( new \WP_REST_Request( array( 'token' => 'bueno' ) ) );

		$this->assertTrue( $respuesta->get_data()['success'] );
		$this->assertSame( 'nueva@example.test', $respuesta->get_data()['email'] );
		$this->assertSame( 'nueva@example.test', get_post_meta( 55, '_convoca_email', true ), 'el correo nuevo está puesto' );
		$this->assertSame( '', (string) get_post_meta( 55, '_convoca_email_token', true ), 'el testigo no se queda' );
		$this->assertSame( '', (string) get_post_meta( 55, '_convoca_email_pendiente', true ) );
		$this->assertSame( '', (string) get_post_meta( 55, '_convoca_email_token_exp', true ), 'y su caducidad tampoco se queda' );

		$hooks = array_column( $GLOBALS['_wp_actions'] ?? array(), 'hook' );
		$this->assertContains( 'convoca_members_email_cambiado', $hooks, 'el resto del ecosistema se entera' );
	}

	/**
	 * Verificar el teléfono: sin ficha, 404; sin número, 400; ya verificado, nada que hacer.
	 */
	public function test_verificar_el_telefono(): void {
		$sin_ficha = $this->api->verify_phone( new \WP_REST_Request() );
		$this->assertSame( 404, $sin_ficha->get_status() );

		$this->con_sesion( 55 );
		$sin_numero = $this->api->verify_phone( new \WP_REST_Request() );
		$this->assertSame( 400, $sin_numero->get_status(), 'primero hay que guardar el número' );

		$GLOBALS['_wp_stores']['post_meta'][55]['_convoca_telefono']            = '600000000';
		$GLOBALS['_wp_stores']['post_meta'][55]['_convoca_telefono_verificado'] = '1';
		$ya = $this->api->verify_phone( new \WP_REST_Request() );

		$this->assertTrue( $ya->get_data()['verified'], 'ya estaba verificado' );
		$this->assertSame( '', (string) get_post_meta( 55, '_convoca_telefono_token', true ), 'y no se genera otro' );
	}

	/**
	 * Y con número sin verificar se genera un testigo que caduca en un día.
	 */
	public function test_verificar_el_telefono_genera_testigo(): void {
		$this->con_sesion( 55, array( '_convoca_telefono' => '600000000' ) );

		$this->api->verify_phone( new \WP_REST_Request() );

		$token = (string) get_post_meta( 55, '_convoca_telefono_token', true );
		$exp   = (int) get_post_meta( 55, '_convoca_telefono_token_exp', true );

		$this->assertNotSame( '', $token, 'se genera el testigo' );
		$this->assertGreaterThan( time(), $exp, 'con caducidad futura' );
		$this->assertLessThanOrEqual( time() + DAY_IN_SECONDS + 5, $exp, 'de un día como mucho' );
	}

	/**
	 * Pedir la baja deja el estado y avisa; no borra nada (la baja no es la supresión).
	 */
	public function test_pedir_la_baja(): void {
		$this->con_sesion( 55 );

		$respuesta = $this->api->unsubscribe_request( new \WP_REST_Request() );

		$this->assertTrue( $respuesta->get_data()['success'] );
		$this->assertSame(
			'baja_solicitada',
			(string) get_post_meta( 55, '_convoca_estado_miembro', true ),
			'el estado lo dice'
		);
		$this->assertSame(
			'ada@example.test',
			(string) get_post_meta( 55, '_convoca_email', true ),
			'pero los datos personales siguen ahí: eso es la supresión'
		);

		$historial = get_post_meta( 55, '_convoca_historial', true );
		$this->assertIsArray( $historial, 'el cambio de estado deja rastro' );
		$ultimo = end( $historial );
		$this->assertSame( 'baja_solicitada', $ultimo['a'] ?? '', 'con el estado nuevo' );
		$this->assertSame(
			'Solicitud de baja desde el panel de socio.',
			$ultimo['nota'] ?? '',
			'y el motivo: es lo que lee quien la revisa'
		);

		$hooks = array_column( $GLOBALS['_wp_actions'] ?? array(), 'hook' );
		$this->assertContains( 'convoca_members_unsubscribe_request', $hooks );
	}

	/**
	 * La supresión desde el panel: devuelve su fecha, y sin ficha falla con 500 (no finge éxito).
	 */
	public function test_solicitar_la_supresion_desde_el_panel(): void {
		$GLOBALS['_wp_stores']['options']['admin_email'] = 'junta@example.test';

		$sin_sesion = $this->api->solicitar_supresion( new \WP_REST_Request() );
		$this->assertSame( 500, $sin_sesion->get_status(), 'sin socio no se puede registrar' );
		$this->assertFalse( $sin_sesion->get_data()['ok'] );

		$this->con_sesion( 55 );
		$buena = $this->api->solicitar_supresion( new \WP_REST_Request() );

		$this->assertTrue( $buena->get_data()['ok'] );
		$this->assertNotSame( '', $buena->get_data()['fecha'], 'con su fecha' );
		$this->assertSame( $buena->get_data()['fecha'], get_post_meta( 55, '_convoca_supresion_solicitada', true ) );
	}
}
