<?php
namespace Convoca\Members;

/**
 * Admin Metaboxes for CPT Miembro.
 *
 * @package Convoca\Members
 */

use Convoca\Gateway\Payment_Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Metaboxes {

	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_metaboxes' ) );
		add_action( 'save_post_miembro', array( $this, 'save_metaboxes' ) );

		// AJAX Handlers.
		add_action( 'wp_ajax_bdv_send_payment_link', array( $this, 'ajax_send_payment_link' ) );
		add_action( 'wp_ajax_bdv_send_reminder', array( $this, 'ajax_send_reminder' ) );
		add_action( 'wp_ajax_bdv_export_member_data', array( $this, 'ajax_export_member_data' ) );
		add_action( 'wp_ajax_bdv_delete_member_data', array( $this, 'ajax_delete_member_data' ) );

		// User Profile (for Volunteers).
		add_action( 'show_user_profile', array( $this, 'render_user_documents_section' ), 10, 1 );
		add_action( 'edit_user_profile', array( $this, 'render_user_documents_section' ), 10, 1 );
	}

	public function add_metaboxes(): void {
		add_meta_box(
			'bdv_miembro_details',
			'Datos del Miembro',
			array( $this, 'render_metabox' ),
			'miembro',
			'normal',
			'high'
		);

		add_meta_box(
			'bdv_miembro_actions',
			'Acciones y Pagos',
			array( $this, 'render_actions_metabox' ),
			'miembro',
			'side',
			'high'
		);
	}

	public function render_user_documents_section( \WP_User $user ): void {
		if ( ! current_user_can( 'gestionar_documentos_voluntariado' ) && get_current_user_id() !== $user->ID ) {
			return;
		}

		$docs = get_posts(
			array(
				'post_type'      => 'bdv_documento',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'meta_key'       => '_bdv_usuario_id',
				'meta_value'     => $user->ID,
			)
		);

		?>
		<h2><?php esc_html_e( 'Documentos de Voluntariado', 'convoca-members' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label><?php esc_html_e( 'Documentos Generados', 'convoca-members' ); ?></label></th>
				<td>
					<?php if ( empty( $docs ) ) : ?>
						<p><?php esc_html_e( 'No hay documentos asociados a este usuario.', 'convoca-members' ); ?></p>
					<?php else : ?>
						<ul>
							<?php foreach ( $docs as $doc ) : ?>
								<?php $url = get_post_meta( $doc->ID, '_bdv_documento_url', true ); ?>
								<li style="margin-bottom: 5px;">
									<strong><?php echo esc_html( get_the_title( $doc ) ); ?></strong>
									<?php if ( $url ) : ?>
										- <a href="<?php echo esc_url( $url ); ?>" target="_blank" class="button button-small"><?php esc_html_e( 'Ver PDF', 'convoca-members' ); ?></a>
									<?php endif; ?>
									<span style="color: #666; font-size: 0.9em; margin-left: 10px;">
										(Generado el <?php echo esc_html( get_the_date( 'd/m/Y H:i', $doc ) ); ?>)
									</span>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</td>
			</tr>
		</table>
		<?php
	}

	public function render_actions_metabox( \WP_Post $post ): void {
		$pago_id   = get_post_meta( $post->ID, '_bdv_pago_id', true );
		$pago_link = '';
		if ( $pago_id ) {
			$pago_link = Payment_Handler::get_payment_link( (int) $pago_id );
		}

		$estado_cuota = get_post_meta( $post->ID, '_bdv_estado_cuota', true );
		?>
		<div class="bdv-actions-panel">
			<p><strong>Estado Cuota:</strong> <?php echo esc_html( ucfirst( $estado_cuota ?: 'pendiente' ) ); ?></p>

			<?php if ( $pago_id ) : ?>
				<p>
					<strong>Pago Activo:</strong> #<?php echo esc_html( $pago_id ); ?>
					<br>
					<a href="<?php echo esc_url( $pago_link ); ?>" target="_blank" style="text-decoration:none;">🔗 Ver enlace de pago</a>
				</p>
			<?php endif; ?>

			<button type="button" class="biodevas-btn biodevas-btn-primary biodevas-btn--full" id="bdv-send-payment-link" style="margin-bottom:10px;">
				Generar y Enviar Link de Pago
			</button>

			<button type="button" class="biodevas-btn biodevas-btn-secondary biodevas-btn--full" id="bdv-send-reminder">
				Enviar Recordatorio
			</button>

			<div id="bdv-ajax-response" style="margin-top:10px;"></div>
			
			<hr>

			<h4 style="margin:15px 0 10px 0;"><?php esc_html_e( 'RGPD', 'convoca-members' ); ?></h4>
			<button type="button" class="biodevas-btn biodevas-btn-outline biodevas-btn--full" id="bdv-export-data" style="margin-bottom:5px;">
				📥 Exportar datos (JSON)
			</button>
			<button type="button" class="biodevas-btn biodevas-btn-outline biodevas-btn--full biodevas-btn--danger" id="bdv-delete-data">
				🗑️  Eliminar todos los datos
			</button>

			<div id="bdv-rgpd-response" style="margin-top:10px;"></div>
		</div>

		<script>
			document.addEventListener('DOMContentLoaded', function () {
				var postId = <?php echo $post->ID; ?>;
				var nonce = '<?php echo wp_create_nonce( 'bdv_actions_' . $post->ID ); ?>';
				var msgBox = document.getElementById('bdv-ajax-response');
				var rgpdMsgBox = document.getElementById('bdv-rgpd-response');

				function bdv_ajax_action(action, btn) {
					btn.disabled = true;
					btn.textContent = 'Procesando...';
					msgBox.innerHTML = '';

					var fd = new FormData();
					fd.append('action', action);
					fd.append('post_id', postId);
					fd.append('nonce', nonce);

					fetch(ajaxurl, {
						method: 'POST',
						body: fd
					}).then(r => r.json()).then(res => {
						btn.disabled = false;
						btn.textContent = action === 'bdv_send_payment_link' ? 'Generar y Enviar Link de Pago' : 'Enviar Recordatorio';
						
						if (res.success) {
							msgBox.innerHTML = '<div class="notice notice-success inline"><p>' + res.data.message + '</p></div>';
							if (res.data.reload) setTimeout(function () { location.reload(); }, 1500);
						} else {
							msgBox.innerHTML = '<div class="notice notice-error inline"><p>' + (res.data ? res.data.message : 'Error desconocido') + '</p></div>';
						}
					}).catch(function () {
						btn.disabled = false;
						btn.textContent = 'Reintentar';
						msgBox.innerHTML = '<div class="notice notice-error inline"><p>Error de servidor.</p></div>';
					});
				}

				var btnPaymentLink = document.getElementById('bdv-send-payment-link');
				if (btnPaymentLink) {
					btnPaymentLink.addEventListener('click', function () {
						if (confirm('¿Generar un nuevo pago en Redsys y enviar email al socio?')) {
							bdv_ajax_action('bdv_send_payment_link', this);
						}
					});
				}

				var btnReminder = document.getElementById('bdv-send-reminder');
				if (btnReminder) {
					btnReminder.addEventListener('click', function () {
						if (confirm('¿Reenviar el recordatorio de pago?')) {
							bdv_ajax_action('bdv_send_reminder', this);
						}
					});
				}

				var btnExportData = document.getElementById('bdv-export-data');
				if (btnExportData) {
					btnExportData.addEventListener('click', function () {
						if (!confirm('¿Exportar todos los datos de este miembro en formato JSON?')) return;
						
						var fd = new FormData();
						fd.append('action', 'bdv_export_member_data');
						fd.append('post_id', postId);
						fd.append('nonce', nonce);

						fetch(ajaxurl, {
							method: 'POST',
							body: fd
						}).then(r => r.json()).then(res => {
							if (res.success) {
								var blob = new Blob([JSON.stringify(res.data, null, 2)], {type: 'application/json'});
								var url = URL.createObjectURL(blob);
								var a = document.createElement('a');
								a.href = url;
								a.download = 'miembro-' + postId + '-datos.json';
								a.click();
								URL.revokeObjectURL(url);
								rgpdMsgBox.innerHTML = '<div class="notice notice-success inline"><p>Datos exportados correctamente.</p></div>';
							} else {
								rgpdMsgBox.innerHTML = '<div class="notice notice-error inline"><p>Error: ' + (res.data ? res.data.message : 'Error desconocido') + '</p></div>';
							}
						}).catch(function(err) {
							rgpdMsgBox.innerHTML = '<div class="notice notice-error inline"><p>Error de conexión.</p></div>';
						});
					});
				}

				var btnDeleteData = document.getElementById('bdv-delete-data');
				if (btnDeleteData) {
					btnDeleteData.addEventListener('click', function () {
						if (!confirm('⚠️ ¿Estás seguro? Esta acción eliminará PERMANENTEMENTE todos los datos del miembro (incluyendo inscripciones, pagos y logs). Esta acción no se puede deshacer.')) return;
						if (!confirm('¿Confirmas definitivamente la eliminación de todos los datos?')) return;
						
						var fd = new FormData();
						fd.append('action', 'bdv_delete_member_data');
						fd.append('post_id', postId);
						fd.append('nonce', nonce);

						fetch(ajaxurl, {
							method: 'POST',
							body: fd
						}).then(r => r.json()).then(res => {
							if (res.success) {
								rgpdMsgBox.innerHTML = '<div class="notice notice-success inline"><p>' + res.data.message + '</p></div>';
								setTimeout(function () { location.href = '<?php echo admin_url( 'edit.php?post_type=miembro' ); ?>'; }, 2000);
							} else {
								rgpdMsgBox.innerHTML = '<div class="notice notice-error inline"><p>Error: ' + (res.data ? res.data.message : 'Error desconocido') + '</p></div>';
							}
						}).catch(function(err) {
							rgpdMsgBox.innerHTML = '<div class="notice notice-error inline"><p>Error de conexión.</p></div>';
						});
					});
				}
			});
		</script>
		<?php
	}

	public function render_metabox( \WP_Post $post ): void {
		wp_nonce_field( 'bdv_save_miembro', 'bdv_miembro_nonce' );

		$get   = fn( $k ) => get_post_meta( $post->ID, '_bdv_' . $k, true );
		$plans = CPT_Miembro::get_plans();
		?>
		<div class="biodevas-grid-2">
			<!-- Status & Plan -->
			<h3 class="biodevas-field" style="grid-column: 1 / -1; margin-top: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--bde-border, #ccc);">Estado y Plan</h3>

			<div class="biodevas-field">
				<label for="bdv_estado_miembro">Estado</label>
				<select name="bdv_estado_miembro" id="bdv_estado_miembro">
					<?php foreach ( Estados::LABELS as $slug => $label ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $get( 'estado_miembro' ), $slug ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="biodevas-field">
				<label for="bdv_plan">Plan</label>
				<select name="bdv_plan" id="bdv_plan">
					<option value="">— Seleccionar —</option>
					<?php foreach ( $plans as $slug => $data ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $get( 'plan' ), $slug ); ?>>
							<?php echo esc_html( $data['label'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="biodevas-field">
				<label for="bdv_sub_plan">Sub-plan (si aplica)</label>
				<input type="text" name="bdv_sub_plan" value="<?php echo esc_attr( $get( 'sub_plan' ) ); ?>"
					placeholder="e.g. fam-busgosu">
			</div>

			<div class="biodevas-field">
				<label for="bdv_forma_pago">Forma de pago</label>
				<select name="bdv_forma_pago">
					<option value="cuota" <?php selected( $get( 'forma_pago' ), 'cuota' ); ?>>Cuota económica</option>
					<option value="voluntariado" <?php selected( $get( 'forma_pago' ), 'voluntariado' ); ?>>Voluntariado</option>
				</select>
			</div>

			<div class="biodevas-field">
				<div class="biodevas-check-group">
					<input type="checkbox" name="bdv_pago_recurrente" value="1" <?php checked( $get( 'pago_recurrente' ), '1' ); ?>>
					<label><?php _e( 'Renovación automática anual', 'convoca-members' ); ?></label>
				</div>
			</div>

			<!-- Personal Data -->
			<h3 class="biodevas-field" style="grid-column: 1 / -1; margin-top: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--bde-border, #ccc);">Datos Personales</h3>

			<div class="biodevas-field">
				<label for="bdv_email">Email</label>
				<input type="email" name="bdv_email" value="<?php echo esc_attr( $get( 'email' ) ); ?>">
			</div>

			<div class="biodevas-field">
				<label for="bdv_dni">DNI / NIE</label>
				<input type="text" name="bdv_dni" value="<?php echo esc_attr( $get( 'dni' ) ); ?>">
			</div>

			<div class="biodevas-field">
				<label for="bdv_telefono">Teléfono</label>
				<input type="text" name="bdv_telefono" value="<?php echo esc_attr( $get( 'telefono' ) ); ?>">
			</div>

			<div class="biodevas-field">
				<label for="bdv_fecha_nacimiento">Fecha de nacimiento</label>
				<input type="date" name="bdv_fecha_nacimiento" value="<?php echo esc_attr( $get( 'fecha_nacimiento' ) ); ?>">
			</div>

			<!-- Address -->
			<h3 class="biodevas-field" style="grid-column: 1 / -1; margin-top: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--bde-border, #ccc);">Dirección</h3>

			<div class="biodevas-field">
				<label for="bdv_direccion">Dirección</label>
				<input type="text" name="bdv_direccion" value="<?php echo esc_attr( $get( 'direccion' ) ); ?>">
			</div>

			<div class="biodevas-field">
				<label for="bdv_municipio">Municipio</label>
				<input type="text" name="bdv_municipio" value="<?php echo esc_attr( $get( 'municipio' ) ); ?>">
			</div>

			<!-- Notes -->
			<h3 class="biodevas-field" style="grid-column: 1 / -1; margin-top: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--bde-border, #ccc);">Notas Internas</h3>

			<div class="biodevas-field" style="grid-column: 1 / -1;">
				<label for="bdv_observaciones">Observaciones</label>
				<textarea name="bdv_observaciones" rows="4"><?php echo esc_textarea( $get( 'observaciones' ) ); ?></textarea>
			</div>

			<!-- Voluntariado -->
			<h3 class="biodevas-field" style="grid-column: 1 / -1; margin-top: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--bde-border, #ccc);">Voluntariado</h3>

			<div class="biodevas-field">
				<div class="biodevas-check-group">
					<?php
					$is_vol = $get( 'es_voluntario' );
					// If creating new and es_voluntario=1 in URL.
					if ( $post->post_status === 'auto-draft' && isset( $_GET['es_voluntario'] ) ) {
						$is_vol = '1';
					}
					?>
					<input type="checkbox" name="bdv_es_voluntario" value="1" <?php checked( $is_vol, '1' ); ?>>
					<label>¿Es voluntario?</label>
				</div>
			</div>

			<div class="biodevas-field">
				<label for="bdv_intereses">Intereses / Áreas</label>
				<input type="text" name="bdv_intereses" value="<?php echo esc_attr( $get( 'intereses' ) ); ?>" placeholder="e.g. Educación, Aves, Marina">
			</div>

			<div class="biodevas-field">
				<label for="bdv_disponibilidad">Disponibilidad</label>
				<input type="text" name="bdv_disponibilidad" value="<?php echo esc_attr( $get( 'disponibilidad' ) ); ?>">
			</div>

			<div class="biodevas-field">
				<label for="bdv_tipo_voluntariado">Tipo de voluntariado</label>
				<input type="text" name="bdv_tipo_voluntariado" value="<?php echo esc_attr( $get( 'tipo_voluntariado' ) ); ?>">
			</div>

			<div class="biodevas-field" style="grid-column: 1 / -1;">
				<label for="bdv_experiencia">Experiencia previa</label>
				<textarea name="bdv_experiencia" rows="2"><?php echo esc_textarea( $get( 'experiencia' ) ); ?></textarea>
			</div>

			<div class="biodevas-field" style="grid-column: 1 / -1;">
				<label for="bdv_motivacion">Motivación</label>
				<textarea name="bdv_motivacion" rows="2"><?php echo esc_textarea( $get( 'motivacion' ) ); ?></textarea>
			</div>
		</div>
		<?php
	}

	public function ajax_send_payment_link(): void {
		$this->verify_ajax();
		$data    = wp_unslash( $_POST );
		$post_id = (int) $data['post_id'];

		// Get Plan and Price.
		$plan_key = get_post_meta( $post_id, '_bdv_plan', true );
		$plan     = CPT_Miembro::get_plan( $plan_key );

		if ( ! $plan ) {
			wp_send_json_error( array( 'message' => 'Plan no válido o no asignado.' ) );
		}

		$amount = $plan['price'];
		if ( $amount <= 0 ) {
			wp_send_json_error( array( 'message' => 'Este plan no tiene coste económico.' ) );
		}

		// Create Payment in Gateway.
		if ( ! \Convoca\Core\Features::is_gateway_active() || ! function_exists( 'Convoca\Gateway\bdv_gateway_create_payment' ) ) {
			wp_send_json_error( array( 'message' => 'Gateway no activo.' ) );
		}

		$result = \Convoca\Gateway\bdv_gateway_create_payment(
			array(
				'amount_cents' => (int) ( $amount * 100 ),
				'origin'       => 'members',
				'origin_id'    => $post_id,
				'product_desc' => 'Cuota Socio: ' . $plan['label'],
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Save Payment ID to Member.
		update_post_meta( $post_id, '_bdv_pago_id', $result['pago_id'] );
		update_post_meta( $post_id, '_bdv_estado_cuota', 'pendiente' );

		// Send Email.
		$email_manager = new Email_Manager();
		$email_manager->send_recordatorio_pago( $post_id, array( '{link_pago}' => $result['payment_url'] ) );

		wp_send_json_success(
			array(
				'message' => 'Enlace generado y enviado correctamente. Recargando...',
				'reload'  => true,
			)
		);
	}

	public function ajax_send_reminder(): void {
		$this->verify_ajax();
		$data    = wp_unslash( $_POST );
		$post_id = (int) $data['post_id'];

		$pago_id = get_post_meta( $post_id, '_bdv_pago_id', true );
		if ( ! $pago_id ) {
			wp_send_json_error( array( 'message' => 'No hay un pago activo. Genera uno primero.' ) );
		}

		$payment_url = Payment_Handler::get_payment_link( (int) $pago_id );

		// Send Email using existing functionality.
		$email_manager = new Email_Manager();
		$email_manager->send_recordatorio_pago( $post_id, array( '{link_pago}' => $payment_url ) );

		wp_send_json_success( array( 'message' => 'Recordatorio enviado correctamente.' ) );
	}

	private function verify_ajax(): void {
		$data    = wp_unslash( $_POST );
		$post_id = isset( $data['post_id'] ) ? (int) $data['post_id'] : 0;
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => 'Sin permisos sobre este registro.' ) );
		}
		if ( ! isset( $data['nonce'] ) || ! wp_verify_nonce( $data['nonce'], 'bdv_actions_' . $post_id ) ) {
			wp_send_json_error( array( 'message' => 'Nonce inválido.' ) );
		}
	}

	public function save_metaboxes( int $post_id ): void {
		$data = wp_unslash( $_POST );
		if ( ! isset( $data['bdv_miembro_nonce'] ) || ! wp_verify_nonce( $data['bdv_miembro_nonce'], 'bdv_save_miembro' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$fields = array(
			'plan',
			'sub_plan',
			'forma_pago',
			'email',
			'dni',
			'telefono',
			'fecha_nacimiento',
			'direccion',
			'municipio',
			'observaciones',
			'pago_recurrente',
			'es_voluntario',
			'intereses',
			'disponibilidad',
			'tipo_voluntariado',
			'experiencia',
			'motivacion',
		);

		foreach ( $fields as $field ) {
			if ( $field === 'pago_recurrente' || $field === 'es_voluntario' ) {
				$val = isset( $data[ 'bdv_' . $field ] ) ? '1' : '0';
			} else {
				$val = isset( $data[ 'bdv_' . $field ] ) ? sanitize_text_field( $data[ 'bdv_' . $field ] ) : '';
			}
			update_post_meta( $post_id, '_bdv_' . $field, $val );
		}

		// Handle State Change via State Machine.
		$new_state = isset( $data['bdv_estado_miembro'] ) ? sanitize_text_field( $data['bdv_estado_miembro'] ) : '';
		$old_state = get_post_meta( $post_id, '_bdv_estado_miembro', true );

		if ( $new_state && $new_state !== $old_state ) {
			Estados::change( $post_id, $new_state, __( 'Cambio manual desde edición de miembro.', 'convoca-members' ) );

			// If activating, ensure they have a number and cuota is active.
			if ( $new_state === 'activo' ) {
				$num = get_post_meta( $post_id, '_bdv_numero_socio', true );
				if ( ! $num ) {
					$num = CPT_Miembro::get_next_member_number( $post_id );
					update_post_meta( $post_id, '_bdv_numero_socio', $num );
					\Convoca\Core\Logger::info( "Número de socio #$num asignado automáticamente al activar.", 'Members/Admin', $post_id );
				}

				$estado_cuota = get_post_meta( $post_id, '_bdv_estado_cuota', true );
				if ( $estado_cuota !== 'activa' ) {
					update_post_meta( $post_id, '_bdv_estado_cuota', 'activa' );
					\Convoca\Core\Logger::info( "Estado de cuota marcado como 'Activa' al activar miembro manualmente.", 'Members/Admin', $post_id );
				}
			}
		}

		// Handle title sync if needed (optional).
		if ( isset( $_POST['post_title'] ) && empty( $_POST['post_title'] ) ) {
			// Could auto-generate title from name if we had a separate name field, currently post_title is Name.
		}
	}

	public function ajax_export_member_data(): void {
		$data = wp_unslash( $_POST );
		check_ajax_referer( 'bdv_actions_' . $data['post_id'], 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Sin permisos' ) );
		}

		$member_id = absint( $data['post_id'] );
		$member    = get_post( $member_id );

		if ( ! $member || $member->post_type !== 'miembro' ) {
			wp_send_json_error( array( 'message' => 'Miembro no encontrado' ) );
		}

		$data = array(
			'member'          => array(
				'id'         => $member->ID,
				'name'       => $member->post_title,
				'email'      => get_post_meta( $member_id, '_bdv_email', true ),
				'dni'        => get_post_meta( $member_id, '_bdv_dni', true ),
				'telefono'   => get_post_meta( $member_id, '_bdv_telefono', true ),
				'estado'     => get_post_meta( $member_id, '_bdv_estado_miembro', true ),
				'plan'       => get_post_meta( $member_id, '_bdv_plan', true ),
				'fecha_alta' => $member->post_date,
			),
			'inscriptions'    => array(),
			'payments'        => array(),
			'volunteer_hours' => array(),
		);

		$inscriptions = get_posts(
			array(
				'post_type'      => 'inscripcion',
				'posts_per_page' => -1,
				'meta_key'       => '_bde_email',
				'meta_value'     => get_post_meta( $member_id, '_bdv_email', true ),
			)
		);
		foreach ( $inscriptions as $insc ) {
			$data['inscriptions'][] = array(
				'id'        => $insc->ID,
				'actividad' => get_the_title( get_post_meta( $insc->ID, '_bde_actividad_id', true ) ),
				'estado'    => get_post_meta( $insc->ID, '_bde_estado', true ),
				'fecha'     => $insc->post_date,
			);
		}

		$hours = \get_posts(
			array(
				'post_type'      => 'registro_hora',
				'posts_per_page' => -1,
				'meta_key'       => '_bdv_miembro_id',
				'meta_value'     => $member_id,
			)
		);
		foreach ( $hours as $hour ) {
			$proyecto_id               = \get_post_meta( $hour->ID, '_bdv_proyecto_id', true );
			$data['volunteer_hours'][] = array(
				'id'          => $hour->ID,
				'horas'       => \get_post_meta( $hour->ID, '_bdv_horas', true ),
				'proyecto'    => $proyecto_id ? \get_the_title( $proyecto_id ) : '',
				'descripcion' => \get_post_meta( $hour->ID, '_bdv_descripcion', true ),
				'estado'      => \get_post_meta( $hour->ID, '_bdv_estado', true ),
				'fecha'       => $hour->post_date,
			);
		}

		wp_send_json_success( $data );
	}

	public function ajax_delete_member_data(): void {
		$data = wp_unslash( $_POST );
		check_ajax_referer( 'bdv_actions_' . $data['post_id'], 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Sin permisos' ) );
		}

		$member_id = absint( $data['post_id'] );
		$member    = get_post( $member_id );

		if ( ! $member || $member->post_type !== 'miembro' ) {
			wp_send_json_error( array( 'message' => 'Miembro no encontrado' ) );
		}

		global $wpdb;

		$email = get_post_meta( $member_id, '_bdv_email', true );

		$inscriptions = get_posts(
			array(
				'post_type'      => 'inscripcion',
				'posts_per_page' => -1,
				'meta_key'       => '_bde_email',
				'meta_value'     => $email,
			)
		);
		foreach ( $inscriptions as $insc ) {
			wp_delete_post( $insc->ID, true );
		}

		$hours = get_posts(
			array(
				'post_type'      => 'registro_hora',
				'posts_per_page' => -1,
				'meta_key'       => '_bdv_miembro_id',
				'meta_value'     => $member_id,
			)
		);
		foreach ( $hours as $hour ) {
			wp_delete_post( $hour->ID, true );
		}

		wp_delete_post( $member_id, true );

		wp_send_json_success( array( 'message' => 'Todos los datos del miembro han sido eliminados.' ) );
	}
}

