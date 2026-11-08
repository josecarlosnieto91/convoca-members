<?php
/**
 * Lo que el servidor no puede dejar pasar en el alta: menores sin tutor, método de pago que el plan no
 * admite, y un error que dice a qué campo corresponde (#93, #94, #96, #100).
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Formato;
use Convoca\Members\Process_Member;
use PHPUnit\Framework\TestCase;

class AltaValidacionMinimoTest extends TestCase {

	/**
	 * Alta completa de una persona adulta.
	 *
	 * @param array<string, string> $cambios Campos que se sustituyen.
	 * @return array<string, string>
	 */
	private function alta( array $cambios = array() ): array {
		return array_merge(
			array(
				'nombre'           => 'Ada Lovelace',
				'dni'              => '00000000T',
				'email'            => 'ada@example.test',
				'fecha_nacimiento' => '1990-01-01',
				'telefono'         => '600000000',
				'direccion'        => 'Calle de prueba 1',
				'municipio'        => 'Oviedo',
				'plan'             => 'bronze',
				'forma_pago'       => 'transferencia',
				'rgpd'             => '1',
			),
			$cambios
		);
	}

	/**
	 * Un menor sin tutor no se da de alta.
	 */
	public function test_un_menor_necesita_tutor(): void {
		$error = Process_Member::validate( $this->alta( array( 'fecha_nacimiento' => '2012-01-01' ) ) );

		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertStringContainsString( 'nombre del tutor', $error->get_error_message() );
		$this->assertStringContainsString( 'DNI del tutor', $error->get_error_message() );

		$campos = $error->get_error_data()['campos'] ?? array();
		$this->assertArrayHasKey( 'tutor_nombre', $campos );
		$this->assertArrayHasKey( 'tutor_dni', $campos );
	}

	/**
	 * El DNI del tutor se comprueba con su letra.
	 */
	public function test_el_dni_del_tutor_se_valida(): void {
		$error = Process_Member::validate(
			$this->alta(
				array(
					'fecha_nacimiento' => '2012-01-01',
					'tutor_nombre'     => 'Marta Tutora',
					'tutor_dni'        => '00000000A',
				)
			)
		);

		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertStringContainsString( 'DNI del tutor o tutora no es válido', $error->get_error_message() );
	}

	/**
	 * Con tutor y DNI correcto, el alta de un menor pasa.
	 */
	public function test_un_menor_con_tutor_valido_pasa(): void {
		$this->assertTrue(
			Process_Member::validate(
				$this->alta(
					array(
						'fecha_nacimiento' => '2012-01-01',
						'tutor_nombre'     => 'Marta Tutora',
						'tutor_dni'        => '00000000T',
					)
				)
			)
		);
	}

	/**
	 * Un método de pago que el plan no admite se rechaza antes de crear la ficha.
	 */
	public function test_el_metodo_de_pago_tiene_que_estar_en_el_plan(): void {
		$error = Process_Member::validate( $this->alta( array( 'forma_pago' => 'criptomoneda' ) ) );

		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertStringContainsString( 'no está disponible para tu cuota', $error->get_error_message() );
	}

	/**
	 * El voluntariado no es un cobro: se admite aunque el plan no lo liste como pasarela.
	 */
	public function test_el_voluntariado_no_es_un_metodo_de_pago(): void {
		$this->assertTrue( Process_Member::validate( $this->alta( array( 'forma_pago' => 'voluntariado' ) ) ) );
	}

	/**
	 * Un plan que el formulario no ofrece no se puede dar de alta por la API (#103).
	 */
	public function test_el_plan_voluntariado_no_es_una_cuota(): void {
		// El formulario ofrece las modalidades Numerario, Familiar y Juvenil; «voluntariado» es una
		// modalidad interna, no una cuota elegible.
		$this->assertFalse( Process_Member::plan_ofertado( 'voluntariado' ) );
		$this->assertTrue( Process_Member::plan_ofertado( 'bronze' ) );

		// Y el motor lo rechaza antes de crear nada, que es lo que hacía la API (#103).
		$motor = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Process_Member.php' );
		$this->assertStringContainsString( 'if ( ! self::plan_ofertado( $plan_key ) )', $motor );
	}

	/**
	 * El error dice qué campo corregir, para que el formulario pueda volver a ese paso (#96).
	 */
	public function test_el_error_dice_el_campo(): void {
		$error = Process_Member::validate( $this->alta( array( 'telefono' => '12345' ) ) );

		$this->assertInstanceOf( \WP_Error::class, $error );
		$campos = $error->get_error_data()['campos'] ?? array();
		$this->assertArrayHasKey( 'telefono', $campos );
	}

	/**
	 * Con una sola modalidad no se dice «Desde» (#100).
	 */
	public function test_una_sola_modalidad_no_dice_desde(): void {
		$this->assertSame( '45€', Formato::precio( array( array( 'price' => 45 ) ) ) );
		$this->assertSame( 'Desde 30€', Formato::precio( array( array( 'price' => 30 ), array( 'price' => 60 ) ) ) );
	}

	/**
	 * Las horas van con coma, no con punto (#100).
	 */
	public function test_las_horas_van_con_coma(): void {
		$this->assertSame( '22,5', Formato::horas( 22.5 ) );
		$this->assertSame( '20', Formato::horas( 20 ) );
		$this->assertSame( '7,5', Formato::horas( '7.50' ) );
	}
}
