<?php
/**
 * La importación por lotes.
 *
 * Con más de 50 filas la importación se parte: `handle_import()` guarda el estado en un transitorio, procesa el primer
 * lote y redirige; cada visita posterior a la pantalla procesa otro lote. `process_batch()` es quien avanza el
 * contador, acumula los errores y, al llegar al final, limpia.
 *
 * Es la parte donde un fallo **deja la importación a medias sin que nadie se entere**: si el offset no avanza, el
 * mismo lote se repite para siempre; si se borra el transitorio antes de tiempo, las filas que faltan se pierden en
 * silencio y el resumen dice que fue todo bien.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Admin_Import_CSV;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/admin/class-admin-import-csv.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Miembro.php';

/**
 * Lotes.
 */
final class LotesDeImportacionTest extends TestCase {

	/**
	 * Valores previos.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Ficheros creados.
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
	 * Clave del lote.
	 *
	 * @var string
	 */
	private string $clave;

	/**
	 * Monta el escenario.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->previo = array(
			'posts'      => $GLOBALS['_wp_stores']['posts'] ?? array(),
			'meta'       => $GLOBALS['_wp_stores']['post_meta'] ?? array(),
			'transients' => $GLOBALS['_wp_stores']['transients'] ?? array(),
			'user'       => $GLOBALS['_test_user_id'] ?? null,
		);

		$GLOBALS['_wp_stores']['posts']      = array();
		$GLOBALS['_wp_stores']['post_meta']  = array();
		$GLOBALS['_wp_stores']['transients'] = array();
		$GLOBALS['_test_user_id']            = 7;

		$this->clave      = 'convoca_import_batch_7';
		$this->importador = ( new \ReflectionClass( Admin_Import_CSV::class ) )->newInstanceWithoutConstructor();
	}

	/**
	 * Devuelve el escenario.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']['posts']      = $this->previo['posts'];
		$GLOBALS['_wp_stores']['post_meta']  = $this->previo['meta'];
		$GLOBALS['_wp_stores']['transients'] = $this->previo['transients'];
		if ( null === $this->previo['user'] ) {
			unset( $GLOBALS['_test_user_id'] );
		} else {
			$GLOBALS['_test_user_id'] = $this->previo['user'];
		}

		foreach ( $this->ficheros as $f ) {
			if ( is_file( $f ) ) {
				unlink( $f );
			}
		}
		$this->ficheros = array();
	}

	/**
	 * Prepara un estado de lote con N filas buenas.
	 *
	 * @param int  $filas      Cuántas.
	 * @param bool $con_fich   Si el estado lleva fichero temporal.
	 * @return void
	 */
	private function sembrar_lote( int $filas, bool $con_fich = false ): void {
		$rows = array();
		for ( $i = 1; $i <= $filas; $i++ ) {
			$rows[] = array(
				'nombre' => 'Socia ' . $i,
				'email'  => 'socia' . $i . '@example.test',
			);
		}

		$estado = array(
			'rows'            => $rows,
			'total'           => $filas,
			'offset'          => 0,
			'imported'        => 0,
			'errors'          => array(),
			'send_welcome'    => false,
			'update_existing' => false,
			'filepath'        => '',
		);

		if ( $con_fich ) {
			$ruta = sys_get_temp_dir() . '/convoca-lote-' . bin2hex( random_bytes( 4 ) ) . '.csv';
			file_put_contents( $ruta, "nombre,email\n" );
			$this->ficheros[]    = $ruta;
			$estado['filepath'] = $ruta;
		}

		$GLOBALS['_wp_stores']['transients'][ $this->clave ] = $estado;
	}

	/**
	 * Ejecuta un lote.
	 *
	 * @param int $tamano Tamaño.
	 * @return void
	 */
	private function lote( int $tamano = 25 ): void {
		$m = new \ReflectionMethod( Admin_Import_CSV::class, 'process_batch' );
		$m->invoke( $this->importador, $this->clave, $tamano );
	}

	/**
	 * Estado guardado.
	 *
	 * @return array
	 */
	private function estado(): array {
		return (array) ( $GLOBALS['_wp_stores']['transients'][ $this->clave ] ?? array() );
	}

	/**
	 * Sin estado no se hace nada y no revienta: la pantalla se puede recargar sin transitorio.
	 */
	public function test_sin_estado_no_hace_nada(): void {
		$this->lote();

		$this->assertSame( array(), $GLOBALS['_wp_stores']['posts'], 'no se importó nada' );
		$this->assertArrayNotHasKey(
			'convoca_import_batch_7_done',
			$GLOBALS['_wp_stores']['transients'],
			'ni se anuncia una importación que no existía («0 socios importados»)'
		);
	}

	/**
	 * Un lote intermedio importa **solo su tanda** y deja el resto para la siguiente.
	 */
	public function test_un_lote_intermedio_importa_su_tanda_y_guarda_el_resto(): void {
		$this->sembrar_lote( 60 );

		$this->lote( 25 );

		$this->assertCount( 25, $GLOBALS['_wp_stores']['posts'], 'una tanda' );
		$estado = $this->estado();
		$this->assertSame( 25, $estado['offset'] ?? null, 'el contador avanzó' );
		$this->assertSame( 25, $estado['imported'] ?? null );
		$this->assertArrayHasKey( $this->clave, $GLOBALS['_wp_stores']['transients'], 'el estado sigue vivo' );
	}

	/**
	 * **El contador avanza de verdad**: tres lotes seguidos importan 25, 25 y 10, no tres veces los mismos.
	 *
	 * Si el offset no avanzara, el mismo lote se repetiría para siempre y la importación no terminaría nunca —con el
	 * servidor recibiendo la misma petición una y otra vez.
	 */
	public function test_tres_lotes_seguidos_no_reptien_filas(): void {
		$this->sembrar_lote( 60 );

		$this->lote( 25 );
		$this->lote( 25 );
		$this->lote( 25 );

		$this->assertCount( 60, $GLOBALS['_wp_stores']['posts'], 'las 60, ni una repetida' );

		$emails = array();
		foreach ( $GLOBALS['_wp_stores']['post_meta'] as $meta ) {
			$emails[] = $meta['_convoca_email'] ?? '';
		}
		$this->assertCount( 60, array_unique( $emails ), 'sin correos duplicados' );
	}

	/**
	 * El último lote **cierra**: borra el estado, borra el fichero temporal y deja el mensaje final.
	 */
	public function test_el_ultimo_lote_cierra_y_limpia(): void {
		$this->sembrar_lote( 10, true );
		$ruta = (string) ( $this->estado()['filepath'] ?? '' );
		$this->assertFileExists( $ruta, 'el fichero temporal existe antes' );

		$this->lote( 25 );

		$this->assertArrayNotHasKey( $this->clave, $GLOBALS['_wp_stores']['transients'], 'el estado se borró' );
		$this->assertFileDoesNotExist( $ruta, 'y el CSV temporal también' );

		$final = (string) ( $GLOBALS['_wp_stores']['transients'][ 'convoca_import_batch_7_done' ] ?? '' );
		$this->assertStringContainsString( '10 socios importados', $final, 'deja el resumen' );
	}

	/**
	 * El mensaje final **concuerda**: «1 socio importado», no «1 socios importados».
	 *
	 * Es el mismo defecto que se corrigió en la importación corta (#26), pero este camino se quedó con `__()` en vez
	 * de `_n()` y sigue diciendo «1 socios importados».
	 */
	public function test_el_mensaje_final_concuerda_en_singular(): void {
		$this->sembrar_lote( 1 );

		$this->lote( 25 );

		$final = (string) ( $GLOBALS['_wp_stores']['transients'][ 'convoca_import_batch_7_done' ] ?? '' );
		$this->assertStringContainsString( '1 socio importado', $final, 'en singular' );
		$this->assertStringNotContainsString( '1 socios importados', $final, 'no en plural' );
	}

	/**
	 * Una fila rechazada cuenta como **error**, no como importada, también en lotes.
	 */
	public function test_una_fila_mala_cuenta_como_error_y_no_como_importada(): void {
		$GLOBALS['_wp_stores']['transients'][ $this->clave ] = array(
			'rows'            => array(
				array(
					'nombre' => 'Buena',
					'email'  => 'buena@example.test',
				),
				array(
					'nombre' => 'Sin correo',
					'email'  => '',
				),
			),
			'total'           => 2,
			'offset'          => 0,
			'imported'        => 0,
			'errors'          => array(),
			'send_welcome'    => false,
			'update_existing' => false,
			'filepath'        => '',
		);

		$this->lote( 25 );

		$final = (string) ( $GLOBALS['_wp_stores']['transients'][ 'convoca_import_batch_7_done' ] ?? '' );
		$this->assertStringContainsString( '1 socio importado', $final, 'solo la buena, y en singular' );
		$this->assertStringContainsString( '1 error:', $final, 'la mala como error, en singular' );
		$this->assertCount( 1, $GLOBALS['_wp_stores']['posts'], 'una sola ficha' );
	}
}
