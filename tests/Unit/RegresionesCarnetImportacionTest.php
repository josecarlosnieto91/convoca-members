<?php
/**
 * Regresiones de tarjeta/verificación, importación CSV y validación del alta.
 *
 * R9  — La tarjeta del socio firma su URL de verificación con
 *       `hash_hmac( 'sha256', 'member_' . $id, Utils::get_persistent_salt() )`, y el shortcode
 *       `[convoca_verificar_socio]` tiene que recalcular **exactamente lo mismo**. Si las dos fórmulas se
 *       separan, todas las tarjetas emitidas dejan de validar (o peor: validan las manipuladas).
 * R7  — El resumen de la importación cuenta como importadas las filas que han fallado (issue #26).
 * R8  — Un alta con nombre y DNI en blanco se acepta y bloquea las siguientes (issue #23).
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

final class RegresionesCarnetImportacionTest extends TestCase {

	private function fuente( string $relativo ): string {
		$ruta = dirname( __DIR__, 2 ) . '/' . ltrim( $relativo, '/' );
		$this->assertFileExists( $ruta );
		return (string) file_get_contents( $ruta );
	}

	/**
	 * R9a — Tarjeta y verificador usan la misma fórmula de firma.
	 */
	public function test_la_firma_del_carnet_y_la_del_verificador_coinciden(): void {
		$formula = "hash_hmac( 'sha256', 'member_' . ";

		$tarjeta     = $this->fuente( 'includes/PDF_Card.php' );
		$verificador = $this->fuente( 'convoca-members.php' );

		$this->assertStringContainsString( $formula, $tarjeta, 'La tarjeta ya no firma con la fórmula esperada.' );
		$this->assertStringContainsString( $formula, $verificador, 'El verificador ya no recalcula la firma de la tarjeta.' );
		$this->assertStringContainsString( 'get_persistent_salt()', $tarjeta );
		$this->assertStringContainsString( 'get_persistent_salt()', $verificador );
	}

	/**
	 * R9b — La URL del QR del carnet apunta al verificador con id y token.
	 */
	public function test_el_qr_del_carnet_apunta_al_verificador_con_token(): void {
		$tarjeta = $this->fuente( 'includes/PDF_Card.php' );

		$this->assertMatchesRegularExpression(
			"/home_url\( '\/verificar-socio\/\?id='/",
			$tarjeta,
			'El QR debe apuntar a /verificar-socio/ con el id del socio.'
		);
		$this->assertStringContainsString( '&token=', $tarjeta, 'El QR debe llevar el token de verificación.' );
	}

	/**
	 * R7a — La importación distingue el duplicado por email del duplicado por DNI.
	 */
	public function test_la_importacion_avisa_del_duplicado_con_el_campo_que_choca(): void {
		$src = $this->fuente( 'admin/class-admin-import-csv.php' );

		$this->assertStringContainsString( 'socio ya existe', $src );
		$this->assertMatchesRegularExpression( '/socio ya existe \(email: %2\$s, DNI: %3\$s\)/', $src );
		$this->assertMatchesRegularExpression( '/socio ya existe \(email: %2\$s\)/', $src );
	}

	/**
	 * R7b — El resumen no puede contar como importada una fila que ha fallado.
	 *
	 * Hoy el bucle incrementa el contador en las tres ramas, así que una fila duplicada sale contada como
	 * importada y como error a la vez («1 socios importados correctamente. 1 errores: …»).
	 */
	public function test_las_filas_con_error_no_cuentan_como_importadas(): void {
		$this->markTestIncomplete(
			'Pendiente del arreglo de convoca-members#26: en el bucle de importación, la rama de is_wp_error() ' .
			'debe hacer continue antes de ++$imported. Cuando se arregle, activar aquí la comprobación de que ' .
			'el incremento no aparece dentro de la rama de error (y lo mismo en process_batch()).'
		);
	}

	/**
	 * R8 — El alta debe rechazar nombre y DNI vacíos antes de crear la ficha.
	 *
	 * Hoy se acepta: la ficha nace sin número de socio y la siguiente alta queda bloqueada (issue #23).
	 */
	public function test_el_alta_rechaza_nombre_y_dni_vacios(): void {
		$this->markTestIncomplete(
			'Pendiente del arreglo de convoca-members#23: public/class-form-handler.php debe validar nombre y ' .
			'DNI (no vacíos, DNI con letra válida) antes de insertar el CPT. Al arreglarlo, comprobar aquí que ' .
			'un POST con nombre="" y dni="" no crea ninguna ficha.'
		);
	}
}
