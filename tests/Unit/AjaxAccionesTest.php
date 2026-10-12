<?php
/**
 * Toda acción AJAX que publica el JavaScript tiene que existir en el PHP.
 *
 * Defecto real del 12/10/2026, encontrado por el auditor a ciegas de la pasada 6 del gauntlet y
 * reproducido por ingeniería: el JS de convoca-members publicaba TRES acciones con el prefijo
 * viejo `conv_` mientras el plugin registra `convoca_`:
 *
 *   convoca-members-public.js:294   action=conv_get_nonce          (PHP: convoca_get_nonce)
 *   convoca-members-admin.js:117    action=conv_export_csv         (PHP: convoca_export_csv)
 *   convoca-members-admin.js:131    action=conv_save_csv_columns   (PHP: convoca_save_csv_columns)
 *
 * Consecuencia: el endpoint del nonce «bypass cache» estaba muerto (comprobado por HTTP: la acción
 * del JS devuelve `0` y la registrada devuelve `{"success":true,…}`), así que en una página cacheada
 * el nonce caduca y el alta responde «Nonce inválido» sin poder refrescar; y dos acciones de la
 * administración (exportar CSV y guardar columnas) tampoco llegaban a su handler.
 *
 * Un desajuste así no lo ve ni el lint ni el CI: cada lado es válido por separado. Solo se ve
 * cruzando los dos. convoca-enroll ya tenía esta prueba (por eso allí se corrigieron cinco casos en
 * septiembre); a convoca-members le faltaba, y por eso el defecto vivía aquí.
 *
 * Y la prueba mira TRES formas de publicar una acción, no una: la del ayudante, la del objeto de
 * jQuery y —la que se colaba— la del `action=` dentro de una URL. La versión de enroll solo miraba
 * dos, y por eso dos ficheros publicaron acciones inexistentes durante meses.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Contrato JS ↔ PHP de las acciones AJAX.
 */
class AjaxAccionesTest extends TestCase
{
	/**
	 * Raíz del plugin.
	 */
	private function raiz(): string
	{
		return dirname( __DIR__, 2 );
	}

	/**
	 * Ficheros PHP del plugin (sin dependencias ni pruebas).
	 *
	 * @return array<int, string>
	 */
	private function ficheros_php(): array
	{
		$fuera = array( 'vendor', 'tests', 'node_modules', '.git', 'languages' );
		$lista = array();
		$raiz  = $this->raiz();

		foreach ( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $raiz ) ) as $f ) {
			$ruta = $f->getPathname();
			foreach ( $fuera as $carpeta ) {
				if ( str_contains( $ruta, '/' . $carpeta . '/' ) ) {
					continue 2;
				}
			}
			if ( str_ends_with( $ruta, '.php' ) ) {
				$lista[] = $ruta;
			}
		}

		return $lista;
	}

	/**
	 * Acciones que publica el JavaScript del plugin.
	 *
	 * @return array<string, string> acción => fichero donde se publica.
	 */
	private function acciones_del_js(): array
	{
		$publicadas = array();
		$patrones   = array(
			// 1. El ayudante del plugin.
			"/ajaxPost\\(\\s*['\"]([a-z0-9_]+)['\"]/",
			// 2. Objeto de jQuery / fetch: action: 'convoca_x'.
			"/action\\s*:\\s*['\"]([a-z0-9_]+)['\"]/",
			// 3. Dentro de una URL: action=convoca_x (la forma que se colaba sin que nadie la viera).
			"/action=([a-z0-9_]+)/",
		);

		foreach ( (array) glob( $this->raiz() . '/assets/js/*.js' ) as $js ) {
			$contenido = (string) file_get_contents( $js );
			foreach ( $patrones as $patron ) {
				if ( preg_match_all( $patron, $contenido, $m ) ) {
					foreach ( $m[1] as $accion ) {
						// Solo las acciones del plugin: se descartan las de WordPress o de terceros.
						if ( str_starts_with( $accion, 'conv' ) ) {
							$publicadas[ $accion ] = basename( $js );
						}
					}
				}
			}
		}

		return $publicadas;
	}

	public function test_toda_accion_publicada_desde_el_js_esta_registrada_en_php(): void
	{
		$publicadas = $this->acciones_del_js();
		$this->assertNotEmpty( $publicadas, 'Debería haber acciones publicadas desde el JS.' );

		$registradas = array();
		foreach ( $this->ficheros_php() as $php ) {
			if ( preg_match_all( '/wp_ajax(?:_nopriv)?_([a-z0-9_]+)/', (string) file_get_contents( $php ), $m ) ) {
				foreach ( $m[1] as $accion ) {
					$registradas[ $accion ] = true;
				}
			}
		}
		$this->assertNotEmpty( $registradas, 'Debería haber acciones registradas en el PHP.' );

		foreach ( $publicadas as $accion => $origen ) {
			$this->assertArrayHasKey(
				$accion,
				$registradas,
				"{$origen} publica «{$accion}» pero el plugin no registra esa acción (wp_ajax_{$accion}): la llamada no llegará a ningún handler."
			);
		}
	}

	public function test_ningun_nombre_de_accion_queda_del_prefijo_viejo(): void
	{
		// `conv_*` es el prefijo viejo: el plugin registra `convoca_*`. Se busca en las tres formas.
		foreach ( $this->acciones_del_js() as $accion => $origen ) {
			$this->assertStringStartsWith(
				'convoca',
				$accion,
				"{$origen} publica «{$accion}» con el prefijo viejo (conv_): el plugin registra «convoca_»."
			);
		}
	}
}
