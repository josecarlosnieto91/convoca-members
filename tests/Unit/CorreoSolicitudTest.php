<?php
/**
 * El correo de solicitud del alta: cuándo sale y qué enlaces lleva (members#101 y #102).
 *
 * Los dos fallos se ven en la misma pantalla: el correo decía «concepto: tu nombre» porque se enviaba
 * antes de crear el pedido, y no daba ninguna dirección para subir el justificante, solo un botón a Mi
 * Área, que a un socio recién inscrito le está cerrada.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

class CorreoSolicitudTest extends TestCase {

	/**
	 * Lee un fichero del plugin.
	 *
	 * @param string $rel Ruta relativa.
	 * @return string
	 */
	private function leer( string $rel ): string {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/' . $rel );
	}

	/**
	 * El correo sale DESPUÉS de crear el pedido: si no, el concepto de la transferencia es el nombre.
	 */
	public function test_el_correo_sale_despues_del_pedido(): void {
		$motor = $this->leer( 'includes/Process_Member.php' );

		$pedido = strpos( $motor, "update_post_meta( \$post_id, '_convoca_pago_id', \$payment['pago_id'] )" );
		$correo = strpos( $motor, "'convoca_email_solicitud', \$post_id" );

		$this->assertNotFalse( $pedido, 'tiene que guardarse el pedido' );
		$this->assertNotFalse( $correo, 'tiene que enviarse el correo de solicitud' );
		$this->assertLessThan( $correo, $pedido, 'el pedido (y su código) va antes del correo' );

		// Y no queda ningún envío anterior al bloque del pedido.
		$antes = substr( $motor, 0, (int) $pedido );
		$this->assertStringNotContainsString( "'convoca_email_solicitud'", $antes, 'no puede salir el correo antes del pedido' );
	}

	/**
	 * El cuerpo de la transferencia lleva el enlace firmado, no una promesa sin dirección.
	 */
	public function test_la_transferencia_lleva_el_enlace_del_justificante(): void {
		$gestor = $this->leer( 'includes/Email_Manager.php' );

		$this->assertStringContainsString( 'Justificante::url( $post_id )', $gestor );
		$this->assertStringContainsString( 'sube el justificante aquí', $gestor );
		$this->assertStringContainsString( 'El enlace caduca en 3 días', $gestor );
	}

	/**
	 * El botón del correo de solicitud no lleva a Mi Área: lleva al justificante.
	 */
	public function test_el_boton_del_alta_nueva_no_lleva_a_mi_area(): void {
		$gestor = $this->leer( 'includes/Email_Manager.php' );

		$this->assertStringContainsString(
			"'solicitud_recibida'        => array( '{justificante_url}', __( 'Subir el justificante', 'convoca-members' ) )",
			$gestor
		);
	}
}
