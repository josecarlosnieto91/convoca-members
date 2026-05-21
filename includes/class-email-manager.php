<?php
/**
 * Email template system with dynamic variables.
 *
 * Templates stored in wp_options, editable via admin.
 * Uses wp_mail() — SMTP configured externally.
 * All emails use the premium Biodevas HTML layout
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
	private const OPTION = 'bdv_email_templates';

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
	);

	public function __construct() {
		// New standard hooks.
		add_action( 'convoca_members_email_solicitud', array( $this, 'send_solicitud' ) );
		add_action( 'convoca_members_email_bienvenida', array( $this, 'send_bienvenida' ) );
		add_action( 'convoca_members_email_recordatorio_pago', array( $this, 'send_recordatorio_pago' ), 10, 2 );
		add_action( 'convoca_members_email_renovacion', array( $this, 'send_renovacion' ) );
		add_action( 'convoca_members_email_renovacion_automatica', array( $this, 'send_renovacion_automatica' ), 10, 2 );
		add_action( 'convoca_members_email_renovacion_completada', array( $this, 'send_renovacion_completada' ) );
		add_action( 'convoca_members_email_objetivo_voluntariado', array( $this, 'send_objetivo_voluntariado' ) );

		// Legacy hooks removed. The do_action() call in Process_Member and.
		// other dispatchers fires both old and new hooks via Utils::do_action().
		// Keeping both add_action() and do_action() would trigger deprecation notices.
		// Use the new hook names (biodevas_members_*) going forward.
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
				'subject' => 'Bienvenido/a a Biodevas — Tus credenciales de acceso',
				'body'    => '<h1>¡Bienvenido/a, {nombre}!</h1>'
					. '<p>Tu solicitud ha sido aprobada. Ya puedes acceder a tu área privada de socio/a.</p>'
					. Email_Layout::meta_table(
						array(
							array(
								'label' => 'Usuario',
								'value' => '{usuario}',
							),
							array(
								'label' => 'Contraseña',
								'value' => '{password}',
							),
						)
					)
					. '<p style="color:#dc2626;font-size:0.9rem;">⚠️ Por seguridad, cambia tu contraseña después del primer inicio de sesión.</p>'
					. Email_Layout::button_html( '{login_url}', 'Acceder a Mi Área' )
					. '<p>Si tienes cualquier problema, responde a este email o escribe a coordinacion@biodevas.org.</p>',
			),
			'solicitud_recibida'               => array(
				'subject' => 'Hemos recibido tu solicitud — Biodevas',
				'body'    => '<h1>Hola {nombre},</h1>'
					. '<p>Hemos recibido correctamente tu solicitud como <strong>{tipo_miembro}</strong> en Biodevas.</p>'
					. Email_Layout::meta_table(
						array(
							array(
								'label' => 'Plan',
								'value' => '{plan}',
							),
							array(
								'label' => 'Cuota',
								'value' => '{cuota}',
							),
							array(
								'label' => 'Estado',
								'value' => '{estado}',
							),
						)
					)
					. '<p>Un miembro del equipo revisará tu solicitud y te contactaremos pronto.</p>'
					. '<p>¡Gracias por unirte a la familia Biodevas!</p>',
			),
			'bienvenida'                       => array(
				'subject' => '¡Bienvenido/a a Biodevas, {nombre}!',
				'body'    => '<h1>¡Bienvenido/a a Biodevas, {nombre}! 🎉</h1>'
					. '<p>Tu alta como <strong>{tipo_miembro}</strong> ha sido confirmada.</p>'
					. Email_Layout::meta_table(
						array(
							array(
								'label' => 'Nº Socio',
								'value' => '{numero_socio}',
							),
							array(
								'label' => 'Plan',
								'value' => '{plan}',
							),
							array(
								'label' => 'Estado',
								'value' => '{estado}',
							),
						)
					)
					. '<p>Ya formas parte de la comunidad Biodevas. Puedes participar en todas nuestras actividades y proyectos.</p>'
					. '<p>Si tienes cualquier duda, escríbenos a <a href="mailto:coordinacion@biodevas.org">coordinacion@biodevas.org</a>.</p>'
					. '<p>¡Nos vemos en el campo! 🌿</p>',
			),
			'recordatorio_pago'                => array(
				'subject' => 'Recordatorio de pago (1/3) — Biodevas',
				'body'    => '<h1>Hola {nombre},</h1>'
					. '<p>Tu solicitud de alta/renovación como <strong>{tipo_miembro}</strong> está pendiente de pago.</p>'
					. Email_Layout::meta_table(
						array(
							array(
								'label' => 'Plan',
								'value' => '{plan}',
							),
							array(
								'label' => 'Importe',
								'value' => '{importe}€',
							),
						)
					)
					. '<p>Puedes realizar el pago de forma segura desde tu panel de socio.</p>'
					. '<p>Si ya has realizado el pago, ignora este mensaje.</p>',
			),
			'pago_pendiente_2'                 => array(
				'subject' => 'Segundo aviso: pago pendiente — Biodevas',
				'body'    => '<h1>Hola {nombre},</h1>'
					. '<p>Seguimos sin constancia del pago de tu cuota de <strong>{tipo_miembro}</strong>.</p>'
					. Email_Layout::meta_table(
						array(
							array(
								'label' => 'Importe',
								'value' => '{importe}€',
							),
						)
					)
					. '<p>Es importante completar el pago para mantener tu estado activo y acceder a las ventajas de socio.</p>',
			),
			'pago_pendiente_ultimo'            => array(
				'subject' => 'Último aviso: suspensión inminente — Biodevas',
				'body'    => '<h1>Hola {nombre},</h1>'
					. '<p>Este es el <strong>último aviso</strong> respecto a tu cuota pendiente de <strong>{tipo_miembro}</strong>.</p>'
					. Email_Layout::meta_table(
						array(
							array(
								'label' => 'Importe',
								'value' => '{importe}€',
							),
						)
					)
					. '<p>Si no recibimos el pago en los próximos días, tu cuenta será suspendida automáticamente.</p>',
			),
			'renovacion'                       => array(
				'subject' => 'Tu renovación en Biodevas (30 días) — Biodevs',
				'body'    => '<h1>Hola {nombre},</h1>'
					. '<p>Faltan <strong>30 días</strong> para tu fecha de renovación como {tipo_miembro}.</p>'
					. Email_Layout::meta_table(
						array(
							array(
								'label' => 'Plan actual',
								'value' => '{plan}',
							),
							array(
								'label' => 'Cuota',
								'value' => '{cuota}',
							),
							array(
								'label' => 'Fecha renovación',
								'value' => '{fecha_renovacion}',
							),
						)
					)
					. '<p>Puedes renovar desde tu panel de socio.</p>'
					. '<p>¡Gracias por seguir con nosotros!</p>',
			),
			'renovacion_15d'                   => array(
				'subject' => 'Tu renovación en Biodevas (15 días) — Biodevas',
				'body'    => '<h1>Hola {nombre},</h1>'
					. '<p>Faltan solo <strong>15 días</strong> para que venza tu membresía de {tipo_miembro}.</p>'
					. '<p>Recuerda renovar para no perder tu antigüedad y beneficios.</p>',
			),
			'renovacion_7d'                    => array(
				'subject' => 'Última semana para tu renovación — Biodevas',
				'body'    => '<h1>Hola {nombre},</h1>'
					. '<p>Tu membresía de {tipo_miembro} vencerá en <strong>7 días</strong>.</p>'
					. '<p>Evita la suspensión automática realizando el pago desde tu panel de socio.</p>',
			),
			'renovacion_automatica'            => array(
				'subject' => 'Procesando tu renovación automática — Biodevas',
				'body'    => '<h1>Hola {nombre},</h1>'
					. '<p>Como tienes activada la renovación automática, hemos generado el cargo correspondiente a tu cuota de {tipo_miembro}.</p>'
					. Email_Layout::meta_table(
						array(
							array(
								'label' => 'Plan',
								'value' => '{plan}',
							),
							array(
								'label' => 'Importe',
								'value' => '{importe}€',
							),
							array(
								'label' => 'Próxima renovación',
								'value' => '{fecha_renovacion}',
							),
						)
					)
					. '<p>No tienes que hacer nada. Si hay algún problema con el cargo, te avisaremos.</p>'
					. '<p>¡Gracias por seguir apoyando a Biodevas!</p>',
			),
			'renovacion_completada'            => array(
				'subject' => 'Renovación completada con éxito — Biodevas',
				'body'    => '<h1>¡Buenas noticias, {nombre}! 🎉</h1>'
					. '<p>El pago de tu cuota anual como <strong>{tipo_miembro}</strong> se ha procesado correctamente.</p>'
					. Email_Layout::meta_table(
						array(
							array(
								'label' => 'Nueva fecha renovación',
								'value' => '{fecha_renovacion}',
							),
						)
					)
					. '<p>Adjunto a este email encontrarás tu tarjeta de socio/a actualizada.</p>'
					. '<p>¡Gracias por tu compromiso con la naturaleza! 🌍</p>',
			),
			'voluntariado_recordatorio'        => array(
				'subject' => 'Recuerda tus horas de voluntariado — Biodevas',
				'body'    => '<h1>Hola {nombre},</h1>'
					. '<p>Te escribimos para recordarte tu compromiso de voluntariado con Biodevas.</p>'
					. Email_Layout::meta_table(
						array(
							array(
								'label' => 'Horas registradas',
								'value' => '{horas_actuales}h',
							),
							array(
								'label' => 'Objetivo anual',
								'value' => '{horas_objetivo}h',
							),
							array(
								'label' => 'Progreso',
								'value' => '{porcentaje_cumplimiento}%',
							),
						)
					)
					. '<p>Si tienes horas pendientes de registrar, puedes hacerlo desde tu <a href="' . home_url( '/mi-area/' ) . '">panel de socio</a>.</p>'
					. '<p>¡Tus manos son fundamentales para la asociación! 🌱</p>',
			),
			'objetivo_voluntariado_completado' => array(
				'subject' => '🎉 ¡Felicidades! Has completado tu voluntariado — Biodevas',
				'body'    => '<h1>¡Enhorabuena, {nombre}! 🎉</h1>'
					. '<p>Has completado las <strong>{horas_totales}h</strong> de voluntariado requeridas para el plan <strong>{plan_nombre}</strong>.</p>'
					. Email_Layout::meta_table(
						array(
							array(
								'label' => 'Horas completadas',
								'value' => '{horas_totales}h',
							),
							array(
								'label' => 'Plan',
								'value' => '{plan_nombre}',
							),
							array(
								'label' => 'Proyectos',
								'value' => '{proyectos_participados}',
							),
						)
					)
					. '<p>Ya puedes descargar tu certificado oficial de voluntariado:</p>'
					. Email_Layout::button_html( '{certificado_url_verificacion}', 'Descargar Certificado' )
					. '<p style="font-size:13px;color:#64748b;margin-top:8px">ID del certificado: {certificado_id}</p>'
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

	private function send( string $template_slug, int $post_id, array $extra_vars = array() ): void {
		$templates = get_option( self::OPTION, array() );
		$tpl       = $templates[ $template_slug ] ?? null;

		if ( ! $tpl ) {
			return;
		}

		$email = get_post_meta( $post_id, '_bdv_email', true ) ?: get_the_author_meta( 'user_email', get_post_field( 'post_author', $post_id ) );
		if ( empty( $email ) ) {
			\Convoca\Core\Logger::warning( "Email vacío para miembro #{$post_id}, no se envía {$template_slug}.", 'Members/Emails' );
			return;
		}
		if ( ! is_email( $email ) ) {
			\Convoca\Core\Logger::warning( "Email inválido ({$email}) para miembro #{$post_id}, no se envía {$template_slug}.", 'Members/Emails' );
			return;
		}

		// Dedup: prevent sending the same email template to the same member within 5 minutes.
		$dedup_key = '_bdv_last_email_sent_' . $template_slug;
		$last_sent = (int) get_post_meta( $post_id, $dedup_key, true );
		if ( $last_sent && ( time() - $last_sent ) < 300 ) {
			\Convoca\Core\Logger::info( "Email $template_slug ya enviado recientemente a miembro #$post_id, omitiendo.", 'Members/Emails' );
			return;
		}

		$vars = array_merge( $this->build_variables( $post_id ), $extra_vars );

		$subject = $this->replace_vars( $tpl['subject'], $vars );
		$body    = $this->replace_vars( $tpl['body'] ?? '', $vars );

		// Wrap in premium Biodevas HTML layout (always HTML now).
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		// Get Sender Name from settings.
		$settings     = get_option( 'bdv_members_settings', array() );
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

		$sent = wp_mail( $email, $subject, $body, $headers );

		if ( $sent ) {
			// Update tracking and Audit Log.
			$now = current_time( 'mysql' );
			update_post_meta( $post_id, '_bdv_ultimo_contacto_email', $now );
			update_post_meta( $post_id, '_bdv_ultimo_contacto', $now );
			update_post_meta( $post_id, $dedup_key, time() );

			\Convoca\Core\Logger::info(
				sprintf( __( 'Email enviado: %s', 'convoca-members' ), $subject ),
				'Members/Emails',
				$post_id
			);
		} else {
			\Convoca\Core\Logger::error(
				sprintf( __( 'Error al enviar email: %s', 'convoca-members' ), $subject ),
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
				'[Biodevas] ' . $subject,
				"Notificación automática — Miembro: {$vars['{nombre}']}\n\n" . $body,
				$admin_headers
			);
		}
	}

	/**
	 * Build variable replacements from post meta.
	 */
	private function build_variables( int $post_id ): array {
		$meta = fn( string $key ) => get_post_meta( $post_id, '_bdv_' . $key, true );

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
		$estado_label = Estados::LABELS[ $estado ] ?? $estado;

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
						'key'   => '_bdv_miembro_id',
						'value' => $post_id,
					),
					array(
						'key'   => '_bdv_estado',
						'value' => 'aprobada',
					),
				),
			)
		);
		if ( ! empty( $proyecto_ids ) ) {
			$nombres_proyecto = array();
			foreach ( $proyecto_ids as $pid ) {
				$proy_id = get_post_meta( $pid, '_bdv_proyecto_id', true );
				if ( $proy_id && ( $titulo = get_the_title( $proy_id ) ) ) {
					$nombres_proyecto[ $proy_id ] = $titulo;
				}
			}
			$proyectos = implode( ', ', array_unique( $nombres_proyecto ) ) ?: '—';
		}

		$certificado_id = $meta( 'certificado_id' ) ?: '—';

		return array(
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
