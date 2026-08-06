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
 * Public member panel: shortcode [convoca_mi_area].
 *
 * @package Convoca\Members
 */

namespace Convoca\Members;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mi_Area {

	public function __construct() {
		add_shortcode( 'convoca_mi_area', array( $this, 'render' ) );
		add_shortcode( 'convoca_mi_perfil', array( $this, 'render_perfil' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue CSS/JS.
	 */
	public function enqueue_assets(): void {
		wp_enqueue_style( 'convoca-mi-area', CONVOCA_MEMBERS_URL . 'public/assets/mi-area.css', array(), CONVOCA_MEMBERS_VERSION );
		wp_enqueue_script( 'convoca-mi-area', CONVOCA_MEMBERS_URL . 'public/assets/mi-area.js', array( 'convoca-common-js' ), CONVOCA_MEMBERS_VERSION, true );

		$member_id = Member_Auth::get_current_member_id();
		wp_localize_script(
			'convoca-mi-area',
			'convMiArea',
			array(
				'apiUrl'     => rest_url( 'convoca/v1' ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'isLoggedIn' => $member_id > 0,
				'memberId'   => $member_id,
				'messages'   => array(
					'loginSuccess' => __( 'Has iniciado sesión correctamente.', 'convoca-members' ),
					'loginError'   => __( 'Email o código incorrecto.', 'convoca-members' ),
					'saveSuccess'  => __( 'Datos guardados correctamente.', 'convoca-members' ),
					'hourSuccess'  => __( 'Registro de horas enviado a revisión.', 'convoca-members' ),
				),
			)
		);
	}

	/**
	 * Render the panel.
	 */
	public function render(): string {
		ob_start();
		$member_id = Member_Auth::get_current_member_id();

		echo '<div id="conv-mi-area" class="conv-mi-area" data-loading="true">';

		if ( $member_id > 0 ) {
			$this->render_dashboard( $member_id );
		} else {
			$this->render_login_form();
		}

		echo '</div>';

		return ob_get_clean();
	}

	/**
	 * Render Login Form.
	 */
	private function render_login_form(): void {
		?>
		<div class="conv-login-container card-glass">
			<h2 class="text-gradient">Acceso Socios</h2>
			<p><?php esc_html_e( 'Introduce tu usuario y contraseña para acceder a tu área privada.', 'convoca-members' ); ?></p>
			<form id="conv-login-form" class="conv-login-form">
				<div class="form-group">
					<label for="username"><?php esc_html_e( 'Usuario o Email', 'convoca-members' ); ?></label>
					<input type="text" id="username" name="username" required placeholder="ejemplo@correo.com">
				</div>
				<div class="form-group">
					<label for="password"><?php esc_html_e( 'Contraseña', 'convoca-members' ); ?></label>
					<input type="password" id="password" name="password" required minlength="4">
					<small><?php esc_html_e( 'Las credenciales las recibiste por email al ser aprobado.', 'convoca-members' ); ?></small>
					<br><small><a href="mailto:<?php echo esc_attr( get_bloginfo( 'admin_email' ) ); ?>"><?php esc_html_e( '¿No tienes acceso? Escríbenos.', 'convoca-members' ); ?></a></small>
				</div>
				<button type="submit" class="btn-primary"><?php esc_html_e( 'Entrar', 'convoca-members' ); ?></button>
				<div class="form-result"></div>
			</form>
		</div>
		<?php
	}

	/**
	 * Render Dashboard (React-like Skeleton).
	 */
	private function render_dashboard( int $member_id ): void {
		$post = get_post( $member_id );
		?>
		<div class="conv-dashboard-container">
			<header class="conv-panel-header">
				<div>
					<h1>¡Hola, <span><?php echo esc_html( $post->post_title ); ?></span>!</h1>
					<p class="subtitle"><?php esc_html_e( 'Bienvenido a tu panel de socio de Convoca.', 'convoca-members' ); ?></p>
				</div>
				<button id="conv-logout-btn" type="button" class="btn-link"><?php esc_html_e( 'Cerrar sesión', 'convoca-members' ); ?></button>
			</header>

			<div class="conv-panel-grid">
				<!-- Navigation -->
				<nav class="conv-panel-nav card-glass">
					<ul>
						<li class="active" data-tab="profile">👤 <?php esc_html_e( 'Mis Datos', 'convoca-members' ); ?></li>
						<li data-tab="membership">🪪 <?php esc_html_e( 'Carnet Digital', 'convoca-members' ); ?></li>
						<li data-tab="inscriptions">📝 <?php esc_html_e( 'Inscripciones', 'convoca-members' ); ?></li>
						<li data-tab="hours">⏳ <?php esc_html_e( 'Voluntariado', 'convoca-members' ); ?></li>
						<li data-tab="payments">💳 <?php esc_html_e( 'Pagos y Cuotas', 'convoca-members' ); ?></li>
						<li data-tab="search">🔍 <?php esc_html_e( 'Buscar', 'convoca-members' ); ?></li>
						<li data-tab="notifications" class="conv-notif-tab">🔔 <?php esc_html_e( 'Notificaciones', 'convoca-members' ); ?> <span class="conv-notif-badge" id="conv-notif-count" style="display:none">0</span></li>
					</ul>
				</nav>

				<!-- Content -->
				<main class="conv-panel-content card-glass" id="conv-main-content">
					<div class="conv-spinner"></div>
				</main>
			</div>
		</div>
		<?php
	}

	/**
	 * [convoca_mi_perfil] — Legacy profile shortcode (moved from theme).
	 * Shows the member record + active inscriptions for the logged-in user.
	 */
	public function render_perfil(): string {
		if ( ! is_user_logged_in() ) {
			return sprintf(
				'<div class="conv-profile-login">%s <a href="%s" class="button">%s</a></div>',
				__( 'Inicia sesión para ver tu perfil.', 'convoca-members' ),
				wp_login_url( get_permalink() ),
				__( 'Iniciar sesión', 'convoca-members' )
			);
		}

		$current_user = wp_get_current_user();
		$email        = $current_user->user_email;

		// 1. Get member record.
		$members = get_posts( array(
			'post_type'      => 'miembro',
			'posts_per_page' => 1,
			'meta_query'     => array(
				array( 'key' => '_convoca_email', 'value' => $email ),
			),
		) );

		$member_html = '';
		if ( ! empty( $members ) ) {
			$m          = $members[0];
			$estado     = get_post_meta( $m->ID, '_convoca_estado_miembro', true );
			$plan       = get_post_meta( $m->ID, '_convoca_plan', true );
			$renovacion = get_post_meta( $m->ID, '_convoca_fecha_renovacion', true );

			$member_html = sprintf(
				'<div class="conv-member-info card glass">
					<h3>%s</h3>
					<p><strong>%s:</strong> <span class="badge state-%s">%s</span></p>
					<p><strong>%s:</strong> %s</p>
					<p><strong>%s:</strong> %s</p>
				</div>',
				__( 'Tu condición de socio/a', 'convoca-members' ),
				__( 'Estado', 'convoca-members' ),
				esc_attr( $estado ),
				esc_html( ucfirst( $estado ) ),
				__( 'Plan', 'convoca-members' ),
				esc_html( ucfirst( $plan ) ),
				__( 'Próxima renovación', 'convoca-members' ),
				esc_html( $renovacion )
			);
		} else {
			$member_html = sprintf(
				'<div class="conv-member-info card glass">
					<p>%s</p>
					<a href="%s" class="button">%s</a>
				</div>',
				sprintf( __( 'Aún no eres socio/a de %s.', 'convoca-members' ), get_bloginfo( 'name' ) ),
				home_url( '/hazte-socio/' ),
				__( 'Hacerse socio/a', 'convoca-members' )
			);
		}

		// 2. Get active inscriptions.
		$inscriptions = get_posts( array(
			'post_type'      => 'inscripcion',
			'posts_per_page' => -1,
			'meta_query'     => array(
				'relation' => 'AND',
				array( 'key' => '_convoca_email', 'value' => $email ),
				array( 'key' => '_convoca_estado', 'value' => 'cancelada', 'compare' => '!=' ),
			),
		) );

		$insc_html = '<h3>' . __( 'Tus inscripciones activas', 'convoca-members' ) . '</h3>';
		if ( ! empty( $inscriptions ) ) {
			$insc_html .= '<div class="conv-inscriptions-grid">';
			foreach ( $inscriptions as $i ) {
				$actividad_id = get_post_meta( $i->ID, '_convoca_actividad_id', true );
				$estado       = get_post_meta( $i->ID, '_convoca_estado', true );
				$fecha        = get_the_date( 'd/m/Y', $i->ID );

				$insc_html .= sprintf(
					'<div class="conv-insc-item card secondary">
						<h4>%s</h4>
						<p><span class="badge state-%s">%s</span> — %s</p>
						<a href="%s" class="link-arrow">%s</a>
					</div>',
					get_the_title( $actividad_id ),
					esc_attr( $estado ),
					esc_html( ucfirst( $estado ) ),
					$fecha,
					get_permalink( $actividad_id ),
					__( 'Ver actividad', 'convoca-members' )
				);
			}
			$insc_html .= '</div>';
		} else {
			$insc_html .= '<p>' . __( 'No tienes inscripciones activas en este momento.', 'convoca-members' ) . '</p>';
		}

		return '<div class="convoca-my-profile">' . $member_html . $insc_html . '</div>';
	}
}
