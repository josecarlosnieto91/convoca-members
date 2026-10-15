<?php
/**
 * Un solo limitador para el acceso (issue #14).
 *
 * Medido por la auditoría: había DOS, con la misma clave `login` y umbrales distintos (10/300 en el endpoint,
 * 5/900 en la autenticación), y los dos se llamaban en la misma petición. Un intento contaba doble, el umbral
 * real no era ninguno de los dos, y el mismo endpoint respondía con dos esperas distintas («15 minutos» y «5
 * minutos») alternándose.
 *
 * Aquí se vigilan las dos mitades: que haya **un solo** limitador para el acceso, y que su mensaje diga la
 * espera **de verdad**. Lo segundo porque un mensaje que miente sobre cuánto hay que esperar es lo que hacía
 * que el defecto pareciera un capricho del servidor.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

class UnSoloLimitadorDeAccesoTest extends TestCase {

	private function raiz(): string {
		return dirname( __DIR__, 2 );
	}

	/** Los ficheros PHP del plugin, sin vendor ni tests. */
	private function ficheros(): array {
		$salida = array();
		$iter   = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $this->raiz() ) );
		foreach ( $iter as $f ) {
			$ruta = $f->getPathname();
			if ( 'php' !== strtolower( $f->getExtension() ) ) {
				continue;
			}
			foreach ( array( '/vendor/', '/node_modules/', '/tests/' ) as $fuera ) {
				if ( false !== strpos( $ruta, $fuera ) ) {
					continue 2;
				}
			}
			$salida[] = $ruta;
		}

		return $salida;
	}

	public function test_hay_un_solo_limitador_para_el_acceso(): void {
		$sitios = array();
		foreach ( $this->ficheros() as $ruta ) {
			$txt = (string) file_get_contents( $ruta );
			if ( preg_match_all( "/check_rate_limit\\s*\\(\\s*'login'/", $txt, $m ) ) {
				for ( $i = 0; $i < count( $m[0] ); $i++ ) {
					$sitios[] = str_replace( $this->raiz() . '/', '', $ruta );
				}
			}
		}

		$this->assertCount( 1, $sitios, 'Una sola fuente de verdad para el límite del acceso, y está en: ' . implode( ', ', $sitios ) );
	}

	public function test_el_mensaje_dice_la_espera_real(): void {
		$auth = (string) file_get_contents( $this->raiz() . '/includes/Member_Auth.php' );

		// La constante se escribe como `15 * MINUTE_IN_SECONDS`, no como un número suelto: mi primera versión
		// de esta prueba no sabía leerla y **daba un falso positivo** (leía «15» y se quejaba de que no eran
		// 900). Se lee la expresión entera.
		$this->assertMatchesRegularExpression( '/LOGIN_LOCKOUT_SECONDS\s*=\s*(\d+)\s*\*\s*MINUTE_IN_SECONDS/', $auth, 'El bloqueo se escribe en minutos' );
		preg_match( '/LOGIN_LOCKOUT_SECONDS\s*=\s*(\d+)\s*\*\s*MINUTE_IN_SECONDS/', $auth, $m );
		$minutos = (int) $m[1];

		$this->assertSame( 15, $minutos, 'El bloqueo del acceso son 15 minutos' );
		$this->assertStringContainsString( "espera {$minutos} minutos", $auth, 'Y el mensaje dice los minutos que son de verdad' );
	}

	public function test_el_endpoint_no_lleva_su_propio_limite(): void {
		$rest = (string) file_get_contents( $this->raiz() . '/includes/Rest_API.php' );
		$this->assertStringNotContainsString( "check_rate_limit( 'login' )", $rest );
		$this->assertStringContainsString( 'issue #14', $rest, 'Y queda dicho dónde vive el límite, para que nadie lo vuelva a poner aquí' );
	}
}
