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
 * Admin Logs page: visualize logs from the centralized Convoca logs table.
 *
 * @package Convoca\Members
 */

namespace Convoca\Members;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Logs {

	/**
	 * Render the logs page.
	 */
	public static function render(): void {
		global $wpdb;
		$table_name = $wpdb->prefix . 'convoca_logs';

		// Check if table exists.
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
			echo '<div class="wrap"><h1>Logs</h1><p>La tabla de logs no ha sido creada. Desactiva y reactiva el plugin <strong>Convoca Common</strong> para crearla.</p></div>';
			return;
		}

		// Handle delete action.
		$get_data = wp_unslash( $_GET );
		if ( isset( $get_data['action'] ) && $get_data['action'] === 'clear' && current_user_can( 'common_view_logs' ) ) {
			check_admin_referer( 'convoca_clear_members_logs' );
			$wpdb->query( $wpdb->prepare( "DELETE FROM $table_name WHERE context LIKE %s", '%Members%' ) );
			echo '<div class="updated"><p>Logs de Members borrados.</p></div>';
		}

		$pagenum  = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
		$per_page = 50;
		$offset   = ( $pagenum - 1 ) * $per_page;

		// Filter to Members-related logs.
		$total_items = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $table_name WHERE context LIKE %s",
				'%Members%'
			)
		);
		$num_pages   = ceil( $total_items / $per_page );

		$logs = $wpdb->get_results(
			$wpdb->prepare(
				"
            SELECT * FROM $table_name 
            WHERE context LIKE %s
            ORDER BY created_at DESC 
            LIMIT %d OFFSET %d
        ",
				'%Members%',
				$per_page,
				$offset
			)
		);

		?>
		<div class="wrap">
			<h1>Registros de Auditoría</h1>

			<p>
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=conv-members-logs&action=clear' ), 'convoca_clear_members_logs' ) ); ?>" 
					class="button button-secondary"
					onclick="return confirm('<?php echo esc_js( __( '¿Estás seguro de que quieres borrar todos los logs de Members?', 'convoca-members' ) ); ?>');">
					Borrar logs de Members
				</a>
			</p>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width: 170px;">Fecha</th>
						<th style="width: 100px;">Nivel</th>
						<th>Mensaje</th>
						<th style="width: 150px;">Contexto</th>
						<th style="width: 100px;">ID Objeto</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( $logs ) : ?>
						<?php foreach ( $logs as $log ) : ?>
							<tr>
								<td><?php echo esc_html( $log->created_at ); ?></td>
								<td>
									<?php echo wp_kses_post( \Convoca\Core\Utils::render_log_level_badge( $log->level ) ); ?>
								</td>
								<td><?php echo esc_html( $log->message ); ?></td>
								<td><code><?php echo esc_html( $log->context ); ?></code></td>
								<td>
									<?php if ( $log->object_id ) : ?>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=conv-members&member_id=' . $log->object_id ) ); ?>">#<?php echo (int) $log->object_id; ?></a>
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr><td colspan="5">No hay logs registrados.</td></tr>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ( $num_pages > 1 ) : ?>
				<div class="tablenav">
					<div class="tablenav-pages">
						<?php
						echo wp_kses_post( paginate_links(
							array(
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'prev_text' => '&laquo;',
								'next_text' => '&raquo;',
								'total'     => $num_pages,
							'current'   => $pagenum,
						)
					) );
					?>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
