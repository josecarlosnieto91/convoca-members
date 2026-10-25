<?php
/**
 * Las columnas del listado de horas.
 *
 * Son presentación, y precisamente por eso se rompen sin que nadie lo note: una columna que devuelve una cadena sin
 * escapar no da error, no falla ninguna prueba de hoy y solo se ve cuando alguien mira el HTML. Aquí se mide lo que
 * cada columna **pinta**, incluidos los casos que casi nunca se dan (socio borrado, sin proyecto, sin tareas).
 *
 * El caso que motivó esta tanda: `column_estado()` metía el estado en el HTML sin escapar, y el estado llega de un
 * campo de texto que `sanitize_text_field()` deja con sus comillas. Medido: `<span class="status-x"
 * onclick="alert(1)">` — un atributo inyectado (members#75).
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Admin_Horas;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/admin/class-admin-horas.php';

/**
 * Columnas.
 */
final class ColumnasDeHorasTest extends TestCase {

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
	 * Monta el escenario.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->previo = array(
			'posts' => $GLOBALS['_wp_stores']['posts'] ?? array(),
			'meta'  => $GLOBALS['_wp_stores']['post_meta'] ?? array(),
		);

		$GLOBALS['_wp_stores']['posts']     = array();
		$GLOBALS['_wp_stores']['post_meta'] = array();

		$this->pantalla = ( new \ReflectionClass( Admin_Horas::class ) )->newInstanceWithoutConstructor();
	}

	/**
	 * Devuelve el escenario.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']['posts']     = $this->previo['posts'];
		$GLOBALS['_wp_stores']['post_meta'] = $this->previo['meta'];
	}

	/**
	 * Crea un registro con sus metas y devuelve el objeto-fila.
	 *
	 * @param array $meta Metas.
	 * @param int   $id   ID.
	 * @return object
	 */
	private function fila( array $meta, int $id = 700 ): object {
		$GLOBALS['_wp_stores']['posts'][ $id ] = (object) array(
			'ID'           => $id,
			'post_type'    => 'registro_hora',
			'post_status'  => 'publish',
			'post_title'   => 'Registro ' . $id,
			'post_content' => 'Lo que se hizo',
		);
		$GLOBALS['_wp_stores']['post_meta'][ $id ] = $meta;

		return $GLOBALS['_wp_stores']['posts'][ $id ];
	}

	/**
	 * Pinta una columna.
	 *
	 * @param string $columna Nombre.
	 * @param object $fila    Fila.
	 * @return string
	 */
	private function pintar( string $columna, object $fila ): string {
		return (string) $this->pantalla->$columna( $fila );
	}

	/**
	 * **El estado no puede inyectar atributos.**
	 *
	 * El valor que se guarda pasa por `sanitize_text_field()`, que quita etiquetas pero **no comillas**: con
	 * `x" onclick="alert(1)` el `"` cerraba el `class` y abría un atributo nuevo. Es XSS almacenado de quien tiene
	 * permiso de gestión contra quien administra.
	 */
	public function test_el_estado_no_puede_inyectar_atributos(): void {
		$fila = $this->fila( array( '_convoca_estado' => 'x" onclick="alert(1)' ) );

		$html = $this->pintar( 'column_estado', $fila );

		$this->assertStringNotContainsString( 'onclick', $html, 'no se cuela ningún atributo' );
		$this->assertStringNotContainsString( 'alert', $html, 'ni código' );
		$this->assertStringNotContainsString( '"x', $html );
	}

	/**
	 * Un estado que no existe se pinta como **pendiente**: es el estado por defecto del producto y el único que tiene
	 * estilo en el listado. Sin esto, una fila con un estado raro sale sin color y nadie sabe por qué.
	 */
	public function test_un_estado_desconocido_se_pinta_como_pendiente(): void {
		$fila = $this->fila( array( '_convoca_estado' => 'inventado' ) );

		$html = $this->pintar( 'column_estado', $fila );

		$this->assertStringContainsString( 'status-pendiente', $html );
		$this->assertStringContainsString( 'Pendiente', $html );
		$this->assertStringNotContainsString( 'status-inventado', $html, 'no se inventa una clase CSS' );
	}

	/**
	 * Los tres estados de verdad se pintan con su clase y su etiqueta.
	 */
	public function test_los_tres_estados_se_pintan_bien(): void {
		$casos = array(
			'pendiente' => 'Pendiente',
			'aprobada'  => 'Aprobada',
			'rechazada' => 'Rechazada',
		);

		foreach ( $casos as $estado => $etiqueta ) {
			$fila = $this->fila( array( '_convoca_estado' => $estado ), 700 + strlen( $estado ) );
			$html = $this->pintar( 'column_estado', $fila );

			$this->assertStringContainsString( 'status-' . $estado, $html, "clase de {$estado}" );
			$this->assertStringContainsString( $etiqueta, $html, "etiqueta de {$estado}" );
		}
	}

	/**
	 * Sin la meta, el estado es pendiente.
	 */
	public function test_sin_la_meta_el_estado_es_pendiente(): void {
		$fila = $this->fila( array() );

		$html = $this->pintar( 'column_estado', $fila );

		$this->assertStringContainsString( 'status-pendiente', $html );
	}

	/**
	 * Un registro sin socio no inventa un enlace: pinta una raya.
	 */
	public function test_sin_socio_pinta_una_raya(): void {
		$fila = $this->fila( array() );

		$this->assertSame( '—', $this->pintar( 'column_socio', $fila ) );
	}

	/**
	 * Un socio borrado se dice, no se pinta vacío.
	 */
	public function test_un_socio_borrado_se_dice(): void {
		$fila = $this->fila( array( '_convoca_member_id' => 999 ) );

		$this->assertSame( 'Socio eliminado', $this->pintar( 'column_socio', $fila ) );
	}

	/**
	 * Con socio y enlace, sale un enlace con su nombre escapado.
	 */
	public function test_con_socio_y_enlace_sale_el_enlace(): void {
		$GLOBALS['_wp_stores']['posts'][500] = (object) array(
			'ID'          => 500,
			'post_type'   => 'miembro',
			'post_status' => 'publish',
			'post_title'  => 'Ada <b>Lovelace</b>',
		);
		$fila = $this->fila( array( '_convoca_member_id' => 500 ) );

		$html = $this->pintar( 'column_socio', $fila );

		$this->assertStringContainsString( 'Ada', $html );
		$this->assertStringNotContainsString( '<b>', $html, 'el nombre va escapado' );
	}

	/**
	 * Sin proyecto no hay enlace ni texto inventado.
	 */
	public function test_sin_proyecto_pinta_una_raya(): void {
		$fila = $this->fila( array() );

		$this->assertSame( '—', $this->pintar( 'column_proyecto', $fila ) );
	}

	/**
	 * Un proyecto borrado se dice.
	 */
	public function test_un_proyecto_borrado_se_dice(): void {
		$fila = $this->fila( array( '_convoca_proyecto_id' => 888 ) );

		$this->assertSame( 'Proyecto eliminado', $this->pintar( 'column_proyecto', $fila ) );
	}

	/**
	 * Sin actividad se dice «General/Otros», que es como se agrupan las horas sin actividad.
	 */
	public function test_sin_actividad_pinta_general_otros(): void {
		$fila = $this->fila( array() );

		$this->assertSame( 'General/Otros', $this->pintar( 'column_actividad', $fila ) );
	}

	/**
	 * Una actividad borrada se dice.
	 */
	public function test_una_actividad_borrada_se_dice(): void {
		$fila = $this->fila( array( '_convoca_actividad_id' => 777 ) );

		$this->assertSame( 'Actividad eliminada', $this->pintar( 'column_actividad', $fila ) );
	}

	/**
	 * Las tareas se recortan a 60 caracteres **y se avisa con puntos suspensivos**.
	 *
	 * Sin el aviso, quien lee el listado cree que la descripción acaba ahí.
	 */
	public function test_las_tareas_largas_se_recortan_con_puntos_suspensivos(): void {
		$largas = str_repeat( 'a', 80 );
		$fila   = $this->fila( array( '_convoca_tareas' => $largas ) );

		$html = $this->pintar( 'column_tareas', $fila );

		$this->assertStringContainsString( str_repeat( 'a', 60 ), $html );
		$this->assertStringContainsString( '...', $html, 'se avisa del recorte' );
		$this->assertStringNotContainsString( $largas, $html, 'no sale entera' );
	}

	/**
	 * Una tarea corta sale entera y **sin** puntos suspensivos.
	 */
	public function test_una_tarea_corta_sale_entera_sin_puntos(): void {
		$fila = $this->fila( array( '_convoca_tareas' => 'Reparto de lotes' ) );

		$html = $this->pintar( 'column_tareas', $fila );

		$this->assertStringContainsString( 'Reparto de lotes', $html );
		$this->assertStringNotContainsString( '...', $html );
	}

	/**
	 * Sin tareas, raya.
	 */
	public function test_sin_tareas_pinta_una_raya(): void {
		$fila = $this->fila( array() );

		$this->assertSame( '—', $this->pintar( 'column_tareas', $fila ) );
	}

	/**
	 * Las horas salen con su unidad, y **escapadas**.
	 */
	public function test_las_horas_salen_con_su_unidad(): void {
		$fila = $this->fila( array( '_convoca_horas' => '6.5' ) );

		$this->assertSame( '6.5h', $this->pintar( 'column_horas', $fila ) );
	}

	/**
	 * La descripción va escapada: es texto libre que escribe el socio.
	 */
	public function test_la_descripcion_va_escapada(): void {
		$GLOBALS['_wp_stores']['posts'][700] = (object) array(
			'ID'           => 700,
			'post_type'    => 'registro_hora',
			'post_status'  => 'publish',
			'post_title'   => 'Registro',
			'post_content' => 'Puse <script>alert(1)</script>',
		);
		$GLOBALS['_wp_stores']['post_meta'][700] = array();

		$html = $this->pintar( 'column_descripcion', $GLOBALS['_wp_stores']['posts'][700] );

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( 'script', $html, 'el texto se ve, la etiqueta no se ejecuta' );
	}

	/**
	 * **Los botones llevan nonce.**
	 *
	 * Sin nonce, aprobar sería un enlace que cualquiera puede provocar (CSRF) y las horas quedarían aprobadas por
	 * visitar una página.
	 */
	public function test_los_botones_llevan_nonce(): void {
		$fila = $this->fila( array( '_convoca_estado' => 'pendiente' ) );

		$html = $this->pintar( 'column_acciones', $fila );

		// **Cada** enlace, no «alguno»: con dos botones, que uno lleve nonce y el otro no pasaría una comprobación
		// de «contiene _wpnonce=».
		$this->assertSame( 2, substr_count( $html, '_wpnonce=' ), 'los dos enlaces llevan nonce' );
		$this->assertStringContainsString( 'Aprobar', $html );
		$this->assertStringContainsString( 'Rechazar', $html );
	}

	/**
	 * Un registro **ya aprobado** no ofrece «Aprobar» (pero sí «Rechazar»: se puede rectificar).
	 */
	public function test_un_registro_aprobado_no_ofrece_aprobar(): void {
		$fila = $this->fila( array( '_convoca_estado' => 'aprobada' ) );

		$html = $this->pintar( 'column_acciones', $fila );

		$this->assertStringNotContainsString( 'Aprobar', $html, 'no se puede aprobar dos veces desde aquí' );
		$this->assertStringContainsString( 'Rechazar', $html );
	}

	/**
	 * Y uno **rechazado** no ofrece «Rechazar» (pero sí «Aprobar»).
	 */
	public function test_un_registro_rechazado_no_ofrece_rechazar(): void {
		$fila = $this->fila( array( '_convoca_estado' => 'rechazada' ) );

		$html = $this->pintar( 'column_acciones', $fila );

		$this->assertStringNotContainsString( 'Rechazar', $html );
		$this->assertStringContainsString( 'Aprobar', $html );
	}
}
