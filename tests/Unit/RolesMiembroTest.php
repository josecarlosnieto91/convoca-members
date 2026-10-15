<?php
/**
 * Real behavioral tests for Convoca Members — Roles_Miembro.
 *
 * Lo que se protege aquí es lo que estaba roto: que las condiciones de socio y
 * voluntario se SUMAN (un socio puede ser voluntario a la vez) y que el espejo al
 * usuario de WordPress usa add_role/remove_role, nunca set_role.
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;
use Convoca\Members\Roles_Miembro;

/**
 * Usuario de WordPress de mentira, con la misma interfaz que importa aquí.
 */
class Rol_Usuario_Falso {

	/** @var int */
	public $ID;

	/** @var string */
	public $user_email;

	/** @var string[] */
	public $roles;

	/** @var string[] Llamadas recibidas, para poder afirmar cómo se tocó. */
	public $llamadas = array();

	/**
	 * @param int      $id    ID.
	 * @param string[] $roles Roles iniciales.
	 */
	public function __construct( int $id, array $roles ) {
		$this->ID         = $id;
		$this->roles      = $roles;
		$this->user_email = "usuario{$id}@example.com";
	}

	/**
	 * @param string $rol Rol que se añade.
	 * @return void
	 */
	public function add_role( string $rol ): void {
		$this->llamadas[] = 'add_role:' . $rol;
		if ( ! in_array( $rol, $this->roles, true ) ) {
			$this->roles[] = $rol;
		}
	}

	/**
	 * @param string $rol Rol que se quita.
	 * @return void
	 */
	public function remove_role( string $rol ): void {
		$this->llamadas[] = 'remove_role:' . $rol;
		$this->roles      = array_values( array_diff( $this->roles, array( $rol ) ) );
	}

	/**
	 * Existe en WP_User; si se llamara, este test lo delataría.
	 *
	 * @param string $rol Rol nuevo.
	 * @return void
	 */
	public function set_role( string $rol ): void {
		$this->llamadas[] = 'set_role:' . $rol;
		$this->roles      = array( $rol );
	}
}

class RolesMiembroTest extends TestCase {

	private const ID   = 4242;
	private const USER = 77;

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['_wp_stores']['post_meta'] = array();
		$GLOBALS['_wp_stores']['user_meta'] = array();
		$GLOBALS['_wp_stores']['options']   = array();
		$GLOBALS['_test_users']             = array();

		$path = dirname( __DIR__, 2 ) . '/includes/Roles_Miembro.php';
		if ( file_exists( $path ) ) {
			require_once $path;
		}
	}

	/**
	 * Deja la ficha vinculada al usuario de mentira.
	 *
	 * @param string[] $roles Roles iniciales del usuario.
	 * @return Rol_Usuario_Falso
	 */
	private function con_usuario( array $roles = array( 'subscriber' ) ): Rol_Usuario_Falso {
		\update_post_meta( self::ID, '_convoca_user_id', (string) self::USER );
		$user                          = new Rol_Usuario_Falso( self::USER, $roles );
		$GLOBALS['_test_users'][ self::USER ] = $user;
		return $user;
	}

	// ── Lo central: los roles se suman ──────────────────────────────

	public function test_una_ficha_nueva_no_tiene_ningun_rol(): void {
		$this->assertSame( array(), Roles_Miembro::roles( self::ID ) );
		$this->assertFalse( Roles_Miembro::es_socio( self::ID ) );
		$this->assertFalse( Roles_Miembro::es_voluntario( self::ID ) );
	}

	public function test_un_socio_puede_ser_voluntario_a_la_vez(): void {
		Roles_Miembro::anadir( self::ID, Roles_Miembro::SOCIO );
		Roles_Miembro::anadir( self::ID, Roles_Miembro::VOLUNTARIO );

		$this->assertTrue( Roles_Miembro::es_socio( self::ID ) );
		$this->assertTrue( Roles_Miembro::es_voluntario( self::ID ) );
		$this->assertTrue( Roles_Miembro::tiene_doble_rol( self::ID ) );
		$this->assertSame( array( 'socio', 'voluntario' ), Roles_Miembro::roles( self::ID ) );
	}

	public function test_quitar_un_rol_no_toca_el_otro(): void {
		Roles_Miembro::anadir( self::ID, Roles_Miembro::SOCIO );
		Roles_Miembro::anadir( self::ID, Roles_Miembro::VOLUNTARIO );

		Roles_Miembro::quitar( self::ID, Roles_Miembro::VOLUNTARIO );

		$this->assertTrue( Roles_Miembro::es_socio( self::ID ), 'Quitar el voluntariado no puede quitar el socio.' );
		$this->assertFalse( Roles_Miembro::es_voluntario( self::ID ) );
	}

	public function test_revocar_el_voluntariado_no_deja_al_socio_sin_su_condicion(): void {
		Roles_Miembro::anadir( self::ID, Roles_Miembro::SOCIO );
		Roles_Miembro::anadir( self::ID, Roles_Miembro::VOLUNTARIO );
		Roles_Miembro::quitar( self::ID, Roles_Miembro::VOLUNTARIO );

		$this->assertTrue( Roles_Miembro::es_socio( self::ID ) );
	}

	// ── El espejo al usuario de WordPress ───────────────────────────

	public function test_el_espejo_anade_y_quita_roles_sin_pisar(): void {
		$user = $this->con_usuario( array( 'subscriber' ) );

		Roles_Miembro::anadir( self::ID, Roles_Miembro::VOLUNTARIO );
		$this->assertContains( 'voluntario_aprobado', $user->roles );
		$this->assertContains( 'subscriber', $user->roles, 'El rol que ya tenía no se puede perder.' );
		$this->assertContains( 'add_role:voluntario_aprobado', $user->llamadas );

		Roles_Miembro::quitar( self::ID, Roles_Miembro::VOLUNTARIO );
		$this->assertNotContains( 'voluntario_aprobado', $user->roles );
		$this->assertContains( 'subscriber', $user->roles );

		foreach ( $user->llamadas as $llamada ) {
			$this->assertStringNotContainsString( 'set_role', $llamada, 'set_role() sustituye todos los roles: aquí no se usa.' );
		}
	}

	public function test_al_quitar_el_voluntariado_sin_mas_roles_queda_el_por_defecto(): void {
		$user = $this->con_usuario( array( 'voluntario_aprobado' ) );

		Roles_Miembro::anadir( self::ID, Roles_Miembro::VOLUNTARIO );
		Roles_Miembro::quitar( self::ID, Roles_Miembro::VOLUNTARIO );

		$this->assertSame( array( 'subscriber' ), $user->roles, 'Sin roles no puede quedarse.' );
	}

	public function test_sin_usuario_vinculado_no_se_toca_nada(): void {
		$GLOBALS['_test_users'] = array();
		Roles_Miembro::anadir( self::ID, Roles_Miembro::VOLUNTARIO );

		$this->assertTrue( Roles_Miembro::es_voluntario( self::ID ), 'La condición va en la ficha aunque no haya usuario.' );
	}

	// ── Las dos vías de cumplimiento ────────────────────────────────

	public function test_socio_por_pago_no_pierde_el_voluntariado(): void {
		$this->con_usuario();
		Roles_Miembro::anadir( self::ID, Roles_Miembro::VOLUNTARIO );

		$this->assertTrue( Roles_Miembro::registrar_socio_por_pago( self::ID ) );

		$this->assertTrue( Roles_Miembro::es_socio( self::ID ) );
		$this->assertTrue( Roles_Miembro::es_voluntario( self::ID ) );
		$this->assertSame( 'pago', Roles_Miembro::cumplimiento( self::ID ) );
	}

	public function test_cumplimiento_por_horas(): void {
		Roles_Miembro::anadir( self::ID, Roles_Miembro::SOCIO );

		$this->assertTrue( Roles_Miembro::registrar_cumplimiento_por_horas( self::ID ) );
		$this->assertSame( 'horas', Roles_Miembro::cumplimiento( self::ID ) );
		$this->assertTrue( Roles_Miembro::ciclo_cumplido( self::ID ) );
		$this->assertTrue( Roles_Miembro::es_socio( self::ID ) );
	}

	public function test_via_de_cumplimiento_invalida_no_cuela(): void {
		$this->assertFalse( Roles_Miembro::marcar_cumplimiento( self::ID, 'lo-que-sea' ) );
		$this->assertSame( '', Roles_Miembro::cumplimiento( self::ID ) );
	}

	public function test_reiniciar_ciclo_borra_banderas_y_abre_periodo(): void {
		Roles_Miembro::anadir( self::ID, Roles_Miembro::SOCIO );
		\update_post_meta( self::ID, '_convoca_objetivo_horas_completado', '1' );
		Roles_Miembro::marcar_cumplimiento( self::ID, 'horas' );

		Roles_Miembro::reiniciar_ciclo( self::ID );

		$this->assertSame( '', Roles_Miembro::cumplimiento( self::ID ) );
		$this->assertSame( '', \get_post_meta( self::ID, '_convoca_objetivo_horas_completado', true ) );
		$this->assertNotSame( '', \get_post_meta( self::ID, '_convoca_fecha_inicio_periodo', true ), 'El ciclo nuevo empieza hoy.' );
		$this->assertTrue( Roles_Miembro::es_socio( self::ID ), 'Reiniciar el ciclo no cambia los roles.' );
	}

	// ── Y lo de siempre ─────────────────────────────────────────────

	public function test_roles_invalidos_no_hacen_nada(): void {
		$this->assertFalse( Roles_Miembro::anadir( self::ID, 'presidente' ) );
		$this->assertFalse( Roles_Miembro::quitar( self::ID, 'presidente' ) );
		$this->assertFalse( Roles_Miembro::tiene( self::ID, 'presidente' ) );
		// Al adoptar el borrador se añadió el monitor como tercera condición acumulable (issue #30).
		$this->assertSame( array( 'socio', 'voluntario', 'monitor' ), Roles_Miembro::roles_validos() );
	}

	public function test_ids_invalidos_no_hacen_nada(): void {
		$this->assertFalse( Roles_Miembro::anadir( 0, Roles_Miembro::SOCIO ) );
		$this->assertFalse( Roles_Miembro::es_socio( 0 ) );
		$this->assertSame( array(), Roles_Miembro::roles( 0 ) );
	}
}
