/**
 * Appointments tab (amp 1-9): answer the requests made to you, see what is
 * coming up, find an office bearer (search + office filter) and ask for an
 * appointment. Writes go through OACUI.post() to /admin/api/ampte/ (CSRF).
 * The calendar reads the same appointments, so nothing is copied anywhere
 * else. Dates and the red tab count are admin.js's (window.OACAdmin).
 */
(function () {
  'use strict';

  const root = document.getElementById('afsprakeRoot');
  const layer = document.getElementById('appointmentModal');
  const form = document.getElementById('appointmentForm');
  if (!root || !layer || !form || !window.OACUI) return;

  const L = window.AFSPRAKE_LANG || {};
  const body = document.body;
  // A fixed layer must not sit inside anything transformed: the sheet lives on <body>
  body.appendChild(layer);
  const sheet = layer.querySelector('.afsprake-sheet');
  const dateEl = document.getElementById('appointmentDate');
  const timeEl = document.getElementById('appointmentTime');
  const submitBtn = form.querySelector('button[type="submit"]');
  let lastFocus = null;

  const admin = () => window.OACAdmin || null;

  // ===== "Coming up": fetched again from this page after every change =====
  async function refreshUpcoming() {
    const slot = document.getElementById('afsprakeUpcomingSlot');
    if (!slot || !window.DOMParser) return;
    try {
      const res = await fetch('/admin/index.php?tab=afsprake', { credentials: 'same-origin' });
      if (!res.ok) return;
      const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
      const fresh = doc.getElementById('afsprakeUpcomingSlot');
      if (!fresh) return;
      slot.innerHTML = fresh.innerHTML;
      if (admin()) admin().dates(slot);
    } catch (e) { /* the list stays as it was; a reload shows the rest */ }
  }

  function localISO(d) {
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  }

  // ===== The appointment sheet =====
  function openAppointment(userId, userName, presetDate, presetTime) {
    lastFocus = document.activeElement;
    form.reset();
    document.getElementById('appointmentTargetId').value = userId;
    document.getElementById('appointmentTargetName').textContent = userName;
    dateEl.min = localISO(new Date());
    if (presetDate) dateEl.value = presetDate;
    if (presetTime) timeEl.value = presetTime;

    layer.hidden = false;
    void layer.offsetWidth; // let the closed state paint, so the sheet slides in
    body.style.overflow = 'hidden';
    requestAnimationFrame(function () {
      layer.classList.add('show');
      (presetDate ? timeEl : dateEl).focus();
    });
  }

  function closeAppointment() {
    layer.classList.remove('show');
    body.style.overflow = '';
    setTimeout(function () { layer.hidden = true; }, 220);
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }

  layer.addEventListener('click', function (e) {
    if (e.target === layer || e.target.closest('[data-close]')) closeAppointment();
  });

  document.addEventListener('keydown', function (e) {
    if (layer.hidden) return;
    if (e.key === 'Escape') {
      closeAppointment();
    } else if (e.key === 'Tab') {
      // keep the focus inside the sheet
      const f = sheet.querySelectorAll('button, input, textarea, select, a[href]');
      const list = Array.prototype.filter.call(f, function (n) { return !n.disabled && n.offsetParent !== null; });
      if (!list.length) return;
      const first = list[0], last = list[list.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
  });

  root.addEventListener('click', function (e) {
    const btn = e.target.closest('.btn-make-appointment');
    if (btn) openAppointment(btn.getAttribute('data-id'), btn.getAttribute('data-name'), '', '');
  });

  // Opened from the calendar: ?make_appointment=1&target_user=…&date=…&time=…
  if (layer.dataset.openId) {
    openAppointment(layer.dataset.openId, layer.dataset.openName, layer.dataset.openDate || '', layer.dataset.openTime || '');
    // The address no longer needs the request in it (a reload would open it again)
    if (window.history && history.replaceState) history.replaceState(null, '', location.pathname + '?tab=afsprake');
  }

  form.addEventListener('submit', async function (e) {
    e.preventDefault();
    if (!dateEl.value || !timeEl.value) {
      (dateEl.value ? timeEl : dateEl).focus();
      OACUI.toast(L.pickDate || '', 'error');
      return;
    }

    submitBtn.disabled = true;
    submitBtn.classList.add('is-busy');
    const data = await OACUI.post('/admin/api/ampte/appointment.php', new FormData(form));
    submitBtn.disabled = false;
    submitBtn.classList.remove('is-busy');

    if (data.success) {
      OACUI.toast(L.requestSent || '', 'success');
      closeAppointment();
      refreshUpcoming();
    } else {
      OACUI.toast(data.error || '', 'error');
    }
  });

  // ===== Find an office bearer: search words and one office at a time =====
  const search = document.getElementById('afsprakeSearch');
  const chips = Array.prototype.slice.call(root.querySelectorAll('[data-office][aria-pressed]'));
  const people = Array.prototype.slice.call(root.querySelectorAll('.afsprake-card-person'));
  const groups = Array.prototype.slice.call(root.querySelectorAll('.afsprake-group'));
  const none = document.getElementById('afsprakeNoMatch');
  let office = '';

  function applyFilter() {
    const q = search ? search.value.toLowerCase().trim() : '';
    let any = false;
    groups.forEach(function (g) {
      const inOffice = office === '' || g.getAttribute('data-office') === office;
      let shown = 0;
      g.querySelectorAll('.afsprake-card-person').forEach(function (card) {
        const hit = inOffice && (q === '' || (card.getAttribute('data-name') || '').indexOf(q) !== -1);
        card.hidden = !hit;
        if (hit) shown++;
      });
      g.hidden = shown === 0;
      any = any || shown > 0;
    });
    if (none) none.hidden = any || !people.length;
  }

  if (search) search.addEventListener('input', applyFilter);
  chips.forEach(function (chip) {
    chip.addEventListener('click', function () {
      office = chip.getAttribute('data-office') || '';
      chips.forEach(function (c) { c.setAttribute('aria-pressed', String(c === chip)); });
      applyFilter();
    });
  });

  // ===== Answer a request, or cancel one that is coming up =====
  function cardGone(card) {
    const section = card.closest('[data-appt-section]');
    card.classList.add('is-leaving');
    setTimeout(function () {
      card.remove();
      if (!section) return;
      const left = section.querySelectorAll('.afsprake-card').length;
      if (left === 0 && section.id === 'afsprakePending') {
        section.remove();
        return;
      }
      const count = section.querySelector('[data-count]');
      if (count) count.textContent = String(left);
    }, 240);
  }

  root.addEventListener('click', async function (e) {
    const yes = e.target.closest('.confirm-appointment');
    const no = e.target.closest('.cancel-appointment');
    const btn = yes || no;
    if (!btn || btn.disabled) return;

    const id = btn.getAttribute('data-id');
    const card = btn.closest('.afsprake-card');
    const wasRequest = !!(card && card.closest('#afsprakePending'));

    if (no) {
      const sure = await OACUI.confirm(L.confirmCancel || '', { danger: true });
      if (!sure) return;
    }

    const buttons = card ? card.querySelectorAll('button') : [btn];
    buttons.forEach(function (b) { b.disabled = true; });
    btn.classList.add('is-busy');

    const data = await OACUI.post(yes ? '/admin/api/ampte/confirm_appointment.php' : '/admin/api/ampte/cancel_appointment.php', { appointment_id: id });
    btn.classList.remove('is-busy');
    if (data.success) {
      OACUI.toast(yes ? L.confirmed : L.cancelled, 'success');
      if (wasRequest && admin()) admin().lowerBadge('afsprake');
      if (wasRequest && card) cardGone(card);
      // Confirmed or cancelled, "coming up" is now different
      refreshUpcoming();
    } else {
      OACUI.toast(data.error || '', 'error');
      buttons.forEach(function (b) { b.disabled = false; });
    }
  });
})();
