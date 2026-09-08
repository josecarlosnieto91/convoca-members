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

/**
 * Custom Post Type: miembro + taxonomy tipo_miembro + meta fields.
 *
 * Aligned with libro de socios v2 (full field set).
 *
 * @package Convoca\Members
 */

namespace Convoca\Members;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CPT_Miembro {

	public const SLUG = 'miembro';



	/**
	 * Meta keys used by the plugin.
	 * Aligned with the libro de socios spreadsheet.
	 */
	public const META_KEYS = array(
		// ── Identity ──
		'email',
		'dni',
		'fecha_nacimiento',
		'menor_edad',           // boolean.

		// ── Contact ──
		'telefono',
		'whatsapp',             // si / no.
		'canal_contacto',       // whatsapp, email, telefono.
		'direccion',
		'municipio',

		// ── Membership ──
		'plan',                 // Plan slug (customizable via convoca_members_plans filter).
		'sub_plan',             // e.g. fam-bronze, juv-bronze
		'modalidad',            // Numerario, Familiar, Juvenil.
		'estado_miembro',       // activo, proximo_vencer, baja.
		'forma_pago',           // cuota, voluntariado.
		'importe_cuota',        // e.g. 100.
		'estado_cuota',         // activa, pendiente, vencida.
		'pago_id',              // Gateway pago post ID.
		'metodo_pago',          // tarjeta | bizum.
		'cuota',                // legacy alias.
		'fecha_renovacion',
		'fecha_baja',

		// ── Volunteer ──
		'es_voluntario',        // boolean.
		'tipo_voluntariado',    // string description.
		'intereses',
		'disponibilidad',
		'experiencia',
		'motivacion',

		// ── Minor ──
		'tutor_nombre',
		'tutor_dni',
		'tutor_autorizacion',   // boolean.

		// ── Legal ──
		'rgpd_version',
		'rgpd_timestamp',
		'comunicaciones_ok',    // boolean.

		// ── Admin / Tracking ──
		'observaciones',        // admin notes (textarea).
		'incidencias',          // admin notes (textarea).
		'ultimo_contacto',      // date of last contact.
		'responsable',          // responsible admin user.
		'msg_bienvenida',       // boolean — sent?
		'msg_renovacion',       // boolean — sent?
		'avisos_generales',     // free text.
		'access_code',          // member portal access code.
	);

	/** Status for payments (cuotas). */
	public const ESTADO_CUOTA = array(
		'activa'    => 'Activa',
		'pendiente' => 'Pendiente',
		'vencida'   => 'Vencida',
	);

	/** Plan definitions (price in €, hours in h). */
	public const DEFAULT_PLANS = array(
		'bronze'     => array(
			'label'           => '🥉 Bronce',
			'price'           => 30,
			'hours'           => 15,
			'modalidad'       => 'Numerario',
			'payment_methods' => array( 'bizum', 'tarjeta', 'transferencia' ),
			'advantages'      => array(
				'Participa en todas las actividades de naturaleza de forma gratuita.',
				'Prioridad en las inscripciones para actividades ambientales.',
			),
		),
		'silver'     => array(
			'label'           => '🥈 Plata',
			'price'           => 50,
			'hours'           => 25,
			'modalidad'       => 'Numerario',
			'payment_methods' => array( 'bizum', 'tarjeta', 'transferencia' ),
			'advantages'      => array(
				'20% de descuento en actividades de pago del Centro.',
				'Accede a los espacios sociales del local cuando quieras.',
				'Reserva el local 2 veces al año para eventos privados.',
			),
		),
		'gold'       => array(
			'label'           => '🥇 Oro',
			'price'           => 100,
			'hours'           => 50,
			'modalidad'       => 'Numerario',
			'payment_methods' => array( 'bizum', 'tarjeta', 'transferencia' ),
			'advantages'      => array(
				'Todas las ventajas de Bronce y Plata.',
				'Prioridad en ofertas de trabajo internas.',
				'Grupo de WhatsApp exclusivo con comunidad activa.',
				'Descuentos especiales con diferentes colaboradores.',
			),
		),
		'fam-bronze' => array(
			'label'           => 'Familiar Bronce',
			'price'           => 45,
			'hours'           => 22.5,
			'modalidad'       => 'Familiar',
			'payment_methods' => array( 'bizum', 'tarjeta', 'transferencia' ),
			'advantages'      => array(
				'Participa en todas las actividades de naturaleza de forma gratuita.',
				'Prioridad en las inscripciones para actividades ambientales.',
				'Ventajas aplicables a toda la unidad familiar.',
			),
		),
		'fam-silver' => array(
			'label'           => 'Familiar Plata',
			'price'           => 75,
			'hours'           => 37.5,
			'modalidad'       => 'Familiar',
			'payment_methods' => array( 'bizum', 'tarjeta', 'transferencia' ),
			'advantages'      => array(
				'20% de descuento en actividades de pago del Centro.',
				'Accede a los espacios sociales del local cuando quieras.',
				'Reserva el local 2 veces al año para eventos privados.',
				'Ventajas aplicables a toda la unidad familiar.',
			),
		),
		'fam-gold'   => array(
			'label'           => 'Familiar Oro',
			'price'           => 150,
			'hours'           => 75,
			'modalidad'       => 'Familiar',
			'payment_methods' => array( 'bizum', 'tarjeta', 'transferencia' ),
			'advantages'      => array(
				'Todas las ventajas de Bronce y Plata.',
				'Prioridad en ofertas de trabajo internas.',
				'Grupo de WhatsApp exclusivo con comunidad activa.',
				'Descuentos especiales con diferentes colaboradores.',
				'Ventajas aplicables a toda la unidad familiar.',
			),
		),
		'juv-bronze' => array(
			'label'           => 'Juvenil Bronce',
			'price'           => 15,
			'hours'           => 7.5,
			'modalidad'       => 'Juvenil',
			'payment_methods' => array( 'bizum', 'tarjeta', 'transferencia' ),
			'advantages'      => array(
				'Participa en todas las actividades de naturaleza de forma gratuita.',
				'Prioridad en las inscripciones para actividades ambientales.',
				'Espacio independiente para actuar y tomar decisiones.',
			),
		),
		'juv-silver' => array(
			'label'           => 'Juvenil Plata',
			'price'           => 25,
			'hours'           => 12.5,
			'modalidad'       => 'Juvenil',
			'payment_methods' => array( 'bizum', 'tarjeta', 'transferencia' ),
			'advantages'      => array(
				'20% de descuento en actividades de pago del Centro.',
				'Accede a los espacios sociales del local cuando quieras.',
				'Reserva el local 2 veces al año para eventos privados.',
				'Espacio independiente para actuar y tomar decisiones.',
			),
		),
		'juv-gold'   => array(
			'label'           => 'Juvenil Oro',
			'price'           => 50,
			'hours'           => 25,
			'modalidad'       => 'Juvenil',
			'payment_methods' => array( 'bizum', 'tarjeta', 'transferencia' ),
			'advantages'      => array(
				'Todas las ventajas de Bronce y Plata.',
				'Prioridad en ofertas de trabajo internas.',
				'Grupo de WhatsApp exclusivo con comunidad activa.',
				'Descuentos especiales con diferentes colaboradores.',
				'Espacio independiente para actuar y tomar decisiones.',
			),
		),
		'familiar'   => array(
			'label'           => 'Modalidad Familiar',
			'price'           => 0,
			'hours'           => 0,
			'modalidad'       => 'Virtual',
			'payment_methods' => array( 'bizum', 'tarjeta', 'transferencia' ),
			'advantages'      => array(
				'Descuentos en los diferentes formatos.',
				'Mismas ventajas que el plan correspondiente, adaptadas al grupo familiar.',
			),
		),
		'juvenil'    => array(
			'label'           => 'Modalidad Juvenil',
			'price'           => 0,
			'hours'           => 0,
			'modalidad'       => 'Virtual',
			'payment_methods' => array( 'bizum', 'tarjeta', 'transferencia' ),
			'advantages'      => array(
				'Espacio independiente para actuar y tomar decisiones.',
				'Descuentos del 50% en todos los formatos.',
			),
		),
	);



	/**
	 * Register Post Type and Taxonomy.
	 */
	public static function register(): void {
		// Taxonomy: Tipo de miembro.
		register_taxonomy(
			'tipo_miembro',
			array( self::SLUG ),
			array(
				'labels'       => array(
					'name'          => 'Tipos de miembro',
					'singular_name' => 'Tipo de miembro',
				),
				'hierarchical' => true,
				'show_in_rest' => true,
				'public'       => false,
			)
		);

		// CPT: Miembro.
		register_post_type(
			self::SLUG,
			array(
				'labels'       => array(
					'name'               => __( 'Miembros', 'convoca-members' ),
					'singular_name'      => __( 'Miembro', 'convoca-members' ),
					'add_new'            => __( 'Añadir nuevo', 'convoca-members' ),
					'add_new_item'       => __( 'Añadir nuevo miembro', 'convoca-members' ),
					'edit_item'          => __( 'Editar miembro', 'convoca-members' ),
					'new_item'           => __( 'Nuevo miembro', 'convoca-members' ),
					'view_item'          => __( 'Ver miembro', 'convoca-members' ),
					'search_items'       => __( 'Buscar miembros', 'convoca-members' ),
					'not_found'          => __( 'No se han encontrado miembros', 'convoca-members' ),
					'not_found_in_trash' => __( 'No se han encontrado miembros en la papelera', 'convoca-members' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => false,
				'supports'     => array( 'title', 'custom-fields' ),
				'rewrite'      => false,
				'has_archive'  => false,
				'show_in_rest' => false, // Datos personales: solo vía rutas REST propias autenticadas (Rest_API).
				'map_meta_cap' => true,
			)
		);
	}

	/**
	 * Get default plan definitions.
	 *
	 * @return array Default plan data.
	 */
	public static function get_default_plans(): array {
		return self::DEFAULT_PLANS;
	}



	/**
	 * Get all membership plans (merged with DB settings).
	 */
	public static function get_plans(): array {
		$db_plans = get_option( 'convoca_members_plans', array() );

		// If DB is empty, use defaults (legacy mode).
		$plans = empty( $db_plans ) ? self::get_default_plans() : $db_plans;

		// Normalize: Ensure all plans have critical keys.
		foreach ( $plans as $key => &$plan ) {
			$default = isset( self::DEFAULT_PLANS[ $key ] ) ? self::DEFAULT_PLANS[ $key ] : array();

			if ( ! isset( $plan['payment_methods'] ) ) {
				$plan['payment_methods'] = isset( $default['payment_methods'] ) ? $default['payment_methods'] : array( 'bizum', 'tarjeta', 'transferencia' );
			}
			if ( ! isset( $plan['price'] ) ) {
				$plan['price'] = isset( $default['price'] ) ? $default['price'] : 0;
			}
			if ( ! isset( $plan['hours'] ) ) {
				$plan['hours'] = isset( $default['hours'] ) ? $default['hours'] : 0;
			}
		}

		return apply_filters( 'convoca_members_plans', $plans );
	}

	/**
	 * Get plan data by key.
	 */
	public static function get_plan( string $key ): ?array {
		$plans = self::get_plans();
		return $plans[ $key ] ?? null;
	}

	/**
	 * Build a WhatsApp wa.me link for a member.
	 *
	 * @param int    $post_id  Member post ID.
	 * @param string $message  Optional custom message (URL-encoded automatically).
	 * @return string|null     Full wa.me URL or null if no phone.
	 */
	public static function whatsapp_link( int $post_id, string $message = '' ): ?string {
		$phone  = get_post_meta( $post_id, '_convoca_telefono', true );
		$has_wa = get_post_meta( $post_id, '_convoca_whatsapp', true );

		if ( ! $phone || $has_wa === 'no' ) {
			return null;
		}

		// Normalize phone: remove spaces, dashes, parentheses; add 34 prefix if needed.
		$clean = preg_replace( '/[\s\-\(\)]+/', '', $phone );
		if ( ! str_starts_with( $clean, '+' ) && ! str_starts_with( $clean, '34' ) ) {
			$clean = '34' . $clean;
		}
		$clean = ltrim( $clean, '+' );

		$url = 'https://wa.me/' . $clean;
		if ( $message ) {
			$nombre = get_post_meta( $post_id, '_convoca_nombre', true ) ?: get_the_title( $post_id );
			$msg    = str_replace( '{nombre}', $nombre, $message );
			$url   .= '?text=' . rawurlencode( $msg );
		}

		return $url;
	}

	/**
	 * Get next sequential member number.
	 *
	 * @param int $post_id The ID of the member being approved/created.
	 */
	public static function get_next_member_number( int $post_id = 0 ): int {
		try {
			return self::get_next_member_number_internal( $post_id );
		} catch ( \Throwable $e ) {
			\Convoca\Core\Logger::error( 'Error al generar número de socio (secuencia): ' . $e->getMessage(), 'Members' );

			// Fallback: atomic increment using MySQL's LAST_INSERT_ID.
			global $wpdb;
			$option_name = 'convoca_last_member_number_fallback';
			$wpdb->query( 'START TRANSACTION' );

			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Fallback atomic sequence using wp_options table. $option_name is a hardcoded constant, not user input.
			try {
				// Atomic increment with row-level lock to prevent duplicates.
				$wpdb->query( "SELECT option_value FROM {$wpdb->options} WHERE option_name = '{$option_name}' FOR UPDATE" );
				$wpdb->query(
					"UPDATE {$wpdb->options} SET option_value = CAST(option_value AS UNSIGNED) + 1 
                     WHERE option_name = '{$option_name}'"
				);
				$next = (int) $wpdb->get_var(
					"SELECT CAST(option_value AS UNSIGNED) FROM {$wpdb->options} WHERE option_name = '{$option_name}'"
				);
				if ( ! $next ) {
					$wpdb->query(
						"INSERT INTO {$wpdb->options} (option_name, option_value, autoload) 
                         VALUES ('{$option_name}', '1', 'no')"
					);
					$next = 1;
				}
				// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter
				$wpdb->query( 'COMMIT' );
				return $next;
			} catch ( \Throwable $e2 ) {
				$wpdb->query( 'ROLLBACK' );
				throw new \Exception( 'No se pudo obtener número de socio: ' . esc_html( $e2->getMessage() ) );
			}
		}
	}

	/**
	 * Internal logic for next member number.
	 * Uses a dedicated table to ensure atomic AUTO_INCREMENT without locking wp_options.
	 */
	private static function get_next_member_number_internal( int $post_id = 0 ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'convoca_member_sequence';

		// Verify table exists before using it (handles incomplete upgrades).
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) !== $table ) {
			// Try to create the table safely.
			$charset = $wpdb->get_charset_collate();
			$wpdb->query(
				"CREATE TABLE IF NOT EXISTS $table (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                member_id BIGINT(20) UNSIGNED NOT NULL,
                PRIMARY KEY (id),
                INDEX member_id (member_id)
            ) $charset"
			);
			// If still doesn't exist, fallback will handle it.
			if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) !== $table ) {
				throw new \Exception( 'Sequence table not available.' );
			}
		}

		// Atomic insert to get the next sequential ID.
		$wpdb->insert( $table, array( 'member_id' => $post_id ), array( '%d' ) );
		$next_number = (int) $wpdb->insert_id;

		if ( ! $next_number ) {
			throw new \Exception( 'No se pudo obtener el ID de secuencia.' );
		}

		// Update legacy option for compatibility - use SELECT MAX to ensure consistency under concurrency.
		$max_from_db = (int) $wpdb->get_var( "SELECT MAX(id) FROM $table" );
		if ( $max_from_db > 0 ) {
			update_option( 'convoca_last_member_number', $max_from_db, false );
		}

		return $next_number;
	}

	/**
	 * Approve member: Assign number, set dates, activate.
	 */
	public static function approve_member( int $post_id ): bool {
		global $wpdb;

		$status = get_post_meta( $post_id, '_convoca_estado_miembro', true );
		$num    = get_post_meta( $post_id, '_convoca_numero_socio', true );

		// If already active AND already has a number, nothing to do.
		if ( $status === 'activo' && ! empty( $num ) ) {
			return false;
		}

		$wpdb->query( 'START TRANSACTION' );

		try {
			// Assign number if missing.
			if ( empty( $num ) ) {
				$num = self::get_next_member_number_internal( $post_id );
				update_post_meta( $post_id, '_convoca_numero_socio', $num );
			}
			update_post_meta( $post_id, '_convoca_estado_cuota', 'activa' );

			// Set dates. Keep an existing fecha_alta (historical reactivation
			// preserves seniority); only first activation sets it to today.
			$now = current_time( 'Y-m-d' );
			$fecha_alta_existente = get_post_meta( $post_id, '_convoca_fecha_alta', true );
			if ( empty( $fecha_alta_existente ) ) {
				update_post_meta( $post_id, '_convoca_fecha_alta', $now );
			}

			// Renewal date: only when none exists. Payment_Listener computes the
			// late-renewal date from the previous vencimiento and must not have
			// it overwritten here.
			$renewal_existente = get_post_meta( $post_id, '_convoca_fecha_renovacion', true );
			if ( empty( $renewal_existente ) ) {
				update_post_meta( $post_id, '_convoca_fecha_renovacion', \Convoca\Core\Utils::format_date( '+1 year', 'Y-m-d' ) );
			}
			// Reactivation clears any baja date.
			delete_post_meta( $post_id, '_convoca_fecha_baja' );

			// Activate via state machine (triggers audit log + hooks).
			Estados::change( $post_id, 'activo', "Aprobado manualmente. Socio #$num" );

			$wpdb->query( 'COMMIT' );

			// Clear cache to ensure subsequent get_option calls see the new value.
			wp_cache_delete( 'convoca_last_member_number', 'options' );

			return true;
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			\Convoca\Core\Logger::error( "Error al aprobar miembro #$post_id: " . $e->getMessage(), 'Members' );
			return false;
		}
	}
	/**
	 * Centralized member status validation logic.
	 * Checks if a member should be suspended (grace period) or marked as baja
	 * based on the renewal date.
	 *
	 * Policies (configurable via convoca_members_settings):
	 *  - grace_suspend_days (default 1): days after renewal before the member
	 *    loses benefits and can only renew (state 'suspendido').
	 *  - grace_baja_days (default 30): days after renewal before automatic 'baja'.
	 * Volunteers (forma_pago=voluntariado): their cycle is evaluated by annual
	 * hours (see check_volunteer_cycle); they are not suspended by fee dates.
	 *
	 * @param int $post_id Member ID.
	 */
	public static function check_member_status( int $post_id ): void {
		$status       = get_post_meta( $post_id, '_convoca_estado_miembro', true );
		$renewal_date = get_post_meta( $post_id, '_convoca_fecha_renovacion', true );
		$today        = current_time( 'Y-m-d' );

		if ( $status === 'baja' || empty( $renewal_date ) ) {
			return;
		}

		$forma_pago = get_post_meta( $post_id, '_convoca_forma_pago', true );
		if ( $forma_pago === 'voluntariado' ) {
			// Volunteer annual cycle (hours). Not fee-driven.
			self::check_volunteer_cycle( $post_id, $status, $renewal_date );
			return;
		}

		$settings = get_option( 'convoca_members_settings', array() );
		$baja_days = (int) ( $settings['grace_baja_days'] ?? 30 );
		$baja_days = max( 1, min( 90, $baja_days ) );

		// 1. Final Baja logic (renewal + grace_baja_days) — check FIRST (most severe).
		$baja_date = \Convoca\Core\Utils::format_date( $renewal_date . " +{$baja_days} days", 'Y-m-d' );
		if ( $today > $baja_date ) {
			// Log out all sessions BEFORE the state change so the member
			// cannot keep using the panel afterwards.
			Member_Auth::logout_member_sessions( $post_id );
			Estados::change( $post_id, 'baja', "Baja automática por falta de pago ({$baja_days} días tras vencimiento)." );
			update_post_meta( $post_id, '_convoca_estado_cuota', 'vencida' );
			update_post_meta( $post_id, '_convoca_fecha_baja', $today );
			\Convoca\Core\Utils::do_action( 'convoca_members_miembro_dado_de_baja', 'convoca_miembro_dado_de_baja', $post_id );
			return;
		}

		// 2. Grace suspension (renewal + grace_suspend_days): loses benefits,
		//    keeps access ONLY to renew.
		$suspend_days = (int) ( $settings['grace_suspend_days'] ?? 1 );
		$suspend_days = max( 0, min( 30, $suspend_days ) );
		$suspension_date = \Convoca\Core\Utils::format_date( $renewal_date . " +{$suspend_days} days", 'Y-m-d' );
		if ( $today > $suspension_date && $status !== 'suspendido' ) {
			Estados::change( $post_id, 'suspendido', "Periodo de gracia: vencido el {$renewal_date}. Solo puede renovar." );
			update_post_meta( $post_id, '_convoca_estado_cuota', 'vencida' );
			return;
		}
	}

	/**
	 * Volunteer annual cycle evaluation.
	 *
	 * Volunteers earn the "socio" status by completing the plan's annual hours.
	 * A volunteer in 'activo' whose renewal date has passed is checked here:
	 *  - If enough approved hours were registered since the cycle start → renew
	 *    (fecha_renovacion + 1 year, keep 'activo').
	 *  - Otherwise → back to 'pendiente_documentacion' (volunteer only, no
	 *    member benefits) until hours are completed again.
	 *
	 * @param int    $post_id      Member ID.
	 * @param string $status       Current member state.
	 * @param string $renewal_date Current renewal date (Y-m-d).
	 */
	private static function check_volunteer_cycle( int $post_id, string $status, string $renewal_date ): void {
		$today = current_time( 'Y-m-d' );

		// Only active members can "lose" their status at renewal time.
		if ( $status !== 'activo' ) {
			return;
		}

		// Not due yet.
		if ( $today <= $renewal_date ) {
			return;
		}

		$plan_key  = get_post_meta( $post_id, '_convoca_plan', true );
		$plan_data = self::get_plan( $plan_key );
		if ( ! $plan_data ) {
			return;
		}
		$objetivo = (float) ( $plan_data['hours'] ?? 0 );
		if ( $objetivo <= 0 ) {
			// No hour objective → nothing to evaluate.
			return;
		}

		// Cycle start = explicit period start; fallback to one year before the
		// renewal, then fecha_alta.
		$cycle_start = get_post_meta( $post_id, '_convoca_fecha_inicio_periodo', true );
		if ( empty( $cycle_start ) ) {
			$cycle_start = \Convoca\Core\Utils::format_date( $renewal_date . ' -1 year', 'Y-m-d' );
		}
		$fecha_alta = get_post_meta( $post_id, '_convoca_fecha_alta', true );
		if ( $fecha_alta && $fecha_alta > $cycle_start ) {
			$cycle_start = $fecha_alta;
		}

		$horas = Voluntariado_Manager::get_horas_aprobadas_desde( $post_id, $cycle_start );

		if ( $horas >= $objetivo ) {
			// Met the annual hours → renew the cycle, keep status. The new period
			// starts the day this renewal was due.
			$next = \Convoca\Core\Utils::format_date( $renewal_date . ' +1 year', 'Y-m-d' );
			update_post_meta( $post_id, '_convoca_fecha_renovacion', $next );
			update_post_meta( $post_id, '_convoca_fecha_inicio_periodo', $renewal_date );
			update_post_meta( $post_id, '_convoca_estado_cuota', 'activa' );
			\Convoca\Core\Logger::info(
				"Voluntario #$post_id renueva ciclo anual ({$horas}h >= {$objetivo}h). Nueva renovación: {$next}.",
				'Members/Cron',
				$post_id
			);
			return;
		}

		// Did not meet hours → volunteer only (no member benefits). The next
		// activation attempt counts hours from today onward.
		Estados::change(
			$post_id,
			'pendiente_documentacion',
			"No renovó el ciclo anual: {$horas}h de {$objetivo}h requeridas (venció {$renewal_date}). Vuelve a ser solo voluntario."
		);
		update_post_meta( $post_id, '_convoca_estado_cuota', '' );
		update_post_meta( $post_id, '_convoca_objetivo_horas_completado', '' );
		update_post_meta( $post_id, '_convoca_fecha_objetivo_completado', '' );
		update_post_meta( $post_id, '_convoca_fecha_inicio_periodo', $today );
		delete_post_meta( $post_id, '_convoca_fecha_renovacion' );
		\Convoca\Core\Logger::info(
			"Voluntario #$post_id no cumplió el ciclo anual ({$horas}h < {$objetivo}h). Vuelve a pendiente_documentacion.",
			'Members/Cron',
			$post_id
		);
	}
}
