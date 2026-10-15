<?php

/**
 * Convoca Members
 *
 * @package    Convoca\Members
 * @subpackage Templates
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
 * Template: Volunteer registration form v2.
 *
 * Rendered by [convoca_voluntariado] shortcode.
 * Aligned with Convoca Theme v2 and static voluntariado.html.
 *
 * @package Convoca\Members
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$areas = \Convoca\Members\Form_Voluntariado::interest_areas();

/*
 * Sin JavaScript: la degradación se DECLARA y se da salida (mismo defecto medido en el alta:
 * el formulario no declaraba `method` ni `action` y el envío sin JS se perdía en silencio,
 * sin procesarse y sin decir nada al visitante).
 */
$convoca_sin_js   = ! empty( $_POST['convoca_sin_js_voluntariado'] );
$convoca_opciones = get_option( 'convoca_members_settings' );
$convoca_email    = is_array( $convoca_opciones ) && ! empty( $convoca_opciones['admin_email'] )
	? (string) $convoca_opciones['admin_email']
	: (string) get_option( 'admin_email' );
$convoca_tel      = is_array( $convoca_opciones ) && ! empty( $convoca_opciones['telefono'] )
	? (string) $convoca_opciones['telefono']
	: '';
$convoca_email    = (string) apply_filters( 'convoca_voluntariado_sin_js_email', $convoca_email );
$convoca_tel      = (string) apply_filters( 'convoca_voluntariado_sin_js_telefono', $convoca_tel );

if ( $convoca_sin_js ) :
	?>
	<div class="convoca-alert convoca-alert--warning" role="status">
		<p><?php esc_html_e( 'No se ha registrado tu solicitud: este formulario necesita JavaScript en el navegador.', 'convoca-members' ); ?></p>
		<p><?php esc_html_e( 'Actívalo y vuelve a intentarlo.', 'convoca-members' ); ?>
		<?php if ( $convoca_email ) : ?>
			<?php esc_html_e( 'Si no puedes, escríbenos:', 'convoca-members' ); ?>
			<a href="<?php echo esc_url( 'mailto:' . $convoca_email ); ?>"><?php echo esc_html( $convoca_email ); ?></a>
		<?php endif; ?>
		<?php if ( $convoca_tel ) : ?>
			(<?php echo esc_html( $convoca_tel ); ?>)
		<?php endif; ?>
		</p>
	</div>
	<?php
	return;
endif;
?>

<div id="conv-vol-wrapper" class="convoca-form" role="region" aria-label="<?php esc_attr_e( 'Formulario de voluntariado', 'convoca-members' ); ?>">

	<!-- Alert container -->
	<div id="conv-vol-alert" class="convoca-alert" style="display:none" role="alert" aria-live="assertive"></div>

	<!-- Sin JavaScript no hay formulario que enviar: se dice antes de rellenarlo. -->
	<noscript>
		<div class="convoca-alert convoca-alert--warning" role="status">
			<p><?php esc_html_e( 'Este formulario necesita JavaScript en el navegador.', 'convoca-members' ); ?>
			<?php if ( $convoca_email ) : ?>
				<?php esc_html_e( 'Actívalo y vuelve a intentarlo, o escríbenos:', 'convoca-members' ); ?>
				<a href="<?php echo esc_url( 'mailto:' . $convoca_email ); ?>"><?php echo esc_html( $convoca_email ); ?></a>
			<?php endif; ?>
			<?php if ( $convoca_tel ) : ?>
				(<?php echo esc_html( $convoca_tel ); ?>)
			<?php endif; ?>
			</p>
		</div>
	</noscript>

	<form id="conv-vol-form" method="post" action="" novalidate>
		<?php // Marcador del envío sin JavaScript: lo lee el aviso de arriba. ?>
		<input type="hidden" name="convoca_sin_js_voluntariado" value="1" />

		<!-- Personal data -->
		<h2><?php esc_html_e( '📌 Datos personales', 'convoca-members' ); ?></h2>

			<div class="convoca-grid-2">
				<div class="convoca-field">
					<label for="conv-vol-nombre"><?php esc_html_e( 'Nombre completo *', 'convoca-members' ); ?></label>
					<input type="text" id="conv-vol-nombre" name="nombre" required autocomplete="name"
						placeholder="<?php esc_attr_e( 'Nombre y apellidos', 'convoca-members' ); ?>">
					<span class="convoca-error-msg"><?php esc_html_e( 'Este campo es obligatorio.', 'convoca-members' ); ?></span>
				</div>
				<div class="convoca-field">
					<label for="conv-vol-dni"><?php esc_html_e( 'DNI / NIE *', 'convoca-members' ); ?></label>
					<input type="text" id="conv-vol-dni" name="dni" placeholder="12345678A" required
						pattern="([0-9]{8}[A-Za-z]|[XYZxyz][0-9]{7}[A-Za-z])">
					<span class="convoca-error-msg"><?php esc_html_e( 'Introduce un DNI o NIE válido.', 'convoca-members' ); ?></span>
				</div>
			</div>

			<div class="convoca-grid-2">
				<div class="convoca-field">
					<label for="conv-vol-fechanac"><?php esc_html_e( 'Fecha de nacimiento *', 'convoca-members' ); ?></label>
					<input type="date" id="conv-vol-fechanac" name="fecha_nacimiento" required>
					<span class="convoca-error-msg"><?php esc_html_e( 'Introduce tu fecha de nacimiento.', 'convoca-members' ); ?></span>
					<span id="conv-vol-age-badge" class="conv-age-badge" style="display:none"></span>
				</div>
				<div class="convoca-field">
					<label for="conv-vol-email"><?php esc_html_e( 'Correo electrónico *', 'convoca-members' ); ?></label>
					<input type="email" id="conv-vol-email" name="email" required autocomplete="email"
						placeholder="<?php esc_attr_e( 'tu@correo.com', 'convoca-members' ); ?>">
					<span class="convoca-error-msg"><?php esc_html_e( 'Introduce un email válido.', 'convoca-members' ); ?></span>
				</div>
			</div>

			<div class="convoca-grid-2">
				<div class="convoca-field">
					<label for="conv-vol-telefono"><?php esc_html_e( 'Teléfono *', 'convoca-members' ); ?></label>
					<input type="tel" id="conv-vol-telefono" name="telefono" required autocomplete="tel"
						placeholder="600 000 000">
					<span class="convoca-error-msg"><?php esc_html_e( 'Introduce tu número de teléfono.', 'convoca-members' ); ?></span>
				</div>
				<div class="convoca-field">
					<label for="conv-vol-whatsapp"><?php esc_html_e( '¿Tu teléfono tiene WhatsApp?', 'convoca-members' ); ?></label>
					<select id="conv-vol-whatsapp" name="whatsapp">
						<option value="si"><?php esc_html_e( 'Sí', 'convoca-members' ); ?></option>
						<option value="no"><?php esc_html_e( 'No', 'convoca-members' ); ?></option>
					</select>
				</div>
			</div>

			<div class="convoca-field">
				<label for="conv-vol-direccion"><?php esc_html_e( 'Dirección postal *', 'convoca-members' ); ?></label>
				<input type="text" id="conv-vol-direccion" name="direccion" required autocomplete="street-address"
					placeholder="<?php esc_attr_e( 'Calle, número, piso', 'convoca-members' ); ?>">
				<span class="convoca-error-msg"><?php esc_html_e( 'Introduce tu dirección.', 'convoca-members' ); ?></span>
			</div>

			<div class="convoca-grid-2">
				<div class="convoca-field">
					<label for="conv-vol-municipio"><?php esc_html_e( 'Municipio / Localidad *', 'convoca-members' ); ?></label>
					<input type="text" id="conv-vol-municipio" name="municipio" required placeholder="<?php esc_attr_e( 'Ej: Oviedo', 'convoca-members' ); ?>">
					<span class="convoca-error-msg"><?php esc_html_e( 'Introduce tu municipio.', 'convoca-members' ); ?></span>
				</div>
				<div class="convoca-field">
					<label for="conv-vol-canal"><?php esc_html_e( 'Canal de contacto preferente', 'convoca-members' ); ?></label>
					<select id="conv-vol-canal" name="canal_contacto">
						<option value="whatsapp"><?php esc_html_e( 'WhatsApp', 'convoca-members' ); ?></option>
						<option value="email"><?php esc_html_e( 'Email', 'convoca-members' ); ?></option>
						<option value="telefono"><?php esc_html_e( 'Teléfono', 'convoca-members' ); ?></option>
					</select>
				</div>
			</div>

			<!-- Minor notice -->
			<div id="conv-vol-minor" class="convoca-box-warning" style="display:none">
				<strong><?php esc_html_e( '⚠️ Al ser menor de edad, necesitas autorización de tu tutor/a legal.', 'convoca-members' ); ?></strong>
				<div class="convoca-grid-2 convoca-mt-small">
					<div class="convoca-field">
						<label for="conv-vol-tutor-nombre"><?php esc_html_e( 'Nombre del tutor/a legal *', 'convoca-members' ); ?></label>
						<input type="text" id="conv-vol-tutor-nombre" name="tutor_nombre">
					</div>
					<div class="convoca-field">
						<label for="conv-vol-tutor-dni"><?php esc_html_e( 'DNI del tutor/a *', 'convoca-members' ); ?></label>
						<input type="text" id="conv-vol-tutor-dni" name="tutor_dni">
					</div>
				</div>
				<div class="convoca-check-group">
					<input type="checkbox" id="conv-vol-tutor-auth" name="tutor_auth">
					<label for="conv-vol-tutor-auth">El/la tutor/a legal autoriza la participación del menor en actividades
						de voluntariado de <?php echo esc_html( \Convoca\Members\Identidad::nombre_entidad() ); ?>.</label>
				</div>
			</div>

		<!-- Interest areas -->
		<h2 style="margin-top:2rem"><?php esc_html_e( '🌍 ¿En qué te interesa colaborar?', 'convoca-members' ); ?></h2>
		<p class="conv-small"><?php esc_html_e( 'Selecciona todas las áreas que te interesen.', 'convoca-members' ); ?></p>
		<div class="conv-interest-grid" role="group" aria-label="<?php esc_attr_e( 'Áreas de interés', 'convoca-members' ); ?>">
			<?php foreach ( $areas as $value => $label ) : ?>
				<label>
					<input type="checkbox" name="intereses[]" value="<?php echo esc_attr( $value ); ?>">
					<?php echo esc_html( $label ); ?>
				</label>
			<?php endforeach; ?>
		</div>

		<!-- Availability -->
		<h2 style="margin-top:2rem"><?php esc_html_e( '📅 Tu disponibilidad', 'convoca-members' ); ?></h2>
		<div class="convoca-grid-2">
			<div class="convoca-field">
				<label for="conv-vol-disponibilidad"><?php esc_html_e( '¿Cuándo puedes participar?', 'convoca-members' ); ?></label>
				<select id="conv-vol-disponibilidad" name="disponibilidad">
					<option value="fines-semana"><?php esc_html_e( 'Fines de semana', 'convoca-members' ); ?></option>
					<option value="entre-semana"><?php esc_html_e( 'Entre semana', 'convoca-members' ); ?></option>
					<option value="ambos"><?php esc_html_e( 'Ambos', 'convoca-members' ); ?></option>
					<option value="puntual"><?php esc_html_e( 'Solo eventos puntuales', 'convoca-members' ); ?></option>
				</select>
			</div>
			<div class="convoca-field">
				<label for="conv-vol-experiencia"><?php esc_html_e( '¿Experiencia previa en voluntariado?', 'convoca-members' ); ?></label>
				<select id="conv-vol-experiencia" name="experiencia">
					<option value="no"><?php esc_html_e( 'No, es mi primera vez', 'convoca-members' ); ?></option>
					<option value="algo"><?php esc_html_e( 'Sí, algo de experiencia', 'convoca-members' ); ?></option>
					<option value="mucha"><?php esc_html_e( 'Sí, amplia experiencia', 'convoca-members' ); ?></option>
				</select>
			</div>
		</div>

		<div class="convoca-field">
			<label for="conv-vol-motivacion">¿Por qué quieres ser voluntario/a en <?php echo esc_html( \Convoca\Members\Identidad::nombre_entidad() ); ?>?</label>
			<textarea id="conv-vol-motivacion" name="motivacion" rows="3"
				placeholder="<?php esc_attr_e( 'Cuéntanos qué te motiva…', 'convoca-members' ); ?>"></textarea>
		</div>

		<!-- Dynamic Fields -->
		<?php
		$dynamic_fields = get_option( 'convoca_volunteer_fields', array() );
		if ( ! empty( $dynamic_fields ) ) :
			echo '<h2 style="margin-top:2rem">' . esc_html__( '📋 Información adicional', 'convoca-members' ) . '</h3>';
			foreach ( $dynamic_fields as $field ) :
				$field_id = 'conv-vol-' . esc_attr( $field['name'] );
				$name     = 'dyn_' . esc_attr( $field['name'] );
				$required = ! empty( $field['required'] ) ? 'required' : '';
				$req_mark = ! empty( $field['required'] ) ? ' *' : '';
				?>
				<div class="convoca-field">
					<label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $field['label'] ); ?><?php echo esc_html( $req_mark ); ?></label>
					<?php if ( $field['type'] === 'textarea' ) : ?>
						<textarea id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $name ); ?>" rows="3" <?php echo esc_attr( $required ); ?>></textarea>
						<?php
					elseif ( $field['type'] === 'select' ) :
						$options = array_filter( array_map( 'trim', explode( "\n", $field['options'] ?? '' ) ) );
						?>
						<select id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $name ); ?>" <?php echo esc_attr( $required ); ?>>
							<option value=""><?php esc_html_e( 'Selecciona una opción', 'convoca-members' ); ?></option>
							<?php foreach ( $options as $opt ) : ?>
								<option value="<?php echo esc_attr( $opt ); ?>"><?php echo esc_html( $opt ); ?></option>
							<?php endforeach; ?>
						</select>
					<?php elseif ( $field['type'] === 'checkbox' ) : ?>
						<div class="convoca-check-group">
							<input type="checkbox" id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $name ); ?>" <?php echo esc_attr( $required ); ?>>
							<label for="<?php echo esc_attr( $field_id ); ?>"><?php esc_html_e( 'Sí', 'convoca-members' ); ?></label>
						</div>
					<?php else : ?>
						<input type="<?php echo esc_attr( $field['type'] ); ?>" id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $name ); ?>" <?php echo esc_attr( $required ); ?>>
					<?php endif; ?>
				</div>
				<?php
			endforeach;
		endif;
		?>

		<!-- Consent -->
		<h2 style="margin-top:2rem"><?php esc_html_e( '✅ Compromisos y consentimiento', 'convoca-members' ); ?></h2>
		<div class="convoca-check-group">
			<input type="checkbox" id="conv-vol-codigo" name="acepto_codigo" required>
			<label for="conv-vol-codigo">He leído y acepto el
				<?php
				// Los documentos de la entidad son ajustes del panel, no URLs metidas a mano en la
				// plantilla (issue convoca-members#12), y sin ajuste NO se pinta el enlace: antes
				// esto era una URL de un servicio externo fija en el código, y en el alta salía
				// un `href=""` que no llevaba a ninguna parte.
				// (El comentario no nombra la URL ni el servicio a propósito: el guardián de
				// higiene prohíbe los datos del cliente en el código que se publica, y esto
				// viaja dentro del plugin.)
				echo \Convoca\Members\Identidad::enlace_documento( 'Código Ético', \Convoca\Members\Identidad::OPCION_DOC_ETICO ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya viene escapado del ayudante.
				?>
				y el
				<?php
				echo \Convoca\Members\Identidad::enlace_documento( 'Programa de Voluntariado', \Convoca\Members\Identidad::OPCION_DOC_VOLUNTARIADO ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya viene escapado del ayudante.
				?>
				. *</label>
		</div>
		<div class="convoca-check-group">
			<input type="checkbox" id="conv-vol-protocolo" name="acepto_protocolo" required>
			<label for="conv-vol-protocolo">He leído el
				<?php
				echo \Convoca\Members\Identidad::enlace_documento( 'Protocolo para la prevención del acoso', \Convoca\Members\Identidad::OPCION_DOC_PROTOCOLO ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya viene escapado del ayudante.
				?>
				. *</label>
		</div>
		<div class="convoca-check-group">
			<input type="checkbox" id="conv-vol-rgpd" name="rgpd" required>
			<label for="conv-vol-rgpd">
			<?php
			printf(
				/* translators: %s es el enlace a la política de privacidad. */
				esc_html__( 'He leído y acepto la %s y el tratamiento de mis datos personales conforme al RGPD. *', 'convoca-members' ),
				sprintf(
					'<a href="%s" target="_blank" rel="noopener">%s</a>',
					esc_url( home_url( '/politica-de-privacidad/' ) ),
					esc_html__( 'Política de Privacidad', 'convoca-members' )
				)
			);
			?>
			</label>
		</div>
		<div class="convoca-check-group">
			<input type="checkbox" id="conv-vol-comunicaciones" name="comunicaciones">
			<label for="conv-vol-comunicaciones">Autorizo a <?php echo esc_html( \Convoca\Members\Identidad::nombre_entidad() ); ?> a enviarme comunicaciones sobre actividades,
				proyectos y oportunidades de voluntariado.</label>
		</div>
		
		<?php
		$legal_text = get_option( 'convoca_volunteer_legal_text', '' );
		if ( ! empty( $legal_text ) ) :
			?>
		<div style="margin-bottom: 10px; font-size: 0.9em; color: #555; padding: 15px; background: #f9f9f9; border-left: 4px solid #ff8700;">
			<?php echo wp_kses_post( $legal_text ); ?>
		</div>
		<?php endif; ?>
		<div class="convoca-check-group">
			<input type="checkbox" id="conv-vol-declaracion" name="declaracion_responsable" required>
			<label for="conv-vol-declaracion" style="font-weight: bold;"><?php esc_html_e( 'He leído y acepto la Declaración Responsable. *', 'convoca-members' ); ?></label>
		</div>

		<button type="submit" class="convoca-btn convoca-btn-primary" style="width:100%;margin-top:1.5rem"><?php esc_html_e( 'Enviar solicitud de voluntariado 🌱', 'convoca-members' ); ?></button>

	</form>

	<!-- Success screen -->
	<div id="conv-vol-success" class="convoca-success-screen" style="display:none" role="status" aria-live="polite">
		<div class="convoca-success-icon">🌱</div>
		<h2><?php esc_html_e( '¡Gracias por querer ser voluntario/a!', 'convoca-members' ); ?></h2>
		<p><?php esc_html_e( 'Tu solicitud se ha registrado correctamente. Hemos enviado un email de confirmación a tu correo.', 'convoca-members' ); ?></p>
		<p class="convoca-small"><?php esc_html_e( '¿Tienes dudas? Escríbenos a', 'convoca-members' ); ?> <a
				href="mailto:<?php echo esc_attr( get_bloginfo( 'admin_email' ) ); ?>"><?php echo esc_html( get_bloginfo( 'admin_email' ) ); ?></a></p>
		<p><a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="convoca-btn convoca-btn-outline"><?php esc_html_e( '← Volver al inicio', 'convoca-members' ); ?></a></p>
	</div>

</div>