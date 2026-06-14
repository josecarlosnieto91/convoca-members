<?php
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
		wp_enqueue_style( 'convoca-mi-area', CONV_MEMBERS_URL . 'public/assets/mi-area.css', array(), CONV_MEMBERS_VERSION );
		wp_enqueue_script( 'convoca-mi-area', CONV_MEMBERS_URL . 'public/assets/mi-area.js', array( 'convoca-common-js' ), CONV_MEMBERS_VERSION, true );

		$member_id = Member_Auth::get_current_member_id();
		wp_localize_script(
			'convoca-mi-area',
			'bdvMiArea',
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
			<p><?php _e( 'Introduce tu usuario y contraseña para acceder a tu área privada.', 'convoca-members' ); ?></p>
			<form id="conv-login-form" class="conv-login-form">
				<div class="form-group">
					<label for="username"><?php _e( 'Usuario o Email', 'convoca-members' ); ?></label>
					<input type="text" id="username" name="username" required placeholder="ejemplo@getconvoca.app">
				</div>
				<div class="form-group">
					<label for="password"><?php _e( 'Contraseña', 'convoca-members' ); ?></label>
					<input type="password" id="password" name="password" required minlength="4">
					<small><?php _e( 'Las credenciales las recibiste por email al ser aprobado.', 'convoca-members' ); ?></small>
					<br><small><a href="mailto:coordinacion@getconvoca.app"><?php _e( '¿No tienes acceso? Escribe a coordinación.', 'convoca-members' ); ?></a></small>
				</div>
				<button type="submit" class="btn-primary"><?php _e( 'Entrar', 'convoca-members' ); ?></button>
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
					<p class="subtitle"><?php _e( 'Bienvenido a tu panel de socio de Convoca.', 'convoca-members' ); ?></p>
				</div>
				<button id="conv-logout-btn" class="btn-link"><?php _e( 'Cerrar sesión', 'convoca-members' ); ?></button>
			</header>

			<div class="conv-panel-grid">
				<!-- Navigation -->
				<nav class="conv-panel-nav card-glass">
					<ul>
						<li class="active" data-tab="profile">👤 <?php _e( 'Mis Datos', 'convoca-members' ); ?></li>
						<li data-tab="membership">🪪 <?php _e( 'Carnet Digital', 'convoca-members' ); ?></li>
						<li data-tab="inscriptions">📝 <?php _e( 'Inscripciones', 'convoca-members' ); ?></li>
						<li data-tab="hours">⏳ <?php _e( 'Voluntariado', 'convoca-members' ); ?></li>
						<li data-tab="payments">💳 <?php _e( 'Pagos y Cuotas', 'convoca-members' ); ?></li>
						<li data-tab="search">🔍 <?php _e( 'Buscar', 'convoca-members' ); ?></li>
						<li data-tab="notifications" class="conv-notif-tab">🔔 <?php _e( 'Notificaciones', 'convoca-members' ); ?> <span class="conv-notif-badge" id="conv-notif-count" style="display:none">0</span></li>
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
