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
        }
      });

      this.$container.addEventListener('click', function(e) {
        if (e.target.closest('#conv-logout-btn')) {
            e.preventDefault();
            self.handleLogout();
        } else if (e.target.closest('#conv-btn-unsubscribe')) {
            e.preventDefault();
            if (confirm('¿Estás seguro de que quieres solicitar la baja del club de socios?')) {
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
        case 'hours': this.fetchHours(); break;
        case 'membership': this.renderMembership(); break;
        case 'search': this.renderSearch(); break;
        case 'notifications': this.fetchNotifications(); break;
      }
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
        const html = `
          <h2>👤 Mis Datos</h2>
          <div class="conv-meta-grid">
            <div class="meta-item"><strong>Nombre:</strong> <span>${profile.nombre}</span></div>
            <div class="meta-item"><strong>Email:</strong> <span>${profile.email}</span></div>
            <div class="meta-item"><strong>Código de Acceso:</strong> <span>${profile.codigo}</span></div>
            <div class="meta-item"><strong>Estado:</strong> ${this.formatEstado(profile.estado)}</div>
          </div>
          <hr>
          <h3>Zona de Peligro</h3>
          <p class="text-muted">Si deseas solicitar tu baja como socio/a, puedes hacerlo pulsando el siguiente botón. Se notificará a la administración para procesarla.</p>
          <button id="conv-btn-unsubscribe" class="btn-danger-outline">Solicitar Baja</button>
        `;
        this.$main.innerHTML = html;
      })
      .catch(() => { this.$main.innerHTML = '<p>Error cargando perfil.</p>'; });
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

    fetchPayments: function() {
      fetch(window.convMiArea.apiUrl + '/me/pagos', { headers: { 'X-WP-Nonce': window.convMiArea.nonce } })
      .then(res => res.json())
      .then(data => {
        if (!data.items || data.items.length === 0) {
          this.$main.innerHTML = '<h2>💳 Pagos y Cuotas</h2><p>No se han encontrado pagos registrados.</p>';
          return;
        }

        let html = `
          <h2>💳 Mis Pagos</h2>
          <table class="conv-table">
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Concepto</th>
                <th>Importe</th>
                <th>Estado</th>
              </tr>
            </thead>
            <tbody>
              ${data.items.map(item => `
                <tr>
                  <td>${item.fecha}</td>
                  <td>${item.concepto}</td>
                  <td>${item.importe}€</td>
                  <td>${this.formatEstadoBadge(item.estado)}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        `;
        this.$main.innerHTML = html;
      })
      .catch(() => { this.$main.innerHTML = '<p>Error cargando pagos.</p>'; });
    },

    fetchHours: function() {
      Promise.all([
        fetch(window.convMiArea.apiUrl + '/me/horas', { headers: { 'X-WP-Nonce': window.convMiArea.nonce } }).then(res => res.json()),
        fetch(window.convMiArea.apiUrl + '/activities', { headers: { 'X-WP-Nonce': window.convMiArea.nonce } }).then(res => res.json()),
        fetch(window.convMiArea.apiUrl + '/proyectos').then(res => res.json()),
        fetch(window.convMiArea.apiUrl + '/me/gamification', { headers: { 'X-WP-Nonce': window.convMiArea.nonce } }).then(res => res.json().catch(function(){return null;}))
      ])
      .then(([data, activities, proyectos, gamification]) => {
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

        let html = `
          <div class="conv-hours-layout">
            <div class="hours-header">
              <h2>⏳ Mis Horas de Voluntariado</h2>
              <div class="hours-summary">
                <span class="total-badge">${(data && data.total_horas ? data.total_horas : 0)}h <span>Totales Aprobadas</span></span>
              </div>
            </div>

            ${gamifyHtml}
            
            <section class="card-inner">
              <h3>Añadir Nuevo Registro</h3>
              <form id="conv-hours-form" class="conv-inline-form">
                <div class="form-group"><input type="date" name="fecha" required></div>
                <div class="form-group">
                  <select name="proyecto_id" required>
                    <option value="">Selecciona proyecto *</option>
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
            </section>

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
            const pct = cert.progress.porcentaje;
            html += '<div class="conv-certificate-section" style="margin-top: 30px; text-align: center; padding: 20px; background: #fff3cd; border-radius: 8px;">' +
              '<h3>📊 Progreso hacia el certificado</h3>' +
              '<div style="width: 100%; background: #e9ecef; height: 20px; border-radius: 10px; overflow: hidden; margin: 10px 0;">' +
              '<div style="width: ' + pct + '%; background: #28a745; height: 100%;"></div></div>' +
              '<p>' + cert.progress.total + ' / ' + cert.progress.objetivo + ' horas (' + pct + '%)</p>' +
              '<p><small>Completa ' + cert.progress.objetivo + ' horas para obtener tu certificado</small></p>' +
              '</div>';
          }
          this.$main.innerHTML = html;
        });
      })
      .catch(() => { this.$main.innerHTML = '<p>Error cargando datos de horas.</p>'; });
    },

    submitHours: function(form) {
      if (!conv.form.validate(form)) {
          alert('Revisa los campos obligatorios.');
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
              .then(res => res.text())
              .then(html => {
                  const win = window.open('', '_blank');
                  if (win) {
                      win.document.write(html);
                      win.document.close();
                  }
              });
          });
      }
    },

    formatEstado: function(estado) {
      const map = {
        'activo': '<span class="text-success">Activo/a</span>',
        'pendiente_pago': '<span class="text-warning">Pendiente de Pago</span>',
        'pendiente_documentacion': '<span class="text-warning">Pendiente de Documentación</span>',
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
        'pendiente_pago': 'badge-warning'
      };
      const label = estado.charAt(0).toUpperCase() + estado.slice(1);
      return `<span class="badge ${map[estado] || 'badge-default'}">${label}</span>`;
    }
  };

  document.addEventListener('DOMContentLoaded', () => MiArea.init());

})(window.convoca || {});
