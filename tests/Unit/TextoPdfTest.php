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

	public function test_las_horas_usan_el_separador_del_idioma_del_sitio(): void
	{
		// Inglés: punto.
		$this->assertSame( '18.5', Texto_PDF::horas( 18.5 ) );

		// Castellano: coma. Es el caso real de Lugg.
		$GLOBALS['_test_decimal_coma'] = true;
		$this->assertSame( '18,5', Texto_PDF::horas( 18.5 ) );
		$this->assertSame( '4,0', Texto_PDF::horas( 4.0 ) );
	}
}
