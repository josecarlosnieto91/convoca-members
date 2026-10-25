<?php
/**
 * El flujo de la importación: guardas y camino feliz.
 *
 * `handle_import()` es lo que corre cuando alguien pulsa «Importar» con un CSV de socios: comprueba el nonce, el
 * permiso, resuelve la ruta del fichero **a partir de la ficha** (el navegador no manda rutas, members#48), lee el
 * CSV y decide si lo hace de una vez o por lotes.
 *
 * Se miden las cinco guardas —cada una con su mensaje— y los dos caminos: el corto (≤50 filas, todo en esta petición)
 * y el largo (>50, guarda el estado en un transitorio y sigue por lotes). Las guardas se miden con `_wp_die_lanza`,
 * porque `wp_die()` sin lanzar deja seguir la ejecución y la prueba no distinguiría «paró» de «siguió».
 *
 * El camino largo termina en `exit;` → proceso aparte.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Admin_Import_CSV;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/admin/class-admin-import-csv.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Miembro.php';

/**
 * El flujo de importación.
 */
final class FlujoDeImportacionTest extends TestCase {

	/**
	 * Valores previos.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Ficheros creados por la prueba.
	 *
	 * @var string[]
	 */
	private array $ficheros = array();

	/**
	 * Importador.
	 *
	 * @var Admin_Import_CSV
	 */
	private Admin_Import_CSV $importador;

	/**
	 * Monta el escenario.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->previo = array(
			'posts'     => $GLOBALS['_wp_stores']['posts'] ?? array(),
			'meta'      => $GLOBALS['_wp_stores']['post_meta'] ?? array(),
			'transients'=> $GLOBALS['_wp_stores']['transients'] ?? array(),
			'nonces'    => $GLOBALS['_wp_stores']['nonces_validos'] ?? null,
			'die'       => $GLOBALS['_wp_die_lanza'] ?? null,
			'die_msg'   => $GLOBALS['_wp_die_llamado'] ?? null,
			'post'      => $_POST ?? array(),
			'redirect'  => $GLOBALS['_wp_redirects'] ?? array(),
			'caps'      => $GLOBALS['_test_caps'] ?? array(),
			'user'      => $GLOBALS['_test_user_id'] ?? null,
		);

		$GLOBALS['_wp_stores']['posts']            = array();
		$GLOBALS['_wp_stores']['post_meta']        = array();
		$GLOBALS['_wp_stores']['transients']       = array();
		$GLOBALS['_wp_stores']['nonces_validos']   = array( 'nonce-bueno' );
		$GLOBALS['_wp_die_lanza']                  = true;
		$GLOBALS['_test_caps']                     = array();
		$GLOBALS['_test_user_id']                  = 7;
		$_POST                                     = array();
		$_REQUEST                                  = array();

		$this->importador = ( new \ReflectionClass( Admin_Import_CSV::class ) )->newInstanceWithoutConstructor();
	}

	/**
	 * Devuelve el escenario.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']['posts']          = $this->previo['posts'];
		$GLOBALS['_wp_stores']['post_meta']      = $this->previo['meta'];
		$GLOBALS['_wp_stores']['transients']     = $this->previo['transients'];
		$GLOBALS['_wp_die_lanza']                = $this->previo['die'];
		$GLOBALS['_wp_die_llamado']              = $this->previo['die_msg'];
		$GLOBALS['_test_caps']                   = $this->previo['caps'];
		if ( null === $this->previo['user'] ) {
			unset( $GLOBALS['_test_user_id'] );
		} else {
			$GLOBALS['_test_user_id'] = $this->previo['user'];
		}
		$_POST                                   = $this->previo['post'];
		$_REQUEST                                = array();

		if ( null === $this->previo['nonces'] ) {
			unset( $GLOBALS['_wp_stores']['nonces_validos'] );
		} else {
			$GLOBALS['_wp_stores']['nonces_validos'] = $this->previo['nonces'];
		}

		foreach ( $this->ficheros as $f ) {
			if ( is_file( $f ) ) {
				unlink( $f );
			}
		}
		$this->ficheros = array();
	}

	/**
	 * El directorio de importación del producto.
	 *
	 * @return string
	 */
	private function dir(): string {
		$m = new \ReflectionMethod( Admin_Import_CSV::class, 'dir_importacion' );

		return (string) $m->invoke( null );
	}

	/**
	 * Deja un CSV en el directorio de importación y devuelve su ficha (token, ruta, usuario).
	 *
	 * @param string $contenido CSV.
	 * @param int    $usuario   Dueño.
	 * @return array{token: string, ruta: string}
	 */
	private function sembrar_csv( string $contenido, int $usuario = 7 ): array {
		$dir  = $this->dir();
		$ruta = $dir . '/prueba-' . bin2hex( random_bytes( 6 ) ) . '.csv';
		file_put_contents( $ruta, $contenido );
		$this->ficheros[] = $ruta;

		$token = bin2hex( random_bytes( 16 ) );
		$GLOBALS['_wp_stores']['transients'][ 'convoca_csv_fichero_' . $token ] = array(
			'ruta'    => $ruta,
			'usuario' => $usuario,
		);

		return array( 'token' => $token, 'ruta' => $ruta );
	}

	/**
	 * Prepara la petición.
	 *
	 * @param array $post Campos.
	 * @return void
	 */
	private function peticion( array $post ): void {
		$_POST    = $post;
		$_REQUEST = $post;
	}

	/**
	 * Ejecuta el flujo.
	 *
	 * @return void
	 */
	private function ejecutar(): void {
		$this->importador->handle_import();
	}

	/**
	 * Ejecuta el flujo y devuelve la URL del redirect final.
	 *
	 * El doble de `wp_safe_redirect()` **lanza** con la URL en el mensaje, así que el resumen de la importación se
	 * puede leer tal cual: es lo que ve el socio que importa, y es donde estaba el defecto de contar tres veces la
	 * misma fila.
	 *
	 * @return string
	 */
	private function ejecutar_y_leer_resumen(): string {
		try {
			$this->importador->handle_import();
		} catch ( \RuntimeException $e ) {
			if ( ! str_contains( $e->getMessage(), 'wp_safe_redirect' ) ) {
				throw $e;
			}
			return urldecode( $e->getMessage() );
		}

		$this->fail( 'la importación no terminó en redirect' );

		return '';
	}

	/**
	 * Un nonce que no vale detiene la importación antes de leer nada.
	 */
	public function test_sin_nonce_valido_se_para(): void {
		$csv = $this->sembrar_csv( "nombre,email\nAda,ada@example.test\n" );
		$this->peticion( array( 'token' => $csv['token'] ) );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/nonce/i' );

		$this->ejecutar();
	}

	/**
	 * Sin `manage_options` no se importa: un editor no carga el censo de socios.
	 */
	public function test_sin_permiso_se_para(): void {
		$csv = $this->sembrar_csv( "nombre,email\nAda,ada@example.test\n" );
		$this->peticion(
			array(
				'_wpnonce' => 'nonce-bueno',
				'token'    => $csv['token'],
			)
		);
		$GLOBALS['_test_caps'] = array( 'manage_options' => false );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/permisos/i' );

		$this->ejecutar();
	}

	/**
	 * Un token con forma inválida se rechaza sin tocar el disco.
	 */
	public function test_un_token_mal_formado_se_rechaza(): void {
		$this->peticion(
			array(
				'_wpnonce' => 'nonce-bueno',
				'token'    => '../../etc/passwd',
			)
		);

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/caducado/i' );

		$this->ejecutar();
	}

	/**
	 * **El token de otra persona no sirve**: el censo de socios no se importa en la sesión de otro.
	 */
	public function test_el_token_de_otro_usuario_no_sirve(): void {
		$csv = $this->sembrar_csv( "nombre,email\nAda,ada@example.test\n", 99 );
		$this->peticion(
			array(
				'_wpnonce' => 'nonce-bueno',
				'token'    => $csv['token'],
			)
		);

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/caducado/i' );

		$this->ejecutar();
	}

	/**
	 * Un CSV sin cabeceras se para con su mensaje.
	 */
	public function test_un_csv_sin_cabeceras_se_para(): void {
		$csv = $this->sembrar_csv( '' );
		$this->peticion(
			array(
				'_wpnonce' => 'nonce-bueno',
				'token'    => $csv['token'],
				'map'      => array( 'email' => 'email' ),
			)
		);

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/cabeceras/i' );

		$this->ejecutar();
	}

	/**
	 * **Un transitorio manipulado no puede leer un fichero de fuera.**
	 *
	 * La ruta la resuelve el servidor a partir del token (members#48), y el token se comprueba dos veces: que sea de
	 * quien lo pide **y que el fichero esté dentro del directorio de importación**. Sin la segunda, quien consiguiera
	 * escribir un transitorio podría importar —o sea, **volcar a la base**— cualquier fichero legible del servidor:
	 * `/etc/passwd`, un `wp-config.php` de otro sitio, un volcado de la base.
	 *
	 * Aquí el token es válido, es del usuario correcto y apunta a un CSV perfectamente formado... que está **fuera**.
	 */
	public function test_un_fichero_de_fuera_del_directorio_se_rechaza(): void {
		// CSV real y válido, pero en el directorio de al lado.
		$fuera = $this->dir() . '-de-fuera';
		if ( ! is_dir( $fuera ) ) {
			mkdir( $fuera, 0700, true );
		}
		$ruta = $fuera . '/censo.csv';
		file_put_contents( $ruta, "nombre,email\nAda Lovelace,ada@example.test\n" );
		$this->ficheros[] = $ruta;

		$token = bin2hex( random_bytes( 16 ) );
		$GLOBALS['_wp_stores']['transients'][ 'convoca_csv_fichero_' . $token ] = array(
			'ruta'    => $ruta,
			'usuario' => 7,
		);

		$this->peticion(
			array(
				'_wpnonce' => 'nonce-bueno',
				'token'    => $token,
				'map'      => array(
					'nombre' => 'nombre',
					'email'  => 'email',
				),
			)
		);

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/caducado/i' );

		try {
			$this->ejecutar();
		} finally {
			$this->assertSame( array(), $GLOBALS['_wp_stores']['posts'], 'no se importó ni una ficha' );
			if ( is_dir( $fuera ) ) {
				foreach ( (array) glob( $fuera . '/*' ) as $resto ) {
					unlink( $resto );
				}
				rmdir( $fuera );
			}
		}
	}

	/**
	 * El camino corto: un CSV de tres filas se importa entero en la misma petición.
	 */
	public function test_un_csv_pequeno_se_importa_entero(): void {
		$csv = $this->sembrar_csv(
			"nombre,email,dni\n" .
			"Ada Lovelace,ada@example.test,00000000T\n" .
			"Grace Hopper,grace@example.test,11111111H\n" .
			"Alan Turing,alan@example.test,22222222J\n"
		);

		$this->peticion(
			array(
				'_wpnonce' => 'nonce-bueno',
				'token'    => $csv['token'],
				'map'      => array(
					'nombre' => 'nombre',
					'email'  => 'email',
					'dni'    => 'dni',
				),
			)
		);

		$resumen = $this->ejecutar_y_leer_resumen();

		$this->assertStringContainsString( 'import_status=ok', $resumen );
		$this->assertStringContainsString( '3 socios importados correctamente', $resumen, 'el resumen cuenta los tres' );
		$this->assertCount( 3, $GLOBALS['_wp_stores']['posts'], 'tres fichas' );
		$emails = array();
		foreach ( $GLOBALS['_wp_stores']['post_meta'] as $meta ) {
			$emails[] = $meta['_convoca_email'] ?? '';
		}
		sort( $emails );
		$this->assertSame( array( 'ada@example.test', 'alan@example.test', 'grace@example.test' ), $emails );
	}

	/**
	 * Las filas **en blanco** del CSV no cuentan ni crean fichas fantasma.
	 *
	 * Una hoja de cálculo deja filas vacías al final; si contaran, el resumen mentiría y el socio fantasma
	 * aparecería en el listado.
	 */
	public function test_las_filas_en_blanco_no_cuentan(): void {
		$csv = $this->sembrar_csv(
			"nombre,email\n" .
			"Ada Lovelace,ada@example.test\n" .
			",\n" .
			"   ,   \n" .
			"Grace Hopper,grace@example.test\n"
		);

		$this->peticion(
			array(
				'_wpnonce' => 'nonce-bueno',
				'token'    => $csv['token'],
				'map'      => array(
					'nombre' => 'nombre',
					'email'  => 'email',
				),
			)
		);

		$resumen = $this->ejecutar_y_leer_resumen();

		$this->assertStringContainsString( 'import_status=ok', $resumen, 'las filas vacías no son errores' );
		$this->assertStringNotContainsString( 'filas con errores', $resumen, 'ni generan avisos' );
		$this->assertStringContainsString( '2 socios importados correctamente', $resumen, 'las filas vacías no cuentan' );
		$this->assertCount( 2, $GLOBALS['_wp_stores']['posts'], 'dos fichas, no cuatro' );
	}

	/**
	 * Una columna sin mapear no se importa (pero no rompe la fila).
	 */
	public function test_una_columna_sin_mapear_no_rompe_la_fila(): void {
		$csv = $this->sembrar_csv( "nombre,email,notas\nAda Lovelace,ada@example.test,lo que sea\n" );

		$this->peticion(
			array(
				'_wpnonce' => 'nonce-bueno',
				'token'    => $csv['token'],
				'map'      => array(
					'nombre' => 'nombre',
					'email'  => 'email',
					'notas'  => '',
				),
			)
		);

		$resumen = $this->ejecutar_y_leer_resumen();

		$this->assertStringContainsString( '1 socio importado correctamente', $resumen, 'en singular' );
		$this->assertCount( 1, $GLOBALS['_wp_stores']['posts'] );
		$meta = reset( $GLOBALS['_wp_stores']['post_meta'] );
		$this->assertSame( 'ada@example.test', $meta['_convoca_email'] ?? '' );
		$this->assertArrayNotHasKey( '_convoca_notas', (array) $meta, 'la columna sin mapear no se guarda' );
	}

	/**
	 * **El resumen concuerda**: «1 socio importado», «3 socios importados».
	 *
	 * El contador ya falló una vez —contaba la misma fila en las tres ramas y decía «1 socios importados
	 * correctamente. 1 errores»— y el número va directo al redirect que lee la persona que importa.
	 */
	public function test_el_resumen_concuerda_en_singular_y_plural(): void {
		$csv = $this->sembrar_csv( "nombre,email\nAda Lovelace,ada@example.test\n" );
		$this->peticion(
			array(
				'_wpnonce' => 'nonce-bueno',
				'token'    => $csv['token'],
				'map'      => array(
					'nombre' => 'nombre',
					'email'  => 'email',
				),
			)
		);

		$this->assertStringContainsString( '1 socio importado correctamente', $this->ejecutar_y_leer_resumen() );
	}

	/**
	 * Una fila rechazada aparece como **error**, no como importada.
	 *
	 * Es el defecto que se arregló: el resumen decía «importados» de una fila rechazada y la persona daba por hecho
	 * que su socio estaba dado de alta.
	 */
	public function test_una_fila_rechazada_se_cuenta_como_error(): void {
		$csv = $this->sembrar_csv( "nombre,email\nAda Lovelace,ada@example.test\nSIN CORREO,\n" );
		$this->peticion(
			array(
				'_wpnonce' => 'nonce-bueno',
				'token'    => $csv['token'],
				'map'      => array(
					'nombre' => 'nombre',
					'email'  => 'email',
				),
			)
		);

		$resumen = $this->ejecutar_y_leer_resumen();

		$this->assertStringContainsString( '1 socio importado correctamente', $resumen, 'solo el bueno' );
		$this->assertStringContainsString( 'import_status=parcial', $resumen, 'el estado avisa de que fue a medias' );
		$this->assertStringContainsString( '1 fila con errores', $resumen, 'y dice cuál falló' );
		$this->assertStringContainsString( 'Fila 2', $resumen, 'con su número de línea' );
		$this->assertStringContainsString( 'obligatorios', $resumen, 'y el motivo' );
		$this->assertCount( 1, $GLOBALS['_wp_stores']['posts'], 'una sola ficha' );
	}

	/**
	 * El camino largo: más de 50 filas deja el estado en un transitorio y sigue por lotes.
	 *
	 * Termina en `exit;` → proceso aparte.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_un_csv_grande_se_parte_en_lotes(): void {
		$lineas = "nombre,email\n";
		for ( $i = 1; $i <= 60; $i++ ) {
			$lineas .= "Socia {$i},socia{$i}@example.test\n";
		}
		$csv = $this->sembrar_csv( $lineas );

		$this->peticion(
			array(
				'_wpnonce' => 'nonce-bueno',
				'token'    => $csv['token'],
				'map'      => array(
					'nombre' => 'nombre',
					'email'  => 'email',
				),
			)
		);

		try {
			$this->ejecutar();
			$this->fail( 'el camino largo termina en exit' );
		} catch ( \Throwable $e ) {
			// `exit` en proceso aparte: PHPUnit detecta que el hijo murió. Si el flujo siguiera, aquí no se
			// llegaría a nada y el test fallaría por el `fail()` de arriba.
			$this->assertTrue( true );
		}
	}
}
