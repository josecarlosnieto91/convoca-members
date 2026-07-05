<?php

/**
 * Convoca Members
 *
 * @package    Convoca\Members
 * @subpackage Admin
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
 * Settings page: general settings + plans + email template editor.
 *
 * @package Convoca\Members
 */

namespace Convoca\Members;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Settings {

	private const CACHE_KEY = 'convoca_members_diagnostic_cache';

	public function __construct() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'handle_custom_save' ) );
	}

	public function handle_custom_save(): void {
		if ( ! isset( $_POST['_convoca_settings_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( wp_unslash( $_POST['_convoca_settings_nonce'] ), 'convoca_members_settings_save' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos.', 'convoca-members' ) );
		}

		if ( isset( $_POST['convoca_save_settings'] ) && $_POST['convoca_save_settings'] === 'general' ) {
			$raw      = wp_unslash( $_POST['convoca_members_settings'] ?? array() );
			$settings = get_option( 'convoca_members_settings', array() );
			foreach ( $raw as $key => $val ) {
				$settings[ $key ] = sanitize_text_field( $val );
			}
			update_option( 'convoca_members_settings', $settings );
			wp_safe_redirect( add_query_arg( 'updated', '1', wp_get_referer() ) );
			exit;
		}
	}

	public function register_settings(): void {
		register_setting(
			'convoca_members_settings_group',
			'convoca_members_settings',
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);
		register_setting(
			'convoca_members_plans_group',
			'convoca_members_plans',
			array(
				'sanitize_callback' => array( $this, 'sanitize_plans' ),
			)
		);
		register_setting(
			'convoca_members_volunteers_group',
			'convoca_volunteer_fields',
			array(
				'sanitize_callback' => array( $this, 'sanitize_volunteer_fields' ),
			)
		);
		register_setting(
			'convoca_members_volunteers_group',
			'convoca_volunteer_legal_text',
			array(
				'sanitize_callback' => 'wp_kses_post',
			)
		);
		register_setting(
			'convoca_members_gamification_group',
			'convoca_gamification_tracks',
			array(
				'sanitize_callback' => array( $this, 'sanitize_gamification_tracks' ),
			)
		);
	}

	public function sanitize_settings( $input ): array {
		delete_option( self::CACHE_KEY );
		if ( ! is_array( $input ) ) {
			return array();
		}
		$input = wp_unslash( $input );
		return array(
			'admin_email'  => sanitize_email( $input['admin_email'] ?? '' ),
			'iban'         => sanitize_text_field( $input['iban'] ?? '' ),
			'rgpd_version' => sanitize_text_field( $input['rgpd_version'] ?? '1.0' ),
			'sender_name'  => sanitize_text_field( $input['sender_name'] ?? get_bloginfo( 'name' ) ),
			'min_age'      => (int) ( $input['min_age'] ?? 0 ),
		);
	}

	public function sanitize_plans( $input ): array {
		delete_option( self::CACHE_KEY );
		if ( ! is_array( $input ) ) {
			return array();
		}
		$input     = wp_unslash( $input );
		$sanitized = array();

		foreach ( $input as $key => $plan ) {
			// If checking for removal/inactive, logic goes here.
			// We use the key provided in the input, ensuring it's safe.
			$key = sanitize_key( $key );
			if ( empty( $key ) ) {
				continue;
			}

			$sanitized[ $key ] = array(
				'label'           => sanitize_text_field( $plan['label'] ?? '' ),
				'price'           => (float) ( $plan['price'] ?? 0 ),
				'hours'           => (float) ( $plan['hours'] ?? 0 ),
				'modalidad'       => sanitize_text_field( $plan['modalidad'] ?? 'Numerario' ),
				'payment_methods' => array_map( 'sanitize_text_field', $plan['payment_methods'] ?? array() ),
				'url_bizum'       => sanitize_url( $plan['url_bizum'] ?? '' ),
				'url_tarjeta'     => sanitize_url( $plan['url_tarjeta'] ?? '' ),
				'advantages'      => array_filter( array_map( 'trim', explode( "\n", $plan['advantages_raw'] ?? '' ) ) ),
				'active'          => isset( $plan['active'] ) ? (bool) $plan['active'] : false,
				'order'           => (int) ( $plan['order'] ?? 0 ),
				'emoji'           => sanitize_text_field( $plan['emoji'] ?? '' ),
				'description'     => sanitize_textarea_field( $plan['description'] ?? '' ),
			);
		}

		// Sort by order.
		uasort(
			$sanitized,
			function ( $a, $b ) {
				return ( $a['order'] ?? 0 ) <=> ( $b['order'] ?? 0 );
			}
		);

		return $sanitized;
	}

	public function sanitize_volunteer_fields( $input ): array {
		delete_option( self::CACHE_KEY );
		if ( ! is_array( $input ) ) {
			return array();
		}
		$input     = wp_unslash( $input );
		$sanitized = array();
		foreach ( $input as $field ) {
			$name = sanitize_key( $field['name'] ?? '' );
			if ( empty( $name ) ) {
				continue;
			}
			$sanitized[] = array(
				'name'     => $name,
				'label'    => sanitize_text_field( $field['label'] ?? '' ),
				'type'     => sanitize_key( $field['type'] ?? 'text' ),
				'options'  => sanitize_textarea_field( $field['options'] ?? '' ),
				'required' => ! empty( $field['required'] ),
			);
		}
		return $sanitized;
	}

	/**
	 * Sanitize gamification tracks saved from admin.
	 *
	 * @param  mixed $input Raw POST data.
	 * @return array Sanitized track config.
	 */
	public function sanitize_gamification_tracks( $input ): array {
		if ( ! is_array( $input ) ) {
			return array();
		}
		$sanitized = array();
		foreach ( $input as $track_key => $track ) {
			$tk = sanitize_key( $track_key );
			if ( ! in_array( $tk, array( 'busgosu', 'lugg', 'deva' ), true ) ) {
				continue;
			}
			$sanitized[ $tk ] = array(
				'label'  => sanitize_text_field( $track['label'] ?? '' ),
				'levels' => array(),
			);
			if ( isset( $track['levels'] ) && is_array( $track['levels'] ) ) {
				foreach ( $track['levels'] as $i => $level ) {
					$idx                                = (int) $i;
					$sanitized[ $tk ]['levels'][ $idx ] = array(
						'name'  => sanitize_text_field( $level['name'] ?? '' ),
						'emoji' => sanitize_text_field( $level['emoji'] ?? '' ),
						'hours' => (float) ( $level['hours'] ?? 0 ),
						'color' => sanitize_hex_color( $level['color'] ?? '' ) ?: '#000000',
						'desc'  => sanitize_textarea_field( $level['desc'] ?? '' ),
					);
				}
			}
		}
		return $sanitized;
	}

	private function get_active_tab(): string {
		return isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
	}

	public function render(): void {
		$active_tab = $this->get_active_tab();
		?>
		<div class="wrap conv-members-settings-wrap">
			<div class="conv-admin-header" style="display: flex; align-items: center; gap: 20px; margin-bottom: 20px;">
				<img src="<?php echo esc_url( CONVOCA_IMAGES_URL . 'logo.png' ); ?>" alt="Convoca Members" style="width: 80px; height: 80px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
				<div>
					<h1 style="margin: 0; padding: 0;"><?php esc_html_e( 'Ajustes — Miembros Convoca', 'convoca-members' ); ?></h1>
					<p style="margin: 5px 0 0; color: #666; font-size: 1.1em;"><?php esc_html_e( 'Gestión de socios, planes y comunicaciones', 'convoca-members' ); ?></p>
				</div>
			</div>

			<nav class="nav-tab-wrapper">
				<a href="?page=conv-members-settings&tab=general"
					class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'General', 'convoca-members' ); ?>
				</a>
				<a href="?page=conv-members-settings&tab=plans"
					class="nav-tab <?php echo $active_tab === 'plans' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Planes de Socio', 'convoca-members' ); ?>
				</a>
				<a href="?page=conv-members-settings&tab=emails"
					class="nav-tab <?php echo $active_tab === 'emails' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Plantillas de Email', 'convoca-members' ); ?>
				</a>
				<a href="?page=conv-members-settings&tab=volunteers"
					class="nav-tab <?php echo $active_tab === 'volunteers' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Campos Voluntariado', 'convoca-members' ); ?>
				</a>
				<?php if ( \Convoca\Core\License_Manager::has_pro( 'gamification' ) ) : ?>
				<a href="?page=conv-members-settings&tab=gamification"
					class="nav-tab <?php echo $active_tab === 'gamification' ? 'nav-tab-active' : ''; ?>">
					🏆 <?php esc_html_e( 'Gamificación', 'convoca-members' ); ?>
				</a>
				<?php endif; ?>
				<a href="?page=conv-members-settings&tab=status"
					class="nav-tab <?php echo $active_tab === 'status' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Estado', 'convoca-members' ); ?>
				</a>
			</nav>

			<?php \Convoca\Core\Utils::render_stored_notices(); ?>

			<div class="conv-settings-content">
				<?php
				switch ( $active_tab ) {
					case 'plans':
						$this->render_plans_tab();
						break;
					case 'emails':
						$this->render_emails_tab();
						break;
					case 'volunteers':
						$this->render_volunteers_tab();
						break;
					case 'gamification':
						if ( \Convoca\Core\License_Manager::has_pro( 'gamification' ) ) {
							$this->render_gamification_tab();
						} else {
							echo '<div class="convoca-alert convoca-alert--info" style="display:block;margin:20px 0;"><p>🔒 <strong>Gamificación</strong> es una funcionalidad PRO. <a href="' . esc_url( admin_url( 'admin.php?page=convoca-license' ) ) . '">Activa tu licencia</a> para desbloquear badges, niveles y puntos de voluntariado.</p></div>';
						}
						break;
					case 'status':
						$this->render_status_tab();
						break;
					case 'general':
					default:
						$this->render_general_tab();
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	public function render_general_tab(): void {
		$settings = get_option( 'convoca_members_settings', array() );
		?>
		<form method="post">
			<?php wp_nonce_field( 'convoca_members_settings_save', '_convoca_settings_nonce' ); ?>
			<input type="hidden" name="convoca_save_settings" value="general">

			<div class="convoca-field">
				<label for="admin_email"><?php esc_html_e( 'Email administrador', 'convoca-members' ); ?></label>
				<input type="email" id="admin_email" name="convoca_members_settings[admin_email]"
					value="<?php echo esc_attr( $settings['admin_email'] ?? '' ); ?>">
				<small class="convoca-small"><?php esc_html_e( 'Recibe notificaciones de nuevas altas.', 'convoca-members' ); ?></small>
			</div>

			<div class="convoca-field">
				<label for="sender_name"><?php esc_html_e( 'Nombre remitente emails', 'convoca-members' ); ?></label>
				<input type="text" id="sender_name" name="convoca_members_settings[sender_name]"
					value="<?php echo esc_attr( $settings['sender_name'] ?? get_bloginfo( 'name' ) ); ?>">
			</div>

			<div class="convoca-field">
				<label for="iban"><?php esc_html_e( 'IBAN para transferencias', 'convoca-members' ); ?></label>
				<input type="text" id="iban" name="convoca_members_settings[iban]"
					value="<?php echo esc_attr( $settings['iban'] ?? '' ); ?>" placeholder="ES00 0000 0000 0000 0000 0000">
			</div>

			<div class="convoca-field">
				<label for="rgpd_version"><?php esc_html_e( 'Versión texto legal RGPD', 'convoca-members' ); ?></label>
				<input type="text" id="rgpd_version" name="convoca_members_settings[rgpd_version]"
					value="<?php echo esc_attr( $settings['rgpd_version'] ?? '1.0' ); ?>">
			</div>

			<div class="convoca-field">
				<label for="min_age"><?php esc_html_e( 'Edad mínima de registro', 'convoca-members' ); ?></label>
				<input type="number" id="min_age" name="convoca_members_settings[min_age]"
					value="<?php echo esc_attr( $settings['min_age'] ?? '0' ); ?>" min="0">
				<small class="convoca-small"><?php esc_html_e( 'Edad mínima permitida para el alta de socios (0 para desactivar).', 'convoca-members' ); ?></small>
			</div>

			<div style="margin-top:30px;">
				<button type="submit" class="convoca-btn convoca-btn-primary"><?php esc_html_e( 'Guardar ajustes', 'convoca-members' ); ?></button>
			</div>
		</form>

		<!-- PRO Features Section -->
		<div class="conv-pro-section" style="margin-top:40px;padding:25px;background:#fefce8;border:2px dashed #eab308;border-radius:12px;">
			<h2 style="margin-top:0;color:#a16207;">✨ Funcionalidades PRO</h2>
			<p style="color:#713f12;">Las siguientes funcionalidades están disponibles con una licencia PRO. <a href="<?php echo esc_url( admin_url( 'admin.php?page=convoca-license' ) ); ?>">Activa tu licencia</a> para desbloquearlas.</p>
			<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px;margin-top:15px;">
				<div style="background:#fff;border-radius:8px;padding:15px;border:1px solid #e2e8f0;opacity:0.7;">
					<span style="font-size:1.2rem;">🏆</span>
					<span style="font-weight:600;margin-left:8px;">Gamificación</span>
					<span style="display:block;font-size:11px;color:#94a3b8;margin-top:4px;">Badges, niveles y puntos de voluntariado</span>
				</div>
				<div style="background:#fff;border-radius:8px;padding:15px;border:1px solid #e2e8f0;opacity:0.7;">
					<span style="font-size:1.2rem;">📄</span>
					<span style="font-weight:600;margin-left:8px;">PDF Memories</span>
					<span style="display:block;font-size:11px;color:#94a3b8;margin-top:4px;">Exportación PDF y tarjetas de socio</span>
				</div>
				<div style="background:#fff;border-radius:8px;padding:15px;border:1px solid #e2e8f0;opacity:0.7;">
					<span style="font-size:1.2rem;">🔗</span>
					<span style="font-weight:600;margin-left:8px;">Webhooks salientes</span>
					<span style="display:block;font-size:11px;color:#94a3b8;margin-top:4px;">Integración con sistemas externos</span>
				</div>
			</div>
		</div>
		<?php
	}

	private function render_plans_tab(): void {
		$plans = CPT_Miembro::get_plans();

		// Ensure at least one empty plan slot for adding new ones is hard to handle in simple options.php form.
		// We will use JS to clone a template or just save what we have.
		// For simplicity in this first iteration: we iterate existing + provide a "Add New" block at the bottom.
		// that serves to create a new key.
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'convoca_members_plans_group' ); ?>

			<div id="conv-plans-container">
				<?php foreach ( $plans as $key => $plan ) : ?>
					<?php $this->render_single_plan_card( $key, $plan ); ?>
				<?php endforeach; ?>
			</div>

			<!-- "Add New" Logic: We can generate a random unique ID for new plans in JS or PHP. -->
			<!-- For now, we will add a hidden template or rely on the user to manually add via code? 
				No, request says "Add from settings". 
				Strategy: A simple "Add Plan" button that reveals a new empty card with a key input. -->

			<div class="conv-add-plan-wrapper"
				style="margin-top: 20px; padding: 20px; border: 2px dashed #ccc; text-align: center;">
				<h3><?php esc_html_e( 'Añadir Nuevo Plan', 'convoca-members' ); ?></h3>
				<p><?php esc_html_e( 'Para añadir un plan, guarda primero los cambios actuales.', 'convoca-members' ); ?></p>
				<div style="text-align: left; max_width: 600px; margin: 0 auto; display: inline-block;">
					<label><?php esc_html_e( 'ID del Plan (ej: <code>socio-colaborador</code>):', 'convoca-members' ); ?> </label>
					<input type="text" id="new_plan_key" placeholder="slug-unico">
					<button type="button" class="button" onclick="convAddNewPlan()"><?php esc_html_e( 'Añadir', 'convoca-members' ); ?></button>
				</div>
			</div>

			<template id="conv-plan-card-template">
				<?php
				$clean_plan = array(
					'label'           => __( 'Nuevo Plan', 'convoca-members' ),
					'price'           => 0,
					'hours'           => 0,
					'modalidad'       => 'Numerario',
					'active'          => 1,
					'order'           => 0,
					'emoji'           => '⭐',
					'description'     => '',
					'payment_methods' => array(),
					'url_bizum'       => '',
					'url_tarjeta'     => '',
					'advantages'      => array(),
				);
				$this->render_single_plan_card( '__KEY__', $clean_plan );
				?>
			</template>

			<script>
				function convAddNewPlan() {
					var key = document.getElementById('new_plan_key').value.trim();
					if (!key) { alert('<?php echo esc_js( __( 'Escribe un ID único para el plan', 'convoca-members' ) ); ?>'); return; }
					key = key.toLowerCase().replace(/[^a-z0-9-]/g, '-');

					var container = document.getElementById('conv-plans-container');
					if (document.querySelector('input[name="convoca_members_plans[' + key + '][label]"]')) {
						alert('<?php echo esc_js( __( 'Este ID ya existe.', 'convoca-members' ) ); ?>');
						return;
					}

					var template = document.getElementById('conv-plan-card-template');
					var clone = template.content.cloneNode(true);
					var html = clone.firstElementChild.outerHTML.replace(/__KEY__/g, key);
					
					var tempDiv = document.createElement('div');
					tempDiv.innerHTML = html;
					container.appendChild(tempDiv.firstElementChild);
					
					document.getElementById('new_plan_key').value = '';
				}
			</script>

			<?php submit_button( __( 'Guardar Planes', 'convoca-members' ) ); ?>
		</form>
		<?php
	}

	private function render_single_plan_card( $key, $plan ): void {
		$active = isset( $plan['active'] ) ? (bool) $plan['active'] : true; // Default active if not set.
		?>
		<div class="conv-plan-card postbox" style="margin-bottom: 20px;">
			<div class="postbox-header"
				style="padding: 10px; display: flex; justify-content: space-between; align-items: center;">
				<h2 style="margin:0; font-size: 1.2em;">
					<span class="bad-emoji"><?php echo esc_html( $plan['emoji'] ?? '📄' ); ?></span>
					<?php echo esc_html( $plan['label'] ); ?>
					<code style="opacity: 0.5;">(<?php echo esc_html( $key ); ?>)</code>
				</h2>
				<div>
					<label>
						<input type="checkbox" name="convoca_members_plans[<?php echo esc_attr( $key ); ?>][active]" value="1" <?php checked( $active ); ?>>
						<?php esc_html_e( 'Activo', 'convoca-members' ); ?>
					</label>
					<button type="button" class="button-link-delete" style="color: #b32d2e; margin-left: 15px;"
						onclick="if(confirm('<?php echo esc_js( __( '¿Eliminar este plan al guardar? (Desmarca activo para ocultarlo)', 'convoca-members' ) ); ?>')) { this.closest('.conv-plan-card').remove(); }">
						<?php esc_html_e( 'Eliminar', 'convoca-members' ); ?>
					</button>
				</div>
			</div>
			<div class="inside" style="padding: 10px;">
				<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
					<div>
						<p>
							<label><strong><?php esc_html_e( 'Nombre visible:', 'convoca-members' ); ?></strong></label>
							<input type="text" name="convoca_members_plans[<?php echo esc_attr( $key ); ?>][label]"
								value="<?php echo esc_attr( $plan['label'] ); ?>" class="large-text">
						</p>
						<div style="display: flex; gap: 10px;">
							<p style="flex:1;">
								<label><strong><?php esc_html_e( 'Emoji:', 'convoca-members' ); ?></strong></label>
								<input type="text" name="convoca_members_plans[<?php echo esc_attr( $key ); ?>][emoji]"
									value="<?php echo esc_attr( $plan['emoji'] ?? '' ); ?>" class="small-text">
							</p>
							<p style="flex:1;">
								<label><strong><?php esc_html_e( 'Orden:', 'convoca-members' ); ?></strong></label>
								<input type="number" name="convoca_members_plans[<?php echo esc_attr( $key ); ?>][order]"
									value="<?php echo esc_attr( $plan['order'] ?? 0 ); ?>" class="small-text">
							</p>
						</div>
						<p>
							<label><strong><?php esc_html_e( 'Descripción:', 'convoca-members' ); ?></strong></label>
							<textarea name="convoca_members_plans[<?php echo esc_attr( $key ); ?>][description]" rows="2"
								class="large-text"><?php echo esc_textarea( $plan['description'] ?? '' ); ?></textarea>
						</p>
					</div>
					<div>
						<div style="display: flex; gap: 10px;">
							<p style="flex:1;">
								<label><strong><?php esc_html_e( 'Precio (€):', 'convoca-members' ); ?></strong></label>
								<input type="number" step="0.01" name="convoca_members_plans[<?php echo esc_attr( $key ); ?>][price]"
									value="<?php echo esc_attr( $plan['price'] ); ?>" class="small-text">
							</p>
							<p style="flex:1;">
								<label><strong><?php esc_html_e( 'Horas Voluntariado:', 'convoca-members' ); ?></strong></label>
								<input type="number" step="0.5" name="convoca_members_plans[<?php echo esc_attr( $key ); ?>][hours]"
									value="<?php echo esc_attr( $plan['hours'] ); ?>" class="small-text">
							</p>
							<p style="flex:1;">
								<label><strong><?php esc_html_e( 'Modalidad:', 'convoca-members' ); ?></strong></label><br>
								<select name="convoca_members_plans[<?php echo esc_attr( $key ); ?>][modalidad]">
									<?php foreach ( array( 'Numerario', 'Familiar', 'Juvenil' ) as $m ) : ?>
										<option value="<?php echo esc_attr( $m ); ?>" <?php selected( $plan['modalidad'], $m ); ?>>
											<?php echo esc_html( $m ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</p>
						</div>

						<div style="background: #f0f0f1; padding: 10px; border-radius: 4px;">
							<p style="margin-top:0;"><strong><?php esc_html_e( 'Pagos:', 'convoca-members' ); ?></strong></p>
							<?php $methods = $plan['payment_methods'] ?? array(); ?>
							<label><input type="checkbox"
									name="convoca_members_plans[<?php echo esc_attr( $key ); ?>][payment_methods][]" value="bizum"
									<?php checked( in_array( 'bizum', $methods ) ); ?>> Bizum</label>
							<label><input type="checkbox"
									name="convoca_members_plans[<?php echo esc_attr( $key ); ?>][payment_methods][]" value="tarjeta"
									<?php checked( in_array( 'tarjeta', $methods ) ); ?>> Tarjeta</label>
							<label><input type="checkbox"
									name="convoca_members_plans[<?php echo esc_attr( $key ); ?>][payment_methods][]"
									value="transferencia" <?php checked( in_array( 'transferencia', $methods ) ); ?>>
								<?php esc_html_e( 'Transferencia', 'convoca-members' ); ?></label>
						</div>
					</div>
				</div>

				<p>
					<label><strong><?php esc_html_e( 'Ventajas (una por línea):', 'convoca-members' ); ?></strong></label>
					<textarea name="convoca_members_plans[<?php echo esc_attr( $key ); ?>][advantages_raw]" rows="4"
						class="large-text">
						<?php
						$adv = $plan['advantages'] ?? array();
						if ( is_array( $adv ) ) {
							echo esc_textarea( implode( "\n", $adv ) );
						}
						?>
						</textarea>
				</p>
			</div>
		</div>
		<?php
	}

	private function render_emails_tab(): void {
		// Save email templates logic specifically for this tab if needed differently,.
		// but since we want to leverage options.php, we might need to register a setting for templates too?
		// THE PREVIOUS CODE handled this manually via POST check.
		// We will keep that manual saving logic but integrate it into the flow.

		if ( isset( $_POST['convoca_save_templates'] ) && check_admin_referer( 'convoca_templates_nonce' ) ) {
			$data      = wp_unslash( $_POST );
			$templates = array();
			foreach ( Email_Manager::TEMPLATES as $slug ) {
				$templates[ $slug ] = array(
					'subject' => sanitize_text_field( $data[ 'tpl_' . $slug . '_subject' ] ?? '' ),
					'body'    => wp_kses_post( $data[ 'tpl_' . $slug . '_body' ] ?? '' ), // Allow HTML.
				);
			}
			Email_Manager::save_templates( $templates );
			delete_option( self::CACHE_KEY );
			echo '<div class="updated"><p>' . esc_html__( 'Plantillas guardadas.', 'convoca-members' ) . '</p></div>';
		}

		$templates  = Email_Manager::get_templates();
		$tpl_labels = array(
			'solicitud_recibida'    => __( 'Solicitud recibida', 'convoca-members' ),
			'bienvenida'            => __( 'Bienvenida (activación)', 'convoca-members' ),
			'recordatorio_pago'     => __( 'Recordatorio de pago', 'convoca-members' ),
			'renovacion'            => __( 'Renovación anual (30 días)', 'convoca-members' ),
			'renovacion_automatica' => __( 'Aviso renovación automática', 'convoca-members' ),
			'renovacion_completada' => __( 'Renovación completada 🎉', 'convoca-members' ),
		);

		?>
		<form method="post">
			<?php wp_nonce_field( 'convoca_templates_nonce' ); ?>
			<p class="description">
				<?php esc_html_e( 'Variables disponibles:', 'convoca-members' ); ?>
				<code>{nombre}</code>, <code>{plan}</code>, <code>{importe}</code>, <code>{link_pago}</code>,
				<code>{fecha_baja}</code>, <code>{numero_socio}</code>
			</p>

			<?php
			foreach ( Email_Manager::TEMPLATES as $slug ) :
				$tpl = $templates[ $slug ] ?? array(
					'subject' => '',
					'body'    => '',
				);
				?>
				<div class="conv-template-card postbox">
					<div class="postbox-header" style="padding:10px;">
						<h2 style="margin:0;"><?php echo esc_html( $tpl_labels[ $slug ] ?? $slug ); ?></h2>
					</div>
					<div class="inside">
						<p>
							<label><strong><?php esc_html_e( 'Asunto:', 'convoca-members' ); ?></strong></label>
							<input type="text" name="tpl_<?php echo esc_attr( $slug ); ?>_subject"
								value="<?php echo esc_attr( $tpl['subject'] ); ?>" class="large-text">
						</p>
						<p>
							<label><strong><?php esc_html_e( 'Cuerpo (HTML permitido):', 'convoca-members' ); ?></strong></label>
							<textarea name="tpl_<?php echo esc_attr( $slug ); ?>_body" rows="12"><?php echo esc_textarea( $tpl['body'] ); ?></textarea>
						</p>
					</div>
				</div>
			<?php endforeach; ?>

			<input type="hidden" name="convoca_save_templates" value="1">
			<?php submit_button( __( 'Guardar Plantillas', 'convoca-members' ) ); ?>
		</form>
		<?php
	}
	private function render_volunteers_tab(): void {
		$fields     = get_option( 'convoca_volunteer_fields', array() );
		$legal_text = get_option( 'convoca_volunteer_legal_text', '' );
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'convoca_members_volunteers_group' ); ?>

			<h2><?php esc_html_e( 'Texto Legal (Declaración Responsable)', 'convoca-members' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Este texto se mostrará junto a un checkbox obligatorio al final del formulario de voluntariado.', 'convoca-members' ); ?></p>
			<textarea name="convoca_volunteer_legal_text" rows="10"><?php echo esc_textarea( $legal_text ); ?></textarea>

			<h2 style="margin-top: 40px;"><?php esc_html_e( 'Campos Personalizados', 'convoca-members' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Estos campos se solicitarán en el formulario de registro de voluntarios. Los campos base (Nombre, DNI, Email, Teléfono) ya están incluidos y no necesitan añadirse aquí.', 'convoca-members' ); ?></p>

			<div id="conv-fields-container">
				<?php foreach ( $fields as $index => $field ) : ?>
					<?php $this->render_single_field_card( $index, $field ); ?>
				<?php endforeach; ?>
			</div>

			<div class="conv-add-field-wrapper" style="margin-top: 20px; padding: 20px; border: 2px dashed #ccc; text-align: center;">
				<h3><?php esc_html_e( 'Añadir Nuevo Campo', 'convoca-members' ); ?></h3>
				<p><?php esc_html_e( 'Para añadir un campo, guarda primero los cambios actuales.', 'convoca-members' ); ?></p>
				<div style="text-align: left; max_width: 600px; margin: 0 auto; display: inline-block;">
					<label><?php esc_html_e( 'ID del Campo (solo minúsculas y guiones, ej: alergias):', 'convoca-members' ); ?> </label>
					<input type="text" id="new_field_name" placeholder="id-del-campo">
					<button type="button" class="button" onclick="convAddNewField()"><?php esc_html_e( 'Añadir', 'convoca-members' ); ?></button>
				</div>
			</div>

			<template id="conv-field-card-template">
				<?php
				$clean_field = array(
					'label'    => 'Nuevo Campo',
					'type'     => 'text',
					'options'  => '',
					'required' => false,
				);
				$this->render_single_field_card( '__INDEX__', $clean_field );
				?>
			</template>

			<script>
				function convAddNewField() {
					var name = document.getElementById('new_field_name').value.trim();
					if (!name) { alert('Escribe un ID único para el campo'); return; }
					name = name.toLowerCase().replace(/[^a-z0-9-]/g, '-');

					var container = document.getElementById('conv-fields-container');
					if (document.querySelector('input[name="convoca_volunteer_fields[' + name + '][label]"]')) {
						alert('Este ID ya existe.');
						return;
					}

					var template = document.getElementById('conv-field-card-template');
					var clone = template.content.cloneNode(true);
					var html = clone.firstElementChild.outerHTML.replace(/__INDEX__/g, name);

					var tempDiv = document.createElement('div');
					tempDiv.innerHTML = html;
					var newCard = tempDiv.firstElementChild;
					
					// Update input names and values.
					var inputs = newCard.querySelectorAll('[name*="__INDEX__"]');
					inputs.forEach(input => {
						input.name = input.name.replace('__INDEX__', name);
					});
					
					var nameInput = newCard.querySelector('input.field-name-input');
					if (nameInput) nameInput.value = name;

					container.appendChild(newCard);
					document.getElementById('new_field_name').value = '';
				}
			</script>

			<p class="submit">
				<?php submit_button( __( 'Guardar Campos y Textos', 'convoca-members' ), 'primary', 'submit', false ); ?>
			</p>
		</form>
		<?php
	}

	private function render_single_field_card( $index, $field ): void {
		$name = esc_attr( $field['name'] ?? $index );
		?>
		<div class="conv-field-card postbox" style="margin-bottom: 15px;">
			<div class="postbox-header" style="padding: 10px; display: flex; justify-content: space-between; align-items: center;">
				<h3 style="margin:0;">
					<?php echo esc_html( $field['label'] ?? 'Nuevo Campo' ); ?>
					<code style="opacity: 0.5;">(<?php echo esc_html( $name ); ?>)</code>
				</h3>
				<button type="button" class="button-link-delete" style="color: #b32d2e;"
					onclick="if(confirm('<?php echo esc_js( __( '¿Eliminar este campo al guardar?', 'convoca-members' ) ); ?>')) { this.closest('.conv-field-card').remove(); }">
					<?php esc_html_e( 'Eliminar', 'convoca-members' ); ?>
				</button>
			</div>
			<div class="inside" style="padding: 10px;">
				<input type="hidden" class="field-name-input" name="convoca_volunteer_fields[<?php echo esc_attr( $index ); ?>][name]" value="<?php echo esc_attr( $name ); ?>">
				
				<div style="display: flex; gap: 20px; align-items: flex-start;">
					<div style="flex: 1;">
						<p>
							<label><strong><?php esc_html_e( 'Etiqueta (Label):', 'convoca-members' ); ?></strong></label><br>
							<input type="text" name="convoca_volunteer_fields[<?php echo esc_attr( $index ); ?>][label]" value="<?php echo esc_attr( $field['label'] ?? '' ); ?>" class="large-text">
						</p>
						<p>
							<label><strong><?php esc_html_e( 'Tipo de campo:', 'convoca-members' ); ?></strong></label><br>
							<select name="convoca_volunteer_fields[<?php echo esc_attr( $index ); ?>][type]">
								<?php
								$types = array(
									'text'     => 'Texto corto',
									'textarea' => 'Texto largo (párrafo)',
									'email'    => 'Email',
									'tel'      => 'Teléfono',
									'number'   => 'Número',
									'date'     => 'Fecha',
									'select'   => 'Desplegable (Select)',
									'checkbox' => 'Casilla de verificación (Checkbox)',
								);
								foreach ( $types as $val => $label ) {
									echo '<option value="' . esc_attr( $val ) . '" ' . selected( $field['type'] ?? 'text', $val, false ) . '>' . esc_html( $label ) . '</option>';
								}
								?>
							</select>
						</p>
						<p>
							<label>
								<input type="checkbox" name="convoca_volunteer_fields[<?php echo esc_attr( $index ); ?>][required]" value="1" <?php checked( ! empty( $field['required'] ) ); ?>>
								<strong><?php esc_html_e( 'Campo obligatorio', 'convoca-members' ); ?></strong>
							</label>
						</p>
					</div>
					<div style="flex: 1;">
						<p>
							<label><strong><?php esc_html_e( 'Opciones (solo para Select):', 'convoca-members' ); ?></strong></label><br>
							<span class="description">Una opción por línea. Ej: Sí \n No \n Tal vez</span><br>
							<textarea name="convoca_volunteer_fields[<?php echo esc_attr( $index ); ?>][options]" rows="4" class="large-text"><?php echo esc_textarea( $field['options'] ?? '' ); ?></textarea>
						</p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the Gamification settings tab.
	 */
	public function render_gamification_tab(): void {
		$tracks = \Convoca\Members\Voluntariado_Gamification::get_tracks_config();

		// Handle POST save.
		if ( isset( $_POST['convoca_save_gamification'] ) && check_admin_referer( 'convoca_gamification_nonce' ) ) {
			$raw       = wp_unslash( $_POST['convoca_gamification_tracks'] ?? array() );
			$sanitized = $this->sanitize_gamification_tracks( $raw );
			update_option( \Convoca\Members\Voluntariado_Gamification::OPTION_KEY, $sanitized );
			echo '<div class="updated"><p>' . esc_html__( 'Badges guardados correctamente.', 'convoca-members' ) . '</p></div>';
			// Reload tracks after save.
			$tracks = \Convoca\Members\Voluntariado_Gamification::get_tracks_config();
		}

		?>
		<div class="conv-gamification-settings">
			<h2><?php esc_html_e( '🏆 Gamificación — Badges por Modalidad', 'convoca-members' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Aquí puedes personalizar los badges de cada track (modalidad). Los valores por defecto se usan si no introduces cambios.', 'convoca-members' ); ?>
			</p>

			<div class="conv-gamification-track-selector" style="margin: 20px 0;">
				<label for="conv-gamification-track-select"><strong><?php esc_html_e( 'Selecciona track:', 'convoca-members' ); ?></strong></label>
				<select id="conv-gamification-track-select" onchange="convGamiSwitchTrack(this.value)">
					<?php foreach ( $tracks as $key => $track ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>">
							<?php echo esc_html( $track['label'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<form method="post">
				<?php wp_nonce_field( 'convoca_gamification_nonce' ); ?>
				<input type="hidden" name="convoca_save_gamification" value="1">

				<?php foreach ( $tracks as $track_key => $track ) : ?>
					<div class="conv-gami-track-panel" id="conv-gami-track-<?php echo esc_attr( $track_key ); ?>"
						style="display: <?php echo $track_key === 'busgosu' ? 'block' : 'none'; ?>;">
						<div class="postbox">
							<div class="postbox-header" style="padding: 12px 15px; display: flex; align-items: center; gap: 10px;">
								<h2 style="margin:0;"><?php echo esc_html( $track['label'] ); ?></h2>
								<code style="opacity:0.5;">(<?php echo esc_html( $track_key ); ?>)</code>
							</div>
							<div class="inside" style="padding: 10px 15px;">
								<p>
									<label><strong><?php esc_html_e( 'Nombre visible del track:', 'convoca-members' ); ?></strong></label>
									<input type="text"
											name="convoca_gamification_tracks[<?php echo esc_attr( $track_key ); ?>][label]"
											value="<?php echo esc_attr( $track['label'] ); ?>"
											class="regular-text">
								</p>

								<h3><?php esc_html_e( 'Niveles', 'convoca-members' ); ?></h3>
								<table class="wp-list-table widefat fixed striped conv-gami-levels-table">
									<thead>
										<tr>
											<th style="width:50px;">#</th>
											<th><?php esc_html_e( 'Nombre', 'convoca-members' ); ?></th>
											<th style="width:80px;"><?php esc_html_e( 'Emoji', 'convoca-members' ); ?></th>
											<th style="width:100px;"><?php esc_html_e( 'Horas mín.', 'convoca-members' ); ?></th>
											<th style="width:100px;"><?php esc_html_e( 'Color', 'convoca-members' ); ?></th>
											<th><?php esc_html_e( 'Descripción', 'convoca-members' ); ?></th>
											<th style="width:120px;"><?php esc_html_e( 'Vista previa', 'convoca-members' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( $track['levels'] as $i => $level ) : ?>
											<tr>
												<td><?php echo (int) $i + 1; ?></td>
												<td>
													<input type="text"
															name="convoca_gamification_tracks[<?php echo esc_attr( $track_key ); ?>][levels][<?php echo esc_attr( $i ); ?>][name]"
															value="<?php echo esc_attr( $level['name'] ); ?>"
															class="regular-text" style="width:100%;">
												</td>
												<td>
													<input type="text"
															name="convoca_gamification_tracks[<?php echo esc_attr( $track_key ); ?>][levels][<?php echo esc_attr( $i ); ?>][emoji]"
															value="<?php echo esc_attr( $level['emoji'] ); ?>"
															style="width:60px; text-align:center; font-size:1.2em;"
															class="conv-emoji-input">
												</td>
												<td>
													<input type="number" step="0.5" min="0"
															name="convoca_gamification_tracks[<?php echo esc_attr( $track_key ); ?>][levels][<?php echo esc_attr( $i ); ?>][hours]"
															value="<?php echo esc_attr( $level['hours'] ); ?>"
															style="width:80px;">
												</td>
												<td>
													<input type="color"
															name="convoca_gamification_tracks[<?php echo esc_attr( $track_key ); ?>][levels][<?php echo esc_attr( $i ); ?>][color]"
															value="<?php echo esc_attr( $level['color'] ); ?>"
															style="width:60px; height:30px; padding:0; border:none; cursor:pointer;">
												</td>
												<td>
													<input type="text"
															name="convoca_gamification_tracks[<?php echo esc_attr( $track_key ); ?>][levels][<?php echo esc_attr( $i ); ?>][desc]"
															value="<?php echo esc_attr( $level['desc'] ?? '' ); ?>"
															class="regular-text" style="width:100%;">
												</td>
												<td>
													<div class="conv-gami-preview" style="display:flex; align-items:center; gap:6px;">
														<span style="font-size:1.4rem;"><?php echo esc_html( $level['emoji'] ); ?></span>
														<span style="font-weight:600; color:<?php echo esc_attr( $level['color'] ); ?>; font-size:0.85rem;"><?php echo esc_html( $level['name'] ); ?></span>
													</div>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						</div>
					</div>
				<?php endforeach; ?>

				<p class="submit">
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Guardar cambios', 'convoca-members' ); ?>
					</button>
				</p>
			</form>
		</div>

		<script>
		function convGamiSwitchTrack(trackKey) {
			document.querySelectorAll('.conv-gami-track-panel').forEach(function(el) {
				el.style.display = 'none';
			});
			var panel = document.getElementById('conv-gami-track-' + trackKey);
			if (panel) panel.style.display = 'block';
		}
		</script>
		<?php
	}

	private function render_status_tab(): void {
		$checks = $this->get_system_checks( true );
		\Convoca\Core\Utils::render_diagnostic_panel( $checks, __( 'Estado del Sistema', 'convoca-members' ) );
	}

	private function get_system_checks( bool $force = false ): array {
		if ( ! $force ) {
			$cached = get_option( self::CACHE_KEY );
			if ( $cached && isset( $cached['expires'] ) && $cached['expires'] > time() ) {
				return $cached['results'];
			}
		}

		$checks = array();

		// 1. Plugins
		$plugin_definitions = array(
			'convoca-core'    => array(
				'name'     => 'Convoca Common',
				'class'    => '\\Convoca\\Core\\Utils',
				'severity' => 'error',
			),
			'convoca-enroll'  => array(
				'name'     => 'Convoca Enroll',
				'class'    => '\\Convoca\\Enroll\\Motor_Inscripcion',
				'severity' => 'warning',
			),
			'convoca-gateway' => array(
				'name'     => 'Convoca Gateway',
				'class'    => '\\Convoca\\Gateway\\Payment_Handler',
				'severity' => 'warning',
			),
		);

		foreach ( $plugin_definitions as $slug => $data ) {
			$is_active = class_exists( $data['class'] );
			$checks[]  = array(
				'title'   => sprintf( /* translators: %s: plugin name */ __( 'Plugin: %s', 'convoca-members' ), $data['name'] ),
				'status'  => $is_active ? 'ok' : $data['severity'],
				'message' => $is_active ? __( 'Activo y funcionando.', 'convoca-members' ) : __( 'Plugin no detectado o inactivo.', 'convoca-members' ),
				'fix'     => ! $is_active ? sprintf( /* translators: %s: plugin name */ __( 'Instala y activa el plugin %s.', 'convoca-members' ), $data['name'] ) : '',
			);
		}

		// 2. Pages
		$required_pages = array(
			'convoca_alta_socio'            => array(
				'title'     => __( 'Página: Alta de Socio', 'convoca-members' ),
				'shortcode' => '[convoca_alta]',
				'fix'       => __( 'Crea una página con el shortcode [convoca_alta].', 'convoca-members' ),
			),
			'convoca_voluntariado'          => array(
				'title'     => __( 'Página: Registro Voluntariado', 'convoca-members' ),
				'shortcode' => '[convoca_voluntariado]',
				'fix'       => __( 'Crea una página con el shortcode [convoca_voluntariado].', 'convoca-members' ),
			),
			'convoca_mi_area'               => array(
				'title'     => __( 'Página: Área de Miembro', 'convoca-members' ),
				'shortcode' => '[convoca_mi_area]',
				'fix'       => __( 'Crea una página con el shortcode [convoca_mi_area].', 'convoca-members' ),
			),
			'convoca_verificar_certificado' => array(
				'title'     => __( 'Página: Verificación de Certificados', 'convoca-members' ),
				'shortcode' => '[convoca_verificar_certificado]',
				'fix'       => __( 'Crea una página con el shortcode [convoca_verificar_certificado].', 'convoca-members' ),
			),
			'convoca_panel_reservas'        => array(
				'title'     => __( 'Página: Panel de Reservas', 'convoca-members' ),
				'shortcode' => '[convoca_panel_reservas]',
				'fix'       => __( 'Esta página es necesaria para el enlace QR de la tarjeta. Créala con el shortcode [convoca_panel_reservas].', 'convoca-members' ),
			),
		);

		foreach ( $required_pages as $slug => $data ) {
			$page     = $this->find_page_by_shortcode( $data['shortcode'] );
			$checks[] = array(
				'title'   => $data['title'],
				'status'  => $page ? 'ok' : 'error',
				'message' => $page ? sprintf( /* translators: %s: page title */ __( 'Detectada: %s', 'convoca-members' ), get_the_title( $page ) ) : __( 'No se ha encontrado ninguna página con este shortcode.', 'convoca-members' ),
				'fix'     => ! $page ? $data['fix'] : '',
			);
		}

		// 3. Database
		$db_version = get_option( 'convoca_members_db_version', '0' );
		$is_db_ok   = version_compare( $db_version, CONVOCA_MEMBERS_DB_VERSION, '>=' );
		$checks[]   = array(
			'title'   => __( 'Base de Datos', 'convoca-members' ),
			'status'  => $is_db_ok ? 'ok' : 'warning',
			'message' => sprintf( /* translators: %s: database version */ __( 'Versión actual: %s', 'convoca-members' ), $db_version ),
			'fix'     => ! $is_db_ok ? __( 'La base de datos necesita actualizarse. Desactiva y vuelve a activar el plugin.', 'convoca-members' ) : '',
		);

		update_option(
			self::CACHE_KEY,
			array(
				'results' => $checks,
				'expires' => time() + HOUR_IN_SECONDS,
			)
		);

		return $checks;
	}

	private function find_page_by_shortcode( string $shortcode ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Statement is prepared via $wpdb->prepare() above.
		$query = $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_content LIKE %s AND post_status = 'publish' AND post_type = 'page' LIMIT 1", '%' . $wpdb->esc_like( $shortcode ) . '%' );
		return $wpdb->get_var( $query );
	}
}
