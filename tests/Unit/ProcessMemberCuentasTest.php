<?php
/**
 * El alta de un socio: qué se rechaza y qué cuenta de usuario se crea.
 *
 * Dos zonas donde equivocarse cuesta caro y no da error. La primera es la **validación**: una ficha con el DNI
 * en blanco no solo mete basura, además casa con el deduplicador y bloquea el alta de los siguientes (issue
 * #23). La segunda es la **cuenta de usuario**: crear una segunda cuenta para un correo que ya existe termina en
 * «Sorry, that email address is already used!» y el socio se queda de alta sin poder entrar.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Process_Member;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Miembro.php';
require_once dirname( __DIR__, 2 ) . '/includes/Process_Member.php';

/**
 * Validación del alta y plan de cuenta.
 */
final class ProcessMemberCuentasTest extends TestCase {

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
		unset( $GLOBALS['_wp_stores'], $GLOBALS['_test_posts'], $GLOBALS['_test_users'], $GLOBALS['_wp_create_fail'] );

		$GLOBALS['_wp_stores'] = array(
			'options'    => array(),
			'post_meta'  => array(),
			'transients' => array(),
			'user_meta'  => array(),
			'emails'     => array(),
			'posts'      => array(),
			'usuarios'   => array(),
		);
	}

	/**
	 * Un alta válida de base; cada prueba estropea lo que necesita.
	 *
	 * @return array
	 */
	private function alta(): array {
		return array(
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
		);
	}

	/**
	 * Un alta completa pasa.
	 */
	public function test_un_alta_valida_pasa(): void {
		$this->assertTrue( Process_Member::validate( $this->alta() ) );
	}

	/**
	 * Los campos en blanco no cuentan como rellenos: un alta con espacios donde va el nombre y el DNI se
	 * rechazaba antes (issue #23) y además envenenaba el deduplicador de las siguientes.
	 */
	public function test_los_espacios_no_cuentan_como_relleno(): void {
		$datos             = $this->alta();
		$datos['nombre']   = '   ';
		$datos['dni']      = '  ';

		$error = Process_Member::validate( $datos );

		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertStringContainsString( 'El nombre es obligatorio', $error->get_error_message() );
		$this->assertStringContainsString( 'El DNI/NIE es obligatorio', $error->get_error_message() );
	}

	/**
	 * El teléfono tiene que ser español: ni corto, ni con prefijo internacional.
	 */
	public function test_el_telefono(): void {
		foreach ( array( '12345678', '6000000', '+34600000000' ) as $malo ) {
			$datos             = $this->alta();
			$datos['telefono'] = $malo;

			$this->assertInstanceOf(
				\WP_Error::class,
				Process_Member::validate( $datos ),
				'debería rechazar ' . $malo
			);
		}

		$datos             = $this->alta();
		$datos['telefono'] = '600 00 00 00'; // con espacios: se limpian antes de mirarlo

		$this->assertTrue( Process_Member::validate( $datos ) );
	}

	/**
	 * La fecha de nacimiento: no futura, no anterior a 1900 y con el formato de la ficha.
	 */
	public function test_la_fecha_de_nacimiento(): void {
		$datos                      = $this->alta();
		$datos['fecha_nacimiento']  = gmdate( 'Y-m-d', time() + ( 400 * DAY_IN_SECONDS ) );

		$this->assertStringContainsString(
			'no puede ser futura',
			Process_Member::validate( $datos )->get_error_message()
		);

		$datos['fecha_nacimiento'] = '1899-12-31';
		$this->assertStringContainsString(
			'mínimo año 1900',
			Process_Member::validate( $datos )->get_error_message()
		);

		$datos['fecha_nacimiento'] = '01/01/1990';
		$this->assertStringContainsString(
			'formato inválido',
			Process_Member::validate( $datos )->get_error_message()
		);
	}

	/**
	 * Se exige aceptar la política de privacidad y elegir plan y forma de pago.
	 */
	public function test_lo_que_es_obligatorio_elegir(): void {
		foreach ( array( 'plan' => 'plan de membresía', 'forma_pago' => 'forma de pago', 'rgpd' => 'política de privacidad' ) as $campo => $texto ) {
			$datos = $this->alta();
			unset( $datos[ $campo ] );

			$this->assertStringContainsString(
				$texto,
				Process_Member::validate( $datos )->get_error_message(),
				'sin ' . $campo . ' no se puede seguir'
			);
		}
	}

	/**
	 * Un DNI con la letra que no toca no pasa; con la buena, sí.
	 */
	public function test_el_dni_con_su_letra(): void {
		$datos        = $this->alta();
		$datos['dni'] = '00000000A'; // la letra de 00000000 es T

		$this->assertStringContainsString(
			'DNI/NIE no es correcto',
			Process_Member::validate( $datos )->get_error_message()
		);
	}

	/**
	 * La edad mínima del sitio se respeta; con el ajuste a cero, no se exige.
	 */
	public function test_la_edad_minima(): void {
		update_option( 'convoca_members_settings', array( 'min_age' => 30 ) );

		$datos                     = $this->alta();
		$datos['fecha_nacimiento'] = gmdate( 'Y-m-d', strtotime( '-20 years' ) );

		$this->assertStringContainsString(
			'al menos 30 años',
			Process_Member::validate( $datos )->get_error_message()
		);
	}

	/**
	 * El plan de cuenta: cuatro casos, y cada uno lleva a un camino distinto.
	 */
	public function test_el_plan_de_cuenta(): void {
		$this->assertSame( 'reuse_linked', Process_Member::plan_user_account( 'a@example.test', 7, true, 0 ), 'vínculo bueno' );
		$this->assertSame( 'create', Process_Member::plan_user_account( 'a@example.test', 7, false, 0 ), 'vínculo roto: se crea' );
		$this->assertSame( 'link_by_email', Process_Member::plan_user_account( 'a@example.test', 0, false, 9 ), 'el correo ya tiene cuenta' );
		$this->assertSame( 'create', Process_Member::plan_user_account( 'a@example.test', 0, false, 0 ), 'sin nada: se crea' );
		$this->assertSame(
			'link_by_email',
			Process_Member::plan_user_account( 'a@example.test', 7, false, 9 ),
			'si el vínculo está roto pero el correo tiene cuenta, se enlaza esa'
		);
	}

	/**
	 * El vínculo va en los dos sentidos o no es un vínculo.
	 */
	public function test_el_vinculo_es_bidireccional(): void {
		Process_Member::link_user_to_member( 9, 55 );

		$this->assertSame( 9, (int) get_post_meta( 55, '_convoca_user_id', true ), 'la ficha sabe su cuenta' );
		$this->assertSame( 55, (int) get_user_meta( 9, '_convoca_member_id', true ), 'y la cuenta su ficha' );
	}

	/**
	 * Si el correo ya es de una cuenta del sitio, se enlaza **esa**: no se crea otra ni se manda contraseña.
	 */
	public function test_si_el_correo_ya_tiene_cuenta_se_enlaza(): void {
		$this->ficha( 55, 'ada@example.test' );
		$GLOBALS['_wp_stores']['usuarios'][9] = (object) array(
			'ID'         => 9,
			'user_login' => 'ada',
			'user_email' => 'ada@example.test',
		);

		$resultado = Process_Member::ensure_wp_user( 55 );

		$this->assertSame( 9, $resultado['user_id'] );
		$this->assertSame( 'ada', $resultado['username'] );
		$this->assertSame( '', $resultado['password'], 'no se le cambia la contraseña a una cuenta que ya usaba' );
		$this->assertFalse( $resultado['is_new'] );
		$this->assertArrayNotHasKey( 'users_created', $GLOBALS['_wp_stores'], 'no se crea ninguna cuenta' );
	}

	/**
	 * Con un vínculo bueno se reutiliza la cuenta, pero con contraseña nueva: la que tenía no la sabemos.
	 */
	public function test_con_vinculo_bueno_se_renueva_la_contrasena(): void {
		$this->ficha( 55, 'ada@example.test' );
		$GLOBALS['_wp_stores']['usuarios'][9] = (object) array(
			'ID'         => 9,
			'user_login' => 'ada',
			'user_email' => 'ada@example.test',
		);
		$GLOBALS['_wp_stores']['post_meta'][55]['_convoca_user_id'] = 9;

		$resultado = Process_Member::ensure_wp_user( 55 );

		$this->assertSame( 9, $resultado['user_id'] );
		$this->assertNotSame( '', $resultado['password'], 'se le da una nueva' );
		$this->assertSame( $resultado['password'], $GLOBALS['_wp_stores']['passwords'][9] ?? null, 'y se aplica de verdad' );
		$this->assertFalse( $resultado['is_new'] );
	}

	/**
	 * Sin nada previo se crea la cuenta, con el nombre de usuario sacado del correo y los datos copiados.
	 */
	public function test_se_crea_la_cuenta(): void {
		$this->ficha( 55, 'ada@example.test' );

		$resultado = Process_Member::ensure_wp_user( 55 );

		$this->assertTrue( $resultado['is_new'] );
		$this->assertSame( 'ada', $resultado['username'] );
		$this->assertNotSame( '', $resultado['password'] );
		$this->assertSame( 1, count( $GLOBALS['_wp_stores']['users_created'] ?? array() ), 'una cuenta' );
		$this->assertSame(
			$resultado['user_id'],
			(int) get_post_meta( 55, '_convoca_user_id', true ),
			'la ficha queda enlazada'
		);
	}

	/**
	 * Si el nombre de usuario está cogido, se prueba con sufijo: dos socios con el mismo nombre de pila no
	 * pueden compartir cuenta.
	 */
	public function test_el_nombre_de_usuario_no_se_repite(): void {
		$this->ficha( 55, 'ada@example.test' );
		$GLOBALS['_wp_stores']['usuarios'][1] = (object) array(
			'ID'         => 1,
			'user_login' => 'ada',
			'user_email' => 'otra@example.test',
		);

		$resultado = Process_Member::ensure_wp_user( 55 );

		$this->assertSame( 'ada1', $resultado['username'], 'se añade sufijo hasta encontrar uno libre' );
	}

	/**
	 * Si la creación falla la primera vez, se reintenta con un nombre más único; y si vuelve a fallar, se dice
	 * que no hay cuenta en vez de devolver un id inventado.
	 */
	public function test_si_falla_la_creacion(): void {
		$this->ficha( 55, 'ada@example.test' );
		$GLOBALS['_wp_create_fail'] = array( new \WP_Error( 'exists', 'Sorry, that email address is already used!' ) );

		$resultado = Process_Member::ensure_wp_user( 55 );

		$this->assertTrue( $resultado['is_new'], 'el reintento funcionó' );
		$this->assertSame( 'ada_55', $resultado['username'], 'con el nombre más único' );

		// Ahora fallan las dos.
		$this->limpiar();
		$this->ficha( 56, 'bea@example.test' );
		$GLOBALS['_wp_create_fail'] = array( new \WP_Error( 'x', 'no' ), new \WP_Error( 'x', 'no' ) );

		$fallo = Process_Member::ensure_wp_user( 56 );

		$this->assertSame( 0, $fallo['user_id'], 'sin cuenta' );
		$this->assertSame( '', $fallo['password'] );
		$this->assertFalse( $fallo['is_new'], 'no se finge un alta' );
	}

	/**
	 * Deja una ficha de socio.
	 *
	 * @param int    $id    Socio.
	 * @param string $email Correo.
	 */
	private function ficha( int $id, string $email ): void {
		$ficha                              = (object) array(
			'ID'          => $id,
			'post_type'   => 'miembro',
			'post_status' => 'publish',
			'post_title'  => 'Ada Lovelace',
		);
		$GLOBALS['_test_posts'][ $id ]      = $ficha;
		$GLOBALS['_wp_stores']['posts'][ $id ] = $ficha;
		$GLOBALS['_wp_stores']['post_meta'][ $id ] = array(
			'_convoca_email' => $email,
			'_convoca_dni'   => '00000000T',
		);
	}
}
