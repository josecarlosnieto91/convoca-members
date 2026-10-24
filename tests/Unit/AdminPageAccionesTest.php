<?php
/**
 * Las acciones del panel de socios: cambio rápido de estado, contacto por WhatsApp y widget.
 *
 * El cambio de estado mueve a una persona de «activo» a «baja» y con eso cambia quién cuenta como socio; el registro de
 * WhatsApp deja constancia de a quién se ha contactado. Lo que se prueba: sin nonce o sin permiso **no se ejecuta
 * nada**, un estado inválido no se acepta, y el aviso de permisos no deja pasar.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Admin_Page;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/admin/class-admin-page.php';

/**
 * Acciones del panel.
 */
final class AdminPageAccionesTest extends TestCase {

	/**
	 * Página.
	 *
	 * @var Admin_Page
	 */
	private Admin_Page $pagina;

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
			'json'      => $GLOBALS['_wp_json'] ?? array(),
			'nonces'    => $GLOBALS['_wp_stores']['nonces_validos'] ?? null,
		);

		$GLOBALS['_wp_stores']['posts']          = array();
		$GLOBALS['_wp_stores']['post_meta']      = array();
		$GLOBALS['_wp_json']                     = array();
		$GLOBALS['_wp_stores']['nonces_validos'] = array( 'bueno' );
		$GLOBALS['_wp_die_lanza']                = true; // como en producción: una guarda que falla corta.
		$GLOBALS['_test_caps']                   = array( 'gestionar_miembros' => true, 'edit_post' => true );
		$_POST                                   = array();
		$_REQUEST                                = array();
		$this->pagina                            = new Admin_Page();
	}

	/**
	 * Limpia.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']['posts']     = $this->previo['posts'];
		$GLOBALS['_wp_stores']['post_meta'] = $this->previo['post_meta'];
		$GLOBALS['_wp_json']                = $this->previo['json'];
		$GLOBALS['_test_caps']              = array();
		if ( null === $this->previo['nonces'] ) {
			unset( $GLOBALS['_wp_stores']['nonces_validos'] );
		} else {
			$GLOBALS['_wp_stores']['nonces_validos'] = $this->previo['nonces'];
		}
		$_POST    = array();
		$_REQUEST = array();
		unset( $GLOBALS['_wp_die_lanza'] );
	}

	/**
	 * Una ficha de socio.
	 *
	 * @param int   $id   Identificador.
	 * @param array $meta Meta.
	 * @return \WP_Post
	 */
	private function ficha( int $id, array $meta = array() ): \WP_Post {
		$post              = new \WP_Post();
		$post->ID          = $id;
		$post->post_type   = 'miembro';
		$post->post_status = 'publish';
		$post->post_title  = 'Ada Lovelace';
		$post->post_date   = '2026-01-10 09:00:00';

		$GLOBALS['_wp_stores']['posts'][ $id ]     = $post;
		$GLOBALS['_wp_stores']['post_meta'][ $id ] = $meta;

		return $post;
	}

	/**
	 * Sin nonce válido el cambio de estado no llega a ejecutarse.
	 */
	public function test_cambio_de_estado_sin_nonce(): void {
		$GLOBALS['_wp_stores']['nonces_validos'] = array( 'otro' );
		$_POST = array( 'nonce' => 'malo', 'post_id' => '55', 'nuevo_estado' => 'baja' );

		$this->expectException( \RuntimeException::class );
		$this->pagina->ajax_change_state();
	}

	/**
	 * Sin capacidad, la respuesta dice «Sin permisos» y no se cambia nada.
	 */
	public function test_cambio_de_estado_sin_capacidad(): void {
		$GLOBALS['_test_caps']['gestionar_miembros'] = false;
		$this->ficha( 55, array( '_convoca_estado_miembro' => 'activo' ) );
		$_POST = array( 'nonce' => 'bueno', 'post_id' => '55', 'nuevo_estado' => 'baja' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/Sin permisos/' );
		$this->pagina->ajax_change_state();
	}

	/**
	 * Un estado que no existe no se acepta.
	 */
	public function test_cambio_a_un_estado_inventado(): void {
		$this->ficha( 55, array( '_convoca_estado_miembro' => 'activo' ) );
		$_POST = array( 'nonce' => 'bueno', 'post_id' => '55', 'nuevo_estado' => 'inventado' );

		$this->expectException( \RuntimeException::class );
		$this->pagina->ajax_change_state();
	}

	/**
	 * Un cambio válido responde con el estado nuevo y su insignia.
	 */
	public function test_cambio_de_estado_valido(): void {
		$this->ficha( 55, array( '_convoca_estado_miembro' => 'activo' ) );
		$_POST = array( 'nonce' => 'bueno', 'post_id' => '55', 'nuevo_estado' => 'baja', 'nota' => 'Lo pide por correo' );

		$this->pagina->ajax_change_state();

		$ultima = end( $GLOBALS['_wp_json'] );
		$this->assertTrue( $ultima['ok'] );
		$this->assertSame( 'baja', $ultima['data']['estado'] );
		$this->assertNotSame( '', $ultima['data']['badge'], 'la respuesta trae la insignia ya pintada' );
		$this->assertSame( 'baja', get_post_meta( 55, '_convoca_estado_miembro', true ) );
	}

	/**
	 * Registrar el contacto por WhatsApp sin ficha corta.
	 */
	public function test_whatsapp_sin_ficha(): void {
		// Se manda `post_id` a 0 y no se omite: omitirlo dispara el aviso de `class-admin-page.php:592`
		// (issue abierto), y aquí lo que se prueba es el corte por ficha inválida, no el aviso.
		$_POST = array( 'nonce' => 'bueno', 'post_id' => '0' );

		$this->expectException( \RuntimeException::class );
		$this->pagina->ajax_log_whatsapp();
	}

	/**
	 * Sin permiso sobre la ficha, también corta.
	 */
	public function test_whatsapp_sin_permiso(): void {
		$GLOBALS['_test_caps']['edit_post'] = false;
		$_POST = array( 'nonce' => 'bueno', 'post_id' => '55' );

		$this->expectException( \RuntimeException::class );
		$this->pagina->ajax_log_whatsapp();
	}

	/**
	 * Un contacto se anota en las dos metas y se responde con éxito.
	 */
	public function test_whatsapp_anota_el_contacto(): void {
		$this->ficha( 55 );
		$_POST = array( 'nonce' => 'bueno', 'post_id' => '55' );

		$this->pagina->ajax_log_whatsapp();

		$meta = $GLOBALS['_wp_stores']['post_meta'][55];
		$this->assertNotEmpty( $meta['_convoca_ultimo_contacto_whatsapp'] );
		$this->assertSame( $meta['_convoca_ultimo_contacto_whatsapp'], $meta['_convoca_ultimo_contacto'], 'las dos fechas coinciden' );
		$this->assertTrue( end( $GLOBALS['_wp_json'] )['ok'] );
	}

	/**
	 * El widget del escritorio se registra con su id.
	 */
	public function test_el_widget_del_escritorio(): void {
		$GLOBALS['_wp_dashboard_widgets'] = array();

		$this->pagina->dashboard_widget();

		$this->assertArrayHasKey( 'convoca_members_widget', $GLOBALS['_wp_dashboard_widgets'] );
		$this->assertIsCallable( $GLOBALS['_wp_dashboard_widgets']['convoca_members_widget']['callback'] );
	}
}
