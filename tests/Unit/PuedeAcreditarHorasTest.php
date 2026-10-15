<?php
/**
 * Quién puede acreditar horas (issue #30).
 *
 * Decisión de JC (15/10/2026): la aprobación de las horas extra que registra una persona desde su panel la
 * da **un monitor o un administrador**. Hasta ahora solo podía el voluntariado aprobado, así que un monitor
 * —que es quien está delante de la actividad— no podía cerrar la acreditación de nadie.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;
use Convoca\Members\Admin_Voluntariado;

class PuedeAcreditarHorasTest extends TestCase {

	private const USER = 91;

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['_wp_stores']['user_meta'] = array();
		$GLOBALS['_test_users']             = array();
	}

	/**
	 * Usuario de mentira con unos roles.
	 *
	 * @param string[] $roles Roles.
	 * @return void
	 */
	private function con_roles( array $roles ): void {
		$user                  = new Rol_Usuario_Falso( self::USER, $roles );
		$GLOBALS['_test_users'][ self::USER ] = $user;
	}

	public function test_un_monitor_puede_acreditar(): void {
		$this->con_roles( array( 'monitor_actividad' ) );
		$this->assertTrue( Admin_Voluntariado::puede_acreditar_horas( self::USER ) );
	}

	public function test_un_voluntario_aprobado_puede_acreditar(): void {
		$this->con_roles( array( 'voluntario_aprobado' ) );
		$this->assertTrue( Admin_Voluntariado::puede_acreditar_horas( self::USER ) );
	}

	public function test_un_socio_corriente_no_puede_acreditar(): void {
		$this->con_roles( array( 'subscriber', 'socio' ) );
		$this->assertFalse(
			Admin_Voluntariado::puede_acreditar_horas( self::USER ),
			'Ser socio no da permiso para acreditar horas de otras personas'
		);
	}

	public function test_sin_usuario_no_se_puede(): void {
		$this->assertFalse( Admin_Voluntariado::puede_acreditar_horas( 0 ) );
	}
}
