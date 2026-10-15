<?php
/**
 * Ninguna acción `admin_post_*` puede estar registrada dos veces (issue #27).
 *
 * Defecto real: `convoca_delete_member` estaba registrado en `convoca-members.php` (un cierre heredado que
 * mandaba la ficha a la papelera) **y** en `admin/class-admin-member-editor.php` (el manejador bueno). El
 * cierre se registraba antes, así que corría el primero y su `exit` dejaba al bueno sin ejecutar nunca: el
 * enlace «Eliminar» parecía funcionar y no hacía nada de lo que decía.
 *
 * Dos `add_action` sobre el mismo hook no dan error: se ejecutan los dos y el primero que llama a `exit`
 * se lleva la acción. Por eso se vigila sobre el código, que es donde se ve.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

class HooksUnicosTest extends TestCase {

	/**
	 * Ficheros PHP del plugin (sin vendor, sin tests).
	 *
	 * @return array<string>
	 */
	private function ficheros(): array {
		$raiz = dirname( __DIR__, 2 );
		$salida = array();
		$it = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $raiz ) );
		foreach ( $it as $f ) {
			$ruta = $f->getPathname();
			if ( ! $f->isFile() || 'php' !== $f->getExtension() ) {
				continue;
			}
			if ( preg_match( '#/(vendor|tests|node_modules)/#', $ruta ) ) {
				continue;
			}
			$salida[] = $ruta;
		}
		return $salida;
	}

	public function test_ninguna_accion_admin_post_se_registra_dos_veces(): void {
		$registros = array();

		foreach ( $this->ficheros() as $fichero ) {
			$codigo = file_get_contents( $fichero );
			if ( false === $codigo ) {
				continue;
			}
			if ( preg_match_all( "/add_action\(\s*'admin_post_([a-z0-9_]+)'/", $codigo, $m ) ) {
				foreach ( $m[1] as $accion ) {
					$registros[ $accion ][] = basename( $fichero );
				}
			}
		}

		$this->assertNotEmpty( $registros, 'No se ha encontrado ningún admin_post_*: la prueba no está midiendo nada' );

		foreach ( $registros as $accion => $donde ) {
			$this->assertCount(
				1,
				$donde,
				"admin_post_$accion está registrado " . count( $donde ) . ' veces (' . implode( ', ', $donde )
					. '): el que corra primero se lleva la acción y el otro queda muerto'
			);
		}
	}
}
