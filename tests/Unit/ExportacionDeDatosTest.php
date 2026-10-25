<?php
/**
 * La exportación de datos de un socio.
 *
 * `ajax_export_member_data()` es la respuesta al **derecho de acceso** del RGPD: vuelca en un JSON todo lo que la
 * asociación tiene de una persona —ficha, inscripciones, pagos y horas— y se lo entrega. Dos cosas aquí no dan
 * error si se rompen:
 *
 *  - **solo un administrador**. No basta con gestionar socios: el volcado incluye DNI, teléfono, dirección y
 *    pagos de otra persona;
 *  - **la ficha tiene que ser de tipo `miembro`**. Sin esa comprobación, el identificador de cualquier entrada del
 *    sitio serviría para pedir un volcado, y lo que saldría es lo que hubiera en sus metas.
 *
 * El nonce va atado **a la ficha concreta** (`convoca_actions_<id>`), así que el de un socio no sirve para pedir
 * los datos de otro.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Admin_Metaboxes;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/admin/class-admin-metaboxes.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Miembro.php';

/**
 * Exportación de datos personales.
 */
final class ExportacionDeDatosTest extends TestCase {

	/**
	 * Valores previos.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Metaboxes.
	 *
	 * @var Admin_Metaboxes
	 */
	private Admin_Metaboxes $metaboxes;

	/**
	 * Monta el escenario.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->previo = array(
			'posts'    => $GLOBALS['_wp_stores']['posts'] ?? array(),
			'meta'     => $GLOBALS['_wp_stores']['post_meta'] ?? array(),
			'xaccion'  => $GLOBALS['_wp_stores']['nonces_por_accion'] ?? null,
			'nonces'   => $GLOBALS['_wp_stores']['nonces_validos'] ?? null,
			'json'     => $GLOBALS['_wp_json'] ?? null,
			'caps'     => $GLOBALS['_test_caps'] ?? array(),
			'post'     => $_POST ?? array(),
			'die'      => $GLOBALS['_wp_die_lanza'] ?? null,
			'errlog'   => ini_get( 'error_log' ),
		);

		$GLOBALS['_wp_stores']['posts']              = array();
		$GLOBALS['_wp_stores']['post_meta']          = array();
		$GLOBALS['_wp_stores']['nonces_por_accion']  = array(
			'convoca_actions_500' => 'nonce-500',
			'convoca_actions_501' => 'nonce-501',
			'convoca_actions_999' => 'nonce-999',
		);
		$GLOBALS['_wp_json']                         = array();
		$GLOBALS['_test_caps']                       = array( 'manage_options' => true );
		$GLOBALS['_wp_die_lanza']                    = true;
		unset( $GLOBALS['_wp_stores']['nonces_validos'] );

		$GLOBALS['_errlog_tmp'] = sys_get_temp_dir() . '/convoca-export-test.log';
		ini_set( 'error_log', $GLOBALS['_errlog_tmp'] );

		$this->metaboxes = ( new \ReflectionClass( Admin_Metaboxes::class ) )->newInstanceWithoutConstructor();
	}

	/**
	 * Devuelve el escenario.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']['posts']     = $this->previo['posts'];
		$GLOBALS['_wp_stores']['post_meta'] = $this->previo['meta'];
		$GLOBALS['_wp_json']                = $this->previo['json'] ?? array();
		$GLOBALS['_test_caps']              = $this->previo['caps'];
		$GLOBALS['_wp_die_lanza']           = $this->previo['die'];
		$_POST                              = $this->previo['post'];

		foreach ( array( 'nonces_por_accion' => 'xaccion', 'nonces_validos' => 'nonces' ) as $clave => $origen ) {
			if ( null === $this->previo[ $origen ] ) {
				unset( $GLOBALS['_wp_stores'][ $clave ] );
			} else {
				$GLOBALS['_wp_stores'][ $clave ] = $this->previo[ $origen ];
			}
		}

		ini_set( 'error_log', $this->previo['errlog'] ?: '' );
		if ( is_file( (string) ( $GLOBALS['_errlog_tmp'] ?? '' ) ) ) {
			unlink( (string) $GLOBALS['_errlog_tmp'] );
		}
	}

	/**
	 * Siembra un post.
	 *
	 * @param int    $id     ID.
	 * @param string $tipo   Tipo.
	 * @param array  $meta   Metas.
	 * @param string $titulo Título.
	 * @return void
	 */
	private function post( int $id, string $tipo, array $meta, string $titulo = 'Ficha' ): void {
		$GLOBALS['_wp_stores']['posts'][ $id ]      = (object) array(
			'ID'          => $id,
			'post_type'   => $tipo,
			'post_status' => 'publish',
			'post_title'  => $titulo,
			'post_date'   => '2026-01-15 10:00:00',
		);
		$GLOBALS['_wp_stores']['post_meta'][ $id ] = $meta;
	}

	/**
	 * Pide la exportación y devuelve el corte o el JSON.
	 *
	 * @param array $post Campos de `$_POST`.
	 * @return array{0:string,1:array}
	 */
	private function exportar( array $post ): array {
		$_POST    = $post;
		$_REQUEST = $post;

		try {
			$this->metaboxes->ajax_export_member_data();
		} catch ( \Throwable $e ) {
			return array( 'corte:' . $e->getMessage(), array() );
		}

		$json = (array) $GLOBALS['_wp_json'];
		return array( 'ok', end( $json ) ?: array() );
	}

	/**
	 * **Sin `manage_options` no se exporta nada.**
	 *
	 * El volcado lleva DNI, teléfono y pagos de otra persona: gestionar socios no basta.
	 */
	public function test_sin_permiso_de_administrador_no_se_exporta(): void {
		$this->post( 500, 'miembro', array( '_convoca_email' => 'ana@example.test' ), 'Ana' );
		$GLOBALS['_test_caps'] = array(
			'manage_options'     => false,
			'gestionar_miembros' => true,
		);

		list( $corte ) = $this->exportar(
			array(
				'post_id' => '500',
				'nonce'   => 'nonce-500',
			)
		);

		$this->assertStringContainsString( 'Sin permisos', $corte );
		// `wp_send_json_error()` apunta la respuesta **y** lanza, así que la lista no queda vacía: lo que se exige
		// es que no haya ni una respuesta con éxito.
		$this->assertSame( array(), array_filter( (array) $GLOBALS['_wp_json'], static fn( $r ) => ! empty( $r['ok'] ) ), 'no salió ningún dato' );
	}

	/**
	 * **Una ficha que no es de tipo `miembro` no se exporta.**
	 *
	 * Sin esta comprobación, el identificador de cualquier entrada del sitio serviría para pedir un volcado y lo
	 * que saldría es lo que hubiera en sus metas.
	 */
	public function test_una_ficha_que_no_es_de_socio_no_se_exporta(): void {
		$this->post( 500, 'page', array( '_convoca_email' => 'secreto@example.test' ), 'Una página' );

		list( $corte ) = $this->exportar(
			array(
				'post_id' => '500',
				'nonce'   => 'nonce-500',
			)
		);

		$this->assertStringContainsString( 'Miembro no encontrado', $corte );
		$this->assertSame( array(), array_filter( (array) $GLOBALS['_wp_json'], static fn( $r ) => ! empty( $r['ok'] ) ) );
	}

	/**
	 * Una ficha que no existe tampoco.
	 */
	public function test_una_ficha_inexistente_no_se_exporta(): void {
		list( $corte ) = $this->exportar(
			array(
				'post_id' => '999',
				'nonce'   => 'nonce-999',
			)
		);

		$this->assertStringContainsString( 'Miembro no encontrado', $corte );
	}

	/**
	 * **El nonce de un socio no sirve para pedir los datos de otro.**
	 */
	public function test_el_nonce_de_otro_socio_no_sirve(): void {
		$this->post( 500, 'miembro', array( '_convoca_email' => 'ana@example.test' ), 'Ana' );
		$this->post( 501, 'miembro', array( '_convoca_email' => 'bea@example.test' ), 'Bea' );

		list( $corte ) = $this->exportar(
			array(
				'post_id' => '500',
				'nonce'   => 'nonce-501',
			)
		);

		$this->assertStringContainsString( 'nonce', strtolower( $corte ) );
		$this->assertSame( array(), array_filter( (array) $GLOBALS['_wp_json'], static fn( $r ) => ! empty( $r['ok'] ) ) );
	}

	/**
	 * El camino bueno: la ficha con sus datos y lo que cuelga de ella.
	 */
	public function test_exporta_la_ficha_sus_inscripciones_y_sus_horas(): void {
		$this->post(
			500,
			'miembro',
			array(
				'_convoca_email'           => 'ana@example.test',
				'_convoca_dni'             => '00000000T',
				'_convoca_telefono'        => '600111222',
				'_convoca_estado_miembro'  => 'activo',
				'_convoca_plan'            => 'socio',
			),
			'Ana Lovelace'
		);
		// Inscripción: se busca por correo.
		$this->post( 700, 'inscripcion', array( '_convoca_email' => 'ana@example.test' ), 'Taller de setas' );
		// Horas: se buscan por id de socio.
		$this->post(
			800,
			'registro_hora',
			array(
				'_convoca_member_id' => '500',
				'_convoca_horas'     => '4',
				'_convoca_estado'    => 'aprobada',
			)
		);
		// Y una inscripción de otra persona, que no puede salir.
		$this->post( 701, 'inscripcion', array( '_convoca_email' => 'bea@example.test' ), 'Taller de aves' );

		list( $estado, $json ) = $this->exportar(
			array(
				'post_id' => '500',
				'nonce'   => 'nonce-500',
			)
		);

		$this->assertSame( 'ok', $estado );
		$this->assertTrue( (bool) ( $json['ok'] ?? false ), 'respondió con éxito' );

		$datos = (array) ( $json['data'] ?? array() );
		$this->assertSame( 500, $datos['member']['id'] ?? null );
		$this->assertSame( 'Ana Lovelace', $datos['member']['name'] ?? null );
		$this->assertSame( 'ana@example.test', $datos['member']['email'] ?? null );
		$this->assertSame( '00000000T', $datos['member']['dni'] ?? null, 'el DNI tiene que salir: es el derecho de acceso' );
		$this->assertSame( '600111222', $datos['member']['telefono'] ?? null );

		$inscripciones = array_column( (array) ( $datos['inscriptions'] ?? array() ), 'id' );
		$this->assertContains( 700, array_map( 'intval', $inscripciones ), 'su inscripción sí' );
		$this->assertNotContains( 701, array_map( 'intval', $inscripciones ), 'la de otra persona no' );

		$horas = array_column( (array) ( $datos['volunteer_hours'] ?? array() ), 'id' );
		$this->assertContains( 800, array_map( 'intval', $horas ), 'sus horas sí' );
	}

	/**
	 * El volcado trae las cuatro secciones siempre, aunque estén vacías: quien lo recibe tiene que poder leer
	 * «no hay pagos» en vez de encontrar la clave ausente y no saber si falta el dato o el apartado.
	 */
	public function test_el_volcado_trae_todas_las_secciones(): void {
		$this->post( 500, 'miembro', array( '_convoca_email' => 'ana@example.test' ), 'Ana' );

		list( , $json ) = $this->exportar(
			array(
				'post_id' => '500',
				'nonce'   => 'nonce-500',
			)
		);

		$datos = (array) ( $json['data'] ?? array() );
		foreach ( array( 'member', 'inscriptions', 'payments', 'volunteer_hours' ) as $seccion ) {
			$this->assertArrayHasKey( $seccion, $datos, 'falta la sección ' . $seccion );
		}
	}
}
