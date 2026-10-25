<?php
/**
 * Quién puede aprobar horas y a quién.
 *
 * `handle_save_admin()` no es solo un formulario: es la puerta por la que unas horas pasan a contar para el
 * certificado de voluntariado. Tiene tres guardas encadenadas y las tres son de permisos:
 *
 *  1. `gestionar_miembros` — sin capacidad, no se entra.
 *  2. **Nadie aprueba sus propias horas** (si no eres administrador).
 *  3. **Solo un administrador aprueba las horas de un monitor** (si no, un monitor se avalaría a sí mismo por
 *     la puerta de atrás).
 *
 * Hasta ahora esas dos últimas eran **incomprobables**: `wp_get_current_user()` devolvía siempre un administrador
 * porque el doble no leía los roles del almacén (members#70). Con el arnés arreglado, se miden.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Admin_Horas;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/admin/class-admin-horas.php';

/**
 * Guardado de horas desde el panel.
 */
final class GuardadoDeHorasDelPanelTest extends TestCase {

	/**
	 * Valores previos.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Monta el escenario.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->previo = array(
			'capas'  => $GLOBALS['_test_caps'] ?? array(),
			'usuario' => $GLOBALS['_test_user_id'] ?? null,
			'post'   => $GLOBALS['_wp_stores']['post_meta'] ?? array(),
			'users'  => $GLOBALS['_wp_stores']['users'] ?? array(),
			'posts'  => $GLOBALS['_wp_stores']['posts'] ?? array(),
			'tusers' => $GLOBALS['_test_users'] ?? array(),
		);

		$GLOBALS['_test_caps']                  = array( 'gestionar_miembros' => true );
		$GLOBALS['_wp_stores']['post_meta']     = array();
		$GLOBALS['_wp_stores']['users']         = array();
		$_REQUEST['_wpnonce']                   = 'nonce';
		// El proyecto tiene que existir y ser de tipo `proyecto`: si no, la validación corta antes y la prueba
		// mediría el error del proyecto en vez de la guarda que quiere medir.
		$GLOBALS['_wp_stores']['posts']         = array(
			3 => (object) array( 'ID' => 3, 'post_type' => 'proyecto', 'post_title' => 'Proyecto' ),
		);
		$_POST                                  = array();
		// Como en producción: una guarda que falla CORTA.
		$GLOBALS['_wp_die_lanza']               = true;
	}

	/**
	 * Devuelve el escenario.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['_test_caps']              = $this->previo['capas'];
		$GLOBALS['_wp_stores']['post_meta'] = $this->previo['post'];
		$GLOBALS['_wp_stores']['users']     = $this->previo['users'];
		$GLOBALS['_wp_stores']['posts']     = $this->previo['posts'];
		$GLOBALS['_test_users']             = $this->previo['tusers'];
		if ( null === $this->previo['usuario'] ) {
			unset( $GLOBALS['_test_user_id'] );
		} else {
			$GLOBALS['_test_user_id'] = $this->previo['usuario'];
		}
		unset( $GLOBALS['_wp_die_lanza'] );
		$_POST    = array();
		$_REQUEST = array();
	}

	/**
	 * Deja el usuario actual con un rol concreto.
	 *
	 * @param int   $id    ID.
	 * @param array $roles Roles.
	 * @return void
	 */
	private function usuario( int $id, array $roles ): void {
		$GLOBALS['_test_user_id']              = $id;
		$GLOBALS['_wp_stores']['users'][ $id ] = array( 'roles' => $roles );

		// `get_userdata()` del arnés del núcleo lee `_test_users`, no `_wp_stores['users']`: hay que fijar los dos o
		// la guarda del monitor no ve su rol y no salta (parecía que la guarda no existía).
		$u               = new \stdClass();
		$u->ID           = $id;
		$u->roles        = $roles;
		$u->display_name = 'Usuario ' . $id;
		$u->user_email   = 'u' . $id . '@example.test';
		$GLOBALS['_test_users'][ $id ] = $u;
	}

	/**
	 * Rellena el POST del formulario.
	 *
	 * @param array $extra Campos extra.
	 * @return void
	 */
	private function formulario( array $extra = array() ): void {
		$_POST = array_merge(
			array(
				'id'           => 0,
				'miembro_id'   => 50,
				'fecha'        => '2026-10-20',
				'horas'        => '2.5',
				'proyecto_id'  => 3,
				'actividad_id' => 0,
				'tareas'       => 'Jornada',
				'descripcion'  => 'Salida de campo',
				'estado'       => 'pendiente',
				'nota_admin'   => '',
			),
			$extra
		);
	}

	/**
	 * Sin la capacidad, no se entra: corta con `wp_die`.
	 */
	public function test_sin_capacidad_no_se_entra(): void {
		$GLOBALS['_test_caps']['gestionar_miembros'] = false;
		$this->usuario( 10, array( 'administrator' ) );
		$this->formulario();

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/No tienes permisos/' );

		( new Admin_Horas() )->handle_save_admin();
	}

	/**
	 * Un no-admin NO puede aprobar sus propias horas.
	 *
	 * Es la guarda que impide que alguien se firme el certificado a sí mismo.
	 */
	public function test_nadie_aprueba_sus_propias_horas(): void {
		$this->usuario( 77, array( 'socio' ) );
		// El registro es suyo.
		$GLOBALS['_wp_stores']['post_meta'][900] = array( '_convoca_usuario_id' => '77' );
		$this->formulario( array( 'id' => 900, 'estado' => 'aprobada' ) );

		try {
			( new Admin_Horas() )->handle_save_admin();
			$this->fail( 'se aprobó sus propias horas' );
		} catch ( \RuntimeException $e ) {
			$this->assertStringContainsString( 'tus propias horas', $e->getMessage() );
		}

		$this->assertArrayNotHasKey(
			900,
			array_filter(
				$GLOBALS['_wp_stores']['post_meta'],
				static fn( $m ) => isset( $m['_convoca_estado'] )
			),
			'no se guardó ningún estado'
		);
	}

	/**
	 * Un no-admin NO puede aprobar las horas de un monitor.
	 *
	 * Si no, un monitor se avala a sí mismo por la puerta de atrás.
	 */
	public function test_solo_un_admin_aprueba_las_horas_de_un_monitor(): void {
		$this->usuario( 77, array( 'socio' ) );
		$this->usuario( 88, array( 'monitor_actividad' ) );
		$GLOBALS['_wp_stores']['users'][88]      = array( 'roles' => array( 'monitor_actividad' ) );
		$GLOBALS['_wp_stores']['post_meta'][900] = array( '_convoca_usuario_id' => '88' );
		$this->formulario( array( 'id' => 900, 'estado' => 'aprobada' ) );

		$GLOBALS['_test_user_id'] = 77;

		try {
			( new Admin_Horas() )->handle_save_admin();
			$this->fail( 'un socio aprobó las horas de un monitor' );
		} catch ( \RuntimeException $e ) {
			$this->assertStringContainsString( 'monitores', $e->getMessage() );
		}
	}

	/**
	 * Un estado que no es de aprobación NO pasa por la validación de autoridad.
	 *
	 * Un socio puede guardar sus horas en `pendiente`: eso no es aprobarlas.
	 */
	public function test_guardar_en_pendiente_no_exige_autoridad(): void {
		$this->usuario( 77, array( 'socio' ) );
		$GLOBALS['_wp_stores']['post_meta'][900] = array( '_convoca_usuario_id' => '77' );
		$this->formulario( array( 'id' => 900, 'estado' => 'pendiente' ) );

		// No debe cortar por autoridad; termina en la redirección.
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/wp_safe_redirect/' );

		( new Admin_Horas() )->handle_save_admin();
	}
}
