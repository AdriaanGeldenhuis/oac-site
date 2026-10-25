/**
 * Calendar — month, week and day, with sheets for adding and reading.
 *
 * Reads:  /calendar/api/list.php (own calendar) or
 *         /calendar/api/view_user_calendar.php (someone else's, read only)
 * Writes: visits      → /calendar/api/upsert.php, delete.php
 *         room events → Gospel Media's own API (/gospel_media/api/posts/*),
 *                       so the feed and the calendar always agree
 *         sharing     → share.php, reminders → remind.php,
 *         appointments→ appointment.php
 * Every POST carries the session CSRF token (X-CSRF-Token).
 *
 * Dates are plain "YYYY-MM-DD" strings in South African time; no date
 * strings are ever parsed by the browser (iOS Safari safe). The server
 * always decides what is allowed; the buttons only follow its answer.
 */
(function () {
  'use strict';

  const CFG = window.CAL;
  if (!CFG) return;
  const T = CFG.t || {};
  const VIEW_MODE = CFG.mode === 'view';
  const NS = 'http://www.w3.org/2000/svg';

  // ===== STRINGS =====
  function tr(key, params) {
    let s = T[key] || key;
    if (params) {
      for (const p in params) s = s.split('{' + p + '}').join(String(params[p]));
    }
    return s;
  }

  const ERRORS = {
    csrf: 'cal_err_session', session: 'cal_err_session', offline: 'cal_err_offline',
    forbidden: 'cal_err_forbidden', not_found: 'cal_err_not_found', title_required: 'cal_err_title',
    empty_post: 'cal_err_title', invalid_date: 'cal_err_date', invalid_time: 'cal_err_time',
    invalid_person: 'cal_err_person', room_not_found: 'cal_err_room', rate_limited: 'cal_err_rate',
    reminder_recent: 'cal_reminder_recent', event_past: 'cal_err_past', already_done: 'cal_err_already',
    no_spouse: 'cal_err_no_spouse'
  };
  const errorText = (code) => tr(ERRORS[code] || 'cal_err_generic');

  // ===== DOM HELPERS =====
  const $ = (s, el) => (el || document).querySelector(s);

  function el(tag, attrs, kids) {
    const n = document.createElement(tag);
    if (attrs) {
      for (const k in attrs) {
        const v = attrs[k];
        if (v === null || v === undefined || v === false) continue;
        if (k === 'class') n.className = v;
        else if (k === 'text') n.textContent = v;
        else if (k === 'on') { for (const ev in v) n.addEventListener(ev, v[ev]); }
        else n.setAttribute(k, v === true ? '' : String(v));
      }
    }
    (kids || []).forEach((c) => { if (c !== null && c !== undefined && c !== false) n.append(c); });
    return n;
  }

  function icon(id, cls) {
    const s = document.createElementNS(NS, 'svg');
    s.setAttribute('viewBox', '0 0 24 24');
    s.setAttribute('aria-hidden', 'true');
    if (cls) s.setAttribute('class', cls);
    const u = document.createElementNS(NS, 'use');
    u.setAttribute('href', '#cal-i-' + id);
    s.appendChild(u);
    return s;
  }

  function strokeIcon(d, cls) {
    const s = document.createElementNS(NS, 'svg');
    s.setAttribute('viewBox', '0 0 24 24');
    s.setAttribute('fill', 'none');
    s.setAttribute('stroke', 'currentColor');
    s.setAttribute('stroke-width', '2.2');
    s.setAttribute('stroke-linecap', 'round');
    s.setAttribute('stroke-linejoin', 'round');
    s.setAttribute('aria-hidden', 'true');
    if (cls) s.setAttribute('class', cls);
    const p = document.createElementNS(NS, 'path');
    p.setAttribute('d', d);
    s.appendChild(p);
    return s;
  }
  const ICON_PLUS = 'M12 5v14M5 12h14';
  const ICON_CLOSE = 'M6 6l12 12M18 6L6 18';
  const ICON_EDIT = 'M12 20h9M16.5 3.5a2.12 2.12 0 013 3L7 19l-4 1 1-4 12.5-12.5z';
  const ICON_TRASH = 'M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 11v6M14 11v6';
  const ICON_CHECK = 'M5 12l5 5L20 7';
  const ICON_X = 'M18 6L6 18M6 6l12 12';
  const ICON_OPEN = 'M14 3h7v7M10 14L21 3M21 14v7H3V3h7';

  // ===== DATES (strings in, strings out) =====
  const pad = (n) => String(n).padStart(2, '0');
  const parts = (s) => [+s.slice(0, 4), +s.slice(5, 7), +s.slice(8, 10)];
  const isDate = (s) => typeof s === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(s);
  function toDate(s) { const p = parts(s); return new Date(p[0], p[1] - 1, p[2], 12); }
  function ymd(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
  function addDays(s, n) { const d = toDate(s); d.setDate(d.getDate() + n); return ymd(d); }
  function addMonths(s, n) {
    const p = parts(s);
    const first = new Date(p[0], p[1] - 1 + n, 1, 12);
    const last = new Date(first.getFullYear(), first.getMonth() + 1, 0, 12).getDate();
    first.setDate(Math.min(p[2], last));
    return ymd(first);
  }
  const weekday = (s) => toDate(s).getDay();            // 0 = Sunday
  const mondayOf = (s) => addDays(s, -((weekday(s) + 6) % 7));
  const monthStart = (s) => s.slice(0, 8) + '01';
  function monthEnd(s) { const p = parts(s); return ymd(new Date(p[0], p[1], 0, 12)); }
  function isoWeek(s) {
    const d = toDate(s);
    const day = (d.getDay() + 6) % 7;
    d.setDate(d.getDate() - day + 3);
    const firstThursday = new Date(d.getFullYear(), 0, 4, 12);
    return 1 + Math.round(((d - firstThursday) / 864e5 - 3 + ((firstThursday.getDay() + 6) % 7)) / 7);
  }

  // South African time (no daylight saving): the same answer on any phone
  function sastNow() {
    const d = new Date(Date.now() + 2 * 3600e3);
    return {
      date: d.getUTCFullYear() + '-' + pad(d.getUTCMonth() + 1) + '-' + pad(d.getUTCDate()),
      time: pad(d.getUTCHours()) + ':' + pad(d.getUTCMinutes())
    };
  }
  const today = () => sastNow().date;

  const months = T.months || [];
  const days = T.days || [];
  const daysShort = T.days_short || [];
  const monthShort = (m) => String(months[m - 1] || '').slice(0, 3);
  function dayLong(s) {
    const p = parts(s);
    return (days[weekday(s)] || '') + ', ' + p[2] + ' ' + (months[p[1] - 1] || '') + ' ' + p[0];
  }
  function dayMedium(s) {
    const p = parts(s);
    return (daysShort[weekday(s)] || '') + ' ' + p[2] + ' ' + monthShort(p[1]);
  }
  function relativeDay(s) {
    if (s === today()) return T.today;
    if (s === addDays(today(), 1)) return T.tomorrow;
    return '';
  }

  // ===== API =====
  async function api(url, opts) {
    const o = Object.assign({ credentials: 'same-origin' }, opts || {});
    o.headers = Object.assign({ Accept: 'application/json' }, o.headers || {});
    if (String(o.method || 'GET').toUpperCase() === 'POST') o.headers['X-CSRF-Token'] = CFG.csrf || '';
    let res;
    try {
      res = await fetch(url, o);
    } catch (e) {
      return { ok: false, error: 'offline' };
    }
    if (res.redirected && /\/login\.php/.test(res.url)) return { ok: false, error: 'session' };
    let data = null;
    try { data = JSON.parse(await res.text()); } catch (e) { data = null; }
    if (data === null) return { ok: false, error: res.status === 401 ? 'session' : 'server_error', status: res.status };
    const ok = res.ok && (Array.isArray(data) || data.success === true || data.ok === 1 || data.ok === true);
    return { ok: ok, data: data, status: res.status, error: ok ? '' : (data.error || 'server_error') };
  }

  function post(url, fields) {
    const fd = new FormData();
    for (const k in fields) {
      if (fields[k] !== undefined && fields[k] !== null) fd.append(k, fields[k]);
    }
    return api(url, { method: 'POST', body: fd });
  }

  // ===== STORAGE (a convenience; the page works without it) =====
  function store(key, value) {
    try {
      if (value === undefined) return JSON.parse(localStorage.getItem('cal_' + key) || 'null');
      localStorage.setItem('cal_' + key, JSON.stringify(value));
    } catch (e) { /* private mode */ }
    return null;
  }

  // ===== STATE =====
  const TYPES = VIEW_MODE ? ['visit', 'appointment'] : ['event', 'visit', 'appointment', 'diary', 'birthday'];
  const TYPE_ICON = { event: 'event', visit: 'visit', appointment: 'appointment', diary: 'diary', birthday: 'birthday' };
  const TYPE_LABEL = { event: 'event', visit: 'cal_visit', appointment: 'appointment', diary: 'diary', birthday: 'cal_birthday' };
  const FILTER_LABEL = { event: 'cal_f_events', visit: 'cal_f_visits', appointment: 'cal_f_appointments', diary: 'diary', birthday: 'cal_f_birthdays' };
  const VIEWS = ['month', 'week', 'day'];

  const saved = store(VIEW_MODE ? 'view_state' : 'state') || {};
  const state = {
    view: VIEWS.indexOf(saved.view) !== -1 ? saved.view : 'month',
    date: today(),
    selected: today(),
    filters: {},
    items: [],
    range: null,
    loading: false,
    error: '',
    loadedAt: 0,
    rooms: null,
    users: null,
    spouse: { has_spouse: false, spouse_name: '', pending_requests: 0 }
  };
  TYPES.forEach((t) => { state.filters[t] = !(saved.filters && saved.filters[t] === false); });

  // #v=week&d=2026-10-30&i=event-12 — survives the language switch and reloads
  function readHash() {
    const h = new URLSearchParams(String(window.location.hash || '').replace(/^#/, ''));
    const v = h.get('v');
    const d = h.get('d');
    if (VIEWS.indexOf(v) !== -1) state.view = v;
    if (isDate(d)) { state.date = d; state.selected = d; }
    return h.get('i') || '';
  }
  let pendingItem = readHash();

  function writeHash() {
    const h = '#v=' + state.view + '&d=' + state.selected;
    if (window.location.hash !== h) {
      try { history.replaceState(null, '', window.location.pathname + window.location.search + h); } catch (e) { /* old WebView */ }
    }
    store(VIEW_MODE ? 'view_state' : 'state', { view: state.view, filters: state.filters });
  }

  // ===== TOAST =====
  let toastTimer = 0;
  function toast(message, isError) {
    const t = $('#calToast');
    if (!t || !message) return;
    t.textContent = message;
    t.classList.toggle('is-error', !!isError);
    t.classList.add('is-on');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => t.classList.remove('is-on'), isError ? 4200 : 2400);
  }

  // ===== LAYERS =====
  // One stack: Escape and the scrim close the top layer, Tab stays inside
  // it, focus returns to where it was, and the page behind does not scroll.
  const layers = [];

  function openLayer(panel, labelledBy) {
    const back = el('div', { class: 'cal-layer' });
    panel.setAttribute('role', panel.getAttribute('role') || 'dialog');
    panel.setAttribute('aria-modal', 'true');
    if (labelledBy) panel.setAttribute('aria-labelledby', labelledBy);
    panel.setAttribute('tabindex', '-1');
    back.appendChild(panel);

    const prevFocus = document.activeElement;
    const layer = { back: back, panel: panel, onClose: null };

    function close() {
      if (!back.parentNode) return;
      back.classList.add('is-closing');
      const done = () => { if (back.parentNode) back.parentNode.removeChild(back); };
      setTimeout(done, 180);
      const i = layers.indexOf(layer);
      if (i !== -1) layers.splice(i, 1);
      if (!layers.length) document.body.classList.remove('cal-locked');
      if (layer.onClose) layer.onClose();
      if (prevFocus && prevFocus.focus && document.body.contains(prevFocus)) {
        try { prevFocus.focus({ preventScroll: true }); } catch (e) { prevFocus.focus(); }
      }
    }

    layer.close = close;
    layers.push(layer);
    back.addEventListener('click', (e) => { if (e.target === back) close(); });
    document.body.appendChild(back);
    document.body.classList.add('cal-locked');
    enableSwipeDown(panel, close);

    const first = panel.querySelector('[autofocus]') || panel;
    try { first.focus({ preventScroll: true }); } catch (e) { first.focus(); }
    return layer;
  }

  document.addEventListener('keydown', (e) => {
    if (!layers.length) return;
    const top = layers[layers.length - 1];
    if (e.key === 'Escape') {
      e.preventDefault();
      top.close();
    } else if (e.key === 'Tab') {
      const focusable = Array.prototype.filter.call(
        top.panel.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'),
        (n) => !n.disabled && n.offsetParent !== null
      );
      if (!focusable.length) return;
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (e.shiftKey && (document.activeElement === first || document.activeElement === top.panel)) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    }
  });

  // Phones: pull the sheet down by its handle to close it
  function enableSwipeDown(panel, close) {
    const grip = panel.querySelector('.cal-sheet-grip');
    if (!grip) return;
    let startY = 0;
    let dy = 0;
    let dragging = false;
    grip.addEventListener('touchstart', (e) => { startY = e.touches[0].clientY; dy = 0; dragging = true; }, { passive: true });
    grip.addEventListener('touchmove', (e) => {
      if (!dragging) return;
      dy = Math.max(0, e.touches[0].clientY - startY);
      panel.style.transform = 'translateY(' + dy + 'px)';
    }, { passive: true });
    grip.addEventListener('touchend', () => {
      dragging = false;
      panel.style.transform = '';
      if (dy > 90) close();
    });
  }

  /** A sheet (phone) / card (desktop) with a title and a close button */
  function sheet(title, opts) {
    opts = opts || {};
    const id = 'calSheet' + Math.random().toString(36).slice(2, 8);
    const panel = el('div', { class: 'cal-sheet' + (opts.type ? ' t-' + opts.type : '') + (opts.cls ? ' ' + opts.cls : '') });
    const head = el('div', { class: 'cal-sheet-head' }, [
      el('div', { class: 'cal-sheet-heading' }, [
        opts.eyebrow || null,
        el('h2', { class: 'cal-sheet-title' + (opts.script ? ' cal-sheet-title--script' : ''), id: id, text: title })
      ]),
      el('button', { class: 'cal-sheet-close', type: 'button', 'aria-label': T.close, on: { click: () => layer.close() } }, [strokeIcon(ICON_CLOSE)])
    ]);
    const body = el('div', { class: 'cal-sheet-body' });
    panel.append(el('span', { class: 'cal-sheet-grip', 'aria-hidden': 'true' }), head, body);
    const layer = openLayer(panel, id);
    layer.body = body;
    return layer;
  }

  /** A confirm card instead of confirm() (which shows the site address in the app) */
  function confirmCard(title, text, okLabel, danger) {
    return new Promise((resolve) => {
      const panel = el('div', { class: 'cal-sheet cal-confirm', role: 'alertdialog' });
      const id = 'calConfirm' + Math.random().toString(36).slice(2, 8);
      let answered = false;
      const ok = el('button', { class: 'cal-btn ' + (danger ? 'cal-btn--danger' : 'cal-btn--primary'), type: 'button', text: okLabel });
      const cancel = el('button', { class: 'cal-btn cal-btn--glass', type: 'button', text: T.cancel, autofocus: true });
      panel.append(
        el('h2', { class: 'cal-sheet-title', id: id, text: title }),
        text ? el('p', { class: 'cal-confirm-text', text: text }) : null,
        el('div', { class: 'cal-form-actions' }, [cancel, ok])
      );
      const layer = openLayer(panel, id);
      const finish = (v) => { if (answered) return; answered = true; layer.close(); resolve(v); };
      layer.onClose = () => { if (!answered) { answered = true; resolve(false); } };
      ok.addEventListener('click', () => finish(true));
      cancel.addEventListener('click', () => finish(false));
    });
  }

  function setBusy(btn, busy, label) {
    if (!btn) return;
    btn.disabled = !!busy;
    btn.classList.toggle('is-busy', !!busy);
    if (label !== undefined) {
      const span = btn.querySelector('span') || btn;
      if (busy) { btn.dataset.label = span.textContent; span.textContent = label; }
      else if (btn.dataset.label) { span.textContent = btn.dataset.label; }
    }
  }

  // ===== ITEMS =====
  function itemTitle(it) {
    if (it.type === 'birthday') return it.is_self ? T.cal_your_birthday : tr('cal_birthday_of', { name: it.title });
    return it.title || it.person_name || T[TYPE_LABEL[it.type]] || '';
  }

  function itemMeta(it) {
    const bits = [];
    if (it.type === 'event' && it.room_name) bits.push(it.room_name);
    if ((it.type === 'visit') && it.person_name && it.person_name !== it.title) bits.push(it.person_name);
    if (it.type === 'appointment' && it.status === 'pending') bits.push(T.cal_status_pending);
    if (it.location) bits.push(it.location);
    if (it.is_shared) bits.push(T.cal_shared_by_spouse);
    return bits.join(' · ');
  }

  function isPast(it) {
    const now = sastNow();
    const key = it.date + ' ' + (it.time || '23:59');
    return key < now.date + ' ' + now.time;
  }

  function visible() {
    return state.items.filter((it) => state.filters[it.type] !== false);
  }

  function byDate(list) {
    const map = new Map();
    list.forEach((it) => {
      if (!map.has(it.date)) map.set(it.date, []);
      map.get(it.date).push(it);
    });
    return map;
  }

  function itemButton(it, compact) {
    const past = isPast(it);
    const b = el('button', {
      class: 'cal-item t-' + it.type + (compact ? ' cal-item--compact' : '') + (past ? ' is-past' : '') +
        (it.type === 'appointment' && it.status === 'pending' ? ' is-pending' : ''),
      type: 'button',
      'data-uid': it.uid,
      'aria-haspopup': 'dialog'
    });
    const time = el('span', { class: 'cal-item-time' }, [
      it.time ? el('b', { text: it.time }) : icon(TYPE_ICON[it.type], 'cal-item-allday')
    ]);
    const body = el('span', { class: 'cal-item-body' }, [
      el('span', { class: 'cal-item-title', text: itemTitle(it) })
    ]);
    const meta = itemMeta(it);
    if (meta && !compact) body.append(el('span', { class: 'cal-item-meta', text: meta }));
    b.append(el('span', { class: 'cal-item-bar', 'aria-hidden': 'true' }), time, body);
    if (!compact) {
      b.append(el('span', { class: 'cal-item-orb', 'aria-hidden': 'true' }, [icon(TYPE_ICON[it.type])]));
    }
    b.setAttribute('aria-label', [it.time || T.cal_all_day, itemTitle(it), meta, T[TYPE_LABEL[it.type]]].filter(Boolean).join(', '));
    b.addEventListener('click', () => openDetail(it));
    return b;
  }

  // ===== RANGE / LOADING =====
  function neededRange() {
    if (state.view === 'month') {
      const from = mondayOf(monthStart(state.date));
      return { from: from, to: addDays(from, 41) };
    }
    if (state.view === 'week') {
      const from = mondayOf(state.date);
      return { from: from, to: addDays(from, 6) };
    }
    return { from: state.date, to: state.date };
  }

  const covers = (r) => state.range && state.range.from <= r.from && state.range.to >= r.to;

  let loadSeq = 0;
  async function load(force) {
    const need = neededRange();
    if (!force && covers(need)) { render(); return; }

    // Ask for the whole month around it, so moving between views is instant
    const mFrom = mondayOf(monthStart(state.date));
    const fetchRange = { from: need.from < mFrom ? need.from : mFrom, to: addDays(mFrom, 41) > need.to ? addDays(mFrom, 41) : need.to };

    const seq = ++loadSeq;
    state.loading = true;
    state.error = '';
    render();

    const qs = 'from=' + fetchRange.from + '&to=' + fetchRange.to;
    const url = VIEW_MODE
      ? '/calendar/api/view_user_calendar.php?user_id=' + encodeURIComponent(CFG.targetId) + '&' + qs
      : '/calendar/api/list.php?' + qs;
    const res = await api(url);
    if (seq !== loadSeq) return;

    state.loading = false;
    if (!res.ok || !Array.isArray(res.data)) {
      state.error = res.error || 'server_error';
      render();
      return;
    }
    state.items = res.data;
    state.range = fetchRange;
    state.loadedAt = Date.now();
    render();

    if (pendingItem) {
      const uid = pendingItem;
      pendingItem = '';
      const it = state.items.find((x) => x.uid === uid);
      if (it) openDetail(it);
    }
  }

  function reload() {
    state.range = null;
    return load(true);
  }

  // ===== RENDER =====
  const board = $('#calBoardInner');
  const boardWrap = $('#calBoard');
  const agenda = $('#calAgenda');

  function render() {
    renderHeader();
    renderFilters();
    renderBoard();
    renderAgenda();
    writeHash();
    document.dispatchEvent(new CustomEvent('cal:render', {
      detail: { view: state.view, date: state.date, selected: state.selected, range: neededRange() }
    }));
  }

  function renderHeader() {
    const title = $('#calTitle span');
    const sub = $('#calSub');
    const p = parts(state.date);
    let big = months[p[1] - 1] || '';
    let small = String(p[0]);
    if (state.view === 'week') {
      const from = mondayOf(state.date);
      const to = addDays(from, 6);
      const fp = parts(from);
      const tp = parts(to);
      big = months[tp[1] - 1] === months[fp[1] - 1] ? months[fp[1] - 1] : monthShort(fp[1]) + ' – ' + monthShort(tp[1]);
      small = tr('cal_week_n', { n: isoWeek(from) }) + ' · ' + fp[2] + ' ' + monthShort(fp[1]) + ' – ' + tp[2] + ' ' + monthShort(tp[1]) + ' ' + tp[0];
    } else if (state.view === 'day') {
      big = days[weekday(state.date)] || '';
      small = p[2] + ' ' + (months[p[1] - 1] || '') + ' ' + p[0];
      const rel = relativeDay(state.date);
      if (rel) small = rel + ' · ' + small;
    }
    if (title) title.textContent = big;
    if (sub) sub.textContent = small;
    document.querySelectorAll('.cal-seg-btn').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.view === state.view)));
    const todayBtn = $('#calToday');
    if (todayBtn) todayBtn.classList.toggle('is-here', state.selected === today());
  }

  function rangeItems() {
    const r = neededRange();
    let from = r.from;
    let to = r.to;
    if (state.view === 'month') { from = monthStart(state.date); to = monthEnd(state.date); }
    return state.items.filter((it) => it.date >= from && it.date <= to);
  }

  function renderFilters() {
    const box = $('#calFilters');
    if (!box) return;
    const counts = {};
    rangeItems().forEach((it) => { counts[it.type] = (counts[it.type] || 0) + 1; });
    box.textContent = '';
    TYPES.forEach((type) => {
      const on = state.filters[type] !== false;
      const chip = el('button', {
        class: 'cal-chip t-' + type + (on ? '' : ' is-off'),
        type: 'button',
        'aria-pressed': String(on)
      }, [
        el('span', { class: 'cal-chip-dot', 'aria-hidden': 'true' }),
        el('span', { text: T[FILTER_LABEL[type]] || type }),
        counts[type] ? el('span', { class: 'cal-chip-count', text: String(counts[type]) }) : null
      ]);
      chip.addEventListener('click', () => {
        state.filters[type] = !on;
        render();
        const again = box.querySelector('.t-' + type);
        if (again) again.focus();
      });
      box.appendChild(chip);
    });
  }

  function renderBoard() {
    const need = neededRange();
    boardWrap.setAttribute('aria-busy', String(state.loading));
    boardWrap.classList.toggle('is-loading', state.loading);
    board.textContent = '';

    if (state.error && !covers(need)) {
      board.append(el('div', { class: 'cal-error', role: 'alert' }, [
        el('p', { text: state.error === 'session' || state.error === 'offline' ? errorText(state.error) : T.cal_load_failed }),
        el('button', { class: 'cal-btn cal-btn--primary', type: 'button', text: T.cal_retry, on: { click: () => reload() } })
      ]));
      return;
    }
    if (state.loading && !covers(need)) {
      board.append(el('div', { class: 'cal-skel cal-skel--' + state.view, 'aria-hidden': 'true' }, [el('i'), el('i'), el('i'), el('i'), el('i')]));
      return;
    }

    if (state.view === 'month') renderMonth();
    else if (state.view === 'week') renderWeek();
    else renderDay();
  }

  function renderMonth() {
    const start = mondayOf(monthStart(state.date));
    const end = monthEnd(state.date);
    const month = state.date.slice(0, 7);
    const map = byDate(visible());
    const now = today();

    const head = el('div', { class: 'cal-weekdays', 'aria-hidden': 'true' });
    for (let i = 0; i < 7; i++) head.append(el('span', { text: daysShort[(i + 1) % 7] || '' }));

    const grid = el('div', { class: 'cal-month' });
    let d = start;
    // Whole weeks only: 5 or 6 rows
    const rows = addDays(start, 35) > end ? 5 : 6;
    for (let i = 0; i < rows * 7; i++) {
      const list = map.get(d) || [];
      const dd = d;
      const other = d.slice(0, 7) !== month;
      const cell = el('button', {
        class: 'cal-cell' + (other ? ' is-other' : '') + (d === now ? ' is-today' : '') +
          (d === state.selected ? ' is-selected' : '') + (weekday(d) === 0 ? ' is-sunday' : ''),
        type: 'button',
        'data-date': d,
        'aria-pressed': String(d === state.selected),
        'aria-current': d === now ? 'date' : null,
        'aria-label': dayLong(d) + ', ' + (list.length === 1 ? T.cal_one_item : tr('cal_items', { n: list.length })),
        tabindex: d === state.selected ? '0' : '-1'
      });
      cell.append(el('span', { class: 'cal-cell-num', text: String(+d.slice(8, 10)) }));

      if (list.length) {
        const chips = el('span', { class: 'cal-cell-items', 'aria-hidden': 'true' });
        list.slice(0, 3).forEach((it) => {
          chips.append(el('span', { class: 'cal-mini t-' + it.type }, [
            it.time ? el('b', { text: it.time }) : null,
            document.createTextNode(itemTitle(it))
          ]));
        });
        if (list.length > 3) chips.append(el('span', { class: 'cal-mini-more', text: tr('cal_more', { n: list.length - 3 }) }));

        const dots = el('span', { class: 'cal-cell-dots', 'aria-hidden': 'true' });
        const seen = [];
        list.forEach((it) => { if (seen.indexOf(it.type) === -1) seen.push(it.type); });
        seen.slice(0, 4).forEach((type) => dots.append(el('i', { class: 't-' + type })));
        cell.append(chips, dots, el('span', { class: 'cal-cell-count', 'aria-hidden': 'true', text: String(list.length) }));
      }

      cell.addEventListener('click', () => {
        state.selected = dd;
        if (dd.slice(0, 7) !== month) state.date = dd;
        render();
        const again = board.querySelector('.cal-cell[data-date="' + dd + '"]');
        if (again) again.focus({ preventScroll: true });
        if (window.matchMedia && !window.matchMedia('(min-width: 720px)').matches && agenda) {
          agenda.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'nearest' });
        }
      });
      cell.addEventListener('dblclick', () => { if (!VIEW_MODE) openNewMenu(dd); });
      grid.append(cell);
      d = addDays(d, 1);
    }

    // Arrow keys move through the days
    grid.addEventListener('keydown', (e) => {
      const steps = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 };
      if (!(e.key in steps)) return;
      e.preventDefault();
      state.selected = addDays(state.selected, steps[e.key]);
      if (state.selected.slice(0, 7) !== month) state.date = state.selected;
      load(false).then(() => {
        const again = board.querySelector('.cal-cell[data-date="' + state.selected + '"]');
        if (again) again.focus({ preventScroll: true });
      });
    });

    board.append(head, grid);
  }

  function renderWeek() {
    const start = mondayOf(state.date);
    const map = byDate(visible());
    const now = today();
    const wrap = el('div', { class: 'cal-week' });

    for (let i = 0; i < 7; i++) {
      const d = addDays(start, i);
      const list = map.get(d) || [];
      const col = el('section', {
        class: 'cal-wday' + (d === now ? ' is-today' : '') + (d === state.selected ? ' is-selected' : '') + (weekday(d) === 0 ? ' is-sunday' : ''),
        'aria-label': dayLong(d)
      });
      const headBtn = el('button', {
        class: 'cal-wday-head', type: 'button', 'aria-current': d === now ? 'date' : null,
        'aria-label': dayLong(d) + ', ' + (list.length === 1 ? T.cal_one_item : tr('cal_items', { n: list.length }))
      }, [
        el('span', { class: 'cal-wday-name', text: daysShort[weekday(d)] || '' }),
        el('b', { class: 'cal-wday-num', text: String(+d.slice(8, 10)) }),
        list.length ? el('span', { class: 'cal-wday-count', text: String(list.length) }) : null
      ]);
      headBtn.addEventListener('click', () => { state.selected = d; state.date = d; state.view = 'day'; load(false); });
      const items = el('div', { class: 'cal-wday-items' });
      if (list.length) list.forEach((it) => items.append(itemButton(it, true)));
      else items.append(el('span', { class: 'cal-wday-empty', 'aria-hidden': 'true', text: '–' }));
      col.append(headBtn, items);
      wrap.append(col);
    }
    board.append(wrap);
  }

  function renderDay() {
    board.append(dayList(state.date, true));
  }

  /** The items of one day, all-day ones first, or an empty state */
  function dayList(date, withAdd) {
    const list = visible().filter((it) => it.date === date);
    const box = el('div', { class: 'cal-daylist' });
    if (!list.length) {
      box.append(emptyState(date, withAdd));
      return box;
    }
    const allDay = list.filter((it) => !it.time);
    const timed = list.filter((it) => it.time);
    if (allDay.length) {
      const g = el('div', { class: 'cal-group' }, [el('p', { class: 'cal-group-label', text: T.cal_all_day })]);
      allDay.forEach((it) => g.append(itemButton(it, false)));
      box.append(g);
    }
    if (timed.length) {
      const g = el('div', { class: 'cal-group' });
      if (allDay.length) g.append(el('p', { class: 'cal-group-label', text: T.time }));
      timed.forEach((it) => g.append(itemButton(it, false)));
      box.append(g);
    }
    if (withAdd) {
      const add = addButton(date);
      if (add) box.append(el('div', { class: 'cal-daylist-foot' }, [add]));
    }
    return box;
  }

  function addButton(date) {
    if (VIEW_MODE) {
      if (!CFG.canAppoint || date < today()) return null;
      const a = el('a', {
        class: 'cal-btn cal-btn--glass cal-btn--sm',
        href: '/admin/index.php?tab=afsprake&make_appointment=1&target_user=' + encodeURIComponent(CFG.targetId) + '&date=' + date
      }, [icon('appointment'), el('span', { text: T.cal_make_appointment })]);
      return a;
    }
    return el('button', { class: 'cal-btn cal-btn--glass cal-btn--sm', type: 'button', on: { click: () => openNewMenu(date) } },
      [strokeIcon(ICON_PLUS), el('span', { text: T.cal_add })]);
  }

  function emptyState(date, withAdd) {
    const box = el('div', { class: 'cal-empty' }, [
      el('span', { class: 'cal-empty-orb', 'aria-hidden': 'true' }, [icon('calendar')]),
      el('p', { class: 'cal-empty-text', text: T.cal_nothing_day })
    ]);
    if (!VIEW_MODE && withAdd) box.append(el('p', { class: 'cal-empty-hint', text: T.cal_empty_hint }));
    const add = withAdd ? addButton(date) : null;
    if (add) box.append(add);
    return box;
  }

  function renderAgenda() {
    if (!agenda) return;
    const show = state.view === 'month';
    const list = $('#calList');
    agenda.hidden = !show;
    if (!show) { list.textContent = ''; return; }
    const titleEl = $('#calAgendaTitle');
    const countEl = $('#calAgendaCount');
    const rel = relativeDay(state.selected);
    titleEl.textContent = (rel ? rel + ' · ' : '') + dayLong(state.selected);
    list.textContent = '';
    if (state.loading && !covers(neededRange())) {
      countEl.textContent = '';
      return;
    }
    const n = visible().filter((it) => it.date === state.selected).length;
    countEl.textContent = n ? (n === 1 ? T.cal_one_item : tr('cal_items', { n: n })) : '';
    list.append(dayList(state.selected, true));
  }

  // ===== NAVIGATION =====
  function navigate(dir) {
    if (state.view === 'month') {
      state.date = addMonths(state.date, dir);
      const t = today();
      state.selected = t.slice(0, 7) === state.date.slice(0, 7) ? t : monthStart(state.date);
    } else if (state.view === 'week') {
      state.date = addDays(state.date, dir * 7);
      state.selected = state.date;
    } else {
      state.date = addDays(state.date, dir);
      state.selected = state.date;
    }
    animate(dir);
    load(false);
  }

  const reduceMotion = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  function animate(dir) {
    if (reduceMotion || !boardWrap) return;
    boardWrap.classList.remove('slide-l', 'slide-r');
    void boardWrap.offsetWidth;
    boardWrap.classList.add(dir > 0 ? 'slide-l' : 'slide-r');
  }

  function goToday() {
    state.date = today();
    state.selected = state.date;
    load(false);
  }

  function setView(v) {
    if (VIEWS.indexOf(v) === -1) return;
    state.view = v;
    state.date = state.selected;
    load(false);
  }

  // Swipe sideways on the board to move a month / week / day
  function enableSwipe() {
    if (!boardWrap) return;
    let x0 = 0;
    let y0 = 0;
    let t0 = 0;
    let tracking = false;
    boardWrap.addEventListener('touchstart', (e) => {
      if (e.touches.length !== 1) { tracking = false; return; }
      x0 = e.touches[0].clientX;
      y0 = e.touches[0].clientY;
      t0 = Date.now();
      tracking = true;
    }, { passive: true });
    boardWrap.addEventListener('touchend', (e) => {
      if (!tracking) return;
      tracking = false;
      const t = e.changedTouches[0];
      const dx = t.clientX - x0;
      const dy = t.clientY - y0;
      if (Math.abs(dx) > 60 && Math.abs(dx) > Math.abs(dy) * 1.6 && Date.now() - t0 < 700) {
        navigate(dx < 0 ? 1 : -1);
      }
    }, { passive: true });
  }

  // ===== DETAIL SHEET =====
  function detailRow(iconId, label, value, extraClass) {
    if (!value) return null;
    return el('div', { class: 'cal-dl-row' + (extraClass ? ' ' + extraClass : '') }, [
      icon(iconId),
      el('div', {}, [label ? el('dt', { text: label }) : null, el('dd', { text: value })])
    ]);
  }

  function action(label, iconNode, onClick, cls) {
    const b = el('button', { class: 'cal-action' + (cls ? ' ' + cls : ''), type: 'button' }, [iconNode, el('span', { text: label })]);
    b.addEventListener('click', () => onClick(b));
    return b;
  }

  function actionLink(label, iconNode, href) {
    return el('a', { class: 'cal-action', href: href }, [iconNode, el('span', { text: label })]);
  }

  function openDetail(it) {
    const typeChip = el('span', { class: 'cal-type-chip t-' + it.type }, [icon(TYPE_ICON[it.type]), el('span', { text: T[TYPE_LABEL[it.type]] || '' })]);
    const layer = sheet(itemTitle(it), { type: it.type, eyebrow: typeChip });
    const body = layer.body;

    const when = dayLong(it.date) + ' · ' + (it.time || T.cal_all_day);
    const dl = el('dl', { class: 'cal-dl' }, [
      detailRow('clock', T.cal_when, when),
      it.type === 'event' ? detailRow('room', T.room, it.room_name) : null,
      detailRow('pin', T.location, it.location),
      (it.type === 'visit' || it.type === 'appointment') && it.person_name && it.person_name !== it.title
        ? detailRow('person', T.with_whom, it.person_name) : null,
      it.type === 'appointment' ? detailRow('appointment', T.cal_status, it.status === 'pending' ? T.cal_status_pending : T.cal_status_confirmed, it.status === 'pending' ? 'is-pending' : '') : null,
      it.type === 'event' && it.author_name ? detailRow('person', '', tr('cal_by', { name: it.author_name })) : null,
      it.is_shared ? detailRow('share', '', T.cal_shared_by_spouse) : null
    ]);
    body.append(dl);
    if (it.notes) body.append(el('p', { class: 'cal-notes', text: it.notes }));
    if (it.type === 'event') body.append(el('p', { class: 'cal-hint', text: T.cal_room_event_note }));

    if (VIEW_MODE) {
      const add = addButton(it.date);
      if (add) body.append(el('div', { class: 'cal-actions' }, [add]));
      return;
    }

    const actions = el('div', { class: 'cal-actions' });

    if (it.type === 'appointment' && it.can_confirm) {
      actions.append(action(T.cal_confirm, strokeIcon(ICON_CHECK), (b) => answerAppointment(it, 'confirm', b, layer), 'is-primary'));
    }
    if ((it.type === 'event' || it.type === 'visit') && it.can_edit) {
      actions.append(action(T.edit, strokeIcon(ICON_EDIT), () => { layer.close(); openForm(it.type, it); }));
    }
    if (it.type === 'event' && it.can_remind) {
      actions.append(action(T.cal_send_reminder, icon('bell'), (b) => sendReminder(it, b)));
    }
    if (it.type !== 'birthday') {
      actions.append(action(T.cal_add_to_phone, icon('phone'), () => addToPhone(it)));
    }
    if ((it.type === 'event' || it.type === 'visit') && !it.is_shared && state.spouse.has_spouse && (it.type === 'event' || it.can_edit)) {
      actions.append(shareToggle(it));
    }
    if (it.type === 'event') {
      actions.append(actionLink(T.cal_open_in_gospel, strokeIcon(ICON_OPEN), '/gospel_media/gospel.php?room_id=' + encodeURIComponent(it.room_id) + '#post-' + it.id));
    }
    if (it.type === 'diary') {
      actions.append(actionLink(T.cal_open_in_diary, icon('diary'), '/diary/diary.php?entry=' + encodeURIComponent(it.id)));
    }
    if (it.type === 'appointment' && it.can_cancel) {
      actions.append(action(T.cal_cancel_appt, strokeIcon(ICON_X), (b) => answerAppointment(it, 'cancel', b, layer), 'is-danger'));
    }
    if ((it.type === 'event' || it.type === 'visit') && it.can_delete) {
      actions.append(action(T.delete, strokeIcon(ICON_TRASH), () => removeItem(it, layer), 'is-danger'));
    }
    if (actions.children.length) body.append(actions);
  }

  function shareToggle(it) {
    const on = !!it.shared;
    const b = el('button', { class: 'cal-action cal-action--toggle', type: 'button', 'aria-pressed': String(on) }, [
      icon('share'),
      el('span', { text: on ? tr('cal_shared_with', { name: state.spouse.spouse_name }) : T.share_with_spouse }),
      el('span', { class: 'cal-switch', 'aria-hidden': 'true' })
    ]);
    b.addEventListener('click', async () => {
      setBusy(b, true);
      const next = b.getAttribute('aria-pressed') !== 'true';
      const res = await post('/calendar/api/share.php', { type: it.type, id: it.id, share: next ? '1' : '0' });
      setBusy(b, false);
      if (!res.ok) { toast(errorText(res.error), true); return; }
      it.shared = next;
      b.setAttribute('aria-pressed', String(next));
      b.querySelector('span:not(.cal-switch)').textContent = next ? tr('cal_shared_with', { name: state.spouse.spouse_name }) : T.share_with_spouse;
      toast(T.saved_success);
    });
    return b;
  }

  // ===== ACTIONS =====
  /** Phone calendar: the app's own "new event" screen if it has one, else an .ics file */
  function addToPhone(it) {
    const native = window.NativeApp && typeof window.NativeApp.addToCalendar === 'function';
    if (native) {
      const p = parts(it.date);
      let begin;
      let end;
      const allDay = !it.time;
      if (allDay) {
        begin = Date.UTC(p[0], p[1] - 1, p[2]);
        end = begin + 864e5;
      } else {
        const hm = it.time.split(':');
        begin = Date.UTC(p[0], p[1] - 1, p[2], +hm[0], +hm[1]) - 2 * 3600e3; // SAST → UTC
        end = begin + 3600e3;
      }
      try {
        const opened = window.NativeApp.addToCalendar(itemTitle(it), String(begin), String(end), allDay,
          it.location || '', [it.room_name, it.notes].filter(Boolean).join('\n\n'));
        if (opened === true || opened === 'true') return;
      } catch (e) { /* older app: fall back to the file */ }
    }
    window.location.href = '/calendar/api/ics.php?type=' + encodeURIComponent(it.type) + '&id=' + encodeURIComponent(it.id);
    toast(T.cal_ics_ready);
  }

  async function sendReminder(it, btn) {
    setBusy(btn, true);
    const res = await post('/calendar/api/remind.php', { event_id: it.id });
    setBusy(btn, false);
    if (!res.ok) { toast(errorText(res.error), true); return; }
    toast(T.cal_reminder_sent);
  }

  async function answerAppointment(it, act, btn, layer) {
    if (act === 'cancel') {
      const sure = await confirmCard(T.cal_cancel_appt_q, T.cal_cancel_appt_text, T.cal_cancel_appt, true);
      if (!sure) return;
    }
    setBusy(btn, true);
    const res = await post('/calendar/api/appointment.php', { action: act, id: it.id });
    setBusy(btn, false);
    if (!res.ok) { toast(errorText(res.error), true); return; }
    layer.close();
    toast(act === 'confirm' ? T.cal_appt_confirmed : T.cal_appt_cancelled);
    reload();
  }

  async function removeItem(it, layer) {
    const isEvent = it.type === 'event';
    const sure = await confirmCard(isEvent ? T.cal_delete_event_q : T.cal_delete_q,
      (isEvent ? T.cal_delete_event_text + ' ' : '') + T.cal_cannot_undo, T.delete, true);
    if (!sure) return;
    const res = isEvent
      ? await post('/gospel_media/api/posts/delete.php', { post_id: it.id })
      : await post('/calendar/api/delete.php', { type: 'visit', id: it.id });
    if (!res.ok) { toast(errorText(res.error), true); return; }
    layer.close();
    toast(T.deleted_success);
    reload();
  }

  // ===== NEW =====
  async function loadRooms() {
    if (state.rooms) return state.rooms;
    const res = await api('/calendar/api/rooms.php');
    if (res.ok && res.data && Array.isArray(res.data.rooms)) state.rooms = res.data.rooms;
    return state.rooms || [];
  }

  async function loadUsers() {
    if (state.users) return state.users;
    const res = await api('/calendar/api/users.php');
    if (res.ok && res.data && Array.isArray(res.data.users)) state.users = res.data.users;
    return state.users || [];
  }

  async function openNewMenu(date) {
    date = isDate(date) ? date : state.selected;
    const layer = sheet(T.cal_new, { script: true });
    const list = el('div', { class: 'cal-choices' });
    layer.body.append(el('p', { class: 'cal-sheet-sub', text: dayLong(date) }), list);

    const choice = (type, title, hint, onClick, disabled) => {
      const b = el('button', { class: 'cal-choice t-' + type, type: 'button', disabled: disabled ? true : null }, [
        el('span', { class: 'cal-choice-orb', 'aria-hidden': 'true' }, [icon(TYPE_ICON[type])]),
        el('span', { class: 'cal-choice-text' }, [el('b', { text: title }), el('span', { text: hint })])
      ]);
      b.addEventListener('click', onClick);
      return b;
    };

    const eventSlot = el('div', { class: 'cal-choice-slot' }, [el('span', { class: 'cal-choice cal-choice--skel', 'aria-hidden': 'true' })]);
    list.append(
      eventSlot,
      choice('visit', T.cal_new_visit, T.cal_new_visit_hint, () => { layer.close(); openForm('visit', null, date); }),
      choice('diary', T.cal_new_diary, T.cal_new_diary_hint, () => {
        window.location.href = '/diary/diary.php?date=' + encodeURIComponent(date);
      })
    );
    const firstChoice = list.querySelector('.cal-choice.t-visit');
    if (firstChoice) firstChoice.focus();

    const rooms = await loadRooms();
    eventSlot.textContent = '';
    if (rooms.length) {
      eventSlot.append(choice('event', T.cal_new_event, T.cal_new_event_hint, () => { layer.close(); openForm('event', null, date); }));
    } else {
      eventSlot.append(el('p', { class: 'cal-hint', text: T.cal_no_rooms }));
    }
  }

  // ===== FORMS =====
  function field(label, input, id, extra) {
    input.id = id;
    return el('div', { class: 'cal-field' + (extra ? ' ' + extra : '') }, [
      el('label', { class: 'cal-field-label', for: id, text: label }),
      input,
      el('p', { class: 'cal-field-error', id: id + 'Err', role: 'alert', hidden: true })
    ]);
  }

  function showFieldError(form, id, message) {
    const input = form.querySelector('#' + id);
    const err = form.querySelector('#' + id + 'Err');
    if (input) { input.setAttribute('aria-invalid', 'true'); input.setAttribute('aria-describedby', id + 'Err'); input.focus(); }
    if (err) { err.textContent = message; err.hidden = false; }
  }

  function clearErrors(form) {
    form.querySelectorAll('[aria-invalid]').forEach((n) => n.removeAttribute('aria-invalid'));
    form.querySelectorAll('.cal-field-error').forEach((n) => { n.hidden = true; n.textContent = ''; });
  }

  /** Split a post into title (first line) and notes, keeping the separator */
  function splitText(text) {
    text = String(text || '').replace(/\r\n?/g, '\n'); // form posts store CRLF
    const i = text.indexOf('\n');
    if (i === -1) return { title: text.trim(), notes: '', sep: '\n\n' };
    const rest = text.slice(i);
    const sep = (rest.match(/^\n+/) || ['\n\n'])[0];
    return { title: text.slice(0, i).trim(), notes: rest.slice(sep.length), sep: sep };
  }

  async function openForm(type, it, date) {
    const editing = !!it;
    const isEvent = type === 'event';
    const layer = sheet(editing ? (isEvent ? T.cal_edit_event : T.cal_edit_visit) : (isEvent ? T.cal_new_event : T.cal_new_visit),
      { type: type, script: true, cls: 'cal-sheet--form' });
    const form = el('form', { class: 'cal-form', novalidate: true });
    layer.body.append(form);

    const split = isEvent && editing ? splitText(it.text || it.title) : { title: it ? it.title : '', notes: it ? (it.notes || '') : '', sep: '\n\n' };
    const title = el('input', { class: 'cal-input', type: 'text', name: 'title', autocomplete: 'off', maxlength: isEvent ? null : '150',
      placeholder: isEvent ? T.cal_title_event_ph : T.cal_title_visit_ph, autofocus: true });
    title.value = split.title;

    const dateIn = el('input', { class: 'cal-input', type: 'date', name: 'date', required: true });
    dateIn.value = it ? it.date : (date || state.selected);
    const timeIn = el('input', { class: 'cal-input', type: 'time', name: 'time' });
    timeIn.value = it && it.time ? it.time : (isEvent ? '' : '');

    form.append(field(T.title, title, 'calFTitle'));
    form.append(el('div', { class: 'cal-row' }, [field(T.date, dateIn, 'calFDate'), field(T.time, timeIn, 'calFTime')]));

    let allDay = null;
    if (!isEvent) {
      allDay = el('input', { type: 'checkbox', id: 'calFAllDay' });
      allDay.checked = !!(it && !it.time);
      const syncAllDay = () => { timeIn.disabled = allDay.checked; if (allDay.checked) timeIn.value = ''; };
      allDay.addEventListener('change', syncAllDay);
      syncAllDay();
      form.append(el('label', { class: 'cal-check', for: 'calFAllDay' }, [allDay, el('span', { class: 'cal-check-box', 'aria-hidden': 'true' }), el('span', { text: T.cal_all_day })]));
    }

    let roomSel = null;
    if (isEvent) {
      roomSel = el('select', { class: 'cal-input cal-select', name: 'room_id' }, [el('option', { value: '', text: T.select_room })]);
      if (editing) {
        roomSel.append(el('option', { value: String(it.room_id), text: it.room_name }));
        roomSel.value = String(it.room_id);
        roomSel.disabled = true; // a post stays in its room (as in Gospel Media)
      } else {
        const rooms = await loadRooms();
        rooms.forEach((r) => roomSel.append(el('option', { value: String(r.id), text: r.name })));
        if (rooms.length === 1) roomSel.value = String(rooms[0].id);
      }
      form.append(field(T.room, roomSel, 'calFRoom'));
    }

    // Visit: who with — someone from the list, or a typed name
    let personSel = null;
    let personText = null;
    let modeList = true;
    if (!isEvent) {
      personSel = el('select', { class: 'cal-input cal-select', name: 'linked_user_id' }, [el('option', { value: '', text: T.select_user })]);
      personText = el('input', { class: 'cal-input', type: 'text', name: 'person_name', maxlength: '120', placeholder: T.type_name, autocomplete: 'off' });
      modeList = !(it && !it.linked_user_id && it.person_name);
      const btnList = el('button', { type: 'button', class: 'cal-seg-btn', 'aria-pressed': String(modeList), text: T.cal_from_list });
      const btnName = el('button', { type: 'button', class: 'cal-seg-btn', 'aria-pressed': String(!modeList), text: T.name });
      const listWrap = el('div', { class: 'cal-person-pick' }, [personSel]);
      const nameWrap = el('div', { class: 'cal-person-pick' }, [personText]);
      const sync = () => {
        btnList.setAttribute('aria-pressed', String(modeList));
        btnName.setAttribute('aria-pressed', String(!modeList));
        listWrap.hidden = !modeList;
        nameWrap.hidden = modeList;
      };
      btnList.addEventListener('click', () => { modeList = true; sync(); personSel.focus(); });
      btnName.addEventListener('click', () => { modeList = false; sync(); personText.focus(); });
      sync();
      personSel.id = 'calFPerson';
      form.append(el('div', { class: 'cal-field' }, [
        el('span', { class: 'cal-field-label', id: 'calFWhoLabel', text: T.with_whom }),
        el('div', { class: 'cal-seg cal-seg--small', role: 'group', 'aria-labelledby': 'calFWhoLabel' }, [btnList, btnName]),
        listWrap, nameWrap,
        el('p', { class: 'cal-field-error', id: 'calFPersonErr', role: 'alert', hidden: true })
      ]));
      if (it && !it.linked_user_id && it.person_name) personText.value = it.person_name;
      loadUsers().then((users) => {
        users.forEach((u) => personSel.append(el('option', { value: String(u.id), text: (u.name + ' ' + u.surname).trim() })));
        if (it && it.linked_user_id) personSel.value = String(it.linked_user_id);
      });
    }

    const place = el('input', { class: 'cal-input', type: 'text', name: 'location', maxlength: '200', autocomplete: 'off' });
    place.value = it && it.location ? it.location : '';
    form.append(field(T.location, place, 'calFPlace'));

    const notes = el('textarea', { class: 'cal-input cal-textarea', name: 'notes', rows: '4', maxlength: isEvent ? null : '5000' });
    notes.value = split.notes;
    form.append(field(T.notes, notes, 'calFNotes'));

    let share = null;
    if (state.spouse.has_spouse) {
      share = el('input', { type: 'checkbox', id: 'calFShare' });
      share.checked = !!(it && it.shared);
      form.append(el('label', { class: 'cal-check', for: 'calFShare' }, [share, el('span', { class: 'cal-check-box', 'aria-hidden': 'true' }),
        el('span', { text: T.share_with_spouse + (state.spouse.spouse_name ? ' (' + state.spouse.spouse_name + ')' : '') })]));
    }

    if (isEvent) form.append(el('p', { class: 'cal-hint', text: T.cal_room_event_note }));

    const saveBtn = el('button', { class: 'cal-btn cal-btn--primary', type: 'submit' }, [strokeIcon(ICON_CHECK), el('span', { text: T.save })]);
    const actions = el('div', { class: 'cal-form-actions' }, [
      el('button', { class: 'cal-btn cal-btn--glass', type: 'button', text: T.cancel, on: { click: () => layer.close() } }),
      saveBtn
    ]);
    form.append(actions);

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      clearErrors(form);
      const vTitle = title.value.trim();
      const vDate = dateIn.value;
      const vTime = timeIn.value;
      if (!vTitle) { showFieldError(form, 'calFTitle', T.cal_err_title); return; }
      if (!isDate(vDate)) { showFieldError(form, 'calFDate', T.cal_err_date); return; }
      if (isEvent && !/^\d{2}:\d{2}$/.test(vTime)) { showFieldError(form, 'calFTime', T.cal_err_time_event); return; }
      if (!isEvent && !allDay.checked && !/^\d{2}:\d{2}$/.test(vTime)) { showFieldError(form, 'calFTime', T.cal_err_time); return; }
      if (isEvent && !editing && !roomSel.value) { showFieldError(form, 'calFRoom', T.cal_err_room); return; }

      setBusy(saveBtn, true, T.cal_saving);
      let res;
      let newId = it ? it.id : 0;
      if (isEvent) {
        const text = vTitle + (notes.value.trim() ? split.sep + notes.value.replace(/\s+$/, '') : '');
        const fields = { text: text, event_at: vDate + 'T' + vTime, event_place: place.value.trim() };
        if (editing) {
          fields.post_id = it.id;
          res = await post('/gospel_media/api/posts/update.php', fields);
        } else {
          fields.room_id = roomSel.value;
          fields.type = 'event';
          res = await post('/gospel_media/api/posts/create.php', fields);
          if (res.ok) newId = +res.data.post_id || 0;
        }
        if (res.ok && share && newId && share.checked !== !!(it && it.shared)) {
          await post('/calendar/api/share.php', { type: 'event', id: newId, share: share.checked ? '1' : '0' });
        }
      } else {
        res = await post('/calendar/api/upsert.php', {
          type: 'visit',
          id: editing ? it.id : '',
          title: vTitle,
          date: vDate,
          time: allDay.checked ? '' : vTime,
          all_day: allDay.checked ? '1' : '0',
          linked_user_id: modeList ? personSel.value : '',
          person_name: modeList ? '' : personText.value.trim(),
          location: place.value.trim(),
          notes: notes.value,
          share_with_spouse: share && share.checked ? '1' : '0'
        });
      }
      setBusy(saveBtn, false);

      if (!res.ok) {
        const code = res.error;
        if (code === 'title_required' || code === 'empty_post') showFieldError(form, 'calFTitle', T.cal_err_title);
        else if (code === 'invalid_date') showFieldError(form, 'calFDate', T.cal_err_date);
        else if (code === 'invalid_time') showFieldError(form, 'calFTime', T.cal_err_time);
        else if (code === 'invalid_person') showFieldError(form, 'calFPerson', T.cal_err_person);
        toast(errorText(code), true);
        return;
      }
      layer.close();
      toast(T.saved_success);
      state.selected = vDate;
      state.date = vDate;
      reload();
    });
  }

  // ===== START =====
  function bind() {
    const prev = $('#calPrev');
    const next = $('#calNext');
    const todayBtn = $('#calToday');
    if (prev) prev.addEventListener('click', () => navigate(-1));
    if (next) next.addEventListener('click', () => navigate(1));
    if (todayBtn) todayBtn.addEventListener('click', goToday);
    document.querySelectorAll('.cal-seg-btn[data-view]').forEach((b) => b.addEventListener('click', () => setView(b.dataset.view)));

    const newBtn = $('#calNew');
    const fab = $('#calFab');
    if (newBtn) newBtn.addEventListener('click', () => openNewMenu(state.selected));
    if (fab) {
      if (VIEW_MODE) fab.hidden = true;
      else fab.addEventListener('click', () => openNewMenu(state.selected));
    }

    // Keyboard: ← → move, T jumps to today (not while typing or in a sheet)
    document.addEventListener('keydown', (e) => {
      if (layers.length || e.altKey || e.ctrlKey || e.metaKey) return;
      const tag = (e.target && e.target.tagName) || '';
      if (/INPUT|TEXTAREA|SELECT/.test(tag) || (e.target && e.target.closest && e.target.closest('.cal-month'))) return;
      if (e.key === 'ArrowLeft') navigate(-1);
      else if (e.key === 'ArrowRight') navigate(1);
      else if (e.key === 't' || e.key === 'T') goToday();
    });

    window.addEventListener('hashchange', () => {
      const uid = readHash();
      if (uid) pendingItem = uid;
      load(true);
    });

    // Back after a while (or after editing in Gospel Media): fresh data
    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'visible' && Date.now() - state.loadedAt > 120000) reload();
    });

    enableSwipe();
  }

  async function loadSpouse() {
    if (VIEW_MODE) return;
    const res = await api('/calendar/api/spouse_status.php');
    if (!res.data) return;
    state.spouse = res.data;
    const banner = $('#calBanner');
    if (banner) banner.hidden = !(res.data.pending_requests > 0);
  }

  bind();
  loadSpouse();
  load(true);
})();
