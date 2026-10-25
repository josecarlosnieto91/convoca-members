<?php
/**
 * Las puertas de la API REST de socios: qué se expone y a quién.
 *
 * Aquí no se prueba «que la ruta conteste»: se prueba **quién** puede llegar. Una ruta de socio con
 * `__return_true` es una fuga de datos de una persona a cualquiera que sepa la URL, y no da ningún error: hay
 * que ir a mirarlo. Por eso lo primero es la matriz —qué rutas son públicas y cuáles no— y después cada
 * puerta, con y sin sesión, y con la ficha en cada estado.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Member_Auth;
use Convoca\Members\Rest_API;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Member_Auth.php';
require_once dirname( __DIR__, 2 ) . '/includes/Rest_API.php';

/**
 * Matriz de rutas y puertas de acceso.
 */
final class RestApiPuertasTest extends TestCase {

	/**
	 * Rutas que pueden ser públicas por diseño.
	 *
	 * @var string[]
	 */
	private const PUBLICAS_A_PROPOSITO = array( '/login', '/proyectos', '/search' );

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
	}

	/**
	 * Deja el arnés como estaba.
	 */
	private function limpiar(): void {
		unset( $GLOBALS['_wp_routes'], $GLOBALS['_wp_stores'], $GLOBALS['_test_posts'] );

		$GLOBALS['_wp_stores'] = array(
			'options'    => array(),
			'post_meta'  => array(),
			'transients' => array(),
			'user_meta'  => array(),
			'cookies'    => array(),
		);
		$GLOBALS['_wp_routes'] = array();
		unset( $_COOKIE['convoca_member_session'] );
	}

	/**
	 * Registra las rutas del plugin y las devuelve indexadas por camino.
	 *
	 * @return array<string, array>
	 */
	private function rutas(): array {
		( new Rest_API() )->register_routes();

		$por_camino = array();
		foreach ( $GLOBALS['_wp_routes'] as $ruta ) {
			$args = $ruta['args'];
			if ( isset( $args['callback'] ) ) {
				$por_camino[ $ruta['route'] ] = $args;
				continue;
			}
			foreach ( $args as $metodo => $definicion ) {
				if ( is_array( $definicion ) && isset( $definicion['callback'] ) ) {
					$por_camino[ $ruta['route'] ][ $metodo ] = $definicion;
				}
			}
		}

		return $por_camino;
	}

	/**
	 * Todas las rutas van al mismo espacio de nombres, y es **el que ya usan los clientes**: cambiarlo deja
	 * muertas todas las llamadas del front (se fija a mano, no contra la constante, que cambia sola).
	 */
	public function test_el_espacio_de_nombres(): void {
		$this->rutas();

		$this->assertNotEmpty( $GLOBALS['_wp_routes'], 'se registran rutas' );
		foreach ( $GLOBALS['_wp_routes'] as $ruta ) {
			$this->assertSame( 'convoca-members/v1', $ruta['namespace'], $ruta['route'] . ' fuera de sitio' );
		}
	}

	/**
	 * Ninguna ruta de datos de socio queda abierta: sólo las de entrar y recuperar la cuenta son públicas.
	 */
	public function test_ninguna_ruta_de_socio_es_publica(): void {
		$abiertas = array();

		foreach ( $this->rutas() as $camino => $definicion ) {
			foreach ( $this->definiciones( $definicion ) as $d ) {
				if ( '__return_true' === ( $d['permission_callback'] ?? null ) ) {
					$abiertas[] = $camino;
				}
			}
		}

		$abiertas  = array_values( array_unique( $abiertas ) );
		$esperadas = self::PUBLICAS_A_PROPOSITO;
		sort( $abiertas );
		sort( $esperadas );

		$this->assertSame(
			$esperadas,
			$abiertas,
			'sólo pueden ser públicas las de entrar o recuperar la cuenta: ' . implode( ', ', $abiertas )
		);
	}

	/**
	 * Toda ruta declara puerta y callback: una ruta sin puerta es una fuga, y una sin callback no contesta.
	 */
	public function test_toda_ruta_declara_puerta_y_callback(): void {
		foreach ( $this->rutas() as $camino => $definicion ) {
			foreach ( $this->definiciones( $definicion ) as $d ) {
				$this->assertArrayHasKey( 'permission_callback', $d, $camino . ': sin puerta' );
				$this->assertTrue(
					is_callable( $d['permission_callback'] ),
					$camino . ': la puerta no es invocable'
				);
				$this->assertArrayHasKey( 'callback', $d, $camino . ': sin callback' );
				$this->assertTrue( is_callable( $d['callback'] ), $camino . ': el callback no es invocable' );
			}
		}
	}

	/**
	 * Las rutas de administración exigen la capacidad del módulo, no sólo estar dentro.
	 */
	public function test_las_rutas_de_gestion_exigen_capacidad(): void {
		$gestion = array();

		foreach ( $this->rutas() as $camino => $definicion ) {
			foreach ( $this->definiciones( $definicion ) as $d ) {
				$puerta = $d['permission_callback'] ?? null;
				if ( $puerta instanceof \Closure ) {
					$gestion[] = $camino;
				}
			}
		}

		$this->assertNotEmpty( $gestion, 'hay rutas de gestión (clausura con current_user_can)' );
		$this->assertContains( '/admin/members/search', $gestion );
	}

	/**
	 * Sin sesión no se pasa, y se avisa de que no se cachee.
	 */
	public function test_la_puerta_de_sesion(): void {
		$this->assertFalse( Rest_API::check_member_auth(), 'sin testigo no se pasa' );
		$this->assertSame( 1, $GLOBALS['_wp_stores']['nocache'] ?? 0, 'y la respuesta no se cachea' );

		$this->con_sesion( 55, 'activo' );

		$this->assertTrue( Rest_API::check_member_auth() );
		$this->assertSame( 2, $GLOBALS['_wp_stores']['nocache'] );
	}

	/**
	 * La puerta de socio activo: sin sesión no pasa; con sesión pero inactivo, 403 con su motivo.
	 *
	 * Y un socio de **baja no llega ni aquí**: su sesión se destruye antes (`get_current_member_id()`), así que
	 * la respuesta es `false` (401/redirección) y no el 403 con motivo. Se afirma tal cual es.
	 */
	public function test_la_puerta_de_socio_activo(): void {
		$this->assertFalse( Rest_API::check_active_member(), 'sin sesión no pasa y no es un error de estado' );

		$this->con_sesion( 55, 'baja' );
		$this->assertFalse( Rest_API::check_active_member(), 'de baja no hay sesión: no es un 403 con motivo' );
		$this->assertFalse( get_transient( 'convoca_member_session_testigo-de-prueba' ), 'la sesión se tiró' );

		foreach ( array( 'suspendido', 'pendiente' ) as $estado ) {
			$this->con_sesion( 55, $estado );
			$error = Rest_API::check_active_member();

			$this->assertInstanceOf( \WP_Error::class, $error, 'con estado ' . $estado );
			$this->assertSame( 'inactive_member', $error->get_error_code() );
			$this->assertSame( 403, $error->get_error_data()['status'] ?? 0 );
		}

		$this->con_sesion( 55, 'activo' );
		$this->assertTrue( Rest_API::check_active_member() );
	}

	/**
	 * La puerta de renovación: activo y suspendido sí; de baja tiene su propio mensaje; otros estados, no.
	 */
	public function test_la_puerta_de_renovacion(): void {
		$this->assertFalse( Rest_API::check_renewable_member(), 'sin sesión no pasa' );

		foreach ( array( 'activo', 'suspendido' ) as $estado ) {
			$this->con_sesion( 55, $estado );
			$this->assertTrue( Rest_API::check_renewable_member(), 'con estado ' . $estado . ' se puede renovar' );
		}

		// De baja **sí se llega** y tiene su propio mensaje: `check_renewable_member()` lee el id con
		// `$permitir_baja = true`, así que distingue «estás de baja» de «no hay sesión» (members#66). Antes esta
		// prueba afirmaba `false`, que es lo que devolvía por no poder distinguirlos.
		$this->con_sesion( 55, 'baja' );
		$error = Rest_API::check_renewable_member();
		$this->assertInstanceOf( \WP_Error::class, $error, 'de baja no renueva' );
		$this->assertSame( 'member_baja', $error->get_error_code() );
		$this->assertStringContainsString( 'Vuelve a darte de alta', $error->get_error_message() );

		$this->con_sesion( 55, 'pendiente' );
		$error = Rest_API::check_renewable_member();
		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertSame( 'inactive_member', $error->get_error_code(), 'pendiente no renueva: primero se le aprueba' );
	}

	/**
	 * Las definiciones de una ruta, sea simple o por método.
	 *
	 * @param array $definicion Definición registrada.
	 * @return array<int, array>
	 */
	private function definiciones( array $definicion ): array {
		if ( isset( $definicion['callback'] ) ) {
			return array( $definicion );
		}

		return array_values( array_filter( $definicion, 'is_array' ) );
	}

	/**
	 * Abre una sesión de socio con ese estado.
	 *
	 * @param int    $id     Socio.
	 * @param string $estado Estado de la ficha.
	 */
	private function con_sesion( int $id, string $estado ): void {
		$testigo = 'testigo-de-prueba';

		// La clave del testigo es la del producto: `convoca_member_session_` + testigo (constante privada).
		// La sesión guarda `id` (el socio), `wp_user_id` y si toca renovar el testigo.
		$GLOBALS['_wp_stores']['transients'][ 'convoca_member_session_' . $testigo ] = array(
			'id'             => $id,
			'wp_user_id'     => 900 + $id,
			'last_renewal'   => time(),
			'pending_cookie' => '',
		);
		$GLOBALS['_wp_stores']['post_meta'][ $id ]['_convoca_estado_miembro']          = $estado;
		$GLOBALS['_test_posts'][ $id ] = (object) array(
			'ID'          => $id,
			'post_type'   => 'miembro',
			'post_status' => 'publish',
		);
		$_COOKIE['convoca_member_session'] = $testigo;
	}
}
