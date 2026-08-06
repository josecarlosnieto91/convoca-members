<?php

/**
 * Convoca Members
 *
 * @package    Convoca\Members
 * @subpackage Admin
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
 * Admin Dashboard Widget for Convoca alerts and quick stats.
 *
 * Displays:
 * - Key metrics (active members, pending payments, expiring soon)
 * - Action items requiring attention
 * - Recent system events
 *
 * @package Convoca\Members
 */

namespace Convoca\Members;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Dashboard {

	public function __construct() {
		add_action( 'wp_dashboard_setup', array( $this, 'register_widget' ) );
		add_action( 'admin_head', array( $this, 'widget_styles' ) );
	}

	/**
	 * Register the dashboard widget.
	 */
	public function register_widget(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'convoca_dashboard_alerts',
			'🌿 ' . get_bloginfo( 'name' ) . ' — ' . __( 'Panel de control', 'convoca-members' ),
			array( $this, 'render_widget' )
		);
	}

	/**
	 * Render the dashboard widget content.
	 */
	public function render_widget(): void {
		$stats  = $this->get_stats();
		$alerts = $this->get_alerts( $stats );
		$recent = $this->get_recent_events();

		echo '<div class="conv-dashboard-widget">';

		// Quick stats row.
		echo '<div class="conv-dash-stats">';
		$this->render_stat( '👥', $stats['total_active'], 'Activos' );
		$this->render_stat( '⏳', $stats['pending_payment'], 'Pend. pago' );
		$this->render_stat( '⚠️', $stats['expiring_soon'], 'Vencen pronto' );
		$this->render_stat( '🕐', $stats['hours_month'] . 'h', 'Voluntariado (mes)' );
		echo '</div>';

		// Alerts section.
		if ( ! empty( $alerts ) ) {
			echo '<div class="conv-dash-alerts">';
			echo '<h4>' . esc_html__( '⚡ Requiere atención', 'convoca-members' ) . '</h4>';
			echo '<ul>';
			foreach ( $alerts as $alert ) {
				printf(
					'<li class="conv-alert--%s"><span class="conv-alert__icon">%s</span> %s %s</li>',
					esc_attr( $alert['severity'] ),
					esc_html( $alert['icon'] ),
					esc_html( $alert['message'] ),
					! empty( $alert['link'] ) ? '<a href="' . esc_url( $alert['link'] ) . '">Ver →</a>' : ''
				);
			}
			echo '</ul>';
			echo '</div>';
		} else {
			echo '<div class="conv-dash-ok"><span>✅</span> Todo en orden. No hay alertas pendientes.</div>';
		}

		// Recent events.
		if ( ! empty( $recent ) ) {
			echo '<div class="conv-dash-recent">';
			echo '<h4>' . esc_html__( '📋 Actividad reciente', 'convoca-members' ) . '</h4>';
			echo '<table class="conv-dash-table">';
			foreach ( $recent as $event ) {
				printf(
					'<tr><td class="conv-dash-time">%s</td><td>%s</td></tr>',
					esc_html( $event['time'] ),
					esc_html( $event['message'] )
				);
			}
			echo '</table>';
			echo '</div>';
		}

		// Quick links.
		echo '<div class="conv-dash-links">';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=conv-members' ) ) . '" class="button">Gestionar socios</a> ';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=conv-members-settings' ) ) . '" class="button">Ajustes</a> ';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=conv-horas' ) ) . '" class="button">' . esc_html__( 'Horas voluntariado', 'convoca-members' ) . '</a>';
		echo '</div>';

		echo '</div>';
	}

	/**
	 * Render a single stat box.
	 */
	private function render_stat( string $icon, $value, string $label ): void {
		printf(
			'<div class="conv-dash-stat"><span class="conv-dash-stat__icon">%s</span><span class="conv-dash-stat__value">%s</span><span class="conv-dash-stat__label">%s</span></div>',
			esc_html( $icon ),
			esc_html( $value ),
			esc_html( $label )
		);
	}

	/**
	 * Get current statistics.
	 */
	private function get_stats(): array {
		global $wpdb;

		$default_stats = array(
			'total_active'    => 0,
			'pending_payment' => 0,
			'expiring_soon'   => 0,
			'hours_month'     => 0,
			'new_month'       => 0,
			'pending_docs'    => 0,
			'recent_errors'   => 0,
		);

		try {
			$today      = wp_date( 'Y-m-d' );
			$week_later = wp_date( 'Y-m-d', strtotime( '+7 days' ) );
			$month_ago  = wp_date( 'Y-m-d', strtotime( '-30 days' ) );

			// Active members.
			$total_active = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM {$wpdb->posts} p
                 JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                 WHERE p.post_type = 'miembro' AND p.post_status = 'publish'
                 AND pm.meta_key = '_convoca_estado_miembro' AND pm.meta_value = 'activo'"
			);

			// Pending payment.
			$pending_payment = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM {$wpdb->posts} p
                 JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                 WHERE p.post_type = 'miembro' AND p.post_status = 'publish'
                 AND pm.meta_key = '_convoca_estado_miembro' AND pm.meta_value = 'pendiente_pago'"
			);

			// Expiring within 7 days.
			$expiring_soon = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->posts} p
                 JOIN {$wpdb->postmeta} pm_e ON p.ID = pm_e.post_id AND pm_e.meta_key = '_convoca_estado_miembro' AND pm_e.meta_value = 'activo'
                 JOIN {$wpdb->postmeta} pm_r ON p.ID = pm_r.post_id AND pm_r.meta_key = '_convoca_fecha_renovacion'
                 WHERE p.post_type = 'miembro' AND p.post_status = 'publish'
                 AND CAST(pm_r.meta_value AS DATE) BETWEEN %s AND %s",
					$today,
					$week_later
				)
			);

			// Volunteer hours this month.
			$hours_month = (float) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COALESCE(SUM(pm.meta_value), 0) FROM {$wpdb->posts} p
                 JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_convoca_horas'
                 WHERE p.post_type = 'registro_hora' AND p.post_status = 'publish'
                 AND p.post_date >= %s",
					$month_ago . ' 00:00:00'
				)
			);

			// New members this month.
			$new_month = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->posts} 
                 WHERE post_type = 'miembro' AND post_status = 'publish'
                 AND post_date >= %s",
					$month_ago . ' 00:00:00'
				)
			);

			// Pending documentation.
			$pending_docs = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM {$wpdb->posts} p
                 JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                 WHERE p.post_type = 'miembro' AND p.post_status = 'publish'
                 AND pm.meta_key = '_convoca_estado_miembro' AND pm.meta_value = 'pendiente_documentacion'"
			);

			// System errors (last 24h).
			$table_logs    = $wpdb->prefix . 'convoca_logs';
			$recent_errors = 0;
			// Basic check if table exists.
			$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_logs ) ) === $table_logs;

			if ( $table_exists ) {
				$recent_errors = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM $table_logs 
                     WHERE level = 'error' AND created_at >= %s",
						wp_date( 'Y-m-d H:i:s', strtotime( '-24 hours' ) )
					)
				);
			}

			return compact(
				'total_active',
				'pending_payment',
				'expiring_soon',
				'hours_month',
				'new_month',
				'pending_docs',
				'recent_errors'
			);
		} catch ( \Throwable $e ) {
			error_log( 'Convoca Dashboard Stats Error: ' . $e->getMessage() );
			return $default_stats;
		}
	}

	/**
	 * Generate actionable alerts from stats.
	 */
	private function get_alerts( array $stats ): array {
		$alerts = array();

		if ( $stats['expiring_soon'] > 0 ) {
			$alerts[] = array(
				'severity' => 'warning',
				'icon'     => '⚠️',
				'message'  => $stats['expiring_soon'] . ' membresía(s) vencen en los próximos 7 días.',
				'link'     => admin_url( 'admin.php?page=conv-members&status=activo' ),
			);
		}

		if ( $stats['pending_payment'] > 0 ) {
			$alerts[] = array(
				'severity' => 'warning',
				'icon'     => '💳',
				'message'  => $stats['pending_payment'] . ' socio(s) con pago pendiente.',
				'link'     => admin_url( 'admin.php?page=conv-members&status=pendiente_pago' ),
			);
		}

		if ( $stats['pending_docs'] > 0 ) {
			$alerts[] = array(
				'severity' => 'info',
				'icon'     => '📄',
				'message'  => $stats['pending_docs'] . ' solicitud(es) pendiente(s) de documentación.',
				'link'     => admin_url( 'admin.php?page=conv-members&status=pendiente_documentacion' ),
			);
		}

		if ( $stats['recent_errors'] > 0 ) {
			$alerts[] = array(
				'severity' => 'error',
				'icon'     => '🔴',
				'message'  => $stats['recent_errors'] . ' error(es) del sistema en las últimas 24h.',
				'link'     => admin_url( 'admin.php?page=conv-members-logs' ),
			);
		}

		return $alerts;
	}

	/**
	 * Get recent system events for the dashboard.
	 */
	private function get_recent_events(): array {
		$logs = \Convoca\Core\Logger::get_logs(
			array(
				'limit' => 8,
			)
		);

		$events = array();
		foreach ( $logs as $log ) {
			$time = '';
			if ( ! empty( $log['created_at'] ) ) {
				$diff = human_time_diff( strtotime( $log['created_at'] ), time() );
				$time = 'hace ' . $diff;
			}

			$events[] = array(
				'time'    => $time,
				'message' => mb_substr( $log['message'] ?? '', 0, 80 ),
			);
		}

		return $events;
	}

	/**
	 * Inline styles for the dashboard widget.
	 */
	public function widget_styles(): void {
		$screen = get_current_screen();
		if ( ! $screen || $screen->id !== 'dashboard' ) {
			return;
		}

		echo '<style>
        .conv-dashboard-widget { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        
        .conv-dash-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-bottom: 15px;
        }
        .conv-dash-stat {
            text-align: center;
            padding: 12px 8px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        .conv-dash-stat__icon { display: block; font-size: 20px; margin-bottom: 4px; }
        .conv-dash-stat__value { display: block; font-size: 22px; font-weight: 700; color: #2c5e3e; }
        .conv-dash-stat__label { display: block; font-size: 11px; color: #6c757d; margin-top: 2px; }
        
        .conv-dash-alerts { margin-bottom: 15px; }
        .conv-dash-alerts h4 { margin: 0 0 8px; font-size: 14px; }
        .conv-dash-alerts ul { margin: 0; padding: 0; list-style: none; }
        .conv-dash-alerts li {
            padding: 8px 12px;
            margin-bottom: 4px;
            border-radius: 6px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .conv-dash-alerts li a { margin-left: auto; font-size: 12px; text-decoration: none; }
        .conv-alert--warning { background: #fff3cd; border: 1px solid #ffc107; }
        .conv-alert--error { background: #f8d7da; border: 1px solid #f5c6cb; }
        .conv-alert--info { background: #d1ecf1; border: 1px solid #bee5eb; }
        
        .conv-dash-ok {
            text-align: center;
            padding: 15px;
            background: #d4edda;
            border-radius: 8px;
            color: #155724;
            margin-bottom: 15px;
        }
        
        .conv-dash-recent { margin-bottom: 15px; }
        .conv-dash-recent h4 { margin: 0 0 8px; font-size: 14px; }
        .conv-dash-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .conv-dash-table tr { border-bottom: 1px solid #f0f0f0; }
        .conv-dash-table td { padding: 5px 0; }
        .conv-dash-time { color: #6c757d; white-space: nowrap; width: 80px; }
        
        .conv-dash-links { padding-top: 10px; border-top: 1px solid #e9ecef; }
        .conv-dash-links .button { font-size: 12px; }
        </style>';
	}
}
