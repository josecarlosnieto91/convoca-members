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
 * Voluntariado Manager - Handles auto-conversion of volunteers to active members.
 *
 * @package Convoca\Members
 */

namespace Convoca\Members;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Voluntariado_Manager {

	/**
	 * Initialize hooks.
	 */
	public static function init(): void {
		add_action( 'convoca_members_hora_aprobada', array( __CLASS__, 'on_hora_aprobada' ), 10, 2 );
	}

	/**
	 * Horas de voluntariado que pide el ciclo de esta ficha, según **su plan**.
	 *
	 * El umbral sale del plan y no de una cifra global (decisión de JC, 15/10/2026): cada plan trae las
	 * suyas —bronce 15, plata 25, oro 50, voluntariado 20—, así que un socio de oro y uno de bronce no
	 * renuevan con las mismas horas. Si el plan no está en la configuración se usa el objetivo por defecto,
	 * que es filtrable; sin plan no hay ciclo que cumplir.
	 *
	 * @param int $miembro_id ID de la ficha.
	 * @return float Horas objetivo, o 0 si no hay plan.
	 */
	public static function horas_objetivo( int $miembro_id ): float {
		$plan = (string) get_post_meta( $miembro_id, '_convoca_plan', true );
		if ( '' === $plan ) {
			return 0.0;
		}

		$plan_data = CPT_Miembro::get_plan( $plan );
		if ( $plan_data && isset( $plan_data['hours'] ) ) {
			return (float) $plan_data['hours'];
		}

		return (float) apply_filters( 'convoca_members_horas_objetivo_defecto', 20.0, $miembro_id, $plan );
	}

	/**
	 * Handle hora approved - check if volunteer should be converted to active.
	 */
	public static function on_hora_aprobada( int $hora_id, int $miembro_id ): void {
		// Hours count per volunteer cycle (period), not all-time history, so a
		// volunteer who returned to 'pendiente_documentacion' must earn fresh
		// hours again instead of reactivating on historical totals.
		$inicio_periodo = get_post_meta( $miembro_id, '_convoca_fecha_inicio_periodo', true );
		if ( empty( $inicio_periodo ) ) {
			$inicio_periodo = get_post_meta( $miembro_id, '_convoca_fecha_alta', true );
		}
		$total_horas = $inicio_periodo
			? self::get_horas_aprobadas_desde( $miembro_id, $inicio_periodo )
			: self::get_horas_aprobadas( $miembro_id );

		$horas_objetivo = self::horas_objetivo( $miembro_id );

		if ( $horas_objetivo <= 0 ) {
			return;
		}

		$ya_completo = get_post_meta( $miembro_id, '_convoca_objetivo_horas_completado', true );

		if ( $ya_completo === '1' ) {
			return;
		}

		if ( $total_horas >= $horas_objetivo ) {
			// Reclamación atómica: solo un hilo puede fijar la bandera de 0/'' → '1'.
			global $wpdb;
			$claimed = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->postmeta} SET meta_value = '1'
                     WHERE post_id = %d AND meta_key = '_convoca_objetivo_horas_completado'
                       AND (meta_value IS NULL OR meta_value = '' OR meta_value != '1')",
					$miembro_id
				)
			);
			// Si no existía la meta, insertarla atómicamente con INSERT ... ON DUPLICATE.
			if ( ! $claimed ) {
				$exists = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT meta_id FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_convoca_objetivo_horas_completado'",
						$miembro_id
					)
				);
				if ( ! $exists ) {
					$wpdb->query(
						$wpdb->prepare(
							"INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
                             VALUES (%d, '_convoca_objetivo_horas_completado', '1')",
							$miembro_id
						)
					);
					$claimed = 1;
				}
			}
			if ( ! $claimed ) {
				// Otro hilo ya completó la conversión.
				return;
			}
			// La conversión necesita saber de qué plan viene la ficha (el umbral ya se calculó arriba con
			// `horas_objetivo()`, que lo lee del plan: aquí solo se recupera para la llamada).
			$plan = (string) get_post_meta( $miembro_id, '_convoca_plan', true );
			self::convertir_en_activo( $miembro_id, $total_horas, $plan );
		}
	}

	/**
	 * Get total approved hours for a member.
	 */
	public static function get_horas_aprobadas( int $miembro_id ): float {
		global $wpdb;

		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(CAST(meta_value AS DECIMAL(10,2))) 
             FROM {$wpdb->postmeta} 
             WHERE meta_key = '_convoca_horas' 
             AND post_id IN (
                 SELECT ID FROM {$wpdb->posts} 
                 WHERE post_type = 'registro_hora' 
                 AND post_status = 'publish'
             )
             AND post_id IN (
                 SELECT post_id FROM {$wpdb->postmeta} 
                 WHERE meta_key = '_convoca_member_id' AND meta_value = %d
             )
             AND post_id IN (
                 SELECT post_id FROM {$wpdb->postmeta} 
                 WHERE meta_key = '_convoca_estado' AND meta_value = 'aprobada'
             )",
				$miembro_id
			)
		);

		return (float) ( $result ?: 0 );
	}

	/**
	 * Sum approved hours registered on or after a given date (inclusive).
	 * Used for annual volunteer cycles: hours count per period, not all-time.
	 *
	 * @param int    $miembro_id Member ID.
	 * @param string $desde      Y-m-d start date (inclusive).
	 * @return float
	 */
	public static function get_horas_aprobadas_desde( int $miembro_id, string $desde ): float {
		global $wpdb;

		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(CAST(pm_h.meta_value AS DECIMAL(10,2))) 
             FROM {$wpdb->posts} p
             JOIN {$wpdb->postmeta} pm_h ON p.ID = pm_h.post_id AND pm_h.meta_key = '_convoca_horas'
             JOIN {$wpdb->postmeta} pm_m ON p.ID = pm_m.post_id AND pm_m.meta_key = '_convoca_member_id' AND pm_m.meta_value = %d
             JOIN {$wpdb->postmeta} pm_e ON p.ID = pm_e.post_id AND pm_e.meta_key = '_convoca_estado' AND pm_e.meta_value = 'aprobada'
             LEFT JOIN {$wpdb->postmeta} pm_f ON p.ID = pm_f.post_id AND pm_f.meta_key = '_convoca_fecha'
             WHERE p.post_type = 'registro_hora'
               AND p.post_status = 'publish'
               AND (COALESCE(NULLIF(pm_f.meta_value, ''), DATE(p.post_date)) >= %s)",
				$miembro_id,
				$desde
			)
		);

		return (float) ( $result ?: 0 );
	}

	/**
	 * Convert volunteer to active member.
	 */
	private static function convertir_en_activo( int $miembro_id, float $total_horas, string $plan ): void {
		update_post_meta( $miembro_id, '_convoca_objetivo_horas_completado', '1' );
		update_post_meta( $miembro_id, '_convoca_fecha_objetivo_completado', current_time( 'mysql' ) );
		update_post_meta( $miembro_id, '_convoca_horas_totales_voluntariado', $total_horas );

		// Cumplir las horas del plan satisface la condición de socio y deja constancia de la vía,
		// sin perder el voluntariado: las dos condiciones conviven.
		\Convoca\Members\Roles_Miembro::anadir( $miembro_id, \Convoca\Members\Roles_Miembro::SOCIO );
		\Convoca\Members\Roles_Miembro::registrar_cumplimiento_por_horas( $miembro_id );

		$estado_actual = get_post_meta( $miembro_id, '_convoca_estado_miembro', true );

		if ( $estado_actual !== 'activo' ) {
			update_post_meta( $miembro_id, '_convoca_estado_cuota', 'activa' );
			Estados::change( $miembro_id, 'activo', "Completadas {$total_horas}h del plan {$plan}" );

			// Volunteer annual cycle management.
			// Cycle start: explicit period start, else fecha_alta, else today.
			$inicio_periodo = get_post_meta( $miembro_id, '_convoca_fecha_inicio_periodo', true );
			if ( empty( $inicio_periodo ) ) {
				$inicio_periodo = get_post_meta( $miembro_id, '_convoca_fecha_alta', true );
			}
			if ( empty( $inicio_periodo ) ) {
				$inicio_periodo = current_time( 'Y-m-d' );
				update_post_meta( $miembro_id, '_convoca_fecha_inicio_periodo', $inicio_periodo );
			}

			// Renewal date = one year after the cycle start (first cycle) or the
			// existing future date (kept on reactivation without expiry change).
			$renewal = get_post_meta( $miembro_id, '_convoca_fecha_renovacion', true );
			if ( empty( $renewal ) ) {
				update_post_meta(
					$miembro_id,
					'_convoca_fecha_renovacion',
					\Convoca\Core\Utils::format_date( $inicio_periodo . ' +1 year', 'Y-m-d' )
				);
			}
		}

		\Convoca\Core\Logger::info(
			"Voluntario ID $miembro_id ha completado las {$total_horas}h del plan {$plan}. Migrado a socio activo.",
			'Members/Voluntariado',
			$miembro_id
		);

		\Convoca\Core\Utils::do_action(
			'convoca_members_objetivo_completado',
			'convoca_member_objetivo_completado',
			$miembro_id,
			$total_horas,
			$plan
		);

		\Convoca\Core\Utils::do_action(
			'convoca_members_email_objetivo_voluntariado',
			'convoca_email_objetivo_voluntariado',
			$miembro_id
		);
	}

	/**
	 * Check if member has completed their hours objective.
	 */
	public static function ha_completado_objetivo( int $miembro_id ): bool {
		return get_post_meta( $miembro_id, '_convoca_objetivo_horas_completado', true ) === '1';
	}

	/**
	 * Get hours objective for a member's plan.
	 */
	public static function get_horas_objetivo( int $miembro_id ): float {
		$plan = get_post_meta( $miembro_id, '_convoca_plan', true );
		if ( empty( $plan ) ) {
			return 0;
		}

		$plan_data = CPT_Miembro::get_plan( $plan );
		return $plan_data ? (float) ( $plan_data['hours'] ?? 0 ) : 0;
	}

	/**
	 * Get progress percentage for a member.
	 */
	public static function get_progreso( int $miembro_id ): array {
		$total    = self::get_horas_aprobadas( $miembro_id );
		$objetivo = self::get_horas_objetivo( $miembro_id );

		$porcentaje = $objetivo > 0 ? min( 100, round( ( $total / $objetivo ) * 100 ) ) : 0;

		return array(
			'total'      => $total,
			'objetivo'   => $objetivo,
			'porcentaje' => $porcentaje,
			'completado' => $total >= $objetivo,
		);
	}
}
