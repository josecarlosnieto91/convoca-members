<?php
/**
 * Aprobar y rechazar horas desde el listado.
 *
 * `process_action()` es el botón de «Aprobar» del listado, y hace tres cosas que se pueden romper en silencio:
 *
 *  1. **Autoridad**: un administrador puede todo; un monitor no aprueba sus horas ni las de otro monitor; cualquier
 *     otro rol con permiso de gestión tiene el mismo límite que un monitor. Una rama mal puesta no da error: el
 *     registro se queda `aprobada` y nadie se entera.
 *  2. **Una sola transición**: el cambio se hace con un `UPDATE ... WHERE meta_value != <nuevo>` y solo el primer
 *     hilo que cambia el estado dispara el hook. Si eso se rompe, `convoca_members_hora_aprobada` sale dos veces y el
 *     socio recibe **dos correos** por la misma aprobación.
 *  3. **Registros antiguos**: si la meta `_convoca_estado` no existe todavía, se crea. Si ya estaba en el estado
 *     objetivo, se redirige **sin repetir el hook**.
 *
 * El método termina en `exit;` en todos sus caminos, así que las pruebas leen lo que ha pasado por los efectos
 * (metas, ganchos, log) y capturan el redirect.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Admin_Horas;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/admin/class-admin-horas.php';
require_once dirname( __DIR__, 2 ) . '/includes/Hours_Manager.php';

/**
 * Aprobar y rechazar.
 */
final class AprobarHorasTest extends TestCase {

	/**
	 * Valores previos.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Pantalla.
	 *
	 * @var Admin_Horas
	 */
	private Admin_Horas $pantalla;

	/**
	 * Monta el escenario.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->previo = array(
			'posts'      => $GLOBALS['_wp_stores']['posts'] ?? array(),
			'meta'       => $GLOBALS['_wp_stores']['post_meta'] ?? array(),
			'usuarios'   => $GLOBALS['_wp_stores']['usuarios'] ?? array(),
			'nonces'     => $GLOBALS['_wp_stores']['nonces_validos'] ?? null,
			'caps'       => $GLOBALS['_test_caps'] ?? array(),
			'user'       => $GLOBALS['_test_user_id'] ?? null,
			'die'        => $GLOBALS['_wp_die_lanza'] ?? null,
			'acciones'   => $GLOBALS['_wp_actions'] ?? array(),
			'get'        => $_GET ?? array(),
			'gvmapa'     => $GLOBALS['_wp_stores']['get_var_mapa'] ?? null,
		);

		$GLOBALS['_wp_stores']['posts']      = array();
		$GLOBALS['_wp_stores']['post_meta']  = array();
		$GLOBALS['_wp_actions']              = array();
		unset( $GLOBALS['_wp_stores']['get_var_mapa'] );
		$GLOBALS['_wp_stores']['nonces_validos'] = array( 'nonce-bueno' );
		$GLOBALS['_test_caps']               = array( 'gestionar_miembros' => true );
		$GLOBALS['_wp_die_lanza']            = true;
		$_GET                                = array();
		$_REQUEST                            = array();

		$GLOBALS['_wp_stores']['usuarios'] = array(
			10 => (object) array(
				'ID'         => 10,
				'user_email' => 'admin@example.test',
				'roles'      => array( 'administrator' ),
			),
			20 => (object) array(
				'ID'         => 20,
				'user_email' => 'monitora@example.test',
				'roles'      => array( 'monitor_actividad' ),
			),
			30 => (object) array(
				'ID'         => 30,
				'user_email' => 'voluntaria@example.test',
				'roles'      => array( 'suscriptor' ),
			),
			40 => (object) array(
				'ID'         => 40,
				'user_email' => 'gestora@example.test',
				'roles'      => array( 'shop_manager' ),
			),
		);

		$this->pantalla = ( new \ReflectionClass( Admin_Horas::class ) )->newInstanceWithoutConstructor();
	}

	/**
	 * Devuelve el escenario.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']['posts']     = $this->previo['posts'];
		$GLOBALS['_wp_stores']['post_meta'] = $this->previo['meta'];
		$GLOBALS['_wp_stores']['usuarios']  = $this->previo['usuarios'];
		$GLOBALS['_test_caps']              = $this->previo['caps'];
		$GLOBALS['_wp_die_lanza']           = $this->previo['die'];
		$GLOBALS['_wp_actions']             = $this->previo['acciones'];
		$_GET                               = $this->previo['get'];
		$_REQUEST                           = array();

		if ( null === $this->previo['nonces'] ) {
			unset( $GLOBALS['_wp_stores']['nonces_validos'] );
		} else {
			$GLOBALS['_wp_stores']['nonces_validos'] = $this->previo['nonces'];
		}
		if ( null === $this->previo['user'] ) {
			unset( $GLOBALS['_test_user_id'] );
		} else {
			$GLOBALS['_test_user_id'] = $this->previo['user'];
		}
		if ( null === $this->previo['gvmapa'] ) {
			unset( $GLOBALS['_wp_stores']['get_var_mapa'] );
		} else {
			$GLOBALS['_wp_stores']['get_var_mapa'] = $this->previo['gvmapa'];
		}
	}

	/**
	 * Siembra un registro de horas y devuelve su ID.
	 *
	 * @param int    $usuario_id  Dueño.
	 * @param string $estado      Estado guardado, o '' para no crear la meta.
	 * @param int    $id          ID.
	 * @param int    $miembro_id  Ficha asociada.
	 * @return int
	 */
	private function registro( int $usuario_id, string $estado = 'pendiente', int $id = 700, int $miembro_id = 500 ): int {
		$GLOBALS['_wp_stores']['posts'][ $id ] = (object) array(
			'ID'          => $id,
			'post_type'   => 'registro_hora',
			'post_status' => 'publish',
			'post_title'  => 'Registro ' . $id,
			'post_author' => $usuario_id,
		);
		$meta = array(
			'_convoca_usuario_id' => $usuario_id,
			'_convoca_member_id'  => $miembro_id,
		);
		if ( '' !== $estado ) {
			$meta['_convoca_estado'] = $estado;
		}
		$GLOBALS['_wp_stores']['post_meta'][ $id ] = $meta;

		return $id;
	}

	/**
	 * Prepara la petición.
	 *
	 * @param int    $registro   ID.
	 * @param string $new_status Estado nuevo.
	 * @return void
	 */
	private function peticion( int $registro, string $new_status ): void {
		$_GET = array(
			'record_id'  => (string) $registro,
			'new_status' => $new_status,
			'_wpnonce'   => 'nonce-bueno',
		);
		$_REQUEST = $_GET;
	}

	/**
	 * Ejecuta y devuelve la URL del redirect.
	 *
	 * @return string
	 */
	private function ejecutar(): string {
		try {
			$this->pantalla->process_action();
		} catch ( \Throwable $e ) {
			if ( false !== strpos( $e->getMessage(), 'wp_safe_redirect' ) ) {
				return $e->getMessage();
			}
			throw $e;
		}
		$this->fail( 'process_action no devolvió por redirect' );

		return '';
	}

	/**
	 * Ganchos disparados.
	 *
	 * @return string[]
	 */
	private function hooks(): array {
		return array_column( (array) ( $GLOBALS['_wp_actions'] ?? array() ), 'hook' );
	}

	/**
	 * Sin permiso de gestión no se toca nada.
	 */
	public function test_sin_permiso_se_para(): void {
		$reg = $this->registro( 30 );
		$this->peticion( $reg, 'aprobada' );
		$GLOBALS['_test_caps'] = array( 'gestionar_miembros' => false );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/permisos/i' );

		$this->pantalla->process_action();
	}

	/**
	 * Sin nonce válido no se aprueba, aunque el permiso esté.
	 */
	public function test_sin_nonce_valido_se_para(): void {
		$reg = $this->registro( 30 );
		$this->peticion( $reg, 'aprobada' );
		$_GET['_wpnonce']    = 'nonce-malo';
		$_REQUEST['_wpnonce'] = 'nonce-malo';

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/nonce/i' );

		$this->pantalla->process_action();
	}

	/**
	 * La administradora aprueba y el hook sale **una vez**.
	 */
	public function test_la_administradora_aprueba_y_avisa(): void {
		$reg = $this->registro( 30 );
		$GLOBALS['_test_user_id'] = 10;
		$this->peticion( $reg, 'aprobada' );

		$this->ejecutar();

		$this->assertContains( 'convoca_members_hora_aprobada', $this->hooks(), 'se avisa de la aprobación' );
		$this->assertSame( 1, count( array_filter( $this->hooks(), static fn( $h ) => 'convoca_members_hora_aprobada' === $h ) ), 'una sola vez' );
		$this->assertSame( 'aprobada', $GLOBALS['_wp_stores']['post_meta'][ $reg ]['_convoca_estado'] ?? '' );
		$this->assertSame( '10', (string) ( $GLOBALS['_wp_stores']['post_meta'][ $reg ]['_convoca_aprobada_por'] ?? '' ), 'queda quién la aprobó' );
	}

	/**
	 * Rechazar tiene **su** hook, no el de aprobación.
	 */
	public function test_rechazar_tiene_su_propio_aviso(): void {
		$reg = $this->registro( 30 );
		$GLOBALS['_test_user_id'] = 10;
		$this->peticion( $reg, 'rechazada' );

		$this->ejecutar();

		$this->assertContains( 'convoca_members_hora_rechazada', $this->hooks() );
		$this->assertNotContains( 'convoca_members_hora_aprobada', $this->hooks(), 'no se avisa de una aprobación que no hubo' );
	}

	/**
	 * **Un monitor no aprueba sus propias horas.**
	 */
	public function test_un_monitor_no_aprueba_sus_horas(): void {
		$reg = $this->registro( 20 );
		$GLOBALS['_test_user_id'] = 20;
		$this->peticion( $reg, 'aprobada' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/tus propias horas/i' );

		$this->pantalla->process_action();
	}

	/**
	 * **Un monitor no aprueba las horas de otro monitor.**
	 */
	public function test_un_monitor_no_aprueba_a_otro_monitor(): void {
		$reg = $this->registro( 21 );
		$GLOBALS['_wp_stores']['usuarios'][21] = (object) array(
			'ID'         => 21,
			'user_email' => 'monitor2@example.test',
			'roles'      => array( 'monitor_actividad' ),
		);
		$GLOBALS['_test_user_id'] = 20;
		$this->peticion( $reg, 'aprobada' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/monitores/i' );

		$this->pantalla->process_action();
	}

	/**
	 * Un monitor **sí** aprueba a una voluntaria: la regla es sobre monitores, no sobre «nadie».
	 */
	public function test_un_monitor_si_aprueba_a_una_voluntaria(): void {
		$reg = $this->registro( 30 );
		$GLOBALS['_test_user_id'] = 20;
		$this->peticion( $reg, 'aprobada' );

		$this->ejecutar();

		$this->assertContains( 'convoca_members_hora_aprobada', $this->hooks() );
	}

	/**
	 * **Otro rol con permiso de gestión tiene el mismo límite**: no aprueba sus propias horas.
	 *
	 * Es la tercera rama del método, y la más fácil de dejar sin cubrir.
	 */
	public function test_otro_rol_gestor_no_aprueba_sus_horas(): void {
		$reg = $this->registro( 40 );
		$GLOBALS['_test_user_id'] = 40;
		$this->peticion( $reg, 'aprobada' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/tus propias horas/i' );

		$this->pantalla->process_action();
	}

	/**
	 * Y tampoco aprueba a un monitor.
	 */
	public function test_otro_rol_gestor_no_aprueba_a_un_monitor(): void {
		$reg = $this->registro( 20 );
		$GLOBALS['_test_user_id'] = 40;
		$this->peticion( $reg, 'aprobada' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/monitores/i' );

		$this->pantalla->process_action();
	}

	/**
	 * **Si el registro ya está en el estado objetivo, NO se repite el hook.**
	 *
	 * Es la guarda que evita el segundo correo: dos clics en «Aprobar», o dos pestañas.
	 */
	public function test_si_ya_estaba_aprobada_no_se_repite_el_aviso(): void {
		$reg = $this->registro( 30, 'aprobada' );
		$GLOBALS['_test_user_id'] = 10;
		$this->peticion( $reg, 'aprobada' );
		// El UPDATE no cambia nada porque ya estaba en `aprobada` (el doble del núcleo lo simula y devuelve 0), y la
		// fila de `wp_postmeta` **existe**, que es lo que contesta la base real a `SELECT meta_id ...`.
		$GLOBALS['_wp_stores']['get_var_mapa'] = array( 'SELECT meta_id FROM' => '99' );

		$this->ejecutar();

		$this->assertNotContains( 'convoca_members_hora_aprobada', $this->hooks(), 'no se avisa dos veces' );
	}

	/**
	 * **Un registro antiguo sin la meta** se aprueba igual: se crea la meta y se avisa una vez.
	 *
	 * Es el camino del `insert`, que solo corre con registros anteriores a que existiera `_convoca_estado`.
	 */
	public function test_un_registro_antiguo_sin_la_meta_se_aprueba(): void {
		$reg = $this->registro( 30, '' );
		$GLOBALS['_test_user_id'] = 10;
		$this->peticion( $reg, 'aprobada' );
		// Sin mapa: `get_var()` devuelve null, así que el `SELECT meta_id` no encuentra fila → se crea la meta.

		$this->ejecutar();

		$this->assertContains( 'convoca_members_hora_aprobada', $this->hooks(), 'se avisa de la aprobación' );
		$this->assertSame( '10', (string) ( $GLOBALS['_wp_stores']['post_meta'][ $reg ]['_convoca_aprobada_por'] ?? '' ) );
	}

	/**
	 * Un `new_status` que no es aprobar ni rechazar **no hace nada**: solo redirige.
	 *
	 * Importa porque el parámetro llega por la URL: si el método aceptara cualquier cosa, `?new_status=loquesea`
	 * dejaría el registro en un estado que no es ninguno.
	 */
	public function test_un_estado_inventado_no_cambia_nada(): void {
		$reg = $this->registro( 30 );
		$GLOBALS['_test_user_id'] = 10;
		$this->peticion( $reg, 'cualquier_cosa' );

		$this->ejecutar();

		$this->assertSame( 'pendiente', $GLOBALS['_wp_stores']['post_meta'][ $reg ]['_convoca_estado'] ?? '', 'sigue pendiente' );
		$this->assertSame( array(), $this->hooks(), 'sin avisos' );
	}

	/**
	 * Y un `record_id` que no existe tampoco.
	 */
	public function test_un_registro_que_no_existe_no_hace_nada(): void {
		$GLOBALS['_test_user_id'] = 10;
		$this->peticion( 0, 'aprobada' );

		$this->ejecutar();

		$this->assertSame( array(), $this->hooks(), 'sin avisos' );
	}
}
