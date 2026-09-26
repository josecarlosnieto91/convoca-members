<?php
/**
 * La base de estilos que comparten todos los documentos de Convoca.
 *
 * Los tres documentos (carnet, acuerdo y certificado) usan la misma paleta y la misma tipografía, y
 * los tres se dibujan con Dompdf, que **no es un navegador**: cada regla que se le da por sabida
 * cuesta una tarde. Así que aquí viven dos cosas:
 *
 *  1. Los colores de la marca y la tipografía, en un solo sitio, para que no se separen.
 *  2. Las reglas que Dompdf necesita, con el porqué escrito al lado — este comentario es la
 *     documentación de las trampas, medidas una a una:
 *
 *   - **No hay flexbox.** `display:flex` no se interpreta: los hijos se apilan en vertical. Hay que
 *     colocar con flotantes (`float`) y bajar el bloque siguiente con `clear: both`.
 *   - **`overflow: hidden` con flotantes dentro RECORTA el contenido.** Dompdf calcula el
 *     contenedor con altura cero y se come lo de dentro; el texto sigue en el fichero (por eso
 *     `pdftotext` no lo delata) pero no se pinta. Para contener flotantes, el `clear` del bloque
 *     siguiente; nunca `overflow`.
 *   - **`box-sizing: border-box` no se respeta.** Dompdf SUMA el padding y el borde a las medidas:
 *     un bloque de 450×280 con `padding:30px` y borde de 1px se le va a 510×340. Hay que darle las
 *     medidas del CONTENIDO.
 *   - **La página se define con `@page { size: …; margin: … }`** (en píxeles se convierte exacto) y
 *     eso manda sobre el A4 por defecto.
 *   - **No existe `@media print`.** Lo que se marque como `no-print` acaba igualmente dentro del PDF.
 *   - **Solo están las tipografías del sistema** (Helvetica por defecto): un emoji sale como «?».
 *     Para eso está `Texto_PDF::sin_emoji()`, y para las cifras `Texto_PDF::horas()`.
 *   - **No hay variables CSS (`var(--x)`)**: los colores van escritos, por eso son constantes de PHP.
 *
 * La base se **antepone** a los estilos de cada documento, así que las reglas propias de cada uno
 * mandan y añadir esto no cambia lo que ya se veía.
 *
 * @package Convoca\Members
 */

namespace Convoca\Members;

/**
 * Base de estilos común de los documentos.
 */
final class Estilos_PDF {

	/** Morado de la marca (texto principal). */
	public const UVA = '#320028';

	/** Naranja de la marca (cifras y realces). */
	public const NARANJA = '#ff8700';

	/** Gris de los datos secundarios. */
	public const GRIS = '#5c4250';

	/** La tipografía de los documentos (Dompdf cae a Helvetica, que es lo que tiene). */
	public const TIPOGRAFIA = '"Segoe UI", Roboto, Helvetica, Arial, sans-serif';

	/**
	 * Reglas comunes a todos los documentos.
	 *
	 * Se antepone a la hoja de cada documento: define lo mínimo que comparten y deja que cada uno
	 * ponga encima lo suyo.
	 *
	 * @return string CSS de la base.
	 */
	public static function base(): string {
		return '
		/* ── Base común de los documentos (ver Estilos_PDF para el porqué de cada regla) ── */
		/* La tipografía y el margen del folio sí; el COLOR del texto NO: cada documento (y la
		   plantilla del acuerdo, que la edita el sitio) decide el suyo, y si esta base lo fijara,
		   por ir después en la hoja se impondría a lo que cada uno tiene escrito. */
		body { font-family: ' . self::TIPOGRAFIA . '; margin: 0; }
		img { max-width: 100%; }
		table { border-collapse: collapse; }
		/* Para los realces, sin volver a escribir los colores a mano. */
		.acento { color: ' . self::NARANJA . '; }
		.secundario { color: ' . self::GRIS . '; }
		';
	}
}
