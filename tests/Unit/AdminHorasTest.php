<?php
/**
 * El listado de horas de voluntariado: qué se pinta en cada celda y quién puede aprobar.
 *
 * La tabla es donde se aprueban horas que luego cuentan para el certificado, así que lo que se prueba es que cada
 * celda diga la verdad: una ficha borrada **no** se pinta como si existiera, sin tareas no se inventa texto, el estado
 * por defecto es `pendiente`, y el botón de aprobar solo aparece cuando hay algo que aprobar.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Admin_Horas;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/admin/class-admin-horas.php';

/**
 * Listado de horas.
 */
final class AdminHorasTest extends TestCase {

	/**
	 * Listado.
	 *
	 * @var Admin_Horas
	 */
	private Admin_Horas $listado;

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
		);

		$GLOBALS['_wp_stores']['posts']     = array();
		$GLOBALS['_wp_stores']['post_meta'] = array();
		$GLOBALS['_test_caps']              = array( 'gestionar_miembros' => true );
		// Como en producción: una guarda que falla CORTA. Sin esto el arnés sigue adelante y una prueba de permisos
		// acaba midiendo la redirección en vez del permiso (members#70). Misma convención que AdminProyectosTest,
		// MemberEditorTest, AdminPageAccionesTest y otros cuatro ficheros.
		$GLOBALS['_wp_die_lanza']           = true;
		$_GET                               = array();
		$_REQUEST                           = array();
		$this->listado                      = new Admin_Horas();
	}

	/**
	 * Limpia.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']['posts']     = $this->previo['posts'];
		$GLOBALS['_wp_stores']['post_meta'] = $this->previo['post_meta'];
		$_GET                               = array();
		$_REQUEST                           = array();
		unset( $GLOBALS['_wp_die_lanza'] );
	}

	/**
	 * Un registro de horas con su meta.
	 *
	 * @param int   $id   Identificador.
	 * @param array $meta Meta.
	 * @return \WP_Post
	 */
	private function registro( int $id, array $meta = array() ): \WP_Post {
		$post              = new \WP_Post();
		$post->ID          = $id;
		$post->post_type   = 'registro_hora';
		$post->post_status = 'publish';
		$post->post_content = 'Descripción de la tarea';

		$GLOBALS['_wp_stores']['posts'][ $id ]     = $post;
		$GLOBALS['_wp_stores']['post_meta'][ $id ] = $meta;

		return $post;
	}

	/**
	 * Una ficha con título, para las celdas que enlazan.
	 *
	 * @param int    $id    Identificador.
	 * @param string $tipo  Tipo de contenido.
	 * @param string $titulo Título.
	 * @return void
	 */
	private function ficha( int $id, string $tipo, string $titulo ): void {
		$post              = new \WP_Post();
		$post->ID          = $id;
		$post->post_type   = $tipo;
		$post->post_status = 'publish';
		$post->post_title  = $titulo;

		$GLOBALS['_wp_stores']['posts'][ $id ] = $post;
	}

	/**
	 * Diez columnas, con la casilla de selección.
	 */
	public function test_las_columnas(): void {
		$columnas = $this->listado->get_columns();

		$this->assertSame(
			array( 'cb', 'fecha', 'socio', 'proyecto', 'tareas', 'actividad', 'horas', 'descripcion', 'estado', 'acciones' ),
			array_keys( $columnas )
		);
	}

	/**
	 * La fecha ordena por defecto y descendente.
	 */
	public function test_las_columnas_ordenables(): void {
		$metodo = new \ReflectionMethod( Admin_Horas::class, 'get_sortable_columns' );

		$this->assertSame(
			array(
				'fecha' => array( 'fecha', true ),
				'socio' => array( 'socio', false ),
				'horas' => array( 'horas', false ),
			),
			$metodo->invoke( $this->listado )
		);
	}

	/**
	 * La fecha sale de su meta y, si no está, la del registro.
	 */
	public function test_la_fecha(): void {
		$con_meta = $this->registro( 10, array( '_convoca_fecha' => '2026-10-01' ) );
		$sin_meta = $this->registro( 11 );
		$sin_meta->post_date = '2026-09-15 10:00:00';

		$this->assertSame( '2026-10-01', $this->listado->column_fecha( $con_meta ) );
		$this->assertStringContainsString( '2026-09-15', (string) $this->listado->column_fecha( $sin_meta ), 'sin meta, la del registro' );
	}

	/**
	 * Sin socio, una raya; con la ficha borrada, se dice; con ficha, enlace.
	 */
	public function test_la_celda_del_socio(): void {
		$sin_socio   = $this->registro( 20 );
		$socio_borrado = $this->registro( 21, array( '_convoca_member_id' => '999' ) );
		$con_socio   = $this->registro( 22, array( '_convoca_member_id' => '55' ) );
		$this->ficha( 55, 'miembro', 'Ada Lovelace' );

		$this->assertSame( '—', $this->listado->column_socio( $sin_socio ) );
		$this->assertSame( 'Socio eliminado', $this->listado->column_socio( $socio_borrado ) );

		$html = $this->listado->column_socio( $con_socio );
		$this->assertStringContainsString( 'Ada Lovelace', $html );
		$this->assertStringContainsString( '<a href=', $html, 'con ficha se enlaza a su edición' );
	}

	/**
	 * Sin actividad es «General/Otros»; con la actividad borrada, se dice.
	 */
	public function test_la_celda_de_la_actividad(): void {
		$sin    = $this->registro( 30 );
		$roto   = $this->registro( 31, array( '_convoca_actividad_id' => '999' ) );
		$con    = $this->registro( 32, array( '_convoca_actividad_id' => '88' ) );
		$this->ficha( 88, 'actividad', 'Limpieza del río' );

		$this->assertSame( 'General/Otros', $this->listado->column_actividad( $sin ) );
		$this->assertSame( 'Actividad eliminada', $this->listado->column_actividad( $roto ) );
		$this->assertStringContainsString( 'Limpieza del río', $this->listado->column_actividad( $con ) );
	}

	/**
	 * El proyecto: raya, «eliminado» o su título.
	 */
	public function test_la_celda_del_proyecto(): void {
		$sin  = $this->registro( 40 );
		$roto = $this->registro( 41, array( '_convoca_proyecto_id' => '999' ) );
		$con  = $this->registro( 42, array( '_convoca_proyecto_id' => '77' ) );
		$this->ficha( 77, 'proyecto', 'Reforestación' );

		$this->assertSame( '—', $this->listado->column_proyecto( $sin ) );
		$this->assertSame( 'Proyecto eliminado', $this->listado->column_proyecto( $roto ) );
		$this->assertSame( 'Reforestación', $this->listado->column_proyecto( $con ) );
	}

	/**
	 * Las tareas se recortan a 60 caracteres y se avisa con puntos suspensivos.
	 */
	public function test_las_tareas_se_recortan(): void {
		$sin  = $this->registro( 50 );
		$corta = $this->registro( 51, array( '_convoca_tareas' => 'Barrer' ) );
		$larga = $this->registro( 52, array( '_convoca_tareas' => str_repeat( 'a', 80 ) ) );

		$this->assertSame( '—', $this->listado->column_tareas( $sin ) );
		$this->assertSame( 'Barrer', $this->listado->column_tareas( $corta ) );
		$this->assertSame( str_repeat( 'a', 60 ) . '...', $this->listado->column_tareas( $larga ) );
	}

	/**
	 * Las horas llevan su unidad y el estado sale capitalizado, `pendiente` por defecto.
	 */
	public function test_horas_y_estado(): void {
		$registro = $this->registro( 60, array( '_convoca_horas' => '4' ) );

		$this->assertSame( '4h', $this->listado->column_horas( $registro ) );
		$this->assertSame( '<span class="status-pendiente">Pendiente</span>', $this->listado->column_estado( $registro ) );

		$GLOBALS['_wp_stores']['post_meta'][60]['_convoca_estado'] = 'aprobada';
		$this->assertStringContainsString( 'status-aprobada', $this->listado->column_estado( $registro ) );
	}

	/**
	 * Las acciones dependen del estado: no se aprueba lo aprobado ni se rechaza lo rechazado.
	 */
	public function test_las_acciones_segun_el_estado(): void {
		$pendiente = $this->registro( 70 );
		$aprobada  = $this->registro( 71, array( '_convoca_estado' => 'aprobada' ) );
		$rechazada = $this->registro( 72, array( '_convoca_estado' => 'rechazada' ) );

		$html = $this->listado->column_acciones( $pendiente );
		$this->assertStringContainsString( 'Aprobar', $html );
		$this->assertStringContainsString( 'Rechazar', $html );
		$this->assertStringContainsString( '_wpnonce', $html, 'cada acción lleva su nonce' );

		$html = $this->listado->column_acciones( $aprobada );
		$this->assertStringNotContainsString( 'Aprobar', $html );
		$this->assertStringContainsString( 'Rechazar', $html );

		$html = $this->listado->column_acciones( $rechazada );
		$this->assertStringContainsString( 'Aprobar', $html );
		$this->assertStringNotContainsString( 'Rechazar', $html );
	}

	/**
	 * Aprobar exige capacidad: sin ella, corta antes de tocar nada.
	 */
	public function test_aprobar_sin_capacidad_muere(): void {
		$GLOBALS['_test_caps']['gestionar_miembros'] = false;
		$_GET = array( 'record_id' => '70', 'new_status' => 'aprobada' );

		// El corte está activo (ver `setUp`) y se exige el MENSAJE: antes esta prueba pasaba porque el doble de
		// `wp_safe_redirect` también lanza RuntimeException, así que medía la redirección y no el permiso.
		try {
			$this->listado->process_action();
			$this->fail( 'sin capacidad no puede seguir: no cortó' );
		} catch ( \RuntimeException $e ) {
			$this->assertStringContainsString( 'permisos', strtolower( $e->getMessage() ), 'el corte es por permisos' );
		}
	}
}
