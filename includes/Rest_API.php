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
 * REST API for Members.
 *
 * @package Convoca\Members
 */

namespace Convoca\Members;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rest_API {

	/**
	 * Namespace for the API.
	 */
	public const NAMESPACE = 'convoca-members/v1';

	/**
	 * Rate limit: max attempts per IP in window.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		// Login.
		register_rest_route(
			self::NAMESPACE,
			'/login',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'login' ),
				'permission_callback' => '__return_true',
			)
		);

		// Current member profile.
		register_rest_route(
			self::NAMESPACE,
			'/me',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_profile' ),
				'permission_callback' => array( $this, 'check_member_auth' ),
			)
		);

		// Inscriptions (read-only for any authenticated member; only active
		// members may create new ones via convoca-enroll).
		register_rest_route(
			self::NAMESPACE,
			'/me/inscripciones',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_inscriptions' ),
				'permission_callback' => array( $this, 'check_member_auth' ),
			)
		);

		// Payments.
		register_rest_route(
			self::NAMESPACE,
			'/me/pagos',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_payments' ),
				'permission_callback' => array( $this, 'check_member_auth' ),
			)
		);

		// Volunteering hours: read-only for authenticated members (including
		// grace/suspendido); submitting new hours requires an active member.
		register_rest_route(
			self::NAMESPACE,
			'/me/horas',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_hours' ),
					'permission_callback' => array( $this, 'check_member_auth' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'submit_hours' ),
					'permission_callback' => array( $this, 'check_active_member' ),
				),
			)
		);

		// Actividades para el selector de horas del panel del socio.
		register_rest_route(
			self::NAMESPACE,
			'/activities',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_activities' ),
				'permission_callback' => array( $this, 'check_member_auth' ),
			)
		);

		// Card download/view.
		register_rest_route(
			self::NAMESPACE,
			'/me/card',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_card' ),
				'permission_callback' => array( $this, 'check_active_member' ),
			)
		);

		// Active projects for dropdown.
		register_rest_route(
			self::NAMESPACE,
			'/proyectos',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_proyectos' ),
				'permission_callback' => '__return_true',
			)
		);

		// Admin: search members (autocomplete).
		register_rest_route(
			self::NAMESPACE,
			'/admin/members/search',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'admin_search_members' ),
				'permission_callback' => fn() => current_user_can( 'gestionar_miembros' ),
			)
		);

		// Download certificate.
		register_rest_route(
			self::NAMESPACE,
			'/me/certificate',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_certificate' ),
				'permission_callback' => array( $this, 'check_active_member' ),
			)
		);

		// Download certificate as PDF.
		register_rest_route(
			self::NAMESPACE,
			'/me/certificate/download',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'download_certificate' ),
				'permission_callback' => array( $this, 'check_active_member' ),
			)
		);

		// Renovar sin pago por horas (members#45): el socio que ya ha hecho las horas de su plan no tiene que
		// esperar al vencimiento ni pasar por caja. La puerta la guarda el propio método, que vuelve a
		// comprobar las horas en el servidor: el panel solo enseña lo que hay.
		register_rest_route(
			self::NAMESPACE,
			'/me/renovar-por-horas',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'renovar_por_horas' ),
				'permission_callback' => array( $this, 'check_active_member' ),
			)
		);

		// Mis turnos (shifts#19): el contenido lo renderiza el plugin de turnos con SUS shortcodes.
		register_rest_route(
			self::NAMESPACE,
			'/me/turnos',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_turnos' ),
				'permission_callback' => array( $this, 'check_member_auth' ),
			)
		);

		// Unsubscribe request.
		register_rest_route(
			self::NAMESPACE,
			'/me/unsubscribe',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'unsubscribe_request' ),
				'permission_callback' => array( $this, 'check_active_member' ),
			)
		);

		// Renew membership (generates payment link).
		register_rest_route(
			self::NAMESPACE,
			'/me/renovar',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'renew_membership' ),
				// Renewal is allowed for 'activo' and for 'suspendido' (grace
				// period: the member kept access ONLY to renew).
				'permission_callback' => array( $this, 'check_renewable_member' ),
			)
		);

		// Privacidad del socio (members#42): ver su estado, descargar sus datos y pedir la supresión.
		// El estado lo ve cualquier socio con sesión; las dos que tocan datos piden la sesión de socio
		// (la de supresión, además, solo para quien está activo o en gracia).
		register_rest_route(
			self::NAMESPACE,
			'/me/privacidad',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_privacidad' ),
				'permission_callback' => array( $this, 'check_member_auth' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/me/datos',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_mis_datos' ),
				'permission_callback' => array( $this, 'check_member_auth' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/me/supresion',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'solicitar_supresion' ),
				'permission_callback' => array( $this, 'check_member_auth' ),
			)
		);

		// Update profile fields (address, phone, email with confirmation, birthday once).
		// Profile update is an action: only active members may change their data.
		// Members in grace (suspendido) can view but not edit.
		register_rest_route(
			self::NAMESPACE,
			'/me/profile',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'update_profile' ),
				'permission_callback' => array( $this, 'check_active_member' ),
			)
		);

		// Confirm pending email via token.
		register_rest_route(
			self::NAMESPACE,
			'/me/confirm-email',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'confirm_email' ),
				'permission_callback' => array( $this, 'check_member_auth' ),
			)
		);

		// Start phone verification (confirmation link sent by email).
		register_rest_route(
			self::NAMESPACE,
			'/me/verify-phone',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'verify_phone' ),
				'permission_callback' => array( $this, 'check_member_auth' ),
			)
		);

		// Member notifications.
		register_rest_route(
			self::NAMESPACE,
			'/member/notifications',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_member_notifications' ),
				'permission_callback' => array( $this, 'check_member_auth' ),
			)
		);

		// Mark notification as read.
		register_rest_route(
			self::NAMESPACE,
			'/member/notifications/read',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'mark_notification_read' ),
				'permission_callback' => array( $this, 'check_member_auth' ),
			)
		);

		// Mark all notifications as read.
		register_rest_route(
			self::NAMESPACE,
			'/member/notifications/read-all',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'mark_all_notifications_read' ),
				'permission_callback' => array( $this, 'check_member_auth' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/documentos/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_document' ),
				'permission_callback' => array( $this, 'check_member_auth' ),
			)
		);

		// Unified full-text search: /convoca-members/v1/search?q=...
		register_rest_route(
			self::NAMESPACE,
			'/search',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'fulltext_search' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'q'     => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'type'  => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
					'limit' => array(
						'sanitize_callback' => 'absint',
						'default'           => 20,
					),
				),
			)
		);
	}

	/**
	 * Check if a member is logged in via session cookie.
	 * Sends no-cache headers so the browser never serves stale private data
	 * (member status/renovation must always be fresh).
	 */
	public static function check_member_auth(): bool {
		if ( function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}
		return Member_Auth::is_authenticated();
	}

	/**
	 * Check if a member is logged in AND is active.
	 */
	public static function check_active_member(): bool|\WP_Error {
		if ( function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}
		if ( ! Member_Auth::is_authenticated() ) {
			return false;
		}

		if ( ! Member_Auth::is_active() ) {
			return new \WP_Error(
				'inactive_member',
				__( 'Tu cuenta no está activa. Contacta con coordinación si crees que es un error.', 'convoca-members' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Check if a member is logged in AND may renew: 'activo' (normal renewal)
	 * or 'suspendido' (grace period — access restricted to renewing).
	 * Members with 'baja' cannot renew here; they must re-register.
	 */
	public static function check_renewable_member(): bool|\WP_Error {
		if ( function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}
		if ( ! Member_Auth::is_authenticated() ) {
			return false;
		}

		$member_id = Member_Auth::get_current_member_id();
		$status    = get_post_meta( $member_id, '_convoca_estado_miembro', true );

		if ( in_array( $status, array( 'activo', 'suspendido' ), true ) ) {
			return true;
		}

		if ( $status === 'baja' ) {
			return new \WP_Error(
				'member_baja',
				__( 'Tu membresía está dada de baja. Vuelve a darte de alta desde el formulario para reincorporarte.', 'convoca-members' ),
				array( 'status' => 403 )
			);
		}

		return new \WP_Error(
			'inactive_member',
			__( 'Tu cuenta no puede renovar en este estado. Contacta con coordinación.', 'convoca-members' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * Login handler — WordPress credentials (username or email + password).
	 */
	public function login( \WP_REST_Request $request ): \WP_REST_Response {
		// El límite de intentos del acceso lo lleva Member_Auth (issue #14): aquí había OTRO, con otra
		// clave y otro umbral, y cada intento contaba dos veces. Una sola fuente de verdad por medida.
		$username = sanitize_text_field( $request->get_param( 'username' ) ?? $request->get_param( 'email' ) ?? '' );
		$password = $request->get_param( 'password' ) ?? '';

		if ( empty( $username ) || empty( $password ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Usuario y contraseña son obligatorios.', 'convoca-members' ),
				),
				400
			);
		}

		$token = Member_Auth::login( $username, $password );

		if ( is_wp_error( $token ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => $token->get_error_message(),
				),
				401
			);
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'token'   => $token,
			)
		);
	}

	/**
	 * Get profile data.
	 */
	/**
	 * El estado de la cuota tal como lo necesita el panel del socio (members#38).
	 *
	 * Una sola fuente para «Mis Datos» y para «Pagos y Cuotas» —la pantalla donde está el botón de
	 * renovar—. Sin esto, el socio veía el botón sin saber hasta cuándo tiene pagado el año: podía renovar
	 * en octubre creyendo que le tocaba y pagar otra vez un periodo ya cubierto. Medido: con la cuota
	 * pagada hasta 14/10/2027, la pantalla del botón no decía nada de esa fecha.
	 *
	 * Y `pago_pendiente`: si ya hay un pago de cuota esperando, se enseña ESE en lugar de generar otro
	 * enlace. Dos enlaces abiertos para el mismo periodo es como se cobra dos veces sin querer.
	 *
	 * @param int $member_id Ficha del socio.
	 * @return array{fecha_renovacion:string,cuota_vencida:bool,puede_renovar:bool,renovable:bool,pago_pendiente:int,pago_pendiente_url:string}
	 */
	public static function estado_cuota_panel( int $member_id ): array {
		$estado = (string) get_post_meta( $member_id, '_convoca_estado_miembro', true );
		$fecha  = (string) get_post_meta( $member_id, '_convoca_fecha_renovacion', true );
		$puede  = in_array( $estado, array( 'activo', 'suspendido' ), true );

		$pendiente = 0;
		$pagos     = get_posts(
			array(
				'post_type'      => 'pago',
				'post_status'    => 'publish',
				'posts_per_page' => 5,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => '_convoca_origin',
						'value' => 'members',
					),
					array(
						'key'   => '_convoca_origin_id',
						'value' => $member_id,
					),
					array(
						'key'   => '_convoca_status',
						'value' => 'pending',
					),
				),
			)
		);
		if ( ! empty( $pagos ) ) {
			$pendiente = (int) $pagos[0];
		}

		// El ciclo de voluntariado, para que el socio sepa por dónde va (members#45). Sale de la MISMA fuente
		// que usa el cron para renovar: si algún día cambia la regla, cambia en un sitio.
		$ciclo = \Convoca\Members\CPT_Miembro::ciclo_voluntariado( $member_id );

		return array(
			'fecha_renovacion'   => $fecha,
			'cuota_vencida'      => ( '' !== $fecha && $fecha < current_time( 'Y-m-d' ) ),
			// Horas del plan y del ciclo: 0/0 si el plan no pide horas (entonces no hay vía de voluntariado).
			'horas_plan'         => $ciclo['objetivo'],
			'horas_ciclo'        => $ciclo['horas'],
			'horas_faltan'       => $ciclo['faltan'],
			'renueva_por_horas'  => $ciclo['cumple'],
			// La fecha que tendría si renovara sin pago AHORA (la renovación por horas se aplica en cuanto
			// cumple, sin esperar al vencimiento: es lo que ha ganado con su trabajo).
			'renovacion_gratuita' => ( $ciclo['cumple'] && '' !== $fecha )
				? \Convoca\Core\Utils::format_date( $fecha . ' +1 year', 'Y-m-d' )
				: '',
			'puede_renovar'      => $puede,
			'renovable'          => $puede && ( 'voluntariado' !== (string) get_post_meta( $member_id, '_convoca_forma_pago', true ) ),
			'pago_pendiente'     => $pendiente,
			'pago_pendiente_url' => self::url_pago_pendiente( $pendiente ),
		);
	}

	/**
	 * Renueva el ciclo sin pago porque el socio cumple las horas de su plan (members#45).
	 *
	 * No se fía de lo que diga el panel: vuelve a contar las horas aprobadas en el servidor y solo renueva si
	 * llega. Así el botón no puede dar lo que no está ganado.
	 *
	 * @return \WP_REST_Response
	 */
	public function renovar_por_horas(): \WP_REST_Response {
		$member_id = Member_Auth::get_current_member_id();
		if ( $member_id <= 0 ) {
			return new \WP_REST_Response(
				array(
					'success' => false, 'message' => __( 'No hemos podido identificarte como socio.', 'convoca-members' )
				),
				403 
			);
		}

		if ( ! \Convoca\Members\CPT_Miembro::renovar_por_horas( $member_id, 'panel' ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Todavía no has llegado a las horas de tu plan, así que este año te toca cuota. En cuanto las tengas, la renovación te sale sin pago.', 'convoca-members' ),
				),
				409
			);
		}

		$estado = self::estado_cuota_panel( $member_id );

		return new \WP_REST_Response(
			array(
				'success'    => true,
				'message'    => __( '¡Hecho! Tu membresía se ha renovado sin pago con tus horas de voluntariado.', 'convoca-members' ),
				'cuota'      => $estado,
				'renovacion' => (string) $estado['fecha_renovacion'],
			),
			200
		);
	}

	/**
	 * El enlace para terminar un pago ya creado (members#38): quien perdió el correo no tiene por qué
	 * empezar de cero. Lo construye el gateway, que es quien sabe firmar el enlace; si no está, se devuelve
	 * vacío y el panel simplemente no ofrece el botón.
	 *
	 * @param int $pago_id Pago pendiente (0 si no hay).
	 * @return string
	 */
	private static function url_pago_pendiente( int $pago_id ): string {
		if ( $pago_id <= 0 || ! class_exists( '\\Convoca\\Gateway\\Payment_Handler' ) ) {
			return '';
		}

		// `build_payment_url()` es API pública del gateway y este plugin ya la usa: basta con comprobar que el
		// plugin esté ahí (PHPStan ve el doble y avisa, con razón, de que `method_exists` sobra).
		return (string) \Convoca\Gateway\Payment_Handler::build_payment_url( $pago_id );
	}

	public function get_profile( \WP_REST_Request $request ): \WP_REST_Response {
		$member_id = Member_Auth::get_current_member_id();
		$post      = get_post( $member_id );

		if ( ! $post || $post->post_type !== 'miembro' ) {
			return new \WP_REST_Response( array( 'error' => __( 'Miembro no encontrado.', 'convoca-members' ) ), 404 );
		}

		$access_code = get_post_meta( $member_id, '_convoca_access_code', true );
		$masked_code = ! empty( $access_code ) ? substr( $access_code, 0, 4 ) . '****' : '';

		$fecha_baja = get_post_meta( $member_id, '_convoca_fecha_baja', true );
		$estado     = get_post_meta( $member_id, '_convoca_estado_miembro', true );

		// El estado de la cuota lo resuelve una sola pieza (members#38).
		$cuota         = self::estado_cuota_panel( (int) $member_id );
		$fecha_renov   = $cuota['fecha_renovacion'];
		$puede_renovar = $cuota['puede_renovar'];
		$vencida       = $cuota['cuota_vencida'];

		return new \WP_REST_Response(
			array(
				'id'                  => $member_id,
				'nombre'              => $post->post_title,
				'email'               => get_post_meta( $member_id, '_convoca_email', true ),
				'email_pendiente'     => get_post_meta( $member_id, '_convoca_email_pendiente', true ),
				'direccion'           => get_post_meta( $member_id, '_convoca_direccion', true ),
				'telefono'            => get_post_meta( $member_id, '_convoca_telefono', true ),
				'telefono_verificado' => '1' === get_post_meta( $member_id, '_convoca_telefono_verificado', true ),
				'cumpleanos'          => get_post_meta( $member_id, '_convoca_cumpleanos', true ),
				'codigo'              => $masked_code,
				'estado'              => $estado,
				'plan'                => get_post_meta( $member_id, '_convoca_plan_label', true ) ?: get_post_meta( $member_id, '_convoca_plan', true ),
				'forma_pago'          => get_post_meta( $member_id, '_convoca_forma_pago', true ),
				'fecha_renovacion'    => $fecha_renov,
				'fecha_alta'          => get_post_meta( $member_id, '_convoca_fecha_alta', true ),
				'fecha_baja'          => $fecha_baja,
				'cuota_vencida'       => $vencida,
				'puede_renovar'       => $puede_renovar,
				'renovable'           => $cuota['renovable'],
				'pago_pendiente'      => $cuota['pago_pendiente'],
				'tipo'                => (array) get_post_meta( $member_id, '_convoca_modalidad', true ) ?: array( 'Socio/a' ),
			)
		);
	}

	/**
	 * Get inscriptions from convoca-enroll.
	 */
	public function get_inscriptions( \WP_REST_Request $request ): \WP_REST_Response {
		$member_id = Member_Auth::get_current_member_id();
		$email     = get_post_meta( $member_id, '_convoca_email', true );
		$dni       = get_post_meta( $member_id, '_convoca_dni', true );

		// Query inscriptions by email or DNI.
		$args = array(
			'post_type'      => 'inscripcion',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'meta_query'     => array(
				'relation' => 'OR',
				array(
					'key'   => '_convoca_email',
					'value' => $email,
				),
				array(
					'key'   => '_convoca_dni',
					'value' => $dni,
				),
			),
		);

		$query = new \WP_Query( $args );
		$items = array();

		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post ) {
				$actividad_id = get_post_meta( $post->ID, '_convoca_actividad_id', true );
				$actividad    = get_the_title( $actividad_id );

				$estado = get_post_meta( $post->ID, '_convoca_estado', true );
				$item   = array(
					'id'        => $post->ID,
					'fecha'     => get_the_date( 'd/m/Y', $post->ID ),
					'actividad' => $actividad ?: __( 'Actividad desconocida', 'convoca-members' ),
					'estado'    => $estado,
				);

				// Only include token for confirmed states (needed for ICS download).
				if ( in_array( $estado, array( 'confirmada', 'pagada' ), true ) ) {
					$item['token'] = get_post_meta( $post->ID, '_convoca_checkin_token', true );
				}

				// Include Google Photos album link if available.
				if ( $actividad_id ) {
					$album_url = get_post_meta( $actividad_id, '_convoca_google_album_url', true );
					if ( ! empty( $album_url ) ) {
						$item['fotos_url'] = $album_url;
					}
				}

				$items[] = $item;
			}
		}

		return new \WP_REST_Response( array( 'items' => $items ) );
	}

	/**
	 * El enlace público del recibo de un pago (gateway#8). Lo construye el gateway, que es quien firma el
	 * enlace y comprueba la clave; aquí solo se pide. Sin gateway instalado, no hay enlace y el panel no
	 * ofrece el botón.
	 *
	 * @param int $pago_id Pago.
	 * @return string
	 */
	private static function url_recibo( int $pago_id ): string {
		if ( $pago_id <= 0 || ! class_exists( '\\Convoca\\Gateway\\Receipt_Generator' ) ) {
			return '';
		}

		return (string) \Convoca\Gateway\Receipt_Generator::receipt_url( $pago_id );
	}

	/**
	 * El estado de privacidad del socio para su panel (members#42).
	 */
	public function get_privacidad( \WP_REST_Request $request ): \WP_REST_Response {
		$member_id = Member_Auth::get_current_member_id();

		return new \WP_REST_Response( \Convoca\Members\GDPR_Tools::estado_privacidad( $member_id ) );
	}

	/**
	 * Los datos del socio, para que se los descargue (members#42).
	 *
	 * Se sirve el MISMO exportador que usa Herramientas → Exportar datos personales: así el socio recibe
	 * exactamente lo que la asociación guarda, sin una segunda lista de campos que se quede atrás.
	 */
	public function get_mis_datos( \WP_REST_Request $request ): \WP_REST_Response {
		$member_id = Member_Auth::get_current_member_id();

		return new \WP_REST_Response( \Convoca\Members\GDPR_Tools::datos_para_descargar( $member_id ) );
	}

	/**
	 * Registra la solicitud de supresión del socio (members#42).
	 *
	 * No borra nada: deja la solicitud anotada en su ficha, avisa a la junta por correo y responde con el
	 * estado nuevo. La supresión la resuelve una persona con el borrador que ya existe.
	 */
	public function solicitar_supresion( \WP_REST_Request $request ): \WP_REST_Response {
		$member_id = Member_Auth::get_current_member_id();
		$fecha     = \Convoca\Members\GDPR_Tools::solicitar_supresion( $member_id );

		if ( '' === $fecha ) {
			return new \WP_REST_Response(
				array(
					'ok'      => false,
					'mensaje' => __( 'No se ha podido registrar la solicitud. Escríbenos y lo arreglamos.', 'convoca-members' ),
				),
				500
			);
		}

		return new \WP_REST_Response(
			array(
				'ok'      => true,
				'fecha'   => $fecha,
				'mensaje' => __( 'Solicitud registrada. La junta la revisará y borrará tus datos personales; recibirás aviso cuando esté.', 'convoca-members' ),
			)
		);
	}

	/**
	 * Get payments from convoca-gateway.
	 */
	public function get_payments( \WP_REST_Request $request ): \WP_REST_Response {
		$member_id = Member_Auth::get_current_member_id();

		$args = array(
			'post_type'      => 'pago',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'   => '_convoca_origin',
					'value' => 'members',
				),
				array(
					'key'   => '_convoca_origin_id',
					'value' => $member_id,
				),
			),
		);

		$query = new \WP_Query( $args );
		$items = array();

		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post ) {
				$status = get_post_meta( $post->ID, '_convoca_status', true );
				$amount = (int) get_post_meta( $post->ID, '_convoca_amount_cents', true );

				// El recibo (members#8 del gateway): el número que ya se guardaba y, si el pago está cobrado, el
				// enlace público del gateway para verlo o imprimirlo. El socio lo tenía solo si no perdía el
				// correo: cuando se perdía, acababa pidiéndoselo a la junta por WhatsApp.
				$recibo     = (string) get_post_meta( $post->ID, '_convoca_receipt_number', true );
				$recibo_url = ( 'paid' === $status ) ? self::url_recibo( (int) $post->ID ) : '';

				$items[] = array(
					'id'         => $post->ID,
					'fecha'      => get_the_date( 'd/m/Y', $post->ID ),
					'concepto'   => get_post_meta( $post->ID, '_convoca_product_desc', true ),
					'importe'    => number_format( $amount / 100, 2, ',', '.' ),
					'estado'     => $status,
					'recibo'     => $recibo,
					'recibo_url' => $recibo_url,
				);
			}
		}

		return new \WP_REST_Response(
			array(
				'items' => $items,
				// La pantalla que enseña el botón de renovar necesita saber hasta cuándo está pagada la
				// cuota y si ya hay un pago esperando (members#38). Va AQUÍ, en «Pagos y Cuotas», que es
				// donde está el botón; el primer intento lo puse en el endpoint de inscripciones.
				'cuota' => self::estado_cuota_panel( (int) $member_id ),
			)
		);
	}

	/**
	 * La sección «Mis turnos» del panel (shifts#19).
	 *
	 * El panel del socio no tenía nada de turnos, así que quien quería apuntarse tenía que encontrar la página
	 * del cuadrante por su cuenta. Aquí se trae el cuadrante, sus próximos turnos y la caja de suscripción a su
	 * calendario `.ics`, **reutilizando los shortcodes del plugin de turnos**: es él quien sabe de turnos, y
	 * duplicar su lógica aquí sería tener dos versiones de la verdad.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_turnos(): \WP_REST_Response {
		if ( ! shortcode_exists( 'convoca_proximos_turnos' ) ) {
			return new \WP_REST_Response(
				array(
					'success' => true,
					'html'    => '<h2>📅 ' . esc_html__( 'Mis turnos', 'convoca-members' ) . '</h2>'
						. '<p class="text-muted">' . esc_html__( 'Los turnos no están disponibles en este momento.', 'convoca-members' ) . '</p>',
					'turnos'  => false,
				)
			);
		}

		$html = '<h2>📅 ' . esc_html__( 'Mis turnos', 'convoca-members' ) . '</h2>';

		// A lo que ya está apuntado, lo primero.
		$html .= '<section class="conv-turnos-proximos">' . do_shortcode( '[convoca_proximos_turnos]' ) . '</section>';

		// El cuadrante, para ver los huecos libres de esta semana.
		if ( shortcode_exists( 'convoca_calendario' ) ) {
			$html .= '<section class="conv-turnos-cuadrante"><h3>' . esc_html__( 'Cuadrante del centro', 'convoca-members' ) . '</h3>'
				. do_shortcode( '[convoca_calendario]' ) . '</section>';
		}

		// Y su calendario, para suscribirlo desde aquí (antes solo estaba en una caja de una página suelta).
		if ( shortcode_exists( 'convoca_suscripcion_turnos' ) ) {
			$html .= '<section class="conv-turnos-calendario"><h3>' . esc_html__( 'Tu calendario', 'convoca-members' ) . '</h3>'
				. do_shortcode( '[convoca_suscripcion_turnos]' ) . '</section>';
		}

		return new \WP_REST_Response(
			array(
				'success' => true, 'html' => $html, 'turnos' => true
			) 
		);
	}

	/**
	 * Get volunteering hours.
	 */
	public function get_hours( \WP_REST_Request $request ): \WP_REST_Response {
		$member_id = Member_Auth::get_current_member_id();

		$args = array(
			'post_type'      => 'registro_hora',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'   => '_convoca_member_id',
					'value' => $member_id,
				),
			),
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$query       = new \WP_Query( $args );
		$items       = array();
		$total_horas = 0;

		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post ) {
				$horas        = (float) get_post_meta( $post->ID, '_convoca_horas', true );
				$estado       = get_post_meta( $post->ID, '_convoca_estado', true ) ?: 'pendiente';
				$actividad_id = get_post_meta( $post->ID, '_convoca_actividad_id', true );
				$proyecto_id  = get_post_meta( $post->ID, '_convoca_proyecto_id', true );
				$tareas       = get_post_meta( $post->ID, '_convoca_tareas', true );

				if ( $estado === 'aprobada' ) {
					$total_horas += $horas;
				}

				$items[] = array(
					'id'           => $post->ID,
					'fecha'        => get_post_meta( $post->ID, '_convoca_fecha', true ) ?: get_the_date( 'd/m/Y', $post->ID ),
					'descripcion'  => $post->post_content,
					'horas'        => $horas,
					'estado'       => $estado,
					'actividad'    => $actividad_id ? get_the_title( $actividad_id ) : '',
					'actividad_id' => $actividad_id,
					'proyecto'     => $proyecto_id ? get_the_title( $proyecto_id ) : '',
					'proyecto_id'  => $proyecto_id,
					'tareas'       => $tareas,
					'nota_admin'   => get_post_meta( $post->ID, '_convoca_nota_admin', true ) ?: '',
				);
			}
		}

		return new \WP_REST_Response(
			array(
				'items'       => $items,
				'total_horas' => $total_horas,
			)
		);
	}

	/**
	 * Submit new hours record.
	 */
	public function submit_hours( \WP_REST_Request $request ): \WP_REST_Response {
		$member_id = Member_Auth::get_current_member_id();
		$data      = array(
			'miembro_id'   => $member_id,
			'fecha'        => sanitize_text_field( $request->get_param( 'fecha' ) ),
			'descripcion'  => sanitize_textarea_field( $request->get_param( 'descripcion' ) ),
			'horas'        => (float) $request->get_param( 'horas' ),
			'actividad_id' => (int) $request->get_param( 'actividad_id' ),
			'proyecto_id'  => (int) $request->get_param( 'proyecto_id' ),
			'tareas'       => sanitize_textarea_field( $request->get_param( 'tareas' ) ),
		);

		$post_id = Hours_Manager::save_hours_record( $data, false );

		if ( is_wp_error( $post_id ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => $post_id->get_error_message(),
				),
				400
			);
		}

		// Notification to admin via action.
		\Convoca\Core\Utils::do_action( 'convoca_members_hours_submitted', 'convoca_hours_submitted', $post_id, $member_id );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'id'      => $post_id,
			)
		);
	}

	/**
	 * Get active projects for dropdown.
	 */
	public function get_proyectos(): \WP_REST_Response {
		if ( ! \Convoca\Core\Utils::check_rate_limit( 'convoca_get_proyectos', 30, 60 ) ) {
			return new \WP_REST_Response( array( 'error' => __( 'Demasiadas peticiones.', 'convoca-members' ) ), 429 );
		}

		$items = \Convoca\Core\Utils::rest_cache_get(
			'proyectos',
			120,
			function () {
				$proyectos = CPT_Proyecto::get_active_proyectos();
				$items     = array();
				foreach ( $proyectos as $id => $title ) {
					$items[] = array(
						'id'    => $id,
						'title' => $title,
					);
				}
				return $items;
			}
		);

		return new \WP_REST_Response( $items );
	}

	/**
	 * Admin: search members by name or email (autocomplete).
	 */
	/**
	 * Unified full-text search across members and activities.
	 *
	 * GET /convoca-members/v1/search?q=...&type=members|activities&limit=20
	 *
	 * Public endpoint — returns only non-sensitive data.
	 * Members search requires auth to see personal info.
	 * Activities search is public.
	 */
	public function fulltext_search( \WP_REST_Request $req ): \WP_REST_Response {
		$q     = sanitize_text_field( $req->get_param( 'q' ) ?? '' );
		$type  = sanitize_text_field( $req->get_param( 'type' ) ?? '' ); // Valores admitidos: members, activities, o vacio (ambos).
		$limit = min( absint( $req->get_param( 'limit' ) ?: 20 ), 50 );

		// Seguridad: la búsqueda de miembros (datos personales) exige sesión de socio/admin.
		$is_member_auth = Member_Auth::is_authenticated();
		if ( ! $is_member_auth ) {
			$type = 'activities'; // Los anónimos solo consultan actividades públicas.
		}

		if ( strlen( $q ) < 2 ) {
			return new \WP_REST_Response(
				array(
					'results' => array(),
					'total'   => 0,
					'query'   => $q,
				)
			);
		}

		$results = array();

		// ── Search members (requires member/admin session) ─────────────
		if ( ( ! $type || $type === 'members' ) && $is_member_auth ) {
			$member_args  = array(
				'post_type'      => 'miembro',
				'post_status'    => 'any',
				'posts_per_page' => $limit,
				's'              => $q,
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'     => '_convoca_email',
						'value'   => $q,
						'compare' => 'LIKE',
					),
					array(
						'key'     => '_convoca_dni',
						'value'   => $q,
						'compare' => 'LIKE',
					),
					array(
						'key'     => '_convoca_telefono',
						'value'   => $q,
						'compare' => 'LIKE',
					),
					array(
						'key'     => '_convoca_numero_socio',
						'value'   => $q,
						'compare' => 'LIKE',
					),
					array(
						'key'     => '_convoca_access_code',
						'value'   => $q,
						'compare' => 'LIKE',
					),
					array(
						'key'     => '_convoca_nombre',
						'value'   => $q,
						'compare' => 'LIKE',
					),
				),
			);
			$member_query = new \WP_Query( $member_args );
			foreach ( $member_query->posts as $p ) {
				$results[] = array(
					'type'  => 'member',
					'id'    => $p->ID,
					'title' => $p->post_title,
					// Sin datos personales: el detalle se obtiene vía /me (autenticado) o el admin.
					'url'   => rest_url( 'convoca-members/v1/me' ),
				);
			}
		}

		// ── Search activities (publicly readable) ───────────────────
		if ( ! $type || $type === 'activities' ) {
			$act_args  = array(
				'post_type'      => 'actividad',
				'post_status'    => array( 'publish', 'future' ),
				'posts_per_page' => $type === 'activities' ? $limit : max( 1, $limit - count( $results ) ),
				's'              => $q,
				'orderby'        => 'date',
				'order'          => 'DESC',
			);
			$act_query = new \WP_Query( $act_args );
			foreach ( $act_query->posts as $p ) {
				$fecha     = get_post_meta( $p->ID, '_convoca_fecha_inicio', true );
				$results[] = array(
					'type'      => 'activity',
					'id'        => $p->ID,
					'title'     => $p->post_title,
					'fecha'     => $fecha ? \Convoca\Core\Utils::format_date( $fecha, 'd/m/Y H:i' ) : '',
					'ubicacion' => get_post_meta( $p->ID, '_convoca_ubicacion', true ),
					'url'       => get_permalink( $p->ID ),
				);
			}
		}

		// Sort: members before activities, then by title.
		usort(
			$results,
			function ( $a, $b ) {
				if ( $a['type'] !== $b['type'] ) {
					return $a['type'] === 'member' ? -1 : 1;
				}
				return strcmp( $a['title'], $b['title'] );
			}
		);

		// Trim to limit after sort.
		$results = array_slice( $results, 0, $limit );

		return new \WP_REST_Response(
			array(
				'results' => $results,
				'total'   => count( $results ),
				'query'   => $q,
			)
		);
	}

	/**
	 * Admin: search members by name or email (autocomplete).
	 */
	public function admin_search_members( \WP_REST_Request $req ): \WP_REST_Response {
		$term = sanitize_text_field( $req->get_param( 'term' ) ?? '' );
		if ( strlen( $term ) < 2 ) {
			return new \WP_REST_Response( array(), 200 );
		}

		$args    = array(
			'post_type'      => 'miembro',
			'post_status'    => 'any',
			'posts_per_page' => 20,
			's'              => $term,
		);
		$query   = new \WP_Query( $args );
		$results = array();
		foreach ( $query->posts as $p ) {
			$results[] = array(
				'id'    => $p->ID,
				'title' => $p->post_title,
				'email' => get_post_meta( $p->ID, '_convoca_email', true ),
			);
		}
		return new \WP_REST_Response( $results, 200 );
	}



	/**
	 * Get certificate download URL and info.
	 */
	public function get_certificate(): \WP_REST_Response {
		$member_id = Member_Auth::get_current_member_id();
		$post      = get_post( $member_id );

		if ( ! $post || $post->post_type !== 'miembro' ) {
			return new \WP_REST_Response( array( 'error' => __( 'Miembro no encontrado.', 'convoca-members' ) ), 404 );
		}

		// Verify completion by recalculating actual hours (not just meta).
		$horas_aprobadas = Voluntariado_Manager::get_horas_aprobadas( $member_id );
		$objetivo        = Voluntariado_Manager::get_horas_objetivo( $member_id );
		$completado      = $horas_aprobadas >= $objetivo;

		if ( ! $completado ) {
			return new \WP_REST_Response(
				array(
					'success'  => false,
					'message'  => __( 'Aún no has completado las horas mínimas requeridas para generar el certificado.', 'convoca-members' ),
					'progress' => array(
						'aprobadas' => $horas_aprobadas,
						'objetivo'  => $objetivo,
					),
				),
				400
			);
		}

		$cert_id = get_post_meta( $member_id, '_convoca_certificado_id', true );

		return new \WP_REST_Response(
			array(
				'success'        => true,
				'certificado_id' => $cert_id,
				'horas'          => $horas_aprobadas,
				'objetivo'       => $objetivo,
				'download_url'   => rest_url( 'convoca-members/v1/me/certificate/download' ),
			)
		);
	}

	/**
	 * Download certificate as PDF.
	 */
	public function download_certificate(): void {
		$member_id = Member_Auth::get_current_member_id();
		$post      = get_post( $member_id );

		if ( ! $member_id || ! $post || $post->post_type !== 'miembro' ) {
			wp_send_json_error( __( 'Miembro no encontrado o no autorizado', 'convoca-members' ), 404 );
		}

		Certificate_Generator::serve_pdf( $member_id );
	}

	/**
	 * Process unsubscribe request.
	 */
	public function unsubscribe_request( \WP_REST_Request $request ): \WP_REST_Response {
		$member_id = Member_Auth::get_current_member_id();

		// Log the request and notify admin.
		Estados::change( $member_id, 'baja_solicitada', 'Solicitud de baja desde el panel de socio.' );

		// Action for Email Manager to hook into.
		\Convoca\Core\Utils::do_action( 'convoca_members_unsubscribe_request', 'convoca_member_unsubscribe_request', $member_id );

		return new \WP_REST_Response( array( 'success' => true ) );
	}

	/**
	 * Actividades publicadas, para el selector de horas del panel del socio.
	 *
	 * El panel pide `/activities` desde siempre y el endpoint no existía: su `Promise.all` fallaba entero
	 * y la pestaña de Voluntariado no cargaba nunca.
	 */
	public function get_activities(): \WP_REST_Response {
		$ids   = get_posts(
			array(
				'post_type'      => 'actividad',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'fields'         => 'ids',
			)
		);
		$items = array();
		foreach ( $ids as $id ) {
			$items[] = array(
				'id'    => (int) $id,
				'title' => get_the_title( $id ),
			);
		}

		return new \WP_REST_Response( $items, 200 );
	}

	/**
	 * Get member card HTML.
	 */
	public function get_card( \WP_REST_Request $request ): \WP_REST_Response {
		$member_id = Member_Auth::get_current_member_id();
		$theme     = sanitize_key( (string) $request->get_param( 'theme' ) );
		$html      = PDF_Card::get_html( $member_id, $theme );
		return new \WP_REST_Response( array( 'html' => $html ) );
	}

	/**
	 * Get member notifications.
	 */
	public function get_member_notifications( \WP_REST_Request $request ): \WP_REST_Response {
		$member_id = Member_Auth::get_current_member_id();
		$limit     = (int) $request->get_param( 'limit' ) ?: 10;

		$notifications = \Convoca\Core\Notifications::get_member( $member_id, $limit );
		$unread_count  = \Convoca\Core\Notifications::count_member_unread( $member_id );
		$all           = get_post_meta( $member_id, '_convoca_notifications', true ) ?: array();

		return new \WP_REST_Response(
			array(
				'items'  => $notifications,
				'unread' => $unread_count,
				'total'  => count( $all ),
			)
		);
	}

	/**
	 * Mark a notification as read.
	 */
	public function mark_notification_read( \WP_REST_Request $request ): \WP_REST_Response {
		$member_id       = Member_Auth::get_current_member_id();
		$notification_id = sanitize_text_field( $request->get_param( 'id' ) ?? '' );

		if ( empty( $notification_id ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Falta ID de notificación.', 'convoca-members' ),
				),
				400
			);
		}

		\Convoca\Core\Notifications::mark_member_read( $member_id, $notification_id );

		return new \WP_REST_Response( array( 'success' => true ) );
	}

	/**
	 * Mark all notifications as read.
	 */
	public function mark_all_notifications_read( \WP_REST_Request $request ): \WP_REST_Response {
		$member_id = Member_Auth::get_current_member_id();
		\Convoca\Core\Notifications::mark_member_all_read( $member_id );
		return new \WP_REST_Response( array( 'success' => true ) );
	}

	/**
	 * Serve a document PDF securely.
	 */
	public function get_document( \WP_REST_Request $request ): void {
		$id   = (int) $request['id'];
		$post = get_post( $id );

		if ( ! $post || $post->post_type !== 'convoca_documento' ) {
			wp_die( esc_html__( 'Documento no encontrado.', 'convoca-members' ), esc_html__( 'Error', 'convoca-members' ), array( 'response' => 404 ) );
		}

		$user_id   = (int) get_post_meta( $id, '_convoca_usuario_id', true );
		$member_id = Member_Auth::get_current_member_id();
		// El documento puede estar vinculado al WP user del socio, o al propio miembro (CPT).
		$member_user_id = 0;
		if ( $member_id ) {
			$member_user_id = (int) get_post_meta( $member_id, '_convoca_usuario_id', true );
		}
		$wp_user_id = get_current_user_id();

		// Admin OR propietario real. La sesión de socio (cookie propia) NO setea el WP user,
		// por eso comparamos contra el miembro autenticado y su user WP vinculado.
		$is_owner = ( $user_id > 0 && ( $user_id === $wp_user_id || ( $member_user_id > 0 && $user_id === $member_user_id ) ) )
			|| ( 0 === $user_id && $member_id > 0 && (int) get_post_meta( $id, '_convoca_member_id', true ) === $member_id );

		if ( ! current_user_can( 'manage_options' ) && ! $is_owner ) {
			wp_die( esc_html__( 'No tienes permiso para ver este documento.', 'convoca-members' ), esc_html__( 'Acceso Denegado', 'convoca-members' ), array( 'response' => 403 ) );
		}

		$filepath = get_post_meta( $id, '_convoca_documento_path', true );

		if ( ! $filepath || ! file_exists( $filepath ) ) {
			wp_die( esc_html__( 'El archivo físico no existe en el servidor.', 'convoca-members' ), esc_html__( 'Error', 'convoca-members' ), array( 'response' => 404 ) );
		}

		// Path traversal protection: ensure file is within the safe documents directory.
		$real_path = realpath( $filepath );
		$safe_base = realpath( wp_upload_dir()['basedir'] . '/convoca-documentos' );
		if ( $real_path === false || $safe_base === false || ! str_starts_with( $real_path, $safe_base ) ) {
			\Convoca\Core\Logger::warning( "Intento de path traversal detectado: $filepath", 'Members/Security' );
			wp_die( esc_html__( 'Archivo no válido.', 'convoca-members' ), esc_html__( 'Acceso Denegado', 'convoca-members' ), array( 'response' => 403 ) );
		}

		// Clear output buffer to avoid corruption.
		if ( ob_get_level() ) {
			ob_end_clean();
		}

		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: inline; filename="' . basename( $filepath ) . '"' );
		header( 'Content-Length: ' . filesize( $filepath ) );
		header( 'Cache-Control: private, max-age=0, must-revalidate' );
		header( 'Pragma: public' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- PDF binary output, no WP_Filesystem equivalent.
		readfile( $filepath );
		exit;
	}

	/**
	 * Renew membership: creates a payment for one more year.
	 */
	public function renew_membership( \WP_REST_Request $request ): \WP_REST_Response {
		$member_id = Member_Auth::get_current_member_id();
		$miembro   = get_post( $member_id );

		if ( ! $miembro || $miembro->post_type !== 'miembro' ) {
			return new \WP_REST_Response( array( 'error' => __( 'Miembro no encontrado.', 'convoca-members' ) ), 404 );
		}

		$plan_key  = get_post_meta( $member_id, '_convoca_plan', true ) ?: get_post_meta( $member_id, '_convoca_sub_plan', true );
		$plan_data = CPT_Miembro::get_plan( $plan_key );

		if ( ! $plan_data ) {
			return new \WP_REST_Response( array( 'error' => __( 'No se encontró el plan de la membresía.', 'convoca-members' ) ), 400 );
		}

		$importe = (float) ( get_post_meta( $member_id, '_convoca_importe_cuota', true ) ?: ( $plan_data['price'] ?? 0 ) );

		if ( $importe <= 0 ) {
			return new \WP_REST_Response( array( 'error' => __( 'Este plan no requiere pago.', 'convoca-members' ) ), 400 );
		}

		if ( ! \Convoca\Core\Features::is_gateway_active() || ! function_exists( 'Convoca\Gateway\convoca_gateway_create_payment' ) ) {
			return new \WP_REST_Response( array( 'error' => __( 'La pasarela de pago no está disponible.', 'convoca-members' ) ), 503 );
		}

		$payment = \Convoca\Gateway\convoca_gateway_create_payment(
			array(
				'amount_cents' => (int) round( $importe * 100 ),
				'method'       => 'tarjeta',
				'origin'       => 'members',
				'origin_id'    => $member_id,
				'product_desc' => mb_substr( 'RENOVACIÓN ' . strtoupper( get_bloginfo( 'name' ) ) . ' - ' . strtoupper( $plan_data['label'] ?? 'SOCIO' ), 0, 125 ),
				'payer_email'  => get_post_meta( $member_id, '_convoca_email', true ),
				'redirect_ok'  => home_url( '/pago-ok/' ),
				'redirect_ko'  => home_url( '/pago-ko/' ),
			)
		);

		if ( is_wp_error( $payment ) ) {
			return new \WP_REST_Response( array( 'error' => $payment->get_error_message() ), 400 );
		}

		update_post_meta( $member_id, '_convoca_pago_id', $payment['pago_id'] );
		\Convoca\Core\Logger::info( "Renovación manual solicitada (Pago #{$payment['pago_id']}) para el miembro #$member_id.", 'Members/Renewal', $member_id );

		return new \WP_REST_Response(
			array(
				'success'     => true,
				'payment_url' => $payment['payment_url'],
				'pago_id'     => $payment['pago_id'],
			)
		);
	}

	/**
	 * Update member profile fields.
	 *
	 * Rules:
	 * - email: only applied after confirmation via link (pending email flow).
	 * - cumpleaños: only settable once, never overwritten.
	 * - dirección / teléfono: freely updatable.
	 */
	public function update_profile( \WP_REST_Request $request ): \WP_REST_Response {
		$member_id = Member_Auth::get_current_member_id();
		$miembro   = get_post( $member_id );

		if ( ! $miembro || $miembro->post_type !== 'miembro' ) {
			return new \WP_REST_Response( array( 'error' => __( 'Miembro no encontrado.', 'convoca-members' ) ), 404 );
		}

		$changes = array();

		// ── Dirección ──
		$direccion = sanitize_text_field( $request->get_param( 'direccion' ) ?? '' );
		if ( $request->get_param( 'direccion' ) !== null ) {
			update_post_meta( $member_id, '_convoca_direccion', $direccion );
			$changes[] = 'direccion';
		}

		// ── Teléfono ──
		$telefono = sanitize_text_field( $request->get_param( 'telefono' ) ?? '' );
		if ( $request->get_param( 'telefono' ) !== null ) {
			update_post_meta( $member_id, '_convoca_telefono', $telefono );
			$changes[] = 'telefono';
		}

		// ── Cumpleaños (una sola vez) ──
		$cumpleanos = sanitize_text_field( $request->get_param( 'cumpleanos' ) ?? '' );
		$actual_c   = get_post_meta( $member_id, '_convoca_cumpleanos', true );
		if ( $request->get_param( 'cumpleanos' ) !== null && ! empty( $cumpleanos ) ) {
			if ( ! empty( $actual_c ) ) {
				return new \WP_REST_Response(
					array(
						'error'   => __( 'El cumpleaños ya está establecido y no puede modificarse.', 'convoca-members' ),
						'changes' => $changes,
					),
					400
				);
			}
			// Validate YYYY-MM-DD and not in the future.
			$ts = strtotime( $cumpleanos );
			if ( $ts === false || $ts > strtotime( 'today' ) ) {
				return new \WP_REST_Response( array( 'error' => __( 'Fecha de cumpleaños no válida.', 'convoca-members' ) ), 400 );
			}
			update_post_meta( $member_id, '_convoca_cumpleanos', gmdate( 'Y-m-d', $ts ) );
			$changes[] = 'cumpleanos';
		}

		// ── Email (solo tras confirmación) ──
		$nuevo_email  = sanitize_email( $request->get_param( 'email' ) ?? '' );
		$email_actual = get_post_meta( $member_id, '_convoca_email', true );
		if ( $request->get_param( 'email' ) !== null && ! empty( $nuevo_email ) && $nuevo_email !== $email_actual ) {
			if ( ! is_email( $nuevo_email ) ) {
				return new \WP_REST_Response( array( 'error' => __( 'Email no válido.', 'convoca-members' ) ), 400 );
			}

			// Check not used by another member.
			$duplicate = get_posts(
				array(
					'post_type'      => 'miembro',
					'posts_per_page' => 1,
					'meta_query'     => array(
						array(
							'key'   => '_convoca_email',
							'value' => $nuevo_email,
						),
					),
					'fields'         => 'ids',
					'exclude'        => array( $member_id ),
				)
			);
			if ( ! empty( $duplicate ) ) {
				return new \WP_REST_Response( array( 'error' => __( 'Ese email ya está en uso por otro socio.', 'convoca-members' ) ), 400 );
			}

			// Generate confirmation token (24h validity).
			$token = wp_generate_password( 32, false );
			update_post_meta( $member_id, '_convoca_email_pendiente', $nuevo_email );
			update_post_meta( $member_id, '_convoca_email_token', $token );
			update_post_meta( $member_id, '_convoca_email_token_exp', time() + DAY_IN_SECONDS );

			// Send confirmation link.
			$confirm_url = add_query_arg(
				array(
					'convoca_confirm_email' => 1,
					'member'                => $member_id,
					'token'                 => $token,
				),
				home_url( '/mi-cuenta/' )
			);
			\Convoca\Core\Utils::do_action( 'convoca_members_email_confirm', 'convoca_email_confirm', $member_id, $confirm_url, $nuevo_email );

			$changes[] = 'email_pendiente';
		}

		return new \WP_REST_Response(
			array(
				'success'         => true,
				'changes'         => $changes,
				'email_pendiente' => ! empty( get_post_meta( $member_id, '_convoca_email_pendiente', true ) ),
			)
		);
	}

	/**
	 * Confirm a pending email change via token.
	 */
	public function confirm_email( \WP_REST_Request $request ): \WP_REST_Response {
		$member_id = Member_Auth::get_current_member_id();
		$token     = sanitize_text_field( $request->get_param( 'token' ) ?? '' );
		$stored    = get_post_meta( $member_id, '_convoca_email_token', true );
		$exp       = (int) get_post_meta( $member_id, '_convoca_email_token_exp', true );
		$pendiente = get_post_meta( $member_id, '_convoca_email_pendiente', true );

		if ( empty( $token ) || empty( $stored ) || ! hash_equals( $stored, $token ) ) {
			return new \WP_REST_Response( array( 'error' => __( 'Token de confirmación no válido.', 'convoca-members' ) ), 400 );
		}
		if ( time() > $exp ) {
			return new \WP_REST_Response( array( 'error' => __( 'El enlace de confirmación ha caducado. Solicítalo de nuevo.', 'convoca-members' ) ), 400 );
		}

		// Apply the email and clean up.
		update_post_meta( $member_id, '_convoca_email', $pendiente );
		delete_post_meta( $member_id, '_convoca_email_pendiente' );
		delete_post_meta( $member_id, '_convoca_email_token' );
		delete_post_meta( $member_id, '_convoca_email_token_exp' );

		\Convoca\Core\Utils::do_action( 'convoca_members_email_cambiado', 'convoca_email_cambiado', $member_id, $pendiente );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'email'   => $pendiente,
			)
		);
	}

	/**
	 * Start phone verification: sends a confirmation link to the member's email.
	 */
	public function verify_phone( \WP_REST_Request $request ): \WP_REST_Response {
		$member_id = Member_Auth::get_current_member_id();
		$miembro   = get_post( $member_id );

		if ( ! $miembro || $miembro->post_type !== 'miembro' ) {
			return new \WP_REST_Response( array( 'error' => __( 'Miembro no encontrado.', 'convoca-members' ) ), 404 );
		}

		$telefono = get_post_meta( $member_id, '_convoca_telefono', true );
		if ( empty( $telefono ) ) {
			return new \WP_REST_Response(
				array( 'error' => __( 'Guarda primero tu número de teléfono en el perfil.', 'convoca-members' ) ),
				400
			);
		}

		// Already verified? no-op success.
		if ( '1' === get_post_meta( $member_id, '_convoca_telefono_verificado', true ) ) {
			return new \WP_REST_Response(
				array(
					'success'  => true,
					'verified' => true,
				)
			);
		}

		// Generate confirmation token (24h).
		$token = wp_generate_password( 24, false );
		update_post_meta( $member_id, '_convoca_telefono_token', $token );
		update_post_meta( $member_id, '_convoca_telefono_token_exp', time() + DAY_IN_SECONDS );

		$confirm_url = add_query_arg(
			array(
				'convoca_confirm_phone' => 1,
				'member'                => $member_id,
				'token'                 => $token,
			),
			home_url( '/mi-cuenta/' )
		);

		// Send the confirmation link to the member's email.
		\Convoca\Core\Utils::do_action( 'convoca_members_email_verify_phone', 'convoca_email_verify_phone', $member_id, $confirm_url, $telefono );

		return new \WP_REST_Response(
			array(
				'success'  => true,
				'verified' => false,
				'email'    => get_post_meta( $member_id, '_convoca_email', true ),
			)
		);
	}
}
