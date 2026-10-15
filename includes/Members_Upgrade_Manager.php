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
 * Upgrade Manager for Convoca Members.
 *
 * Handles database structure upgrades for the members plugin.
 *
 * To add a new upgrade:
 * 1. Increment CONVOCA_MEMBERS_DB_VERSION in convoca-members.php
 * 2. Add a callback: '1.0.1' => [$this, 'upgrade_to_1_0_1']
 * 3. Implement the private method with idempotent logic.
 *
 * @package Convoca\Members
 */

namespace Convoca\Members;

use Convoca\Core\Upgrade_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Members_Upgrade_Manager extends Upgrade_Manager {

	public function __construct() {
		$this->init();
	}

	protected function get_db_version(): string {
		return defined( 'CONVOCA_MEMBERS_DB_VERSION' ) ? CONVOCA_MEMBERS_DB_VERSION : '0.0.0';
	}

	protected function get_option_name(): string {
		return 'convoca_members_db_version';
	}

	protected function get_transient_prefix(): string {
		return 'convoca_members';
	}

	protected function get_upgrade_callbacks(): array {
		return array(
			'1.0.2' => array( $this, 'upgrade_to_1_0_2' ),
			'1.0.3' => array( $this, 'upgrade_to_1_0_3' ),
			'2.8.47' => array( $this, 'upgrade_to_2_8_47' ),
			'2.8.81' => array( $this, 'upgrade_to_2_8_81' ),
		);
	}

	/**
	 * Paso 2.8.81: meter los datos de la transferencia en el correo de «solicitud recibida» (gateway#9).
	 *
	 * La plantilla vive en una OPCIÓN, así que cambiar la de por defecto no toca las instalaciones que ya
	 * la tienen guardada: hay que actualizarla aquí. Solo se añade el token si falta, y no se toca nada más
	 * de lo que la asociación haya escrito.
	 */
	protected function upgrade_to_2_8_81(): bool {
		$opcion    = \Convoca\Members\Email_Manager::option_name();
		$plantilla = get_option( $opcion, array() );

		if ( ! is_array( $plantilla ) || empty( $plantilla['solicitud_recibida']['body'] ) ) {
			return true;
		}

		$cuerpo = (string) $plantilla['solicitud_recibida']['body'];
		if ( false !== strpos( $cuerpo, '{datos_transferencia}' ) ) {
			return true;
		}

		$plantilla['solicitud_recibida']['body'] = str_replace(
			'<p>Un miembro del equipo revisará tu solicitud',
			'{datos_transferencia}<p>Un miembro del equipo revisará tu solicitud',
			$cuerpo
		);
		update_option( $opcion, $plantilla );

		\Convoca\Core\Logger::info( 'Plantilla «solicitud_recibida»: añadidos los datos de la transferencia.', 'Members/Upgrade' );

		return true;
	}

	/**
	 * Paso 2.8.47: limpiar las filas huérfanas que dejó la secuencia de números de socio.
	 *
	 * La tabla solo crecía porque nadie borraba la fila al eliminar la ficha (issue #16). Desde esta
	 * versión se borra al vuelo; este paso limpia lo acumulado en las instalaciones que ya existen.
	 */
	protected function upgrade_to_2_8_47(): bool {
		$borradas = \Convoca\Members\CPT_Miembro::limpiar_secuencia_huerfana();

		if ( $borradas > 0 ) {
			\Convoca\Core\Logger::info( "Limpieza de la secuencia de socios: $borradas filas huérfanas borradas.", 'Members/Upgrade' );
		}

		return true;
	}

	/**
	 * Create dedicated table for member sequence to prevent deadlocks on wp_options.
	 */
	protected function upgrade_to_1_0_3(): bool {
		global $wpdb;
		$table           = $wpdb->prefix . 'convoca_member_sequence';
		$charset_collate = $wpdb->get_charset_collate();

		// Table is now created in the common Installer, but ensure it exists.
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$sql = "CREATE TABLE IF NOT EXISTS $table (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            member_id BIGINT(20) UNSIGNED NOT NULL,
            PRIMARY KEY (id),
            INDEX member_id (member_id)
        ) $charset_collate;";
		dbDelta( $sql );

		// Idempotent: only initialize if empty.
		$current_max_seq = (int) $wpdb->get_var( "SELECT MAX(id) FROM $table" );
		if ( $current_max_seq === 0 ) {
			$last_number = (int) get_option( 'convoca_last_member_number', 0 );

			if ( $last_number > 0 ) {
				// Initialize the AUTO_INCREMENT to the last known number.
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- ALTER TABLE with numeric value from option, no user input.
				$wpdb->query( "ALTER TABLE $table AUTO_INCREMENT = " . ( $last_number + 1 ) );
			} else {
				// Sync with existing postmeta if option is missing.
				$max_postmeta = (int) $wpdb->get_var(
					"
                    SELECT MAX(CAST(meta_value AS UNSIGNED)) 
                    FROM {$wpdb->postmeta} 
                    WHERE meta_key = '_convoca_numero_socio'
                "
				);
				if ( $max_postmeta > 0 ) {
					// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- ALTER TABLE with numeric value, no user input.
					$wpdb->query( "ALTER TABLE $table AUTO_INCREMENT = " . ( $max_postmeta + 1 ) );
				}
			}
		}

		return true;
	}

	/**
	 * Normalize _convoca_fecha_renovacion to Y-m-d.
	 * Prevents issues with time components in cron queries.
	 */
	protected function upgrade_to_1_0_2(): bool {
		global $wpdb;

		$metas = $wpdb->get_results( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_convoca_fecha_renovacion'" );

		if ( empty( $metas ) ) {
			return true;
		}

		foreach ( $metas as $meta ) {
			$current = trim( (string) $meta->meta_value );

			// Skip empty, zero, or '0' values (not parseable as dates).
			if ( empty( $current ) || $current === '0000-00-00' ) {
				continue;
			}

			// Extract only the date part if it contains time or other chars.
			$timestamp = strtotime( $current );
			if ( $timestamp === false || $timestamp <= 0 ) {
				\Convoca\Core\Logger::warning( "Upgrade 1.0.2: fecha inválida '{$current}' para post_id {$meta->post_id}, omitiendo.", 'Members/Upgrade' );
				continue;
			}

			$normalized = wp_date( 'Y-m-d', $timestamp );
			if ( $normalized !== $current ) {
				update_post_meta( $meta->post_id, '_convoca_fecha_renovacion', $normalized );
			}
		}

		return true;
	}
}
