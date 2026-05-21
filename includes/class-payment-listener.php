<?php
/**
 * Listens for biodevas_payment_completed and failed to manage member states.
 *
 * @package Convoca\Members
 */

namespace Convoca\Members;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Payment_Listener {


	private const TRANSIENT_KEY = 'bdv_member_payment_processed_';

	public function __construct() {
		// Only listen to the unified hook to avoid duplication
		add_action( 'convoca_gateway_payment_completed', array( $this, 'on_payment_completed' ), 10, 5 );
		add_action( 'convoca_gateway_payment_failed', array( $this, 'on_payment_failed' ), 10, 2 );
	}

	/**
	 * Handle successful payment from the gateway.
	 * Uses transient to prevent duplicate processing.
	 *
	 * @param int    $pago_id    Payment post ID.
	 * @param string $origin     Origin plugin key.
	 * @param int    $origin_id  Post ID of the miembro.
	 * @param array  $meta       Payment metadata.
	 */
	public function on_payment_completed( $pago_id, $origin, $origin_id, $meta ): void {
		// Deduplication: use atomic lock
		$transient_key = self::TRANSIENT_KEY . $pago_id;

		// Use atomic lock instead of transient
		if ( ! \Convoca\Core\Utils::acquire_lock( $transient_key, 60 ) ) {
			\Convoca\Core\Logger::info( "Member payment $pago_id already processed, skipping (deduplicated)", 'Members/Payment' );
			return;
		}

		// Only handle members payments.
		if ( $origin !== 'members' ) {
			return;
		}

		$miembro = get_post( $origin_id );
		if ( ! $miembro || $miembro->post_type !== 'miembro' ) {
			\Convoca\Core\Logger::warning( "Pago $pago_id completado pero miembro #$origin_id no existe o fue eliminado.", 'Members/Payment', $origin_id );
			return;
		}

		// Fetch specific meta fields individually to avoid array-vs-string comparison issues
		$current_member_state = get_post_meta( $origin_id, '_bdv_estado_miembro', true );
		$old_renewal_date     = get_post_meta( $origin_id, '_bdv_fecha_renovacion', true );
		$last_pago_id         = (int) get_post_meta( $origin_id, '_bdv_pago_id', true );

		if ( $last_pago_id === (int) $pago_id ) {
			\Convoca\Core\Logger::info( "Member payment $pago_id already applied to member #$origin_id, skipping.", 'Members/Payment' );
			return;
		}

		// Update fee information.
		update_post_meta( $origin_id, '_bdv_estado_cuota', 'activa' );
		update_post_meta( $origin_id, '_bdv_metodo_pago', $meta['method'] ?? '' );
		update_post_meta( $origin_id, '_bdv_pago_id', $pago_id );
		update_post_meta( $origin_id, '_bdv_forma_pago', 'cuota' );

		// Validate plan existence before renewal
		$plan_key  = get_post_meta( $origin_id, '_bdv_plan', true );
		$plan_data = CPT_Miembro::get_plan( $plan_key );
		if ( ! $plan_data ) {
			\Convoca\Core\Logger::error( "No se pudo procesar renovación: el plan '$plan_key' no existe para el miembro #$origin_id.", 'Members/Payment', $origin_id );
			return;
		}

		// Update renewal date.
		// If it's a renewal (already active), we add 1 year to the OLD date if it was in the future,
		// or 1 year to TODAY if it was in the past.
		$today = current_time( 'Y-m-d' );
		if ( $current_member_state === 'activo' && ! empty( $old_renewal_date ) ) {
			$base_date   = ( $old_renewal_date > $today ) ? $old_renewal_date : $today;
			$new_renewal = \Convoca\Core\Utils::format_date( $base_date . ' +1 year', 'Y-m-d' );
		} else {
			// First activation: +1 year from today
			$new_renewal = \Convoca\Core\Utils::format_date( $today . ' +1 year', 'Y-m-d' );
		}

		update_post_meta( $origin_id, '_bdv_fecha_renovacion', $new_renewal );
		delete_post_meta( $origin_id, '_bdv_last_renewal_notice' );
		delete_post_meta( $origin_id, '_bdv_msg_renovacion_proxima' );

		$email_manager = new Email_Manager();

		// Activation / Renewal logic.
		if ( $current_member_state !== 'activo' ) {
			// First activation via approve_member (assigns number, sets dates, etc.)
			CPT_Miembro::approve_member( $origin_id );
			// Generate WP user + send credentials
			Process_Member::handle_approved( $origin_id );
			\Convoca\Core\Logger::info( "Membresía activada tras pago completado (ID: $pago_id) para el miembro #$origin_id.", 'Members/Payment', $origin_id );
		} else {
			// It's a renewal.
			\Convoca\Core\Logger::info( "Cuota renovada por un año (Nueva fecha: $new_renewal) para el miembro #$origin_id.", 'Members/Payment', $origin_id );

			// Send renewal completed email.
			$email_manager->send_renovacion_completada( $origin_id );
		}

		// Fire custom action for other integrations.
		\Convoca\Core\Utils::do_action( 'convoca_members_cuota_pagada', 'biodevas_miembro_cuota_pagada', $origin_id, $pago_id );
	}

	/**
	 * Handle failed payment from the gateway.
	 *
	 * @param int    $pago_id       Payment post ID.
	 * @param string $response_code Redsys error code.
	 */
	public function on_payment_failed( int $pago_id, string $response_code ): void {
		// Deduplication for failed payments too
		$transient_key = self::TRANSIENT_KEY . 'failed_' . $pago_id;

		if ( ! \Convoca\Core\Utils::acquire_lock( $transient_key, 60 ) ) {
			return;
		}

		if ( ! \Convoca\Core\Features::is_gateway_active() ) {
			return;
		}

		$meta = \Convoca\Gateway\CPT_Pago::get_meta( $pago_id );

		if ( $meta['origin'] !== 'members' ) {
			return;
		}

		$miembro_id = (int) $meta['origin_id'];
		if ( ! $miembro_id ) {
			return;
		}

		\Convoca\Core\Logger::warning( "Pago fallido (ID: $pago_id) para el miembro #$miembro_id. Código Redsys: $response_code", 'Members/Payment', $miembro_id );

		$current_status = get_post_meta( $miembro_id, '_bdv_estado_cuota', true );

		// Update status: 'vencida' (or 'pendiente' if it's the first attempt).
		$new_status = ( ! $current_status || $current_status === 'pendiente' ) ? 'pendiente' : 'vencida';
		update_post_meta( $miembro_id, '_bdv_estado_cuota', $new_status );

		// Send email with new link
		if ( ! \Convoca\Core\Features::is_gateway_active() ) {
			return;
		}
		$link = \Convoca\Gateway\Payment_Handler::get_payment_link( $pago_id );

		$email_manager = new Email_Manager();
		$email_manager->send_recordatorio_pago( $miembro_id, array( '{link_pago}' => $link ) );
	}
}
