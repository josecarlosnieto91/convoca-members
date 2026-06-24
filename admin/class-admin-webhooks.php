<?php
/**
 * Admin page for managing webhooks.
 *
 * Provides CRUD interface for webhook endpoints, event subscriptions,
 * delivery logs, and test ping functionality.
 *
 * @package Convoca\Members
 */

namespace Convoca\Members;

use Convoca\Core\Webhook_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Webhooks {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ), 30 );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
	}

	/**
	 * Register submenu under Convoca Members.
	 */
	public function add_menu(): void {
		add_submenu_page(
			'conv-members',
			__( 'Webhooks', 'convoca-members' ),
			'🔗 Webhooks',
			'convoca_manage_webhooks',
			'conv-webhooks',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Handle form submissions and actions.
	 */
	public function handle_actions(): void {
		if ( ! isset( $_POST['convoca_webhook_action'] ) && ! isset( $_GET['convoca_wh_action'] ) ) {
			return;
		}

		if ( ! current_user_can( 'convoca_manage_webhooks' ) ) {
			return;
		}

		// Handle POST actions (create/update).
		if ( isset( $_POST['convoca_webhook_action'] ) ) {
			check_admin_referer( 'convoca_webhook_nonce' );

			$post_data = wp_unslash( $_POST );
			$action    = sanitize_text_field( $post_data['convoca_webhook_action'] );

			if ( $action === 'create' ) {
				$events = isset( $post_data['webhook_events'] ) ? array_map( 'sanitize_text_field', $post_data['webhook_events'] ) : array();

				Webhook_Manager::add_webhook(
					array(
						'url'    => sanitize_url( $post_data['webhook_url'] ?? '' ),
						'secret' => sanitize_text_field( $post_data['webhook_secret'] ?? '' ),
						'events' => $events,
						'label'  => sanitize_text_field( $post_data['webhook_label'] ?? '' ),
					)
				);

				wp_redirect( admin_url( 'admin.php?page=conv-webhooks&msg=created' ) );
				exit;
			}

			if ( $action === 'update' ) {
				$id     = sanitize_text_field( $post_data['webhook_id'] ?? '' );
				$events = isset( $post_data['webhook_events'] ) ? array_map( 'sanitize_text_field', $post_data['webhook_events'] ) : array();

				Webhook_Manager::update_webhook(
					$id,
					array(
						'url'    => sanitize_url( $post_data['webhook_url'] ?? '' ),
						'secret' => sanitize_text_field( $post_data['webhook_secret'] ?? '' ),
						'events' => $events,
						'label'  => sanitize_text_field( $post_data['webhook_label'] ?? '' ),
						'active' => isset( $post_data['webhook_active'] ),
					)
				);

				wp_redirect( admin_url( 'admin.php?page=conv-webhooks&msg=updated' ) );
				exit;
			}
		}

		// Handle GET actions (delete, test, toggle).
		if ( isset( $_GET['convoca_wh_action'] ) ) {
			$get_data = wp_unslash( $_GET );
			$action   = sanitize_text_field( $get_data['convoca_wh_action'] );
			$id       = sanitize_text_field( $get_data['webhook_id'] ?? '' );

			check_admin_referer( 'convoca_wh_action_' . $id );

			if ( $action === 'delete' ) {
				Webhook_Manager::delete_webhook( $id );
				wp_redirect( admin_url( 'admin.php?page=conv-webhooks&msg=deleted' ) );
				exit;
			}

			if ( $action === 'test' ) {
				Webhook_Manager::test_webhook( $id );
				wp_redirect( admin_url( 'admin.php?page=conv-webhooks&msg=tested' ) );
				exit;
			}

			if ( $action === 'toggle' ) {
				$webhook = Webhook_Manager::get_webhook( $id );
				if ( $webhook ) {
					Webhook_Manager::update_webhook( $id, array( 'active' => ! $webhook['active'] ) );
				}
				wp_redirect( admin_url( 'admin.php?page=conv-webhooks&msg=toggled' ) );
				exit;
			}

			if ( $action === 'clear_logs' ) {
				Webhook_Manager::clear_delivery_logs( $id );
				wp_redirect( admin_url( 'admin.php?page=conv-webhooks&view=logs&webhook_id=' . $id . '&msg=logs_cleared' ) );
				exit;
			}
		}
	}

	/**
	 * Render the webhooks admin page.
	 */
	public function render_page(): void {
		$view = sanitize_text_field( $_GET['view'] ?? 'list' );
		$msg  = sanitize_text_field( $_GET['msg'] ?? '' );

		echo '<div class="wrap">';
		echo '<h1>🔗 Webhooks — Convoca</h1>';

		// Flash messages.
		$messages = array(
			'created'      => array( 'success', 'Webhook creado correctamente.' ),
			'updated'      => array( 'success', 'Webhook actualizado.' ),
			'deleted'      => array( 'warning', 'Webhook eliminado.' ),
			'tested'       => array( 'info', 'Ping de prueba enviado.' ),
			'toggled'      => array( 'info', 'Estado del webhook actualizado.' ),
			'logs_cleared' => array( 'info', 'Registro de entregas limpiado.' ),
		);

		if ( ! empty( $msg ) && isset( $messages[ $msg ] ) ) {
			\Convoca\Core\Utils::admin_notice(
				$messages[ $msg ][1],
				$messages[ $msg ][0] === 'success' ? 'success' : ( $messages[ $msg ][0] === 'error' ? 'danger' : 'warning' )
			);
		}

		switch ( $view ) {
			case 'create':
				$this->render_form();
				break;
			case 'edit':
				$id = sanitize_text_field( $_GET['webhook_id'] ?? '' );
				$this->render_form( $id );
				break;
			case 'logs':
				$id = sanitize_text_field( $_GET['webhook_id'] ?? '' );
				$this->render_logs( $id );
				break;
			default:
				$this->render_list();
				break;
		}

		echo '</div>';
	}

	/**
	 * Render the list of webhooks.
	 */
	private function render_list(): void {
		$webhooks = Webhook_Manager::get_webhooks();

		echo '<div style="margin-bottom:15px;">';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=conv-webhooks&view=create' ) ) . '" class="button button-primary">+ Añadir Webhook</a>';
		echo '</div>';

		if ( empty( $webhooks ) ) {
			echo '<div class="convoca-alert convoca-alert--info" style="display:block;margin-bottom:20px;"><p>No hay webhooks configurados. Los webhooks permiten notificar a sistemas externos cuando ocurren eventos en Convoca.</p></div>';
			return;
		}

		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr>';
		echo '<th>Nombre</th><th>URL</th><th>Eventos</th><th>Estado</th><th>Creado</th><th>Acciones</th>';
		echo '</tr></thead><tbody>';

		foreach ( $webhooks as $wh ) {
			$id      = $wh['id'] ?? '';
			$active  = $wh['active'] ?? true;
			$events  = $wh['events'] ?? array();
			$label   = $wh['label'] ?? 'Sin nombre';
			$url     = $wh['url'] ?? '';
			$created = $wh['created_at'] ?? '';

			$event_count  = empty( $events ) ? 'Todos' : count( $events ) . ' evento(s)';
			$status_badge = $active
				? '<span style="color:#155724;background:#d4edda;padding:2px 8px;border-radius:4px;font-size:12px;">Activo</span>'
				: '<span style="color:#856404;background:#fff3cd;padding:2px 8px;border-radius:4px;font-size:12px;">Inactivo</span>';

			echo '<tr>';
			echo '<td><strong>' . esc_html( $label ) . '</strong></td>';
			echo '<td><code style="font-size:12px;">' . esc_html( mb_substr( $url, 0, 50 ) ) . '</code></td>';
			echo '<td>' . esc_html( $event_count ) . '</td>';
			echo '<td>' . $status_badge . '</td>';
			echo '<td>' . esc_html( $created ? \Convoca\Core\Utils::format_date( $created, 'd/m/Y' ) : '—' ) . '</td>';
			echo '<td>';

			// Edit.
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=conv-webhooks&view=edit&webhook_id=' . $id ) ) . '" class="button button-small">Editar</a> ';

			// Test.
			$test_url = wp_nonce_url(
				admin_url( 'admin.php?page=conv-webhooks&conv_wh_action=test&webhook_id=' . $id ),
				'convoca_wh_action_' . $id
			);
			echo '<a href="' . esc_url( $test_url ) . '" class="button button-small">🔔 Test</a> ';

			// Logs.
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=conv-webhooks&view=logs&webhook_id=' . $id ) ) . '" class="button button-small">📋 Logs</a> ';

			// Toggle.
			$toggle_url = wp_nonce_url(
				admin_url( 'admin.php?page=conv-webhooks&conv_wh_action=toggle&webhook_id=' . $id ),
				'convoca_wh_action_' . $id
			);
			echo '<a href="' . esc_url( $toggle_url ) . '" class="button button-small">' . ( $active ? '⏸ Pausar' : '▶ Activar' ) . '</a> ';

			// Delete.
			$delete_url = wp_nonce_url(
				admin_url( 'admin.php?page=conv-webhooks&conv_wh_action=delete&webhook_id=' . $id ),
				'convoca_wh_action_' . $id
			);
			echo '<a href="' . esc_url( $delete_url ) . '" class="button button-small" onclick="return confirm(\'¿Eliminar este webhook?\')">🗑</a>';

			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Render create/edit form for a webhook.
	 */
	private function render_form( ?string $edit_id = null ): void {
		$webhook = null;
		$is_edit = false;

		if ( $edit_id ) {
			$webhook = Webhook_Manager::get_webhook( $edit_id );
			$is_edit = (bool) $webhook;
		}

		$action = $is_edit ? 'update' : 'create';
		$title  = $is_edit ? 'Editar Webhook' : 'Nuevo Webhook';

		echo '<h2>' . esc_html( $title ) . '</h2>';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=conv-webhooks' ) ) . '" class="button" style="margin-bottom:15px;">← Volver a la lista</a>';

		echo '<form method="post" action="">';
		wp_nonce_field( 'convoca_webhook_nonce' );
		echo '<input type="hidden" name="conv_webhook_action" value="' . esc_attr( $action ) . '">';

		if ( $is_edit ) {
			echo '<input type="hidden" name="webhook_id" value="' . esc_attr( $edit_id ) . '">';
		}

		echo '<table class="form-table">';

		// Label.
		echo '<tr><th><label for="webhook_label">Nombre / Etiqueta</label></th>';
		echo '<td><input type="text" id="webhook_label" name="webhook_label" value="' . esc_attr( $webhook['label'] ?? '' ) . '" class="regular-text" placeholder="Ej: Slack Notifications"></td></tr>';

		// URL.
		echo '<tr><th><label for="webhook_url">URL del Webhook</label></th>';
		echo '<td><input type="url" id="webhook_url" name="webhook_url" value="' . esc_attr( $webhook['url'] ?? '' ) . '" class="regular-text" required placeholder="https://ejemplo.com/webhook"></td></tr>';

		// Secret.
		echo '<tr><th><label for="webhook_secret">Secreto HMAC (opcional)</label></th>';
		echo '<td><input type="text" id="webhook_secret" name="webhook_secret" value="' . esc_attr( $webhook['secret'] ?? '' ) . '" class="regular-text" placeholder="Se usa para firmar los payloads">';
		echo '<p class="description">Si se configura, cada entrega incluirá un header <code>X-Convoca-Signature</code> con el HMAC-SHA256.</p></td></tr>';

		// Active (only for edit).
		if ( $is_edit ) {
			echo '<tr><th>Estado</th>';
			echo '<td><label><input type="checkbox" name="webhook_active" value="1" ' . checked( $webhook['active'] ?? true, true, false ) . '> Activo</label></td></tr>';
		}

		// Events.
		echo '<tr><th>Eventos suscritos</th>';
		echo '<td><fieldset>';
		echo '<p class="description" style="margin-bottom:10px;">Selecciona los eventos. Si no seleccionas ninguno, recibirá <strong>todos</strong> los eventos.</p>';

		$subscribed = $webhook['events'] ?? array();

		foreach ( Webhook_Manager::EVENTS as $key => $label ) {
			$checked = empty( $subscribed ) || in_array( $key, $subscribed, true );
			printf(
				'<label style="display:block;margin-bottom:4px;"><input type="checkbox" name="webhook_events[]" value="%s" %s> <code>%s</code> — %s</label>',
				esc_attr( $key ),
				checked( $checked, true, false ),
				esc_html( $key ),
				esc_html( $label )
			);
		}

		echo '</fieldset></td></tr>';

		echo '</table>';

		echo '<p class="submit"><button type="submit" class="button button-primary">' . ( $is_edit ? 'Guardar cambios' : 'Crear Webhook' ) . '</button></p>';
		echo '</form>';
	}

	/**
	 * Render delivery logs for a webhook.
	 */
	private function render_logs( string $webhook_id ): void {
		$webhook = Webhook_Manager::get_webhook( $webhook_id );
		if ( ! $webhook ) {
			echo '<div class="convoca-alert convoca-alert--danger" style="display:block;margin-bottom:20px;"><p>Webhook no encontrado.</p></div>';
			return;
		}

		echo '<h2>📋 Registro de entregas — ' . esc_html( $webhook['label'] ?? 'Sin nombre' ) . '</h2>';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=conv-webhooks' ) ) . '" class="button">← Volver</a> ';

		$clear_url = wp_nonce_url(
			admin_url( 'admin.php?page=conv-webhooks&conv_wh_action=clear_logs&webhook_id=' . $webhook_id ),
			'convoca_wh_action_' . $webhook_id
		);
		echo '<a href="' . esc_url( $clear_url ) . '" class="button" onclick="return confirm(\'¿Limpiar todos los registros?\')">🗑 Limpiar logs</a>';

		$logs = Webhook_Manager::get_delivery_logs( $webhook_id, 30 );

		if ( empty( $logs ) ) {
			echo '<div class="convoca-alert convoca-alert--info" style="display:block;margin-bottom:20px;margin-top:15px;"><p>No hay registros de entregas para este webhook.</p></div>';
			return;
		}

		echo '<table class="wp-list-table widefat fixed striped" style="margin-top:15px;">';
		echo '<thead><tr><th width="160">Fecha</th><th width="180">Evento</th><th width="80">Estado</th><th>Detalle</th></tr></thead><tbody>';

		foreach ( $logs as $log ) {
			$status = ( $log['success'] ?? false )
				? '<span style="color:#155724;background:#d4edda;padding:2px 8px;border-radius:4px;font-size:11px;">✓ OK</span>'
				: '<span style="color:#721c24;background:#f8d7da;padding:2px 8px;border-radius:4px;font-size:11px;">✗ Error</span>';

			echo '<tr>';
			echo '<td>' . esc_html( $log['time'] ?? '' ) . '</td>';
			echo '<td><code style="font-size:11px;">' . esc_html( $log['event'] ?? '' ) . '</code></td>';
			echo '<td>' . $status . '</td>';
			echo '<td style="font-size:12px;">' . esc_html( mb_substr( $log['message'] ?? '', 0, 100 ) ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}
}
