/**
 * Gospel Media Main UI Logic
 * All styling in gospel.css. Who may edit what is mirrored from
 * lib/permissions.php in canModerate() — the server always decides.
 */
(function () {
  'use strict';

  // ===== HELPERS =====
  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => Array.from(el.querySelectorAll(s));
  const ce = (tag, attrs = {}) => {
    const n = document.createElement(tag);
    for (const k in attrs) {
      if (k === 'class') n.className = attrs[k];
      else if (k === 'text') n.textContent = attrs[k];
      else if (k === 'html') n.innerHTML = attrs[k];
      else n.setAttribute(k, attrs[k]);
    }
    return n;
  };

  const esc = (s) => String(s || '').replace(/[<>&"']/g, m => ({
    '<': '&lt;', '>': '&gt;', '&': '&amp;', '"': '&quot;', "'": '&#39;'
  }[m]));

  async function fetchJSON(url, opt) {
    try {
      const o = Object.assign({ credentials: 'include' }, opt || {});
      // Every POST carries the session CSRF token (exposed by gospel.php)
      if (String(o.method || 'GET').toUpperCase() === 'POST') {
        o.headers = new Headers(o.headers || {});
        o.headers.set('X-CSRF-Token', window.CSRF_TOKEN || '');
      }
      const r = await fetch(url, o);
      const t = await r.text();
      try {
        return JSON.parse(t);
      } catch (e) {
        return { ok: false, error: t || (r.status + ' ' + r.statusText) };
      }
    } catch (e) {
      // Offline / connection dropped
      return { ok: false, error: T('error') };
    }
  }

  // Resize + convert an image File to WebP in the browser so uploads stay small.
  // Keeps originals untouched if the browser can't encode WebP or anything throws.
  function compressImageToWebP(file, maxDim = 1920, quality = 0.85) {
    return new Promise((resolve) => {
      if (!file || !file.type || !file.type.startsWith('image/')) return resolve(file);
      const url = URL.createObjectURL(file);
      const img = new Image();
      img.onload = () => {
        try {
          const { width, height } = img;
          const ratio = Math.min(1, maxDim / Math.max(width, height));
          const w = Math.max(1, Math.round(width * ratio));
          const h = Math.max(1, Math.round(height * ratio));
          const canvas = document.createElement('canvas');
          canvas.width = w;
          canvas.height = h;
          const ctx = canvas.getContext('2d');
          ctx.drawImage(img, 0, 0, w, h);
          URL.revokeObjectURL(url);
          canvas.toBlob((blob) => {
            if (!blob) return resolve(file);
            const base = (file.name || 'image').replace(/\.[^.]+$/, '');
            resolve(new File([blob], base + '.webp', { type: 'image/webp', lastModified: Date.now() }));
          }, 'image/webp', quality);
        } catch (e) {
          URL.revokeObjectURL(url);
          resolve(file);
        }
      };
      img.onerror = () => { URL.revokeObjectURL(url); resolve(file); };
      img.src = url;
    });
  }

  async function compressImages(files) {
    const out = [];
    for (const f of files) out.push(await compressImageToWebP(f));
    return out;
  }

  // Use server-provided translations for all 5 languages
  const T = (key) => (window.JS_T && window.JS_T[key]) ? window.JS_T[key] : key;

  const reduceMotion = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);

  // Inline icons (stroke icons follow currentColor)
  const ICON = {
    plus: '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>',
    send: '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>',
    close: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>',
    dots: '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>',
    chat: '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>',
    pencil: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 013 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>',
    trash: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 11v6M14 11v6"/></svg>',
    chevron: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>',
    arrowUp: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7"/></svg>',
    photo: '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><use href="#gm-i-photo"/></svg>',
    event: '<svg viewBox="0 0 24 24" aria-hidden="true"><use href="#gm-i-event"/></svg>',
    pin: '<svg viewBox="0 0 24 24" aria-hidden="true"><use href="#gm-i-pin"/></svg>'
  };

  // ===== STATE =====
  const ROOM_ID = window.CURRENT_ROOM_ID || 0;
  const ROOM_TYPE = (window.CURRENT_ROOM_TYPE || '').toLowerCase();
  const USER_ID = window.USER_ID || null;
  const USER_AMP_ID = window.USER_AMP_ID || 999;
  let ME = { id: USER_ID, amp_id: USER_AMP_ID };
  const POST_MAP = new Map();
  let activeType = 'post';

  // ===== TOAST =====
  let toastTimer = 0;

  function toast(message, isError) {
    const el = $('#gmToast');
    if (!el || !message) return;
    el.textContent = message;
    el.classList.toggle('is-error', !!isError);
    el.classList.add('is-on');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => el.classList.remove('is-on'), isError ? 3600 : 2200);
  }

  // API errors are codes ("forbidden", "server_error") or, when PHP itself
  // failed, an HTML page: show something human instead
  function errorText(j, fallbackKey) {
    const msg = j && j.error ? String(j.error) : '';
    if (!msg || /^[a-z_]+$/.test(msg) || msg.indexOf('<') !== -1) return T(fallbackKey || 'error');
    return msg.slice(0, 160);
  }

  // ===== LAYERS (composer, modals, lightbox) =====
  // One stack: Escape and the scrim close the top one, focus goes back to
  // where it was, and the page behind doesn't scroll.
  const layers = [];

  function openLayer(back) {
    const prevFocus = document.activeElement;
    const layer = { back: back, close: null };

    function close() {
      if (!back.parentNode) return;
      back.parentNode.removeChild(back);
      const i = layers.indexOf(layer);
      if (i !== -1) layers.splice(i, 1);
      if (!layers.length && !document.querySelector('#roommenu:not([hidden])')) {
        document.body.classList.remove('gm-locked');
      }
      if (prevFocus && prevFocus.focus && document.body.contains(prevFocus)) {
        try { prevFocus.focus({ preventScroll: true }); } catch (e) { prevFocus.focus(); }
      }
    }

    layer.close = close;
    layers.push(layer);
    back.addEventListener('click', (e) => { if (e.target === back) close(); });
    document.body.appendChild(back);
    document.body.classList.add('gm-locked');
    return close;
  }

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && layers.length) {
      e.preventDefault();
      layers[layers.length - 1].close();
    }
  });

  // A small confirm card instead of the browser's confirm() (which shows the
  // site address as its title inside the app)
  function confirmAction(title, text, okLabel) {
    return new Promise((resolve) => {
      const back = ce('div', { class: 'modal-backdrop' });
      const panel = ce('div', { class: 'modal-panel', role: 'alertdialog', 'aria-modal': 'true' });
      const h = ce('h2', { text: title });
      const p = ce('p', { class: 'modal-info', text: text || '' });
      const actions = ce('div', { class: 'modal-actions' });
      const ok = ce('button', { class: 'modal-btn danger', type: 'button', text: okLabel || T('delete') });
      const cancel = ce('button', { class: 'modal-btn', type: 'button', text: T('cancel') });
      actions.append(ok, cancel);
      panel.append(h);
      if (text) panel.append(p);
      panel.append(actions);
      back.appendChild(panel);

      let answered = false;
      const close = openLayer(back);
      const finish = (v) => { if (answered) return; answered = true; close(); resolve(v); };
      ok.addEventListener('click', () => finish(true));
      cancel.addEventListener('click', () => finish(false));
      // Scrim / Escape count as "no"
      new MutationObserver((_, obs) => {
        if (!back.parentNode) { obs.disconnect(); finish(false); }
      }).observe(document.body, { childList: true });
      cancel.focus();
    });
  }

  // ===== DISPLAY NAME =====
  function computeDisplayName(p) {
    const first = (p.user_name || p.name || '').trim();
    const surname = (p.user_surname || p.surname || '').trim();

    if (p.amp_title && String(p.amp_title).trim() !== '') {
      return (p.amp_title + ' ' + first + ' ' + surname).trim();
    }

    const gender = ((p.user_gender || p.gender) || '').toLowerCase();
    const male = p.amp_male_name || 'Broer';
    const female = p.amp_female_name || 'Suster';
    const prefix = gender === 'vrou' ? female : male;

    return (prefix + ' ' + first + ' ' + surname).trim();
  }

  function avatarEl(photo, initials, cls) {
    let el;
    if (photo) {
      el = ce('img', { class: cls, alt: '', loading: 'lazy', decoding: 'async' });
      el.src = photo;
      // A broken photo falls back to initials instead of a broken-image icon
      el.addEventListener('error', () => {
        const fb = ce('div', { class: cls + ' gm-avatar-initials', 'aria-hidden': 'true', text: initials || '?' });
        if (el.parentNode) el.parentNode.replaceChild(fb, el);
      });
    } else {
      el = ce('div', { class: cls + ' gm-avatar-initials', 'aria-hidden': 'true', text: initials || '?' });
    }
    return el;
  }

  // ===== EDIT RIGHTS =====
  function canModerate() {
    const a = parseInt(ME.amp_id || 0, 10);
    if (!a) return false;
    const rt = ROOM_TYPE;
    if (rt === 'opsienerskap') return a >= 2 && a <= 5;
    if (rt === 'gemeente') return a >= 5 && a <= 9;
    if (rt === 'jeug' || rt === 'sondagskool') return a >= 1 && a <= 9;
    if (rt === 'gemeenskap') return a >= 1 && a <= 6;
    return false;
  }

  function canEditPost(p) {
    if (!ME.id) return false;
    if (String(ME.id) === String(p.user_id)) return true;
    return canModerate();
  }

  function canEditComment(c) {
    if (!ME.id) return false;
    if (String(ME.id) === String(c.user_id)) return true;
    return canModerate();
  }

  // ===== DATE FORMATTING =====
  // Show weekday names in the user's chosen language (falls back to English
  // if the browser doesn't ship that locale)
  const LOCALE_MAP = { af: 'af-ZA', en: 'en-GB', zu: 'zu-ZA', xh: 'xh-ZA', pt: 'pt-PT', st: 'st-ZA' };
  const DATE_LOCALES = [LOCALE_MAP[window.PAGE_LANG || 'af'] || 'af-ZA', 'en-GB'];

  // MySQL "YYYY-MM-DD HH:MM:SS" is not parseable on Safari/iOS - normalise to ISO
  function parseDT(s) {
    if (!s) return null;
    const d = new Date(typeof s === 'string' ? s.replace(' ', 'T') : s);
    return isNaN(d.getTime()) ? null : d;
  }

  const hhmm = d => d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', hour12: false });

  const fmtDT = s => {
    try {
      const d = parseDT(s);
      if (!d) return s || '';
      const day = d.toLocaleDateString(DATE_LOCALES, { weekday: 'short' }).toUpperCase();
      const date = d.toLocaleDateString('en-GB', { year: '2-digit', month: '2-digit', day: '2-digit' });
      return `${day} ${date}, ${hhmm(d)}`;
    } catch (e) {
      return s || '';
    }
  };

  function shortDate(d) {
    const opts = { day: 'numeric', month: 'short' };
    if (d.getFullYear() !== new Date().getFullYear()) opts.year = 'numeric';
    return d.toLocaleDateString(DATE_LOCALES, opts);
  }

  // "5 min. gelede" / "2 hours ago" for the last week, then the date
  function timeAgo(s) {
    const d = parseDT(s);
    if (!d) return s || '';
    try {
      const secs = (Date.now() - d.getTime()) / 1000;
      if (!window.Intl || !Intl.RelativeTimeFormat || secs < -60 || secs > 7 * 86400) return shortDate(d);
      const rtf = new Intl.RelativeTimeFormat(DATE_LOCALES, { numeric: 'auto', style: 'short' });
      if (secs < 60) return rtf.format(0, 'second');
      if (secs < 3600) return rtf.format(-Math.floor(secs / 60), 'minute');
      if (secs < 86400) return rtf.format(-Math.floor(secs / 3600), 'hour');
      return rtf.format(-Math.floor(secs / 86400), 'day');
    } catch (e) {
      return fmtDT(s);
    }
  }

  function timeEl(s, cls) {
    const el = ce('time', { class: cls || '', text: timeAgo(s) });
    const d = parseDT(s);
    if (d) {
      el.setAttribute('datetime', d.toISOString());
      el.title = fmtDT(s);
    }
    return el;
  }

  // ===== TEXT WITH LINKS =====
  // Only http(s) links, built as real nodes: the text itself is never parsed as HTML
  const URL_RE = /\b(?:https?:\/\/|www\.)[^\s<>"']+/gi;

  function fillText(el, text) {
    text = String(text || '');
    let last = 0;
    let m;
    URL_RE.lastIndex = 0;
    while ((m = URL_RE.exec(text)) !== null) {
      let url = m[0];
      // Leave closing punctuation outside the link
      const trail = url.match(/[.,!?;:)\]]+$/);
      if (trail) url = url.slice(0, -trail[0].length);
      if (m.index > last) el.appendChild(document.createTextNode(text.slice(last, m.index)));
      const a = ce('a', { href: /^www\./i.test(url) ? 'https://' + url : url, target: '_blank', rel: 'noopener noreferrer nofollow ugc', text: url });
      el.appendChild(a);
      last = m.index + url.length;
      URL_RE.lastIndex = last;
    }
    if (last < text.length) el.appendChild(document.createTextNode(text.slice(last)));
  }

  // ===== REVEAL CARDS AS THEY SCROLL IN =====
  const revealer = ('IntersectionObserver' in window && !reduceMotion)
    ? new IntersectionObserver((entries) => {
        entries.forEach(en => {
          if (en.isIntersecting) {
            en.target.classList.add('is-in');
            revealer.unobserve(en.target);
          }
        });
      }, { rootMargin: '0px 0px -5% 0px', threshold: 0.04 })
    : null;

  function reveal(el, i) {
    el.classList.add('gm-reveal');
    if (i) el.style.setProperty('--i', String(Math.min(i, 4)));
    if (revealer) revealer.observe(el);
    else el.classList.add('is-in');
  }

  // ===== COMPOSER OVERLAY =====
  function autoGrow(ta, max) {
    ta.style.height = 'auto';
    ta.style.height = Math.min(ta.scrollHeight + 2, max) + 'px';
  }

  function openComposerOverlay(mode) {
    if (!window.CAN_POST) return;

    const existing = $('.composer-overlay');
    if (existing) existing.remove();

    activeType = 'post';

    const backdrop = ce('div', { class: 'composer-overlay' });
    const panel = ce('div', { class: 'composer-overlay-panel', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'gmComposerTitle' });

    // Header
    const header = ce('div', { class: 'composer-overlay-header' });
    const title = ce('h2', { class: 'composer-overlay-title', id: 'gmComposerTitle', text: T('new_post') });
    const closeBtn = ce('button', { class: 'composer-overlay-close', type: 'button', 'aria-label': T('close'), html: ICON.close });
    header.append(title, closeBtn);

    // Tabs
    const tabs = ce('div', { class: 'composer-tabs', role: 'tablist' });
    const postTab = ce('button', { class: 'composer-tab active', type: 'button', role: 'tab', 'aria-selected': 'true' });
    postTab.innerHTML = ICON.chat.replace('class="icon"', '') + '<span>' + esc(T('post')) + '</span>';
    const evtTab = ce('button', { class: 'composer-tab', type: 'button', role: 'tab', 'aria-selected': 'false' });
    evtTab.innerHTML = ICON.event + '<span>' + esc(T('datetime')) + '</span>';
    tabs.append(postTab, evtTab);

    if (ROOM_TYPE === 'gemeenskap') evtTab.hidden = true;

    // Event fields
    const evtFields = ce('div', { class: 'composer-event-fields' });
    evtFields.hidden = true;
    const atField = ce('label', { class: 'composer-field' });
    const evtAt = ce('input', { type: 'datetime-local', class: 'composer-input' });
    atField.append(ce('span', { text: T('datetime') }), evtAt);
    const placeField = ce('label', { class: 'composer-field' });
    const evtPlace = ce('input', { type: 'text', class: 'composer-input', placeholder: T('place'), maxlength: '255' });
    placeField.append(ce('span', { text: T('place') }), evtPlace);
    evtFields.append(atField, placeField);

    function setType(type) {
      activeType = type;
      const isEvent = type === 'event';
      postTab.classList.toggle('active', !isEvent);
      evtTab.classList.toggle('active', isEvent);
      postTab.setAttribute('aria-selected', String(!isEvent));
      evtTab.setAttribute('aria-selected', String(isEvent));
      evtFields.hidden = !isEvent;
    }

    postTab.addEventListener('click', () => setType('post'));
    evtTab.addEventListener('click', () => { setType('event'); evtAt.focus(); });

    // Textarea (restores any draft saved for this room, e.g. after an accidental close)
    const DRAFT_KEY = 'gm_draft_' + ROOM_ID;
    const ta = ce('textarea', { class: 'composer-textarea', placeholder: T('what_share'), 'aria-label': T('type_message') });
    try { ta.value = localStorage.getItem(DRAFT_KEY) || ''; } catch (e) { /* storage unavailable */ }
    ta.addEventListener('input', () => {
      autoGrow(ta, Math.round(window.innerHeight * 0.46));
      try {
        if (ta.value.trim()) localStorage.setItem(DRAFT_KEY, ta.value);
        else localStorage.removeItem(DRAFT_KEY);
      } catch (e) { /* storage unavailable */ }
    });

    // File input + preview (each preview gets a remove button, like the edit modal)
    const fileInput = ce('input', { type: 'file', accept: 'image/*', multiple: 'multiple', hidden: 'hidden' });
    const preview = ce('div', { class: 'composer-preview' });

    function renderPreviews() {
      $$('img', preview).forEach(img => URL.revokeObjectURL(img.src));
      preview.innerHTML = '';
      Array.from(fileInput.files || []).forEach((f, idx) => {
        const box = ce('div', { class: 'modal-img-box' });

        const img = ce('img', { class: 'composer-preview-img', alt: '' });
        img.src = URL.createObjectURL(f);

        const x = ce('button', { type: 'button', class: 'modal-img-del', text: '×', title: T('remove'), 'aria-label': T('remove') });
        x.addEventListener('click', () => {
          const dt = new DataTransfer();
          Array.from(fileInput.files).forEach((f2, i2) => {
            if (i2 !== idx) dt.items.add(f2);
          });
          fileInput.files = dt.files;
          renderPreviews();
        });

        box.append(img, x);
        preview.appendChild(box);
      });
    }

    fileInput.addEventListener('change', renderPreviews);

    // Actions
    const actions = ce('div', { class: 'composer-actions' });

    const attachBtn = ce('button', { type: 'button', class: 'composer-attach' });
    attachBtn.innerHTML = ICON.photo + '<span>' + esc(T('add_photo')) + '</span>';
    attachBtn.addEventListener('click', () => fileInput.click());

    const submitBtn = ce('button', { type: 'button', class: 'composer-submit' });
    const submitIdle = ICON.send + '<span>' + esc(T('post')) + '</span>';
    submitBtn.innerHTML = submitIdle;

    async function submit() {
      if (submitBtn.disabled) return;
      const text = (ta.value || '').trim();
      const hasImages = fileInput.files && fileInput.files.length > 0;
      // A post needs text or at least one photo
      if (!text && !hasImages) {
        toast(T('type_message'), true);
        ta.focus();
        return;
      }

      const fd = new FormData();
      fd.append('room_id', ROOM_ID);
      fd.append('text', text);
      fd.append('type', activeType === 'event' ? 'event' : 'post');

      if (activeType === 'event') {
        if (evtAt.value) fd.append('event_at', evtAt.value);
        if (evtPlace.value) fd.append('event_place', evtPlace.value);
      }

      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span class="gm-spinner" aria-hidden="true"></span><span>' + esc(T('posting')) + '</span>';

      if (hasImages) {
        const compressed = await compressImages(Array.from(fileInput.files));
        compressed.forEach(f => fd.append('images[]', f));
      }

      const j = await fetchJSON('/gospel_media/api/posts/create.php', { method: 'POST', body: fd });

      submitBtn.disabled = false;
      submitBtn.innerHTML = submitIdle;

      if (!(j && (j.ok || j.success))) {
        toast(errorText(j, 'error_posting'), true);
        return;
      }

      try { localStorage.removeItem(DRAFT_KEY); } catch (e) { /* storage unavailable */ }
      closeOverlay();
      window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
      await loadFeed(ROOM_ID, true);
    }

    submitBtn.addEventListener('click', submit);
    // Ctrl/Cmd + Enter posts from the keyboard
    ta.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); submit(); }
    });

    actions.append(attachBtn, submitBtn);

    panel.append(header, tabs, ta, evtFields, fileInput, preview, actions);
    backdrop.appendChild(panel);
    const closeOverlay = openLayer(backdrop);
    closeBtn.addEventListener('click', closeOverlay);

    if (mode === 'event' && !evtTab.hidden) setType('event');
    autoGrow(ta, Math.round(window.innerHeight * 0.46));

    if (mode === 'photo') {
      // Straight to the picker (still inside the tap, so the browser allows it)
      fileInput.click();
    } else {
      ta.focus();
      // Put the cursor at the end of a restored draft
      ta.setSelectionRange(ta.value.length, ta.value.length);
    }
  }

  // ===== REACTIONS =====
  function renderReactions(card, p) {
    const bar = ce('div', { class: 'post-actions' });
    const myReacts = Array.isArray(p.my_reactions) ? p.my_reactions : [];

    const bH = ce('button', { type: 'button', class: 'btn-react heart-btn', 'aria-pressed': String(myReacts.includes('heart')) });
    if (myReacts.includes('heart')) bH.classList.add('reacted');
    const heartIcon = ce('img', { class: 'icon', src: '/assets/icons/heart.png', alt: '♥' });
    const heartCount = ce('span', { class: 'badge badge-clickable count-heart', text: (p.heart_count || 0), title: T('reactions') });
    bH.append(heartIcon, heartCount);

    const bP = ce('button', { type: 'button', class: 'btn-react pray-btn', 'aria-pressed': String(myReacts.includes('pray')) });
    if (myReacts.includes('pray')) bP.classList.add('reacted');
    const prayIcon = ce('img', { class: 'icon', src: '/assets/icons/amen.png', alt: 'Amen' });
    const prayCount = ce('span', { class: 'badge badge-clickable count-pray', text: (p.pray_count || 0), title: T('reactions') });
    bP.append(prayIcon, prayCount);

    bH.addEventListener('click', () => toggleReact(p.id, 'heart', bH, bP));
    bP.addEventListener('click', () => toggleReact(p.id, 'pray', bH, bP));

    // WhatsApp/FB style: tapping the count shows who reacted
    heartCount.addEventListener('click', (e) => { e.stopPropagation(); openReactionsModal(p); });
    prayCount.addEventListener('click', (e) => { e.stopPropagation(); openReactionsModal(p); });

    const bC = ce('button', { type: 'button', class: 'btn-react comments-btn', 'aria-expanded': 'false', 'aria-label': T('comments') });
    bC.innerHTML = ICON.chat + `<span class="badge count-comments">${parseInt(p.comment_count, 10) || 0}</span>`;
    bC.addEventListener('click', () => toggleComments(p.id, card, bC));

    bar.append(bH, bP, bC);
    card.appendChild(bar);
  }

  const reactInFlight = new Set();

  async function toggleReact(post_id, type, bH, bP) {
    const key = post_id + ':' + type;
    if (reactInFlight.has(key)) return;
    reactInFlight.add(key);

    const btn = type === 'heart' ? bH : bP;
    const countEl = btn.querySelector('.badge');

    // Optimistic update so the button feels instant; rolled back on failure
    const wasReacted = btn.classList.contains('reacted');
    const prevCount = parseInt(countEl.textContent || '0', 10);
    btn.classList.toggle('reacted');
    btn.setAttribute('aria-pressed', String(!wasReacted));
    countEl.textContent = Math.max(0, prevCount + (wasReacted ? -1 : 1));

    // A small haptic tick where the device supports it
    if (!wasReacted && navigator.vibrate) { try { navigator.vibrate(8); } catch (e) { /* ignore */ } }

    const fd = new FormData();
    fd.append('post_id', post_id);
    fd.append('type', type);

    const j = await fetchJSON('/gospel_media/api/posts/react.php', { method: 'POST', body: fd });
    reactInFlight.delete(key);

    if (!(j && j.ok)) {
      btn.classList.toggle('reacted');
      btn.setAttribute('aria-pressed', String(wasReacted));
      countEl.textContent = prevCount;
      toast(T('error'), true);
      return;
    }

    // Sync with the authoritative server counts
    if (bH && typeof j.heart_count !== 'undefined') {
      bH.querySelector('.count-heart').textContent = j.heart_count;
    }
    if (bP && typeof j.pray_count !== 'undefined') {
      bP.querySelector('.count-pray').textContent = j.pray_count;
    }

    // Keep the cached post in step for re-renders and the reactions modal
    const p = POST_MAP.get(String(post_id));
    if (p) {
      if (typeof j.heart_count !== 'undefined') p.heart_count = j.heart_count;
      if (typeof j.pray_count !== 'undefined') p.pray_count = j.pray_count;
      const mine = Array.isArray(p.my_reactions) ? p.my_reactions : (p.my_reactions = []);
      const idx = mine.indexOf(type);
      if (wasReacted && idx !== -1) mine.splice(idx, 1);
      if (!wasReacted && idx === -1) mine.push(type);
    }
  }

  // ===== WHO REACTED (WhatsApp/FB style) =====
  function openReactionsModal(p) {
    openReactionsListModal('/gospel_media/api/posts/reactions.php?post_id=' + encodeURIComponent(p.id));
  }

  function openCommentReactionsModal(commentId) {
    openReactionsListModal('/gospel_media/api/comments/reactions.php?comment_id=' + encodeURIComponent(commentId));
  }

  function openReactionsListModal(url) {
    const back = ce('div', { class: 'modal-backdrop' });
    const panel = ce('div', { class: 'modal-panel reactions-panel', role: 'dialog', 'aria-modal': 'true' });
    const title = ce('h2', { text: T('reactions') });

    const tabs = ce('div', { class: 'reactions-tabs' });
    const list = ce('div', { class: 'reactions-list' });
    list.innerHTML = '<p class="muted">' + esc(T('loading')) + '</p>';

    const actions = ce('div', { class: 'modal-actions' });
    const closeBtn = ce('button', { class: 'modal-btn', type: 'button', text: T('back') });
    actions.appendChild(closeBtn);

    panel.append(title, tabs, list, actions);
    back.appendChild(panel);
    const close = openLayer(back);
    closeBtn.addEventListener('click', close);
    closeBtn.focus();

    fetchJSON(url).then(j => {
      const all = (j && j.ok && Array.isArray(j.reactions)) ? j.reactions : [];

      if (!all.length) {
        list.innerHTML = '<p class="muted">' + esc(T('no_reactions')) + '</p>';
        return;
      }

      const hearts = all.filter(r => r.type === 'heart');
      const prays = all.filter(r => r.type === 'pray');

      function renderList(rows) {
        list.innerHTML = '';
        if (!rows.length) {
          list.innerHTML = '<p class="muted">' + esc(T('no_reactions')) + '</p>';
          return;
        }
        rows.forEach(r => {
          const item = ce('div', { class: 'reaction-item' });
          const avatar = avatarEl(r.photo, r.initials, 'comment-avatar');

          const name = ce('a', {
            class: 'reaction-name',
            href: '/profile/index.php?u=' + encodeURIComponent(r.user_id),
            text: computeDisplayName(r)
          });

          const emoji = ce('span', { class: 'reaction-emoji', text: r.type === 'heart' ? '❤️' : '🙏' });

          item.append(avatar, name, emoji);
          list.appendChild(item);
        });
      }

      function makeTab(label, rows) {
        const b = ce('button', { type: 'button', class: 'reactions-tab', text: label });
        b.addEventListener('click', () => {
          $$('.reactions-tab', tabs).forEach(t => t.classList.remove('active'));
          b.classList.add('active');
          renderList(rows);
        });
        return b;
      }

      const tabAll = makeTab(T('all') + ' ' + all.length, all);
      tabAll.classList.add('active');
      tabs.appendChild(tabAll);
      if (hearts.length) tabs.appendChild(makeTab('❤️ ' + hearts.length, hearts));
      if (prays.length) tabs.appendChild(makeTab('🙏 ' + prays.length, prays));

      renderList(all);
    });
  }

  // ===== COMMENT HEARTS =====
  const commentReactInFlight = new Set();

  async function toggleCommentHeart(commentId, btn) {
    if (commentReactInFlight.has(commentId)) return;
    commentReactInFlight.add(commentId);

    const countEl = btn.querySelector('.comment-like-count');
    const wasReacted = btn.classList.contains('reacted');
    const prevCount = parseInt(countEl.textContent || '0', 10);

    // Optimistic update, rolled back on failure
    btn.classList.toggle('reacted');
    btn.setAttribute('aria-pressed', String(!wasReacted));
    const nextCount = Math.max(0, prevCount + (wasReacted ? -1 : 1));
    countEl.textContent = nextCount > 0 ? String(nextCount) : '';

    const fd = new FormData();
    fd.append('comment_id', commentId);
    fd.append('type', 'heart');

    const j = await fetchJSON('/gospel_media/api/comments/react.php', { method: 'POST', body: fd });
    commentReactInFlight.delete(commentId);

    if (!(j && j.ok)) {
      btn.classList.toggle('reacted');
      btn.setAttribute('aria-pressed', String(wasReacted));
      countEl.textContent = prevCount > 0 ? String(prevCount) : '';
      toast(T('error'), true);
      return;
    }

    if (typeof j.heart_count !== 'undefined') {
      countEl.textContent = j.heart_count > 0 ? String(j.heart_count) : '';
    }
  }

  // ===== COMMENTS =====
  function setCommentCount(card, delta) {
    const badge = card && card.querySelector('.count-comments');
    if (!badge) return;
    badge.textContent = Math.max(0, (parseInt(badge.textContent || '0', 10) || 0) + delta);
  }

  async function deleteComment(commentId, postId, listEl) {
    const yes = await confirmAction(T('delete_comment'), T('cannot_undo'), T('delete'));
    if (!yes) return;

    const fd = new FormData();
    fd.append('comment_id', commentId);

    const j = await fetchJSON('/gospel_media/api/comments/delete.php', { method: 'POST', body: fd });

    if (!(j && (j.ok || j.success))) {
      toast(errorText(j), true);
      return;
    }

    setCommentCount(document.querySelector(`[data-post-id="${postId}"]`), -1);
    await loadComments(postId, listEl);
  }

  function editComment(c, item, postId, listEl) {
    const textEl = item.querySelector('.comment-text');
    if (!textEl || item.querySelector('.comment-edit-input')) return;

    const ta = ce('textarea', { class: 'comment-input comment-edit-input', 'aria-label': T('edit_comment') });
    ta.value = c.text || '';
    ta.rows = 2;

    const btnRow = ce('div', { class: 'comment-edit-actions' });
    const saveBtn = ce('button', { class: 'gm-btn gm-btn--primary', type: 'button', text: T('save') });
    const cancelBtn = ce('button', { class: 'gm-btn gm-btn--glass', type: 'button', text: T('cancel') });

    const restore = () => {
      ta.replaceWith(textEl);
      btnRow.remove();
    };

    cancelBtn.addEventListener('click', restore);
    ta.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') { e.stopPropagation(); restore(); }
    });

    saveBtn.addEventListener('click', async () => {
      const newText = ta.value.trim();
      if (!newText || newText === c.text) {
        restore();
        return;
      }
      saveBtn.disabled = true;
      saveBtn.textContent = T('saving');

      const fd = new FormData();
      fd.append('comment_id', c.id);
      fd.append('text', newText);

      const j = await fetchJSON('/gospel_media/api/comments/update.php', { method: 'POST', body: fd });
      if (!(j && (j.ok || j.success))) {
        toast(errorText(j), true);
        saveBtn.disabled = false;
        saveBtn.textContent = T('save');
        return;
      }
      await loadComments(postId, listEl);
    });

    btnRow.append(saveBtn, cancelBtn);
    textEl.replaceWith(ta);
    ta.after(btnRow);
    autoGrow(ta, 200);
    ta.focus();
  }

  async function toggleComments(postId, card, toggleBtn) {
    let commentsSection = $('.comments-section', card);

    if (commentsSection) {
      const nowHidden = !commentsSection.hidden;
      commentsSection.hidden = nowHidden;
      if (toggleBtn) toggleBtn.setAttribute('aria-expanded', String(!nowHidden));
      return;
    }

    commentsSection = ce('div', { class: 'comments-section' });
    commentsSection.innerHTML = `
      <h3 class="gm-sr-only">${esc(T('comments'))}</h3>
      <div class="comments-list"></div>
      <div class="comment-composer">
        <textarea class="comment-input" placeholder="${esc(T('write_comment'))}" aria-label="${esc(T('write_comment'))}" rows="1"></textarea>
        <button class="comment-submit" type="button" aria-label="${esc(T('post'))}" title="${esc(T('post'))}">${ICON.send}</button>
      </div>
    `;

    card.appendChild(commentsSection);
    if (toggleBtn) toggleBtn.setAttribute('aria-expanded', 'true');

    const list = $('.comments-list', commentsSection);
    const input = $('.comment-input', commentsSection);
    const submit = $('.comment-submit', commentsSection);

    input.addEventListener('input', () => autoGrow(input, 140));
    // WhatsApp style: Enter sends, Shift+Enter makes a new line
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        if (!submit.disabled) submit.click();
      }
    });

    submit.addEventListener('click', async () => {
      const text = input.value.trim();
      if (!text) { input.focus(); return; }

      submit.disabled = true;
      submit.classList.add('is-busy');

      const fd = new FormData();
      fd.append('post_id', postId);
      fd.append('text', text);

      const j = await fetchJSON('/gospel_media/api/comments/create.php', { method: 'POST', body: fd });

      submit.disabled = false;
      submit.classList.remove('is-busy');

      if (!(j && (j.ok || j.success))) {
        toast(errorText(j), true);
        return;
      }

      input.value = '';
      input.style.height = '';
      if (j.comment) setCommentCount(card, 1);

      await loadComments(postId, list);
    });

    await loadComments(postId, list);
  }

  async function loadComments(postId, listEl) {
    if (!listEl) return;

    listEl.innerHTML = '<p class="muted">' + esc(T('loading')) + '</p>';

    const j = await fetchJSON('/gospel_media/api/comments/list.php?post_id=' + encodeURIComponent(postId));
    const comments = Array.isArray(j) ? j : [];

    listEl.innerHTML = '';

    if (!Array.isArray(j)) {
      listEl.innerHTML = '<p class="muted">' + esc(T('error')) + '</p>';
      return;
    }

    if (!comments.length) {
      listEl.innerHTML = '<p class="muted">' + esc(T('no_comments')) + '</p>';
      return;
    }

    comments.forEach(c => {
      const item = ce('div', { class: 'comment-item' });
      const avatar = avatarEl(c.photo, c.initials, 'comment-avatar');

      const body = ce('div', { class: 'comment-body' });
      const bubble = ce('div', { class: 'comment-bubble' });
      const header = ce('div', { class: 'comment-header' });
      const author = ce('a', {
        class: 'comment-author',
        href: '/profile/index.php?u=' + encodeURIComponent(c.user_id),
        text: computeDisplayName(c)
      });
      header.appendChild(author);

      if (canEditComment(c)) {
        const actions = ce('div', { class: 'comment-actions' });

        const editBtn = ce('button', { class: 'comment-action-btn', type: 'button', title: T('edit'), 'aria-label': T('edit'), html: ICON.pencil });
        editBtn.addEventListener('click', () => editComment(c, item, postId, listEl));

        const delBtn = ce('button', { class: 'comment-action-btn is-danger', type: 'button', title: T('delete'), 'aria-label': T('delete'), html: ICON.trash });
        delBtn.addEventListener('click', () => deleteComment(c.id, postId, listEl));

        actions.append(editBtn, delBtn);
        header.appendChild(actions);
      }

      const text = ce('div', { class: 'comment-text' });
      fillText(text, c.text || '');
      bubble.append(header, text);

      // Heart on the comment; tapping the count shows who hearted it
      const foot = ce('div', { class: 'comment-foot' });
      if (c.created_at) foot.appendChild(timeEl(c.created_at, 'comment-time'));

      const likeBtn = ce('button', { type: 'button', class: 'comment-like-btn', title: T('reactions'), 'aria-pressed': String(!!c.my_heart) });
      if (c.my_heart) likeBtn.classList.add('reacted');
      const likeIcon = ce('span', { class: 'comment-like-icon', text: '♥', 'aria-hidden': 'true' });
      const likeCount = ce('span', { class: 'comment-like-count', text: (c.heart_count > 0 ? String(c.heart_count) : '') });
      likeBtn.append(likeIcon, likeCount);

      likeBtn.addEventListener('click', () => toggleCommentHeart(c.id, likeBtn));
      likeCount.addEventListener('click', (e) => {
        const n = parseInt(likeCount.textContent || '0', 10);
        if (n > 0) {
          e.stopPropagation();
          openCommentReactionsModal(c.id);
        }
      });

      foot.appendChild(likeBtn);
      body.append(bubble, foot);
      item.append(avatar, body);
      listEl.appendChild(item);
    });
  }

  // ===== EVENT HELPERS =====
  // "in 3 days" / "oor 3 dae" in the user's language
  function relTimeTo(dateStr) {
    try {
      const target = new Date(String(dateStr).replace(' ', 'T')).getTime();
      if (isNaN(target)) return '';
      const diffMs = target - Date.now();
      const rtf = new Intl.RelativeTimeFormat(DATE_LOCALES, { numeric: 'auto' });
      const absHours = Math.abs(diffMs) / 36e5;
      if (absHours < 1) return rtf.format(Math.round(diffMs / 6e4), 'minute');
      if (absHours < 24) return rtf.format(Math.round(diffMs / 36e5), 'hour');
      return rtf.format(Math.round(diffMs / 864e5), 'day');
    } catch (e) {
      return '';
    }
  }

  // Add the event to the phone's own calendar: the app's "new event" screen
  // when it offers one, else the same .ics file the calendar gives
  // (/calendar/api/ics.php — one format and one event UID everywhere).
  function downloadEventICS(p) {
    const m = String(p.event_at || '').match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
    if (!m) return;
    const native = window.NativeApp && typeof window.NativeApp.addToCalendar === 'function';
    if (native) {
      // Stored times are South African time (UTC+2, no daylight saving)
      const begin = Date.UTC(+m[1], +m[2] - 1, +m[3], +m[4], +m[5]) - 2 * 3600e3;
      const lines = String(p.text || '').split('\n');
      try {
        const opened = window.NativeApp.addToCalendar(
          (lines[0] || 'Gospel Media').slice(0, 140), String(begin), String(begin + 3600e3), false,
          p.event_place || '', lines.slice(1).join('\n').trim());
        if (opened === true || opened === 'true') return;
      } catch (e) { /* older app: fall back to the file */ }
    }
    window.location.href = '/calendar/api/ics.php?type=event&id=' + encodeURIComponent(p.id);
  }

  // Date stamp + when/where + countdown, like "Up next" on the welcome page
  function renderEvent(card, p) {
    const d = parseDT(p.event_at);
    if (!d) return;
    const isPast = d.getTime() < Date.now();

    const block = ce('div', { class: 'event-block' });

    const stamp = ce('span', { class: 'event-stamp' + (isPast ? ' past' : ''), 'aria-hidden': 'true' });
    stamp.append(
      ce('b', { text: String(d.getDate()) }),
      ce('i', { text: d.toLocaleDateString(DATE_LOCALES, { month: 'short' }).replace('.', '') })
    );

    const info = ce('div', { class: 'event-info' });
    let when = '';
    try {
      when = d.toLocaleDateString(DATE_LOCALES, { weekday: 'long', day: 'numeric', month: 'long' }) + ' · ' + hhmm(d);
    } catch (e) {
      when = fmtDT(p.event_at);
    }
    const whenEl = ce('h4', { class: 'subheading event-when', text: when.charAt(0).toUpperCase() + when.slice(1) });
    info.appendChild(whenEl);

    if (p.event_place) {
      const place = ce('p', { class: 'event-place' });
      place.innerHTML = ICON.pin;
      place.appendChild(document.createTextNode(p.event_place));
      info.appendChild(place);
    }

    const metaRow = ce('div', { class: 'event-meta-row' });

    // Countdown chip: "over 3 dae" / "in 3 days", in the user's language
    const rel = relTimeTo(p.event_at);
    if (rel) metaRow.appendChild(ce('span', { class: 'event-countdown' + (isPast ? ' past' : ''), text: rel }));

    // Add-to-calendar button for upcoming events
    if (!isPast) {
      const calBtn = ce('button', { type: 'button', class: 'event-cal-btn' });
      calBtn.innerHTML = ICON.event + '<span>' + esc(T('add_to_calendar')) + '</span>';
      calBtn.addEventListener('click', () => downloadEventICS(p));
      metaRow.appendChild(calBtn);
    }

    if (metaRow.children.length) info.appendChild(metaRow);

    block.append(stamp, info);
    card.appendChild(block);
  }

  // ===== POST CARDS =====
  function renderCard(p) {
    // Key by string so lookups from data attributes always match,
    // regardless of whether the API returned numeric or string ids
    POST_MAP.set(String(p.id), p);

    const card = ce('article', { class: 'post-card', id: 'post-' + p.id, 'data-post-id': String(p.id) });
    const header = ce('div', { class: 'post-header' });
    const profile = '/profile/index.php?u=' + encodeURIComponent(p.user_id);

    const avatarLink = ce('a', { class: 'post-avatar-link', href: profile, tabindex: '-1', 'aria-hidden': 'true' });
    avatarLink.appendChild(avatarEl(p.user_photo, p.initials, 'avatar'));
    header.appendChild(avatarLink);

    const info = ce('div', { class: 'post-who' });
    const _nmDiv = ce('div', { class: 'name' });
    const _a = ce('a', {
      class: 'name-link',
      href: profile,
      text: computeDisplayName(p)
    });
    _nmDiv.appendChild(_a);
    info.appendChild(_nmDiv);

    const meta = ce('div', { class: 'post-meta' });
    if (p.created_at) meta.appendChild(timeEl(p.created_at));
    info.appendChild(meta);
    header.appendChild(info);

    if (canEditPost(p)) {
      const wrap = ce('div', { class: 'post-menu' });
      const btn = ce('button', { type: 'button', class: 'post-menu-btn', 'aria-label': T('post_actions'), title: T('post_actions'), 'aria-haspopup': 'dialog', html: ICON.dots });
      wrap.appendChild(btn);
      header.appendChild(wrap);
    }

    card.appendChild(header);

    if ((p.type || '') === 'event' && p.event_at) renderEvent(card, p);

    const text = String(p.text || '');
    if (text) {
      const textEl = ce('div', { class: 'post-text' });
      fillText(textEl, text);
      card.appendChild(textEl);

      // Long posts fold; the measured height decides once the card is on the page
      const lines = text.split('\n').length;
      if (text.length > 420 || lines > 7) {
        textEl.classList.add('is-clamped');
        const more = ce('button', { type: 'button', class: 'post-more', 'aria-expanded': 'false' });
        more.innerHTML = '<span>' + esc(T('read_more')) + '</span>' + ICON.chevron;
        more.addEventListener('click', () => {
          const open = textEl.classList.toggle('is-clamped') === false;
          more.setAttribute('aria-expanded', String(open));
          more.querySelector('span').textContent = open ? T('read_less') : T('read_more');
          if (!open) {
            const r = card.getBoundingClientRect();
            if (r.top < 0) card.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
          }
        });
        card.appendChild(more);
        requestAnimationFrame(() => {
          if (textEl.isConnected && textEl.scrollHeight <= textEl.clientHeight + 4) {
            textEl.classList.remove('is-clamped');
            more.remove();
          }
        });
      }
    }

    // ===== FACEBOOK-STYLE PHOTO GRID =====
    if (Array.isArray(p.attachments) && p.attachments.length) {
      const atts = p.attachments;
      const count = atts.length;
      const grid = ce('div', { class: 'photo-grid photo-grid-' + Math.min(count, 5) });

      atts.slice(0, 4).forEach((a, idx) => {
        const cell = ce('button', { type: 'button', class: 'photo-grid-cell', 'aria-label': (idx + 1) + ' / ' + count });
        const img = ce('img', { class: 'photo-grid-img', alt: '' });
        img.loading = 'lazy';
        img.decoding = 'async';
        img.src = a.path_thumb || a.path_original || a.url || '';
        if (a.width && a.height && count === 1) {
          img.width = a.width;
          img.height = a.height;
        }

        // For 5+ images, show overlay on the last visible cell
        if (count > 4 && idx === 3) {
          cell.appendChild(ce('div', { class: 'photo-grid-more', text: '+' + (count - 4) }));
        }

        cell.addEventListener('click', () => openLightbox(atts, idx));
        cell.appendChild(img);
        grid.appendChild(cell);
      });

      card.appendChild(grid);
    }

    renderReactions(card, p);
    return card;
  }

  // ===== LIGHTBOX =====
  function openLightbox(attachments, startIdx) {
    let idx = startIdx;
    const backdrop = ce('div', { class: 'lightbox-backdrop', role: 'dialog', 'aria-modal': 'true' });
    const img = ce('img', { class: 'lightbox-img', alt: '' });
    const counter = ce('div', { class: 'lightbox-counter' });
    const closeBtn = ce('button', { class: 'lightbox-close', type: 'button', text: '×', 'aria-label': T('close') });

    function show(i) {
      idx = i;
      const a = attachments[idx];
      img.src = a.path_original || a.path_thumb || a.url || '';
      counter.textContent = (idx + 1) + ' / ' + attachments.length;
    }

    const prevPhoto = () => show((idx - 1 + attachments.length) % attachments.length);
    const nextPhoto = () => show((idx + 1) % attachments.length);

    if (attachments.length > 1) {
      const prev = ce('button', { class: 'lightbox-nav lightbox-prev', type: 'button', text: '‹', 'aria-label': '‹' });
      const next = ce('button', { class: 'lightbox-nav lightbox-next', type: 'button', text: '›', 'aria-label': '›' });
      prev.addEventListener('click', (e) => { e.stopPropagation(); prevPhoto(); });
      next.addEventListener('click', (e) => { e.stopPropagation(); nextPhoto(); });
      backdrop.append(prev, next);
    } else {
      counter.hidden = true;
    }

    backdrop.append(closeBtn, img, counter);

    function onKey(e) {
      if (e.key === 'ArrowLeft') prevPhoto();
      if (e.key === 'ArrowRight') nextPhoto();
    }
    document.addEventListener('keydown', onKey);

    // Swipe left/right between photos, swipe down to close
    let touchX = null;
    let touchY = null;
    backdrop.addEventListener('touchstart', (e) => {
      if (e.touches.length === 1) {
        touchX = e.touches[0].clientX;
        touchY = e.touches[0].clientY;
      }
    }, { passive: true });
    backdrop.addEventListener('touchend', (e) => {
      if (touchX === null) return;
      const dx = e.changedTouches[0].clientX - touchX;
      const dy = e.changedTouches[0].clientY - touchY;
      touchX = touchY = null;
      if (dy > 90 && Math.abs(dy) > Math.abs(dx)) { close(); return; }
      if (attachments.length < 2 || Math.abs(dx) < 40) return;
      if (dx < 0) nextPhoto();
      else prevPhoto();
    }, { passive: true });

    // The image itself doesn't close the box; the dark around it does
    const close = openLayer(backdrop);
    const observer = new MutationObserver(() => {
      if (!backdrop.parentNode) {
        document.removeEventListener('keydown', onKey);
        observer.disconnect();
      }
    });
    observer.observe(document.body, { childList: true });
    closeBtn.addEventListener('click', close);
    show(startIdx);
    closeBtn.focus();
  }

  // ===== INFINITE SCROLL FEED =====
  const FEED_LIMIT = 10;
  let feedLastId = null;
  let feedLoading = false;
  let feedDone = false;
  let feedGen = 0;
  let scrollObserver = null;
  let sentinelEl = null;
  let feedNewestId = 0;

  function placeholder(icon, text) {
    return '<div class="gm-placeholder"><svg class="gm-placeholder-icon" viewBox="0 0 24 24" aria-hidden="true"><use href="#gm-i-' + icon + '"/></svg><p>' + esc(text) + '</p></div>';
  }

  function skeleton() {
    return '<div class="gm-skel-card" aria-hidden="true"><i></i><i></i><i></i></div>'.repeat(2) +
      '<p class="gm-sr-only">' + esc(T('loading_posts')) + '</p>';
  }

  async function loadFeed(roomId, reset) {
    if (reset === undefined) reset = true;
    const feed = $('#feed');
    if (!feed) return;

    const loading = $('#loadingIndicator');

    if (reset) {
      // New generation: any in-flight response from before this reset is discarded
      feedGen++;
      if (revealer) $$('.post-card', feed).forEach(c => revealer.unobserve(c));
      feed.innerHTML = skeleton();
      feed.setAttribute('aria-busy', 'true');
      POST_MAP.clear();
      feedLastId = null;
      feedDone = false;
      feedLoading = false;
      if (scrollObserver) scrollObserver.disconnect();
      sentinelEl = null;
    } else if (feedLoading || feedDone) {
      return;
    }

    const gen = feedGen;
    feedLoading = true;
    if (loading) loading.hidden = false;

    let url = '/gospel_media/api/posts/list.php?room_id=' + encodeURIComponent(roomId) + '&limit=' + FEED_LIMIT;
    if (feedLastId) url += '&after_id=' + encodeURIComponent(feedLastId);

    const j = await fetchJSON(url);

    // A reset happened while this request was in flight - drop the stale response
    if (gen !== feedGen) return;

    if (loading) loading.hidden = true;
    feedLoading = false;
    feed.setAttribute('aria-busy', 'false');

    const failed = !Array.isArray(j) && !(j && Array.isArray(j.rows));
    const rows = Array.isArray(j) ? j : (j && Array.isArray(j.rows) ? j.rows : []);

    if (reset) feed.innerHTML = '';

    if (failed) {
      if (reset) feed.innerHTML = placeholder('gospel', T('error'));
      else toast(T('error'), true);
      // feedDone stays false: scrolling the sentinel back into view tries again
      return;
    }

    if (!rows.length) {
      if (reset) feed.innerHTML = placeholder('gospel', T('no_posts'));
      feedDone = true;
      if (sentinelEl) sentinelEl.remove();
      return;
    }

    if (rows.length < FEED_LIMIT) feedDone = true;

    // Newest loaded post id, used to detect brand-new posts while the page is open
    if (reset && rows.length) feedNewestId = Number(rows[0].id) || 0;

    rows.forEach((p, i) => {
      const card = renderCard(p);
      if (sentinelEl && sentinelEl.parentNode === feed) feed.insertBefore(card, sentinelEl);
      else feed.appendChild(card);
      reveal(card, reset ? i : 0);
      feedLastId = p.id;
    });

    // Set up infinite scroll sentinel
    setupScrollObserver(feed, roomId);
  }

  // ===== NEW POSTS PILL (auto-check without reloading) =====
  let newPostsPill = null;

  function showNewPostsPill() {
    if (newPostsPill) return;
    newPostsPill = ce('button', { type: 'button', class: 'gm-new-posts-pill' });
    newPostsPill.innerHTML = ICON.arrowUp + '<span>' + esc(T('new_posts')) + '</span>';
    newPostsPill.addEventListener('click', async () => {
      newPostsPill.remove();
      newPostsPill = null;
      window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
      await loadFeed(ROOM_ID, true);
    });
    document.body.appendChild(newPostsPill);
  }

  async function checkForNewPosts() {
    if (document.hidden || feedLoading || !feedNewestId || newPostsPill) return;
    try {
      // peek=1 keeps this check from marking the room as read
      const j = await fetchJSON('/gospel_media/api/posts/list.php?room_id=' + encodeURIComponent(ROOM_ID) + '&limit=1&peek=1');
      const rows = Array.isArray(j) ? j : [];
      if (rows.length && Number(rows[0].id) > feedNewestId) showNewPostsPill();
    } catch (e) { /* network hiccup - try again next round */ }
  }

  function setupScrollObserver(feed, roomId) {
    if (scrollObserver) scrollObserver.disconnect();
    if (feedDone) {
      if (sentinelEl) sentinelEl.remove();
      return;
    }

    // Keep one sentinel at the very end of the feed
    if (!sentinelEl) sentinelEl = ce('div', { class: 'feed-sentinel', 'aria-hidden': 'true' });
    feed.appendChild(sentinelEl);

    if (!('IntersectionObserver' in window)) return;
    scrollObserver = new IntersectionObserver((entries) => {
      if (entries[0].isIntersecting && !feedLoading && !feedDone) {
        loadFeed(roomId, false);
      }
    }, { rootMargin: '400px' });

    scrollObserver.observe(sentinelEl);
  }

  // ===== MODALS =====
  function openEditModal(p) {
    const back = ce('div', { class: 'modal-backdrop' });
    const panel = ce('div', { class: 'modal-panel', role: 'dialog', 'aria-modal': 'true' });

    const title = ce('h2', { text: T('edit_post') });
    const who = ce('div', { class: 'muted', text: computeDisplayName(p) });
    const when = ce('div', { class: 'muted', text: p.created_at ? fmtDT(p.created_at) : '' });

    const isEvent = ((p.type || '') === 'event') || !!p.event_at;
    let evtWrap = null, evtInput = null, placeInput = null;

    if (isEvent) {
      const toInputLocal = (s) => s ? (String(s).replace(' ', 'T').slice(0, 16)) : '';
      evtWrap = ce('div', { class: 'modal-event-fields' });
      const lbl = ce('label', { text: T('datetime'), class: 'modal-label', for: 'gmEditAt' });
      const inp = ce('input', { type: 'datetime-local', class: 'composer-input', id: 'gmEditAt' });
      inp.value = toInputLocal(p.event_at || '');
      evtInput = inp;

      const placeLbl = ce('label', { text: T('place'), class: 'modal-label', for: 'gmEditPlace' });
      const placeInp = ce('input', { type: 'text', class: 'composer-input', id: 'gmEditPlace', maxlength: '255' });
      placeInp.value = p.event_place || '';
      placeInput = placeInp;

      evtWrap.append(lbl, inp, placeLbl, placeInp);
    }

    const ta = ce('textarea', { class: 'composer-textarea', 'aria-label': T('edit_post') });
    ta.value = p.text || '';
    ta.addEventListener('input', () => autoGrow(ta, Math.round(window.innerHeight * 0.46)));

    const file = ce('input', { type: 'file', accept: 'image/*', multiple: 'multiple', hidden: 'hidden' });

    const prev = ce('div', { class: 'composer-preview' });
    const existing = ce('div', { class: 'modal-existing-images' });
    let photosChanged = false;

    if (Array.isArray(p.attachments) && p.attachments.length) {
      p.attachments.forEach(a => {
        const box = ce('div', { class: 'modal-img-box' });

        const img = ce('img', { class: 'modal-img', alt: '' });
        img.src = a.path_thumb || a.path_original || '';

        const x = ce('button', { type: 'button', class: 'modal-img-del', text: '×', title: T('remove_photo'), 'aria-label': T('remove_photo') });

        x.addEventListener('click', async () => {
          const yes = await confirmAction(T('delete_photo'), T('cannot_undo'), T('delete'));
          if (!yes) return;

          x.disabled = true;
          x.textContent = '…';

          const fd = new FormData();
          fd.append('post_id', p.id);
          fd.append('path', a.path_original || a.path_thumb || '');

          const j = await fetchJSON('/gospel_media/api/posts/attachment_delete.php', { method: 'POST', body: fd });

          if (j && (j.ok || j.success)) {
            box.remove();
            photosChanged = true;
          } else {
            toast(errorText(j), true);
            x.disabled = false;
            x.textContent = '×';
          }
        });

        box.append(img, x);
        existing.appendChild(box);
      });
    }

    const attachBtn = ce('button', { type: 'button', class: 'composer-attach' });
    attachBtn.innerHTML = ICON.photo + '<span>' + esc(T('add_photo')) + '</span>';
    attachBtn.addEventListener('click', () => file.click());

    file.addEventListener('change', () => {
      $$('img', prev).forEach(img => URL.revokeObjectURL(img.src));
      prev.innerHTML = '';
      Array.from(file.files || []).forEach((f, idx) => {
        const box = ce('div', { class: 'modal-img-box' });

        const img = ce('img', { class: 'modal-img', alt: '' });
        img.src = URL.createObjectURL(f);

        const x = ce('button', { type: 'button', class: 'modal-img-del', text: '×', title: T('remove'), 'aria-label': T('remove') });

        x.addEventListener('click', () => {
          const dt = new DataTransfer();
          Array.from(file.files).forEach((f2, i2) => {
            if (i2 !== idx) dt.items.add(f2);
          });
          file.files = dt.files;
          box.remove();
        });

        box.append(img, x);
        prev.appendChild(box);
      });
    });

    const actions = ce('div', { class: 'modal-actions' });

    const save = ce('button', { class: 'modal-btn primary', type: 'button', text: T('save') });
    const closeBtn = ce('button', { class: 'modal-btn', type: 'button', text: T('back') });

    let saved = false;
    const close = openLayer(back);
    // A removed photo is already gone on the server: show that even without saving
    new MutationObserver((_, obs) => {
      if (back.parentNode) return;
      obs.disconnect();
      if (photosChanged && !saved) loadFeed(ROOM_ID);
    }).observe(document.body, { childList: true });

    save.addEventListener('click', async () => {
      const fd = new FormData();
      fd.append('post_id', p.id);
      fd.append('text', ta.value.trim());

      if (evtInput && evtInput.value) {
        fd.append('event_at', evtInput.value);
      }

      if (placeInput) {
        fd.append('event_place', placeInput.value.trim());
      }

      save.disabled = true;
      save.textContent = T('saving');

      if (file && file.files && file.files.length) {
        const compressed = await compressImages(Array.from(file.files));
        compressed.forEach(f => fd.append('images[]', f));
      }

      const j = await fetchJSON('/gospel_media/api/posts/update.php', { method: 'POST', body: fd });

      if (!(j && (j.success || j.ok))) {
        toast(errorText(j), true);
        save.disabled = false;
        save.textContent = T('save');
        return;
      }

      saved = true;
      close();
      await loadFeed(ROOM_ID);
    });

    closeBtn.addEventListener('click', close);

    panel.append(title, who, when);
    if (evtWrap) panel.append(evtWrap);
    panel.append(ta, existing, prev, attachBtn, file);
    actions.append(save, closeBtn);
    panel.append(actions);
    back.appendChild(panel);
    autoGrow(ta, Math.round(window.innerHeight * 0.46));
    ta.focus();
  }

  function openDeleteModal(p) {
    const back = ce('div', { class: 'modal-backdrop' });
    const panel = ce('div', { class: 'modal-panel', role: 'alertdialog', 'aria-modal': 'true' });

    const title = ce('h2', { text: T('confirm_delete') });
    const info = ce('p', { text: T('cannot_undo'), class: 'modal-info' });
    const who = ce('div', { class: 'muted', text: computeDisplayName(p) });
    const when = ce('div', { class: 'muted', text: p.created_at ? fmtDT(p.created_at) : '' });

    const actions = ce('div', { class: 'modal-actions' });

    const confirmBtn = ce('button', { class: 'modal-btn danger', type: 'button', text: T('delete') });
    const cancel = ce('button', { class: 'modal-btn', type: 'button', text: T('cancel') });

    const close = openLayer(back);

    confirmBtn.addEventListener('click', async () => {
      const fd = new FormData();
      fd.append('post_id', p.id);

      confirmBtn.disabled = true;
      confirmBtn.textContent = T('deleting');

      const j = await fetchJSON('/gospel_media/api/posts/delete.php', { method: 'POST', body: fd });

      if (!(j && (j.success || j.ok))) {
        toast(errorText(j), true);
        confirmBtn.disabled = false;
        confirmBtn.textContent = T('delete');
        return;
      }
      close();

      // Fold the card away instead of reloading the whole feed
      const card = document.getElementById('post-' + p.id);
      POST_MAP.delete(String(p.id));
      if (card) {
        card.style.transition = 'opacity .3s ease, transform .3s ease';
        card.style.opacity = '0';
        card.style.transform = 'scale(0.97)';
        setTimeout(() => {
          card.remove();
          const feed = $('#feed');
          if (feed && !feed.querySelector('.post-card')) loadFeed(ROOM_ID);
        }, reduceMotion ? 0 : 300);
      } else {
        await loadFeed(ROOM_ID);
      }
    });

    cancel.addEventListener('click', close);

    actions.append(confirmBtn, cancel);
    panel.append(title, info, who, when, actions);
    back.appendChild(panel);
    cancel.focus();
  }

  function openActionsModal(p) {
    const back = ce('div', { class: 'modal-backdrop' });
    const panel = ce('div', { class: 'modal-panel', role: 'dialog', 'aria-modal': 'true' });

    const title = ce('h2', { text: T('post_actions') });
    const who = ce('div', { class: 'muted', text: computeDisplayName(p) });
    const when = ce('div', { class: 'muted', text: p.created_at ? fmtDT(p.created_at) : '' });
    const text = ce('p', { text: (p.text || '').substring(0, 140) + (p.text && p.text.length > 140 ? '…' : ''), class: 'modal-preview' });

    const list = ce('div', { class: 'modal-action-list' });
    const bEdit = ce('button', { class: 'modal-action', type: 'button' });
    bEdit.innerHTML = ICON.pencil + '<span>' + esc(T('edit')) + '</span>';
    const bDel = ce('button', { class: 'modal-action danger', type: 'button' });
    bDel.innerHTML = ICON.trash + '<span>' + esc(T('delete')) + '</span>';
    list.append(bEdit, bDel);

    const actions = ce('div', { class: 'modal-actions' });
    const bClose = ce('button', { class: 'modal-btn', type: 'button', text: T('cancel') });
    actions.append(bClose);

    const close = openLayer(back);
    bEdit.addEventListener('click', () => { close(); openEditModal(p); });
    bDel.addEventListener('click', () => { close(); openDeleteModal(p); });
    bClose.addEventListener('click', close);

    panel.append(title, who, when);
    if (p.text) panel.append(text);
    panel.append(list, actions);
    back.appendChild(panel);
    bEdit.focus();
  }

  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.post-menu-btn');
    if (!btn) return;

    const card = btn.closest('[data-post-id]');
    const id = card ? card.getAttribute('data-post-id') : null;
    const p = id ? POST_MAP.get(id) : null;

    if (!p || !canEditPost(p)) return;
    openActionsModal(p);
  });

  // ===== ATMOSPHERE: embers + a little parallax on the halo =====
  function initAtmosphere() {
    const aura = $('#gmAura');
    const host = $('#gmEmbers');
    if (!aura || reduceMotion) return;

    if (host) {
      const rand = (lo, hi) => lo + Math.random() * (hi - lo);
      const count = window.innerWidth < 640 ? 12 : 20;
      const frag = document.createDocumentFragment();
      for (let i = 0; i < count; i++) {
        const ember = document.createElement('i');
        ember.className = 'gm-ember';
        ember.style.cssText =
          '--x:' + rand(0, 100).toFixed(1) + '%;' +
          '--s:' + rand(3, 8).toFixed(1) + 'px;' +
          '--d:' + rand(16, 32).toFixed(1) + 's;' +
          '--delay:-' + rand(0, 32).toFixed(1) + 's;' +
          '--o:' + rand(0.3, 0.8).toFixed(2) + ';' +
          '--sway:' + rand(-44, 44).toFixed(0) + 'px';
        frag.appendChild(ember);
      }
      host.appendChild(frag);
    }

    let queued = false;
    window.addEventListener('scroll', () => {
      if (queued) return;
      queued = true;
      requestAnimationFrame(() => {
        queued = false;
        aura.style.setProperty('--sy', String(Math.min(window.pageYOffset || 0, 1400)));
      });
    }, { passive: true });

    if (window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
      let px = 0, py = 0, pq = false;
      document.addEventListener('pointermove', (e) => {
        px = e.clientX / (window.innerWidth || 1) - 0.5;
        py = e.clientY / (window.innerHeight || 1) - 0.5;
        if (pq) return;
        pq = true;
        requestAnimationFrame(() => {
          pq = false;
          aura.style.setProperty('--px', px.toFixed(3));
          aura.style.setProperty('--py', py.toFixed(3));
        });
      }, { passive: true });
    }
  }

  // Static sections (rooms rail, composer prompt) rise in like the cards
  function initReveal() {
    $$('.gm-reveal').forEach((el, i) => {
      if (revealer) revealer.observe(el);
      else el.classList.add('is-in');
      if (!el.style.getPropertyValue('--i')) el.style.setProperty('--i', String(i));
    });
  }

  // ===== DEEP LINKS: notifications point at #post-N =====
  async function openDeepLink() {
    if (!location.hash || !/^#post-\d+$/.test(location.hash)) return;
    const id = location.hash.slice(1);
    let target = document.getElementById(id);

    // Older posts are further down the feed: fetch a few more pages to find them
    for (let tries = 0; !target && !feedDone && tries < 5; tries++) {
      // The scroll sentinel may already be fetching the next page
      while (feedLoading) await new Promise(r => setTimeout(r, 120));
      await loadFeed(ROOM_ID, false);
      target = document.getElementById(id);
    }
    if (!target) return;

    target.classList.add('is-in');
    target.classList.add('is-target');
    target.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
  }

  // ===== BOOT =====
  async function boot() {
    [initAtmosphere, initReveal].forEach(fn => {
      try { fn(); } catch (err) { if (window.console) console.error('gospel.js:', err); }
    });

    // New post: hero button, the "What do you want to share?" card, footer button
    const composerBtn = $('#open-composer');
    if (composerBtn) composerBtn.addEventListener('click', () => openComposerOverlay('post'));

    document.addEventListener('click', (e) => {
      const opener = e.target.closest('[data-composer-open]');
      if (opener) openComposerOverlay(opener.getAttribute('data-composer-open'));
    });

    // Footer new-post button (rendered by footer.php on gospel_media pages)
    const fab = $('#gm-fab');
    if (fab) fab.addEventListener('click', () => openComposerOverlay('post'));

    try {
      const me = await fetchJSON('/gospel_media/api/users/me.php');
      if (me && me.id) {
        ME.id = me.id;
        ME.amp_id = me.amp_id;
      }
    } catch (e) {
      console.error('Failed to load user info:', e);
    }

    await loadFeed(ROOM_ID);
    await openDeepLink();

    setInterval(checkForNewPosts, 30000);
    // Coming back to the app: look straight away instead of waiting a round
    document.addEventListener('visibilitychange', () => { if (!document.hidden) checkForNewPosts(); });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
