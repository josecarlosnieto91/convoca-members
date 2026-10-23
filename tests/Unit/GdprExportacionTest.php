<?php
/**
 * El derecho de acceso: la descarga de datos personales de un socio.
 *
 * Esto es lo que se entrega cuando alguien pide «dame todo lo que tenéis de mí». Si falta un grupo, la respuesta
 * es incompleta y —peor— parece completa. Y si sobra, se está entregando información de otra persona.
 *
 * Se comprueban los cuatro grupos (perfil, pagos, inscripciones y horas) y, sobre todo, el **filtro**: sólo los
 * datos de ese correo.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\GDPR_Tools;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/GDPR_Tools.php';

/**
 * Exportación de datos personales.
 */
final class GdprExportacionTest extends TestCase {

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
		unset( $GLOBALS['_wp_stores'], $GLOBALS['_test_posts'], $GLOBALS['_wp_site'] );

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
	 * Un registro en el almacén.
	 *
	 * @param int    $id     Id.
	 * @param string $tipo   Tipo.
	 * @param array  $meta   Metadatos.
	 * @param string $titulo Título.
	 */
	private function registro( int $id, string $tipo, array $meta, string $titulo = 'Registro' ): void {
		$post = (object) array(
			'ID'           => $id,
			'post_type'    => $tipo,
			'post_status'  => 'publish',
			'post_title'   => $titulo,
			'post_date'    => '2026-10-01 10:00:00',
			'post_content' => '',
		);
		$GLOBALS['_test_posts'][ $id ]         = $post;
		$GLOBALS['_wp_stores']['posts'][ $id ] = $post;
		$GLOBALS['_wp_stores']['post_meta'][ $id ] = $meta;
	}

	/**
	 * Los grupos de un resultado.
	 *
	 * @param array $items Resultado de la exportación.
	 * @return array<string, array>
	 */
	private function grupos( array $exportacion ): array {
		// El exportador devuelve la forma que espera WordPress: `['data' => items, 'done' => true]`. Leer los
		// items del nivel de arriba recorre `data`/`done` como si fueran grupos y no encuentra ninguno.
		$por_id = array();
		foreach ( (array) ( $exportacion['data'] ?? array() ) as $item ) {
			$por_id[ $item['group_id'] ] = $item;
		}

		return $por_id;
	}

	/**
	 * Los datos de la ficha, y **sólo los que tienen valor** (una descarga llena de campos vacíos no se lee).
	 */
	public function test_el_perfil(): void {
		$this->registro(
			55,
			'miembro',
			array(
				'_convoca_email'        => 'ada@example.test',
				'_convoca_nombre'       => 'Ada Lovelace',
				'_convoca_dni'          => '00000000T',
				'_convoca_telefono'     => '600000000',
				'_convoca_plan'         => 'bronze',
				'_convoca_numero_socio' => '12',
				// `_convoca_apellidos` sin valor: no debe aparecer.
			)
		);

		$grupos = $this->grupos( GDPR_Tools::export_personal_data( 'ada@example.test' ) );

		$this->assertArrayHasKey( 'convoca-member-profile', $grupos, 'el grupo del perfil' );
		$valores = array_column( $grupos['convoca-member-profile']['data'], 'value', 'name' );

		$this->assertSame( 'Ada Lovelace', $valores['Nombre'] );
		$this->assertSame( '00000000T', $valores['DNI/NIE'] );
		$this->assertSame( '12', $valores['Número de socio'] );
		$this->assertArrayNotHasKey( 'Apellidos', $valores, 'los campos vacíos no se entregan' );
	}

	/**
	 * El consentimiento va con su fecha **y su versión**: es lo que hay que poder demostrar.
	 */
	public function test_el_consentimiento(): void {
		$this->registro(
			55,
			'miembro',
			array(
				'_convoca_email'             => 'ada@example.test',
				'_convoca_consent_timestamp' => '2026-01-15 10:00:00',
				'_convoca_consent_version'   => '2.1',
			)
		);

		$valores = array_column(
			$this->grupos( GDPR_Tools::export_personal_data( 'ada@example.test' ) )['convoca-member-profile']['data'],
			'value',
			'name'
		);

		$this->assertSame( '2026-01-15 10:00:00', $valores['Consentimiento registrado'] );
		$this->assertSame( '2.1', $valores['Versión de consentimiento'] );
	}

	/**
	 * Los pagos del socio, con su importe en formato español; los de otro no aparecen.
	 */
	public function test_los_pagos(): void {
		$this->registro( 55, 'miembro', array( '_convoca_email' => 'ada@example.test' ) );
		$this->registro(
			201,
			'pago',
			array(
				'_convoca_origin'      => 'members',
				'_convoca_origin_id'   => 55,
				'_convoca_amount_cents' => 3000,
				'_convoca_product_desc' => 'CUOTA BRONCE',
				'_convoca_status'      => 'completado',
				'_convoca_method'      => 'transferencia',
			)
		);
		$this->registro( 202, 'pago', array( '_convoca_origin' => 'members', '_convoca_origin_id' => 56, '_convoca_amount_cents' => 9999 ) );

		$pagos = $this->grupos( GDPR_Tools::export_personal_data( 'ada@example.test' ) )['convoca-payments'];

		$this->assertSame( 'payment-201', $pagos['item_id'], 'sólo el suyo' );
		$valores = array_column( $pagos['data'], 'value', 'name' );
		$this->assertSame( '30,00 €', $valores['Importe'], 'en formato español' );
		$this->assertSame( 'CUOTA BRONCE', $valores['Concepto'] );
	}

	/**
	 * Las inscripciones a actividades y las horas de voluntariado.
	 */
	public function test_inscripciones_y_horas(): void {
		$this->registro( 55, 'miembro', array( '_convoca_email' => 'ada@example.test' ) );
		$this->registro( 301, 'actividad', array(), 'Huerto' );
		$this->registro(
			101,
			'inscripcion',
			array(
				'_convoca_email'       => 'ada@example.test',
				'_convoca_actividad_id' => 301,
				'_convoca_estado'      => 'confirmada',
			)
		);
		$this->registro( 400, 'proyecto', array(), 'Vivero' );
		$GLOBALS['_wp_stores']['posts'][501] = (object) array(
			'ID'           => 501,
			'post_type'    => 'registro_hora',
			'post_status'  => 'publish',
			'post_title'   => 'Horas',
			'post_date'    => '2026-10-02 10:00:00',
			'post_content' => 'Reparto de carteles',
		);
		$GLOBALS['_test_posts'][501] = $GLOBALS['_wp_stores']['posts'][501];
		$GLOBALS['_wp_stores']['post_meta'][501] = array(
			'_convoca_member_id'  => 55,
			'_convoca_fecha'      => '2026-10-02',
			'_convoca_horas'      => '3',
			'_convoca_proyecto_id' => 400,
			'_convoca_estado'     => 'aprobada',
		);

		$grupos = $this->grupos( GDPR_Tools::export_personal_data( 'ada@example.test' ) );

		$this->assertArrayHasKey( 'convoca-inscriptions', $grupos, 'sus inscripciones' );
		$inscripcion = array_column( $grupos['convoca-inscriptions']['data'], 'value', 'name' );
		$this->assertSame( 'Huerto', $inscripcion['Actividad'] );
		$this->assertSame( 'confirmada', $inscripcion['Estado'] );

		$this->assertArrayHasKey( 'convoca-volunteering', $grupos, 'sus horas' );
		$horas = array_column( $grupos['convoca-volunteering']['data'], 'value', 'name' );
		$this->assertSame( '3', $horas['Horas'] );
		$this->assertSame( 'Vivero', $horas['Proyecto'] );
		$this->assertSame( 'Reparto de carteles', $horas['Descripción'], 'lo que escribió el socio' );
		$this->assertSame( 'aprobada', $horas['Estado'], 'y si esas horas están aprobadas o no' );
	}

	/**
	 * Un correo que no es de nadie devuelve una descarga vacía, no un error.
	 */
	public function test_sin_socio_no_hay_nada(): void {
		$exportacion = GDPR_Tools::export_personal_data( 'nadie@example.test' );

		$this->assertSame( array(), $exportacion['data'], 'ni un grupo' );
		$this->assertTrue( $exportacion['done'], 'y se da por terminado (no hay más páginas)' );
	}

	/**
	 * El correo se sanea: con espacios o mayúsculas se encuentra igual.
	 */
	public function test_el_correo_se_sanea(): void {
		$this->registro( 55, 'miembro', array( '_convoca_email' => 'ada@example.test', '_convoca_nombre' => 'Ada Lovelace' ) );

		// Se afirma **el grupo**, no «no está vacío»: el exportador devuelve siempre `data` y `done`, así que
		// `assertNotEmpty` pasaba aunque no encontrara a nadie (lo destapó una sonda, no la prueba).
		$grupos = $this->grupos( GDPR_Tools::export_personal_data( '  ADA@EXAMPLE.TEST  ' ) );

		$this->assertArrayHasKey( 'convoca-member-profile', $grupos, 'un correo con espacios y mayúsculas es el mismo' );
	}
}
