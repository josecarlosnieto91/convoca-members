<?php
/**
 * Enlace de justificante: firma y caducidad de 3 días (members#86).
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Justificante;
use PHPUnit\Framework\TestCase;

/**
 * El enlace no puede servir para siempre ni para cualquiera.
 */
final class JustificanteTest extends TestCase {

	/**
	 * Un token recién emitido vale.
	 */
	public function test_el_token_recien_emitido_vale(): void {
		$token = Justificante::token( 42 );

		$this->assertNotSame( '', $token );
		$this->assertTrue( Justificante::token_valido( 42, $token ) );
	}

	/**
	 * El token de un socio no vale para otro.
	 */
	public function test_el_token_no_sirve_para_otro_socio(): void {
		$token = Justificante::token( 42 );

		$this->assertFalse( Justificante::token_valido( 43, $token ) );
	}

	/**
	 * Un token manipulado no vale.
	 */
	public function test_un_token_manipulado_no_vale(): void {
		$token = Justificante::token( 42 );
		$partes = explode( '.', $token );

		$this->assertFalse( Justificante::token_valido( 42, ( (int) $partes[0] + 3600 ) . '.' . $partes[1] ) );
		$this->assertFalse( Justificante::token_valido( 42, $partes[0] . '.' . str_repeat( 'a', 64 ) ) );
	}

	/**
	 * Un token caducado no vale.
	 */
	public function test_un_token_caducado_no_vale(): void {
		$expira = time() - 60;
		$token  = $expira . '.' . hash_hmac( 'sha256', '42|' . $expira, wp_salt( 'auth' ) );

		$this->assertFalse( Justificante::token_valido( 42, $token ) );
	}

	/**
	 * La vigencia declarada son tres días.
	 */
	public function test_la_vigencia_es_de_tres_dias(): void {
		$this->assertSame( 259200, Justificante::VIDA );
	}

	/**
	 * Sin token, el formulario no se pinta.
	 */
	public function test_sin_token_no_hay_formulario(): void {
		$_GET = array();

		$html = Justificante::render();

		$this->assertStringContainsString( 'no es válido o ha caducado', $html );
		$this->assertStringNotContainsString( '<form', $html );
	}
}
