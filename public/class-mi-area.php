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
}
