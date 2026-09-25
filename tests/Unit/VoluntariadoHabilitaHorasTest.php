<?php
/**
 * Quién puede acreditar horas de voluntariado.
 *
 * Regla única (opción B, documentada en docs/spec-voluntariado-habilita-horas.md): el compromiso
 * del alta es una **solicitud** y las horas —que valen para renovar sin cuota y para los
 * certificados— exigen la **aprobación** de la asociación. El permiso vive en el usuario de
 * WordPress (rol `voluntario_aprobado` o meta `_convoca_voluntario_aprobado = 1`), que es donde
 * escribe `Admin_Voluntariado`; el post meta de la misma ficha (`_convoca_es_voluntario`) es la
 * solicitud y **no** habilita nada.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;
use Convoca\Members\Admin_Voluntariado;

class VoluntariadoHabilitaHorasTest extends TestCase {

	private const USER = 700;
	private const SIN_CUENTA = 0;

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['_wp_stores']['user_meta'] = array();
		$GLOBALS['_wp_stores']['post_meta'] = array();
		$GLOBALS['_test_users']             = array();

		$path = dirname( __DIR__, 2 ) . '/includes/Admin_Voluntariado.php';
		if ( file_exists( $path ) ) {
			require_once $path;
		}
		$this->usuario( self::USER, array( 'subscriber' ) );
	}

	/** Crea el usuario del mock con sus roles. */
	private function usuario( int $id, array $roles ): void {
		$u               = new \stdClass();
		$u->ID           = $id;
		$u->display_name = 'Voluntario ' . $id;
		$u->user_email   = 'voluntario' . $id . '@ejemplo.org';
		$u->roles        = $roles;
		$GLOBALS['_test_users'][ $id ] = $u;
	}

	private function puede( int $user_id ): bool {
		return Admin_Voluntariado::puede_acreditar_horas( $user_id );
	}

	public function test_sin_cuenta_de_wordpress_no_hay_permiso_posible(): void {
		$this->assertFalse( $this->puede( self::SIN_CUENTA ), 'Sin cuenta WP no hay dónde registrar la aprobación.' );
		$this->assertFalse( $this->puede( -5 ), 'Un ID inválido tampoco habilita.' );
	}

	public function test_un_socio_normal_no_puede_acreditar_horas(): void {
		$this->assertFalse( $this->puede( self::USER ), 'Ser socio no habilita a registrar horas.' );
	}

	public function test_el_compromiso_del_alta_no_habilita_por_si_solo(): void {
		// El alta guarda la solicitud en la FICHA (post meta), no en el usuario.
		update_post_meta( 1234, '_convoca_es_voluntario', '1' );

		$this->assertFalse(
			$this->puede( self::USER ),
			'Marcar el compromiso es una solicitud: sin aprobación no se acreditan horas.'
		);
	}

	public function test_el_voluntariado_pendiente_no_habilita(): void {
		update_user_meta( self::USER, '_convoca_voluntario_aprobado', '0' );

		$this->assertFalse( $this->puede( self::USER ), 'Pendiente de aprobación no es aprobado.' );
	}

	public function test_el_voluntariado_revocado_no_habilita(): void {
		update_user_meta( self::USER, '_convoca_voluntario_aprobado', '-1' );

		$this->assertFalse( $this->puede( self::USER ) );
	}

	public function test_el_voluntariado_aprobado_habilita(): void {
		update_user_meta( self::USER, '_convoca_voluntario_aprobado', '1' );

		$this->assertTrue( $this->puede( self::USER ), 'La aprobación es lo que habilita las horas.' );
	}

	public function test_el_rol_aprobado_habilita_aunque_falte_el_meta(): void {
		$this->usuario( self::USER, array( 'voluntario_aprobado' ) );

		$this->assertTrue( $this->puede( self::USER ), 'El rol que asigna la aprobación también vale.' );
	}

	public function test_tras_aprobar_se_habilita_y_tras_revocar_se_deja_de_poder(): void {
		$this->assertFalse( $this->puede( self::USER ) );

		update_user_meta( self::USER, '_convoca_voluntario_aprobado', '1' );
		$this->usuario( self::USER, array( 'voluntario_aprobado' ) );
		$this->assertTrue( $this->puede( self::USER ) );

		// Revocación: como hace Admin_Voluntariado::revoke_volunteer().
		$this->usuario( self::USER, array( 'subscriber' ) );
		update_user_meta( self::USER, '_convoca_voluntario_aprobado', '-1' );
		$this->assertFalse( $this->puede( self::USER ), 'Revocado: deja de poder acreditar.' );
	}

	public function test_el_permiso_no_depende_de_ser_socio_activo(): void {
		// La condición de socio y el permiso de voluntariado son estados distintos: un voluntario
		// aprobado al que se le marca la ficha como suspendida sigue teniendo el permiso, y el
		// motor de horas no depende del estado de la cuota.
		update_user_meta( self::USER, '_convoca_voluntario_aprobado', '1' );
		update_post_meta( 1234, '_convoca_estado_miembro', 'suspendido' );

		$this->assertTrue( $this->puede( self::USER ), 'El permiso es de voluntariado, no de cuota.' );
	}
}
