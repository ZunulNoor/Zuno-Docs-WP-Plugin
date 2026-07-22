(function () {
    'use strict';

    var CFG = window.ZUNO_DOCS_ADMIN || {};
    var THEME_COLOR = CFG.themeColor || '#2563EB';

    /* ===================================================================
     * ZunoDocsPopup — unified modal dialog system
     * Replaces all window.alert(), window.confirm(), window.prompt()
     * =================================================================== */
    var ZunoDocsPopup = {
        _overlay: null,
        _modal: null,
        _resolve: null,
        _active: false,

        icons: {
            success: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
            warning: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
            error: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
            question: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
            info: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>'
        },

        _create: function () {
            if (this._overlay) return;

            var overlay = document.createElement('div');
            overlay.className = 'zuno-docs-modal-overlay';
            overlay.style.cssText = 'position:fixed;inset:0;z-index:999999;background:rgba(0,0,0,0.4);display:flex;align-items:center;justify-content:center;opacity:0;visibility:hidden;transition:opacity 0.2s ease,visibility 0.2s ease;-webkit-backdrop-filter:blur(2px);backdrop-filter:blur(2px);';

            var modal = document.createElement('div');
            modal.className = 'zuno-docs-modal';
            modal.setAttribute('role', 'dialog');
            modal.setAttribute('aria-modal', 'true');
            modal.style.cssText = 'background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,0.15),0 8px 20px rgba(0,0,0,0.08);max-width:420px;width:calc(100% - 32px);max-height:85vh;overflow-y:auto;transform:scale(0.92) translateY(8px);transition:transform 0.25s cubic-bezier(0.34,1.56,0.64,1);padding:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif;';

            overlay.appendChild(modal);
            document.body.appendChild(overlay);

            this._overlay = overlay;
            this._modal = modal;

            var self = this;
            overlay.addEventListener('click', function (e) {
                if (e.target === overlay && self._type === 'alert') {
                    self.close(null);
                }
            });

            if (!this._keyHandler) {
                this._keyHandler = function (e) {
                    if (e.key === 'Escape' && self._active) {
                        e.preventDefault();
                        self.close(self._type === 'confirm' ? false : null);
                    }
                };
                document.addEventListener('keydown', this._keyHandler);
            }
        },

        _render: function (opts) {
            this._create();
            var m = this._modal;
            m.innerHTML = '';

            var type = opts.type || 'info';
            this._type = type;

            var iconHtml = this.icons[type] || this.icons.info;

            var iconColor = THEME_COLOR;
            if (type === 'error') iconColor = '#DC2626';
            else if (type === 'warning') iconColor = '#F59E0B';
            else if (type === 'success') iconColor = '#16A34A';
            else if (type === 'question') iconColor = THEME_COLOR;

            var header = document.createElement('div');
            header.style.cssText = 'display:flex;align-items:center;gap:12px;padding:20px 24px 0;';

            var iconWrap = document.createElement('div');
            iconWrap.style.cssText = 'flex-shrink:0;width:32px;height:32px;display:flex;align-items:center;justify-content:center;color:' + iconColor + ';';
            iconWrap.innerHTML = iconHtml;
            header.appendChild(iconWrap);

            var title = document.createElement('div');
            title.style.cssText = 'font-size:16px;font-weight:600;color:#1d2327;line-height:1.4;';
            title.textContent = opts.title || '';
            if (opts.title) header.appendChild(title);

            m.appendChild(header);

            var body = document.createElement('div');
            body.style.cssText = 'padding:12px 24px 20px;font-size:14px;color:#50575e;line-height:1.5;';
            body.textContent = opts.message || '';
            m.appendChild(body);

            var footer = document.createElement('div');
            footer.style.cssText = 'display:flex;justify-content:flex-end;gap:8px;padding:16px 24px;border-top:1px solid #f0f0f1;';

            var confirmText = opts.confirmText || (type === 'confirm' ? 'Delete' : (type === 'alert' ? 'OK' : 'Confirm'));
            var cancelText = opts.cancelText || 'Cancel';
            var showCancel = type === 'confirm';

            if (showCancel) {
                var cancelBtn = document.createElement('button');
                cancelBtn.textContent = cancelText;
                cancelBtn.className = 'button';
                cancelBtn.style.cssText = 'min-height:36px;padding:6px 16px;border-radius:8px;';
                var self = this;
                cancelBtn.addEventListener('click', function () { self.close(false); });
                footer.appendChild(cancelBtn);
            }

            var confirmBtn = document.createElement('button');
            confirmBtn.textContent = confirmText;
            confirmBtn.className = 'button button-primary';
            var isDestructive = type === 'confirm' || type === 'warning' || type === 'error';
            confirmBtn.style.cssText = 'min-height:36px;padding:6px 20px;border-radius:8px;border:none;background:' + (isDestructive ? '#DC2626' : THEME_COLOR) + ';color:#fff;font-weight:500;cursor:pointer;transition:opacity 0.15s;';
            confirmBtn.addEventListener('mouseenter', function () { confirmBtn.style.opacity = '0.85'; });
            confirmBtn.addEventListener('mouseleave', function () { confirmBtn.style.opacity = '1'; });

            var self = this;
            confirmBtn.addEventListener('click', function () {
                if (opts.onConfirm) opts.onConfirm();
                self.close(type === 'confirm' ? true : true);
            });

            footer.appendChild(confirmBtn);
            m.appendChild(footer);
        },

        show: function (opts) {
            var self = this;
            return new Promise(function (resolve) {
                self._resolve = resolve;
                self._render(opts);
                self._active = true;

                requestAnimationFrame(function () {
                    self._overlay.style.opacity = '1';
                    self._overlay.style.visibility = 'visible';
                    self._modal.style.transform = 'scale(1) translateY(0)';
                });

                var confirmBtn = self._modal.querySelector('.button-primary');
                if (confirmBtn) setTimeout(function () { confirmBtn.focus(); }, 100);
            });
        },

        alert: function (message, opts) {
            opts = opts || {};
            return this.show({
                type: opts.type || 'info',
                title: opts.title || 'ZUNO Docs',
                message: message,
                confirmText: opts.confirmText || 'OK'
            });
        },

        confirm: function (message, opts) {
            opts = opts || {};
            return this.show({
                type: 'question',
                title: opts.title || 'ZUNO Docs',
                message: message,
                confirmText: opts.confirmText || 'Delete',
                cancelText: opts.cancelText || 'Cancel'
            });
        },

        close: function (value) {
            if (!this._active) return;
            this._active = false;

            var self = this;
            this._overlay.style.opacity = '0';
            this._overlay.style.visibility = 'hidden';
            this._modal.style.transform = 'scale(0.92) translateY(8px)';

            setTimeout(function () {
                if (self._resolve) {
                    self._resolve(value);
                    self._resolve = null;
                }
                if (self._overlay && self._overlay.parentNode) {
                    self._overlay.parentNode.removeChild(self._overlay);
                }
                self._overlay = null;
                self._modal = null;
                if (self._keyHandler) {
                    document.removeEventListener('keydown', self._keyHandler);
                    self._keyHandler = null;
                }
            }, 200);
        }
    };

    /* ===================================================================
     * Delete confirmation handler — replaces inline confirm()
     * =================================================================== */
    function initDeleteConfirmations() {
        document.addEventListener('click', function (e) {
            var delBtn = e.target.closest('.zuno-docs-delete-cat, .zuno-docs-delete-doc');
            if (!delBtn) return;

            var msg = delBtn.getAttribute('data-confirm') || 'Are you sure?';
            e.preventDefault();
            var href = delBtn.getAttribute('href');

            ZunoDocsPopup.confirm(msg, {
                title: 'Confirm Delete',
                confirmText: 'Delete'
            }).then(function (confirmed) {
                if (confirmed && href) {
                    window.location.href = href;
                }
            });
        });
    }

    /* ===================================================================
     * Deactivation flow — branded modal on plugins page
     * =================================================================== */
    function initDeactivationFlow() {
        var deactivateLink = document.querySelector('a[href*="action=deactivate"][href*="zuno-docs-engine"]');
        if (!deactivateLink) return;

        deactivateLink.addEventListener('click', function (e) {
            e.preventDefault();
            var href = deactivateLink.getAttribute('href');

            showDeactivationModal(href);
        });
    }

    function showDeactivationModal(deactivateHref) {
        var overlay = document.createElement('div');
        overlay.className = 'zuno-docs-modal-overlay';
        overlay.style.cssText = 'position:fixed;inset:0;z-index:999999;background:rgba(0,0,0,0.4);display:flex;align-items:center;justify-content:center;opacity:0;visibility:hidden;transition:opacity 0.2s ease,visibility 0.2s ease;-webkit-backdrop-filter:blur(2px);backdrop-filter:blur(2px);';

        var modal = document.createElement('div');
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.style.cssText = 'background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,0.15),0 8px 20px rgba(0,0,0,0.08);max-width:440px;width:calc(100% - 32px);max-height:85vh;overflow-y:auto;transform:scale(0.92) translateY(8px);transition:transform 0.25s cubic-bezier(0.34,1.56,0.64,1);padding:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif;';

        modal.innerHTML =
            '<div style="padding:24px 24px 0;text-align:center;">' +
            '<div style="width:48px;height:48px;margin:0 auto 12px;background:#FEF3C7;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#F59E0B;">' +
            '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>' +
            '</div>' +
            '<h2 style="margin:0 0 4px;font-size:18px;font-weight:700;color:#1d2327;">' + (CFG.i18n.deactivationTitle || 'Leaving ZUNO Docs?') + '</h2>' +
            '<p style="margin:0 0 20px;font-size:14px;color:#50575e;line-height:1.5;">' + (CFG.i18n.deactivationDesc || 'Would you like to keep your documentation and settings for future use?') + '</p>' +
            '</div>' +
            '<div style="padding:0 24px 20px;">' +
            '<label style="display:flex;align-items:flex-start;gap:10px;padding:12px 14px;background:#f6f7f7;border-radius:10px;cursor:pointer;margin-bottom:8px;border:2px solid ' + THEME_COLOR + ';" id="zuno-docs-deactivate-keep">' +
            '<input type="radio" name="zuno_docs_deactivate_action" value="keep" checked style="margin-top:3px;accent-color:' + THEME_COLOR + ';">' +
            '<div><strong style="display:block;font-size:14px;color:#1d2327;">' + (CFG.i18n.keepData || 'Keep my documentation and settings') + '</strong>' +
            '<span style="font-size:13px;color:#646970;">' + (CFG.i18n.keepDataDesc || 'Database will remain intact for future use.') + '</span></div>' +
            '</label>' +
            '<label style="display:flex;align-items:flex-start;gap:10px;padding:12px 14px;background:#f6f7f7;border-radius:10px;cursor:pointer;border:2px solid transparent;" id="zuno-docs-deactivate-remove">' +
            '<input type="radio" name="zuno_docs_deactivate_action" value="remove" style="margin-top:3px;accent-color:' + THEME_COLOR + ';">' +
            '<div><strong style="display:block;font-size:14px;color:#1d2327;">' + (CFG.i18n.removeData || 'Remove all plugin data') + '</strong>' +
            '<span style="font-size:13px;color:#646970;">' + (CFG.i18n.removeDataDesc || 'All documentation, categories, and settings will be deleted on uninstall.') + '</span></div>' +
            '</label>' +
            '</div>' +
            '<div style="display:flex;justify-content:flex-end;gap:8px;padding:16px 24px;border-top:1px solid #f0f0f1;">' +
            '<button class="button" id="zuno-docs-deactivate-cancel" style="min-height:36px;padding:6px 16px;border-radius:8px;">' + (CFG.i18n.cancel || 'Cancel') + '</button>' +
            '<button class="button button-primary" id="zuno-docs-deactivate-confirm" style="min-height:36px;padding:6px 20px;border-radius:8px;border:none;background:' + THEME_COLOR + ';color:#fff;font-weight:500;cursor:pointer;">' + (CFG.i18n.deactivate || 'Deactivate Plugin') + '</button>' +
            '</div>';

        overlay.appendChild(modal);
        document.body.appendChild(overlay);

        requestAnimationFrame(function () {
            overlay.style.opacity = '1';
            overlay.style.visibility = 'visible';
            modal.style.transform = 'scale(1) translateY(0)';
            document.getElementById('zuno-docs-deactivate-confirm').focus();
        });

        function closeModal() {
            overlay.style.opacity = '0';
            overlay.style.visibility = 'hidden';
            modal.style.transform = 'scale(0.92) translateY(8px)';
            document.addEventListener('keydown', function escHandler(e) {
                if (e.key === 'Escape') {
                    closeModal();
                    document.removeEventListener('keydown', escHandler);
                }
            });
            setTimeout(function () {
                if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
            }, 200);
        }

        document.getElementById('zuno-docs-deactivate-cancel').addEventListener('click', closeModal);

        document.getElementById('zuno-docs-deactivate-confirm').addEventListener('click', function () {
            var selected = document.querySelector('input[name="zuno_docs_deactivate_action"]:checked');
            var action = selected ? selected.value : 'keep';

            var xhr = new XMLHttpRequest();
            xhr.open('POST', CFG.ajaxUrl || ajaxurl, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function () {
                closeModal();
                window.location.href = deactivateHref;
            };
            xhr.onerror = function () {
                closeModal();
                window.location.href = deactivateHref;
            };
            xhr.send('action=zuno_docs_set_deactivation_pref&deactivate_action=' + encodeURIComponent(action) + '&_wpnonce=' + encodeURIComponent(CFG.deactivationNonce || ''));
        });

        document.getElementById('zuno-docs-deactivate-keep').addEventListener('click', function () {
            document.querySelector('#zuno-docs-deactivate-keep input').checked = true;
            this.style.borderColor = THEME_COLOR;
            document.getElementById('zuno-docs-deactivate-remove').style.borderColor = 'transparent';
        });

        document.getElementById('zuno-docs-deactivate-remove').addEventListener('click', function () {
            document.querySelector('#zuno-docs-deactivate-remove input').checked = true;
            this.style.borderColor = THEME_COLOR;
            document.getElementById('zuno-docs-deactivate-keep').style.borderColor = 'transparent';
        });

        document.addEventListener('keydown', function escHandler(e) {
            if (e.key === 'Escape') {
                closeModal();
                document.removeEventListener('keydown', escHandler);
            }
        });
    }

    /* ===================================================================
     * Init
     * =================================================================== */
    function boot() {
        initDeleteConfirmations();

        if (document.body.classList.contains('plugins-php')) {
            initDeactivationFlow();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
