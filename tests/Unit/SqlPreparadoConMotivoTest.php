<?php
/**
 * El SQL que va a `prepare()` es un literal, o lleva su motivo escrito al lado.
 *
 * Por qué existe: el deduplicador del alta armaba el `WHERE` por concatenación y le pasaba el resultado a
 * `prepare()` por una variable. Funcionaba —el valor viajaba preparado—, pero los analizadores de
 * WordPress.org no pueden comprobar una cadena armada a trozos: PCP marcaba la consulta entera como no
 * preparada (`WordPress.DB.PreparedSQL.NotPrepared` y su propio `DirectDB.UnescapedDBParameter`) y el CI de
 * `main` salía en rojo por código que era correcto. Y un rojo que no distingue «esto está mal» de «esto no
 * se puede comprobar» deja el CI sin significado.
 *
 * El contrato que se vigila aquí: en las rutas que se publican, el primer argumento de `$wpdb->prepare()` es
 * un literal de cadena. Cuando de verdad hace falta una variable (el `WHERE` de una consulta dinámica, como
 * las tres de `Cron_Manager`), va anotada **en la misma línea** con `phpcs:ignore` y su motivo después de
 * `--`: la anotación es entonces una decisión escrita y revisable, no un silencio.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class SqlPreparadoConMotivoTest extends TestCase {

	/** El SQL que va a prepare() o es literal, o lleva su motivo. */
	public function test_el_sql_que_va_a_prepare_es_literal_o_lleva_motivo(): void {
		$raiz      = dirname( __DIR__, 2 );
		$hallazgos = array();

		foreach ( $this->ficheros( $raiz ) as $relativa ) {
			$lineas = preg_split( '/\R/', (string) file_get_contents( $raiz . '/' . $relativa ) );
			foreach ( (array) $lineas as $numero => $linea ) {
				if ( ! preg_match( '/prepare\(\s*\$/', $linea ) ) {
					continue;
				}
				if ( preg_match( '/phpcs:ignore\s+\S+\s+--\s+\S/', $linea ) ) {
					continue;
				}
				$hallazgos[] = $relativa . ':' . ( $numero + 1 );
			}
		}

		$this->assertSame(
			array(),
			$hallazgos,
			'El SQL que va a prepare() o es un literal de cadena, o va anotado con su motivo en la misma línea.'
		);
	}

	/**
	 * Los ficheros que se publican: ni tests ni tooling.
	 *
	 * @param string $raiz Raíz del repositorio.
	 * @return array<int, string>
	 */
	private function ficheros( string $raiz ): array {
		$salida = array();

		foreach ( array( 'includes', 'admin', 'public' ) as $dir ) {
			if ( ! is_dir( $raiz . '/' . $dir ) ) {
				continue;
			}
			$iterador = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $raiz . '/' . $dir, FilesystemIterator::SKIP_DOTS )
			);
			foreach ( $iterador as $fichero ) {
				if ( $fichero->isFile() && 'php' === strtolower( $fichero->getExtension() ) ) {
					$salida[] = str_replace( $raiz . '/', '', $fichero->getPathname() );
				}
			}
		}

		sort( $salida );

		return $salida;
	}
}
