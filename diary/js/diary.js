/**
 * Diary — the list, the reader and the writer.
 *
 * Reads:  /diary/api/list.php (pages of excerpts), get.php (one whole entry)
 * Writes: create.php, update.php, delete.php; ai_enhance.php only suggests
 * Files:  export.php (PDF or text download, also in the app)
 * Every POST carries the session CSRF token (X-CSRF-Token).
 *
 * The reader and the writer are full pages below the header with their own
 * history entry, so the phone's Back button, the app's and the floating
 * back button all close them. Unsaved writing is kept on this device until
 * it is saved or discarded (a lost connection or session loses nothing).
 * Dates are plain "YYYY-MM-DD" strings in South African time; the browser
 * never parses a date string (iOS Safari safe).
 */
(function () {
  'use strict';

  const CFG = window.DIARY;
  if (!CFG) return;
  const T = CFG.t || {};
  const NS = 'http://www.w3.org/2000/svg';
  const MOOD_EMOJI = { happy: '😊', sad: '😢', excited: '🤗', calm: '😌', thoughtful: '🤔', grateful: '🙏', inspired: '✨', tired: '😴' };
  const WEATHER_EMOJI = { sunny: '☀️', cloudy: '☁️', rainy: '🌧️', stormy: '⛈️', snowy: '❄️', windy: '🌬️' };
  const LIMITS = CFG.limits || { title: 150, body: 60000, tag: 24, tags: 10 };

  // ===== STRINGS =====
  function tr(key, params) {
    let s = T[key] || key;
    if (params) {
      for (const p in params) s = s.split('{' + p + '}').join(String(params[p]));
    }
    return s;
  }

  const ERRORS = {
    csrf: 'dy_err_session', session: 'dy_err_session', offline: 'dy_err_offline', not_found: 'dy_err_not_found',
    invalid_date: 'dy_err_date', invalid_time: 'dy_err_time', empty_entry: 'dy_err_empty', too_long: 'dy_err_too_long',
    rate_limited: 'dy_err_rate', ai_unavailable: 'dy_ai_unavailable', ai_failed: 'dy_ai_unavailable', ai_too_long: 'dy_ai_too_long'
  };
  const errorText = (code) => tr(ERRORS[code] || 'dy_err_generic');

  // ===== DOM HELPERS =====
  const $ = (s, root) => (root || document).querySelector(s);

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
    u.setAttribute('href', '#dy-i-' + id);
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
  const ICON_CLOSE = 'M6 6l12 12M18 6L6 18';
  const ICON_EDIT = 'M12 20h9M16.5 3.5a2.12 2.12 0 013 3L7 19l-4 1 1-4 12.5-12.5z';
  const ICON_TRASH = 'M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 11v6M14 11v6';
  const ICON_SHARE = 'M4 12v7a2 2 0 002 2h12a2 2 0 002-2v-7M16 6l-4-4-4 4M12 2v13';
  const ICON_COPY = 'M8 4h10a2 2 0 012 2v12M16 8H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2V10a2 2 0 00-2-2z';
  const ICON_PREV = 'M15 18l-6-6 6-6';
  const ICON_NEXT = 'M9 18l6-6-6-6';
  const ICON_CHECK = 'M5 12l5 5L20 7';
  const ICON_PLUS = 'M12 5v14M5 12h14';
  const ICON_UNDO = 'M9 14L4 9l5-5M4 9h10.5a5.5 5.5 0 010 11H11';

  // ===== DATES (strings in, strings out) =====
  const pad = (n) => String(n).padStart(2, '0');
  const parts = (s) => [+s.slice(0, 4), +s.slice(5, 7), +s.slice(8, 10)];
  const isDate = (s) => typeof s === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(s);
  const isTime = (s) => typeof s === 'string' && /^([01]\d|2[0-3]):[0-5]\d$/.test(s);
  function toDate(s) { const p = parts(s); return new Date(p[0], p[1] - 1, p[2], 12); }
  function ymd(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
  function addDays(s, n) { const d = toDate(s); d.setDate(d.getDate() + n); return ymd(d); }
  function realDate(s) { return isDate(s) && ymd(toDate(s)) === s; }

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
  const weekday = (s) => toDate(s).getDay();
  function dayLong(s) {
    const p = parts(s);
    return (days[weekday(s)] || '') + ' ' + p[2] + ' ' + (months[p[1] - 1] || '') + ' ' + p[0];
  }
  function dayMedium(s) {
    const p = parts(s);
    return p[2] + ' ' + String(months[p[1] - 1] || '').slice(0, 3) + ' ' + p[0];
  }
  function monthLabel(key) {
    return (months[+key.slice(5, 7) - 1] || '') + ' ' + key.slice(0, 4);
  }
  function relativeDay(s) {
    if (s === today()) return T.today;
    if (s === addDays(today(), -1)) return T.dy_yesterday;
    return '';
  }
  /** "2026-10-25 14:03:11" (server time, SAST) → "25 Okt 2026" */
  function stampDay(stamp) {
    const d = String(stamp || '').slice(0, 10);
    return isDate(d) ? dayMedium(d) : '';
  }

  // ===== API =====
  async function api(url, opts) {
    const o = Object.assign({ credentials: 'same-origin' }, opts || {});
    o.headers = Object.assign({ Accept: 'application/json' }, o.headers || {});
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
    const ok = res.ok && data.success === true;
    return { ok: ok, data: data, status: res.status, error: ok ? '' : (data.error || 'server_error') };
  }

  function postJSON(url, body) {
    return api(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CFG.csrf || '' },
      body: JSON.stringify(body)
    });
  }

  // ===== STORAGE (a convenience; the page works without it) =====
  function store(key, value) {
    try {
      if (value === undefined) return JSON.parse(localStorage.getItem('dy_' + key) || 'null');
      if (value === null) localStorage.removeItem('dy_' + key);
      else localStorage.setItem('dy_' + key, JSON.stringify(value));
    } catch (e) { /* private mode, full storage */ }
    return null;
  }

  // Drafts are per account, so someone else on this phone never sees them
  const draftKey = (id) => 'draft_' + CFG.userId + '_' + (id || 'new');
  function readDraft(id) {
    const d = store(draftKey(id));
    if (!d || typeof d !== 'object') return null;
    if (!d.ts || Date.now() - d.ts > 14 * 864e5) { store(draftKey(id), null); return null; }
    return d;
  }

  // ===== STATE =====
  const state = {
    q: '',
    period: '',
    tag: '',
    sort: store('sort') === 'oldest' ? 'oldest' : 'newest',
    entries: [],
    otd: [],
    tags: [],
    total: 0,
    hasMore: false,
    loading: false,
    error: '',
    req: 0,
    loadedAt: 0
  };
  const filtered = () => !!(state.q || state.period || state.tag);

  // ===== TOAST =====
  let toastTimer = 0;
  function toast(message, isError) {
    const t = $('#dyToast');
    if (!t || !message) return;
    t.textContent = message;
    t.classList.toggle('is-error', !!isError);
    t.classList.add('is-on');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => t.classList.remove('is-on'), isError ? 4800 : 2600);
  }

  // ===== SHEETS (small questions above everything) =====
  // One stack: Escape and the scrim close the top sheet, Tab stays inside it,
  // focus returns to where it was.
  const layers = [];

  function openLayer(panel, labelledBy) {
    const back = el('div', { class: 'dy-layer' });
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
      setTimeout(() => { if (back.parentNode) back.parentNode.removeChild(back); }, 180);
      const i = layers.indexOf(layer);
      if (i !== -1) layers.splice(i, 1);
      lockScroll();
      if (layer.onClose) layer.onClose();
      if (prevFocus && prevFocus.focus && document.body.contains(prevFocus)) {
        try { prevFocus.focus({ preventScroll: true }); } catch (e) { prevFocus.focus(); }
      }
    }

    layer.close = close;
    layers.push(layer);
    back.addEventListener('click', (e) => { if (e.target === back) close(); });
    document.body.appendChild(back);
    lockScroll();
    enableSwipeDown(panel, close);

    const first = panel.querySelector('[autofocus]') || panel;
    try { first.focus({ preventScroll: true }); } catch (e) { first.focus(); }
    return layer;
  }

  // Phones: pull a sheet down by its handle to close it
  function enableSwipeDown(panel, close) {
    const grip = panel.querySelector('.dy-sheet-grip');
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
    const id = 'dySheet' + Math.random().toString(36).slice(2, 8);
    const panel = el('div', { class: 'dy-sheet' + (opts.cls ? ' ' + opts.cls : '') });
    const head = el('div', { class: 'dy-sheet-head' }, [
      el('h2', { class: 'dy-sheet-title', id: id, text: title }),
      el('button', { class: 'dy-sheet-close', type: 'button', 'aria-label': T.close, on: { click: () => layer.close() } }, [strokeIcon(ICON_CLOSE)])
    ]);
    const body = el('div', { class: 'dy-sheet-body' });
    panel.append(el('span', { class: 'dy-sheet-grip', 'aria-hidden': 'true' }), head, body);
    const layer = openLayer(panel, id);
    layer.body = body;
    return layer;
  }

  /** A confirm card instead of confirm() (which shows the site address in the app) */
  function confirmCard(title, text, okLabel, cancelLabel, danger) {
    return new Promise((resolve) => {
      const panel = el('div', { class: 'dy-sheet dy-confirm', role: 'alertdialog' });
      const id = 'dyConfirm' + Math.random().toString(36).slice(2, 8);
      let answered = false;
      const ok = el('button', { class: 'dy-btn ' + (danger ? 'dy-btn--danger' : 'dy-btn--primary'), type: 'button', text: okLabel });
      const cancel = el('button', { class: 'dy-btn dy-btn--glass', type: 'button', text: cancelLabel || T.cancel, autofocus: true });
      panel.append(
        el('h2', { class: 'dy-sheet-title', id: id, text: title }),
        text ? el('p', { class: 'dy-confirm-text', text: text }) : null,
        el('div', { class: 'dy-form-actions' }, [cancel, ok])
      );
      const layer = openLayer(panel, id);
      const finish = (v) => { if (answered) return; answered = true; layer.close(); resolve(v); };
      layer.onClose = () => { if (!answered) { answered = true; resolve(false); } };
      ok.addEventListener('click', () => finish(true));
      cancel.addEventListener('click', () => finish(false));
    });
  }

  function action(label, iconNode, onClick, hint, cls) {
    return el('button', { class: 'dy-action' + (cls ? ' ' + cls : ''), type: 'button', on: { click: onClick } }, [
      iconNode,
      el('span', { class: 'dy-action-text' }, [el('b', { text: label }), hint ? el('small', { text: hint }) : null])
    ]);
  }

  function setBusy(btn, busy, label) {
    if (!btn) return;
    btn.disabled = !!busy;
    btn.classList.toggle('is-busy', !!busy);
    const span = btn.querySelector('span') || btn;
    if (busy && label !== undefined) {
      btn.dataset.label = span.textContent;
      span.textContent = label;
    } else if (!busy && btn.dataset.label) {
      span.textContent = btn.dataset.label;
      delete btn.dataset.label;
    }
  }

  // Background scrolling stops while a page or sheet is open
  function lockScroll() {
    document.body.classList.toggle('dy-locked', !!(layers.length || page));
  }

  // ===== COPY (works in WebViews without clipboard permission) =====
  function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(text).catch(() => copyLegacy(text));
    }
    return copyLegacy(text);
  }

  function copyLegacy(text) {
    return new Promise((resolve, reject) => {
      const ta = el('textarea', { readonly: true, 'aria-hidden': 'true', class: 'dy-copy-buffer' });
      ta.value = text;
      document.body.appendChild(ta);
      ta.select();
      try {
        if (document.execCommand('copy')) resolve(); else reject(new Error('copy'));
      } catch (e) {
        reject(e);
      } finally {
        ta.remove();
      }
    });
  }

  // ===== TEXT HELPERS =====
  /** Text with the search words marked, without ever using innerHTML */
  function marked(text, words) {
    const frag = document.createDocumentFragment();
    text = String(text || '');
    if (!words.length || !text) { frag.append(text); return frag; }
    const escaped = words.map((w) => w.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
    const re = new RegExp('(' + escaped.join('|') + ')', 'gi');
    let last = 0;
    text.replace(re, (m, g, i) => {
      if (i > last) frag.append(text.slice(last, i));
      frag.append(el('mark', { text: m }));
      last = i + m.length;
      return m;
    });
    if (last < text.length) frag.append(text.slice(last));
    return frag;
  }
  const searchWords = () => state.q.split(/\s+/).filter((w) => w.length > 0).slice(0, 5);

  function wordsLabel(n) {
    return n === 1 ? T.dy_word_one : tr('dy_words_n', { n: n });
  }

  function countWords(text) {
    const m = String(text || '').match(/[\p{L}\p{N}][\p{L}\p{N}'’-]*/gu);
    return m ? m.length : 0;
  }

  const byteLength = (s) => (window.TextEncoder ? new TextEncoder().encode(s).length : unescape(encodeURIComponent(s)).length);

  function entryTitle(e) {
    return e.title || T.dy_no_title;
  }

  function whenText(e) {
    const rel = relativeDay(e.date);
    return (rel ? rel + ' · ' : '') + dayLong(e.date) + (e.time ? ' · ' + e.time : '');
  }

  /** The text that goes out when sharing or copying an entry */
  function entryAsText(e) {
    const lines = [whenText(e)];
    if (e.title) lines.push(e.title);
    lines.push('', e.body || '');
    return lines.join('\n').trim();
  }

  // ===== LIST: LOADING =====
  function queryString(extra) {
    const p = new URLSearchParams();
    if (state.q) p.set('q', state.q);
    if (state.period) p.set('period', state.period);
    if (state.tag) p.set('tag', state.tag);
    if (state.sort === 'oldest') p.set('sort', 'oldest');
    for (const k in (extra || {})) p.set(k, extra[k]);
    const s = p.toString();
    return s ? '?' + s : '';
  }

  async function load(more) {
    const req = ++state.req;
    const offset = more ? state.entries.length : 0;
    state.loading = true;
    state.error = '';
    if (!more) renderList();
    setBusy($('#dyMore'), true);

    const res = await api('/diary/api/list.php' + queryString({ offset: offset }));
    if (req !== state.req) return; // a newer search took over
    state.loading = false;
    setBusy($('#dyMore'), false);

    if (!res.ok) {
      if (more) { toast(errorText(res.error), true); return; }
      state.error = res.error;
      state.entries = [];
      renderList();
      return;
    }
    const d = res.data;
    state.entries = more ? state.entries.concat(d.entries || []) : (d.entries || []);
    state.total = d.total || 0;
    state.hasMore = !!d.has_more;
    state.loadedAt = Date.now();
    if (!more) {
      state.otd = d.on_this_day || [];
      state.tags = d.tags || [];
    }
    renderList();
  }

  const reload = () => load(false);

  // ===== LIST: DRAWING =====
  function renderFilters() {
    document.querySelectorAll('.dy-chip[data-period]').forEach((b) => {
      b.setAttribute('aria-pressed', b.dataset.period === state.period ? 'true' : 'false');
    });
    const box = $('#dyFilters');
    const old = $('.dy-chip--tag', box);
    if (old) old.remove();
    if (state.tag) {
      box.append(el('button', {
        class: 'dy-chip dy-chip--tag', type: 'button', 'aria-pressed': 'true',
        'aria-label': T.dy_remove_filter + ': #' + state.tag,
        on: { click: () => { state.tag = ''; renderFilters(); reload(); } }
      }, [el('span', { text: tr('dy_tag_filter', { tag: state.tag }) }), strokeIcon(ICON_CLOSE, 'dy-chip-x')]));
    }
    const sortLabel = $('#dySortLabel');
    if (sortLabel) sortLabel.textContent = state.sort === 'oldest' ? T.oldest_first : T.newest_first;
    const clear = $('#dySearchClear');
    if (clear) clear.hidden = !$('#dySearch').value;
  }

  function stamp(date) {
    const p = parts(date);
    return el('span', { class: 'dy-stamp', 'aria-hidden': 'true' }, [
      el('b', { text: String(p[2]) }),
      el('small', { text: daysShort[weekday(date)] || '' })
    ]);
  }

  function entryCard(e) {
    const words = searchWords();
    const meta = [];
    if (e.mood) meta.push(el('span', { class: 'dy-entry-emoji', title: T['mood_' + e.mood], text: MOOD_EMOJI[e.mood] || '' }));
    if (e.weather) meta.push(el('span', { class: 'dy-entry-emoji', title: T['weather_' + e.weather], text: WEATHER_EMOJI[e.weather] || '' }));
    (e.tags || []).slice(0, 3).forEach((t) => meta.push(el('span', { class: 'dy-entry-tag', text: '#' + t })));
    if ((e.tags || []).length > 3) meta.push(el('span', { class: 'dy-entry-tag', text: '+' + (e.tags.length - 3) }));

    const rel = relativeDay(e.date);
    const when = (rel ? rel + ' · ' : '') + dayMedium(e.date) + (e.time ? ' · ' + e.time : '');
    const title = el('h3', { class: 'dy-entry-title' + (e.title ? '' : ' is-untitled') });
    title.append(marked(entryTitle(e), e.title ? words : []));
    const excerpt = e.excerpt ? el('p', { class: 'dy-entry-excerpt' }) : null;
    if (excerpt) excerpt.append(marked(e.excerpt, words));

    const a = el('a', {
      class: 'dy-entry' + (e.date === today() ? ' is-today' : ''), href: '#e=' + e.id, 'data-id': e.id,
      on: { click: (ev) => { ev.preventDefault(); openReader(e.id); } }
    }, [
      stamp(e.date),
      el('span', { class: 'dy-entry-body' }, [
        el('span', { class: 'dy-entry-when', text: when }),
        title,
        excerpt,
        meta.length ? el('span', { class: 'dy-entry-meta' }, meta) : null
      ])
    ]);
    return a;
  }

  function emptyState(kind) {
    const box = el('div', { class: 'dy-card dy-empty' });
    if (kind === 'error') {
      box.append(
        el('div', { class: 'dy-empty-orb' }, [icon('notes')]),
        el('p', { class: 'dy-empty-text', text: T.dy_load_failed }),
        el('p', { class: 'dy-empty-hint', text: errorText(state.error) }),
        el('button', { class: 'dy-btn dy-btn--glass', type: 'button', on: { click: reload } }, [el('span', { text: T.dy_retry })])
      );
    } else if (kind === 'none') {
      box.append(
        el('div', { class: 'dy-empty-orb' }, [strokeIcon('M11 4a7 7 0 100 14 7 7 0 000-14zM20 20l-3.5-3.5')]),
        el('p', { class: 'dy-empty-text', text: T.dy_none_found }),
        el('p', { class: 'dy-empty-hint', text: T.dy_none_found_hint }),
        el('button', { class: 'dy-btn dy-btn--glass', type: 'button', on: { click: clearFilters } }, [el('span', { text: T.dy_show_all })])
      );
    } else {
      box.classList.add('dy-empty--first');
      box.append(
        el('div', { class: 'dy-empty-orb dy-empty-orb--lit' }, [icon('book')]),
        el('p', { class: 'dy-empty-text', text: T.dy_empty_title }),
        el('p', { class: 'dy-empty-hint', text: T.dy_empty_hint }),
        el('button', { class: 'dy-btn dy-btn--primary', type: 'button', on: { click: () => openWriter(null, {}) } }, [
          icon('diary'), el('span', { text: T.dy_write_first })
        ])
      );
    }
    return box;
  }

  function renderOtd() {
    const box = $('#dyOtd');
    const list = $('#dyOtdList');
    if (!box || !list) return;
    const show = !filtered() && state.otd.length > 0 && !state.error;
    box.hidden = !show;
    if (!show) return;
    const year = +today().slice(0, 4);
    list.replaceChildren(...state.otd.map((e) => {
      const ago = year - +e.date.slice(0, 4);
      return el('a', {
        class: 'dy-otd-item', href: '#e=' + e.id,
        on: { click: (ev) => { ev.preventDefault(); openReader(e.id); } }
      }, [
        el('span', { class: 'dy-otd-ago', text: ago === 1 ? T.dy_year_ago : tr('dy_years_ago', { n: ago }) }),
        // No title: the first words say more than "Untitled" (as in the calendar)
        el('span', { class: 'dy-otd-title' + (e.title ? '' : ' is-excerpt'), text: e.title || e.excerpt || T.dy_no_title })
      ]);
    }));
  }

  function renderList() {
    renderFilters();
    renderOtd();
    const list = $('#dyList');
    const count = $('#dyCount');
    const moreWrap = $('#dyMoreWrap');
    list.setAttribute('aria-busy', state.loading ? 'true' : 'false');
    list.classList.toggle('is-loading', state.loading);

    if (state.loading && !state.entries.length) {
      list.replaceChildren(el('div', { class: 'dy-skel', 'aria-hidden': 'true' }, [el('i'), el('i'), el('i')]), el('p', { class: 'dy-sr', text: T.loading_entries }));
      moreWrap.hidden = true;
      return;
    }
    if (state.error) {
      count.textContent = '';
      list.replaceChildren(emptyState('error'));
      moreWrap.hidden = true;
      return;
    }
    if (!state.entries.length) {
      count.textContent = '';
      list.replaceChildren(emptyState(filtered() ? 'none' : 'first'));
      moreWrap.hidden = true;
      return;
    }

    if (filtered()) count.textContent = state.total === 1 ? T.dy_found_one : tr('dy_found_n', { n: state.total });
    else count.textContent = state.total === 1 ? T.dy_entry_one : tr('dy_entries_n', { n: state.total });

    // Grouped by month, like the pages of a journal
    const groups = [];
    let current = null;
    state.entries.forEach((e) => {
      const key = e.date.slice(0, 7);
      if (!current || current.key !== key) {
        current = { key: key, items: [] };
        groups.push(current);
      }
      current.items.push(e);
    });
    list.replaceChildren(...groups.map((g) => el('section', { class: 'dy-month' }, [
      el('h3', { class: 'dy-month-label', text: monthLabel(g.key) }),
      el('div', { class: 'dy-month-items' }, g.items.map(entryCard))
    ])));
    moreWrap.hidden = !state.hasMore;
  }

  function clearFilters() {
    state.q = '';
    state.period = '';
    state.tag = '';
    const input = $('#dySearch');
    if (input) input.value = '';
    reload();
  }

  /** After a save: the list shows the entry as it is now */
  function patchEntry(entry) {
    const short = Object.assign({}, entry, { excerpt: String(entry.body || '').replace(/\s+/g, ' ').trim().slice(0, 280) });
    const i = state.entries.findIndex((e) => e.id === entry.id);
    if (i === -1) return false;
    state.entries[i] = short;
    const dir = state.sort === 'oldest' ? 1 : -1;
    state.entries.sort((a, b) => {
      const ka = a.date + ' ' + (a.time || '00:00');
      const kb = b.date + ' ' + (b.time || '00:00');
      if (ka !== kb) return ka < kb ? -dir : dir;
      return (a.id - b.id) * dir;
    });
    renderList();
    return true;
  }

  function removeEntry(id) {
    const before = state.entries.length;
    state.entries = state.entries.filter((e) => e.id !== id);
    state.otd = state.otd.filter((e) => e.id !== id);
    if (state.entries.length !== before) state.total = Math.max(0, state.total - 1);
    renderList();
  }

  // ===== PAGES (reader and writer, full screen below the header) =====
  // Each open page has its own history entry; Back (or Close) pops it and
  // the popstate handler draws what the history now says.
  let page = null; // { kind, id, node, ... }
  let skipGuard = false;

  function pageNode(kind, labelId) {
    const node = el('div', { class: 'dy-page dy-page--' + kind, role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': labelId, tabindex: '-1' });
    const scroll = el('div', { class: 'dy-page-scroll' });
    const inner = el('div', { class: 'dy-page-inner' });
    const bar = el('div', { class: 'dy-page-bar' });
    scroll.append(inner);
    node.append(scroll, bar);
    return { node: node, scroll: scroll, inner: inner, bar: bar };
  }

  function showPage(p) {
    const prev = page;
    page = p;
    document.body.appendChild(p.node);
    if (prev) {
      if (prev.cleanup) prev.cleanup();
      if (prev.node.parentNode) prev.node.remove();
    }
    lockScroll();
  }

  function hidePage() {
    if (!page) return;
    if (page.cleanup) page.cleanup();
    const node = page.node;
    page = null;
    node.classList.add('is-closing');
    setTimeout(() => node.remove(), 200);
    lockScroll();
  }

  function pushPage(s, hash) {
    try {
      if (history.state && history.state.dy) history.replaceState(s, '', hash);
      else history.pushState(s, '', hash);
    } catch (e) { /* old WebView: pages still work, Back just leaves the diary */ }
  }

  /** Close the open page through history, so Back and Close behave the same */
  function closePage() {
    if (history.state && history.state.dy) history.back();
    else route(null);
  }

  function route(s) {
    if (!s || !s.dy) {
      if (page) {
        const returnTo = page.kind === 'read' ? page.id : null;
        hidePage();
        if (returnTo) {
          const card = document.querySelector('.dy-entry[data-id="' + returnTo + '"]');
          if (card) { try { card.focus({ preventScroll: false }); } catch (e) { card.focus(); } }
        }
      }
      return;
    }
    if (s.dy === 'read') renderReader(s.id);
    else if (s.dy === 'write') renderWriter(s);
  }

  window.addEventListener('popstate', (e) => {
    const next = e.state;
    // Back closes the sheet on top first, and the page stays
    if (!skipGuard && page && layers.length) {
      while (layers.length) layers[layers.length - 1].close();
      try { history.pushState(page.state, '', page.hash); } catch (err) { /* keep going */ }
      return;
    }
    // Leaving the writer with unsaved changes: ask first (the text is kept on this device anyway)
    if (!skipGuard && page && page.kind === 'write' && page.dirty() && !(next && next.dy === 'write')) {
      try { history.pushState(page.state, '', page.hash); } catch (err) { /* keep going */ }
      askDiscard();
      return;
    }
    skipGuard = false;
    while (layers.length) layers[layers.length - 1].close();
    route(next);
  });

  async function askDiscard() {
    const yes = await confirmCard(T.dy_discard_q, T.dy_discard_text, T.dy_discard, T.dy_keep_writing, true);
    if (!yes || !page || page.kind !== 'write') return;
    if (page.cleanup) page.cleanup(); // no late draft save after this
    page.dirty = () => false;
    store(draftKey(page.id), null);
    skipGuard = true;
    history.back();
  }

  // ===== READER =====
  function openReader(id) {
    pushPage({ dy: 'read', id: id }, '#e=' + id);
    renderReader(id);
  }

  async function renderReader(id) {
    const labelId = 'dyReadTitle';
    const p = pageNode('read', labelId);
    p.kind = 'read';
    p.id = id;
    p.state = { dy: 'read', id: id };
    p.hash = '#e=' + id;
    p.inner.append(el('div', { class: 'dy-read-skel', 'aria-hidden': 'true' }, [el('i'), el('i'), el('i'), el('i')]), el('h2', { class: 'dy-sr', id: labelId, text: T.dy_reading }));
    showPage(p);
    try { p.node.focus({ preventScroll: true }); } catch (e) { p.node.focus(); }

    const res = await api('/diary/api/get.php?id=' + encodeURIComponent(id));
    if (page !== p) return;
    if (!res.ok) {
      p.inner.replaceChildren(el('div', { class: 'dy-card dy-empty' }, [
        el('div', { class: 'dy-empty-orb' }, [icon('notes')]),
        el('p', { class: 'dy-empty-text', id: labelId, text: errorText(res.error) }),
        res.error === 'not_found' ? null : el('button', { class: 'dy-btn dy-btn--glass', type: 'button', on: { click: () => renderReader(id) } }, [el('span', { text: T.dy_retry })])
      ]));
      p.bar.replaceChildren(barButton(T.close, ICON_CLOSE, closePage, 'is-primary'));
      if (res.error === 'not_found') removeEntry(id);
      return;
    }
    const e = res.data.entry;
    p.entry = e;
    drawReader(p, e, labelId);
  }

  function barButton(label, d, onClick, cls) {
    return el('button', { class: 'dy-bar-btn' + (cls ? ' ' + cls : ''), type: 'button', on: { click: onClick } }, [strokeIcon(d), el('span', { text: label })]);
  }

  function neighbours(id) {
    const i = state.entries.findIndex((x) => x.id === id);
    if (i === -1) return { prev: null, next: null };
    // "Previous" is the older page of the journal, whatever the list order
    const older = state.sort === 'oldest' ? state.entries[i - 1] : state.entries[i + 1];
    const newer = state.sort === 'oldest' ? state.entries[i + 1] : state.entries[i - 1];
    return { prev: older || null, next: newer || null };
  }

  function goTo(entry) {
    if (!entry) return;
    pushPage({ dy: 'read', id: entry.id }, '#e=' + entry.id);
    renderReader(entry.id);
  }

  function drawReader(p, e, labelId) {
    const nb = neighbours(e.id);
    const tags = (e.tags || []).map((t) => el('button', {
      class: 'dy-pill dy-pill--tag', type: 'button',
      on: { click: () => { state.tag = t; state.q = ''; $('#dySearch').value = ''; closePage(); reload(); } }
    }, [el('span', { text: '#' + t })]));
    const pills = [];
    if (e.mood) pills.push(el('span', { class: 'dy-pill' }, [el('span', { class: 'dy-pill-emoji', 'aria-hidden': 'true', text: MOOD_EMOJI[e.mood] }), el('span', { text: T['mood_' + e.mood] })]));
    if (e.weather) pills.push(el('span', { class: 'dy-pill' }, [el('span', { class: 'dy-pill-emoji', 'aria-hidden': 'true', text: WEATHER_EMOJI[e.weather] }), el('span', { text: T['weather_' + e.weather] })]));

    const paras = String(e.body || '').split(/\n{2,}/).filter((s) => s.trim() !== '')
      .map((s) => el('p', { text: s }));

    const footBits = [wordsLabel(e.words || 0)];
    if (e.created_at) footBits.push(tr('dy_written', { date: stampDay(e.created_at) }));
    if (e.updated_at && String(e.updated_at).slice(0, 16) !== String(e.created_at || '').slice(0, 16)) {
      footBits.push(tr('dy_edited', { date: stampDay(e.updated_at) }));
    }

    const navBtn = (entry, d, label) => el('button', {
      class: 'dy-nav-btn', type: 'button', 'aria-label': label, disabled: !entry,
      on: { click: () => goTo(entry) }
    }, [strokeIcon(d)]);

    const article = el('article', { class: 'dy-read' }, [
      el('div', { class: 'dy-read-top' }, [
        navBtn(nb.prev, ICON_PREV, T.dy_prev),
        el('p', { class: 'dy-read-when' }, [icon('calendar'), el('span', { text: whenText(e) })]),
        navBtn(nb.next, ICON_NEXT, T.dy_next)
      ]),
      el('h2', { class: 'dy-read-title' + (e.title ? '' : ' is-untitled'), id: labelId, text: entryTitle(e) }),
      pills.length || tags.length ? el('div', { class: 'dy-read-pills' }, pills.concat(tags)) : null,
      el('div', { class: 'dy-read-body' }, paras),
      el('footer', { class: 'dy-read-foot' }, [
        el('span', { text: footBits.join(' · ') }),
        el('a', { class: 'dy-link', href: '/calendar/calendar.php#d=' + encodeURIComponent(e.date) + '&i=diary-' + e.id }, [icon('calendar'), el('span', { text: T.dy_open_calendar })])
      ])
    ]);
    p.inner.replaceChildren(article);

    p.bar.replaceChildren(
      barButton(T.edit, ICON_EDIT, () => openWriter(e, { from: 'read' })),
      barButton(T.share, ICON_SHARE, () => openShare(e)),
      barButton(T.delete, ICON_TRASH, () => removeFlow(e), 'is-danger'),
      barButton(T.close, ICON_CLOSE, closePage, 'is-primary')
    );
    enableReaderSwipe(p, nb);
  }

  // Swipe sideways through the pages of the journal
  function enableReaderSwipe(p, nb) {
    let x0 = 0;
    let y0 = 0;
    let tracking = false;
    p.scroll.addEventListener('touchstart', (e) => {
      if (e.touches.length !== 1) { tracking = false; return; }
      x0 = e.touches[0].clientX;
      y0 = e.touches[0].clientY;
      tracking = true;
    }, { passive: true });
    p.scroll.addEventListener('touchend', (e) => {
      if (!tracking) return;
      tracking = false;
      const t = e.changedTouches[0];
      const dx = t.clientX - x0;
      const dy = t.clientY - y0;
      if (Math.abs(dx) < 70 || Math.abs(dx) < Math.abs(dy) * 2) return;
      const sel = window.getSelection ? String(window.getSelection()) : '';
      if (sel) return; // selecting text, not turning a page
      if (dx < 0 && nb.next) goTo(nb.next);
      else if (dx > 0 && nb.prev) goTo(nb.prev);
    }, { passive: true });
  }

  async function removeFlow(e) {
    const yes = await confirmCard(T.dy_delete_q, T.dy_cannot_undo, T.delete, T.cancel, true);
    if (!yes) return;
    const res = await postJSON('/diary/api/delete.php', { id: e.id });
    if (!res.ok && res.error !== 'not_found') {
      toast(errorText(res.error), true);
      return;
    }
    store(draftKey(e.id), null);
    removeEntry(e.id);
    toast(T.dy_deleted);
    if (page && page.kind === 'read' && page.id === e.id) closePage();
  }

  // ===== SHARE / DOWNLOAD =====
  function startDownload(url) {
    // A plain navigation: the browser and the app's download manager both handle it
    window.location.href = url;
    toast(T.dy_downloading);
  }

  function openShare(e) {
    const layer = sheet(T.dy_share_title);
    const text = entryAsText(e);
    const list = el('div', { class: 'dy-actions' });
    if (navigator.share) {
      list.append(action(T.dy_share_text, strokeIcon(ICON_SHARE), async () => {
        try {
          await navigator.share({ title: entryTitle(e), text: text });
          layer.close();
        } catch (err) { /* cancelled */ }
      }, T.dy_share_text_hint));
    }
    list.append(action(T.dy_copy_text, strokeIcon(ICON_COPY), () => {
      copyText(text).then(() => { layer.close(); toast(T.copied); }, () => toast(T.dy_copy_failed, true));
    }, navigator.share ? null : T.dy_share_text_hint));
    list.append(action(T.dy_download_pdf, icon('pdf'), () => {
      layer.close();
      startDownload('/diary/api/export.php?format=pdf&id=' + encodeURIComponent(e.id));
    }));
    layer.body.append(list);
  }

  function openExport() {
    if (!state.total) {
      toast(filtered() ? T.dy_none_found : T.dy_empty_title, true);
      return;
    }
    const layer = sheet(T.dy_export_title);
    layer.body.append(
      el('p', { class: 'dy-hint', text: tr('dy_export_hint', { n: state.total }) }),
      el('div', { class: 'dy-actions' }, [
        action(T.dy_export_pdf, icon('pdf'), () => { layer.close(); startDownload('/diary/api/export.php' + queryString({ format: 'pdf' })); }, T.dy_export_pdf_hint),
        action(T.dy_export_txt, icon('notes'), () => { layer.close(); startDownload('/diary/api/export.php' + queryString({ format: 'txt' })); }, T.dy_export_txt_hint)
      ])
    );
  }

  // ===== WRITER =====
  /**
   * opts.from: 'read' when editing from the reader; opts.date / opts.time
   * start a new entry on that day (calendar links).
   */
  function openWriter(entry, opts) {
    opts = opts || {};
    const s = {
      dy: 'write',
      id: entry ? entry.id : null,
      from: opts.from || 'list',
      date: opts.date || null,
      time: opts.time || null
    };
    const hash = '#w=' + (s.id || 'new');
    if (s.from === 'read') {
      try { history.pushState(s, '', hash); } catch (e) { /* old WebView */ }
    } else {
      pushPage(s, hash);
    }
    renderWriter(s, entry);
  }

  async function renderWriter(s, known) {
    const labelId = 'dyWriteTitle';
    const p = pageNode('write', labelId);
    p.kind = 'write';
    p.id = s.id;
    p.state = s;
    p.hash = '#w=' + (s.id || 'new');
    p.dirty = () => false;
    showPage(p);

    let entry = known && known.body !== undefined ? known : null;
    if (s.id && !entry) {
      p.inner.append(el('div', { class: 'dy-read-skel', 'aria-hidden': 'true' }, [el('i'), el('i'), el('i')]));
      const res = await api('/diary/api/get.php?id=' + encodeURIComponent(s.id));
      if (page !== p) return;
      if (!res.ok) {
        // Nothing to edit, but a draft for it would still be on this device
        p.inner.replaceChildren(el('div', { class: 'dy-card dy-empty' }, [
          el('p', { class: 'dy-empty-text', id: labelId, text: errorText(res.error) })
        ]));
        p.bar.replaceChildren(barButton(T.close, ICON_CLOSE, closePage, 'is-primary'));
        return;
      }
      entry = res.data.entry;
    }
    drawWriter(p, s, entry, labelId);
  }

  function drawWriter(p, s, entry, labelId) {
    const now = sastNow();
    const startDate = entry ? entry.date : (realDate(s.date) ? s.date : now.date);
    const startTime = entry ? (entry.time || '') : (isTime(s.time) ? s.time : (startDate === now.date ? now.time : ''));
    const base = {
      date: startDate,
      time: startTime,
      title: entry ? entry.title || '' : '',
      body: entry ? entry.body || '' : '',
      mood: entry ? entry.mood || '' : '',
      weather: entry ? entry.weather || '' : '',
      tags: entry ? (entry.tags || []).slice() : []
    };
    const snapshot = JSON.stringify(base);

    // A draft left on this device wins (the explicit day from a calendar link still counts)
    const draft = readDraft(s.id);
    const restored = !!(draft && JSON.stringify(draft.fields) !== snapshot);
    const v = restored ? Object.assign({}, base, draft.fields) : Object.assign({}, base);
    if (restored && !entry && realDate(s.date)) v.date = s.date;
    v.tags = Array.isArray(v.tags) ? v.tags.slice(0, LIMITS.tags) : [];

    // ----- fields -----
    const form = el('form', { class: 'dy-form', novalidate: true });
    const heading = el('h2', { class: 'dy-write-heading', id: labelId, text: entry ? T.dy_edit_entry : T.new_entry });

    const notice = el('div', { class: 'dy-notice', hidden: !restored, role: 'status' });
    function showNotice(text, btnLabel, onClick, iconD) {
      notice.replaceChildren(el('span', { text: text }), btnLabel ? el('button', { class: 'dy-link-btn', type: 'button', on: { click: onClick } }, [iconD ? strokeIcon(iconD) : null, el('span', { text: btnLabel })]) : null);
      notice.hidden = false;
    }
    if (restored) {
      showNotice(T.dy_draft_restored, T.dy_draft_discard, () => {
        store(draftKey(s.id), null);
        renderWriter(s, entry);
      }, ICON_UNDO);
    }

    const date = el('input', { class: 'dy-input', type: 'date', id: 'dyDate', required: true, value: v.date });
    const time = el('input', { class: 'dy-input', type: 'time', id: 'dyTime', value: v.time });
    const title = el('input', {
      class: 'dy-input dy-input--title', type: 'text', id: 'dyTitleIn', maxlength: LIMITS.title,
      placeholder: T.my_thoughts_today, autocomplete: 'off', enterkeyhint: 'next', value: v.title
    });
    const body = el('textarea', { class: 'dy-input dy-textarea', id: 'dyBody', rows: '8', placeholder: T.write_thoughts_here });
    body.value = v.body;
    const words = el('span', { class: 'dy-words', 'aria-live': 'polite' });

    const fieldErr = (id) => el('p', { class: 'dy-field-error', id: id + 'Err', role: 'alert', hidden: true });

    let mood = v.mood;
    let weather = v.weather;
    let tags = v.tags.slice();

    function picker(kind, list, emoji, current, set) {
      const wrap = el('div', { class: 'dy-picker', role: 'group', 'aria-label': kind === 'mood' ? T.mood : T.weather });
      list.forEach((key) => {
        const b = el('button', {
          class: 'dy-pick', type: 'button', 'aria-pressed': key === current ? 'true' : 'false',
          on: {
            click: () => {
              const on = b.getAttribute('aria-pressed') !== 'true';
              wrap.querySelectorAll('.dy-pick').forEach((x) => x.setAttribute('aria-pressed', 'false'));
              if (on) b.setAttribute('aria-pressed', 'true');
              set(on ? key : '');
              changed();
            }
          }
        }, [el('span', { class: 'dy-pick-emoji', 'aria-hidden': 'true', text: emoji[key] }), el('span', { text: T[kind + '_' + key] })]);
        wrap.append(b);
      });
      return wrap;
    }

    // ----- tags -----
    const tagList = el('div', { class: 'dy-tags' });
    const tagInput = el('input', {
      class: 'dy-input dy-tag-input', type: 'text', id: 'dyTagIn', maxlength: LIMITS.tag,
      placeholder: T.dy_tag_ph, autocomplete: 'off', enterkeyhint: 'done', list: 'dyTagSuggest'
    });
    const suggest = el('datalist', { id: 'dyTagSuggest' }, state.tags.map((t) => el('option', { value: t.tag })));
    const tagAdd = el('button', { class: 'dy-icon-btn', type: 'button', 'aria-label': T.dy_add_tag }, [strokeIcon(ICON_PLUS)]);

    function cleanTag(t) {
      return String(t || '').replace(/["\\#,[\]]/g, ' ').replace(/\s+/g, ' ').trim().slice(0, LIMITS.tag);
    }
    function drawTags() {
      tagList.replaceChildren(...tags.map((t) => el('span', { class: 'dy-tag' }, [
        el('span', { text: '#' + t }),
        el('button', {
          class: 'dy-tag-x', type: 'button', 'aria-label': tr('dy_remove_tag', { tag: t }),
          on: { click: () => { tags = tags.filter((x) => x !== t); drawTags(); changed(); tagInput.focus(); } }
        }, [strokeIcon(ICON_CLOSE)])
      ])));
      tagInput.disabled = tags.length >= LIMITS.tags;
      tagAdd.disabled = tags.length >= LIMITS.tags;
    }
    function addTag() {
      const parts2 = tagInput.value.split(',');
      let added = false;
      parts2.forEach((raw) => {
        const t = cleanTag(raw);
        if (!t || tags.length >= LIMITS.tags) return;
        if (tags.some((x) => x.toLowerCase() === t.toLowerCase())) return;
        tags.push(t);
        added = true;
      });
      tagInput.value = '';
      if (added) { drawTags(); changed(); }
    }
    tagInput.addEventListener('keydown', (ev) => {
      if (ev.key === 'Enter' || ev.key === ',') { ev.preventDefault(); addTag(); }
      else if (ev.key === 'Backspace' && !tagInput.value && tags.length) { tags.pop(); drawTags(); changed(); }
    });
    tagInput.addEventListener('change', () => { if (tagInput.value.trim()) addTag(); });
    tagAdd.addEventListener('click', () => { addTag(); tagInput.focus(); });
    drawTags();

    // ----- layout -----
    const label = (forId, text, extra) => el('label', { class: 'dy-field-label', for: forId }, [el('span', { text: text }), extra ? el('small', { text: extra }) : null]);
    const groupLabel = (text) => el('p', { class: 'dy-field-label' }, [el('span', { text: text })]);

    form.append(
      heading,
      notice,
      el('div', { class: 'dy-row' }, [
        el('div', { class: 'dy-field' }, [label('dyDate', T.date), date, fieldErr('dyDate')]),
        el('div', { class: 'dy-field' }, [label('dyTime', T.time, T.dy_optional), time, fieldErr('dyTime')])
      ]),
      el('div', { class: 'dy-field' }, [label('dyTitleIn', T.title, T.dy_optional), title]),
      el('div', { class: 'dy-field dy-field--body' }, [
        el('label', { class: 'dy-sr', for: 'dyBody', text: T.dy_writing }),
        body,
        el('div', { class: 'dy-body-foot' }, [words]),
        fieldErr('dyBody')
      ]),
      el('div', { class: 'dy-field' }, [groupLabel(T.mood), picker('mood', CFG.moods || [], MOOD_EMOJI, mood, (x) => { mood = x; })]),
      el('div', { class: 'dy-field' }, [groupLabel(T.weather), picker('weather', CFG.weathers || [], WEATHER_EMOJI, weather, (x) => { weather = x; })]),
      el('div', { class: 'dy-field' }, [
        label('dyTagIn', T.tags, T.dy_optional),
        tagList,
        el('div', { class: 'dy-tag-row' }, [tagInput, tagAdd]),
        suggest
      ]),
      el('p', { class: 'dy-hint dy-hint--calendar' }, [icon('calendar'), el('span', { text: T.dy_calendar_note })])
    );
    p.inner.replaceChildren(form);

    // ----- state -----
    function values() {
      return {
        date: date.value,
        time: time.value,
        title: title.value,
        body: body.value,
        mood: mood,
        weather: weather,
        tags: tags.slice()
      };
    }
    p.dirty = () => JSON.stringify(values()) !== snapshot;

    let draftTimer = 0;
    function saveDraft() {
      clearTimeout(draftTimer);
      if (p.dirty()) store(draftKey(s.id), { ts: Date.now(), fields: values() });
      else store(draftKey(s.id), null);
    }
    function changed() {
      clearTimeout(draftTimer);
      draftTimer = setTimeout(saveDraft, 500);
    }
    function grow() {
      body.style.height = 'auto';
      body.style.height = Math.max(body.scrollHeight + 2, 220) + 'px';
    }
    function updateWords() {
      words.textContent = wordsLabel(countWords(body.value));
    }
    [date, time, title].forEach((n) => n.addEventListener('input', changed));
    body.addEventListener('input', () => { grow(); updateWords(); changed(); });
    title.addEventListener('keydown', (ev) => { if (ev.key === 'Enter') { ev.preventDefault(); body.focus(); } });
    updateWords();
    requestAnimationFrame(grow);
    // Phones and the app may be put away at any moment: keep the text now
    document.addEventListener('visibilitychange', saveDraft);
    p.cleanup = () => {
      clearTimeout(draftTimer);
      document.removeEventListener('visibilitychange', saveDraft);
    };

    // ----- errors -----
    function clearErrors() {
      form.querySelectorAll('[aria-invalid]').forEach((n) => n.removeAttribute('aria-invalid'));
      form.querySelectorAll('.dy-field-error').forEach((n) => { n.hidden = true; n.textContent = ''; });
    }
    function fieldError(input, message) {
      const err = form.querySelector('#' + input.id + 'Err');
      input.setAttribute('aria-invalid', 'true');
      if (err) { err.textContent = message; err.hidden = false; input.setAttribute('aria-describedby', err.id); }
      try { input.focus({ preventScroll: false }); } catch (e) { input.focus(); }
    }

    // ----- save -----
    const saveBtn = barButton(T.save, ICON_CHECK, () => save(), 'is-primary');
    const aiBtn = barButton(T.ai_assist, '', () => askAi(), 'is-ai');
    aiBtn.querySelector('svg').replaceWith(icon('spark'));
    const cancelBtn = barButton(T.cancel, ICON_CLOSE, closePage);
    p.bar.replaceChildren(cancelBtn, aiBtn, saveBtn);

    let saving = false;
    async function save() {
      if (saving) return;
      clearErrors();
      const val = values();
      if (!realDate(val.date)) return fieldError(date, T.dy_err_date);
      if (val.time && !isTime(val.time)) return fieldError(time, T.dy_err_time);
      if (!val.title.trim() && !val.body.trim()) return fieldError(body, T.dy_err_empty);
      if (byteLength(val.body) > LIMITS.body) return fieldError(body, T.dy_err_too_long);
      if (tagInput.value.trim()) { addTag(); val.tags = tags.slice(); }

      saving = true;
      saveDraft();
      setBusy(saveBtn, true, T.dy_saving);
      const payload = Object.assign({}, val, { id: s.id || undefined });
      const res = await postJSON(s.id ? '/diary/api/update.php' : '/diary/api/create.php', payload);
      saving = false;
      setBusy(saveBtn, false);
      if (page !== p) return;

      if (!res.ok) {
        if (res.error === 'invalid_date') return fieldError(date, T.dy_err_date);
        if (res.error === 'invalid_time') return fieldError(time, T.dy_err_time);
        if (res.error === 'empty_entry' || res.error === 'too_long') return fieldError(body, errorText(res.error));
        const keep = ['session', 'csrf', 'offline', 'server_error'].indexOf(res.error) !== -1 ? ' ' + T.dy_draft_kept : '';
        toast(errorText(res.error) + keep, true);
        return;
      }

      store(draftKey(s.id), null);
      const saved = res.data.entry;
      toast(T.dy_saved);
      p.dirty = () => false;
      if (p.cleanup) p.cleanup();

      if (s.id) {
        if (!patchEntry(saved)) reload();
      } else {
        reload();
      }
      if (s.from === 'read') {
        // Back to the reader underneath, which draws the entry afresh
        skipGuard = true;
        history.back();
      } else {
        // A new entry: show it; Back then returns to the list
        const next = { dy: 'read', id: saved.id };
        try { history.replaceState(next, '', '#e=' + saved.id); } catch (e) { /* old WebView */ }
        renderReader(saved.id);
      }
    }
    p.save = save;

    // ----- AI suggestion -----
    let undoText = null;
    async function askAi() {
      clearErrors();
      const text = body.value.trim();
      if (!text) return fieldError(body, T.dy_err_empty);
      const layer = sheet(T.dy_ai_title, { cls: 'dy-sheet--ai' });
      const box = el('div', { class: 'dy-ai-box', 'aria-live': 'polite' }, [el('p', { class: 'dy-ai-wait', text: T.dy_ai_working })]);
      layer.body.append(box);
      const res = await postJSON('/diary/api/ai_enhance.php', { text: text });
      if (!layer.back.parentNode) return; // closed while waiting
      if (!res.ok) {
        box.replaceChildren(el('p', { class: 'dy-field-error', text: errorText(res.error) }));
        return;
      }
      const suggestion = res.data.text;
      box.replaceChildren(el('div', { class: 'dy-ai-text', tabindex: '0', text: suggestion }));
      const keep = el('button', { class: 'dy-btn dy-btn--glass', type: 'button', text: T.dy_ai_keep, on: { click: () => layer.close() } });
      const use = el('button', {
        class: 'dy-btn dy-btn--primary', type: 'button', text: T.dy_ai_use,
        on: {
          click: () => {
            undoText = body.value;
            body.value = suggestion;
            grow();
            updateWords();
            changed();
            layer.close();
            showNotice(T.dy_ai_used, T.dy_undo, () => {
              if (undoText === null) return;
              body.value = undoText;
              undoText = null;
              grow();
              updateWords();
              changed();
              notice.hidden = true;
            }, ICON_UNDO);
          }
        }
      });
      layer.body.append(el('p', { class: 'dy-hint', text: T.dy_ai_hint }), el('div', { class: 'dy-form-actions' }, [keep, use]));
      try { use.focus({ preventScroll: true }); } catch (e) { use.focus(); }
    }

    // New entries start typing straight away; edits wait for a tap
    if (!entry) {
      try { title.focus({ preventScroll: true }); } catch (e) { title.focus(); }
    } else {
      try { p.node.focus({ preventScroll: true }); } catch (e) { p.node.focus(); }
    }
  }

  // ===== START =====
  function bind() {
    const write = () => openWriter(null, {});
    const writeBtn = $('#dyWrite');
    const fab = $('#dyFab');
    if (writeBtn) writeBtn.addEventListener('click', write);
    if (fab) fab.addEventListener('click', write);

    // Search: as you type (a moment after the last key), at once on Enter
    const search = $('#dySearch');
    let searchTimer = 0;
    const runSearch = () => {
      clearTimeout(searchTimer);
      const q = search.value.trim();
      if (q === state.q) return;
      state.q = q;
      reload();
    };
    search.addEventListener('input', () => {
      $('#dySearchClear').hidden = !search.value;
      clearTimeout(searchTimer);
      searchTimer = setTimeout(runSearch, 350);
    });
    search.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') { e.preventDefault(); runSearch(); search.blur(); }
      else if (e.key === 'Escape' && search.value) { e.preventDefault(); search.value = ''; runSearch(); }
    });
    $('#dySearchClear').addEventListener('click', () => { search.value = ''; runSearch(); search.focus(); });

    document.querySelectorAll('.dy-chip[data-period]').forEach((b) => b.addEventListener('click', () => {
      if (state.period === b.dataset.period) return;
      state.period = b.dataset.period;
      reload();
    }));

    $('#dySort').addEventListener('click', () => {
      state.sort = state.sort === 'oldest' ? 'newest' : 'oldest';
      store('sort', state.sort);
      reload();
    });
    $('#dyExport').addEventListener('click', openExport);
    $('#dyMore').addEventListener('click', () => load(true));

    document.addEventListener('keydown', (e) => {
      if (layers.length) {
        const top = layers[layers.length - 1];
        if (e.key === 'Escape') { e.preventDefault(); top.close(); }
        else if (e.key === 'Tab') trapTab(e, top.panel);
        return;
      }
      if (!page) return;
      const typing = /INPUT|TEXTAREA|SELECT/.test((e.target && e.target.tagName) || '');
      if (e.key === 'Escape') { e.preventDefault(); closePage(); return; }
      if (e.key === 'Tab') { trapTab(e, page.node); return; }
      if (page.kind === 'write' && (e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'Enter')) {
        e.preventDefault();
        if (page.save) page.save();
        return;
      }
      if (page.kind === 'read' && !typing && !e.altKey && !e.ctrlKey && !e.metaKey && page.entry) {
        const nb = neighbours(page.id);
        if (e.key === 'ArrowLeft' && nb.prev) goTo(nb.prev);
        else if (e.key === 'ArrowRight' && nb.next) goTo(nb.next);
        else if (e.key === 'e' || e.key === 'E') openWriter(page.entry, { from: 'read' });
      }
    });

    // Back after a while: fresh data (another phone may have written)
    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'visible' && !page && Date.now() - state.loadedAt > 120000) reload();
    });
  }

  function trapTab(e, root) {
    const focusable = Array.prototype.filter.call(
      root.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'),
      (n) => !n.disabled && n.offsetParent !== null
    );
    if (!focusable.length) return;
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (e.shiftKey && (document.activeElement === first || document.activeElement === root)) {
      e.preventDefault();
      last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault();
      first.focus();
    }
  }

  /**
   * Where to start: ?entry=ID and ?date= links (from the calendar), or the
   * #e= / #w= hash that survived a reload or the language switch. The page
   * underneath is always the list, so Back from the first page lands there.
   */
  function startRoute() {
    const q = new URLSearchParams(window.location.search);
    const h = new URLSearchParams(String(window.location.hash || '').replace(/^#/, ''));
    const entryId = parseInt(q.get('entry') || h.get('e') || '', 10);
    const writeId = h.get('w');
    const date = q.get('date') || '';
    const time = q.get('time') || '';
    try { history.replaceState(null, '', window.location.pathname); } catch (e) { /* old WebView */ }

    if (entryId > 0) {
      openReader(entryId);
    } else if (realDate(date)) {
      openWriter(null, { date: date, time: isTime(time) ? time : null });
    } else if (writeId === 'new') {
      openWriter(null, {});
    } else if (/^\d+$/.test(writeId || '')) {
      openWriter({ id: +writeId }, {});
    }
  }

  bind();
  startRoute();
  load(false);
})();
