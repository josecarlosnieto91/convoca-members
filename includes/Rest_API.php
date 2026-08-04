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
	private const RATE_LIMIT_MAX    = 10;
	private const RATE_LIMIT_WINDOW = 300; // 5 minutes

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

		// Inscriptions.
		register_rest_route(
			self::NAMESPACE,
			'/me/inscripciones',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_inscriptions' ),
				'permission_callback' => array( $this, 'check_active_member' ),
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

		// Volunteering hours.
		register_rest_route(
			self::NAMESPACE,
			'/me/horas',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_hours' ),
					'permission_callback' => array( $this, 'check_active_member' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'submit_hours' ),
					'permission_callback' => array( $this, 'check_active_member' ),
				),
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
	 */
	public static function check_member_auth(): bool {
		return Member_Auth::is_authenticated();
	}

	/**
	 * Check if a member is logged in AND is active.
	 */
	public static function check_active_member(): bool|\WP_Error {
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
	 * Login handler — WordPress credentials (username or email + password).
	 */
	public function login( \WP_REST_Request $request ): \WP_REST_Response {
		// Check rate limit before processing.
		if ( ! $this->check_rate_limit( 'login' ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Demasiados intentos. Intenta de nuevo en 5 minutos.', 'convoca-members' ),
				),
				429
			);
		}

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
	 * Check rate limit for an action.
	 * Uses transients to track attempts per IP.
	 */
	private function check_rate_limit( string $action ): bool {
		return \Convoca\Core\Utils::check_rate_limit( $action, self::RATE_LIMIT_MAX, self::RATE_LIMIT_WINDOW );
	}

	/**
	 * Get profile data.
	 */
	public function get_profile( \WP_REST_Request $request ): \WP_REST_Response {
		$member_id = Member_Auth::get_current_member_id();
		$post      = get_post( $member_id );

		if ( ! $post || $post->post_type !== 'miembro' ) {
			return new \WP_REST_Response( array( 'error' => __( 'Miembro no encontrado.', 'convoca-members' ) ), 404 );
		}

		$access_code = get_post_meta( $member_id, '_convoca_access_code', true );
		$masked_code = ! empty( $access_code ) ? substr( $access_code, 0, 4 ) . '****' : '';

		return new \WP_REST_Response(
			array(
				'id'     => $member_id,
				'nombre' => $post->post_title,
				'email'  => get_post_meta( $member_id, '_convoca_email', true ),
				'codigo' => $masked_code,
				'estado' => get_post_meta( $member_id, '_convoca_estado_miembro', true ),
				'tipo'   => (array) get_post_meta( $member_id, '_convoca_modalidad', true ) ?: array( 'Socio/a' ),
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

				$items[] = array(
					'id'       => $post->ID,
					'fecha'    => get_the_date( 'd/m/Y', $post->ID ),
					'concepto' => get_post_meta( $post->ID, '_convoca_product_desc', true ),
					'importe'  => number_format( $amount / 100, 2, ',', '.' ),
					'estado'   => $status,
				);
			}
		}

		return new \WP_REST_Response( array( 'items' => $items ) );
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
		$type  = sanitize_text_field( $req->get_param( 'type' ) ?? '' ); // 'members', 'activities', or '' (both)
		$limit = min( absint( $req->get_param( 'limit' ) ?: 20 ), 50 );

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

		// ── Search members (admin-accessible via REST) ──────────────
		if ( ! $type || $type === 'members' ) {
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
					'type'   => 'member',
					'id'     => $p->ID,
					'title'  => $p->post_title,
					'email'  => get_post_meta( $p->ID, '_convoca_email', true ),
					'estado' => get_post_meta( $p->ID, '_convoca_estado_miembro', true ),
					'url'    => rest_url( 'convoca-members/v1/me' ),
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
			return;
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
	 * Get member card HTML.
	 */
	public function get_card( \WP_REST_Request $request ): \WP_REST_Response {
		$member_id = Member_Auth::get_current_member_id();
		$html      = PDF_Card::get_html( $member_id );
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

		$user_id         = (int) get_post_meta( $id, '_convoca_usuario_id', true );
		$current_user_id = get_current_user_id();

		// Security check: Admin OR Owner.
		if ( ! current_user_can( 'manage_options' ) && $user_id !== $current_user_id ) {
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
}
