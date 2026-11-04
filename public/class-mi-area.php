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
		add_shortcode( 'convoca_renovar', array( $this, 'render_renovar' ) );
		add_action( 'template_redirect', array( $this, 'handle_email_confirmation' ) );
		add_action( 'template_redirect', array( $this, 'handle_phone_confirmation' ) );
	}

	/**
	 * Handle the email confirmation link (?convoca_confirm_email=1&member=X&token=Y).
	 */
	public function handle_email_confirmation(): void {
		if ( empty( $_GET['convoca_confirm_email'] ) || empty( $_GET['member'] ) || empty( $_GET['token'] ) ) {
			return;
		}

		$member_id = (int) $_GET['member'];
		$token     = sanitize_text_field( wp_unslash( $_GET['token'] ) );
		$stored    = get_post_meta( $member_id, '_convoca_email_token', true );
		$exp       = (int) get_post_meta( $member_id, '_convoca_email_token_exp', true );
		$pendiente = get_post_meta( $member_id, '_convoca_email_pendiente', true );

		// `empty( $pendiente )` va en la misma guarda que el token: sin cambio pendiente **no hay nada que aplicar**, y
		// escribir el pendiente vacío dejaba al socio **sin correo** — sin avisos, sin recuperar la contraseña y sin que
		// le llegue el enlace siguiente (members#79). Pasa de verdad: el pendiente y el token se borran en sitios
		// distintos, así que cualquier camino que borre uno y no el otro abría esta puerta.
		if ( empty( $token ) || empty( $stored ) || ! hash_equals( $stored, $token ) || empty( $pendiente ) ) {
			wp_safe_redirect( add_query_arg( 'convoca_confirm_error', 'token', home_url( '/mi-cuenta/' ) ) );
			exit;
		}
		if ( time() > $exp ) {
			wp_safe_redirect( add_query_arg( 'convoca_confirm_error', 'expirado', home_url( '/mi-cuenta/' ) ) );
			exit;
		}

		update_post_meta( $member_id, '_convoca_email', $pendiente );
		delete_post_meta( $member_id, '_convoca_email_pendiente' );
		delete_post_meta( $member_id, '_convoca_email_token' );
		delete_post_meta( $member_id, '_convoca_email_token_exp' );

		\Convoca\Core\Utils::do_action( 'convoca_members_email_cambiado', 'convoca_email_cambiado', $member_id, $pendiente );

		wp_safe_redirect( add_query_arg( 'convoca_confirm_ok', '1', home_url( '/mi-cuenta/' ) ) );
		exit;
	}

	/**
	 * Handle the phone confirmation link (?convoca_confirm_phone=1&member=X&token=Y).
	 */
	public function handle_phone_confirmation(): void {
		if ( empty( $_GET['convoca_confirm_phone'] ) || empty( $_GET['member'] ) || empty( $_GET['token'] ) ) {
			return;
		}

		$member_id = (int) $_GET['member'];
		$token     = sanitize_text_field( wp_unslash( $_GET['token'] ) );
		$stored    = get_post_meta( $member_id, '_convoca_telefono_token', true );
		$exp       = (int) get_post_meta( $member_id, '_convoca_telefono_token_exp', true );

		if ( empty( $token ) || empty( $stored ) || ! hash_equals( $stored, $token ) ) {
			wp_safe_redirect( add_query_arg( 'convoca_confirm_error', 'token', home_url( '/mi-cuenta/' ) ) );
			exit;
		}
		if ( time() > $exp ) {
			wp_safe_redirect( add_query_arg( 'convoca_confirm_error', 'expirado', home_url( '/mi-cuenta/' ) ) );
			exit;
		}

		update_post_meta( $member_id, '_convoca_telefono_verificado', '1' );
		delete_post_meta( $member_id, '_convoca_telefono_token' );
		delete_post_meta( $member_id, '_convoca_telefono_token_exp' );

		\Convoca\Core\Utils::do_action( 'convoca_members_phone_verificado', 'convoca_phone_verificado', $member_id );

		wp_safe_redirect( add_query_arg( 'convoca_phone_ok', '1', home_url( '/mi-cuenta/' ) ) );
		exit;
	}

	/**
	 * [convoca_renovar] — Renewal shortcode.
	 * For logged-in members: shows a renew button that generates the payment.
	 * For guests: login prompt.
	 */
	public function render_renovar(): string {
		self::enqueue_front_assets();
		$member_id = Member_Auth::get_current_member_id();
		ob_start();

		if ( $member_id <= 0 ) {
			echo '<div class="convoca-alert convoca-alert--info">';
			echo esc_html__( 'Para renovar tu membresía, inicia sesión con tu email y código.', 'convoca-members' );
			echo ' <a href="' . esc_url( home_url( '/mi-cuenta/' ) ) . '">' . esc_html__( 'Acceder a Mi Cuenta', 'convoca-members' ) . '</a>';
			echo '</div>';
			return (string) ob_get_clean();
		}

		$miembro    = get_post( $member_id );
		$plan_key   = get_post_meta( $member_id, '_convoca_plan', true ) ?: get_post_meta( $member_id, '_convoca_sub_plan', true );
		$plan_data  = CPT_Miembro::get_plan( $plan_key );
		$estado     = get_post_meta( $member_id, '_convoca_estado_miembro', true );
		$renovacion = get_post_meta( $member_id, '_convoca_fecha_renovacion', true );
		$importe    = (float) ( get_post_meta( $member_id, '_convoca_importe_cuota', true ) ?: ( $plan_data['price'] ?? 0 ) );

		?>
		<div class="convoca-renovar card glass">
			<h2><?php esc_html_e( 'Renovación de membresía', 'convoca-members' ); ?></h2>
			<p><strong><?php esc_html_e( 'Socio/a:', 'convoca-members' ); ?></strong> <?php echo esc_html( isset( $miembro->post_title ) ? (string) $miembro->post_title : '' ); ?></p>
			<p><strong><?php esc_html_e( 'Plan:', 'convoca-members' ); ?></strong> <?php echo esc_html( ucfirst( $plan_key ) ); ?></p>
			<p><strong><?php esc_html_e( 'Estado:', 'convoca-members' ); ?></strong> <span class="badge state-<?php echo esc_attr( $estado ); ?>"><?php echo esc_html( ucfirst( $estado ) ); ?></span></p>
			<?php if ( $renovacion ) : ?>
				<p><strong><?php esc_html_e( 'Próxima renovación:', 'convoca-members' ); ?></strong> <?php echo esc_html( $renovacion ); ?></p>
			<?php endif; ?>
			<?php if ( $importe > 0 ) : ?>
				<p><strong><?php esc_html_e( 'Importe de renovación:', 'convoca-members' ); ?></strong> <?php echo esc_html( number_format( (float) $importe, 2, ',', '.' ) ); ?> €</p>
			<?php endif; ?>
			<button type="button" id="conv-btn-renovar" class="button button-primary">
				<?php esc_html_e( 'Renovar membresía', 'convoca-members' ); ?>
			</button>
			<div id="conv-renovar-msg"></div>
		</div>
		<script>
		document.addEventListener('click', function(e) {
			if (e.target && e.target.id === 'conv-btn-renovar') {
				e.preventDefault();
				var btn = e.target;
				var msg = document.getElementById('conv-renovar-msg');
				btn.disabled = true;
				msg.innerHTML = '<?php echo esc_js( __( 'Generando pago…', 'convoca-members' ) ); ?>';
				fetch(window.convMiArea ? window.convMiArea.apiUrl : '<?php echo esc_url( rest_url( 'convoca-members/v1' ) ); ?>' + '/me/renovar', {
					method: 'POST',
					headers: {
						'X-WP-Nonce': window.convMiArea ? window.convMiArea.nonce : '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>',
						'Content-Type': 'application/json'
					}
				}).then(function(r) { return r.json(); }).then(function(d) {
					if (d.payment_url) {
						window.location.href = d.payment_url;
					} else {
						btn.disabled = false;
						// Escape error message to prevent XSS (it may come from the API).
						var err = d.error || '<?php echo esc_js( __( 'Error', 'convoca-members' ) ); ?>';
						var div = document.createElement('div');
						div.className = 'convoca-alert convoca-alert--danger';
						div.textContent = err;
						msg.innerHTML = '';
						msg.appendChild(div);
					}
				}).catch(function() {
					btn.disabled = false;
					msg.innerHTML = '<div class="convoca-alert convoca-alert--danger"><?php echo esc_js( __( 'Error de red.', 'convoca-members' ) ); ?></div>';
				});
			}
		});
		</script>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Enqueue CSS/JS of the members area.
	 *
	 * Se llama desde cada punto de render (shortcodes y bloques), no en
	 * wp_enqueue_scripts: el área privada solo existe en las páginas que la pintan,
	 * y encolarla en todas metía ~14 KiB en cada visita del sitio. Hacerlo en el
	 * render cubre también las plantillas FSE y los bloques reutilizables, que un
	 * heurístico sobre el contenido de la entrada no vería.
	 */
	public static function enqueue_front_assets(): void {
		wp_enqueue_style( 'convoca-mi-area', CONVOCA_MEMBERS_URL . 'public/assets/mi-area.css', array( 'convoca-core' ), CONVOCA_MEMBERS_VERSION );
		wp_enqueue_script( 'convoca-mi-area', CONVOCA_MEMBERS_URL . 'public/assets/mi-area.js', array( 'convoca-common-js' ), CONVOCA_MEMBERS_VERSION, true );

		$member_id = Member_Auth::get_current_member_id();
		wp_localize_script(
			'convoca-mi-area',
			'convMiArea',
			array(
				'apiUrl'     => rest_url( 'convoca-members/v1' ),
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
	/**
	 * Qué es esta persona y qué le supone, dentro de su panel.
	 *
	 * Va en servidor: es lo primero que ve al entrar y no depende de que el JS cargue.
	 *
	 * @param int $member_id Ficha del socio.
	 * @return void
	 */
	private function render_condiciones( int $member_id ): void {
		$condiciones = Roles_Miembro::roles( $member_id );

		if ( ! $condiciones ) {
			return;
		}

		$etiquetas = array();

		foreach ( $condiciones as $rol ) {
			$etiquetas[] = Roles_Miembro::etiqueta( $rol );
		}

		echo '<p class="conv-condiciones">';
		printf(
			/* translators: %s: condiciones de la ficha (Socio, Voluntario, Monitor). */
			esc_html__( 'Tu ficha: %s', 'convoca-members' ),
			esc_html( implode( ' · ', $etiquetas ) )
		);

		if ( in_array( Roles_Miembro::SOCIO, $condiciones, true ) ) {
			echo ' — ' . esc_html__( 'tus inscripciones van con tarifa de socio.', 'convoca-members' );
		}

		echo '</p>';
	}

	/**
	 * Cursos en los que el socio puede matricularse, si `courses` contesta al filtro.
	 *
	 * `members` no sabe qué cursos hay: pregunta y, si nadie contesta (courses no instalado), no
	 * pinta nada. Cada curso llega con su enlace de matrícula; al abrirlo, el formulario reconoce
	 * al socio por la sesión y no le pide otra vez sus datos.
	 *
	 * @param int $member_id Ficha del socio.
	 * @return void
	 */
	private function render_cursos( int $member_id ): void {
		$user_id = (int) get_post_meta( $member_id, '_convoca_user_id', true );

		/**
		 * Cursos disponibles para un socio.
		 *
		 * @param array<int,array{titulo:string,url:string,precio:string}> $cursos    Vacío.
		 * @param int                                                       $user_id   Usuario.
		 * @param int                                                       $member_id Ficha.
		 */
		$cursos = apply_filters( 'convoca_cursos_para_socio', array(), $user_id, $member_id );

		if ( ! is_array( $cursos ) || array() === $cursos ) {
			return;
		}

		echo '<section class="conv-mi-area__cursos">';
		echo '<h2 class="conv-mi-area__titulo">' . esc_html__( 'Cursos', 'convoca-members' ) . '</h2>';
		echo '<ul class="conv-mi-area__lista">';

		foreach ( $cursos as $curso ) {
			$titulo = isset( $curso['titulo'] ) ? (string) $curso['titulo'] : '';
			$url    = isset( $curso['url'] ) ? (string) $curso['url'] : '';
			$precio = isset( $curso['precio'] ) ? (string) $curso['precio'] : '';

			if ( '' === $titulo || '' === $url ) {
				continue;
			}

			echo '<li><a href="' . esc_url( $url ) . '">' . esc_html( $titulo ) . '</a>';
			echo $precio !== '' ? ' <span class="conv-mi-area__precio">' . esc_html( $precio ) . '</span>' : '';
			echo '</li>';
		}

		echo '</ul></section>';
	}

	/**
	 * Panel del socio.
	 *
	 * @return string
	 */
	public function render(): string {
		self::enqueue_front_assets();
		ob_start();
		$member_id = Member_Auth::get_current_member_id();

		echo '<div id="conv-mi-area" class="conv-mi-area" data-loading="true">';

		if ( $member_id > 0 ) {
			$this->render_dashboard( $member_id );
			$this->render_cursos( $member_id );
		} else {
			$this->render_login_form();
		}

		echo '</div>';

		return (string) ob_get_clean();
	}

	/**
	 * Render Login Form.
	 */
	/**
	 * Aviso para quien intenta entrar sin JavaScript.
	 *
	 * @return void
	 */
	private function aviso_sin_js_panel(): void {
		$convoca_email = (string) apply_filters( 'convoca_panel_socio_sin_js_email', (string) get_bloginfo( 'admin_email' ) );
		?>
		<div class="conv-login-container card-glass">
			<div class="convoca-alert convoca-alert--warning" role="status">
				<p><?php esc_html_e( 'No se ha podido iniciar la sesión: este formulario necesita JavaScript en el navegador.', 'convoca-members' ); ?></p>
				<p><?php esc_html_e( 'Actívalo y vuelve a intentarlo.', 'convoca-members' ); ?>
				<?php if ( $convoca_email ) : ?>
					<?php esc_html_e( 'Si no puedes, escríbenos:', 'convoca-members' ); ?>
					<a href="<?php echo esc_url( 'mailto:' . $convoca_email ); ?>"><?php echo esc_html( $convoca_email ); ?></a>
				<?php endif; ?>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Formulario de acceso al área privada.
	 *
	 * @return void
	 */
	private function render_login_form(): void {
		?>
		<?php
		/*
		 * Sin JavaScript: la degradación se DECLARA y se da salida (mismo defecto medido en el
		 * resto de formularios del ecosistema: sin `method` ni `action` el envío se pierde,
		 * sin procesarse y sin decir nada).
		 */
		if ( ! empty( $_POST['convoca_sin_js_panel_socio'] ) ) {
			$this->aviso_sin_js_panel();
			return;
		}
		?>
		<div class="conv-login-container card-glass">
			<h2 class="text-gradient"><?php esc_html_e( 'Acceso Socios', 'convoca-members' ); ?></h2>
			<p><?php esc_html_e( 'Introduce tu usuario y contraseña para acceder a tu área privada.', 'convoca-members' ); ?></p>
			<noscript>
				<div class="convoca-alert convoca-alert--warning" role="status">
					<p><?php esc_html_e( 'Este acceso necesita JavaScript en el navegador. Actívalo y vuelve a intentarlo.', 'convoca-members' ); ?></p>
				</div>
			</noscript>
			<form id="conv-login-form" class="conv-login-form" method="post" action="">
				<?php // Marcador del envío sin JavaScript: lo lee el aviso de arriba. ?>
				<input type="hidden" name="convoca_sin_js_panel_socio" value="1" />
				<div class="form-group">
					<label for="username"><?php esc_html_e( 'Usuario o Email', 'convoca-members' ); ?></label>
					<input type="text" id="username" name="username" required placeholder="<?php esc_attr_e( 'ejemplo@correo.com', 'convoca-members' ); ?>">
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
	 * Cabecera del panel: saludo, condiciones de la ficha y botón de salir.
	 *
	 * @param int   $member_id Ficha.
	 * @param mixed $post      Ficha (objeto con `post_title`).
	 * @return void
	 */
	private function render_cabecera_panel( int $member_id, $post ): void {
		?>
		<header class="conv-panel-header">
			<div>
				<h1><?php /* translators: %s es el nombre de la persona socia. */ printf( esc_html__( '¡Hola, %s!', 'convoca-members' ), '<span>' . esc_html( (string) $post->post_title ) . '</span>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- el nombre va escapado y el span es literal. ?></h1>
				<p class="subtitle"><?php esc_html_e( 'Bienvenido a tu panel de socio de Convoca.', 'convoca-members' ); ?></p>
				<?php $this->render_condiciones( $member_id ); ?>
			</div>
			<button id="conv-logout-btn" type="button" class="btn-link"><?php esc_html_e( 'Cerrar sesión', 'convoca-members' ); ?></button>
		</header>
		<?php
	}

	/**
	 * Navegación del panel: pestañas y enlaces que cada sitio añade por filtro.
	 *
	 * @return void
	 */
	private function render_navegacion_panel(): void {
		?>
		<nav class="conv-panel-nav card-glass">
			<ul>
				<li class="active" data-tab="profile">👤 <?php esc_html_e( 'Mis Datos', 'convoca-members' ); ?></li>
				<li data-tab="membership">🪪 <?php esc_html_e( 'Carnet Digital', 'convoca-members' ); ?></li>
				<li data-tab="inscriptions">📝 <?php esc_html_e( 'Inscripciones', 'convoca-members' ); ?></li>
				<li data-tab="hours">⏳ <?php esc_html_e( 'Voluntariado', 'convoca-members' ); ?></li>
				<li data-tab="payments">💳 <?php esc_html_e( 'Pagos y Cuotas', 'convoca-members' ); ?></li>
				<?php
				// Mis turnos (shifts#19): solo si el plugin de turnos está puesto. El contenido lo pone él
				// (sus shortcodes), aquí no se reescribe nada suyo.
				if ( shortcode_exists( 'convoca_proximos_turnos' ) ) :
					?>
					<li data-tab="turnos">📅 <?php esc_html_e( 'Mis turnos', 'convoca-members' ); ?></li>
				<?php endif; ?>
				<li data-tab="privacy">🔒 <?php esc_html_e( 'Mis datos y privacidad', 'convoca-members' ); ?></li>
				<li data-tab="search">🔍 <?php esc_html_e( 'Buscar', 'convoca-members' ); ?></li>
				<li data-tab="notifications" class="conv-notif-tab">🔔 <?php esc_html_e( 'Notificaciones', 'convoca-members' ); ?> <span class="conv-notif-badge" id="conv-notif-count" style="display:none">0</span></li>
			</ul>
			<?php $this->render_enlaces_panel(); ?>
		</nav>
		<?php
	}

	/**
	 * Enlaces que cada sitio añade al panel con `convoca_mi_area_links`.
	 *
	 * @return void
	 */
	private function render_enlaces_panel(): void {
		/**
		 * Enlaces a páginas del sitio que conviene tener a mano desde el panel
		 * (turnos, perfil público, renovación…).
		 *
		 * El plugin no lleva a mano las páginas de un sitio concreto —no existen en
		 * otro—, así que las pone quien las tiene: cada sitio añade las suyas con
		 * este filtro. Cada enlace es array( 'url' => …, 'label' => … ).
		 *
		 * @param array $enlaces Enlaces a pintar.
		 */
		// Primero los del ajuste del sitio (Members → Enlaces del panel), y después el filtro, que
		// manda si coinciden: así un sitio que ya los ponía por código no cambia de comportamiento.
		$ajustes = (array) get_option( 'convoca_members_settings', array() );
		$enlaces = array();

		foreach ( (array) ( $ajustes['panel_links'] ?? array() ) as $conv_enlace ) {
			if ( is_array( $conv_enlace ) && ! empty( $conv_enlace['url'] ) && ! empty( $conv_enlace['label'] ) ) {
				$enlaces[ (string) $conv_enlace['url'] ] = $conv_enlace;
			}
		}

		foreach ( (array) apply_filters( 'convoca_mi_area_links', array() ) as $conv_enlace ) {
			if ( is_array( $conv_enlace ) && ! empty( $conv_enlace['url'] ) && ! empty( $conv_enlace['label'] ) ) {
				$enlaces[ (string) $conv_enlace['url'] ] = $conv_enlace;
			}
		}

		if ( ! $enlaces ) {
			return;
		}
		?>
		<ul class="conv-panel-links">
			<?php foreach ( $enlaces as $enlace ) : ?>
				<li>
					<a href="<?php echo esc_url( $enlace['url'] ); ?>">
						<?php echo isset( $enlace['icon'] ) ? esc_html( $enlace['icon'] ) . ' ' : ''; ?><?php echo esc_html( $enlace['label'] ); ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/**
	 * Render Dashboard (React-like Skeleton).
	 */
	private function render_dashboard( int $member_id ): void {
		$post = get_post( $member_id );
		// Una ficha borrada (o un `_convoca_member_id` que apunta al vacío) deja `get_post()` en `null`, y aquí
		// se leía `->post_title` a ciegas: en producción eso es un warning de PHP y un saludo sin nombre. Y el
		// panel no puede caerse por eso: quien entra sigue siendo socio aunque su ficha se haya ido.
		if ( ! $post instanceof \WP_Post ) {
			$post = (object) array( 'post_title' => '' );
		}
		?>
		<div class="conv-dashboard-container">
			<?php $this->render_cabecera_panel( $member_id, $post ); ?>

			<div class="conv-panel-grid">
				<?php $this->render_navegacion_panel(); ?>

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
		self::enqueue_front_assets();

		if ( ! is_user_logged_in() ) {
			return sprintf(
				'<div class="conv-profile-login">%s <a href="%s" class="button">%s</a></div>',
				__( 'Inicia sesión para ver tu perfil.', 'convoca-members' ),
				wp_login_url( (string) get_permalink() ),
				__( 'Iniciar sesión', 'convoca-members' )
			);
		}

		$current_user = wp_get_current_user();
		$email        = $current_user->user_email;

		// 1. Get member record.
		$members = get_posts(
			array(
				'post_type'      => 'miembro',
				'posts_per_page' => 1,
				'meta_query'     => array(
					array(
						'key'   => '_convoca_email',
						'value' => $email,
					),
				),
			)
		);

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
				/* translators: %s: site name. */
				sprintf( __( 'Aún no eres socio/a de %s.', 'convoca-members' ), get_bloginfo( 'name' ) ),
				home_url( '/hazte-socio/' ),
				__( 'Hacerse socio/a', 'convoca-members' )
			);
		}

		// 2. Get active inscriptions.
		$inscriptions = get_posts(
			array(
				'post_type'      => 'inscripcion',
				'posts_per_page' => -1,
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'   => '_convoca_email',
						'value' => $email,
					),
					array(
						'key'     => '_convoca_estado',
						'value'   => 'cancelada',
						'compare' => '!=',
					),
				),
			)
		);

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
