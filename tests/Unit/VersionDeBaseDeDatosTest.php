<?php
/**
 * La versión de la BASE DE DATOS va pegada a la del plugin, no clavada a mano (medido 16/10/2026).
 *
 * DEFECTO QUE FIJA ESTA PRUEBA: el gestor de migraciones compara la versión de la BD con la guardada en
 * la instalación y **sale por el atajo si no ha subido**:
 *
 *     if ( version_compare( $saved, $current, '>=' ) ) { $this->set_cache(); return; }
 *
 * `CONVOCA_MEMBERS_DB_VERSION` estaba **clavada en `1.0.3`** desde el principio, así que la instalación
 * tenía guardado `1.0.3` y el gestor daba por hecho que estaba al día: **ninguna migración se ejecutó
 * nunca** — ni la limpieza de filas huérfanas de 2.8.47, ni la de la ficha `{datos_transferencia}` de
 * 2.8.81. Y en silencio: no falla nada, simplemente no pasa nada.
 *
 * Se comprueba sobre la FUENTE (la constante se deriva de la cabecera del plugin) porque en el arnés
 * unitario el fichero principal no se carga; es la misma vara con la que este repositorio vigila otros
 * contratos de versión.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

class VersionDeBaseDeDatosTest extends TestCase {

	private function principal(): string {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/convoca-members.php' );
	}

	/** La constante de BD no puede llevar un literal: se deriva de la versión del plugin. */
	public function test_la_version_de_bd_no_esta_clavada(): void {
		$fuente = $this->principal();

		$this->assertDoesNotMatchRegularExpression(
			"/define\(\s*'CONVOCA_MEMBERS_DB_VERSION'\s*,\s*'[0-9]+\.[0-9]+/",
			$fuente,
			'Un literal deja el gestor de migraciones muerto en cuanto alguien lo olvida: tiene que salir de la cabecera'
		);
		$this->assertStringContainsString(
			"define( 'CONVOCA_MEMBERS_DB_VERSION', ! empty( \$convoca_members_header_db['Version'] )",
			$fuente,
			'Y tiene que derivarse de la versión declarada en la cabecera del plugin'
		);
	}

	/** Y el gestor devuelve esa constante (no otra cosa). */
	public function test_el_gestor_usa_esa_version(): void {
		$gestor = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Members_Upgrade_Manager.php' );

		$this->assertStringContainsString(
			"return defined( 'CONVOCA_MEMBERS_DB_VERSION' ) ? CONVOCA_MEMBERS_DB_VERSION : '0.0.0';",
			$gestor,
			'La versión con la que se compara es la de la base de datos'
		);
	}

	/** Cada migración declara la versión del plugin en la que entra (si no, nunca se alcanzaría). */
	public function test_las_migraciones_declaran_su_version(): void {
		$gestor = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Members_Upgrade_Manager.php' );

		preg_match_all( "/'([0-9]+\.[0-9]+\.[0-9]+)'\s*=>\s*array\(\s*\\\$this,\s*'([a-z0-9_]+)'/", $gestor, $m, PREG_SET_ORDER );

		$this->assertNotEmpty( $m, 'Tiene que haber migraciones declaradas con su versión' );
		foreach ( $m as $entrada ) {
			$this->assertNotSame(
				'0.0.0',
				$entrada[1],
				'Una migración con versión 0.0.0 no se ejecutaría nunca: ' . $entrada[2]
			);
			$this->assertMatchesRegularExpression(
				'/^[0-9]+\.[0-9]+\.[0-9]+$/',
				$entrada[1],
				'La versión de ' . $entrada[2] . ' tiene que ser una versión del plugin (x.y.z)'
			);
		}
	}
}
