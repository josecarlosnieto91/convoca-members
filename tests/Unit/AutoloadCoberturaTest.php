<?php
/**
 * Toda clase, trait o interfaz del plugin tiene que estar en el autoload.
 *
 * El 01/11/2026 un trait nuevo quedó fuera del classmap y tumbó biodevas con
 * «Trait not found». Sin un test, el fallo solo aparece en producción.
 *
 * Se pregunta al propio cargador de Composer (`findFile`), no a un regex sobre el mapa:
 * así el resultado es el mismo que tendrá el sitio.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Cobertura del autoload de Composer.
 */
final class AutoloadCoberturaTest extends TestCase {

	/**
	 * Ninguna declaración del plugin se queda sin fichero resoluble.
	 */
	public function test_todas_las_clases_se_autocargan(): void {
		$raiz = dirname( __DIR__, 2 );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- es el cargador, no un global de WordPress.
		$cargador = require $raiz . '/vendor/autoload.php';

		$faltan = array();

		$iterador = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $raiz, \FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $iterador as $fichero ) {
			$ruta = (string) $fichero->getPathname();

			// Los stubs de PHPStan declaran clases que no existen en el plugin: no cuentan.
			$es_stub = '.stub.php' === substr( $ruta, -9 ) || 'phpstan-bootstrap.php' === basename( $ruta );

			if ( $es_stub || '.php' !== substr( $ruta, -4 ) ) {
				continue;
			}

			if ( false !== strpos( $ruta, '/vendor/' ) || false !== strpos( $ruta, '/tests/' ) ) {
				continue;
			}

			$codigo = (string) file_get_contents( $ruta );

			preg_match( '/^\s*namespace\s+([^;{\s]+)\s*;/m', $codigo, $ns );
			$espacio = $ns[1] ?? '';

			if ( ! preg_match_all( '/^\s*(?:final\s+|abstract\s+|readonly\s+)*(?:class|trait|interface|enum)\s+([A-Za-z_][A-Za-z0-9_]*)/m', $codigo, $decl ) ) {
				continue;
			}

			foreach ( $decl[1] as $nombre ) {
				$fq = ltrim( '' === $espacio ? $nombre : $espacio . '\\' . $nombre, '\\' );

				if ( false === $cargador->findFile( $fq ) ) {
					$faltan[] = $fq;
				}
			}
		}

		$this->assertSame(
			array(),
			$faltan,
			'estas clases no las conoce el autoload: ' . implode( ', ', $faltan )
		);
	}
}
