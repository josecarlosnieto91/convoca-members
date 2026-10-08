<?php
/**
 * Guardián del contrato entre el JS del alta y la plantilla del paso 4.
 *
 * Bug real (issue #3, alta del 04/10/2026): el JS rellenaba 18 nodos `#conv-sum-*` y la plantilla solo
 * definía 10. Los 8 que faltaban no se veían y no daba ningún error: `setText` escribe sobre un nodo
 * inexistente sin avisar. La persona no podía revisar su WhatsApp, su canal, la modalidad ni el importe.
 *
 * Este test vigila la CLASE de fallo, no los 18 nodos de hoy: compara lo que el JS escribe con lo que
 * la plantilla define. Si alguien añade un `setText` sin su fila (o borra una fila), falla.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

class ResumenAltaTest extends TestCase {

	private function fuente( string $rel ): string {
		$path = dirname( __DIR__, 2 ) . '/' . $rel;
		$this->assertFileExists( $path, "Falta {$rel}" );
		return (string) file_get_contents( $path );
	}

	/** Devuelve los ids `conv-sum-*` que el JS intenta rellenar. */
	private function nodos_del_js(): array {
		preg_match_all( "/setText\(\s*'#(conv-sum-[a-z]+)'/", $this->fuente( 'assets/js/convoca-members-public.js' ), $m );
		$ids = array_unique( $m[1] );
		sort( $ids );
		return $ids;
	}

	/** Devuelve los ids `conv-sum-*` que la plantilla define. */
	private function nodos_de_la_plantilla(): array {
		preg_match_all( '/id="(conv-sum-[a-z]+)"/', $this->fuente( 'templates/form-alta.php' ), $m );
		$ids = array_unique( $m[1] );
		sort( $ids );
		return $ids;
	}

	/** Lo que el JS pinta tiene que existir en la plantilla. */
	public function test_el_js_no_pinta_nodos_que_la_plantilla_no_tiene(): void {
		$faltan = array_diff( $this->nodos_del_js(), $this->nodos_de_la_plantilla() );
		$this->assertSame(
			array(),
			array_values( $faltan ),
			'El JS rellena nodos que la plantilla no define (el defecto es silencioso): ' . implode( ', ', $faltan )
		);
	}

	/** Y al revés: una fila de la revisión que nadie rellena se quedaría en «—». */
	public function test_la_plantilla_no_tiene_filas_muertas(): void {
		$sobran = array_diff( $this->nodos_de_la_plantilla(), $this->nodos_del_js() );
		$this->assertSame(
			array(),
			array_values( $sobran ),
			'La plantilla define filas que el JS nunca rellena (se verían con «—»): ' . implode( ', ', $sobran )
		);
	}
}
