<?php
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
		update_post_meta( $member_id, '_conv_consent_timestamp', current_time( 'mysql' ) );
		update_post_meta( $member_id, '_conv_consent_version', sanitize_text_field( $consent_version ) );
		update_post_meta( $member_id, '_conv_consent_text', sanitize_textarea_field( $consent_text ) );
		update_post_meta( $member_id, '_conv_consent_ip', sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ) );

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
			'exporter_friendly_name' => get_bloginfo('name') . ' — ' . __( 'Datos de socio', 'convoca-members' ),
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
						'key'   => '_conv_email',
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
				'_conv_nombre'           => 'Nombre',
				'_conv_apellidos'        => 'Apellidos',
				'_conv_email'            => 'Email',
				'_conv_telefono'         => 'Teléfono',
				'_conv_dni'              => 'DNI/NIE',
				'_conv_direccion'        => 'Dirección',
				'_conv_fecha_nacimiento' => 'Fecha de nacimiento',
				'_conv_plan'             => 'Plan de membresía',
				'_conv_estado'           => 'Estado',
				'_conv_numero_socio'     => 'Número de socio',
				'_conv_fecha_alta'       => 'Fecha de alta',
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
			$consent_ts = get_post_meta( $member_id, '_conv_consent_timestamp', true );
			if ( $consent_ts ) {
				$profile_data[] = array(
					'name'  => 'Consentimiento registrado',
					'value' => $consent_ts,
				);
				$profile_data[] = array(
					'name'  => 'Versión de consentimiento',
					'value' => get_post_meta( $member_id, '_conv_consent_version', true ),
				);
			}

			if ( ! empty( $profile_data ) ) {
				$export_items[] = array(
					'group_id'          => 'convoca-member-profile',
					'group_label'       => 'Datos de socio ' . get_bloginfo('name'),
					'group_description' => 'Datos personales almacenados como socio de la asociación.',
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
							'key'   => '_conv_origin',
							'value' => 'members',
						),
						array(
							'key'   => '_conv_origin_id',
							'value' => $member_id,
						),
					),
				)
			);

			foreach ( $pagos as $pago ) {
				$amount_cents = (int) get_post_meta( $pago->ID, '_conv_amount_cents', true );
				$pago_data    = array(
					array(
						'name'  => 'Concepto',
						'value' => get_post_meta( $pago->ID, '_conv_product_desc', true ),
					),
					array(
						'name'  => 'Importe',
						'value' => number_format( $amount_cents / 100, 2, ',', '.' ) . ' €',
					),
					array(
						'name'  => 'Estado',
						'value' => get_post_meta( $pago->ID, '_conv_status', true ),
					),
					array(
						'name'  => 'Método',
						'value' => get_post_meta( $pago->ID, '_conv_method', true ),
					),
					array(
						'name'  => 'Fecha',
						'value' => $pago->post_date,
					),
				);

				$export_items[] = array(
					'group_id'          => 'convoca-payments',
					'group_label'       => 'Pagos ' . get_bloginfo('name'),
					'group_description' => 'Historial de pagos realizados.',
					'item_id'           => "payment-{$pago->ID}",
					'data'              => $pago_data,
				);
			}

			// ── Inscriptions (search by member email) ──
			$member_email  = get_post_meta( $member_id, '_conv_email', true );
			$inscripciones = get_posts(
				array(
					'post_type'      => 'inscripcion',
					'posts_per_page' => -1,
					'post_status'    => 'any',
					'meta_query'     => array(
						array(
							'key'   => '_conv_email',
							'value' => $member_email,
						),
					),
				)
			);

			foreach ( $inscripciones as $inscripcion ) {
				$actividad_id    = get_post_meta( $inscripcion->ID, '_conv_actividad_id', true );
				$actividad_title = $actividad_id ? get_the_title( $actividad_id ) : 'N/A';

				$insc_data = array(
					array(
						'name'  => 'Actividad',
						'value' => $actividad_title,
					),
					array(
						'name'  => 'Estado',
						'value' => get_post_meta( $inscripcion->ID, '_conv_estado', true ),
					),
					array(
						'name'  => 'Fecha inscripción',
						'value' => $inscripcion->post_date,
					),
				);

				$export_items[] = array(
					'group_id'          => 'convoca-inscriptions',
					'group_label'       => 'Inscripciones ' . get_bloginfo('name'),
					'group_description' => 'Inscripciones a actividades.',
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
							'key'   => '_conv_member_id',
							'value' => $member_id,
						),
					),
				)
			);

			foreach ( $horas as $hora ) {
				$proyecto_id = get_post_meta( $hora->ID, '_conv_proyecto_id', true );
				$hora_data   = array(
					array(
						'name'  => 'Fecha',
						'value' => get_post_meta( $hora->ID, '_conv_fecha', true ),
					),
					array(
						'name'  => 'Horas',
						'value' => get_post_meta( $hora->ID, '_conv_horas', true ),
					),
					array(
						'name'  => 'Proyecto',
						'value' => $proyecto_id ? get_the_title( $proyecto_id ) : '',
					),
					array(
						'name'  => 'Descripción',
						'value' => $hora->post_content,
					),
					array(
						'name'  => 'Estado',
						'value' => get_post_meta( $hora->ID, '_conv_estado', true ),
					),
				);

				$export_items[] = array(
					'group_id'          => 'convoca-volunteering',
					'group_label'       => 'Horas de voluntariado ' . get_bloginfo('name'),
					'group_description' => 'Registros de horas de voluntariado.',
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
						'key'   => '_conv_email',
						'value' => sanitize_email( $email_address ),
					),
				),
			)
		);

		foreach ( $members as $member ) {
			$member_id    = $member->ID;
			$member_email = get_post_meta( $member_id, '_conv_email', true );

			Logger::info( "GDPR: Inicio de borrado de datos para socio ID: {$member_id}", 'Members/GDPR', $member_id );

			// ── Anonymize member profile ──
			$pii_fields = array(
				'_conv_nombre',
				'_conv_apellidos',
				'_conv_email',
				'_conv_telefono',
				'_conv_dni',
				'_conv_direccion',
				'_conv_fecha_nacimiento',
				'_conv_access_code',
				'_conv_consent_ip',
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
			update_post_meta( $member_id, '_conv_estado', 'baja' );
			update_post_meta( $member_id, '_conv_gdpr_erased', current_time( 'mysql' ) );

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
							'key'   => '_conv_origin',
							'value' => 'members',
						),
						array(
							'key'   => '_conv_origin_id',
							'value' => $member_id,
						),
					),
				)
			);

			foreach ( $pagos as $pago ) {
				update_post_meta( $pago->ID, '_conv_origin_id', 0 );
				update_post_meta( $pago->ID, '_conv_product_desc', '[Eliminado]' );
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
								'key'   => '_conv_email',
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
							'key'   => '_conv_member_id',
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
