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
	 * Horas con el separador decimal del idioma del sitio.
	 *
	 * `number_format()` a secas deja siempre el punto, así que un certificado en castellano decía
	 * «18.5 horas». Esto respeta el idioma del sitio («18,5» en español, «18.5» en inglés).
	 *
	 * @param float $horas     Cantidad de horas.
	 * @param int   $decimales Decimales a mostrar.
	 * @return string Horas listas para el documento.
	 */
	public static function horas( float $horas, int $decimales = 1 ): string {
		return number_format_i18n( $horas, $decimales );
	}
}
