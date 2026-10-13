<?php
/**
 * Los errores del JavaScript propio dicen QUÉ HACER.
 *
 * Defecto del gauntlet (pasadas 6→7): cuando una petición fallaba sin mensaje del servidor —o
 * cuando la respuesta era el `0` / `-1` que responde WordPress— el usuario veía
 * «Error desconocido.», y en algún camino texto técnico o en inglés. El texto común vive en
 * `Convoca\Core` (`convoca_common_js_strings()`, servido como `window.convocaI18n` /
 * `window.convocaAdminI18n`) y se resuelve en un único sitio (`conv.mensajes`,
 * `conv.normalizarError`); este repo solo tiene que usarlo y no inventarse un respaldo propio.
 *
 * Prueba en negativo: con el respaldo anterior («Error desconocido.») falla y lo nombra.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

class MensajesDeErrorTest extends TestCase
{
	/** Textos que no pueden volver al código de este repo. */
	private const PROHIBIDOS = array(
		'Error desconocido',
		'Authentication required. Please log in.',
		'Service temporarily unavailable.',
		'Redirect detected. Please log in.',
	);

	private function raiz(): string
	{
		return dirname( __DIR__, 2 );
	}

	/**
	 * Ficheros propios con código de usuario: JS del plugin y scripts inline de PHP.
	 *
	 * @return array<string, string> ruta relativa => código sin comentarios.
	 */
	private function ficheros(): array
	{
		$raiz   = $this->raiz();
		$salida = array();

		foreach ( array( 'assets/js', 'admin', 'public', 'includes' ) as $dir ) {
			$ruta = $raiz . '/' . $dir;
			if ( ! is_dir( $ruta ) ) {
				continue;
			}
			foreach ( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $ruta, \FilesystemIterator::SKIP_DOTS ) ) as $f ) {
				if ( ! $f->isFile() ) {
					continue;
				}
				if ( 'js' !== $f->getExtension() && 'php' !== $f->getExtension() ) {
					continue;
				}
				$txt = (string) file_get_contents( $f->getPathname() );
				// Sin comentarios: el comentario que explica el defecto nombra lo prohibido.
				$txt = (string) preg_replace( '#/\*.*?\*/#s', '', $txt );
				$txt = (string) preg_replace( '#(^|\s)//[^\n]*#', '$1', $txt );
				$rel = ltrim( str_replace( $raiz, '', $f->getPathname() ), '/' );

				$salida[ $rel ] = $txt;
			}
		}

		$this->assertNotEmpty( $salida, 'No se ha encontrado código propio que revisar.' );

		return $salida;
	}

	public function test_ningun_mensaje_prohibido_en_codigo_propio(): void
	{
		foreach ( $this->ficheros() as $rel => $codigo ) {
			foreach ( self::PROHIBIDOS as $prohibido ) {
				$this->assertStringNotContainsString(
					$prohibido,
					$codigo,
					"El fichero {$rel} sigue con «{$prohibido}»."
				);
			}
		}
	}

	public function test_los_flujos_de_usuario_usan_el_texto_comun(): void
	{
		// El respaldo del alta de socio y del voluntariado sale de la capa común: si alguien
		// vuelve a escribir un literal propio, esto lo denuncia.
		$js = file_get_contents( $this->raiz() . '/assets/js/convoca-members-public.js' );

		$this->assertStringContainsString( 'conv.mensajes.reintentar', (string) $js );
		$this->assertSame(
			2,
			substr_count( (string) $js, 'conv.mensajes.reintentar' ),
			'Los dos flujos de usuario (alta y voluntariado) tienen que usarlo.'
		);
	}
}
