<?php
/**
 * State machine for member status with audit logging.
 *
 * @package Convoca\Members
 */

namespace Convoca\Members;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Estados {


	/** Valid states. */
	public const STATES = array(
		'pendiente_documentacion',
		'pendiente_pago',
		'activo',
		'suspendido',
		'baja_solicitada',
		'baja',
	);

	/** Allowed transitions: from => [ to, to, … ] */
	public const TRANSITIONS = array(
		'pendiente_documentacion' => array( 'pendiente_pago', 'activo', 'baja' ),
		'pendiente_pago'          => array( 'activo', 'suspendido', 'baja' ),
		'activo'                  => array( 'suspendido', 'baja_solicitada', 'baja' ),
		'suspendido'              => array( 'activo', 'baja_solicitada', 'baja' ),
		'baja_solicitada'         => array( 'baja', 'activo' ),  // Admin can reactivate or confirm baja.
		'baja'                    => array( 'pendiente_documentacion' ),  // Re-entry.
	);

	/** Human-readable labels. */
	public const LABELS = array(
		'pendiente_documentacion' => 'Pendiente documentación',
		'pendiente_pago'          => 'Pendiente pago',
		'activo'                  => 'Activo',
		'suspendido'              => 'Suspendido',
		'baja_solicitada'         => 'Baja solicitada',
		'baja'                    => 'Baja',
	);

	/** Badge CSS classes (matching theme). */
	public const BADGE_CLASSES = array(
		'pendiente_documentacion' => 'convoca-badge convoca-badge--pending',
		'pendiente_pago'          => 'convoca-badge convoca-badge--pending',
		'activo'                  => 'convoca-badge convoca-badge--confirmed',
		'suspendido'              => 'convoca-badge convoca-badge--waitlist',
		'baja_solicitada'         => 'convoca-badge convoca-badge--warning',
		'baja'                    => 'convoca-badge convoca-badge--pending',
	);

	public function __construct() {
		// Listen for state changes to trigger emails.
		add_action( 'convoca_members_estado_changed', array( $this, 'on_state_change' ), 10, 3 );
	}

	/**
	 * Change a member's state with validation.
	 *
	 * @param int    $post_id  Miembro post ID.
	 * @param string $new      Target state.
	 * @param string $note     Optional note for the audit log.
	 * @return bool|\WP_Error
	 */
	public static function change( int $post_id, string $new, string $note = '' ): bool|\WP_Error {
		// Prevent concurrent state changes with transient lock.
		$lock_key = "convoca_state_change_{$post_id}";
		if ( get_transient( $lock_key ) ) {
			\Convoca\Core\Logger::warning(
				"Intento de cambio de estado concurrente bloqueado para miembro #$post_id",
				'Members/Estados',
				$post_id
			);
			return new \WP_Error( 'concurrent_change', __( 'Ya hay un cambio de estado en proceso para este miembro.', 'convoca-members' ) );
		}

		// Set lock for 10 seconds.
		set_transient( $lock_key, 1, 10 );

		// Register shutdown function to ensure lock is cleared even on fatal errors.
		register_shutdown_function(
			function () use ( $lock_key ) {
				delete_transient( $lock_key );
			}
		);

		if ( ! in_array( $new, self::STATES, true ) ) {
			delete_transient( $lock_key );
			return new \WP_Error( 'invalid_state', __( 'Estado no válido.', 'convoca-members' ) );
		}

		$old = get_post_meta( $post_id, '_convoca_estado_miembro', true );

		if ( $old === $new && ! empty( $old ) ) {
			delete_transient( $lock_key );
			return true; // No-op if already in that state and state was set.
		}

		// Validate transition if not first state.
		if ( ! empty( $old ) ) {
			$allowed = self::TRANSITIONS[ $old ] ?? array();
			if ( ! in_array( $new, $allowed, true ) ) {
				delete_transient( $lock_key );
				return new \WP_Error(
					'invalid_transition',
					sprintf(
						/* translators: %1$s: current state, %2$s: target state */
						__( 'Transición no permitida: %1$s → %2$s', 'convoca-members' ),
						self::LABELS[ $old ] ?? $old,
						self::LABELS[ $new ] ?? $new
					)
				);
			}
		}

		$old_log = ! empty( $old ) ? $old : 'NUEVO';

		// Save new state.
		update_post_meta( $post_id, '_convoca_estado_miembro', $new );

		// Record timestamp for pending payment state (for cron reminders).
		if ( $new === 'pendiente_pago' ) {
			update_post_meta( $post_id, '_convoca_fecha_pendiente_pago', current_time( 'mysql' ) );
		}

		// Audit log.
		self::log( $post_id, $old, $new, $note );

		// Release lock.
		delete_transient( $lock_key );

		/**
		 * Fires after a member's state has changed.
		 *
		 * @param int    $post_id  Post ID.
		 * @param string $new      New state.
		 * @param string $old      Previous state.
		 */
		\Convoca\Core\Utils::do_action( 'convoca_members_estado_changed', 'convoca_estado_changed', $post_id, $new, $old );

		return true;
	}

	private static function log( int $post_id, string $old, string $new, string $note ): void {
		$history   = get_post_meta( $post_id, '_convoca_historial', true );
		$history   = is_array( $history ) ? $history : array();
		$history[] = array(
			'de'      => $old,
			'a'       => $new,
			'fecha'   => current_time( 'mysql' ),
			'usuario' => get_current_user_id(),
			'nota'    => $note,
		);
		update_post_meta( $post_id, '_convoca_historial', $history );

		// Also write to the members audit log table.
		\Convoca\Core\Logger::info(
			sprintf(
				'Estado: %s → %s. %s',
				self::LABELS[ $old ] ?? $old ?: 'NUEVO',
				self::LABELS[ $new ] ?? $new,
				$note ? "Nota: $note" : ''
			),
			'Members/Estados',
			$post_id
		);
	}

	/**
	 * Trigger emails on state change.
	 */
	public function on_state_change( int $post_id, string $new, string $old ): void {
		switch ( $new ) {
			case 'activo':
				\Convoca\Core\Utils::do_action( 'convoca_members_email_bienvenida', 'convoca_email_bienvenida', $post_id );
				break;
			case 'pendiente_pago':
				\Convoca\Core\Utils::do_action( 'convoca_members_email_recordatorio_pago', 'convoca_email_recordatorio_pago', $post_id );
				break;
		}
	}

	/**
	 * Get the audit history for a member.
	 *
	 * @return array<array{de:string, a:string, fecha:string, usuario:int, nota:string}>
	 */
	public static function get_history( int $post_id ): array {
		$history = get_post_meta( $post_id, '_convoca_historial', true );
		return is_array( $history ) ? $history : array();
	}

	/**
	 * Get badge HTML for a state.
	 */
	public static function badge_html( string $state ): string {
		$class = self::BADGE_CLASSES[ $state ] ?? 'convoca-badge';
		$label = self::LABELS[ $state ] ?? $state;
		return '<span class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</span>';
	}
}
