<?php
/**
 * La consulta del listado de socios: búsqueda y filtros a la vez.
 *
 * Hay dos cosas aquí que no dan error si se rompen, y las dos enseñan de más o de menos:
 *
 *  1. **Los filtros se disuelven dentro del `OR` de la búsqueda.** `prepare_items()` arma un `meta_query` con
 *     `relation => OR` cuando hay búsqueda («casa por correo **o** por DNI **o** por teléfono…») y luego añade
 *     cada filtro con `array_merge` a ese mismo array. El filtro acaba **dentro** del `OR`, así que deja de
 *     filtrar: pedir «activo» devuelve también las fichas de baja que casan la búsqueda.
 *  2. **La búsqueda exige el término en el título además de en las metas.** `$args['s']` va por un lado y el
 *     `meta_query` por otro, y WordPress los combina con AND: para que salga una ficha, el texto buscado tiene
 *     que estar **también** en el título o el contenido. Buscar un correo, un DNI o un teléfono —que están en
 *     metas, no en el título— no encuentra nada.
 *
 * Se mide lo que la pantalla devuelve de verdad, no lo que el código parece querer decir. El doble de
 * `WP_Query` filtra `s` sobre título/extracto/contenido, igual que WordPress.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Admin_List;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/admin/class-admin-list.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Miembro.php';
require_once dirname( __DIR__, 2 ) . '/includes/Estados.php';

/**
 * Búsqueda y filtros del listado.
 */
final class BusquedaYFiltrosListadoTest extends TestCase {

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
			'posts'  => $GLOBALS['_wp_stores']['posts'] ?? array(),
			'meta'   => $GLOBALS['_wp_stores']['post_meta'] ?? array(),
			'nonces' => $GLOBALS['_wp_stores']['nonces_validos'] ?? null,
			'opts'   => $GLOBALS['_wp_stores']['options'] ?? array(),
			'trans'  => $GLOBALS['_wp_stores']['transients'] ?? array(),
			'req'    => $_REQUEST ?? array(),
			'get'    => $_GET ?? array(),
			'errlog' => ini_get( 'error_log' ),
		);

		$GLOBALS['_wp_stores']['posts']      = array();
		$GLOBALS['_wp_stores']['post_meta']  = array();
		$GLOBALS['_wp_stores']['transients'] = array();
		$_REQUEST                            = array();
		$_GET                                = array();

		$GLOBALS['_errlog_tmp'] = sys_get_temp_dir() . '/convoca-filtros-test.log';
		ini_set( 'error_log', $GLOBALS['_errlog_tmp'] );
	}

	/**
	 * Devuelve el escenario.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']['posts']      = $this->previo['posts'];
		$GLOBALS['_wp_stores']['post_meta']  = $this->previo['meta'];
		$GLOBALS['_wp_stores']['options']    = $this->previo['opts'];
		$GLOBALS['_wp_stores']['transients'] = $this->previo['trans'];
		$_REQUEST                            = $this->previo['req'];
		$_GET                                = $this->previo['get'];

		if ( null === $this->previo['nonces'] ) {
			unset( $GLOBALS['_wp_stores']['nonces_validos'] );
		} else {
			$GLOBALS['_wp_stores']['nonces_validos'] = $this->previo['nonces'];
		}

		ini_set( 'error_log', $this->previo['errlog'] ?: '' );
		if ( is_file( (string) ( $GLOBALS['_errlog_tmp'] ?? '' ) ) ) {
			unlink( (string) $GLOBALS['_errlog_tmp'] );
		}
	}

	/**
	 * Siembra una ficha.
	 *
	 * @param int    $id     ID.
	 * @param string $titulo Título (es lo que mira `s`).
	 * @param array  $meta   Metas.
	 * @return void
	 */
	private function ficha( int $id, string $titulo, array $meta ): void {
		$GLOBALS['_wp_stores']['posts'][ $id ]      = (object) array(
			'ID'          => $id,
			'post_type'   => 'miembro',
			'post_status' => 'publish',
			'post_title'  => $titulo,
			'post_date'   => '2026-01-01 00:00:00',
		);
		$GLOBALS['_wp_stores']['post_meta'][ $id ] = $meta;
	}

	/**
	 * Prepara el listado con los parámetros dados.
	 *
	 * @param array $req `$_REQUEST`.
	 * @param array $get `$_GET`.
	 * @return array IDs devueltos.
	 */
	private function listar( array $req, array $get = array() ): array {
		$_REQUEST = $req;
		$_GET     = array_merge( $req, $get );

		$listado = new Admin_List();
		$listado->prepare_items();

		return array_map(
			static function ( $item ) {
				return (int) $item->ID;
			},
			(array) $listado->items
		);
	}

	/* ── Filtros que se disuelven en el OR ───────────────────────────── */

	/**
	 * **Buscar y filtrar por estado devuelve solo los que cumplen las dos cosas.**
	 *
	 * Con el `relation => OR` de la búsqueda, el filtro se queda dentro del OR y la pantalla enseña fichas de
	 * socios que no están en el estado pedido. En un listado de personas eso es enseñar de más.
	 */
	public function test_busqueda_y_filtro_de_estado_se_aplican_las_dos(): void {
		$this->ficha( 500, 'Ana Uno', array( '_convoca_nombre' => 'Ana Uno', '_convoca_email' => 'ana@example.test', '_convoca_estado_miembro' => 'activo' ) );
		$this->ficha( 501, 'Ana Dos', array( '_convoca_nombre' => 'Ana Dos', '_convoca_email' => 'ana@example.test', '_convoca_estado_miembro' => 'baja' ) );

		$ids = $this->listar(
			array( 's' => 'Ana' ),
			array( 'estado_filter' => 'activo' )
		);

		$this->assertSame( array( 500 ), $ids, 'la ficha de baja no puede salir con el filtro activo' );
	}

	/**
	 * Y al revés: el filtro no se come la búsqueda.
	 */
	public function test_el_filtro_tampoco_se_come_la_busqueda(): void {
		$this->ficha( 500, 'Ana Uno', array( '_convoca_nombre' => 'Ana Uno', '_convoca_estado_miembro' => 'activo' ) );
		$this->ficha( 501, 'Ana Dos', array( '_convoca_nombre' => 'Ana Dos', '_convoca_estado_miembro' => 'baja' ) );
		$this->ficha( 502, 'Bea', array( '_convoca_nombre' => 'Bea', '_convoca_estado_miembro' => 'baja' ) );

		$ids = $this->listar(
			array( 's' => 'Ana' ),
			array( 'estado_filter' => 'baja' )
		);

		$this->assertSame( array( 501 ), $ids, 'ni la activa ni la que no casa la búsqueda' );
	}

	/**
	 * Buscar y filtrar por plan a la vez: solo los que cumplen las dos.
	 */
	public function test_busqueda_y_filtro_de_plan_se_aplican_los_dos(): void {
		$this->ficha( 500, 'Ana Uno', array( '_convoca_nombre' => 'Ana Uno', '_convoca_plan' => 'socio' ) );
		$this->ficha( 501, 'Ana Dos', array( '_convoca_nombre' => 'Ana Dos', '_convoca_plan' => 'voluntariado' ) );

		$ids = $this->listar(
			array( 's' => 'Ana' ),
			array( 'plan_filter' => 'voluntariado' )
		);

		$this->assertSame( array( 501 ), $ids );
	}

	/**
	 * Buscar y pedir los que no tienen consentimiento: la búsqueda no puede acabar exigiendo que la ficha tenga
	 * **todas** las metas a la vez.
	 */
	public function test_busqueda_y_filtro_de_consentimiento_se_aplican_los_dos(): void {
		$this->ficha( 500, 'Ana Uno', array( '_convoca_nombre' => 'Ana Uno', '_convoca_consent_timestamp' => '2026-01-01 10:00:00' ) );
		$this->ficha( 501, 'Ana Dos', array( '_convoca_nombre' => 'Ana Dos', '_convoca_email' => 'ana@example.test' ) );

		$ids = $this->listar(
			array( 's' => 'Ana' ),
			array( 'consentimiento_filter' => 'sin' )
		);

		$this->assertSame( array( 501 ), $ids, 'solo la que no tiene constancia' );
	}

	/**
	 * Buscar y pedir solo voluntarios: idem.
	 */
	public function test_busqueda_y_filtro_de_voluntarios_se_aplican_los_dos(): void {
		$this->ficha( 500, 'Ana Uno', array( '_convoca_nombre' => 'Ana Uno', '_convoca_es_voluntario' => '0' ) );
		$this->ficha( 501, 'Ana Dos', array( '_convoca_nombre' => 'Ana Dos', '_convoca_es_voluntario' => '1' ) );

		$ids = $this->listar(
			array( 's' => 'Ana' ),
			array( 'voluntarios' => '1' )
		);

		$this->assertSame( array( 501 ), $ids );
	}

	/* ── La búsqueda exige el término en el título ───────────────────── */

	/**
	 * **Buscar por correo tiene que encontrar la ficha.**
	 *
	 * El correo vive en una meta, no en el título. Si `s` se exige además del `meta_query`, un término que no esté
	 * en el título no devuelve nada: el buscador del listado no encuentra a nadie por correo, DNI ni teléfono.
	 */
	public function test_la_busqueda_encuentra_por_correo(): void {
		$this->ficha( 500, 'Socia 500', array( '_convoca_email' => 'ana@example.test' ) );
		$this->ficha( 501, 'Socia 501', array( '_convoca_email' => 'bea@example.test' ) );

		$this->assertSame( array( 500 ), $this->listar( array( 's' => 'ana@example.test' ) ) );
	}

	/**
	 * Y por DNI.
	 */
	public function test_la_busqueda_encuentra_por_dni(): void {
		$this->ficha( 500, 'Socia 500', array( '_convoca_dni' => '00000000T' ) );
		$this->ficha( 501, 'Socia 501', array( '_convoca_dni' => '11111111H' ) );

		$this->assertSame( array( 500 ), $this->listar( array( 's' => '00000000T' ) ) );
	}

	/**
	 * Y por teléfono.
	 */
	public function test_la_busqueda_encuentra_por_telefono(): void {
		$this->ficha( 500, 'Socia 500', array( '_convoca_telefono' => '600111222' ) );
		$this->ficha( 501, 'Socia 501', array( '_convoca_telefono' => '600333444' ) );

		$this->assertSame( array( 500 ), $this->listar( array( 's' => '600111222' ) ) );
	}

	/* ── Lo que ya iba bien ──────────────────────────────────────────── */

	/**
	 * Los filtros solos, sin búsqueda, funcionan.
	 */
	public function test_los_filtros_solos_funcionan(): void {
		$this->ficha( 500, 'Ana', array( '_convoca_estado_miembro' => 'activo' ) );
		$this->ficha( 501, 'Bea', array( '_convoca_estado_miembro' => 'baja' ) );

		$this->assertSame( array( 500 ), $this->listar( array(), array( 'estado_filter' => 'activo' ) ) );
	}

	/**
	 * Sin búsqueda ni filtros salen todas.
	 */
	public function test_sin_filtros_salen_todas(): void {
		$this->ficha( 500, 'Ana', array() );
		$this->ficha( 501, 'Bea', array() );

		$ids = $this->listar( array() );
		sort( $ids );
		$this->assertSame( array( 500, 501 ), $ids );
	}
}
