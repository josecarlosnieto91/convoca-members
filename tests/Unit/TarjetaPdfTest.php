<?php
/**
 * La tarjeta de socio cuando se genera como PDF.
 *
 * El HTML de la tarjeta lo usan dos sitios distintos: el navegador (donde se ve con flexbox y un
 * botón para imprimir) y Dompdf (donde no hay flexbox, no existe `@media print` y el modelo de
 * caja es el antiguo). Estas pruebas fijan lo que se aprendió midiendo el PDF de verdad, porque
 * cada uno de estos detalles costaba una página de más o un botón impreso dentro de la tarjeta:
 *
 *  - Dompdf ignora `@media print`, así que el botón «IMPRIMIR / GUARDAR PDF» se colaba DENTRO del
 *    PDF. En modo PDF no se pinta.
 *  - Dompdf no respeta `box-sizing: border-box`: suma el padding y el borde a las medidas. La
 *    tarjeta, de 450x280, se iba a 510x340 y no cabía en un folio de 450x280 → dos páginas.
 *  - Dompdf no sabe hacer flexbox: las tres zonas de la tarjeta se apilaban y el QR se salía.
 *  - Un emoji en la etiqueta del plan no tiene glifo en Helvetica y sale un «?».
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\PDF_Card;
use PHPUnit\Framework\TestCase;

/**
 * El HTML de la tarjeta en modo navegador y en modo PDF.
 */
class TarjetaPdfTest extends TestCase
{
	private const SOCIO = 501;

	protected function setUp(): void
	{
		parent::setUp();
		$GLOBALS['_wp_stores']['post_meta']     = array();
		$GLOBALS['_wp_stores']['options']       = array();
		$GLOBALS['_test_document_theme']        = 'light';

		update_post_meta( self::SOCIO, '_convoca_numero_socio', 7 );
		update_post_meta( self::SOCIO, '_convoca_plan', 'bronce' );
		update_post_meta( self::SOCIO, '_convoca_fecha_alta', '2024-03-15' );
	}

	/** HTML del navegador (el de siempre). */
	private function navegador(): string
	{
		return PDF_Card::get_html( self::SOCIO, 'light' );
	}

	/** HTML que va a Dompdf. */
	private function pdf(): string
	{
		return PDF_Card::get_html( self::SOCIO, 'light', true );
	}

	public function test_el_navegador_sigue_teniendo_el_boton_de_imprimir(): void
	{
		$html = $this->navegador();

		$this->assertStringContainsString( 'IMPRIMIR / GUARDAR PDF', $html );
		$this->assertStringContainsString( 'window.print()', $html );
	}

	public function test_el_pdf_no_lleva_el_boton_de_imprimir_dentro(): void
	{
		$html = $this->pdf();

		// Dompdf ignora `@media print`: lo que se marque como «no-print» acaba igualmente en el PDF.
		$this->assertStringNotContainsString( 'IMPRIMIR / GUARDAR PDF', $html );
		$this->assertStringNotContainsString( 'window.print()', $html );
		// Ni el elemento: la regla CSS de `.btn-print` puede quedarse (sin botón no pinta nada).
		$this->assertStringNotContainsString( '<button', $html );
	}

	public function test_el_pdf_es_del_tamano_de_la_tarjeta_no_de_un_folio(): void
	{
		$html = $this->pdf();

		$this->assertStringContainsString( '@page { size: 450px 280px; margin: 0; }', $html );
		// Las medidas del CONTENIDO, porque Dompdf le suma el padding (30x2) y el borde (1x2).
		$this->assertStringContainsString( 'width: 388px; height: 218px;', $html );
	}

	public function test_el_pdf_no_usa_flexbox_ni_los_adornos_que_se_salen(): void
	{
		$html = $this->pdf();

		// Flexbox no existe en Dompdf y los adornos de 200px se salían del folio.
		$this->assertStringContainsString( '.card::before, .card::after { display: none; }', $html );
		$this->assertStringContainsString( '.header-right, .footer .qr-code { float: right; }', $html );
		$this->assertStringContainsString( '.body, .footer { clear: both; }', $html );
	}

	public function test_el_pdf_no_recorta_las_zonas_con_overflow(): void
	{
		// Medido: `overflow: hidden` en un contenedor con flotantes dentro hace que Dompdf lo
		// calcule con altura CERO y recorte su contenido. Con eso, el carnet salía en el PDF con
		// solo dos líneas de texto: el logo, las insignias, la fecha y el QR desaparecían de la
		// página (aunque siguieran en la capa de texto, que es lo que engaña a `pdftotext`).
		// El `.card` sí conserva su `overflow` de la hoja base: el que no puede volver es el de las
		// zonas con flotantes.
		$this->assertStringNotContainsString( '.header, .footer { overflow: hidden; }', $this->pdf() );
	}

	public function test_las_tres_zonas_del_pdf_se_reparten_en_la_tarjeta(): void
	{
		$html = $this->pdf();

		// El `justify-content: space-between` del navegador no existe en Dompdf: sin repartirlas,
		// el contenido se apelotonaba arriba y quedaba un tercio de la tarjeta vacío.
		$this->assertMatchesRegularExpression( '/\.header \{ position: absolute; top: 30px;/', $html );
		$this->assertMatchesRegularExpression( '/\.body \{ position: absolute; top: \d+px;/', $html );
		$this->assertMatchesRegularExpression( '/\.footer \{ position: absolute; top: \d+px;/', $html );
	}

	public function test_el_pdf_no_cambia_el_html_del_navegador(): void
	{
		$html = $this->navegador();

		// El modo PDF es un añadido: el navegador conserva su flexbox y sus adornos.
		$this->assertStringNotContainsString( '@page { size: 450px 280px', $html );
		$this->assertStringNotContainsString( '.header-right, .footer .qr-code { float: right; }', $html );
	}

	public function test_un_emoji_en_la_etiqueta_del_plan_sale_del_pdf(): void
	{
		update_option(
			'convoca_members_plans',
			array(
				'bronce' => array(
					'label' => '🏅 Bronce',
					'price' => 30,
				),
			)
		);

		// En el navegador el emoji se pinta bien y se queda.
		$this->assertStringContainsString( '🏅', $this->navegador() );

		// En el PDF, Helvetica no tiene el glifo y saldría un «?»: se quita el emoji y se deja el texto.
		$pdf = $this->pdf();
		$this->assertStringNotContainsString( '🏅', $pdf );
		$this->assertStringContainsString( 'Bronce', $pdf );
	}
}
