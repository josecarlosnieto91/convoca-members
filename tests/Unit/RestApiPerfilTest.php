<?php
/**
 * Editar el perfil desde el área de socio.
 *
 * Tres cosas no pueden fallar aquí. Que **no se borre lo que no se envía** (un formulario que sólo manda el
 * teléfono no puede dejar la dirección en blanco). Que el **cumpleaños se fije una sola vez** —se usa para saber
 * quién cumple años, y si se puede cambiar deja de servir—. Y que el **correo no se cambie al instante**: se
 * manda un enlace y hasta que se confirma, la cuenta sigue con el anterior.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Rest_API;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Member_Auth.php';
require_once dirname( __DIR__, 2 ) . '/includes/Rest_API.php';

/**
 * Edición del perfil.
 */
final class RestApiPerfilTest extends TestCase {

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
		);
	}

	/**
	 * Abre la sesión de un socio.
	 *
	 * @param int   $id   Socio.
	 * @param array $meta Metadatos de la ficha.
	 */
	private function con_sesion( int $id = 55, array $meta = array() ): void {
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
				'_convoca_direccion'      => 'Calle de siempre 1',
			),
			$meta
		);
	}

	/**
	 * Sin ficha no hay perfil que editar.
	 */
	public function test_sin_ficha(): void {
		$respuesta = $this->api->update_profile( new \WP_REST_Request( array( 'direccion' => 'Nueva 2' ) ) );

		$this->assertSame( 404, $respuesta->get_status() );
	}

	/**
	 * Lo que no se envía no se toca: un formulario que sólo manda el teléfono no borra la dirección.
	 */
	public function test_no_borra_lo_que_no_se_envia(): void {
		$this->con_sesion();

		$respuesta = $this->api->update_profile( new \WP_REST_Request( array( 'telefono' => '600000000' ) ) );

		$this->assertTrue( $respuesta->get_data()['success'] );
		$this->assertSame( '600000000', get_post_meta( 55, '_convoca_telefono', true ) );
		$this->assertSame(
			'Calle de siempre 1',
			get_post_meta( 55, '_convoca_direccion', true ),
			'la dirección sigue donde estaba'
		);
		$this->assertSame( array( 'telefono' ), $respuesta->get_data()['changes'], 'y sólo se apunta lo cambiado' );
	}

	/**
	 * El cumpleaños se fija una vez. La segunda vez se rechaza con su motivo.
	 */
	public function test_el_cumpleanos_una_sola_vez(): void {
		$this->con_sesion();

		$primera = $this->api->update_profile( new \WP_REST_Request( array( 'cumpleanos' => '1990-05-17' ) ) );
		$this->assertTrue( $primera->get_data()['success'] );
		$this->assertSame( '1990-05-17', get_post_meta( 55, '_convoca_cumpleanos', true ) );

		$segunda = $this->api->update_profile( new \WP_REST_Request( array( 'cumpleanos' => '1991-01-01' ) ) );

		$this->assertSame( 400, $segunda->get_status(), 'no se puede cambiar' );
		$this->assertStringContainsString( 'ya está establecido', $segunda->get_data()['error'] );
		$this->assertSame( '1990-05-17', get_post_meta( 55, '_convoca_cumpleanos', true ), 'y el valor no se toca' );
	}

	/**
	 * Una fecha futura o inventada no entra.
	 */
	public function test_el_cumpleanos_no_puede_ser_futuro_ni_inventado(): void {
		$this->con_sesion();

		$futuro = $this->api->update_profile(
			new \WP_REST_Request( array( 'cumpleanos' => gmdate( 'Y-m-d', time() + ( 400 * DAY_IN_SECONDS ) ) ) )
		);
		$this->assertSame( 400, $futuro->get_status() );

		$inventado = $this->api->update_profile( new \WP_REST_Request( array( 'cumpleanos' => 'no-es-una-fecha' ) ) );
		$this->assertSame( 400, $inventado->get_status() );
		$this->assertSame( '', (string) get_post_meta( 55, '_convoca_cumpleanos', true ) );
	}

	/**
	 * El correo no se cambia al instante: queda pendiente, con testigo de un día, y se avisa para confirmar.
	 */
	public function test_el_correo_queda_pendiente(): void {
		$this->con_sesion();

		$respuesta = $this->api->update_profile( new \WP_REST_Request( array( 'email' => 'nueva@example.test' ) ) );
		$datos     = $respuesta->get_data();

		$this->assertTrue( $datos['success'] );
		$this->assertTrue( $datos['email_pendiente'] );
		$this->assertSame( array( 'email_pendiente' ), $datos['changes'] );
		$this->assertSame( 'ada@example.test', get_post_meta( 55, '_convoca_email', true ), 'el correo real NO cambia' );
		$this->assertSame( 'nueva@example.test', get_post_meta( 55, '_convoca_email_pendiente', true ) );

		$token = (string) get_post_meta( 55, '_convoca_email_token', true );
		$exp   = (int) get_post_meta( 55, '_convoca_email_token_exp', true );
		$this->assertSame( 32, strlen( $token ), 'testigo de 32 caracteres' );
		$this->assertGreaterThan( time(), $exp, 'con caducidad futura' );
		$this->assertLessThanOrEqual( time() + DAY_IN_SECONDS + 5, $exp, 'de un día como mucho' );

		$hooks = array_column( $GLOBALS['_wp_actions'] ?? array(), 'hook' );
		$this->assertContains( 'convoca_members_email_confirm', $hooks, 'se avisa para confirmar' );
	}

	/**
	 * Un correo mal escrito o ya usado por otro socio se rechaza, y no se deja nada pendiente.
	 */
	public function test_el_correo_malo_o_repetido(): void {
		$this->con_sesion();

		$malo = $this->api->update_profile( new \WP_REST_Request( array( 'email' => 'no-es-correo' ) ) );
		$this->assertSame( 400, $malo->get_status() );

		// Otra ficha con ese correo.
		$GLOBALS['_wp_stores']['posts'][56]              = (object) array( 'ID' => 56, 'post_type' => 'miembro', 'post_status' => 'publish', 'post_title' => 'Otra' );
		$GLOBALS['_wp_stores']['post_meta'][56]['_convoca_email'] = 'ocupado@example.test';

		$repetido = $this->api->update_profile( new \WP_REST_Request( array( 'email' => 'ocupado@example.test' ) ) );
		$this->assertSame( 400, $repetido->get_status() );
		$this->assertStringContainsString( 'ya está en uso', $repetido->get_data()['error'] );
		$this->assertSame( '', (string) get_post_meta( 55, '_convoca_email_pendiente', true ), 'no queda nada pendiente' );
	}

	/**
	 * Mandar el mismo correo que ya tiene no genera un pendiente absurdo.
	 */
	public function test_el_mismo_correo_no_hace_nada(): void {
		$this->con_sesion();

		$respuesta = $this->api->update_profile( new \WP_REST_Request( array( 'email' => 'ada@example.test' ) ) );

		$this->assertTrue( $respuesta->get_data()['success'] );
		$this->assertSame( array(), $respuesta->get_data()['changes'], 'nada que cambiar' );
		$this->assertFalse( $respuesta->get_data()['email_pendiente'] );
	}
}
