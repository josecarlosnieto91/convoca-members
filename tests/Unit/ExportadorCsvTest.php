<?php
/**
 * El exportador CSV de socios.
 *
 * `admin/class-csv-exporter.php` estaba al 0 % (157 statements) y es la pantalla por la que **sale** la
 * lista de socios con DNI, dirección y teléfono a un fichero. Lo que se fija: que solo exporte quien
 * tiene la capacidad, que la selección de columnas no pueda colar una clave inventada (se perdería en
 * silencio) y que cada celda vaya escapada.
 *
 * `export()` termina en `exit;` después de escribir el fichero: su último tramo no se mide aquí (matar el
 * proceso se lleva por delante la suite y con ella la medición). Se mide lo que decide el contenido,
 * `build_row()`, que es el 80 % del fichero.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\CSV_Exporter;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ExportadorCsvTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		require_once dirname( __DIR__, 2 ) . '/includes/Estados.php';
		require_once dirname( __DIR__, 2 ) . '/admin/class-csv-exporter.php';

		$GLOBALS['_wp_stores']       = array( 'options' => array(), 'acciones' => array(), 'user_meta' => array() );
		$GLOBALS['_wp_json']         = array();
		$GLOBALS['_test_caps']       = array( 'convoca_export_members' => true );
		$GLOBALS['convoca_test_caps'] = array( 'convoca_export_members' => true );
		$_GET                        = array();
		$_POST                       = array();
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores'] = array();
		unset( $GLOBALS['_test_caps'], $GLOBALS['convoca_test_caps'], $GLOBALS['_wp_json'], $_GET, $_POST );
	}

	/**
	 * Invoca el constructor de filas (privado) con un socio.
	 *
	 * @param array<string>        $columnas Columnas pedidas.
	 * @param array<string, mixed> $meta     Meta del socio.
	 * @param array<int, string>   $terminos Tipos de miembro.
	 * @return array<string>
	 */
	private function fila( array $columnas, array $meta = array(), array $terminos = array() ): array {
		$post = new \WP_Post( 55 );
		$post->ID = 55;
		$post->post_title = 'Ana Pérez';
		$post->post_type = 'miembro';
		$post->post_status = 'publish';
		$post->post_date = '2026-02-03 10:00:00';

		foreach ( $meta as $clave => $valor ) {
			$GLOBALS['_wp_stores']['post_meta'][55][ '_convoca_' . $clave ] = $valor;
		}
		$GLOBALS['_wp_stores']['object_terms'][55] = $terminos;

		$metodo = new ReflectionMethod( CSV_Exporter::class, 'build_row' );

		return $metodo->invoke( new CSV_Exporter(), $post, $columnas );
	}

	/**
	 * La selección por defecto solo usa claves que existen.
	 *
	 * Es la trampa silenciosa de esta pantalla: una clave mal escrita en la lista por defecto no da error,
	 * simplemente la columna no aparece en el fichero y nadie lo nota hasta que falta el dato.
	 */
	public function test_la_seleccion_por_defecto_solo_usa_claves_validas(): void {
		$disponibles = array_keys( CSV_Exporter::get_available_columns() );
		$por_defecto = CSV_Exporter::get_default_columns();

		$this->assertSame(
			array(),
			array_values( array_diff( $por_defecto, $disponibles ) ),
			'alguna columna por defecto no existe en la lista de disponibles'
		);
		$this->assertNotEmpty( $por_defecto );
	}

	/**
	 * Sin preferencia guardada se devuelve null (que significa «todas»).
	 */
	public function test_sin_columnas_guardadas_no_hay_preferencia(): void {
		$this->assertNull( CSV_Exporter::get_user_columns(), 'nunca guardado = null' );

		$GLOBALS['_wp_stores']['user_meta'][1]['_convoca_csv_columns'] = array();
		$this->assertNull( CSV_Exporter::get_user_columns(), 'una lista vacía tampoco es una preferencia' );

		$GLOBALS['_wp_stores']['user_meta'][1]['_convoca_csv_columns'] = array( 'Nombre', 'Email' );
		$this->assertSame( array( 'Nombre', 'Email' ), CSV_Exporter::get_user_columns() );
	}

	/**
	 * Cada columna trae su dato: los booleanos se traducen y el estado sale con su etiqueta.
	 */
	public function test_cada_columna_trae_su_dato(): void {
		$fila = $this->fila(
			array( 'ID', 'Nombre', 'Email', 'DNI', 'Menor', 'Comunicaciones', 'Recurrente', 'Voluntario', 'Estado', 'Tipo' ),
			array(
				'email'              => 'ana@example.test',
				'dni'                => '12345678Z',
				'menor_edad'         => '1',
				'comunicaciones_ok'  => '1',
				'pago_recurrente'    => '0',
				'voluntario'         => '1',
				'estado_miembro'     => 'activo',
			),
			array( 'Socio', 'Voluntario' )
		);

		$this->assertSame( '55', $fila[0] );
		$this->assertSame( 'ana@example.test', $fila[2] );
		$this->assertSame( '12345678Z', $fila[3], 'el DNI sale tal cual: es lo que se pide al exportar' );
		$this->assertSame( 'Sí', $fila[4], 'menor: 1 es Sí' );
		$this->assertSame( 'Sí', $fila[5] );
		$this->assertSame( 'No', $fila[6], 'recurrente: 0 es No' );
		$this->assertSame( 'Sí', $fila[7] );
		$this->assertNotSame( 'activo', $fila[8], 'el estado se exporta con su etiqueta, no con la clave' );
		$this->assertSame( 'Socio, Voluntario', $fila[9], 'los tipos se juntan en una celda' );
	}

	/**
	 * Una columna vacía no revienta la fila y una desconocida aporta celda vacía (no descoloca).
	 */
	public function test_las_columnas_vacias_y_desconocidas_no_descolocan(): void {
		$fila = $this->fila( array( 'Email', 'Código Postal', 'Inventada', 'Municipio' ) );

		$this->assertCount( 4, $fila, 'una celda por columna pedida' );
		$this->assertSame( '', $fila[0], 'sin meta, celda vacía' );
		$this->assertSame( '', $fila[2], 'una columna que no existe da celda vacía' );
	}

	/**
	 * Una celda que empieza por «=» va entrecomillada: si no, Excel la ejecuta como fórmula.
	 *
	 * Es el ataque clásico de inyección por CSV: alguien escribe `=HYPERLINK(...)` en sus notas y el
	 * fichero que abre la junta se comporta distinto de lo que parece.
	 */
	public function test_una_celda_que_parece_formula_va_entrecomillada(): void {
		$peligrosa = $this->fila( array( 'Notas' ), array( 'notas' => '=HYPERLINK("http://malo", "pincha")' ) );
		$this->assertSame( 1, preg_match( '/^".*"$/', $peligrosa[0] ), 'una celda con fórmula sale entrecomillada' );

		$normal = $this->fila( array( 'Notas' ), array( 'notas' => 'dice "hola", y adiós' ) );
		$this->assertSame( 'dice "hola", y adiós', $normal[0], 'el texto normal no se toca' );
	}

	/**
	 * Guardar la selección exige nonce y capacidad.
	 */
	public function test_guardar_columnas_exige_capacidad(): void {
		$GLOBALS['_test_caps']['convoca_export_members'] = false; // `current_user_can` es el del NÚCLEO.
		$GLOBALS['_wp_die_lanza'] = true;
		$exportador = new CSV_Exporter();

		try {
			$exportador->ajax_save_columns();
			$this->fail( 'sin capacidad no puede guardar' );
		} catch ( \RuntimeException $e ) {
			$this->assertStringContainsString( 'permiso', $e->getMessage() );
		}
	}

	/**
	 * Una selección que no es una lista se rechaza.
	 */
	public function test_una_seleccion_invalida_se_rechaza(): void {
		$_POST['columns'] = 'no-es-json';

		$exportador = new CSV_Exporter();
		try {
			$exportador->ajax_save_columns();
			$this->fail( 'un formato inválido no puede guardarse' );
		} catch ( \RuntimeException $e ) {
			$this->assertStringContainsString( 'inválido', $e->getMessage() );
		}
	}

	/**
	 * Guardar filtra las claves inventadas y conserva las buenas.
	 */
	public function test_guardar_filtra_las_claves_inventadas(): void {
		$_POST['columns'] = wp_json_encode( array( 'Nombre', 'Inventada', 'Email' ) );

		$exportador = new CSV_Exporter();
		$exportador->ajax_save_columns();

		$this->assertSame(
			array( 'Nombre', 'Email' ),
			$GLOBALS['_wp_stores']['user_meta'][1]['_convoca_csv_columns'],
			'lo inventado no se guarda y lo bueno sí'
		);
		$this->assertTrue( $GLOBALS['_wp_json'][0]['ok'] );
	}
}
