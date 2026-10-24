<?php
/**
 * La pantalla de webhooks: crear, editar, borrar, probar y activar.
 *
 * Un webhook manda datos de socios a un servidor de fuera, así que la pantalla que los gestiona tiene que estar cerrada:
 * sin la capacidad `convoca_manage_webhooks` no se hace nada, y sin nonce no se crea ni se borra. Se prueba la acción
 * por su **efecto**: qué queda guardado y a dónde se vuelve.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Admin_Webhooks;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/admin/class-admin-webhooks.php';

/**
 * Webhooks.
 */
final class AdminWebhooksTest extends TestCase {

	/**
	 * Pantalla.
	 *
	 * @var Admin_Webhooks
	 */
	private Admin_Webhooks $pantalla;

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
			'options' => $GLOBALS['_wp_stores']['options'] ?? array(),
			'nonces'  => $GLOBALS['_wp_stores']['nonces_validos'] ?? null,
		);

		$GLOBALS['_wp_stores']['options']        = array();
		$GLOBALS['_wp_stores']['nonces_validos'] = array( 'bueno' );
		$GLOBALS['_wp_die_lanza']                = true;
		$GLOBALS['_test_caps']                   = array( 'convoca_manage_webhooks' => true );
		$_POST                                   = array();
		$_GET                                    = array();
		$_REQUEST                                = array();
		$this->pantalla                          = new Admin_Webhooks();
	}

	/**
	 * Limpia.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']['options'] = $this->previo['options'];
		if ( null === $this->previo['nonces'] ) {
			unset( $GLOBALS['_wp_stores']['nonces_validos'] );
		} else {
			$GLOBALS['_wp_stores']['nonces_validos'] = $this->previo['nonces'];
		}
		unset( $GLOBALS['_wp_die_lanza'] );
		$_POST    = array();
		$_GET     = array();
		$_REQUEST = array();
	}

	/**
	 * Los webhooks guardados.
	 *
	 * @return array
	 */
	private function guardados(): array {
		return (array) ( $GLOBALS['_wp_stores']['options']['convoca_webhooks'] ?? array() );
	}

	/**
	 * El webhook con ese id (se guardan en una lista, no en un mapa).
	 *
	 * @param string $id Identificador.
	 * @return array|null
	 */
	private function por_id( string $id ): ?array {
		foreach ( $this->guardados() as $webhook ) {
			if ( ( $webhook['id'] ?? '' ) === $id ) {
				return $webhook;
			}
		}
		return null;
	}

	/**
	 * Ejecuta la acción con el bloqueo de la operación libre.
	 *
	 * `Webhook_Manager` toma un bloqueo con vencimiento; si una llamada anterior no lo suelta, la siguiente no guarda y
	 * la prueba mediría el bloqueo en vez de la acción.
	 *
	 * @return void
	 */
	private function ejecutar(): void {
		unset( $GLOBALS['_wp_stores']['locks']['convoca_lock_webhook_crud'] );
		$this->pantalla->handle_actions();
	}

	/**
	 * Sin acción no se hace nada.
	 */
	public function test_sin_accion_no_pasa_nada(): void {
		$_GET = array();

		$this->ejecutar();

		$this->assertSame( array(), $this->guardados() );
	}

	/**
	 * Sin la capacidad, tampoco (aunque la acción venga en el formulario).
	 */
	public function test_sin_capacidad_no_pasa_nada(): void {
		$GLOBALS['_test_caps']['convoca_manage_webhooks'] = false;
		$_POST = array( 'convoca_webhook_action' => 'create', 'webhook_url' => 'https://destino.example.test/hook' );

		$this->ejecutar();

		$this->assertSame( array(), $this->guardados() );
	}

	/**
	 * Crear exige nonce: sin él, corta.
	 */
	public function test_crear_sin_nonce(): void {
		$GLOBALS['_wp_stores']['nonces_validos'] = array( 'otro' );
		$_POST = array( 'convoca_webhook_action' => 'create', '_wpnonce' => 'malo' );

		$this->expectException( \RuntimeException::class );
		$this->ejecutar();
	}

	/**
	 * Crear guarda la URL, el secreto, las etiquetas y los eventos, y vuelve con `created`.
	 */
	public function test_crear(): void {
		$_POST = array(
			'convoca_webhook_action' => 'create',
			'_wpnonce'               => 'bueno',
			'webhook_url'            => 'https://destino.example.test/hook',
			'webhook_secret'         => '  s3cr3t  ',
			'webhook_label'          => 'Mi integración',
			'webhook_events'         => array( 'member.created', 'member.activated' ),
		);

		try {
			$this->ejecutar();
			$this->fail( 'debía redirigir' );
		} catch ( \RuntimeException $r ) {
			$this->assertStringContainsString( 'msg=created', $r->getMessage() );
		}

		$guardados = $this->guardados();
		$this->assertCount( 1, $guardados );
		$webhook = reset( $guardados );
		$this->assertSame( 'https://destino.example.test/hook', $webhook['url'] );
		$this->assertSame( 's3cr3t', $webhook['secret'], 'sin espacios alrededor' );
		$this->assertSame( 'Mi integración', $webhook['label'] );
		$this->assertSame( array( 'member.created', 'member.activated' ), $webhook['events'] );
	}

	/**
	 * Editar cambia la URL y puede desactivar el webhook.
	 */
	public function test_editar(): void {
		$_POST = array(
			'convoca_webhook_action' => 'create',
			'_wpnonce'               => 'bueno',
			'webhook_url'            => 'https://viejo.example.test/hook',
			'webhook_label'          => 'Viejo',
			'webhook_events'         => array( 'member.created' ),
		);
		try {
			$this->ejecutar();
		} catch ( \RuntimeException $e ) {
			// Redirección de la creación.
		}

		$guardados = $this->guardados();
		$id        = (string) $guardados[0]['id'];

		$_POST = array(
			'convoca_webhook_action' => 'update',
			'_wpnonce'               => 'bueno',
			'webhook_id'             => $id,
			'webhook_url'            => 'https://nuevo.example.test/hook',
			'webhook_label'          => 'Nuevo',
			'webhook_events'         => array( 'member.created' ),
		);

		try {
			$this->ejecutar();
		} catch ( \RuntimeException $r ) {
			$this->assertStringContainsString( 'msg=updated', $r->getMessage() );
		}

		$webhook = $this->por_id( $id );
		$this->assertSame( 'https://nuevo.example.test/hook', $webhook['url'] );
		$this->assertNotEmpty( $webhook, 'el webhook sigue existiendo' );
		$this->assertFalse( $webhook['active'], 'sin la casilla marcada queda inactivo' );
	}

	/**
	 * Borrar quita el webhook.
	 */
	public function test_borrar(): void {
		$GLOBALS['_wp_stores']['options']['convoca_webhooks'] = array(
			array(
				'id'     => 'abc',
				'url'    => 'https://destino.example.test/hook',
				'secret' => 'x',
				'events' => array(),
				'label'  => 'Uno',
				'active' => true,
			),
		);
		$_GET = array( 'convoca_wh_action' => 'delete', 'webhook_id' => 'abc', '_wpnonce' => 'bueno' );

		try {
			$this->ejecutar();
			$this->fail( 'debía redirigir' );
		} catch ( \RuntimeException $r ) {
			$this->assertStringContainsString( 'msg=deleted', $r->getMessage() );
		}

		$this->assertSame( array(), $this->guardados() );
	}

	/**
	 * Activar/desactivar invierte el estado, no lo deja como estaba.
	 */
	public function test_activar_y_desactivar(): void {
		$GLOBALS['_wp_stores']['options']['convoca_webhooks'] = array(
			array(
				'id'     => 'abc',
				'url'    => 'https://destino.example.test/hook',
				'secret' => 'x',
				'events' => array(),
				'label'  => 'Uno',
				'active' => true,
			),
		);
		$_GET = array( 'convoca_wh_action' => 'toggle', 'webhook_id' => 'abc', '_wpnonce' => 'bueno' );

		try {
			$this->ejecutar();
		} catch ( \RuntimeException $r ) {
			$this->assertStringContainsString( 'msg=toggled', $r->getMessage() );
		}

		$webhook = $this->por_id( 'abc' );
		$this->assertNotNull( $webhook );
		$this->assertFalse( $webhook['active'], 'estaba activo y queda inactivo' );
	}

	/**
	 * Una acción que no existe no toca nada.
	 */
	public function test_accion_desconocida(): void {
		$GLOBALS['_wp_stores']['options']['convoca_webhooks'] = array( array( 'id' => 'abc', 'url' => 'https://x.example.test', 'active' => true ) );
		$_GET = array( 'convoca_wh_action' => 'inventada', 'webhook_id' => 'abc', '_wpnonce' => 'bueno' );

		$this->ejecutar();

		$this->assertNotNull( $this->por_id( 'abc' ), 'no se borra por una acción inventada' );
	}
}
