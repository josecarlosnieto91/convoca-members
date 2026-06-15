<?php
/**
 * Multi-step member registration form (shortcode [convoca_alta]).
 *
 * @package Convoca\Members
 */

namespace Convoca\Members;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Form_Handler {


	public function __construct() {
		add_shortcode( 'convoca_alta_socio', array( $this, 'render' ) );
		add_action( 'wp_ajax_conv_alta_submit', array( $this, 'handle_submit' ) );
		add_action( 'wp_ajax_nopriv_conv_alta_submit', array( $this, 'handle_submit' ) );

		// Dynamic nonce endpoint (bypass cache) + plans data.
		add_action( 'wp_ajax_conv_get_nonce', array( $this, 'ajax_get_nonce' ) );
		add_action( 'wp_ajax_nopriv_conv_get_nonce', array( $this, 'ajax_get_nonce' ) );

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
	public function render(): string {
		wp_enqueue_style(
			'conv-members-public',
			CONV_MEMBERS_URL . 'assets/css/convoca-members-public.css',
			array(),
			CONV_MEMBERS_VERSION
		);
		wp_enqueue_script(
			'conv-members-public',
			CONV_MEMBERS_URL . 'assets/js/convoca-members-public.js',
			array( 'convoca-common-js' ),
			CONV_MEMBERS_VERSION,
			true
		);
		$raw_gateway    = function_exists( 'Convoca\\Gateway\\conv_get_gateway_settings' ) ? \Convoca\Gateway\conv_get_gateway_settings() : array();
		$public_gateway = array_intersect_key( $raw_gateway, array_flip( array( 'iban', 'beneficiary', 'instructions' ) ) );

		$config = array(
			'restUrl'   => rest_url( 'convoca-members/v1/alta' ),
			'restNonce' => wp_create_nonce( 'wp_rest' ),
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'conv_alta_nonce' ),
			'plans'     => CPT_Miembro::get_plans(),
			'gateway'   => $public_gateway,
		);

		ob_start();
		include CONV_MEMBERS_DIR . 'templates/form-alta.php';
		$html = ob_get_clean();

		// Inject data-config into the wrapper.
		$html = str_replace(
			'id="convoca-form-alta"',
			'id="convoca-form-alta" data-config=\'' . esc_attr( wp_json_encode( $config ) ) . '\'',
			$html
		);

		return $html;
	}

	/**
	 * REST API callback for member registration.
	 * Validates custom nonce for public (non-authenticated) access.
	 */
	public static function handle_submit_rest( \WP_REST_Request $request ): \WP_REST_Response {
		// 1. Validate custom nonce (works for public users)
		$nonce = $request->get_header( 'X-Conv-Nonce' ) ?: $request->get_param( 'nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'conv_alta_nonce' ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'data'    => array( 'errors' => array( 'Petición no autorizada (Nonce inválido)' ) ),
				),
				403
			);
		}

		// 1b. Rate limit
		if ( ! \Convoca\Core\Utils::check_rate_limit( 'alta_socio', 3, 3600 ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'data'    => array( 'errors' => array( 'Demasiados intentos de registro. Inténtalo de nuevo en una hora.' ) ),
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
	public function handle_submit(): void {
		check_ajax_referer( 'conv_alta_nonce', 'nonce' );

		if ( ! \Convoca\Core\Utils::check_rate_limit( 'alta_socio', 3, 3600 ) ) {
			wp_send_json_error( array( 'errors' => array( 'Demasiados intentos de registro. Inténtalo de nuevo en una hora.' ) ), 429 );
		}

		$result = Process_Member::register( wp_unslash( $_POST ), $_FILES );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'errors' => array( $result->get_error_message() ) ) );
		}

		do_action( 'conv_member_created', $result['member_id'], $result );

		wp_send_json_success( $result );
	}

	/**
	 * Get fresh nonce and plans via AJAX.
	 * Bypasses page cache and ensures frontend has latest config.
	 */
	public function ajax_get_nonce(): void {
		// Rate limit: max 30 requests per hour per IP.
		if ( ! \Convoca\Core\Utils::check_rate_limit( 'conv_get_nonce', 30, 3600 ) ) {
			wp_send_json_error( array( 'message' => __( 'Demasiadas peticiones. Inténtalo de nuevo más tarde.', 'convoca-members' ) ), 429 );
		}

		// Add headers to prevent caching of this response.
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
		header( 'Pragma: no-cache' );

		wp_send_json_success(
			array(
				'nonce'      => wp_create_nonce( 'conv_alta_nonce' ),
				'rest_nonce' => wp_create_nonce( 'wp_rest' ),
				'plans'      => CPT_Miembro::get_plans(),
			)
		);
	}
}
