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
 * Member registration processing logic.
 *
 * @package Convoca\Members
 */

namespace Convoca\Members;

use Convoca\Core\Logger;
use Convoca\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Process_Member {


	/**
	 * Ensure a WP user exists for a member, generate credentials if new, and return them.
	 *
	 * @param int $member_id Member CPT post ID.
	 * @return array{user_id: int, username: string, password: string, is_new: bool}
	 */
	public static function ensure_wp_user( int $member_id ): array {
		$member = get_post( $member_id );
		$email  = get_post_meta( $member_id, '_convoca_email', true );
		$nombre = $member ? $member->post_title : 'Miembro';

		// Check if WP user already exists via meta link.
		$existing_user_id = (int) get_post_meta( $member_id, '_convoca_user_id', true );
		if ( $existing_user_id && get_userdata( $existing_user_id ) ) {
			// User exists — generate new password and send.
			$new_password = wp_generate_password( 12, false );
			wp_set_password( $new_password, $existing_user_id );
			return array(
				'user_id'  => $existing_user_id,
				'username' => get_userdata( $existing_user_id )->user_login,
				'password' => $new_password,
				'is_new'   => false,
			);
		}

		// Create new WP user.
		$base_username = sanitize_user( current( explode( '@', $email ) ), true );
		$username      = $base_username;
		$suffix        = 1;
		while ( username_exists( $username ) ) {
			$username = $base_username . $suffix;
			++$suffix;
		}

		$password = wp_generate_password( 12, false );
		$user_id  = wp_create_user( $username, $password, $email );

		if ( is_wp_error( $user_id ) ) {
			Logger::error( "Error al crear usuario WP para miembro #{$member_id}: " . $user_id->get_error_message(), 'Members' );
			// Fallback: try with a more unique username.
			$username = $base_username . '_' . $member_id;
			$user_id  = wp_create_user( $username, $password, $email );
			if ( is_wp_error( $user_id ) ) {
				Logger::error( "Error fatal al crear usuario WP para miembro #{$member_id}", 'Members' );
				return array(
					'user_id'  => 0,
					'username' => '',
					'password' => '',
					'is_new'   => false,
				);
			}
		}

		// Update user info.
		wp_update_user(
			array(
				'ID'           => $user_id,
				'first_name'   => $nombre,
				'display_name' => $nombre,
			)
		);

		// Link WP user to member.
		update_post_meta( $member_id, '_convoca_user_id', $user_id );
		update_user_meta( $user_id, '_convoca_member_id', $member_id );

		// Copy member data to user meta.
		update_user_meta( $user_id, '_convoca_email', $email );
		update_user_meta( $user_id, '_convoca_dni', get_post_meta( $member_id, '_convoca_dni', true ) );
		update_user_meta( $user_id, '_convoca_telefono', get_post_meta( $member_id, '_convoca_telefono', true ) );

		Logger::info( "Usuario WP creado para miembro #{$member_id}: {$username}", 'Members', $member_id );

		return array(
			'user_id'  => $user_id,
			'username' => $username,
			'password' => $password,
			'is_new'   => true,
		);
	}

	/**
	 * Send credentials email to a member via Email_Manager template.
	 *
	 * @param int    $member_id Member CPT post ID.
	 * @param string $username  WP username.
	 * @param string $password  Plain-text password.
	 */
	public static function send_credentials_email( int $member_id, string $username, string $password ): void {
		$email = get_post_meta( $member_id, '_convoca_email', true );

		if ( ! $email || ! is_email( $email ) ) {
			Logger::warning( "No se pueden enviar credenciales: email inválido para miembro #{$member_id}", 'Members' );
			return;
		}

		// Send via Email_Manager template (customizable in admin).
		$email_manager = new Email_Manager();
		$email_manager->send_credenciales(
			$member_id,
			array(
				'{usuario}'   => $username,
				'{password}'  => $password,
				'{login_url}' => home_url( '/mi-area/' ),
			)
		);

		update_post_meta( $member_id, '_convoca_credenciales_enviadas', current_time( 'mysql' ) );
		Logger::info( "Credenciales enviadas a {$email} para miembro #{$member_id}", 'Members', $member_id );
	}

	/**
	 * Process a member registration request.
	 *
	 * @param array $data  Sanitized input data.
	 * @param array $files Uploaded files ($_FILES).
	 * @return array|\WP_Error Success data or WP_Error.
	 */
	/**
	 * Generate credentials for a member upon approval and send them.
	 * Hook this to approval triggers.
	 */
	public static function handle_approved( int $member_id ): void {
		$creds = self::ensure_wp_user( $member_id );
		if ( $creds['user_id'] && $creds['username'] ) {
			self::send_credentials_email( $member_id, $creds['username'], $creds['password'] );
		}
	}

	public static function register( array $data, array $files = array() ): array|\WP_Error {
		// 1. Validate
		$validation = self::validate( $data, $files );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// 2. Identify and sanitize key fields
		$nombre     = sanitize_text_field( $data['nombre'] ?? '' );
		$dni        = strtoupper( trim( $data['dni'] ?? '' ) );
		$dni        = str_replace( array( ' ', '-' ), '', $dni );
		$email      = sanitize_email( $data['email'] ?? '' );
		$plan       = sanitize_text_field( $data['plan'] ?? '' );
		$sub_plan   = sanitize_text_field( $data['sub_plan'] ?? '' );
		$forma_pago = sanitize_text_field( $data['forma_pago'] ?? '' );

		// Mask DNI for logging (show only first 2 and last 2 chars).
		$dni_masked = strlen( $dni ) > 4 ? substr( $dni, 0, 2 ) . '...' . substr( $dni, -2 ) : 'XXXX';
		\Convoca\Core\Logger::info( "Iniciando registro de nuevo miembro: $nombre (DNI: $dni_masked, Email: $email)", 'Members' );

		// 3. Resolve plan data
		$plan_key  = ( in_array( $plan, array( 'familiar', 'juvenil' ), true ) && $sub_plan ) ? $sub_plan : $plan;
		$plan_data = CPT_Miembro::get_plan( $plan_key );
		if ( ! $plan_data ) {
			return new \WP_Error( 'invalid_plan', __( 'El plan seleccionado no es válido.', 'convoca-members' ) );
		}
		// 3b. Reject inactive plans (desactivados desde Ajustes o el asistente).
		if ( isset( $plan_data['active'] ) && false === $plan_data['active'] ) {
			return new \WP_Error( 'inactive_plan', __( 'El plan seleccionado no está disponible en este momento.', 'convoca-members' ) );
		}

		// 5. Calculate age/minor status
		$fecha_nac = sanitize_text_field( $data['fecha_nacimiento'] ?? '' );
		$menor     = false;
		if ( $fecha_nac ) {
			$dob   = new \DateTime( $fecha_nac );
			$today = new \DateTime();
			$age   = $today->diff( $dob )->y;
			$menor = $age < 18;
		}

		// 6. Determine initial state
		// If they choose 'voluntariado' (hours instead of money), they are 'pendiente_documentacion' (proof of volunteering or similar).
		// If they choose 'cuota' (money), they are 'pendiente_pago'.
		$estado = ( $forma_pago === 'voluntariado' ) ? 'pendiente_documentacion' : 'pendiente_pago';

		// 7. Create Post with transaction
		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );

		try {
			// Check for duplicates ATOMICALLY inside transaction to prevent race conditions.
			// Nota: COUNT(*) ... FOR UPDATE no bloquea filas (MySQL ignora FOR UPDATE en agregados);
			// usamos SELECT ... LIMIT 1 FOR UPDATE para serializar sobre la(s) fila(s) existente(s).
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT pm.post_id FROM {$wpdb->postmeta} pm
                 JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                 WHERE p.post_type = 'miembro' 
                   AND p.post_status = 'publish'
                   AND ((pm.meta_key = '_convoca_dni' AND pm.meta_value = %s)
                        OR (pm.meta_key = '_convoca_email' AND pm.meta_value = %s))
                 LIMIT 1 FOR UPDATE",
					$dni,
					$email
				)
			);

			if ( ! empty( $exists ) ) {
				$wpdb->query( 'ROLLBACK' );
				\Convoca\Core\Logger::warning( "Intento de registro duplicado detectado en transacción: $dni_masked / $email", 'Members' );
				return new \WP_Error( 'duplicate', __( 'Ya existe un miembro registrado con este DNI o email.', 'convoca-members' ) );
			}

			$post_id = wp_insert_post(
				array(
					'post_type'   => 'miembro',
					'post_title'  => $nombre,
					'post_status' => 'publish', // CPT is private, so 'publish' means internally available.
				),
				true
			);

			if ( is_wp_error( $post_id ) ) {
				$wpdb->query( 'ROLLBACK' );
				\Convoca\Core\Logger::error( 'Error al insertar post de miembro: ' . $post_id->get_error_message(), 'Members' );
				return new \WP_Error( 'create_failed', __( 'Error al crear el registro en la base de datos.', 'convoca-members' ) );
			}

			// 8. Save Meta
			$settings = get_option( 'convoca_members_settings', array() );

			$meta = array(
				'estado_miembro'    => $estado,
				'plan'              => $plan,
				'plan_label'        => ( $p = CPT_Miembro::get_plan( $plan ) ) ? $p['label'] : $plan,
				'sub_plan'          => $sub_plan,
				'forma_pago'        => $forma_pago,
				'cuota'             => $plan_key, // legacy alias.
				'modalidad'         => $plan_data['modalidad'] ?? 'Numerario',
				'importe_cuota'     => $plan_data['price'] ?? 0,
				'estado_cuota'      => ( $forma_pago === 'voluntariado' ) ? 'activa' : 'pendiente',
				'dni'               => $dni,
				'fecha_nacimiento'  => $fecha_nac,
				'email'             => $email,
				'access_code'       => Utils::generate_access_code(),
				'telefono'          => sanitize_text_field( $data['telefono'] ?? '' ),
				'whatsapp'          => sanitize_text_field( $data['whatsapp'] ?? 'si' ),
				'direccion'         => sanitize_text_field( $data['direccion'] ?? '' ),
				'municipio'         => sanitize_text_field( $data['municipio'] ?? '' ),
				'canal_contacto'    => sanitize_text_field( $data['canal_contacto'] ?? 'whatsapp' ),
				'menor_edad'        => $menor ? '1' : '0',
				'es_voluntario'     => ( $forma_pago === 'voluntariado' ) ? '1' : '0',
				'rgpd_version'      => $settings['rgpd_version'] ?? '1.0',
				'rgpd_timestamp'    => current_time( 'mysql' ),
				'comunicaciones_ok' => ! empty( $data['comunicaciones'] ) ? '1' : '0',
				'pago_recurrente'   => ! empty( $data['pago_recurrente'] ) ? '1' : '0',
				// Note: Renewal date is set on payment completion/approval, not here.
				// But for initial record, we might set a tentative one or leave empty.
			);

			if ( $menor ) {
				$meta['tutor_nombre']       = sanitize_text_field( $data['tutor_nombre'] ?? '' );
				$meta['tutor_dni']          = sanitize_text_field( $data['tutor_dni'] ?? '' );
				$meta['tutor_autorizacion'] = '1';
			}

			foreach ( $meta as $key => $value ) {
				update_post_meta( $post_id, '_convoca_' . $key, $value );
			}

			// Log GDPR consent.
			GDPR_Tools::log_consent(
				$post_id,
				'Aceptación de política de privacidad y condiciones de alta como socio.',
				$settings['rgpd_version'] ?? '1.0'
			);

			// Initial state log.
			Estados::change( $post_id, $estado, 'Registro inicial desde formulario' );

			// Commit transaction.
			$wpdb->query( 'COMMIT' );
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			\Convoca\Core\Logger::error( 'Excepción durante registro de miembro: ' . $e->getMessage(), 'Members' );
			return new \WP_Error( 'create_failed', __( 'Error inesperado al crear el registro.', 'convoca-members' ) );
		}

		// 9. Notifications
		\Convoca\Core\Utils::do_action( 'convoca_members_email_solicitud', 'convoca_email_solicitud', $post_id );
		\Convoca\Core\Utils::do_action( 'convoca_members_created', 'convoca_miembro_creado', $post_id );

		// 10. Handle Gateway Redirection
		$importe = (float) ( $plan_data['price'] ?? 0 );
		if ( in_array( $forma_pago, array( 'tarjeta', 'bizum', 'transferencia' ), true ) && $importe > 0 && \Convoca\Core\Features::is_gateway_active() && function_exists( 'Convoca\Gateway\convoca_gateway_create_payment' ) ) {
			$amount_cents = (int) round( $importe * 100 );

			$payment = \Convoca\Gateway\convoca_gateway_create_payment(
				array(
					'amount_cents' => $amount_cents,
					'method'       => $forma_pago,
					'origin'       => 'members',
					'origin_id'    => $post_id,
					'product_desc' => mb_substr( strtoupper( get_bloginfo( 'name' ) ) . ' CUOTA ' . ( $plan_data['label'] ?? '' ), 0, 125 ),
				)
			);

			if ( ! is_wp_error( $payment ) ) {
				update_post_meta( $post_id, '_convoca_pago_id', $payment['pago_id'] );
				return array(
					'id'       => $post_id,
					'nombre'   => $nombre,
					'redirect' => $payment['payment_url'],
					'estado'   => $estado,
				);
			} else {
				\Convoca\Core\Logger::error( 'Error al crear pago en pasarela: ' . $payment->get_error_message(), 'Members', $post_id );

				// Flag for manual review since payment failed.
				update_post_meta( $post_id, '_convoca_needs_manual_review', '1' );
				update_post_meta( $post_id, '_convoca_review_note', 'Error pasarela: ' . $payment->get_error_message() );

				return array(
					'id'            => $post_id,
					'nombre'        => $nombre,
					'gateway_error' => true,
					'error_message' => __( 'No se ha podido contactar con la pasarela. Tu registro está guardado pero el pago está pendiente.', 'convoca-members' ),
					'estado'        => $estado,
				);
			}
		}

		return array(
			'id'       => $post_id,
			'nombre'   => $nombre,
			'estado'   => $estado,
			'redirect' => add_query_arg( 'members_success', '1', home_url() ),
		);
	}

	/**
	 * Validate registration data.
	 */
	public static function validate( array $data, array $files = array() ): bool|\WP_Error {
		$errors = array();

		if ( empty( $data['nombre'] ) ) {
			$errors[] = 'El nombre es obligatorio.';
		}
		if ( empty( $data['dni'] ) ) {
			$errors[] = 'El DNI/NIE es obligatorio.';
		}
		if ( empty( $data['email'] ) ) {
			$errors[] = 'El email es obligatorio.';
		}
		if ( ! is_email( $data['email'] ?? '' ) ) {
			$errors[] = 'El email no es válido.';
		}

		// Validate fecha_nacimiento: not empty, valid date, not in the future.
		$fecha_nac = $data['fecha_nacimiento'] ?? '';
		if ( empty( $fecha_nac ) ) {
			$errors[] = 'La fecha de nacimiento es obligatoria.';
		} else {
			$dob = \DateTime::createFromFormat( 'Y-m-d', $fecha_nac );
			if ( ! $dob ) {
				$errors[] = 'La fecha de nacimiento tiene un formato inválido.';
			} elseif ( $dob > new \DateTime() ) {
				$errors[] = 'La fecha de nacimiento no puede ser futura.';
			} elseif ( $dob < new \DateTime( '1900-01-01' ) ) {
				$errors[] = 'La fecha de nacimiento no es válida (mínimo año 1900).';
			} else {
				// Minimum age validation (Task 45).
				$today    = new \DateTime();
				$age      = $today->diff( $dob )->y;
				$settings = get_option( 'convoca_members_settings', array() );
				$min_age  = (int) ( $settings['min_age'] ?? 0 );

				if ( $age < $min_age ) {
					$errors[] = sprintf( 'Lo sentimos, debes tener al menos %d años para registrarte (tienes %d).', $min_age, $age );
				}
			}
		}

		// Validate telefono: not empty and reasonable format.
		$telefono = $data['telefono'] ?? '';
		if ( empty( $telefono ) ) {
			$errors[] = 'El teléfono es obligatorio.';
		} elseif ( ! preg_match( '/^[6-9]\d{8}$/', preg_replace( '/\s+/', '', $telefono ) ) ) {
			$errors[] = 'El teléfono debe ser un número español válido (9 dígitos, mulaiing con 6-9).';
		}

		if ( empty( $data['direccion'] ) ) {
			$errors[] = 'La dirección es obligatoria.';
		}
		if ( empty( $data['municipio'] ) ) {
			$errors[] = 'El municipio es obligatorio.';
		}
		if ( empty( $data['plan'] ) ) {
			$errors[] = 'Debes seleccionar un plan de membresía.';
		}
		if ( empty( $data['forma_pago'] ) ) {
			$errors[] = 'Debes seleccionar una forma de pago.';
		}
		if ( empty( $data['rgpd'] ) ) {
			$errors[] = 'Debes aceptar la política de privacidad.';
		}

		// DNI Checksum.
		$dni = strtoupper( trim( $data['dni'] ?? '' ) );
		if ( $dni && ! Utils::validar_dni( $dni ) ) {
			$errors[] = 'El formato del DNI/NIE no es correcto.';
		}

		// Juvenile check: age must be <= 30.
		$plan      = $data['plan'] ?? '';
		$sub_plan  = $data['sub_plan'] ?? '';
		$plan_key  = ( in_array( $plan, array( 'familiar', 'juvenil' ), true ) && $sub_plan ) ? $sub_plan : $plan;
		$plan_data = CPT_Miembro::get_plan( $plan_key );

		if ( $plan_data && ! empty( $fecha_nac ) ) {
			// Check if it's a juvenile modality or the plan name implies it.
			$is_juvenil = ( isset( $plan_data['modalidad'] ) && $plan_data['modalidad'] === 'Juvenil' ) || $plan === 'juvenil';

			if ( $is_juvenil ) {
				$dob   = new \DateTime( $fecha_nac );
				$today = new \DateTime();
				$age   = $today->diff( $dob )->y;

				if ( $age > 30 ) {
					$errors[] = sprintf(
						'La modalidad Juvenil está reservada para menores de 30 años (tienes %d años). Por favor, selecciona una modalidad de socio ordinario u otra que se ajuste a tu perfil.',
						$age
					);
				}
			}
		}

		// No longer required here as it's handled in the gateway instructions page.

		if ( ! empty( $errors ) ) {
			return new \WP_Error( 'validation_error', implode( ' ', $errors ) );
		}

		return true;
	}
}
