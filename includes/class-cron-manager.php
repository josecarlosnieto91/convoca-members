<?php
/**
 * Cron jobs for notifications, reminders, and automated renewals.
 *
 * Handles:
 * - Escalated payment reminders (3d, 7d, 14d)
 * - Escalated renewal notices (30d, 15d, 7d)
 * - Volunteer hour reminders (quarterly)
 * - Automatic recurrent renewals
 * - Member expiration processing
 * - Admin weekly digest
 *
 * @package Convoca\Members
 */

namespace Convoca\Members;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cron_Manager {

	/** Meta key to track last reminder sent. */
	private const META_LAST_REMINDER = '_bdv_last_reminder';

	/** Meta key to track last renewal notice sent. */
	private const META_LAST_RENEWAL_NOTICE = '_bdv_last_renewal_notice';

	/** Meta key to track volunteer reminder quarter. */
	private const META_VOLUNTARIADO_REMINDER_Q = '_bdv_voluntariado_reminder_q';

	public function __construct() {
		// Daily cron
		add_action( 'bdv_daily_event', array( $this, 'run_daily' ) );

		// Weekly cron (admin digest)
		add_action( 'bdv_weekly_event', array( $this, 'run_weekly' ) );
	}

	/**
	 * Daily check for payments pending, renewals, or expirations.
	 */
	public function run_daily(): void {
		// 0. Acquire lock to prevent concurrent runs
		if ( ! \Convoca\Core\Utils::acquire_lock( 'bdv_members_daily_lock', 7200 ) ) {
			return;
		}

		try {
			$this->process_pending_payments();
			$this->process_renewals();
			$this->process_automatic_renewals();
			$this->process_expirations();
			$this->process_volunteer_reminders();
		} finally {
			\Convoca\Core\Utils::release_lock( 'bdv_members_daily_lock' );
		}
	}

	/**
	 * Weekly tasks: admin digest.
	 */
	public function run_weekly(): void {
		// 0. Acquire lock to prevent concurrent runs
		if ( ! \Convoca\Core\Utils::acquire_lock( 'bdv_members_weekly_lock', 14400 ) ) {
			return;
		}

		try {
			$this->send_admin_digest();
		} finally {
			\Convoca\Core\Utils::release_lock( 'bdv_members_weekly_lock' );
		}
	}

	/* ── Escalated Payment Reminders (3d, 7d, 14d) ─────────── */

	/**
	 * Remind users with 'pendiente_pago' status at escalating intervals.
	 * - Day 3: First reminder (recordatorio_pago)
	 * - Day 7: Second reminder (pago_pendiente_2)
	 * - Day 14: Final warning (pago_pendiente_ultimo)
	 */
	private function process_pending_payments(): void {
		// Limit to 50 members per cron run to avoid timeout
		$args = array(
			'post_type'      => 'miembro',
			'meta_query'     => array(
				array(
					'key'   => '_bdv_estado_miembro',
					'value' => 'pendiente_pago',
				),
			),
			'posts_per_page' => 50,
			'fields'         => 'ids',
			'orderby'        => 'date',
			'order'          => 'ASC',
		);

		$ids = get_posts( $args );

		if ( empty( $ids ) ) {
			return;
		}

		$email_manager = new Email_Manager();

		foreach ( $ids as $post_id ) {
			// Use the timestamp when the member moved to pending payment state
			$pending_date = get_post_meta( $post_id, '_bdv_fecha_pendiente_pago', true );

			// Fallback to post_modified if meta is missing
			if ( empty( $pending_date ) ) {
				$post = get_post( $post_id );
				if ( $post ) {
					$pending_date = $post->post_modified;
					update_post_meta( $post_id, '_bdv_fecha_pendiente_pago', $pending_date );
					\Convoca\Core\Logger::warning( "Member $post_id had no pending payment date, fallback set to post_modified", 'Members/Cron' );
				} else {
					continue;
				}
			}

			$base_ts = strtotime( $pending_date );
			if ( ! $base_ts ) {
				continue;
			}

			$today     = time();
			$days_diff = (int) floor( ( $today - $base_ts ) / DAY_IN_SECONDS );

			// Per-member lock to prevent duplicate sends if cron overlaps
			$lock_key = 'bdv_reminder_' . $post_id;
			if ( ! \Convoca\Core\Utils::acquire_lock( $lock_key, 600 ) ) {
				continue;
			}

			try {
				// Re-check state to avoid race condition: member may have paid while this cron runs
				$current_state = get_post_meta( $post_id, '_bdv_estado_miembro', true );
				if ( $current_state !== 'pendiente_pago' ) {
					continue;
				}

				// Determine which reminder to send (inside lock to prevent race with parallel cron)
				$last_sent    = get_post_meta( $post_id, self::META_LAST_REMINDER, true );
				$template     = null;
				$reminder_key = null;

				if ( $days_diff >= 14 && $last_sent !== 'ultimo' ) {
					$template     = 'pago_pendiente_ultimo';
					$reminder_key = 'ultimo';
				} elseif ( $days_diff >= 7 && ! in_array( $last_sent, array( 'segundo', 'ultimo' ), true ) ) {
					$template     = 'pago_pendiente_2';
					$reminder_key = 'segundo';
				} elseif ( $days_diff >= 3 && empty( $last_sent ) ) {
					$template     = 'recordatorio_pago';
					$reminder_key = 'primero';
				}

				if ( $template && $reminder_key ) {
					$pago_id = (int) get_post_meta( $post_id, '_bdv_pago_id', true );
					$link    = '';
					if ( $pago_id ) {
						$link = $this->get_payment_link( $pago_id );
					}

					$method = 'send_' . ( $template === 'recordatorio_pago' ? 'recordatorio_pago' : $template );
					if ( method_exists( $email_manager, $method ) ) {
						$email_manager->$method( $post_id, array( '{link_pago}' => $link ) );
					} else {
						$this->send_template_email( $email_manager, $template, $post_id, array( '{link_pago}' => $link ) );
					}

					update_post_meta( $post_id, self::META_LAST_REMINDER, $reminder_key );

					\Convoca\Core\Logger::info(
						"Recordatorio de pago escalonado ($reminder_key) enviado al miembro #$post_id (día $days_diff).",
						'Members/Cron',
						$post_id
					);

					do_action( 'convoca_members_payment_reminder_sent', $post_id, $reminder_key, $days_diff );
				}
			} finally {
				\Convoca\Core\Utils::release_lock( $lock_key );
			}
		}
	}

	/* ── Escalated Renewal Reminders (30d, 15d, 7d) ────────── */

	/**
	 * Handle renewal reminders at 30, 15, and 7 days before expiration.
	 */
	private function process_renewals(): void {
		global $wpdb;
		$email_manager = new Email_Manager();

		// Check each interval
		$intervals = array(
			30 => 'renovacion',
			15 => 'renovacion_15d',
			7  => 'renovacion_7d',
		);

		foreach ( $intervals as $days => $template ) {
			$target_date = ( new \DateTime( 'now', new \DateTimeZone( wp_timezone_string() ) ) )
				->modify( "+{$days} days" )
				->format( 'Y-m-d' );

			// Use $wpdb for robust date comparison (ignoring time if present)
			$query = "
                SELECT p.ID 
                FROM {$wpdb->posts} p
                JOIN {$wpdb->postmeta} pm_ren ON p.ID = pm_ren.post_id AND pm_ren.meta_key = '_bdv_fecha_renovacion' AND pm_ren.meta_value IS NOT NULL AND pm_ren.meta_value != ''
                JOIN {$wpdb->postmeta} pm_est ON p.ID = pm_est.post_id AND pm_est.meta_key = '_bdv_estado_miembro' AND pm_est.meta_value = 'activo'
                WHERE p.post_type = 'miembro' 
                  AND p.post_status = 'publish'
                  AND CAST(pm_ren.meta_value AS DATE) = %s
                LIMIT 50
            ";

			$ids = $wpdb->get_col( $wpdb->prepare( $query, $target_date ) );

			if ( empty( $ids ) ) {
				continue;
			}

			foreach ( $ids as $post_id ) {
				$post_id = (int) $post_id;
				// Check if this notice was already sent
				$last_notice = get_post_meta( $post_id, self::META_LAST_RENEWAL_NOTICE, true );
				if ( $last_notice === (string) $days ) {
					continue;
				}

				$link = $this->get_renewal_link( $post_id );

				// Use the specific send method
				$method = 'send_' . $template;
				if ( method_exists( $email_manager, $method ) ) {
					$email_manager->$method( $post_id, array( '{link_pago}' => $link ) );
				} else {
					$this->send_template_email( $email_manager, $template, $post_id, array( '{link_pago}' => $link ) );
				}

				update_post_meta( $post_id, self::META_LAST_RENEWAL_NOTICE, (string) $days );

				\Convoca\Core\Logger::info(
					"Aviso de renovación ({$days}d) enviado al miembro #$post_id.",
					'Members/Cron',
					$post_id
				);

				// Fire webhook
				do_action( 'convoca_members_renewal_reminder_sent', $post_id, $days );
			}
		}
	}

	/* ── Volunteer Hour Reminders (quarterly) ──────────────── */

	/**
	 * Quarterly reminder for volunteers to log their hours.
	 * Runs daily but checks if we're in a new quarter.
	 */
	private function process_volunteer_reminders(): void {
		$current_quarter = 'Q' . ceil( wp_date( 'n' ) / 3 ) . '-' . wp_date( 'Y' );

		$args = array(
			'post_type'      => 'miembro',
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'   => '_bdv_estado_miembro',
					'value' => 'activo',
				),
				array(
					'key'   => '_bdv_forma_pago',
					'value' => 'voluntariado',
				),
				array(
					'key'     => self::META_VOLUNTARIADO_REMINDER_Q,
					'value'   => $current_quarter,
					'compare' => '!=',
				),
			),
			'posts_per_page' => 100, // Process more per run
			'fields'         => 'ids',
			'orderby'        => 'date',
			'order'          => 'ASC',
		);

		$ids = get_posts( $args );

		if ( empty( $ids ) ) {
			return;
		}

		$email_manager = new Email_Manager();

		foreach ( $ids as $post_id ) {
			// Calculate volunteer hours progress
			$horas_data = $this->get_volunteer_hours_summary( $post_id );
			$extra_vars = array(
				'{horas_actuales}'          => $horas_data['total'],
				'{horas_objetivo}'          => $horas_data['objetivo'],
				'{porcentaje_cumplimiento}' => $horas_data['porcentaje'],
			);

			$this->send_template_email( $email_manager, 'voluntariado_recordatorio', $post_id, $extra_vars );

			update_post_meta( $post_id, self::META_VOLUNTARIADO_REMINDER_Q, $current_quarter );

			\Convoca\Core\Logger::info(
				"Recordatorio de voluntariado ($current_quarter) enviado al miembro #$post_id. Progreso: {$horas_data['porcentaje']}%",
				'Members/Cron',
				$post_id
			);
		}
	}

	/* ── Automatic Renewals ────────────────────────────────── */

	/**
	 * Process automatic renewals for members with recurrent payment enabled.
	 */
	private function process_automatic_renewals(): void {
		global $wpdb;

		$today = wp_date( 'Y-m-d' );

		$query = "
            SELECT p.ID 
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm_rec ON p.ID = pm_rec.post_id AND pm_rec.meta_key = '_bdv_pago_recurrente' AND pm_rec.meta_value = '1'
            JOIN {$wpdb->postmeta} pm_est ON p.ID = pm_est.post_id AND pm_est.meta_key = '_bdv_estado_miembro' AND pm_est.meta_value = 'activo'
            JOIN {$wpdb->postmeta} pm_ren ON p.ID = pm_ren.post_id AND pm_ren.meta_key = '_bdv_fecha_renovacion'
            WHERE p.post_type = 'miembro' 
              AND p.post_status = 'publish'
              AND CAST(pm_ren.meta_value AS DATE) <= %s
        ";

		$member_ids = $wpdb->get_col( $wpdb->prepare( $query, $today ) );

		if ( empty( $member_ids ) ) {
			return;
		}

		$email_manager = new Email_Manager();

		foreach ( $member_ids as $member_id ) {
			$member_id = (int) $member_id;
			$lock_key  = 'bdv_autorenewal_' . $member_id;

			// Prevent concurrent/double processing via atomic lock
			if ( ! \Convoca\Core\Utils::acquire_lock( $lock_key, 300 ) ) {
				continue;
			}

			$plan_key  = get_post_meta( $member_id, '_bdv_plan', true ) ?: get_post_meta( $member_id, '_bdv_sub_plan', true );
			$plan_data = CPT_Miembro::get_plan( $plan_key );

			if ( ! $plan_data || (float) $plan_data['price'] <= 0 ) {
				\Convoca\Core\Logger::warning(
					"Intento de renovación automática fallido: Plan no encontrado o importe 0 para el miembro #$member_id.",
					'Members/Cron',
					$member_id
				);
				continue;
			}

			// Verify recurring token (Hardening Task)
			$token              = \Convoca\Gateway\Payment_Handler::get_member_token( $member_id );
			$needs_tokenization = empty( $token );

			if ( $needs_tokenization ) {
				\Convoca\Core\Logger::warning(
					"Renovación automática: Miembro #$member_id no tiene token guardado. Se solicitará tokenización en el nuevo pago.",
					'Members/Cron',
					$member_id
				);
			}

			// Create payment via Gateway
			if ( ! \Convoca\Core\Features::is_gateway_active() ) {
				\Convoca\Core\Logger::error(
					"Gateway no disponible para renovación automática del miembro #$member_id.",
					'Members/Cron',
					$member_id
				);
				continue;
			}

			$payment_result = \Convoca\Gateway\Payment_Handler::create_payment(
				array(
					'origin'       => 'members',
					'origin_id'    => $member_id,
					'amount_cents' => (int) round( $plan_data['price'] * 100 ),
					'product_desc' => mb_substr( 'RENOVACIÓN BIODEVAS - ' . strtoupper( $plan_data['label'] ), 0, 125 ),
					'method'       => 'tarjeta',
					'tokenize'     => $needs_tokenization,
				)
			);

			if ( is_wp_error( $payment_result ) ) {
				\Convoca\Core\Utils::release_lock( $lock_key );
				\Convoca\Core\Logger::error(
					"Error al generar renovación automática para el miembro #$member_id: " . $payment_result->get_error_message(),
					'Members/Cron',
					$member_id
				);

				// Notificar al socio que la renovación falló
				if ( method_exists( $email_manager, 'send_renovacion_fallida' ) ) {
					$email_manager->send_renovacion_fallida( $member_id );
				}

				// Cambiar estado a pendiente_pago via state machine (triggers audit log)
				Estados::change( $member_id, 'pendiente_pago', 'Renovación automática fallida - requerido pago manual' );

				continue;
			}

			$pago_id = $payment_result['pago_id'];

			update_post_meta( $member_id, '_bdv_last_auto_renewal', $today );
			update_post_meta( $member_id, '_bdv_pago_id', $pago_id );

			\Convoca\Core\Logger::info(
				"Procesando renovación automática para el miembro #$member_id (Pago: #$pago_id).",
				'Members/Cron',
				$member_id
			);

			$email_manager->send_renovacion_automatica( $member_id );

			// Fire webhook
			do_action( 'convoca_members_auto_renewal_created', $member_id, $pago_id );
		}
	}

	/* ── Expirations ───────────────────────────────────────── */

	/**
	 * Check for expired members daily and update their status.
	 */
	private function process_expirations(): void {
		global $wpdb;
		$today = wp_date( 'Y-m-d' );

		$query = "
            SELECT p.ID 
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm_est ON p.ID = pm_est.post_id AND pm_est.meta_key = '_bdv_estado_miembro' AND pm_est.meta_value = 'activo'
            JOIN {$wpdb->postmeta} pm_ren ON p.ID = pm_ren.post_id AND pm_ren.meta_key = '_bdv_fecha_renovacion' AND pm_ren.meta_value IS NOT NULL AND pm_ren.meta_value != ''
            WHERE p.post_type = 'miembro' 
              AND p.post_status = 'publish'
              AND CAST(pm_ren.meta_value AS DATE) < %s
        ";

		$member_ids = $wpdb->get_col( $wpdb->prepare( $query, $today ) );

		foreach ( $member_ids as $member_id ) {
			$member_id = (int) $member_id;
			CPT_Miembro::check_member_status( $member_id );

			\Convoca\Core\Logger::info(
				"Expiración procesada para el miembro #$member_id.",
				'Members/Cron',
				$member_id
			);

			// Fire webhook
			do_action( 'convoca_members_membership_expired', $member_id );
		}
	}

	/* ── Admin Weekly Digest ───────────────────────────────── */

	/**
	 * Send a weekly summary email to administrators.
	 */
	private function send_admin_digest(): void {
		$settings    = get_option( 'bdv_members_settings', array() );
		$admin_email = $settings['admin_email'] ?? get_option( 'admin_email' );

		// Fallback if admin_email is empty
		if ( empty( $admin_email ) ) {
			$admin_email = get_site_option( 'admin_email' ) ?: get_option( 'admin_email' );
		}

		if ( empty( $admin_email ) ) {
			\Convoca\Core\Logger::error( 'No se pudo enviar digest: admin_email no configurado.', 'Members/Cron' );
			return;
		}

		$sender_name = $settings['sender_name'] ?? get_bloginfo( 'name' );

		// Gather stats
		$stats = $this->gather_weekly_stats();

		// Build email body
		$body = $this->build_digest_body( $stats );

		$subject = sprintf(
			'[Biodevas] Resumen semanal — %s',
			wp_date( 'd/m/Y' )
		);

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $sender_name . ' <' . $admin_email . '>',
		);

		// Also send to extra admin emails if configured
		$extra_emails = $settings['digest_extra_emails'] ?? '';
		$recipients   = array( $admin_email );
		if ( ! empty( $extra_emails ) ) {
			$extras     = array_map( 'trim', explode( ',', $extra_emails ) );
			$recipients = array_merge( $recipients, array_filter( $extras, 'is_email' ) );
		}

		foreach ( $recipients as $recipient ) {
			$sent = wp_mail( $recipient, $subject, $body, $headers );
			if ( ! $sent ) {
				\Convoca\Core\Logger::error(
					"Weekly digest failed to send to: $recipient",
					'Members/Cron'
				);
			}
		}

		\Convoca\Core\Logger::info(
			'Resumen semanal enviado a: ' . implode( ', ', $recipients ),
			'Members/Cron'
		);
	}

	/**
	 * Gather weekly statistics for the digest.
	 */
	private function gather_weekly_stats(): array {
		global $wpdb;

		$tz       = new \DateTimeZone( wp_timezone_string() );
		$week_ago = ( new \DateTime( 'now', $tz ) )->modify( '-7 days' )->format( 'Y-m-d' );
		$today    = ( new \DateTime( 'now', $tz ) )->format( 'Y-m-d' );

		// New members this week
		$new_members = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'miembro' 
             AND post_status = 'publish' 
             AND post_date >= %s",
				$week_ago . ' 00:00:00'
			)
		);

		// Status counts
		$status_counts = array();
		foreach ( Estados::STATES as $state ) {
			$status_counts[ $state ] = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->posts} p
                 JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                 WHERE p.post_type = 'miembro'
                 AND p.post_status = 'publish'
                 AND pm.meta_key = '_bdv_estado_miembro'
                 AND pm.meta_value = %s",
					$state
				)
			);
		}

		// Expiring in the next 7 days
		$expiring_soon = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} p
             JOIN {$wpdb->postmeta} pm_est ON p.ID = pm_est.post_id AND pm_est.meta_key = '_bdv_estado_miembro' AND pm_est.meta_value = 'activo'
            JOIN {$wpdb->postmeta} pm_ren ON p.ID = pm_ren.post_id AND pm_ren.meta_key = '_bdv_fecha_renovacion' AND pm_ren.meta_value IS NOT NULL AND pm_ren.meta_value != ''
            WHERE p.post_type = 'miembro'
             AND p.post_status = 'publish'
             AND CAST(pm_ren.meta_value AS DATE) BETWEEN %s AND %s",
				$today,
				( new \DateTime( 'now', $tz ) )->modify( '+7 days' )->format( 'Y-m-d' )
			)
		);

		// Pending payments
		$pending_payments = $status_counts['pendiente_pago'] ?? 0;

		// Volunteer hours logged this week
		$hours_logged = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(pm.meta_value), 0) FROM {$wpdb->posts} p
             JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_bdv_horas'
             WHERE p.post_type = 'registro_hora'
             AND p.post_status = 'publish'
             AND p.post_date >= %s",
				$week_ago . ' 00:00:00'
			)
		);

		// Recent errors from logs
		$recent_errors = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}biodevas_logs 
             WHERE level = 'error' 
             AND created_at >= %s",
				$week_ago . ' 00:00:00'
			)
		);

		return array(
			'new_members'      => $new_members,
			'status_counts'    => $status_counts,
			'expiring_soon'    => $expiring_soon,
			'pending_payments' => $pending_payments,
			'hours_logged'     => $hours_logged,
			'recent_errors'    => $recent_errors,
			'total_active'     => $status_counts['activo'] ?? 0,
		);
	}

	/**
	 * Build the HTML body for the admin weekly digest.
	 */
	private function build_digest_body( array $stats ): string {
		$admin_url  = admin_url( 'admin.php?page=bdv-members' );
		$tz         = new \DateTimeZone( wp_timezone_string() );
		$week_ago_f = ( new \DateTime( 'now', $tz ) )->modify( '-7 days' )->format( 'd/m/Y' );
		$today_f    = ( new \DateTime( 'now', $tz ) )->format( 'd/m/Y' );
		$date_range = $week_ago_f . ' — ' . $today_f;

		$html = '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">';

		// Header
		$html .= '<div style="background:#2c5e3e;color:#fff;padding:20px;border-radius:8px 8px 0 0;text-align:center;">';
		$html .= '<h1 style="margin:0;font-size:22px;">📊 Resumen Semanal Biodevas</h1>';
		$html .= '<p style="margin:5px 0 0;opacity:0.8;font-size:14px;">' . esc_html( $date_range ) . '</p>';
		$html .= '</div>';

		// Body
		$html .= '<div style="background:#f8f9fa;padding:20px;border:1px solid #e9ecef;">';

		// Key metrics row
		$html .= '<table style="width:100%;border-collapse:collapse;margin-bottom:20px;">';
		$html .= '<tr>';
		$html .= $this->digest_metric_cell( 'Socios Activos', $stats['total_active'], '#2c5e3e' );
		$html .= $this->digest_metric_cell( 'Nuevos esta semana', $stats['new_members'], '#0d6efd' );
		$html .= $this->digest_metric_cell( 'Horas Registradas', $stats['hours_logged'] . 'h', '#6f42c1' );
		$html .= '</tr>';
		$html .= '</table>';

		// Alerts
		if ( $stats['expiring_soon'] > 0 || $stats['pending_payments'] > 0 || $stats['recent_errors'] > 0 ) {
			$html .= '<div style="background:#fff3cd;border:1px solid #ffc107;padding:12px;border-radius:6px;margin-bottom:15px;">';
			$html .= '<strong>⚠️ Requiere atención:</strong><ul style="margin:8px 0 0;padding-left:20px;">';

			if ( $stats['expiring_soon'] > 0 ) {
				$html .= '<li>' . $stats['expiring_soon'] . ' membresía(s) vencen en los próximos 7 días</li>';
			}
			if ( $stats['pending_payments'] > 0 ) {
				$html .= '<li>' . $stats['pending_payments'] . ' pago(s) pendiente(s)</li>';
			}
			if ( $stats['recent_errors'] > 0 ) {
				$html .= '<li>' . $stats['recent_errors'] . ' error(es) en el sistema esta semana</li>';
			}

			$html .= '</ul></div>';
		}

		// Status breakdown
		$html .= '<div style="background:#fff;padding:15px;border-radius:6px;border:1px solid #dee2e6;">';
		$html .= '<h3 style="margin:0 0 10px;font-size:16px;">Desglose por estado</h3>';
		$html .= '<table style="width:100%;font-size:14px;">';

		foreach ( $stats['status_counts'] as $state => $count ) {
			$label = Estados::LABELS[ $state ] ?? $state;
			$html .= '<tr><td style="padding:4px 0;">' . esc_html( $label ) . '</td>';
			$html .= '<td style="padding:4px 0;text-align:right;font-weight:bold;">' . $count . '</td></tr>';
		}

		$html .= '</table></div>';

		$html .= '</div>';

		// Footer
		$html .= '<div style="background:#e9ecef;padding:15px;border-radius:0 0 8px 8px;text-align:center;font-size:13px;color:#6c757d;">';
		$html .= '<a href="' . esc_url( $admin_url ) . '" style="color:#2c5e3e;text-decoration:none;font-weight:bold;">Ver panel de administración →</a>';
		$html .= '<br><br>Este email se genera automáticamente cada lunes.';
		$html .= '</div>';

		$html .= '</div>';

		return $html;
	}

	/**
	 * Build a single metric cell for the digest email.
	 */
	private function digest_metric_cell( string $label, $value, string $color ): string {
		return '<td style="text-align:center;padding:10px;background:#fff;border-radius:6px;border:1px solid #dee2e6;margin:5px;">'
			. '<div style="font-size:28px;font-weight:bold;color:' . $color . ';">' . esc_html( $value ) . '</div>'
			. '<div style="font-size:12px;color:#6c757d;margin-top:4px;">' . esc_html( $label ) . '</div>'
			. '</td>';
	}

	/* ── Helpers ────────────────────────────────────────────── */

	/**
	 * Get signed payment link for a payment ID.
	 */
	private function get_payment_link( int $pago_id ): string {
		if ( \Convoca\Core\Features::is_gateway_active() ) {
			// Get or create persistent token for long-lived links
			$token = get_post_meta( $pago_id, '_bdg_link_key', true );
			if ( empty( $token ) ) {
				$token = wp_generate_password( 32, false, false );
				update_post_meta( $pago_id, '_bdg_link_key', $token );
			}
			return \Convoca\Gateway\Payment_Handler::get_payment_link( $pago_id, $token );
		}
		return home_url( '/pagar/' );
	}

	/**
	 * Get renewal link for a member (try existing payment, fallback to renew page).
	 */
	private function get_renewal_link( int $post_id ): string {
		// If recurrent, create payment automatically
		$es_recurrente = get_post_meta( $post_id, '_bdv_pago_recurrente', true );
		$forma_pago    = get_post_meta( $post_id, '_bdv_forma_pago', true );

		if ( $es_recurrente && in_array( $forma_pago, array( 'tarjeta', 'bizum', 'cuota' ), true ) ) {
			$plan_key  = get_post_meta( $post_id, '_bdv_plan', true );
			$plan_data = CPT_Miembro::get_plan( $plan_key );
			$importe   = (float) ( get_post_meta( $post_id, '_bdv_importe_cuota', true ) ?: ( $plan_data ? $plan_data['price'] : 0 ) );

			if ( $importe > 0 && \Convoca\Core\Features::is_gateway_active() && function_exists( 'Convoca\Gateway\bdv_gateway_create_payment' ) ) {
				$payment = \Convoca\Gateway\bdv_gateway_create_payment(
					array(
						'amount_cents' => (int) round( $importe * 100 ),
						'method'       => ( $forma_pago === 'cuota' ) ? 'tarjeta' : $forma_pago,
						'origin'       => 'members',
						'origin_id'    => $post_id,
						'product_desc' => mb_substr( 'RENOVACION BIODEVAS ' . ( $plan_data['label'] ?? 'SOCIO' ), 0, 125 ),
					)
				);

				if ( ! is_wp_error( $payment ) ) {
					update_post_meta( $post_id, '_bdv_pago_id', $payment['pago_id'] );
					\Convoca\Core\Logger::info(
						"Renovación automática generada (Pago #{$payment['pago_id']}) para el miembro #$post_id.",
						'Members/Renewal',
						$post_id
					);
					return $this->get_payment_link( (int) $payment['pago_id'] );
				}
			}
		}

		// Fallback: try existing payment link
		$pago_id = (int) get_post_meta( $post_id, '_bdv_pago_id', true );
		if ( $pago_id ) {
			return $this->get_payment_link( $pago_id );
		}

		return home_url( '/renovar/' );
	}

	/**
	 * Get volunteer hours summary for a member.
	 */
	private function get_volunteer_hours_summary( int $post_id ): array {
		global $wpdb;

		$year = wp_date( 'Y' );

		// Get total approved hours for current year
		$total = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(pm_h.meta_value), 0)
             FROM {$wpdb->posts} p
             JOIN {$wpdb->postmeta} pm_m ON p.ID = pm_m.post_id AND pm_m.meta_key = '_bdv_miembro_id' AND pm_m.meta_value = %d
             JOIN {$wpdb->postmeta} pm_h ON p.ID = pm_h.post_id AND pm_h.meta_key = '_bdv_horas'
             JOIN {$wpdb->postmeta} pm_e ON p.ID = pm_e.post_id AND pm_e.meta_key = '_bdv_estado' AND pm_e.meta_value = 'aprobada'
             WHERE p.post_type = 'registro_hora'
             AND p.post_status = 'publish'
             AND YEAR(p.post_date) = %d",
				$post_id,
				$year
			)
		);

		// Get the objective from plan
		$plan_key  = get_post_meta( $post_id, '_bdv_plan', true );
		$plan_data = CPT_Miembro::get_plan( $plan_key );
		$objetivo  = (float) ( $plan_data ? $plan_data['hours'] : 40 );

		$porcentaje = $objetivo > 0 ? min( 100, round( ( $total / $objetivo ) * 100 ) ) : 0;

		return array(
			'total'      => $total,
			'objetivo'   => $objetivo,
			'porcentaje' => $porcentaje,
		);
	}

	/**
	 * Generic template email sender (uses reflection to access private send method).
	 * This serves templates that don't have a dedicated public method.
	 */
	private function send_template_email( Email_Manager $email_manager, string $template, int $post_id, array $extra_vars = array() ): void {
		// Try dedicated method first
		$method = 'send_' . $template;
		if ( method_exists( $email_manager, $method ) ) {
			$email_manager->$method( $post_id, $extra_vars );
			return;
		}

		// For templates without a dedicated method, manually replicate the send logic
		$templates = Email_Manager::get_templates();
		$tpl       = $templates[ $template ] ?? null;

		if ( ! $tpl ) {
			return;
		}

		$email = get_post_meta( $post_id, '_bdv_email', true );
		if ( empty( $email ) ) {
			return;
		}

		// Build basic vars
		$nombre = get_the_title( $post_id );
		$vars   = array_merge(
			array(
				'{nombre}' => $nombre,
				'{email}'  => $email,
			),
			$extra_vars
		);

		$subject = str_replace( array_keys( $vars ), array_values( $vars ), $tpl['subject'] );
		$body    = str_replace( array_keys( $vars ), array_values( $vars ), $tpl['body'] );

		$settings    = get_option( 'bdv_members_settings', array() );
		$sender_name = $settings['sender_name'] ?? get_bloginfo( 'name' );
		$admin_email = $settings['admin_email'] ?? get_option( 'admin_email' );

		$headers = array(
			'Content-Type: text/plain; charset=UTF-8',
			'From: ' . $sender_name . ' <' . $admin_email . '>',
		);

		wp_mail( $email, $subject, $body, $headers );
	}
}
