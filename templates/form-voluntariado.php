<?php
/**
 * Template: Volunteer registration form v2.
 *
 * Rendered by [biodevas_voluntariado] shortcode.
 * Aligned with Biodevas Theme v2 and static voluntariado.html.
 *
 * @package Convoca\Members
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$areas = \Convoca\Members\Form_Voluntariado::INTEREST_AREAS;
?>

<div id="bdv-vol-wrapper" class="biodevas-form" role="region" aria-label="Formulario de voluntariado">

	<!-- Alert container -->
	<div id="bdv-vol-alert" class="biodevas-alert" style="display:none" role="alert" aria-live="assertive"></div>

	<form id="bdv-vol-form" novalidate>

		<!-- Personal data -->
		<h3>📌 Datos personales</h3>

			<div class="biodevas-grid-2">
				<div class="biodevas-field">
					<label for="bdv-vol-nombre">Nombre completo *</label>
					<input type="text" id="bdv-vol-nombre" name="nombre" required autocomplete="name"
						placeholder="Nombre y apellidos">
					<span class="biodevas-error-msg">Este campo es obligatorio.</span>
				</div>
				<div class="biodevas-field">
					<label for="bdv-vol-dni">DNI / NIE *</label>
					<input type="text" id="bdv-vol-dni" name="dni" placeholder="12345678A" required
						pattern="([0-9]{8}[A-Za-z]|[XYZxyz][0-9]{7}[A-Za-z])">
					<span class="biodevas-error-msg">Introduce un DNI o NIE válido.</span>
				</div>
			</div>

			<div class="biodevas-grid-2">
				<div class="biodevas-field">
					<label for="bdv-vol-fechanac">Fecha de nacimiento *</label>
					<input type="date" id="bdv-vol-fechanac" name="fecha_nacimiento" required>
					<span class="biodevas-error-msg">Introduce tu fecha de nacimiento.</span>
					<span id="bdv-vol-age-badge" class="bdv-age-badge" style="display:none"></span>
				</div>
				<div class="biodevas-field">
					<label for="bdv-vol-email">Correo electrónico *</label>
					<input type="email" id="bdv-vol-email" name="email" required autocomplete="email"
						placeholder="tu@correo.com">
					<span class="biodevas-error-msg">Introduce un email válido.</span>
				</div>
			</div>

			<div class="biodevas-grid-2">
				<div class="biodevas-field">
					<label for="bdv-vol-telefono">Teléfono *</label>
					<input type="tel" id="bdv-vol-telefono" name="telefono" required autocomplete="tel"
						placeholder="600 000 000">
					<span class="biodevas-error-msg">Introduce tu número de teléfono.</span>
				</div>
				<div class="biodevas-field">
					<label for="bdv-vol-whatsapp">¿Tu teléfono tiene WhatsApp?</label>
					<select id="bdv-vol-whatsapp" name="whatsapp">
						<option value="si">Sí</option>
						<option value="no">No</option>
					</select>
				</div>
			</div>

			<div class="biodevas-field">
				<label for="bdv-vol-direccion">Dirección postal *</label>
				<input type="text" id="bdv-vol-direccion" name="direccion" required autocomplete="street-address"
					placeholder="Calle, número, piso">
				<span class="biodevas-error-msg">Introduce tu dirección.</span>
			</div>

			<div class="biodevas-grid-2">
				<div class="biodevas-field">
					<label for="bdv-vol-municipio">Municipio / Localidad *</label>
					<input type="text" id="bdv-vol-municipio" name="municipio" required placeholder="Ej: Oviedo">
					<span class="biodevas-error-msg">Introduce tu municipio.</span>
				</div>
				<div class="biodevas-field">
					<label for="bdv-vol-canal">Canal de contacto preferente</label>
					<select id="bdv-vol-canal" name="canal_contacto">
						<option value="whatsapp">WhatsApp</option>
						<option value="email">Email</option>
						<option value="telefono">Teléfono</option>
					</select>
				</div>
			</div>

			<!-- Minor notice -->
			<div id="bdv-vol-minor" class="biodevas-box-warning" style="display:none">
				<strong>⚠️ Al ser menor de edad, necesitas autorización de tu tutor/a legal.</strong>
				<div class="biodevas-grid-2 biodevas-mt-small">
					<div class="biodevas-field">
						<label for="bdv-vol-tutor-nombre">Nombre del tutor/a legal *</label>
						<input type="text" id="bdv-vol-tutor-nombre" name="tutor_nombre">
					</div>
					<div class="biodevas-field">
						<label for="bdv-vol-tutor-dni">DNI del tutor/a *</label>
						<input type="text" id="bdv-vol-tutor-dni" name="tutor_dni">
					</div>
				</div>
				<div class="biodevas-check-group">
					<input type="checkbox" id="bdv-vol-tutor-auth" name="tutor_auth">
					<label for="bdv-vol-tutor-auth">El/la tutor/a legal autoriza la participación del menor en actividades
						de voluntariado de Biodevas.</label>
				</div>
			</div>

		<!-- Interest areas -->
		<h3 style="margin-top:2rem">🌍 ¿En qué te interesa colaborar?</h3>
		<p class="bdv-small">Selecciona todas las áreas que te interesen.</p>
		<div class="bdv-interest-grid" role="group" aria-label="Áreas de interés">
			<?php foreach ( $areas as $value => $label ) : ?>
				<label>
					<input type="checkbox" name="intereses[]" value="<?php echo esc_attr( $value ); ?>">
					<?php echo esc_html( $label ); ?>
				</label>
			<?php endforeach; ?>
		</div>

		<!-- Availability -->
		<h3 style="margin-top:2rem">📅 Tu disponibilidad</h3>
		<div class="biodevas-grid-2">
			<div class="biodevas-field">
				<label for="bdv-vol-disponibilidad">¿Cuándo puedes participar?</label>
				<select id="bdv-vol-disponibilidad" name="disponibilidad">
					<option value="fines-semana">Fines de semana</option>
					<option value="entre-semana">Entre semana</option>
					<option value="ambos">Ambos</option>
					<option value="puntual">Solo eventos puntuales</option>
				</select>
			</div>
			<div class="biodevas-field">
				<label for="bdv-vol-experiencia">¿Experiencia previa en voluntariado?</label>
				<select id="bdv-vol-experiencia" name="experiencia">
					<option value="no">No, es mi primera vez</option>
					<option value="algo">Sí, algo de experiencia</option>
					<option value="mucha">Sí, amplia experiencia</option>
				</select>
			</div>
		</div>

		<div class="biodevas-field">
			<label for="bdv-vol-motivacion">¿Por qué quieres ser voluntario/a en Biodevas?</label>
			<textarea id="bdv-vol-motivacion" name="motivacion" rows="3"
				placeholder="Cuéntanos qué te motiva…"></textarea>
		</div>

		<!-- Dynamic Fields -->
		<?php
		$dynamic_fields = get_option( 'bdv_volunteer_fields', array() );
		if ( ! empty( $dynamic_fields ) ) :
			echo '<h3 style="margin-top:2rem">📋 Información adicional</h3>';
			foreach ( $dynamic_fields as $field ) :
				$id       = 'bdv-vol-' . esc_attr( $field['name'] );
				$name     = 'dyn_' . esc_attr( $field['name'] );
				$required = ! empty( $field['required'] ) ? 'required' : '';
				$req_mark = ! empty( $field['required'] ) ? ' *' : '';
				?>
				<div class="biodevas-field">
					<label for="<?php echo $id; ?>"><?php echo esc_html( $field['label'] ) . $req_mark; ?></label>
					<?php if ( $field['type'] === 'textarea' ) : ?>
						<textarea id="<?php echo $id; ?>" name="<?php echo $name; ?>" rows="3" <?php echo $required; ?>></textarea>
						<?php
					elseif ( $field['type'] === 'select' ) :
						$options = array_filter( array_map( 'trim', explode( "\n", $field['options'] ?? '' ) ) );
						?>
						<select id="<?php echo $id; ?>" name="<?php echo $name; ?>" <?php echo $required; ?>>
							<option value="">Selecciona una opción</option>
							<?php foreach ( $options as $opt ) : ?>
								<option value="<?php echo esc_attr( $opt ); ?>"><?php echo esc_html( $opt ); ?></option>
							<?php endforeach; ?>
						</select>
					<?php elseif ( $field['type'] === 'checkbox' ) : ?>
						<div class="biodevas-check-group">
							<input type="checkbox" id="<?php echo $id; ?>" name="<?php echo $name; ?>" <?php echo $required; ?>>
							<label for="<?php echo $id; ?>">Sí</label>
						</div>
					<?php else : ?>
						<input type="<?php echo esc_attr( $field['type'] ); ?>" id="<?php echo $id; ?>" name="<?php echo $name; ?>" <?php echo $required; ?>>
					<?php endif; ?>
				</div>
				<?php
			endforeach;
		endif;
		?>

		<!-- Consent -->
		<h3 style="margin-top:2rem">✅ Compromisos y consentimiento</h3>
		<div class="biodevas-check-group">
			<input type="checkbox" id="bdv-vol-codigo" name="acepto_codigo" required>
			<label for="bdv-vol-codigo">He leído y acepto el <a
					href="https://drive.google.com/file/d/1MmUSP9hVygjpJi1ACAILLWajzTeqEqlA/view" target="_blank".
					rel="noopener">Código Ético</a> y el <a
					href="https://drive.google.com/file/d/1Qps5ghQjNOPH4B55JEmlkQARJrpPN9jK/view" target="_blank".
					rel="noopener">Programa de Voluntariado</a>. *</label>
		</div>
		<div class="biodevas-check-group">
			<input type="checkbox" id="bdv-vol-protocolo" name="acepto_protocolo" required>
			<label for="bdv-vol-protocolo">He leído el <a
					href="https://drive.google.com/file/d/1g0-466CC7vutS3xkag42tY5vsiSDqHXA/view" target="_blank".
					rel="noopener">Protocolo para la prevención del acoso</a>. *</label>
		</div>
		<div class="biodevas-check-group">
			<input type="checkbox" id="bdv-vol-rgpd" name="rgpd" required>
			<label for="bdv-vol-rgpd">He leído y acepto la <a
					href="<?php echo esc_url( home_url( '/politica-de-privacidad/' ) ); ?>" target="_blank"
					rel="noopener">Política de Privacidad</a> y el tratamiento de mis datos personales conforme al RGPD.
				*</label>
		</div>
		<div class="biodevas-check-group">
			<input type="checkbox" id="bdv-vol-comunicaciones" name="comunicaciones">
			<label for="bdv-vol-comunicaciones">Autorizo a Biodevas a enviarme comunicaciones sobre actividades,
				proyectos y oportunidades de voluntariado.</label>
		</div>
		
		<?php
		$legal_text = get_option( 'bdv_volunteer_legal_text', '' );
		if ( ! empty( $legal_text ) ) :
			?>
		<div class="biodevas-check-group" style="margin-top: 1.5rem; padding: 15px; background: #f9f9f9; border-left: 4px solid #ff8700;">
			<div style="margin-bottom: 10px; font-size: 0.9em; color: #555;">
				<?php echo wp_kses_post( $legal_text ); ?>
			</div>
			<input type="checkbox" id="bdv-vol-declaracion" name="declaracion_responsable" required>
			<label for="bdv-vol-declaracion" style="font-weight: bold;">He leído y acepto esta Declaración Responsable. *</label>
		</div>
		<?php endif; ?>

		<button type="submit" class="biodevas-btn biodevas-btn-primary" style="width:100%;margin-top:1.5rem">Enviar solicitud de
			voluntariado 🌱</button>

	</form>

	<!-- Success screen -->
	<div id="bdv-vol-success" class="biodevas-success-screen" style="display:none" role="status" aria-live="polite">
		<div class="biodevas-success-icon">🌱</div>
		<h2>¡Gracias por querer ser voluntario/a!</h2>
		<p>Tu solicitud se ha registrado correctamente. Hemos enviado un email de confirmación a tu correo.</p>
		<p class="biodevas-small">¿Tienes dudas? Escríbenos a <a
				href="mailto:voluntarios@biodevas.org">voluntarios@biodevas.org</a></p>
		<p><a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="biodevas-btn biodevas-btn-outline">← Volver al inicio</a></p>
	</div>

</div>