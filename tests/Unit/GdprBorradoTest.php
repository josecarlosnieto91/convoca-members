<?php
/**
 * El derecho de supresión: borrar de verdad los datos de alguien.
 *
 * Aquí un fallo no da error, deja datos personales vivos. Los tres agujeros que ya se encontraron en su día
 * (y que esta prueba vigila) son los que no se ven: la foto del documento que se queda en la biblioteca
 * (members#40), la **copia del DNI y el teléfono en el usuario de WordPress** —que el certificado lee de ahí—,
 * y la solicitud de supresión que sigue encendida para siempre porque nadie la marca resuelta (members#42).
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Admin_Voluntariado;
use Convoca\Members\GDPR_Tools;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Admin_Voluntariado.php';
require_once dirname( __DIR__, 2 ) . '/includes/GDPR_Tools.php';

/**
 * Borrado de datos personales.
 */
final class GdprBorradoTest extends TestCase {

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
		unset( $GLOBALS['_wp_stores'], $GLOBALS['_test_posts'], $GLOBALS['_wp_stores']['deleted_posts'], $GLOBALS['_wp_stores']['deleted_attachments'] );

		$GLOBALS['_wp_stores'] = array(
			'options'    => array(),
			'post_meta'  => array(),
			'transients' => array(),
			'user_meta'  => array(),
			'emails'     => array(),
			'posts'      => array(),
			'usuarios'   => array(),
			'db_inserts' => array(),
			'get_var'    => '0',
		);
		$GLOBALS['_wp_stores']['deleted_posts']            = array();
		$GLOBALS['_wp_stores']['deleted_attachments'] = array();
	}

	/**
	 * Un registro.
	 *
	 * @param int    $id   Id.
	 * @param string $tipo Tipo.
	 * @param array  $meta Metadatos.
	 * @param string $titulo Título.
	 */
	private function registro( int $id, string $tipo, array $meta, string $titulo = 'Registro' ): void {
		$post                                         = (object) array(
			'ID'           => $id,
			'post_type'    => $tipo,
			'post_status'  => 'publish',
			'post_title'   => $titulo,
			'post_date'    => '2026-01-01 10:00:00',
			'post_content' => '',
		);
		$GLOBALS['_test_posts'][ $id ]                = $post;
		$GLOBALS['_wp_stores']['posts'][ $id ]        = $post;
		$GLOBALS['_wp_stores']['post_meta'][ $id ]    = $meta;
	}

	/**
	 * La ficha del socio, con todos sus datos personales.
	 *
	 * @param array $extra Metadatos extra.
	 */
	private function socio( array $extra = array() ): void {
		$this->registro(
			55,
			'miembro',
			array_merge(
				array(
					'_convoca_nombre'           => 'Ada Lovelace',
					'_convoca_apellidos'        => 'Lovelace',
					'_convoca_email'            => 'ada@example.test',
					'_convoca_telefono'         => '600000000',
					'_convoca_dni'              => '00000000T',
					'_convoca_direccion'        => 'Calle de prueba 1',
					'_convoca_fecha_nacimiento' => '1990-01-01',
					'_convoca_access_code'      => 'ABC123',
					'_convoca_consent_ip'       => '10.0.0.1',
					'_convoca_estado_miembro'   => 'activo',
				),
				$extra
			),
			'Ada Lovelace'
		);
	}

	/**
	 * La foto del documento se borra de la biblioteca (members#40) y se cuenta.
	 */
	public function test_borra_la_foto_del_documento(): void {
		$this->socio( array( '_convoca_foto_id' => 901 ) );

		$resultado = GDPR_Tools::erase_personal_data( 'ada@example.test' );

		$this->assertContains( 901, $GLOBALS['_wp_stores']['deleted_attachments'], 'el adjunto se borra, no se desvincula' );
		$this->assertSame( '[Eliminado]', (string) get_post_meta( 55, '_convoca_foto_id', true ) );
		$this->assertContains( 'Foto del documento de identidad eliminada.', $resultado['messages'] );
	}

	/**
	 * **La copia del usuario de WordPress también se borra** (members#40): el certificado leía el DNI de ahí.
	 */
	public function test_borra_la_copia_del_usuario(): void {
		$this->socio( array( '_convoca_user_id' => 7 ) );
		$GLOBALS['_wp_stores']['usuarios'][7]   = (object) array(
			'ID'           => 7,
			'display_name' => 'Ada Lovelace',
			'first_name'   => 'Ada',
			'user_email'   => 'ada@example.test',
			'roles'        => array( 'socio', 'voluntario_aprobado', 'administrator' ),
		);
		$GLOBALS['_wp_stores']['user_meta'][7]  = array(
			'_convoca_dni'      => '00000000T',
			'_convoca_telefono' => '600000000',
			Admin_Voluntariado::META_APROBADO => '1',
		);

		$resultado = GDPR_Tools::erase_personal_data( 'ada@example.test' );

		$this->assertSame( '[Eliminado]', (string) get_user_meta( 7, '_convoca_dni', true ), 'el DNI del usuario no sobrevive' );
		$this->assertSame( '[Eliminado]', (string) get_user_meta( 7, '_convoca_telefono', true ) );
		// Solo se retiran los roles **de este módulo**; si la cuenta fuera además administradora, no se toca.
		$roles = $this->roles_de( 7 );
		$this->assertContains( 'administrator', $roles, 'un rol ajeno al módulo no se quita' );
		$this->assertNotContains( 'socio', $roles, 'el rol de socio sí' );
		$this->assertNotContains( 'voluntario_aprobado', $roles, 'y el de voluntario aprobado también' );
		$this->assertNotEmpty(
			array_filter( $resultado['messages'], static fn( $m ) => false !== strpos( $m, 'Usuario de WordPress #7' ) ),
			'y se dice en el informe'
		);
	}

	/**
	 * Los roles del usuario tras el borrado.
	 *
	 * @param int $id Usuario.
	 * @return array
	 */
	private function roles_de( int $id ): array {
		return (array) ( $GLOBALS['_wp_stores']['usuarios'][ $id ]->roles ?? array() );
	}

	/**
	 * Si había pedido la supresión, queda resuelta (members#42): si no, el aviso vive encendido para siempre.
	 */
	public function test_resuelve_la_supresion_pedida(): void {
		$this->socio( array( '_convoca_supresion_solicitada' => '2026-10-01 10:00:00' ) );

		GDPR_Tools::erase_personal_data( 'ada@example.test' );

		$this->assertNotSame(
			'',
			(string) get_post_meta( 55, '_convoca_supresion_resuelta', true ),
			'la solicitud queda marcada como resuelta'
		);
	}

	/**
	 * Los pagos **se anonimizan pero se retienen**: hay obligación fiscal.
	 */
	public function test_los_pagos_se_retienen_anonimizados(): void {
		$this->socio();
		$this->registro(
			201,
			'pago',
			array(
				'_convoca_origin'       => 'members',
				'_convoca_origin_id'    => 55,
				'_convoca_product_desc' => 'CUOTA BRONCE',
				'_convoca_amount_cents' => 3000,
			)
		);
		// Un pago de otra persona no se toca.
		$this->registro( 202, 'pago', array( '_convoca_origin' => 'members', '_convoca_origin_id' => 56, '_convoca_product_desc' => 'CUOTA ORO' ) );

		$resultado = GDPR_Tools::erase_personal_data( 'ada@example.test' );

		$this->assertSame( '0', (string) get_post_meta( 201, '_convoca_origin_id', true ), 'se desvincula del socio' );
		$this->assertSame( '[Eliminado]', (string) get_post_meta( 201, '_convoca_product_desc', true ) );
		$this->assertSame( '3000', (string) get_post_meta( 201, '_convoca_amount_cents', true ), 'pero el importe se queda: es el registro fiscal' );
		$this->assertGreaterThan( 0, (int) $resultado['items_retained'], 'y se cuenta como retenido' );
		$this->assertSame( 'CUOTA ORO', (string) get_post_meta( 202, '_convoca_product_desc', true ), 'el de otra persona no se toca' );
	}

	/**
	 * Las inscripciones y las horas de voluntariado se borran; no se anonimizan.
	 */
	public function test_borra_inscripciones_y_horas(): void {
		$this->socio();
		$this->registro( 101, 'inscripcion', array( '_convoca_email' => 'ada@example.test' ) );
		$this->registro( 501, 'registro_hora', array( '_convoca_member_id' => 55 ) );

		$resultado = GDPR_Tools::erase_personal_data( 'ada@example.test' );

		$this->assertContains( 101, $GLOBALS['_wp_stores']['deleted_posts'], 'la inscripción se borra' );
		$this->assertContains( 501, $GLOBALS['_wp_stores']['deleted_posts'], 'y las horas también' );
		$this->assertNotEmpty( array_filter( $resultado['messages'], static fn( $m ) => false !== strpos( $m, 'inscripción' ) ) );
		$this->assertNotEmpty( array_filter( $resultado['messages'], static fn( $m ) => false !== strpos( $m, 'horas' ) ) );
	}

	/**
	 * Un correo que no es de nadie no borra nada, y no revienta.
	 */
	public function test_sin_socio_no_borra_nada(): void {
		$resultado = GDPR_Tools::erase_personal_data( 'nadie@example.test' );

		$this->assertSame( 0, $resultado['items_removed'] );
		$this->assertSame( 0, $resultado['items_retained'] );
		$this->assertSame( array(), $resultado['messages'] );
	}

	/**
	 * La ficha queda anonimizada y marcada: no a medias.
	 */
	public function test_anonimiza_la_ficha(): void {
		$this->socio( array( '_convoca_foto_id' => 901 ) );

		$resultado = GDPR_Tools::erase_personal_data( 'ada@example.test' );

		foreach ( array( '_convoca_nombre', '_convoca_dni', '_convoca_telefono', '_convoca_direccion', '_convoca_fecha_nacimiento', '_convoca_access_code', '_convoca_consent_ip' ) as $campo ) {
			$this->assertSame( '[Eliminado]', (string) get_post_meta( 55, $campo, true ), "el campo $campo se anonimiza" );
		}
		$this->assertSame( 'Socio anonimizado #55', (string) get_post( 55 )->post_title, 'y el título deja de ser su nombre' );
		$this->assertSame( 'baja', (string) get_post_meta( 55, '_convoca_estado', true ) );
		$this->assertNotSame( '', (string) get_post_meta( 55, '_convoca_gdpr_erased', true ), 'con fecha de borrado' );
		$this->assertTrue( $resultado['done'] );
	}
}
