<?php
/**
 * La cuota pagada manda, y las horas avisan (members#39 + petición de JC, 16/10/2026).
 *
 * Dos cosas que la junta necesita y que antes no pasaban:
 *
 * 1. Una ficha con la cuota pagada y al día **es** socia. El informe members#39 midió una ficha con un pago
 *    cobrado, carnet y cuota al día en la que `es_socio()` decía `false` y la columna «Tipo» decía «Voluntario»:
 *    el recuento de socios para la asamblea o una subvención no servía.
 * 2. Cuando un socio apunta horas, alguien tiene que enterarse para aprobarlas. Antes no avisaba nadie.
 *
 * Los correos se comprueban con el doble del arnés compartido (`$GLOBALS['_wp_stores']['emails']`): no sale
 * ningún correo de verdad, que es lo que tiene que pasar en una prueba.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Hours_Manager;
use Convoca\Members\Roles_Miembro;
use PHPUnit\Framework\TestCase;

class ReconciliacionYHorasTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['_wp_stores']['emails'] = array();
	}

	/** Crea una ficha con la cuota pagada al día. */
	private function ficha_con_cuota( string $renovacion = '' ): int {
		$id = (int) wp_insert_post(
			array(
				'post_type'   => 'miembro',
				'post_status' => 'publish',
				'post_title'  => 'Ficha de prueba',
			)
		);

		update_post_meta( $id, '_convoca_fecha_renovacion', '' !== $renovacion ? $renovacion : gmdate( 'Y-m-d', strtotime( '+6 months' ) ) );
		update_post_meta( $id, Roles_Miembro::META_VOLUNTARIO, '1' );

		$pago = wp_insert_post(
			array(
				'post_type'   => 'pago',
				'post_status' => 'publish',
				'post_title'  => 'Cuota de prueba',
			)
		);
		update_post_meta( $pago, '_convoca_origin', 'members' );
		update_post_meta( $pago, '_convoca_origin_id', $id );
		update_post_meta( $pago, '_convoca_status', 'paid' );

		return $id;
	}

	/** Cuota pagada y al día: la ficha lo sabe. */
	public function test_una_cuota_pagada_y_al_dia_cuenta(): void {
		$id = $this->ficha_con_cuota();
		$this->assertTrue( Roles_Miembro::tiene_cuota_pagada( $id ) );
	}

	/** Una cuota pagada pero con la renovación pasada no cuenta: sería tanto como dar la membresía por viva. */
	public function test_una_cuota_caducada_no_cuenta(): void {
		$id = $this->ficha_con_cuota( gmdate( 'Y-m-d', strtotime( '-2 months' ) ) );
		$this->assertFalse( Roles_Miembro::tiene_cuota_pagada( $id ) );
	}

	/** Y sin pago, tampoco. */
	public function test_sin_pago_no_cuenta(): void {
		$id = (int) wp_insert_post( array( 'post_type' => 'miembro', 'post_status' => 'publish' ) );
		$this->assertFalse( Roles_Miembro::tiene_cuota_pagada( $id ) );
	}

	/**
	 * El descuadre del informe: cuota pagada y la ficha sin la condición de socio.
	 *
	 * La lista de descuadres (`Roles_Miembro::descuadres()`) filtra por meta en la consulta, y el arnés de
	 * pruebas no aplica esos filtros: se mide en la demo con datos reales. Aquí se fija lo que decide:
	 * la reconciliación concede la condición y lo deja dicho.
	 */
	public function test_el_descuadre_se_arregla(): void {
		$id = $this->ficha_con_cuota();

		$this->assertTrue( Roles_Miembro::tiene_cuota_pagada( $id ), 'La cuota está pagada y al día' );
		$this->assertFalse( Roles_Miembro::es_socio( $id ), 'Y de partida la ficha no tiene la condición' );

		$cambios = Roles_Miembro::reconciliar( $id );

		$this->assertNotEmpty( $cambios, 'La reconciliación dice qué ha cambiado' );
		$this->assertTrue( Roles_Miembro::es_socio( $id ), 'Y la ficha queda como socia' );
	}

	/** Reconciliar una ficha que ya está bien no toca nada (y no ensucia el registro). */
	public function test_reconciliar_lo_que_esta_bien_no_hace_nada(): void {
		$id = $this->ficha_con_cuota();
		Roles_Miembro::reconciliar( $id );

		$this->assertSame( array(), Roles_Miembro::reconciliar( $id ), 'La segunda vez no hay nada que hacer' );
	}

	/** Las horas apuntadas por el socio avisan a quien tiene que aprobarlas. */
	public function test_las_horas_pendientes_avisan(): void {
		update_option( 'convoca_members_settings', array( 'voluntariado_email' => 'voluntariado@example.com' ) );
		Hours_Manager::avisar_horas_pendientes( $this->ficha_con_cuota(), 3.5, '2026-10-15', 1 );
		delete_option( 'convoca_members_settings' );

		$correos = (array) ( $GLOBALS['_wp_stores']['emails'] ?? array() );
		$this->assertCount( 1, $correos, 'Sale un correo' );
		$this->assertSame( 'voluntariado@example.com', $correos[0]['to'], 'Va al correo del voluntariado' );
		// El arnés no aplica el idioma, así que aquí el número sale con punto; en un sitio en castellano
		// `number_format_i18n` lo pondrá con coma.
		$this->assertStringContainsString( '3.5', (string) $correos[0]['message'], 'Con las horas dentro' );
		$this->assertStringContainsString( 'conv-horas', (string) $correos[0]['message'], 'Y con el enlace a donde se aprueban' );
	}

	/** Y si no hay correo de voluntariado, al de administración: no se pierde el aviso. */
	public function test_sin_correo_de_voluntariado_avisa_a_administracion(): void {
		update_option( 'convoca_members_settings', array( 'admin_email' => 'junta@example.com' ) );
		Hours_Manager::avisar_horas_pendientes( $this->ficha_con_cuota(), 2.0, '2026-10-15', 1 );
		delete_option( 'convoca_members_settings' );

		$correos = (array) ( $GLOBALS['_wp_stores']['emails'] ?? array() );
		$this->assertCount( 1, $correos );
		$this->assertSame( 'junta@example.com', $correos[0]['to'] );
	}

	/** Sin ninguna dirección configurada no se manda nada: mejor eso que un correo a la nada. */
	public function test_sin_direcciones_no_intenta_nada(): void {
		$anterior = get_option( 'admin_email' );
		update_option( 'admin_email', '' );
		delete_option( 'convoca_members_settings' );

		Hours_Manager::avisar_horas_pendientes( $this->ficha_con_cuota(), 1.0, '2026-10-15', 1 );

		if ( false !== $anterior ) {
			update_option( 'admin_email', $anterior );
		}

		$this->assertCount( 0, (array) ( $GLOBALS['_wp_stores']['emails'] ?? array() ) );
	}
}
