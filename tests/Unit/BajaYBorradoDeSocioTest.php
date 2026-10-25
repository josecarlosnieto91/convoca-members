<?php
/**
 * Dar de baja y borrar una ficha de socio.
 *
 * Los dos son destructivos y los dos viven detrás de **nonce y permiso distintos**, que es justo lo que hay que
 * fijar:
 *
 *  - dar de baja exige `edit_post` sobre la ficha, y borrar exige `delete_post`: **poder editar no es poder
 *    borrar**;
 *  - el nonce de la baja (`convoca_baja_member_<id>`) no puede servir para borrar, ni al revés. Con un cruce,
 *    un enlace de baja acabaría destruyendo la ficha.
 *
 * Y la baja **no borra**: cambia el estado y deja la ficha. El borrado sí es definitivo (`force = true`), que es
 * lo que arregló `members#27`: antes llamaba a `wp_trash_post()` sobre un tipo que no admite papelera y el enlace
 * «Eliminar» no hacía nada.
 *
 * Los dos terminan en `exit;`, pero el arnés hace que `wp_safe_redirect()` **lance**, así que el `exit` no se
 * alcanza y no hace falta aislar el proceso. Importa: una prueba en proceso aparte **no aporta su cobertura al
 * padre**, así que aislarlas dejaría la cifra igual y el trabajo sin reflejo en el instrumento.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Admin_Member_Editor;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/admin/class-admin-member-editor.php';
require_once dirname( __DIR__, 2 ) . '/includes/Estados.php';

/**
 * Baja y borrado.
 */
final class BajaYBorradoDeSocioTest extends TestCase {

	/**
	 * Valores previos.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Editor.
	 *
	 * @var Admin_Member_Editor
	 */
	private Admin_Member_Editor $editor;

	/**
	 * Monta el escenario.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->previo = array(
			'posts'    => $GLOBALS['_wp_stores']['posts'] ?? array(),
			'meta'     => $GLOBALS['_wp_stores']['post_meta'] ?? array(),
			'borrados' => $GLOBALS['_wp_stores']['deleted_posts'] ?? null,
			'fuerza'   => $GLOBALS['_wp_stores']['deleted_posts_force'] ?? null,
			'nonces'   => $GLOBALS['_wp_stores']['nonces_validos'] ?? null,
			'xaccion'  => $GLOBALS['_wp_stores']['nonces_por_accion'] ?? null,
			'caps'     => $GLOBALS['_test_caps'] ?? array(),
			'postcaps' => $GLOBALS['_test_post_caps'] ?? null,
			'die'      => $GLOBALS['_wp_die_lanza'] ?? null,
			'get'      => $_GET ?? array(),
			'errlog'   => ini_get( 'error_log' ),
		);

		$GLOBALS['_wp_stores']['posts']              = array();
		$GLOBALS['_wp_stores']['post_meta']          = array();
		$GLOBALS['_wp_stores']['deleted_posts']      = array();
		$GLOBALS['_wp_stores']['deleted_posts_force'] = array();
		$GLOBALS['_test_caps']                       = array( 'gestionar_miembros' => true );
		$GLOBALS['_wp_die_lanza']                    = true;
		unset( $GLOBALS['_wp_stores']['nonces_validos'], $GLOBALS['_test_post_caps'] );

		// Nonces **por acción**: con la lista plana el doble acepta cualquier nonce para cualquier acción y un
		// cruce entre baja y borrado no lo notaría nadie.
		$GLOBALS['_wp_stores']['nonces_por_accion'] = array(
			'convoca_baja_member_500'   => 'nonce-baja',
			'convoca_delete_member_500' => 'nonce-borrar',
		);

		$GLOBALS['_errlog_tmp'] = sys_get_temp_dir() . '/convoca-baja-test.log';
		ini_set( 'error_log', $GLOBALS['_errlog_tmp'] );

		$this->editor = ( new \ReflectionClass( Admin_Member_Editor::class ) )->newInstanceWithoutConstructor();
	}

	/**
	 * Devuelve el escenario.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']['posts']               = $this->previo['posts'];
		$GLOBALS['_wp_stores']['post_meta']           = $this->previo['meta'];
		$GLOBALS['_test_caps']                        = $this->previo['caps'];
		$GLOBALS['_wp_die_lanza']                     = $this->previo['die'];
		$_GET                                         = $this->previo['get'];

		foreach ( array( 'deleted_posts' => 'borrados', 'deleted_posts_force' => 'fuerza', 'nonces_validos' => 'nonces', 'nonces_por_accion' => 'xaccion' ) as $clave => $origen ) {
			if ( null === $this->previo[ $origen ] ) {
				unset( $GLOBALS['_wp_stores'][ $clave ] );
			} else {
				$GLOBALS['_wp_stores'][ $clave ] = $this->previo[ $origen ];
			}
		}
		if ( null === $this->previo['postcaps'] ) {
			unset( $GLOBALS['_test_post_caps'] );
		} else {
			$GLOBALS['_test_post_caps'] = $this->previo['postcaps'];
		}

		ini_set( 'error_log', $this->previo['errlog'] ?: '' );
		if ( is_file( (string) ( $GLOBALS['_errlog_tmp'] ?? '' ) ) ) {
			unlink( (string) $GLOBALS['_errlog_tmp'] );
		}
	}

	/**
	 * Siembra una ficha.
	 *
	 * @param array $meta Metas.
	 * @param int   $id   ID.
	 * @return int
	 */
	private function ficha( array $meta, int $id = 500 ): int {
		$GLOBALS['_wp_stores']['posts'][ $id ]      = (object) array(
			'ID'          => $id,
			'post_type'   => 'miembro',
			'post_status' => 'publish',
			'post_title'  => 'Socia ' . $id,
		);
		$GLOBALS['_wp_stores']['post_meta'][ $id ] = $meta;

		return $id;
	}

	/**
	 * Ejecuta un método del editor y devuelve el corte.
	 *
	 * @param string $metodo `handle_baja` o `handle_delete`.
	 * @param array  $get    Campos de `$_GET`.
	 * @return string
	 */
	private function ejecutar( string $metodo, array $get ): string {
		$_GET     = $get;
		$_REQUEST = $get;

		try {
			$this->editor->$metodo();
		} catch ( \Throwable $e ) {
			foreach ( array( 'wp_safe_redirect', 'wp_die' ) as $corte ) {
				if ( false !== strpos( $e->getMessage(), $corte ) ) {
					return $e->getMessage();
				}
			}
			throw $e;
		}

		$this->fail( 'el método terminó sin cortar el proceso' );
		return '';
	}

	/* ── Baja ────────────────────────────────────────────────────────── */

	/**
	 * Dar de baja **no borra**: la ficha sigue y cambia de estado.
	 */
	public function test_la_baja_cambia_el_estado_y_no_borra(): void {
		$id = $this->ficha( array( '_convoca_estado_miembro' => 'activo' ) );

		$corte = $this->ejecutar(
			'handle_baja',
			array(
				'member_id' => (string) $id,
				'_wpnonce'  => 'nonce-baja',
			)
		);

		$this->assertSame( 'baja', (string) ( $GLOBALS['_wp_stores']['post_meta'][ $id ]['_convoca_estado_miembro'] ?? '' ) );
		$this->assertArrayNotHasKey( $id, array_flip( array_map( 'intval', $GLOBALS['_wp_stores']['deleted_posts'] ) ), 'la baja no borra la ficha' );
		$this->assertStringContainsString( 'msg=baja', $corte );
	}

	/**
	 * **El nonce del borrado no sirve para dar de baja.**
	 *
	 * Con un cruce, el enlace de «Eliminar» de la ficha acabaría ejecutando una baja y al revés; aquí lo que
	 * está en juego son datos personales.
	 */
	public function test_el_nonce_de_borrar_no_vale_para_dar_de_baja(): void {
		$id = $this->ficha( array( '_convoca_estado_miembro' => 'activo' ) );

		$corte = $this->ejecutar(
			'handle_baja',
			array(
				'member_id' => (string) $id,
				'_wpnonce'  => 'nonce-borrar',
			)
		);

		$this->assertStringContainsString( 'Acceso denegado', $corte );
		$this->assertSame( 'activo', (string) ( $GLOBALS['_wp_stores']['post_meta'][ $id ]['_convoca_estado_miembro'] ?? '' ) );
	}

	/**
	 * Sin `edit_post` sobre la ficha no hay baja, aunque se gestionen socios.
	 */
	public function test_la_baja_exige_permiso_sobre_la_ficha(): void {
		$id                        = $this->ficha( array( '_convoca_estado_miembro' => 'activo' ) );
		$GLOBALS['_test_post_caps'] = array( $id => array( 'edit_post' => false ) );

		$corte = $this->ejecutar(
			'handle_baja',
			array(
				'member_id' => (string) $id,
				'_wpnonce'  => 'nonce-baja',
			)
		);

		$this->assertStringContainsString( 'No tienes permisos', $corte );
		$this->assertSame( 'activo', (string) ( $GLOBALS['_wp_stores']['post_meta'][ $id ]['_convoca_estado_miembro'] ?? '' ) );
	}

	/**
	 * **Una baja que la máquina de estados rechaza se avisa como error**, no como baja hecha.
	 *
	 * Con un estado de partida desconocido la tabla de transiciones no permite `baja`; la pantalla tiene que decir
	 * que falló. Si el mensaje dijera «baja», el socio seguiría activo y nadie lo sabría.
	 */
	public function test_una_baja_rechazada_se_avisa_como_error(): void {
		$id = $this->ficha( array( '_convoca_estado_miembro' => 'estado-que-no-existe' ) );

		$corte = $this->ejecutar(
			'handle_baja',
			array(
				'member_id' => (string) $id,
				'_wpnonce'  => 'nonce-baja',
			)
		);

		$this->assertStringContainsString( 'msg=error', $corte );
		$this->assertStringNotContainsString( 'msg=baja', $corte );
		$this->assertSame(
			'estado-que-no-existe',
			(string) ( $GLOBALS['_wp_stores']['post_meta'][ $id ]['_convoca_estado_miembro'] ?? '' ),
			'el estado no se toca si la transición no vale'
		);
	}

	/**
	 * Sin `member_id` no se hace nada.
	 */
	public function test_la_baja_sin_identificador_se_para(): void {
		$corte = $this->ejecutar( 'handle_baja', array( '_wpnonce' => 'nonce-baja' ) );

		$this->assertStringContainsString( 'Acceso denegado', $corte );
	}

	/* ── Borrado ─────────────────────────────────────────────────────── */

	/**
	 * **Borrar exige `delete_post`: poder editar no es poder borrar.**
	 *
	 * Es la separación que impide que quien corrige una ficha se lleve por delante su historial, sus horas y sus
	 * inscripciones.
	 */
	public function test_borrar_exige_delete_post_y_no_basta_edit_post(): void {
		$id = $this->ficha( array() );
		$GLOBALS['_test_post_caps'] = array(
			$id => array(
				'edit_post'   => true,
				'delete_post' => false,
			),
		);

		$corte = $this->ejecutar(
			'handle_delete',
			array(
				'member_id' => (string) $id,
				'_wpnonce'  => 'nonce-borrar',
			)
		);

		$this->assertStringContainsString( 'No tienes permisos', $corte );
		$this->assertSame( array(), $GLOBALS['_wp_stores']['deleted_posts'], 'no se borró nada' );
	}

	/**
	 * **El nonce de la baja no sirve para borrar.**
	 */
	public function test_el_nonce_de_baja_no_vale_para_borrar(): void {
		$id = $this->ficha( array() );

		$corte = $this->ejecutar(
			'handle_delete',
			array(
				'member_id' => (string) $id,
				'_wpnonce'  => 'nonce-baja',
			)
		);

		$this->assertStringContainsString( 'Acceso denegado', $corte );
		$this->assertSame( array(), $GLOBALS['_wp_stores']['deleted_posts'] );
	}

	/**
	 * **El borrado es definitivo**: `wp_delete_post( $id, true )`, no un envío a la papelera.
	 *
	 * El tipo `miembro` no admite papelera, así que un `force = false` no borraría nada y el enlace «Eliminar»
	 * mentiría — que es exactamente el defecto `members#27`.
	 */
	public function test_el_borrado_es_definitivo_y_no_una_papelera(): void {
		$id = $this->ficha( array( '_convoca_estado_miembro' => 'activo' ) );

		$corte = $this->ejecutar(
			'handle_delete',
			array(
				'member_id' => (string) $id,
				'_wpnonce'  => 'nonce-borrar',
			)
		);

		$this->assertSame( array( $id ), array_map( 'intval', $GLOBALS['_wp_stores']['deleted_posts'] ), 'se borró la ficha' );
		$this->assertSame( array( true ), $GLOBALS['_wp_stores']['deleted_posts_force'], 'borrado definitivo, no papelera' );
		$this->assertStringContainsString( 'msg=deleted_forever', $corte );
	}

	/**
	 * Sin `member_id` no se borra nada — y esto importa más que en la baja: `wp_delete_post( 0, true )` no hace
	 * nada hoy, pero un `(int)` mal puesto no puede convertirse en un borrado de la ficha equivocada.
	 */
	public function test_el_borrado_sin_identificador_se_para(): void {
		$corte = $this->ejecutar( 'handle_delete', array( '_wpnonce' => 'nonce-borrar' ) );

		$this->assertStringContainsString( 'Acceso denegado', $corte );
		$this->assertSame( array(), $GLOBALS['_wp_stores']['deleted_posts'] );
	}
}
