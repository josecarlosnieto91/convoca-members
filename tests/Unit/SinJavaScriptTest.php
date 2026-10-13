<?php
/**
 * Sin JavaScript, los formularios imprescindibles no pueden ser un callejón sin salida.
 *
 * MEDIDO el 13/10/2026 en el sitio vivo (demo, Playwright con `javaScriptEnabled: false`, 390x844):
 * los formularios de alta y de voluntariado no declaraban `method` ni `action`, así que el
 * navegador los enviaba como GET con los campos en la URL y WordPress lo resolvía como una
 * búsqueda vacía. Resultado literal: el visitante, después de rellenar el alta entera, acababa en
 * `https://demo.getconvoca.app/?s=` con el título «Página no encontrada» — sin un dato guardado y
 * sin una sola frase que le explicara qué había pasado.
 *
 * Arreglo (degradación declarada, criterio 20 del listón): el formulario envía por POST a su
 * propia página con un marcador, y cuando la petición llega SIN JavaScript el template explica la
 * situación y ofrece el canal de contacto; el `<noscript>` lo avisa antes de rellenar.
 *
 * Prueba en negativo: con el template de HEAD no hay aviso, no hay `<noscript>` y el formulario
 * vuelve a no declarar `method`.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

class SinJavaScriptTest extends TestCase
{
	private function raiz(): string
	{
		return dirname( __DIR__, 2 );
	}

	/**
	 * @return array<string, array{0: string, 1: string}> ruta => [marcador del POST, nombre legible]
	 */
	private function formularios(): array
	{
		return array(
			'templates/form-alta.php'         => array( 'convoca_sin_js_alta', 'el alta de socio' ),
			'templates/form-voluntariado.php' => array( 'convoca_sin_js_voluntariado', 'el voluntariado' ),
		);
	}

	public function test_plantilla_sin_js_avisa_en_vez_de_dejar_al_visitante_tirado(): void
	{
		foreach ( $this->formularios() as $ruta => $datos ) {
			$_POST = array( $datos[0] => '1' );

			ob_start();
			include $this->raiz() . '/' . $ruta;
			$salida = (string) ob_get_clean();

			$this->assertStringContainsString(
				'necesita JavaScript',
				$salida,
				"Sin JavaScript, {$datos[1]} tiene que explicar qué pasa."
			);
			$this->assertStringNotContainsString(
				'<form',
				$salida,
				"Sin JavaScript no puede pintarse el formulario: no hay quien lo envíe."
			);
			$this->assertStringContainsString(
				'role="status"',
				$salida,
				'El aviso tiene que ser anunciable por un lector de pantalla.'
			);
		}

		$_POST = array();
	}

	public function test_el_formulario_declara_post_y_marcador(): void
	{
		foreach ( $this->formularios() as $ruta => $datos ) {
			$codigo = (string) file_get_contents( $this->raiz() . '/' . $ruta );

			$this->assertMatchesRegularExpression(
				'/<form[^>]*method="post"/',
				$codigo,
				"{$datos[1]}: sin `method=\"post\"` el envío cae en una búsqueda de WordPress."
			);
			$this->assertStringContainsString(
				'name="' . $datos[0] . '"',
				$codigo,
				"{$datos[1]}: falta el marcador que detecta el envío sin JavaScript."
			);
			$this->assertStringContainsString(
				'<noscript>',
				$codigo,
				"{$datos[1]}: hay que avisar ANTES de que el visitante rellene el formulario."
			);
		}
	}
}
