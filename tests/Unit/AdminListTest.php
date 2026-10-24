<?php
/**
 * El listado de socios: qué dice cada celda.
 *
 * Es la pantalla donde la junta cuenta socios y decide a quién cobra, así que una celda que mienta tiene consecuencias
 * (un recibo de más, una ficha sin número, un teléfono que no llama). Se prueba celda a celda lo que no depende de
 * consultas: el número con su relleno, el correo y el teléfono como enlaces, el WhatsApp, el plan, la cuota con su
 * estado, la renovación con pago y la fecha.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Admin_List;
use Convoca\Members\CPT_Miembro;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/admin/class-admin-list.php';

/**
 * Listado de socios.
 */
final class AdminListTest extends TestCase {

	/**
	 * Listado.
	 *
	 * @var Admin_List
	 */
	private Admin_List $listado;

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
			'options'   => $GLOBALS['_wp_stores']['options'] ?? array(),
		);

		$GLOBALS['_wp_stores']['posts']     = array();
		$GLOBALS['_wp_stores']['post_meta'] = array();
		$GLOBALS['_test_caps']              = array( 'convoca_export_members' => true );
		$this->listado                      = new Admin_List();
	}

	/**
	 * Limpia.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']['posts']     = $this->previo['posts'];
		$GLOBALS['_wp_stores']['post_meta'] = $this->previo['post_meta'];
		$GLOBALS['_wp_stores']['options']   = $this->previo['options'];
	}

	/**
	 * Una ficha con su meta.
	 *
	 * @param int    $id     Identificador.
	 * @param array  $meta   Meta.
	 * @param string $titulo Título.
	 * @return \WP_Post
	 */
	private function ficha( int $id, array $meta = array(), string $titulo = 'Ada Lovelace' ): \WP_Post {
		$post              = new \WP_Post();
		$post->ID          = $id;
		$post->post_type   = 'miembro';
		$post->post_status = 'publish';
		$post->post_title  = $titulo;

		$GLOBALS['_wp_stores']['posts'][ $id ]     = $post;
		$GLOBALS['_wp_stores']['post_meta'][ $id ] = $meta;

		return $post;
	}

	/**
	 * Catorce columnas y dos acciones de lote.
	 */
	public function test_las_columnas_y_el_lote(): void {
		$this->assertSame(
			array( 'cb', 'numero', 'nombre', 'email', 'telefono', 'whatsapp', 'tipo', 'estado', 'consentimiento',
				'renueva_horas', 'plan', 'cuota', 'recurrente', 'fecha' ),
			array_keys( $this->listado->get_columns() )
		);
		$this->assertSame( array( 'bulk-baja' ), array_keys( $this->listado->get_bulk_actions() ) );
	}

	/**
	 * Nombre ordena por título y fecha por fecha.
	 */
	public function test_las_ordenables(): void {
		$this->assertSame(
			array(
				'nombre' => array( 'title', true ),
				'fecha'  => array( 'date', false ),
			),
			$this->listado->get_sortable_columns()
		);
	}

	/**
	 * Una celda sin columna propia lee su meta; si no hay, raya.
	 */
	public function test_la_celda_generica(): void {
		$ficha = $this->ficha( 10, array( '_convoca_municipio' => 'Oviedo' ) );

		$this->assertSame( 'Oviedo', $this->listado->column_default( $ficha, 'municipio' ) );
		$this->assertSame( '—', $this->listado->column_default( $ficha, 'direccion' ) );
	}

	/**
	 * El número sale relleno a cuatro cifras; sin número lo dice y, si procede, ofrece arreglarlo.
	 */
	public function test_el_numero(): void {
		$con      = $this->ficha( 20, array( '_convoca_numero_socio' => '7' ) );
		$sin      = $this->ficha( 21 );
		$activo   = $this->ficha( 22, array( '_convoca_estado_miembro' => 'activo' ) );

		$this->assertSame( '<strong>0007</strong>', $this->listado->column_numero( $con ) );

		$html = $this->listado->column_numero( $sin );
		$this->assertStringContainsString( 'Sin asignar', $html );
		$this->assertStringNotContainsString( 'admin-post.php', $html, 'sin estar activo no se ofrece reparar' );

		$html = $this->listado->column_numero( $activo );
		$this->assertStringContainsString( 'admin-post.php?action=convoca_approve_member', $html, 'activo sin número: se ofrece arreglarlo' );
		$this->assertStringContainsString( '_wpnonce', $html );
	}

	/**
	 * Sin capacidad de exportar no se ofrece el botón de reparar el número.
	 */
	public function test_el_numero_no_se_repara_sin_capacidad(): void {
		$GLOBALS['_test_caps']['convoca_export_members'] = false;
		$activo = $this->ficha( 23, array( '_convoca_estado_miembro' => 'activo' ) );

		$this->assertStringNotContainsString( 'admin-post.php', $this->listado->column_numero( $activo ) );
	}

	/**
	 * Correo y teléfono: enlace si hay dato, raya si no.
	 */
	public function test_correo_y_telefono(): void {
		$con = $this->ficha( 30, array( '_convoca_email' => 'ada@example.test', '_convoca_telefono' => '600000000' ) );
		$sin = $this->ficha( 31 );

		$this->assertSame( '<a href="mailto:ada@example.test">ada@example.test</a>', $this->listado->column_email( $con ) );
		$this->assertSame( '<a href="tel:600000000">600000000</a>', $this->listado->column_telefono( $con ) );
		$this->assertSame( '—', $this->listado->column_email( $sin ) );
		$this->assertSame( '—', $this->listado->column_telefono( $sin ) );
	}

	/**
	 * WhatsApp: «No» cuando lo ha dicho, raya cuando no consta.
	 */
	public function test_whatsapp(): void {
		$no     = $this->ficha( 40, array( '_convoca_whatsapp' => 'no' ) );
		$sin    = $this->ficha( 41 );

		$this->assertStringContainsString( 'No', $this->listado->column_whatsapp( $no ) );
		$this->assertSame( '—', $this->listado->column_whatsapp( $sin ) );
	}

	/**
	 * La cuota lleva importe y su estado; un estado desconocido no pinta insignia.
	 */
	public function test_la_cuota(): void {
		$GLOBALS['_wp_stores']['options']['convoca_members_plans'] = array();

		$activa = $this->ficha(
			50,
			array(
				'_convoca_importe_cuota' => '30',
				'_convoca_estado_cuota'  => 'activa',
			)
		);
		$sin = $this->ficha( 51 );

		$html = $this->listado->column_cuota( $activa );
		$this->assertStringContainsString( '30€', $html );
		$this->assertStringContainsString( 'convoca-badge--confirmed', $html );

		$this->assertSame( '— —', $this->listado->column_cuota( $sin ), 'sin datos no se inventa importe ni estado' );
	}

	/**
	 * La renovación con pago: sí o no, siempre con su icono.
	 */
	public function test_la_renovacion_con_pago(): void {
		$si  = $this->ficha( 60, array( '_convoca_pago_recurrente' => '1' ) );
		$no  = $this->ficha( 61 );

		$this->assertStringContainsString( 'dashicons-yes', $this->listado->column_recurrente( $si ) );
		$this->assertStringContainsString( 'dashicons-no-alt', $this->listado->column_recurrente( $no ) );
	}

	/**
	 * El plan sale con su etiqueta; si no hay plan conocido, con su clave o raya.
	 */
	public function test_el_plan(): void {
		$GLOBALS['_wp_stores']['options']['convoca_members_plans'] = array(
			'numerario' => array( 'label' => 'Numerario' ),
		);
		$GLOBALS['_wp_stores']['options']['convoca_members_settings'] = array();

		$con  = $this->ficha( 70, array( '_convoca_plan' => 'numerario' ) );
		$sub  = $this->ficha( 71, array( '_convoca_plan' => 'numerario', '_convoca_sub_plan' => 'juvenil' ) );
		$sin  = $this->ficha( 72 );

		$this->assertSame( 'Numerario', $this->listado->column_plan( $con ) );
		$this->assertSame( 'Juvenil', $this->listado->column_plan( $sub ), 'el subplan manda' );
		$this->assertSame( '—', $this->listado->column_plan( $sin ) );
	}

	/**
	 * La fecha se pinta en formato español.
	 */
	public function test_la_fecha(): void {
		$ficha             = $this->ficha( 80 );
		$ficha->post_date  = '2026-03-09 12:00:00';

		$this->assertSame( '09/03/2026', $this->listado->column_fecha( $ficha ) );
	}

	/**
	 * La casilla lleva el id y dice a quién selecciona.
	 */
	public function test_la_casilla(): void {
		$ficha = $this->ficha( 90, array(), 'Grace Hopper' );
		$html  = $this->listado->column_cb( $ficha );

		$this->assertStringContainsString( 'name="miembros[]"', $html );
		$this->assertStringContainsString( 'value="90"', $html );
		$this->assertStringContainsString( 'Grace Hopper', $html, 'la casilla se anuncia con el nombre' );
	}

	/**
	 * La papelera no se ofrece: dar de baja conserva la ficha.
	 */
	public function test_sin_papelera(): void {
		$socio  = $this->ficha( 100 );
		$otro   = new \WP_Post();
		$otro->post_type = 'post';

		$this->assertArrayNotHasKey( 'trash', $this->listado->sin_papelera( array( 'edit' => 'x', 'trash' => 'y' ), $socio ) );
		$this->assertArrayHasKey( 'trash', $this->listado->sin_papelera( array( 'trash' => 'y' ), $otro ), 'en otros tipos se respeta' );
		$this->assertSame( array(), $this->listado->sin_papelera_bulk( array( 'trash' => 'y' ) ) );
	}
}
