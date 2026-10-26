/**
 * Gedagtes — today's thought (drawn by gedagtes.php) and the archive.
 *
 * Reads: /gedagtes/api/list.php (pages of earlier thoughts, ?before= / ?q=)
 * Nothing here writes: thoughts are written by elders in the admin.
 * Dates are plain "YYYY-MM-DD" strings in South African time; the browser
 * never parses a date string (iOS Safari safe).
 */
(function () {
  'use strict';

  const CFG = window.GEDAGTES;
  if (!CFG) return;
  const T = CFG.t || {};
  const NS = 'http://www.w3.org/2000/svg';

  function tr(key, params) {
    let s = T[key] || key;
    if (params) {
      for (const p in params) s = s.split('{' + p + '}').join(String(params[p]));
    }
    return s;
  }

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
    u.setAttribute('href', '#gd-i-' + id);
    s.appendChild(u);
    return s;
  }

  function strokeIcon(d) {
    const s = document.createElementNS(NS, 'svg');
    s.setAttribute('viewBox', '0 0 24 24');
    s.setAttribute('fill', 'none');
    s.setAttribute('stroke', 'currentColor');
    s.setAttribute('stroke-width', '2.2');
    s.setAttribute('stroke-linecap', 'round');
    s.setAttribute('stroke-linejoin', 'round');
    s.setAttribute('aria-hidden', 'true');
    const p = document.createElementNS(NS, 'path');
    p.setAttribute('d', d);
    s.appendChild(p);
    return s;
  }
  const ICON_SHARE = 'M4 12v7a2 2 0 002 2h12a2 2 0 002-2v-7M16 6l-4-4-4 4M12 2v13';
  const ICON_CHEVRON = 'M6 9l6 6 6-6';
  const ICON_SEARCH = 'M11 4a7 7 0 100 14 7 7 0 000-14zM20 20l-3.5-3.5';

  // ===== DATES (strings in, strings out) =====
  const pad = (n) => String(n).padStart(2, '0');
  const parts = (s) => [+s.slice(0, 4), +s.slice(5, 7), +s.slice(8, 10)];
  function toDate(s) { const p = parts(s); return new Date(p[0], p[1] - 1, p[2], 12); }
  function ymd(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
  function addDays(s, n) { const d = toDate(s); d.setDate(d.getDate() + n); return ymd(d); }

  const months = T.months || [];
  const days = T.days || [];
  const daysShort = T.days_short || [];
  const weekday = (s) => toDate(s).getDay();
  function dayLong(s) {
    const p = parts(s);
    return (days[weekday(s)] || '') + ' ' + p[2] + ' ' + (months[p[1] - 1] || '') + ' ' + p[0];
  }
  function monthLabel(key) {
    return (months[+key.slice(5, 7) - 1] || '') + ' ' + key.slice(0, 4);
  }
  function whenText(date) {
    if (date === addDays(CFG.today, -1)) return T.time_yesterday + ' · ' + dayLong(date);
    return dayLong(date);
  }

  // ===== API =====
  async function api(url) {
    let res;
    try {
      res = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    } catch (e) {
      return { ok: false, error: 'offline' };
    }
    if (res.redirected && /\/login\.php/.test(res.url)) return { ok: false, error: 'session' };
    let data = null;
    try { data = JSON.parse(await res.text()); } catch (e) { data = null; }
    if (!data || !res.ok || data.success !== true) return { ok: false, error: (data && data.error) || 'server_error' };
    return { ok: true, data: data };
  }

  // ===== TOAST =====
  let toastTimer = 0;
  function toast(message, isError) {
    const t = $('#gdToast');
    if (!t || !message) return;
    t.textContent = message;
    t.classList.toggle('is-error', !!isError);
    t.classList.add('is-on');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => t.classList.remove('is-on'), isError ? 4200 : 2400);
  }

  // ===== SHARE / COPY (works in WebViews without clipboard permission) =====
  function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(text).catch(() => copyLegacy(text));
    }
    return copyLegacy(text);
  }

  function copyLegacy(text) {
    return new Promise((resolve, reject) => {
      const ta = el('textarea', { readonly: true, 'aria-hidden': 'true', class: 'gd-copy-buffer' });
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

  /** “Text” — Author, then "Thought of the Day · Sunday 25 October 2026" */
  function asText(t) {
    return '“' + t.text + '”' + (t.author ? '\n— ' + t.author : '') + '\n\n' + T.thought_of_day + ' · ' + dayLong(t.date);
  }

  function doCopy(t) {
    copyText(asText(t)).then(() => toast(T.copied), () => toast(T.gd_copy_failed, true));
  }

  function doShare(t) {
    const text = asText(t);
    if (navigator.share) {
      navigator.share({ text: text }).catch((e) => {
        // Cancelled is fine; a share sheet that is not allowed here falls back to copying
        if (e && e.name !== 'AbortError') doCopy(t);
      });
      return;
    }
    doCopy(t);
  }

  // ===== STATE =====
  const state = { q: '', items: [], total: 0, hasMore: false, loading: false, error: '', req: 0 };
  const words = () => state.q.split(/\s+/).filter((w) => w.length > 0).slice(0, 5);

  /** Text with the search words marked, without ever using innerHTML */
  function marked(text, list) {
    const frag = document.createDocumentFragment();
    text = String(text || '');
    if (!list.length || !text) { frag.append(text); return frag; }
    const re = new RegExp('(' + list.map((w) => w.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('|') + ')', 'gi');
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

  // ===== LOADING =====
  async function load(more) {
    const req = ++state.req;
    state.loading = true;
    state.error = '';
    if (!more) render();
    const moreBtn = $('#gdMore');
    if (moreBtn) { moreBtn.disabled = true; moreBtn.classList.add('is-busy'); }

    const p = new URLSearchParams();
    if (state.q) p.set('q', state.q);
    if (more && state.items.length) p.set('before', state.items[state.items.length - 1].date);
    const res = await api('/gedagtes/api/list.php' + (p.toString() ? '?' + p.toString() : ''));
    if (req !== state.req) return; // a newer search took over

    state.loading = false;
    if (moreBtn) { moreBtn.disabled = false; moreBtn.classList.remove('is-busy'); }
    if (!res.ok) {
      if (more) { toast(T.gd_load_failed, true); return; }
      state.error = res.error;
      state.items = [];
      render();
      return;
    }
    const d = res.data;
    state.items = more ? state.items.concat(d.thoughts || []) : (d.thoughts || []);
    if (!more) state.total = d.total || 0;
    state.hasMore = !!d.has_more;
    render();
  }

  // ===== DRAWING =====
  function stamp(date) {
    const p = parts(date);
    return el('span', { class: 'gd-stamp', 'aria-hidden': 'true' }, [
      el('b', { text: String(p[2]) }),
      el('small', { text: daysShort[weekday(date)] || '' })
    ]);
  }

  function itemCard(t) {
    const text = el('blockquote', { class: 'gd-item-text' });
    text.append(marked(t.text, words()));
    const author = t.author ? el('p', { class: 'gd-item-author' }) : null;
    if (author) author.append(marked(t.author, words()));

    return el('article', { class: 'gd-item', 'data-date': t.date }, [
      stamp(t.date),
      el('div', { class: 'gd-item-body' }, [
        el('div', { class: 'gd-item-head' }, [
          el('time', { class: 'gd-item-when', datetime: t.date, text: whenText(t.date) }),
          el('button', {
            class: 'gd-icon-btn', type: 'button', 'aria-label': T.gd_share_label, title: T.share,
            on: { click: () => doShare(t) }
          }, [strokeIcon(ICON_SHARE)])
        ]),
        text,
        author
      ])
    ]);
  }

  /** Long thoughts fold after six lines (measured once they are on the page) */
  function foldLong(list) {
    list.querySelectorAll('.gd-item-text:not([data-folded])').forEach((text) => {
      text.setAttribute('data-folded', '');
      text.classList.add('is-clamped');
      if (text.scrollHeight <= text.clientHeight + 4) {
        text.classList.remove('is-clamped');
        return;
      }
      const label = el('span', { text: T.read_more });
      const btn = el('button', { class: 'gd-fold', type: 'button', 'aria-expanded': 'false' }, [label, strokeIcon(ICON_CHEVRON)]);
      btn.addEventListener('click', () => {
        const open = text.classList.toggle('is-clamped') === false;
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        label.textContent = open ? T.read_less : T.read_more;
      });
      text.after(btn);
    });
  }

  function emptyState(kind) {
    const box = el('div', { class: 'gd-card gd-empty' });
    if (kind === 'error') {
      box.append(
        el('div', { class: 'gd-empty-orb' }, [icon('archive')]),
        el('p', { class: 'gd-empty-text', text: T.gd_load_failed }),
        el('button', { class: 'gd-btn gd-btn--glass', type: 'button', on: { click: () => load(false) } }, [el('span', { text: T.gd_retry })])
      );
    } else if (kind === 'none') {
      box.append(
        el('div', { class: 'gd-empty-orb' }, [strokeIcon(ICON_SEARCH)]),
        el('p', { class: 'gd-empty-text', text: T.gd_none_found }),
        el('p', { class: 'gd-empty-hint', text: T.gd_none_found_hint }),
        el('button', { class: 'gd-btn gd-btn--glass', type: 'button', on: { click: clearSearch } }, [el('span', { text: T.gd_show_all })])
      );
    } else {
      box.append(
        el('div', { class: 'gd-empty-orb' }, [icon('bulb')]),
        el('p', { class: 'gd-empty-text', text: T.gd_empty }),
        el('p', { class: 'gd-empty-hint', text: T.gd_empty_hint })
      );
    }
    return box;
  }

  function render() {
    const list = $('#gdList');
    const count = $('#gdCount');
    const moreWrap = $('#gdMoreWrap');
    list.setAttribute('aria-busy', state.loading ? 'true' : 'false');
    list.classList.toggle('is-loading', state.loading && state.items.length > 0);
    const clear = $('#gdSearchClear');
    if (clear) clear.hidden = !$('#gdSearch').value;

    if (state.loading && !state.items.length) {
      list.replaceChildren(el('div', { class: 'gd-skel', 'aria-hidden': 'true' }, [el('i'), el('i'), el('i')]));
      moreWrap.hidden = true;
      return;
    }
    if (state.loading) return; // keep what is shown until the new page arrives
    if (state.error) {
      count.textContent = '';
      list.replaceChildren(emptyState('error'));
      moreWrap.hidden = true;
      return;
    }
    if (!state.items.length) {
      count.textContent = '';
      list.replaceChildren(emptyState(state.q ? 'none' : 'empty'));
      moreWrap.hidden = true;
      return;
    }

    if (state.q) count.textContent = state.total === 1 ? T.gd_found_one : tr('gd_found_n', { n: state.total });
    else count.textContent = state.total === 1 ? T.gd_count_one : tr('gd_count_n', { n: state.total });

    const groups = [];
    let current = null;
    state.items.forEach((t) => {
      const key = t.date.slice(0, 7);
      if (!current || current.key !== key) {
        current = { key: key, items: [] };
        groups.push(current);
      }
      current.items.push(t);
    });
    list.replaceChildren(...groups.map((g) => el('section', { class: 'gd-month' }, [
      el('h4', { class: 'gd-month-label', text: monthLabel(g.key) }),
      el('div', { class: 'gd-month-items' }, g.items.map(itemCard))
    ])));
    foldLong(list);
    moreWrap.hidden = !state.hasMore;
  }

  function clearSearch() {
    const input = $('#gdSearch');
    if (input) input.value = '';
    state.q = '';
    load(false);
  }

  // ===== TODAY (already on the page) =====
  function bindToday() {
    const card = $('#gdToday');
    if (!card) return;
    const textEl = $('#gdTodayText');
    const authorEl = $('#gdTodayAuthor');
    const t = {
      date: card.getAttribute('data-date') || CFG.today,
      text: (textEl && textEl.textContent || '').trim(),
      author: (authorEl && authorEl.textContent || '').trim()
    };
    const share = $('#gdTodayShare');
    const copy = $('#gdTodayCopy');
    if (share) share.addEventListener('click', () => doShare(t));
    if (copy) copy.addEventListener('click', () => doCopy(t));
  }

  // ===== START =====
  function bind() {
    const search = $('#gdSearch');
    let timer = 0;
    const run = () => {
      clearTimeout(timer);
      const q = search.value.trim();
      if (q === state.q) return;
      state.q = q;
      load(false);
    };
    search.addEventListener('input', () => {
      $('#gdSearchClear').hidden = !search.value;
      clearTimeout(timer);
      timer = setTimeout(run, 350);
    });
    search.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') { e.preventDefault(); run(); search.blur(); }
      else if (e.key === 'Escape' && search.value) { e.preventDefault(); search.value = ''; run(); }
    });
    $('#gdSearchClear').addEventListener('click', () => { search.value = ''; run(); search.focus(); });
    $('#gdMore').addEventListener('click', () => load(true));

    // Back after midnight, or after the time today's thought was waiting for:
    // draw the page again so it shows (South African time, no daylight saving)
    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState !== 'visible') return;
      const d = new Date(Date.now() + 2 * 3600e3);
      const date = d.getUTCFullYear() + '-' + pad(d.getUTCMonth() + 1) + '-' + pad(d.getUTCDate());
      const time = pad(d.getUTCHours()) + ':' + pad(d.getUTCMinutes());
      if (date !== CFG.today || (CFG.pending && time >= CFG.pending)) window.location.reload();
    });
  }

  bindToday();
  bind();
  load(false);
})();
