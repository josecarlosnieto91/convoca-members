<?php
/**
 * Integridad del panel de Mi Área (members#144).
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

final class MiAreaJsTest extends TestCase {

	/**
	 * `fetchProfile` se define una sola vez: el stub sobrescrito confundía el mantenimiento.
	 */
	public function test_fetch_profile_tiene_una_sola_definicion(): void {
		$src = (string) file_get_contents( dirname( __DIR__, 2 ) . '/public/assets/mi-area.js' );

		$this->assertSame( 1, substr_count( $src, 'fetchProfile: function()' ) );
		$this->assertStringNotContainsString( 'TODO: implementar carga del perfil', $src );
	}
}
