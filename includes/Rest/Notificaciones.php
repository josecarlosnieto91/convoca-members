<?php
/**
 * Convoca Members — avisos del socio por REST.
 *
 * Primer trozo sacado de `Rest_API` (1.766 líneas, 30 métodos). Los tres manejadores de
 * notificaciones solo dependen de la sesión del socio y de `Convoca\Core\Notifications`, así
 * que no necesitan nada del resto de la clase. Las rutas y el contrato REST no cambian.
 *
 * @package    Convoca\Members
 * @subpackage Rest
 *
 * @copyright  Copyright (C) 2026 Jose Carlos Nieto Ramos
 * @license    GPL-2.0-or-later
 */

namespace Convoca\Members\Rest;

use Convoca\Core\Notifications;
use Convoca\Members\Member_Auth;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lectura y marcado de avisos del socio autenticado.
 */
class Notificaciones {

	/**
	 * Devuelve los avisos del socio con su contador.
	 *
	 * @param \WP_REST_Request $request Petición.
	 */
	public function get( \WP_REST_Request $request ): \WP_REST_Response {
		$member_id = Member_Auth::get_current_member_id();
		$limit     = (int) $request->get_param( 'limit' ) ?: 10;

		$notifications = Notifications::get_member( $member_id, $limit );
		$unread_count  = Notifications::count_member_unread( $member_id );
		$all           = get_post_meta( $member_id, '_convoca_notifications', true ) ?: array();

		return new \WP_REST_Response(
			array(
				'items'  => $notifications,
				'unread' => $unread_count,
				'total'  => count( $all ),
			)
		);
	}

	/**
	 * Marca un aviso como leído.
	 *
	 * @param \WP_REST_Request $request Petición.
	 */
	public function marcar( \WP_REST_Request $request ): \WP_REST_Response {
		$member_id       = Member_Auth::get_current_member_id();
		$notification_id = sanitize_text_field( $request->get_param( 'id' ) ?? '' );

		if ( empty( $notification_id ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Falta ID de notificación.', 'convoca-members' ),
				),
				400
			);
		}

		Notifications::mark_member_read( $member_id, $notification_id );

		return new \WP_REST_Response( array( 'success' => true ) );
	}

	/**
	 * Marca todos los avisos como leídos.
	 *
	 * @param \WP_REST_Request $request Petición.
	 */
	public function marcar_todas( \WP_REST_Request $request ): \WP_REST_Response {
		$member_id = Member_Auth::get_current_member_id();
		Notifications::mark_member_all_read( $member_id );
		return new \WP_REST_Response( array( 'success' => true ) );
	}
}
