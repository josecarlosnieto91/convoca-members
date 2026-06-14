/**
 * Convoca Members — Admin JS
 * Quick state change + CSV export modal + WhatsApp tracking + autocomplete.
 */
(function (bdvAdmin) {
    'use strict';

    if (!bdvAdmin) return;

    /* ── Quick state change ──────────────────────── */
    const stateForm = document.getElementById('conv-state-form');
    if (stateForm) {
        stateForm.addEventListener('submit', function (e) {
            e.preventDefault();

            const submitBtn = stateForm.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Guardando...';
            }

            const fd = new FormData(stateForm);
            const nonce = window.convAdmin?.nonce || '';

            bdvAdmin.ajaxPost('conv_change_state', fd, nonce,
                (res) => { location.reload(); },
                (res) => {
                    alert(res.data?.message || res.data || 'Error al cambiar el estado.');
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Cambiar estado';
                    }
                }
            );
        });
    }

    /* ── CSV Export Modal ─────────────────────────── */
    (function () {
        var modalOverlay = document.getElementById('conv-csv-modal');
        var exportBtn = document.getElementById('conv-csv-export-btn');
        var closeBtn = modalOverlay ? modalOverlay.querySelector('.conv-modal-close') : null;

        var exportSelectedBtn = document.getElementById('conv-csv-export-selected');
        var exportAllBtn = document.getElementById('conv-csv-export-all');
        var restoreDefaultBtn = document.getElementById('conv-csv-restore-default');

        if (!modalOverlay || !exportBtn) return;

        /* ── Open / Close ──────────────────────── */

        function openModal() {
            modalOverlay.style.display = 'flex';
            document.body.classList.add('conv-modal-open');
            // Focus first checkbox
            var firstCheckbox = modalOverlay.querySelector('input[type="checkbox"]');
            if (firstCheckbox) firstCheckbox.focus();
        }

        function closeModal() {
            modalOverlay.style.display = 'none';
            document.body.classList.remove('conv-modal-open');
            exportBtn.focus();
        }

        exportBtn.addEventListener('click', openModal);

        if (closeBtn) {
            closeBtn.addEventListener('click', closeModal);
        }

        // Click outside to close
        modalOverlay.addEventListener('click', function (e) {
            if (e.target === modalOverlay) closeModal();
        });

        // Escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modalOverlay.style.display === 'flex') {
                closeModal();
                e.preventDefault();
            }
        });

        /* ── Focus trap (basic) ────────────────── */

        function trapFocus(e) {
            if (modalOverlay.style.display !== 'flex') return;
            var focusable = modalOverlay.querySelectorAll(
                'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
            );
            if (focusable.length === 0) return;
            var first = focusable[0];
            var last = focusable[focusable.length - 1];

            if (e.key === 'Tab') {
                if (e.shiftKey && document.activeElement === first) {
                    e.preventDefault();
                    last.focus();
                } else if (!e.shiftKey && document.activeElement === last) {
                    e.preventDefault();
                    first.focus();
                }
            }
        }

        document.addEventListener('keydown', trapFocus);

        /* ── Build columns URL ─────────────────── */

        function getSelectedColumns() {
            var checkboxes = modalOverlay.querySelectorAll('input[name="csv_col[]"]:checked');
            return Array.from(checkboxes).map(function (cb) { return cb.value; });
        }

        function downloadCsv(columns) {
            var baseUrl = bdvAdmin.columnsUrl || (bdvAdmin.ajaxUrl + '?action=conv_export_csv&nonce=' + bdvAdmin.csvNonce);
            if (columns && columns.length > 0) {
                baseUrl += '&columns=' + encodeURIComponent(columns.join(','));
            }
            window.location.href = baseUrl;
        }

        /* ── Save preference via AJAX ──────────── */

        function saveColumnPreference(columns, onDone) {
            var fd = new FormData();
            fd.append('columns', JSON.stringify(columns));

            var xhr = new XMLHttpRequest();
            xhr.open('POST', bdvAdmin.ajaxUrl + '?action=conv_save_csv_columns&nonce=' + bdvAdmin.csvNonce, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');

            xhr.onload = function () {
                if (onDone) onDone();
            };
            xhr.onerror = function () {
                if (onDone) onDone();
            };

            // Convert FormData to URL-encoded string
            var params = new URLSearchParams();
            for (var pair of fd.entries()) {
                params.append(pair[0], pair[1]);
            }
            xhr.send(params.toString());
        }

        /* ── Button handlers ───────────────────── */

        if (exportSelectedBtn) {
            exportSelectedBtn.addEventListener('click', function () {
                var selected = getSelectedColumns();
                if (selected.length === 0) {
                    alert('Selecciona al menos una columna.');
                    return;
                }
                saveColumnPreference(selected);
                downloadCsv(selected);
                closeModal();
            });
        }

        if (exportAllBtn) {
            exportAllBtn.addEventListener('click', function () {
                var allCols = Object.keys(bdvAdmin.allColumns || {});
                saveColumnPreference(allCols);
                downloadCsv(null); // Export all columns
                closeModal();
            });
        }

        if (restoreDefaultBtn) {
            restoreDefaultBtn.addEventListener('click', function () {
                var defaults = bdvAdmin.defaultColumns || [];
                saveColumnPreference(defaults, function () {
                    // Reset checkboxes to defaults
                    var checkboxes = modalOverlay.querySelectorAll('input[name="csv_col[]"]');
                    var defaultSet = new Set(defaults);
                    checkboxes.forEach(function (cb) {
                        cb.checked = defaultSet.has(cb.value);
                    });
                });
            });
        }
    })();

    /* ── WhatsApp Click Tracking ─────────────────── */
    document.addEventListener('click', function(e) {
        const waLink = e.target.closest('a[href*="wa.me"]');
        if (waLink) {
            const row = waLink.closest('tr');
            if (row) {
                const cb = row.querySelector('input[name="miembros[]"]');
                const postId = cb ? cb.value : null;
                if (postId) {
                    // Silent ping to log the contact
                    const fd = new FormData();
                    fd.append('action', 'conv_log_whatsapp');
                    fd.append('post_id', postId);
                    fd.append('nonce', bdvAdmin.nonce);
                    
                    fetch(ajaxurl, {
                        method: 'POST',
                        body: fd
                    });
                }
            }
        }
    });

    /* ── Autocomplete search ──────────────────────── */
    var searchInput = document.querySelector('.wp-list-table + .tablenav .search-box input[name="s"], .wp-list-table + div .search-box input[name="s"]');
    if (searchInput) {
        var searchBox = searchInput.closest('.search-box') || searchInput.parentElement;
        var dropdown = document.createElement('div');
        dropdown.className = 'conv-autocomplete-results';
        dropdown.style.cssText = 'position:absolute;background:#fff;border:1px solid #ccc;border-radius:4px;max-height:250px;overflow-y:auto;width:100%;z-index:999;display:none;box-shadow:0 4px 12px rgba(0,0,0,0.1);';
        searchBox.style.position = 'relative';
        searchBox.appendChild(dropdown);

        var timer;
        searchInput.addEventListener('input', function () {
            clearTimeout(timer);
            var term = this.value.trim();
            if (term.length < 2) { dropdown.style.display = 'none'; return; }
            timer = setTimeout(function () {
                fetch('/wp-json/convoca/v1/admin/members/search?term=' + encodeURIComponent(term))
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (!data || !data.length) { dropdown.style.display = 'none'; return; }
                        dropdown.innerHTML = '';
                        data.forEach(function (item) {
                            var el = document.createElement('div');
                            el.style.cssText = 'padding:8px 12px;cursor:pointer;border-bottom:1px solid #f0f0f1;';
                            el.innerHTML = '<strong>' + item.title + '</strong> <span style="color:#999;font-size:12px;">' + (item.email || '') + '</span>';
                            el.addEventListener('click', function () {
                                window.location = 'admin.php?page=conv-members&member_id=' + item.id;
                            });
                            el.addEventListener('mouseenter', function () { el.style.background = '#f0f7ff'; });
                            el.addEventListener('mouseleave', function () { el.style.background = ''; });
                            dropdown.appendChild(el);
                        });
                        dropdown.style.display = 'block';
                    })
                    .catch(function () { dropdown.style.display = 'none'; });
            }, 300);
        });

        document.addEventListener('click', function (e) {
            if (!dropdown.contains(e.target) && e.target !== searchInput) {
                dropdown.style.display = 'none';
            }
        });
    }

})(window.convocaAdmin);
