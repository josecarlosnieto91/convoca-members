/**
 * Biodevas Members — Public JS v2
 * Multi-step navigation, plan logic, advantages, file upload toggle,
 * minor notice, AJAX submission. Uses biodevas-common.js APIs.
 */
(function (bdv) {
  'use strict';

  /* ══ ALTA DE SOCIOS — Multi-step form ══ */
  bdv.observeDynamicForms('#biodevas-form-alta', function(form) {
    if (form.id !== 'bdv-alta-form') return;
      
    // Load config from data attribute or fallback to global
    const wrapper = form.closest('#biodevas-form-alta');
    const inlineConfig = wrapper && wrapper.dataset.config ? JSON.parse(wrapper.dataset.config) : null;
    const config = inlineConfig || window.bdvMembers || {};

    // Fallback data in case PHP localization fails or DB is empty
    const DEFAULT_PLANS = {
      'busgosu': { advantages: ['Participa en todas las actividades de naturaleza de forma gratuita.', 'Prioridad en las inscripciones para actividades ambientales.'] },
      'lugg': { advantages: ['20% de descuento en actividades de pago del Centro.', 'Accede a los espacios sociales del local cuando quieras.', 'Reserva el local 2 veces al año para eventos privados.'] },
      'deva': { advantages: ['Todas las ventajas de Busgosu y Lugg.', 'Prioridad en ofertas de trabajo internas.', 'Grupo de WhatsApp exclusivo con comunidad activa.', 'Descuentos especiales con diferentes colaboradores.'] },
      'familiar': { advantages: ['Descuentos en los diferentes formatos.', 'Mismas ventajas que el plan correspondiente, adaptadas al grupo familiar.'] },
      'juvenil': { advantages: ['Espacio independiente para actuar y tomar decisiones.', 'Descuentos del 50% en todos los formatos.'] },
      'fam-busgosu': { advantages: ['Participa en todas las actividades.', 'Prioridad inscripciones.', 'Ventajas unidad familiar.'] },
      'fam-lugg': { advantages: ['Descuento 20%.', 'Acceso espacios.', 'Reserva local.', 'Ventajas unidad familiar.'] },
      'fam-deva': { advantages: ['Todas ventajas Busgosu/Lugg.', 'Prioridad ofertas.', 'Grupo exclusivo.', 'Descuentos colaboradores.', 'Ventajas unidad familiar.'] },
      'juv-busgosu': { advantages: ['Actividades gratuitas.', 'Prioridad inscripciones.', 'Espacio joven.'] },
      'juv-lugg': { advantages: ['Descuento 20%.', 'Acceso espacios.', 'Reserva local.', 'Espacio joven.'] },
      'juv-deva': { advantages: ['Todas ventajas Busgosu/Lugg.', 'Prioridad ofertas.', 'Grupo exclusivo.', 'Espacio joven.'] }
    };

    const inputData = config.plans || {};
    const plansData = { ...DEFAULT_PLANS };
    for (const k in inputData) {
      plansData[k] = { ...DEFAULT_PLANS[k], ...inputData[k] };
    }

    const altaWrap = bdv.$('#biodevas-form-alta');
    const steps = bdv.$$('.bdv-form-step', altaWrap);
    const dots = bdv.$$('.bdv-step-dot', altaWrap);
    const alert = bdv.$('#bdv-alert');
    const progress = bdv.$('.bdv-step-indicator', altaWrap);
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

    bdv.$$('.bdv-next', altaWrap).forEach(btn =>
      btn.addEventListener('click', () => goStep(+btn.dataset.next))
    );
    bdv.$$('.bdv-prev', altaWrap).forEach(btn =>
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
    bdv.$$('input[name="plan"]', altaWrap).forEach(radio =>
      radio.addEventListener('change', () => {
        const v = radio.value;
        const famEl = bdv.$('#bdv-sub-familiar');
        const juvEl = bdv.$('#bdv-sub-juvenil');
        if (famEl) famEl.style.display = v === 'familiar' ? 'block' : 'none';
        if (juvEl) juvEl.style.display = v === 'juvenil' ? 'block' : 'none';
        if (v !== 'familiar' && v !== 'juvenil') {
          bdv.$$('input[name="sub_plan"]', altaWrap).forEach(r => r.checked = false);
        }
        showAdvantages(v);
        updatePaymentMethods(v);
      })
    );

    /* ── Real‑time blur validation on critical fields ── */
    const criticalSelectors = '#bdv-alta-dni, #bdv-alta-email, #bdv-alta-telefono, #bdv-alta-nombre, #bdv-alta-fechanac';
    bdv.$$(criticalSelectors, form).forEach(function (input) {
      input.addEventListener('blur', function () {
        const field = input.closest('.biodevas-field');
        if (field) bdv.validateField(field);
      });
      input.addEventListener('input', function () {
        const field = input.closest('.biodevas-field');
        if (field) bdv.clearFieldError(field);
      });
    });

    function updatePaymentMethods(planKey) {
      let plan = plansData[planKey];
      if (!plan) return;

      let validMethods = plan.payment_methods || [];
      if (validMethods.length === 0) {
        validMethods = ['tarjeta', 'bizum', 'transferencia'];
      }

      bdv.$$('#bdv-economic-options .bdv-radio-card').forEach(el => {
        const method = el.dataset.method;
        const isIncluded = validMethods.includes(method);
        el.style.display = isIncluded ? 'block' : 'none';
      });

      const ecoBtn = bdv.$('#bdv-type-economic');
      if (ecoBtn) ecoBtn.style.display = 'block';

      const currentMode = form.querySelector('input[name="payment_mode_ui"]:checked');
      if (currentMode && currentMode.value === 'economic' && ecoBtn && ecoBtn.style.display === 'none') {
        currentMode.checked = false;
        bdv.$('#bdv-economic-options').style.display = 'none';
        ecoBtn.classList.remove('selected');
      }

      const subChecked = form.querySelector('#bdv-economic-options input:checked');
      if (subChecked && subChecked.parentElement.style.display === 'none') {
        subChecked.checked = false;
        bdv.$('#bdv-transfer-details').style.display = 'none';
      }
    }

    const fechaInput = bdv.$('#bdv-fechanac');
    if (fechaInput) {
      fechaInput.addEventListener('change', () => {
        const age = calcAge(fechaInput.value);
        const badge = bdv.$('#bdv-age-badge');
        const minor = bdv.$('#bdv-minor');
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
        if (!sel) { bdv.showAlert(alert, 'Selecciona una forma de colaborar.'); return false; }
        if ((sel.value === 'familiar' || sel.value === 'juvenil') &&
          !form.querySelector('input[name="sub_plan"]:checked')) {
          bdv.showAlert(alert, 'Selecciona la modalidad dentro de tu plan.'); return false;
        }
      }

      if (n === 2) {
        let ok = true;
        bdv.$$('input[required]', stepEl).forEach(f => {
          const field = f.closest('.biodevas-field');
          if (!f.value || (f.pattern && !new RegExp(f.pattern).test(f.value))) {
            if (field) field.classList.add('has-error');
            ok = false;
          } else {
            if (field) field.classList.remove('has-error');
          }
        });
        
        const age = calcAge(fechaInput?.value);
        if (age !== null && age < 18) {
          const tutorNombre = bdv.$('#bdv-tutor-nombre');
          const tutorDni = bdv.$('#bdv-tutor-dni');
          if (tutorNombre && !tutorNombre.value.trim()) { ok = false; }
          if (tutorDni && !tutorDni.value.trim()) { ok = false; }
        }
        if (!ok) { bdv.showAlert(alert, 'Rellena todos los campos obligatorios.'); return false; }
      }

      if (n === 3) {
        const pago = form.querySelector('input[name="forma_pago"]:checked');
        if (!pago) { bdv.showAlert(alert, 'Selecciona una forma de pago.'); return false; }
        
        if (pago.value === 'voluntariado') {
          const acuerdo = bdv.$('#bdv-acuerdo-vol');
          if (acuerdo && !acuerdo.checked) { bdv.showAlert(alert, 'Debes aceptar el acuerdo de voluntariado.'); return false; }
        }
        if (!bdv.$('#bdv-rgpd').checked) { bdv.showAlert(alert, 'Debes aceptar la Política de Privacidad.'); return false; }
      }

      bdv.hideAlert(alert);
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

      setText('#bdv-sum-nombre', nombre);
      setText('#bdv-sum-dni', v('dni'));
      setText('#bdv-sum-fechanac', fechanac ? new Date(fechanac).toLocaleDateString('es-ES') : '—');
      setText('#bdv-sum-edad', age !== null ? age + ' años' : '—');
      setText('#bdv-sum-menor', age !== null && age < 18 ? 'Sí' : 'No');
      setText('#bdv-sum-email', v('email'));
      setText('#bdv-sum-telefono', v('telefono'));
      setText('#bdv-sum-whatsapp', v('whatsapp') === 'si' ? 'Sí' : 'No');
      setText('#bdv-sum-direccion', v('direccion'));
      setText('#bdv-sum-municipio', v('municipio'));
      setText('#bdv-sum-canal', v('canal_contacto'));

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

      setText('#bdv-sum-plan', planLabel);
      setText('#bdv-sum-modalidad', modalidad);
      setText('#bdv-sum-pago', paymentMap[v('forma_pago')] || v('forma_pago'));
      setText('#bdv-sum-importe', (v('forma_pago') === 'voluntariado') ? (parseFloat(data?.hours || 0) + 'h') : (parseInt(data?.price || 0) + '€'));

      const today = new Date().toLocaleDateString('es-ES');
      setText('#bdv-sum-fecha', today);
      setText('#bdv-sum-rgpd', 'Sí');
      setText('#bdv-sum-coms', v('comunicaciones_ok') === '1' ? 'Sí' : 'No');

      setText('#bdv-card-name', nombre);
      setText('#bdv-card-type', planLabel);
      setText('#bdv-card-id', 'Socio/a #TEMP');
      setText('#bdv-card-date', 'Alta: ' + today);

      const cardEl = bdv.$('#bdv-card-preview');
      if (cardEl) {
        cardEl.className = 'bdv-card-visual';
        if (key.includes('busgosu')) cardEl.classList.add('card-busgosu');
        if (key.includes('lugg')) cardEl.classList.add('card-lugg');
        if (key.includes('deva')) cardEl.classList.add('card-deva');
      }
    }

    form.addEventListener('submit', e => {
      e.preventDefault();
      if (!validateStep(current)) return;

      const btn = form.querySelector('[type="submit"]');
      bdv.setLoading(btn, true, '✔ Confirmar alta');

      const ajaxUrl = config.ajaxUrl || '/wp-admin/admin-ajax.php';
      const nonceUrl = ajaxUrl + (ajaxUrl.includes('?') ? '&' : '?') + 'action=bdv_get_nonce';

      fetch(nonceUrl)
        .then(r => r.json())
        .then(nonceRes => {
          const freshNonce = nonceRes.success ? nonceRes.data.nonce : (config.nonce || '');
          const freshRestNonce = nonceRes.success ? nonceRes.data.rest_nonce : (config.restNonce || '');

          const fd = new FormData(form);
          fd.append('nonce', freshNonce);

          return fetch(config.restUrl || '/wp-json/biodevas/v1/alta', {
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
            bdv.$$('.bdv-step-indicator', altaWrap).forEach(el => el.style.display = 'none');
            const success = bdv.$('#bdv-success');
            if (success) {
              success.style.display = 'block';

              if (res.data.gateway_error) {
                const title = bdv.$('h3', success);
                const subText = bdv.$('p', success);
                const cardSummary = bdv.$('.bdv-success-summary', success);

                if (title) title.textContent = 'Registro recibido (Pago pendiente)';
                if (subText) subText.innerHTML = `<strong>Aviso:</strong> ${res.data.error_message}. Tu número de socio se confirmará cuando recibamos el pago.`;
                if (cardSummary) cardSummary.style.borderTop = '2px dashed #e74c3c';
              } else if (res.data.is_volunteer) {
                const sub = bdv.$('p', success);
                if (sub) sub.innerHTML = 'Tu solicitud de voluntariado se ha registrado correctamente. <strong>Nos pondremos en contacto contigo para una entrevista personal</strong> y formalizar el acuerdo.';
              }

              setText('#bdv-final-name', res.data.nombre);
              setText('#bdv-final-id', (res.data.gateway_error ? 'Pendiente' : 'Socio/a #' + res.data.member_id));
              setText('#bdv-final-type', res.data.plan_label);
              setText('#bdv-final-date', 'Alta: ' + res.data.fecha);
            }
          } else {
            const msgs = res.data?.errors?.join('<br>') || 'Error desconocido.';
            bdv.showAlert(alert, msgs);
            bdv.setLoading(btn, false, '✔ Confirmar alta');
          }
        })
        .catch(() => {
          bdv.showAlert(alert, 'Error de conexión. Inténtalo de nuevo.');
          bdv.setLoading(btn, false, '✔ Confirmar alta');
        });
    });
  });

  /* ══ VOLUNTARIADO — Single form ══ */
  bdv.observeDynamicForms('#bdv-vol-wrapper', function(form) {
    if (form.id !== 'bdv-vol-form') return;

    // Load config from data attribute or fallback to global
    const wrapper = form.closest('#bdv-vol-wrapper');
    const inlineConfig = wrapper && wrapper.dataset.config ? JSON.parse(wrapper.dataset.config) : null;
    const config = inlineConfig || window.bdvMembers || {};
      
    const alert = bdv.$('#bdv-vol-alert');

    const fechaInput = bdv.$('#bdv-vol-fechanac');
    if (fechaInput) {
      fechaInput.addEventListener('change', () => {
        const age = calcAge(fechaInput.value);
        const badge = bdv.$('#bdv-vol-age-badge');
        const minor = bdv.$('#bdv-vol-minor');
        if (badge && age !== null) {
          badge.style.display = 'inline-block';
          badge.textContent = age + ' años' + (age < 18 ? ' (menor de edad)' : '');
        }
        if (minor) minor.style.display = age !== null && age < 18 ? 'block' : 'none';
      });
    }

    /* ── Real‑time blur validation ── */
    const volCritical = '#bdv-vol-dni, #bdv-vol-email, #bdv-vol-telefono, #bdv-vol-nombre, #bdv-vol-fechanac';
    bdv.$$(volCritical, form).forEach(function (input) {
      input.addEventListener('blur', function () {
        const field = input.closest('.biodevas-field');
        if (field) bdv.validateField(field);
      });
      input.addEventListener('input', function () {
        const field = input.closest('.biodevas-field');
        if (field) bdv.clearFieldError(field);
      });
    });

    form.addEventListener('submit', e => {
      e.preventDefault();

      let ok = true;
      bdv.$$('input[required], textarea[required]', form).forEach(f => {
        const field = f.closest('.biodevas-field');
        if (f.type === 'checkbox') {
          if (!f.checked) ok = false;
        } else if (!f.value) {
          if (field) field.classList.add('has-error');
          ok = false;
        } else {
          if (field) field.classList.remove('has-error');
        }
      });

      if (!bdv.$('#bdv-vol-codigo').checked ||
        !bdv.$('#bdv-vol-protocolo').checked ||
        !bdv.$('#bdv-vol-rgpd').checked) {
        bdv.showAlert(alert, 'Debes aceptar todos los compromisos obligatorios.');
        return;
      }

      if (!ok) {
        bdv.showAlert(alert, 'Rellena todos los campos obligatorios.');
        return;
      }

      const btn = form.querySelector('[type="submit"]');
      bdv.setLoading(btn, true, 'Enviar solicitud de voluntariado 🌱');

      const fd = new FormData(form);
      const nonce = config.volNonce || '';

      bdv.ajaxPost('bdv_voluntariado_submit', fd, nonce, 
          (res) => {
              form.style.display = 'none';
              const success = bdv.$('#bdv-vol-success');
              if (success) success.style.display = 'block';
          },
          (res) => {
              const msgs = res.data?.errors?.join('<br>') || 'Error desconocido.';
              bdv.showAlert(alert, msgs);
              bdv.setLoading(btn, false, 'Enviar solicitud de voluntariado 🌱');
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
    const el = bdv.$(sel);
    if (el) el.textContent = val;
  }

})(window.biodevas || {});
