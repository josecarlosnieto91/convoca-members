<?php
/**
 * La zona del socio: confirmar el correo y el teléfono con un token.
 *
 * Aquí se cambia el correo con el que se entra y se verifica un teléfono, así que el token tiene que compararse en
 * tiempo constante, **caducar** y borrarse al usarse. Lo que se prueba es que un token inventado no cambia nada, que
 * uno caducado tampoco, y que el bueno cambia el dato una sola vez.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Mi_Area;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/public/class-mi-area.php';

/**
 * Confirmaciones.
 */
final class MiAreaConfirmacionTest extends TestCase {

	/**
	 * Zona.
	 *
	 * @var Mi_Area
	 */
	private Mi_Area $area;

	/**
	 * Almacenes previos.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->previo = array(
			'posts'     => $GLOBALS['_wp_stores']['posts'] ?? array(),
			'post_meta' => $GLOBALS['_wp_stores']['post_meta'] ?? array(),
			'permalinks' => $GLOBALS['_wp_stores']['permalinks'] ?? array(),
		);

		$GLOBALS['_wp_stores']['posts']     = array();
		$GLOBALS['_wp_stores']['post_meta'] = array();
		$GLOBALS['_wp_hooks']               = array();
		$_GET                               = array();
		$_REQUEST                           = array();
		$this->area                         = new Mi_Area();
	}

	/**
	 * Limpia.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']['posts']     = $this->previo['posts'];
		$GLOBALS['_wp_stores']['post_meta'] = $this->previo['post_meta'];
		$_GET                               = array();
		$_REQUEST                           = array();
	}

	/**
	 * Prepara una ficha con el token pedido.
	 *
	 * @param int   $id        Identificador.
	 * @param array $meta      Meta.
	 * @return void
	 */
	private function ficha( int $id, array $meta ): void {
		$post              = new \WP_Post();
		$post->ID          = $id;
		$post->post_type   = 'miembro';
		$post->post_status = 'publish';

		$GLOBALS['_wp_stores']['posts'][ $id ]     = $post;
		$GLOBALS['_wp_stores']['post_meta'][ $id ] = $meta;
	}

	/**
	 * Sin los parámetros del enlace no se hace nada.
	 */
	public function test_sin_parametros_no_pasa_nada(): void {
		$this->ficha( 55, array( '_convoca_email' => 'viejo@example.test', '_convoca_email_token' => 'abc' ) );

		$this->area->handle_email_confirmation();

		$this->assertSame( 'viejo@example.test', get_post_meta( 55, '_convoca_email', true ) );
	}

	/**
	 * Un token que no coincide no cambia el correo y lo dice.
	 */
	public function test_token_incorrecto(): void {
		$this->ficha(
			55,
			array(
				'_convoca_email'           => 'viejo@example.test',
				'_convoca_email_pendiente' => 'nuevo@example.test',
				'_convoca_email_token'     => 'el-bueno',
				'_convoca_email_token_exp' => (string) ( time() + 3600 ),
			)
		);
		$_GET = array( 'convoca_confirm_email' => '1', 'member' => '55', 'token' => 'el-malo' );

		try {
			$this->area->handle_email_confirmation();
			$this->fail( 'debía redirigir' );
		} catch ( \RuntimeException $r ) {
			$this->assertStringContainsString( 'convoca_confirm_error=token', $r->getMessage() );
		}

		$this->assertSame( 'viejo@example.test', get_post_meta( 55, '_convoca_email', true ), 'el correo no se toca' );
		$this->assertSame( 'nuevo@example.test', get_post_meta( 55, '_convoca_email_pendiente', true ), 'sigue pendiente' );
	}

	/**
	 * Un token caducado tampoco.
	 */
	public function test_token_caducado(): void {
		$this->ficha(
			55,
			array(
				'_convoca_email'           => 'viejo@example.test',
				'_convoca_email_pendiente' => 'nuevo@example.test',
				'_convoca_email_token'     => 'el-bueno',
				'_convoca_email_token_exp' => (string) ( time() - 60 ),
			)
		);
		$_GET = array( 'convoca_confirm_email' => '1', 'member' => '55', 'token' => 'el-bueno' );

		try {
			$this->area->handle_email_confirmation();
		} catch ( \RuntimeException $r ) {
			$this->assertStringContainsString( 'convoca_confirm_error=expirado', $r->getMessage() );
		}

		$this->assertSame( 'viejo@example.test', get_post_meta( 55, '_convoca_email', true ) );
	}

	/**
	 * El token bueno cambia el correo, borra el token y avisa.
	 */
	public function test_token_bueno_cambia_el_correo(): void {
		$GLOBALS['convoca_avisos'] = 0;
		add_action(
			'convoca_email_cambiado',
			static function () {
				++$GLOBALS['convoca_avisos'];
			}
		);

		$this->ficha(
			55,
			array(
				'_convoca_email'           => 'viejo@example.test',
				'_convoca_email_pendiente' => 'nuevo@example.test',
				'_convoca_email_token'     => 'el-bueno',
				'_convoca_email_token_exp' => (string) ( time() + 3600 ),
			)
		);
		$_GET = array( 'convoca_confirm_email' => '1', 'member' => '55', 'token' => 'el-bueno' );

		try {
			$this->area->handle_email_confirmation();
			$this->fail( 'debía redirigir' );
		} catch ( \RuntimeException $r ) {
			$this->assertStringContainsString( 'convoca_confirm_ok=1', $r->getMessage() );
		}

		$meta = $GLOBALS['_wp_stores']['post_meta'][55];
		$this->assertSame( 'nuevo@example.test', $meta['_convoca_email'], 'el pendiente pasa a ser el correo' );
		$this->assertArrayNotHasKey( '_convoca_email_pendiente', $meta, 'el pendiente se limpia' );
		$this->assertArrayNotHasKey( '_convoca_email_token', $meta, 'el token se borra: no se puede reusar' );
		$this->assertArrayNotHasKey( '_convoca_email_token_exp', $meta );
		$this->assertSame( 1, $GLOBALS['convoca_avisos'], 'se avisa del cambio' );

		unset( $GLOBALS['convoca_avisos'] );
	}

	/**
	 * El teléfono: mismo contrato, con su token y su marca de verificado.
	 */
	public function test_telefono_con_token_bueno(): void {
		$this->ficha(
			56,
			array(
				'_convoca_telefono'          => '600000000',
				'_convoca_telefono_token'    => 'tel-bueno',
				'_convoca_telefono_token_exp' => (string) ( time() + 3600 ),
			)
		);
		$_GET = array( 'convoca_confirm_phone' => '1', 'member' => '56', 'token' => 'tel-bueno' );

		try {
			$this->area->handle_phone_confirmation();
		} catch ( \RuntimeException $r ) {
			$this->assertStringContainsString( 'convoca_phone_ok=1', $r->getMessage() );
		}

		$meta = $GLOBALS['_wp_stores']['post_meta'][56];
		$this->assertSame( '1', $meta['_convoca_telefono_verificado'] );
		$this->assertArrayNotHasKey( '_convoca_telefono_token', $meta );
	}

	/**
	 * El teléfono con token malo no se verifica.
	 */
	public function test_telefono_con_token_malo(): void {
		$this->ficha(
			56,
			array(
				'_convoca_telefono'          => '600000000',
				'_convoca_telefono_token'    => 'tel-bueno',
				'_convoca_telefono_token_exp' => (string) ( time() + 3600 ),
			)
		);
		$_GET = array( 'convoca_confirm_phone' => '1', 'member' => '56', 'token' => 'otro' );

		try {
			$this->area->handle_phone_confirmation();
		} catch ( \RuntimeException $r ) {
			$this->assertStringContainsString( 'convoca_confirm_error=token', $r->getMessage() );
		}

		$this->assertArrayNotHasKey( '_convoca_telefono_verificado', $GLOBALS['_wp_stores']['post_meta'][56] );
	}
}
