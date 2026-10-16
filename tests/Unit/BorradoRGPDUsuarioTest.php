<?php
/**
 * El borrado de datos personales llega también al usuario de WordPress (members#40).
 *
 * DEFECTO QUE FIJA ESTA PRUEBA (medido el 15/10/2026 con el borrador real del plugin): el borrador
 * anonimizaba la ficha y los pagos, y **no tocaba el usuario de WordPress**, donde el alta deja otra
 * copia de los datos (`Process_Member.php` copia el DNI y el teléfono). Después de un borrado:
 *
 *     ficha:   _convoca_dni=[Eliminado]
 *     usuario: _convoca_dni=99999999R · _convoca_telefono=600111222 · _convoca_voluntario_direccion=…
 *     y la cuenta seguía entrando con su rol (voluntario_aprobado)
 *
 * Y no era cosmético: el certificado de voluntariado lee el DNI **del usuario** (`PDF_Document.php:152`),
 * así que el documento seguía saliendo con el DNI de quien había pedido ser olvidado. La foto del DNI
 * (`_convoca_foto_id`) tampoco se borraba: el adjunto se quedaba en la biblioteca.
 *
 * Se prueban las dos piezas por separado —`erase_user_personal_data()` y `erase_identity_photo()`— y se
 * comprueba que el borrador las llama: el defecto era justo que no las llamaba.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

class BorradoRGPDUsuarioTest extends TestCase {

	private const USUARIO = 7701;
	private const FICHA   = 7702;

	protected function setUp(): void {
		foreach ( array( 'Roles_Miembro', 'Logger', 'Admin_Voluntariado', 'GDPR_Tools' ) as $clase ) {
			$ruta = dirname( __DIR__, 2 ) . '/includes/' . $clase . '.php';
			if ( file_exists( $ruta ) ) {
				require_once $ruta;
			}
		}

		$GLOBALS['_wp_stores']['user_meta']          = array();
		$GLOBALS['_test_users']                     = array();
		$GLOBALS['_wp_stores']['post_meta']          = array();
		$GLOBALS['_wp_stores']['deleted_attachments'] = array();
	}

	/** Un usuario con sus roles, tal como lo devuelve el override `_test_users` del arnés. */
	private function usuario_para_la_prueba( array $roles ): \WP_User {
		$usuario        = new \WP_User();
		$usuario->roles = $roles;

		return $usuario;
	}

	/** Los datos que el alta copió al usuario se borran, y los roles de socio/voluntario se retiran. */
	public function test_borra_los_datos_copiados_al_usuario(): void {
		$GLOBALS['_wp_stores']['user_meta'][ self::USUARIO ] = array(
			'_convoca_email'                => 'quien@example.com',
			'_convoca_dni'                  => '99999999R',
			'_convoca_telefono'             => '600111222',
			'_convoca_voluntario_direccion' => 'Calle de Prueba 1',
			'_convoca_shifts_direccion'     => 'Calle de Prueba 2',
			'_convoca_voluntario_aprobado'  => '1',
		);
		$GLOBALS['_test_users'][ self::USUARIO ] = $this->usuario_para_la_prueba( array( 'socio', 'voluntario_aprobado', 'subscriber' ) );

		$borrados = \Convoca\Members\GDPR_Tools::erase_user_personal_data( self::USUARIO );

		// 5 campos con valor (correo, DNI, teléfono y las dos direcciones) + 2 roles + la marca.
		$this->assertSame( 8, $borrados, 'Se cuentan campos y roles borrados' );

		foreach ( array( '_convoca_dni', '_convoca_telefono', '_convoca_voluntario_direccion', '_convoca_shifts_direccion', '_convoca_email' ) as $campo ) {
			$this->assertSame(
				'[Eliminado]',
				get_user_meta( self::USUARIO, $campo, true ),
				"El DNI o el teléfono del usuario no pueden sobrevivir al borrado: {$campo}"
			);
		}

		$this->assertSame( '-1', get_user_meta( self::USUARIO, '_convoca_voluntario_aprobado', true ), 'Deja de estar aprobado como voluntario' );

		$roles = $GLOBALS['_test_users'][ self::USUARIO ]->roles;
		$this->assertNotContains( 'socio', $roles, 'La cuenta borrada no puede seguir siendo socia' );
		$this->assertNotContains( 'voluntario_aprobado', $roles, 'Ni voluntaria aprobada' );
		$this->assertContains( 'subscriber', $roles, 'Los roles que no son de este módulo no se tocan' );
	}

	/** Una cuenta limpia devuelve 0: el borrado no inventa trabajo. */
	public function test_sin_datos_no_borra_nada(): void {
		$GLOBALS['_wp_stores']['user_meta'][ 7799 ] = array();
		$GLOBALS['_test_users'][ 7799 ]             = $this->usuario_para_la_prueba( array( 'editor' ) );

		$this->assertSame( 0, \Convoca\Members\GDPR_Tools::erase_user_personal_data( 7799 ) );
		$this->assertSame( array( 'editor' ), $GLOBALS['_test_users'][ 7799 ]->roles );
	}

	/** La foto del DNI se borra del todo: el adjunto no se queda huérfano en la biblioteca. */
	public function test_borra_la_foto_del_documento(): void {
		$GLOBALS['_wp_stores']['post_meta'][ self::FICHA ] = array( '_convoca_foto_id' => '4321' );

		$this->assertTrue( \Convoca\Members\GDPR_Tools::erase_identity_photo( self::FICHA ) );
		$this->assertContains( 4321, $GLOBALS['_wp_stores']['deleted_attachments'], 'El adjunto tiene que borrarse, no solo desvincularse' );
		$this->assertSame( '[Eliminado]', get_post_meta( self::FICHA, '_convoca_foto_id', true ) );
	}

	/** Y si no hay foto, no pasa nada. */
	public function test_sin_foto_no_hay_nada_que_borrar(): void {
		$GLOBALS['_wp_stores']['post_meta'][ self::FICHA ] = array();

		$this->assertFalse( \Convoca\Members\GDPR_Tools::erase_identity_photo( self::FICHA ) );
		$this->assertEmpty( $GLOBALS['_wp_stores']['deleted_attachments'] );
	}

	/**
	 * El borrador TIENE que llamar a las dos: el defecto era exactamente que no las llamaba.
	 *
	 * Se comprueba sobre la fuente porque el borrador recorre fichas por correo y el doble de `get_posts`
	 * del arnés es mínimo (devuelve una sola ficha preparada): llamarlo aquí probaría el doble, no el
	 * recorrido. La llamada real está medida en el issue con el borrador del plugin sobre un socio de
	 * prueba.
	 */
	public function test_el_borrador_llama_a_las_dos_piezas(): void {
		$fuente = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/GDPR_Tools.php' );

		$this->assertStringContainsString( 'self::erase_identity_photo( $member_id )', $fuente, 'El borrador tiene que borrar la foto' );
		$this->assertStringContainsString( 'self::erase_user_personal_data( $usuario_id )', $fuente, 'Y los datos del usuario de WordPress' );
		$this->assertStringContainsString( "'_convoca_user_id'", $fuente, 'Buscando el usuario por el vínculo de la ficha' );
	}
}
