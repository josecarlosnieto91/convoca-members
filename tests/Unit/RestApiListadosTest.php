<?php
/**
 * Los listados que pide el área de socio: inscripciones, pagos, actividades, privacidad y proyectos.
 *
 * El riesgo de estos handlers no es que revienten: es que devuelvan **de más**. Una inscripción se busca por el
 * correo o el DNI de la ficha; un pago, por el origen y el id de la ficha. Si ese filtro se cae, el socio ve
 * movimientos de otra persona y nadie se entera.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Rest_API;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Member_Auth.php';
require_once dirname( __DIR__, 2 ) . '/includes/GDPR_Tools.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Proyecto.php';
require_once dirname( __DIR__, 2 ) . '/includes/Rest_API.php';

/**
 * Listados del área de socio.
 */
final class RestApiListadosTest extends TestCase {

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
		unset( $GLOBALS['_wp_stores'], $GLOBALS['_test_posts'], $GLOBALS['_wp_cache'], $_COOKIE['convoca_member_session'] );

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
	 * Abre la sesión de un socio con su correo y su DNI.
	 *
	 * @param int    $id    Socio.
	 * @param string $email Correo.
	 * @param string $dni   DNI.
	 */
	private function con_sesion( int $id, string $email = 'ada@example.test', string $dni = '00000000T' ): void {
		$testigo = 'testigo-de-prueba';

		$GLOBALS['_wp_stores']['transients'][ 'convoca_member_session_' . $testigo ] = array(
			'id'             => $id,
			'wp_user_id'     => 900 + $id,
			'last_renewal'   => time(),
			'pending_cookie' => '',
		);
		$_COOKIE['convoca_member_session'] = $testigo;

		$ficha                                    = (object) array(
			'ID'          => $id,
			'post_type'   => 'miembro',
			'post_status' => 'publish',
			'post_title'  => 'Ada Lovelace',
		);
		$GLOBALS['_test_posts'][ $id ]             = $ficha;
		$GLOBALS['_wp_stores']['posts'][ $id ]     = $ficha;
		$GLOBALS['_wp_stores']['post_meta'][ $id ] = array(
			'_convoca_estado_miembro' => 'activo',
			'_convoca_email'          => $email,
			'_convoca_dni'            => $dni,
		);
	}

	/**
	 * Un registro cualquiera en el almacén.
	 *
	 * @param int    $id     Id.
	 * @param string $tipo   Tipo de contenido.
	 * @param array  $meta   Metadatos.
	 * @param string $titulo Título.
	 */
	private function registro( int $id, string $tipo, array $meta, string $titulo = 'Registro' ): void {
		$post = (object) array(
			'ID'           => $id,
			'post_type'    => $tipo,
			'post_status'  => 'publish',
			'post_title'   => $titulo,
			'post_date'    => '2026-10-01 10:00:00',
			'post_content' => '',
		);
		$GLOBALS['_wp_stores']['posts'][ $id ]     = $post;
		$GLOBALS['_wp_stores']['post_meta'][ $id ] = $meta;
	}

	/**
	 * Las inscripciones se buscan por el correo **o** el DNI de la ficha, y sólo esas.
	 */
	public function test_las_inscripciones(): void {
		$this->con_sesion( 55 );

		$this->registro( 101, 'inscripcion', array( '_convoca_email' => 'ada@example.test' ), 'Inscripción por correo' );
		$this->registro( 102, 'inscripcion', array( '_convoca_dni' => '00000000T' ), 'Inscripción por DNI' );
		$this->registro( 103, 'inscripcion', array( '_convoca_email' => 'otra@example.test', '_convoca_dni' => '11111111H' ), 'De otra persona' );

		$datos = $this->api->get_inscriptions( new \WP_REST_Request() )->get_data();
		$items = $datos['items'] ?? $datos;

		$this->assertCount( 2, $items, 'las dos de la ficha, y no la de otra persona' );
	}

	/**
	 * Los pagos: sólo los del origen «members» y de **esta** ficha.
	 */
	public function test_los_pagos(): void {
		$this->con_sesion( 55 );

		$this->registro( 201, 'pago', array( '_convoca_origin' => 'members', '_convoca_origin_id' => 55, '_convoca_importe' => '30.00' ), 'Cuota' );
		$this->registro( 202, 'pago', array( '_convoca_origin' => 'members', '_convoca_origin_id' => 56 ), 'De otra persona' );
		$this->registro( 203, 'pago', array( '_convoca_origin' => 'enroll', '_convoca_origin_id' => 55 ), 'De otro módulo' );

		$datos = $this->api->get_payments( new \WP_REST_Request() )->get_data();
		$items = $datos['items'] ?? $datos;

		$this->assertCount( 1, $items, 'un solo pago es suyo' );
	}

	/**
	 * Sin pagos, lista vacía (no un error).
	 */
	public function test_sin_pagos(): void {
		$this->con_sesion( 55 );

		$datos = $this->api->get_payments( new \WP_REST_Request() )->get_data();

		$this->assertSame( array(), $datos['items'] ?? $datos );
	}

	/**
	 * Las actividades: id y título, para el desplegable de registro de horas.
	 */
	public function test_las_actividades(): void {
		$this->registro( 301, 'actividad', array(), 'Huerto' );
		$this->registro( 302, 'actividad', array(), 'Taller de reciclaje' );

		$datos = $this->api->get_activities()->get_data();

		$this->assertCount( 2, $datos );
		// El orden es por fecha (descendente); lo que importa es el par id → título, no la posición.
		$por_id = array_column( $datos, 'title', 'id' );
		$this->assertSame( 'Huerto', $por_id[301] ?? null );
		$this->assertSame( 'Taller de reciclaje', $por_id[302] ?? null );
	}

	/**
	 * La privacidad sale del estado del socio.
	 */
	public function test_la_privacidad(): void {
		$this->con_sesion( 55 );
		$GLOBALS['_wp_stores']['post_meta'][55]['_convoca_supresion_solicitada'] = '2026-10-20 10:00:00';

		$datos = $this->api->get_privacidad( new \WP_REST_Request() )->get_data();

		$this->assertTrue( $datos['pendiente'], 'con solicitud sin resolver' );
		$this->assertSame( '2026-10-20 10:00:00', $datos['solicitada'] );
	}

	/**
	 * Los proyectos: se sirven cacheados y, con demasiadas peticiones, 429.
	 */
	public function test_los_proyectos(): void {
		$this->registro( 401, 'proyecto', array( '_convoca_activo' => '1' ), 'Vivero' );

		$datos = $this->api->get_proyectos()->get_data();
		$this->assertIsArray( $datos, 'contesta una lista' );

		// El limitador del núcleo: con el atajo de caché a 0, la acción queda bloqueada.
		$GLOBALS['_wp_cache']['convoca_rate_limits'][ 'convoca_rl_convoca_get_proyectos_ip_' . md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ) ) ] = 0;
		$GLOBALS['_wp_cache']['convoca_rate_limits'][ 'convoca_rl_convoca_get_proyectos_id_' ] = 0;

		$bloqueada = $this->api->get_proyectos();

		$this->assertSame( 429, $bloqueada->get_status(), 'demasiadas peticiones: se corta' );
	}
}
