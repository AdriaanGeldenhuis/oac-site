/**
 * Kennisgewings — the list, grouped by day, newest first.
 *
 * Reads:  /notifications/api/list.php (pages, ?filter= / ?before=)
 * Writes: /notifications/api/mark_read.php (read, read_all, delete, delete_read),
 *         POST + the session CSRF token (X-CSRF-Token)
 *
 * Tapping a notification marks it read and opens where it points (only
 * links inside the site are offered by the server). Deleting is undoable
 * for a few seconds: the rows go to the server when the toast closes, or
 * at once when the page is left.
 * Times arrive as Unix seconds; days are South African days.
 */
(function () {
  'use strict';

  const CFG = window.NOTIFS;
  if (!CFG) return;
  const T = CFG.t || {};
  const NS = 'http://www.w3.org/2000/svg';
  const UNDO_MS = 5000;

  function tr(key, params) {
    let s = T[key] || key;
    if (params) {
      for (const p in params) s = s.split('{' + p + '}').join(String(params[p]));
    }
    return s;
  }

  const ERRORS = { csrf: 'nt_err_session', session: 'nt_err_session', offline: 'nt_err_offline' };
  const errorText = (code) => tr(ERRORS[code] || 'nt_err_generic');

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

  function icon(id) {
    const s = document.createElementNS(NS, 'svg');
    s.setAttribute('viewBox', '0 0 24 24');
    s.setAttribute('aria-hidden', 'true');
    const u = document.createElementNS(NS, 'use');
    u.setAttribute('href', '#nt-i-' + id);
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
  const ICON_TRASH = 'M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 11v6M14 11v6';
  const ICON_NEXT = 'M9 18l6-6-6-6';

  // ===== KINDS: the jewel and icon of each type, and the group label =====
  const KINDS = {
    prayer: ['prayer', 'prayer'],
    gospel: ['gospel', 'gospel'], comment: ['gospel', 'gospel'], reaction: ['gospel', 'gospel'], post: ['gospel', 'gospel'], tag: ['gospel', 'gospel'],
    calendar: ['calendar', 'calendar'], reminder: ['calendar', 'calendar'], appointment: ['calendar', 'calendar'],
    event: ['calendar', 'calendar'], visit: ['calendar', 'calendar'], diary: ['calendar', 'calendar'],
    thought: ['thought', 'thought'],
    birthday: ['birthday', 'cake'],
    spouse: ['spouse', 'heart'], spouse_accepted: ['spouse', 'heart'], spouse_rejected: ['spouse', 'heart'],
    ampte: ['ampte', 'star'],
    account: ['account', 'person'], success: ['account', 'check'],
    error: ['alert', 'alert'], warning: ['alert', 'alert']
  };
  const kindOf = (type) => KINDS[type] || ['info', 'bell'];
  const GROUP_LABEL = { prayer: 'nt_group_prayer', gospel: 'nt_group_gospel', calendar: 'nt_group_calendar', thought: 'nt_group_thought' };

  // ===== TIME (Unix seconds in, South African days out) =====
  const pad = (n) => String(n).padStart(2, '0');
  const SAST = 2 * 3600;
  function sast(ts) {
    const d = new Date((ts + SAST) * 1000);
    return { y: d.getUTCFullYear(), m: d.getUTCMonth(), d: d.getUTCDate(), w: d.getUTCDay(), h: d.getUTCHours(), i: d.getUTCMinutes() };
  }
  const dayKey = (ts) => { const s = sast(ts); return s.y + '-' + pad(s.m + 1) + '-' + pad(s.d); };
  const nowTs = () => Math.floor(Date.now() / 1000);

  function dayLabel(key, sampleTs) {
    const now = nowTs();
    if (key === dayKey(now)) return T.today;
    if (key === dayKey(now - 86400)) return T.time_yesterday;
    const s = sast(sampleTs);
    if (now - sampleTs < 6 * 86400) return (T.days || [])[s.w] || key;
    const thisYear = sast(now).y;
    return s.d + ' ' + ((T.months || [])[s.m] || '') + (s.y !== thisYear ? ' ' + s.y : '');
  }

  function timeText(ts) {
    const diff = nowTs() - ts;
    if (dayKey(ts) === dayKey(nowTs())) {
      if (diff < 60) return T.just_now;
      if (diff < 3600) return tr('time_minutes_ago', { n: Math.floor(diff / 60) });
      return tr('time_hours_ago', { n: Math.floor(diff / 3600) });
    }
    const s = sast(ts);
    return pad(s.h) + ':' + pad(s.i);
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
    if (data === null) return { ok: false, error: res.status === 401 ? 'session' : 'server_error' };
    const ok = res.ok && data.success === true;
    return { ok: ok, data: data, error: ok ? '' : (data.error || 'server_error') };
  }

  function post(body, keepalive) {
    return api('/notifications/api/mark_read.php', {
      method: 'POST',
      keepalive: !!keepalive,
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CFG.csrf || '' },
      body: JSON.stringify(body)
    });
  }

  // ===== TOAST =====
  let toastTimer = 0;
  function toast(message, opts) {
    opts = opts || {};
    const t = $('#ntToast');
    if (!t || !message) return;
    const old = $('.nt-toast-btn', t);
    if (old) old.remove();
    $('.nt-toast-text', t).textContent = message;
    t.classList.toggle('is-error', !!opts.error);
    t.classList.toggle('has-action', !!opts.action);
    if (opts.action) {
      t.append(el('button', {
        class: 'nt-toast-btn', type: 'button', text: opts.action,
        on: { click: () => { hideToast(); opts.onAction(); } }
      }));
    }
    t.classList.add('is-on');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(hideToast, opts.ms || (opts.error ? 4200 : 2400));
  }
  function hideToast() {
    clearTimeout(toastTimer);
    const t = $('#ntToast');
    if (t) t.classList.remove('is-on', 'has-action');
  }

  // ===== STATE =====
  const state = {
    filter: 'all',
    items: [],
    counts: null,
    hasMore: false,
    loading: false,
    error: '',
    req: 0,
    loadedAt: 0
  };
  const pending = { rows: [], timer: 0 }; // deletes waiting for their Undo to pass

  const isUnread = (n) => !n.read;
  const matches = (n) => state.filter === 'all' || (state.filter === 'unread' ? isUnread(n) : n.group === state.filter);

  function adjustCounts(n, sign) {
    if (!state.counts) return;
    state.counts.all = Math.max(0, state.counts.all + sign);
    state.counts[n.group] = Math.max(0, (state.counts[n.group] || 0) + sign);
    if (isUnread(n)) state.counts.unread = Math.max(0, state.counts.unread + sign);
  }

  // ===== LOADING =====
  async function load(more) {
    const req = ++state.req;
    state.loading = true;
    state.error = '';
    if (!more) render();
    const moreBtn = $('#ntMore');
    if (moreBtn) { moreBtn.disabled = true; moreBtn.classList.add('is-busy'); }

    const p = new URLSearchParams({ filter: state.filter });
    if (more && state.items.length) p.set('before', String(state.items[state.items.length - 1].id));
    const res = await api('/notifications/api/list.php?' + p.toString());
    if (req !== state.req) return;

    state.loading = false;
    if (moreBtn) { moreBtn.disabled = false; moreBtn.classList.remove('is-busy'); }
    if (!res.ok) {
      if (more) { toast(errorText(res.error), { error: true }); return; }
      state.error = res.error;
      state.items = [];
      render();
      return;
    }
    const d = res.data;
    const hidden = new Set(pending.rows.map((r) => r.n.id));
    const rows = (d.notifications || []).filter((n) => !hidden.has(n.id));
    state.items = more ? state.items.concat(rows) : rows;
    state.hasMore = !!d.has_more;
    if (d.counts) {
      state.counts = d.counts;
      pending.rows.forEach((r) => adjustCounts(r.n, -1)); // still waiting to be deleted
    }
    state.loadedAt = Date.now();
    render();
  }

  /** While the page is open: newer notifications slide in at the top */
  async function refresh() {
    if (state.loading || state.error || document.visibilityState !== 'visible' || layer) return;
    const res = await api('/notifications/api/list.php?filter=' + encodeURIComponent(state.filter));
    if (!res.ok || state.loading) return;
    const top = state.items.length ? state.items[0].id : 0;
    const hidden = new Set(pending.rows.map((r) => r.n.id));
    const fresh = (res.data.notifications || []).filter((n) => n.id > top && !hidden.has(n.id));
    if (res.data.counts) {
      state.counts = res.data.counts;
      pending.rows.forEach((r) => adjustCounts(r.n, -1));
    }
    if (fresh.length) state.items = fresh.concat(state.items);
    state.loadedAt = Date.now();
    render();
  }

  // ===== DRAWING =====
  function itemNode(n) {
    const kind = kindOf(n.type);
    const group = GROUP_LABEL[n.group];
    const meta = [el('time', { text: timeText(n.ts), datetime: new Date(n.ts * 1000).toISOString() })];
    if (group) meta.push(el('span', { 'aria-hidden': 'true', text: '·' }), el('span', { class: 'nt-item-area', text: T[group] }));
    if (isUnread(n)) meta.push(el('span', { class: 'nt-dot', title: T.nt_unread_label }), el('span', { class: 'nt-sr', text: T.nt_unread_label }));

    const body = el('span', { class: 'nt-item-body' }, [
      el('span', { class: 'nt-item-title', text: n.title }),
      n.message ? el('span', { class: 'nt-item-msg', text: n.message }) : null,
      el('span', { class: 'nt-item-meta' }, meta)
    ]);
    const orb = el('span', { class: 'nt-orb', 'aria-hidden': 'true' }, [icon(kind[1])]);

    let main;
    if (n.link) {
      main = el('a', { class: 'nt-item-main', href: n.link, on: { click: (e) => open(e, n) } }, [orb, body, strokeIcon(ICON_NEXT, 'nt-item-go')]);
    } else {
      main = el('button', { class: 'nt-item-main', type: 'button', 'aria-expanded': 'false', on: { click: () => toggle(n, main) } }, [orb, body]);
    }

    return el('article', { class: 'nt-item nt-k-' + kind[0] + (isUnread(n) ? ' is-unread' : ''), 'data-id': n.id }, [
      main,
      el('button', {
        class: 'nt-item-del', type: 'button', 'aria-label': T.nt_delete_one + ': ' + n.title, title: T.delete,
        on: { click: () => remove(n) }
      }, [strokeIcon(ICON_TRASH)])
    ]);
  }

  function emptyState() {
    const box = el('div', { class: 'nt-empty' });
    if (state.error) {
      box.append(
        el('div', { class: 'nt-empty-orb nt-empty-orb--quiet' }, [icon('bell')]),
        el('p', { class: 'nt-empty-text', text: T.nt_load_failed }),
        el('p', { class: 'nt-empty-hint', text: errorText(state.error) }),
        el('button', { class: 'nt-btn nt-btn--glass', type: 'button', on: { click: () => load(false) } }, [el('span', { text: T.nt_retry })])
      );
    } else if (state.filter !== 'all') {
      box.append(
        el('div', { class: 'nt-empty-orb nt-empty-orb--quiet' }, [icon('check')]),
        el('p', { class: 'nt-empty-text', text: T.nt_none_filter }),
        el('button', { class: 'nt-btn nt-btn--glass', type: 'button', on: { click: () => setFilter('all') } }, [el('span', { text: T.nt_show_all })])
      );
    } else {
      box.append(
        el('div', { class: 'nt-empty-orb' }, [icon('check')]),
        el('p', { class: 'nt-empty-text', text: T.nt_empty }),
        el('p', { class: 'nt-empty-hint', text: T.nt_empty_hint })
      );
    }
    return box;
  }

  function renderCounts() {
    const c = state.counts;
    const sub = $('#ntSub');
    if (c) {
      sub.textContent = c.unread === 0 ? T.nt_all_read : (c.unread === 1 ? T.nt_unread_one : tr('nt_unread_n', { n: c.unread }));
      const readAllBtn = $('#ntReadAll');
      readAllBtn.hidden = c.unread === 0;
      readAllBtn.disabled = false;
      const clearBtn = $('#ntClear');
      clearBtn.hidden = c.all - c.unread === 0;
      clearBtn.disabled = false;
      document.querySelectorAll('.nt-chip-count').forEach((b) => {
        const k = b.getAttribute('data-count');
        const n = c[k] || 0;
        b.textContent = n > 0 ? (n > 99 ? '99+' : String(n)) : '';
        const chip = b.parentNode;
        if (k !== 'all' && k !== 'unread') chip.hidden = n === 0 && state.filter !== k;
      });
      // The bell on every page
      const badge = document.getElementById('globalNotifCount');
      if (badge) {
        badge.textContent = c.unread > 99 ? '99+' : String(c.unread);
        badge.hidden = c.unread === 0;
      }
    }
    document.querySelectorAll('.nt-chip').forEach((b) => {
      b.setAttribute('aria-pressed', b.getAttribute('data-filter') === state.filter ? 'true' : 'false');
    });
  }

  function render() {
    renderCounts();
    const list = $('#ntList');
    const moreWrap = $('#ntMoreWrap');
    list.setAttribute('aria-busy', state.loading ? 'true' : 'false');
    list.classList.toggle('is-loading', state.loading && state.items.length > 0);

    if (state.loading && !state.items.length) {
      list.replaceChildren(el('div', { class: 'nt-skel', 'aria-hidden': 'true' }, [el('i'), el('i'), el('i'), el('i')]));
      moreWrap.hidden = true;
      return;
    }
    if (state.loading) return;

    const shown = state.items.filter(matches);
    if (!shown.length && !(state.hasMore && !state.error)) {
      list.replaceChildren(emptyState());
      moreWrap.hidden = true;
      return;
    }

    const groups = [];
    let current = null;
    shown.forEach((n) => {
      const key = dayKey(n.ts);
      if (!current || current.key !== key) {
        current = { key: key, ts: n.ts, items: [] };
        groups.push(current);
      }
      current.items.push(n);
    });
    list.replaceChildren(...groups.map((g) => el('section', { class: 'nt-day' }, [
      el('h3', { class: 'nt-day-label', text: dayLabel(g.key, g.ts) }),
      el('div', { class: 'nt-day-items' }, g.items.map(itemNode))
    ])));
    moreWrap.hidden = !state.hasMore;
  }

  function setFilter(f) {
    if (state.filter === f) return;
    state.filter = f;
    load(false);
  }

  // ===== READING =====
  function markReadLocally(n) {
    if (!isUnread(n)) return false;
    n.read = true;
    if (state.counts) state.counts.unread = Math.max(0, state.counts.unread - 1);
    return true;
  }

  /** Mark read and follow the link (the request survives the page change) */
  function open(e, n) {
    if (e.defaultPrevented || e.button > 0 || e.metaKey || e.ctrlKey || e.shiftKey) {
      if (markReadLocally(n)) { post({ action: 'read', ids: [n.id] }, true); render(); }
      return;
    }
    e.preventDefault();
    flushDeletes(true);
    if (markReadLocally(n)) post({ action: 'read', ids: [n.id] }, true);
    window.location.href = n.link;
  }

  /** No link: the whole message unfolds, and it counts as read */
  function toggle(n, btn) {
    const item = btn.closest('.nt-item');
    const openNow = item.classList.toggle('is-open');
    btn.setAttribute('aria-expanded', openNow ? 'true' : 'false');
    if (markReadLocally(n)) {
      item.classList.remove('is-unread');
      const dot = $('.nt-dot', item);
      if (dot) { dot.nextSibling && dot.nextSibling.remove(); dot.remove(); }
      renderCounts();
      post({ action: 'read', ids: [n.id] }).then((r) => {
        if (r.ok && r.data.counts) { state.counts = r.data.counts; pending.rows.forEach((p) => adjustCounts(p.n, -1)); renderCounts(); }
      });
    }
  }

  async function readAll() {
    const btn = $('#ntReadAll');
    btn.disabled = true;
    const res = await post({ action: 'read_all' });
    if (!res.ok) {
      btn.disabled = false;
      toast(errorText(res.error), { error: true });
      return;
    }
    state.items.forEach((n) => { n.read = true; });
    pending.rows.forEach((r) => { r.n.read = true; });
    state.counts = res.data.counts;
    pending.rows.forEach((r) => adjustCounts(r.n, -1));
    render();
    toast(T.nt_all_marked);
  }

  // ===== DELETING (with Undo) =====
  function remove(n) {
    const index = state.items.indexOf(n);
    if (index === -1) return;
    const node = document.querySelector('.nt-item[data-id="' + n.id + '"]');
    const finish = () => {
      state.items.splice(state.items.indexOf(n), 1);
      adjustCounts(n, -1);
      pending.rows.push({ n: n, index: index });
      render();
      const count = pending.rows.length;
      toast(count === 1 ? T.nt_deleted : tr('nt_deleted_n', { n: count }), { action: T.nt_undo, onAction: undoDeletes, ms: UNDO_MS });
      clearTimeout(pending.timer);
      pending.timer = setTimeout(() => flushDeletes(false), UNDO_MS);
    };
    if (node && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      node.classList.add('is-leaving');
      setTimeout(finish, 220);
    } else {
      finish();
    }
  }

  function undoDeletes() {
    clearTimeout(pending.timer);
    const rows = pending.rows.splice(0);
    rows.sort((a, b) => a.index - b.index).forEach((r) => {
      state.items.splice(Math.min(r.index, state.items.length), 0, r.n);
      adjustCounts(r.n, 1);
    });
    state.items.sort((a, b) => b.id - a.id);
    render();
  }

  async function flushDeletes(keepalive) {
    clearTimeout(pending.timer);
    if (!pending.rows.length) return;
    const rows = pending.rows.splice(0);
    const res = await post({ action: 'delete', ids: rows.map((r) => r.n.id) }, keepalive);
    if (keepalive) return;
    if (!res.ok) {
      // Nothing was deleted: put them back
      rows.forEach((r) => { state.items.push(r.n); adjustCounts(r.n, 1); });
      state.items.sort((a, b) => b.id - a.id);
      render();
      toast(errorText(res.error), { error: true });
      return;
    }
    if (res.data.counts) {
      state.counts = res.data.counts;
      pending.rows.forEach((r) => adjustCounts(r.n, -1));
      renderCounts();
    }
  }

  // ===== CLEAR EVERYTHING ALREADY READ (asks first) =====
  let layer = null;

  function confirmCard(title, text, okLabel) {
    return new Promise((resolve) => {
      const prevFocus = document.activeElement;
      const id = 'ntConfirm' + Math.random().toString(36).slice(2, 8);
      const cancel = el('button', { class: 'nt-btn nt-btn--glass', type: 'button', text: T.cancel });
      const ok = el('button', { class: 'nt-btn nt-btn--danger', type: 'button', text: okLabel });
      const panel = el('div', { class: 'nt-sheet', role: 'alertdialog', 'aria-modal': 'true', 'aria-labelledby': id, tabindex: '-1' }, [
        el('h2', { class: 'nt-sheet-title', id: id, text: title }),
        el('p', { class: 'nt-sheet-text', text: text }),
        el('div', { class: 'nt-sheet-actions' }, [cancel, ok])
      ]);
      const back = el('div', { class: 'nt-layer' }, [panel]);
      const close = (value) => {
        if (!layer) return;
        layer = null;
        document.removeEventListener('keydown', onKey, true);
        back.classList.add('is-closing');
        setTimeout(() => back.remove(), 180);
        document.body.classList.remove('nt-locked');
        if (prevFocus && prevFocus.focus && document.body.contains(prevFocus)) prevFocus.focus();
        resolve(value);
      };
      const onKey = (e) => {
        if (e.key === 'Escape') { e.preventDefault(); close(false); }
        else if (e.key === 'Tab') {
          e.preventDefault();
          (document.activeElement === cancel ? ok : cancel).focus();
        }
      };
      layer = { close: close };
      back.addEventListener('click', (e) => { if (e.target === back) close(false); });
      cancel.addEventListener('click', () => close(false));
      ok.addEventListener('click', () => close(true));
      document.addEventListener('keydown', onKey, true);
      document.body.append(back);
      document.body.classList.add('nt-locked');
      cancel.focus();
    });
  }

  async function clearRead() {
    const readCount = state.counts ? state.counts.all - state.counts.unread : 0;
    if (readCount <= 0) { toast(T.nt_nothing_to_clear); return; }
    const yes = await confirmCard(T.nt_clear_read_q, tr('nt_clear_read_text', { n: readCount }), T.nt_clear_read);
    if (!yes) return;
    await flushDeletes(false);
    const btn = $('#ntClear');
    btn.disabled = true;
    const res = await post({ action: 'delete_read' });
    if (!res.ok) {
      btn.disabled = false;
      toast(errorText(res.error), { error: true });
      return;
    }
    state.items = state.items.filter(isUnread);
    state.counts = res.data.counts;
    render();
    toast(tr('nt_cleared_n', { n: res.data.changed || 0 }));
    if (!state.items.length && state.hasMore) load(false);
  }

  // ===== START =====
  function bind() {
    document.querySelectorAll('.nt-chip').forEach((b) => b.addEventListener('click', () => setFilter(b.getAttribute('data-filter'))));
    $('#ntReadAll').addEventListener('click', readAll);
    $('#ntClear').addEventListener('click', clearRead);
    $('#ntMore').addEventListener('click', () => load(true));

    // Deletes still waiting for their Undo go out when the page is left
    window.addEventListener('pagehide', () => flushDeletes(true));
    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'hidden') {
        if (pending.rows.length) hideToast(); // its Undo no longer applies
        flushDeletes(true);
        return;
      }
      if (Date.now() - state.loadedAt > 60000) refresh();
    });
    setInterval(refresh, 60000);
  }

  bind();
  load(false);
})();
