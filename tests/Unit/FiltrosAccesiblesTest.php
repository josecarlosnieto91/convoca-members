<?php
/**
 * Etiquetas individuales de los filtros del listado (members#142).
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

final class FiltrosAccesiblesTest extends TestCase {

	/**
	 * Cada selector tiene su propio `<label for>`; no basta la primera opción.
	 */
	public function test_los_seis_filtros_tienen_etiqueta(): void {
		$src = (string) file_get_contents( dirname( __DIR__, 2 ) . '/admin/class-admin-list.php' );

		$this->assertSame( 6, preg_match_all( '/<select id="convoca-filter-[a-z]+" name="[a-z_]+">/', $src ) );
		foreach ( array( 'estado', 'tipo', 'plan', 'cuota', 'privacidad', 'consentimiento' ) as $filtro ) {
			$this->assertMatchesRegularExpression(
				'/<label class="screen-reader-text" for="convoca-filter-' . $filtro . '">[^<]+<\/label>/',
				$src,
				"falta la etiqueta de {$filtro}"
			);
		}
	}
}
