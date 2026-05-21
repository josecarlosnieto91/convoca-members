<?php
/**
 * Public verification page for certificates.
 * Shortcode: [biodevas_verificar_certificado]
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

		echo '<div class="bdv-verify-container">';

		if ( $search ) {
			$result = Certificate_Generator::verify( $search );

			if ( $result ) {
				?>
				<div class="biodevas-alert biodevas-alert--ok biodevas-card bdv-cert-result">
					<div class="bdv-cert-header">
						<span class="bdv-cert-icon">✅</span>
						<h2 class="text-gradient"><?php _e( 'Certificado Válido', 'convoca-members' ); ?></h2>
					</div>
					<div class="bdv-cert-details">
						<div class="detail-row">
							<span class="detail-label"><?php _e( 'Nombre:', 'convoca-members' ); ?></span>
							<span class="detail-value"><?php echo esc_html( $result['nombre'] ); ?></span>
						</div>
						<div class="detail-row">
							<span class="detail-label"><?php _e( 'Horas:', 'convoca-members' ); ?></span>
							<span class="detail-value"><?php echo number_format( $result['horas'], 1 ); ?>h</span>
						</div>
						<div class="detail-row">
							<span class="detail-label"><?php _e( 'Emisión:', 'convoca-members' ); ?></span>
							<span class="detail-value"><?php echo wp_date( 'd/m/Y', strtotime( $result['emitido'] ) ); ?></span>
						</div>
						<div class="detail-row">
							<span class="detail-label"><?php _e( 'ID:', 'convoca-members' ); ?></span>
							<span class="detail-value"><code><?php echo esc_html( $result['certificado_id'] ); ?></code></span>
						</div>
					</div>
				</div>
				<?php
			} else {
				?>
				<div class="biodevas-alert biodevas-alert--danger biodevas-card">
					<div class="bdv-cert-header">
						<span class="bdv-cert-icon">❌</span>
						<h2><?php _e( 'Certificado No Encontrado', 'convoca-members' ); ?></h2>
					</div>
					<p><?php _e( 'El ID de certificado proporcionado no es válido o no existe en nuestros registros.', 'convoca-members' ); ?></p>
				</div>
				<?php
			}
		}

		?>
		<div class="biodevas-card biodevas-form bdv-verify-card">
			<h2 class="text-gradient">🔍 <?php _e( 'Verificar Certificado', 'convoca-members' ); ?></h2>
			<p class="subtitle"><?php _e( 'Introduce el ID del certificado para verificar su autenticidad y validez oficial.', 'convoca-members' ); ?></p>
			
			<form method="post" class="bdv-verify-form">
				<div class="form-group">
					<label for="certificado_id"><?php _e( 'ID del Certificado', 'convoca-members' ); ?></label>
					<input type="text" id="certificado_id" name="certificado_id" 
							placeholder="Ej: VOL-2025-XXXXX" 
							value="<?php echo esc_attr( $search ); ?>" required>
				</div>
				<div class="form-actions">
					<button type="submit" class="wp-block-button__link">
						<?php _e( 'Verificar Ahora', 'convoca-members' ); ?>
					</button>
				</div>
			</form>
		</div>
		</div>
		<?php

		return ob_get_clean();
	}
}