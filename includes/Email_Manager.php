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
 * Email template system with dynamic variables.
 *
 * Templates stored in wp_options, editable via admin.
 * Uses wp_mail() — SMTP configured externally.
 * All emails use the premium Convoca HTML layout
 * (orange #FF8700 + violet #320028) from Common\Email_Layout.
 *
 * @package Convoca\Members
 */

namespace Convoca\Members;

use Convoca\Core\Email_Layout;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Email_Manager {


	/** Option key for templates. */
	private const OPTION = 'convoca_email_templates';

	/** Available template slugs. */
	public const TEMPLATES = array(
		'solicitud_recibida',
		'bienvenida',
		'credenciales_acceso', // Enviado tras aprobación con usuario/contraseña.
		'recordatorio_pago',
		'pago_pendiente_2',
		'pago_pendiente_ultimo',
		'renovacion', // 30d
		'renovacion_15d',
		'renovacion_7d',
		'renovacion_automatica',
		'renovacion_completada',
		'voluntariado_recordatorio',
		'objetivo_voluntariado_completado',
	);

	/** Available variables for templates. */
	public const VARIABLES = array(
		'{nombre}',
		'{email}',
		'{tipo_miembro}',
		'{plan}',
		'{cuota}',
		'{importe}',
		'{estado}',
		'{numero_socio}',
		'{fecha_alta}',
		'{fecha_renovacion}',
		'{fecha_baja}',
		'{link_pago}',
		'{horas_actuales}',
		'{horas_objetivo}',
		'{porcentaje_cumplimiento}',
		'{horas_totales}',
		'{plan_nombre}',
		'{proyectos_participados}',
		'{certificado_id}',
		'{certificado_url_verificacion}',
		'{usuario}',
		'{password}',
		'{login_url}',
		'{admin_email}',
		'{link_confirmacion}',
		'{nuevo_email}',
		'{telefono}',
	);

	public function __construct() {
		// New standard hooks.
		add_action( 'convoca_members_email_solicitud', array( $this, 'send_solicitud' ) );
		add_action( 'convoca_members_email_bienvenida', array( $this, 'send_bienvenida' ) );
		add_action( 'convoca_members_email_recordatorio_pago', array( $this, 'send_recordatorio_pago' ), 10, 2 );
		add_action( 'convoca_members_email_renovacion', array( $this, 'send_renovacion' ) );
		add_action( 'convoca_members_email_renovacion_automatica', array( $this, 'send_renovacion_automatica' ), 10, 2 );
		add_action( 'convoca_members_email_renovacion_completada', array( $this, 'send_renovacion_completada' ) );
		add_action( 'convoca_members_email_confirm', array( $this, 'send_confirm' ), 10, 3 );
		add_action( 'convoca_members_email_verify_phone', array( $this, 'send_verify_phone' ), 10, 3 );
		add_action( 'convoca_members_email_objetivo_voluntariado', array( $this, 'send_objetivo_voluntariado' ) );

		// Legacy hooks removed. The do_action() call in Process_Member and.
		// other dispatchers fires both old and new hooks via Utils::do_action().
		// Keeping both add_action() and do_action() would trigger deprecation notices.
		// Use the new hook names (convoca_members_*) going forward.
		add_action( 'convoca_email_objetivo_voluntariado', array( $this, 'send_objetivo_voluntariado' ) );

		// Credentials hook (called by Process_Member::handle_approved).
		add_action( 'convoca_members_email_credenciales', array( $this, 'send_credenciales' ), 10, 2 );
	}

	/* ── Default templates (installed on activation) ───── */

	public static function install_defaults(): void {
		if ( false !== get_option( self::OPTION ) ) {
			return;
		}

		$defaults = array(
			'credenciales_acceso'              => array(
				'subject' => 'Bienvenido/a a ' . get_bloginfo( 'name' ) . ' — Tus credenciales de acceso',
				'body'    => __( '<h1>¡Bienvenido/a, {nombre}!</h1>', 'convoca-members' )
					. __( '<p>Tu solicitud ha sido aprobada. Ya puedes acceder a tu área privada de socio/a.</p>', 'convoca-members' )
					. Email_Layout::meta_table(
						array(
							array(
								'label' => __( 'Usuario', 'convoca-members' ),
								'value' => '{usuario}',
							),
							array(
								'label' => __( 'Contraseña', 'convoca-members' ),
								'value' => '{password}',
							),
						)
					)
					. __( '<p style="color:#dc2626;font-size:0.9rem;">⚠️ Por seguridad, cambia tu contraseña después del primer inicio de sesión.</p>', 'convoca-members' )
					. Email_Layout::button_html( '{login_url}', __( 'Acceder a Mi Área', 'convoca-members' ) )
					. '<p>Si tienes cualquier problema, responde a este email o escribe a <a href="mailto:{admin_email}">{admin_email}</a>.</p>',
			),
			'solicitud_recibida'               => array(
				'subject' => __( 'Hemos recibido tu solicitud — ', 'convoca-members' ) . get_bloginfo( 'name' ),
				'body'    => __( '<h1>Hola {nombre},</h1>', 'convoca-members' )
					. '<p>Hemos recibido correctamente tu solicitud como <strong>{tipo_miembro}</strong> en ' . esc_html( get_bloginfo( 'name' ) ) . '.</p>'
					. Email_Layout::meta_table(
						array(
							array(
								'label' => __( 'Plan', 'convoca-members' ),
								'value' => '{plan}',
							),
							array(
								'label' => __( 'Cuota', 'convoca-members' ),
								'value' => '{cuota}',
							),
							array(
								'label' => __( 'Estado', 'convoca-members' ),
								'value' => '{estado}',
							),
						)
					)
					. __( '<p>Un miembro del equipo revisará tu solicitud y te contactaremos pronto.</p>', 'convoca-members' )
					. '<p>¡Gracias por unirte a la familia Convoca!</p>',
			),
			'bienvenida'                       => array(
				'subject' => '\u00a1Bienvenido/a a ' . get_bloginfo( 'name' ) . ', {nombre}!',
				'body'    => __( '<h1>00a1Bienvenido/a a ', 'convoca-members' ) . esc_html( get_bloginfo( 'name' ) ) . ', {nombre}! 0001F389</H1>'
					. __( '<p>Tu alta como <strong>{tipo_miembro}</strong> ha sido confirmada.</p>', 'convoca-members' )
					. Email_Layout::meta_table(
						array(
							array(
								'label' => __( 'Nº Socio', 'convoca-members' ),
								'value' => '{numero_socio}',
							),
							array(
								'label' => __( 'Plan', 'convoca-members' ),
								'value' => '{plan}',
							),
							array(
								'label' => __( 'Estado', 'convoca-members' ),
								'value' => '{estado}',
							),
						)
					)
					. __( '<p>Ya formas parte de la comunidad Convoca. Puedes participar en todas nuestras actividades y proyectos.</p>', 'convoca-members' )
					. __( '<p>Si tienes cualquier duda, escríbenos a <a href="mailto:{admin_email}">{admin_email}</a>.</p>', 'convoca-members' )
					. '<p>¡Nos vemos en el campo! 🌿</p>',
			),
			'recordatorio_pago'                => array(
				'subject' => __( 'Recordatorio de pago (1/3) — ', 'convoca-members' ) . get_bloginfo( 'name' ),
				'body'    => __( '<h1>Hola {nombre},</h1>', 'convoca-members' )
					. __( '<p>Tu solicitud de alta/renovación como <strong>{tipo_miembro}</strong> está pendiente de pago.</p>', 'convoca-members' )
					. Email_Layout::meta_table(
						array(
							array(
								'label' => __( 'Plan', 'convoca-members' ),
								'value' => '{plan}',
							),
							array(
								'label' => __( 'Importe', 'convoca-members' ),
								'value' => '{importe}€',
							),
						)
					)
					. __( '<p>Puedes realizar el pago de forma segura desde tu panel de socio.</p>', 'convoca-members' )
					. '<p>Si ya has realizado el pago, ignora este mensaje.</p>',
			),
			'pago_pendiente_2'                 => array(
				'subject' => __( 'Segundo aviso: pago pendiente — ', 'convoca-members' ) . get_bloginfo( 'name' ),
				'body'    => __( '<h1>Hola {nombre},</h1>', 'convoca-members' )
					. __( '<p>Seguimos sin constancia del pago de tu cuota de <strong>{tipo_miembro}</strong>.</p>', 'convoca-members' )
					. Email_Layout::meta_table(
						array(
							array(
								'label' => __( 'Importe', 'convoca-members' ),
								'value' => '{importe}€',
							),
						)
					)
					. '<p>Es importante completar el pago para mantener tu estado activo y acceder a las ventajas de socio.</p>',
			),
			'pago_pendiente_ultimo'            => array(
				'subject' => __( 'Último aviso: suspensión inminente — ', 'convoca-members' ) . get_bloginfo( 'name' ),
				'body'    => __( '<h1>Hola {nombre},</h1>', 'convoca-members' )
					. __( '<p>Este es el <strong>último aviso</strong> respecto a tu cuota pendiente de <strong>{tipo_miembro}</strong>.</p>', 'convoca-members' )
					. Email_Layout::meta_table(
						array(
							array(
								'label' => __( 'Importe', 'convoca-members' ),
								'value' => '{importe}€',
							),
						)
					)
					. '<p>Si no recibimos el pago en los próximos días, tu cuenta será suspendida automáticamente.</p>',
			),
			'renovacion'                       => array(
				'subject' => 'Tu renovación en ' . get_bloginfo( 'name' ) . ' (30 días)',
				'body'    => __( '<h1>Hola {nombre},</h1>', 'convoca-members' )
					. __( '<p>Faltan <strong>30 días</strong> para tu fecha de renovación como {tipo_miembro}.</p>', 'convoca-members' )
					. Email_Layout::meta_table(
						array(
							array(
								'label' => __( 'Plan actual', 'convoca-members' ),
								'value' => '{plan}',
							),
							array(
								'label' => __( 'Cuota', 'convoca-members' ),
								'value' => '{cuota}',
							),
							array(
								'label' => __( 'Fecha renovación', 'convoca-members' ),
								'value' => '{fecha_renovacion}',
							),
						)
					)
					. __( '<p>Puedes renovar desde tu panel de socio.</p>', 'convoca-members' )
					. '<p>¡Gracias por seguir con nosotros!</p>',
			),
			'renovacion_15d'                   => array(
				'subject' => 'Tu renovación en ' . get_bloginfo( 'name' ) . ' (15 días)',
				'body'    => __( '<h1>Hola {nombre},</h1>', 'convoca-members' )
					. __( '<p>Faltan solo <strong>15 días</strong> para que venza tu membresía de {tipo_miembro}.</p>', 'convoca-members' )
					. '<p>Recuerda renovar para no perder tu antigüedad y beneficios.</p>',
			),
			'renovacion_7d'                    => array(
				'subject' => __( 'Última semana para tu renovación — ', 'convoca-members' ) . get_bloginfo( 'name' ),
				'body'    => __( '<h1>Hola {nombre},</h1>', 'convoca-members' )
					. __( '<p>Tu membresía de {tipo_miembro} vencerá en <strong>7 días</strong>.</p>', 'convoca-members' )
					. '<p>Evita la suspensión automática realizando el pago desde tu panel de socio.</p>',
			),
			'renovacion_automatica'            => array(
				'subject' => __( 'Procesando tu renovación automática — ', 'convoca-members' ) . get_bloginfo( 'name' ),
				'body'    => __( '<h1>Hola {nombre},</h1>', 'convoca-members' )
					. __( '<p>Como tienes activada la renovación automática, hemos generado el cargo correspondiente a tu cuota de {tipo_miembro}.</p>', 'convoca-members' )
					. Email_Layout::meta_table(
						array(
							array(
								'label' => __( 'Plan', 'convoca-members' ),
								'value' => '{plan}',
							),
							array(
								'label' => __( 'Importe', 'convoca-members' ),
								'value' => '{importe}€',
							),
							array(
								'label' => __( 'Próxima renovación', 'convoca-members' ),
								'value' => '{fecha_renovacion}',
							),
						)
					)
					. __( '<p>No tienes que hacer nada. Si hay algún problema con el cargo, te avisaremos.</p>', 'convoca-members' )
					. '<p>¡Gracias por seguir apoyando a Convoca!</p>',
			),
			'renovacion_completada'            => array(
				'subject' => __( 'Renovación completada con éxito — ', 'convoca-members' ) . get_bloginfo( 'name' ),
				'body'    => __( '<h1>¡Buenas noticias, {nombre}! 🎉</h1>', 'convoca-members' )
					. __( '<p>El pago de tu cuota anual como <strong>{tipo_miembro}</strong> se ha procesado correctamente.</p>', 'convoca-members' )
					. Email_Layout::meta_table(
						array(
							array(
								'label' => __( 'Nueva fecha renovación', 'convoca-members' ),
								'value' => '{fecha_renovacion}',
							),
						)
					)
					. __( '<p>Adjunto a este email encontrarás tu tarjeta de socio/a actualizada.</p>', 'convoca-members' )
					. '<p>¡Gracias por tu compromiso con la naturaleza! 🌍</p>',
			),
			'confirm_email'                    => array(
				'subject' => __( 'Confirma tu nuevo email — ', 'convoca-members' ) . get_bloginfo( 'name' ),
				'body'    => __( '<h1>Hola {nombre},</h1>', 'convoca-members' )
					. __( '<p>Has solicitado cambiar tu email de contacto a <strong>{nuevo_email}</strong>.</p>', 'convoca-members' )
					. __( '<p>Para confirmar el cambio, haz clic en el siguiente enlace (válido por 24 horas):</p>', 'convoca-members' )
					. '<p style="text-align:center;margin:24px 0;"><a href="{link_confirmacion}" style="background:#FF8700;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:bold;">' . esc_html__( 'Confirmar email', 'convoca-members' ) . '</a></p>'
					. __( '<p>Si no has solicitado este cambio, ignora este mensaje. Tu email actual seguirá activo.</p>', 'convoca-members' ),
			),
			'verify_phone'                     => array(
				'subject' => __( 'Verifica tu teléfono — ', 'convoca-members' ) . get_bloginfo( 'name' ),
				'body'    => __( '<h1>Hola {nombre},</h1>', 'convoca-members' )
					. __( '<p>Has solicitado verificar tu número de teléfono <strong>{telefono}</strong>.</p>', 'convoca-members' )
					. __( '<p>Para confirmar que es tu número, haz clic en el siguiente enlace (válido por 24 horas):</p>', 'convoca-members' )
					. '<p style="text-align:center;margin:24px 0;"><a href="{link_confirmacion}" style="background:#FF8700;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:bold;">' . esc_html__( 'Verificar teléfono', 'convoca-members' ) . '</a></p>'
					. __( '<p>Si no has solicitado esta verificación, ignora este mensaje.</p>', 'convoca-members' ),
			),
			'voluntariado_recordatorio'        => array(
				'subject' => __( 'Recuerda tus horas de voluntariado — ', 'convoca-members' ) . get_bloginfo( 'name' ),
				'body'    => __( '<h1>Hola {nombre},</h1>', 'convoca-members' )
					. '<p>Te escribimos para recordarte tu compromiso de voluntariado con ' . esc_html( get_bloginfo( 'name' ) ) . '.</p>'
					. Email_Layout::meta_table(
						array(
							array(
								'label' => __( 'Horas registradas', 'convoca-members' ),
								'value' => '{horas_actuales}h',
							),
							array(
								'label' => __( 'Objetivo anual', 'convoca-members' ),
								'value' => '{horas_objetivo}h',
							),
							array(
								'label' => __( 'Progreso', 'convoca-members' ),
								'value' => '{porcentaje_cumplimiento}%',
							),
						)
					)
					. '<p>Si tienes horas pendientes de registrar, puedes hacerlo desde tu <a href="' . home_url( '/mi-area/' ) . '">panel de socio</a>.</p>'
					. '<p>¡Tus manos son fundamentales para la asociación! 🌱</p>',
			),
			'objetivo_voluntariado_completado' => array(
				'subject' => '\U0001f389 ¡Felicidades! Has completado tu voluntariado — ' . get_bloginfo( 'name' ),
				'body'    => __( '<h1>¡Enhorabuena, {nombre}! 🎉</h1>', 'convoca-members' )
					. __( '<p>Has completado las <strong>{horas_totales}h</strong> de voluntariado requeridas para el plan <strong>{plan_nombre}</strong>.</p>', 'convoca-members' )
					. Email_Layout::meta_table(
						array(
							array(
								'label' => __( 'Horas completadas', 'convoca-members' ),
								'value' => '{horas_totales}h',
							),
							array(
								'label' => __( 'Plan', 'convoca-members' ),
								'value' => '{plan_nombre}',
							),
							array(
								'label' => __( 'Proyectos', 'convoca-members' ),
								'value' => '{proyectos_participados}',
							),
						)
					)
					. __( '<p>Ya puedes descargar tu certificado oficial de voluntariado:</p>', 'convoca-members' )
					. Email_Layout::button_html( '{certificado_url_verificacion}', __( 'Descargar Certificado', 'convoca-members' ) )
					. __( '<p style="font-size:13px;color:#64748b;margin-top:8px">ID del certificado: {certificado_id}</p>', 'convoca-members' )
					. '<p>¡Gracias por tu dedicación y apoyo a la naturaleza! 🌍</p>',
			),
		);

		update_option( self::OPTION, $defaults );
	}

	/* ── Send methods ──────────────────────────────────── */

	public function send_solicitud( int $post_id ): void {
		$this->send( 'solicitud_recibida', $post_id );
	}

	public function send_bienvenida( int $post_id ): void {
		$this->send( 'bienvenida', $post_id );
	}

	/**
	 * Send payment reminder.
	 *
	 * @param int   $post_id Member ID.
	 * @param array $args Optional args (link_pago).
	 */
	public function send_recordatorio_pago( int $post_id, array $args = array() ): void {
		$this->send( 'recordatorio_pago', $post_id, $args );
	}

	public function send_renovacion( int $post_id, array $args = array() ): void {
		$this->send( 'renovacion', $post_id, $args );
	}

	public function send_renovacion_automatica( int $post_id, array $args = array() ): void {
		$this->send( 'renovacion_automatica', $post_id, $args );
	}

	public function send_renovacion_completada( int $post_id ): void {
		$this->send( 'renovacion_completada', $post_id );
	}

	/**
	 * Send email confirmation link.
	 *
	 * @param int    $post_id      Member ID.
	 * @param string $confirm_url  Confirmation link.
	 * @param string $nuevo_email  Pending new email.
	 */
	public function send_confirm( int $post_id, string $confirm_url, string $nuevo_email ): void {
		$this->send(
			'confirm_email',
			$post_id,
			array(
				'{link_confirmacion}' => $confirm_url,
				'{nuevo_email}'       => $nuevo_email,
			),
			$nuevo_email
		);
	}

	/**
	 * Send phone verification link.
	 *
	 * @param int    $post_id      Member ID.
	 * @param string $confirm_url  Confirmation link.
	 * @param string $telefono     Phone being verified.
	 */
	public function send_verify_phone( int $post_id, string $confirm_url, string $telefono ): void {
		$this->send(
			'verify_phone',
			$post_id,
			array(
				'{link_confirmacion}' => $confirm_url,
				'{telefono}'          => $telefono,
			)
		);
	}

	public function send_objetivo_voluntariado( int $post_id ): void {
		$this->send( 'objetivo_voluntariado_completado', $post_id );
	}

	/**
	 * Send credentials email (username + password) after approval.
	 * The extra vars are provided by Process_Member.
	 */
	public function send_credenciales( int $post_id, array $extra_vars = array() ): void {
		$this->send( 'credenciales_acceso', $post_id, $extra_vars );
	}

	/* ── Core send logic ───────────────────────────────── */

	private function send( string $template_slug, int $post_id, array $extra_vars = array(), string $to_email = '' ): void {
		$templates = get_option( self::OPTION, array() );
		$tpl       = $templates[ $template_slug ] ?? null;

		if ( ! $tpl ) {
			return;
		}

		$email = $to_email ?: ( get_post_meta( $post_id, '_convoca_email', true ) ?: get_the_author_meta( 'user_email', get_post_field( 'post_author', $post_id ) ) );
		if ( empty( $email ) ) {
			\Convoca\Core\Logger::warning( "Email vacío para miembro #{$post_id}, no se envía {$template_slug}.", 'Members/Emails' );
			return;
		}
		if ( ! is_email( $email ) ) {
			\Convoca\Core\Logger::warning( "Email inválido ({$email}) para miembro #{$post_id}, no se envía {$template_slug}.", 'Members/Emails' );
			return;
		}

		// Dedup: prevent sending the same email template to the same member within 5 minutes.
		$dedup_key = '_convoca_last_email_sent_' . $template_slug;
		$last_sent = (int) get_post_meta( $post_id, $dedup_key, true );
		if ( $last_sent && ( time() - $last_sent ) < 300 ) {
			\Convoca\Core\Logger::info( "Email $template_slug ya enviado recientemente a miembro #$post_id, omitiendo.", 'Members/Emails' );
			return;
		}

		$vars = array_merge( $this->build_variables( $post_id, $extra_vars ), $extra_vars );

		$subject = $this->replace_vars( $tpl['subject'], $vars );
		$body    = $this->replace_vars( $tpl['body'] ?? '', $vars );

		// Wrap in premium Convoca HTML layout (always HTML now).
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		// Get Sender Name from settings.
		$settings     = get_option( 'convoca_members_settings', array() );
		$sender_name  = $settings['sender_name'] ?? get_bloginfo( 'name' );
		$system_email = $settings['system_email'] ?? get_option( 'admin_email' );

		$headers[] = 'From: ' . $sender_name . ' <' . $system_email . '>';

		// Add Reply-To with user email if available for better deliverability.
		if ( ! empty( $vars['{email}'] ) && filter_var( $vars['{email}'], FILTER_VALIDATE_EMAIL ) ) {
			$headers[] = 'Reply-To: ' . $vars['{email}'];
		}

		$body = Email_Layout::render(
			$body,
			$subject,
			array(
				'footer_text' => sprintf(
					/* translators: %s: site name */
					__( 'Has recibido este email porque eres miembro de %s.', 'convoca-members' ),
					get_bloginfo( 'name' )
				),
			)
		);

		$sent = \Convoca\Members\Email_Verifier::send( $email, $subject, $body, $headers );

		if ( $sent ) {
			// Update tracking and Audit Log.
			$now = current_time( 'mysql' );
			update_post_meta( $post_id, '_convoca_ultimo_contacto_email', $now );
			update_post_meta( $post_id, '_convoca_ultimo_contacto', $now );
			update_post_meta( $post_id, $dedup_key, time() );

			\Convoca\Core\Logger::info(
				sprintf( /* translators: %s: email subject */ __( 'Email enviado: %s', 'convoca-members' ), $subject ),
				'Members/Emails',
				$post_id
			);
		} else {
			\Convoca\Core\Logger::error(
				sprintf( /* translators: %s: email subject */ __( 'Error al enviar email: %s', 'convoca-members' ), $subject ),
				'Members/Emails',
				$post_id
			);
		}

		// Also notify admin (only for specific types or always? User didn't specify, but existing code did).
		// Let's keep it for application/welcome/etc but maybe not general reminders to avoid spam?
		if ( $template_slug === 'solicitud_recibida' ) {
			$admin_headers   = $headers;
			$admin_headers[] = 'From: ' . $sender_name . ' <' . $system_email . '>';
			wp_mail(
				$system_email,
				'[' . esc_html( get_bloginfo( 'name' ) ) . '] ' . $subject,
				"Notificación automática — Miembro: {$vars['{nombre}']}\n\n" . $body,
				$admin_headers
			);
		}
	}

	/**
	 * Build variable replacements from post meta.
	 *
	 * @param int   $post_id    Member post ID.
	 * @param array $extra_vars Vars inyectadas por el caller (credenciales, etc.).
	 */
	private function build_variables( int $post_id, array $extra_vars = array() ): array {
		$meta = fn( string $key ) => get_post_meta( $post_id, '_convoca_' . $key, true );

		// Las credenciales ({usuario}, {password}, {login_url}) se inyectan
		// vía $extra_vars desde send() (array_merge). Aquí solo se definen
		// defaults para cuando no aplican (p. ej. plantillas sin credenciales).
		$extra_vars = (array) $extra_vars;

		$plan_key  = $meta( 'plan' ) ?: $meta( 'sub_plan' );
		$plan_data = CPT_Miembro::get_plan( $plan_key );
		if ( ! $plan_data ) {
			\Convoca\Core\Logger::warning( "Variables de email incompletas: El plan '$plan_key' no existe para el miembro #$post_id", 'Members/Email', $post_id );
		}

		$forma          = $meta( 'forma_pago' );
		$importe_custom = $meta( 'importe_cuota' );

		if ( $forma === 'voluntariado' ) {
			$cuota   = ( $plan_data ? $plan_data['hours'] : 0 ) . 'h voluntariado';
			$importe = 0;
		} else {
			$cuota   = ( $plan_data ? $plan_data['price'] : 0 ) . '€/año';
			$importe = ( $plan_data ? $plan_data['price'] : 0 );
		}

		// Override if custom amount is set and different (legacy).
		if ( $importe_custom && $importe_custom != $importe ) {
			$importe = $importe_custom;
			$cuota   = $importe . '€/año';
		}

		$terms      = wp_get_object_terms( $post_id, 'tipo_miembro', array( 'fields' => 'names' ) );
		$tipo_label = is_array( $terms ) && ! empty( $terms ) ? $terms[0] : '—';

		$estado       = $meta( 'estado_miembro' );
		$estado_label = Estados::labels()[ $estado ] ?? $estado;

		// Voluntariado data (resolved here so all templates can use them).
		$horas_aprobadas = Voluntariado_Manager::get_horas_aprobadas( $post_id );
		$horas_objetivo  = Voluntariado_Manager::get_horas_objetivo( $post_id );
		$porcentaje      = $horas_objetivo > 0 ? min( 100, round( ( $horas_aprobadas / $horas_objetivo ) * 100 ) ) : 0;

		// Projects participated (for certificate email).
		$proyectos    = '';
		$proyecto_ids = get_posts(
			array(
				'post_type'      => 'registro_hora',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => '_convoca_member_id',
						'value' => $post_id,
					),
					array(
						'key'   => '_convoca_estado',
						'value' => 'aprobada',
					),
				),
			)
		);
		if ( ! empty( $proyecto_ids ) ) {
			$nombres_proyecto = array();
			foreach ( $proyecto_ids as $pid ) {
				$proy_id = get_post_meta( $pid, '_convoca_proyecto_id', true );
				if ( $proy_id ) {
					$titulo = get_the_title( $proy_id );
					if ( $titulo ) {
						$nombres_proyecto[ $proy_id ] = $titulo;
					}
				}
			}
			$proyectos = implode( ', ', array_unique( $nombres_proyecto ) ) ?: '—';
		}

		$certificado_id = $meta( 'certificado_id' ) ?: '—';

		return array(
			'{admin_email}'                  => get_bloginfo( 'admin_email' ),
			'{nombre}'                       => get_the_title( $post_id ),
			'{email}'                        => $meta( 'email' ) ?: '—',
			'{tipo_miembro}'                 => $tipo_label,
			'{plan}'                         => $plan_data['label'] ?? ucfirst( $plan_key ?: '—' ),
			'{cuota}'                        => $cuota,
			'{importe}'                      => $importe,
			'{estado}'                       => $estado_label,
			'{numero_socio}'                 => $meta( 'numero_socio' ) ?: '—',
			'{fecha_alta}'                   => get_the_date( 'd/m/Y', $post_id ),
			'{fecha_renovacion}'             => $meta( 'fecha_renovacion' ) ?: '—',
			'{fecha_baja}'                   => $meta( 'fecha_baja' ) ?: '—',
			'{link_pago}'                    => '', // Default empty, usually injected via extra_vars.
			// Voluntariado variables.
			'{horas_actuales}'               => $horas_aprobadas,
			'{horas_objetivo}'               => $horas_objetivo,
			'{porcentaje_cumplimiento}'      => $porcentaje,
			'{horas_totales}'                => $horas_aprobadas,
			'{plan_nombre}'                  => $plan_data['label'] ?? ucfirst( $plan_key ?: '—' ),
			'{proyectos_participados}'       => $proyectos ?: '—',
			'{certificado_id}'               => $certificado_id,
			'{certificado_url_verificacion}' => $certificado_id !== '—'
				? rest_url( 'convoca-members/v1/me/certificate' )
				: '—',
			// Credentials (usually injected via extra_vars, not from meta).
			'{usuario}'                      => $extra_vars['{usuario}'] ?? '—',
			'{password}'                     => $extra_vars['{password}'] ?? '—',
			'{login_url}'                    => $extra_vars['{login_url}'] ?? home_url( '/mi-area/' ),
		);
	}

	/**
	 * Replace template variables.
	 */
	private function replace_vars( string $text, array $vars ): string {
		return str_replace( array_keys( $vars ), array_values( $vars ), $text );
	}

	/* ── Getters / Setters for admin ───────────────────── */

	public static function get_templates(): array {
		return get_option( self::OPTION, array() );
	}

	public static function save_templates( array $templates ): void {
		update_option( self::OPTION, $templates );
	}
}
