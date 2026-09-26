<?php
/**
 * Detalles de texto de los documentos que se generan con Dompdf.
 *
 * Son dos cosas pequeñas que se repetían —o faltaban— según el documento, y que se notan en el
 * papel: los emojis, que la tipografía del PDF no sabe pintar, y el formato de las horas, que
 * tiene que seguir el idioma del sitio (en español, «18,5» con coma, no «18.5»).
 *
 * @package Convoca\Members
 */

namespace Convoca\Members;

/**
 * Ayudas de texto para los PDF de Members.
 */
final class Texto_PDF {

	/**
	 * Quita los emojis de un texto.
	 *
	 * Dompdf solo lleva la tipografía del sistema (Helvetica por defecto) y un emoji no tiene
	 * glifo: sale un «?» en medio del documento. Medido con la etiqueta de plan de la demo
	 * («🥉 Bronce» → «? Bronce») y con un carnet de socio.
	 *
	 * Solo se llama con valores decorativos (la etiqueta de un plan), nunca con el nombre de una
	 * persona: ahí el texto se muestra tal y como lo escribió, aunque algún glifo no se pinte.
	 *
	 * @param string $texto Texto de entrada.
	 * @return string Texto sin emojis, con los espacios sobrantes recortados.
	 */
	public static function sin_emoji( string $texto ): string {
		$limpio = preg_replace(
			// Los rangos de emoji, el selector de variación (FE0F), el enlace de ancho cero (200D,
			// que une los emojis de familia) y el combinador de teclas (20E3). Sin quitar también los
			// invisibles, quedaría basura dentro del texto.
			'/[\x{1F000}-\x{1FAFF}\x{2190}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE0F}\x{200D}\x{20E3}]/u',
			'',
			$texto
		);

		return trim( (string) $limpio );
	}

	/**
	 * Horas con la convención del castellano: coma decimal y punto de millar.
	 *
	 * NO se usa `number_format_i18n()` a propósito. Medido en la demo (sitio con locale `es_ES` y
	 * las traducciones cargadas): WordPress devolvía `decimal_point = '.'` y `thousands_sep = ','`,
	 * es decir el formato inglés — «1,234.5» —, así que el certificado salía con las letras en
	 * castellano y las cifras en inglés. Los documentos de Convoca están escritos en castellano, de
	 * modo que sus cifras van en castellano; y si un sitio quiere otra cosa, tiene el filtro.
	 *
	 * @param float $horas     Cantidad de horas.
	 * @param int   $decimales Decimales a mostrar.
	 * @return string Horas listas para el documento.
	 */
	public static function horas( float $horas, int $decimales = 1 ): string {
		$texto = number_format( $horas, $decimales, ',', '.' );

		/**
		 * Permite cambiar cómo se escriben las horas en los documentos.
		 *
		 * @param string $texto     Horas ya formateadas.
		 * @param float  $horas     Cantidad original.
		 * @param int    $decimales Decimales usados.
		 */
		return (string) apply_filters( 'convoca_documento_horas', $texto, $horas, $decimales );
	}
}
