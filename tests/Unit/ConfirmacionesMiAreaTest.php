<?php
/**
 * Las confirmaciones del área del socio: correo y teléfono.
 *
 * Cuando un socio cambia su correo desde «Mi área», no se aplica en el acto: se guarda como **pendiente** y se manda un
 * enlace con un token. Solo al abrirlo se escribe el nuevo correo. Lo mismo con el teléfono, que se marca como
 * verificado.
 *
 * Es una guarda de datos personales: si el token no se comprueba bien, quien adivine el `member` puede cambiar el
 * correo de otra persona y quedarse con su cuenta (el correo es la vía de recuperación de contraseña).
 *
 * Los cuatro caminos terminan en `exit;`, así que cada prueba va en **proceso aparte** y se lee el redirect.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Convoca\Members\Mi_Area;

require_once dirname( __DIR__, 2 ) . '/public/class-mi-area.php';

/**
 * Confirmaciones.
 */
final class ConfirmacionesMiAreaTest extends TestCase {

	/**
	 * Valores previos.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Área del socio.
	 *
	 * @var Mi_Area
	 */
	private Mi_Area $area;

	/**
	 * Monta el escenario.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->previo = array(
			'posts'    => $GLOBALS['_wp_stores']['posts'] ?? array(),
			'meta'     => $GLOBALS['_wp_stores']['post_meta'] ?? array(),
			'actions'  => $GLOBALS['_wp_actions'] ?? array(),
			'get'      => $_GET ?? array(),
			'errlog'   => ini_get( 'error_log' ),
		);

		$GLOBALS['_wp_stores']['posts']     = array();
		$GLOBALS['_wp_stores']['post_meta'] = array();
		$GLOBALS['_wp_actions']             = array();
		$_GET                               = array();
		$_REQUEST                           = array();

		// El `Logger` con `WP_DEBUG` escribe con `error_log()` y PHPUnit, en proceso aparte, lo trata como salida
		// inesperada del test: el caso fallaría por el log y no por el código.
		$GLOBALS['_errlog_tmp'] = sys_get_temp_dir() . '/convoca-miarea-test.log';
		ini_set( 'error_log', $GLOBALS['_errlog_tmp'] );

		$this->area = ( new \ReflectionClass( Mi_Area::class ) )->newInstanceWithoutConstructor();
	}

	/**
	 * Devuelve el escenario.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']['posts']     = $this->previo['posts'];
		$GLOBALS['_wp_stores']['post_meta'] = $this->previo['meta'];
		$GLOBALS['_wp_actions']             = $this->previo['actions'];
		$_GET                               = $this->previo['get'];
		$_REQUEST                           = array();

		ini_set( 'error_log', $this->previo['errlog'] ?: '' );
		if ( is_file( (string) ( $GLOBALS['_errlog_tmp'] ?? '' ) ) ) {
			unlink( (string) $GLOBALS['_errlog_tmp'] );
		}
	}

	/**
	 * Siembra una ficha de socio y devuelve su ID.
	 *
	 * @param array $meta Metas.
	 * @param int   $id   ID.
	 * @return int
	 */
	private function ficha( array $meta, int $id = 500 ): int {
		$GLOBALS['_wp_stores']['posts'][ $id ] = (object) array(
			'ID'          => $id,
			'post_type'   => 'miembro',
			'post_status' => 'publish',
			'post_title'  => 'Socia ' . $id,
		);
		$GLOBALS['_wp_stores']['post_meta'][ $id ] = $meta;

		return $id;
	}

	/**
	 * Ejecuta un método y devuelve la URL del redirect.
	 *
	 * @param string $metodo Método.
	 * @return string|null Null si no hubo redirect (el método volvió sin hacer nada).
	 */
	private function ejecutar( string $metodo ): ?string {
		try {
			$this->area->$metodo();
		} catch ( \Throwable $e ) {
			if ( false !== strpos( $e->getMessage(), 'wp_safe_redirect' ) ) {
				return $e->getMessage();
			}
			throw $e;
		}

		return null;
	}

	/* ── Correo ──────────────────────────────────────────────────────── */

	/**
	 * Sin los parámetros del enlace no se hace **nada**: la pantalla se carga igual y no se toca ninguna ficha.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_sin_parametros_no_hace_nada(): void {
		$id = $this->ficha( array( '_convoca_email' => 'socia@example.test' ) );

		$this->assertNull( $this->ejecutar( 'handle_email_confirmation' ) );

		$this->assertSame( 'socia@example.test', $GLOBALS['_wp_stores']['post_meta'][ $id ]['_convoca_email'] ?? '' );
	}

	/**
	 * **Un token que no casa no confirma nada**, y no se distingue de «no existe token».
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_un_token_que_no_casa_no_confirma(): void {
		$id = $this->ficha(
			array(
				'_convoca_email'           => 'viejo@example.test',
				'_convoca_email_pendiente' => 'nuevo@example.test',
				'_convoca_email_token'     => 'el-bueno',
				'_convoca_email_token_exp' => (string) ( time() + 3600 ),
			)
		);
		$_GET = array(
			'convoca_confirm_email' => '1',
			'member'                => (string) $id,
			'token'                 => 'el-malo',
		);
		$_REQUEST = $_GET;

		$destino = (string) $this->ejecutar( 'handle_email_confirmation' );

		$this->assertStringContainsString( 'convoca_confirm_error=token', $destino );
		$this->assertSame( 'viejo@example.test', $GLOBALS['_wp_stores']['post_meta'][ $id ]['_convoca_email'] ?? '', 'el correo no se toca' );
		$this->assertSame( 'nuevo@example.test', $GLOBALS['_wp_stores']['post_meta'][ $id ]['_convoca_email_pendiente'] ?? '', 'ni se consume el pendiente' );
	}

	/**
	 * **Sin token guardado no se confirma**: un `member` a mano no basta.
	 *
	 * Si esto pasara, cualquiera podría cambiar el correo de otra persona sabiendo su número de ficha, y el correo es
	 * la vía de recuperación de la contraseña.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_sin_token_guardado_no_se_confirma(): void {
		$id = $this->ficha(
			array(
				'_convoca_email'           => 'viejo@example.test',
				'_convoca_email_pendiente' => 'atacante@example.test',
			)
		);
		$_GET = array(
			'convoca_confirm_email' => '1',
			'member'                => (string) $id,
			'token'                 => 'inventado',
		);
		$_REQUEST = $_GET;

		$destino = (string) $this->ejecutar( 'handle_email_confirmation' );

		$this->assertStringContainsString( 'convoca_confirm_error=token', $destino );
		$this->assertSame( 'viejo@example.test', $GLOBALS['_wp_stores']['post_meta'][ $id ]['_convoca_email'] ?? '' );
	}

	/**
	 * **Un token caducado no confirma**, aunque sea el correcto.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_un_token_caducado_no_confirma(): void {
		$id = $this->ficha(
			array(
				'_convoca_email'           => 'viejo@example.test',
				'_convoca_email_pendiente' => 'nuevo@example.test',
				'_convoca_email_token'     => 'el-bueno',
				'_convoca_email_token_exp' => (string) ( time() - 60 ),
			)
		);
		$_GET = array(
			'convoca_confirm_email' => '1',
			'member'                => (string) $id,
			'token'                 => 'el-bueno',
		);
		$_REQUEST = $_GET;

		$destino = (string) $this->ejecutar( 'handle_email_confirmation' );

		$this->assertStringContainsString( 'convoca_confirm_error=expirado', $destino );
		$this->assertSame( 'viejo@example.test', $GLOBALS['_wp_stores']['post_meta'][ $id ]['_convoca_email'] ?? '' );
	}

	/**
	 * Con token válido y vigente: se aplica el correo pendiente, se consumen los tres metadatos y se avisa.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_con_token_valido_se_aplica_el_correo(): void {
		$id = $this->ficha(
			array(
				'_convoca_email'           => 'viejo@example.test',
				'_convoca_email_pendiente' => 'nuevo@example.test',
				'_convoca_email_token'     => 'el-bueno',
				'_convoca_email_token_exp' => (string) ( time() + 3600 ),
			)
		);
		$_GET = array(
			'convoca_confirm_email' => '1',
			'member'                => (string) $id,
			'token'                 => 'el-bueno',
		);
		$_REQUEST = $_GET;

		$destino = (string) $this->ejecutar( 'handle_email_confirmation' );

		$this->assertStringContainsString( 'convoca_confirm_ok=1', $destino );
		$meta = $GLOBALS['_wp_stores']['post_meta'][ $id ];
		$this->assertSame( 'nuevo@example.test', $meta['_convoca_email'] ?? '' );
		$this->assertArrayNotHasKey( '_convoca_email_pendiente', $meta, 'el pendiente se consume' );
		$this->assertArrayNotHasKey( '_convoca_email_token', $meta, 'y el token no se puede reusar' );
		$this->assertArrayNotHasKey( '_convoca_email_token_exp', $meta );
		$this->assertContains( 'convoca_members_email_cambiado', array_column( (array) $GLOBALS['_wp_actions'], 'hook' ) );
	}

	/**
	 * **Un token válido sin cambio pendiente NO puede dejar el correo vacío.**
	 *
	 * `update_post_meta( $member_id, '_convoca_email', $pendiente )` se ejecuta con lo que haya en el pendiente. Si el
	 * pendiente está vacío —porque otra cosa lo borró y el token quedó, o por un transitorio a medias— el socio se
	 * queda **sin correo**: no puede entrar, no puede recuperar la contraseña y no recibe nada.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_un_token_valido_sin_pendiente_no_vacia_el_correo(): void {
		$id = $this->ficha(
			array(
				'_convoca_email'           => 'socia@example.test',
				'_convoca_email_token'     => 'el-bueno',
				'_convoca_email_token_exp' => (string) ( time() + 3600 ),
			)
		);
		$_GET = array(
			'convoca_confirm_email' => '1',
			'member'                => (string) $id,
			'token'                 => 'el-bueno',
		);
		$_REQUEST = $_GET;

		$this->ejecutar( 'handle_email_confirmation' );

		$this->assertSame(
			'socia@example.test',
			$GLOBALS['_wp_stores']['post_meta'][ $id ]['_convoca_email'] ?? '',
			'sin cambio pendiente, el correo que consta se queda como está'
		);
	}

	/* ── Teléfono ────────────────────────────────────────────────────── */

	/**
	 * Sin parámetros no se toca nada.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_telefono_sin_parametros_no_hace_nada(): void {
		$id = $this->ficha( array( '_convoca_telefono' => '600000000' ) );

		$this->assertNull( $this->ejecutar( 'handle_phone_confirmation' ) );

		$this->assertArrayNotHasKey( '_convoca_telefono_verificado', $GLOBALS['_wp_stores']['post_meta'][ $id ] );
	}

	/**
	 * Un token que no casa no verifica el teléfono.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_telefono_con_token_malo_no_verifica(): void {
		$id = $this->ficha(
			array(
				'_convoca_telefono'           => '600000000',
				'_convoca_telefono_token'     => 'el-bueno',
				'_convoca_telefono_token_exp' => (string) ( time() + 3600 ),
			)
		);
		$_GET = array(
			'convoca_confirm_phone' => '1',
			'member'                => (string) $id,
			'token'                 => 'el-malo',
		);
		$_REQUEST = $_GET;

		$destino = (string) $this->ejecutar( 'handle_phone_confirmation' );

		$this->assertStringContainsString( 'convoca_confirm_error=token', $destino );
		$this->assertArrayNotHasKey( '_convoca_telefono_verificado', $GLOBALS['_wp_stores']['post_meta'][ $id ] );
	}

	/**
	 * Un token caducado no verifica.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_telefono_con_token_caducado_no_verifica(): void {
		$id = $this->ficha(
			array(
				'_convoca_telefono'           => '600000000',
				'_convoca_telefono_token'     => 'el-bueno',
				'_convoca_telefono_token_exp' => (string) ( time() - 60 ),
			)
		);
		$_GET = array(
			'convoca_confirm_phone' => '1',
			'member'                => (string) $id,
			'token'                 => 'el-bueno',
		);
		$_REQUEST = $_GET;

		$destino = (string) $this->ejecutar( 'handle_phone_confirmation' );

		$this->assertStringContainsString( 'convoca_confirm_error=expirado', $destino );
		$this->assertArrayNotHasKey( '_convoca_telefono_verificado', $GLOBALS['_wp_stores']['post_meta'][ $id ] );
	}

	/**
	 * Con token válido: se marca verificado, se consume el token y se avisa.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_telefono_con_token_valido_verifica(): void {
		$id = $this->ficha(
			array(
				'_convoca_telefono'           => '600000000',
				'_convoca_telefono_token'     => 'el-bueno',
				'_convoca_telefono_token_exp' => (string) ( time() + 3600 ),
			)
		);
		$_GET = array(
			'convoca_confirm_phone' => '1',
			'member'                => (string) $id,
			'token'                 => 'el-bueno',
		);
		$_REQUEST = $_GET;

		$destino = (string) $this->ejecutar( 'handle_phone_confirmation' );

		$this->assertStringContainsString( 'convoca_phone_ok=1', $destino );
		$meta = $GLOBALS['_wp_stores']['post_meta'][ $id ];
		$this->assertSame( '1', $meta['_convoca_telefono_verificado'] ?? '' );
		$this->assertArrayNotHasKey( '_convoca_telefono_token', $meta, 'el token se consume' );
		$this->assertContains( 'convoca_members_phone_verificado', array_column( (array) $GLOBALS['_wp_actions'], 'hook' ) );
	}
}
