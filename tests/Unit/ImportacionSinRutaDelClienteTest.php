<?php
/**
 * El censo de la importación no pasa por el `/tmp` compartido ni lo abre una ruta del cliente (members#48).
 *
 * MEDIDO (informe del issue, verificado con un CSV real de 3 filas en la demo): el primer paso copiaba el
 * censo a `sys_get_temp_dir()` —el `/tmp` del sistema, modo 1777— y el segundo abría
 * `sys_get_temp_dir() . '/' . $_POST['filename']`. Con nombres, apellidos, DNI, correo, teléfono y dirección
 * dentro, eso significa que mientras dura la importación el censo está a la vista de cualquier otro usuario de
 * la máquina, y que un `filename` a mano hacía leer **cualquier** fichero de ese directorio.
 *
 * Lo que fija esta prueba:
 *  · el censo vive en un directorio propio del sitio, con permisos de dueño y nombre de ficha aleatoria;
 *  · el segundo paso resuelve la ruta **en el servidor** a partir de la ficha, no del navegador;
 *  · y la resolución rechaza cualquier ruta que se salga de la carpeta, aunque la ficha sea válida.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

class ImportacionSinRutaDelClienteTest extends TestCase {

	private function leer( string $rel ): string {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/' . $rel );
	}

	/** El segundo paso ya no abre una ruta que venga del navegador. */
	public function test_el_segundo_paso_no_abre_una_ruta_del_cliente(): void {
		$fuente = $this->leer( 'admin/class-admin-import-csv.php' );

		$this->assertStringNotContainsString( "sanitize_file_name( wp_unslash( \$_POST['filename']", $fuente, 'La ruta ya no se toma del navegador' );
		$this->assertStringNotContainsString( "sys_get_temp_dir() . '/' . \$filename", $fuente, 'Y no se compone con el directorio compartido' );
		$this->assertStringContainsString( "self::ruta_por_ficha( (string) wp_unslash( \$_POST['token'] ?? '' ) )", $fuente );
		$this->assertStringContainsString( '<input type="hidden" name="token"', $fuente, 'El formulario manda la ficha' );
	}

	/** El censo se guarda con nombre de ficha, en un directorio propio y cerrado. */
	public function test_el_censo_vive_en_su_propio_directorio(): void {
		$fuente = $this->leer( 'admin/class-admin-import-csv.php' );

		$this->assertStringContainsString( "bin2hex( random_bytes( 16 ) )", $fuente, 'Nombre aleatorio, no adivinable' );
		$this->assertStringContainsString( "self::dir_importacion() . '/' . \$token . '.csv'", $fuente );
		$this->assertStringContainsString( 'mkdir( $dir, 0700, true )', $fuente, 'La carpeta nace ya en 0700: ni se puede entrar en ella' );
		$this->assertStringContainsString( 'chmod( $destino, 0600 )', $fuente, 'Y el fichero, solo su dueño' );
		$this->assertStringContainsString( "'convoca_csv_fichero_' . \$token", $fuente, 'La ruta la guarda el servidor' );
		$this->assertStringNotContainsString( "tempnam( sys_get_temp_dir(), 'convoca_xlsx_' )", $fuente, 'El XLSX también sale del /tmp compartido' );
	}

	/** El guardián: una ficha válida que apunte fuera de la carpeta se rechaza. */
	public function test_la_ficha_no_sirve_para_leer_cualquier_fichero(): void {
		$clase = '\\Convoca\\Members\\Admin_Import_CSV';
		if ( ! class_exists( $clase ) ) {
			// El importador no lo carga el arnés por su cuenta: se trae a mano para poder comprobarlo de verdad
			// (una prueba que se salta no comprueba nada).
			require_once dirname( __DIR__, 2 ) . '/admin/class-admin-import-csv.php';
		}
		if ( ! class_exists( $clase ) ) {
			$this->markTestSkipped( 'No se ha podido cargar el importador en el arnés.' );
		}

		$resolver = new \ReflectionMethod( $clase, 'ruta_por_ficha' );
		$token = str_repeat( 'a', 32 );

		// 1 · Ficha con formato raro: fuera, sin tocar nada.
		$this->assertSame( '', $resolver->invoke( null, '../etc/passwd' ), 'Una ficha que no es hexadecimal se rechaza' );
		$this->assertSame( '', $resolver->invoke( null, 'no-existe' ) );

		// 2 · Ficha válida que apunta FUERA de la carpeta de importaciones: era el defecto, se rechaza.
		set_transient(
			'convoca_csv_fichero_' . $token,
			array(
				'ruta'    => '/etc/passwd',
				'usuario' => get_current_user_id(),
			),
			HOUR_IN_SECONDS
		);
		$this->assertSame(
			'',
			$resolver->invoke( null, $token ),
			'La ficha no puede servir para leer un fichero de fuera de la carpeta de importaciones'
		);

		// 3 · Y un fichero dentro de la carpeta, con su ficha, sí se resuelve (control positivo).
		$dir = new \ReflectionMethod( $clase, 'dir_importacion' );
		$carpeta = (string) $dir->invoke( null );
		if ( '' === $carpeta || ! is_dir( $carpeta ) ) {
			$this->markTestSkipped( 'No se ha podido preparar la carpeta de importaciones en el arnés.' );
		}
		$fichero = $carpeta . '/' . $token . '.csv';
		file_put_contents( $fichero, "nombre,apellidos\nAna,Uno\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Datos de la prueba.
		set_transient(
			'convoca_csv_fichero_' . $token,
			array(
				'ruta'    => $fichero,
				'usuario' => get_current_user_id(),
			),
			HOUR_IN_SECONDS
		);
		$this->assertSame( realpath( $fichero ), $resolver->invoke( null, $token ), 'Un fichero de la carpeta, con su ficha, sí se resuelve' );

		unlink( $fichero ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Limpieza de la prueba.
		delete_transient( 'convoca_csv_fichero_' . $token );
	}
}
