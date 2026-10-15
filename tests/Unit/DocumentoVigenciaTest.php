<?php
/**
 * La vigencia de los documentos (issue #31).
 *
 * La web vendía «Documentos: … Control de vigencia» y el tipo `convoca_documento` no tenía ni noción de
 * caducidad: cero apariciones de `vigencia`, `caduc` o `fecha_expira`. Lo que sí caduca son los certificados,
 * que es otra cosa.
 *
 * La regla vive en `CPT_Documento::estado_caducidad()` y se prueba aquí de verdad (el arnés trae
 * `current_time`, `DAY_IN_SECONDS` y `_n`), porque una fecha mal calculada avisa tarde o no avisa.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\CPT_Documento;
use PHPUnit\Framework\TestCase;

class DocumentoVigenciaTest extends TestCase {

	const DOC = 4242;

	/**
	 * Estado de un documento con esa fecha de caducidad.
	 *
	 * @param string $fecha Y-m-d, o '' para no ponerle ninguna.
	 * @return array
	 */
	private function estado( string $fecha ): array {
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Documento.php';

		if ( '' === $fecha ) {
			\delete_post_meta( self::DOC, '_convoca_fecha_caducidad' );
		} else {
			\update_post_meta( self::DOC, '_convoca_fecha_caducidad', $fecha );
		}

		return \Convoca\Members\CPT_Documento::estado_caducidad( self::DOC );
	}

	public function test_sin_fecha_lo_dice_y_no_inventa_vigencia(): void {
		$this->assertSame( 'sin_fecha', $this->estado( '' )['estado'] );
		$this->assertNull( $this->estado( '' )['dias'] );
		$this->assertSame( 'sin_fecha', $this->estado( 'el mes que viene' )['estado'], 'Una fecha ilegible no es una fecha' );
	}

	public function test_una_fecha_pasada_esta_caducado(): void {
		$ayer = gmdate( 'Y-m-d', strtotime( '-3 days' ) );
		$estado = $this->estado( $ayer );

		$this->assertSame( 'caducado', $estado['estado'] );
		$this->assertSame( -3, $estado['dias'] );
		$this->assertStringContainsString( 'Caducado', $estado['etiqueta'] );
	}

	public function test_lo_que_caduca_pronto_avisa(): void {
		$proximo = gmdate( 'Y-m-d', strtotime( '+10 days' ) );
		$estado  = $this->estado( $proximo );

		$this->assertSame( 'caduca_pronto', $estado['estado'], 'Dentro del aviso de 30 días tiene que avisar' );
		$this->assertSame( 10, $estado['dias'] );
	}

	public function test_lo_que_queda_lejos_esta_vigente(): void {
		$lejos  = gmdate( 'Y-m-d', strtotime( '+400 days' ) );
		$estado = $this->estado( $lejos );

		$this->assertSame( 'vigente', $estado['estado'] );
		$this->assertStringContainsString( 'Vigente hasta', $estado['etiqueta'] );
	}

	public function test_hoy_mismo_caduca_pronto_no_caducado(): void {
		// El día de la caducidad todavía vale: caduca al terminar el día, no al empezar.
		$hoy = $this->estado( gmdate( 'Y-m-d' ) );
		$this->assertSame( 'caduca_pronto', $hoy['estado'] );
		$this->assertSame( 0, $hoy['dias'] );
	}

	public function test_el_atajo_del_si_o_el_no(): void {
		$this->estado( gmdate( 'Y-m-d', strtotime( '-1 day' ) ) );
		$this->assertTrue( \Convoca\Members\CPT_Documento::caducado( self::DOC ) );

		$this->estado( gmdate( 'Y-m-d', strtotime( '+400 days' ) ) );
		$this->assertFalse( \Convoca\Members\CPT_Documento::caducado( self::DOC ) );
	}

	public function test_el_control_esta_en_la_ficha_y_se_guarda(): void {
		$metaboxes = (string) file_get_contents( dirname( __DIR__, 2 ) . '/admin/class-admin-metaboxes.php' );
		$this->assertStringContainsString( 'estado_caducidad(', $metaboxes, 'La ficha tiene que enseñar el estado' );
		$this->assertStringContainsString( 'name="fecha_caducidad"', $metaboxes, 'Y dejar poner la fecha' );
		$this->assertStringContainsString( "add_action( 'admin_post_convoca_guardar_caducidad'", $metaboxes, 'Con su manejador' );
		$this->assertStringContainsString( 'check_admin_referer', $metaboxes );
		$this->assertStringContainsString( "current_user_can( 'gestionar_documentos_voluntariado' )", $metaboxes, 'Un socio no decide cuándo caduca su DNI' );
	}

	public function test_la_meta_esta_registrada_y_saneada(): void {
		$cpt = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/CPT_Documento.php' );
		$this->assertStringContainsString( "register_post_meta(
			'convoca_documento',
			'_convoca_fecha_caducidad'", $cpt );
		$this->assertStringContainsString( "'sanitize_callback'", $cpt, 'Una fecha que no sea fecha no puede entrar' );
	}
}
