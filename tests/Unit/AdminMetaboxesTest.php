<?php
/**
 * La ficha del socio en el panel: guardado, cambio de estado y acciones AJAX.
 *
 * Aquí se toca dinero y datos personales: el guardado escribe 17 campos, el cambio de estado puede asignar número de
 * socio, y las acciones AJAX generan enlaces de pago y exportan o borran los datos de una persona. Lo que se prueba es
 * que **sin nonce o sin permiso no se ejecute nada**, y que cada negativa diga por qué.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Admin_Metaboxes;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/admin/class-admin-metaboxes.php';

/**
 * Metaboxes de la ficha.
 */
final class AdminMetaboxesTest extends TestCase {

	/**
	 * Metaboxes.
	 *
	 * @var Admin_Metaboxes
	 */
	private Admin_Metaboxes $caja;

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
			'json'      => $GLOBALS['_wp_json'] ?? array(),
		);

		$GLOBALS['_wp_stores']['posts']     = array();
		$GLOBALS['_wp_stores']['post_meta'] = array();
		$GLOBALS['_wp_json']                = array();
		$GLOBALS['_test_caps']              = array();
		$GLOBALS['_wp_stores']['nonces_validos'] = array( 'bueno' );
		$_POST                              = array();
		$_REQUEST                           = array();
		$this->caja                         = new Admin_Metaboxes();
	}

	/**
	 * Limpia.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']['posts']     = $this->previo['posts'];
		$GLOBALS['_wp_stores']['post_meta'] = $this->previo['post_meta'];
		$GLOBALS['_wp_stores']['options']   = $this->previo['options'];
		$GLOBALS['_wp_json']                = $this->previo['json'];
		unset( $GLOBALS['_wp_stores']['nonces_validos'] );
		$_POST    = array();
		$_REQUEST = array();
	}

	/**
	 * Sin nonce, el guardado no toca nada.
	 */
	public function test_sin_nonce_no_guarda(): void {
		$_POST = array( 'convoca_email' => 'ada@example.test' );

		$this->caja->save_metaboxes( 55 );

		$this->assertArrayNotHasKey( 'post_meta', array_filter( $GLOBALS['_wp_stores']['post_meta'] ) );
		$this->assertSame( array(), $GLOBALS['_wp_stores']['post_meta'], 'no se escribe ni un campo' );
	}

	/**
	 * Nonce inválido, tampoco.
	 */
	public function test_nonce_invalido_no_guarda(): void {
		$GLOBALS['_wp_stores']['nonces_validos'] = array( 'otro' );
		$_POST = array(
			'convoca_miembro_nonce' => 'malo',
			'convoca_email'         => 'ada@example.test',
		);

		$this->caja->save_metaboxes( 55 );

		$this->assertSame( array(), $GLOBALS['_wp_stores']['post_meta'] );
	}

	/**
	 * Con nonce válido pero sin permiso sobre la ficha, tampoco.
	 */
	public function test_sin_permiso_no_guarda(): void {
		$GLOBALS['_test_caps']['edit_post'] = false;
		$_POST = array(
			'convoca_miembro_nonce' => 'bueno',
			'convoca_email'         => 'ada@example.test',
		);

		$this->caja->save_metaboxes( 55 );

		$this->assertSame( array(), $GLOBALS['_wp_stores']['post_meta'] );
	}

	/**
	 * Con todo en regla: los campos se guardan, los interruptores como 1/0 y los ausentes vacíos.
	 */
	public function test_guarda_los_campos(): void {
		$_POST = array(
			'convoca_miembro_nonce' => 'bueno',
			'convoca_plan'          => 'numerario',
			'convoca_email'         => '  ada@example.test  ',
			'convoca_telefono'      => '600000000',
			'convoca_pago_recurrente' => '1',
		);

		$this->caja->save_metaboxes( 55 );

		$meta = $GLOBALS['_wp_stores']['post_meta'][55];
		$this->assertSame( 'numerario', $meta['_convoca_plan'] );
		$this->assertSame( 'ada@example.test', $meta['_convoca_email'], 'sin espacios alrededor' );
		$this->assertSame( '600000000', $meta['_convoca_telefono'] );
		$this->assertSame( '1', $meta['_convoca_pago_recurrente'], 'marcado es 1' );
		$this->assertSame( '0', $meta['_convoca_es_voluntario'], 'no marcado es 0' );
		$this->assertSame( '', $meta['_convoca_dni'], 'un campo ausente se guarda vacío' );
	}

	/**
	 * Un cambio de estado distinto del actual pasa por la máquina de estados.
	 */
	public function test_el_cambio_de_estado(): void {
		$GLOBALS['_wp_stores']['post_meta'][55] = array( '_convoca_estado_miembro' => 'pendiente_pago' );
		$_POST = array(
			'convoca_miembro_nonce'   => 'bueno',
			'convoca_estado_miembro'  => 'suspendido',
		);

		$this->caja->save_metaboxes( 55 );

		$this->assertSame( 'suspendido', $GLOBALS['_wp_stores']['post_meta'][55]['_convoca_estado_miembro'] );
	}

	/**
	 * El mismo estado no dispara nada.
	 */
	public function test_el_mismo_estado_no_cambia_nada(): void {
		$GLOBALS['_wp_stores']['post_meta'][55] = array(
			'_convoca_estado_miembro' => 'activo',
			'_convoca_numero_socio'   => '0042',
		);
		$_POST = array(
			'convoca_miembro_nonce'  => 'bueno',
			'convoca_estado_miembro' => 'activo',
		);

		$this->caja->save_metaboxes( 55 );

		$this->assertSame( '0042', $GLOBALS['_wp_stores']['post_meta'][55]['_convoca_numero_socio'] );
	}

	// ── las acciones AJAX ──────────────────────────────────────────────────────────

	/**
	 * Sin permiso sobre la ficha, la acción AJAX se corta antes de nada.
	 */
	public function test_ajax_sin_permiso(): void {
		$GLOBALS['_test_caps']['edit_post'] = false;
		$_POST = array( 'post_id' => '55', 'nonce' => 'bueno' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/Sin permisos sobre este registro/' );
		$this->caja->ajax_send_reminder();
	}

	/**
	 * Sin nonce, también.
	 */
	public function test_ajax_sin_nonce(): void {
		$GLOBALS['_wp_stores']['nonces_validos'] = array( 'otro' );
		$_POST = array( 'post_id' => '55', 'nonce' => 'malo' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/Nonce inválido/' );
		$this->caja->ajax_send_reminder();
	}

	/**
	 * Recordatorio sin pago activo: se dice qué falta, no se manda un enlace roto.
	 */
	public function test_recordatorio_sin_pago(): void {
		$_POST = array( 'post_id' => '55', 'nonce' => 'bueno' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/No hay un pago activo/' );
		$this->caja->ajax_send_reminder();
	}

	/**
	 * Enlace de pago sin plan: no se puede cobrar lo que no está definido.
	 */
	public function test_enlace_sin_plan(): void {
		$GLOBALS['_wp_stores']['options']['convoca_members_plans'] = array();
		$_POST = array( 'post_id' => '55', 'nonce' => 'bueno' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/Plan no válido/' );
		$this->caja->ajax_send_payment_link();
	}

	/**
	 * Un plan sin coste no genera enlace.
	 */
	public function test_enlace_con_plan_gratis(): void {
		$GLOBALS['_wp_stores']['options']['convoca_members_plans'] = array(
			'honorifico' => array( 'label' => 'Honorífico', 'price' => 0 ),
		);
		$GLOBALS['_wp_stores']['post_meta'][55] = array( '_convoca_plan' => 'honorifico' );
		$_POST = array( 'post_id' => '55', 'nonce' => 'bueno' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/no tiene coste económico/' );
		$this->caja->ajax_send_payment_link();
	}

	/**
	 * Borrar los datos de una persona exige `manage_options`: no basta con editar la ficha.
	 */
	public function test_borrado_de_datos_sin_capacidad(): void {
		$GLOBALS['_test_caps']['manage_options'] = false;
		$_POST = array( 'post_id' => '55', 'nonce' => 'bueno' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/Sin permisos/' );
		$this->caja->ajax_delete_member_data();
	}
}
