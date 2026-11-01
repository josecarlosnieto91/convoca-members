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

namespace Convoca\Members;

use Convoca\Core\Admin\Diagnostico;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Estado del sistema de Members.
 *
 * El pintado y las comprobaciones genéricas viven en Core (`Diagnostico`). Aquí queda lo
 * que es de este plugin: qué página necesita y qué tablas y eventos son suyos.
 */
class Admin_Status {

	const SEVERITY_OK      = Diagnostico::SEVERITY_OK;
	const SEVERITY_WARNING = Diagnostico::SEVERITY_WARNING;
	const SEVERITY_ERROR   = Diagnostico::SEVERITY_ERROR;

	/**
	 * Ejecuta todas las comprobaciones.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function run_all(): array {
		return array(
			self::check_php_version(),
			self::check_pages(),
			self::check_tables(),
			self::check_cron(),
		);
	}

	/**
	 * Versión de PHP.
	 *
	 * @return array<string, mixed>
	 */
	public static function check_php_version(): array {
		return Diagnostico::php( '8.0' );
	}

	/**
	 * Página pública del área del socio.
	 *
	 * @return array<string, mixed>
	 */
	public static function check_pages(): array {
		$slug = 'mi-area';
		$page = get_page_by_path( $slug );
		if ( $page && $page->post_status === 'publish' ) {
			$has = has_shortcode( $page->post_content, 'convoca_mi_area' );
			return Diagnostico::resultado(
				'page_mi_area',
				'Página Mi Área',
				$has ? 'Existe con shortcode [convoca_mi_area]' : 'Existe pero sin el shortcode',
				$has ? self::SEVERITY_OK : self::SEVERITY_WARNING,
				'Añade el shortcode [convoca_mi_area] a la página.'
			);
		}
		return Diagnostico::resultado(
			'page_mi_area',
			'Página Mi Área',
			'No encontrada',
			self::SEVERITY_ERROR,
			'Crea una página con el shortcode [convoca_mi_area]'
		);
	}

	/**
	 * Tablas propias del plugin.
	 *
	 * @return array<string, mixed>
	 */
	public static function check_tables(): array {
		global $wpdb;

		return Diagnostico::tablas(
			array(
				"{$wpdb->prefix}convoca_logs",
				"{$wpdb->prefix}convoca_locks",
				"{$wpdb->prefix}convoca_member_sequence",
			),
			'Desactiva y reactiva el plugin para crear las tablas.'
		);
	}

	/**
	 * Eventos cron del plugin.
	 *
	 * @return array<string, mixed>
	 */
	public static function check_cron(): array {
		return Diagnostico::cron(
			array( 'convoca_daily_event', 'convoca_weekly_event' ),
			'Los eventos se programan automáticamente al activar el plugin.'
		);
	}

	/**
	 * Pinta la pantalla de estado.
	 */
	public static function render_page(): void {
		Diagnostico::pintar(
			self::run_all(),
			__( 'Estado del Sistema - Miembros', 'convoca-members' )
		);
	}
}
