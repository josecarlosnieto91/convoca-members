<?php

/**
 * Convoca Members
 *
 * @package    Convoca\Members
 * @subpackage Public
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
 * Multi-step member registration form (shortcode [convoca_alta_socio]).
 *
 * @package Convoca\Members
 */

namespace Convoca\Members;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Form_Handler {


	/**
	 * Acción del formulario sin JavaScript.
	 */
	public const ACCION_SIN_JS = 'convoca_alta_sin_js';

	/**
	 * Nonce del formulario sin JavaScript.
	 */
	public const NONCE_SIN_JS = 'convoca_alta_sin_js_nonce';

	public function __construct() {
		add_shortcode( 'convoca_alta_socio', array( $this, 'render' ) );

		// Camino sin JavaScript: el POST llega a admin-post y se procesa aquí (members#86).
		add_action( 'admin_post_convoca_alta_sin_js', array( $this, 'handle_sin_js' ) );
		add_action( 'admin_post_nopriv_convoca_alta_sin_js', array( $this, 'handle_sin_js' ) );
		add_action( 'wp_ajax_convoca_alta_submit', array( $this, 'handle_submit' ) );
		add_action( 'wp_ajax_nopriv_convoca_alta_submit', array( $this, 'handle_submit' ) );

		// Dynamic nonce endpoint (bypass cache) + plans data.
		add_action( 'wp_ajax_convoca_get_nonce', array( $this, 'ajax_get_nonce' ) );
		add_action( 'wp_ajax_nopriv_convoca_get_nonce', array( $this, 'ajax_get_nonce' ) );

		// Register REST route for form submission.
		add_action(
			'rest_api_init',
			function () {
				register_rest_route(
					'convoca-members/v1',
					'/alta',
					array(
						'methods'             => 'POST',
						'callback'            => array( self::class, 'handle_submit_rest' ),
						'permission_callback' => '__return_true',
					)
				);
			}
		);
	}

	/**
	 * Render the multi-step form via template.
	 */
	/**
	 * Procesa el alta enviada sin JavaScript.
	 *
	 * @return void
	 */
	public function handle_sin_js(): void {
		$nonce = isset( $_POST['_convoca_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_convoca_nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::NONCE_SIN_JS ) ) {
			wp_die(
				esc_html__( 'La petición ha caducado. Vuelve a intentarlo.', 'convoca-members' ),
				'',
				array( 'response' => 403 )
			);
		}

		$datos = wp_unslash( $_POST );
		unset( $datos['_convoca_nonce'], $datos['action'] );

		// El checkbox viaja como «on» o «1»: se normaliza para la validación.
		foreach ( array( 'rgpd', 'acepta_estatutos', 'acepta_reglamento' ) as $clave ) {
			if ( isset( $datos[ $clave ] ) ) {
				$datos[ $clave ] = '1';
			}
		}

		$resultado = Process_Member::register( $datos, $_FILES );

		$destino = wp_get_referer();

		if ( ! $destino ) {
			$destino = home_url( '/' );
		}

		if ( is_wp_error( $resultado ) ) {
			$destino = add_query_arg(
				array(
					'convoca_alta' => 'error',
					'motivo'       => rawurlencode( $resultado->get_error_message() ),
				),
				remove_query_arg( array( 'convoca_alta', 'motivo' ), $destino )
			);

			wp_safe_redirect( $destino );
			exit;
		}

		wp_safe_redirect( add_query_arg( 'convoca_alta', 'ok', remove_query_arg( 'convoca_alta', $destino ) ) );
		exit;
	}

	/**
	 * Pinta el formulario de alta.
	 *
	 * @return string
	 */
	public function render(): string {
		Mi_Area::enqueue_front_assets();

		wp_enqueue_style(
			'conv-members-public',
			CONVOCA_MEMBERS_URL . 'assets/css/convoca-members-public.css',
			array( 'convoca-core' ),
			CONVOCA_MEMBERS_VERSION
		);
		wp_enqueue_script(
			'conv-members-public',
			CONVOCA_MEMBERS_URL . 'assets/js/convoca-members-public.js',
			array( 'convoca-common-js' ),
			CONVOCA_MEMBERS_VERSION,
			true
		);
		$raw_gateway    = function_exists( 'Convoca\\Gateway\\convoca_get_gateway_settings' ) ? \Convoca\Gateway\convoca_get_gateway_settings() : array();
		$public_gateway = array_intersect_key( $raw_gateway, array_flip( array( 'iban', 'beneficiary', 'instructions' ) ) );

		$config = array(
			'restUrl'   => rest_url( 'convoca-members/v1/alta' ),
			'restNonce' => wp_create_nonce( 'wp_rest' ),
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'convoca_alta_nonce' ),
			'plans'     => CPT_Miembro::get_plans(),
			'gateway'   => $public_gateway,
		);

		ob_start();
		include CONVOCA_MEMBERS_DIR . 'templates/form-alta.php';
		// ob_get_clean() devuelve false si no había búfer: sin el cast, str_replace()
		// recibiría false y el HTML saldría vacío en silencio.
		$html = (string) ob_get_clean();

		// Inject data-config into the wrapper.
		$html = str_replace(
			'id="convoca-form-alta"',
			'id="convoca-form-alta" data-config=\'' . esc_attr( (string) wp_json_encode( $config ) ) . '\'',
			$html
		);

		// Render inside an HTML block so wpautop does not wrap the grid
		// labels in <p> tags (which breaks the plan grid layout).
		return do_blocks( '<!-- wp:html -->' . $html . '<!-- /wp:html -->' );
	}

	/**
	 * REST API callback for member registration.
	 * Validates custom nonce for public (non-authenticated) access.
	 */
	public static function handle_submit_rest( \WP_REST_Request $request ): \WP_REST_Response {
		// 1. Validate custom nonce (works for public users)
		$nonce = $request->get_header( 'X-Conv-Nonce' ) ?: $request->get_param( 'nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'convoca_alta_nonce' ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'data'    => array( 'errors' => array( __( 'Petición no autorizada (Nonce inválido)', 'convoca-members' ) ) ),
				),
				403
			);
		}

		// 1b. Rate limit, POR PERSONA (su correo) y por conexión como segundo criterio.
		//
		// Antes era solo por IP: varios errores de una persona dejaban fuera a todos los demás de su misma
		// conexión durante una hora —una sede, un telecentro, una familia— y el siguiente leía «Demasiados
		// intentos» sin saber que no era culpa suya (members#46).
		$datos_peticion = (array) ( $request->get_json_params() ?: $request->get_params() );
		$email_intento  = sanitize_email( (string) ( $datos_peticion['email'] ?? '' ) );

		if ( ! \Convoca\Core\Utils::check_rate_limit( 'alta_socio', 3, 3600, $email_intento ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'data'    => array( 'errors' => array( self::mensaje_bloqueo() ) ),
				),
				429
			);
		}

		// 2. Process via Central Logic
		$params = $request->get_params();
		$files  = $_FILES; // WP REST doesn't handle $_FILES naturally in all cases, but for this multipart it should be populated.

		$result = Process_Member::register( $params, $files );

		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'data'    => array( 'errors' => array( $result->get_error_message() ) ),
				),
				400
			);
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $result,
			),
			200
		);
	}

	/**
	 * AJAX form submission handler (legacy — kept for backward compat).
	 */
	/**
	 * El aviso de bloqueo por intentos repetidos (members#46).
	 *
	 * El mensaje viejo («Demasiados intentos de registro. Inténtalo de nuevo en una hora.») dejaba a la
	 * persona sin saber qué hacer ni adónde ir, y —cuando el bloqueo era por la conexión— le hacía creer que
	 * la culpa era suya. Ahora dice qué hacer y a quién escribir.
	 *
	 * @return string
	 */
	public static function mensaje_bloqueo(): string {
		$ajustes  = get_option( 'convoca_members_settings', array() );
		$contacto = (string) ( $ajustes['contact_email'] ?? '' );
		if ( '' === $contacto || ! is_email( $contacto ) ) {
			$contacto = (string) get_option( 'admin_email' );
		}

		return sprintf(
			/* translators: %s: contact email of the association. */
			__( 'Se han hecho varios intentos de registro desde tu conexión. Espera unos minutos y vuelve a probarlo. Si no eras tú, escríbenos a %s y lo miramos.', 'convoca-members' ),
			$contacto
		);
	}

	public function handle_submit(): void {
		check_ajax_referer( 'convoca_alta_nonce', 'nonce' );

		if ( ! \Convoca\Core\Utils::check_rate_limit( 'alta_socio', 3, 3600, sanitize_email( (string) ( $_POST['email'] ?? '' ) ) ) ) {
			wp_send_json_error( array( 'errors' => array( self::mensaje_bloqueo() ) ), 429 );
		}

		$result = Process_Member::register( wp_unslash( $_POST ), $_FILES );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'errors' => array( $result->get_error_message() ) ) );
		}

		do_action( 'convoca_member_created', $result['member_id'], $result );

		wp_send_json_success( $result );
	}

	/**
	 * Get fresh nonce and plans via AJAX.
	 * Bypasses page cache and ensures frontend has latest config.
	 */
	public function ajax_get_nonce(): void {
		// Rate limit: max 30 requests per hour per IP.
		if ( ! \Convoca\Core\Utils::check_rate_limit( 'convoca_get_nonce', 30, 3600 ) ) {
			wp_send_json_error( array( 'message' => __( 'Demasiadas peticiones. Inténtalo de nuevo más tarde.', 'convoca-members' ) ), 429 );
		}

		// Add headers to prevent caching of this response.
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
		header( 'Pragma: no-cache' );

		wp_send_json_success(
			array(
				'nonce'      => wp_create_nonce( 'convoca_alta_nonce' ),
				'rest_nonce' => wp_create_nonce( 'wp_rest' ),
				'plans'      => CPT_Miembro::get_plans(),
			)
		);
	}
}
