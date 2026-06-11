/**
 * OAC Admin UI - shared toasts & confirm dialogs (replaces alert/confirm)
 * Styles live in /admin/css/ui.css
 */
(function() {
  'use strict';

  const STR = Object.assign({
    ok: 'Bevestig',
    cancel: 'Kanselleer'
  }, window.OAC_UI_STRINGS || {});

  function toastWrap() {
    let wrap = document.querySelector('.oac-toast-wrap');
    if (!wrap) {
      wrap = document.createElement('div');
      wrap.className = 'oac-toast-wrap';
      wrap.setAttribute('aria-live', 'polite');
      document.body.appendChild(wrap);
    }
    return wrap;
  }

  const ICONS = {
    success: '<svg viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    error: '<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/><path d="M12 8v4.5M12 16h.01" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>',
    info: '<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/><path d="M12 11v5M12 8h.01" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>'
  };

  function toast(message, type, duration) {
    type = type || 'info';
    duration = duration || 3800;

    const el = document.createElement('div');
    el.className = 'oac-toast oac-toast--' + type;
    el.setAttribute('role', 'status');
    el.innerHTML =
      '<span class="oac-toast-icon">' + (ICONS[type] || ICONS.info) + '</span>' +
      '<span class="oac-toast-msg"></span>';
    el.querySelector('.oac-toast-msg').textContent = message;

    toastWrap().appendChild(el);

    // trigger enter animation
    requestAnimationFrame(() => el.classList.add('show'));

    const hide = () => {
      el.classList.remove('show');
      el.addEventListener('transitionend', () => el.remove(), { once: true });
      setTimeout(() => el.remove(), 600); // safety net
    };

    const timer = setTimeout(hide, duration);
    el.addEventListener('click', () => {
      clearTimeout(timer);
      hide();
    });
  }

  function confirmDialog(message, opts) {
    opts = opts || {};
    return new Promise(resolve => {
      const overlay = document.createElement('div');
      overlay.className = 'oac-dialog-overlay';
      overlay.innerHTML =
        '<div class="oac-dialog" role="alertdialog" aria-modal="true">' +
          '<p class="oac-dialog-msg"></p>' +
          '<div class="oac-dialog-actions">' +
            '<button type="button" class="oac-dialog-btn oac-dialog-cancel"></button>' +
            '<button type="button" class="oac-dialog-btn oac-dialog-ok"></button>' +
          '</div>' +
        '</div>';

      overlay.querySelector('.oac-dialog-msg').textContent = message;
      const okBtn = overlay.querySelector('.oac-dialog-ok');
      const cancelBtn = overlay.querySelector('.oac-dialog-cancel');
      okBtn.textContent = opts.okText || STR.ok;
      cancelBtn.textContent = opts.cancelText || STR.cancel;
      if (opts.danger) okBtn.classList.add('oac-dialog-ok--danger');

      function close(result) {
        overlay.classList.remove('show');
        document.body.style.overflow = '';
        setTimeout(() => overlay.remove(), 250);
        resolve(result);
      }

      okBtn.addEventListener('click', () => close(true));
      cancelBtn.addEventListener('click', () => close(false));
      overlay.addEventListener('click', e => {
        if (e.target === overlay) close(false);
      });
      document.addEventListener('keydown', function esc(e) {
        if (e.key === 'Escape') {
          document.removeEventListener('keydown', esc);
          close(false);
        }
      });

      document.body.appendChild(overlay);
      document.body.style.overflow = 'hidden';
      requestAnimationFrame(() => overlay.classList.add('show'));
      cancelBtn.focus();
    });
  }

  window.OACUI = { toast: toast, confirm: confirmDialog };
})();
