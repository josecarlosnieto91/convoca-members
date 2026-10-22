<?php
/**
 * El catálogo de traducciones tiene que ir con la versión del plugin.
 *
 * @package Convoca\Members
 */

namespace Convoca\Members\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Una subida de versión son TRES sitios: la cabecera `Version:`, el `Stable tag` del readme y el
 * `Project-Id-Version` del `.pot`. El 22-10-2026 se publicó la 0.3.2 con el `.pot` todavía en 0.3.1 y
 * no lo cazó ni la puerta local ni el CI: lo detectó el guardián diario de higiene, un día después.
 * Esta prueba cierra ese hueco: si la versión y el catálogo se separan, el push se para aquí.
 *
 * @coversNothing
 */
final class VersionYPotTest extends TestCase {

	/**
	 * Raíz del plugin.
	 *
	 * @return string
	 */
	private function raiz(): string {
		return dirname( __DIR__, 2 );
	}

	/**
	 * Versión declarada en la cabecera del plugin.
	 *
	 * @return string
	 */
	private function version(): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- fichero local del repo, no hay petición de red.
		$cabecera = (string) file_get_contents( $this->raiz() . '/convoca-members.php' );
		if ( ! preg_match( '/^\s*\*?\s*Version:\s*([0-9][0-9A-Za-z.\-]*)/mi', $cabecera, $m ) ) {
			$this->fail( 'No se encuentra la cabecera Version: en convoca-members.php' );
		}

		return $m[1];
	}

	/**
	 * Cabecera del catálogo.
	 *
	 * @return string
	 */
	private function cabecera_pot(): string {
		$ruta = $this->raiz() . '/languages/convoca-members.pot';
		$this->assertFileExists( $ruta, 'Falta el catálogo languages/convoca-members.pot' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- fichero local del repo, no hay petición de red.
		$pot = (string) file_get_contents( $ruta );
		if ( ! preg_match( '/Project-Id-Version:\s*([^\\\\"]*)/', $pot, $m ) ) {
			$this->fail( 'El .pot no declara Project-Id-Version' );
		}

		return trim( $m[1] );
	}

	/**
	 * La cabecera del catálogo lleva la versión del plugin.
	 *
	 * @return void
	 */
	public function test_el_pot_declara_la_version_del_plugin(): void {
		$version  = $this->version();
		$cabecera = $this->cabecera_pot();

		// La cabecera tiene que TERMINAR en la versión, no solo contenerla: el 22-10-2026 el .pot de
		// members llegó a decir «Convoca Members 2.9.18nvoca Members 2.9.17», que contiene la versión
		// buena y sin embargo está corrupto. Un assertStringContainsString no lo veía.
		$this->assertMatchesRegularExpression(
			'/\s' . preg_quote( $version, '/' ) . '$/',
			$cabecera,
			"El .pot dice «{$cabecera}» y el plugin es {$version}: regenera el catálogo (wp i18n make-pot) en el mismo commit que la subida de versión."
		);

		// Y no puede llevar la versión dos veces (cabecera con texto pegado de una sustitución anterior).
		$this->assertSame(
			1,
			substr_count( $cabecera, $version ),
			"La cabecera del .pot repite la versión: «{$cabecera}»"
		);
	}

	/**
	 * El catálogo tiene cadenas y no está truncado.
	 *
	 * @return void
	 */
	public function test_el_pot_tiene_cadenas(): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- fichero local del repo, no hay petición de red.
		$pot = (string) file_get_contents( $this->raiz() . '/languages/convoca-members.pot' );

		$this->assertGreaterThan(
			50,
			(int) preg_match_all( '/^msgid\s+"/m', $pot ),
			'El .pot tiene menos cadenas de las esperadas: puede estar truncado o generado a medias.'
		);
	}
}
