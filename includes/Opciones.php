<?php

/**
 * Convoca Members
 *
 * @package    Convoca\Members
 * @subpackage Includes
 *
 * @copyright  Copyright (C) 2026 Jose Carlos Nieto Ramos
 * @license    GPL-2.0-or-later
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 */

/**
 * Ajustes compartidos por el alta de socio y el formulario de voluntariado.
 *
 * @package Convoca\Members
 */

namespace Convoca\Members;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lectura de ajustes con su valor por defecto, en un solo sitio.
 */
final class Opciones {

	/** El DNI se pide y es obligatorio. */
	public const DNI_OBLIGATORIO = 'obligatorio';

	/** El DNI se pide, pero se puede dejar vacío. */
	public const DNI_OPCIONAL = 'opcional';

	/** El DNI no se pide ni se guarda. */
	public const DNI_NO = 'no';

	/** Opción donde viven los ajustes de socios. */
	public const OPCION = 'convoca_members_settings';

	/**
	 * Modo del DNI.
	 *
	 * Por defecto «obligatorio»: es como se comportaba el producto antes del ajuste y no cambia nada en
	 * los sitios ya en marcha (issue #90).
	 *
	 * @return string Uno de los tres modos.
	 */
	public static function dni_modo(): string {
		$ajustes = get_option( self::OPCION, array() );
		$modo    = is_array( $ajustes ) ? (string) ( $ajustes['dni_modo'] ?? '' ) : '';

		$validos = array( self::DNI_OBLIGATORIO, self::DNI_OPCIONAL, self::DNI_NO );

		return in_array( $modo, $validos, true ) ? $modo : self::DNI_OBLIGATORIO;
	}

	/**
	 * ¿El DNI es obligatorio?
	 *
	 * @return bool
	 */
	public static function dni_obligatorio(): bool {
		return self::DNI_OBLIGATORIO === self::dni_modo();
	}

	/**
	 * ¿Hay que pedir el DNI?
	 *
	 * @return bool
	 */
	public static function dni_se_pide(): bool {
		return self::DNI_NO !== self::dni_modo();
	}

	/**
	 * Campo del DNI, con su etiqueta y su aviso.
	 *
	 * Lo pintan el alta de socio y el voluntariado: antes eran dos bloques copiados y los dos exigían el
	 * dato. Con el modo «no pedirlo» devuelve cadena vacía, y el formulario no deja un hueco.
	 *
	 * @param string $id Identificador del campo (distinto en cada formulario).
	 * @return string HTML listo para imprimir.
	 */
	public static function campo_dni( string $id ): string {
		if ( ! self::dni_se_pide() ) {
			return '';
		}

		$obligatorio = self::dni_obligatorio();
		$etiqueta    = $obligatorio
			? __( 'DNI / NIE *', 'convoca-members' )
			: __( 'DNI / NIE (opcional)', 'convoca-members' );

		return '<div class="convoca-field">'
			. '<label for="' . esc_attr( $id ) . '">' . esc_html( $etiqueta ) . '</label>'
			. '<input type="text" id="' . esc_attr( $id ) . '" name="dni" placeholder="12345678A"'
			. ( $obligatorio ? ' required' : '' )
			. ' pattern="([0-9]{8}[A-Za-z]|[XYZxyz][0-9]{7}[A-Za-z])" autocomplete="off">'
			. '<span class="convoca-error-msg">' . esc_html__( 'Introduce un DNI o NIE válido.', 'convoca-members' ) . '</span>'
			. '</div>';
	}
}
