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

namespace Convoca\Members;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Volunteer management (approval/revocation) under the Members admin menu.
 *
 * Owned by Convoca Members since 2026-09-10 (moved from convoca-shifts).
 * Convoca Shifts only consumes the volunteer state (role voluntario_aprobado /
 * cap gestionar_mis_turnos) for shifts and hours; it listens to the
 * convoca_voluntario_revocado hook to release future shifts.
 */
class Admin_Voluntariado {

	const SLUG = 'conv-members-voluntariado-gestion';

	const META_APROBADO = '_convoca_voluntario_aprobado'; // 0 = pending, 1 = approved, -1 = revoked.

	/**
	 * Register the admin submenu under Members.
	 */
	public static function register_menu(): void {
		add_submenu_page(
			'conv-members',
			__( 'Gestionar Voluntarios', 'convoca-members' ),
			__( 'Gestionar Voluntarios', 'convoca-members' ),
			'manage_options',
			self::SLUG,
			array( self::class, 'render_page' )
		);
	}

	/**
	 * Render the volunteer management page.
	 */
	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos suficientes.', 'convoca-members' ) );
		}

		self::handle_actions();

		$pending_users = self::get_pending_users();
		$active_users  = self::get_active_users();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Gestión de Voluntariado', 'convoca-members' ) . '</h1>';
		echo '<p>' . esc_html__( 'Solicitudes de personas que quieren colaborar como voluntarias en el centro. Al aprobar se activa su ficha de miembro y puede gestionar turnos.', 'convoca-members' ) . '</p>';

		// ── PENDING ──.
		echo '<h2 class="title">' . esc_html__( 'Solicitudes Pendientes', 'convoca-members' ) . '</h2>';
		if ( ! empty( $pending_users ) ) {
			echo '<table class="wp-list-table widefat fixed striped table-view-list users">';
			echo '<thead><tr><th>' . esc_html__( 'Nombre', 'convoca-members' ) . '</th><th>' . esc_html__( 'Email', 'convoca-members' ) . '</th><th>' . esc_html__( 'Teléfono', 'convoca-members' ) . '</th><th>' . esc_html__( 'Motivación', 'convoca-members' ) . '</th><th>' . esc_html__( 'Acciones', 'convoca-members' ) . '</th></tr></thead><tbody>';
			foreach ( $pending_users as $user ) {
				$telefono   = get_user_meta( $user->ID, '_convoca_telefono', true );
				$motivacion = get_user_meta( $user->ID, '_convoca_voluntario_motivacion', true );
				$approve    = wp_nonce_url(
					admin_url( 'admin.php?page=' . self::SLUG . '&action=approve&user=' . $user->ID ),
					'convoca_volunteer_approve_' . $user->ID
				);

				echo '<tr>';
				echo '<td><strong>' . esc_html( ! empty( $user->first_name ) ? $user->first_name : $user->display_name ) . '</strong></td>';
				echo '<td><a href="mailto:' . esc_attr( $user->user_email ) . '">' . esc_html( $user->user_email ) . '</a></td>';
				echo '<td>' . esc_html( $telefono ?: '—' ) . '</td>';
				echo '<td>' . nl2br( esc_html( $motivacion ?: '' ) ) . '</td>';
				echo '<td><a href="' . esc_url( $approve ) . '" class="button button-primary">' . esc_html__( 'Aprobar Voluntario', 'convoca-members' ) . '</a></td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		} else {
			echo '<p>' . esc_html__( 'No hay solicitudes pendientes.', 'convoca-members' ) . '</p>';
		}

		echo '<hr style="margin: 40px 0;">';

		// ── ACTIVE ──.
		echo '<h2 class="title">' . esc_html__( 'Voluntarios Activos', 'convoca-members' ) . '</h2>';
		if ( ! empty( $active_users ) ) {
			echo '<table class="wp-list-table widefat fixed striped table-view-list users">';
			echo '<thead><tr><th>' . esc_html__( 'Nombre', 'convoca-members' ) . '</th><th>' . esc_html__( 'Email', 'convoca-members' ) . '</th><th>' . esc_html__( 'Teléfono', 'convoca-members' ) . '</th><th>' . esc_html__( 'Ficha', 'convoca-members' ) . '</th><th>' . esc_html__( 'Acciones', 'convoca-members' ) . '</th></tr></thead><tbody>';
			foreach ( $active_users as $user ) {
				$telefono = get_user_meta( $user->ID, '_convoca_telefono', true );
				$member   = (int) get_user_meta( $user->ID, '_convoca_member_id', true );
				$edit     = $member ? admin_url( 'post.php?post=' . $member . '&action=edit' ) : '';
				$revoke   = wp_nonce_url(
					admin_url( 'admin.php?page=' . self::SLUG . '&action=revoke&user=' . $user->ID ),
					'convoca_volunteer_revoke_' . $user->ID
				);

				echo '<tr>';
				echo '<td><strong>' . esc_html( ! empty( $user->first_name ) ? $user->first_name : $user->display_name ) . '</strong></td>';
				echo '<td><a href="mailto:' . esc_attr( $user->user_email ) . '">' . esc_html( $user->user_email ) . '</a></td>';
				echo '<td>' . esc_html( $telefono ?: '—' ) . '</td>';
				echo '<td>' . ( $edit ? '<a href="' . esc_url( $edit ) . '">' . esc_html__( 'Ver ficha', 'convoca-members' ) . '</a>' : '—' ) . '</td>';
				echo '<td><a href="' . esc_url( $revoke ) . '" class="button" onclick="return confirm(\'' . esc_js( esc_html__( '¿Revocar los permisos de voluntario? Sus turnos futuros quedarán liberados.', 'convoca-members' ) ) . '\');">' . esc_html__( 'Revocar Permisos', 'convoca-members' ) . '</a></td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		} else {
			echo '<p>' . esc_html__( 'No hay voluntarios activos registrados.', 'convoca-members' ) . '</p>';
		}

		echo '</div>';
	}

	/**
	 * Handle approve/revoke GET actions.
	 */
	private static function handle_actions(): void {
		$action = sanitize_key( wp_unslash( $_GET['action'] ?? '' ) );
		$user   = (int) ( wp_unslash( $_GET['user'] ?? 0 ) );

		if ( ! in_array( $action, array( 'approve', 'revoke' ), true ) || ! $user ) {
			return;
		}

		if ( 'approve' === $action ) {
			check_admin_referer( 'convoca_volunteer_approve_' . $user );
			self::approve_volunteer( $user );
		} else {
			check_admin_referer( 'convoca_volunteer_revoke_' . $user );
			self::revoke_volunteer( $user );
		}
	}

	/**
	 * Approve a volunteer: grant role, mark approved, notify (email + PDF attachment).
	 *
	 * @param int $user_id WP user ID.
	 */
	public static function approve_volunteer( int $user_id ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$user->set_role( 'voluntario_aprobado' );
		update_user_meta( $user_id, self::META_APROBADO, '1' );

		// Mirror legacy members activation: activate member, PDF, role guarantee.
		do_action( 'convoca_voluntario_aprobado', $user_id );

		$attachments = apply_filters( 'convoca_voluntario_aprobado_attachments', array(), $user_id );
		wp_mail(
			$user->user_email,
			__( '¡Solicitud de voluntariado aprobada!', 'convoca-members' ),
			__( 'Hola, ya puedes acceder y gestionar turnos en el centro social. Adjunto a este correo encontrarás tu Acuerdo de Incorporación si procede.', 'convoca-members' ),
			'',
			$attachments
		);

		\Convoca\Core\Logger::info(
			sprintf( 'Voluntario aprobado: %s (#%d)', $user->display_name, $user_id ),
			'Members/Voluntariado'
		);
	}

	/**
	 * Revoke a volunteer: remove role, mark revoked, notify Shifts to release future shifts.
	 *
	 * @param int $user_id WP user ID.
	 */
	public static function revoke_volunteer( int $user_id ): void {
		if ( $user_id === get_current_user_id() ) {
			\Convoca\Core\Utils::admin_notice( esc_html__( 'No puedes revocarte tus propios permisos.', 'convoca-members' ), 'warning' );
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$user->set_role( 'subscriber' );
		update_user_meta( $user_id, self::META_APROBADO, '-1' );

		// Let Shifts (and any other consumer) release future shifts for this volunteer.
		do_action( 'convoca_voluntario_revocado', $user_id );

		\Convoca\Core\Logger::info(
			sprintf( 'Permisos de voluntario revocados: %s (#%d)', $user->display_name, $user_id ),
			'Members/Voluntariado'
		);
	}

	/**
	 * Pending volunteers: users with approval meta = 0.
	 *
	 * @return \WP_User[]
	 */
	private static function get_pending_users(): array {
		$q = new \WP_User_Query(
			array(
				'meta_key'   => self::META_APROBADO,
				'meta_value' => '0',
			)
		);
		return $q->get_results();
	}

	/**
	 * Active volunteers: users with the volunteer role.
	 *
	 * @return \WP_User[]
	 */
	private static function get_active_users(): array {
		$q = new \WP_User_Query(
			array(
				'role' => 'voluntario_aprobado',
			)
		);
		return $q->get_results();
	}
}
