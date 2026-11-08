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
 * Resolución de las páginas del sitio.
 *
 * @package Convoca\Members
 */

namespace Convoca\Members;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Las páginas del producto, resueltas y no escritas a mano.
 *
 * Un enlace del carnet o de un correo apuntando a `/verificar-socio/` funciona mientras la página se
 * llame así. Si el sitio la renombra, el QR manda a un 404. Aquí se busca la página por su slug y, si no
 * está, la que lleva el shortcode; si tampoco, se usa el respaldo (#92).
 */
final class Paginas {

	/**
	 * URL de una página del producto.
	 *
	 * @param string $slug      Slug esperado.
	 * @param string $shortcode Shortcode que la identifica.
	 * @param string $respaldo  URL si no se encuentra (por defecto, la portada).
	 * @return string
	 */
	public static function url( string $slug, string $shortcode = '', string $respaldo = '' ): string {
		if ( '' !== $slug ) {
			$pagina = get_page_by_path( $slug, OBJECT, 'page' );
			if ( $pagina instanceof \WP_Post && 'publish' === $pagina->post_status ) {
				$url = get_permalink( $pagina );
				if ( $url ) {
					return (string) $url;
				}
			}
		}

		if ( '' !== $shortcode ) {
			$url = self::por_shortcode( $shortcode );
			if ( '' !== $url ) {
				return $url;
			}
		}

		return '' !== $respaldo ? $respaldo : home_url( '/' );
	}

	/**
	 * URL de la primera página publicada que lleva el shortcode.
	 *
	 * @param string $shortcode Shortcode.
	 * @return string Vacío si no hay ninguna.
	 */
	private static function por_shortcode( string $shortcode ): string {
		$paginas = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				's'              => $shortcode,
			)
		);

		foreach ( $paginas as $pagina ) {
			if ( has_shortcode( (string) $pagina->post_content, $shortcode ) ) {
				$url = get_permalink( $pagina );
				if ( $url ) {
					return (string) $url;
				}
			}
		}

		return '';
	}
}
