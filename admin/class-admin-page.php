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
 * Admin page: menu registration, dashboard widget, and member detail view.
 *
 * @package Convoca\Members
 */

namespace Convoca\Members;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Page {


	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'dashboard_widget' ) );

		// Quick state-change AJAX.
		add_action( 'wp_ajax_convoca_change_state', array( $this, 'ajax_change_state' ) );
		add_action( 'wp_ajax_convoca_log_whatsapp', array( $this, 'ajax_log_whatsapp' ) );
		add_action( 'admin_post_convoca_export_members_pdf', array( $this, 'handle_export_members_pdf' ) );
	}

	/* ── Menu ──────────────────────────────────── */

	public function register_menu(): void {
		add_menu_page(
			__( 'Miembros Convoca', 'convoca-members' ),
			__( 'Miembros', 'convoca-members' ),
			'gestionar_miembros',
			'conv-members',
			array( $this, 'render_list_page' ),
			'dashicons-groups',
			26
		);

		add_submenu_page(
			'conv-members',
			__( 'Todos los Miembros', 'convoca-members' ),
			__( 'Todos los Miembros', 'convoca-members' ),
			'gestionar_miembros',
			'conv-members',
			array( $this, 'render_list_page' )
		);

		add_submenu_page(
			'conv-members',
			__( 'Añadir nuevo Miembro', 'convoca-members' ),
			__( 'Añadir nuevo', 'convoca-members' ),
			'gestionar_miembros',
			Admin_Member_Editor::SLUG,
			array( new Admin_Member_Editor(), 'render' )
		);

		add_submenu_page(
			'conv-members',
			__( 'Gestionar Voluntarios', 'convoca-members' ),
			__( 'Voluntarios', 'convoca-members' ),
			'gestionar_miembros',
			'conv-members-voluntarios',
			array( $this, 'render_list_page' )
		);

		add_submenu_page(
			'conv-members',
			__( 'Añadir nuevo Voluntario', 'convoca-members' ),
			__( 'Añadir voluntario', 'convoca-members' ),
			'gestionar_miembros',
			Admin_Member_Editor::SLUG . '-voluntario',
			array( new Admin_Member_Editor(), 'render' )
		);

		add_submenu_page(
			'conv-members',
			__( 'Ajustes', 'convoca-members' ),
			__( 'Ajustes', 'convoca-members' ),
			'manage_options',
			'conv-members-settings',
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			'conv-members',
			__( 'Registros', 'convoca-members' ),
			__( 'Registros', 'convoca-members' ),
			'common_view_logs',
			'conv-members-logs',
			array( $this, 'render_logs_page' )
		);

		add_submenu_page(
			'conv-members',
			__( 'Estado del Sistema', 'convoca-members' ),
			__( 'Estado', 'convoca-members' ),
			'manage_options',
			'conv-members-status',
			function () {
				if ( ! class_exists( '\\Convoca\\Members\\Admin_Status' ) ) {
					require_once CONVOCA_MEMBERS_DIR . 'includes/class-admin-status.php';
				}
				\Convoca\Members\Admin_Status::render_page();
			}
		);
	}

	/* ── Assets ────────────────────────────────── */

	public function enqueue_assets( string $hook ): void {
		if ( ! str_contains( $hook, 'conv-members' ) ) {
			return;
		}
		wp_enqueue_style(
			'conv-members-admin',
			CONVOCA_MEMBERS_URL . 'assets/css/convoca-members-admin.css',
			array(),
			CONVOCA_MEMBERS_VERSION
		);
		wp_enqueue_script(
			'conv-members-admin',
			CONVOCA_MEMBERS_URL . 'assets/js/convoca-members-admin.js',
			array( 'convoca-common-admin-js' ),
			CONVOCA_MEMBERS_VERSION,
			true
		);
		wp_localize_script(
			'conv-members-admin',
			'convAdmin',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'convoca_admin_nonce' ),
				'csvNonce'       => wp_create_nonce( 'convoca_export_csv' ),
				'allColumns'     => CSV_Exporter::get_available_columns(),
				'defaultColumns' => CSV_Exporter::get_default_columns(),
				'userColumns'    => CSV_Exporter::get_user_columns(),
				'columnsUrl'     => admin_url( 'admin-ajax.php?action=convoca_export_csv&nonce=' . wp_create_nonce( 'convoca_export_csv' ) ),
			)
		);
	}

	/* ── List page ─────────────────────────────── */

	public function render_list_page(): void {
		// Detail view.
		if ( isset( $_GET['member_id'] ) ) {
			$this->render_detail( (int) $_GET['member_id'] );
			return;
		}

		// Mostrar mensajes de feedback después de acciones admin-post.
		$msg = sanitize_text_field( wp_unslash( $_GET['msg'] ?? '' ) );
		if ( $msg === 'approved' ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Miembro aprobado correctamente.', 'convoca-members' ) . '</p></div>';
		} elseif ( $msg === 'deleted' ) {
			echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Miembro enviado a la papelera.', 'convoca-members' ) . '</p></div>';
		} elseif ( $msg === 'error' ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Error al procesar la acción. Inténtalo de nuevo.', 'convoca-members' ) . '</p></div>';
		}

		$list = new Admin_List();
		$list->prepare_items();
		$is_voluntarios = ! empty( $_GET['voluntarios'] ) || ( isset( $_GET['page'] ) && $_GET['page'] === 'conv-members-voluntarios' );
		?>
		<div class="wrap conv-members-page">
			<h1 class="wp-heading-inline">
				<?php if ( $is_voluntarios ) : echo esc_html( get_bloginfo( 'name' ) ) . ' — '; esc_html_e( 'Voluntarios', 'convoca-members' ); else : esc_html_e( 'Miembros Convoca', 'convoca-members' ); endif; ?>
			</h1>
			<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=miembro' . ( $is_voluntarios ? '&es_voluntario=1' : '' ) ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Añadir nuevo', 'convoca-members' ); ?>
			</a>
			<hr class="wp-header-end">

			<!-- Stats bar -->
			<?php $this->render_stats_bar(); ?>

			<!-- Filters -->
			<form method="get">
				<input type="hidden" name="page" value="conv-members">
				<?php wp_nonce_field( 'bulk-members', '_convoca_nonce', true, false ); ?>
				<?php $list->search_box( __( 'Buscar', 'convoca-members' ), 'conv-search' ); ?>
				<?php $list->display(); ?>
			</form>

			<!-- CSV / PDF export -->
			<p style="margin-top:1rem">
				<button type="button" id="conv-csv-export-btn"
					class="convoca-btn convoca-btn-outline">📥
					<?php esc_html_e( 'Exportar CSV', 'convoca-members' ); ?>
				</button>
				<?php if ( \Convoca\Core\License_Manager::has_pro( 'pdf_memories' ) ) : ?>
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=convoca_export_members_pdf' ), 'convoca_export_members_pdf' ) ); ?>"
					class="convoca-btn convoca-btn-outline" style="margin-left:5px;">📄
					<?php esc_html_e( 'Exportar PDF', 'convoca-members' ); ?>
				</a>
				<?php else : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=convoca-license' ) ); ?>"
					class="convoca-btn convoca-btn-outline" style="margin-left:5px;opacity:0.6;" title="<?php esc_attr_e( 'Disponible en PRO', 'convoca-members' ); ?>">🔒
					<?php esc_html_e( 'Exportar PDF (PRO)', 'convoca-members' ); ?>
				</a>
				<?php endif; ?>
			</p>

			<?php $this->render_csv_modal(); ?>
		</div>
		<?php
	}

	/* ── Stats bar ─────────────────────────────── */

	private function render_stats_bar(): void {
		global $wpdb;

		$counts = array();
		foreach ( Estados::STATES as $state ) {
			$counts[ $state ] = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->postmeta} pm
				 JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = '_convoca_estado_miembro' AND pm.meta_value = %s
				   AND p.post_type = 'miembro' AND p.post_status = 'publish'",
					$state
				)
			);
		}

		// Members without any estado meta are treated as "pendiente_documentacion".
		$sin_estado = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} p
			 WHERE p.post_type = 'miembro' AND p.post_status = 'publish'
			   AND NOT EXISTS (
				   SELECT 1 FROM {$wpdb->postmeta} pm
				    WHERE pm.post_id = p.ID AND pm.meta_key = '_convoca_estado_miembro'
			   )"
		);
		$counts['pendiente_documentacion'] += $sin_estado;

		// Total = all published members.
		$total = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			 WHERE post_type = 'miembro' AND post_status = 'publish'"
		);
		?>
		<div class="conv-stats-bar">
			<div class="conv-stat"><span class="conv-stat-num">
					<?php echo (int) $counts['activo']; ?>
				</span> <?php esc_html_e( 'Activos', 'convoca-members' ); ?></div>
			<div class="conv-stat"><span class="conv-stat-num">
					<?php echo (int) $counts['pendiente_pago'] + (int) $counts['pendiente_documentacion']; ?>
				</span> <?php esc_html_e( 'Pendientes', 'convoca-members' ); ?></div>
			<div class="conv-stat"><span class="conv-stat-num">
					<?php echo (int) $counts['baja']; ?>
				</span> <?php esc_html_e( 'Bajas', 'convoca-members' ); ?></div>
			<div class="conv-stat"><span class="conv-stat-num">
					<?php echo (int) $total; ?>
				</span> <?php esc_html_e( 'Total', 'convoca-members' ); ?></div>
		</div>
		<?php
	}

	/* ── Detail view ───────────────────────────── */

	private function render_detail( int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post || $post->post_type !== 'miembro' ) {
			echo '<div class="wrap conv-members-page"><p>Miembro no encontrado.</p></div>';
			return;
		}

		$meta         = fn( string $key ) => get_post_meta( $post_id, '_convoca_' . $key, true );
		$estado       = $meta( 'estado_miembro' ) ?: 'pendiente_documentacion';
		$history      = Estados::get_history( $post_id );
		$terms        = wp_get_object_terms( $post_id, 'tipo_miembro', array( 'fields' => 'names' ) );
		$tipo_miembro = is_wp_error( $terms ) ? '—' : implode( ', ', $terms );
		?>
		<div class="wrap conv-members-page">
			<h1>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=conv-members' ) ); ?>">← <?php esc_html_e( 'Listado', 'convoca-members' ); ?></a>
				&nbsp;
				<?php echo esc_html( $post->post_title ); ?>
				<?php echo wp_kses_post( Estados::badge_html( $estado ) ); ?>
				&nbsp;
				<?php if ( \Convoca\Core\License_Manager::has_pro( 'pdf_memories' ) ) : ?>
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=convoca_pdf_card&member_id=' . $post_id ), 'convoca_pdf_card_' . $post_id ) ); ?>" 
					class="convoca-btn convoca-btn-outline" target="_blank">🪪 <?php esc_html_e( 'Ver Tarjeta', 'convoca-members' ); ?></a>
				<?php else : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=convoca-license' ) ); ?>" 
					class="convoca-btn convoca-btn-outline" target="_blank" style="opacity:0.6;" title="<?php esc_attr_e( 'Disponible en PRO', 'convoca-members' ); ?>">🔒 <?php esc_html_e( 'Ver Tarjeta (PRO)', 'convoca-members' ); ?></a>
				<?php endif; ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Admin_Member_Editor::SLUG . '&id=' . $post_id ) ); ?>" 
					class="convoca-btn convoca-btn-primary"><?php esc_html_e( '✏️ Editar', 'convoca-members' ); ?></a>
			</h1>
			<hr class="wp-header-end">

			<div class="conv-detail-grid">
				<div class="conv-detail-card">
					<h3><?php esc_html_e( 'Datos personales', 'convoca-members' ); ?></h3>
					<table class="widefat striped">
						<tr>
							<th><?php esc_html_e( 'Email', 'convoca-members' ); ?></th>
							<td>
								<?php echo esc_html( $meta( 'email' ) ); ?>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'DNI', 'convoca-members' ); ?></th>
							<td>
								<?php echo esc_html( $meta( 'dni' ) ); ?>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Teléfono', 'convoca-members' ); ?></th>
							<td>
								<?php echo esc_html( $meta( 'telefono' ) ); ?>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'WhatsApp', 'convoca-members' ); ?></th>
							<td>
								<?php echo esc_html( $meta( 'whatsapp' ) ); ?>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Dirección', 'convoca-members' ); ?></th>
							<td>
								<?php echo esc_html( $meta( 'direccion' ) . ', ' . $meta( 'municipio' ) ); ?>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'F. Nacimiento', 'convoca-members' ); ?></th>
							<td>
								<?php echo esc_html( $meta( 'fecha_nacimiento' ) ); ?>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Tipo', 'convoca-members' ); ?></th>
							<td>
								<?php echo esc_html( $tipo_miembro ); ?>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Plan', 'convoca-members' ); ?></th>
							<td>
								<?php echo esc_html( $meta( 'plan' ) . ( $meta( 'sub_plan' ) ? ' / ' . $meta( 'sub_plan' ) : '' ) ); ?>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Forma de pago', 'convoca-members' ); ?></th>
							<td>
								<?php echo esc_html( $meta( 'forma_pago' ) ); ?>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Alta', 'convoca-members' ); ?></th>
							<td>
								<?php echo get_the_date( 'd/m/Y', $post ); ?>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Renovación', 'convoca-members' ); ?></th>
							<td>
								<?php echo esc_html( $meta( 'fecha_renovacion' ) ); ?>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'RGPD', 'convoca-members' ); ?></th>
							<td>
								<?php echo esc_html( $meta( 'rgpd_version' ) ); ?> —
								<?php echo esc_html( $meta( 'rgpd_timestamp' ) ); ?>
							</td>
						</tr>
					</table>
				</div>

				<div class="conv-detail-card">
					<h3><?php esc_html_e( 'Cambiar estado', 'convoca-members' ); ?></h3>
					<form id="conv-state-form">
						<input type="hidden" name="post_id" value="<?php echo (int) $post_id; ?>">
						<select name="nuevo_estado">
							<?php foreach ( Estados::TRANSITIONS[ $estado ] ?? array() as $target ) : ?>
								<option value="<?php echo esc_attr( $target ); ?>">
									<?php echo esc_html( Estados::labels()[ $target ] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<input type="text" name="nota" placeholder="<?php esc_attr_e( 'Nota (opcional)', 'convoca-members' ); ?>" style="margin-top:.5rem;width:100%">
						<button type="submit" class="button button-primary" style="margin-top:.5rem"><?php esc_html_e( 'Cambiar estado', 'convoca-members' ); ?></button>
					</form>

					<h3 style="margin-top:1.5rem"><?php esc_html_e( 'Historial', 'convoca-members' ); ?></h3>
					<?php if ( empty( $history ) ) : ?>
						<p><?php esc_html_e( 'Sin historial todavía.', 'convoca-members' ); ?></p>
					<?php else : ?>
						<table class="widefat striped">
							<thead>
								<tr>
									<th><?php esc_html_e( 'De', 'convoca-members' ); ?></th>
									<th><?php esc_html_e( 'A', 'convoca-members' ); ?></th>
									<th><?php esc_html_e( 'Fecha', 'convoca-members' ); ?></th>
									<th><?php esc_html_e( 'Nota', 'convoca-members' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( array_reverse( $history ) as $entry ) : ?>
									<tr>
										<td>
											<?php echo esc_html( Estados::labels()[ $entry['de'] ] ?? $entry['de'] ); ?>
										</td>
										<td>
											<?php echo esc_html( Estados::labels()[ $entry['a'] ] ?? $entry['a'] ); ?>
										</td>
										<td>
											<?php echo esc_html( $entry['fecha'] ); ?>
										</td>
										<td>
											<?php echo esc_html( $entry['nota'] ); ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>
			</div>

			<!-- Logs Section -->
			<div class="conv-detail-card" style="margin-top: 2rem;">
				<h3><?php esc_html_e( 'Registros de actividad', 'convoca-members' ); ?></h3>
				<?php
				global $wpdb;
				$table_logs = $wpdb->prefix . 'convoca_logs';
				$logs       = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * FROM $table_logs WHERE object_id = %d ORDER BY created_at DESC LIMIT 20",
						$post_id
					)
				);

				if ( $logs ) :
					?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th style="width: 170px;"><?php esc_html_e( 'Fecha', 'convoca-members' ); ?></th>
								<th style="width: 100px;"><?php esc_html_e( 'Nivel', 'convoca-members' ); ?></th>
								<th><?php esc_html_e( 'Mensaje', 'convoca-members' ); ?></th>
								<th><?php esc_html_e( 'Contexto', 'convoca-members' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $logs as $log ) : ?>
								<tr>
									<td><?php echo esc_html( $log->created_at ); ?></td>
									<td>
										<span style="padding: 2px 6px; border-radius: 3px; font-weight: bold; background: 
										<?php
											echo $log->level === 'error' ? '#d63638' : ( $log->level === 'warning' ? '#eba313' : '#72aee6' );
										?>
										; color: #fff; font-size: 10px;">
											<?php echo esc_html( strtoupper( $log->level ) ); ?>
										</span>
									</td>
									<td><?php echo esc_html( $log->message ); ?></td>
									<td><code><?php echo esc_html( $log->context ); ?></code></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p>No hay registros de actividad para este miembro.</p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/* ── Settings page (delegates) ─────────────── */

	public function render_settings_page(): void {
		$settings = new Admin_Settings();
		$settings->render();
	}

	public function render_logs_page(): void {
		Admin_Logs::render();
	}

	/* ── Dashboard widget ──────────────────────── */

	public function dashboard_widget(): void {
		wp_add_dashboard_widget(
			'convoca_members_widget',
			__( '🍁 Miembros Convoca', 'convoca-members' ),
			function () {
				global $wpdb;
				$total   = (int) $wpdb->get_var(
					"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'miembro' AND post_status = 'publish'"
				);
				$activos = (int) $wpdb->get_var(
					"SELECT COUNT(*) FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id
					 WHERE pm.meta_key = '_convoca_estado_miembro' AND pm.meta_value = 'activo'
					   AND p.post_type = 'miembro' AND p.post_status = 'publish'"
				);
				echo '<p>' . sprintf(
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline HTML in translatable string, variables escaped separately
					/* translators: 1: active count, 2: total count */ __( '<strong>%1$d</strong> activos de <strong>%2$d</strong> registrados.', 'convoca-members' ),
					esc_html( $activos ),
					esc_html( $total )
				) . '</p>';
				echo '<a href="' . esc_url( admin_url( 'admin.php?page=conv-members' ) ) . '" class="button">' . esc_html__( 'Ver listado completo', 'convoca-members' ) . '</a>';
			}
		);
	}

	/* ── AJAX: Quick state change ──────────────── */

	public function ajax_change_state(): void {
		check_ajax_referer( 'convoca_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'gestionar_miembros' ) ) {
			wp_send_json_error( __( 'Sin permisos.', 'convoca-members' ) );
		}

		$post_data = wp_unslash( $_POST );
		$post_id   = (int) ( $post_data['post_id'] ?? 0 );
		$state     = sanitize_text_field( $post_data['nuevo_estado'] ?? '' );
		$note      = sanitize_text_field( $post_data['nota'] ?? '' );

		$result = Estados::change( $post_id, $state, $note );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success(
			array(
				'estado' => $state,
				'badge'  => Estados::badge_html( $state ),
			)
		);
	}



	public function ajax_log_whatsapp(): void {
		check_ajax_referer( 'convoca_admin_nonce', 'nonce' );
		$post_id = (int) ( wp_unslash( $_POST['post_id'] ) ?? 0 );
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'No tienes permisos.', 'convoca-members' ) );
		}

		$now = current_time( 'mysql' );
		update_post_meta( $post_id, '_convoca_ultimo_contacto_whatsapp', $now );
		update_post_meta( $post_id, '_convoca_ultimo_contacto', $now );

		\Convoca\Core\Logger::info(
			__( 'Se ha iniciado contacto por WhatsApp desde el panel de administración.', 'convoca-members' ),
			'Members/Audit',
			$post_id
		);
		wp_send_json_success();
	}

	/* ── CSV export modal ──────────────────────── */

	private function render_csv_modal(): void {
		$columns = CSV_Exporter::get_available_columns();
		$saved   = CSV_Exporter::get_user_columns();
		?>
		<div id="conv-csv-modal" class="conv-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="conv-csv-modal-title" style="display:none;">
			<div class="conv-modal-content">
				<div class="conv-modal-header">
					<h2 id="conv-csv-modal-title"><?php esc_html_e( '📥 Exportar CSV — Seleccionar columnas', 'convoca-members' ); ?></h2>
					<button type="button" class="conv-modal-close" aria-label="<?php esc_attr_e( 'Cerrar', 'convoca-members' ); ?>">&times;</button>
				</div>
				<div class="conv-modal-body">
					<p class="description"><?php esc_html_e( 'Selecciona las columnas que quieres incluir en la exportación.', 'convoca-members' ); ?></p>
					<div class="conv-columns-grid">
						<?php foreach ( $columns as $key => $label ) : ?>
							<label class="conv-column-checkbox">
								<input type="checkbox" name="csv_col[]" value="<?php echo esc_attr( $key ); ?>"
									<?php checked( in_array( $key, $saved ?? array_keys( $columns ) ) ); ?>>
								<?php echo esc_html( $label ); ?>
							</label>
						<?php endforeach; ?>
					</div>
				</div>
				<div class="conv-modal-footer">
					<button type="button" id="conv-csv-export-selected" class="button button-primary">
						<?php esc_html_e( 'Exportar seleccionadas', 'convoca-members' ); ?>
					</button>
					<button type="button" id="conv-csv-export-all" class="button">
						<?php esc_html_e( 'Exportar todo', 'convoca-members' ); ?>
					</button>
					<button type="button" id="conv-csv-restore-default" class="button">
						<?php esc_html_e( 'Restaurar defecto', 'convoca-members' ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
	}

	public function handle_export_members_pdf(): void {
		if ( ! wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ?? '' ), 'convoca_export_members_pdf' ) ) {
			wp_die( esc_html__( 'Nonce inválido.', 'convoca-members' ) );
		}
		if ( ! current_user_can( 'gestionar_miembros' ) ) {
			wp_die( esc_html__( 'No tienes permisos.', 'convoca-members' ) );
		}

		$args  = array(
			'post_type'      => 'miembro',
			'posts_per_page' => 5000,
			'post_status'    => 'publish',
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		);
		$query = new \WP_Query( $args );

		$headers = array( __( 'Nombre', 'convoca-members' ), __( 'Email', 'convoca-members' ), __( 'DNI', 'convoca-members' ), __( 'Plan', 'convoca-members' ), __( 'Estado', 'convoca-members' ), __( 'Teléfono', 'convoca-members' ) );
		$rows    = array();
		foreach ( $query->posts as $post ) {
			$rows[] = array(
				$post->post_title,
				get_post_meta( $post->ID, '_convoca_email', true ),
				get_post_meta( $post->ID, '_convoca_dni', true ),
				get_post_meta( $post->ID, '_convoca_plan', true ),
				get_post_meta( $post->ID, '_convoca_estado_miembro', true ) ?: '—',
				get_post_meta( $post->ID, '_convoca_telefono', true ),
			);
		}

		\convoca_export_pdf( __( 'Listado de Miembros', 'convoca-members' ), $headers, $rows, 'socios-convoca' );
	}
}
