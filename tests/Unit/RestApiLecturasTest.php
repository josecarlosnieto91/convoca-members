<?php
/**
 * Lo que el área de socio pide por la API: perfil, horas, datos y avisos.
 *
 * Son las lecturas que hace el front. Dos cosas no pueden fallar aquí: el **código de acceso** no puede salir
 * entero (se enmascara), y las lecturas de un socio **no pueden devolver datos de otro** (el id sale siempre de
 * la sesión, nunca de la petición).
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Rest_API;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Member_Auth.php';
require_once dirname( __DIR__, 2 ) . '/includes/GDPR_Tools.php';
require_once dirname( __DIR__, 2 ) . '/includes/Rest_API.php';

/**
 * Lecturas del área de socio.
 */
final class RestApiLecturasTest extends TestCase {

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
	protected function limpiar(): void {
		unset( $GLOBALS['_wp_stores'], $GLOBALS['_test_posts'], $GLOBALS['_wp_site'], $_COOKIE['convoca_member_session'] );

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
	 * @param int   $id    Socio.
	 * @param array $meta  Metadatos de la ficha.
	 */
	private function con_sesion( int $id, array $meta = array() ): void {
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
			),
			$meta
		);
	}

	/**
	 * El perfil sale de la sesión, con el código de acceso enmascarado.
	 */
	public function test_el_perfil(): void {
		$this->con_sesion(
			55,
			array(
				'_convoca_access_code' => '12345678',
				'_convoca_dni'         => '00000000T',
			)
		);

		$respuesta = $this->api->get_profile( new \WP_REST_Request() );
		$datos     = $respuesta->get_data();

		$this->assertSame( 200, $respuesta->get_status() );
		$this->assertSame( 55, $datos['id'] );
		$this->assertSame( 'Ada Lovelace', $datos['nombre'] );
		$this->assertSame( 'ada@example.test', $datos['email'] );

		$codigo = (string) ( $datos['codigo'] ?? '' );
		$this->assertStringContainsString( '****', (string) $codigo, 'el código no puede salir entero' );
		$this->assertStringNotContainsString( '5678', (string) $codigo, 'y menos la parte final' );
	}

	/**
	 * El perfil es **siempre** el de la sesión: aunque la petición traiga un `id`, no se sirve el de otro socio.
	 */
	public function test_el_perfil_no_hace_caso_al_id_de_la_peticion(): void {
		$this->con_sesion( 55 );
		$GLOBALS['_wp_stores']['posts'][56]         = (object) array( 'ID' => 56, 'post_type' => 'miembro', 'post_status' => 'publish', 'post_title' => 'Otra persona' );
		$GLOBALS['_wp_stores']['post_meta'][56]     = array( '_convoca_email' => 'otra@example.test' );

		$datos = $this->api->get_profile( new \WP_REST_Request( array( 'id' => 56 ) ) )->get_data();

		$this->assertSame( 55, $datos['id'], 'el id sale de la sesión, no de la petición' );
		$this->assertSame( 'Ada Lovelace', $datos['nombre'], 'y no el de la otra ficha' );
	}

	/**
	 * Sin ficha (o con un id que no es socio) se contesta 404, no un perfil vacío.
	 */
	public function test_el_perfil_sin_ficha(): void {
		$respuesta = $this->api->get_profile( new \WP_REST_Request() );

		$this->assertSame( 404, $respuesta->get_status(), 'sin sesión no hay perfil, y se dice' );
		$this->assertArrayHasKey( 'error', $respuesta->get_data() );
	}

	/**
	 * Las horas: se suman las de la ficha y no las de otro socio.
	 */
	public function test_las_horas(): void {
		$this->con_sesion( 55 );

		$registro = static function ( int $id, float $horas, int $socio ): object {
			$post = (object) array(
				'ID'           => $id,
				'post_type'    => 'registro_hora',
				'post_status'  => 'publish',
				'post_title'   => 'Horas',
				'post_date'    => '2026-10-01 10:00:00',
				'post_content' => '',
			);
			$GLOBALS['_wp_stores']['posts'][ $id ]            = $post;
			$GLOBALS['_wp_stores']['post_meta'][ $id ]        = array(
				'_convoca_member_id' => $socio,
				'_convoca_horas'     => $horas,
				'_convoca_estado'    => 'aprobada',
			);

			return $post;
		};

		$registro( 101, 4.5, 55 );
		$registro( 102, 2.0, 55 );
		$registro( 103, 9.0, 56 ); // de otro socio: no puede aparecer

		$datos = $this->api->get_hours( new \WP_REST_Request() )->get_data();

		$this->assertSame( 6.5, (float) ( $datos['total'] ?? $datos['total_horas'] ?? 0 ), 'sólo las de la ficha' );
		$this->assertCount( 2, $datos['items'] ?? array(), 'dos registros, no tres' );
	}

	/**
	 * Sin registros, cero y lista vacía (no un error).
	 */
	public function test_las_horas_sin_registros(): void {
		$this->con_sesion( 55 );

		$datos = $this->api->get_hours( new \WP_REST_Request() )->get_data();

		$this->assertSame( 0.0, (float) ( $datos['total'] ?? $datos['total_horas'] ?? 0 ) );
		$this->assertSame( array(), $datos['items'] ?? array() );
	}

	/**
	 * «Mis datos» devuelve el paquete de descarga con su fecha, el sitio y el estado de privacidad.
	 */
	public function test_mis_datos(): void {
		$this->con_sesion( 55 );
		$GLOBALS['_wp_site']['name'] = 'Centro de pruebas';

		$datos = $this->api->get_mis_datos( new \WP_REST_Request() )->get_data();

		$this->assertArrayHasKey( 'generado', $datos );
		$this->assertSame( 'Centro de pruebas', $datos['sitio'] );
		$this->assertArrayHasKey( 'privacidad', $datos );
	}

	/**
	 * Los avisos: límite por defecto de 10, con su cuenta de no leídos y el total.
	 */
	public function test_los_avisos(): void {
		$avisos = array();
		for ( $i = 1; $i <= 12; $i++ ) {
			$avisos[] = array(
				'id'   => 'n' . $i,
				'text' => 'Aviso ' . $i,
				'read' => $i > 9,
			);
		}
		$this->con_sesion( 55, array( '_convoca_notifications' => $avisos ) );

		$datos = $this->api->get_member_notifications( new \WP_REST_Request() )->get_data();

		$this->assertSame( 10, count( $datos['items'] ), 'diez por defecto' );
		$this->assertSame( 9, $datos['unread'], 'nueve sin leer' );
		$this->assertSame( 12, $datos['total'], 'y el total no se recorta' );

		$tres = $this->api->get_member_notifications( new \WP_REST_Request( array( 'limit' => 3 ) ) )->get_data();
		$this->assertSame( 3, count( $tres['items'] ), 'el límite de la petición manda' );
	}

	/**
	 * Marcar un aviso sin id es un error de la petición; con id, se marca.
	 */
	public function test_marcar_un_aviso(): void {
		$avisos = array(
			array( 'id' => 'n1', 'text' => 'Uno', 'read' => false ),
			array( 'id' => 'n2', 'text' => 'Dos', 'read' => false ),
		);
		$this->con_sesion( 55, array( '_convoca_notifications' => $avisos ) );

		$malo = $this->api->mark_notification_read( new \WP_REST_Request() );
		$this->assertSame( 400, $malo->get_status(), 'sin id no se puede marcar nada' );
		$this->assertFalse( $malo->get_data()['success'] );

		$bueno = $this->api->mark_notification_read( new \WP_REST_Request( array( 'id' => 'n2' ) ) );
		$this->assertTrue( $bueno->get_data()['success'] );

		$guardados = get_post_meta( 55, '_convoca_notifications', true );
		$this->assertTrue( (bool) $guardados[1]['read'], 'se marca el que se pidió' );
		$this->assertFalse( (bool) $guardados[0]['read'], 'y no los demás' );
	}

	/**
	 * «Marcar todos» los deja todos leídos.
	 */
	public function test_marcar_todos(): void {
		$avisos = array(
			array( 'id' => 'n1', 'read' => false ),
			array( 'id' => 'n2', 'read' => false ),
		);
		$this->con_sesion( 55, array( '_convoca_notifications' => $avisos ) );

		$respuesta = $this->api->mark_all_notifications_read( new \WP_REST_Request() );
		$this->assertTrue( $respuesta->get_data()['success'] );

		$guardados = get_post_meta( 55, '_convoca_notifications', true );
		$this->assertSame( 0, count( array_filter( $guardados, static fn( $n ) => empty( $n['read'] ) ) ), 'ninguno sin leer' );
	}
}
