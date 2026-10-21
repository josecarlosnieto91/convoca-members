/**
 * Convoca Members — Mi Area JS (Vanilla)
 * User panel operations inside 'Mi Area'.
 */
(function(conv) {
  'use strict';

  const MiArea = {
    init: function() {
      this.cache();
      if (!this.$container) return;
      this.bindEvents();
      this.checkLoginStatus();
    },

    cache: function() {
      this.$container = conv.$('#conv-mi-area');
      this.$main = conv.$('#conv-main-content');
    },

    bindEvents: function() {
      const self = this;

      // Event delegation implementation for container
      this.$container.addEventListener('submit', function(e) {
        if (e.target && e.target.id === 'conv-login-form') {
            e.preventDefault();
            self.handleLogin(e.target);
        } else if (e.target && e.target.id === 'conv-hours-form') {
            e.preventDefault();
            self.submitHours(e.target);
        } else if (e.target && e.target.matches('form[data-convoca-voluntariado]')) {
            // El circuito del voluntariado (members#50): el cuestionario, el acuerdo y la baja se
            // mandan por la misma puerta que todo lo demás del panel.
            e.preventDefault();
            self.enviarVoluntariado(e.target);
        }
      });

      this.$container.addEventListener('click', function(e) {
        if (e.target.closest('#conv-logout-btn')) {
            e.preventDefault();
            self.handleLogout();
        } else if (e.target.closest('#conv-btn-descargar-datos')) {
          this.descargarDatos(e.target.closest('#conv-btn-descargar-datos'));
        } else if (e.target.closest('#conv-btn-pedir-supresion')) {
          this.pedirSupresion(e.target.closest('#conv-btn-pedir-supresion'));
        } else if (e.target.closest('#conv-btn-unsubscribe')) {
            e.preventDefault();
            if (confirm('¿Seguro que quieres solicitar la baja como socio/a?\n\nLa baja CONSERVA tu historial (pagos e inscripciones): solo deja de ser socio/a.\nSi además quieres que borremos tus datos personales, pídelo después en «Mis datos y privacidad».')) {
                self.requestUnsubscribe();
            }
        } else {
            const tabBtn = e.target.closest('.conv-panel-nav li');
            if (tabBtn) {
                if (tabBtn.classList.contains('active')) return;
                conv.$$('.conv-panel-nav li', self.$container).forEach(t => t.classList.remove('active'));
                tabBtn.classList.add('active');
                self.switchTab(tabBtn.dataset.tab);
            }

            // Notifications: mark single read
            var readBtn = e.target.closest('.conv-member-notif-read-btn');
            if (readBtn) {
                e.preventDefault();
                self.markNotificationRead(readBtn.dataset.notifId);
            }

            // Notifications: mark all read
            var markAllBtn = e.target.closest('[data-action="mark-all-read"]');
            if (markAllBtn) {
                e.preventDefault();
                self.markAllNotificationsRead();
            }
        }
      });
    },

    checkLoginStatus: function() {
      if (window.convMiArea && window.convMiArea.isLoggedIn) {
        const activeTabEl = conv.$('.conv-panel-nav li.active', this.$container);
        const activeTab = activeTabEl ? activeTabEl.dataset.tab : 'profile';
        this.switchTab(activeTab);
        // Fetch unread notification count for badge
        this.fetchUnreadCount();
      }
    },

    fetchUnreadCount: function() {
      var self = this;
      fetch(window.convMiArea.apiUrl + '/member/notifications?limit=1', {
        headers: { 'X-WP-Nonce': window.convMiArea.nonce }
      })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        self.updateNotifBadge(data.unread || 0);
      })
      .catch(function() {});
    },

    updateNotifBadge: function(count) {
      var badge = conv.$('#conv-notif-count');
      if (!badge) return;
      if (count > 0) {
        badge.style.display = 'inline';
        badge.textContent = count > 99 ? '99+' : count;
      } else {
        badge.style.display = 'none';
      }
    },

    /* ── Fetch Profile (current session) ──────── */
    fetchProfile: function() {
      // TODO: implementar carga del perfil
    },

    handleLogin: function(form) {
      const btn = form.querySelector('button');
      const result = conv.$('.form-result', form);
      const data = {
        username: conv.$('#username', form).value,
        password: conv.$('#password', form).value
      };

      conv.setLoading(btn, true, 'Entrar');
      result.innerHTML = '';

      fetch(window.convMiArea.apiUrl + '/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': window.convMiArea.nonce },
        body: JSON.stringify(data)
      })
      .then(res => res.json())
      .then(res => {
        if (res.success) {
          location.reload();
        } else {
          result.innerHTML = '<p class="text-error">' + res.message + '</p>';
          conv.setLoading(btn, false, 'Entrar');
        }
      })
      .catch(() => {
        result.innerHTML = '<p class="text-error">Error de conexión.</p>';
        conv.setLoading(btn, false, 'Entrar');
      });
    },

    handleLogout: function() {
      document.cookie = 'conv_member_session=; Path=/; Expires=Thu, 01 Jan 1970 00:00:01 GMT;';
      location.reload();
    },

    switchTab: function(tab) {
      this.$main.innerHTML = '<div class="conv-spinner"></div>';

      // Refresh unread count on any tab switch
      this.fetchUnreadCount();

      switch(tab) {
        case 'profile': this.fetchProfile(); break;
        case 'inscriptions': this.fetchInscriptions(); break;
        case 'payments': this.fetchPayments(); break;
        case 'turnos': this.fetchTurnos(); break;
        case 'hours': this.fetchHours(); break;
        case 'membership': this.renderMembership(); break;
        case 'search': this.renderSearch(); break;
        case 'privacy': this.fetchPrivacidad(); break;
        case 'notifications': this.fetchNotifications(); break;
      }
    },

    /* ── Mis turnos (shifts#19) ──────────────────────── */
    /**
     * El contenido lo trae el plugin de turnos ya renderizado (sus shortcodes): aquí solo se pinta.
     */
    fetchTurnos: function() {
      fetch(window.convMiArea.apiUrl + '/me/turnos', { headers: { 'X-WP-Nonce': window.convMiArea.nonce } })
        .then(function(r) { return r.json(); })
        .then(function(d) {
          if (d && d.html) { this.$main.innerHTML = '<div class="conv-turnos-wrap">' + d.html + '</div>'; }
          else { this.$main.innerHTML = '<p class="text-error">No se han podido cargar tus turnos.</p>'; }
        }.bind(this))
        .catch(function() {
          this.$main.innerHTML = '<p class="text-error">No se han podido cargar tus turnos.</p>';
        }.bind(this));
    },

    /* ── Search activities (public) ──────────────────── */
    renderSearch: function() {
      var self = this;
      var html = '' +
        '<h2>🔍 ' + (window.convTrans ? convTrans('Buscar actividades', 'convoca-members') : 'Buscar actividades') + '</h2>' +
        '<div class="conv-search-wrap">' +
          '<input type="text" id="conv-search-input" class="conv-search-input" placeholder="' + (window.convTrans ? convTrans('Buscar por nombre, ubicación…', 'convoca-members') : 'Buscar por nombre, ubicación…') + '" />' +
          '<button id="conv-search-btn" class="conv-search-btn">🔍</button>' +
        '</div>' +
        '<div id="conv-search-results" class="conv-search-results"></div>';
      this.$main.innerHTML = html;

      // Wire up search
      var input = conv.$('#conv-search-input');
      var btn = conv.$('#conv-search-btn');
      var results = conv.$('#conv-search-results');
      if (!input) return;

      function doSearch() {
        var q = input.value.trim();
        if (q.length < 2) {
          results.innerHTML = '<p class="text-muted">' + (window.convTrans ? convTrans('Escribe al menos 2 caracteres.', 'convoca-members') : 'Escribe al menos 2 caracteres.') + '</p>';
          return;
        }
        results.innerHTML = '<div class="conv-spinner"></div>';
        fetch(window.convMiArea.apiUrl + '/search?q=' + encodeURIComponent(q) + '&type=activities&limit=20')
          .then(function(r) { return r.json(); })
          .then(function(data) {
            if (!data.results || data.results.length === 0) {
              results.innerHTML = '<p class="text-muted">' + (window.convTrans ? convTrans('No se encontraron resultados.', 'convoca-members') : 'No se encontraron resultados.') + '</p>';
              return;
            }
            var rows = '';
            data.results.forEach(function(item) {
              var dateHtml = item.fecha ? '<span class="conv-search-date">' + item.fecha + '</span>' : '';
              var locHtml = item.ubicacion ? '<span class="conv-search-loc">📍 ' + item.ubicacion + '</span>' : '';
              rows += '<div class="conv-search-item">' +
                '<div class="conv-search-item-title">' + item.title + '</div>' +
                '<div class="conv-search-item-meta">' + dateHtml + ' ' + locHtml + '</div>' +
              '</div>';
            });
            results.innerHTML = rows;
          })
          .catch(function() {
            results.innerHTML = '<p class="text-danger">' + (window.convTrans ? convTrans('Error al buscar.', 'convoca-members') : 'Error al buscar.') + '</p>';
          });
      }

      input.addEventListener('keydown', function(e) { if (e.key === 'Enter') doSearch(); });
      btn.addEventListener('click', doSearch);

      // Focus input
      setTimeout(function() { input.focus(); }, 100);
    },

    fetchProfile: function() {
      fetch(window.convMiArea.apiUrl + '/me', { headers: { 'X-WP-Nonce': window.convMiArea.nonce } })
      .then(res => res.json())
      .then(profile => {
        const isActive = profile.estado === 'activo';
        const canRenew = profile.puede_renovar && profile.renovable && !isActive;
        const vencida  = profile.cuota_vencida;

        // Read-only notice for non-active states (grace period / volunteer-only).
        let noticeHtml = '';
        if (!isActive && profile.estado !== 'pendiente_pago' && profile.estado !== 'pendiente_documentacion') {
          noticeHtml = `
            <div class="conv-alert conv-alert-warning" style="padding:14px 16px;border-radius:8px;background:#fff7e6;border:1px solid #ffd591;margin-bottom:16px;">
              <strong>Tu membresía no está activa.</strong> Puedes ver tu información pero no realizar cambios ni acciones.
              ${canRenew ? '<br>Tu cuota está vencida. Renueva para recuperar los beneficios de socio/a.' : ''}
            </div>`;
        }

        // Expired / grace banner with renewal button.
        let renewBanner = '';
        if (vencida && profile.estado === 'activo') {
          renewBanner = `
            <div class="conv-alert conv-alert-warning" style="padding:14px 16px;border-radius:8px;background:#fff7e6;border:1px solid #ffd591;margin-bottom:16px;">
              <strong>Tu cuota venció el ${this.formatDate(profile.fecha_renovacion)}.</strong> Durante el periodo de gracia puedes renovar; pasado un mes se dará de baja.
            </div>`;
        }

        const renewalRow = profile.fecha_renovacion
          ? `<div class="meta-item"><strong>Próxima renovación:</strong> <span>${this.formatDate(profile.fecha_renovacion)}</span></div>`
          : '';

        const editSection = isActive ? `
          <hr>
          <h3>✏️ Editar mis datos</h3>
          <form id="conv-profile-form" class="conv-form">
            <label>Dirección
              <input type="text" name="direccion" value="${this.escAttr(profile.direccion || '')}" placeholder="Calle, número, ciudad…">
            </label>
            <label>Teléfono
              <input type="tel" name="telefono" value="${this.escAttr(profile.telefono || '')}" placeholder="600 000 000">
            </label>
            <p class="text-muted">${profile.telefono_verificado ? '✅ Teléfono verificado' : 'Verifica tu teléfono con un enlace que recibirás por email.'}</p>
            ${profile.telefono_verificado ? '' : `<button type="button" id="conv-btn-verify-phone" class="btn-secondary">Verificar teléfono</button>
            <div id="conv-phone-verify-msg"></div>`}
            <label>Email
              <input type="email" name="email" value="${this.escAttr(profile.email || '')}" placeholder="tucorreo@ejemplo.com">
            </label>
            <p class="text-muted">Al cambiar el email recibirás un correo con un enlace para confirmarlo. Hasta que no lo confirmes, se mantendrá el email actual.</p>
            ${profile.cumpleanos ? '' : `
            <label>Cumpleaños (solo se puede establecer una vez)
              <input type="date" name="cumpleanos">
            </label>`}
            <button type="submit" class="btn-primary">Guardar cambios</button>
            <div id="conv-profile-msg"></div>
          </form>` : '';

        const dangerSection = isActive ? `
          <hr>
          <h3>Zona de Peligro</h3>
          <p class="text-muted">Si deseas solicitar tu baja como socio/a, puedes hacerlo pulsando el siguiente botón. Se notificará a la administración para procesarla.</p>
          <button id="conv-btn-unsubscribe" class="btn-danger-outline">Solicitar Baja</button>` : '';

        // Renewal CTA shown to expired/grace members.
        const ctaSection = canRenew ? `
          <hr>
          <div style="text-align:center;padding:8px 0;">
            <p><strong>Renueva tu membresía para seguir disfrutando de los beneficios de socio/a.</strong></p>
            <button id="conv-btn-renew-cta" class="btn-primary">🔄 Renovar membresía</button>
            <div id="conv-renovar-cta-msg"></div>
          </div>` : '';

        const html = `
          ${noticeHtml}
          ${renewBanner}
          <h2>👤 Mis Datos</h2>
          <div class="conv-meta-grid">
            <div class="meta-item"><strong>Nombre:</strong> <span>${profile.nombre}</span></div>
            <div class="meta-item"><strong>Email:</strong> <span>${profile.email}</span>${profile.email_pendiente ? ' <em class="text-muted">(cambio pendiente de confirmar)</em>' : ''}</div>
            <div class="meta-item"><strong>Código de Acceso:</strong> <span>${profile.codigo}</span></div>
            <div class="meta-item"><strong>Estado:</strong> ${this.formatEstado(profile.estado)}</div>
            ${renewalRow}
            <div class="meta-item"><strong>Dirección:</strong> <span>${profile.direccion || '—'}</span></div>
            <div class="meta-item"><strong>Teléfono:</strong> <span>${profile.telefono || '—'}</span></div>
            <div class="meta-item"><strong>Cumpleaños:</strong> <span>${profile.cumpleanos || '—'}</span></div>
          </div>

          ${editSection}
          ${ctaSection}
          ${dangerSection}
        `;
        this.$main.innerHTML = html;

        const form = conv.$('#conv-profile-form');
        if (form) {
          form.addEventListener('submit', (e) => {
            e.preventDefault();
            this.saveProfile(form);
          });
        }
        const verifyBtn = conv.$('#conv-btn-verify-phone');
        if (verifyBtn) {
          verifyBtn.addEventListener('click', () => this.verifyPhone(verifyBtn));
        }
        const renewCta = conv.$('#conv-btn-renew-cta');
        if (renewCta) {
          renewCta.addEventListener('click', () => this.renewMembershipFromCta(renewCta));
        }
      })
      .catch(() => { this.$main.innerHTML = '<p>Error cargando perfil.</p>'; });
    },

    renewMembershipFromCta: function(btn) {
      const msg = conv.$('#conv-renovar-cta-msg');
      if (!msg) return;
      btn.disabled = true;
      msg.innerHTML = '<p class="text-muted">Generando pago…</p>';

      fetch(window.convMiArea.apiUrl + '/me/renovar', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': window.convMiArea.nonce }
      })
      .then(res => res.json())
      .then(d => {
        if (d.payment_url) {
          window.location.href = d.payment_url;
        } else {
          btn.disabled = false;
          msg.innerHTML = '<p class="text-error">' + this.escHtml(d.error || 'Error al renovar.') + '</p>';
        }
      })
      .catch(() => {
        btn.disabled = false;
        msg.innerHTML = '<p class="text-error">Error de conexión.</p>';
      });
    },

    formatDate: function(dateStr) {
      if (!dateStr) return '—';
      // Accepts Y-m-d or d/m/Y; try to normalise.
      var parts = String(dateStr).split(/[-/]/);
      if (parts.length === 3) {
        var isYmd = parts[0].length === 4;
        var y = isYmd ? parts[0] : parts[2];
        var m = parts[1];
        var d = isYmd ? parts[2] : parts[0];
        return d + '/' + m + '/' + y;
      }
      return dateStr;
    },

    verifyPhone: function(btn) {
      const msg = conv.$('#conv-phone-verify-msg');
      if (!msg) return;
      btn.disabled = true;
      msg.innerHTML = '<p class="text-muted">Enviando enlace de verificación…</p>';

      fetch(window.convMiArea.apiUrl + '/me/verify-phone', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': window.convMiArea.nonce }
      })
      .then(res => res.json())
      .then(d => {
        btn.disabled = false;
        if (d.success) {
          if (d.verified) {
            msg.innerHTML = '<p class="text-success">✅ Teléfono ya verificado.</p>';
            setTimeout(() => this.fetchProfile(), 800);
          } else {
            msg.innerHTML = '<p class="text-success">📧 Revisa tu correo (' + this.escHtml(d.email || '') + ') y pulsa el enlace para verificar tu teléfono.</p>';
          }
        } else {
          msg.innerHTML = '<p class="text-error">' + this.escHtml(d.error || 'No se pudo iniciar la verificación.') + '</p>';
        }
      })
      .catch(() => {
        btn.disabled = false;
        msg.innerHTML = '<p class="text-error">Error de conexión.</p>';
      });
    },

    saveProfile: function(form) {
      const btn = form.querySelector('button[type="submit"]');
      const msg = conv.$('#conv-profile-msg');
      const payload = {
        direccion: form.querySelector('[name="direccion"]').value,
        telefono: form.querySelector('[name="telefono"]').value,
        email: form.querySelector('[name="email"]').value
      };
      const cumple = form.querySelector('[name="cumpleanos"]');
      if (cumple && cumple.value) payload.cumpleanos = cumple.value;

      btn.disabled = true;
      msg.innerHTML = '<p class="text-muted">Guardando…</p>';

      fetch(window.convMiArea.apiUrl + '/me/profile', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': window.convMiArea.nonce },
        body: JSON.stringify(payload)
      })
      .then(res => res.json())
      .then(d => {
        btn.disabled = false;
        if (d.success) {
          msg.innerHTML = '<p class="text-success">' + (window.convTrans ? convTrans('Datos guardados correctamente.', 'convoca-members') : 'Datos guardados correctamente.') + '</p>';
          if (d.email_pendiente) {
            msg.innerHTML += '<p class="text-muted">Revisa tu correo nuevo para confirmar el cambio de email.</p>';
          }
          setTimeout(() => this.fetchProfile(), 1200);
        } else {
          // Escape API error before injecting (defense-in-depth).
          msg.innerHTML = '<p class="text-error">' + this.escHtml(d.error || 'Error al guardar.') + '</p>';
        }
      })
      .catch(() => {
        btn.disabled = false;
        msg.innerHTML = '<p class="text-error">Error de conexión.</p>';
      });
    },

    escAttr: function(s) {
      return String(s == null ? '' : s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    },

    // Escape para texto de CONTENIDO (títulos, números de recibo, mensajes). Faltaba: el panel
    // llamaba a this.esc() en cuatro sitios y sólo existían escAttr y escHtml, así que la pestaña
    // de Pagos reventaba con «TypeError: this.esc is not a function» en cuanto un pago tenía
    // recibo —la rama del recibo es la única que lo usa ahí— y el panel entero mostraba «Error
    // cargando pagos.» (members#56). Medido en la demo con la ficha 2215.
    esc: function(s) {
      return String(s == null ? '' : s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    },

    fetchInscriptions: function() {
      fetch(window.convMiArea.apiUrl + '/me/inscripciones', { headers: { 'X-WP-Nonce': window.convMiArea.nonce } })
      .then(res => res.json())
      .then(data => {
        if (!data.items || data.items.length === 0) {
          this.$main.innerHTML = '<h2>📝 Mis Inscripciones</h2><p>No tienes inscripciones recientes.</p>';
          return;
        }

        const googleIcon = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>';

        let html = `
          <h2>📝 Mis Inscripciones</h2>
          <table class="conv-table">
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Actividad</th>
                <th>Estado</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              ${data.items.map(item => `
                <tr>
                  <td>${item.fecha}</td>
                  <td>${item.actividad}</td>
                  <td>${this.formatEstadoBadge(item.estado)}</td>
                  <td>
                    ${(['confirmada', 'pagada'].includes(item.estado)) && item.token ? `
                      <a href="/wp-json/convoca-enroll/v1/ics?id=${item.id}&token=${item.token}" class="btn-primary-mini" title="Añadir a mi calendario">📅</a>
                    ` : ''}
                    ${item.fotos_url ? `
                      <a href="${item.fotos_url}" target="_blank" class="btn-primary-mini" title="Ver fotos de la actividad">${googleIcon}</a>
                    ` : ''}
                  </td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        `;
        this.$main.innerHTML = html;
      })
      .catch(() => { this.$main.innerHTML = '<p>Error cargando inscripciones.</p>'; });
    },

    /**
     * «Mis datos y privacidad» (members#42).
     *
     * Antes no había forma de ejercer los derechos desde aquí: quien quería sus datos o su supresión tenía
     * que escribir un correo y esperar a que alguien de la junta supiera dónde estaba la herramienta del
     * escritorio. Y quien pedía la baja creía que con eso desaparecían sus datos, y no: la baja conserva el
     * historial. Aquí se ve lo que se guarda, se descarga en un fichero y se pide la supresión —que la
     * resuelve una persona, no el botón.
     */
    fetchPrivacidad: function() {
      fetch(window.convMiArea.apiUrl + '/me/privacidad', { headers: { 'X-WP-Nonce': window.convMiArea.nonce } })
      .then(res => res.json())
      .then(p => {
        const consentimiento = p.consentimiento_fecha
          ? `Aceptaste la política (versión ${this.esc(p.consentimiento_version || '—')}) el ${this.formatDate(p.consentimiento_fecha)}.`
          : `No tenemos constancia de que hayas aceptado la política. Si la aceptaste en papel o por correo, la junta puede registrarlo desde su panel.`;

        let estadoSupresion = '';
        if (p.pendiente) {
          estadoSupresion = `
            <div class="conv-alert conv-alert-warning" style="padding:14px 16px;border-radius:8px;background:#fff7e6;border:1px solid #ffd591;margin:14px 0;">
              <strong>Has pedido la supresión de tus datos.</strong>
              La pediste el ${this.formatDate(p.solicitada)} y está pendiente: la junta la revisará y borrará tus datos personales.
            </div>`;
        } else if (p.resuelta) {
          estadoSupresion = `
            <div class="conv-alert conv-alert-success" style="padding:14px 16px;border-radius:8px;background:#f6ffed;border:1px solid #b7eb8f;margin:14px 0;">
              <strong>Tus datos personales se borraron</strong> el ${this.formatDate(p.resuelta)}.
            </div>`;
        }

        this.$main.innerHTML = `
          <h2>🔒 Mis datos y privacidad</h2>
          <p class="text-muted">Aquí puedes ver qué guardamos, llevártelo en un fichero y pedir que borremos tus
          datos personales. Lo que sigue guardándose después de un borrado son los <strong>pagos</strong>, y
          solo porque la ley obliga a conservarlos.</p>

          <h3>Qué guardamos</h3>
          <p>Los datos que diste al darte de alta (nombre, apellidos, documento, correo, teléfono y
          dirección) para gestionar tu membresía; tus inscripciones; tus horas de voluntariado; y los pagos,
          que la asociación debe conservar por obligación fiscal.</p>
          <p>${consentimiento}</p>
          ${p.politica_url ? `<p><a href="${this.escAttr(p.politica_url)}" target="_blank" rel="noopener">Leer la política de privacidad</a></p>` : ''}

          <h3>Llevarte tus datos</h3>
          <p class="text-muted">Se descarga un fichero con todo lo que la asociación tiene sobre ti.</p>
          <button id="conv-btn-descargar-datos" class="btn-secondary">⬇️ Descargar mis datos</button>
          <div id="conv-datos-msg"></div>

          <hr>
          <h3>Borrar mis datos</h3>
          ${estadoSupresion}
          <p class="text-muted">Pedir la supresión <strong>no borra nada al momento</strong>: deja la solicitud
          en tu ficha, la junta recibe aviso y la resuelve borrando tus datos personales. Tus pagos se
          conservan por obligación fiscal.</p>
          ${p.pendiente || p.resuelta ? '' : `<button id="conv-btn-pedir-supresion" class="btn-danger-outline">🗑️ Pedir la supresión de mis datos</button>`}
          <div id="conv-supresion-msg"></div>
        `;
      })
      .catch(() => { this.$main.innerHTML = '<p>Error cargando tu privacidad.</p>'; });
    },

    fetchPayments: function() {
      fetch(window.convMiArea.apiUrl + '/me/pagos', { headers: { 'X-WP-Nonce': window.convMiArea.nonce } })
      .then(res => res.json())
      .then(data => {
        const cuota = data.cuota || {};
        const items = data.items || [];

        // El estado de la cuota, DELANTE del botón (members#38): hasta cuándo está pagado el año, o si ya
        // hay un pago esperando. Medido: con la cuota pagada hasta 14/10/2027, esta pantalla no decía
        // nada de esa fecha y el socio podía renovar creyendo que le tocaba.
        let estadoCuota = '';
        if (cuota.pago_pendiente) {
          estadoCuota = `
            <div class="conv-alert conv-alert-warning" style="padding:14px 16px;border-radius:8px;background:#fff7e6;border:1px solid #ffd591;margin-bottom:16px;">
              <strong>Tienes un pago pendiente.</strong> No hace falta crear otro: puedes terminarlo.
              ${cuota.pago_pendiente_url ? `<br><a href="${cuota.pago_pendiente_url}" class="btn-primary-mini">Continuar el pago</a>` : ''}
            </div>`;
        } else if (cuota.fecha_renovacion) {
          const hasta = this.formatDate(cuota.fecha_renovacion);
          if (cuota.cuota_vencida) {
            estadoCuota = `
              <div class="conv-alert conv-alert-warning" style="padding:14px 16px;border-radius:8px;background:#fff7e6;border:1px solid #ffd591;margin-bottom:16px;">
                <strong>Tu cuota venció el ${hasta}.</strong> Al renovar, el año nuevo empieza donde venció, no hoy.
              </div>`;
          } else {
            // Se calcula el vencimiento que quedaría tras renovar, para que se vea que AMPLÍA.
            const partes = cuota.fecha_renovacion.split('-');
            const ampliada = (parseInt(partes[0], 10) + 1) + '-' + partes[1] + '-' + partes[2];
            estadoCuota = `
              <div class="conv-alert conv-alert-success" style="padding:14px 16px;border-radius:8px;background:#f6ffed;border:1px solid #b7eb8f;margin-bottom:16px;">
                <strong>Estás al día hasta el ${hasta}.</strong> Si renuevas ahora, se te amplía un año más (hasta el ${this.formatDate(ampliada)}): no se cobra dos veces el mismo periodo.
              </div>`;
          }
        }

        // La renovación por horas (members#45): el socio ve por dónde va y si su próxima renovación ya le sale
        // gratis. Medido en la demo (ficha 2215: 20 h con plan Bronce de 15 h): su renovación ya estaba
        // cubierta y el panel solo le ofrecía pagar 30 €. Quien pagó sin saberlo se siente engañado.
        let estadoHoras = '';
        if (cuota.horas_plan > 0) {
          const pct = Math.min(100, Math.round((cuota.horas_ciclo / cuota.horas_plan) * 100));
          const barra = (color) => `<div style="background:#f0f0f1;border-radius:6px;height:8px;margin:10px 0;"><div style="background:${color};height:8px;border-radius:6px;width:${pct}%"></div></div>`;
          if (cuota.renueva_por_horas) {
            estadoHoras = `
              <div class="conv-alert conv-alert-success" style="padding:14px 16px;border-radius:8px;background:#f6ffed;border:1px solid #b7eb8f;margin-bottom:16px;">
                <strong>Llevas ${cuota.horas_ciclo} h de las ${cuota.horas_plan} h de tu plan: tu próxima renovación es sin pagar.</strong>
                ${barra('#52c41a')}
                ${cuota.renovacion_gratuita ? `<span>Con tus horas, te quedaría cubierto hasta el ${this.formatDate(cuota.renovacion_gratuita)}.</span>` : ''}
                <div style="margin-top:10px;">
                  <button id="conv-btn-renovar-horas" class="btn-primary-mini">🌱 Renovar ahora sin pago</button>
                </div>
                <div id="conv-renovar-horas-msg" style="margin-top:8px;"></div>
              </div>`;
          } else {
            estadoHoras = `
              <div class="conv-alert" style="padding:14px 16px;border-radius:8px;background:#fafafa;border:1px solid #e0e0e0;margin-bottom:16px;">
                <strong>Llevas ${cuota.horas_ciclo} h de las ${cuota.horas_plan} h de tu plan.</strong>
                ${barra('#1677ff')}
                <span>Te faltan ${cuota.horas_faltan} h para que tu próxima renovación sea sin pagar. Las horas de voluntariado valen por la cuota.</span>
              </div>`;
          }
        }

        const alDia = !!cuota.fecha_renovacion && !cuota.cuota_vencida;
        const etiquetaBoton = alDia ? '🔄 Renovar por adelantado' : '🔄 Renovar membresía';
        const avisoAmplia = alDia
          ? 'Vas a renovar por adelantado. Se te sumará un año más al periodo que ya tienes pagado, no se cobra dos veces lo mismo. ¿Seguir?'
          : '';

        let html = `
          <h2>💳 Mis Pagos</h2>
          ${estadoHoras}
          ${estadoCuota}
          <table class="conv-table">
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Concepto</th>
                <th>Importe</th>
                <th>Estado</th>
                <th>Recibo</th>
              </tr>
            </thead>
            <tbody>
              ${items.map(item => `
                <tr>
                  <td>${item.fecha}</td>
                  <td>${item.concepto}</td>
                  <td>${item.importe}€</td>
                  <td>${this.formatEstadoBadge(item.estado)}</td>
                  <td>${item.recibo_url
                    ? `<a href="${this.escAttr(item.recibo_url)}" target="_blank" rel="noopener" title="Ver o imprimir el recibo (se abre en otra pestaña)">🧾 ${item.recibo ? this.esc(item.recibo) : 'Ver recibo'}</a>`
                    : (item.recibo ? this.esc(item.recibo) : '—')}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
          <hr>
          <h3>Renovación</h3>
          <p class="text-muted">Tu membresía se renueva cada año. Puedes renovar por adelantado cuando quieras desde aquí.</p>
          ${cuota.pago_pendiente
            ? '<p class="text-muted">Ya tienes un pago pendiente: termínalo antes de crear otro.</p>'
            : `<button id="conv-btn-renovar-panel" class="btn-primary" ${avisoAmplia ? `data-amplia="${this.escAttr(avisoAmplia)}"` : ''}>${etiquetaBoton}</button>`}
          <div id="conv-renovar-panel-msg"></div>
        `;
        this.$main.innerHTML = html;

        const btnHoras = conv.$('#conv-btn-renovar-horas');
        if (btnHoras) {
          btnHoras.addEventListener('click', () => this.renovarPorHoras(btnHoras));
        }

        const btn = conv.$('#conv-btn-renovar-panel');
        if (btn) {
          btn.addEventListener('click', () => this.renewMembership(btn));
        }
      })
      .catch((err) => {
        // Antes se tragaba el error y sólo se veía «Error cargando pagos.» (members#56): ni pista para
        // quien lo sufre ni para quien lo depura. Ahora queda en la consola con su traza.
        console.error('convoca-members: error cargando pagos', err);
        this.$main.innerHTML = '<p>Error cargando pagos.</p>';
      });
    },

    /** Descarga los datos personales del socio como fichero (members#42). */
    descargarDatos: function(btn) {
      const msg = conv.$('#conv-datos-msg');
      btn.disabled = true;
      if (msg) { msg.innerHTML = '<p class="text-muted">Preparando tu fichero…</p>'; }

      fetch(window.convMiArea.apiUrl + '/me/datos', { headers: { 'X-WP-Nonce': window.convMiArea.nonce } })
      .then(res => res.json())
      .then(datos => {
        const texto = JSON.stringify(datos, null, 2);
        const url   = URL.createObjectURL(new Blob([texto], { type: 'application/json' }));
        const a     = document.createElement('a');
        a.href = url;
        a.download = 'mis-datos-convoca.json';
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
        if (msg) { msg.innerHTML = '<p class="text-muted">Fichero descargado. Guárdalo donde quieras.</p>'; }
        btn.disabled = false;
      })
      .catch(() => {
        if (msg) { msg.innerHTML = '<p class="text-error">No se ha podido preparar el fichero. Inténtalo de nuevo.</p>'; }
        btn.disabled = false;
      });
    },

    /** Pide la supresión de los datos personales: queda en la ficha y la junta recibe aviso (members#42). */
    pedirSupresion: function(btn) {
      if (!window.confirm('Vas a pedir que se borren tus datos personales. Se avisará a la junta y lo resolverá una persona; tus pagos se conservan por obligación fiscal. ¿Seguir?')) {
        return;
      }

      const msg = conv.$('#conv-supresion-msg');
      btn.disabled = true;
      fetch(window.convMiArea.apiUrl + '/me/supresion', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': window.convMiArea.nonce }
      })
      .then(res => res.json())
      .then(d => {
        if (msg) {
          msg.innerHTML = '<p class="' + (d.ok ? 'text-muted' : 'text-error') + '">' + this.esc(d.mensaje || '') + '</p>';
        }
        if (d.ok) { setTimeout(() => this.fetchPrivacidad(), 900); } else { btn.disabled = false; }
      })
      .catch(() => {
        if (msg) { msg.innerHTML = '<p class="text-error">No se ha podido registrar la solicitud. Inténtalo de nuevo.</p>'; }
        btn.disabled = false;
      });
    },

    /**
     * Renovar sin pago por las horas hechas (members#45).
     *
     * El servidor vuelve a contar las horas antes de renovar: si no llegan, responde que no y se le dice sin
     * rodeos. Este botón no da nada que no esté ganado.
     */
    renovarPorHoras: function(btn) {
      const msg = document.getElementById('conv-renovar-horas-msg');
      if (btn) { btn.disabled = true; }
      if (msg) { msg.innerHTML = '<p>Comprobando tus horas…</p>'; }
      fetch(window.convMiArea.apiUrl + '/me/renovar-por-horas', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': window.convMiArea.nonce }
      })
        .then(r => r.json().then(d => ({ ok: r.ok, d: d })))
        .then(({ ok, d }) => {
          if (!ok || !d.success) { throw new Error(d.message || 'No se ha podido renovar.'); }
          if (msg) {
            msg.innerHTML = '<p class="text-success">' + this.escHtml(d.message)
              + (d.renovacion ? ' Nueva fecha: ' + this.formatDate(d.renovacion) + '.' : '') + '</p>';
          }
          window.setTimeout(() => window.location.reload(), 1500);
        })
        .catch(e => {
          if (msg) { msg.innerHTML = '<p class="text-error">' + this.escHtml(e.message) + '</p>'; }
          if (btn) { btn.disabled = false; }
        });
    },

    renewMembership: function(btn) {
      // Renovar con la cuota al día AMPLÍA el periodo (el servidor suma un año al vencimiento anterior):
      // se dice antes de crear el pago, para que nadie pague creyendo que le tocaba (members#38).
      if (btn && btn.dataset && btn.dataset.amplia && !window.confirm(btn.dataset.amplia)) {
        return;
      }

      const msg = conv.$('#conv-renovar-panel-msg');
      btn.disabled = true;
      msg.innerHTML = '<p class="text-muted">Generando pago…</p>';

      fetch(window.convMiArea.apiUrl + '/me/renovar', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': window.convMiArea.nonce }
      })
      .then(res => res.json())
      .then(d => {
        if (d.payment_url) {
          window.location.href = d.payment_url;
        } else {
          btn.disabled = false;
          msg.innerHTML = '<p class="text-error">' + this.escHtml(d.error || 'Error al renovar.') + '</p>';
        }
      })
      .catch(() => {
        btn.disabled = false;
        msg.innerHTML = '<p class="text-error">Error de conexión.</p>';
      });
    },

    /* ── El circuito del voluntariado (members#50) ────── */
    /**
     * Trae el bloque que dice en qué paso está la persona y le ofrece lo que le toca.
     *
     * Va aparte del resto de la pestaña a propósito: si este endpoint fallara, lo que ya había —las horas,
     * los proyectos— tiene que seguir viéndose.
     */
    cargarBloqueVoluntariado: function() {
      const self = this;
      fetch(window.convMiArea.apiUrl + '/me/voluntariado', { headers: { 'X-WP-Nonce': window.convMiArea.nonce } })
        .then(function(r) { return r.ok ? r.json() : { html: '' }; })
        .then(function(d) {
          if (!d || !d.html) return;
          self.pintarBloqueVoluntariado(d.html);
        })
        .catch(function() {});
    },

    /**
     * Pinta el bloque **fuera** del contenedor que repinta cada pestaña.
     *
     * Dentro no vale: la pestaña de Voluntariado reemplaza su propio contenido entero al cargar las horas, y
     * se llevaba por delante este bloque (medido: el bloque no aparecía nunca en el panel).
     *
     * @param {string} html El bloque tal cual lo manda el servidor.
     */
    pintarBloqueVoluntariado: function(html) {
      const previo = document.getElementById('conv-bloque-voluntariado');
      if (previo) { previo.outerHTML = html; return; }
      if (this.$main && this.$main.parentNode) {
        // El bloque se convierte en nodo con un contenedor de usar y tirar: el panel no tiene helper de HTML.
        const caja = document.createElement('div');
        caja.innerHTML = html;
        const nodo = caja.firstElementChild;
        if (nodo) { this.$main.parentNode.insertBefore(nodo, this.$main); }
      }
    },

    /**
     * Manda un paso del circuito y repinta el bloque con lo que responda el servidor.
     *
     * @param {HTMLFormElement} form El formulario del paso.
     */
    enviarVoluntariado: function(form) {
      const self = this;
      const datos = new FormData(form);
      datos.append('accion', form.dataset.convocaVoluntariado);

      fetch(window.convMiArea.apiUrl + '/me/voluntariado', {
        method: 'POST',
        headers: { 'X-WP-Nonce': window.convMiArea.nonce },
        body: datos
      })
        .then(function(r) { return r.ok ? r.json() : null; })
        .then(function(d) {
          if (!d) { alert('No se ha podido enviar. Inténtalo de nuevo.'); return; }
          if (d.html) { self.pintarBloqueVoluntariado(d.html); }
          if (d.message) { alert(d.message); }
        })
        .catch(function() { alert('No se ha podido enviar. Inténtalo de nuevo.'); });
    },

    fetchHours: function() {
      this.cargarBloqueVoluntariado();

      // Cada petición lleva su propio respaldo: antes, un solo endpoint que fallara o no existiera
      // tumbaba la pestaña entera con «Error cargando datos de horas» y el socio no veía nada más.
      const pedir = (url, conNonce, respaldo) => fetch(
        window.convMiArea.apiUrl + url,
        conNonce ? { headers: { 'X-WP-Nonce': window.convMiArea.nonce } } : undefined
      )
        .then(res => (res.ok ? res.json() : respaldo))
        .catch(() => respaldo);

      Promise.all([
        pedir('/me/horas', true, { items: [] }),
        pedir('/activities', true, []),
        pedir('/proyectos', false, []),
        pedir('/me/gamification', true, null),
        pedir('/me', true, null)
      ])
      .then(([data, activities, proyectos, gamification, profile]) => {
        const canSubmitHours = profile && profile.estado === 'activo';
        let itemsHtml = '<p>No tienes registros de horas.</p>';
        if (data && data.items && data.items.length > 0) {
          itemsHtml = `
            <table class="conv-table">
              <thead>
                <tr>
                  <th>Fecha</th>
                  <th>Proyecto</th>
                  <th>Tareas</th>
                  <th>Horas</th>
                  <th>Estado</th>
                </tr>
              </thead>
              <tbody>
                ${data.items.map(item => `
                  <tr>
                    <td>${item.fecha}</td>
                    <td>${item.proyecto || '—'}</td>
                    <td>${item.tareas ? (item.tareas.length > 40 ? item.tareas.substring(0,40) + '...' : item.tareas) : '—'}</td>
                    <td>${item.horas}h</td>
                    <td>${this.formatEstadoBadge(item.estado)}</td>
                  </tr>
                `).join('')}
              </tbody>
            </table>
          `;
        }

        let activityOptions = (activities || []).map(act => `<option value="${act.id}">${act.title}</option>`).join('');
        let proyectoOptions = (proyectos || []).map(p => `<option value="${p.id}">${p.title}</option>`).join('');

        // Build gamification section
        var gamifyHtml = '';
        if (gamification && gamification.level) {
          var lvl = gamification.level;
          var nextLvl = gamification.next_level;
          var allLevels = gamification.levels || [];
          var trackLabel = gamification.track_label || '';

          // Track label
          if (trackLabel) {
            gamifyHtml += '<div class="conv-track-label"><span>' + trackLabel + '</span></div>';
          }

          // Level badge
          gamifyHtml += '<div class="conv-level-badge" style="--conv-level-color: ' + lvl.color + '">';
          gamifyHtml += '<span class="conv-level-emoji">' + lvl.emoji + '</span>';
          gamifyHtml += '<div class="conv-level-info"><strong>' + lvl.name + '</strong>';
          gamifyHtml += '<small>' + (lvl.desc || 'Nivel ' + (lvl.index + 1) + ' de 5') + '</small></div>';
          gamifyHtml += '</div>';

          // Progress bar toward next level
          if (nextLvl) {
            gamifyHtml += '<div class="conv-progress-container">';
            gamifyHtml += '<div class="conv-progress-bar"><div class="conv-progress-fill" style="width: ' + nextLvl.progress_percent + '%; background: ' + nextLvl.color + ';"></div></div>';
            gamifyHtml += '<div class="conv-progress-label"><span>' + nextLvl.progress_percent + '%</span><span>Siguiente nivel: ' + nextLvl.name + ' (' + nextLvl.hours + 'h)</span><span>' + nextLvl.hours_to_go + 'h restantes</span></div>';
            gamifyHtml += '</div>';
          } else {
            gamifyHtml += '<div class="conv-progress-container">';
            gamifyHtml += '<div class="conv-progress-bar"><div class="conv-progress-fill" style="width: 100%; background: #7D0032;"></div></div>';
            gamifyHtml += '<div class="conv-progress-label completed">🏆 ¡Has alcanzado el nivel máximo!</div>';
            gamifyHtml += '</div>';
          }

          // Level ladder
          gamifyHtml += '<div class="conv-level-steps">';
          allLevels.forEach(function(l) {
            var cls = l.reached ? 'conv-level-step active' : 'conv-level-step locked';
            gamifyHtml += '<div class="' + cls + '" style="--step-color: ' + l.color + '">';
            gamifyHtml += '<span class="conv-step-emoji">' + l.emoji + '</span>';
            gamifyHtml += '<span class="conv-step-name">' + l.name + '</span>';
            gamifyHtml += '<span class="conv-step-hours">' + l.hours + 'h</span>';
            gamifyHtml += '</div>';
          });
          gamifyHtml += '</div>';
        }

        // members#55: a quien no es voluntario no se le ofrece el área de horas. «No es voluntario» es
        // no haber pasado el circuito (estado 'solicitud' es el de fábrica, también para quien no ha
        // pedido nada) y no tener ninguna hora. Si tiene horas o estuvo en el circuito, se le muestra:
        // son sus datos y su certificado.
        const estadosDeVoluntario = ['entrevista', 'activo', 'desvinculado'];
        const esVoluntario = estadosDeVoluntario.indexOf(data && data.voluntariado) !== -1
          || (data && data.items ? data.items.length : 0) > 0
          || (data && Number(data.total_horas) > 0);

        if (!esVoluntario) {
          this.$main.innerHTML = `
            <div class="conv-hours-layout">
              <div class="hours-header"><h2>⏳ Voluntariado</h2></div>
              <section class="card-inner" style="background:#fff7e6;border:1px solid #ffd591;">
                <p style="margin:0 0 8px;"><strong>Todavía no eres voluntario/a.</strong></p>
                <p class="text-muted" style="margin:0;">El voluntariado entra por la junta: pide el alta y te
                citarán para una entrevista. Cuando estés dentro, aquí podrás anotar tus horas y ver tu progreso
                hacia el certificado.</p>
                <p class="text-muted" style="margin:8px 0 0;">Para pedirlo, escribe a la asociación desde la
                pestaña de <strong>Mis Datos</strong> o coméntalo en el centro.</p>
              </section>
            </div>`;
          return;
        }

        let html = `
          <div class="conv-hours-layout">
            <div class="hours-header">
              <h2>⏳ Mis Horas de Voluntariado</h2>
              <div class="hours-summary">
                <span class="total-badge">${(data && data.total_horas ? data.total_horas : 0)}h <span>Totales Aprobadas</span></span>
              </div>
            </div>

            ${gamifyHtml}

            ${canSubmitHours ? `
            <section class="card-inner">
              <h3>Añadir Nuevo Registro</h3>
              <p class="text-muted" style="margin:0 0 10px;font-size:0.95em;">Anota el trabajo voluntario que no va con una actividad (carteles, contabilidad, arreglos…). <strong>Queda pendiente de que la junta lo apruebe</strong>: hasta entonces no cuenta en tu total ni en tu nivel.</p>
              <form id="conv-hours-form" class="conv-inline-form">
                <div class="form-group"><input type="date" name="fecha" required></div>
                <div class="form-group">
                  <select name="proyecto_id">
                    <option value="">Proyecto (opcional)</option>
                    ${proyectoOptions}
                  </select>
                </div>
                <div class="form-group">
                  <select name="actividad_id">
                    <option value="">Selecciona actividad (opcional)</option>
                    ${activityOptions}
                  </select>
                </div>
                <div class="form-group"><input type="number" name="horas" step="0.5" placeholder="Horas" required></div>
                <div class="form-group">
                  <textarea name="tareas" placeholder="Describe las tareas realizadas (obligatorio)" maxlength="500" rows="2" required></textarea>
                </div>
                <div class="form-group"><input type="text" name="descripcion" placeholder="Descripción breve (opcional)"></div>
                <button type="submit" class="btn-primary-mini">Añadir</button>
              </form>
            </section>` : `
            <section class="card-inner" style="background:#fff7e6;border:1px solid #ffd591;">
              <p class="text-muted" style="margin:0"><strong>Solo lectura:</strong> para registrar horas de voluntariado tu membresía debe estar activa.</p>
            </section>`}

            <section class="history-section">
              <h3>Historial</h3>
              ${itemsHtml}
            </section>
          </div>
        `;

        fetch(window.convMiArea.apiUrl + '/me/certificate', { headers: { 'X-WP-Nonce': window.convMiArea.nonce } })
        .then(res => res.json())
        .then(cert => {
          if (cert.success) {
            html += '<div class="conv-certificate-section" style="margin-top: 30px; text-align: center; padding: 20px; background: #d4edda; border-radius: 8px;">' +
              '<h3>🎉 ¡Felicidades! Has completado tu voluntariado</h3>' +
              '<p>Has completado ' + cert.horas + ' horas. Ya puedes descargar tu certificado.</p>' +
              '<a href="' + cert.download_url + '" class="btn btn-primary" target="_blank">📜 Descargar Certificado</a>' +
              '</div>';
          } else if (cert.progress) {
            // Con 0 h la API no manda `porcentaje` y salía «undefined / 15 horas (undefined%)».
            const total = Number(cert.progress.total) || 0;
            const objetivo = Number(cert.progress.objetivo) || 0;
            const pct = (cert.progress.porcentaje === undefined || cert.progress.porcentaje === null)
              ? (objetivo > 0 ? Math.min(100, Math.round((total / objetivo) * 100)) : 0)
              : cert.progress.porcentaje;
            html += '<div class="conv-certificate-section" style="margin-top: 30px; text-align: center; padding: 20px; background: #fff3cd; border-radius: 8px;">' +
              '<h3>📊 Progreso hacia el certificado</h3>' +
              '<div style="width: 100%; background: #e9ecef; height: 20px; border-radius: 10px; overflow: hidden; margin: 10px 0;">' +
              '<div style="width: ' + pct + '%; background: #28a745; height: 100%;"></div></div>' +
              '<p>' + total + ' / ' + objetivo + ' horas (' + pct + '%)</p>' +
              '<p><small>Completa ' + cert.progress.objetivo + ' horas para obtener tu certificado</small></p>' +
              '</div>';
          }
          this.$main.innerHTML = html;
        });
      })
      .catch(() => { this.$main.innerHTML = '<p>Error cargando datos de horas.</p>'; });
    },

    submitHours: function(form) {
      // `conv.form.validate()` NO existe: lanzaba un TypeError que se tragaba el manejador, así que el
      // botón «Añadir» no hacía nada —ni mensaje ni error— (members#57). Se usa la validación del
      // navegador, que es la que ya llevan los campos con `required`.
      if (form.checkValidity && !form.checkValidity()) {
          form.reportValidity();
          return;
      }

      const data = {
        fecha: conv.$('[name="fecha"]', form).value,
        proyecto_id: conv.$('[name="proyecto_id"]', form).value,
        actividad_id: conv.$('[name="actividad_id"]', form).value,
        horas: conv.$('[name="horas"]', form).value,
        tareas: conv.$('[name="tareas"]', form).value,
        descripcion: conv.$('[name="descripcion"]', form).value
      };

      const btn = form.querySelector('button[type="submit"]');
      conv.setLoading(btn, true, 'Añadiendo...');

      fetch(window.convMiArea.apiUrl + '/me/horas', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': window.convMiArea.nonce },
        body: JSON.stringify(data)
      })
      .then(res => res.json())
      .then(res => {
        if (res.success) {
          this.switchTab('hours');
        } else {
          alert('Error: ' + res.message);
          conv.setLoading(btn, false, 'Añadir');
        }
      })
      .catch(() => {
          alert('Error de conexión.');
          conv.setLoading(btn, false, 'Añadir');
      });
    },

    requestUnsubscribe: function() {
      fetch(window.convMiArea.apiUrl + '/me/unsubscribe', {
        method: 'POST',
        headers: { 'X-WP-Nonce': window.convMiArea.nonce }
      })
      .then(res => res.json())
      .then(res => {
        if (res.success) {
          alert('Solicitud enviada correctamente. La administración se pondrá en contacto contigo.');
          location.reload();
        } else {
          alert(res.message || 'Error en la solicitud.');
        }
      })
      .catch(() => alert('Error de conexión.'));
    },

    fetchNotifications: function() {
      var self = this;
      fetch(window.convMiArea.apiUrl + '/member/notifications', {
        headers: { 'X-WP-Nonce': window.convMiArea.nonce }
      })
      .then(res => res.json())
      .then(data => {
        var notifications = data.items || [];
        var unread = data.unread || 0;

        var html = '<h2>🔔 Notificaciones</h2>';

        if (notifications.length === 0) {
          html += '<div class="conv-notif-empty-state"><p>No tienes notificaciones pendientes.</p></div>';
          this.$main.innerHTML = html;
          return;
        }

        if (unread > 0) {
          html += '<div style="margin-bottom: 1rem;"><button class="conv-btn-mark-all-read" data-action="mark-all-read">✅ Marcar todas como leídas</button></div>';
        }

        html += '<div class="conv-member-notifications-list">';

        notifications.forEach(function(n) {
          var isUnread = !n.read;
          var iconMap = {
            'success': '✅',
            'warning': '⚠️',
            'error': '❌',
            'info': 'ℹ️'
          };
          var icon = iconMap[n.type] || 'ℹ️';
          var itemClass = 'conv-member-notif-item' + (isUnread ? ' conv-member-notif-unread' : '');

          html += '<div class="' + itemClass + '" data-id="' + n.id + '">';
          html += '<span class="conv-member-notif-icon">' + icon + '</span>';
          html += '<div class="conv-member-notif-body">';
          html += '<div class="conv-member-notif-title">' + this.escHtml(n.title) + '</div>';
          html += '<div class="conv-member-notif-time">' + this.humanTime(n.time) + '</div>';
          html += '</div>';
          if (isUnread) {
            html += '<button class="conv-member-notif-read-btn" data-notif-id="' + n.id + '" title="Marcar como leída">✓</button>';
          }
          html += '</div>';
        }.bind(this));

        html += '</div>';
        this.$main.innerHTML = html;
        this.updateNotifBadge(data.unread || 0);
      })
      .catch(function() {
        this.$main.innerHTML = '<p>Error cargando notificaciones.</p>';
      }.bind(this));
    },

    markNotificationRead: function(id) {
      var self = this;
      fetch(window.convMiArea.apiUrl + '/member/notifications/read', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': window.convMiArea.nonce },
        body: JSON.stringify({ id: id })
      })
      .then(function(res) { return res.json(); })
      .then(function() {
        self.fetchNotifications();
      });
    },

    markAllNotificationsRead: function() {
      var self = this;
      fetch(window.convMiArea.apiUrl + '/member/notifications/read-all', {
        method: 'POST',
        headers: { 'X-WP-Nonce': window.convMiArea.nonce }
      })
      .then(function(res) { return res.json(); })
      .then(function() {
        self.fetchNotifications();
      });
    },

    escHtml: function(text) {
      var div = document.createElement('div');
      div.appendChild(document.createTextNode(text));
      return div.innerHTML;
    },

    humanTime: function(mysqlDatetime) {
      if (!mysqlDatetime) return '';
      var date = new Date(mysqlDatetime.replace(' ', 'T') + 'Z');
      var now = new Date();
      var diff = Math.floor((now - date) / 1000);
      if (diff < 60) return 'Ahora';
      if (diff < 3600) return Math.floor(diff / 60) + ' min atrás';
      if (diff < 86400) return Math.floor(diff / 3600) + 'h atrás';
      return Math.floor(diff / 86400) + 'd atrás';
    },

    renderMembership: function() {
      const html = `
        <h2>🪪 Mi Carnet Digital</h2>
        <div class="digital-card-preview">
          <p>Tu carnet de socio/a digital:</p>
          <div class="placeholder-card card-glass">
            <h4 class="text-gradient">Convoca Membership</h4>
            <div class="card-chip"></div>
            <div class="card-details">
              <span class="card-label">Socio/a Numerario/a</span>
            </div>
          </div>
          <br>
          <button id="conv-btn-card" class="btn-primary">Ver / Descargar Carnet</button>
        </div>
      `;
      this.$main.innerHTML = html;

      const btnCard = conv.$('#conv-btn-card', this.$main);
      if (btnCard) {
          btnCard.addEventListener('click', () => {
              fetch(window.convMiArea.apiUrl + '/me/card', { headers: { 'X-WP-Nonce': window.convMiArea.nonce } })
              .then(res => res.json())
              .then(data => {
                  // El endpoint devuelve { html: '...' }, no el HTML suelto: escribir la respuesta tal cual
                  // abría una pestaña con el JSON en crudo en vez de con el carnet.
                  const win = window.open('', '_blank');
                  if (win) {
                      win.document.write(data.html || '<p>No se pudo cargar el carnet.</p>');
                      win.document.close();
                  }
              });
          });
      }
    },

    formatEstado: function(estado) {
      const map = {
        'activo': '<span class="text-success">Activo/a</span>',
        'suspendido': '<span class="text-warning">En periodo de gracia</span>',
        'pendiente_pago': '<span class="text-warning">Pendiente de Pago</span>',
        'pendiente_documentacion': '<span class="text-warning">Solo voluntario/a</span>',
        'baja': '<span class="text-error">Dado/a de baja</span>',
        'baja_solicitada': '<span class="text-error">Baja Solicitada</span>',
      };
      return map[estado] || estado;
    },

    formatEstadoBadge: function(estado) {
      const map = {
        'confirmada': 'badge-success',
        'pagada': 'badge-success',
        'paid': 'badge-success',
        'pendiente': 'badge-warning',
        'waitlist': 'badge-warning',
        'cancelled': 'badge-error',
        'failed': 'badge-error',
        'pendiente_pago': 'badge-warning',
        // El gateway guarda 'pending' y 'refunded': al panel le llegaban sin color ni traducción.
        'pending': 'badge-warning',
        'refunded': 'badge-error'
      };
      // El endpoint devuelve el estado interno en inglés ('paid', 'pending', 'failed') y se pintaba tal
      // cual. La etiqueta es para quien lee, así que se traduce aquí; un estado nuevo sin traducción se
      // enseña en crudo en vez de inventarse una palabra.
      const etiquetas = {
        'paid': 'Pagado',
        'pending': 'Pendiente',
        'failed': 'Fallido',
        'refunded': 'Reembolsado',
        'cancelled': 'Cancelado',
        'confirmed': 'Confirmado',
        'waitlist': 'Lista de espera',
        'confirmada': 'Confirmada',
        'pagada': 'Pagada',
        'pendiente': 'Pendiente',
        'pendiente_pago': 'Pendiente de pago'
      };
      const label = etiquetas[estado] || (estado ? estado.charAt(0).toUpperCase() + estado.slice(1) : '');
      return `<span class="badge ${map[estado] || 'badge-default'}">${label}</span>`;
    }
  };

  document.addEventListener('DOMContentLoaded', () => MiArea.init());

})(window.convoca || {});
