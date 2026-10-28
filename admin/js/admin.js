/**
 * Admin dashboard — the buttons on the Approvals tab and the password form
 * on the Settings tab. Tabs are server-side (?tab=…). Every write is a POST
 * through OACUI.post() (ui.js), which adds the session's CSRF token.
 * The words come from window.OAC_ADMIN (index.php), in the member's language.
 */
(function () {
  'use strict';

  const root = document.querySelector('.admin-body');
  if (!root || !window.OACUI) return;

  const T = window.OAC_ADMIN || {};

  // ===== Approvals =====

  function decrementBadge() {
    const badge = document.querySelector('.admin-tab-badge');
    if (!badge) return;
    const current = parseInt(badge.textContent, 10);
    if (isNaN(current) || current <= 1) {
      badge.remove();
    } else {
      badge.textContent = String(current - 1);
    }
  }

  // Take a row out; when it was the last one, the group says so (or goes)
  function removeRow(id, countsInBadge) {
    const row = document.getElementById(id);
    if (!row) return;
    const group = row.closest('[data-group]');
    row.remove();
    if (countsInBadge) decrementBadge();

    // The count beside the section label
    const count = group && group.querySelector('[data-count]');
    if (count) {
      const left = parseInt(count.textContent, 10) - 1;
      if (left > 0) count.textContent = String(left);
      else count.remove();
    }

    if (!group || group.querySelector('tbody tr')) return;
    if (group.getAttribute('data-group') === 'users') {
      const wrap = group.querySelector('.admin-table-wrap');
      if (wrap) {
        const empty = document.createElement('p');
        empty.className = 'admin-muted';
        empty.textContent = T.noPending || '';
        wrap.replaceWith(empty);
      }
    } else {
      group.remove();
    }
  }

  // One handler for every approve / reject / unlink button (works for rows added later too)
  const ACTIONS = {
    'approve-btn':        { url: '/admin/api/approve_user.php',    field: 'user_id',    row: 'row-',        badge: true,  ok: () => T.approved },
    'reject-btn':         { url: '/admin/api/reject_user.php',     field: 'user_id',    row: 'row-',        badge: true,  ok: () => T.rejected, confirm: () => T.confirmReject, danger: true },
    'spouse-approve-btn': { url: '/admin/api/spouse/approve.php',  field: 'request_id', row: 'spouse-row-', badge: true,  ok: () => T.spouseLinked, confirm: () => T.confirmSpouseLink },
    'spouse-reject-btn':  { url: '/admin/api/spouse/reject.php',   field: 'request_id', row: 'spouse-row-', badge: true,  ok: () => T.spouseRejected, confirm: () => T.confirmSpouseReject, danger: true },
    'spouse-unlink-btn':  { url: '/admin/api/spouse/unlink.php',   field: 'user_id',    row: 'couple-row-', badge: false, ok: () => T.spouseUnlinked, confirm: () => T.confirmUnlink, danger: true }
  };

  root.addEventListener('click', async function (e) {
    const btn = e.target.closest('button[data-id]');
    if (!btn) return;
    const cls = Object.keys(ACTIONS).find(c => btn.classList.contains(c));
    if (!cls) return;

    const action = ACTIONS[cls];
    const id = btn.getAttribute('data-id');
    if (!id || btn.disabled) return;

    if (action.confirm) {
      const sure = await OACUI.confirm(action.confirm(), { danger: !!action.danger });
      if (!sure) return;
    }

    btn.disabled = true;
    const data = await OACUI.post(action.url, { [action.field]: id });
    if (data.success) {
      removeRow(action.row + id, action.badge);
      OACUI.toast(data.message || action.ok() || '', 'success');
    } else {
      OACUI.toast(data.error || '', 'error');
      btn.disabled = false;
    }
  });

  // ===== Settings: change password =====

  // The eye in a password field: show / hide what was typed
  root.addEventListener('click', function (e) {
    const eye = e.target.closest('[data-pw-toggle]');
    if (!eye) return;
    const input = document.getElementById(eye.getAttribute('data-pw-toggle'));
    if (!input) return;
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    eye.setAttribute('aria-pressed', String(show));
    eye.setAttribute('aria-label', show ? (T.pwHide || '') : (T.pwShow || ''));
    input.focus();
  });

  const form = document.getElementById('password-form');
  if (form) {
    form.addEventListener('submit', async function (e) {
      e.preventDefault();

      const current = form.elements.current_password.value;
      const next = form.elements.new_password.value;
      const confirmPw = form.elements.confirm_password.value;
      const btn = form.querySelector('button[type="submit"]');

      if (next !== confirmPw) {
        OACUI.toast(T.pwNoMatch, 'error');
        form.elements.confirm_password.focus();
        return;
      }
      if (next.length < 8) {
        OACUI.toast(T.pwMin, 'error');
        form.elements.new_password.focus();
        return;
      }
      if (next === current) {
        OACUI.toast(T.pwSame, 'error');
        form.elements.new_password.focus();
        return;
      }

      btn.disabled = true;
      btn.classList.add('is-busy');
      const data = await OACUI.post('/admin/api/users/change_password.php', {
        current_password: current,
        new_password: next
      });
      btn.disabled = false;
      btn.classList.remove('is-busy');

      if (data.success) {
        OACUI.toast(T.pwChanged, 'success');
        form.reset();
        // Hidden again, so the next person at this phone sees dots
        form.querySelectorAll('[data-pw-toggle]').forEach(function (eye) {
          const input = document.getElementById(eye.getAttribute('data-pw-toggle'));
          if (input) input.type = 'password';
          eye.setAttribute('aria-pressed', 'false');
          eye.setAttribute('aria-label', T.pwShow || '');
        });
      } else {
        OACUI.toast(data.error || T.pwFailed, 'error');
      }
    });
  }
})();
