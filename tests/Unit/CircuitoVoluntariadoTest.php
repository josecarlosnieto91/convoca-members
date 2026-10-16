<?php
/**
 * El circuito del voluntariado: de la solicitud a la desvinculación.
 *
 * Lo que se protege, con las decisiones de JC del 16/10/2026 delante:
 *
 * - El paso en el que está cada persona se puede leer y **sigue a la aprobación**: si alguien aprueba por su
 *   cuenta, el circuito no puede seguir diciendo «solicitud».
 * - La entrevista se cita con fecha y hora, y se resuelve con el cuestionario contestado o marcándola hecha:
 *   **solamente a partir de ahí** la persona es voluntaria.
 * - Se puede ser voluntario, o voluntario y monitor; **nunca monitor solo**.
 * - El acuerdo lo acepta la persona, y queda con su fecha y su huella.
 * - La desvinculación guarda su causa y su fecha, y **no toca las horas** ni el certificado.
 * - Cada paso queda en el historial, con quién lo movió: para poder responder «¿por qué esta persona es
 *   voluntaria hoy y ayer no?».
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Admin_Voluntariado;
use Convoca\Members\Circuito_Voluntariado;
use Convoca\Members\Roles_Miembro;
use PHPUnit\Framework\TestCase;

class CircuitoVoluntariadoTest extends TestCase {

	private const ID   = 6161;
	private const USER = 99;

	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['_wp_stores']['post_meta'] = array();
		$GLOBALS['_wp_stores']['user_meta'] = array();
		$GLOBALS['_wp_stores']['options']   = array();
		$GLOBALS['_wp_stores']['emails']    = array();

		foreach ( array( 'includes/Roles_Miembro.php', 'includes/Admin_Voluntariado.php', 'includes/Circuito_Voluntariado.php' ) as $fichero ) {
			$ruta = dirname( __DIR__, 2 ) . '/' . $fichero;
			if ( file_exists( $ruta ) ) {
				require_once $ruta;
			}
		}
	}

	/**
	 * Una ficha con la solicitud de voluntariado hecha.
	 *
	 * @param string $aprobado '0' = pendiente, '1' = aprobada.
	 */
	private function ficha( string $aprobado = '0' ): int {
		update_post_meta( self::ID, '_convoca_user_id', (string) self::USER );
		update_post_meta( self::ID, Roles_Miembro::META_VOLUNTARIO, '1' );
		update_user_meta( self::USER, Admin_Voluntariado::META_APROBADO, $aprobado );

		return self::ID;
	}

	/** Una solicitud, sin más, está en el primer paso. */
	public function test_una_solicitud_esta_en_el_primer_paso(): void {
		$this->ficha();

		$this->assertSame( Circuito_Voluntariado::ESTADO_SOLICITUD, Circuito_Voluntariado::estado( self::ID ) );
		$this->assertContains( 'Citar para la entrevista inicial', Circuito_Voluntariado::faltantes( self::ID ) );
	}

	/** Y si la aprueba alguien por su cuenta, el circuito no se queda diciendo «solicitud». */
	public function test_el_paso_sigue_a_la_aprobacion(): void {
		$this->ficha( '1' );

		$this->assertSame( Circuito_Voluntariado::ESTADO_ACTIVO, Circuito_Voluntariado::estado( self::ID ) );
	}

	/** La cita: con fecha y hora, y el paso avanza. */
	public function test_la_entrevista_se_cita_con_fecha_y_hora(): void {
		$this->ficha();

		$this->assertTrue( Circuito_Voluntariado::citar_entrevista( self::ID, '2026-11-05', '18:30' ) );

		$cita = Circuito_Voluntariado::entrevista( self::ID );
		$this->assertSame( '2026-11-05', $cita['fecha'] );
		$this->assertSame( '18:30', $cita['hora'] );
		$this->assertSame( Circuito_Voluntariado::ESTADO_ENTREVISTA, Circuito_Voluntariado::estado( self::ID ) );
		$this->assertStringContainsString( '2026-11-05', implode( ' ', Circuito_Voluntariado::faltantes( self::ID ) ) );
	}

	/** Con el cuestionario contestado —o marcada como hecha— la persona **es** voluntaria. */
	public function test_la_entrevista_hecha_hace_voluntaria_a_la_persona(): void {
		$this->ficha();
		$this->assertFalse( Roles_Miembro::es_voluntario( self::ID ) );

		$this->assertTrue( Circuito_Voluntariado::resolver_entrevista( self::ID, array( '¿Por qué quieres colaborar?' => 'Porque vivo al lado.' ) ) );

		$this->assertTrue( Roles_Miembro::es_voluntario( self::ID ), 'Ya es voluntaria' );
		$this->assertTrue( Roles_Miembro::voluntario_aprobado( self::ID ), 'Y puede acreditar horas' );
		$this->assertSame( Circuito_Voluntariado::ESTADO_ACTIVO, Circuito_Voluntariado::estado( self::ID ) );
		$this->assertSame(
			array( '¿Por qué quieres colaborar?' => 'Porque vivo al lado.' ),
			Circuito_Voluntariado::respuestas( self::ID ),
			'Y sus respuestas quedan guardadas'
		);
	}

	/** Con monitor, además: las dos condiciones juntas. */
	public function test_la_entrevista_puede_conceder_voluntariado_y_monitorado(): void {
		$this->ficha();

		Circuito_Voluntariado::resolver_entrevista( self::ID, array(), true );

		$this->assertSame( array( 'voluntario', 'monitor' ), Roles_Miembro::roles( self::ID ) );
	}

	/** Pero monitor solo, nunca. */
	public function test_no_hay_monitor_sin_voluntariado(): void {
		$this->ficha();

		$this->assertFalse( Circuito_Voluntariado::conceder_monitor( self::ID ) );
		$this->assertNotContains( 'monitor', Roles_Miembro::roles( self::ID ) );
	}

	/** El acuerdo lo acepta la persona, una sola vez, y queda con su fecha. */
	public function test_el_acuerdo_se_acepta_una_vez_y_queda_registrado(): void {
		$this->ficha( '1' );

		$this->assertFalse( Circuito_Voluntariado::acuerdo_aceptado( self::ID ) );
		$this->assertTrue( Circuito_Voluntariado::aceptar_acuerdo( self::ID ) );
		$this->assertFalse( Circuito_Voluntariado::aceptar_acuerdo( self::ID ), 'Aceptarlo dos veces no aporta nada' );

		$acuerdo = Circuito_Voluntariado::acuerdo( self::ID );
		$this->assertNotSame( '', $acuerdo['fecha'] );
		$this->assertNotSame( '', $acuerdo['huella'] );
		$this->assertStringNotContainsString(
			'Firmar el acuerdo de voluntariado',
			implode( ' ', Circuito_Voluntariado::faltantes( self::ID ) ),
			'Ya no le queda por firmar'
		);
	}

	/** La desvinculación guarda su causa, su fecha y su motivo. */
	public function test_la_desvinculacion_guarda_causa_y_fecha(): void {
		$this->ficha( '1' );
		update_post_meta( self::ID, '_convoca_horas_aprobadas', '37' );

		$this->assertTrue( Circuito_Voluntariado::desvincular( self::ID, Circuito_Voluntariado::CAUSA_JUNTA, 'Se traslada de ciudad.' ) );

		$baja = Circuito_Voluntariado::desvinculacion( self::ID );
		$this->assertSame( Circuito_Voluntariado::CAUSA_JUNTA, $baja['causa'] );
		$this->assertSame( 'Se traslada de ciudad.', $baja['motivo'] );
		$this->assertNotSame( '', $baja['fecha'] );
		$this->assertSame( Circuito_Voluntariado::ESTADO_DESVINCULADO, Circuito_Voluntariado::estado( self::ID ) );

		// Y lo que importa: sus horas siguen ahí. El certificado se descarga y se valida igual.
		$this->assertSame( '37', get_post_meta( self::ID, '_convoca_horas_aprobadas', true ) );
		$this->assertSame( array(), Circuito_Voluntariado::faltantes( self::ID ), 'Ya no le queda nada por hacer' );
	}

	/** La baja que pide la propia persona no la da de baja: la confirma la junta. */
	public function test_la_baja_la_pide_el_voluntario_y_la_confirma_la_junta(): void {
		$this->ficha( '1' );

		$this->assertTrue( Circuito_Voluntariado::solicitar_desvinculacion( self::ID, 'Motivos personales.' ) );

		$this->assertTrue( Circuito_Voluntariado::baja_solicitada( self::ID ) );
		$this->assertSame( Circuito_Voluntariado::ESTADO_ACTIVO, Circuito_Voluntariado::estado( self::ID ), 'Sigue en activo hasta que se confirme' );
		$this->assertStringContainsString( 'Confirmar la baja', implode( ' ', Circuito_Voluntariado::faltantes( self::ID ) ) );

		Circuito_Voluntariado::desvincular( self::ID, Circuito_Voluntariado::CAUSA_VOLUNTARIO );
		$this->assertFalse( Circuito_Voluntariado::baja_solicitada( self::ID ), 'Al confirmarla, la petición se cierra' );
	}

	/** Cada paso queda apuntado con quién lo movió. */
	public function test_el_historial_cuenta_quien_movio_cada_paso(): void {
		$this->ficha();
		Circuito_Voluntariado::citar_entrevista( self::ID, '2026-11-05', '18:30' );
		Circuito_Voluntariado::resolver_entrevista( self::ID );

		$historial = Circuito_Voluntariado::historial( self::ID );

		$this->assertCount( 2, $historial );
		$this->assertSame( Circuito_Voluntariado::ESTADO_ENTREVISTA, $historial[0]['paso'] );
		$this->assertSame( Circuito_Voluntariado::ESTADO_ACTIVO, $historial[1]['paso'] );
		foreach ( $historial as $apunte ) {
			$this->assertNotSame( '', $apunte['fecha'], 'Cada paso dice cuándo' );
		}
	}

	/** Las preguntas de la entrevista son de cada organización, y salen de los ajustes. */
	public function test_las_preguntas_de_la_entrevista_salen_de_los_ajustes(): void {
		$this->assertSame( array(), Circuito_Voluntariado::preguntas(), 'Sin preguntas configuradas no hay cuestionario' );

		update_option(
			'convoca_members_settings',
			array( 'entrevista_preguntas' => "¿Por qué quieres colaborar?\n¿Qué disponibilidad tienes?\n\n" )
		);

		$this->assertSame(
			array( '¿Por qué quieres colaborar?', '¿Qué disponibilidad tienes?' ),
			Circuito_Voluntariado::preguntas(),
			'Una por línea, y las líneas vacías no cuentan'
		);
	}
}
