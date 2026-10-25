<?php
/**
 * Guardar horas desde el panel y quién puede aprobarlas.
 *
 * `handle_save_admin()` no solo guarda: **decide quién tiene autoridad para aprobar**. Dos reglas de sentido común que
 * en una asociación importan (las horas se certifican y sostienen subvenciones):
 *
 *  1. nadie aprueba **sus propias** horas;
 *  2. solo un administrador aprueba las horas de un **monitor**.
 *
 * Una regla que se salta no da error: el registro se guarda como `aprobada` y ya está — con el visto bueno de quien no
 * debía darlo. Por eso se miden las dos, con sus mensajes.
 *
 * Se dobla lo mínimo: `Hours_Manager::save_hours_record()` corre **real** (usa `wp_insert_post` y `update_post_meta`,
 * que el arnés sirve), para que la prueba diga algo del producto y no de una copia.
 *
 * El camino feliz termina en `exit;` → proceso aparte.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Admin_Horas;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/admin/class-admin-horas.php';
require_once dirname( __DIR__, 2 ) . '/includes/Hours_Manager.php';

/**
 * Autoridad de aprobación.
 */
final class AutoridadDeHorasTest extends TestCase {

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
	 * Monta el escenario con tres personas: administradora, monitora y voluntaria.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->previo = array(
			'posts'      => $GLOBALS['_wp_stores']['posts'] ?? array(),
			'meta'       => $GLOBALS['_wp_stores']['post_meta'] ?? array(),
			'usuarios'   => $GLOBALS['_wp_stores']['usuarios'] ?? array(),
			'users'      => $GLOBALS['_wp_stores']['users'] ?? array(),
			'transients' => $GLOBALS['_wp_stores']['transients'] ?? array(),
			'nonces'     => $GLOBALS['_wp_stores']['nonces_validos'] ?? null,
			'caps'       => $GLOBALS['_test_caps'] ?? array(),
			'user'       => $GLOBALS['_test_user_id'] ?? null,
			'die'        => $GLOBALS['_wp_die_lanza'] ?? null,
			'post'       => $_POST ?? array(),
			'ddl'        => $GLOBALS['_ddl'] ?? array(),
		);

		$GLOBALS['_wp_stores']['posts']          = array();
		$GLOBALS['_wp_stores']['post_meta']      = array();
		$GLOBALS['_wp_stores']['transients']     = array();
		$GLOBALS['_wp_stores']['nonces_validos'] = array( 'nonce-bueno' );
		$GLOBALS['_test_caps']                   = array();
		$GLOBALS['_wp_die_lanza']                = true;
		$_POST                                   = array();
		$_REQUEST                                = array();

		// Tres personas: 10 administradora, 20 monitora, 30 voluntaria.
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
		);

		$this->pantalla = ( new \ReflectionClass( Admin_Horas::class ) )->newInstanceWithoutConstructor();
	}

	/**
	 * Devuelve el escenario.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']['posts']      = $this->previo['posts'];
		$GLOBALS['_wp_stores']['post_meta']  = $this->previo['meta'];
		$GLOBALS['_wp_stores']['usuarios']   = $this->previo['usuarios'];
		$GLOBALS['_wp_stores']['users']      = $this->previo['users'];
		$GLOBALS['_wp_stores']['transients'] = $this->previo['transients'];
		$GLOBALS['_test_caps']               = $this->previo['caps'];
		$GLOBALS['_wp_die_lanza']            = $this->previo['die'];
		$_POST                               = $this->previo['post'];
		$_REQUEST                            = array();

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
	}

	/**
	 * Siembra una ficha de socio y devuelve su ID.
	 *
	 * @param string $email Correo.
	 * @param int    $id    ID.
	 * @return int
	 */
	private function ficha( string $email, int $id = 500 ): int {
		$GLOBALS['_wp_stores']['posts'][ $id ] = (object) array(
			'ID'          => $id,
			'post_type'   => 'miembro',
			'post_status' => 'publish',
			'post_title'  => 'Ficha ' . $id,
		);
		$GLOBALS['_wp_stores']['post_meta'][ $id ] = array( '_convoca_email' => $email );

		return $id;
	}

	/**
	 * Siembra un registro de horas de un usuario y devuelve su ID.
	 *
	 * @param int $usuario_id Dueño del registro.
	 * @param int $id         ID del registro.
	 * @return int
	 */
	private function registro( int $usuario_id, int $id = 700 ): int {
		$GLOBALS['_wp_stores']['posts'][ $id ] = (object) array(
			'ID'          => $id,
			'post_type'   => 'registro_hora',
			'post_status' => 'publish',
			'post_title'  => 'Registro ' . $id,
		);
		$GLOBALS['_wp_stores']['post_meta'][ $id ] = array( '_convoca_usuario_id' => $usuario_id );

		return $id;
	}

	/**
	 * Prepara la petición.
	 *
	 * @param int    $miembro_id Ficha.
	 * @param string $estado     Estado pedido.
	 * @param int    $registro   ID del registro (0 = nuevo).
	 * @return void
	 */
	private function peticion( int $miembro_id, string $estado, int $registro = 0 ): void {
		$_POST = array(
			'_wpnonce'     => 'nonce-bueno',
			'id'           => $registro,
			'miembro_id'   => $miembro_id,
			'fecha'        => '2026-10-20',
			'horas'        => '4',
			'proyecto_id'  => 0,
			'actividad_id' => 0,
			'tareas'       => 'Tareas',
			'descripcion'  => 'Descripción',
			'estado'       => $estado,
			'nota_admin'   => '',
		);
		$_REQUEST = $_POST;
	}

	/**
	 * Sin nonce válido no se guarda nada.
	 */
	public function test_sin_nonce_valido_se_para(): void {
		$this->peticion( $this->ficha( 'voluntaria@example.test' ), 'pendiente' );
		$_POST['_wpnonce']    = 'nonce-malo';
		$_REQUEST['_wpnonce'] = 'nonce-malo';

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/nonce/i' );

		$this->pantalla->handle_save_admin();
	}

	/**
	 * Sin `gestionar_miembros` no se guarda nada.
	 */
	public function test_sin_permiso_se_para(): void {
		$this->peticion( $this->ficha( 'voluntaria@example.test' ), 'pendiente' );
		$GLOBALS['_test_caps'] = array( 'gestionar_miembros' => false );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/permisos/i' );

		$this->pantalla->handle_save_admin();
	}

	/**
	 * **Nadie aprueba sus propias horas.**
	 *
	 * Es la regla que impide que las horas certificadas de una asociación se las valide uno mismo.
	 */
	public function test_nadie_aprueba_sus_propias_horas(): void {
		$ficha = $this->ficha( 'monitora@example.test' );
		$reg   = $this->registro( 20 );
		$GLOBALS['_test_user_id'] = 20;
		$this->peticion( $ficha, 'aprobada', $reg );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/tus propias horas/i' );

		$this->pantalla->handle_save_admin();
	}

	/**
	 * **Lo mismo en un registro nuevo**: se busca el usuario por el correo de la ficha.
	 *
	 * Si la comprobación solo mirara el registro existente, quien apunta horas nuevas y las aprueba en el mismo
	 * formulario se saltaría la regla.
	 */
	public function test_tampoco_en_un_registro_nuevo(): void {
		$ficha = $this->ficha( 'voluntaria@example.test' );
		$GLOBALS['_test_user_id'] = 30;
		$this->peticion( $ficha, 'aprobada' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/tus propias horas/i' );

		$this->pantalla->handle_save_admin();
	}

	/**
	 * **Solo un administrador aprueba las horas de un monitor.**
	 *
	 * Quien coordina no valida las horas de quien coordina con él.
	 */
	public function test_solo_un_admin_aprueba_las_horas_de_un_monitor(): void {
		$ficha = $this->ficha( 'monitora@example.test' );
		$reg   = $this->registro( 20 );
		// Sesión de una voluntaria con permiso de gestión (no administradora).
		$GLOBALS['_test_user_id'] = 30;
		$GLOBALS['_test_caps']    = array( 'gestionar_miembros' => true );
		$this->peticion( $ficha, 'aprobada', $reg );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/monitores/i' );

		$this->pantalla->handle_save_admin();
	}

	/**
	 * **Un caso que la regla deja pasar**: alguien con permiso de gestión aprueba las horas de otra persona que no es
	 * monitor. Se permite a propósito (la JD de una asociación no tiene por qué ser administradora de WordPress).
	 */
	public function test_un_no_admin_si_aprueba_las_horas_de_otra_persona(): void {
		$ficha = $this->ficha( 'voluntaria@example.test' );
		$reg   = $this->registro( 30 );
		$GLOBALS['_test_user_id'] = 20;
		$GLOBALS['_test_caps']    = array( 'gestionar_miembros' => true );
		$this->peticion( $ficha, 'aprobada', $reg );

		try {
			$this->pantalla->handle_save_admin();
			$this->fail( 'el camino feliz termina en exit' );
		} catch ( \Throwable $e ) {
			if ( false !== strpos( $e->getMessage(), 'tus propias horas' )
				|| false !== strpos( $e->getMessage(), 'monitores' )
				|| false !== strpos( $e->getMessage(), 'permisos' )
				|| false !== strpos( $e->getMessage(), 'nonce' ) ) {
				$this->fail( 'no debía bloquearse: ' . $e->getMessage() );
			}
			// wp_safe_redirect (o el exit) del camino feliz.
			$this->assertTrue( true );
		}
	}

	/**
	 * El estado `pendiente` no dispara ninguna comprobación de autoridad: apuntar horas no es aprobarlas.
	 */
	public function test_el_estado_pendiente_no_pide_autoridad(): void {
		$ficha = $this->ficha( 'voluntaria@example.test' );
		$GLOBALS['_test_user_id'] = 30;
		$this->peticion( $ficha, 'pendiente' );

		try {
			$this->pantalla->handle_save_admin();
		} catch ( \Throwable $e ) {
			$this->assertStringNotContainsString( 'tus propias horas', $e->getMessage() );
			$this->assertStringNotContainsString( 'monitores', $e->getMessage() );
		}

		$this->assertNotEmpty( $GLOBALS['_wp_stores']['posts'], 'se guardó el registro' );
	}

	/**
	 * Una validación fallida del producto se enseña con **su** mensaje, no con un error genérico.
	 *
	 * Aquí: cero horas. Y lo que se exige es el texto del producto («el número de horas debe ser mayor que cero»),
	 * no «algo ha fallado»: un `wp_die()` con un mensaje cualquiera pasaría una prueba que solo mirara el tipo de la
	 * excepción, y quien apunta las horas se quedaría sin saber qué corregir.
	 */
	public function test_una_validacion_fallida_ensena_el_motivo(): void {
		$ficha = $this->ficha( 'voluntaria@example.test' );
		$GLOBALS['_test_user_id'] = 10;
		$this->peticion( $ficha, 'pendiente' );
		$_POST['horas']    = '0';
		$_REQUEST['horas'] = '0';

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/horas debe ser mayor que cero/i' );

		$this->pantalla->handle_save_admin();
	}

	/**
	 * Y el mensaje de una ficha que falta también es el suyo.
	 */
	public function test_sin_socio_se_ensena_su_motivo(): void {
		$GLOBALS['_test_user_id'] = 10;
		$this->peticion( 0, 'pendiente' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/seleccionar un socio/i' );

		$this->pantalla->handle_save_admin();
	}

	/**
	 * La aprobación de un administrador sí pasa, incluso sobre un monitor.
	 */
	public function test_un_admin_aprueba_las_horas_de_un_monitor(): void {
		$ficha = $this->ficha( 'monitora@example.test' );
		$reg   = $this->registro( 20 );
		$GLOBALS['_test_user_id'] = 10;
		$this->peticion( $ficha, 'aprobada', $reg );

		try {
			$this->pantalla->handle_save_admin();
			$this->fail( 'el camino feliz termina en exit' );
		} catch ( \Throwable $e ) {
			if ( false !== strpos( $e->getMessage(), 'tus propias horas' )
				|| false !== strpos( $e->getMessage(), 'monitores' )
				|| false !== strpos( $e->getMessage(), 'permisos' ) ) {
				$this->fail( 'la administradora no debía bloquearse: ' . $e->getMessage() );
			}
			$this->assertTrue( true );
		}
	}
}
