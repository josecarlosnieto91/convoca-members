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
 * GDPR compliance tools: data export, data erasure, consent logging.
 *
 * Integrates with WordPress Privacy Tools (Tools > Export/Erase Personal Data).
 *
 * @package Convoca\Members
 */

namespace Convoca\Members;

use Convoca\Core\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GDPR_Tools {

	/**
	 * Initialize GDPR hooks.
	 */
	public static function init(): void {
		// Register personal data exporter.
		add_filter( 'wp_privacy_personal_data_exporters', array( self::class, 'register_exporter' ) );

		// Register personal data eraser.
		add_filter( 'wp_privacy_personal_data_erasers', array( self::class, 'register_eraser' ) );
	}

	/*
	────────────────────────────────────────────
	 * CONSENT LOGGING
	 * ──────────────────────────────────────────── */

	/**
	 * Record consent when a member registers.
	 *
	 * @param int    $member_id      Post ID of the member.
	 * @param string $consent_text   Description of what was consented to.
	 * @param string $consent_version Version identifier (e.g. '1.0').
	 */
	public static function log_consent( int $member_id, string $consent_text = '', string $consent_version = '1.0' ): void {
		update_post_meta( $member_id, '_convoca_consent_timestamp', current_time( 'mysql' ) );
		update_post_meta( $member_id, '_convoca_consent_version', sanitize_text_field( $consent_version ) );
		update_post_meta( $member_id, '_convoca_consent_text', sanitize_textarea_field( $consent_text ) );
		update_post_meta( $member_id, '_convoca_consent_ip', sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ) );

		Logger::info( "Consentimiento registrado para socio ID: {$member_id} (v{$consent_version})", 'Members/GDPR', $member_id );
	}

	/*
	────────────────────────────────────────────
	 * DATA EXPORTER (WordPress Privacy Tools)
	 * ──────────────────────────────────────────── */

	/**
	 * Register the Convoca data exporter.
	 *
	 * @param array $exporters
	 * @return array
	 */
	public static function register_exporter( array $exporters ): array {
		$exporters['convoca-members'] = array(
			'exporter_friendly_name' => get_bloginfo( 'name' ) . ' — ' . __( 'Datos de socio', 'convoca-members' ),
			'callback'               => array( self::class, 'export_personal_data' ),
		);
		return $exporters;
	}

	/**
	 * Export personal data for a given email address.
	 *
	 * @param string $email_address
	 * @param int    $page
	 * @return array
	 */
	public static function export_personal_data( string $email_address, int $page = 1 ): array {
		$export_items = array();

		// Find member by email.
		$members = get_posts(
			array(
				'post_type'      => 'miembro',
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'meta_query'     => array(
					array(
						'key'   => '_convoca_email',
						'value' => sanitize_email( $email_address ),
					),
				),
			)
		);

		foreach ( $members as $member ) {
			$member_id = $member->ID;

			// ── Member profile data ──
			$profile_data = array();
			$meta_map     = array(
				'_convoca_nombre'           => __( 'Nombre', 'convoca-members' ),
				'_convoca_apellidos'        => __( 'Apellidos', 'convoca-members' ),
				'_convoca_email'            => __( 'Email', 'convoca-members' ),
				'_convoca_telefono'         => __( 'Teléfono', 'convoca-members' ),
				'_convoca_dni'              => __( 'DNI/NIE', 'convoca-members' ),
				'_convoca_direccion'        => __( 'Dirección', 'convoca-members' ),
				'_convoca_fecha_nacimiento' => __( 'Fecha de nacimiento', 'convoca-members' ),
				'_convoca_plan'             => __( 'Plan de membresía', 'convoca-members' ),
				'_convoca_estado'           => __( 'Estado', 'convoca-members' ),
				'_convoca_numero_socio'     => __( 'Número de socio', 'convoca-members' ),
				'_convoca_fecha_alta'       => __( 'Fecha de alta', 'convoca-members' ),
			);

			foreach ( $meta_map as $meta_key => $label ) {
				$value = get_post_meta( $member_id, $meta_key, true );
				if ( $value ) {
					$profile_data[] = array(
						'name'  => $label,
						'value' => $value,
					);
				}
			}

			// Add consent info.
			$consent_ts = get_post_meta( $member_id, '_convoca_consent_timestamp', true );
			if ( $consent_ts ) {
				$profile_data[] = array(
					'name'  => __( 'Consentimiento registrado', 'convoca-members' ),
					'value' => $consent_ts,
				);
				$profile_data[] = array(
					'name'  => __( 'Versión de consentimiento', 'convoca-members' ),
					'value' => get_post_meta( $member_id, '_convoca_consent_version', true ),
				);
			}

			if ( ! empty( $profile_data ) ) {
				$export_items[] = array(
					'group_id'          => 'convoca-member-profile',
					'group_label'       => __( 'Datos de socio', 'convoca-members' ) . ' ' . get_bloginfo( 'name' ),
					'group_description' => __( 'Datos personales almacenados como socio de la asociación.', 'convoca-members' ),
					'item_id'           => "member-{$member_id}",
					'data'              => $profile_data,
				);
			}

			// ── Payments ──
			$pagos = get_posts(
				array(
					'post_type'      => 'pago',
					'posts_per_page' => -1,
					'post_status'    => 'any',
					'meta_query'     => array(
						'relation' => 'AND',
						array(
							'key'   => '_convoca_origin',
							'value' => 'members',
						),
						array(
							'key'   => '_convoca_origin_id',
							'value' => $member_id,
						),
					),
				)
			);

			foreach ( $pagos as $pago ) {
				$amount_cents = (int) get_post_meta( $pago->ID, '_convoca_amount_cents', true );
				$pago_data    = array(
					array(
						'name'  => __( 'Concepto', 'convoca-members' ),
						'value' => get_post_meta( $pago->ID, '_convoca_product_desc', true ),
					),
					array(
						'name'  => __( 'Importe', 'convoca-members' ),
						'value' => number_format( $amount_cents / 100, 2, ',', '.' ) . ' €',
					),
					array(
						'name'  => __( 'Estado', 'convoca-members' ),
						'value' => get_post_meta( $pago->ID, '_convoca_status', true ),
					),
					array(
						'name'  => __( 'Método', 'convoca-members' ),
						'value' => get_post_meta( $pago->ID, '_convoca_method', true ),
					),
					array(
						'name'  => __( 'Fecha', 'convoca-members' ),
						'value' => $pago->post_date,
					),
				);

				$export_items[] = array(
					'group_id'          => 'convoca-payments',
					'group_label'       => __( 'Pagos', 'convoca-members' ) . ' ' . get_bloginfo( 'name' ),
					'group_description' => __( 'Historial de pagos realizados.', 'convoca-members' ),
					'item_id'           => "payment-{$pago->ID}",
					'data'              => $pago_data,
				);
			}

			// ── Inscriptions (search by member email) ──
			$member_email  = get_post_meta( $member_id, '_convoca_email', true );
			$inscripciones = get_posts(
				array(
					'post_type'      => 'inscripcion',
					'posts_per_page' => -1,
					'post_status'    => 'any',
					'meta_query'     => array(
						array(
							'key'   => '_convoca_email',
							'value' => $member_email,
						),
					),
				)
			);

			foreach ( $inscripciones as $inscripcion ) {
				$actividad_id    = get_post_meta( $inscripcion->ID, '_convoca_actividad_id', true );
				$actividad_title = $actividad_id ? get_the_title( $actividad_id ) : 'N/A';

				$insc_data = array(
					array(
						'name'  => __( 'Actividad', 'convoca-members' ),
						'value' => $actividad_title,
					),
					array(
						'name'  => __( 'Estado', 'convoca-members' ),
						'value' => get_post_meta( $inscripcion->ID, '_convoca_estado', true ),
					),
					array(
						'name'  => __( 'Fecha inscripción', 'convoca-members' ),
						'value' => $inscripcion->post_date,
					),
				);

				$export_items[] = array(
					'group_id'          => 'convoca-inscriptions',
					'group_label'       => __( 'Inscripciones', 'convoca-members' ) . ' ' . get_bloginfo( 'name' ),
					'group_description' => __( 'Inscripciones a actividades.', 'convoca-members' ),
					'item_id'           => "inscription-{$inscripcion->ID}",
					'data'              => $insc_data,
				);
			}

			// ── Volunteering hours ──
			$horas = get_posts(
				array(
					'post_type'      => 'registro_hora',
					'posts_per_page' => -1,
					'post_status'    => 'any',
					'meta_query'     => array(
						array(
							'key'   => '_convoca_member_id',
							'value' => $member_id,
						),
					),
				)
			);

			foreach ( $horas as $hora ) {
				$proyecto_id = get_post_meta( $hora->ID, '_convoca_proyecto_id', true );
				$hora_data   = array(
					array(
						'name'  => __( 'Fecha', 'convoca-members' ),
						'value' => get_post_meta( $hora->ID, '_convoca_fecha', true ),
					),
					array(
						'name'  => __( 'Horas', 'convoca-members' ),
						'value' => get_post_meta( $hora->ID, '_convoca_horas', true ),
					),
					array(
						'name'  => __( 'Proyecto', 'convoca-members' ),
						'value' => $proyecto_id ? get_the_title( $proyecto_id ) : '',
					),
					array(
						'name'  => __( 'Descripción', 'convoca-members' ),
						'value' => $hora->post_content,
					),
					array(
						'name'  => __( 'Estado', 'convoca-members' ),
						'value' => get_post_meta( $hora->ID, '_convoca_estado', true ),
					),
				);

				$export_items[] = array(
					'group_id'          => 'convoca-volunteering',
					'group_label'       => __( 'Horas de voluntariado ', 'convoca-members' ) . get_bloginfo( 'name' ),
					'group_description' => __( 'Registros de horas de voluntariado.', 'convoca-members' ),
					'item_id'           => "volunteer-hour-{$hora->ID}",
					'data'              => $hora_data,
				);
			}
		}

		return array(
			'data' => $export_items,
			'done' => true,
		);
	}

	/*
	────────────────────────────────────────────
	 * DATA ERASER (WordPress Privacy Tools)
	 * ──────────────────────────────────────────── */

	/**
	 * Register the Convoca data exporter.eraser.
	 *
	 * @param array $erasers
	 * @return array
	 */
	public static function register_eraser( array $erasers ): array {
		$erasers['convoca-members'] = array(
			'eraser_friendly_name' => __( 'Convoca — Datos de socio', 'convoca-members' ),
			'callback'             => array( self::class, 'erase_personal_data' ),
		);
		return $erasers;
	}

	/**
	 * Erase or anonymize personal data for a given email address.
	 *
	 * Strategy:
	 * - Member profile: anonymize (replace PII with "[Eliminado]").
	 * - Payments: anonymize (keep records for fiscal compliance, remove PII).
	 * - Inscriptions: delete.
	 * - Volunteering hours: delete.
	 *
	 * @param string $email_address
	 * @param int    $page
	 * @return array
	 */
	public static function erase_personal_data( string $email_address, int $page = 1 ): array {
		$items_removed  = 0;
		$items_retained = 0;
		$messages       = array();

		// Find member by email.
		$members = get_posts(
			array(
				'post_type'      => 'miembro',
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'meta_query'     => array(
					array(
						'key'   => '_convoca_email',
						'value' => sanitize_email( $email_address ),
					),
				),
			)
		);

		foreach ( $members as $member ) {
			$member_id    = $member->ID;
			$member_email = get_post_meta( $member_id, '_convoca_email', true );

			Logger::info( "GDPR: Inicio de borrado de datos para socio ID: {$member_id}", 'Members/GDPR', $member_id );

			// ── Anonymize member profile ──
			$pii_fields = array(
				'_convoca_nombre',
				'_convoca_apellidos',
				'_convoca_email',
				'_convoca_telefono',
				'_convoca_dni',
				'_convoca_direccion',
				'_convoca_fecha_nacimiento',
				'_convoca_access_code',
				'_convoca_consent_ip',
			);

			foreach ( $pii_fields as $field ) {
				update_post_meta( $member_id, $field, '[Eliminado]' );
			}

			// Update member title.
			wp_update_post(
				array(
					'ID'         => $member_id,
					'post_title' => 'Socio anonimizado #' . $member_id,
				)
			);

			// Mark as erased.
			update_post_meta( $member_id, '_convoca_estado', 'baja' );
			update_post_meta( $member_id, '_convoca_gdpr_erased', current_time( 'mysql' ) );

			++$items_removed;
			$messages[] = "Datos personales de socio #{$member_id} anonimizados.";

			// ── Anonymize payments (retain for fiscal compliance) ──
			$pagos = get_posts(
				array(
					'post_type'      => 'pago',
					'posts_per_page' => -1,
					'post_status'    => 'any',
					'meta_query'     => array(
						'relation' => 'AND',
						array(
							'key'   => '_convoca_origin',
							'value' => 'members',
						),
						array(
							'key'   => '_convoca_origin_id',
							'value' => $member_id,
						),
					),
				)
			);

			foreach ( $pagos as $pago ) {
				update_post_meta( $pago->ID, '_convoca_origin_id', 0 );
				update_post_meta( $pago->ID, '_convoca_product_desc', '[Eliminado]' );
				++$items_retained;
			}

			if ( ! empty( $pagos ) ) {
				$messages[] = count( $pagos ) . ' pago(s) anonimizado(s) (retenidos por obligación fiscal).';
			}

			// ── Delete inscriptions (search by member email) ──
			if ( ! empty( $member_email ) && $member_email !== '[Eliminado]' ) {
				$inscripciones = get_posts(
					array(
						'post_type'      => 'inscripcion',
						'posts_per_page' => -1,
						'post_status'    => 'any',
						'meta_query'     => array(
							array(
								'key'   => '_convoca_email',
								'value' => $member_email,
							),
						),
					)
				);

				foreach ( $inscripciones as $inscripcion ) {
					wp_delete_post( $inscripcion->ID, true );
					++$items_removed;
				}

				if ( ! empty( $inscripciones ) ) {
					$messages[] = count( $inscripciones ) . ' inscripción(es) eliminada(s).';
				}
			}

			// ── Delete volunteering hours ──
			$horas = get_posts(
				array(
					'post_type'      => 'registro_hora',
					'posts_per_page' => -1,
					'post_status'    => 'any',
					'meta_query'     => array(
						array(
							'key'   => '_convoca_member_id',
							'value' => $member_id,
						),
					),
				)
			);

			foreach ( $horas as $hora ) {
				wp_delete_post( $hora->ID, true );
				++$items_removed;
			}

			if ( ! empty( $horas ) ) {
				$messages[] = count( $horas ) . ' registro(s) de horas eliminado(s).';
			}

			Logger::info( "GDPR: Borrado completado para socio ID: {$member_id}. Eliminados: {$items_removed}, Retenidos: {$items_retained}", 'Members/GDPR', $member_id );
		}

		return array(
			'items_removed'  => $items_removed,
			'items_retained' => $items_retained,
			'messages'       => $messages,
			'done'           => true,
		);
	}
}
