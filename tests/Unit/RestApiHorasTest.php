<?php
/**
 * Apuntar horas desde el área de socio.
 *
 * Es lo que sostiene el certificado y los niveles de voluntariado: si aquí se guarda mal, el socio trabaja y no
 * le cuenta. Y hay una decisión de producto que hay que fijar: el voluntario puede apuntar horas **sin
 * proyecto** (hay trabajo que no es de ningún proyecto: repartir carteles, llevar la contabilidad), pero la
 * junta sí lo exige en su pantalla, porque su libro tiene que cuadrar por proyectos.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Hours_Manager;
use Convoca\Members\Rest_API;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Hours_Manager.php';
require_once dirname( __DIR__, 2 ) . '/includes/Member_Auth.php';
require_once dirname( __DIR__, 2 ) . '/includes/Rest_API.php';

/**
 * Registro de horas por la API.
 */
final class RestApiHorasTest extends TestCase {

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
			'db_inserts' => array(),
		);
	}

	/**
	 * Abre la sesión de un socio.
	 *
	 * @param int $id Socio.
	 */
	private function con_sesion( int $id = 55 ): void {
		$testigo = 'testigo-de-prueba';

		$GLOBALS['_wp_stores']['transients'][ 'convoca_member_session_' . $testigo ] = array(
			'id'             => $id,
			'wp_user_id'     => 900 + $id,
			'last_renewal'   => time(),
			'pending_cookie' => '',
		);
		$_COOKIE['convoca_member_session'] = $testigo;

		$ficha                                  = (object) array(
			'ID'          => $id,
			'post_type'   => 'miembro',
			'post_status' => 'publish',
			'post_title'  => 'Ada Lovelace',
		);
		$GLOBALS['_test_posts'][ $id ]          = $ficha;
		$GLOBALS['_wp_stores']['posts'][ $id ]  = $ficha;
		$GLOBALS['_wp_stores']['post_meta'][ $id ] = array( '_convoca_estado_miembro' => 'activo' );
	}

	/**
	 * Un proyecto válido en el almacén.
	 *
	 * @param int $id Id.
	 */
	private function proyecto( int $id = 400 ): void {
		$proyecto = (object) array(
			'ID'          => $id,
			'post_type'   => 'proyecto',
			'post_status' => 'publish',
			'post_title'  => 'Vivero',
		);
		// `get_post()` del arnés mira `_test_posts` primero: sin sembrar ahí, devuelve un post inventado de tipo
		// «post» y la validación del proyecto lo rechaza por no ser un proyecto.
		$GLOBALS['_test_posts'][ $id ]         = $proyecto;
		$GLOBALS['_wp_stores']['posts'][ $id ] = $proyecto;
	}

	/**
	 * Un apunte completo se guarda, con sus datos, y se avisa.
	 */
	public function test_apuntar_horas_guarda_lo_enviado(): void {
		$this->con_sesion();
		$this->proyecto();

		$respuesta = $this->api->submit_hours(
			new \WP_REST_Request(
				array(
					'fecha'        => '2026-10-18',
					'horas'        => 4.5,
					'actividad_id' => 700,
					'proyecto_id'  => 400,
					'tareas'       => 'Siembra y riego',
					'descripcion'  => 'Mañana en el vivero',
				)
			)
		);
		$datos     = $respuesta->get_data();

		$this->assertTrue( $datos['success'], 'se ha guardado' );
		$this->assertGreaterThan( 0, (int) $datos['id'], 'con su id' );

		$registro = (int) $datos['id'];
		$this->assertSame( 55, (int) get_post_meta( $registro, '_convoca_member_id', true ), 'de la sesión, no de otro' );
		$this->assertSame( '2026-10-18', (string) get_post_meta( $registro, '_convoca_fecha', true ) );
		$this->assertSame( 4.5, (float) get_post_meta( $registro, '_convoca_horas', true ) );
		$this->assertSame( 400, (int) get_post_meta( $registro, '_convoca_proyecto_id', true ) );
		$this->assertSame( 'Siembra y riego', (string) get_post_meta( $registro, '_convoca_tareas', true ) );
		$this->assertSame( 'pendiente', (string) get_post_meta( $registro, '_convoca_estado', true ), 'nace pendiente de aprobación' );

		$hooks = array_column( $GLOBALS['_wp_actions'] ?? array(), 'hook' );
		$this->assertContains( 'convoca_members_hours_submitted', $hooks, 'se avisa de que hay horas que revisar' );
	}

	/**
	 * El voluntario puede apuntar horas **sin proyecto**: hay trabajo que no es de ningún proyecto.
	 */
	public function test_sin_proyecto_se_puede_apuntar(): void {
		$this->con_sesion();

		$respuesta = $this->api->submit_hours(
			new \WP_REST_Request(
				array(
					'fecha'  => '2026-10-18',
					'horas'  => 2,
					'tareas' => 'Repartir carteles',
				)
			)
		);

		$this->assertTrue( $respuesta->get_data()['success'], 'las horas sin proyecto cuentan: no se pueden perder' );
		$this->assertSame( 0, (int) get_post_meta( (int) $respuesta->get_data()['id'], '_convoca_proyecto_id', true ) );
	}

	/**
	 * Lo que no vale se rechaza con su motivo y no crea nada.
	 */
	public function test_los_apuntes_que_no_valen(): void {
		$this->con_sesion();

		$casos = array(
			'sin fecha'        => array( 'horas' => 2, 'tareas' => 'Algo' ),
			'sin horas'        => array( 'fecha' => '2026-10-18', 'tareas' => 'Algo' ),
			'horas a cero'     => array( 'fecha' => '2026-10-18', 'horas' => 0, 'tareas' => 'Algo' ),
			'horas negativas'  => array( 'fecha' => '2026-10-18', 'horas' => -3, 'tareas' => 'Algo' ),
			'sin tareas'       => array( 'fecha' => '2026-10-18', 'horas' => 2 ),
		);

		foreach ( $casos as $caso => $params ) {
			$respuesta = $this->api->submit_hours( new \WP_REST_Request( $params ) );

			$this->assertSame( 400, $respuesta->get_status(), 'debería rechazar: ' . $caso );
			$this->assertFalse( $respuesta->get_data()['success'] );
			$this->assertNotSame( '', (string) $respuesta->get_data()['message'], 'y decir por qué' );
		}

		$this->assertCount( 0, array_filter( $GLOBALS['_wp_stores']['posts'], static fn( $p ) => 'registro_hora' === ( $p->post_type ?? '' ) ), 'no se creó ningún registro' );
	}

	/**
	 * Un proyecto que no existe no se acepta.
	 */
	public function test_un_proyecto_inventado_no_vale(): void {
		$this->con_sesion();

		$respuesta = $this->api->submit_hours(
			new \WP_REST_Request(
				array(
					'fecha'       => '2026-10-18',
					'horas'       => 2,
					'tareas'      => 'Algo',
					'proyecto_id' => 999,
				)
			)
		);

		$this->assertSame( 400, $respuesta->get_status() );
		$this->assertStringContainsString( 'proyecto', strtolower( (string) $respuesta->get_data()['message'] ) );
	}

	/**
	 * Las horas que apunta el socio nacen **pendientes**: las aprueba quien coordina, no él.
	 */
	public function test_nacen_pendientes(): void {
		$this->con_sesion();

		$id = (int) $this->api->submit_hours(
			new \WP_REST_Request(
				array(
					'fecha'  => '2026-10-18',
					'horas'  => 3,
					'tareas' => 'Algo',
					'estado' => 'aprobada',
				)
			)
		)->get_data()['id'];

		$this->assertSame(
			'pendiente',
			(string) get_post_meta( $id, '_convoca_estado', true ),
			'el socio no puede aprobarse sus propias horas'
		);
	}
}
