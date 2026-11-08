<?php

/**
 * Convoca Members
 *
 * @package    Convoca\Members
 * @subpackage Includes
 *
 * @copyright  Copyright (C) 2026 Jose Carlos Nieto Ramos
 * @license    GPL-2.0-or-later
 */

/**
 * Teléfonos: normalización y validación.
 *
 * @package Convoca\Members
 */

namespace Convoca\Members;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normaliza lo que la gente escribe de verdad.
 *
 * `+34 600 111 222`, `600-111-222`, `600.111.222` y `(600) 111 222` son el mismo número. Antes solo se
 * quitaban espacios y cualquier otro formato se rechazaba con un mensaje que no decía qué hacer (#98).
 */
final class Telefono {

	/**
	 * Deja el número en nueve dígitos, sin prefijo ni separadores.
	 *
	 * @param string $valor Lo que llega del formulario.
	 * @return string
	 */
	public static function normalizar( string $valor ): string {
		$numero = preg_replace( '/[^0-9+]/', '', $valor );
		$numero = (string) $numero;

		// Prefijo internacional de España, escrito de las dos formas habituales.
		if ( str_starts_with( $numero, '+34' ) ) {
			$numero = substr( $numero, 3 );
		} elseif ( str_starts_with( $numero, '0034' ) ) {
			$numero = substr( $numero, 4 );
		}

		return ltrim( $numero, '+' );
	}

	/**
	 * ¿Es un número español válido?
	 *
	 * @param string $valor Número, ya normalizado o no.
	 * @return bool
	 */
	public static function es_valido( string $valor ): bool {
		return (bool) preg_match( '/^[6-9][0-9]{8}$/', self::normalizar( $valor ) );
	}
}
