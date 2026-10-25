/**
 * Gebede — the prayer wall.
 *
 * Reads:  /prayers/api/posts/list.php (pages, ?filter= / ?before=, or ?id=),
 *         posts/reactions.php (who prays along), comments/list.php
 * Writes: posts/create.php, update.php (multipart), delete.php, react.php,
 *         comments/create.php, update.php, delete.php
 * Every POST carries the session CSRF token (X-CSRF-Token).
 *
 * Everything people wrote is drawn with textContent (links are built as
 * real nodes), never as HTML. Sheets (composer, comments, ...) have their
 * own history entry, so the phone's Back button, the app's and the floating
 * back button close the sheet instead of leaving the page.
 * Times arrive as Unix seconds and are shown in South African time.
 */
(function () {
  'use strict';

  const CFG = window.PRAYERS;
  if (!CFG) return;
  const T = CFG.t || {};
  const NS = 'http://www.w3.org/2000/svg';
  const LIMITS = CFG.limits || { text: 3000, comment: 1000 };
  const ICON_PRAY = '/assets/icons/amen.png';
  const ICON_HEART = '/assets/icons/heart.png';

  function tr(key, params) {
    let s = T[key] || key;
    if (params) {
      for (const p in params) s = s.split('{' + p + '}').join(String(params[p]));
    }
    return s;
  }

  const ERRORS = {
    csrf: 'pr_err_session', session: 'pr_err_session', offline: 'pr_err_offline', rate_limited: 'pr_err_rate',
    too_long: 'pr_err_too_long', empty: 'pr_err_empty', photo_invalid: 'pr_err_photo', photo_failed: 'pr_err_photo',
    photo_too_big: 'pr_err_photo_big', forbidden: 'pr_err_forbidden', not_found: 'pr_not_found'
  };
  const errorText = (code) => tr(ERRORS[code] || 'pr_err_generic');

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
    u.setAttribute('href', '#pr-i-' + id);
    s.appendChild(u);
    return s;
  }

  function strokeIcon(d, sw) {
    const s = document.createElementNS(NS, 'svg');
    s.setAttribute('viewBox', '0 0 24 24');
    s.setAttribute('fill', 'none');
    s.setAttribute('stroke', 'currentColor');
    s.setAttribute('stroke-width', String(sw || 2.2));
    s.setAttribute('stroke-linecap', 'round');
    s.setAttribute('stroke-linejoin', 'round');
    s.setAttribute('aria-hidden', 'true');
    const p = document.createElementNS(NS, 'path');
    p.setAttribute('d', d);
    s.appendChild(p);
    return s;
  }
  const ICON_CLOSE = 'M6 6l12 12M18 6L6 18';
  const ICON_EDIT = 'M12 20h9M16.5 3.5a2.12 2.12 0 013 3L7 19l-4 1 1-4 12.5-12.5z';
  const ICON_TRASH = 'M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 11v6M14 11v6';
  const ICON_CHAT = 'M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z';
  const ICON_SEND = 'M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z';
  const ICON_CHEVRON = 'M6 9l6 6 6-6';
  const ICON_DOTS = 'M12 5h.01M12 12h.01M12 19h.01';

  function img(src, alt) {
    return el('img', { src: src, alt: alt || '', width: 21, height: 21, decoding: 'async' });
  }

  function avatar(person, small) {
    const cls = 'pr-avatar' + (small ? ' pr-avatar--sm' : '');
    if (person.photo) {
      const i = el('img', { class: cls, src: person.photo, alt: '', loading: 'lazy', decoding: 'async', width: small ? 36 : 46, height: small ? 36 : 46 });
      // A missing photo falls back to the initials
      i.addEventListener('error', () => i.replaceWith(el('span', { class: cls + ' pr-initials', 'aria-hidden': 'true', text: person.initials || '?' })), { once: true });
      return i;
    }
    return el('span', { class: cls + ' pr-initials', 'aria-hidden': 'true', text: person.initials || '?' });
  }

  // Only http(s) links, built as real nodes: the text itself is never parsed as HTML
  const URL_RE = /\b(?:https?:\/\/|www\.)[^\s<>"']+/gi;
  function fillText(node, text) {
    text = String(text || '');
    let last = 0;
    let m;
    URL_RE.lastIndex = 0;
    while ((m = URL_RE.exec(text)) !== null) {
      let url = m[0];
      const trail = url.match(/[.,!?;:)\]]+$/);
      if (trail) url = url.slice(0, -trail[0].length);
      if (m.index > last) node.append(text.slice(last, m.index));
      node.append(el('a', { href: /^www\./i.test(url) ? 'https://' + url : url, target: '_blank', rel: 'noopener noreferrer nofollow ugc', text: url }));
      last = m.index + url.length;
      URL_RE.lastIndex = last;
    }
    if (last < text.length) node.append(text.slice(last));
    return node;
  }

  // ===== TIME (Unix seconds in, South African time out) =====
  const pad = (n) => String(n).padStart(2, '0');
  function sast(ts) {
    const d = new Date((ts + 7200) * 1000);
    return { y: d.getUTCFullYear(), m: d.getUTCMonth(), d: d.getUTCDate(), w: d.getUTCDay(), h: d.getUTCHours(), i: d.getUTCMinutes() };
  }
  const dayKey = (ts) => { const s = sast(ts); return s.y * 10000 + s.m * 100 + s.d; };
  const nowTs = () => Math.floor(Date.now() / 1000);

  function timeText(ts) {
    if (!ts) return '';
    const now = nowTs();
    const diff = Math.max(0, now - ts);
    const s = sast(ts);
    const hm = pad(s.h) + ':' + pad(s.i);
    if (dayKey(ts) === dayKey(now)) {
      if (diff < 60) return T.just_now;
      if (diff < 3600) return tr('time_minutes_ago', { n: Math.floor(diff / 60) });
      return tr('time_hours_ago', { n: Math.floor(diff / 3600) });
    }
    if (dayKey(ts) === dayKey(now - 86400)) return T.time_yesterday + ' ' + hm;
    if (diff < 6 * 86400) return ((T.days || [])[s.w] || '') + ' ' + hm;
    return s.d + ' ' + ((T.months || [])[s.m] || '') + (s.y !== sast(now).y ? ' ' + s.y : '');
  }

  function timeNode(ts, cls) {
    const s = sast(ts);
    return el('time', {
      class: cls || null,
      datetime: new Date(ts * 1000).toISOString(),
      title: s.d + ' ' + ((T.months || [])[s.m] || '') + ' ' + s.y + ' ' + pad(s.h) + ':' + pad(s.i),
      text: timeText(ts)
    });
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
    if (data === null) return { ok: false, error: res.status === 413 ? 'photo_too_big' : (res.status === 401 ? 'session' : 'server_error') };
    const ok = res.ok && data.success === true;
    return { ok: ok, data: data, error: ok ? '' : (data.error || 'server_error') };
  }

  function postJSON(url, body) {
    return api(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CFG.csrf || '' },
      body: JSON.stringify(body)
    });
  }

  function postForm(url, form) {
    return api(url, { method: 'POST', headers: { 'X-CSRF-Token': CFG.csrf || '' }, body: form });
  }

  // ===== STORAGE (a convenience; the page works without it) =====
  function store(key, value) {
    try {
      if (value === undefined) return localStorage.getItem('pr_' + key);
      localStorage.setItem('pr_' + key, value);
    } catch (e) { /* private mode */ }
    return null;
  }

  // ===== TOAST =====
  let toastTimer = 0;
  function toast(message, isError) {
    const t = $('#prToast');
    if (!t || !message) return;
    t.textContent = message;
    t.classList.toggle('is-error', !!isError);
    t.classList.add('is-on');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => t.classList.remove('is-on'), isError ? 4600 : 2600);
  }

  function setBusy(btn, busy, label) {
    if (!btn) return;
    btn.disabled = !!busy;
    btn.classList.toggle('is-busy', !!busy);
    const span = btn.querySelector('span');
    if (!span) return;
    if (busy && label !== undefined) {
      btn.dataset.label = span.textContent;
      span.textContent = label;
    } else if (!busy && btn.dataset.label) {
      span.textContent = btn.dataset.label;
      delete btn.dataset.label;
    }
  }

  // ===== LAYERS (sheets, the confirm card, the photo) =====
  // One stack: Escape, the scrim and Back close the top layer, Tab stays
  // inside it, focus returns to where it was. The first layer adds a
  // history entry, so Back closes it instead of leaving the page.
  const layers = [];
  let historyArmed = false;
  let ignorePop = false;

  function armHistory() {
    if (historyArmed) return;
    try {
      history.pushState({ pr: 'layer' }, '', window.location.href);
      historyArmed = true;
    } catch (e) { /* old WebView: Back just leaves the page */ }
  }

  function disarmHistory() {
    if (!historyArmed) return;
    historyArmed = false;
    if (history.state && history.state.pr === 'layer') {
      ignorePop = true;
      history.back();
    }
  }

  window.addEventListener('popstate', () => {
    if (ignorePop) { ignorePop = false; return; }
    if (!historyArmed) return;
    historyArmed = false;
    const top = layers[layers.length - 1];
    if (top) top.close({ fromHistory: true });
    if (layers.length) armHistory();
  });

  function lockScroll() {
    document.body.classList.toggle('pr-locked', layers.length > 0);
  }

  function openLayer(panel, opts) {
    opts = opts || {};
    const back = el('div', { class: opts.backClass || 'pr-layer' });
    if (!opts.bare) {
      panel.setAttribute('role', panel.getAttribute('role') || 'dialog');
      panel.setAttribute('aria-modal', 'true');
      if (opts.labelledBy) panel.setAttribute('aria-labelledby', opts.labelledBy);
      panel.setAttribute('tabindex', '-1');
    }
    back.appendChild(panel);

    const prevFocus = document.activeElement;
    const layer = { back: back, panel: panel, onClose: null, beforeClose: null };

    layer.close = async function (how) {
      how = how || {};
      // A forced close goes through even while "Discard?" is still being asked
      if (layer.closed || (layer.closing && !how.force)) return;
      if (layer.beforeClose && !how.force) {
        layer.closing = true;
        const ok = await layer.beforeClose();
        layer.closing = false;
        if (layer.closed) return; // closed by force meanwhile
        if (!ok) {
          if (how.fromHistory) armHistory(); // Back was used up: put the entry back
          return;
        }
      }
      layer.closed = true;
      layer.closing = true;
      back.classList.add('is-closing');
      setTimeout(() => back.remove(), 180);
      const i = layers.indexOf(layer);
      if (i !== -1) layers.splice(i, 1);
      lockScroll();
      // The last layer gone: take our history entry away again (after a Back
      // the popstate handler has already let go of it, unless a nested card
      // added a new one, which goes here too)
      if (!layers.length) disarmHistory();
      if (layer.onClose) layer.onClose();
      if (prevFocus && prevFocus.focus && document.body.contains(prevFocus)) {
        try { prevFocus.focus({ preventScroll: true }); } catch (e) { prevFocus.focus(); }
      }
    };

    layers.push(layer);
    armHistory();
    back.addEventListener('click', (e) => { if (e.target === back || opts.closeOnAnyClick) layer.close(); });
    document.body.appendChild(back);
    lockScroll();
    enableSwipeDown(panel, layer);

    const first = panel.querySelector('[autofocus]') || panel;
    try { first.focus({ preventScroll: true }); } catch (e) { first.focus(); }
    return layer;
  }

  // Phones: pull a sheet down by its handle to close it
  function enableSwipeDown(panel, layer) {
    const grip = panel.querySelector('.pr-sheet-grip');
    if (!grip) return;
    let startY = 0;
    let dy = 0;
    grip.addEventListener('touchstart', (e) => { startY = e.touches[0].clientY; dy = 0; }, { passive: true });
    grip.addEventListener('touchmove', (e) => {
      dy = Math.max(0, e.touches[0].clientY - startY);
      panel.style.transform = 'translateY(' + dy + 'px)';
    }, { passive: true });
    grip.addEventListener('touchend', () => {
      panel.style.transform = '';
      if (dy > 90) layer.close();
    });
  }

  /** A sheet (phone) / card (desktop) with a title, a close button, a body and a foot */
  function sheet(title, cls) {
    const id = 'prSheet' + Math.random().toString(36).slice(2, 8);
    const panel = el('div', { class: 'pr-sheet' + (cls ? ' ' + cls : '') });
    const body = el('div', { class: 'pr-sheet-body' });
    const foot = el('div', { class: 'pr-sheet-foot', hidden: true });
    let layer = null;
    panel.append(
      el('span', { class: 'pr-sheet-grip', 'aria-hidden': 'true' }),
      el('div', { class: 'pr-sheet-head' }, [
        el('h2', { class: 'pr-sheet-title', id: id, text: title }),
        el('button', { class: 'pr-sheet-close', type: 'button', 'aria-label': T.close, on: { click: () => layer.close() } }, [strokeIcon(ICON_CLOSE)])
      ]),
      body,
      foot
    );
    layer = openLayer(panel, { labelledBy: id });
    layer.body = body;
    layer.foot = foot;
    return layer;
  }

  /** A confirm card instead of confirm() (which shows the site address in the app) */
  function confirmCard(title, text, okLabel, cancelLabel, danger) {
    return new Promise((resolve) => {
      const id = 'prConfirm' + Math.random().toString(36).slice(2, 8);
      let answered = false;
      const ok = el('button', { class: 'pr-btn ' + (danger ? 'pr-btn--danger' : 'pr-btn--primary'), type: 'button', text: okLabel });
      const cancel = el('button', { class: 'pr-btn pr-btn--glass', type: 'button', text: cancelLabel || T.cancel, autofocus: true });
      const panel = el('div', { class: 'pr-sheet pr-confirm', role: 'alertdialog' }, [
        el('h2', { class: 'pr-sheet-title', id: id, text: title }),
        text ? el('p', { class: 'pr-confirm-text', text: text }) : null,
        el('div', { class: 'pr-form-actions' }, [cancel, ok])
      ]);
      const layer = openLayer(panel, { labelledBy: id });
      const finish = (v) => { if (answered) return; answered = true; layer.close(); resolve(v); };
      layer.onClose = () => { if (!answered) { answered = true; resolve(false); } };
      ok.addEventListener('click', () => finish(true));
      cancel.addEventListener('click', () => finish(false));
    });
  }

  function trapTab(e, root) {
    const focusable = Array.prototype.filter.call(
      root.querySelectorAll('button, [href], input, textarea, [tabindex]:not([tabindex="-1"])'),
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

  function autoGrow(ta, max) {
    ta.style.height = 'auto';
    ta.style.height = Math.min(ta.scrollHeight + 2, max) + 'px';
  }

  // ===== STATE =====
  const FILTERS = ['all', 'town', 'mine'];
  const savedFilter = store('filter');
  const state = {
    filter: FILTERS.indexOf(savedFilter) !== -1 && (savedFilter !== 'town' || CFG.town) ? savedFilter : 'all',
    posts: [],
    hasMore: false,
    loading: false,
    error: '',
    req: 0,
    loadedAt: 0
  };
  const byId = (id) => state.posts.find((p) => p.id === id) || null;

  // ===== LOADING =====
  async function load(more) {
    const req = ++state.req;
    state.loading = true;
    state.error = '';
    if (!more) render();
    setBusy($('#prMore'), true);

    const p = new URLSearchParams({ filter: state.filter });
    if (more && state.posts.length) p.set('before', String(state.posts[state.posts.length - 1].id));
    const res = await api('/prayers/api/posts/list.php?' + p.toString());
    if (req !== state.req) return;

    state.loading = false;
    setBusy($('#prMore'), false);
    if (!res.ok) {
      if (more) { toast(errorText(res.error), true); return; }
      state.error = res.error;
      state.posts = [];
      render();
      return;
    }
    const rows = res.data.posts || [];
    if (more) {
      const known = new Set(state.posts.map((x) => x.id));
      state.posts = state.posts.concat(rows.filter((x) => !known.has(x.id)));
    } else {
      state.posts = rows;
    }
    state.hasMore = !!res.data.has_more;
    state.loadedAt = Date.now();
    render();
  }

  // ===== DRAWING =====
  function tallyNode(p) {
    const parts = [];
    if (p.pray_count) parts.push(el('span', { class: 'pr-tally-item' }, [img(ICON_PRAY), el('span', { text: p.pray_count === 1 ? T.pr_tally_pray_one : tr('pr_tally_pray_n', { n: p.pray_count }) })]));
    if (p.heart_count) parts.push(el('span', { class: 'pr-tally-item' }, [img(ICON_HEART), el('span', { text: p.heart_count === 1 ? T.pr_tally_heart_one : tr('pr_tally_heart_n', { n: p.heart_count }) })]));
    if (p.comment_count) parts.push(el('span', { class: 'pr-tally-item pr-tally-comments', text: p.comment_count === 1 ? T.pr_tally_comments_one : tr('pr_tally_comments_n', { n: p.comment_count }) }));
    if (!parts.length) return null;
    return el('button', {
      class: 'pr-tally', type: 'button',
      on: { click: () => (p.pray_count || p.heart_count) ? openWho(p) : openComments(p) }
    }, parts);
  }

  function actionsNode(p) {
    const pray = el('button', {
      class: 'pr-act pr-act--pray', type: 'button', 'aria-pressed': p.prayed ? 'true' : 'false',
      on: { click: () => react(p, 'pray') }
    }, [img(ICON_PRAY), el('span', { text: p.prayed ? T.pr_praying : T.pr_pray })]);
    const heart = el('button', {
      class: 'pr-act pr-act--heart pr-act--icon', type: 'button', 'aria-pressed': p.hearted ? 'true' : 'false',
      'aria-label': T.pr_heart, title: T.pr_heart, on: { click: () => react(p, 'heart') }
    }, [img(ICON_HEART)]);
    const talk = el('button', {
      class: 'pr-act pr-act--talk pr-act--icon', type: 'button', 'aria-label': T.pr_comment, title: T.pr_comment,
      on: { click: () => openComments(p) }
    }, [strokeIcon(ICON_CHAT, 2)]);
    return el('div', { class: 'pr-actions' }, [pray, heart, talk]);
  }

  function postCard(p) {
    const meta = [];
    if (p.author.town) meta.push(icon('pin'), el('span', { text: p.author.town }), el('span', { 'aria-hidden': 'true', text: '·' }));
    meta.push(timeNode(p.ts));
    if (p.edited) meta.push(el('span', { 'aria-hidden': 'true', text: '·' }), el('span', { text: T.pr_edited }));

    const text = fillText(el('div', { class: 'pr-text' }), p.text);
    const card = el('article', { class: 'pr-post', id: 'post-' + p.id, 'data-id': p.id }, [
      el('header', { class: 'pr-post-head' }, [
        avatar(p.author),
        el('div', { class: 'pr-who' }, [
          el('div', { class: 'pr-name', text: p.author.name }),
          el('div', { class: 'pr-meta' }, meta)
        ]),
        (p.can_edit || p.can_delete) ? el('button', {
          class: 'pr-menu-btn', type: 'button', 'aria-label': T.pr_options, 'aria-haspopup': 'dialog',
          on: { click: () => openMenu(p) }
        }, [strokeIcon(ICON_DOTS, 3)]) : null
      ]),
      text,
      p.photo ? el('button', { class: 'pr-photo', type: 'button', 'aria-label': T.pr_photo_alt, on: { click: () => lightbox(p.photo) } }, [
        el('img', { src: p.photo, alt: T.pr_photo_alt, loading: 'lazy', decoding: 'async' })
      ]) : null,
      tallyNode(p),
      actionsNode(p)
    ]);
    return card;
  }

  /** Long prayers fold after eight lines (measured once they are on the page) */
  function foldLong(root) {
    root.querySelectorAll('.pr-text:not([data-folded])').forEach((text) => {
      text.setAttribute('data-folded', '');
      text.classList.add('is-clamped');
      if (text.scrollHeight <= text.clientHeight + 4) {
        text.classList.remove('is-clamped');
        return;
      }
      const label = el('span', { text: T.read_more });
      const btn = el('button', { class: 'pr-fold', type: 'button', 'aria-expanded': 'false' }, [label, strokeIcon(ICON_CHEVRON)]);
      btn.addEventListener('click', () => {
        const open = text.classList.toggle('is-clamped') === false;
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        label.textContent = open ? T.read_less : T.read_more;
      });
      text.after(btn);
    });
  }

  function emptyState() {
    const box = el('div', { class: 'pr-card pr-empty' });
    if (state.error) {
      box.append(
        el('div', { class: 'pr-empty-orb pr-empty-orb--quiet' }, [icon('prayer')]),
        el('p', { class: 'pr-empty-text', text: T.pr_load_failed }),
        el('p', { class: 'pr-empty-hint', text: errorText(state.error) }),
        el('button', { class: 'pr-btn pr-btn--glass', type: 'button', on: { click: () => load(false) } }, [el('span', { text: T.pr_retry })])
      );
      return box;
    }
    const text = state.filter === 'mine' ? T.pr_empty_mine : (state.filter === 'town' ? tr('pr_empty_town', { town: CFG.town }) : T.pr_empty);
    box.append(
      el('div', { class: 'pr-empty-orb' }, [icon('prayer')]),
      el('p', { class: 'pr-empty-text', text: text }),
      el('p', { class: 'pr-empty-hint', text: T.pr_empty_hint }),
      el('button', { class: 'pr-btn pr-btn--primary', type: 'button', on: { click: () => openComposer(null) } }, [el('span', { text: T.share_prayer_testimony })])
    );
    return box;
  }

  function render() {
    document.querySelectorAll('.pr-chip').forEach((b) => b.setAttribute('aria-pressed', b.getAttribute('data-filter') === state.filter ? 'true' : 'false'));
    const feed = $('#prFeed');
    const moreWrap = $('#prMoreWrap');
    feed.setAttribute('aria-busy', state.loading ? 'true' : 'false');
    feed.classList.toggle('is-loading', state.loading && state.posts.length > 0);

    if (state.loading && !state.posts.length) {
      feed.replaceChildren(el('div', { class: 'pr-skel', 'aria-hidden': 'true' }, [el('i'), el('i')]));
      moreWrap.hidden = true;
      return;
    }
    if (state.loading) return;
    if (state.error || !state.posts.length) {
      feed.replaceChildren(emptyState());
      moreWrap.hidden = true;
      return;
    }
    feed.replaceChildren(...state.posts.map(postCard));
    foldLong(feed);
    moreWrap.hidden = !state.hasMore;
  }

  /** Redraw one card (after an edit) */
  function patchCard(p) {
    const old = document.getElementById('post-' + p.id);
    if (!old) return;
    const fresh = postCard(p);
    fresh.style.animation = 'none';
    old.replaceWith(fresh);
    foldLong(fresh.parentNode || document);
  }

  /** Update a card's counts and buttons in place (an opened prayer stays open, focus stays put) */
  function patchCounts(p) {
    const card = document.getElementById('post-' + p.id);
    if (!card) return;
    const pray = $('.pr-act--pray', card);
    const heart = $('.pr-act--heart', card);
    if (pray) {
      pray.setAttribute('aria-pressed', p.prayed ? 'true' : 'false');
      $('span', pray).textContent = p.prayed ? T.pr_praying : T.pr_pray;
    }
    if (heart) heart.setAttribute('aria-pressed', p.hearted ? 'true' : 'false');
    const old = $('.pr-tally', card);
    const fresh = tallyNode(p);
    if (old && fresh) old.replaceWith(fresh);
    else if (old) old.remove();
    else if (fresh) $('.pr-actions', card).before(fresh);
  }

  function setFilter(f) {
    if (state.filter === f) return;
    state.filter = f;
    store('filter', f);
    load(false);
  }

  // ===== REACTIONS =====
  const reacting = new Set();

  async function react(p, type) {
    const key = p.id + type;
    if (reacting.has(key)) return;
    reacting.add(key);
    // Shown at once; the server's answer settles the counts
    const field = type === 'pray' ? 'prayed' : 'hearted';
    const count = type === 'pray' ? 'pray_count' : 'heart_count';
    const before = { prayed: p.prayed, hearted: p.hearted, pray_count: p.pray_count, heart_count: p.heart_count };
    p[field] = !p[field];
    p[count] = Math.max(0, p[count] + (p[field] ? 1 : -1));
    patchCounts(p);

    const res = await postJSON('/prayers/api/posts/react.php', { post_id: p.id, type: type });
    reacting.delete(key);
    if (!res.ok) {
      Object.assign(p, before);
      patchCounts(p);
      toast(errorText(res.error), true);
      return;
    }
    Object.assign(p, {
      prayed: !!res.data.prayed, hearted: !!res.data.hearted,
      pray_count: res.data.pray_count || 0, heart_count: res.data.heart_count || 0
    });
    patchCounts(p);
  }

  // ===== WHO PRAYS ALONG =====
  async function openWho(p) {
    const layer = sheet(T.pr_who_title);
    const tabs = el('div', { class: 'pr-tabs', role: 'tablist' });
    const list = el('div', { class: 'pr-people', role: 'tabpanel' }, [el('div', { class: 'pr-skel', 'aria-hidden': 'true' }, [el('i')])]);
    layer.body.append(tabs, list);

    const res = await api('/prayers/api/posts/reactions.php?post_id=' + p.id);
    if (!res.ok) {
      list.replaceChildren(el('p', { class: 'pr-c-empty', text: errorText(res.error) }));
      return;
    }
    const groups = { pray: res.data.pray || [], heart: res.data.heart || [] };
    let current = groups.pray.length || !groups.heart.length ? 'pray' : 'heart';

    function draw() {
      tabs.replaceChildren(...['pray', 'heart'].map((k) => el('button', {
        class: 'pr-tab', type: 'button', role: 'tab', 'aria-selected': k === current ? 'true' : 'false',
        on: { click: () => { current = k; draw(); } }
      }, [img(k === 'pray' ? ICON_PRAY : ICON_HEART), el('span', { text: (k === 'pray' ? T.pr_tab_pray : T.pr_tab_heart) + ' · ' + groups[k].length })])));
      const people = groups[current];
      list.replaceChildren(...(people.length
        ? people.map((person) => el('div', { class: 'pr-person' }, [avatar(person, true), el('span', { text: person.me ? person.name + ' (' + T.pr_you + ')' : person.name })]))
        : [el('p', { class: 'pr-c-empty', text: T.pr_who_none })]));
    }
    draw();
  }

  // ===== PHOTO VIEWER =====
  function lightbox(src) {
    const close = el('button', { class: 'pr-sheet-close', type: 'button', 'aria-label': T.close }, [strokeIcon(ICON_CLOSE)]);
    const panel = el('div', { class: 'pr-lightbox-inner', role: 'dialog', 'aria-modal': 'true', 'aria-label': T.pr_photo_alt, tabindex: '-1' }, [
      el('img', { src: src, alt: T.pr_photo_alt }),
      close
    ]);
    openLayer(panel, { backClass: 'pr-lightbox', closeOnAnyClick: true, bare: true });
  }

  // ===== MENU / DELETE =====
  function openMenu(p) {
    const layer = sheet(T.pr_options);
    const list = el('div', { class: 'pr-actions-list' });
    if (p.can_edit) {
      list.append(el('button', { class: 'pr-action', type: 'button', on: { click: () => { layer.close({ force: true }); setTimeout(() => openComposer(p), 200); } } }, [strokeIcon(ICON_EDIT, 2), el('span', { text: T.pr_edit })]));
    }
    if (p.can_delete) {
      list.append(el('button', { class: 'pr-action is-danger', type: 'button', on: { click: () => { layer.close({ force: true }); setTimeout(() => removePost(p), 200); } } }, [strokeIcon(ICON_TRASH, 2), el('span', { text: T.pr_delete })]));
    }
    layer.body.append(list);
  }

  async function removePost(p) {
    const yes = await confirmCard(T.pr_delete_q, T.pr_delete_text, T.pr_delete, T.cancel, true);
    if (!yes) return;
    const res = await postJSON('/prayers/api/posts/delete.php', { post_id: p.id });
    if (!res.ok) { toast(errorText(res.error), true); return; }
    const card = document.getElementById('post-' + p.id);
    const done = () => {
      state.posts = state.posts.filter((x) => x.id !== p.id);
      render();
      toast(T.pr_deleted);
    };
    if (card) { card.classList.add('is-leaving'); setTimeout(done, 260); } else done();
  }

  // ===== COMPOSER =====
  /** Resize + convert a photo in the browser so uploads stay small (the server re-encodes anyway) */
  function shrinkPhoto(file) {
    return new Promise((resolve) => {
      if (!file || !/^image\//.test(file.type || '') || !window.URL || !URL.createObjectURL) return resolve(file);
      const url = URL.createObjectURL(file);
      const image = new Image();
      image.onload = () => {
        try {
          const ratio = Math.min(1, 1920 / Math.max(image.naturalWidth, image.naturalHeight));
          const canvas = document.createElement('canvas');
          canvas.width = Math.max(1, Math.round(image.naturalWidth * ratio));
          canvas.height = Math.max(1, Math.round(image.naturalHeight * ratio));
          canvas.getContext('2d').drawImage(image, 0, 0, canvas.width, canvas.height);
          URL.revokeObjectURL(url);
          canvas.toBlob((blob) => {
            if (!blob || blob.size > file.size) return resolve(file);
            resolve(new File([blob], 'photo.' + (blob.type === 'image/webp' ? 'webp' : 'jpg'), { type: blob.type }));
          }, 'image/webp', 0.86);
        } catch (e) {
          URL.revokeObjectURL(url);
          resolve(file);
        }
      };
      image.onerror = () => { URL.revokeObjectURL(url); resolve(file); };
      image.src = url;
    });
  }

  function openComposer(post, opts) {
    opts = opts || {};
    const editing = !!post;
    const layer = sheet(editing ? T.pr_edit_title : T.pr_new_title, 'pr-sheet--compose');
    const ta = el('textarea', {
      class: 'pr-textarea', rows: 5, maxlength: LIMITS.text + 200, placeholder: T.pr_text_ph,
      'aria-label': editing ? T.pr_edit_title : T.pr_new_title, autofocus: !opts.pickPhoto
    });
    ta.value = editing ? post.text : '';
    const counter = el('span', { class: 'pr-counter', 'aria-live': 'polite' });
    const file = el('input', { class: 'pr-file', type: 'file', accept: 'image/*', tabindex: '-1', 'aria-hidden': 'true' });
    const pickLabel = el('span');
    const pick = el('button', { class: 'pr-pick', type: 'button', on: { click: () => file.click() } }, [icon('photo'), pickLabel]);
    const preview = el('div', { class: 'pr-preview', hidden: true });
    const submit = el('button', { class: 'pr-btn pr-btn--primary', type: 'button' }, [
      strokeIcon(ICON_SEND, 2), el('span', { text: editing ? T.pr_save_btn : T.pr_share_btn })
    ]);

    let photoFile = null;              // a newly chosen photo
    let keepOld = editing && !!post.photo;
    let previewUrl = '';

    function drawPhoto() {
      if (previewUrl) { URL.revokeObjectURL(previewUrl); previewUrl = ''; }
      const src = photoFile ? (previewUrl = URL.createObjectURL(photoFile)) : (keepOld ? post.photo : '');
      pickLabel.textContent = src ? T.pr_change_photo : T.pr_add_photo;
      preview.hidden = !src;
      preview.replaceChildren();
      if (!src) return;
      preview.append(
        el('img', { src: src, alt: T.pr_photo_alt }),
        el('button', {
          class: 'pr-preview-x', type: 'button',
          on: { click: () => { photoFile = null; keepOld = false; drawPhoto(); } }
        }, [strokeIcon(ICON_CLOSE), el('span', { text: T.pr_remove_photo })])
      );
    }

    function updateCounter() {
      const left = LIMITS.text - ta.value.trim().length;
      counter.textContent = left < 300 ? tr('pr_chars_left', { n: left }) : '';
      counter.classList.toggle('is-near', left < 300 && left >= 0);
      counter.classList.toggle('is-over', left < 0);
      ta.classList.toggle('is-invalid', left < 0);
    }

    const dirty = () => editing
      ? (ta.value.trim() !== post.text || !!photoFile || keepOld !== !!post.photo)
      : (ta.value.trim() !== '' || !!photoFile);

    file.addEventListener('change', () => {
      const f = file.files && file.files[0];
      file.value = '';
      if (!f) return;
      if (!/^image\//.test(f.type || '')) { toast(T.pr_err_photo, true); return; }
      photoFile = f;
      drawPhoto();
    });
    ta.addEventListener('input', () => { updateCounter(); autoGrow(ta, Math.round(window.innerHeight * 0.45)); });
    ta.addEventListener('keydown', (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { e.preventDefault(); save(); }
    });

    async function save() {
      if (submit.disabled) return; // already sending (a second Ctrl+Enter)
      const text = ta.value.trim();
      if (!text) { toast(T.pr_err_empty, true); ta.focus(); return; }
      if (text.length > LIMITS.text) { toast(T.pr_err_too_long, true); ta.focus(); return; }
      setBusy(submit, true, editing ? T.pr_saving : T.pr_posting);
      const form = new FormData();
      form.append('text', text);
      if (editing) {
        form.append('post_id', String(post.id));
        if (!keepOld && !photoFile && post.photo) form.append('remove_photo', '1');
      }
      if (photoFile) form.append('photo', await shrinkPhoto(photoFile));

      const res = await postForm(editing ? '/prayers/api/posts/update.php' : '/prayers/api/posts/create.php', form);
      setBusy(submit, false);
      if (!res.ok) { toast(errorText(res.error), true); return; }
      const saved = res.data.post;
      layer.close({ force: true });
      if (saved) {
        if (editing) {
          const i = state.posts.findIndex((x) => x.id === saved.id);
          if (i !== -1) state.posts[i] = saved;
          patchCard(saved);
        } else {
          if (state.filter === 'all' || state.filter === 'mine' || (state.filter === 'town' && saved.author.town === CFG.town)) {
            state.posts.unshift(saved);
            render();
            const card = document.getElementById('post-' + saved.id);
            if (card) card.scrollIntoView({ behavior: 'smooth', block: 'center' });
          }
        }
      }
      toast(editing ? T.pr_saved : T.pr_shared);
    }
    submit.addEventListener('click', save);

    layer.body.append(
      el('div', { class: 'pr-compose-who' }, [avatar(CFG.me), el('span', { text: CFG.me.name })]),
      ta,
      el('div', { class: 'pr-compose-tools' }, [pick, file, counter]),
      preview
    );
    layer.foot.hidden = false;
    layer.foot.append(el('div', { class: 'pr-form-actions' }, [
      el('button', { class: 'pr-btn pr-btn--glass', type: 'button', text: T.cancel, on: { click: () => layer.close() } }),
      submit
    ]));
    layer.beforeClose = async () => !dirty() || confirmCard(T.pr_discard_q, '', T.pr_discard, T.pr_keep, true);
    layer.onClose = () => { if (previewUrl) URL.revokeObjectURL(previewUrl); };

    drawPhoto();
    updateCounter();
    autoGrow(ta, Math.round(window.innerHeight * 0.45));
    // Still inside the tap that opened the composer, so the picker may open
    if (opts.pickPhoto) file.click();
  }

  // ===== COMMENTS =====
  async function openComments(p) {
    const layer = sheet(T.comments, 'pr-sheet--talk');
    const list = el('div', { class: 'pr-c-list', 'aria-live': 'polite' }, [el('div', { class: 'pr-skel', 'aria-hidden': 'true' }, [el('i')])]);
    const about = el('p', { class: 'pr-talk-about', text: '“' + p.text.replace(/\s+/g, ' ').slice(0, 160) + (p.text.length > 160 ? '…' : '') + '”' });
    layer.body.append(about, list);

    const input = el('textarea', { class: 'pr-c-input', rows: 1, maxlength: LIMITS.comment + 100, placeholder: T.write_encouraging_word, 'aria-label': T.write_encouraging_word });
    const send = el('button', { class: 'pr-send', type: 'button', 'aria-label': T.send, title: T.send, disabled: true }, [strokeIcon(ICON_SEND, 2)]);
    layer.foot.hidden = false;
    layer.foot.append(el('div', { class: 'pr-c-compose' }, [input, send]));
    layer.beforeClose = async () => !input.value.trim() || confirmCard(T.pr_discard_q, '', T.pr_discard, T.pr_keep, true);

    let comments = [];

    function setCount(n) {
      p.comment_count = n;
      const live = byId(p.id);
      if (live && live !== p) live.comment_count = n;
      patchCounts(live || p);
    }

    function commentNode(c) {
      const textNode = fillText(el('div', { class: 'pr-c-text' }), c.text);
      const bubble = el('div', { class: 'pr-c-bubble' }, [el('span', { class: 'pr-c-name', text: c.author.name }), textNode]);
      const foot = el('div', { class: 'pr-c-foot' }, [timeNode(c.ts)]);
      const node = el('div', { class: 'pr-c' + (c.mine ? ' is-mine' : ''), 'data-id': c.id }, [
        avatar(c.author, true),
        el('div', { class: 'pr-c-body' }, [bubble, foot])
      ]);
      if (c.can_edit) foot.append(el('button', { class: 'pr-c-btn', type: 'button', text: T.pr_comment_edit, on: { click: () => editComment(c, node) } }));
      if (c.can_delete) foot.append(el('button', { class: 'pr-c-btn is-danger', type: 'button', text: T.pr_delete, on: { click: () => deleteComment(c) } }));
      return node;
    }

    function draw() {
      list.replaceChildren(...(comments.length ? comments.map(commentNode) : [el('p', { class: 'pr-c-empty', text: T.pr_no_comments })]));
    }

    function editComment(c, node) {
      const ta = el('textarea', { class: 'pr-c-input', rows: 2, maxlength: LIMITS.comment + 100, 'aria-label': T.pr_comment_edit });
      ta.value = c.text;
      const save = el('button', { class: 'pr-btn pr-btn--primary', type: 'button' }, [el('span', { text: T.pr_save_btn })]);
      const cancel = el('button', { class: 'pr-btn pr-btn--glass', type: 'button', text: T.cancel, on: { click: draw } });
      save.addEventListener('click', async () => {
        const text = ta.value.trim();
        if (!text) { toast(T.pr_err_empty, true); return; }
        setBusy(save, true, T.pr_saving);
        const res = await postJSON('/prayers/api/comments/update.php', { comment_id: c.id, text: text });
        setBusy(save, false);
        if (!res.ok) { toast(errorText(res.error), true); return; }
        const i = comments.findIndex((x) => x.id === c.id);
        if (i !== -1 && res.data.comment) comments[i] = res.data.comment;
        draw();
        toast(T.pr_comment_saved);
      });
      const body = $('.pr-c-body', node);
      body.replaceChildren(el('div', { class: 'pr-c-edit' }, [ta, el('div', { class: 'pr-form-actions' }, [cancel, save])]));
      autoGrow(ta, 220);
      ta.addEventListener('input', () => autoGrow(ta, 220));
      ta.focus();
    }

    async function deleteComment(c) {
      const yes = await confirmCard(T.pr_comment_delete_q, '', T.pr_delete, T.cancel, true);
      if (!yes) return;
      const res = await postJSON('/prayers/api/comments/delete.php', { comment_id: c.id });
      if (!res.ok) { toast(errorText(res.error), true); return; }
      comments = comments.filter((x) => x.id !== c.id);
      draw();
      setCount(comments.length);
      toast(T.pr_comment_deleted);
    }

    async function submit() {
      if (send.classList.contains('is-busy')) return; // already sending
      const text = input.value.trim();
      if (!text) return;
      if (text.length > LIMITS.comment) { toast(T.pr_err_too_long, true); return; }
      send.disabled = true;
      send.classList.add('is-busy');
      const res = await postJSON('/prayers/api/comments/create.php', { post_id: p.id, text: text });
      send.classList.remove('is-busy');
      if (!res.ok) {
        send.disabled = false;
        toast(errorText(res.error), true);
        return;
      }
      input.value = '';
      autoGrow(input, 140);
      if (res.data.comment) comments.push(res.data.comment);
      draw();
      setCount(comments.length);
      layer.body.scrollTop = layer.body.scrollHeight;
    }

    input.addEventListener('input', () => { send.disabled = !input.value.trim(); autoGrow(input, 140); });
    input.addEventListener('keydown', (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { e.preventDefault(); submit(); }
    });
    send.addEventListener('click', submit);

    const res = await api('/prayers/api/comments/list.php?post_id=' + p.id);
    if (!layer.panel.isConnected) return;
    if (!res.ok) {
      list.replaceChildren(el('p', { class: 'pr-c-empty', text: errorText(res.error) }));
      return;
    }
    comments = res.data.comments || [];
    draw();
    if (comments.length !== p.comment_count) setCount(comments.length);
    layer.body.scrollTop = layer.body.scrollHeight;
  }

  // ===== A PRAYER FROM A NOTIFICATION (#post-12, #post-12-c opens its comments) =====
  async function openTarget(id, talk) {
    let p = byId(id);
    if (!p) {
      const res = await api('/prayers/api/posts/list.php?id=' + id);
      if (!res.ok || !res.data.posts || !res.data.posts[0]) { toast(T.pr_not_found, true); return; }
      p = res.data.posts[0];
      state.posts = [p].concat(state.posts.filter((x) => x.id !== p.id));
      render();
    }
    document.querySelectorAll('.pr-post.is-target').forEach((n) => n.classList.remove('is-target'));
    const card = document.getElementById('post-' + id);
    if (card) {
      card.scrollIntoView({ behavior: 'smooth', block: 'start' });
      card.classList.add('is-target');
      setTimeout(() => card.classList.remove('is-target'), 3000);
    }
    if (talk) setTimeout(() => openComments(p), 350);
  }

  // ===== START =====
  function bind() {
    const compose = () => openComposer(null);
    $('#prPrompt').addEventListener('click', compose);
    $('#prPromptPhoto').addEventListener('click', () => openComposer(null, { pickPhoto: true }));
    const fab = $('#prFab');
    if (fab) fab.addEventListener('click', compose);
    document.querySelectorAll('.pr-chip').forEach((b) => b.addEventListener('click', () => setFilter(b.getAttribute('data-filter'))));
    $('#prMore').addEventListener('click', () => load(true));

    document.addEventListener('keydown', (e) => {
      if (!layers.length) return;
      const top = layers[layers.length - 1];
      if (e.key === 'Escape') { e.preventDefault(); top.close(); }
      else if (e.key === 'Tab') trapTab(e, top.panel);
    });

    // Back after a while: fresh prayers (others may have shared or prayed)
    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'visible' && !layers.length && Date.now() - state.loadedAt > 120000) load(false);
    });
  }

  /** #post-12 / #post-12-c in the address: show that prayer (the address is tidied up again) */
  function takeHash() {
    const m = /^#post-(\d+)(-c)?$/.exec(window.location.hash || '');
    if (!m) return null;
    try { history.replaceState(history.state, '', window.location.pathname); } catch (e) { /* old WebView */ }
    return { id: +m[1], talk: !!m[2] };
  }

  async function start() {
    const target = takeHash();
    bind();
    // A notification opened while the page is already showing
    window.addEventListener('hashchange', () => {
      const t = takeHash();
      if (!t) return;
      layers.slice().reverse().forEach((l) => l.close({ force: true }));
      openTarget(t.id, t.talk);
    });
    await load(false);
    if (target) openTarget(target.id, target.talk);
  }

  start();
})();
