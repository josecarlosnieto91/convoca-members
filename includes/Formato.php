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
 * Cómo se escriben el precio y las horas del plan.
 *
 * @package Convoca\Members
 */

namespace Convoca\Members;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Texto de precio y horas tal y como lo lee una persona.
 *
 * «Desde» solo tiene sentido cuando hay más de una modalidad donde elegir; con una sola, el precio va
 * tal cual. Y las horas llevan coma decimal: 22,5 h, no 22.5h (#100).
 */
final class Formato {

	/**
	 * Precio de una modalidad.
	 *
	 * @param array<int|string, array<string, mixed>> $planes Planes de esa modalidad.
	 * @return string
	 */
	public static function precio( array $planes ): string {
		if ( array() === $planes ) {
			return '';
		}

		$min = PHP_FLOAT_MAX;
		foreach ( $planes as $plan ) {
			$min = min( $min, (float) ( $plan['price'] ?? 0 ) );
		}
		if ( PHP_FLOAT_MAX === $min ) {
			return '';
		}

		if ( 1 === count( $planes ) ) {
			/* translators: %s es el precio en euros. */
			return sprintf( __( '%s€', 'convoca-members' ), self::euros( $min ) );
		}

		/* translators: %s es el precio en euros del plan más barato. */
		return sprintf( __( 'Desde %s€', 'convoca-members' ), self::euros( $min ) );
	}

	/**
	 * Horas con coma decimal y sin ceros de más.
	 *
	 * @param mixed $horas Horas del plan.
	 * @return string
	 */
	public static function horas( $horas ): string {
		$texto = number_format_i18n( (float) $horas, 1 );

		return rtrim( rtrim( $texto, '0' ), ',' );
	}

	/**
	 * Euros: sin decimales si son cero, con dos si el precio los lleva.
	 *
	 * @param float $importe Importe.
	 * @return string
	 */
	private static function euros( float $importe ): string {
		return number_format_i18n( $importe, fmod( $importe, 1.0 ) > 0 ? 2 : 0 );
	}
}
