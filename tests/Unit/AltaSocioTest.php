<?php
/**
 * El alta de socio por el formulario público.
 *
 * `public/class-form-handler.php` estaba al 0 % (118 statements) y es la puerta por la que entra un socio
 * con sus datos personales: valida el nonce, limita los intentos (por persona, no por conexión), delega
 * el alta y contesta 200/400/403/429. Si una guarda se cae, cualquiera puede crear socios en masa o leer
 * el mensaje equivocado cuando el bloqueo es de su red.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Form_Handler;
use PHPUnit\Framework\TestCase;

class AltaSocioTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Miembro.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Process_Member.php';
		require_once dirname( __DIR__, 2 ) . '/public/class-form-handler.php';

		// Almacenes frescos, incluida la caché de objetos: si queda el «bloqueado» de la prueba anterior,
		// las siguientes salen 429 sin haber hecho nada.
		$GLOBALS['_wp_stores'] = array( 'options' => array(), 'transients' => array(), 'acciones' => array(), 'shortcodes' => array(), 'cache' => array() );
		unset( $GLOBALS['_wp_cache'], $GLOBALS['_wp_cache_objects'] );
		$GLOBALS['_cm_json']            = array();
		$GLOBALS['_cm_nonces_comprobados'] = array();
		unset( $GLOBALS['_cm_contador'], $GLOBALS['_cm_cache'] );
		$GLOBALS['convoca_test_posts']  = array();
		$GLOBALS['convoca_test_meta']   = array();
		$GLOBALS['convoca_test_caps']   = array();
		$_FILES                         = array();
		$_POST                          = array();
		$_SERVER['REMOTE_ADDR']         = '203.0.113.9';
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores'] = array();
		unset( $GLOBALS['_cm_json'], $GLOBALS['_cm_nonces_comprobados'], $GLOBALS['_cm_nonce_ok'], $GLOBALS['_cm_contador'], $GLOBALS['_cm_cache'], $_FILES, $_POST );
	}

	/**
	 * El manejador se engancha: shortcode, los tres endpoints AJAX y la ruta REST.
	 */
	public function test_se_engancha_al_formulario_y_a_sus_endpoints(): void {
		$GLOBALS['_wp_shortcodes'] = array();
		$GLOBALS['_wp_hooks']      = array();

		new Form_Handler();

		$this->assertArrayHasKey( 'convoca_alta_socio', $GLOBALS['_wp_shortcodes'] ?? array(), 'el formulario se pinta por shortcode' );

		foreach ( array( 'wp_ajax_convoca_alta_submit', 'wp_ajax_nopriv_convoca_alta_submit', 'wp_ajax_convoca_get_nonce', 'wp_ajax_nopriv_convoca_get_nonce', 'rest_api_init' ) as $hook ) {
			$this->assertNotEmpty( $GLOBALS['_wp_hooks'][ $hook ] ?? array(), "falta el enganche {$hook}" );
		}
	}

	/**
	 * Sin nonce no se crea nada y se contesta 403.
	 */
	public function test_sin_nonce_no_se_crea_nada(): void {
		$GLOBALS['_wp_stores']['nonces_validos'] = array( 'el-bueno' );

		$respuesta = Form_Handler::handle_submit_rest( new \WP_REST_Request() );

		$this->assertSame( 403, $respuesta->get_status() );
		$this->assertFalse( $respuesta->get_data()['success'] );
		$this->assertStringContainsString( 'no autorizada', $respuesta->get_data()['data']['errors'][0] );
		$this->assertSame( array(), $GLOBALS['convoca_test_posts'], 'no se ha dado de alta a nadie' );
	}

	/**
	 * Con un nonce que no vale, tampoco.
	 */
	public function test_un_nonce_invalido_tampoco_pasa(): void {
		$GLOBALS['_wp_stores']['nonces_validos'] = array( 'el-bueno' );

		$peticion = new \WP_REST_Request();
		$peticion->set_header( 'X-Conv-Nonce', 'el-malo' );

		$respuesta = Form_Handler::handle_submit_rest( $peticion );

		$this->assertSame( 403, $respuesta->get_status() );
	}

	/**
	 * El nonce también puede venir en el cuerpo (clientes que no mandan cabecera).
	 */
	public function test_el_nonce_del_cuerpo_sirve(): void {
		$GLOBALS['_wp_stores']['nonces_validos'] = array( 'el-bueno' );

		$peticion = new \WP_REST_Request();
		$peticion->set_param( 'nonce', 'el-bueno' );

		$respuesta = Form_Handler::handle_submit_rest( $peticion );

		$this->assertNotSame( 403, $respuesta->get_status(), 'con el nonce bueno no se rechaza por autorización' );
	}

	/**
	 * El límite de intentos es POR PERSONA: al cuarto intento de la misma cuenta se bloquea.
	 */
	public function test_el_limite_de_intentos_bloquea_por_persona(): void {
		$GLOBALS['_wp_stores']['nonces_validos'] = array( 'el-bueno' );

		// El limitador del núcleo guarda caducidad en los bits altos e intentos en los bajos: se
		// siembra un contador de 4 intentos con caducidad futura, que es el estado real tras pasarse.
		$GLOBALS['_wp_stores']['get_var_mapa'] = array(
			'SELECT option_value FROM' => (string) ( ( ( time() + 3600 ) << 20 ) | 4 ),
		);

		$peticion = new \WP_REST_Request();
		$peticion->set_param( 'nonce', 'el-bueno' );
		$peticion->set_json_params( array( 'email' => 'ana@example.test' ) );

		$respuesta = Form_Handler::handle_submit_rest( $peticion );

		$this->assertSame( 429, $respuesta->get_status(), 'cuatro intentos de la misma persona se cortan' );
		$this->assertFalse( $respuesta->get_data()['success'] );
		$this->assertSame( array(), $GLOBALS['convoca_test_posts'], 'y no se crea a nadie' );
	}

	/**
	 * El mensaje de bloqueo dice a quién escribir, y usa el correo de contacto del centro.
	 */
	public function test_el_mensaje_de_bloqueo_lleva_el_correo_de_contacto(): void {
		$GLOBALS['_wp_stores']['options']['convoca_members_settings'] = array( 'contact_email' => 'socios@example.test' );

		$mensaje = Form_Handler::mensaje_bloqueo();

		$this->assertStringContainsString( 'socios@example.test', $mensaje, 'la persona sabe a quién escribir' );
		$this->assertStringContainsString( 'intentos', $mensaje );
	}

	/**
	 * Sin correo de contacto válido se usa el del sitio: nunca un mensaje sin destinatario.
	 */
	public function test_sin_contacto_valido_se_usa_el_correo_del_sitio(): void {
		$GLOBALS['_wp_stores']['options']['convoca_members_settings'] = array( 'contact_email' => 'no-es-un-correo' );
		$GLOBALS['_wp_stores']['options']['admin_email']             = 'admin@example.test';

		$this->assertStringContainsString( 'admin@example.test', Form_Handler::mensaje_bloqueo() );
	}

	/**
	 * Sin plan válido, el alta se rechaza con 400 y el motivo a la vista.
	 */
	public function test_sin_plan_valido_el_alta_se_rechaza(): void {
		$GLOBALS['_wp_stores']['nonces_validos'] = array( 'el-bueno' );

		$peticion = new \WP_REST_Request();
		$peticion->set_param( 'nonce', 'el-bueno' );
		$peticion->set_param( 'nombre', 'Ana' );
		$peticion->set_param( 'email', 'ana@example.test' );
		$peticion->set_param( 'plan', 'inexistente' );

		$respuesta = Form_Handler::handle_submit_rest( $peticion );

		$this->assertSame( 400, $respuesta->get_status(), 'un alta que no valida no puede contestar 200' );
		$this->assertFalse( $respuesta->get_data()['success'] );
		$this->assertNotEmpty( $respuesta->get_data()['data']['errors'][0], 'y dice por qué' );
	}

	/**
	 * El endpoint del nonce contesta nonce, nonce REST y planes; sin caché.
	 */
	public function test_el_endpoint_del_nonce_contesta_lo_que_necesita_el_formulario(): void {
		$GLOBALS['_wp_json'] = array();

		$manejador = new Form_Handler();
		$manejador->ajax_get_nonce();

		$respuesta = $GLOBALS['_wp_json'][0];
		$this->assertTrue( $respuesta['ok'] );
		$this->assertArrayHasKey( 'nonce', $respuesta['data'] );
		$this->assertArrayHasKey( 'rest_nonce', $respuesta['data'], 'el formulario necesita el nonce de REST' );
		$this->assertArrayHasKey( 'plans', $respuesta['data'] );
	}

	/**
	 * El endpoint del nonce también tiene su límite: pasado, contesta 429.
	 */
	public function test_el_endpoint_del_nonce_se_limita(): void {
		$GLOBALS['_wp_stores']['get_var_mapa'] = array(
			// El tope de la conexión es max( 30*5, 30+20 ) = 150: hay que pasar de ahí para bloquear.
			'SELECT option_value FROM' => (string) ( ( ( time() + 3600 ) << 20 ) | 151 ),
		);

		$GLOBALS['_wp_json'] = array();
		$manejador            = new Form_Handler();

		try {
			$manejador->ajax_get_nonce();
			$this->fail( 'pasado el límite no puede contestar con éxito' );
		} catch ( \RuntimeException $e ) {
			$this->assertStringStartsWith( 'wp_send_json_error', $e->getMessage() );
		}

		$this->assertFalse( $GLOBALS['_wp_json'][0]['ok'], 'la respuesta es de error' );
		$this->assertSame( 429, $GLOBALS['_wp_json'][0]['status'] );
	}
}
