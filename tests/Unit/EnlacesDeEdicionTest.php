<?php
/**
 * El enlace de edición nunca se pasa a `esc_url()` a pelo.
 *
 * Defecto del gauntlet (pasada 8): varias pantallas de administración hacían
 * `esc_url( get_edit_post_link( $id ) )`. Esa función devuelve `null` cuando la entrada no existe o
 * no es editable, y `esc_url( null )` acaba llamando a `ltrim( null )`, que en PHP 8.1+ deja un
 * `PHP Deprecated` en el registro **en cada carga** de la pantalla (medido: 10 líneas por carga en
 * la pantalla de Logs de `convoca-enroll`).
 *
 * El código propio se lee SIN comentarios (tokenizador) porque el comentario que explica el defecto
 * nombra justo el patrón prohibido: un test que lee fuente y no los quita falla contra el comentario.
 *
 * Prueba en negativo: con el patrón puesto, el test falla y nombra el fichero.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

class EnlacesDeEdicionTest extends TestCase
{
	/**
	 * Directorios con código propio de administración/render.
	 *
	 * @var string[]
	 */
	private const DIRS = array( 'admin', 'includes', 'public', 'templates' );

	private function raiz(): string
	{
		return dirname( __DIR__, 2 );
	}

	/**
	 * Quita los comentarios del fuente.
	 *
	 * @param string $src Contenido del fichero.
	 * @param string $ext Extensión.
	 * @return string
	 */
	private function sinComentarios( string $src, string $ext ): string
	{
		if ( 'php' !== $ext ) {
			return (string) preg_replace( '#/\*.*?\*/#s', '', $src );
		}

		$out = '';
		foreach ( token_get_all( $src ) as $token ) {
			if ( is_array( $token ) ) {
				if ( in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
					continue;
				}
				$out .= $token[1];
			} else {
				$out .= $token;
			}
		}

		return $out;
	}

	/**
	 * @return array<string, string> ruta relativa => código sin comentarios.
	 */
	private function codigoPropio(): array
	{
		$raiz   = $this->raiz();
		$salida = array();

		foreach ( self::DIRS as $dir ) {
			$ruta = $raiz . '/' . $dir;
			if ( ! is_dir( $ruta ) ) {
				continue;
			}
			foreach ( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $ruta, \FilesystemIterator::SKIP_DOTS ) ) as $f ) {
				if ( ! $f->isFile() || 'php' !== $f->getExtension() ) {
					continue;
				}
				$rel          = ltrim( str_replace( $raiz, '', $f->getPathname() ), '/' );
				$salida[ $rel ] = $this->sinComentarios( (string) file_get_contents( $f->getPathname() ), 'php' );
			}
		}

		$this->assertNotEmpty( $salida, 'No se ha encontrado código propio que revisar.' );

		return $salida;
	}

	public function test_el_enlace_de_edicion_nunca_va_directo_a_esc_url(): void
	{
		foreach ( $this->codigoPropio() as $rel => $codigo ) {
			$this->assertSame(
				0,
				preg_match( '/esc_url\s*\(\s*get_edit_post_link\s*\(/', $codigo ),
				"En {$rel} se pasa get_edit_post_link() directo a esc_url(): con un enlace nulo eso deja un PHP Deprecated en el registro en cada carga."
			);
		}
	}

	public function test_el_enlace_de_edicion_sigue_existiendo(): void
	{
		// Control: si alguien borra la función en vez de arreglarla, esto lo denuncia.
		$total = 0;
		foreach ( $this->codigoPropio() as $codigo ) {
			$total += (int) preg_match_all( '/get_edit_post_link/', $codigo );
		}

		$this->assertGreaterThan( 0, $total, 'Ya no hay ningún uso de get_edit_post_link(): ¿se ha borrado la función?' );
	}
}
