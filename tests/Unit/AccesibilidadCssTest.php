<?php
/**
 * Contraste de las alertas públicas (members#114).
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

final class AccesibilidadCssTest extends TestCase {

	/**
	 * Las alertas definen fondo y color, y el enlace no hereda el naranja de marca.
	 */
	public function test_las_alertas_tienen_contraste_aa(): void {
		$css = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/css/convoca-members-public.css' );

		$this->assertStringContainsString( '.convoca-alert--info', $css );
		$this->assertStringContainsString( '.convoca-alert--warning', $css );
		$this->assertMatchesRegularExpression( '/\.convoca-alert\s*>\s*a\s*\{[^}]*color:\s*inherit/s', $css );
		$this->assertMatchesRegularExpression( '/\.convoca-alert\s*>\s*a\s*\{[^}]*text-decoration:\s*underline/s', $css );
	}
}
