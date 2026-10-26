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

	/**
	 * Nombre de la opción donde viven las plantillas. Público para que la migración que las
	 * actualiza no tenga que repetir el literal (y no se desincronice si cambia).
	 *
	 * @return string
	 */
	public static function option_name(): string {
		return self::OPTION;
	}

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
		// El circuito del voluntariado (members#50): cuatro avisos que se pueden editar desde el editor de
		// plantillas de siempre, con sus variables. Los textos de fábrica están abajo.
		'voluntariado_cita',
		'voluntariado_acuerdo',
		'voluntariado_baja_solicitada',
		'voluntariado_desvinculacion',
		'voluntariado_horas_pendientes',
		// Estas dos las envía el propio plugin (cambio de email y verificación de
		// teléfono). Si no están en la lista, el editor de plantillas no las pinta
		// y al guardar las borra de la opción: el correo desaparece en silencio.
		'confirm_email',
		'verify_phone',
	);

	/** Available variables for templates. */
	public const VARIABLES = array(
		'{nombre}',
		'{email}',
		'{tipo_miembro}',
		'{tipo_solicitud}',
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
		// Del circuito del voluntariado (members#50).
		'{fecha_cita}',
		'{hora_cita}',
		'{motivo}',
		'{causa_baja}',
		'{horas}',
		'{fecha_horas}',
		'{enlace_horas}',
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

		// El circuito del voluntariado (members#50). Cuatro avisos más, editables como los demás: el texto
		// vive en la plantilla y la dirección la decide quien dispara (la del socio, o la del voluntariado
		// configurada en los ajustes).
		add_action( 'convoca_members_email_voluntariado_cita', array( $this, 'send_voluntariado_cita' ), 10, 3 );
		add_action( 'convoca_members_email_voluntariado_baja_solicitada', array( $this, 'send_voluntariado_baja_solicitada' ), 10, 3 );
		add_action( 'convoca_members_email_voluntariado_desvinculacion', array( $this, 'send_voluntariado_desvinculacion' ), 10, 2 );
		add_action( 'convoca_members_email_voluntariado_horas_pendientes', array( $this, 'send_voluntariado_horas_pendientes' ), 10, 4 );

		// Los nombres legacy NO se registran: `Utils::do_action()` dispara el
		// nombre nuevo y, si queda algún oyente en el viejo, lo dispara también
		// (con aviso de deprecación). Registrar el mismo callback en los dos
		// nombres enviaba el correo DOS veces y avisaba por PHP en cada envío.
		// Los correos usan los nombres nuevos (convoca_members_*).

		// Credentials hook (called by Process_Member::handle_approved).
		add_action( 'convoca_members_email_credenciales', array( $this, 'send_credenciales' ), 10, 2 );

		// Corrección de plantillas guardadas por versiones anteriores.
		add_action( 'admin_init', array( __CLASS__, 'maybe_migrate' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_relocalize' ), 5 );
	}

	/* ── Default templates (installed on activation) ───── */

	/**
	 * Plantillas de fábrica, una por slug de TEMPLATES.
	 *
	 * @return array<string, array{subject: string, body: string}>
	 */
	public static function default_templates(): array {
		return array(
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
				'subject' => __( 'Hemos recibido tu solicitud de {tipo_solicitud} — ', 'convoca-members' ) . get_bloginfo( 'name' ),
				'body'    => __( '<h1>Hola {nombre},</h1>', 'convoca-members' )
					. '<p>Hemos recibido correctamente tu solicitud de <strong>{tipo_solicitud}</strong> como <strong>{tipo_miembro}</strong> en ' . esc_html( get_bloginfo( 'name' ) ) . '.</p>'
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
					. '{datos_transferencia}'
					. __( '<p>Un miembro del equipo revisará tu solicitud y te contactaremos pronto.</p>', 'convoca-members' )
					. sprintf( /* translators: %s: site name */ __( '<p>¡Gracias por unirte a la familia %s!</p>', 'convoca-members' ), esc_html( get_bloginfo( 'name' ) ) ),
			),
			'bienvenida'                       => array(
				'subject' => sprintf( /* translators: %s: site name */ __( '¡Bienvenido/a, {nombre}! Ya formas parte de %s', 'convoca-members' ), get_bloginfo( 'name' ) ),
				'body'    => __( '<h1>¡Bienvenido/a, {nombre}! 🎉</h1>', 'convoca-members' )
					. sprintf( /* translators: %s: site name */ __( '<p>Tu alta como <strong>{tipo_miembro}</strong> en %s ha sido confirmada.</p>', 'convoca-members' ), esc_html( get_bloginfo( 'name' ) ) )
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
					. sprintf( /* translators: %s: site name */ __( '<p>Ya formas parte de la comunidad %s. Puedes participar en todas nuestras actividades y proyectos.</p>', 'convoca-members' ), esc_html( get_bloginfo( 'name' ) ) )
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
					. __( '<p>Puedes realizar el pago de forma segura desde tu panel de socio o con un clic en el botón:</p>', 'convoca-members' )
					. Email_Layout::button_html( '{link_pago}', __( 'Pagar ahora', 'convoca-members' ) )
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
					. __( '<p>Es importante completar el pago para mantener tu estado activo y acceder a las ventajas de socio.</p>', 'convoca-members' )
					. Email_Layout::button_html( '{link_pago}', __( 'Pagar ahora', 'convoca-members' ) ),
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
					. '<p>Si no recibimos el pago en los próximos días, tu cuenta será suspendida automáticamente. Aún estás a tiempo con este enlace:</p>'
					. Email_Layout::button_html( '{link_pago}', __( 'Pagar ahora', 'convoca-members' ) ),
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
					. __( '<p>Puedes renovar con un clic. El enlace es seguro y seguirá siendo válido aunque se acerque o pase la fecha de vencimiento:</p>', 'convoca-members' )
					. Email_Layout::button_html( '{link_pago}', __( 'Renovar ahora', 'convoca-members' ) )
					. '<p>¡Gracias por seguir con nosotros!</p>',
			),
			'renovacion_15d'                   => array(
				'subject' => 'Tu renovación en ' . get_bloginfo( 'name' ) . ' (15 días)',
				'body'    => __( '<h1>Hola {nombre},</h1>', 'convoca-members' )
					. __( '<p>Faltan solo <strong>15 días</strong> para que venza tu membresía de {tipo_miembro}.</p>', 'convoca-members' )
					. '<p>Recuerda renovar para no perder tu antigüedad y beneficios.</p>'
					. Email_Layout::button_html( '{link_pago}', __( 'Renovar ahora', 'convoca-members' ) ),
			),
			'renovacion_7d'                    => array(
				'subject' => __( 'Última semana para tu renovación — ', 'convoca-members' ) . get_bloginfo( 'name' ),
				'body'    => __( '<h1>Hola {nombre},</h1>', 'convoca-members' )
					. __( '<p>Tu membresía de {tipo_miembro} vencerá en <strong>7 días</strong>.</p>', 'convoca-members' )
					. '<p>Evita la suspensión automática renovando ahora. Si la fecha ya ha pasado, el enlace sigue siendo válido durante el periodo de gracia:</p>'
					. Email_Layout::button_html( '{link_pago}', __( 'Renovar ahora', 'convoca-members' ) ),
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
					. sprintf( /* translators: %s: site name */ __( '<p>¡Gracias por seguir apoyando a %s!</p>', 'convoca-members' ), esc_html( get_bloginfo( 'name' ) ) ),
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
					. Email_Layout::button_html( '{link_confirmacion}', __( 'Confirmar email', 'convoca-members' ) )
					. __( '<p>Si no has solicitado este cambio, ignora este mensaje. Tu email actual seguirá activo.</p>', 'convoca-members' ),
			),
			'verify_phone'                     => array(
				'subject' => __( 'Verifica tu teléfono — ', 'convoca-members' ) . get_bloginfo( 'name' ),
				'body'    => __( '<h1>Hola {nombre},</h1>', 'convoca-members' )
					. __( '<p>Has solicitado verificar tu número de teléfono <strong>{telefono}</strong>.</p>', 'convoca-members' )
					. __( '<p>Para confirmar que es tu número, haz clic en el siguiente enlace (válido por 24 horas):</p>', 'convoca-members' )
					. Email_Layout::button_html( '{link_confirmacion}', __( 'Verificar teléfono', 'convoca-members' ) )
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
					. '<p>Si tienes horas pendientes de registrar, puedes hacerlo desde tu <a href="{login_url}">panel de socio</a>.</p>'
					. '<p>¡Tus manos son fundamentales para la asociación! 🌱</p>',
			),
			'objetivo_voluntariado_completado' => array(
				'subject' => '🎉 ' . sprintf( /* translators: %s: site name */ __( '¡Felicidades! Has completado tu voluntariado — %s', 'convoca-members' ), get_bloginfo( 'name' ) ),
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
			'voluntariado_cita'                => array(
				'subject' => __( 'Tu entrevista de voluntariado: {fecha_cita} a las {hora_cita}', 'convoca-members' ),
				'body'    => __( '<h1>Hola {nombre},</h1>', 'convoca-members' )
					. __( '<p>Queremos conocerte. Te esperamos para la entrevista inicial de voluntariado:</p>', 'convoca-members' )
					. Email_Layout::meta_table(
						array(
							array(
								'label' => __( 'Día', 'convoca-members' ),
								'value' => '{fecha_cita}',
							),
							array(
								'label' => __( 'Hora', 'convoca-members' ),
								'value' => '{hora_cita}',
							),
						)
					)
					. __( '<p>Si te viene mal, respóndenos a este correo y buscamos otro día.</p>', 'convoca-members' )
					. Email_Layout::button_html( '{login_url}', __( 'Ver mi área', 'convoca-members' ) ),
			),
			'voluntariado_acuerdo'             => array(
				'subject' => __( 'Tu acuerdo de voluntariado — ', 'convoca-members' ) . get_bloginfo( 'name' ),
				'body'    => __( '<h1>Hola {nombre},</h1>', 'convoca-members' )
					. __( '<p>Aquí tienes tu acuerdo de voluntariado, con tus datos y tu firma. Guárdalo: es tu compromiso con la entidad y el nuestro contigo.</p>', 'convoca-members' )
					. __( '<p>Puedes aceptarlo (y volver a descargarlo) desde tu área.</p>', 'convoca-members' )
					. Email_Layout::button_html( '{login_url}', __( 'Ver y aceptar mi acuerdo', 'convoca-members' ) ),
			),
			'voluntariado_baja_solicitada'     => array(
				'subject' => __( 'Un voluntariado ha pedido la baja: {nombre}', 'convoca-members' ),
				'body'    => __( '<h1>{nombre} ha pedido desvincularse</h1>', 'convoca-members' )
					. __( '<p>Lo que cuenta:</p>', 'convoca-members' )
					. '<blockquote>{motivo}</blockquote>'
					. __( '<p>La baja no se aplica sola: confírmala desde Miembros → Voluntarios.</p>', 'convoca-members' )
					. Email_Layout::button_html( '{login_url}', __( 'Confirmarla', 'convoca-members' ) ),
			),
			'voluntariado_desvinculacion'      => array(
				'subject' => __( 'Tu etapa como voluntario se ha cerrado', 'convoca-members' ),
				'body'    => __( '<h1>Gracias por el tiempo, {nombre}</h1>', 'convoca-members' )
					. __( '<p>Tu etapa como voluntario se ha cerrado ({causa_baja}).</p>', 'convoca-members' )
					. __( '<p>Lo que hiciste no se borra: tus horas quedan registradas ({horas} h) y tu certificado se puede descargar y validar cuando quieras.</p>', 'convoca-members' )
					. Email_Layout::button_html( '{login_url}', __( 'Descargar mi certificado', 'convoca-members' ) ),
			),
			'voluntariado_horas_pendientes'    => array(
				'subject' => __( 'Horas de voluntariado pendientes de aprobar: {nombre}', 'convoca-members' ),
				'body'    => __( '<h1>{nombre} ha apuntado horas</h1>', 'convoca-members' )
					. __( '<p>{horas} horas del día {fecha_horas}. Se aprueban (o se rechazan) aquí:</p>', 'convoca-members' )
					. Email_Layout::button_html( '{enlace_horas}', __( 'Aprobar las horas', 'convoca-members' ) ),
			),
		);
	}

	/**
	 * Escribe las plantillas de fábrica. Solo al activar: no pisa las de un sitio
	 * que ya las tenga.
	 */
	public static function install_defaults(): void {
		if ( false !== get_option( self::OPTION ) ) {
			return;
		}

		update_option( self::OPTION, self::default_templates() );
		update_option( self::TEMPLATES_LOCALE_OPTION, determine_locale() );
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
		$attachments = array();

		// Attach the member card as a PDF (renewal confirmation).
		if ( class_exists( '\Convoca\Members\PDF_Card' ) ) {
			try {
				$pdf_binary = \Convoca\Members\PDF_Card::generate_pdf( $post_id );
				if ( strlen( $pdf_binary ) > 500 ) {
					$pdf_path = sys_get_temp_dir() . '/convoca-card-' . wp_generate_password( 12, false, false ) . '.pdf';
					file_put_contents( $pdf_path, $pdf_binary ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
					if ( file_exists( $pdf_path ) ) {
						$attachments[] = $pdf_path;
					}
				}
			} catch ( \Throwable $e ) {
				\Convoca\Core\Logger::warning( 'No se pudo adjuntar el carnet al email de renovación: ' . $e->getMessage(), 'Members/Emails', $post_id );
			}
		}

		$this->send( 'renovacion_completada', $post_id, array(), '', $attachments );

		// Cleanup attachment after dispatch.
		foreach ( $attachments as $file ) {
			if ( file_exists( $file ) ) {
				unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			}
		}
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
		// Este correo felicita por completar el objetivo y ofrece el certificado.
		// Sin horas acreditadas felicitaría por «completar 0h» y enlazaría a un
		// certificado que no existe, así que no se envía: es un dato incoherente,
		// no un correo legítimo con huecos.
		if ( ! self::objetivo_tiene_horas( $post_id ) ) {
			\Convoca\Core\Logger::warning(
				"No se envía el correo de objetivo completado al miembro #{$post_id}: no tiene horas acreditadas.",
				'Members/Emails',
				$post_id
			);
			return;
		}

		$this->send( 'objetivo_voluntariado_completado', $post_id );
	}

	/**
	 * ¿Tiene el miembro horas acreditadas con las que justificar el correo
	 * de objetivo completado (y su certificado)?
	 */
	public static function objetivo_tiene_horas( int $post_id ): bool {
		return Voluntariado_Manager::get_horas_aprobadas( $post_id ) > 0;
	}

	/**
	 * Send credentials email (username + password) after approval.
	 * The extra vars are provided by Process_Member.
	 */
	public function send_credenciales( int $post_id, array $extra_vars = array() ): void {
		$this->send( 'credenciales_acceso', $post_id, $extra_vars );
	}

	/* ── El circuito del voluntariado (members#50) ─────── */

	/**
	 * Avisa a la persona de su cita para la entrevista inicial.
	 *
	 * @param int    $post_id Ficha del socio.
	 * @param string $fecha   Día de la cita.
	 * @param string $hora    Hora de la cita.
	 */
	public function send_voluntariado_cita( int $post_id, string $fecha, string $hora ): void {
		$this->send(
			'voluntariado_cita',
			$post_id,
			array(
				'{fecha_cita}' => $fecha,
				'{hora_cita}'  => $hora,
			)
		);
	}

	/**
	 * Avisa a la junta de que alguien ha pedido la baja.
	 *
	 * La dirección la decide quien dispara el aviso (el correo del voluntariado, o el de administración):
	 * eso es configuración del sitio, no texto del correo.
	 *
	 * @param int    $post_id  Ficha del socio.
	 * @param string $motivo   Lo que haya contado la persona.
	 * @param string $to_email A quién se avisa.
	 */
	public function send_voluntariado_baja_solicitada( int $post_id, string $motivo, string $to_email ): void {
		$this->send(
			'voluntariado_baja_solicitada',
			$post_id,
			array( '{motivo}' => '' !== $motivo ? $motivo : __( '(sin motivo)', 'convoca-members' ) ),
			$to_email
		);
	}

	/**
	 * Avisa a la persona de que su desvinculación está aplicada.
	 *
	 * Sus horas quedan: el correo las dice, porque es la duda razonable de quien se va.
	 *
	 * @param int    $post_id Ficha del socio.
	 * @param string $causa   Causa de la desvinculación, ya legible.
	 */
	public function send_voluntariado_desvinculacion( int $post_id, string $causa ): void {
		$this->send(
			'voluntariado_desvinculacion',
			$post_id,
			array(
				'{causa_baja}' => $causa,
				'{horas}'      => number_format_i18n( Voluntariado_Manager::get_horas_aprobadas( $post_id ), 1 ),
			)
		);
	}

	/**
	 * Avisa de que hay horas apuntadas esperando aprobación.
	 *
	 * @param int    $post_id  Ficha del socio.
	 * @param string $horas    Las horas, ya formateadas.
	 * @param string $fecha    El día del trabajo.
	 * @param string $to_email A quién se avisa.
	 */
	public function send_voluntariado_horas_pendientes( int $post_id, string $horas, string $fecha, string $to_email ): void {
		$this->send(
			'voluntariado_horas_pendientes',
			$post_id,
			array(
				'{horas}'        => $horas,
				'{fecha_horas}'  => $fecha,
				'{enlace_horas}' => admin_url( 'admin.php?page=conv-horas' ),
			),
			$to_email
		);
	}

	/* ── Core send logic ───────────────────────────────── */

	private function send( string $template_slug, int $post_id, array $extra_vars = array(), string $to_email = '', array $attachments = array() ): void {
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

		$layout_opts = array(
			'footer_text' => sprintf(
				/* translators: %s: site name */
				__( 'Has recibido este email porque eres miembro de %s.', 'convoca-members' ),
				get_bloginfo( 'name' )
			),
		);

		// CTA: si la plantilla no trae botón, se añade uno para que el email no
		// llegue sin acción. Muchas plantillas guardadas vienen de versiones
		// anteriores que no lo incluían (el texto pedía «paga desde tu panel»
		// pero no había forma de llegar).
		if ( false === strpos( $body, 'email-btn' ) ) {
			$cta = self::default_cta( $template_slug );
			if ( $cta ) {
				$cta_url  = strtr( (string) $cta[0], $vars );
				$cta_text = (string) $cta[1];

				// {link_pago} puede venir vacío (p. ej. sin pasarela configurada):
				// en ese caso el CTA apunta al panel del socio.
				if ( '' === trim( $cta_url ) || '#' === trim( $cta_url ) ) {
					$cta_url = (string) ( $vars['{login_url}'] ?? '' );
				}

				if ( '' !== trim( $cta_url ) && '' !== $cta_text ) {
					$layout_opts['button_url']  = $cta_url;
					$layout_opts['button_text'] = $cta_text;
				}
			}
		}

		$body = Email_Layout::render( $body, $subject, $layout_opts );

		$sent = \Convoca\Members\Email_Verifier::send( $email, $subject, $body, $headers, $attachments );

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

		// Copia al administrador (o al monitor de la actividad) de TODO correo de socio.
		// Sustituye al aviso que existía solo para `solicitud_recibida` y que salía hacia
		// $system_email — el propio remitente — en vez del correo configurado en
		// «Email administrador»: por eso no llegaba a la asociación.
		\Convoca\Core\Email_Copy::maybe_copy(
			array(
				'to'              => array( $email ),
				'subject'         => $subject,
				'body'            => $body,
				'plugin'          => 'convoca-members',
				'template'        => $template_slug,
				'entity_id'       => $post_id,
				'has_attachments' => ! empty( $attachments ),
				'send'            => static function ( array $to, string $copy_subject, string $copy_body, array $copy_headers ): bool {
					$ok = true;
					foreach ( $to as $one ) {
						$ok = \Convoca\Members\Email_Verifier::send( (string) $one, $copy_subject, $copy_body, $copy_headers ) && $ok;
					}
					return $ok;
				},
			)
		);
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
		if ( $importe_custom && $importe_custom !== $importe ) {
			$importe = $importe_custom;
			$cuota   = $importe . '€/año';
		}

		$terms      = wp_get_object_terms( $post_id, 'tipo_miembro', array( 'fields' => 'names' ) );
		$tipo_label = is_array( $terms ) && ! empty( $terms ) ? $terms[0] : '';

		// Fallback E2E-6: si la taxonomía tipo_miembro no tiene término asignado
		// (no se asigna en el alta), derivar una etiqueta legible del contexto.
		if ( empty( $tipo_label ) ) {
			if ( $forma === 'voluntariado' ) {
				$tipo_label = __( 'Voluntario/a', 'convoca-members' );
			} elseif ( $plan_data && ! empty( $plan_data['label'] ) ) {
				$tipo_label = $plan_data['label'];
			} else {
				$tipo_label = __( 'Socio/a', 'convoca-members' );
			}
		}

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
			// Alta o renovación: lo marca el flujo que crea/reactiva la ficha, para
			// que el correo no diga «solicitud» a secas cuando alguien vuelve.
			'{tipo_solicitud}'               => ( '1' === (string) get_post_meta( $post_id, '_convoca_solicitud_renovacion', true ) ) ? __( 'renovación', 'convoca-members' ) : __( 'alta', 'convoca-members' ),
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
			'{login_url}'                    => $extra_vars['{login_url}'] ?? \Convoca\Core\Email_Links::panel(),
			// Datos para hacer la transferencia, cuando el alta se hizo por esa vía (gateway#9): es lo
			// que necesita quien acaba de apuntarse para poder ingresar sin preguntar. Vacío si no
			// procede, para que el correo no deje un hueco.
			'{datos_transferencia}'          => self::datos_transferencia( $post_id ),
		);
	}

	/**
	 * Bloque con los datos para transferir la cuota, o cadena vacía si esta ficha no va por transferencia.
	 *
	 * El concepto lleva el número de pago: es lo que permite a la junta enlazar la transferencia que
	 * llegue con el alta, que es justo lo que hoy hay que resolver a mano por WhatsApp (gateway#9).
	 *
	 * @param int $post_id Ficha del miembro.
	 * @return string HTML ya escapado, o ''.
	 */
	private static function datos_transferencia( int $post_id ): string {
		if ( 'transferencia' !== (string) get_post_meta( $post_id, '_convoca_metodo_pago', true ) ) {
			return '';
		}

		$settings = (array) get_option( 'convoca_gateway_settings', array() );
		$iban     = trim( (string) ( $settings['iban'] ?? '' ) );
		if ( '' === $iban ) {
			// Sin IBAN no se inventa nada: el aviso de que falta va en la pantalla de pago.
			return '';
		}

		$pago_id  = (int) get_post_meta( $post_id, '_convoca_pago_id', true );
		$concepto = $pago_id > 0 ? (string) get_post_meta( $pago_id, '_convoca_order_id', true ) : '';
		if ( '' === $concepto ) {
			$concepto = (string) ( get_post_meta( $post_id, 'numero_socio', true ) ?: get_the_title( $post_id ) );
		}

		// El importe se toma del PAGO (es el que se va a transferir de verdad); si no hay pago, del
		// precio del plan. `$plan_data` no existe en este ámbito: el primer intento lo usaba y habría
		// dejado la fila vacía.
		$importe = '';
		if ( $pago_id > 0 && class_exists( '\Convoca\Gateway\CPT_Pago' ) ) {
			$centimos = (int) get_post_meta( $pago_id, '_convoca_amount_cents', true );
			if ( $centimos > 0 ) {
				$importe = \Convoca\Gateway\CPT_Pago::format_amount( $centimos );
			}
		}
		if ( '' === $importe ) {
			$plan    = CPT_Miembro::get_plan( (string) get_post_meta( $post_id, '_convoca_plan', true ) );
			$importe = isset( $plan['price'] ) ? number_format_i18n( (float) $plan['price'], 2 ) . ' €' : '';
		}

		$filas = array(
			array(
				'label' => __( 'IBAN', 'convoca-members' ),
				'value' => $iban,
			),
			array(
				'label' => __( 'Beneficiario', 'convoca-members' ),
				'value' => (string) ( $settings['beneficiary'] ?? '' ),
			),
			array(
				'label' => __( 'Concepto', 'convoca-members' ),
				'value' => $concepto,
			),
			array(
				'label' => __( 'Importe', 'convoca-members' ),
				'value' => $importe,
			),
		);

		// Se quitan las filas sin valor para que el correo no enseñe etiquetas en blanco. Un `foreach`
		// explícito y no `array_filter` + `array_values`: la cadena de llamadas dejaba un tipo que PHPStan
		// no puede resolver (medido al estrenarlo).
		$con_valor = array();
		foreach ( $filas as $fila ) {
			if ( '' !== trim( (string) $fila['value'] ) ) {
				$con_valor[] = $fila;
			}
		}
		if ( empty( $con_valor ) ) {
			return '';
		}
		$filas = $con_valor;

		$instrucciones = (string) ( $settings['instructions'] ?? '' );

		return '<h3>' . esc_html__( 'Cómo hacer la transferencia', 'convoca-members' ) . '</h3>'
			. Email_Layout::meta_table( $filas )
			. ( '' !== trim( $instrucciones ) ? '<p>' . esc_html( $instrucciones ) . '</p>' : '' )
			. '<p>' . esc_html__( 'Cuando la hagas, puedes adjuntar el justificante desde la página de pago para que podamos validarla antes.', 'convoca-members' ) . '</p>';
	}

	/**
	 * Replace template variables.
	 */
	private function replace_vars( string $text, array $vars ): string {
		return str_replace( array_keys( $vars ), array_values( $vars ), $text );
	}

	/* ── Vista previa (datos de ejemplo) ─────────────── */

	/**
	 * Acción por defecto de cada plantilla: [url_placeholder, texto].
	 *
	 * Garantiza que ningún email llegue sin una acción clara. Se usa solo cuando
	 * el cuerpo no incluye ya un botón (las plantillas guardadas de versiones
	 * anteriores pedían «paga desde tu panel» sin enlace).
	 *
	 * @return array<int,string>|array{}
	 */
	/**
	 * Dominios de correo que se consideran del equipo: los que las plantillas nombran a mano
	 * («escribe a coordinacion@…») y hay que sustituir por el email configurado en WordPress.
	 *
	 * Se configuran en Convoca → Miembros → Ajustes. El valor por defecto reproduce el que
	 * estaba fijo en el código, así que una instalación que no lo toque no cambia de comportamiento.
	 */
	private static function dominios_de_correo(): string {
		$settings = get_option( 'convoca_members_settings', array() );
		$dominios = trim( (string) ( $settings['admin_email_domains'] ?? '' ) );

		if ( '' === $dominios ) {
			// Por defecto, el dominio del propio sitio (el del email configurado arriba): así cada
			// instalación reconoce los suyos y el código no lleva el nombre de ninguna asociación.
			$correo   = (string) ( $settings['admin_email'] ?? get_option( 'admin_email' ) );
			$arroba   = strrchr( $correo, '@' );
			$propio   = false === $arroba ? '' : substr( $arroba, 1 );
			$dominios = 'getconvoca.app' . ( '' !== $propio ? '|' . $propio : '' );
		}

		// Solo lo que puede formar parte del patrón: fuera metacaracteres.
		$dominios = (string) preg_replace( '/[^a-z0-9._|\-]/i', '', $dominios );
		$dominios = trim( $dominios, '|' );
		if ( '' === $dominios ) {
			$dominios = 'getconvoca.app';
		}

		// El punto separa dominios: en un patrón tiene que ir escapado.
		return str_replace( '.', '\.', $dominios );
	}

	private static function default_cta( string $slug ): array {
		$ctas = array(
			'solicitud_recibida'        => array( '{login_url}', __( 'Acceder a Mi Área', 'convoca-members' ) ),
			'bienvenida'                => array( '{login_url}', __( 'Acceder a Mi Área', 'convoca-members' ) ),
			'recordatorio_pago'         => array( '{link_pago}', __( 'Pagar ahora', 'convoca-members' ) ),
			'pago_pendiente_2'          => array( '{link_pago}', __( 'Pagar ahora', 'convoca-members' ) ),
			'pago_pendiente_ultimo'     => array( '{link_pago}', __( 'Pagar ahora', 'convoca-members' ) ),
			'renovacion'                => array( '{link_pago}', __( 'Renovar ahora', 'convoca-members' ) ),
			'renovacion_15d'            => array( '{login_url}', __( 'Renovar mi cuota', 'convoca-members' ) ),
			'renovacion_7d'             => array( '{login_url}', __( 'Renovar mi cuota', 'convoca-members' ) ),
			'renovacion_automatica'     => array( '{login_url}', __( 'Ver mi membresía', 'convoca-members' ) ),
			'renovacion_completada'     => array( '{login_url}', __( 'Ver mi carnet', 'convoca-members' ) ),
			'voluntariado_recordatorio' => array( '{login_url}', __( 'Registrar mis horas', 'convoca-members' ) ),

			// Las nueve que faltaban (members#76). Su red era el cuerpo editable: si alguien quitaba el botón
			// desde el panel, el correo llegaba sin ninguna acción y nada lo avisaba. Este CTA **solo se usa
			// cuando el cuerpo no trae botón**, así que no cambia ningún correo actual.
			'credenciales_acceso'               => array( '{login_url}', __( 'Acceder a Mi Área', 'convoca-members' ) ),
			'objetivo_voluntariado_completado'  => array( '{login_url}', __( 'Ver mi voluntariado', 'convoca-members' ) ),
			'voluntariado_cita'                 => array( '{login_url}', __( 'Ver la cita', 'convoca-members' ) ),
			'voluntariado_acuerdo'              => array( '{login_url}', __( 'Ver el acuerdo', 'convoca-members' ) ),
			'voluntariado_baja_solicitada'      => array( '{login_url}', __( 'Ver mi voluntariado', 'convoca-members' ) ),
			'voluntariado_desvinculacion'       => array( '{login_url}', __( 'Ver mi voluntariado', 'convoca-members' ) ),
			'voluntariado_horas_pendientes'     => array( '{login_url}', __( 'Registrar mis horas', 'convoca-members' ) ),
			'confirm_email'                     => array( '{link_confirmacion}', __( 'Confirmar mi correo', 'convoca-members' ) ),
			'verify_phone'                      => array( '{login_url}', __( 'Verificar mi teléfono', 'convoca-members' ) ),
		);

		return $ctas[ $slug ] ?? array();
	}

	/**
	 * Valores de ejemplo para previsualizar una plantilla: cubre TODAS las
	 * variables soportadas (VARIABLES) para que ninguna quede sin sustituir.
	 *
	 * Nota: `{importe}` va sin el símbolo € a propósito — el € lo añade la propia
	 * plantilla (p. ej. `{importe}€`), incluirlo aquí lo duplicaba.
	 *
	 * @return array<string,string>
	 */
	public static function preview_vars(): array {
		return array(
			'{nombre}'                       => 'María García López',
			'{email}'                        => 'maria@ejemplo.com',
			'{tipo_miembro}'                 => __( 'Socia', 'convoca-members' ),
			'{plan}'                         => '🥈 Plata',
			'{cuota}'                        => '50€/año',
			'{importe}'                      => '50',
			'{estado}'                       => __( 'Activo', 'convoca-members' ),
			'{numero_socio}'                 => '042',
			'{fecha_alta}'                   => '01/01/2026',
			'{fecha_renovacion}'             => '01/01/2027',
			'{fecha_baja}'                   => '—',
			'{link_pago}'                    => 'https://ejemplo.test/pago',
			'{horas_actuales}'               => '25',
			'{horas_objetivo}'               => '25',
			'{porcentaje_cumplimiento}'      => '100',
			'{horas_totales}'                => '25',
			'{plan_nombre}'                  => '🥈 Plata',
			'{proyectos_participados}'       => __( 'Limpieza de río, Plantación de árboles', 'convoca-members' ),
			'{certificado_id}'               => 'CERT-2026-042',
			'{certificado_url_verificacion}' => 'https://ejemplo.test/certificado/CERT-2026-042',
			'{usuario}'                      => 'maria.garcia',
			'{password}'                     => '••••••••',
			'{login_url}'                    => 'https://ejemplo.test/mi-area/',
			'{admin_email}'                  => get_bloginfo( 'admin_email' ),
			'{link_confirmacion}'            => 'https://ejemplo.test/confirmar',
			'{nuevo_email}'                  => 'nuevo@ejemplo.com',
			'{telefono}'                     => '600 000 000',
			'{fecha_cita}'                   => '05/11/2026',
			'{hora_cita}'                    => '18:30',
			'{motivo}'                       => 'Me mudo de ciudad.',
			'{causa_baja}'                   => 'Fuerza mayor de la junta',
			'{horas}'                        => '42,5',
			'{fecha_horas}'                  => '15/10/2026',
			'{enlace_horas}'                 => 'https://ejemplo.test/wp-admin/admin.php?page=conv-horas',
		);
	}

	/**
	 * HTML completo (con el layout de email) de una plantilla con datos de ejemplo.
	 */
	public static function preview_html( string $slug ): string {
		$templates = self::get_templates();
		$tpl       = $templates[ $slug ] ?? array(
			'subject' => '',
			'body'    => '',
		);

		$vars    = self::preview_vars();
		$subject = strtr( (string) ( $tpl['subject'] ?? '' ), $vars );
		$body    = strtr( (string) ( $tpl['body'] ?? '' ), $vars );

		$opts = array(
			'footer_text' => sprintf(
				/* translators: %s: site name */
				__( 'Has recibido este email porque eres miembro de %s.', 'convoca-members' ),
				get_bloginfo( 'name' )
			),
		);

		// Igual que en el envío: si la plantilla no trae botón, el ejemplo muestra
		// el CTA que se añadirá realmente.
		if ( false === strpos( $body, 'email-btn' ) ) {
			$cta = self::default_cta( $slug );
			if ( $cta ) {
				$cta_url = strtr( (string) $cta[0], $vars );
				if ( '' === trim( $cta_url ) || '#' === trim( $cta_url ) ) {
					$cta_url = (string) ( $vars['{login_url}'] ?? '' );
				}
				if ( '' !== trim( $cta_url ) ) {
					$opts['button_url']  = $cta_url;
					$opts['button_text'] = (string) $cta[1];
				}
			}
		}

		return Email_Layout::render( $body, $subject, $opts );
	}

	/* ── Getters / Setters for admin ───────────────────── */

	public static function get_templates(): array {
		return get_option( self::OPTION, array() );
	}

	public static function save_templates( array $templates ): void {
		update_option( self::OPTION, $templates );
	}

	/* ── Migración de plantillas ya guardadas ─────────── */

	const TEMPLATES_VERSION_OPTION = 'convoca_email_templates_version';
	const TEMPLATES_VERSION        = '2026-10-16-1';
	/**
	 * Idioma con el que se sembraron las plantillas.
	 *
	 * Las plantillas son texto ya traducido guardado en una opción, así que quedan **congeladas** en el
	 * idioma en que se sembraron. Medido el 17/10/2026 (members#60): el correo de cambio de email salía
	 * entero en inglés en un sitio en castellano, porque la siembra ocurrió con el catálogo en inglés.
	 */
	const TEMPLATES_LOCALE_OPTION = 'convoca_email_templates_locale';

	/**
	 * Corrige las plantillas ya guardadas en sitios existentes: `install_defaults()`
	 * solo actúa al activar, así que los textos rotos de versiones anteriores
	 * (escapes `\u00a1`/`\U0001f389`, enlaces sin llaves y la marca «Convoca» donde
	 * debe ir el nombre del sitio) seguirían ahí indefinidamente.
	 *
	 * Las sustituciones son literales y acotadas: no reescribe las plantillas
	 * personalizadas más allá de esos patrones.
	 */
	/**
	 * Las plantillas de fábrica, calculadas en un idioma concreto.
	 *
	 * @param string $locale Idioma.
	 * @return array<string,array{subject:string,body:string}>
	 */
	private static function defaults_in_locale( string $locale ): array {
		switch_to_locale( $locale );
		$defecto = self::default_templates();
		restore_previous_locale();

		return $defecto;
	}

	/**
	 * Pone las plantillas en el idioma del sitio, sin pisar las que el admin haya cambiado.
	 *
	 * Las plantillas de fábrica son texto ya traducido (`__( ... )`) que se guarda en una opción: si la
	 * siembra ocurre con el catálogo en otro idioma, los correos se quedan en ese idioma para siempre
	 * aunque el sitio esté en castellano. Medido el 17/10/2026 (members#60): el correo de confirmación
	 * del cambio de email salía entero en inglés, con el pie en castellano porque viene de otro sitio.
	 *
	 * Cómo reconoce lo que puede tocar: una plantilla se re-siembra **solo** si es idéntica a la de
	 * fábrica **de otro idioma**. Lo que el admin haya escrito no coincide con ninguna de fábrica, así que
	 * se queda como está. También cubre los sitios que se sembraron antes de que existiera esta marca.
	 */
	public static function maybe_relocalize(): void {
		$templates = get_option( self::OPTION );
		if ( ! is_array( $templates ) || empty( $templates ) ) {
			return;
		}

		$actual   = determine_locale();
		$guardado = (string) get_option( self::TEMPLATES_LOCALE_OPTION, '' );

		if ( $guardado === $actual ) {
			return;
		}

		// Idiomas contra los que comparar: el anotado, y los dos del producto si no hay marca.
		$candidatos = '' !== $guardado ? array( $guardado ) : array( 'en_US', 'es_ES' );
		$origenes   = array();
		foreach ( array_unique( $candidatos ) as $locale ) {
			if ( $locale === $actual ) {
				continue;
			}
			$origenes[ $locale ] = self::defaults_in_locale( $locale );
		}

		$defecto     = self::default_templates();
		$resembradas = 0;

		foreach ( $defecto as $slug => $tpl ) {
			if ( ! isset( $templates[ $slug ] ) || $templates[ $slug ] === $tpl ) {
				continue;
			}

			foreach ( $origenes as $defecto_origen ) {
				if ( isset( $defecto_origen[ $slug ] ) && $templates[ $slug ] === $defecto_origen[ $slug ] ) {
					$templates[ $slug ] = $tpl;
					++$resembradas;
					break;
				}
			}
		}

		update_option( self::OPTION, $templates );
		update_option( self::TEMPLATES_LOCALE_OPTION, $actual );

		if ( $resembradas > 0 && class_exists( '\\Convoca\\Core\\Logger' ) ) {
			\Convoca\Core\Logger::info(
				sprintf( 'Plantillas de correo puestas en el idioma del sitio (%s): %d re-sembradas; el resto, personalizadas y respetadas.', $actual, $resembradas ),
				'Members/Emails'
			);
		}
	}

	public static function maybe_migrate(): void {
		if ( self::TEMPLATES_VERSION === get_option( self::TEMPLATES_VERSION_OPTION ) ) {
			return;
		}

		$templates = get_option( self::OPTION );

		if ( is_array( $templates ) && ! empty( $templates ) ) {
			$site = get_bloginfo( 'name' );
			$map  = array(
				// Escapes Unicode que se guardaron como texto literal.
				'\u00a1'                => '¡',
				'\u0001F389'            => '🎉',
				'\U0001f389'            => '🎉',
				// Residuo del doble escape (el \u se perdió al guardar).
				'<h1>00a1Bienvenido/a'  => '<h1>¡Bienvenido/a',
				'! 0001F389</H1>'       => '! 🎉</h1>',
				// Placeholders que perdieron las llaves: enlaces roto. Se
				// reconocen por su NOMBRE, no por el caso suelto que aparezca
				// (antes solo estaban contemplados dos de los cinco).
				'familia Convoca'       => 'familia ' . $site,
				'comunidad Convoca'     => 'comunidad ' . $site,
				'apoyando a Convoca'    => 'apoyando a ' . $site,
				// Enlaces al panel escritos a mano en el cuerpo: en un sitio cuyo
				// panel se llama distinto son un 404, y no se arreglan solos si la
				// página cambia de nombre. Vuelven a ser el placeholder, que se
				// resuelve en cada envío contra la página real.
				home_url( '/mi-area/' ) => '{login_url}',
			);

			foreach ( $templates as $slug => $tpl ) {
				foreach ( array( 'subject', 'body' ) as $field ) {
					if ( isset( $tpl[ $field ] ) ) {
						$text = str_replace(
							array_keys( $map ),
							array_values( $map ),
							(string) $tpl[ $field ]
						);

						// Emails fijos («escribe a coordinacion@…») → la variable del
						// email configurado en WordPress, para que cada sitio envíe
						// al suyo sin tocar plantillas.
						$text = preg_replace(
							'/[a-z0-9._%+\-]+@(?:' . self::dominios_de_correo() . ')/i',
							'{admin_email}',
							$text
						);

						// `esc_url()` destruye un placeholder: `{link_pago}` quedó
						// guardado como `http://link_pago`, así que la sustitución
						// posterior no encuentra nada y el botón viaja roto. Se
						// reconocen SOLO los nombres de variable reales, para no
						// tocar una URL de verdad que contenga una palabra parecida.
						$nombres = implode(
							'|',
							array_map(
								static fn( string $v ): string => preg_quote( trim( $v, '{}' ), '/' ),
								self::VARIABLES
							)
						);

						$text = (string) preg_replace( '#https?://(' . $nombres . ')(?=["\'])#i', '{$1}', (string) $text );

						$templates[ $slug ][ $field ] = $text;
					}
				}
			}

			// El saludo de bienvenida era LA MISMA frase en el asunto y en la
			// primera línea del cuerpo (el nombre del sitio entraba en las dos),
			// así que el correo empezaba repitiendo su propio asunto. Se
			// reescribe solo si la plantilla conserva el saludo de fábrica: si
			// el sitio lo personalizó, se respeta.
			$saludo_asunto = '¡Bienvenido/a a ' . $site . ', {nombre}!';
			$saludo_cuerpo = '<h1>¡Bienvenido/a a ' . $site . ', {nombre}!';

			if ( isset( $templates['bienvenida']['subject'] ) && false !== strpos( (string) $templates['bienvenida']['subject'], $saludo_asunto ) ) {
				$templates['bienvenida']['subject'] = str_replace(
					$saludo_asunto,
					'¡Bienvenido/a, {nombre}! Ya formas parte de ' . $site,
					(string) $templates['bienvenida']['subject']
				);
			}

			if ( isset( $templates['bienvenida']['body'] ) && false !== strpos( (string) $templates['bienvenida']['body'], $saludo_cuerpo ) ) {
				$templates['bienvenida']['body'] = str_replace(
					$saludo_cuerpo,
					'<h1>¡Bienvenido/a, {nombre}!',
					(string) $templates['bienvenida']['body']
				);
			}

			// Una plantilla que falta no se «corrige»: no existe. El editor del
			// admin solo escribe las de TEMPLATES y sobreescribe la opción, así
			// que una plantilla añadida después desaparece en el primer guardado
			// —y `send()` la descarta sin decir nada—. Se reponen las de fábrica
			// que falten, sin tocar las que el sitio tenga.
			$repuestas = array();

			foreach ( self::default_templates() as $slug => $tpl ) {
				if ( ! isset( $templates[ $slug ] ) ) {
					$templates[ $slug ] = $tpl;
					$repuestas[]        = $slug;
				}
			}

			if ( $repuestas ) {
				\Convoca\Core\Logger::warning(
					'Plantillas de correo repuestas (faltaban en el sitio): ' . implode( ', ', $repuestas ),
					'Members/Emails'
				);
			}

			update_option( self::OPTION, $templates );
		} else {
			// Sitio sin plantillas guardadas (opción ausente o vacía): se escriben
			// las de fábrica, que es lo que `install_defaults()` no hizo porque la
			// opción ya existía.
			update_option( self::OPTION, self::default_templates() );
		}

		update_option( self::TEMPLATES_VERSION_OPTION, self::TEMPLATES_VERSION );
	}
}
