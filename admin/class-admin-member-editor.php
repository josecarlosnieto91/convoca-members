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
		add_action( 'admin_post_convoca_save_member', array( $this, 'handle_save' ) );
		add_action( 'admin_post_convoca_delete_member', array( $this, 'handle_delete' ) );
		add_action( 'admin_post_convoca_baja_member', array( $this, 'handle_baja' ) );
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
			wp_die( esc_html__( 'No tienes permisos para editar este miembro.', 'convoca-members' ) );
		}

		$post = $is_edit ? get_post( $post_id ) : null;
		$m    = fn( string $k ) => $post ? get_post_meta( $post_id, '_convoca_' . $k, true ) : '';

		$plans = CPT_Miembro::get_plans();

		// Detect if we are adding a volunteer.
		$is_vol_slug     = isset( $_GET['page'] ) && $_GET['page'] === self::SLUG . '-voluntario';
		$voluntario_flag = ( isset( $_GET['es_voluntario'] ) || $is_vol_slug ) ? '1' : $m( 'es_voluntario' );
		?>
		<div class="wrap" style="max-width: 960px; margin: 20px auto;">
			<h1><?php echo $is_edit ? esc_html__( 'Editar Miembro', 'convoca-members' ) : esc_html__( 'Añadir nuevo miembro', 'convoca-members' ); ?></h1>

			<?php $saved = get_transient( 'convoca_member_saved_' . get_current_user_id() ); ?>
			<?php if ( $saved ) : ?>
				<div class="convoca-alert convoca-alert--success" style="display:block;margin-bottom:20px;">
					<p><?php echo esc_html( $saved ); ?></p>
				</div>
				<?php delete_transient( 'convoca_member_saved_' . get_current_user_id() ); ?>
			<?php endif; ?>

			<?php
			// El selector de foto usa la biblioteca de medios de WordPress: sin esto, `wp.media` no está.
			wp_enqueue_media();
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="convoca-box" style="background:#fff;border-radius:12px;box-shadow:0 10px 25px rgba(0,0,0,0.05);padding:40px;margin-top:20px;">
				<input type="hidden" name="action" value="convoca_save_member">
				<input type="hidden" name="post_id" value="<?php echo $is_edit ? esc_attr( (string) $post_id ) : 0; ?>">
				<?php wp_nonce_field( 'convoca_save_member_' . $post_id, '_convoca_nonce' ); ?>

				<div class="convoca-grid-2">

					<!-- Full Name -->
					<div class="convoca-field" style="grid-column:1/-1;">
						<label for="convoca_nombre"><?php esc_html_e( 'Nombre completo *', 'convoca-members' ); ?></label>
						<input type="text" id="convoca_nombre" name="convoca_nombre" value="<?php echo $is_edit && $post instanceof \WP_Post ? esc_attr( $post->post_title ) : ''; ?>" required>
					</div>

					<?php
					// Foto del carnet (issue #31): la web prometía «carnets con foto» y no había ni campo. El
					// adjunto se guarda por id y el carnet lo inserta como data URI.
					$conv_foto_id  = $is_edit ? (int) $m( 'foto_id' ) : 0;
					$conv_foto_url = $conv_foto_id ? (string) wp_get_attachment_image_url( $conv_foto_id, 'thumbnail' ) : '';
					?>
					<div class="convoca-field" style="grid-column:1/-1;">
						<label for="convoca_foto_id"><?php esc_html_e( 'Foto para el carnet', 'convoca-members' ); ?></label>
						<input type="hidden" id="convoca_foto_id" name="convoca_foto_id" value="<?php echo esc_attr( (string) $conv_foto_id ); ?>">
						<div style="display:flex;align-items:center;gap:12px;">
							<img id="convoca-foto-preview" src="<?php echo esc_url( $conv_foto_url ); ?>" alt=""
								style="width:72px;height:72px;object-fit:cover;border-radius:6px;background:#f0f0f1;<?php echo $conv_foto_url ? '' : 'display:none;'; ?>">
							<button type="button" class="button" id="convoca-elegir-foto"><?php esc_html_e( 'Elegir foto', 'convoca-members' ); ?></button>
							<button type="button" class="button" id="convoca-quitar-foto" <?php echo $conv_foto_url ? '' : 'style="display:none;"'; ?>><?php esc_html_e( 'Quitar', 'convoca-members' ); ?></button>
						</div>
						<p class="description"><?php esc_html_e( 'Se imprime en el carnet digital recortada a un cuadrado de 300 px. Cuadrada queda mejor (por ejemplo 600×600) y con la cara de frente.', 'convoca-members' ); ?></p>
					</div>

					<h3 style="grid-column:1/-1;margin-top:1rem;padding-bottom:.5rem;border-bottom:1px solid var(--convoca-border,#ccc);"><?php esc_html_e( 'Estado y Plan', 'convoca-members' ); ?></h3>

					<div class="convoca-field">
						<label for="convoca_estado_miembro"><?php esc_html_e( 'Estado', 'convoca-members' ); ?></label>
						<select id="convoca_estado_miembro" name="convoca_estado_miembro">
							<?php foreach ( Estados::labels() as $slug => $label ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $m( 'estado_miembro' ), $slug ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="convoca-field">
						<label for="convoca_plan"><?php esc_html_e( 'Plan', 'convoca-members' ); ?></label>
						<select id="convoca_plan" name="convoca_plan">
							<option value="">— <?php esc_html_e( 'Seleccionar', 'convoca-members' ); ?> —</option>
							<?php foreach ( $plans as $slug => $data ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $m( 'plan' ), $slug ); ?>>
									<?php echo esc_html( $data['label'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="convoca-field">
						<label for="convoca_sub_plan"><?php esc_html_e( 'Sub-plan', 'convoca-members' ); ?></label>
						<input type="text" id="convoca_sub_plan" name="convoca_sub_plan" value="<?php echo esc_attr( $m( 'sub_plan' ) ); ?>" placeholder="e.g. familiar">
					</div>

					<?php
					// Condiciones de la ficha que no salen de ningún plan (issue #30, fase 4): el voluntariado
					// se marca más abajo y el monitor se concede aquí. Solo un administrador —un monitor no
					// reparte su propio rol—, y con marca de presencia (`_control`) para que un formulario que
					// no traiga la casilla no quite la condición sin querer.
					if ( current_user_can( 'manage_options' ) ) :
						?>
						<div class="convoca-field" style="grid-column:1/-1;">
							<input type="hidden" name="convoca_monitor_control" value="1">
							<label style="display:flex;align-items:center;gap:.5rem;font-weight:600;">
								<input type="checkbox" id="convoca_es_monitor" name="convoca_es_monitor" value="1" <?php checked( $is_edit && $post instanceof \WP_Post && Roles_Miembro::es_monitor( $post->ID ) ); ?>>
								<?php esc_html_e( 'Monitor de actividad', 'convoca-members' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Puede aprobar horas, impartir actividades y sumar horas sin estar inscrito. Se suma a las demás condiciones.', 'convoca-members' ); ?>
							</p>
						</div>
					<?php endif; ?>

					<div class="convoca-field">
						<label for="convoca_forma_pago"><?php esc_html_e( 'Forma de pago', 'convoca-members' ); ?></label>
						<select id="convoca_forma_pago" name="convoca_forma_pago">
							<option value="cuota" <?php selected( $m( 'forma_pago' ), 'cuota' ); ?>><?php esc_html_e( 'Cuota económica', 'convoca-members' ); ?></option>
							<option value="voluntariado" <?php selected( $m( 'forma_pago' ), 'voluntariado' ); ?>><?php esc_html_e( 'Voluntariado', 'convoca-members' ); ?></option>
						</select>
					</div>

					<div class="convoca-field" style="display:flex;align-items:flex-end;padding-bottom:10px;">
						<div class="convoca-check-group">
							<input type="checkbox" id="convoca_pago_recurrente" name="convoca_pago_recurrente" value="1" <?php checked( $m( 'pago_recurrente' ), '1' ); ?>>
							<label for="convoca_pago_recurrente"><?php esc_html_e( 'Renovación automática anual', 'convoca-members' ); ?></label>
						</div>
					</div>

					<h3 style="grid-column:1/-1;margin-top:1rem;padding-bottom:.5rem;border-bottom:1px solid var(--convoca-border,#ccc);"><?php esc_html_e( 'Datos Personales', 'convoca-members' ); ?></h3>

					<div class="convoca-field">
						<label for="conv_email"><?php esc_html_e( 'Email', 'convoca-members' ); ?></label>
						<input type="email" id="conv_email" name="convoca_email" value="<?php echo esc_attr( $m( 'email' ) ); ?>">
					</div>

					<div class="convoca-field">
						<label for="convoca_dni"><?php esc_html_e( 'DNI / NIE', 'convoca-members' ); ?></label>
						<input type="text" id="convoca_dni" name="convoca_dni" value="<?php echo esc_attr( $m( 'dni' ) ); ?>">
					</div>

					<div class="convoca-field">
						<label for="convoca_telefono"><?php esc_html_e( 'Teléfono', 'convoca-members' ); ?></label>
						<input type="tel" id="convoca_telefono" name="convoca_telefono" value="<?php echo esc_attr( $m( 'telefono' ) ); ?>">
					</div>

					<div class="convoca-field">
						<label for="convoca_fecha_nacimiento"><?php esc_html_e( 'Fecha de nacimiento', 'convoca-members' ); ?></label>
						<input type="date" id="convoca_fecha_nacimiento" name="convoca_fecha_nacimiento" value="<?php echo esc_attr( $m( 'fecha_nacimiento' ) ); ?>">
					</div>

					<h3 style="grid-column:1/-1;margin-top:1rem;padding-bottom:.5rem;border-bottom:1px solid var(--convoca-border,#ccc);"><?php esc_html_e( 'Dirección', 'convoca-members' ); ?></h3>

					<div class="convoca-field">
						<label for="convoca_direccion"><?php esc_html_e( 'Dirección', 'convoca-members' ); ?></label>
						<input type="text" id="convoca_direccion" name="convoca_direccion" value="<?php echo esc_attr( $m( 'direccion' ) ); ?>">
					</div>

					<div class="convoca-field">
						<label for="convoca_municipio"><?php esc_html_e( 'Municipio', 'convoca-members' ); ?></label>
						<input type="text" id="convoca_municipio" name="convoca_municipio" value="<?php echo esc_attr( $m( 'municipio' ) ); ?>">
					</div>

					<h3 style="grid-column:1/-1;margin-top:1rem;padding-bottom:.5rem;border-bottom:1px solid var(--convoca-border,#ccc);"><?php esc_html_e( 'Voluntariado', 'convoca-members' ); ?></h3>

					<div class="convoca-field" style="grid-column:1/-1;">
						<div class="convoca-check-group">
							<input type="checkbox" id="conv_es_voluntario" name="convoca_es_voluntario" value="1" <?php checked( $voluntario_flag, '1' ); ?>>
							<label for="conv_es_voluntario"><?php esc_html_e( 'Es voluntario', 'convoca-members' ); ?></label>
						</div>
					</div>

					<div class="convoca-field">
						<label for="convoca_tipo_voluntariado"><?php esc_html_e( 'Tipo de voluntariado', 'convoca-members' ); ?></label>
						<input type="text" id="convoca_tipo_voluntariado" name="convoca_tipo_voluntariado" value="<?php echo esc_attr( $m( 'tipo_voluntariado' ) ); ?>">
					</div>

					<div class="convoca-field">
						<label for="convoca_intereses"><?php esc_html_e( 'Intereses', 'convoca-members' ); ?></label>
						<input type="text" id="convoca_intereses" name="convoca_intereses" value="<?php echo esc_attr( $m( 'intereses' ) ); ?>">
					</div>

					<div class="convoca-field">
						<label for="convoca_disponibilidad"><?php esc_html_e( 'Disponibilidad', 'convoca-members' ); ?></label>
						<input type="text" id="convoca_disponibilidad" name="convoca_disponibilidad" value="<?php echo esc_attr( $m( 'disponibilidad' ) ); ?>">
					</div>

					<div class="convoca-field">
						<label for="conv_experiencia"><?php esc_html_e( 'Experiencia', 'convoca-members' ); ?></label>
						<textarea id="conv_experiencia" name="convoca_experiencia" rows="3"><?php echo esc_textarea( $m( 'experiencia' ) ); ?></textarea>
					</div>

					<div class="convoca-field">
						<label for="convoca_motivacion"><?php esc_html_e( 'Motivación', 'convoca-members' ); ?></label>
						<textarea id="convoca_motivacion" name="convoca_motivacion" rows="3"><?php echo esc_textarea( $m( 'motivacion' ) ); ?></textarea>
					</div>

					<h3 style="grid-column:1/-1;margin-top:1rem;padding-bottom:.5rem;border-bottom:1px solid var(--convoca-border,#ccc);"><?php esc_html_e( 'Notas Internas', 'convoca-members' ); ?></h3>

					<div class="convoca-field" style="grid-column:1/-1;">
						<label for="convoca_observaciones"><?php esc_html_e( 'Observaciones', 'convoca-members' ); ?></label>
						<textarea id="convoca_observaciones" name="convoca_observaciones" rows="4"><?php echo esc_textarea( $m( 'observaciones' ) ); ?></textarea>
					</div>

					<div class="convoca-field" style="grid-column:1/-1;">
						<label for="convoca_incidencias"><?php esc_html_e( 'Incidencias', 'convoca-members' ); ?></label>
						<textarea id="convoca_incidencias" name="convoca_incidencias" rows="4"><?php echo esc_textarea( $m( 'incidencias' ) ); ?></textarea>
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

			<script>
			// Selector de la foto del carnet (issue #31): botón «Elegir foto» y previsualización.
			jQuery( function( $ ) {
				var marco;
				$( '#convoca-elegir-foto' ).on( 'click', function( e ) {
					e.preventDefault();
					if ( ! marco ) {
						marco = wp.media( {
							title: <?php echo wp_json_encode( __( 'Foto del socio', 'convoca-members' ) ); ?>,
							library: { type: 'image' },
							multiple: false,
							button: { text: <?php echo wp_json_encode( __( 'Usar esta foto', 'convoca-members' ) ); ?> }
						} );
						marco.on( 'select', function() {
							var adjunto = marco.state().get( 'selection' ).first().toJSON();
							var url = ( adjunto.sizes && adjunto.sizes.thumbnail ) ? adjunto.sizes.thumbnail.url : adjunto.url;
							$( '#convoca_foto_id' ).val( adjunto.id );
							$( '#convoca-foto-preview' ).attr( 'src', url ).show();
							$( '#convoca-quitar-foto' ).show();
						} );
					}
					marco.open();
				} );
				$( '#convoca-quitar-foto' ).on( 'click', function( e ) {
					e.preventDefault();
					$( '#convoca_foto_id' ).val( '' );
					$( '#convoca-foto-preview' ).hide();
					$( this ).hide();
				} );
			} );
			</script>
		</div>
		<?php
	}

	/**
	 * Process the form submission.
	 */
	public function handle_save(): void {
		$data = wp_unslash( $_POST );

		if ( ! isset( $data['_convoca_nonce'] ) ) {
			wp_die( esc_html__( 'Acceso denegado.', 'convoca-members' ) );
		}

		$post_id = (int) ( $data['post_id'] ?? 0 );
		$is_edit = $post_id > 0;

		if ( $is_edit ) {
			if ( ! wp_verify_nonce( $data['_convoca_nonce'], 'convoca_save_member_' . $post_id ) ) {
				wp_die( esc_html__( 'Nonce inválido.', 'convoca-members' ) );
			}
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				wp_die( esc_html__( 'No tienes permisos.', 'convoca-members' ) );
			}
		} else {
			if ( ! wp_verify_nonce( $data['_convoca_nonce'], 'convoca_save_member_0' ) ) {
				wp_die( esc_html__( 'Nonce inválido.', 'convoca-members' ) );
			}
			if ( ! current_user_can( 'gestionar_miembros' ) ) {
				wp_die( esc_html__( 'No tienes permisos.', 'convoca-members' ) );
			}
		}

		// Get the full name (used as post title).
		$nombre = sanitize_text_field( $data['convoca_nombre'] ?? '' );
		if ( empty( $nombre ) ) {
			wp_die( esc_html__( 'El nombre es obligatorio.', 'convoca-members' ) );
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
				),
				true
			);

			if ( is_wp_error( $post_id ) ) {
				wp_die( esc_html__( 'Error al crear el miembro.', 'convoca-members' ) );
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
			'foto_id'           => 'absint',
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
			// NOTA: 'estado_miembro' se excluye del loop a propósito (E2E-1):
			// si se escribe aquí antes de leer $old_state, la máquina de estados
			// ve old === new y nunca numera/activa. Se gestiona más abajo.
		);

		foreach ( $fields as $key => $sanitizer ) {
			$raw = $data[ 'convoca_' . $key ] ?? '';
			$val = $sanitizer( $raw );
			update_post_meta( $post_id, '_convoca_' . $key, $val );
		}

		// Boolean checkboxes.
		$bool_fields = array( 'pago_recurrente', 'es_voluntario' );
		foreach ( $bool_fields as $key ) {
			$val = isset( $data[ 'convoca_' . $key ] ) ? '1' : '0';
			update_post_meta( $post_id, '_convoca_' . $key, $val );
		}

		// Condición de monitor (issue #30, fase 4): no sale de ningún plan, se concede a mano desde aquí.
		// Se pasa por `Roles_Miembro` y no por el meta a pelo porque es quien mantiene el espejo del rol de
		// WordPress —con `add_role`/`remove_role`, que suman—. La marca de presencia evita que un formulario
		// que no traiga la casilla quite la condición, y solo puede concederla un administrador.
		if ( current_user_can( 'manage_options' ) && ! empty( $data['convoca_monitor_control'] ) ) {
			if ( ! empty( $data['convoca_es_monitor'] ) ) {
				Roles_Miembro::anadir( $post_id, Roles_Miembro::MONITOR );
			} else {
				Roles_Miembro::quitar( $post_id, Roles_Miembro::MONITOR );
			}
		}

		// Handle state change via state machine.
		// Leer $old_state ANTES de tocar el meta (el post puede ser nuevo → '').
		$new_state = $data['convoca_estado_miembro'] ?? '';
		$old_state = get_post_meta( $post_id, '_convoca_estado_miembro', true );

		if ( $new_state && $new_state !== $old_state ) {
			// Si se activa: asignar número y cuota ANTES de Estados::change,
			// porque el change dispara el email de bienvenida (hook estado_changed)
			// y ese email debe llevar número/plan ya resueltos (E2E-6).
			if ( $new_state === 'activo' ) {
				$num = get_post_meta( $post_id, '_convoca_numero_socio', true );
				if ( ! $num ) {
					$num = CPT_Miembro::get_next_member_number( $post_id );
					update_post_meta( $post_id, '_convoca_numero_socio', $num );
					\Convoca\Core\Logger::info( "Número de socio #$num asignado automáticamente al activar.", 'Members/Admin', $post_id );
				}
				$estado_cuota = get_post_meta( $post_id, '_convoca_estado_cuota', true );
				if ( $estado_cuota !== 'activa' ) {
					update_post_meta( $post_id, '_convoca_estado_cuota', 'activa' );
				}
			}

			Estados::change( $post_id, $new_state, esc_html__( 'Cambio manual desde editor de miembro.', 'convoca-members' ) );
		} elseif ( $new_state && $new_state === $old_state && $new_state === 'activo' ) {
			// Miembro ya activo: garantizar número/cuota si por algún flujo anterior faltaron.
			$num = get_post_meta( $post_id, '_convoca_numero_socio', true );
			if ( ! $num ) {
				$num = CPT_Miembro::get_next_member_number( $post_id );
				update_post_meta( $post_id, '_convoca_numero_socio', $num );
			}
			if ( get_post_meta( $post_id, '_convoca_estado_cuota', true ) !== 'activa' ) {
				update_post_meta( $post_id, '_convoca_estado_cuota', 'activa' );
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
	 * Da de baja a un socio: la ficha se conserva y deja de contar como activo (issue #27).
	 *
	 * El modelo de datos ya tenía el estado `baja`, así que la baja manual se hace cambiando el estado y no
	 * borrando: el número de socio, las cuotas, las horas y las inscripciones siguen teniendo a quién apuntar.
	 * Se puede reactivar.
	 */
	public function handle_baja(): void {
		$data    = wp_unslash( $_GET );
		$post_id = (int) ( $data['member_id'] ?? 0 );

		if ( ! $post_id || ! wp_verify_nonce( $data['_wpnonce'] ?? '', 'convoca_baja_member_' . $post_id ) ) {
			wp_die( esc_html__( 'Acceso denegado.', 'convoca-members' ) );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'No tienes permisos.', 'convoca-members' ) );
		}

		$resultado = Estados::change( $post_id, 'baja', __( 'Baja manual desde el listado', 'convoca-members' ) );

		wp_safe_redirect(
			admin_url( 'admin.php?page=conv-members&msg=' . ( is_wp_error( $resultado ) ? 'error' : 'baja' ) )
		);
		exit;
	}

	/**
	 * Borra la ficha definitivamente. Es la excepción, no la baja (issue #27).
	 *
	 * Antes esto llamaba a `wp_trash_post()` sobre un tipo que **no admite papelera**, así que el enlace
	 * «Eliminar» del listado no hacía nada y tampoco lo decía. Ahora hace lo que anuncia, y el aviso de la
	 * pantalla dice qué se lleva por delante. El registro de auditoría lo deja anotado solo (Audit_Logger
	 * engancha `before_delete_post`).
	 */
	public function handle_delete(): void {
		$data    = wp_unslash( $_GET );
		$post_id = (int) ( $data['member_id'] ?? 0 );

		if ( ! $post_id || ! wp_verify_nonce( $data['_wpnonce'] ?? '', 'convoca_delete_member_' . $post_id ) ) {
			wp_die( esc_html__( 'Acceso denegado.', 'convoca-members' ) );
		}
		if ( ! current_user_can( 'delete_post', $post_id ) ) {
			wp_die( esc_html__( 'No tienes permisos.', 'convoca-members' ) );
		}

		wp_delete_post( $post_id, true );

		wp_safe_redirect( admin_url( 'admin.php?page=conv-members&msg=deleted_forever' ) );
		exit;
	}
}
