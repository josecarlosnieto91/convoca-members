<?php
/**
 * El listado y el editor de proyectos.
 *
 * Un proyecto agrupa horas de voluntariado, así que sus fechas y su responsable tienen que decir la verdad: un proyecto
 * terminado se marca en rojo (para no asignarle horas nuevas por error) y un responsable borrado no se pinta como si
 * existiera. El guardado exige nonce y capacidad.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Admin_Proyectos;
use Convoca\Members\CPT_Proyecto;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/admin/class-admin-proyectos.php';

/**
 * Proyectos.
 */
final class AdminProyectosTest extends TestCase {

	/**
	 * Listado.
	 *
	 * @var Admin_Proyectos
	 */
	private Admin_Proyectos $listado;

	/**
	 * Almacenes previos.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->previo = array(
			'posts'     => $GLOBALS['_wp_stores']['posts'] ?? array(),
			'post_meta' => $GLOBALS['_wp_stores']['post_meta'] ?? array(),
			'users'     => $GLOBALS['_wp_stores']['users'] ?? array(),
			'nonces'    => $GLOBALS['_wp_stores']['nonces_validos'] ?? null,
		);

		$GLOBALS['_wp_stores']['posts']          = array();
		$GLOBALS['_wp_stores']['post_meta']      = array();
		$GLOBALS['_wp_stores']['users']          = array();
		$GLOBALS['_wp_stores']['nonces_validos'] = array( 'bueno' );
		$GLOBALS['_wp_stub_next_id']             = 800;
		$GLOBALS['_wp_die_lanza']                = true;
		$GLOBALS['_test_caps']                   = array( 'gestionar_miembros' => true );
		$_POST                                   = array();
		$_REQUEST                                = array();
		$this->listado                           = new Admin_Proyectos();
	}

	/**
	 * Limpia.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']['posts']     = $this->previo['posts'];
		$GLOBALS['_wp_stores']['post_meta'] = $this->previo['post_meta'];
		$GLOBALS['_wp_stores']['users']     = $this->previo['users'];
		unset( $GLOBALS['_test_users'] );
		if ( null === $this->previo['nonces'] ) {
			unset( $GLOBALS['_wp_stores']['nonces_validos'] );
		} else {
			$GLOBALS['_wp_stores']['nonces_validos'] = $this->previo['nonces'];
		}
		unset( $GLOBALS['_wp_die_lanza'], $GLOBALS['_wp_stub_next_id'] );
		$_POST    = array();
		$_REQUEST = array();
	}

	/**
	 * Un proyecto con su meta.
	 *
	 * @param int   $id   Identificador.
	 * @param array $meta Meta.
	 * @return \WP_Post
	 */
	private function proyecto( int $id, array $meta = array() ): \WP_Post {
		$post              = new \WP_Post();
		$post->ID          = $id;
		$post->post_type   = CPT_Proyecto::POST_TYPE;
		$post->post_status = 'publish';
		$post->post_title  = 'Reforestación';

		$GLOBALS['_wp_stores']['posts'][ $id ]     = $post;
		$GLOBALS['_wp_stores']['post_meta'][ $id ] = $meta;

		return $post;
	}

	/**
	 * Seis columnas y tres ordenables.
	 */
	public function test_las_columnas(): void {
		$this->assertSame(
			array( 'cb', 'title', 'fecha_inicio', 'fecha_fin', 'responsable', 'activo' ),
			array_keys( $this->listado->get_columns() )
		);

		$metodo = new \ReflectionMethod( Admin_Proyectos::class, 'get_sortable_columns' );
		$this->assertSame( array( 'title', 'fecha_inicio', 'fecha_fin' ), array_keys( $metodo->invoke( $this->listado ) ) );
	}

	/**
	 * El título enlaza a su edición.
	 */
	public function test_la_columna_del_titulo(): void {
		$proyecto = $this->proyecto( 10 );

		$html = $this->listado->column_default( $proyecto, 'title' );
		$this->assertStringContainsString( 'Reforestación', $html );
		$this->assertStringContainsString( '<a href=', $html );
	}

	/**
	 * Un proyecto ya terminado se marca en rojo; uno en curso, no.
	 */
	public function test_la_fecha_de_fin_vencida(): void {
		$vencido = $this->proyecto( 20, array( '_convoca_fecha_inicio' => '2020-01-01', '_convoca_fecha_fin' => '2020-06-30' ) );
		$futuro  = $this->proyecto( 21, array( '_convoca_fecha_fin' => '2099-06-30' ) );
		$sin     = $this->proyecto( 22 );

		$this->assertStringContainsString( 'color:#d63638', $this->listado->column_default( $vencido, 'fecha_fin' ), 'vencido en rojo' );
		$this->assertStringNotContainsString( 'd63638', $this->listado->column_default( $futuro, 'fecha_fin' ) );
		$this->assertSame( '—', $this->listado->column_default( $sin, 'fecha_fin' ) );
		$this->assertSame( '2020-01-01', $this->listado->column_default( $vencido, 'fecha_inicio' ) );
		// Las dos fechas se pintan igual: sin dato, raya (members#69). Antes «Inicio» devolvía cadena vacía.
		$this->assertSame( '—', $this->listado->column_default( $sin, 'fecha_inicio' ), 'sin fecha, raya' );
	}

	/**
	 * El responsable se pinta con su nombre; si no existe, raya.
	 */
	public function test_el_responsable(): void {
		$usuario               = new \WP_User();
		$usuario->ID           = 9;
		$usuario->display_name = 'Ada Lovelace';
		$GLOBALS['_test_users'][9] = $usuario;

		$con = $this->proyecto( 30, array( '_convoca_responsable' => '9' ) );
		$sin = $this->proyecto( 31, array( '_convoca_responsable' => '999' ) );
		$no  = $this->proyecto( 32 );

		$this->assertSame( 'Ada Lovelace', $this->listado->column_default( $con, 'responsable' ) );
		$this->assertSame( '—', $this->listado->column_default( $sin, 'responsable' ), 'un usuario borrado no se inventa' );
		$this->assertSame( '—', $this->listado->column_default( $no, 'responsable' ) );
	}

	/**
	 * Activo solo cuando la meta es exactamente `1`.
	 */
	public function test_la_columna_activo(): void {
		$si = $this->proyecto( 40, array( '_convoca_activo' => '1' ) );
		$no = $this->proyecto( 41, array( '_convoca_activo' => '0' ) );

		$this->assertStringContainsString( '✓', $this->listado->column_default( $si, 'activo' ) );
		$this->assertStringNotContainsString( '✓', $this->listado->column_default( $no, 'activo' ) );
	}

	/**
	 * Una columna desconocida no pinta nada.
	 */
	public function test_una_columna_que_no_existe(): void {
		$this->assertSame( '', $this->listado->column_default( $this->proyecto( 50 ), 'inventada' ) );
	}

	/**
	 * La casilla lleva el id.
	 */
	public function test_la_casilla(): void {
		$html = $this->listado->column_cb( $this->proyecto( 60 ) );

		$this->assertStringContainsString( 'value="60"', $html );
		$this->assertStringContainsString( 'bulk-delete[]', $html );
	}

	/**
	 * Sin proyectos, el listado lo dice.
	 */
	public function test_sin_proyectos(): void {
		ob_start();
		try {
			$this->listado->no_items();
		} finally {
			$html = (string) ob_get_clean();
		}

		$this->assertStringContainsString( 'No se encontraron proyectos', $html );
	}

	/**
	 * Guardar sin nonce no toca nada.
	 */
	public function test_guardar_sin_nonce(): void {
		$GLOBALS['_wp_stores']['nonces_validos'] = array( 'otro' );
		$_POST = array( 'post_title' => 'Inventado' );

		$this->expectException( \RuntimeException::class );
		$this->listado->handle_save_admin();
	}

	/**
	 * Guardar sin capacidad corta con su aviso.
	 */
	public function test_guardar_sin_capacidad(): void {
		$GLOBALS['_test_caps']['gestionar_miembros'] = false;
		$_POST = array( 'post_title' => 'Inventado', '_wpnonce' => 'bueno' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/No tienes permisos/' );
		$this->listado->handle_save_admin();
	}

	/**
	 * Alta: título saneado, contenido filtrado y meta del proyecto.
	 */
	public function test_alta_de_proyecto(): void {
		$_POST = array(
			'post_title'   => '  Reforestación  ',
			'post_content' => '<p>Plantar</p><script>alert(1)</script>',
			'fecha_inicio' => '2026-01-01',
			'fecha_fin'    => '2026-12-31',
			'responsable'  => '9',
			'activo'       => '1',
			'_wpnonce'     => 'bueno',
		);

		try {
			$this->listado->handle_save_admin();
			$this->fail( 'debía redirigir' );
		} catch ( \RuntimeException $r ) {
			$this->assertStringContainsString( 'message=saved', $r->getMessage() );
		}

		$id = array_key_first( $GLOBALS['_wp_stores']['post_meta'] );
		$this->assertSame( 'Reforestación', $GLOBALS['_wp_stores']['posts'][ $id ]->post_title );
		$this->assertStringNotContainsString( '<script', $GLOBALS['_wp_stores']['posts'][ $id ]->post_content );
		$meta = $GLOBALS['_wp_stores']['post_meta'][ $id ];
		$this->assertSame( '2026-01-01', $meta['_convoca_fecha_inicio'] );
		$this->assertSame( 9, $meta['_convoca_responsable'], 'el responsable es un entero' );
		$this->assertSame( '1', $meta['_convoca_activo'] );
	}

	/**
	 * Sin marcar «activo», se guarda 0.
	 */
	public function test_alta_sin_activo(): void {
		$_POST = array(
			'post_title'   => 'Proyecto',
			'post_content' => '',
			'fecha_inicio' => '',
			'fecha_fin'    => '',
			'responsable'  => '0',
			'_wpnonce'     => 'bueno',
		);

		try {
			$this->listado->handle_save_admin();
		} catch ( \RuntimeException $e ) {
			// Redirección final.
		}

		$id = array_key_first( $GLOBALS['_wp_stores']['post_meta'] );
		$this->assertSame( '0', $GLOBALS['_wp_stores']['post_meta'][ $id ]['_convoca_activo'] );
	}
}
