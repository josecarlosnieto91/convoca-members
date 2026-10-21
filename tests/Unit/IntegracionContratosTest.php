<?php
/**
 * Members contesta los contratos y no crea roles.
 *
 * @package Convoca\Members
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Integracion_Contratos;
use PHPUnit\Framework\TestCase;

/**
 * El reparto de responsabilidades (JC, 21/10/2026):
 *
 *  - **El vocabulario es del producto**: los roles `socio`, `voluntario_aprobado` y `monitor_actividad` los
 *    crea Convoca Core. Members administra **quién** los tiene, no el rol.
 *  - **Las personas son de Members**: quién es voluntario aprobado, quién es monitor y quién puede acreditar
 *    horas lo dice él, con sus fichas y sus aprobaciones, contestando a los contratos del núcleo. Antes lo
 *    averiguaba cada plugin leyendo roles por su cuenta, y por eso en un sitio sin gestión de personas
 *    aparecían personas asignables igual.
 *
 * Lo que se comprueba aquí es la DELEGACIÓN y la forma de la respuesta. El contenido depende de las fichas
 * y de las aprobaciones, y eso se prueba en la suite de roles con usuarios de verdad; el doble genérico de
 * `get_users()` del harness devuelve una lista fija, así que afirmar contenidos concretos aquí sería medir
 * el doble y no el plugin.
 */
class IntegracionContratosTest extends TestCase {

	protected function setUp(): void {
		require_once dirname( __DIR__, 2 ) . '/includes/Integracion_Contratos.php';
	}

	public function test_la_puerta_de_horas_es_la_de_members_no_la_del_nucleo(): void {
		// El núcleo pregunta con su valor por defecto (true/false) y Members responde con SU regla: un
		// usuario que no existe no acredita nunca, diga lo que diga quien pregunta.
		$this->assertFalse( Integracion_Contratos::filtrar_puede_acreditar_horas( true, 0 ) );
		$this->assertFalse( Integracion_Contratos::filtrar_puede_acreditar_horas( true, -5 ) );
	}

	public function test_responde_con_lista_bien_formada_en_los_dos_contextos(): void {
		foreach ( array( 'voluntario', 'monitor' ) as $contexto ) {
			$personas = Integracion_Contratos::filtrar_personas_asignables( array(), $contexto );

			$this->assertIsArray( $personas, "el contexto {$contexto} debe devolver una lista" );

			foreach ( $personas as $persona ) {
				$this->assertArrayHasKey( 'id', $persona );
				$this->assertArrayHasKey( 'nombre', $persona );
				$this->assertIsInt( $persona['id'] );
				$this->assertGreaterThan( 0, $persona['id'], 'una persona sin id no es asignable' );
			}
		}
	}

	public function test_no_devuelve_la_misma_lista_para_los_dos_contextos(): void {
		// Si los dos contextos devolvieran lo mismo, el selector de monitores ofrecería voluntarios (y al
		// revés). Se comprueba que la consulta cambia de rol, no el resultado concreto.
		$fuente = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Integracion_Contratos.php' );

		$this->assertStringContainsString( "'voluntario_aprobado'", $fuente );
		$this->assertStringContainsString( "'monitor_actividad'", $fuente );
		$this->assertStringContainsString( "'monitor' === \$contexto", $fuente );
	}

	public function test_members_ya_no_crea_roles(): void {
		$ficheros = array(
			'convoca-members.php',
			'includes/Roles_Miembro.php',
		);

		foreach ( $ficheros as $fichero ) {
			$fuente = (string) file_get_contents( dirname( __DIR__, 2 ) . '/' . $fichero );
			$lineas = array_filter(
				explode( "\n", $fuente ),
				static function ( $linea ) {
					$t = ltrim( $linea );
					return 0 !== strpos( $t, '*' ) && 0 !== strpos( $t, '//' ) && 0 !== strpos( $t, '/*' );
				}
			);

			foreach ( $lineas as $numero => $linea ) {
				// `add_role()` global crea un rol (vocabulario de Core); `$user->add_role()` asigna el rol a
				// una persona, y eso sí es de Members. Por eso se descarta el método del usuario.
				if ( preg_match( '/(?<!->)\badd_role\s*\(/', $linea ) ) {
					$this->fail( "{$fichero}:" . ( $numero + 1 ) . ' vuelve a crear un rol: el vocabulario es de Core.' );
				}
			}
		}

		$this->assertTrue( true );
	}
}
