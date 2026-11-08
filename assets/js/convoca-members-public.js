/**
 * Convoca Members — Public JS v2
 * Multi-step navigation, plan logic, advantages, file upload toggle,
 * minor notice, AJAX submission. Uses convoca-common.js APIs.
 */
(function (conv) {
  'use strict';

  /* ══ ALTA DE SOCIOS — Multi-step form ══ */
  conv.observeDynamicForms('#convoca-form-alta', function(form) {
    if (form.id !== 'conv-alta-form') return;

    // Load config from data attribute or fallback to global
    const wrapper = form.closest('#convoca-form-alta');
    const inlineConfig = wrapper && wrapper.dataset.config ? JSON.parse(wrapper.dataset.config) : null;
    const config = inlineConfig || window.convMembers || {};

    // Plan data loaded from PHP via data-config attribute (CPT_Miembro::get_plans())
    const plansData = config.plans || {};

    const altaWrap = conv.$('#convoca-form-alta');
    const steps = conv.$$('.conv-form-step', altaWrap);
    const dots = conv.$$('.conv-step-dot', altaWrap);
    const alert = conv.$('#conv-alert');
    const progress = conv.$('.conv-step-indicator', altaWrap);
    let current = 1;

    /* Step navigation */
    function goStep(n) {
      if (n > current && !validateStep(current)) return;
      current = n;
      steps.forEach(s => s.classList.toggle('active', +s.dataset.step === n));
      dots.forEach(d => {
        d.classList.remove('active', 'done');
        if (+d.dataset.step < n) d.classList.add('done');
        if (+d.dataset.step === n) d.classList.add('active');
      });
      if (progress) progress.setAttribute('aria-valuenow', n);
      if (n === 4) populateSummary();
      altaWrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    conv.$$('.conv-next', altaWrap).forEach(btn =>
      btn.addEventListener('click', () => goStep(+btn.dataset.next))
    );
    conv.$$('.conv-prev', altaWrap).forEach(btn =>
      btn.addEventListener('click', () => goStep(+btn.dataset.prev))
    );

    // Init state
    const initialPlan = form.querySelector('input[name="plan"]:checked');
    if (initialPlan) {
      showAdvantages(initialPlan.value);
      updatePaymentMethods(initialPlan.value);
    }
    const initialSubPlan = form.querySelector('input[name="sub_plan"]:checked');
    if (initialSubPlan) {
      updatePaymentMethods(initialSubPlan.value);
    }

    /* Plan selection */
    conv.$$('input[name="plan"]', altaWrap).forEach(radio =>
      radio.addEventListener('change', () => {
        const v = radio.value;
        const famEl = conv.$('#conv-sub-familiar');
        const juvEl = conv.$('#conv-sub-juvenil');
        if (famEl) famEl.style.display = v === 'familiar' ? 'block' : 'none';
        if (juvEl) juvEl.style.display = v === 'juvenil' ? 'block' : 'none';
        if (v !== 'familiar' && v !== 'juvenil') {
          conv.$$('input[name="sub_plan"]', altaWrap).forEach(r => r.checked = false);
        }
        showAdvantages(v);
        updatePaymentMethods(v);
      })
    );

    /* ── Real‑time blur validation on critical fields ── */
    const criticalSelectors = '#conv-alta-dni, #conv-alta-email, #conv-alta-telefono, #conv-alta-nombre, #conv-alta-fechanac';
    conv.$$(criticalSelectors, form).forEach(function (input) {
      input.addEventListener('blur', function () {
        const field = input.closest('.convoca-field');
        if (field) conv.validateField(field);
      });
      input.addEventListener('input', function () {
        const field = input.closest('.convoca-field');
        if (field) conv.clearFieldError(field);
      });
    });


    function showAdvantages(planKey) {
      var advEl = conv.$("#conv-advantages");
      var listEl = conv.$("#conv-advantages-list");
      if (!advEl || !listEl) return;

      var plan = plansData[planKey];
      var advantages = (plan && plan.advantages) ? plan.advantages : [];

      if (advantages.length > 0) {
        listEl.innerHTML = advantages.map(function(a) { return "<li>" + a + "</li>"; }).join("");
        advEl.style.display = "block";
      } else {
        listEl.innerHTML = "";
        advEl.style.display = "none";
      }
    }

    /* Show sub-plan advantages when sub-plan is selected */
    conv.$$("input[name=sub_plan]", altaWrap).forEach(function(radio) {
      radio.addEventListener("change", function() {
        showAdvantages(radio.value);
        updatePaymentMethods(radio.value);
      });
    });
    function updatePaymentMethods(planKey) {
      let plan = plansData[planKey];
      if (!plan) return;

      let validMethods = plan.payment_methods || [];
      if (validMethods.length === 0) {
        validMethods = ['tarjeta', 'bizum', 'transferencia'];
      }

      conv.$$('#conv-economic-options .conv-radio-card').forEach(el => {
        const method = el.dataset.method;
        const isIncluded = validMethods.includes(method);
        el.style.display = isIncluded ? 'block' : 'none';
      });

      const ecoBtn = conv.$('#conv-type-economic');
      if (ecoBtn) ecoBtn.style.display = 'block';

      const currentMode = form.querySelector('input[name="payment_mode_ui"]:checked');
      if (currentMode && currentMode.value === 'economic' && ecoBtn && ecoBtn.style.display === 'none') {
        currentMode.checked = false;
        conv.$('#conv-economic-options').style.display = 'none';
        ecoBtn.classList.remove('selected');
      }

      const subChecked = form.querySelector('#conv-economic-options input:checked');
      if (subChecked && subChecked.parentElement.style.display === 'none') {
        subChecked.checked = false;
        conv.$('#conv-transfer-details').style.display = 'none';
      }

      // Detalles del método de pago: IBAN si es transferencia, pasarela si es tarjeta
      // o Bizum. Antes nada mostraba estos bloques y el alta no se podía terminar.
      const detallesTransfer = conv.$('#conv-transfer-details');
      const detallesTpv = conv.$('#conv-payment-upload');
      const pintaMetodo = () => {
        const sel = form.querySelector('input[name="forma_pago"]:checked');
        const v = sel ? sel.value : '';
        if (detallesTransfer) detallesTransfer.style.display = (v === 'transferencia') ? 'block' : 'none';
        if (detallesTpv) detallesTpv.style.display = (v === 'tarjeta' || v === 'bizum') ? 'block' : 'none';
      };
      form.querySelectorAll('input[name="forma_pago"]').forEach(r => r.addEventListener('change', pintaMetodo));
      pintaMetodo();
    }

    const fechaInput = conv.$('#conv-fechanac');
    if (fechaInput) {
      fechaInput.addEventListener('change', () => {
        const age = calcAge(fechaInput.value);
        const badge = conv.$('#conv-age-badge');
        const minor = conv.$('#conv-minor');
        if (badge && age !== null) {
          badge.style.display = 'inline-block';
          badge.textContent = age + ' años' + (age < 18 ? ' (menor de edad)' : '');
        }
        if (minor) minor.style.display = age !== null && age < 18 ? 'block' : 'none';
      });
    }

    /* Validation per step */
    function validateStep(n) {
      const stepEl = steps[n - 1];
      if (!stepEl) return true;

      if (n === 1) {
        const sel = form.querySelector('input[name="plan"]:checked');
        if (!sel) { conv.showAlert(alert, 'Selecciona una forma de colaborar.'); return false; }
        if ((sel.value === 'familiar' || sel.value === 'juvenil') &&
          !form.querySelector('input[name="sub_plan"]:checked')) {
          conv.showAlert(alert, 'Selecciona la modalidad dentro de tu plan.'); return false;
        }
      }

      if (n === 2) {
        // Obligatorios del paso + los del tutor/a si quien se apunta es menor de edad.
        //
        // Cada fallo se MARCA en su campo (`has-error` para el color y el aviso, `aria-invalid` para quien
        // navega con lector de pantalla) y el foco va al PRIMERO que falta. Antes, en el caso del menor, el
        // asistente se quedaba parado sin señalar nada: ni campo en rojo, ni aviso junto al tutor, ni foco
        // —medido en la demo el 16/10/2026— y quien rellenaba el formulario no tenía forma de saber qué
        // le faltaba. Los mismos 7 campos del caso adulto ya se marcaban bien; el hueco era el tutor.
        const obligatorios = Array.from(conv.$$('input[required]', stepEl));
        const age = calcAge(fechaInput?.value);
        if (age !== null && age < 18) {
          const tutorNombre = conv.$('#conv-tutor-nombre');
          const tutorDni = conv.$('#conv-tutor-dni');
          if (tutorNombre) obligatorios.push(tutorNombre);
          if (tutorDni) obligatorios.push(tutorDni);
        }

        let ok = true;
        let primerFallo = null;
        obligatorios.forEach(f => {
          const field = f.closest('.convoca-field');
          const falta = !f.value || (f.pattern && !new RegExp(f.pattern).test(f.value));
          if (field) field.classList.toggle('has-error', falta);
          if (falta) {
            f.setAttribute('aria-invalid', 'true');
            ok = false;
            if (!primerFallo) primerFallo = f;
          } else {
            f.removeAttribute('aria-invalid');
          }
        });

        if (!ok) {
          conv.showAlert(alert, 'Revisa los campos marcados: falta algún dato obligatorio.');
          if (primerFallo) primerFallo.focus();
          return false;
        }

        // Formato: DNI/NIE con letra correcta y teléfono español (#97, #98).
        const dniInput = conv.$('#conv-dni', stepEl);
        if (dniInput && dniInput.value.trim() && !dniValido(dniInput.value)) {
          marcarCampo(dniInput, 'El DNI/NIE no es válido: revisa los números y la letra.');
          conv.showAlert(alert, 'El DNI/NIE no es válido: revisa los números y la letra.');
          dniInput.focus();
          return false;
        }

        const telInput = conv.$('#conv-telefono', stepEl);
        if (telInput && telInput.value.trim() && !telefonoValido(telInput.value)) {
          marcarCampo(telInput, 'Escribe un teléfono español de 9 cifras; puedes usar +34, guiones o puntos.');
          conv.showAlert(alert, 'El teléfono no es válido: escribe 9 cifras (admite +34, guiones o puntos).');
          telInput.focus();
          return false;
        }

        const tutorDniInput = conv.$('#conv-tutor-dni', stepEl);
        if (tutorDniInput && tutorDniInput.value.trim() && !dniValido(tutorDniInput.value)) {
          marcarCampo(tutorDniInput, 'El DNI del tutor o tutora no es válido.');
          conv.showAlert(alert, 'El DNI del tutor o tutora no es válido.');
          tutorDniInput.focus();
          return false;
        }
      }

      if (n === 3) {
        const pago = form.querySelector('input[name="forma_pago"]:checked');
        if (!pago) { conv.showAlert(alert, 'Selecciona una forma de pago.'); return false; }

        const rgpd = conv.$('#conv-rgpd');
        if (!rgpd.checked) {
          // El aviso ya se pintaba, pero el campo no daba ninguna señal: ni se marcaba, ni se
          // resaltaba, ni se le movía el foco. Quien navega con teclado o lector de pantalla
          // oía un mensaje sin saber a qué campo se refería (defecto medido con el navegador el
          // 14/10/2026 sobre la demo). Ahora se marca el campo y se le lleva el foco.
          const grupo = rgpd.closest('.convoca-check-group') || rgpd.closest('.convoca-field');
          if (grupo) grupo.classList.add('has-error');
          rgpd.setAttribute('aria-invalid', 'true');
          conv.showAlert(alert, 'Debes aceptar la Política de Privacidad.');
          rgpd.focus();
          return false;
        }
        rgpd.removeAttribute('aria-invalid');
        (rgpd.closest('.convoca-check-group') || rgpd.closest('.convoca-field'))?.classList.remove('has-error');
      }

      conv.hideAlert(alert);
      return true;
    }

    function populateSummary() {
      const v = key => {
        const el = form.querySelector(`[name="${key}"]`);
        if (!el) return '—';
        if (el.type === 'checkbox') return el.checked ? 'Sí' : 'No';
        if (el.type === 'radio') {
          const checked = form.querySelector(`[name="${key}"]:checked`);
          return checked ? checked.value : '—';
        }
        return el.value || '—';
      };

      const nombre = v('nombre');
      const fechanac = v('fecha_nacimiento');
      const age = calcAge(fechanac);

      setText('#conv-sum-nombre', nombre);
      setText('#conv-sum-dni', v('dni'));
      setText('#conv-sum-fechanac', fechanac ? new Date(fechanac).toLocaleDateString('es-ES') : '—');
      setText('#conv-sum-edad', age !== null ? age + ' años' : '—');
      setText('#conv-sum-menor', age !== null && age < 18 ? 'Sí' : 'No');
      setText('#conv-sum-email', v('email'));
      setText('#conv-sum-telefono', v('telefono'));
      setText('#conv-sum-whatsapp', v('whatsapp') === 'si' ? 'Sí' : 'No');
      setText('#conv-sum-direccion', v('direccion'));
      setText('#conv-sum-municipio', v('municipio'));
      setText('#conv-sum-canal', v('canal_contacto'));

      const plan = v('plan');
      const sub = v('sub_plan');
      const key = (plan === 'familiar' || plan === 'juvenil') && sub !== '—' ? sub : plan;
      const data = config.plans?.[key];

      const planLabel = data ? data.label : key;
      const modalidad = data ? (data.modalidad || 'Numerario') : '—';

      const paymentMap = {
        'tarjeta': 'Tarjeta bancaria',
        'bizum': 'Bizum',
        'transferencia': 'Transferencia bancaria',
        'voluntariado': 'Horas de voluntariado'
      };

      setText('#conv-sum-plan', planLabel);
      setText('#conv-sum-modalidad', modalidad);
      setText('#conv-sum-pago', paymentMap[v('forma_pago')] || v('forma_pago'));
      setText('#conv-sum-importe', parseInt(data?.price || 0) + '€');

      const today = new Date().toLocaleDateString('es-ES');
      setText('#conv-sum-fecha', today);
      setText('#conv-sum-rgpd', 'Sí');
      // La casilla se llama 'comunicaciones'; se leía 'comunicaciones_ok', que no existe, así que el
      // resumen decía siempre «No» aunque estuviera marcada. El helper ya traduce la casilla a Sí/No.
      setText('#conv-sum-coms', v('comunicaciones'));

      setText('#conv-card-name', nombre);
      setText('#conv-card-type', planLabel);
      // «Socio/a #TEMP» no es un dato: es un hueco. El número lo asigna el servidor al confirmar
      // el alta, así que hasta entonces se dice lo que pasa en vez de inventarse un número.
      setText('#conv-card-id', config.i18n?.numeroPendiente || 'Socio/a — (número al confirmar)');
      setText('#conv-card-date', 'Alta: ' + today);

      const cardEl = conv.$('#conv-card-preview');
      if (cardEl) {
        cardEl.className = 'conv-card-visual';
        if (key) cardEl.classList.add('card-' + key);
      }
    }

    form.addEventListener('submit', e => {
      e.preventDefault();
      if (!validateStep(current)) return;

      const btn = form.querySelector('[type="submit"]');
      conv.setLoading(btn, true, '✔ Confirmar alta');

      const ajaxUrl = config.ajaxUrl || '/wp-admin/admin-ajax.php';
      const nonceUrl = ajaxUrl + (ajaxUrl.includes('?') ? '&' : '?') + 'action=convoca_get_nonce';

      fetch(nonceUrl)
        .then(r => r.json())
        .then(nonceRes => {
          const freshNonce = nonceRes.success ? nonceRes.data.nonce : (config.nonce || '');
          const freshRestNonce = nonceRes.success ? nonceRes.data.rest_nonce : (config.restNonce || '');

          const fd = new FormData(form);
          fd.append('nonce', freshNonce);

          return fetch(config.restUrl || '/wp-json/convoca/v1/alta', {
            method: 'POST',
            headers: {
              'X-BDV-Nonce': freshNonce,
              'X-WP-Nonce': freshRestNonce
            },
            body: fd,
          });
        })
        .then(r => r.json())
        .then(res => {
          if (res.success) {
            if (res.data.redirect) {
              window.location.href = res.data.redirect;
              return;
            }

            form.style.display = 'none';
            conv.$$('.conv-step-indicator', altaWrap).forEach(el => el.style.display = 'none');
            const success = conv.$('#conv-success');
            if (success) {
              success.style.display = 'block';

              if (res.data.gateway_error) {
                const title = conv.$('h3', success);
                const subText = conv.$('p', success);
                const cardSummary = conv.$('.conv-success-summary', success);

                if (title) title.textContent = 'Registro recibido (Pago pendiente)';
                if (subText) subText.innerHTML = `<strong>Aviso:</strong> ${res.data.error_message}. Tu número de socio se confirmará cuando recibamos el pago.`;
                if (cardSummary) cardSummary.style.borderTop = '2px dashed #e74c3c';
              } else if (res.data.is_volunteer) {
                const sub = conv.$('p', success);
                if (sub) sub.innerHTML = 'Tu solicitud de voluntariado se ha registrado correctamente. <strong>Nos pondremos en contacto contigo para una entrevista personal</strong> y formalizar el acuerdo.';
              }

              setText('#conv-final-name', res.data.nombre);
              setText('#conv-final-id', (res.data.gateway_error ? 'Pendiente' : 'Socio/a #' + res.data.member_id));
              setText('#conv-final-type', res.data.plan_label);
              setText('#conv-final-date', 'Alta: ' + res.data.fecha);
            }
          } else {
            const msgs = res.data?.errors?.join('<br>') || res.data?.message || conv.mensajes.reintentar;
            conv.showAlert(alert, msgs);
            conv.setLoading(btn, false, '✔ Confirmar alta');

            // Si el servidor dice qué campo falla, se vuelve a ese paso y se marca (#96).
            const campos = res.data?.fields || {};
            const primero = Object.keys(campos)[0];
            if (primero) {
              irAlCampo(primero);
              marcarCampo(form.querySelector(`[name="${primero}"]`), campos[primero]);
            }
          }
        })
        .catch(() => {
          conv.showAlert(alert, 'Error de conexión. Inténtalo de nuevo.');
          conv.setLoading(btn, false, '✔ Confirmar alta');
        });
    });
  });

  /* ══ VOLUNTARIADO — Single form ══ */
  conv.observeDynamicForms('#conv-vol-wrapper', function(form) {
    if (form.id !== 'conv-vol-form') return;

    // Load config from data attribute or fallback to global
    const wrapper = form.closest('#conv-vol-wrapper');
    const inlineConfig = wrapper && wrapper.dataset.config ? JSON.parse(wrapper.dataset.config) : null;
    const config = inlineConfig || window.convMembers || {};

    const alert = conv.$('#conv-vol-alert');

    const fechaInput = conv.$('#conv-vol-fechanac');
    if (fechaInput) {
      fechaInput.addEventListener('change', () => {
        const age = calcAge(fechaInput.value);
        const badge = conv.$('#conv-vol-age-badge');
        const minor = conv.$('#conv-vol-minor');
        if (badge && age !== null) {
          badge.style.display = 'inline-block';
          badge.textContent = age + ' años' + (age < 18 ? ' (menor de edad)' : '');
        }
        if (minor) minor.style.display = age !== null && age < 18 ? 'block' : 'none';
      });
    }

    /* ── Real‑time blur validation ── */
    const volCritical = '#conv-vol-dni, #conv-vol-email, #conv-vol-telefono, #conv-vol-nombre, #conv-vol-fechanac';
    conv.$$(volCritical, form).forEach(function (input) {
      input.addEventListener('blur', function () {
        const field = input.closest('.convoca-field');
        if (field) conv.validateField(field);
      });
      input.addEventListener('input', function () {
        const field = input.closest('.convoca-field');
        if (field) conv.clearFieldError(field);
      });
    });

    form.addEventListener('submit', e => {
      e.preventDefault();

      let ok = true;
      conv.$$('input[required], textarea[required]', form).forEach(f => {
        const field = f.closest('.convoca-field');
        if (f.type === 'checkbox') {
          if (!f.checked) ok = false;
        } else if (!f.value) {
          if (field) field.classList.add('has-error');
          ok = false;
        } else {
          if (field) field.classList.remove('has-error');
        }
      });

      if (!conv.$('#conv-vol-codigo').checked ||
        !conv.$('#conv-vol-protocolo').checked ||
        !conv.$('#conv-vol-rgpd').checked ||
        !conv.$('#conv-vol-declaracion').checked) {
        conv.showAlert(alert, 'Debes aceptar todos los compromisos obligatorios.');
        return;
      }

      if (!ok) {
        conv.showAlert(alert, 'Rellena todos los campos obligatorios.');
        return;
      }

      const btn = form.querySelector('[type="submit"]');
      conv.setLoading(btn, true, 'Enviar solicitud de voluntariado 🌱');

      const fd = new FormData(form);
      const nonce = config.volNonce || '';

      conv.ajaxPost('convoca_voluntariado_submit', fd, nonce,
          (res) => {
              // Al acertar hay que retirar el aviso del intento anterior: si no, se leen a la vez el
              // error viejo y el mensaje de éxito.
              conv.hideAlert(alert);
              form.style.display = 'none';
              const success = conv.$('#conv-vol-success');
              if (success) success.style.display = 'block';
          },
          (res) => {
              const msgs = res.data?.errors?.join('<br>') || res.data?.message || conv.mensajes.reintentar;
              conv.showAlert(alert, msgs);
              conv.setLoading(btn, false, 'Enviar solicitud de voluntariado 🌱');
          }
      );
    });
  });

  /* ── Shared utilities ── */
  /* ── Validación de DNI/NIE y teléfono en el navegador (#97) ──
     El servidor ya lo comprobaba; el formulario no, así que se podía rellenar todo, pulsar «confirmar» y
     recibir un rechazo sin señal en el campo. Estas dos funciones replican exactamente las reglas del
     servidor para avisar antes de enviar. */

  function normalizarTelefono(valor) {
    let n = String(valor || '').replace(/[^0-9+]/g, '');
    if (n.indexOf('+34') === 0) n = n.slice(3);
    else if (n.indexOf('0034') === 0) n = n.slice(4);
    return n.replace(/^\+/, '');
  }

  function telefonoValido(valor) {
    return /^[6-9][0-9]{8}$/.test(normalizarTelefono(valor));
  }

  function dniValido(valor) {
    const v = String(valor || '').toUpperCase().replace(/[\s-]/g, '');
    const letras = 'TRWAGMYFPDXBNJZSQVHLCKE';
    let m = v.match(/^([0-9]{8})([A-Z])$/);
    if (m) return letras[parseInt(m[1], 10) % 23] === m[2];
    m = v.match(/^([XYZ])([0-9]{7})([A-Z])$/);
    if (m) {
      const numero = String({ X: 0, Y: 1, Z: 2 }[m[1]]) + m[2];
      return letras[parseInt(numero, 10) % 23] === m[3];
    }
    return false;
  }

  /** Marca un campo, le pone el aviso y devuelve el elemento. */
  function marcarCampo(el, mensaje) {
    if (!el) return null;
    const field = el.closest('.convoca-field') || el.closest('.convoca-check-group');
    if (field) field.classList.add('has-error');
    el.setAttribute('aria-invalid', 'true');
    if (field && mensaje) {
      let aviso = field.querySelector('.convoca-field-error');
      if (!aviso) {
        aviso = document.createElement('span');
        aviso.className = 'convoca-field-error';
        aviso.setAttribute('role', 'alert');
        field.appendChild(aviso);
      }
      aviso.textContent = mensaje;
    }
    return el;
  }

  /** Lleva el formulario al paso que contiene el campo y lo marca (#96). */
  function irAlCampo(nombre) {
    const mapa = { forma_pago: '#conv-pago', rgpd: '#conv-rgpd' };
    const el = form.querySelector(`[name="${nombre}"], [name="${nombre}[]"]`) || conv.$(mapa[nombre] || ('#conv-' + nombre.replace(/_/g, '-')));
    if (!el) return false;

    const paso = el.closest('.conv-form-step');
    const n = paso ? +paso.dataset.step : 0;
    if (n && n !== current) {
      // Salto directo: no se valida el paso actual, porque el que manda es el error del servidor.
      current = n;
      steps.forEach(st => st.classList.toggle('active', +st.dataset.step === n));
      dots.forEach(d => {
        d.classList.remove('active', 'done');
        if (+d.dataset.step < n) d.classList.add('done');
        if (+d.dataset.step === n) d.classList.add('active');
      });
      if (progress) progress.setAttribute('aria-valuenow', n);
    }

    marcarCampo(el);
    if (typeof el.focus === 'function') el.focus({ preventScroll: true });
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    return true;
  }

  function calcAge(dateStr) {
    if (!dateStr) return null;
    const dob = new Date(dateStr);
    const now = new Date();
    let age = now.getFullYear() - dob.getFullYear();
    const m = now.getMonth() - dob.getMonth();
    if (m < 0 || (m === 0 && now.getDate() < dob.getDate())) age--;
    return age;
  }

  function setText(sel, val) {
    const el = conv.$(sel);
    if (el) el.textContent = val;
  }

})(window.convoca || {});
