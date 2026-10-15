<?php
/**
 * Comprobación de contrato: el repo tiene UNA sola configuración de PHPStan, y es la que usa el CI.
 *
 * Por qué existe: convoca-members tenía dos. La del CI (`phpstan.neon.dist`, nivel 5, con su catálogo de
 * excepciones y los stubs de WordPress) y una copia vieja en `dev-tools/` (nivel 0, con rutas del monorepo)
 * que **nadie usaba y que daba 11 errores**: quien la pasara a mano —o una herramienta que descubriera el
 * `.dist` primero— concluiría que el análisis del repo está roto mientras el CI dice verde. Medido el
 * 15/10/2026: el CI [OK], la copia de dev-tools 11 errores.
 *
 * Dos varas para el mismo código: el verde deja de significar algo.
 *
 * Prueba en negativo: si vuelve a aparecer un segundo fichero de configuración (en la raíz **o en
 * `dev-tools/`**), o la del CI deja de ser la que existe, esta comprobación falla y la nombra.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

class PhpstanUnaSolaConfiguracionTest extends TestCase {

	private function raiz(): string {
		return dirname( __DIR__, 2 );
	}

	/**
	 * Configuraciones de PHPStan presentes en el repo: la raíz y `dev-tools/`.
	 *
	 * `dev-tools/` entra a propósito: la copia que este contrato existe para evitar vivía justo ahí, y una
	 * comprobación que solo mira la raíz no la ve.
	 *
	 * @return array<int, string> Rutas relativas a la raíz del repositorio.
	 */
	private function configuraciones(): array {
		$encontradas = array();
		$candidatas  = array_merge(
			(array) glob( $this->raiz() . '/phpstan.neon*' ),
			(array) glob( $this->raiz() . '/dev-tools/phpstan.neon*' )
		);
		foreach ( $candidatas as $f ) {
			if ( is_file( $f ) ) {
				$encontradas[] = str_replace( $this->raiz() . '/', '', $f );
			}
		}
		sort( $encontradas );

		return $encontradas;
	}

	/** La configuración que usa el CI, leída del propio workflow. */
	private function configuracionDelCi(): string {
		$yml = (string) file_get_contents( $this->raiz() . '/.github/workflows/ci.yml' );
		if ( preg_match( '/phpstan analyse[^\n]*--configuration=([^\s]+)/', $yml, $m ) ) {
			return $m[1];
		}

		return 'phpstan.neon';
	}

	public function test_hay_una_sola_configuracion_de_phpstan(): void {
		$cfgs = $this->configuraciones();
		$this->assertCount(
			1,
			$cfgs,
			'Debe haber una única configuración de PHPStan. Encontradas: ' . implode( ', ', $cfgs )
		);
	}

	public function test_la_configuracion_del_ci_es_la_que_hay(): void {
		$cfgs = $this->configuraciones();
		if ( empty( $cfgs ) ) {
			$this->fail( 'No hay ninguna configuración de PHPStan: el job del CI no analizaría nada' );
		}
		$this->assertContains(
			$this->configuracionDelCi(),
			$cfgs,
			'El CI debe usar la configuración que existe en el repo'
		);
	}
}
