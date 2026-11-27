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

		// La página se resuelve por slug y, si el sitio la renombra, por shortcode (#92): el QR no puede
		// llevar una ruta escrita a mano que deje de existir.
		$this->assertStringContainsString(
			"Paginas::url( 'verificar-socio'",
			$tarjeta,
			'El QR tiene que resolver la página del verificador, no escribir la ruta.'
		);
		$this->assertStringContainsString( "'id' => \$post_id", $tarjeta, 'El QR debe llevar el id del socio.' );
		$this->assertStringContainsString( "'token' => \$verification_hash", $tarjeta, 'El QR debe llevar el token de verificación.' );
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
		$src = $this->fuente( 'admin/class-admin-import-csv.php' );

		$this->assertSame( 2, substr_count( $src, '++$imported;' ), 'solo las dos ramas de importación' );
		$pos = strpos( $src, 'elseif ( is_wp_error( $result ) )' );
		$this->assertNotFalse( $pos, 'existe la rama de error' );
		$rama = substr( $src, (int) $pos, 300 );
		$this->assertStringNotContainsString( '++$imported', $rama, 'una fila fallida no cuenta como importada' );
	}

	/**
	 * R8 — El alta debe rechazar nombre y DNI vacíos antes de crear la ficha.
	 *
	 * Hoy se acepta: la ficha nace sin número de socio y la siguiente alta queda bloqueada (issue #23).
	 */
	public function test_el_alta_rechaza_nombre_y_dni_vacios_en_el_motor(): void {
		$src = $this->fuente( 'includes/Process_Member.php' );
		$this->assertStringContainsString( "'validation_error'", $src );
		$this->assertStringContainsString( "El nombre es obligatorio.", $src );
		$this->assertStringContainsString( "El DNI/NIE es obligatorio.", $src );
	}
}
