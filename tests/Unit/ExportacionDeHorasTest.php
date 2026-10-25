<?php
/**
 * La exportación de horas: nonce y permisos.
 *
 * `handle_export_csv()` vuelca **datos personales** (quién, cuándo, cuántas horas, en qué proyecto) a un fichero que
 * se descarga. Es la operación más sensible de esta pantalla, así que lo primero que se mide son sus dos puertas:
 *
 *  1. **nonce** — un enlace viejo o fabricado no puede descargar nada.
 *  2. **capacidad** — sin `gestionar_miembros` no hay exportación, aunque el nonce sea válido.
 *
 * **Las tres van en PROCESO APARTE, y no es por gusto**: `handle_export_csv()` termina en `exit;`. Si una guarda
 * faltara, el flujo llegaría al volcado del CSV y el `exit` **terminaría PHPUnit con código 0**, así que la prueba
 * parecería pasar y el mutante sobreviviría (comprobado: con la guarda del nonce quitada, la salida era el CSV y
 * `rc=0`). En un proceso hijo, en cambio, PHPUnit detecta que el hijo murió sin terminar el caso y **falla**.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Admin_Horas;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/admin/class-admin-horas.php';

/**
 * Guardas de la exportación.
 */
final class ExportacionDeHorasTest extends TestCase {

	/**
	 * Valores previos.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Monta el escenario con el corte por `wp_die` activo.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->previo = array(
			'capas'   => $GLOBALS['_test_caps'] ?? array(),
			'nonce'   => $GLOBALS['_wp_stores']['nonces_validos'] ?? null,
			'usuario' => $GLOBALS['_test_user_id'] ?? null,
			'get'     => $_GET,
		);

		$GLOBALS['_test_caps']    = array( 'gestionar_miembros' => true );
		$GLOBALS['_test_user_id'] = 10;
		$_GET                     = array();
		// Como en producción: una guarda que falla CORTA.
		$GLOBALS['_wp_die_lanza'] = true;
	}

	/**
	 * Devuelve el escenario.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['_test_caps'] = $this->previo['capas'];
		$_GET                  = $this->previo['get'];
		if ( null === $this->previo['nonce'] ) {
			unset( $GLOBALS['_wp_stores']['nonces_validos'] );
		} else {
			$GLOBALS['_wp_stores']['nonces_validos'] = $this->previo['nonce'];
		}
		if ( null === $this->previo['usuario'] ) {
			unset( $GLOBALS['_test_user_id'] );
		} else {
			$GLOBALS['_test_user_id'] = $this->previo['usuario'];
		}
		unset( $GLOBALS['_wp_die_lanza'] );
	}

	/**
	 * Un nonce que no verifica no exporta nada.
	 *
	 * Es la guarda que impide que un enlace reenviado meses después siga descargando datos personales.
	 */
	#[RunInSeparateProcess]
	public function test_sin_nonce_valido_no_hay_exportacion(): void {
		// `nonces_validos` vacío: el doble del núcleo solo acepta lo que esté en esa lista.
		$GLOBALS['_wp_stores']['nonces_validos'] = array();
		$_GET                                    = array( '_wpnonce' => 'fabricado' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/Nonce inválido/' );

		( new Admin_Horas() )->handle_export_csv();
	}

	/**
	 * Sin la capacidad, un nonce válido tampoco abre la puerta.
	 *
	 * La capacidad se comprueba DESPUÉS del nonce: si se comprobara antes, un enlace sin nonce revelaría a quien no
	 * tiene permiso si la pantalla existe.
	 */
	#[RunInSeparateProcess]
	public function test_sin_capacidad_no_hay_exportacion(): void {
		$GLOBALS['_wp_stores']['nonces_validos']     = array( 'bueno' );
		$GLOBALS['_test_caps']['gestionar_miembros'] = false;
		$_GET                                        = array( '_wpnonce' => 'bueno' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/No tienes permisos/' );

		( new Admin_Horas() )->handle_export_csv();
	}

	/**
	 * El nonce se comprueba ANTES que la capacidad.
	 *
	 * Se mide el orden a propósito: primero el nonce (barato y no revela nada) y después el permiso.
	 */
	#[RunInSeparateProcess]
	public function test_el_nonce_se_comprueba_antes_que_el_permiso(): void {
		// Nonce malo Y sin capacidad: el mensaje que sale tiene que ser el del nonce.
		$GLOBALS['_wp_stores']['nonces_validos']     = array();
		$GLOBALS['_test_caps']['gestionar_miembros'] = false;
		$_GET                                        = array( '_wpnonce' => 'fabricado' );

		try {
			( new Admin_Horas() )->handle_export_csv();
			$this->fail( 'exportó sin nonce ni permiso' );
		} catch ( \RuntimeException $e ) {
			$this->assertStringContainsString( 'Nonce', $e->getMessage(), 'el nonce se mira primero' );
		}
	}
}
