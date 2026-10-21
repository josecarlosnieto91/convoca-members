<?php
/**
 * El listado de horas tiene que pintar celdas (issue #32).
 *
 * `WP_List_Table` necesita que `prepare_items()` fije `_column_headers`; sin eso,
 * `get_column_info()` devuelve el array vacío con el que se construye la tabla y tanto la cabecera como las
 * filas se pintan **sin celdas**: el listado decía «9 elementos» y no mostraba ni un dato, con lo que el
 * botón «Aprobar» de las horas era inalcanzable.
 *
 * Se comprueba sobre el código y no renderizando la tabla: el arnés de pruebas no trae `WP_List_Table` y
 * montarlo entero mediría el doble, no el listado. La comprobación de verdad es la de la demo (la tabla con
 * sus celdas), y esa queda anotada en el issue.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

class ListadoHorasColumnasTest extends TestCase {

	/**
	 * Cuerpo de un método del fichero.
	 *
	 * @param string $fichero Ruta relativa.
	 * @param string $metodo  Nombre del método.
	 * @return string
	 */
	private function cuerpo( string $fichero, string $metodo ): string {
		$ruta = dirname( __DIR__, 2 ) . '/' . $fichero;
		$this->assertFileExists( $ruta );
		$codigo = file_get_contents( $ruta );
		$ini    = strpos( $codigo, 'public function ' . $metodo . '(' );
		$this->assertNotFalse( $ini, "No existe $metodo en $fichero" );
		$fin = strpos( $codigo, 'public function ', $ini + 20 );

		return false === $fin ? substr( $codigo, $ini ) : substr( $codigo, $ini, $fin - $ini );
	}

	public function test_prepare_items_fija_las_cabeceras_de_columna(): void {
		$this->assertStringContainsString(
			'$this->_column_headers =',
			$this->cuerpo( 'admin/class-admin-horas.php', 'prepare_items' ),
			'Sin `_column_headers`, WP_List_Table pinta las filas sin celdas y el listado de horas sale vacío'
		);
	}

	public function test_las_columnas_tienen_su_render(): void {
		// Cada columna declarada tiene que tener su `column_*`: si no, la celda sale en blanco.
		$codigo = file_get_contents( dirname( __DIR__, 2 ) . '/admin/class-admin-horas.php' );
		preg_match( '/public function get_columns\(\)[^}]*?array\((.*?)\);/s', $codigo, $m );
		$this->assertNotEmpty( $m, 'No se han podido leer las columnas' );

		preg_match_all( "/'([a-z_]+)'\s*=>/", $m[1], $cols );
		foreach ( $cols[1] as $col ) {
			if ( 'cb' === $col ) {
				continue;
			}
			$this->assertStringContainsString(
				'function column_' . $col . '(',
				$codigo,
				'La columna «' . $col . '» no tiene render: saldrá vacía'
			);
		}
	}
}
