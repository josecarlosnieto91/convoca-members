<?php
/**
 * Custom add/edit screen for Members (CPT miembro).
 *
 * Replaces the standard WordPress editor with a full custom form
 * using convoca-common CSS classes.
 *
 * @package Convoca\Members
 */

namespace Convoca\Members;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Member_Editor {

	const SLUG = 'conv-member-editor';

	public function __construct() {
		add_action( 'admin_post_conv_save_member', array( $this, 'handle_save' ) );
		add_action( 'admin_post_conv_delete_member', array( $this, 'handle_delete' ) );
		// Redirect from WP default editor.
		add_action( 'load-post-new.php', array( $this, 'redirect_from_default' ) );
		add_action( 'load-post.php', array( $this, 'redirect_from_default' ) );
	}


	/**
	 * Redirect from WP default editor to our custom page.
	 */
	public function redirect_from_default(): void {
		global $typenow;
		if ( $typenow !== CPT_Miembro::SLUG ) {
			return;
		}
		$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;

		if ( $post_id > 0 ) {
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&id=' . $post_id ) );
		} else {
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG ) );
		}
		exit;
	}

	/**
	 * Render the custom add/edit form.
	 */
	public function render(): void {
		$post_id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$is_edit = $post_id > 0 && get_post_type( $post_id ) === CPT_Miembro::SLUG;

		if ( $is_edit && ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( __( 'No tienes permisos para editar este miembro.', 'convoca-members' ) );
		}

		$post = $is_edit ? get_post( $post_id ) : null;
		$m    = fn( string $k ) => $post ? get_post_meta( $post_id, '_conv_' . $k, true ) : '';

		$plans = CPT_Miembro::get_plans();

		// Detect if we are adding a volunteer.
		$is_vol_slug     = isset( $_GET['page'] ) && $_GET['page'] === self::SLUG . '-voluntario';
		$voluntario_flag = ( isset( $_GET['es_voluntario'] ) || $is_vol_slug ) ? '1' : $m( 'es_voluntario' );
		?>
		<div class="wrap" style="max-width: 960px; margin: 20px auto;">
			<h1><?php echo $is_edit ? esc_html__( 'Editar Miembro', 'convoca-members' ) : esc_html__( 'Añadir nuevo miembro', 'convoca-members' ); ?></h1>

			<?php if ( $saved = get_transient( 'convoca_member_saved_' . get_current_user_id() ) ) : ?>
				<div class="convoca-alert convoca-alert--success" style="display:block;margin-bottom:20px;">
					<p><?php echo esc_html( $saved ); ?></p>
				</div>
				<?php delete_transient( 'convoca_member_saved_' . get_current_user_id() ); ?>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="convoca-box" style="background:#fff;border-radius:12px;box-shadow:0 10px 25px rgba(0,0,0,0.05);padding:40px;margin-top:20px;">
				<input type="hidden" name="action" value="conv_save_member">
				<input type="hidden" name="post_id" value="<?php echo $is_edit ? $post_id : 0; ?>">
				<?php wp_nonce_field( 'convoca_save_member_' . $post_id, '_conv_nonce' ); ?>

				<div class="convoca-grid-2">

					<!-- Full Name -->
					<div class="convoca-field" style="grid-column:1/-1;">
						<label for="conv_nombre"><?php esc_html_e( 'Nombre completo *', 'convoca-members' ); ?></label>
						<input type="text" id="conv_nombre" name="conv_nombre" value="<?php echo $is_edit ? esc_attr( $post->post_title ) : ''; ?>" required>
					</div>

					<h3 style="grid-column:1/-1;margin-top:1rem;padding-bottom:.5rem;border-bottom:1px solid var(--convoca-border,#ccc);"><?php esc_html_e( 'Estado y Plan', 'convoca-members' ); ?></h3>

					<div class="convoca-field">
						<label for="conv_estado_miembro"><?php esc_html_e( 'Estado', 'convoca-members' ); ?></label>
						<select id="conv_estado_miembro" name="conv_estado_miembro">
							<?php foreach ( Estados::LABELS as $slug => $label ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $m( 'estado_miembro' ), $slug ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="convoca-field">
						<label for="conv_plan"><?php esc_html_e( 'Plan', 'convoca-members' ); ?></label>
						<select id="conv_plan" name="conv_plan">
							<option value="">— <?php esc_html_e( 'Seleccionar', 'convoca-members' ); ?> —</option>
							<?php foreach ( $plans as $slug => $data ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $m( 'plan' ), $slug ); ?>>
									<?php echo esc_html( $data['label'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="convoca-field">
						<label for="conv_sub_plan"><?php esc_html_e( 'Sub-plan', 'convoca-members' ); ?></label>
						<input type="text" id="conv_sub_plan" name="conv_sub_plan" value="<?php echo esc_attr( $m( 'sub_plan' ) ); ?>" placeholder="e.g. fam-busgosu">
					</div>

					<div class="convoca-field">
						<label for="conv_forma_pago"><?php esc_html_e( 'Forma de pago', 'convoca-members' ); ?></label>
						<select id="conv_forma_pago" name="conv_forma_pago">
							<option value="cuota" <?php selected( $m( 'forma_pago' ), 'cuota' ); ?>><?php esc_html_e( 'Cuota económica', 'convoca-members' ); ?></option>
							<option value="voluntariado" <?php selected( $m( 'forma_pago' ), 'voluntariado' ); ?>><?php esc_html_e( 'Voluntariado', 'convoca-members' ); ?></option>
						</select>
					</div>

					<div class="convoca-field" style="display:flex;align-items:flex-end;padding-bottom:10px;">
						<div class="convoca-check-group">
							<input type="checkbox" id="conv_pago_recurrente" name="conv_pago_recurrente" value="1" <?php checked( $m( 'pago_recurrente' ), '1' ); ?>>
							<label for="conv_pago_recurrente"><?php esc_html_e( 'Renovación automática anual', 'convoca-members' ); ?></label>
						</div>
					</div>

					<h3 style="grid-column:1/-1;margin-top:1rem;padding-bottom:.5rem;border-bottom:1px solid var(--convoca-border,#ccc);"><?php esc_html_e( 'Datos Personales', 'convoca-members' ); ?></h3>

					<div class="convoca-field">
						<label for="conv_email"><?php esc_html_e( 'Email', 'convoca-members' ); ?></label>
						<input type="email" id="conv_email" name="conv_email" value="<?php echo esc_attr( $m( 'email' ) ); ?>">
					</div>

					<div class="convoca-field">
						<label for="conv_dni"><?php esc_html_e( 'DNI / NIE', 'convoca-members' ); ?></label>
						<input type="text" id="conv_dni" name="conv_dni" value="<?php echo esc_attr( $m( 'dni' ) ); ?>">
					</div>

					<div class="convoca-field">
						<label for="conv_telefono"><?php esc_html_e( 'Teléfono', 'convoca-members' ); ?></label>
						<input type="tel" id="conv_telefono" name="conv_telefono" value="<?php echo esc_attr( $m( 'telefono' ) ); ?>">
					</div>

					<div class="convoca-field">
						<label for="conv_fecha_nacimiento"><?php esc_html_e( 'Fecha de nacimiento', 'convoca-members' ); ?></label>
						<input type="date" id="conv_fecha_nacimiento" name="conv_fecha_nacimiento" value="<?php echo esc_attr( $m( 'fecha_nacimiento' ) ); ?>">
					</div>

					<h3 style="grid-column:1/-1;margin-top:1rem;padding-bottom:.5rem;border-bottom:1px solid var(--convoca-border,#ccc);"><?php esc_html_e( 'Dirección', 'convoca-members' ); ?></h3>

					<div class="convoca-field">
						<label for="conv_direccion"><?php esc_html_e( 'Dirección', 'convoca-members' ); ?></label>
						<input type="text" id="conv_direccion" name="conv_direccion" value="<?php echo esc_attr( $m( 'direccion' ) ); ?>">
					</div>

					<div class="convoca-field">
						<label for="conv_municipio"><?php esc_html_e( 'Municipio', 'convoca-members' ); ?></label>
						<input type="text" id="conv_municipio" name="conv_municipio" value="<?php echo esc_attr( $m( 'municipio' ) ); ?>">
					</div>

					<h3 style="grid-column:1/-1;margin-top:1rem;padding-bottom:.5rem;border-bottom:1px solid var(--convoca-border,#ccc);"><?php esc_html_e( 'Voluntariado', 'convoca-members' ); ?></h3>

					<div class="convoca-field" style="grid-column:1/-1;">
						<div class="convoca-check-group">
							<input type="checkbox" id="conv_es_voluntario" name="conv_es_voluntario" value="1" <?php checked( $voluntario_flag, '1' ); ?>>
							<label for="conv_es_voluntario"><?php esc_html_e( 'Es voluntario', 'convoca-members' ); ?></label>
						</div>
					</div>

					<div class="convoca-field">
						<label for="conv_tipo_voluntariado"><?php esc_html_e( 'Tipo de voluntariado', 'convoca-members' ); ?></label>
						<input type="text" id="conv_tipo_voluntariado" name="conv_tipo_voluntariado" value="<?php echo esc_attr( $m( 'tipo_voluntariado' ) ); ?>">
					</div>

					<div class="convoca-field">
						<label for="conv_intereses"><?php esc_html_e( 'Intereses', 'convoca-members' ); ?></label>
						<input type="text" id="conv_intereses" name="conv_intereses" value="<?php echo esc_attr( $m( 'intereses' ) ); ?>">
					</div>

					<div class="convoca-field">
						<label for="conv_disponibilidad"><?php esc_html_e( 'Disponibilidad', 'convoca-members' ); ?></label>
						<input type="text" id="conv_disponibilidad" name="conv_disponibilidad" value="<?php echo esc_attr( $m( 'disponibilidad' ) ); ?>">
					</div>

					<div class="convoca-field">
						<label for="conv_experiencia"><?php esc_html_e( 'Experiencia', 'convoca-members' ); ?></label>
						<textarea id="conv_experiencia" name="conv_experiencia" rows="3"><?php echo esc_textarea( $m( 'experiencia' ) ); ?></textarea>
					</div>

					<div class="convoca-field">
						<label for="conv_motivacion"><?php esc_html_e( 'Motivación', 'convoca-members' ); ?></label>
						<textarea id="conv_motivacion" name="conv_motivacion" rows="3"><?php echo esc_textarea( $m( 'motivacion' ) ); ?></textarea>
					</div>

					<h3 style="grid-column:1/-1;margin-top:1rem;padding-bottom:.5rem;border-bottom:1px solid var(--convoca-border,#ccc);"><?php esc_html_e( 'Notas Internas', 'convoca-members' ); ?></h3>

					<div class="convoca-field" style="grid-column:1/-1;">
						<label for="conv_observaciones"><?php esc_html_e( 'Observaciones', 'convoca-members' ); ?></label>
						<textarea id="conv_observaciones" name="conv_observaciones" rows="4"><?php echo esc_textarea( $m( 'observaciones' ) ); ?></textarea>
					</div>

					<div class="convoca-field" style="grid-column:1/-1;">
						<label for="conv_incidencias"><?php esc_html_e( 'Incidencias', 'convoca-members' ); ?></label>
						<textarea id="conv_incidencias" name="conv_incidencias" rows="4"><?php echo esc_textarea( $m( 'incidencias' ) ); ?></textarea>
					</div>

				</div>

				<div style="margin-top:40px;display:flex;justify-content:flex-end;gap:15px;align-items:center;">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=conv-members' ) ); ?>" class="convoca-btn convoca-btn-outline">
						&larr; <?php esc_html_e( 'Volver al listado', 'convoca-members' ); ?>
					</a>
					<button type="submit" class="convoca-btn convoca-btn-primary">
						<?php echo $is_edit ? esc_html__( 'Guardar cambios', 'convoca-members' ) : esc_html__( 'Crear miembro', 'convoca-members' ); ?>
					</button>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Process the form submission.
	 */
	public function handle_save(): void {
		$data = wp_unslash( $_POST );

		if ( ! isset( $data['_conv_nonce'] ) ) {
			wp_die( __( 'Acceso denegado.', 'convoca-members' ) );
		}

		$post_id = (int) ( $data['post_id'] ?? 0 );
		$is_edit = $post_id > 0;

		if ( $is_edit ) {
			if ( ! wp_verify_nonce( $data['_conv_nonce'], 'convoca_save_member_' . $post_id ) ) {
				wp_die( __( 'Nonce inválido.', 'convoca-members' ) );
			}
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				wp_die( __( 'No tienes permisos.', 'convoca-members' ) );
			}
		} else {
			if ( ! wp_verify_nonce( $data['_conv_nonce'], 'convoca_save_member_0' ) ) {
				wp_die( __( 'Nonce inválido.', 'convoca-members' ) );
			}
			if ( ! current_user_can( 'edit_posts' ) ) {
				wp_die( __( 'No tienes permisos.', 'convoca-members' ) );
			}
		}

		// Get the full name (used as post title).
		$nombre = sanitize_text_field( $data['convoca_nombre'] ?? '' );
		if ( empty( $nombre ) ) {
			wp_die( __( 'El nombre es obligatorio.', 'convoca-members' ) );
		}

		if ( $is_edit ) {
			// Update existing post.
			wp_update_post(
				array(
					'ID'         => $post_id,
					'post_title' => $nombre,
				)
			);
		} else {
			// Create new member post.
			$post_id = wp_insert_post(
				array(
					'post_type'   => CPT_Miembro::SLUG,
					'post_title'  => $nombre,
					'post_status' => 'publish',
					'post_author' => get_current_user_id(),
				)
			);

			if ( is_wp_error( $post_id ) ) {
				wp_die( __( 'Error al crear el miembro.', 'convoca-members' ) );
			}
		}

		// Save meta fields.
		$fields = array(
			'plan'              => 'sanitize_text_field',
			'sub_plan'          => 'sanitize_text_field',
			'forma_pago'        => 'sanitize_text_field',
			'email'             => fn( $v ) => sanitize_email( $v ),
			'dni'               => 'sanitize_text_field',
			'telefono'          => 'sanitize_text_field',
			'fecha_nacimiento'  => 'sanitize_text_field',
			'direccion'         => 'sanitize_text_field',
			'municipio'         => 'sanitize_text_field',
			'observaciones'     => 'sanitize_textarea_field',
			'incidencias'       => 'sanitize_textarea_field',
			'tipo_voluntariado' => 'sanitize_text_field',
			'intereses'         => 'sanitize_text_field',
			'disponibilidad'    => 'sanitize_text_field',
			'experiencia'       => 'sanitize_textarea_field',
			'motivacion'        => 'sanitize_textarea_field',
			'estado_miembro'    => 'sanitize_text_field',
		);

		foreach ( $fields as $key => $sanitizer ) {
			$raw = $data[ 'convoca_' . $key ] ?? '';
			$val = is_callable( $sanitizer ) ? $sanitizer( $raw ) : $raw;
			update_post_meta( $post_id, '_conv_' . $key, $val );
		}

		// Boolean checkboxes.
		$bool_fields = array( 'pago_recurrente', 'es_voluntario' );
		foreach ( $bool_fields as $key ) {
			$val = isset( $data[ 'convoca_' . $key ] ) ? '1' : '0';
			update_post_meta( $post_id, '_conv_' . $key, $val );
		}

		// Handle state change via state machine.
		$new_state = $data['convoca_estado_miembro'] ?? '';
		$old_state = get_post_meta( $post_id, '_conv_estado_miembro', true );

		if ( $new_state && $new_state !== $old_state ) {
			Estados::change( $post_id, $new_state, __( 'Cambio manual desde editor de miembro.', 'convoca-members' ) );

			if ( $new_state === 'activo' ) {
				$num = get_post_meta( $post_id, '_conv_numero_socio', true );
				if ( ! $num ) {
					$num = CPT_Miembro::get_next_member_number( $post_id );
					update_post_meta( $post_id, '_conv_numero_socio', $num );
					\Convoca\Core\Logger::info( "Número de socio #$num asignado automáticamente al activar.", 'Members/Admin', $post_id );
				}
				$estado_cuota = get_post_meta( $post_id, '_conv_estado_cuota', true );
				if ( $estado_cuota !== 'activa' ) {
					update_post_meta( $post_id, '_conv_estado_cuota', 'activa' );
				}
			}
		}

		$message = $is_edit
			? __( 'Miembro actualizado correctamente.', 'convoca-members' )
			: __( 'Miembro creado correctamente.', 'convoca-members' );

		set_transient( 'convoca_member_saved_' . get_current_user_id(), $message, 30 );

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&id=' . $post_id ) );
		exit;
	}

	/**
	 * Handle member deletion from the editor.
	 */
	public function handle_delete(): void {
		$data    = wp_unslash( $_POST );
		$post_id = (int) ( $data['post_id'] ?? 0 );

		if ( ! $post_id || ! wp_verify_nonce( $data['_conv_nonce'] ?? '', 'convoca_delete_member_' . $post_id ) ) {
			wp_die( __( 'Acceso denegado.', 'convoca-members' ) );
		}
		if ( ! current_user_can( 'delete_post', $post_id ) ) {
			wp_die( __( 'No tienes permisos.', 'convoca-members' ) );
		}

		wp_trash_post( $post_id );
		wp_safe_redirect( admin_url( 'admin.php?page=conv-members' ) );
		exit;
	}
}
