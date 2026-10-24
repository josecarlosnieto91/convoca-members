<?php
/**
 * El editor de la ficha del socio: alta, edición, baja y borrado.
 *
 * Es la puerta por la que se crea una ficha con datos personales y por la que se activa a alguien —y al activar se le
 * asigna número de socio y se le marca la cuota—. Lo que se prueba: sin nonce o sin permiso **no se escribe nada**, sin
 * nombre no hay ficha, y al reactivar se garantiza número y cuota aunque el flujo anterior los dejara a medias.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Admin_Member_Editor;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/admin/class-admin-member-editor.php';

/**
 * Editor de la ficha.
 */
final class MemberEditorTest extends TestCase {

	/**
	 * Editor.
	 *
	 * @var Admin_Member_Editor
	 */
	private Admin_Member_Editor $editor;

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
			'posts'      => $GLOBALS['_wp_stores']['posts'] ?? array(),
			'post_meta'  => $GLOBALS['_wp_stores']['post_meta'] ?? array(),
			'transients' => $GLOBALS['_wp_stores']['transients'] ?? array(),
			'nonces'     => $GLOBALS['_wp_stores']['nonces_validos'] ?? null,
		);

		$GLOBALS['_wp_stores']['posts']          = array();
		$GLOBALS['_wp_stores']['post_meta']      = array();
		$GLOBALS['_wp_stores']['transients']     = array();
		$GLOBALS['_wp_stores']['nonces_validos'] = array( 'bueno' );
		$GLOBALS['_wp_stub_next_id']             = 700;
		$GLOBALS['_wp_insert_args']              = array();
		$GLOBALS['_wp_die_lanza']                = true;
		$GLOBALS['_test_caps']                   = array( 'edit_post' => true, 'gestionar_miembros' => true, 'manage_options' => true );
		$_POST                                   = array();
		$_GET                                    = array();
		$_REQUEST                                = array();
		$this->editor                            = new Admin_Member_Editor();
	}

	/**
	 * Limpia.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']['posts']      = $this->previo['posts'];
		$GLOBALS['_wp_stores']['post_meta']  = $this->previo['post_meta'];
		$GLOBALS['_wp_stores']['transients'] = $this->previo['transients'];
		if ( null === $this->previo['nonces'] ) {
			unset( $GLOBALS['_wp_stores']['nonces_validos'] );
		} else {
			$GLOBALS['_wp_stores']['nonces_validos'] = $this->previo['nonces'];
		}
		unset( $GLOBALS['_wp_die_lanza'], $GLOBALS['_wp_stub_next_id'], $GLOBALS['_wp_insert_args'] );
		$_POST    = array();
		$_GET     = array();
		$_REQUEST = array();
	}

	/**
	 * Una ficha existente.
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
		$post->post_title  = 'Nombre viejo';

		$GLOBALS['_wp_stores']['posts'][ $id ]     = $post;
		$GLOBALS['_wp_stores']['post_meta'][ $id ] = $meta;

		return $post;
	}

	/**
	 * Sin nonce, el editor no toca nada.
	 */
	public function test_sin_nonce(): void {
		$_POST = array( 'convoca_nombre' => 'Ada' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/Acceso denegado/' );
		$this->editor->handle_save();
	}

	/**
	 * Alta sin capacidad de gestión: no se crea la ficha.
	 */
	public function test_alta_sin_capacidad(): void {
		$GLOBALS['_test_caps']['gestionar_miembros'] = false;
		$_POST = array(
			'_convoca_nonce'  => 'bueno',
			'post_id'         => '0',
			'convoca_nombre'  => 'Ada Lovelace',
		);

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/No tienes permisos/' );
		$this->editor->handle_save();
	}

	/**
	 * Edición sin permiso sobre la ficha: tampoco.
	 */
	public function test_edicion_sin_permiso(): void {
		$GLOBALS['_test_caps']['edit_post'] = false;
		$this->ficha( 55 );
		$_POST = array(
			'_convoca_nonce' => 'bueno',
			'post_id'        => '55',
			'convoca_nombre' => 'Ada Lovelace',
		);

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/No tienes permisos/' );
		$this->editor->handle_save();
	}

	/**
	 * Sin nombre no hay ficha: es el único campo obligatorio.
	 */
	public function test_sin_nombre(): void {
		$_POST = array(
			'_convoca_nonce' => 'bueno',
			'post_id'        => '0',
			'convoca_nombre' => '   ',
		);

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/El nombre es obligatorio/' );
		$this->editor->handle_save();
	}

	/**
	 * Alta: crea la ficha, guarda la meta, los interruptores y deja el aviso pendiente de pintar.
	 */
	public function test_alta_completa(): void {
		$_POST = array(
			'_convoca_nonce'       => 'bueno',
			'post_id'              => '0',
			'convoca_nombre'       => 'Grace Hopper',
			'convoca_email'        => '  grace@example.test  ',
			'convoca_telefono'     => '600000000',
			'convoca_observaciones' => 'Socia nueva',
			'convoca_pago_recurrente' => '1',
		);

		try {
			$this->editor->handle_save();
			$this->fail( 'debía redirigir' );
		} catch ( \RuntimeException $r ) {
			$this->assertStringContainsString( 'conv-member-editor', $r->getMessage() );
		}

		$id = array_key_first( $GLOBALS['_wp_stores']['post_meta'] );
		$this->assertIsInt( $id );
		$this->assertSame( 'Grace Hopper', $GLOBALS['_wp_stores']['posts'][ $id ]->post_title );
		$meta = $GLOBALS['_wp_stores']['post_meta'][ $id ];
		$this->assertSame( 'grace@example.test', $meta['_convoca_email'] );
		$this->assertSame( 'Socia nueva', $meta['_convoca_observaciones'] );
		$this->assertSame( '1', $meta['_convoca_pago_recurrente'] );
		$this->assertSame( '0', $meta['_convoca_es_voluntario'], 'casilla sin marcar es 0' );
		$this->assertArrayHasKey(
			'convoca_member_saved_' . get_current_user_id(),
			$GLOBALS['_wp_stores']['transients'],
			'el aviso queda listo para la pantalla siguiente'
		);
	}

	/**
	 * Edición: actualiza la ficha que había, no crea otra.
	 */
	public function test_edicion_actualiza(): void {
		$this->ficha( 55, array( '_convoca_email' => 'viejo@example.test' ) );
		$_POST = array(
			'_convoca_nonce' => 'bueno',
			'post_id'        => '55',
			'convoca_nombre' => 'Ada Lovelace',
			'convoca_email'  => 'nuevo@example.test',
		);

		try {
			$this->editor->handle_save();
		} catch ( \RuntimeException $r ) {
			$this->assertStringContainsString( 'admin.php?page=conv-member-editor&id=55', $r->getMessage() );
		}

		$this->assertCount( 1, $GLOBALS['_wp_stores']['posts'], 'no se duplica la ficha' );
		$this->assertSame( 'Ada Lovelace', $GLOBALS['_wp_stores']['posts'][55]->post_title );
		$this->assertSame( 'nuevo@example.test', $GLOBALS['_wp_stores']['post_meta'][55]['_convoca_email'] );
		$this->assertStringContainsString(
			'actualizado',
			$GLOBALS['_wp_stores']['transients'][ 'convoca_member_saved_' . get_current_user_id() ]
		);
	}

	/**
	 * Al activar se garantiza número de socio y cuota activa.
	 */
	public function test_activar_asigna_numero_y_cuota(): void {
		$this->ficha( 55, array( '_convoca_estado_miembro' => 'pendiente_pago' ) );
		$_POST = array(
			'_convoca_nonce'         => 'bueno',
			'post_id'                => '55',
			'convoca_nombre'         => 'Ada Lovelace',
			'convoca_estado_miembro' => 'activo',
		);

		try {
			$this->editor->handle_save();
		} catch ( \RuntimeException $e ) {
			// Redirección final.
		}

		$meta = $GLOBALS['_wp_stores']['post_meta'][55];
		$this->assertArrayHasKey( '_convoca_numero_socio', $meta, 'activar numera' );
		$this->assertSame( 'activa', $meta['_convoca_estado_cuota'] );
	}

	/**
	 * Ya activo y con número no se le asigna otro.
	 */
	public function test_ya_activo_conserva_su_numero(): void {
		$this->ficha(
			55,
			array(
				'_convoca_estado_miembro' => 'activo',
				'_convoca_numero_socio'   => '0042',
				'_convoca_estado_cuota'   => 'activa',
			)
		);
		$_POST = array(
			'_convoca_nonce'         => 'bueno',
			'post_id'                => '55',
			'convoca_nombre'         => 'Ada Lovelace',
			'convoca_estado_miembro' => 'activo',
		);

		try {
			$this->editor->handle_save();
		} catch ( \RuntimeException $e ) {
			// Redirección final.
		}

		$this->assertSame( '0042', $GLOBALS['_wp_stores']['post_meta'][55]['_convoca_numero_socio'] );
	}

	/**
	 * La baja conserva la ficha y solo cambia el estado.
	 */
	public function test_la_baja_cambia_el_estado(): void {
		$this->ficha( 55, array( '_convoca_estado_miembro' => 'activo' ) );
		$_GET = array( 'member_id' => '55', '_wpnonce' => 'bueno' );

		try {
			$this->editor->handle_baja();
			$this->fail( 'debía redirigir' );
		} catch ( \RuntimeException $r ) {
			$this->assertStringContainsString( 'msg=baja', $r->getMessage() );
		}

		$this->assertSame( 'baja', $GLOBALS['_wp_stores']['post_meta'][55]['_convoca_estado_miembro'] );
		$this->assertArrayHasKey( 55, $GLOBALS['_wp_stores']['posts'], 'la ficha no se borra' );
	}

	/**
	 * Sin nonce, la baja no toca nada.
	 */
	public function test_baja_sin_nonce(): void {
		$this->ficha( 55, array( '_convoca_estado_miembro' => 'activo' ) );
		$GLOBALS['_wp_stores']['nonces_validos'] = array( 'otro' );
		$_GET = array( 'member_id' => '55', '_wpnonce' => 'malo' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/Acceso denegado/' );
		$this->editor->handle_baja();
	}

	/**
	 * Sin ficha no hay baja.
	 */
	public function test_baja_sin_ficha(): void {
		$_GET = array( '_wpnonce' => 'bueno' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/Acceso denegado/' );
		$this->editor->handle_baja();
	}
}
