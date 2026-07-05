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
 * Public verification page for certificates.
 * Shortcode: [convoca_verificar_certificado]
 *
 * @package Convoca\Members
 */

namespace Convoca\Members;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Verificar_Certificado {

	public function __construct() {
		add_shortcode( 'convoca_verificar_certificado', array( $this, 'render' ) );
	}

	public function render(): string {
		$cert_id = isset( $_GET['id'] ) ? sanitize_text_field( $_GET['id'] ) : '';
		$search  = isset( $_POST['certificado_id'] ) ? sanitize_text_field( $_POST['certificado_id'] ) : $cert_id;

		ob_start();

		echo '<div class="conv-verify-container">';

		if ( $search ) {
			$result = Certificate_Generator::verify( $search );

			if ( $result ) {
				?>
				<div class="convoca-alert convoca-alert--ok convoca-card conv-cert-result">
					<div class="conv-cert-header">
						<span class="conv-cert-icon">✅</span>
						<h2 class="text-gradient"><?php esc_html_e( 'Certificado Válido', 'convoca-members' ); ?></h2>
					</div>
					<div class="conv-cert-details">
						<div class="detail-row">
							<span class="detail-label"><?php esc_html_e( 'Nombre:', 'convoca-members' ); ?></span>
							<span class="detail-value"><?php echo esc_html( $result['nombre'] ); ?></span>
						</div>
						<div class="detail-row">
							<span class="detail-label"><?php esc_html_e( 'Horas:', 'convoca-members' ); ?></span>
							<span class="detail-value"><?php echo number_format( $result['horas'], 1 ); ?>h</span>
						</div>
						<div class="detail-row">
							<span class="detail-label"><?php esc_html_e( 'Emisión:', 'convoca-members' ); ?></span>
							<span class="detail-value"><?php echo wp_date( 'd/m/Y', strtotime( $result['emitido'] ) ); ?></span>
						</div>
						<div class="detail-row">
							<span class="detail-label"><?php esc_html_e( 'ID:', 'convoca-members' ); ?></span>
							<span class="detail-value"><code><?php echo esc_html( $result['certificado_id'] ); ?></code></span>
						</div>
					</div>
				</div>
				<?php
			} else {
				?>
				<div class="convoca-alert convoca-alert--danger convoca-card">
					<div class="conv-cert-header">
						<span class="conv-cert-icon">❌</span>
						<h2><?php esc_html_e( 'Certificado No Encontrado', 'convoca-members' ); ?></h2>
					</div>
					<p><?php esc_html_e( 'El ID de certificado proporcionado no es válido o no existe en nuestros registros.', 'convoca-members' ); ?></p>
				</div>
				<?php
			}
		}

		?>
		<div class="convoca-card convoca-form conv-verify-card">
			<h2 class="text-gradient">🔍 <?php esc_html_e( 'Verificar Certificado', 'convoca-members' ); ?></h2>
			<p class="subtitle"><?php esc_html_e( 'Introduce el ID del certificado para verificar su autenticidad y validez oficial.', 'convoca-members' ); ?></p>
			
			<form method="post" class="conv-verify-form">
				<div class="form-group">
					<label for="certificado_id"><?php esc_html_e( 'ID del Certificado', 'convoca-members' ); ?></label>
					<input type="text" id="certificado_id" name="certificado_id" 
							placeholder="Ej: VOL-2025-XXXXX" 
							value="<?php echo esc_attr( $search ); ?>" required>
				</div>
				<div class="form-actions">
					<button type="submit" class="wp-block-button__link">
						<?php esc_html_e( 'Verificar Ahora', 'convoca-members' ); ?>
					</button>
				</div>
			</form>
		</div>
		</div>
		<?php

		return ob_get_clean();
	}
}