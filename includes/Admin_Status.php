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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Status {

	const SEVERITY_OK      = 'ok';
	const SEVERITY_WARNING = 'warning';
	const SEVERITY_ERROR   = 'error';

	public static function run_all(): array {
		return array(
			self::check_php_version(),
			self::check_pages(),
			self::check_tables(),
			self::check_cron(),
		);
	}

	public static function check_php_version(): array {
		$v = PHP_VERSION;
		if ( version_compare( $v, '8.0', '>=' ) ) {
			return self::result( 'php', 'PHP', "Versión $v", self::SEVERITY_OK );
		}
		return self::result(
			'php',
			'PHP',
			"Versión $v (mínimo 8.0)",
			self::SEVERITY_ERROR,
			'Actualiza PHP a 8.0 o superior.'
		);
	}

	public static function check_pages(): array {
		$slug = 'mi-area';
		$page = get_page_by_path( $slug );
		if ( $page && $page->post_status === 'publish' ) {
			$has = has_shortcode( $page->post_content, 'convoca_mi_area' );
			return self::result(
				'page_mi_area',
				'Página Mi Área',
				$has ? 'Existe con shortcode [convoca_mi_area]' : 'Existe pero sin el shortcode',
				$has ? self::SEVERITY_OK : self::SEVERITY_WARNING,
				'Añade el shortcode [convoca_mi_area] a la página.'
			);
		}
		return self::result(
			'page_mi_area',
			'Página Mi Área',
			'No encontrada',
			self::SEVERITY_ERROR,
			'Crea una página con el shortcode [convoca_mi_area]'
		);
	}

	public static function check_tables(): array {
		global $wpdb;
		$tables  = array(
			"{$wpdb->prefix}convoca_logs",
			"{$wpdb->prefix}convoca_locks",
			"{$wpdb->prefix}convoca_member_sequence",
		);
		$missing = array();
		foreach ( $tables as $t ) {
			if ( ! $wpdb->get_var( "SHOW TABLES LIKE '$t'" ) ) {
				$missing[] = $t;
			}
		}
		if ( empty( $missing ) ) {
			return self::result( 'tables', 'Tablas BD', 'Todas las tablas existen', self::SEVERITY_OK );
		}
		return self::result(
			'tables',
			'Tablas BD',
			'Faltan: ' . implode( ', ', $missing ),
			self::SEVERITY_ERROR,
			'Desactiva y reactiva el plugin para crear las tablas.'
		);
	}

	public static function check_cron(): array {
		$hooks   = array( 'convoca_daily_event', 'convoca_weekly_event' );
		$missing = array();
		foreach ( $hooks as $h ) {
			$ts = wp_next_scheduled( $h );
			if ( ! $ts ) {
				$missing[] = $h;
			}
		}
		if ( empty( $missing ) ) {
			return self::result( 'cron', 'Cron', 'Eventos programados activos', self::SEVERITY_OK );
		}
		return self::result(
			'cron',
			'Cron',
			'Faltan: ' . implode( ', ', $missing ),
			self::SEVERITY_WARNING,
			'Los eventos se programan automáticamente al activar el plugin.'
		);
	}

	public static function render_page(): void {
		$results      = self::run_all();
		$has_errors   = false;
		$has_warnings = false;
		foreach ( $results as $r ) {
			if ( $r['severity'] === self::SEVERITY_ERROR ) {
				$has_errors = true;
			}
			if ( $r['severity'] === self::SEVERITY_WARNING ) {
				$has_warnings = true;
			}
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Estado del Sistema - Miembros', 'convoca-members' ); ?></h1>
			<div class="convoca-diagnostic">
				<div class="convoca-diagnostic-header convoca-diagnostic-header--<?php echo $has_errors ? 'error' : ( $has_warnings ? 'warning' : 'success' ); ?>">
					<div class="convoca-diagnostic-icon">
						<?php echo $has_errors ? '✗' : ( $has_warnings ? '⚠' : '✓' ); ?>
					</div>
					<div class="convoca-diagnostic-summary">
						<h3><?php echo $has_errors ? esc_html__( 'Se encontraron errores', 'convoca-members' ) : ( $has_warnings ? esc_html__( 'Atención: algunas comprobaciones requieren revisión', 'convoca-members' ) : esc_html__( 'Todo correcto', 'convoca-members' ) ); ?></h3>
						<p><?php printf( esc_html__( '%d comprobaciones realizadas.', 'convoca-members' ), count( $results ) ); ?></p>
					</div>
				</div>
				<div class="convoca-diagnostic-results">
					<?php foreach ( $results as $r ) : ?>
						<div class="convoca-diagnostic-row">
							<div class="convoca-diagnostic-severity convoca-badge--<?php echo esc_attr( $r['severity'] === self::SEVERITY_OK ? 'success' : ( $r['severity'] === self::SEVERITY_WARNING ? 'warning' : 'error' ) ); ?>">
								<?php echo $r['severity'] === self::SEVERITY_OK ? '✓' : ( $r['severity'] === self::SEVERITY_WARNING ? '⚠' : '✗' ); ?>
							</div>
							<div class="convoca-diagnostic-content">
								<strong><?php echo esc_html( $r['label'] ); ?></strong>
								<div class="convoca-diagnostic-message"><?php echo esc_html( $r['message'] ); ?></div>
								<?php if ( ! empty( $r['fix'] ) ) : ?>
									<div class="convoca-diagnostic-fix"><?php echo esc_html( $r['fix'] ); ?></div>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php
	}

	private static function result( string $id, string $label, string $message, string $severity, string $fix = '' ): array {
		return compact( 'id', 'label', 'message', 'severity', 'fix' );
	}
}
