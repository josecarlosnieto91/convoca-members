<?php
/**
 * Plugin Name:       Convoca Members
 * Plugin URI:        https://getconvoca.app
 * Description:       Members, volunteers and communications management.
 * Version:           2.6.2
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Tested up to:      7.0
 * Author:            Jose Carlos Nieto Ramos
 * Author URI:        https://getconvoca.app
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       convoca-members
 * Domain Path:       /languages
 * Requires Plugins:  convoca-core
 */

namespace Convoca\Members;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


namespace Convoca\Members;

if ( ! defined( 'ABSPATH' ) ) {
}

/* ── Composer autoload ─────────────────────────────── */
$composer_autoload = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $composer_autoload ) ) {
	require_once $composer_autoload;
}

/* ── Convoca Core fallback ────────────────────────── */
// Core classes auto-loaded via Convoca Core's Composer PSR-4

// Compatibility Check: Ensure Convoca Common is loaded.
if ( ! class_exists( '\\Convoca\\Core\\Utils' ) ) {
	add_action(
		'admin_notices',
		function () {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				'Convoca Members requiere el plugin Convoca Common Utilities activo para funcionar.'
			);
		}
	);
	return;
}

/* ── Constants ────────────────────────────────────────────── */
if ( ! defined( 'CONVOCA_MEMBERS_VERSION' ) ) {
	define( 'CONVOCA_MEMBERS_VERSION', '2.6.1' );
}
if ( ! defined( 'CONVOCA_MEMBERS_DB_VERSION' ) ) {
	define( 'CONVOCA_MEMBERS_DB_VERSION', '1.0.3' );
}
if ( ! defined( 'CONVOCA_MEMBERS_FILE' ) ) {
	define( 'CONVOCA_MEMBERS_FILE', __FILE__ );
}
if ( ! defined( 'CONVOCA_MEMBERS_DIR' ) ) {
	define( 'CONVOCA_MEMBERS_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'CONVOCA_MEMBERS_URL' ) ) {
	define( 'CONVOCA_MEMBERS_URL', plugin_dir_url( __FILE__ ) );
}

/* ── Autoloader ───────────────────────────────────────────── */
// PSR-4 autoloading handled by Composer (vendor/autoload.php)

/*
── Activation / Deactivation ────────────────────────────── */
/* ── Activation / Deactivation ────────────────────────────── */
register_activation_hook(
	__FILE__,
	function (): void {
		if ( ! class_exists( '\\Convoca\\Core\\Utils' ) ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			wp_die( __( 'Convoca Members requires Convoca Core to be active. Please activate Convoca Core first.', 'convoca-members' ) );
		}
		// Autoloader is already registered, no need to require these manually.
		\Convoca\Members\CPT_Miembro::register();
		\Convoca\Core\Installer::db_init();

		// Schedule Daily Cron.
		if ( ! wp_next_scheduled( 'convoca_daily_event' ) ) {
			wp_schedule_event( time(), 'daily', 'convoca_daily_event' );
		}

		// Schedule Weekly Cron (admin digest).
		if ( ! wp_next_scheduled( 'convoca_weekly_event' ) ) {
			// Next Monday at 08:00.
			$next_monday = strtotime( 'next monday 08:00:00' );
			wp_schedule_event( $next_monday, 'weekly', 'convoca_weekly_event' );
		}

		flush_rewrite_rules();

		// Default email templates.
		\Convoca\Members\Email_Manager::install_defaults();

		// Default settings.
		if ( false === get_option( 'convoca_members_settings' ) ) {
			update_option(
				'convoca_members_settings',
				array(
					'admin_email'  => get_option( 'admin_email' ),
					'iban'         => '',
					'rgpd_version' => '1.0',
				)
			);
		}

		// Always ensure administrators have the necessary capabilities.
		$role = get_role( 'administrator' );
		if ( $role ) {
			$role->add_cap( 'gestionar_miembros' );
			$role->add_cap( 'gestionar_documentos_voluntariado' );
			$role->add_cap( 'view_reports' );
			$role->add_cap( 'convoca_shifts_manage_turnos' );
			$role->add_cap( 'convoca_shifts_view_stats' );
			$role->add_cap( 'convoca_shifts_audit_hours' );
			$role->add_cap( 'convoca_manage_checkin' );
			$role->add_cap( 'convoca_manage_evaluations' );
			$role->add_cap( 'convoca_view_reports' );
			$role->add_cap( 'convoca_manage_hours' );
			$role->add_cap( 'convoca_export_members' );
			$role->add_cap( 'convoca_manage_webhooks' );
			$role->add_cap( 'convoca_view_payments' );
			$role->add_cap( 'convoca_manage_payments' );
			$role->add_cap( 'common_view_logs' );
			$role->add_cap( 'common_manage_backup' );
		}

		// Ensure monitor_actividad role exists (also created by convoca-enroll).
		if ( ! get_role( 'monitor_actividad' ) ) {
			add_role(
				'monitor_actividad',
				__( 'Monitor de Actividad', 'convoca-members' ),
				array(
					'read' => true,
				)
			);
		}

		// Add caps to other relevant roles if they exist.
		foreach ( array( 'shop_manager', 'monitor_actividad' ) as $role_name ) {
			$r = get_role( $role_name );
			if ( $r ) {
				$r->add_cap( 'gestionar_miembros' );
				$r->add_cap( 'gestionar_documentos_voluntariado' );
				$r->add_cap( 'view_reports' );
				$r->add_cap( 'manage_inscripciones' );

				// New granular caps for monitor_actividad.
				if ( $role_name === 'monitor_actividad' ) {
					$r->add_cap( 'convoca_shifts_manage_turnos' );
					$r->add_cap( 'convoca_shifts_view_stats' );
					$r->add_cap( 'convoca_shifts_audit_hours' );
					$r->add_cap( 'convoca_manage_checkin' );
					$r->add_cap( 'convoca_manage_evaluations' );
					$r->add_cap( 'convoca_view_reports' );
					$r->add_cap( 'convoca_manage_hours' );
				}
			}
		}

		// Centro turno editing capabilities for monitors.
		$monitor_role = get_role( 'monitor_actividad' );
		if ( $monitor_role ) {
			$monitor_role->add_cap( 'edit_posts' );
			$monitor_role->add_cap( 'edit_others_posts' );
			$monitor_role->add_cap( 'edit_published_posts' );
			$monitor_role->add_cap( 'publish_posts' );
			$monitor_role->add_cap( 'delete_posts' );
			$monitor_role->add_cap( 'delete_others_posts' );
			$monitor_role->add_cap( 'delete_published_posts' );
			$monitor_role->add_cap( 'read_private_posts' );
		}

		// Register Volunteer Approved Role (Used by Turnos).
		if ( ! get_role( 'voluntario_aprobado' ) ) {
			add_role(
				'voluntario_aprobado',
				__( 'Voluntario Aprobado', 'convoca-members' ),
				array(
					'read'                 => true,
					'gestionar_mis_turnos' => true,
				)
			);
		} else {
			$v_role = get_role( 'voluntario_aprobado' );
			if ( $v_role && ! $v_role->has_cap( 'gestionar_mis_turnos' ) ) {
				$v_role->add_cap( 'gestionar_mis_turnos' );
			}
		}

		// Save initial DB version.
		add_option( 'convoca_members_db_version', CONVOCA_MEMBERS_DB_VERSION, '', false );
	}
);


register_deactivation_hook(
	__FILE__,
	function () {
		wp_clear_scheduled_hook( 'convoca_daily_event' );
		wp_clear_scheduled_hook( 'convoca_weekly_event' );
		flush_rewrite_rules();
	}
);

/* ── Boot ─────────────────────────────────────────────────── */
/* ── Script translations (JS i18n) ──────────────────────── */
add_action( 'init', function() {
	wp_set_script_translations( 'convoca-members-scripts', 'convoca-members', plugin_dir_path( __FILE__ ) . 'languages/' );
}, 20 );

add_action(
	'plugins_loaded',
	function (): void {

		// Load translations.
	load_plugin_textdomain( 'convoca-members', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		// Core.
		new \Convoca\Members\CPT_Miembro();
		new \Convoca\Members\Estados();
		new \Convoca\Members\Email_Manager();
		new \Convoca\Members\Rest_API();
		new \Convoca\Members\Payment_Listener();
		new \Convoca\Members\CPT_Registro_Hora();
		new \Convoca\Members\CPT_Proyecto();
		new \Convoca\Members\CPT_Documento();
		\Convoca\Members\PDF_Document::init();
		\Convoca\Members\Certificate_Generator::init();
		\Convoca\Members\Voluntariado_Manager::init();
		\Convoca\Members\Voluntariado_Gamification::init();
		// GDPR compliance: data export/erasure via WordPress Privacy Tools.
		\Convoca\Members\GDPR_Tools::init();
		// Automation handled via state hooks.
		// Load Cron Jobs.
		new \Convoca\Members\Cron_Manager();
		new \Convoca\Members\Audit_Logger();

		// Upgrade Manager (checks for DB version upgrades on admin_init).
		new \Convoca\Members\Members_Upgrade_Manager();

		// Admin.
		if ( is_admin() ) {
			new \Convoca\Members\Admin_Page();
			new \Convoca\Members\Admin_Settings();
			new \Convoca\Members\Admin_Metaboxes();
			new \Convoca\Members\CSV_Exporter();
			new \Convoca\Members\Admin_Horas();
			new \Convoca\Members\Admin_Dashboard();
			new \Convoca\Members\Admin_Webhooks();
			new \Convoca\Members\Admin_Proyectos();
			new \Convoca\Members\Admin_Member_Editor();
			new \Convoca\Members\Admin_Import_CSV();
		}

		// Public.
		new \Convoca\Members\Form_Handler();
		new \Convoca\Members\Form_Voluntariado();
		new \Convoca\Members\Mi_Area();
		new \Convoca\Members\Verificar_Certificado();
		new \Convoca\Members\Block_Members();
	}
);

add_action( 'init', array( \Convoca\Members\CPT_Miembro::class, 'register' ) );
add_action( 'init', array( \Convoca\Members\CPT_Registro_Hora::class, 'register' ) );
add_action( 'init', array( \Convoca\Members\CPT_Proyecto::class, 'register' ) );
add_action( 'add_meta_boxes_proyecto', array( \Convoca\Members\CPT_Proyecto::class, 'render_metabox' ) );
add_action( 'save_post_proyecto', array( \Convoca\Members\CPT_Proyecto::class, 'save_metabox' ) );

/* ── Admin Actions ────────────────────────────────────────── */
add_action(
	'admin_post_convoca_approve_member',
	function () {
		if ( ! current_user_can( 'edit_users' ) ) {
			wp_die( __( 'No tienes permisos.', 'convoca-members' ) );
		}

		$id = (int) ( $_GET['member_id'] ?? 0 );
		check_admin_referer( 'convoca_approve_member_' . $id );

		if ( $id && \Convoca\Members\CPT_Miembro::approve_member( $id ) ) {
			// Generate WP user + send credentials.
			\Convoca\Members\Process_Member::handle_approved( $id );
			// Redirect back.
			wp_redirect( admin_url( 'admin.php?page=conv-members&msg=approved' ) );
			exit;
		}

		wp_redirect( admin_url( 'admin.php?page=conv-members&msg=error' ) );
		exit;
	}
);

add_action(
	'admin_post_convoca_delete_member',
	function () {
		if ( ! current_user_can( 'delete_posts' ) ) {
			wp_die( __( 'No tienes permisos.', 'convoca-members' ) );
		}

		$id = (int) ( $_GET['member_id'] ?? 0 );
		check_admin_referer( 'convoca_delete_member_' . $id );

		if ( $id && wp_trash_post( $id ) ) {
			wp_redirect( admin_url( 'admin.php?page=conv-members&msg=deleted' ) );
			exit;
		}

		wp_redirect( admin_url( 'admin.php?page=conv-members&msg=error' ) );
		exit;
	}
);

add_action(
	'admin_post_convoca_pdf_card',
	function () {
		$id = (int) ( $_GET['member_id'] ?? 0 );

		// Security checks.
		if ( ! $id || get_post_type( $id ) !== 'miembro' ) {
			wp_die( esc_html__( 'ID de miembro no válido.', 'convoca-members' ) );
		}

		check_admin_referer( 'convoca_pdf_card_' . $id );

		if ( ! current_user_can( 'edit_post', $id ) ) {
			wp_die( esc_html__( 'No tienes permisos para ver la tarjeta de este miembro.', 'convoca-members' ) );
		}

		echo \Convoca\Members\PDF_Card::get_html( $id );
		exit;
	}
);

/**
 * Verification shortcode: [convoca_verificar_socio]
 * Accessible publicly. Shows member name + plan if token is valid.
 */
// Activar miembro del voluntario cuando se aprueba desde Centro Social.
add_action(
	'convoca_voluntario_aprobado',
	function ( int $user_id ): void {
		$member_id = (int) get_user_meta( $user_id, '_convoca_member_id', true );
		if ( $member_id ) {
			$estado = get_post_meta( $member_id, '_convoca_estado_miembro', true );
			if ( $estado !== 'activo' ) {
				\Convoca\Members\CPT_Miembro::approve_member( $member_id );
				\Convoca\Core\Logger::info( "Voluntario #$user_id aprobado -> miembro #$member_id activado", 'Members/Admin' );
			}
			// Generate WP user + send credentials (for volunteers who registered without being linked yet).
			\Convoca\Members\Process_Member::handle_approved( $member_id );
			// Ensure the WP user has the volunteer role.
			$user = get_userdata( $user_id );
			if ( $user && ! in_array( 'voluntario_aprobado', (array) $user->roles, true ) ) {
				$user->set_role( 'voluntario_aprobado' );
			}
		}
	}
);
add_shortcode(
	'convoca_verificar_socio',
	function (): string {
		$member_id = (int) ( $_GET['id'] ?? 0 );
		$token     = sanitize_text_field( $_GET['token'] ?? '' );

		if ( ! $member_id || ! $token ) {
			return '<div class="convoca-alert convoca-alert--info" style="display:block;max-width:500px;margin:40px auto;text-align:center;">
            <p>' . __( 'Escanea el código QR de tu tarjeta de socio para verificar tu membresía.', 'convoca-members' ) . '</p>
        </div>';
		}

		$expected = hash_hmac( 'sha256', 'member_' . $member_id, \Convoca\Core\Utils::get_persistent_salt() );
		if ( ! hash_equals( $expected, $token ) ) {
			return '<div class="convoca-alert convoca-alert--danger" style="display:block;max-width:500px;margin:40px auto;text-align:center;">
            <p><strong>' . __( 'Tarjeta no válida', 'convoca-members' ) . '</strong></p>
            <p>' . __( 'El código de verificación no coincide.', 'convoca-members' ) . '</p>
        </div>';
		}

		$post = get_post( $member_id );
		if ( ! $post || $post->post_type !== 'miembro' ) {
			return '<div class="convoca-alert convoca-alert--danger" style="display:block;max-width:500px;margin:40px auto;text-align:center;">
            <p>' . __( 'Socio no encontrado.', 'convoca-members' ) . '</p>
        </div>';
		}

		$estado = get_post_meta( $member_id, '_convoca_estado_miembro', true );
		if ( $estado !== 'activo' ) {
			return '<div class="convoca-alert convoca-alert--warning" style="display:block;max-width:500px;margin:40px auto;text-align:center;">
            <p><strong>' . __( 'Membresía no activa', 'convoca-members' ) . '</strong></p>
            <p>' . __( 'El socio no tiene una membresía activa en este momento.', 'convoca-members' ) . '</p>
        </div>';
		}

		$plan       = get_post_meta( $member_id, '_convoca_plan', true );
		$plan_label = '';
		if ( class_exists( '\\Convoca\\Members\\CPT_Miembro' ) ) {
			$plans      = \Convoca\Members\CPT_Miembro::get_plans();
			$plan_label = $plans[ $plan ]['label'] ?? $plan;
		}
		$num = get_post_meta( $member_id, '_convoca_numero_socio', true );

		return '<div style="max-width:500px;margin:40px auto;text-align:center;background:#fff;border-radius:16px;padding:40px;box-shadow:0 10px 25px rgba(0,0,0,0.05);">
        <div style="font-size:64px;margin-bottom:20px;">✅</div>
        <h2 style="color:var(--wp--preset--color--violeta,#320028);margin:0 0 10px;">' . esc_html__( 'Socio Activo', 'convoca-members' ) . '</h2>
        <p style="font-size:18px;font-weight:700;margin:5px 0;">' . esc_html( $post->post_title ) . '</p>
        <p style="color:#666;">' . ( $num ? '#' . esc_html( str_pad( $num, 4, '0', STR_PAD_LEFT ) ) : '' ) . ' · ' . esc_html( $plan_label ) . '</p>
        <p style="font-size:13px;color:#999;margin-top:20px;">' . esc_html__( 'Verificado por Convoca', 'convoca-members' ) . '</p>
    </div>';
	}
);
