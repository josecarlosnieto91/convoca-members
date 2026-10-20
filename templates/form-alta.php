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
 * Rendered by [convoca_alta_socio] shortcode.
 * Aligned with Convoca Theme v2 and static alta-socios.html.
 *
 * @package Convoca\Members
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sin JavaScript: la degradación se DECLARA y se da salida.
 *
 * Medido el 13/10/2026 con Playwright sin JavaScript: el formulario no declaraba `method` ni
 * `action`, así que el navegador lo enviaba por GET a la misma página con los campos en la URL y
 * nadie lo procesaba: el alta se perdía en silencio, sin guardar nada y sin explicación.
 *
 * Ahora envía por POST a la propia página y, cuando llega sin JS, aquí se explica qué ocurre y
 * por dónde seguir; el `<noscript>` avisa ANTES de rellenar.
 */
$convoca_sin_js   = ! empty( $_POST['convoca_sin_js_alta'] );
$convoca_opciones = get_option( 'convoca_members_settings' );
$convoca_email    = is_array( $convoca_opciones ) && ! empty( $convoca_opciones['admin_email'] )
	? (string) $convoca_opciones['admin_email']
	: (string) get_option( 'admin_email' );
$convoca_tel      = is_array( $convoca_opciones ) && ! empty( $convoca_opciones['telefono'] )
	? (string) $convoca_opciones['telefono']
	: '';
$convoca_email    = (string) apply_filters( 'convoca_alta_sin_js_email', $convoca_email );
$convoca_tel      = (string) apply_filters( 'convoca_alta_sin_js_telefono', $convoca_tel );

if ( $convoca_sin_js ) :
	?>
	<div class="convoca-alert convoca-alert--warning" role="status">
		<p><?php esc_html_e( 'No se ha registrado tu solicitud: este formulario necesita JavaScript en el navegador.', 'convoca-members' ); ?></p>
		<p><?php esc_html_e( 'Actívalo y vuelve a intentarlo.', 'convoca-members' ); ?>
		<?php if ( $convoca_email ) : ?>
			<?php esc_html_e( 'Si no puedes, escríbenos y te damos de alta a mano:', 'convoca-members' ); ?>
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

<div id="convoca-form-alta" class="convoca-form" role="region" aria-label="<?php esc_attr_e( 'Formulario de alta de socio/a', 'convoca-members' ); ?>">

	<!-- Step indicator -->
	<div class="conv-step-indicator" role="progressbar" aria-label="<?php esc_attr_e( 'Progreso del formulario', 'convoca-members' ); ?>" aria-valuenow="1"
		aria-valuemin="1" aria-valuemax="4">
		<div class="conv-step-dot active" data-step="1" aria-label="<?php esc_attr_e( 'Paso 1: Plan', 'convoca-members' ); ?>">1</div>
		<div class="conv-step-dot" data-step="2" aria-label="<?php esc_attr_e( 'Paso 2: Datos personales', 'convoca-members' ); ?>">2</div>
		<div class="conv-step-dot" data-step="3" aria-label="<?php esc_attr_e( 'Paso 3: Forma de pago', 'convoca-members' ); ?>">3</div>
		<div class="conv-step-dot" data-step="4" aria-label="<?php esc_attr_e( 'Paso 4: Resumen', 'convoca-members' ); ?>">4</div>
	</div>

	<!-- Alert container -->
	<div id="conv-alert" class="convoca-alert" style="display:none" role="alert" aria-live="assertive"></div>

	<!-- Sin JavaScript no hay formulario que enviar: se dice antes de rellenarlo. -->
	<noscript>
		<div class="convoca-alert convoca-alert--warning" role="status">
			<p><?php esc_html_e( 'Este formulario de alta necesita JavaScript en el navegador.', 'convoca-members' ); ?>
			<?php if ( $convoca_email ) : ?>
				<?php esc_html_e( 'Actívalo y vuelve a intentarlo, o escríbenos y te damos de alta a mano:', 'convoca-members' ); ?>
				<a href="<?php echo esc_url( 'mailto:' . $convoca_email ); ?>"><?php echo esc_html( $convoca_email ); ?></a>
			<?php endif; ?>
			<?php if ( $convoca_tel ) : ?>
				(<?php echo esc_html( $convoca_tel ); ?>)
			<?php endif; ?>
			</p>
		</div>
	</noscript>

	<form id="conv-alta-form" method="post" action="" novalidate>
		<?php // Marcador del envío sin JavaScript: lo lee el aviso de arriba. ?>
		<input type="hidden" name="convoca_sin_js_alta" value="1" />

		<!-- ═══ STEP 1: Plan ═══ -->
		<div class="conv-form-step active" data-step="1">
			<h2><?php esc_html_e( 'Paso 1 — Elige tu forma de colaborar', 'convoca-members' ); ?></h2>
			<p><?php esc_html_e( 'Todas las cuotas se abonan anualmente. Con horas de voluntariado puedes renovar sin pagar a partir del segundo año.', 'convoca-members' ); ?></p>

			<?php
			$all_plans      = \Convoca\Members\CPT_Miembro::get_plans();
			$is_active      = fn( $p ) => ! isset( $p['active'] ) || false !== $p['active'];
			$main_plans     = array_filter( $all_plans, fn( $p ) => ( $p['modalidad'] ?? 'Numerario' ) === 'Numerario' && $is_active( $p ) );
			$familiar_plans = array_filter( $all_plans, fn( $p ) => ( $p['modalidad'] ?? '' ) === 'Familiar' && $is_active( $p ) );
			$juvenil_plans  = array_filter( $all_plans, fn( $p ) => ( $p['modalidad'] ?? '' ) === 'Juvenil' && $is_active( $p ) );
			$has_familiar   = ! empty( $familiar_plans );
			$has_juvenil    = ! empty( $juvenil_plans );
			?>

			<div class="conv-plan-grid" role="radiogroup" aria-label="<?php esc_attr_e( 'Planes de colaboración', 'convoca-members' ); ?>">
				<?php foreach ( $main_plans as $slug => $plan ) : ?>
					<label class="conv-plan-option">
						<input type="radio" name="plan" value="<?php echo esc_attr( $slug ); ?>" required>
						<div class="conv-plan-label">
							<h3><?php echo esc_html( $plan['label'] ); ?></h3>
							<div class="conv-price"><?php echo (int) $plan['price']; ?>€</div>
							<span class="conv-alt"><?php /* translators: %s son las horas de voluntariado del plan. */ echo esc_html( sprintf( __( '· %sh para renovar sin pagar', 'convoca-members' ), (float) $plan['hours'] ) ); ?></span>
						</div>
					</label>
				<?php endforeach; ?>

				<?php if ( $has_familiar ) : ?>
				<label class="conv-plan-option">
					<input type="radio" name="plan" value="familiar">
					<div class="conv-plan-label">
						<h3>👨‍👩‍👧‍👦 <?php esc_html_e( 'Familiar', 'convoca-members' ); ?></h3>
						<div class="conv-price"><?php /* translators: %d es el precio en euros del plan más barato de la modalidad. */ echo esc_html( sprintf( __( 'Desde %d€', 'convoca-members' ), (int) min( array_column( $familiar_plans, 'price' ) ) ) ); ?>
						</div>
						<span class="conv-alt"><?php /* translators: %s son las horas del plan familiar más barato. */ echo esc_html( sprintf( __( 'ó desde %sh', 'convoca-members' ), (float) min( array_column( $familiar_plans, 'hours' ) ) ) ); ?></span>
					</div>
				</label>
				<?php endif; ?>
				<?php if ( $has_juvenil ) : ?>
				<label class="conv-plan-option">
					<input type="radio" name="plan" value="juvenil">
					<div class="conv-plan-label">
						<h3>🚀 <?php esc_html_e( 'Juvenil', 'convoca-members' ); ?></h3>
						<div class="conv-price"><?php /* translators: %d es el precio en euros del plan más barato de la modalidad. */ echo esc_html( sprintf( __( 'Desde %d€', 'convoca-members' ), (int) min( array_column( $juvenil_plans, 'price' ) ) ) ); ?>
						</div>
						<span class="conv-alt"><?php /* translators: %s son las horas del plan juvenil más barato. */ echo esc_html( sprintf( __( 'ó desde %sh (14–30 años)', 'convoca-members' ), (float) min( array_column( $juvenil_plans, 'hours' ) ) ) ); ?></span>
					</div>
				</label>
				<?php endif; ?>
			</div>

			<!-- Sub-plans: Familiar -->
			<div id="conv-sub-familiar" class="conv-sub-plan" style="display:none" role="radiogroup"
				aria-label="<?php esc_attr_e( 'Modalidad familiar', 'convoca-members' ); ?>">
				<p><strong><?php esc_html_e( 'Elige la modalidad familiar:', 'convoca-members' ); ?></strong></p>
				<?php foreach ( $familiar_plans as $slug => $plan ) : ?>
					<label><input type="radio" name="sub_plan" value="<?php echo esc_attr( $slug ); ?>">
						<?php
						printf(
							/* translators: %1$s es el nombre del plan, %2$d el precio en euros y %3$s las horas. */
							esc_html__( '%1$s — %2$d€ ó %3$sh', 'convoca-members' ),
							esc_html( $plan['label'] ),
							(int) $plan['price'],
							(float) $plan['hours']
						);
						?>
						</label>
				<?php endforeach; ?>
			</div>

			<!-- Sub-plans: Juvenil -->
			<div id="conv-sub-juvenil" class="conv-sub-plan" style="display:none" role="radiogroup"
				aria-label="<?php esc_attr_e( 'Modalidad juvenil', 'convoca-members' ); ?>">
				<p><strong><?php esc_html_e( 'Elige la modalidad juvenil:', 'convoca-members' ); ?></strong></p>
				<?php foreach ( $juvenil_plans as $slug => $plan ) : ?>
					<label><input type="radio" name="sub_plan" value="<?php echo esc_attr( $slug ); ?>">
						<?php
						printf(
							/* translators: %1$s es el nombre del plan, %2$d el precio en euros y %3$s las horas. */
							esc_html__( '%1$s — %2$d€ ó %3$sh', 'convoca-members' ),
							esc_html( $plan['label'] ),
							(int) $plan['price'],
							(float) $plan['hours']
						);
						?>
						</label>
				<?php endforeach; ?>
			</div>

			<!-- Advantages box (from static web) -->
			<div id="conv-advantages" class="conv-advantages" style="display:none">
				<p><?php esc_html_e( 'Ventajas de tu plan:', 'convoca-members' ); ?></p>
				<ul id="conv-advantages-list"></ul>
			</div>

			<div class="conv-form-nav">
				<span></span>
				<button type="button" class="convoca-btn convoca-btn-primary conv-next" data-next="2"><?php esc_html_e( 'Siguiente →', 'convoca-members' ); ?></button>
			</div>
		</div>

		<!-- ═══ STEP 2: Personal Data ═══ -->
		<div class="conv-form-step" data-step="2">
			<h2><?php esc_html_e( 'Paso 2 — Tus datos personales', 'convoca-members' ); ?></h2>

			<div class="convoca-grid-2">
				<div class="convoca-field">
					<label for="conv-nombre"><?php esc_html_e( 'Nombre completo *', 'convoca-members' ); ?></label>
					<input type="text" id="conv-nombre" name="nombre" placeholder="<?php esc_attr_e( 'Nombre y apellidos', 'convoca-members' ); ?>" required
						autocomplete="name">
					<span class="convoca-error-msg"><?php esc_html_e( 'Este campo es obligatorio.', 'convoca-members' ); ?></span>
				</div>
				<div class="convoca-field">
					<label for="conv-dni"><?php esc_html_e( 'DNI / NIE *', 'convoca-members' ); ?></label>
					<input type="text" id="conv-dni" name="dni" placeholder="12345678A" required
						pattern="([0-9]{8}[A-Za-z]|[XYZxyz][0-9]{7}[A-Za-z])">
					<span class="convoca-error-msg"><?php esc_html_e( 'Introduce un DNI o NIE válido.', 'convoca-members' ); ?></span>
				</div>
			</div>

			<div class="convoca-grid-2">
				<div class="convoca-field">
					<label for="conv-fechanac"><?php esc_html_e( 'Fecha de nacimiento *', 'convoca-members' ); ?></label>
					<input type="date" id="conv-fechanac" name="fecha_nacimiento" required>
					<span class="convoca-error-msg"><?php esc_html_e( 'Introduce tu fecha de nacimiento.', 'convoca-members' ); ?></span>
					<span id="conv-age-badge" class="conv-age-badge" style="display:none"></span>
				</div>
				<div class="convoca-field">
					<label for="conv-email"><?php esc_html_e( 'Correo electrónico *', 'convoca-members' ); ?></label>
					<input type="email" id="conv-email" name="email" placeholder="<?php esc_attr_e( 'tu@correo.com', 'convoca-members' ); ?>" required
						autocomplete="email">
					<span class="convoca-error-msg"><?php esc_html_e( 'Introduce un email válido.', 'convoca-members' ); ?></span>
				</div>
			</div>

			<div class="convoca-grid-2">
				<div class="convoca-field">
					<label for="conv-telefono"><?php esc_html_e( 'Teléfono *', 'convoca-members' ); ?></label>
					<input type="tel" id="conv-telefono" name="telefono" placeholder="600 000 000" required
						autocomplete="tel">
					<span class="convoca-error-msg"><?php esc_html_e( 'Introduce tu número de teléfono.', 'convoca-members' ); ?></span>
				</div>
				<div class="convoca-field">
					<label for="conv-whatsapp"><?php esc_html_e( '¿Tu teléfono tiene WhatsApp?', 'convoca-members' ); ?></label>
					<select id="conv-whatsapp" name="whatsapp">
						<option value="si"><?php esc_html_e( 'Sí', 'convoca-members' ); ?></option>
						<option value="no"><?php esc_html_e( 'No', 'convoca-members' ); ?></option>
					</select>
				</div>
			</div>

			<div class="convoca-field">
				<label for="conv-direccion"><?php esc_html_e( 'Dirección postal *', 'convoca-members' ); ?></label>
				<input type="text" id="conv-direccion" name="direccion" placeholder="<?php esc_attr_e( 'Calle, número, piso', 'convoca-members' ); ?>" required
					autocomplete="street-address">
				<span class="convoca-error-msg"><?php esc_html_e( 'Introduce tu dirección.', 'convoca-members' ); ?></span>
			</div>

			<div class="convoca-grid-2">
				<div class="convoca-field">
					<label for="conv-municipio"><?php esc_html_e( 'Municipio / Localidad *', 'convoca-members' ); ?></label>
					<input type="text" id="conv-municipio" name="municipio" placeholder="<?php esc_attr_e( 'Ej: Oviedo', 'convoca-members' ); ?>" required>
					<span class="convoca-error-msg"><?php esc_html_e( 'Introduce tu municipio.', 'convoca-members' ); ?></span>
				</div>
				<div class="convoca-field">
					<label for="conv-canal"><?php esc_html_e( 'Canal de contacto preferente', 'convoca-members' ); ?></label>
					<select id="conv-canal" name="canal_contacto">
						<option value="whatsapp"><?php esc_html_e( 'WhatsApp', 'convoca-members' ); ?></option>
						<option value="email"><?php esc_html_e( 'Email', 'convoca-members' ); ?></option>
						<option value="telefono"><?php esc_html_e( 'Teléfono', 'convoca-members' ); ?></option>
					</select>
				</div>
			</div>

			<!-- Minor notice (hidden until age < 18) -->
			<div id="conv-minor" class="convoca-box-warning" style="display:none">
				<strong><?php esc_html_e( '⚠️ Al ser menor de edad, necesitas autorización de tu tutor/a legal.', 'convoca-members' ); ?></strong>
				<div class="convoca-grid-2 convoca-mt-small">
					<div class="convoca-field">
						<label for="conv-tutor-nombre"><?php esc_html_e( 'Nombre del tutor/a legal *', 'convoca-members' ); ?></label>
						<input type="text" id="conv-tutor-nombre" name="tutor_nombre">
						<?php
						// Aviso propio: sin él, a quien se apunta siendo menor el asistente se le quedaba parado
						// sin señalarle NADA (medido en la demo el 16/10/2026). No llevan `required` a propósito:
						// solo hacen falta si es menor, y marcarlos obligatorios bloquearía a los adultos.
						?>
						<span class="convoca-error-msg"><?php esc_html_e( 'Hace falta el nombre de tu tutor/a legal.', 'convoca-members' ); ?></span>
					</div>
					<div class="convoca-field">
						<label for="conv-tutor-dni"><?php esc_html_e( 'DNI del tutor/a *', 'convoca-members' ); ?></label>
						<input type="text" id="conv-tutor-dni" name="tutor_dni">
						<span class="convoca-error-msg"><?php esc_html_e( 'Hace falta el DNI de tu tutor/a legal.', 'convoca-members' ); ?></span>
					</div>
				</div>
			</div>

			<div class="conv-form-nav">
				<button type="button" class="convoca-btn convoca-btn-outline conv-prev" data-prev="1"><?php esc_html_e( '← Atrás', 'convoca-members' ); ?></button>
				<button type="button" class="convoca-btn convoca-btn-primary conv-next" data-next="3"><?php esc_html_e( 'Siguiente →', 'convoca-members' ); ?></button>
			</div>
		</div>

		<!-- ═══ STEP 3: Payment + RGPD ═══ -->
		<div class="conv-form-step" data-step="3">
			<h2><?php esc_html_e( 'Paso 3 — Tu cuota', 'convoca-members' ); ?></h2>
			<p><?php esc_html_e( 'La cuota del primer año se abona siempre: es lo que te hace socio/a. Si además te comprometes a hacer horas de voluntariado, a partir del año que viene podrás renovar sin pagar.', 'convoca-members' ); ?></p>

			<!-- El modo económico es el único camino del alta: los métodos de pago van
			siempre visibles (antes quedaban ocultos y el alta no se podía terminar). El
			valor viaja igual porque el JS y el handler lo leen. -->
			<input type="radio" name="payment_mode_ui" value="economic" checked style="display:none">

			<div id="conv-economic-options" class="convoca-mt-medium">
				<p><strong><?php esc_html_e( 'Selecciona el método de pago:', 'convoca-members' ); ?></strong></p>
				<div class="convoca-grid-3" role="radiogroup" aria-label="<?php esc_attr_e( 'Método de pago', 'convoca-members' ); ?>">
					<!-- Tarjeta -->
					<label class="conv-plan-option conv-radio-card" data-method="tarjeta">
						<input type="radio" name="forma_pago" value="tarjeta">
						<div class="conv-plan-label">
							<h3><?php esc_html_e( '💳 Tarjeta', 'convoca-members' ); ?></h3>
						</div>
					</label>
					<!-- Bizum -->
					<label class="conv-plan-option conv-radio-card" data-method="bizum">
						<input type="radio" name="forma_pago" value="bizum">
						<div class="conv-plan-label">
							<h3><?php esc_html_e( '📱 Bizum', 'convoca-members' ); ?></h3>
						</div>
					</label>
					<!-- Transferencia -->
					<label class="conv-plan-option conv-radio-card" data-method="transferencia">
						<input type="radio" name="forma_pago" value="transferencia">
						<div class="conv-plan-label">
							<h3><?php esc_html_e( '🏦 Transf.', 'convoca-members' ); ?></h3>
						</div>
					</label>
				</div>

				<!-- Recursive Payment Option -->
				<div id="conv-recurring-option" class="convoca-mt-medium">
					<label class="convoca-checkbox-box">
						<input type="checkbox" name="pago_recurrente" value="1">
						<span>
							<strong><?php esc_html_e( '¿Deseas activar la renovación automática anual?', 'convoca-members' ); ?></strong><br>
							<small class="convoca-small"><?php esc_html_e( 'Te enviaremos el enlace de pago directo cuando tu cuota esté cerca de caducar para que no tengas que preocuparte.', 'convoca-members' ); ?></small>
						</span>
					</label>
				</div>
			</div>

			<!-- Transfer Details (IBAN) -->
			<div id="conv-transfer-details" class="convoca-box" style="display:none;">
				<h3 style="margin-top:0"><?php esc_html_e( 'Pago por transferencia bancaria', 'convoca-members' ); ?></h3>
				<p><?php esc_html_e( 'Al confirmar tu alta te llevaremos a la pasarela de pago. Allí eliges el método (tarjeta, Bizum o transferencia); si eliges transferencia, verás el IBAN y el concepto que debes indicar.', 'convoca-members' ); ?></p>
				<p><?php esc_html_e( 'Podrás adjuntar el justificante directamente en esa página para que validemos tu alta lo antes posible.', 'convoca-members' ); ?></p>
			</div>

			<!-- Payment info (TPV redirect) -->
			<div id="conv-payment-upload" style="display:none">
				<div class="convoca-box convoca-text-center">
					<p style="font-size:1rem;margin-bottom:.5rem">🔒 <strong><?php esc_html_e( 'Pago seguro por pasarela bancaria', 'convoca-members' ); ?></strong>
					</p>
					<p style="font-size:.9rem;color:#555;margin:0">
					<?php
					printf(
						/* translators: %1$s y %2$s son los métodos de pago, en negrita. */
						esc_html__( 'Al confirmar tu alta serás redirigido/a a la pasarela de pago seguro (Redsys) donde podrás pagar con %1$s o %2$s.', 'convoca-members' ),
						'<strong>' . esc_html__( 'tarjeta', 'convoca-members' ) . '</strong>',
						'<strong>' . esc_html__( 'Bizum de comercio', 'convoca-members' ) . '</strong>'
					);
					?>
					</p>
				</div>
			</div>

			<!-- Compromiso de voluntariado: NO sustituye a la cuota del primer año;
			habilita la renovación por horas a partir del segundo ciclo. -->
			<div id="conv-volunteer-agreement" class="convoca-box">
				<div class="convoca-field">
					<p class="convoca-small">
					<?php
					// Sin enlace si el ajuste está vacío (antes salía un `href=""` que no llevaba a
					// ninguna parte) y sin URLs de documentos hardcodeadas en la plantilla: son ajustes
					// del panel (issues convoca-members#12 y del recorrido a ciegas). El texto va entero,
					// con marcadores, para que la traducción no haya que trocearla.
					printf(
						/* translators: %1$s y %2$s son los enlaces a los documentos; %3$s y %4$s abren y cierran la negrita. */
						esc_html__( '%3$sVoluntariado (opcional).%4$s Si te comprometes a cumplir las horas de tu plan durante el próximo año, podrás renovar sin pagar la cuota. Aceptas el %1$s y el %2$s.', 'convoca-members' ),
						\Convoca\Members\Identidad::enlace_documento( __( 'Código Ético', 'convoca-members' ), \Convoca\Members\Identidad::OPCION_DOC_ETICO ),
						\Convoca\Members\Identidad::enlace_documento( __( 'Programa de Voluntariado', 'convoca-members' ), \Convoca\Members\Identidad::OPCION_DOC_VOLUNTARIADO ),
						'<strong>',
						'</strong>'
					);
					?>
					</p>
					<div class="convoca-check-group">
						<input type="checkbox" id="conv-acuerdo-vol" name="acuerdo_voluntariado">
						<label for="conv-acuerdo-vol"><?php esc_html_e( 'Me comprometo a hacer las horas de voluntariado de mi plan.', 'convoca-members' ); ?><br>
							<small><?php esc_html_e( 'La asociación revisará tu solicitud y te aprobará como voluntario/a; con las horas aprobadas podrás renovar el próximo año sin cuota.', 'convoca-members' ); ?></small></label>
					</div>
				</div>
			</div>

			<div class="convoca-margin-y">
				<h3><?php esc_html_e( 'Protección de datos', 'convoca-members' ); ?></h3>
				<div class="convoca-check-group">
					<input type="checkbox" id="conv-rgpd" name="rgpd" required>
					<label for="conv-rgpd">
					<?php
					printf(
						/* translators: %s es el enlace a la política de privacidad. */
						esc_html__( 'He leído y acepto la %s y el tratamiento de mis datos personales conforme al RGPD. Mis datos serán incluidos en el libro de socios de la Asociación. *', 'convoca-members' ),
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
					<input type="checkbox" id="conv-comunicaciones" name="comunicaciones">
					<label for="conv-comunicaciones">
					<?php
					printf(
						/* translators: %s es el nombre de la entidad. */
						esc_html__( 'Autorizo a %s a enviarme comunicaciones sobre actividades, proyectos y noticias relevantes.', 'convoca-members' ),
						esc_html( \Convoca\Members\Identidad::nombre_entidad() )
					);
					?>
					</label>
				</div>
			</div>

			<div class="conv-form-nav">
				<button type="button" class="convoca-btn convoca-btn-outline conv-prev" data-prev="2"><?php esc_html_e( '← Atrás', 'convoca-members' ); ?></button>
				<button type="button" class="convoca-btn convoca-btn-primary conv-next" data-next="4"><?php esc_html_e( 'Revisar y confirmar →', 'convoca-members' ); ?></button>
			</div>
		</div>

		<!-- ═══ STEP 4: Summary ═══ -->
		<div class="conv-form-step" data-step="4">
			<h2><?php esc_html_e( 'Paso 4 — Revisa tu alta', 'convoca-members' ); ?></h2>
			<p><?php esc_html_e( 'Verifica que todo esté bien antes de confirmar.', 'convoca-members' ); ?></p>

			<table class="conv-summary-table">
				<tr>
					<td><?php esc_html_e( 'Nombre completo', 'convoca-members' ); ?></td>
					<td id="conv-sum-nombre">—</td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'DNI / NIE', 'convoca-members' ); ?></td>
					<td id="conv-sum-dni">—</td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Fecha de nacimiento', 'convoca-members' ); ?></td>
					<td id="conv-sum-fechanac">—</td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Edad', 'convoca-members' ); ?></td>
					<td id="conv-sum-edad">—</td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Menor de edad (autorización)', 'convoca-members' ); ?></td>
					<td id="conv-sum-menor">—</td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Email', 'convoca-members' ); ?></td>
					<td id="conv-sum-email">—</td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Teléfono', 'convoca-members' ); ?></td>
					<td id="conv-sum-telefono">—</td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'WhatsApp', 'convoca-members' ); ?></td>
					<td id="conv-sum-whatsapp">—</td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Dirección', 'convoca-members' ); ?></td>
					<td id="conv-sum-direccion">—</td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Municipio', 'convoca-members' ); ?></td>
					<td id="conv-sum-municipio">—</td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Canal de contacto', 'convoca-members' ); ?></td>
					<td id="conv-sum-canal">—</td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Plan', 'convoca-members' ); ?></td>
					<td id="conv-sum-plan">—</td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Modalidad', 'convoca-members' ); ?></td>
					<td id="conv-sum-modalidad">—</td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Forma de pago', 'convoca-members' ); ?></td>
					<td id="conv-sum-pago">—</td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Importe', 'convoca-members' ); ?></td>
					<td id="conv-sum-importe">—</td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'RGPD', 'convoca-members' ); ?></td>
					<td id="conv-sum-rgpd">—</td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Fecha de la solicitud', 'convoca-members' ); ?></td>
					<td id="conv-sum-fecha">—</td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Comunicaciones', 'convoca-members' ); ?></td>
					<td id="conv-sum-coms">—</td>
				</tr>
			</table>

			<!-- Card preview -->
			<h3 class="conv-text-center" style="margin-top:2rem"><?php esc_html_e( 'Tu tarjeta de socio/a', 'convoca-members' ); ?></h3>
			<div class="conv-card-preview">
				<h3><?php echo esc_html( \Convoca\Members\Identidad::nombre_entidad() ); ?></h3>
				<div class="conv-card-name" id="conv-card-name">—</div>
				<div class="conv-card-id" id="conv-card-id"><?php esc_html_e( 'Socio/a #—', 'convoca-members' ); ?></div>
				<div class="conv-card-type" id="conv-card-type">—</div>
				<div class="conv-card-date" id="conv-card-date"><?php esc_html_e( 'Alta: —', 'convoca-members' ); ?></div>
			</div>

			<div class="conv-form-nav">
				<button type="button" class="convoca-btn convoca-btn-outline conv-prev" data-prev="3"><?php esc_html_e( '← Atrás', 'convoca-members' ); ?></button>
				<button type="submit" class="convoca-btn convoca-btn-primary"><?php esc_html_e( '✔ Confirmar alta', 'convoca-members' ); ?></button>
			</div>
		</div>

	</form>

	<!-- Success screen -->
	<div id="conv-success" class="convoca-success-screen" style="display:none" role="status" aria-live="polite">
		<div class="convoca-success-icon">🎉</div>
		<h2><?php /* translators: %s es el nombre de la entidad. */ echo esc_html( sprintf( __( '¡Bienvenido/a a %s!', 'convoca-members' ), \Convoca\Members\Identidad::nombre_entidad() ) ); ?></h2>
		<p><?php esc_html_e( 'Tu alta se ha registrado correctamente. Hemos enviado un email de confirmación a tu correo.', 'convoca-members' ); ?></p>
		<div class="conv-card-preview" id="conv-card-final">
			<h3><?php echo esc_html( \Convoca\Members\Identidad::nombre_entidad() ); ?></h3>
			<div class="conv-card-name" id="conv-final-name">—</div>
			<div class="conv-card-id" id="conv-final-id"><?php esc_html_e( 'Socio/a #—', 'convoca-members' ); ?></div>
			<div class="conv-card-type" id="conv-final-type">—</div>
			<div class="conv-card-date" id="conv-final-date"><?php esc_html_e( 'Alta: —', 'convoca-members' ); ?></div>
		</div>
		<p><a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="convoca-btn convoca-btn-outline"><?php esc_html_e( '← Volver al inicio', 'convoca-members' ); ?></a></p>
	</div>

</div>