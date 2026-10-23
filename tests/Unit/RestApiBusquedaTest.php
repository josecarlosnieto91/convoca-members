<?php
/**
 * La búsqueda global: socios y actividades, y la frontera entre los dos.
 *
 * Es la pantalla donde un fallo no da error, da **datos personales**: si un anónimo puede pedir `type=members`,
 * se lleva nombres, correos y números de socio sin haber entrado. Y al revés, si el socio autenticado no
 * encuentra nada, la búsqueda es inútil.
 *
 * Se comprueban las tres cosas: quién puede buscar socios, qué se devuelve de un socio (nada personal) y que la
 * búsqueda de verdad **filtre** por el término —un `s` que no filtra devuelve el listado entero y parece que
 * funciona.
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
 * Búsqueda global.
 */
final class RestApiBusquedaTest extends TestCase {

	/**
	 * La API.
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
		unset( $GLOBALS['_wp_stores'], $GLOBALS['_test_posts'], $_COOKIE['convoca_member_session'] );

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
	 * Abre sesión de socio.
	 *
	 * @param int $id Socio.
	 */
	private function con_sesion( int $id ): void {
		$testigo = 'testigo-de-prueba';

		$GLOBALS['_wp_stores']['transients'][ 'convoca_member_session_' . $testigo ] = array(
			'id'             => $id,
			'wp_user_id'     => 900 + $id,
			'last_renewal'   => time(),
			'pending_cookie' => '',
		);
		$_COOKIE['convoca_member_session'] = $testigo;
	}

	/**
	 * Una ficha.
	 *
	 * @param int    $id     Id.
	 * @param string $tipo   Tipo.
	 * @param string $titulo Título.
	 * @param array  $meta   Metadatos.
	 * @param string $fecha  Fecha.
	 * @param string $estado Estado.
	 */
	private function ficha( int $id, string $tipo, string $titulo, array $meta = array(), string $fecha = '2026-10-01 10:00:00', string $estado = 'publish' ): void {
		$post = (object) array(
			'ID'           => $id,
			'post_type'    => $tipo,
			'post_status'  => $estado,
			'post_title'   => $titulo,
			'post_date'    => $fecha,
			'post_content' => '',
		);
		$GLOBALS['_test_posts'][ $id ]             = $post;
		$GLOBALS['_wp_stores']['posts'][ $id ]     = $post;
		$GLOBALS['_wp_stores']['post_meta'][ $id ] = $meta;
	}

	/**
	 * Ejecuta la búsqueda.
	 *
	 * @param array $params Parámetros.
	 * @return array
	 */
	private function buscar( array $params ): array {
		$req = new \WP_REST_Request();
		foreach ( $params as $clave => $valor ) {
			$req->set_param( $clave, $valor );
		}

		$respuesta = $this->api->fulltext_search( $req );

		return (array) $respuesta->get_data();
	}

	/**
	 * Un anónimo que pide socios **no** los recibe: se le contesta con actividades y nada más.
	 */
	public function test_un_anonimo_no_ve_socios(): void {
		$this->ficha( 55, 'miembro', 'Ada Lovelace', array( '_convoca_email' => 'ada@example.test' ) );
		$this->ficha( 301, 'actividad', 'Taller de Ada', array() );

		$datos = $this->buscar(
			array(
				'q'    => 'Ada',
				'type' => 'members',
			)
		);

		$tipos = array_column( $datos['results'], 'type' );
		$this->assertSame( array( 'activity' ), $tipos, 'pedir socios sin sesión devuelve sólo actividades' );
		$this->assertSame( 1, $datos['total'] );
	}

	/**
	 * Y la actividad del anónimo trae lo público: fecha legible, ubicación y enlace.
	 */
	public function test_la_actividad_trae_lo_publico(): void {
		$this->ficha(
			301,
			'actividad',
			'Taller de huerto',
			array(
				'_convoca_fecha_inicio' => '2026-11-05 18:00:00',
				'_convoca_ubicacion'    => 'Centro social',
			)
		);

		$datos = $this->buscar( array( 'q' => 'huerto' ) );

		$this->assertCount( 1, $datos['results'] );
		$fila = $datos['results'][0];
		$this->assertSame( 'activity', $fila['type'] );
		$this->assertSame( 'Taller de huerto', $fila['title'] );
		$this->assertSame( '05/11/2026 18:00', $fila['fecha'], 'la fecha va legible, no en ISO' );
		$this->assertSame( 'Centro social', $fila['ubicacion'] );
		$this->assertStringContainsString( '301', $fila['url'], 'con su enlace' );
	}

	/**
	 * Con sesión, el socio sí aparece — pero sin datos personales: sólo id, título y el enlace a su ficha.
	 */
	public function test_el_socio_no_filtra_datos_personales(): void {
		$this->con_sesion( 55 );
		$this->ficha(
			55,
			'miembro',
			'Ada Lovelace',
			array(
				'_convoca_email'      => 'ada@example.test',
				'_convoca_dni'        => '00000000T',
				'_convoca_telefono'   => '600000000',
				'_convoca_nombre'     => 'Ada Lovelace',
				'_convoca_numero_socio' => '12',
			)
		);
		// Y un vecino que no casa con el término: la búsqueda tiene que **filtrar**.
		$this->ficha( 56, 'miembro', 'Grace Hopper', array( '_convoca_email' => 'grace@example.test' ) );

		$datos = $this->buscar( array( 'q' => 'ada', 'type' => 'members' ) );

		$this->assertCount( 1, $datos['results'], 'sólo la que casa con el término' );
		$fila = $datos['results'][0];
		$this->assertSame( 'member', $fila['type'] );
		$this->assertSame( 55, $fila['id'] );

		$json = (string) wp_json_encode( $fila );
		foreach ( array( 'ada@example.test', '00000000T', '600000000', '12' ) as $dato ) {
			$this->assertStringNotContainsString( $dato, $json, 'la búsqueda no entrega datos personales' );
		}
		$this->assertStringContainsString( '/me', $fila['url'], 'el detalle se pide en /me, con sesión' );
	}

	/**
	 * Un término de una sola letra no busca: devuelve vacío y el término tal cual.
	 */
	public function test_un_termino_muy_corto_no_busca(): void {
		$this->ficha( 55, 'miembro', 'Ada Lovelace', array( '_convoca_email' => 'ada@example.test' ) );
		$this->con_sesion( 55 );

		$datos = $this->buscar( array( 'q' => 'a' ) );

		$this->assertSame( array(), $datos['results'] );
		$this->assertSame( 0, $datos['total'] );
		$this->assertSame( 'a', $datos['query'], 'se devuelve el término para poder pintarlo' );
	}

	/**
	 * Una actividad en borrador no se enseña al público: no está publicada.
	 */
	public function test_un_borrador_no_sale(): void {
		$this->ficha( 301, 'actividad', 'Taller de huerto', array(), '2026-10-01 10:00:00', 'draft' );

		$datos = $this->buscar( array( 'q' => 'huerto' ) );

		$this->assertSame( array(), $datos['results'], 'un borrador no está publicado' );
	}

	/**
	 * Con sesión y sin `type`, primero los socios y después las actividades.
	 */
	public function test_socios_antes_que_actividades(): void {
		$this->con_sesion( 55 );
		// El término tiene que casar por el `s` **y** por alguno de los metadatos que mira la consulta (correo,
		// DNI, teléfono, número de socio, código o nombre): si no, el socio no entra y el orden no se prueba.
		$this->ficha( 55, 'miembro', 'Zoe de Ada', array( '_convoca_email' => 'zoe@example.test', '_convoca_nombre' => 'Ada Zoe' ) );
		$this->ficha( 301, 'actividad', 'Taller de Ada', array() );

		$datos = $this->buscar( array( 'q' => 'Ada' ) );

		$this->assertSame( array( 'member', 'activity' ), array_column( $datos['results'], 'type' ) );
	}

	/**
	 * El límite se respeta y no pasa de 50 aunque se pidan más.
	 */
	public function test_el_limite(): void {
		for ( $i = 1; $i <= 5; $i++ ) {
			$this->ficha( 300 + $i, 'actividad', 'Taller ' . $i, array(), '2026-10-0' . $i . ' 10:00:00' );
		}

		$this->assertCount( 2, $this->buscar( array( 'q' => 'Taller', 'limit' => 2 ) )['results'] );
		$this->assertCount( 5, $this->buscar( array( 'q' => 'Taller', 'limit' => 999 ) )['results'], 'no hay más' );
	}

	/**
	 * La búsqueda de socios del panel: pide dos letras y devuelve correo, que aquí **sí** es lo que se busca.
	 */
	public function test_la_busqueda_del_panel(): void {
		$this->ficha( 55, 'miembro', 'Ada Lovelace', array( '_convoca_email' => 'ada@example.test' ) );

		$corta = new \WP_REST_Request();
		$corta->set_param( 'term', 'a' );
		$this->assertSame( array(), $this->api->admin_search_members( $corta )->get_data(), 'con una letra no se busca' );

		$larga = new \WP_REST_Request();
		$larga->set_param( 'term', 'ada' );
		$datos = (array) $this->api->admin_search_members( $larga )->get_data();

		$this->assertCount( 1, $datos );
		$this->assertSame( 55, (int) $datos[0]['id'] );
		$this->assertSame( 'Ada Lovelace', $datos[0]['title'] );
		$this->assertSame( 'ada@example.test', $datos[0]['email'], 'aquí sí: es el panel, y el correo es lo que se busca' );
	}
}
