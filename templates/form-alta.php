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
 * Template: Multi-step member registration form v2.
 *
 * Rendered by [convoca_alta] shortcode.
 * Aligned with Convoca Theme v2 and static alta-socios.html.
 *
 * @package Convoca\Members
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div id="convoca-form-alta" class="convoca-form" role="region" aria-label="Formulario de alta de socio/a">

	<!-- Step indicator -->
	<div class="conv-step-indicator" role="progressbar" aria-label="Progreso del formulario" aria-valuenow="1"
		aria-valuemin="1" aria-valuemax="4">
		<div class="conv-step-dot active" data-step="1" aria-label="Paso 1: Plan">1</div>
		<div class="conv-step-dot" data-step="2" aria-label="Paso 2: Datos personales">2</div>
		<div class="conv-step-dot" data-step="3" aria-label="Paso 3: Forma de pago">3</div>
		<div class="conv-step-dot" data-step="4" aria-label="Paso 4: Resumen">4</div>
	</div>

	<!-- Alert container -->
	<div id="conv-alert" class="convoca-alert" style="display:none" role="alert" aria-live="assertive"></div>

	<form id="conv-alta-form" novalidate>

		<!-- ═══ STEP 1: Plan ═══ -->
		<div class="conv-form-step active" data-step="1">
			<h2>Paso 1 — Elige tu forma de colaborar</h2>
			<p>Todas las cuotas pueden abonarse con horas de voluntariado o con un pago anual.</p>

			<?php
			$all_plans      = \Convoca\Members\CPT_Miembro::get_plans();
			$main_plans     = array_filter( $all_plans, fn( $p ) => ( $p['modalidad'] ?? 'Numerario' ) === 'Numerario' );
			$familiar_plans = array_filter( $all_plans, fn( $p ) => ( $p['modalidad'] ?? '' ) === 'Familiar' );
			$juvenil_plans  = array_filter( $all_plans, fn( $p ) => ( $p['modalidad'] ?? '' ) === 'Juvenil' );
			?>

			<div class="conv-plan-grid" role="radiogroup" aria-label="Planes de colaboración">
				<?php foreach ( $main_plans as $slug => $plan ) : ?>
					<label class="conv-plan-option">
						<input type="radio" name="plan" value="<?php echo esc_attr( $slug ); ?>" required>
						<div class="conv-plan-label">
							<h4><?php echo esc_html( $plan['label'] ); ?></h4>
							<div class="conv-price"><?php echo (int) $plan['price']; ?>€</div>
							<span class="conv-alt">ó <?php echo (float) $plan['hours']; ?>h voluntariado</span>
						</div>
					</label>
				<?php endforeach; ?>

				<label class="conv-plan-option">
					<input type="radio" name="plan" value="familiar">
					<div class="conv-plan-label">
						<h4>👨‍👩‍👧‍👦 Familiar</h4>
						<div class="conv-price">Desde <?php echo (int) min( array_column( $familiar_plans, 'price' ) ); ?>€
						</div>
						<span class="conv-alt">ó desde
							<?php echo (float) min( array_column( $familiar_plans, 'hours' ) ); ?>h</span>
					</div>
				</label>
				<label class="conv-plan-option">
					<input type="radio" name="plan" value="juvenil">
					<div class="conv-plan-label">
						<h4>🚀 Juvenil</h4>
						<div class="conv-price">Desde <?php echo (int) min( array_column( $juvenil_plans, 'price' ) ); ?>€
						</div>
						<span class="conv-alt">ó desde <?php echo (float) min( array_column( $juvenil_plans, 'hours' ) ); ?>h
							(14–30 años)</span>
					</div>
				</label>
			</div>

			<!-- Sub-plans: Familiar -->
			<div id="conv-sub-familiar" class="conv-sub-plan" style="display:none" role="radiogroup"
				aria-label="Modalidad familiar">
				<p><strong>Elige la modalidad familiar:</strong></p>
				<?php foreach ( $familiar_plans as $slug => $plan ) : ?>
					<label><input type="radio" name="sub_plan" value="<?php echo esc_attr( $slug ); ?>">
						<?php echo esc_html( $plan['label'] ); ?> — <?php echo (int) $plan['price']; ?>€ ó
						<?php echo (float) $plan['hours']; ?>h</label>
				<?php endforeach; ?>
			</div>

			<!-- Sub-plans: Juvenil -->
			<div id="conv-sub-juvenil" class="conv-sub-plan" style="display:none" role="radiogroup"
				aria-label="Modalidad juvenil">
				<p><strong>Elige la modalidad juvenil:</strong></p>
				<?php foreach ( $juvenil_plans as $slug => $plan ) : ?>
					<label><input type="radio" name="sub_plan" value="<?php echo esc_attr( $slug ); ?>">
						<?php echo esc_html( $plan['label'] ); ?> — <?php echo (int) $plan['price']; ?>€ ó
						<?php echo (float) $plan['hours']; ?>h</label>
				<?php endforeach; ?>
			</div>

			<!-- Advantages box (from static web) -->
			<div id="conv-advantages" class="conv-advantages" style="display:none">
				<p>Ventajas de tu plan:</p>
				<ul id="conv-advantages-list"></ul>
			</div>

			<div class="conv-form-nav">
				<span></span>
				<button type="button" class="btn btn-primary conv-next" data-next="2">Siguiente →</button>
			</div>
		</div>

		<!-- ═══ STEP 2: Personal Data ═══ -->
		<div class="conv-form-step" data-step="2">
			<h2>Paso 2 — Tus datos personales</h2>

			<div class="convoca-grid-2">
				<div class="convoca-field">
					<label for="conv-nombre">Nombre completo *</label>
					<input type="text" id="conv-nombre" name="nombre" placeholder="Nombre y apellidos" required
						autocomplete="name">
					<span class="convoca-error-msg">Este campo es obligatorio.</span>
				</div>
				<div class="convoca-field">
					<label for="conv-dni">DNI / NIE *</label>
					<input type="text" id="conv-dni" name="dni" placeholder="12345678A" required
						pattern="([0-9]{8}[A-Za-z]|[XYZxyz][0-9]{7}[A-Za-z])">
					<span class="convoca-error-msg">Introduce un DNI o NIE válido.</span>
				</div>
			</div>

			<div class="convoca-grid-2">
				<div class="convoca-field">
					<label for="conv-fechanac">Fecha de nacimiento *</label>
					<input type="date" id="conv-fechanac" name="fecha_nacimiento" required>
					<span class="convoca-error-msg">Introduce tu fecha de nacimiento.</span>
					<span id="conv-age-badge" class="conv-age-badge" style="display:none"></span>
				</div>
				<div class="convoca-field">
					<label for="conv-email">Correo electrónico *</label>
					<input type="email" id="conv-email" name="email" placeholder="tu@correo.com" required
						autocomplete="email">
					<span class="convoca-error-msg">Introduce un email válido.</span>
				</div>
			</div>

			<div class="convoca-grid-2">
				<div class="convoca-field">
					<label for="conv-telefono">Teléfono *</label>
					<input type="tel" id="conv-telefono" name="telefono" placeholder="600 000 000" required
						autocomplete="tel">
					<span class="convoca-error-msg">Introduce tu número de teléfono.</span>
				</div>
				<div class="convoca-field">
					<label for="conv-whatsapp">¿Tu teléfono tiene WhatsApp?</label>
					<select id="conv-whatsapp" name="whatsapp">
						<option value="si">Sí</option>
						<option value="no">No</option>
					</select>
				</div>
			</div>

			<div class="convoca-field">
				<label for="conv-direccion">Dirección postal *</label>
				<input type="text" id="conv-direccion" name="direccion" placeholder="Calle, número, piso" required
					autocomplete="street-address">
				<span class="convoca-error-msg">Introduce tu dirección.</span>
			</div>

			<div class="convoca-grid-2">
				<div class="convoca-field">
					<label for="conv-municipio">Municipio / Localidad *</label>
					<input type="text" id="conv-municipio" name="municipio" placeholder="Ej: Oviedo" required>
					<span class="convoca-error-msg">Introduce tu municipio.</span>
				</div>
				<div class="convoca-field">
					<label for="conv-canal">Canal de contacto preferente</label>
					<select id="conv-canal" name="canal_contacto">
						<option value="whatsapp">WhatsApp</option>
						<option value="email">Email</option>
						<option value="telefono">Teléfono</option>
					</select>
				</div>
			</div>

			<!-- Minor notice (hidden until age < 18) -->
			<div id="conv-minor" class="convoca-box-warning" style="display:none">
				<strong>⚠️ Al ser menor de edad, necesitas autorización de tu tutor/a legal.</strong>
				<div class="convoca-grid-2 convoca-mt-small">
					<div class="convoca-field">
						<label for="conv-tutor-nombre">Nombre del tutor/a legal *</label>
						<input type="text" id="conv-tutor-nombre" name="tutor_nombre">
					</div>
					<div class="convoca-field">
						<label for="conv-tutor-dni">DNI del tutor/a *</label>
						<input type="text" id="conv-tutor-dni" name="tutor_dni">
					</div>
				</div>
			</div>

			<div class="conv-form-nav">
				<button type="button" class="convoca-btn convoca-btn-outline conv-prev" data-prev="1">← Atrás</button>
				<button type="button" class="convoca-btn convoca-btn-primary conv-next" data-next="3">Siguiente →</button>
			</div>
		</div>

		<!-- ═══ STEP 3: Payment + RGPD ═══ -->
		<div class="conv-form-step" data-step="3">
			<h2>Paso 3 — ¿Cómo aportas?</h2>
			<p>Puedes cubrir tu cuota con horas de voluntariado o con un pago económico anual.</p>

			<!-- High-level Payment Type Selector -->
			<div class="conv-payment-type-group convoca-grid-2 convoca-mt-medium">
				<label class="conv-plan-option conv-card-selector" id="conv-type-economic">
					<input type="radio" name="payment_mode_ui" value="economic">
					<div class="conv-plan-label">
						<h4>💳 Pago económico</h4>
						<p class="convoca-small">Transferencia, Bizum o Tarjeta</p>
					</div>
				</label>
				<label class="conv-plan-option conv-card-selector" id="conv-type-volunteer">
					<input type="radio" name="payment_mode_ui" value="volunteer"> <!-- Acts as vol selector -->
					<div class="conv-plan-label">
						<h4>🤝 Horas de voluntariado</h4>
						<p class="convoca-small">Firma tu acuerdo de voluntariado</p>
					</div>
				</label>
			</div>

			<!-- Specific Economic Methods (Hidden by default) -->
			<div id="conv-economic-options" style="display:none;" class="convoca-mt-medium">
				<p><strong>Selecciona el método de pago:</strong></p>
				<div class="convoca-grid-3" role="radiogroup" aria-label="Método de pago">
					<!-- Tarjeta -->
					<label class="conv-plan-option conv-radio-card" data-method="tarjeta">
						<input type="radio" name="forma_pago" value="tarjeta">
						<div class="conv-plan-label">
							<h4>💳 Tarjeta</h4>
						</div>
					</label>
					<!-- Bizum -->
					<label class="conv-plan-option conv-radio-card" data-method="bizum">
						<input type="radio" name="forma_pago" value="bizum">
						<div class="conv-plan-label">
							<h4>📱 Bizum</h4>
						</div>
					</label>
					<!-- Transferencia -->
					<label class="conv-plan-option conv-radio-card" data-method="transferencia">
						<input type="radio" name="forma_pago" value="transferencia">
						<div class="conv-plan-label">
							<h4>🏦 Transf.</h4>
						</div>
					</label>
				</div>

				<!-- Recursive Payment Option -->
				<div id="conv-recurring-option" class="convoca-mt-medium">
					<label class="convoca-checkbox-box">
						<input type="checkbox" name="pago_recurrente" value="1">
						<span>
							<strong>¿Deseas activar la renovación automática anual?</strong><br>
							<small class="convoca-small">Te enviaremos el enlace de pago directo cuando tu cuota esté cerca de caducar para que no tengas que preocuparte.</small>
						</span>
					</label>
				</div>
			</div>

			<!-- Hidden radio for voluntariado to keep form logic consistent -->
			<div style="display:none">
				<input type="radio" name="forma_pago" value="voluntariado" id="conv-radio-voluntariado">
			</div>

			<!-- Transfer Details (IBAN) -->
			<div id="conv-transfer-details" class="convoca-box" style="display:none;">
				<h4 style="margin-top:0">Pago por transferencia bancaria</h4>
				<p>Al confirmar tu alta, te mostraremos los datos bancarios (IBAN) y el concepto que debes indicar.</p>
				<p>Podrás adjuntar el justificante directamente en esa página para que validemos tu alta lo antes posible.</p>
			</div>

			<!-- Payment info (TPV redirect) -->
			<div id="conv-payment-upload" style="display:none">
				<div class="convoca-box convoca-text-center">
					<p style="font-size:1rem;margin-bottom:.5rem">🔒 <strong>Pago seguro por pasarela bancaria</strong>
					</p>
					<p style="font-size:.9rem;color:#555;margin:0">Al confirmar tu alta serás redirigido/a a la pasarela
						de pago seguro (Redsys) donde podrás pagar con <strong>tarjeta</strong> o <strong>Bizum de
							comercio</strong>.</p>
				</div>
			</div>

			<!-- Volunteer agreement (from static web) -->
			<div id="conv-volunteer-agreement" style="display:none">
				<div class="convoca-field">
					<p class="convoca-small">Al elegir horas de voluntariado, aceptas el
						<a href="https://drive.google.com/file/d/1MmUSP9hVygjpJi1ACAILLWajzTeqEqlA/view" target="_blank".
							rel="noopener">Código Ético</a> y el <a
							href="https://drive.google.com/file/d/1Qps5ghQjNOPH4B55JEmlkQARJrpPN9jK/view".
							target="_blank" rel="noopener">Programa de Voluntariado</a>.
					</p>
					<div class="convoca-check-group">
						<input type="checkbox" id="conv-acuerdo-vol" name="acuerdo_voluntariado">
						<label for="conv-acuerdo-vol">Acepto y firmo digitalmente el acuerdo de voluntariado con
							Convoca, comprometiéndome a cumplir las horas correspondientes a mi plan con ' . esc_html(get_bloginfo('name')) . '.</label>
					</div>
				</div>
			</div>

			<div class="convoca-margin-y">
				<h3>Protección de datos</h3>
				<div class="convoca-check-group">
					<input type="checkbox" id="conv-rgpd" name="rgpd" required>
					<label for="conv-rgpd">He leído y acepto la <a
							href="<?php echo esc_url( home_url( '/politica-de-privacidad/' ) ); ?>" target="_blank"
							rel="noopener">Política de Privacidad</a> y el tratamiento de mis datos personales conforme
						al RGPD. Mis datos serán incluidos en el libro de socios de la Asociación. *</label>
				</div>
				<div class="convoca-check-group">
					<input type="checkbox" id="conv-comunicaciones" name="comunicaciones">
					<label for="conv-comunicaciones">Autorizo a Convoca a enviarme comunicaciones sobre actividades,
						proyectos y noticias relevantes.</label>
				</div>
			</div>

			<div class="conv-form-nav">
				<button type="button" class="convoca-btn convoca-btn-outline conv-prev" data-prev="2">← Atrás</button>
				<button type="button" class="convoca-btn convoca-btn-primary conv-next" data-next="4">Revisar y confirmar →</button>
			</div>
		</div>

		<!-- ═══ STEP 4: Summary ═══ -->
		<div class="conv-form-step" data-step="4">
			<h2>Paso 4 — Revisa tu alta</h2>
			<p>Verifica que todo esté bien antes de confirmar.</p>

			<table class="conv-summary-table">
				<tr>
					<td>Nombre completo</td>
					<td id="conv-sum-nombre">—</td>
				</tr>
				<tr>
					<td>DNI / NIE</td>
					<td id="conv-sum-dni">—</td>
				</tr>
				<tr>
					<td>Fecha de nacimiento</td>
					<td id="conv-sum-fechanac">—</td>
				</tr>
				<tr>
					<td>Email</td>
					<td id="conv-sum-email">—</td>
				</tr>
				<tr>
					<td>Teléfono</td>
					<td id="conv-sum-telefono">—</td>
				</tr>
				<tr>
					<td>Dirección</td>
					<td id="conv-sum-direccion">—</td>
				</tr>
				<tr>
					<td>Municipio</td>
					<td id="conv-sum-municipio">—</td>
				</tr>
				<tr>
					<td>Plan</td>
					<td id="conv-sum-plan">—</td>
				</tr>
				<tr>
					<td>Forma de pago</td>
					<td id="conv-sum-pago">—</td>
				</tr>
				<tr>
					<td>RGPD</td>
					<td id="conv-sum-rgpd">—</td>
				</tr>
			</table>

			<!-- Card preview -->
			<h3 class="conv-text-center" style="margin-top:2rem">Tu tarjeta de socio/a</h3>
			<div class="conv-card-preview">
				<h3>🍁 CONVOCA</h3>
				<div class="conv-card-name" id="conv-card-name">—</div>
				<div class="conv-card-id" id="conv-card-id">Socio/a #—</div>
				<div class="conv-card-type" id="conv-card-type">—</div>
				<div class="conv-card-date" id="conv-card-date">Alta: —</div>
			</div>

			<div class="conv-form-nav">
				<button type="button" class="convoca-btn convoca-btn-outline conv-prev" data-prev="3">← Atrás</button>
				<button type="submit" class="convoca-btn convoca-btn-primary">✔ Confirmar alta</button>
			</div>
		</div>

	</form>

	<!-- Success screen -->
	<div id="conv-success" class="convoca-success-screen" style="display:none" role="status" aria-live="polite">
		<div class="convoca-success-icon">🎉</div>
		<h2>¡Bienvenido/a a Convoca!</h2>
		<p>Tu alta se ha registrado correctamente. Hemos enviado un email de confirmación a tu correo.</p>
		<div class="conv-card-preview" id="conv-card-final">
			<h3>🍁 CONVOCA</h3>
			<div class="conv-card-name" id="conv-final-name">—</div>
			<div class="conv-card-id" id="conv-final-id">Socio/a #—</div>
			<div class="conv-card-type" id="conv-final-type">—</div>
			<div class="conv-card-date" id="conv-final-date">Alta: —</div>
		</div>
		<p><a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="convoca-btn convoca-btn-outline">← Volver al inicio</a></p>
	</div>

</div>