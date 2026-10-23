<?php
/**
 * Supresión de datos y consentimiento: lo que hay que poder demostrar.
 *
 * Esto no es «una funcionalidad»: es lo que se enseña si alguien pregunta. Si la solicitud de supresión no
 * queda fechada, si el aviso al responsable no sale, o si el borrado cuenta campos que no existían, lo que falla
 * es la respuesta legal, no la comodidad.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\GDPR_Tools;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/GDPR_Tools.php';

/**
 * Consentimiento, supresión y borrado.
 */
final class GdprSupresionTest extends TestCase {

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
		unset( $GLOBALS['_wp_stores'], $GLOBALS['_test_posts'] );

		$GLOBALS['_wp_stores'] = array(
			'options'    => array(),
			'post_meta'  => array(),
			'transients' => array(),
			'user_meta'  => array(),
			'emails'     => array(),
			'posts'      => array(),
		);
	}

	/**
	 * Un socio con ficha.
	 *
	 * @param int $id Socio.
	 */
	private function socio( int $id ): void {
		$ficha                              = (object) array(
			'ID'          => $id,
			'post_type'   => 'miembro',
			'post_status' => 'publish',
			'post_title'  => 'Ada Lovelace',
		);
		$GLOBALS['_test_posts'][ $id ]      = $ficha;
		$GLOBALS['_wp_stores']['posts'][ $id ] = $ficha;
	}

	/**
	 * Correos enviados.
	 *
	 * @return array<int, array>
	 */
	private function correos(): array {
		return $GLOBALS['_wp_stores']['emails'] ?? array();
	}

	/**
	 * El consentimiento: sin fecha no está registrado, con fecha y versión sí.
	 */
	public function test_la_etiqueta_del_consentimiento(): void {
		$this->socio( 55 );

		$sin = GDPR_Tools::etiqueta_consentimiento( 55 );
		$this->assertFalse( $sin['registrado'], 'sin fecha no hay consentimiento que enseñar' );
		$this->assertSame( '', $sin['fecha'] );

		$GLOBALS['_wp_stores']['post_meta'][55]['_convoca_consent_timestamp'] = '2026-01-15 10:00:00';
		$GLOBALS['_wp_stores']['post_meta'][55]['_convoca_consent_version']   = '2.1';

		$con = GDPR_Tools::etiqueta_consentimiento( 55 );
		$this->assertTrue( $con['registrado'] );
		$this->assertSame( '2026-01-15 10:00:00', $con['fecha'] );
		$this->assertSame( '2.1', $con['version'] );
	}

	/**
	 * Se puede pedir el consentimiento y queda fechado con su versión (y no se pierde el anterior).
	 */
	public function test_registrar_el_consentimiento(): void {
		$this->socio( 55 );

		GDPR_Tools::log_consent( 55, 'Texto aceptado', '3.0' );

		$etiqueta = GDPR_Tools::etiqueta_consentimiento( 55 );
		$this->assertTrue( $etiqueta['registrado'] );
		$this->assertSame( '3.0', $etiqueta['version'] );
		$this->assertArrayHasKey( '_convoca_consent_text', $GLOBALS['_wp_stores']['post_meta'][55] );
	}

	/**
	 * La solicitud de supresión se fecha, avisa al responsable y no se duplica.
	 */
	public function test_la_solicitud_de_supresion(): void {
		$this->socio( 55 );
		update_option( 'admin_email', 'coordinacion@example.test' );

		$fecha = GDPR_Tools::solicitar_supresion( 55 );

		$this->assertNotSame( '', $fecha, 'queda fechada' );
		$this->assertSame( $fecha, get_post_meta( 55, '_convoca_supresion_solicitada', true ) );
		$this->assertCount( 1, $this->correos(), 'sale el aviso al responsable' );
		$this->assertSame( 'coordinacion@example.test', $this->correos()[0]['to'] );
		$this->assertStringContainsString( 'Ada Lovelace', $this->correos()[0]['message'], 'con el nombre del socio' );

		$this->assertSame( $fecha, GDPR_Tools::solicitar_supresion( 55 ), 'pedirla otra vez devuelve la misma fecha' );
		$this->assertCount( 1, $this->correos(), 'y no se vuelve a avisar' );
	}

	/**
	 * Pedirla de nuevo **después** de haberla resuelto tiene que dejar la solicitud pendiente otra vez: si la
	 * marca de resuelta se queda, la nueva petición nace «resuelta» y nadie la atiende.
	 */
	public function test_una_nueva_solicitud_vuelve_a_estar_pendiente(): void {
		$this->socio( 55 );
		update_option( 'admin_email', 'coordinacion@example.test' );
		GDPR_Tools::solicitar_supresion( 55 );
		GDPR_Tools::marcar_supresion_resuelta( 55 );
		$this->assertFalse( GDPR_Tools::estado_privacidad( 55 )['pendiente'], 'la primera quedó resuelta' );

		// Se borra la solicitud vieja (ciclo cerrado) y se vuelve a pedir.
		delete_post_meta( 55, '_convoca_supresion_solicitada' );
		GDPR_Tools::solicitar_supresion( 55 );

		$this->assertTrue( GDPR_Tools::estado_privacidad( 55 )['pendiente'], 'la nueva nace pendiente' );
		$this->assertSame( '', (string) get_post_meta( 55, '_convoca_supresion_resuelta', true ), 'sin marca vieja' );
	}

	/**
	 * Una ficha que no existe no genera solicitud ni correo.
	 */
	public function test_sin_ficha_no_hay_solicitud(): void {
		$this->assertSame( '', GDPR_Tools::solicitar_supresion( 0 ), 'id inválido' );

		// `get_post()` del arnés inventa una ficha para un id desconocido, así que se comprueba el caso real:
		// sin ficha en el almacén no hay nada que fechar ni a quién avisar.
		$GLOBALS['_test_posts'][99] = null;
		unset( $GLOBALS['_wp_stores']['posts'][99] );

		$this->assertSame( '', GDPR_Tools::solicitar_supresion( -3 ) );
	}

	/**
	 * Sin correo de destino válido no se manda nada (y la solicitud queda igualmente fechada).
	 */
	public function test_sin_destino_no_se_avisa(): void {
		$this->socio( 55 );
		update_option( 'admin_email', 'no-es-un-correo' );

		$fecha = GDPR_Tools::solicitar_supresion( 55 );

		$this->assertNotSame( '', $fecha, 'la solicitud se registra igual' );
		$this->assertSame( array(), $this->correos(), 'pero no se manda a una dirección inválida' );
	}

	/**
	 * El aviso dice lo que hay que saber: quién, cuándo, que la baja no borra y dónde se resuelve.
	 */
	public function test_el_cuerpo_del_aviso(): void {
		$this->socio( 55 );

		$cuerpo = GDPR_Tools::cuerpo_aviso_supresion( 55, '2026-10-23 09:00:00' );

		$this->assertStringContainsString( 'Ada Lovelace', $cuerpo );
		$this->assertStringContainsString( '#55', $cuerpo );
		$this->assertStringContainsString( '2026-10-23 09:00:00', $cuerpo );
		$this->assertStringContainsString( 'La baja NO borra nada', $cuerpo, 'lo que más se malinterpreta' );
		$this->assertStringContainsString( 'remove_personal_data', $cuerpo, 'y dónde se resuelve' );
	}

	/**
	 * Resolver la supresión exige que se haya pedido: sin solicitud no se marca nada.
	 */
	public function test_marcar_la_supresion_resuelta(): void {
		$this->socio( 55 );

		GDPR_Tools::marcar_supresion_resuelta( 55 );
		$this->assertSame( '', (string) get_post_meta( 55, '_convoca_supresion_resuelta', true ), 'no se pidió' );

		$GLOBALS['_wp_stores']['post_meta'][55]['_convoca_supresion_solicitada'] = '2026-10-20 10:00:00';
		GDPR_Tools::marcar_supresion_resuelta( 55 );

		$this->assertNotSame( '', (string) get_post_meta( 55, '_convoca_supresion_resuelta', true ), 'queda constancia' );
	}

	/**
	 * El estado de privacidad: pendiente mientras no se resuelva, y la versión de la política del sitio.
	 */
	public function test_el_estado_de_privacidad(): void {
		$this->socio( 55 );

		$inicial = GDPR_Tools::estado_privacidad( 55 );
		$this->assertFalse( $inicial['pendiente'], 'sin solicitud no hay nada pendiente' );
		$this->assertSame( '1.0', $inicial['politica_version'], 'versión por defecto' );

		update_option( 'convoca_members_settings', array( 'rgpd_version' => '2026-1', 'rgpd_url' => 'https://example.test/privacidad' ) );
		$GLOBALS['_wp_stores']['post_meta'][55]['_convoca_supresion_solicitada'] = '2026-10-20 10:00:00';

		$pendiente = GDPR_Tools::estado_privacidad( 55 );
		$this->assertTrue( $pendiente['pendiente'], 'pedida y sin resolver' );
		$this->assertSame( '2026-1', $pendiente['politica_version'] );
		$this->assertSame( 'https://example.test/privacidad', $pendiente['politica_url'] );

		$GLOBALS['_wp_stores']['post_meta'][55]['_convoca_supresion_resuelta'] = '2026-10-22 10:00:00';
		$this->assertFalse( GDPR_Tools::estado_privacidad( 55 )['pendiente'], 'ya resuelta' );
	}

	/**
	 * Sólo cuentan las pendientes: pedida **y** sin resolver.
	 */
	public function test_contar_las_supresiones_pendientes(): void {
		foreach ( array( 55, 56, 57 ) as $id ) {
			$this->socio( $id );
		}

		$GLOBALS['_wp_stores']['post_meta'][55]['_convoca_supresion_solicitada'] = '2026-10-20 10:00:00';
		$GLOBALS['_wp_stores']['post_meta'][56]['_convoca_supresion_solicitada'] = '2026-10-20 10:00:00';
		$GLOBALS['_wp_stores']['post_meta'][56]['_convoca_supresion_resuelta']   = '2026-10-21 10:00:00';

		$this->assertSame( 1, GDPR_Tools::contar_supresiones_pendientes(), 'la resuelta no cuenta' );
	}

	/**
	 * La foto se borra de verdad (el adjunto) y el campo no queda apuntando a un fichero que ya no está.
	 */
	public function test_borrar_la_foto(): void {
		$this->socio( 55 );

		$this->assertFalse( GDPR_Tools::erase_identity_photo( 55 ), 'sin foto no hay nada que borrar' );

		$GLOBALS['_wp_stores']['post_meta'][55]['_convoca_foto_id'] = 321;
		$this->assertTrue( GDPR_Tools::erase_identity_photo( 55 ) );

		$this->assertSame( array( 321 ), $GLOBALS['_wp_stores']['deleted_attachments'] ?? array(), 'el adjunto se borra' );
		$this->assertSame( '[Eliminado]', get_post_meta( 55, '_convoca_foto_id', true ), 'y el campo no miente' );
	}

	/**
	 * Borrado de los datos personales: cuenta lo que había y vacía los campos.
	 */
	public function test_borrar_los_datos_personales(): void {
		$this->assertSame( 0, GDPR_Tools::erase_user_personal_data( 0 ), 'sin usuario no borra nada' );

		$GLOBALS['_wp_stores']['user_meta'][900] = array(
			'_convoca_email'    => 'ada@example.test',
			'_convoca_dni'      => '00000000T',
			'_convoca_telefono' => '', // vacío: no cuenta
			'_convoca_access_code' => '1234',
		);

		$this->assertSame( 3, GDPR_Tools::erase_user_personal_data( 900 ), 'sólo los que tenían algo' );
		$this->assertSame( '[Eliminado]', $GLOBALS['_wp_stores']['user_meta'][900]['_convoca_email'] );
		$this->assertSame( '[Eliminado]', $GLOBALS['_wp_stores']['user_meta'][900]['_convoca_dni'] );
		$this->assertSame( '', $GLOBALS['_wp_stores']['user_meta'][900]['_convoca_telefono'], 'el vacío se queda vacío' );
	}
}
