<?php
/**
 * Socio, voluntario y monitor: tres condiciones que se suman (issue #30).
 *
 * Lo que se protege: que el rol `socio` sea un rol de WordPress de verdad —no una meta suelta, que era lo
 * que había—, que el monitor sea una tercera condición acumulable, y que las tres se añadan y se quiten sin
 * pisarse. La causa raíz del bloque era `set_role()`, que sustituye TODOS los roles del usuario: aprobar el
 * voluntariado le quitaba al socio su condición. El doble de usuario delata cualquier llamada a `set_role`.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;
use Convoca\Members\Roles_Miembro;

class RolSocioMonitorTest extends TestCase {

	private const ID   = 5252;
	private const USER = 88;

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
	 * @return Rol_Usuario_Falso
	 */
	private function con_usuario(): Rol_Usuario_Falso {
		\update_post_meta( self::ID, '_convoca_user_id', (string) self::USER );
		$user                                 = new Rol_Usuario_Falso( self::USER, array( 'subscriber' ) );
		$GLOBALS['_test_users'][ self::USER ] = $user;

		return $user;
	}

	public function test_las_tres_condiciones_son_validas(): void {
		$this->assertSame( array( 'socio', 'voluntario', 'monitor' ), Roles_Miembro::roles_validos() );
	}

	public function test_el_socio_es_un_rol_de_wordpress_de_verdad(): void {
		$this->assertSame( 'socio', Roles_Miembro::ROL_WP_SOCIO );
		$this->assertSame( 'socio', Roles_Miembro::rol_wp_de( Roles_Miembro::SOCIO ) );
		$this->assertSame( 'voluntario_aprobado', Roles_Miembro::rol_wp_de( Roles_Miembro::VOLUNTARIO ) );
		$this->assertSame( 'monitor_actividad', Roles_Miembro::rol_wp_de( Roles_Miembro::MONITOR ) );
		$this->assertSame( '', Roles_Miembro::rol_wp_de( 'inventado' ) );
	}

	public function test_las_tres_condiciones_se_suman_en_el_usuario(): void {
		$user = $this->con_usuario();

		Roles_Miembro::anadir( self::ID, Roles_Miembro::SOCIO );
		Roles_Miembro::anadir( self::ID, Roles_Miembro::VOLUNTARIO );
		Roles_Miembro::anadir( self::ID, Roles_Miembro::MONITOR );

		$this->assertSame(
			array( 'socio', 'voluntario', 'monitor' ),
			Roles_Miembro::roles( self::ID )
		);
		$this->assertContains( 'socio', $user->roles );
		$this->assertContains( 'voluntario_aprobado', $user->roles );
		$this->assertContains( 'monitor_actividad', $user->roles );
		// Y se le dio de alta con add_role: ninguna llamada a set_role, que sería la que pisa.
		$this->assertSame(
			array( 'add_role:socio', 'add_role:voluntario_aprobado', 'add_role:monitor_actividad' ),
			$user->llamadas
		);
	}

	public function test_quitar_una_condicion_no_toca_las_otras(): void {
		$user = $this->con_usuario();

		Roles_Miembro::anadir( self::ID, Roles_Miembro::SOCIO );
		Roles_Miembro::anadir( self::ID, Roles_Miembro::VOLUNTARIO );
		Roles_Miembro::anadir( self::ID, Roles_Miembro::MONITOR );

		Roles_Miembro::quitar( self::ID, Roles_Miembro::VOLUNTARIO );

		$this->assertSame( array( 'socio' ), Roles_Miembro::roles( self::ID ) );
		$this->assertNotContains( 'voluntario_aprobado', $user->roles );
		$this->assertContains( 'socio', $user->roles, 'La condición de socio no se toca' );
	}

	/**
	 * «Solo voluntario o voluntario y monitor, pero nunca monitor solo» (JC, 16/10/2026).
	 *
	 * La regla se comprueba donde nace la condición, así que da igual el camino: la ficha no queda marcada
	 * como monitor y el rol de WordPress —el que miran Enroll y Shifts— tampoco se concede.
	 */
	public function test_nunca_monitor_solo(): void {
		$user = $this->con_usuario();

		$this->assertFalse( Roles_Miembro::anadir( self::ID, Roles_Miembro::MONITOR ), 'Sin voluntariado no se concede' );
		$this->assertNotContains( 'monitor', Roles_Miembro::roles( self::ID ) );
		$this->assertNotContains( 'monitor_actividad', $user->roles );

		// Con voluntariado delante, sí.
		Roles_Miembro::anadir( self::ID, Roles_Miembro::VOLUNTARIO );
		$this->assertTrue( Roles_Miembro::anadir( self::ID, Roles_Miembro::MONITOR ) );
		$this->assertSame( array( 'voluntario', 'monitor' ), Roles_Miembro::roles( self::ID ) );
		$this->assertContains( 'monitor_actividad', $user->roles );
	}

	public function test_el_ultimo_rol_quitado_deja_el_por_defecto(): void {
		$user = $this->con_usuario();

		Roles_Miembro::anadir( self::ID, Roles_Miembro::MONITOR );
		Roles_Miembro::quitar( self::ID, Roles_Miembro::MONITOR );

		$this->assertSame( array(), Roles_Miembro::roles( self::ID ) );
		$this->assertContains( 'subscriber', $user->roles, 'Sin condiciones no se le deja sin rol' );
	}

	public function test_crear_roles_no_revienta_sin_las_funciones_de_rol(): void {
		// En el arnés de pruebas no existen add_role/get_role: el guardia tiene que salir sin ruido.
		Roles_Miembro::crear_roles();
		$this->assertTrue( true );
	}

	public function test_una_ficha_antigua_no_pierde_su_rol_de_voluntario(): void {
		// Fichas aprobadas antes de que existiera la marca: el rol de WordPress es la única prueba.
		$user = new Rol_Usuario_Falso( self::USER, array( 'subscriber', 'voluntario_aprobado' ) );
		$GLOBALS['_test_users'][ self::USER ] = $user;
		\update_post_meta( self::ID, '_convoca_user_id', (string) self::USER );

		// Cualquier sincronización (aquí, conceder la condición de socio) no debe tocarle el voluntariado.
		Roles_Miembro::anadir( self::ID, Roles_Miembro::SOCIO );

		$this->assertContains( 'voluntario_aprobado', $user->roles, 'A una ficha sin marca NO se le quita el rol del voluntariado' );
		$this->assertContains( 'socio', $user->roles );
	}

	public function test_quitar_si_quita_el_rol_aunque_sea_antiguo(): void {
		// La marca explícita ('0') sí manda: quitar de verdad quita.
		$user = new Rol_Usuario_Falso( self::USER, array( 'subscriber', 'voluntario_aprobado' ) );
		$GLOBALS['_test_users'][ self::USER ] = $user;
		\update_post_meta( self::ID, '_convoca_user_id', (string) self::USER );

		Roles_Miembro::quitar( self::ID, Roles_Miembro::VOLUNTARIO );

		$this->assertNotContains( 'voluntario_aprobado', $user->roles );
		$this->assertSame( '0', (string) \get_post_meta( self::ID, '_convoca_es_voluntario', true ), 'Quitar deja marca explícita' );
	}

	public function test_las_etiquetas_son_las_mismas_en_todas_las_pantallas(): void {
		// El listado de socios y el panel del socio delegan aquí: una condición, una etiqueta.
		$this->assertSame( 'Socio', Roles_Miembro::etiqueta( Roles_Miembro::SOCIO ) );
		$this->assertSame( 'Voluntario', Roles_Miembro::etiqueta( Roles_Miembro::VOLUNTARIO ) );
		$this->assertSame( 'Monitor', Roles_Miembro::etiqueta( Roles_Miembro::MONITOR ) );
		$this->assertSame( 'presidente', Roles_Miembro::etiqueta( 'presidente' ) );
	}
}
