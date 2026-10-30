/**
 * Admin ("Bestuur") — the clicking on every tab that has no script of its own:
 * approve / reject / link / unlink on the Approvals tab, answering a spouse
 * request made to you (Overview, My account), the password form, the name
 * filters, and dates written out in the member's language.
 * The red counts in the navigation go down as work is done, and the site
 * header's menu is told to count again ("oac:pending-changed").
 * Every write is a POST through OACUI.post() (ui.js), which adds the
 * session's CSRF token. The words come from window.OAC_ADMIN (index.php).
 */
(function () {
  'use strict';

  const root = document.querySelector('body.adm');
  if (!root || !window.OACUI) return;

  const T = window.OAC_ADMIN || {};

  // Back from another page (the browser's page cache): the counts may be old, so ask again
  window.addEventListener('pageshow', function (e) {
    if (e.persisted) location.reload();
  });

  // ===== The active tab in view (the phone's row of tabs scrolls sideways) =====
  const navList = document.querySelector('.adm-nav-list');
  const activeLink = navList && navList.querySelector('.adm-nav-link.is-active');
  if (navList && activeLink && navList.scrollWidth > navList.clientWidth) {
    navList.scrollLeft = Math.max(0, activeLink.offsetLeft - (navList.clientWidth - activeLink.offsetWidth) / 2);
  }

  // ===== Dates in the member's language =====
  const LANG = T.lang || undefined;

  function dayOnly(iso) {
    const d = new Date(iso + 'T00:00:00');
    return isNaN(d) ? null : d;
  }

  function fmt(d, opts) {
    try { return d.toLocaleDateString(LANG, opts); } catch (e) { return d.toLocaleDateString(undefined, opts); }
  }

  /**
   * <time data-date="Y-m-d">: data-style="chip" draws a small calendar leaf
   * (weekday, day, month), "relative" says today / tomorrow when it can,
   * anything else is the full date.
   */
  function dates(scope) {
    (scope || document).querySelectorAll('time[data-date]').forEach(function (el) {
      const d = dayOnly(el.getAttribute('data-date'));
      if (!d) return;
      const style = el.getAttribute('data-style') || '';

      if (style === 'chip') {
        const parts = [fmt(d, { weekday: 'short' }), fmt(d, { day: 'numeric' }), fmt(d, { month: 'short' })];
        el.replaceChildren();
        parts.forEach(function (p, i) {
          const s = document.createElement('span');
          s.className = 'adm-date-chip-' + ['wd', 'd', 'm'][i];
          s.textContent = String(p).replace(/\.$/, '');
          el.appendChild(s);
        });
        el.setAttribute('aria-label', fmt(d, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }));
        return;
      }

      if (style === 'relative') {
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const diff = Math.round((d - today) / 86400000);
        if (diff === 0 && T.today) { el.textContent = T.today; return; }
        if (diff === 1 && T.tomorrow) { el.textContent = T.tomorrow; return; }
        const opts = { weekday: 'long', day: 'numeric', month: 'long' };
        if (d.getFullYear() !== today.getFullYear()) opts.year = 'numeric';
        el.textContent = fmt(d, opts);
        return;
      }

      el.textContent = fmt(d, { day: 'numeric', month: 'long', year: 'numeric' });
    });
  }
  dates(document);

  // ===== Counts =====
  function fill(s, n) {
    return String(s || '').split('{n}').join(String(n));
  }

  /** One less on a tab's red count (it goes at 0), and the header's menu counts again */
  function lowerBadge(tab) {
    document.querySelectorAll('[data-badge="' + tab + '"]').forEach(function (b) {
      const n = Math.max(0, (parseInt(b.textContent, 10) || 0) - 1);
      b.textContent = n > 99 ? '99+' : String(n);
      b.setAttribute('aria-label', fill(T.waitingAria, n));
      b.hidden = n === 0;
    });
    window.dispatchEvent(new CustomEvent('oac:pending-changed'));
  }

  // For afsprake.js, which loads earlier and calls these once it is clicked
  window.OACAdmin = { dates: dates, lowerBadge: lowerBadge };

  const EMPTY_ICON = '<svg class="adm-ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg>';

  /** Take a card out; the group's count follows, and an empty group says so (or goes) */
  function removeRow(row) {
    const group = row.closest('[data-group]');
    row.classList.add('is-leaving');

    setTimeout(function () {
      row.remove();
      if (!group) return;

      const left = group.querySelectorAll('[data-row]').length;
      const count = group.querySelector('[data-count]');
      if (count) {
        if (left > 0) count.textContent = String(left);
        else count.remove();
      }
      if (left > 0) return;

      if (group.getAttribute('data-group') === 'users') {
        const list = group.querySelector('[data-list]');
        if (list) {
          const empty = document.createElement('div');
          empty.className = 'adm-empty';
          empty.innerHTML = '<span class="adm-empty-icon">' + EMPTY_ICON + '</span><p></p>';
          empty.querySelector('p').textContent = T.noPending || '';
          list.replaceWith(empty);
        }
      } else {
        group.remove();
      }
    }, 240);
  }

  // ===== Approvals: one handler for every button (rows added later too) =====
  const ACTIONS = {
    'approve-btn':        { url: '/admin/api/approve_user.php',   field: 'user_id',    badge: 'approvals', ok: () => T.approved },
    'reject-btn':         { url: '/admin/api/reject_user.php',    field: 'user_id',    badge: 'approvals', ok: () => T.rejected, confirm: () => T.confirmReject, danger: true },
    'spouse-approve-btn': { url: '/admin/api/spouse/approve.php', field: 'request_id', badge: 'approvals', ok: () => T.spouseLinked, confirm: () => T.confirmSpouseLink },
    'spouse-reject-btn':  { url: '/admin/api/spouse/reject.php',  field: 'request_id', badge: 'approvals', ok: () => T.spouseRejected, confirm: () => T.confirmSpouseReject, danger: true },
    'spouse-unlink-btn':  { url: '/admin/api/spouse/unlink.php',  field: 'user_id',    badge: '',          ok: () => T.spouseUnlinked, confirm: () => T.confirmUnlink, danger: true }
  };

  root.addEventListener('click', async function (e) {
    const btn = e.target.closest('button[data-id]');
    if (!btn || btn.hasAttribute('data-spouse')) return;
    const cls = Object.keys(ACTIONS).find(c => btn.classList.contains(c));
    if (!cls) return;

    const action = ACTIONS[cls];
    const id = btn.getAttribute('data-id');
    if (!id || btn.disabled) return;

    if (action.confirm) {
      const sure = await OACUI.confirm(action.confirm(), { danger: !!action.danger });
      if (!sure) return;
    }

    const row = btn.closest('[data-row]');
    const buttons = row ? row.querySelectorAll('button') : [btn];
    buttons.forEach(b => { b.disabled = true; });
    btn.classList.add('is-busy');

    const data = await OACUI.post(action.url, { [action.field]: id });
    btn.classList.remove('is-busy');
    if (data.success) {
      if (row) removeRow(row);
      if (action.badge) lowerBadge(action.badge);
      OACUI.toast(data.message || action.ok() || '', 'success');
    } else {
      OACUI.toast(data.error || '', 'error');
      buttons.forEach(b => { b.disabled = false; });
    }
  });

  // ===== A spouse request that involves me: accept, turn down, or withdraw =====
  root.addEventListener('click', async function (e) {
    const btn = e.target.closest('button[data-spouse]');
    if (!btn || btn.disabled) return;
    const action = btn.getAttribute('data-spouse');
    if (['accept', 'reject', 'cancel'].indexOf(action) === -1) return;

    if (action === 'reject') {
      const sure = await OACUI.confirm(T.confirmSpouseReject || '', { danger: true });
      if (!sure) return;
    }

    const box = btn.closest('.adm-task-actions');
    const buttons = box ? box.querySelectorAll('button') : [btn];
    buttons.forEach(b => { b.disabled = true; });
    btn.classList.add('is-busy');

    const data = await OACUI.post('/admin/api/spouse/' + action + '.php', { request_id: btn.getAttribute('data-id') });
    if (data.success) {
      location.reload();
    } else {
      btn.classList.remove('is-busy');
      OACUI.toast(data.error || '', 'error');
      buttons.forEach(b => { b.disabled = false; });
    }
  });

  // ===== Name filters (a search box over a list of [data-search] rows) =====
  root.addEventListener('input', function (e) {
    const input = e.target.closest('input[data-filter]');
    if (!input) return;
    const list = document.querySelector(input.getAttribute('data-filter'));
    if (!list) return;
    const q = input.value.toLowerCase().trim();
    list.querySelectorAll('[data-search]').forEach(function (row) {
      row.hidden = q !== '' && (row.getAttribute('data-search') || '').indexOf(q) === -1;
    });
  });

  // ===== My account: change password =====

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

      if (!current) {
        form.elements.current_password.focus();
        return;
      }
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
