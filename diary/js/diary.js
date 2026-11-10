/**
 * Diary — the list, the reader and the writer.
 *
 * Reads:  /diary/api/list.php (pages of excerpts), get.php (one whole entry)
 * Writes: create.php, update.php, delete.php; ai_enhance.php only suggests
 * Files:  export.php (PDF or text download, also in the app)
 * Every POST carries the session CSRF token (X-CSRF-Token).
 *
 * Built to stand still (the old page jumped while typing and tapping):
 * - The search and period bar is pinned under the header. When a search,
 *   period, tag or order changes, the old list stays (dimmed) until the new
 *   one is in, the list is always at least a screen high, and the page keeps
 *   the bar exactly where it is. Nothing slides or fades in afterwards.
 * - The reader and the writer are full pages below the header with their
 *   actions in a bar at the top, so the phone's keyboard can never cover or
 *   push them. The page ends where the keyboard begins (--dy-kb, from the
 *   visual viewport, for browsers that do not shrink the page themselves).
 * - The writing area grows with the text through a hidden copy of it, so
 *   it never collapses first (that collapse made the page jump on every
 *   key, the space bar included).
 * - While a page or a sheet is open the list underneath is pinned in place
 *   (body position: fixed, also on iPhones) and comes back exactly where it
 *   was; the scrollbar keeps its room, so nothing shifts sideways.
 * - In the reader the text has the focus, so Space and Page Down read on.
 *
 * Each page has its own history entry, so the phone's Back button, the
 * app's and the floating back button all close it. Unsaved writing is kept
 * on this device until it is saved or discarded. Dates are plain
 * "YYYY-MM-DD" strings in South African time; the browser never parses a
 * date string (iOS Safari safe).
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
  const root = document.documentElement;

  // We put the list back ourselves when a page closes
  try { if ('scrollRestoration' in history) history.scrollRestoration = 'manual'; } catch (e) { /* old WebView */ }

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
  const $ = (s, scope) => (scope || document).querySelector(s);

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
  const ICON_BACK = 'M19 12H5M12 19l-7-7 7-7';
  const ICON_EDIT = 'M12 20h9M16.5 3.5a2.12 2.12 0 013 3L7 19l-4 1 1-4 12.5-12.5z';
  const ICON_TRASH = 'M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 11v6M14 11v6';
  const ICON_SHARE = 'M4 12v7a2 2 0 002 2h12a2 2 0 002-2v-7M16 6l-4-4-4 4M12 2v13';
  const ICON_COPY = 'M8 4h10a2 2 0 012 2v12M16 8H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2V10a2 2 0 00-2-2z';
  const ICON_PREV = 'M15 18l-6-6 6-6';
  const ICON_NEXT = 'M9 18l6-6-6-6';
  const ICON_CHECK = 'M5 12l5 5L20 7';
  const ICON_PLUS = 'M12 5v14M5 12h14';
  const ICON_UNDO = 'M9 14L4 9l5-5M4 9h10.5a5.5 5.5 0 010 11H11';
  const ICON_SEARCH = 'M11 4a7 7 0 100 14 7 7 0 000-14zM20 20l-3.5-3.5';

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
    rendered: false,   // something real has been drawn (after that the old list stays while loading)
    error: '',
    req: 0,
    loadedAt: 0
  };
  const filtered = () => !!(state.q || state.period || state.tag);

  // Whole entries already fetched: the reader opens them at once (prev/next, back from the writer)
  const full = new Map();

  // ===== LAYOUT: header height, the pinned bar, the phone's keyboard =====
  function headerHeight() {
    const v = parseFloat(getComputedStyle(root).getPropertyValue('--ghf-height'));
    return v > 0 ? v : 70;
  }

  const bar = $('#dyBar');
  const sentinel = el('div', { 'aria-hidden': 'true' });
  if (bar) bar.before(sentinel);

  /** The bar is pinned under the header (the user has scrolled into the list) */
  function barStuck() {
    return !!bar && sentinel.getBoundingClientRect().top < headerHeight() - 0.5;
  }

  function watchBar() {
    if (!bar) return;
    let raf = 0;
    const update = () => {
      raf = 0;
      if (!locked) bar.classList.toggle('is-stuck', barStuck());
    };
    window.addEventListener('scroll', () => { if (!raf) raf = requestAnimationFrame(update); }, { passive: true });
    window.addEventListener('resize', () => { if (!raf) raf = requestAnimationFrame(update); }, { passive: true });
    update();
    // Entries keep clear of the bar when focused (scroll-margin-top in diary.css)
    const measure = () => root.style.setProperty('--dy-bar-h', Math.round(bar.offsetHeight) + 'px');
    measure();
    if (window.ResizeObserver) new ResizeObserver(measure).observe(bar);
  }

  /**
   * Before new results are drawn: when the bar is pinned, bring the start of
   * the results right under it. The bar itself never moves; the list starts
   * from its first entry instead of somewhere in the middle.
   */
  function anchorList() {
    if (locked || !barStuck()) return;
    const status = $('.dy-status');
    if (!status) return;
    const target = window.scrollY + status.getBoundingClientRect().top - (headerHeight() + bar.offsetHeight);
    if (target < window.scrollY - 1) window.scrollTo(0, Math.max(0, Math.round(target)));
  }

  /**
   * The phone's keyboard. Where the browser does not make the page smaller
   * (iPhones, some app versions), the open page ends where the keyboard
   * begins and starts where the visible part begins, so the line being
   * typed and the Save button always stay in sight.
   */
  function watchKeyboard() {
    const vv = window.visualViewport;
    if (!vv) return;
    let raf = 0;
    let lastKb = -1;
    let lastTop = -1;
    const update = () => {
      raf = 0;
      let kb = 0;
      let top = 0;
      if (Math.abs((vv.scale || 1) - 1) < 0.01) {           // not while zoomed in with two fingers
        kb = Math.round(window.innerHeight - vv.height - vv.offsetTop);
        if (kb < 80) kb = 0;                                  // a toolbar coming and going, not a keyboard
        if (kb > 0) top = Math.max(0, Math.round(vv.offsetTop));
      }
      if (kb !== lastKb) { lastKb = kb; root.style.setProperty('--dy-kb', kb + 'px'); }
      if (top !== lastTop) { lastTop = top; root.style.setProperty('--dy-vvt', top + 'px'); }
    };
    const schedule = () => { if (!raf) raf = requestAnimationFrame(update); };
    vv.addEventListener('resize', schedule);
    vv.addEventListener('scroll', schedule);
    update();
  }

  // ===== SCROLL LOCK =====
  // While a page or a sheet is open the list stands still: the body is
  // pinned where it was (iPhones ignore overflow: hidden) and comes back to
  // exactly that place. The rest of the page cannot be reached meanwhile.
  let locked = false;
  let lockedY = 0;

  function lockScroll() {
    const want = !!(layers.length || page);
    if (want === locked) return;
    locked = want;
    const main = $('#dyMain');
    if (want) {
      lockedY = window.scrollY || window.pageYOffset || 0;
      document.body.style.top = -lockedY + 'px';
      root.classList.add('dy-locked');
      if (main) main.inert = true;
    } else {
      root.classList.remove('dy-locked');
      document.body.style.top = '';
      if (main) main.inert = false;
      window.scrollTo(0, lockedY);
    }
  }

  // ===== TOAST (floats, takes no room) =====
  let toastTimer = 0;
  /** toast(text) · toast(text, true) for an error · toast(text, {error, action: {label, onClick}, ms}) */
  function toast(message, opts) {
    const t = $('#dyToast');
    if (!t || !message) return;
    if (typeof opts === 'boolean') opts = { error: opts };
    opts = opts || {};
    const text = $('.dy-toast-text', t) || t;
    text.textContent = message;
    const old = $('.dy-link-btn', t);
    if (old) old.remove();
    if (opts.action) {
      t.append(el('button', {
        class: 'dy-link-btn', type: 'button',
        on: { click: () => { hideToast(); opts.action.onClick(); } }
      }, [opts.action.icon ? strokeIcon(opts.action.icon) : null, el('span', { text: opts.action.label })]));
    }
    t.classList.toggle('has-action', !!opts.action);
    t.classList.toggle('is-error', !!opts.error);
    t.classList.add('is-on');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(hideToast, opts.ms || (opts.action ? 8000 : (opts.error ? 4800 : 2600)));
  }

  function hideToast() {
    clearTimeout(toastTimer);
    const t = $('#dyToast');
    if (t) t.classList.remove('is-on');
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
      if (!back.parentNode || back.classList.contains('is-closing')) return;
      back.classList.add('is-closing');
      setTimeout(() => { if (back.parentNode) back.parentNode.removeChild(back); }, 170);
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
    grip.addEventListener('touchstart', (e) => { startY = e.touches[0].clientY; dy = 0; dragging = true; panel.style.transition = 'none'; }, { passive: true });
    grip.addEventListener('touchmove', (e) => {
      if (!dragging) return;
      dy = Math.max(0, e.touches[0].clientY - startY);
      panel.style.transform = 'translateY(' + dy + 'px)';
    }, { passive: true });
    const end = () => {
      if (!dragging) return;
      dragging = false;
      panel.style.transition = 'transform 0.2s ease';
      panel.style.transform = '';
      if (dy > 90) close();
    };
    grip.addEventListener('touchend', end);
    grip.addEventListener('touchcancel', end);
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
      panel.append(...[
        el('h2', { class: 'dy-sheet-title', id: id, text: title }),
        text ? el('p', { class: 'dy-confirm-text', text: text }) : null,
        el('div', { class: 'dy-form-actions' }, [cancel, ok])
      ].filter(Boolean));
      const layer = openLayer(panel, id);
      const finish = (v) => { if (answered) return; answered = true; layer.close(); resolve(v); };
      layer.onClose = () => { if (!answered) { answered = true; resolve(false); } };
      ok.addEventListener('click', () => finish(true));
      cancel.addEventListener('click', () => finish(false));
    });
  }

  function action(label, iconNode, onClick, hint) {
    return el('button', { class: 'dy-action', type: 'button', on: { click: onClick } }, [
      iconNode,
      el('span', { class: 'dy-action-text' }, [el('b', { text: label }), hint ? el('small', { text: hint }) : null])
    ]);
  }

  /** Busy: the button is dimmed and its icon pulses; its label (and so its size) stays */
  function setBusy(btn, busy, label) {
    if (!btn) return;
    btn.disabled = !!busy;
    btn.classList.toggle('is-busy', !!busy);
    if (busy) {
      btn.setAttribute('aria-busy', 'true');
      if (label && btn.dataset.prevLabel === undefined) {
        btn.dataset.prevLabel = btn.getAttribute('aria-label') || '';
        btn.setAttribute('aria-label', label);
      }
    } else {
      btn.removeAttribute('aria-busy');
      if (btn.dataset.prevLabel !== undefined) {
        if (btn.dataset.prevLabel) btn.setAttribute('aria-label', btn.dataset.prevLabel);
        else btn.removeAttribute('aria-label');
        delete btn.dataset.prevLabel;
      }
    }
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

  /** opts.more: the next page · opts.anchor: a filter changed, keep the bar where it is */
  async function load(opts) {
    opts = opts || {};
    const more = !!opts.more;
    const req = ++state.req;
    const offset = more ? state.entries.length : 0;
    state.loading = true;
    state.error = more ? state.error : '';
    if (opts.anchor) anchorList();
    if (!more) renderList();
    setBusy($('#dyMore'), true);

    const res = await api('/diary/api/list.php' + queryString({ offset: offset }));
    if (req !== state.req) return; // a newer search took over
    state.loading = false;
    setBusy($('#dyMore'), false);

    if (!res.ok) {
      if (more) { renderList(); toast(errorText(res.error), true); return; }
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

  const reload = (anchor) => load({ anchor: !!anchor });

  // ===== LIST: DRAWING =====
  function renderFilters() {
    document.querySelectorAll('.dy-seg-btn[data-period]').forEach((b) => {
      b.setAttribute('aria-pressed', b.dataset.period === state.period ? 'true' : 'false');
    });

    // The tag being shown sits in the status line, which always has the same height
    const slot = $('#dyTagSlot');
    if (slot) {
      const current = slot.firstChild;
      if (!state.tag) {
        if (current) slot.replaceChildren();
      } else if (!current || current.dataset.tag !== state.tag) {
        slot.replaceChildren(el('button', {
          class: 'dy-tag-chip', type: 'button', 'data-tag': state.tag,
          'aria-label': T.dy_remove_filter + ': #' + state.tag,
          on: { click: () => { state.tag = ''; renderFilters(); reload(true); } }
        }, [el('span', { text: tr('dy_tag_filter', { tag: state.tag }) }), strokeIcon(ICON_CLOSE)]));
      }
    }

    const sortLabel = $('#dySortLabel');
    if (sortLabel) sortLabel.textContent = state.sort === 'oldest' ? T.oldest_first : T.newest_first;
    const clear = $('#dySearchClear');
    const input = $('#dySearch');
    if (clear && input) clear.hidden = !input.value;
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
    const emoji = (e.mood ? MOOD_EMOJI[e.mood] || '' : '') + (e.weather ? WEATHER_EMOJI[e.weather] || '' : '');
    const feel = [e.mood ? T['mood_' + e.mood] : '', e.weather ? T['weather_' + e.weather] : ''].filter(Boolean).join(', ');

    const rel = relativeDay(e.date);
    const when = (rel ? rel + ' · ' : '') + dayMedium(e.date) + (e.time ? ' · ' + e.time : '');
    const title = el('h3', { class: 'dy-entry-title' + (e.title ? '' : ' is-untitled') });
    title.append(marked(entryTitle(e), e.title ? words : []));
    const excerpt = e.excerpt ? el('p', { class: 'dy-entry-excerpt' }) : null;
    if (excerpt) excerpt.append(marked(e.excerpt, words));

    const tags = (e.tags || []).slice(0, 3).map((t) => el('span', { class: 'dy-entry-tag', text: '#' + t }));
    if ((e.tags || []).length > 3) tags.push(el('span', { class: 'dy-entry-tag', text: '+' + (e.tags.length - 3) }));

    return el('a', {
      class: 'dy-entry' + (e.date === today() ? ' is-today' : ''), href: '#e=' + e.id, 'data-id': e.id,
      on: { click: (ev) => { ev.preventDefault(); openReader(e.id); } }
    }, [
      stamp(e.date),
      el('span', { class: 'dy-entry-body' }, [
        el('span', { class: 'dy-entry-top' }, [
          el('span', { class: 'dy-entry-when', text: when }),
          emoji ? el('span', { class: 'dy-entry-emoji', title: feel, text: emoji }) : null,
          feel ? el('span', { class: 'dy-sr', text: feel }) : null
        ]),
        title,
        excerpt,
        tags.length ? el('span', { class: 'dy-entry-tags' }, tags) : null
      ])
    ]);
  }

  function emptyState(kind) {
    const box = el('div', { class: 'dy-card dy-empty' });
    if (kind === 'error') {
      box.append(
        el('div', { class: 'dy-empty-orb' }, [icon('notes')]),
        el('p', { class: 'dy-empty-text', text: T.dy_load_failed }),
        el('p', { class: 'dy-empty-hint', text: errorText(state.error) }),
        el('button', { class: 'dy-btn dy-btn--glass', type: 'button', on: { click: () => reload() } }, [el('span', { text: T.dy_retry })])
      );
    } else if (kind === 'none') {
      box.append(
        el('div', { class: 'dy-empty-orb' }, [strokeIcon(ICON_SEARCH)]),
        el('p', { class: 'dy-empty-text', text: T.dy_none_found }),
        el('p', { class: 'dy-empty-hint', text: T.dy_none_found_hint }),
        el('button', { class: 'dy-btn dy-btn--glass', type: 'button', on: { click: clearFilters } }, [el('span', { text: T.dy_show_all })])
      );
    } else {
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

  /** On this day in earlier years: the first thing in the list (only the plain list) */
  function otdCard() {
    if (filtered() || !state.otd.length) return null;
    const year = +today().slice(0, 4);
    return el('section', { class: 'dy-card dy-otd', 'aria-labelledby': 'dyOtdTitle' }, [
      el('h3', { class: 'dy-label', id: 'dyOtdTitle' }, [icon('history'), el('span', { text: T.dy_on_this_day })]),
      el('div', { class: 'dy-otd-list' }, state.otd.map((e) => {
        const ago = year - +e.date.slice(0, 4);
        return el('a', {
          class: 'dy-otd-item', href: '#e=' + e.id,
          on: { click: (ev) => { ev.preventDefault(); openReader(e.id); } }
        }, [
          el('span', { class: 'dy-otd-ago', text: ago === 1 ? T.dy_year_ago : tr('dy_years_ago', { n: ago }) }),
          // No title: the first words say more than "Untitled" (as in the calendar)
          el('span', { class: 'dy-otd-title' + (e.title ? '' : ' is-excerpt'), text: e.title || e.excerpt || T.dy_no_title })
        ]);
      }))
    ]);
  }

  function renderList() {
    renderFilters();
    const list = $('#dyList');
    const count = $('#dyCount');
    const moreWrap = $('#dyMoreWrap');
    if (!list) return;
    list.setAttribute('aria-busy', state.loading ? 'true' : 'false');
    if (bar) bar.classList.toggle('is-loading', state.loading);

    // Loading again: the list that is there stays (dimmed) until the new one is in
    if (state.loading) {
      list.classList.toggle('is-loading', state.rendered);
      if (!state.rendered) moreWrap.hidden = true;
      return;
    }
    list.classList.remove('is-loading');
    state.rendered = true;

    if (state.error) {
      count.textContent = '';
      list.replaceChildren(emptyState('error'));
      moreWrap.hidden = true;
      return;
    }
    if (!state.entries.length) {
      count.textContent = filtered() ? tr('dy_found_n', { n: 0 }) : '';
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
    const kids = groups.map((g) => el('section', { class: 'dy-month', 'aria-label': monthLabel(g.key) }, [
      el('h3', { class: 'dy-month-label', 'aria-hidden': 'true', text: monthLabel(g.key) }),
      el('div', { class: 'dy-month-items' }, g.items.map(entryCard))
    ]));
    const otd = otdCard();
    if (otd) kids.unshift(otd);
    list.replaceChildren(...kids);
    moreWrap.hidden = !state.hasMore;
  }

  function clearFilters() {
    state.q = '';
    state.period = '';
    state.tag = '';
    const input = $('#dySearch');
    if (input) input.value = '';
    reload(true);
  }

  /** After a save: the list shows the entry as it is now */
  function patchEntry(entry) {
    const short = Object.assign({}, entry, { excerpt: String(entry.body || '').replace(/\s+/g, ' ').trim().slice(0, 280) });
    delete short.body;
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
    full.delete(id);
    if (state.entries.length !== before) state.total = Math.max(0, state.total - 1);
    renderList();
  }

  // ===== PAGES (reader and writer, full screen below the header) =====
  // Each open page has its own history entry; Back (or Close) pops it and
  // the popstate handler draws what the history now says.
  let page = null; // { kind, id, node, ... }
  let skipGuard = false;
  let anchorOnClose = false; // a tag was chosen in the reader: show the start of its list

  function pageNode(kind, labelId) {
    const node = el('div', { class: 'dy-page dy-page--' + kind, role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': labelId, tabindex: '-1' });
    const appbar = el('div', { class: 'dy-appbar' });
    const barInner = el('div', { class: 'dy-appbar-inner' });
    const scroll = el('div', { class: 'dy-page-scroll', tabindex: '-1' });
    const inner = el('div', { class: 'dy-page-inner' });
    appbar.append(barInner);
    scroll.append(inner);
    node.append(appbar, scroll);
    return { node: node, bar: barInner, scroll: scroll, inner: inner };
  }

  /** A button in a page's top bar. opts: primary, danger, ai, compact (icon only on phones) */
  function barButton(label, iconNode, onClick, opts) {
    opts = opts || {};
    const cls = ['dy-ab-btn'];
    if (opts.primary) cls.push('is-primary');
    if (opts.danger) cls.push('is-danger');
    if (opts.ai) cls.push('is-ai');
    if (opts.compact) cls.push('is-compact');
    const b = el('button', {
      class: cls.join(' '), type: 'button', disabled: !!opts.disabled,
      title: opts.compact ? label : null,
      on: { click: onClick }
    }, [typeof iconNode === 'string' ? strokeIcon(iconNode) : iconNode, el('span', { class: 'dy-ab-label', text: label })]);
    return b;
  }

  function focusQuietly(node) {
    if (!node) return;
    try { node.focus({ preventScroll: true }); } catch (e) { node.focus(); }
  }

  function showPage(p) {
    const prev = page;
    page = p;
    // Page to page (prev/next, writer to reader): swapped at once, so the list never shows through
    if (prev) p.node.classList.add('is-swap');
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
    setTimeout(() => node.remove(), 170);
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
        if (anchorOnClose) {
          anchorOnClose = false;
          anchorList();
          return;
        }
        if (returnTo) {
          // Back on the card of the entry last read; only scrolled to when it is out of sight
          const card = document.querySelector('.dy-entry[data-id="' + returnTo + '"]');
          if (card) {
            focusQuietly(card);
            const r = card.getBoundingClientRect();
            const top = headerHeight() + (bar ? bar.offsetHeight : 0);
            if (r.bottom < top || r.top > window.innerHeight) card.scrollIntoView({ block: 'center' });
          }
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

  /** Fetch a whole entry once; later the reader opens it at once */
  async function fetchEntry(id) {
    if (full.has(id)) return { ok: true, entry: full.get(id) };
    const res = await api('/diary/api/get.php?id=' + encodeURIComponent(id));
    if (!res.ok) return { ok: false, error: res.error };
    full.set(id, res.data.entry);
    return { ok: true, entry: res.data.entry };
  }

  function prefetch(entry) {
    if (entry && !full.has(entry.id)) fetchEntry(entry.id);
  }

  async function renderReader(id) {
    const labelId = 'dyReadTitle';
    const p = pageNode('read', labelId);
    p.kind = 'read';
    p.id = id;
    p.state = { dy: 'read', id: id };
    p.hash = '#e=' + id;

    // The bar is complete from the start (disabled until the entry is in), so nothing appears later
    const editBtn = barButton(T.edit, ICON_EDIT, () => { if (p.entry) openWriter(p.entry, { from: 'read' }); }, { primary: true, disabled: true });
    const shareBtn = barButton(T.share, ICON_SHARE, () => { if (p.entry) openShare(p.entry); }, { compact: true, disabled: true });
    const delBtn = barButton(T.delete, ICON_TRASH, () => { if (p.entry) removeFlow(p.entry); }, { compact: true, danger: true, disabled: true });
    p.bar.append(
      barButton(T.back, ICON_BACK, closePage),
      el('span', { class: 'dy-appbar-space' }),
      editBtn, shareBtn, delBtn
    );

    const cached = full.get(id);
    if (!cached) {
      p.inner.append(el('div', { class: 'dy-read-skel', 'aria-hidden': 'true' }, [el('i'), el('i'), el('i'), el('i')]), el('h2', { class: 'dy-sr', id: labelId, text: T.dy_reading }));
    }
    showPage(p);
    focusQuietly(p.scroll);   // Space and Page Down read on

    const res = cached ? { ok: true, entry: cached } : await fetchEntry(id);
    if (page !== p) return;
    if (!res.ok) {
      p.inner.replaceChildren(el('div', { class: 'dy-card dy-empty' }, [
        el('div', { class: 'dy-empty-orb' }, [icon('notes')]),
        el('p', { class: 'dy-empty-text', id: labelId, text: errorText(res.error) }),
        res.error === 'not_found' ? null : el('button', { class: 'dy-btn dy-btn--glass', type: 'button', on: { click: () => renderReader(id) } }, [el('span', { text: T.dy_retry })])
      ]));
      if (res.error === 'not_found') removeEntry(id);
      return;
    }
    p.entry = res.entry;
    [editBtn, shareBtn, delBtn].forEach((b) => { b.disabled = false; });
    drawReader(p, res.entry, labelId);
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
      on: {
        click: () => {
          state.tag = t;
          state.q = '';
          const input = $('#dySearch');
          if (input) input.value = '';
          anchorOnClose = true;
          closePage();
          reload(true);
        }
      }
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
      class: 'dy-nav-btn', type: 'button', 'aria-label': label, title: label, disabled: !entry,
      on: { click: () => goTo(entry) }
    }, [strokeIcon(d)]);

    p.inner.replaceChildren(el('article', { class: 'dy-read' }, [
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
    ]));

    enableReaderSwipe(p, nb);
    // The pages either side open at once
    prefetch(nb.prev);
    prefetch(nb.next);
  }

  // Swipe sideways through the pages of the journal
  function enableReaderSwipe(p, nb) {
    if (p.swipeOn) { p.nb = nb; return; }
    p.swipeOn = true;
    p.nb = nb;
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
      if (dx < 0 && p.nb.next) goTo(p.nb.next);
      else if (dx > 0 && p.nb.prev) goTo(p.nb.prev);
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

    // The bar is complete from the start; Save and AI wait until there is something to save
    const sparkIcon = icon('spark');
    p.saveBtn = barButton(T.save, ICON_CHECK, () => { if (p.save) p.save(); }, { primary: true, disabled: true });
    p.aiBtn = barButton(T.ai_assist, sparkIcon, () => { if (p.askAi) p.askAi(); }, { ai: true, compact: true, disabled: true });
    p.bar.append(
      barButton(T.cancel, ICON_CLOSE, closePage),
      el('span', { class: 'dy-appbar-space' }),
      p.aiBtn,
      p.saveBtn
    );
    showPage(p);

    let entry = known && known.body !== undefined ? known : (s.id && full.has(s.id) ? full.get(s.id) : null);
    if (s.id && !entry) {
      focusQuietly(p.scroll);
      p.inner.append(el('div', { class: 'dy-read-skel', 'aria-hidden': 'true' }, [el('i'), el('i'), el('i')]));
      const res = await fetchEntry(s.id);
      if (page !== p) return;
      if (!res.ok) {
        // Nothing to edit, but a draft for it would still be on this device
        p.inner.replaceChildren(el('div', { class: 'dy-card dy-empty' }, [
          el('div', { class: 'dy-empty-orb' }, [icon('notes')]),
          el('p', { class: 'dy-empty-text', id: labelId, text: errorText(res.error) })
        ]));
        return;
      }
      entry = res.entry;
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
    const form = el('form', { class: 'dy-form', novalidate: true, on: { submit: (ev) => ev.preventDefault() } });
    const heading = el('h2', { class: 'dy-write-heading', id: labelId, text: entry ? T.dy_edit_entry : T.new_entry });

    // Shown from the start (never pops in above the text later)
    const notice = restored ? el('div', { class: 'dy-notice', role: 'status' }, [
      el('span', { text: T.dy_draft_restored }),
      el('button', {
        class: 'dy-link-btn', type: 'button',
        on: { click: () => { store(draftKey(s.id), null); if (p.cleanup) p.cleanup(); renderWriter(s, entry); } }
      }, [strokeIcon(ICON_UNDO), el('span', { text: T.dy_draft_discard })])
    ]) : null;

    const date = el('input', { class: 'dy-input', type: 'date', id: 'dyDate', required: true, value: v.date });
    const time = el('input', { class: 'dy-input', type: 'time', id: 'dyTime', value: v.time });
    const title = el('input', {
      class: 'dy-input dy-input--title', type: 'text', id: 'dyTitleIn', maxlength: LIMITS.title,
      placeholder: T.my_thoughts_today, autocomplete: 'off', enterkeyhint: 'next', value: v.title
    });
    const body = el('textarea', { class: 'dy-textarea', id: 'dyBody', rows: '6', placeholder: T.write_thoughts_here });
    body.value = v.body;
    // The hidden copy behind the text sets the height: it grows, it never collapses first
    const grow = el('div', { class: 'dy-grow' }, [body]);
    const words = el('span', { class: 'dy-words', 'aria-live': 'polite' });

    // Screen readers hear what is wrong; on screen a toast says it (nothing is pushed down)
    const errNote = (id) => el('span', { class: 'dy-sr', id: id + 'Err' });

    let mood = v.mood;
    let weather = v.weather;
    let tags = v.tags.slice();

    function picker(kind, list, emoji, current, set) {
      const wrap = el('div', { class: 'dy-picks', role: 'group', 'aria-label': kind === 'mood' ? T.mood : T.weather });
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

    // ----- tags: chips and the input in one box -----
    const chips = el('span', { class: 'dy-tag-chips', style: 'display: contents' });
    const tagInput = el('input', {
      class: 'dy-tag-input', type: 'text', id: 'dyTagIn', maxlength: LIMITS.tag,
      placeholder: T.dy_tag_ph, autocomplete: 'off', enterkeyhint: 'done', list: 'dyTagSuggest'
    });
    const suggest = el('datalist', { id: 'dyTagSuggest' }, state.tags.map((t) => el('option', { value: t.tag })));
    const tagAdd = el('button', { class: 'dy-tag-add', type: 'button', 'aria-label': T.dy_add_tag, title: T.dy_add_tag }, [strokeIcon(ICON_PLUS)]);
    // The chips and the input wrap on the left; the + keeps its place on the right
    const tagWrap = el('div', { class: 'dy-tag-wrap' }, [chips, tagInput]);
    const tagField = el('div', { class: 'dy-tagfield', tabindex: '-1' }, [tagWrap, tagAdd]);

    function cleanTag(t) {
      return String(t || '').replace(/["\\#,[\]]/g, ' ').replace(/\s+/g, ' ').trim().slice(0, LIMITS.tag);
    }
    function drawTags() {
      // Only the chips are drawn again: the input keeps its focus (and the keyboard stays)
      chips.replaceChildren(...tags.map((t) => el('span', { class: 'dy-tag' }, [
        el('span', { text: '#' + t }),
        el('button', {
          class: 'dy-tag-x', type: 'button', 'aria-label': tr('dy_remove_tag', { tag: t }),
          on: {
            click: (ev) => {
              tags = tags.filter((x) => x !== t);
              drawTags();
              changed();
              // A tap does not call up the keyboard; the keyboard carries on in the input
              if (ev.detail === 0 && !tagInput.disabled) tagInput.focus();
              else focusQuietly(tagField);
            }
          }
        }, [strokeIcon(ICON_CLOSE)])
      ])));
      tagInput.disabled = tags.length >= LIMITS.tags;
      tagAdd.disabled = tags.length >= LIMITS.tags;
    }
    function addTag() {
      let added = false;
      tagInput.value.split(',').forEach((raw) => {
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
    tagAdd.addEventListener('click', () => { addTag(); if (!tagInput.disabled) tagInput.focus(); });
    tagField.addEventListener('click', (ev) => {
      if ((ev.target === tagField || ev.target === tagWrap) && !tagInput.disabled) tagInput.focus();
    });
    drawTags();

    // ----- layout -----
    const label = (forId, text, extra) => el('label', { class: 'dy-field-label', for: forId }, [el('span', { text: text }), extra ? el('small', { text: extra }) : null]);
    const groupLabel = (text) => el('p', { class: 'dy-field-label', 'aria-hidden': 'true' }, [el('span', { text: text })]);

    form.append(...[
      heading,
      notice,
      el('div', { class: 'dy-row' }, [
        el('div', { class: 'dy-field' }, [label('dyDate', T.date), date, errNote('dyDate')]),
        el('div', { class: 'dy-field' }, [label('dyTime', T.time, T.dy_optional), time, errNote('dyTime')])
      ]),
      el('div', { class: 'dy-field' }, [label('dyTitleIn', T.title, T.dy_optional), title]),
      el('div', { class: 'dy-field dy-field--body' }, [
        el('label', { class: 'dy-sr', for: 'dyBody', text: T.dy_writing }),
        grow,
        el('div', { class: 'dy-body-foot' }, [words]),
        errNote('dyBody')
      ]),
      el('div', { class: 'dy-field' }, [groupLabel(T.mood), picker('mood', CFG.moods || [], MOOD_EMOJI, mood, (x) => { mood = x; })]),
      el('div', { class: 'dy-field' }, [groupLabel(T.weather), picker('weather', CFG.weathers || [], WEATHER_EMOJI, weather, (x) => { weather = x; })]),
      el('div', { class: 'dy-field' }, [label('dyTagIn', T.tags, T.dy_optional), tagField, suggest]),
      el('p', { class: 'dy-hint dy-hint--calendar' }, [icon('calendar'), el('span', { text: T.dy_calendar_note })])
    ].filter(Boolean));
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
    function syncText() {
      grow.dataset.value = body.value;
      words.textContent = wordsLabel(countWords(body.value));
    }
    [date, time, title].forEach((n) => n.addEventListener('input', changed));
    body.addEventListener('input', () => { syncText(); changed(); });
    title.addEventListener('keydown', (ev) => { if (ev.key === 'Enter') { ev.preventDefault(); body.focus(); } });
    syncText();
    // Phones and the app may be put away at any moment: keep the text now
    document.addEventListener('visibilitychange', saveDraft);
    window.addEventListener('pagehide', saveDraft);
    p.cleanup = () => {
      clearTimeout(draftTimer);
      document.removeEventListener('visibilitychange', saveDraft);
      window.removeEventListener('pagehide', saveDraft);
    };

    // ----- errors -----
    function clearErrors() {
      form.querySelectorAll('[aria-invalid]').forEach((n) => { n.removeAttribute('aria-invalid'); n.removeAttribute('aria-describedby'); });
      form.querySelectorAll('.dy-sr[id$="Err"]').forEach((n) => { n.textContent = ''; });
    }
    function fieldError(input, message) {
      const err = form.querySelector('#' + input.id + 'Err');
      input.setAttribute('aria-invalid', 'true');
      if (err) { err.textContent = message; input.setAttribute('aria-describedby', err.id); }
      toast(message, true);
      try { input.focus({ preventScroll: false }); } catch (e) { input.focus(); }
    }
    [date, time, body].forEach((n) => n.addEventListener('input', () => {
      if (n.hasAttribute('aria-invalid')) { n.removeAttribute('aria-invalid'); n.removeAttribute('aria-describedby'); }
    }));

    // ----- save -----
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
      setBusy(p.saveBtn, true, T.dy_saving);
      const payload = Object.assign({}, val, { id: s.id || undefined });
      const res = await postJSON(s.id ? '/diary/api/update.php' : '/diary/api/create.php', payload);
      saving = false;
      setBusy(p.saveBtn, false);
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
      if (saved && saved.id && saved.body !== undefined) full.set(saved.id, saved);
      else if (saved && saved.id) full.delete(saved.id);
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
    async function askAi() {
      clearErrors();
      const text = body.value.trim();
      if (!text) return fieldError(body, T.dy_err_empty);
      const layer = sheet(T.dy_ai_title, { cls: 'dy-sheet--ai' });
      const box = el('div', { class: 'dy-ai-box', 'aria-live': 'polite' }, [el('p', { class: 'dy-ai-wait', text: T.dy_ai_working })]);
      layer.body.append(box);
      const res = await postJSON('/diary/api/ai_enhance.php', { text: text });
      if (!layer.back.parentNode || layer.back.classList.contains('is-closing')) return; // closed while waiting
      if (!res.ok) {
        box.replaceChildren(el('p', { class: 'dy-ai-error', text: errorText(res.error) }));
        return;
      }
      const suggestion = res.data.text;
      box.replaceChildren(el('div', { class: 'dy-ai-text', tabindex: '0', text: suggestion }));
      const keep = el('button', { class: 'dy-btn dy-btn--glass', type: 'button', text: T.dy_ai_keep, on: { click: () => layer.close() } });
      const use = el('button', {
        class: 'dy-btn dy-btn--primary', type: 'button', text: T.dy_ai_use,
        on: {
          click: () => {
            const before = body.value;
            body.value = suggestion;
            syncText();
            changed();
            layer.close();
            // Undo lives in a toast: nothing is inserted above the text
            toast(T.dy_ai_used, {
              ms: 10000,
              action: {
                label: T.dy_undo, icon: ICON_UNDO,
                onClick: () => {
                  if (page !== p) return;
                  body.value = before;
                  syncText();
                  changed();
                }
              }
            });
          }
        }
      });
      layer.body.append(el('p', { class: 'dy-hint', text: T.dy_ai_hint }), el('div', { class: 'dy-form-actions' }, [keep, use]));
      focusQuietly(use);
    }
    p.askAi = askAi;
    p.saveBtn.disabled = false;
    p.aiBtn.disabled = false;

    // New entries start typing straight away; edits wait for a tap
    if (!entry) focusQuietly(title);
    else focusQuietly(p.scroll);
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
    const clear = $('#dySearchClear');
    let searchTimer = 0;
    const runSearch = () => {
      clearTimeout(searchTimer);
      const q = search.value.trim();
      if (q === state.q) return;
      state.q = q;
      reload(true);
    };
    search.addEventListener('input', () => {
      clear.hidden = !search.value;
      clearTimeout(searchTimer);
      searchTimer = setTimeout(runSearch, 400);
    });
    search.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') { e.preventDefault(); runSearch(); search.blur(); }
      else if (e.key === 'Escape' && search.value) { e.preventDefault(); search.value = ''; clear.hidden = true; runSearch(); }
    });
    // The browser's own clear (×) on type=search
    search.addEventListener('search', () => { clear.hidden = !search.value; runSearch(); });
    clear.addEventListener('click', () => { search.value = ''; clear.hidden = true; runSearch(); search.focus(); });

    document.querySelectorAll('.dy-seg-btn[data-period]').forEach((b) => b.addEventListener('click', () => {
      if (state.period === b.dataset.period) return;
      state.period = b.dataset.period;
      renderFilters();
      reload(true);
    }));

    $('#dySort').addEventListener('click', () => {
      state.sort = state.sort === 'oldest' ? 'newest' : 'oldest';
      store('sort', state.sort);
      renderFilters();
      reload(true);
    });
    $('#dyExport').addEventListener('click', openExport);
    $('#dyMore').addEventListener('click', () => load({ more: true }));

    document.addEventListener('keydown', (e) => {
      if (layers.length) {
        const top = layers[layers.length - 1];
        if (e.key === 'Escape') { e.preventDefault(); top.close(); }
        else if (e.key === 'Tab') trapTab(e, top.panel);
        return;
      }
      if (!page) return;
      if (document.body.classList.contains('ghf-open')) return; // the site menu is open over the page
      const typing = /INPUT|TEXTAREA|SELECT/.test((e.target && e.target.tagName) || '') || (e.target && e.target.isContentEditable);
      if (e.key === 'Escape') { e.preventDefault(); closePage(); return; }
      if (e.key === 'Tab') { trapTab(e, page.node); return; }
      if (page.kind === 'write' && (e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S' || e.key === 'Enter')) {
        e.preventDefault();
        if (page.save) page.save();
        return;
      }
      if (page.kind === 'read' && !typing && !e.altKey && !e.ctrlKey && !e.metaKey && page.entry) {
        const nb = neighbours(page.id);
        if (e.key === 'ArrowLeft' && nb.prev) { e.preventDefault(); goTo(nb.prev); }
        else if (e.key === 'ArrowRight' && nb.next) { e.preventDefault(); goTo(nb.next); }
        else if (e.key === 'e' || e.key === 'E') { e.preventDefault(); openWriter(page.entry, { from: 'read' }); }
        else if ((e.key === ' ' || e.key === 'PageDown' || e.key === 'PageUp') && !page.scroll.contains(document.activeElement)) {
          // Space reads on, wherever the focus is in the page (not only on the text)
          if (e.target && e.target.closest && e.target.closest('button, a')) return;
          e.preventDefault();
          const step = page.scroll.clientHeight * 0.85;
          page.scroll.scrollBy({ top: (e.shiftKey || e.key === 'PageUp') ? -step : step });
        }
      }
    });

    // Back after a while: fresh data (another phone may have written)
    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'visible' && !page && !layers.length && Date.now() - state.loadedAt > 120000) {
        full.clear();
        reload();
      }
    });
  }

  function trapTab(e, scope) {
    // a[href], not [href]: the icons' <use href> are not stops
    const focusable = Array.prototype.filter.call(
      scope.querySelectorAll('button, a[href], input, select, textarea, [tabindex]:not([tabindex="-1"])'),
      (n) => n instanceof HTMLElement && !n.disabled && n.offsetParent !== null
    );
    if (!focusable.length) return;
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    const active = document.activeElement;
    if (!scope.contains(active) || active === scope) {
      e.preventDefault();
      (e.shiftKey ? last : first).focus();
    } else if (e.shiftKey && active === first) {
      e.preventDefault();
      last.focus();
    } else if (!e.shiftKey && active === last) {
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

  watchKeyboard();
  watchBar();
  bind();
  startRoute();
  load();
})();
