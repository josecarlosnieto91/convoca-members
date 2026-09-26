<?php
/**
 * La base de estilos común de los documentos.
 *
 * Lo que se comprueba aquí es lo que la hace inofensiva: que trae la tipografía y los colores de
 * realce, que NO se impone en lo que cada documento decide (el color del texto) y que los tres
 * documentos la usan — porque una base que un documento no incluye no sirve de nada.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Estilos_PDF;
use Convoca\Members\PDF_Card;
use PHPUnit\Framework\TestCase;

/**
 * Base de estilos de los documentos.
 */
class EstilosPdfTest extends TestCase
{
	private const SOCIO = 701;

	protected function setUp(): void
	{
		parent::setUp();
		$GLOBALS['_wp_stores']['post_meta'] = array();
		$GLOBALS['_test_document_theme']    = 'light';
		update_post_meta( self::SOCIO, '_convoca_numero_socio', 7 );
	}

	/** Código de un fichero del plugin, sin comentarios. */
	private function codigo( string $relativa ): string
	{
		$ruta = dirname( __DIR__, 2 ) . '/' . $relativa;
		$this->assertFileExists( $ruta );

		$limpio = '';
		foreach ( token_get_all( (string) file_get_contents( $ruta ) ) as $token ) {
			if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}
			$limpio .= is_array( $token ) ? $token[1] : $token;
		}

		return $limpio;
	}

	public function test_la_base_trae_la_tipografia_los_realces_y_los_resets_neutros(): void
	{
		$base = Estilos_PDF::base();

		$this->assertStringContainsString( Estilos_PDF::TIPOGRAFIA, $base );
		$this->assertStringContainsString( '.acento { color: ' . Estilos_PDF::NARANJA, $base );
		$this->assertStringContainsString( '.secundario { color: ' . Estilos_PDF::GRIS, $base );
		$this->assertStringContainsString( 'body { font-family:', $base );
		$this->assertStringContainsString( 'margin: 0;', $base );
		$this->assertStringContainsString( 'img { max-width: 100%; }', $base );
		$this->assertStringContainsString( 'table { border-collapse: collapse; }', $base );
	}

	public function test_la_base_no_se_impone_al_color_de_cada_documento(): void
	{
		$base = Estilos_PDF::base();

		// Si la base fijara el color del texto, por ir después en la hoja se impondría a la
		// plantilla del acuerdo (que la edita el sitio) y le cambiaría el color sin querer.
		$this->assertStringNotContainsString( 'color: ' . Estilos_PDF::UVA . ';', $base );
	}

	public function test_el_carnet_incluye_la_base(): void
	{
		$html = PDF_Card::get_html( self::SOCIO, 'light', true );

		$this->assertStringContainsString( 'Base común de los documentos', $html );
		$this->assertStringContainsString( Estilos_PDF::TIPOGRAFIA, $html );
	}

	public function test_los_tres_documentos_usan_la_base(): void
	{
		// La base se llama en el carnet, en el acuerdo (que la inyecta en la plantilla del sitio) y
		// en el certificado. Un documento nuevo que se olvide de incluirla falla aquí.
		$this->assertStringContainsString( 'Estilos_PDF::base()', $this->codigo( 'includes/PDF_Card.php' ) );
		$this->assertStringContainsString( 'Estilos_PDF::base()', $this->codigo( 'includes/PDF_Document.php' ) );
		$this->assertStringContainsString( 'Estilos_PDF::base()', $this->codigo( 'includes/Certificate_Generator.php' ) );
	}
}
