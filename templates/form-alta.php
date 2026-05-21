<?php
/**
 * Template: Multi-step member registration form v2.
 *
 * Rendered by [biodevas_alta] shortcode.
 * Aligned with Biodevas Theme v2 and static alta-socios.html.
 *
 * @package Convoca\Members
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div id="biodevas-form-alta" class="biodevas-form" role="region" aria-label="Formulario de alta de socio/a">

	<!-- Step indicator -->
	<div class="bdv-step-indicator" role="progressbar" aria-label="Progreso del formulario" aria-valuenow="1"
		aria-valuemin="1" aria-valuemax="4">
		<div class="bdv-step-dot active" data-step="1" aria-label="Paso 1: Plan">1</div>
		<div class="bdv-step-dot" data-step="2" aria-label="Paso 2: Datos personales">2</div>
		<div class="bdv-step-dot" data-step="3" aria-label="Paso 3: Forma de pago">3</div>
		<div class="bdv-step-dot" data-step="4" aria-label="Paso 4: Resumen">4</div>
	</div>

	<!-- Alert container -->
	<div id="bdv-alert" class="biodevas-alert" style="display:none" role="alert" aria-live="assertive"></div>

	<form id="bdv-alta-form" novalidate>

		<!-- ═══ STEP 1: Plan ═══ -->
		<div class="bdv-form-step active" data-step="1">
			<h2>Paso 1 — Elige tu forma de colaborar</h2>
			<p>Todas las cuotas pueden abonarse con horas de voluntariado o con un pago anual.</p>

			<?php
			$all_plans      = \Convoca\Members\CPT_Miembro::get_plans();
			$main_plans     = array_filter( $all_plans, fn( $p ) => ( $p['modalidad'] ?? 'Numerario' ) === 'Numerario' );
			$familiar_plans = array_filter( $all_plans, fn( $p ) => ( $p['modalidad'] ?? '' ) === 'Familiar' );
			$juvenil_plans  = array_filter( $all_plans, fn( $p ) => ( $p['modalidad'] ?? '' ) === 'Juvenil' );
			?>

			<div class="bdv-plan-grid" role="radiogroup" aria-label="Planes de colaboración">
				<?php foreach ( $main_plans as $slug => $plan ) : ?>
					<label class="bdv-plan-option">
						<input type="radio" name="plan" value="<?php echo esc_attr( $slug ); ?>" required>
						<div class="bdv-plan-label">
							<h4><?php echo esc_html( $plan['label'] ); ?></h4>
							<div class="bdv-price"><?php echo (int) $plan['price']; ?>€</div>
							<span class="bdv-alt">ó <?php echo (float) $plan['hours']; ?>h voluntariado</span>
						</div>
					</label>
				<?php endforeach; ?>

				<label class="bdv-plan-option">
					<input type="radio" name="plan" value="familiar">
					<div class="bdv-plan-label">
						<h4>👨‍👩‍👧‍👦 Familiar</h4>
						<div class="bdv-price">Desde <?php echo (int) min( array_column( $familiar_plans, 'price' ) ); ?>€
						</div>
						<span class="bdv-alt">ó desde
							<?php echo (float) min( array_column( $familiar_plans, 'hours' ) ); ?>h</span>
					</div>
				</label>
				<label class="bdv-plan-option">
					<input type="radio" name="plan" value="juvenil">
					<div class="bdv-plan-label">
						<h4>🚀 Juvenil</h4>
						<div class="bdv-price">Desde <?php echo (int) min( array_column( $juvenil_plans, 'price' ) ); ?>€
						</div>
						<span class="bdv-alt">ó desde <?php echo (float) min( array_column( $juvenil_plans, 'hours' ) ); ?>h
							(14–30 años)</span>
					</div>
				</label>
			</div>

			<!-- Sub-plans: Familiar -->
			<div id="bdv-sub-familiar" class="bdv-sub-plan" style="display:none" role="radiogroup"
				aria-label="Modalidad familiar">
				<p><strong>Elige la modalidad familiar:</strong></p>
				<?php foreach ( $familiar_plans as $slug => $plan ) : ?>
					<label><input type="radio" name="sub_plan" value="<?php echo esc_attr( $slug ); ?>">
						<?php echo esc_html( $plan['label'] ); ?> — <?php echo (int) $plan['price']; ?>€ ó
						<?php echo (float) $plan['hours']; ?>h</label>
				<?php endforeach; ?>
			</div>

			<!-- Sub-plans: Juvenil -->
			<div id="bdv-sub-juvenil" class="bdv-sub-plan" style="display:none" role="radiogroup"
				aria-label="Modalidad juvenil">
				<p><strong>Elige la modalidad juvenil:</strong></p>
				<?php foreach ( $juvenil_plans as $slug => $plan ) : ?>
					<label><input type="radio" name="sub_plan" value="<?php echo esc_attr( $slug ); ?>">
						<?php echo esc_html( $plan['label'] ); ?> — <?php echo (int) $plan['price']; ?>€ ó
						<?php echo (float) $plan['hours']; ?>h</label>
				<?php endforeach; ?>
			</div>

			<!-- Advantages box (from static web) -->
			<div id="bdv-advantages" class="bdv-advantages" style="display:none">
				<p>Ventajas de tu plan:</p>
				<ul id="bdv-advantages-list"></ul>
			</div>

			<div class="bdv-form-nav">
				<span></span>
				<button type="button" class="btn btn-primary bdv-next" data-next="2">Siguiente →</button>
			</div>
		</div>

		<!-- ═══ STEP 2: Personal Data ═══ -->
		<div class="bdv-form-step" data-step="2">
			<h2>Paso 2 — Tus datos personales</h2>

			<div class="biodevas-grid-2">
				<div class="biodevas-field">
					<label for="bdv-nombre">Nombre completo *</label>
					<input type="text" id="bdv-nombre" name="nombre" placeholder="Nombre y apellidos" required
						autocomplete="name">
					<span class="biodevas-error-msg">Este campo es obligatorio.</span>
				</div>
				<div class="biodevas-field">
					<label for="bdv-dni">DNI / NIE *</label>
					<input type="text" id="bdv-dni" name="dni" placeholder="12345678A" required
						pattern="([0-9]{8}[A-Za-z]|[XYZxyz][0-9]{7}[A-Za-z])">
					<span class="biodevas-error-msg">Introduce un DNI o NIE válido.</span>
				</div>
			</div>

			<div class="biodevas-grid-2">
				<div class="biodevas-field">
					<label for="bdv-fechanac">Fecha de nacimiento *</label>
					<input type="date" id="bdv-fechanac" name="fecha_nacimiento" required>
					<span class="biodevas-error-msg">Introduce tu fecha de nacimiento.</span>
					<span id="bdv-age-badge" class="bdv-age-badge" style="display:none"></span>
				</div>
				<div class="biodevas-field">
					<label for="bdv-email">Correo electrónico *</label>
					<input type="email" id="bdv-email" name="email" placeholder="tu@correo.com" required
						autocomplete="email">
					<span class="biodevas-error-msg">Introduce un email válido.</span>
				</div>
			</div>

			<div class="biodevas-grid-2">
				<div class="biodevas-field">
					<label for="bdv-telefono">Teléfono *</label>
					<input type="tel" id="bdv-telefono" name="telefono" placeholder="600 000 000" required
						autocomplete="tel">
					<span class="biodevas-error-msg">Introduce tu número de teléfono.</span>
				</div>
				<div class="biodevas-field">
					<label for="bdv-whatsapp">¿Tu teléfono tiene WhatsApp?</label>
					<select id="bdv-whatsapp" name="whatsapp">
						<option value="si">Sí</option>
						<option value="no">No</option>
					</select>
				</div>
			</div>

			<div class="biodevas-field">
				<label for="bdv-direccion">Dirección postal *</label>
				<input type="text" id="bdv-direccion" name="direccion" placeholder="Calle, número, piso" required
					autocomplete="street-address">
				<span class="biodevas-error-msg">Introduce tu dirección.</span>
			</div>

			<div class="biodevas-grid-2">
				<div class="biodevas-field">
					<label for="bdv-municipio">Municipio / Localidad *</label>
					<input type="text" id="bdv-municipio" name="municipio" placeholder="Ej: Oviedo" required>
					<span class="biodevas-error-msg">Introduce tu municipio.</span>
				</div>
				<div class="biodevas-field">
					<label for="bdv-canal">Canal de contacto preferente</label>
					<select id="bdv-canal" name="canal_contacto">
						<option value="whatsapp">WhatsApp</option>
						<option value="email">Email</option>
						<option value="telefono">Teléfono</option>
					</select>
				</div>
			</div>

			<!-- Minor notice (hidden until age < 18) -->
			<div id="bdv-minor" class="biodevas-box-warning" style="display:none">
				<strong>⚠️ Al ser menor de edad, necesitas autorización de tu tutor/a legal.</strong>
				<div class="biodevas-grid-2 biodevas-mt-small">
					<div class="biodevas-field">
						<label for="bdv-tutor-nombre">Nombre del tutor/a legal *</label>
						<input type="text" id="bdv-tutor-nombre" name="tutor_nombre">
					</div>
					<div class="biodevas-field">
						<label for="bdv-tutor-dni">DNI del tutor/a *</label>
						<input type="text" id="bdv-tutor-dni" name="tutor_dni">
					</div>
				</div>
			</div>

			<div class="bdv-form-nav">
				<button type="button" class="biodevas-btn biodevas-btn-outline bdv-prev" data-prev="1">← Atrás</button>
				<button type="button" class="biodevas-btn biodevas-btn-primary bdv-next" data-next="3">Siguiente →</button>
			</div>
		</div>

		<!-- ═══ STEP 3: Payment + RGPD ═══ -->
		<div class="bdv-form-step" data-step="3">
			<h2>Paso 3 — ¿Cómo aportas?</h2>
			<p>Puedes cubrir tu cuota con horas de voluntariado o con un pago económico anual.</p>

			<!-- High-level Payment Type Selector -->
			<div class="bdv-payment-type-group biodevas-grid-2 biodevas-mt-medium">
				<label class="bdv-plan-option bdv-card-selector" id="bdv-type-economic">
					<input type="radio" name="payment_mode_ui" value="economic">
					<div class="bdv-plan-label">
						<h4>💳 Pago económico</h4>
						<p class="biodevas-small">Transferencia, Bizum o Tarjeta</p>
					</div>
				</label>
				<label class="bdv-plan-option bdv-card-selector" id="bdv-type-volunteer">
					<input type="radio" name="payment_mode_ui" value="volunteer"> <!-- Acts as vol selector -->
					<div class="bdv-plan-label">
						<h4>🤝 Horas de voluntariado</h4>
						<p class="biodevas-small">Firma tu acuerdo de voluntariado</p>
					</div>
				</label>
			</div>

			<!-- Specific Economic Methods (Hidden by default) -->
			<div id="bdv-economic-options" style="display:none;" class="biodevas-mt-medium">
				<p><strong>Selecciona el método de pago:</strong></p>
				<div class="biodevas-grid-3" role="radiogroup" aria-label="Método de pago">
					<!-- Tarjeta -->
					<label class="bdv-plan-option bdv-radio-card" data-method="tarjeta">
						<input type="radio" name="forma_pago" value="tarjeta">
						<div class="bdv-plan-label">
							<h4>💳 Tarjeta</h4>
						</div>
					</label>
					<!-- Bizum -->
					<label class="bdv-plan-option bdv-radio-card" data-method="bizum">
						<input type="radio" name="forma_pago" value="bizum">
						<div class="bdv-plan-label">
							<h4>📱 Bizum</h4>
						</div>
					</label>
					<!-- Transferencia -->
					<label class="bdv-plan-option bdv-radio-card" data-method="transferencia">
						<input type="radio" name="forma_pago" value="transferencia">
						<div class="bdv-plan-label">
							<h4>🏦 Transf.</h4>
						</div>
					</label>
				</div>

				<!-- Recursive Payment Option -->
				<div id="bdv-recurring-option" class="biodevas-mt-medium">
					<label class="biodevas-checkbox-box">
						<input type="checkbox" name="pago_recurrente" value="1">
						<span>
							<strong>¿Deseas activar la renovación automática anual?</strong><br>
							<small class="biodevas-small">Te enviaremos el enlace de pago directo cuando tu cuota esté cerca de caducar para que no tengas que preocuparte.</small>
						</span>
					</label>
				</div>
			</div>

			<!-- Hidden radio for voluntariado to keep form logic consistent -->
			<div style="display:none">
				<input type="radio" name="forma_pago" value="voluntariado" id="bdv-radio-voluntariado">
			</div>

			<!-- Transfer Details (IBAN) -->
			<div id="bdv-transfer-details" class="biodevas-box" style="display:none;">
				<h4 style="margin-top:0">Pago por transferencia bancaria</h4>
				<p>Al confirmar tu alta, te mostraremos los datos bancarios (IBAN) y el concepto que debes indicar.</p>
				<p>Podrás adjuntar el justificante directamente en esa página para que validemos tu alta lo antes posible.</p>
			</div>

			<!-- Payment info (TPV redirect) -->
			<div id="bdv-payment-upload" style="display:none">
				<div class="biodevas-box biodevas-text-center">
					<p style="font-size:1rem;margin-bottom:.5rem">🔒 <strong>Pago seguro por pasarela bancaria</strong>
					</p>
					<p style="font-size:.9rem;color:#555;margin:0">Al confirmar tu alta serás redirigido/a a la pasarela
						de pago seguro (Redsys) donde podrás pagar con <strong>tarjeta</strong> o <strong>Bizum de
							comercio</strong>.</p>
				</div>
			</div>

			<!-- Volunteer agreement (from static web) -->
			<div id="bdv-volunteer-agreement" style="display:none">
				<div class="biodevas-field">
					<p class="biodevas-small">Al elegir horas de voluntariado, aceptas el
						<a href="https://drive.google.com/file/d/1MmUSP9hVygjpJi1ACAILLWajzTeqEqlA/view" target="_blank"
							rel="noopener">Código Ético</a> y el <a
							href="https://drive.google.com/file/d/1Qps5ghQjNOPH4B55JEmlkQARJrpPN9jK/view"
							target="_blank" rel="noopener">Programa de Voluntariado</a>.
					</p>
					<div class="biodevas-check-group">
						<input type="checkbox" id="bdv-acuerdo-vol" name="acuerdo_voluntariado">
						<label for="bdv-acuerdo-vol">Acepto y firmo digitalmente el acuerdo de voluntariado con
							Biodevas, comprometiéndome a cumplir las horas correspondientes a mi plan.</label>
					</div>
				</div>
			</div>

			<div class="biodevas-margin-y">
				<h3>Protección de datos</h3>
				<div class="biodevas-check-group">
					<input type="checkbox" id="bdv-rgpd" name="rgpd" required>
					<label for="bdv-rgpd">He leído y acepto la <a
							href="<?php echo esc_url( home_url( '/politica-de-privacidad/' ) ); ?>" target="_blank"
							rel="noopener">Política de Privacidad</a> y el tratamiento de mis datos personales conforme
						al RGPD. Mis datos serán incluidos en el libro de socios de la Asociación Biodevas. *</label>
				</div>
				<div class="biodevas-check-group">
					<input type="checkbox" id="bdv-comunicaciones" name="comunicaciones">
					<label for="bdv-comunicaciones">Autorizo a Biodevas a enviarme comunicaciones sobre actividades,
						proyectos y noticias relevantes.</label>
				</div>
			</div>

			<div class="bdv-form-nav">
				<button type="button" class="biodevas-btn biodevas-btn-outline bdv-prev" data-prev="2">← Atrás</button>
				<button type="button" class="biodevas-btn biodevas-btn-primary bdv-next" data-next="4">Revisar y confirmar →</button>
			</div>
		</div>

		<!-- ═══ STEP 4: Summary ═══ -->
		<div class="bdv-form-step" data-step="4">
			<h2>Paso 4 — Revisa tu alta</h2>
			<p>Verifica que todo esté bien antes de confirmar.</p>

			<table class="bdv-summary-table">
				<tr>
					<td>Nombre completo</td>
					<td id="bdv-sum-nombre">—</td>
				</tr>
				<tr>
					<td>DNI / NIE</td>
					<td id="bdv-sum-dni">—</td>
				</tr>
				<tr>
					<td>Fecha de nacimiento</td>
					<td id="bdv-sum-fechanac">—</td>
				</tr>
				<tr>
					<td>Email</td>
					<td id="bdv-sum-email">—</td>
				</tr>
				<tr>
					<td>Teléfono</td>
					<td id="bdv-sum-telefono">—</td>
				</tr>
				<tr>
					<td>Dirección</td>
					<td id="bdv-sum-direccion">—</td>
				</tr>
				<tr>
					<td>Municipio</td>
					<td id="bdv-sum-municipio">—</td>
				</tr>
				<tr>
					<td>Plan</td>
					<td id="bdv-sum-plan">—</td>
				</tr>
				<tr>
					<td>Forma de pago</td>
					<td id="bdv-sum-pago">—</td>
				</tr>
				<tr>
					<td>RGPD</td>
					<td id="bdv-sum-rgpd">—</td>
				</tr>
			</table>

			<!-- Card preview -->
			<h3 class="bdv-text-center" style="margin-top:2rem">Tu tarjeta de socio/a</h3>
			<div class="bdv-card-preview">
				<h3>🍁 BIODEVAS</h3>
				<div class="bdv-card-name" id="bdv-card-name">—</div>
				<div class="bdv-card-id" id="bdv-card-id">Socio/a #—</div>
				<div class="bdv-card-type" id="bdv-card-type">—</div>
				<div class="bdv-card-date" id="bdv-card-date">Alta: —</div>
			</div>

			<div class="bdv-form-nav">
				<button type="button" class="biodevas-btn biodevas-btn-outline bdv-prev" data-prev="3">← Atrás</button>
				<button type="submit" class="biodevas-btn biodevas-btn-primary">✔ Confirmar alta</button>
			</div>
		</div>

	</form>

	<!-- Success screen -->
	<div id="bdv-success" class="biodevas-success-screen" style="display:none" role="status" aria-live="polite">
		<div class="biodevas-success-icon">🎉</div>
		<h2>¡Bienvenido/a a Biodevas!</h2>
		<p>Tu alta se ha registrado correctamente. Hemos enviado un email de confirmación a tu correo.</p>
		<div class="bdv-card-preview" id="bdv-card-final">
			<h3>🍁 BIODEVAS</h3>
			<div class="bdv-card-name" id="bdv-final-name">—</div>
			<div class="bdv-card-id" id="bdv-final-id">Socio/a #—</div>
			<div class="bdv-card-type" id="bdv-final-type">—</div>
			<div class="bdv-card-date" id="bdv-final-date">Alta: —</div>
		</div>
		<p><a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="biodevas-btn biodevas-btn-outline">← Volver al inicio</a></p>
	</div>

</div>