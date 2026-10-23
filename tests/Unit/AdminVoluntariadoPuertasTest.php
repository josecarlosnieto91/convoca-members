<?php
/**
 * Voluntariado en el panel: quién puede acreditar horas y qué pasa al aprobar o revocar.
 *
 * Aquí un error no da pantalla en blanco: da **permisos**. Si un socio cualquiera puede acreditar horas de
 * otros por el simple hecho de ser socio, eso no se nota hasta que alguien se acredita horas que no hizo. Y si
 * aprobar sustituye los roles en vez de sumarlos, el voluntario que además era socio deja de serlo sin avisar.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Admin_Voluntariado;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Roles_Miembro.php';
require_once dirname( __DIR__, 2 ) . '/includes/Admin_Voluntariado.php';

/**
 * Puertas del voluntariado.
 */
final class AdminVoluntariadoPuertasTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->limpiar();
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$this->limpiar();
	}

	/**
	 * Deja el arnés como estaba.
	 */
	private function limpiar(): void {
		unset( $GLOBALS['_wp_stores'], $GLOBALS['_wp_actions'], $GLOBALS['_test_user_caps'], $GLOBALS['_test_caps'], $_POST, $_GET );

		$GLOBALS['_wp_stores'] = array(
			'options'     => array(),
			'post_meta'   => array(),
			'transients'  => array(),
			'user_meta'   => array(),
			'emails'      => array(),
			'posts'       => array(),
			'usuarios'    => array(),
			'notices'     => array(),
			'terms'       => array(),
			'object_terms' => array(),
		);
	}

	/**
	 * Un usuario del sitio con esos roles.
	 *
	 * @param int      $id    Usuario.
	 * @param string[] $roles Roles.
	 */
	private function usuario( int $id, array $roles ): void {
		$GLOBALS['_wp_stores']['usuarios'][ $id ] = new \WP_User();
		$GLOBALS['_wp_stores']['usuarios'][ $id ]->ID           = $id;
		$GLOBALS['_wp_stores']['usuarios'][ $id ]->roles        = $roles;
		$GLOBALS['_wp_stores']['usuarios'][ $id ]->user_email   = 'persona' . $id . '@example.test';
		$GLOBALS['_wp_stores']['usuarios'][ $id ]->display_name = 'Persona ' . $id;
	}

	/**
	 * Quién acredita horas: el voluntariado aprobado, el monitor de la actividad y quien administra.
	 */
	public function test_quien_puede_acreditar_horas(): void {
		// El `get_userdata()` del arnés **inventa** un usuario (con rol de administrador) para cualquier id, así
		// que «usuario que no existe» no se puede distinguir aquí. Queda dicho en vez de fingido.
		$this->assertFalse( Admin_Voluntariado::puede_acreditar_horas( 0 ), 'sin usuario no hay permiso' );

		$casos = array(
			10 => array( array( 'voluntario_aprobado' ), 'voluntariado aprobado' ),
			11 => array( array( 'monitor_actividad' ), 'monitor de la actividad' ),
			12 => array( array( 'subscriber' ), 'quien administra (manage_options)' ),
		);

		foreach ( $casos as $id => $caso ) {
			$this->usuario( $id, $caso[0] );
		}
		$GLOBALS['_test_user_caps'][10]['manage_options'] = false;
		$GLOBALS['_test_user_caps'][11]['manage_options'] = false;
		$GLOBALS['_test_user_caps'][12]['manage_options'] = true;

		$this->assertTrue( Admin_Voluntariado::puede_acreditar_horas( 10 ), $casos[10][1] );
		$this->assertTrue( Admin_Voluntariado::puede_acreditar_horas( 11 ), $casos[11][1] );
		$this->assertTrue( Admin_Voluntariado::puede_acreditar_horas( 12 ), $casos[12][1] );
	}

	/**
	 * Y quien tiene la marca de aprobado sin rol también, que es como quedan las fichas antiguas.
	 */
	public function test_la_marca_de_aprobado_tambien_vale(): void {
		$this->usuario( 13, array( 'subscriber' ) );
		$GLOBALS['_test_user_caps'][13]['manage_options'] = false;

		$this->assertFalse( Admin_Voluntariado::puede_acreditar_horas( 13 ), 'un socio no acredita horas de otros' );

		update_user_meta( 13, Admin_Voluntariado::META_APROBADO, '1' );

		$this->assertTrue( Admin_Voluntariado::puede_acreditar_horas( 13 ), 'con la marca de aprobado sí' );
	}

	/**
	 * Revocado no es aprobado: la marca a `-1` no concede nada.
	 */
	public function test_revocado_no_acredita(): void {
		$this->usuario( 14, array( 'subscriber' ) );
		$GLOBALS['_test_user_caps'][14]['manage_options'] = false;
		update_user_meta( 14, Admin_Voluntariado::META_APROBADO, '-1' );

		$this->assertFalse( Admin_Voluntariado::puede_acreditar_horas( 14 ) );
	}

	/**
	 * La ficha se busca primero por el vínculo y, si no lo hay, por el correo.
	 */
	public function test_la_ficha_del_usuario(): void {
		$this->usuario( 20, array( 'subscriber' ) );
		$this->usuario( 21, array( 'subscriber' ) );

		$ficha = function ( int $user_id ): int {
			$metodo = new \ReflectionMethod( Admin_Voluntariado::class, 'ficha_de_usuario' );

			return (int) $metodo->invoke( null, $user_id );
		};

		$this->assertSame( 0, $ficha( 99 ), 'usuario que no existe' );

		// Sin vínculo, pero con el correo de una ficha.
		$GLOBALS['_wp_stores']['posts'][55]                                  = (object) array( 'ID' => 55, 'post_type' => 'miembro', 'post_status' => 'publish', 'post_title' => 'Ada' );
		$GLOBALS['_wp_stores']['post_meta'][55]['_convoca_email']            = 'persona21@example.test';

		$this->assertSame( 55, $ficha( 21 ), 'se encuentra por el correo' );

		// Con vínculo explícito, ese manda.
		$GLOBALS['_wp_stores']['posts'][56]                        = (object) array( 'ID' => 56, 'post_type' => 'miembro', 'post_status' => 'publish', 'post_title' => 'Bea' );
		$GLOBALS['_wp_stores']['post_meta'][56]['_convoca_user_id'] = 20;
		$GLOBALS['_wp_stores']['post_meta'][56]['_convoca_email']   = 'otra@example.test';

		$this->assertSame( 56, $ficha( 20 ), 'el vínculo manda sobre el correo' );
	}

	/**
	 * Aprobar: marca, **suma** la condición (no la sustituye), avisa al interesado y anuncia el hecho.
	 */
	public function test_aprobar_suma_la_condicion(): void {
		$this->usuario( 30, array( 'socio' ) );
		$GLOBALS['_wp_stores']['posts'][70]                = (object) array( 'ID' => 70, 'post_type' => 'miembro', 'post_status' => 'publish', 'post_title' => 'Ada' );
		$GLOBALS['_wp_stores']['post_meta'][70]['_convoca_user_id'] = 30;

		Admin_Voluntariado::approve_volunteer( 30 );

		$this->assertSame( '1', (string) get_user_meta( 30, Admin_Voluntariado::META_APROBADO, true ), 'queda aprobado' );
		$this->assertContains( 'socio', (array) $GLOBALS['_wp_stores']['usuarios'][30]->roles, 'sigue siendo socio' );
		$this->assertContains( 'voluntario_aprobado', (array) $GLOBALS['_wp_stores']['usuarios'][30]->roles, 'y ahora también voluntariado' );
		$this->assertCount( 1, $GLOBALS['_wp_stores']['emails'] ?? array(), 'se le avisa' );
		$this->assertStringContainsString( 'aprobada', $GLOBALS['_wp_stores']['emails'][0]['subject'] );

		$hooks = array_column( $GLOBALS['_wp_actions'] ?? array(), 'hook' );
		$this->assertContains( 'convoca_voluntario_aprobado', $hooks, 'el resto del ecosistema se entera' );
	}

	/**
	 * El rol de WordPress se garantiza **aunque no haya ficha**: es lo que consumen Enroll y Shifts, y una
	 * aprobación sin rol deja al voluntario sin poder coger turnos.
	 */
	public function test_aprobar_garantiza_el_rol_sin_ficha(): void {
		$this->usuario( 31, array( 'socio' ) ); // sin ficha de socio enlazada

		Admin_Voluntariado::approve_volunteer( 31 );

		$this->assertContains(
			'voluntario_aprobado',
			(array) $GLOBALS['_wp_stores']['usuarios'][31]->roles,
			'sin ficha, el único camino para el rol es el de WordPress'
		);
		$this->assertSame( '1', (string) get_user_meta( 31, Admin_Voluntariado::META_APROBADO, true ) );
	}

	/**
	 * Revocar sin ficha ni rol no revienta; y a uno mismo no se le revoca nada.
	 */
	public function test_revocar(): void {
		$this->usuario( 40, array( 'socio', 'voluntario_aprobado' ) );

		Admin_Voluntariado::revoke_volunteer( 40 );

		$this->assertNotContains( 'voluntario_aprobado', (array) $GLOBALS['_wp_stores']['usuarios'][40]->roles, 'se le quita' );
		$this->assertContains( 'socio', (array) $GLOBALS['_wp_stores']['usuarios'][40]->roles, 'y se le deja lo demás' );

		// A uno mismo no: `get_current_user_id()` del arnés devuelve 1.
		$this->usuario( 1, array( 'administrator', 'voluntario_aprobado' ) );
		Admin_Voluntariado::revoke_volunteer( 1 );

		$this->assertContains(
			'voluntario_aprobado',
			(array) $GLOBALS['_wp_stores']['usuarios'][1]->roles,
			'no puedes revocarte tus propios permisos'
		);
	}

	/**
	 * Los parámetros se leen del POST antes que del GET.
	 */
	public function test_los_parametros(): void {
		$param = function ( string $clave ): string {
			$metodo = new \ReflectionMethod( Admin_Voluntariado::class, 'param' );

			return (string) $metodo->invoke( null, $clave );
		};

		$_GET['accion']  = 'desde_get';
		$_POST['accion'] = 'desde_post';

		$this->assertSame( 'desde_post', $param( 'accion' ), 'el POST manda' );

		unset( $_POST['accion'] );
		$this->assertSame( 'desde_get', $param( 'accion' ), 'y sin POST, el GET' );

		$this->assertSame( '', $param( 'no_existe' ) );
	}
}
