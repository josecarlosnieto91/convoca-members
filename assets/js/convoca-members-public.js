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
        let ok = true;
        conv.$$('input[required]', stepEl).forEach(f => {
          const field = f.closest('.convoca-field');
          if (!f.value || (f.pattern && !new RegExp(f.pattern).test(f.value))) {
            if (field) field.classList.add('has-error');
            ok = false;
          } else {
            if (field) field.classList.remove('has-error');
          }
        });
        
        const age = calcAge(fechaInput?.value);
        if (age !== null && age < 18) {
          const tutorNombre = conv.$('#conv-tutor-nombre');
          const tutorDni = conv.$('#conv-tutor-dni');
          if (tutorNombre && !tutorNombre.value.trim()) { ok = false; }
          if (tutorDni && !tutorDni.value.trim()) { ok = false; }
        }
        if (!ok) { conv.showAlert(alert, 'Rellena todos los campos obligatorios.'); return false; }
      }

      if (n === 3) {
        const pago = form.querySelector('input[name="forma_pago"]:checked');
        if (!pago) { conv.showAlert(alert, 'Selecciona una forma de pago.'); return false; }
        
        if (pago.value === 'voluntariado') {
          const acuerdo = conv.$('#conv-acuerdo-vol');
          if (acuerdo && !acuerdo.checked) { conv.showAlert(alert, 'Debes aceptar el acuerdo de voluntariado.'); return false; }
        }
        if (!conv.$('#conv-rgpd').checked) { conv.showAlert(alert, 'Debes aceptar la Política de Privacidad.'); return false; }
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
      setText('#conv-sum-importe', (v('forma_pago') === 'voluntariado') ? (parseFloat(data?.hours || 0) + 'h') : (parseInt(data?.price || 0) + '€'));

      const today = new Date().toLocaleDateString('es-ES');
      setText('#conv-sum-fecha', today);
      setText('#conv-sum-rgpd', 'Sí');
      setText('#conv-sum-coms', v('comunicaciones_ok') === '1' ? 'Sí' : 'No');

      setText('#conv-card-name', nombre);
      setText('#conv-card-type', planLabel);
      setText('#conv-card-id', 'Socio/a #TEMP');
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
      const nonceUrl = ajaxUrl + (ajaxUrl.includes('?') ? '&' : '?') + 'action=conv_get_nonce';

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
            const msgs = res.data?.errors?.join('<br>') || 'Error desconocido.';
            conv.showAlert(alert, msgs);
            conv.setLoading(btn, false, '✔ Confirmar alta');
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

      conv.ajaxPost('conv_voluntariado_submit', fd, nonce, 
          (res) => {
              form.style.display = 'none';
              const success = conv.$('#conv-vol-success');
              if (success) success.style.display = 'block';
          },
          (res) => {
              const msgs = res.data?.errors?.join('<br>') || 'Error desconocido.';
              conv.showAlert(alert, msgs);
              conv.setLoading(btn, false, 'Enviar solicitud de voluntariado 🌱');
          }
      );
    });
  });

  /* ── Shared utilities ── */
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
