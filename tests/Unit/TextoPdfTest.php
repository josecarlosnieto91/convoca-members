<?php
/**
 * Las ayudas de texto de los PDF: emojis y formato de las horas.
 *
 * Salieron de mirar los documentos: el plan de un socio se imprimía como «? Bronce» (la tipografía
 * de Dompdf no tiene el glifo del emoji) y el certificado decía «18.5 horas» en un sitio en
 * castellano, donde se escribe «18,5».
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Texto_PDF;
use PHPUnit\Framework\TestCase;

/**
 * Ayudas de texto para los documentos.
 */
class TextoPdfTest extends TestCase
{
	protected function tearDown(): void
	{
		unset( $GLOBALS['_test_decimal_coma'] );
		parent::tearDown();
	}

	public function test_quita_los_emojis_de_una_etiqueta(): void
	{
		// Los planes de la demo llevan emoji en la etiqueta.
		$this->assertSame( 'Bronce', Texto_PDF::sin_emoji( '🥉 Bronce' ) );
		$this->assertSame( 'Familiar Oro', Texto_PDF::sin_emoji( '👨‍👩‍👧 Familiar Oro 🏅' ) );
	}

	public function test_no_toca_un_texto_sin_emojis(): void
	{
		$this->assertSame( 'Reforestación del Nora', Texto_PDF::sin_emoji( 'Reforestación del Nora' ) );
		$this->assertSame( 'Ana García Menéndez', Texto_PDF::sin_emoji( 'Ana García Menéndez' ) );
	}

	public function test_deja_el_texto_sin_espacios_de_mas_al_quitar_el_emoji(): void
	{
		// Sin recortar, quedaría un hueco al principio de la etiqueta.
		$this->assertSame( 'Plata', Texto_PDF::sin_emoji( '  🥈  Plata  ' ) );
	}

	public function test_las_horas_van_con_coma_decimal(): void
	{
		// Un documento en castellano escribe «18,5», no «18.5». No se sigue el formato del sitio
		// porque WordPress puede devolver el inglés aunque el locale sea es_ES (medido en la demo).
		$this->assertSame( '18,5', Texto_PDF::horas( 18.5 ) );
		$this->assertSame( '4,0', Texto_PDF::horas( 4.0 ) );
		// Y el punto de millar, si algún día hay muchas horas.
		$this->assertSame( '1.234,5', Texto_PDF::horas( 1234.5 ) );
	}

	public function test_un_sitio_puede_cambiar_el_formato_de_las_horas(): void
	{
		// El filtro existe para quien quiera otra convención (p. ej. un sitio en otro idioma).
		$ingles = static fn( $texto ) => str_replace( ',', '.', $texto );
		add_filter( 'convoca_documento_horas', $ingles );

		$this->assertSame( '18.5', Texto_PDF::horas( 18.5 ) );

		remove_all_filters( 'convoca_documento_horas' );
	}
}
