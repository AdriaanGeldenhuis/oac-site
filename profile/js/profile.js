/**
 * A member's profile — change the office, delete the account, export the
 * calendar. The buttons are only on the page for those allowed; the APIs
 * check every right again. Writes go through OACUI.post() (admin/js/ui.js),
 * which adds the session's CSRF token; the words come from
 * window.OAC_PROFILE (profile/index.php), in the member's language.
 */
(function () {
  'use strict';

  const CFG = window.OAC_PROFILE || {};
  const T = CFG.t || {};
  if (!window.OACUI) return;

  // ===== A broken photo becomes the initials =====
  document.querySelectorAll('img[data-initials]').forEach(function (img) {
    img.addEventListener('error', function () {
      const span = document.createElement('span');
      span.className = img.className + ' prf-avatar--initials';
      span.setAttribute('aria-hidden', 'true');
      span.textContent = img.getAttribute('data-initials') || '';
      img.replaceWith(span);
    });
  });

  // ===== Export: the file downloads in place, so say something =====
  document.querySelectorAll('[data-export]').forEach(function (a) {
    a.addEventListener('click', function () { OACUI.toast(T.exporting || '', 'info'); });
  });

  // ===== Change the office =====
  const dialog = document.getElementById('officeDialog');
  const form = document.getElementById('officeForm');
  const openBtn = document.querySelector('[data-open-office]');

  if (dialog && form && openBtn) {
    const select = form.querySelector('select');
    const saveBtn = form.querySelector('button[type="submit"]');
    const start = select.value;

    openBtn.addEventListener('click', function () {
      select.value = start;
      if (typeof dialog.showModal === 'function') dialog.showModal();
      else dialog.setAttribute('open', '');
      select.focus();
    });

    function close() {
      if (typeof dialog.close === 'function') dialog.close();
      else dialog.removeAttribute('open');
      openBtn.focus();
    }

    dialog.addEventListener('click', function (e) {
      // The close buttons, or a tap on the dimmed backdrop around the card
      if (e.target.closest('[data-close-office]') || e.target === dialog) close();
    });

    form.addEventListener('submit', async function (e) {
      e.preventDefault();
      if (select.value === start) { close(); return; }
      saveBtn.disabled = true;
      saveBtn.classList.add('is-busy');
      const data = await OACUI.post('/profile/api/update_user.php', new FormData(form));
      saveBtn.disabled = false;
      saveBtn.classList.remove('is-busy');
      if (data.success) {
        close();
        OACUI.toast(T.saved || '', 'success');
        setTimeout(function () { location.reload(); }, 900);
      } else {
        OACUI.toast(data.error || '', 'error');
      }
    });
  }

  // ===== Delete the account =====
  const delBtn = document.querySelector('[data-delete-member]');
  if (delBtn) {
    delBtn.addEventListener('click', async function () {
      const sure = await OACUI.confirm((T.deleteQ || '') + ' ' + (T.cannotUndo || ''), { danger: true, okText: T.delete });
      if (!sure) return;
      delBtn.disabled = true;
      delBtn.classList.add('is-busy');
      const data = await OACUI.post('/profile/api/delete_user.php', { user_id: CFG.userId });
      if (data.success) {
        OACUI.toast(T.deleted || '', 'success');
        setTimeout(function () { window.location.href = '/admin/index.php?tab=afsprake'; }, 1200);
      } else {
        delBtn.disabled = false;
        delBtn.classList.remove('is-busy');
        OACUI.toast(data.error || '', 'error');
      }
    });
  }
})();
