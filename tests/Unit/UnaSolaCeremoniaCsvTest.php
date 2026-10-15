<?php
/**
 * Comprobación de contrato: en convoca-members, el CSV se escribe en UN solo sitio (issue convoca-core#16).
 *
 * Por qué existe: los cuatro exportadores de este plugin abrían el CSV a mano —cuatro `header()`, el BOM,
 * `fopen( 'php://output' )`, `fputcsv`— y cada uno decidía por su cuenta lo demás. Medido el 15/10/2026:
 * tres de los cuatro (horas, proyectos y la plantilla de importación) escribían con el separador por
 * omisión de PHP (`,`), que Excel en español no reconoce; el de miembros no escapaba ninguna celda —el
 * `build_row()` solo protegía unas columnas y dejaba fuera el estado, el ID y las fechas— y el de horas
 * dejaba sin proteger la fecha, las horas y el estado.
 *
 * El contrato: la ceremonia vive en `\Convoca\Core\Utils::stream_csv()`. Aquí se comprueba que los
 * cuatro exportadores la usan, que nadie ha vuelto a abrir el flujo y —lo que es de este plugin— que la
 * plantilla de importación sigue saliendo con **coma**, porque su lector está en este mismo repositorio
 * (`fgetcsv( …, ',', … )`) y un fichero con otro separador dejaría de importarse.
 *
 * Prueba en negativo (ejecutada el 15/10/2026): devolviendo a `admin/class-admin-proyectos.php` su
 * `fopen( 'php://output', 'w' )` + `fputcsv`, las dos primeras comprobaciones caen nombrando el fichero;
 * quitando la llamada a `stream_csv()` de `admin/class-admin-horas.php`, cae la tercera.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

class UnaSolaCeremoniaCsvTest extends TestCase {

	/**
	 * Exportadores del plugin: fichero => motivo por el que declara su propia ceremonia.
	 *
	 * Están los tres que descargan un CSV. Si aparece un cuarto, entra aquí (y el contrato lo pedirá
	 * en cuanto la primera comprobación vea su `php://output`).
	 *
	 * @var array<string, string>
	 */
	private const EXPORTADORES = array(
		'admin/class-csv-exporter.php'    => 'Listado de socios/as con las columnas elegidas.',
		'admin/class-admin-horas.php'     => 'Registro de horas de voluntariado.',
		'admin/class-admin-proyectos.php' => 'Listado de proyectos.',
		'admin/class-admin-import-csv.php' => 'Plantilla de importación de socios (esta sale con COMA: la lee el importador de este mismo plugin).',
	);

	private function raiz(): string {
		return dirname( __DIR__, 2 );
	}

	/**
	 * Ficheros PHP del repositorio, sin dependencias, pruebas ni paquetes generados.
	 *
	 * @return array<int, string> Rutas relativas a la raíz.
	 */
	private function ficherosPhp(): array {
		$raiz   = $this->raiz();
		$omitir = array( 'vendor', 'node_modules', 'tests', 'build', 'build-dir', '.git', 'languages', 'dist' );
		$salida = array();

		$iterador = new \RecursiveIteratorIterator(
			new \RecursiveCallbackFilterIterator(
				new \RecursiveDirectoryIterator( $raiz, \FilesystemIterator::SKIP_DOTS ),
				static function ( \SplFileInfo $actual ) use ( $omitir ): bool {
					return ! in_array( $actual->getFilename(), $omitir, true );
				}
			)
		);

		foreach ( $iterador as $f ) {
			if ( $f instanceof \SplFileInfo && $f->isFile() && 'php' === strtolower( $f->getExtension() ) ) {
				$salida[] = ltrim( str_replace( $raiz, '', $f->getPathname() ), '/' );
			}
		}
		sort( $salida );

		return $salida;
	}

	/**
	 * Código sin comentarios, con el tokenizador de PHP: un `// fputcsv(...)` no es una llamada.
	 *
	 * @param string $codigo Fuente PHP.
	 * @return string Fuente sin comentarios.
	 */
	private function sinComentarios( string $codigo ): string {
		$limpio = '';
		foreach ( token_get_all( $codigo ) as $token ) {
			if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}
			$limpio .= is_array( $token ) ? $token[1] : $token;
		}

		return $limpio;
	}

	/**
	 * Ficheros del repositorio que contienen el patrón.
	 *
	 * @param string $patron Expresión regular.
	 * @return array<int, string>
	 */
	private function quienes( string $patron ): array {
		$quienes = array();
		foreach ( $this->ficherosPhp() as $relativo ) {
			$codigo = $this->sinComentarios( (string) file_get_contents( $this->raiz() . '/' . $relativo ) );
			if ( preg_match( $patron, $codigo ) ) {
				$quienes[] = $relativo;
			}
		}

		return $quienes;
	}

	/** Nadie abre el flujo de salida para CSV en este plugin. */
	public function test_nadie_abre_php_output(): void {
		$quienes = $this->quienes( '/php:\/\/output/' );

		$this->assertSame(
			array(),
			$quienes,
			'La ceremonia CSV vive en \Convoca\Core\Utils::stream_csv(). Abren php://output: ' . implode( ', ', $quienes )
		);
	}

	/** Nadie escribe filas por su cuenta: el escapado por celda es del ayudante. */
	public function test_nadie_llama_a_fputcsv(): void {
		$quienes = $this->quienes( '/[^_a-zA-Z]fputcsv\s*\(/' );

		$this->assertSame(
			array(),
			$quienes,
			'Escribir filas a mano se salta la protección contra fórmulas. Lo hacen: ' . implode( ', ', $quienes )
		);
	}

	/**
	 * Los exportadores declarados usan el ayudante compartido (si alguien borra el volcado, esto lo
	 * dice: un refactor a medias no pasa por aquí).
	 */
	public function test_los_exportadores_declarados_usan_el_ayudante(): void {
		foreach ( self::EXPORTADORES as $fichero => $motivo ) {
			$this->assertFileExists( $this->raiz() . '/' . $fichero, $motivo );
			$this->assertStringContainsString(
				'Utils::stream_csv(',
				(string) file_get_contents( $this->raiz() . '/' . $fichero ),
				$fichero . ' tiene que volcar por Utils::stream_csv() (' . $motivo . ')'
			);
		}
	}

	/**
	 * La plantilla de importación es el único CSV del ecosistema que NO sale con `;`: su lector está en
	 * este repositorio y espera comas. Si alguien «unifica» el separador, esta comprobación lo dice.
	 */
	public function test_la_plantilla_de_importacion_sigue_saliendo_con_coma(): void {
		$codigo = (string) file_get_contents( $this->raiz() . '/admin/class-admin-import-csv.php' );

		$this->assertMatchesRegularExpression(
			'/stream_csv\(\s*\$filename,\s*\$headers,\s*array\( \$example \),\s*\',\'\s*\)/',
			$codigo,
			'La plantilla tiene que pedir el separador de su lector (,): si sale con ; deja de importarse'
		);

		// Y el lector sigue ahí: si alguien cambia el importador a `;`, la plantilla tendría que cambiar con él.
		$this->assertStringContainsString(
			'fgetcsv( $handle, 0, \',\', \'"\', \'\' )',
			$codigo,
			'El importador de socios lee con comas: la plantilla y el lector van juntos'
		);
	}
}
