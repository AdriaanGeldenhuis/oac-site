/**
 * Daily thought tab: write a thought (with an optional spelling check), save
 * it for a date in its own language, see the coming ones in every language,
 * correct a translation, edit or delete a thought.
 * Talks to /admin/api/gedagte/ through OACUI.post (CSRF).
 * One thought per date: a new thought on a taken date replaces it; "Edit"
 * loads a thought back into the form and saves it under its own id, so
 * changing its date moves it instead of leaving a copy behind.
 * "Send now" saves it as today's thought and sends it at once, whatever the
 * date and time fields say.
 */
(function () {
  'use strict';

  const form = document.getElementById('gedagteForm');
  const listEl = document.getElementById('gedagteList');
  if (!form || !listEl || !window.OACUI) return;

  const L = window.GEDAGTE_LANG || {};
  const NAMES = L.langNames || {};
  const idEl = document.getElementById('thoughtId');
  const contentEl = document.getElementById('thoughtContent');
  const authorEl = document.getElementById('thoughtAuthor');
  const langEl = document.getElementById('thoughtLang');
  const dateEl = document.getElementById('thoughtDate');
  const timeEl = document.getElementById('thoughtTime');
  const countEl = document.getElementById('thoughtCount');
  const resetBtn = document.getElementById('thoughtReset');
  const submitBtn = form.querySelector('button[type="submit"]');
  const submitLabel = form.querySelector('.gedagte-submit-label');
  const sendBtn = document.getElementById('thoughtSendNow');
  const sendLabel = form.querySelector('.gedagte-send-label');
  const checkBtn = document.getElementById('thoughtCheck');
  const suggestEl = document.getElementById('thoughtSuggest');
  const suggestText = document.getElementById('thoughtSuggestText');
  const MAX = parseInt(contentEl.dataset.max, 10) || 2000;
  const LANG_KEY = 'oac_gedagte_lang';

  // view: thought id -> the language shown; editing: {id, lang} of an open translation editor
  const state = { thoughts: [], view: {}, editing: null, suggestion: '' };

  function fill(s, params) {
    s = String(s || '');
    for (const k in params) s = s.split('{' + k + '}').join(String(params[k]));
    return s;
  }

  /** Characters as the server counts them (code points, so an emoji is one) */
  const length = (s) => Array.from(s).length;

  // The language last written in, a per-device convenience
  function remembered() {
    try { return localStorage.getItem(LANG_KEY); } catch (e) { return null; }
  }
  function remember(v) {
    try { localStorage.setItem(LANG_KEY, v); } catch (e) { /* private mode */ }
  }

  // Today as the member's calendar sees it (not UTC, which is a day behind after midnight in SAST)
  function localISO(d) {
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  }
  const todayISO = () => localISO(new Date());

  function el(tag, cls, text) {
    const n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text != null) n.textContent = text;
    return n;
  }

  function button(cls, text, onClick) {
    const b = el('button', cls, text);
    b.type = 'button';
    if (onClick) b.addEventListener('click', onClick);
    return b;
  }

  function busy(btn, on) {
    if (!btn) return;
    btn.disabled = on;
    btn.classList.toggle('is-busy', on);
  }

  function fmtDate(iso) {
    const d = new Date(iso + 'T00:00:00');
    return d.toLocaleDateString(L.lang || undefined, { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
  }

  // ===== FORM =====
  function resetForm() {
    form.reset();
    idEl.value = '';
    dateEl.value = todayISO();
    timeEl.value = '07:00';
    const lang = remembered();
    if (lang && NAMES[lang]) langEl.value = lang;
    resetBtn.hidden = true;
    hideSuggest();
    updateCount();
  }

  function updateCount() {
    const left = MAX - length(contentEl.value);
    countEl.classList.toggle('is-over', left < 0);
    if (left < 0) countEl.textContent = fill(L.charsOver, { n: -left });
    else countEl.textContent = left <= 200 ? fill(L.charsLeft, { n: left }) : '';
  }

  /** Busy on the button that was pressed, the other one off meanwhile */
  function setSaving(on, now) {
    busy(now ? sendBtn : submitBtn, on);
    const other = now ? submitBtn : sendBtn;
    if (other) other.disabled = on;
    if (submitLabel) submitLabel.textContent = on && !now && L.ai ? (L.saving || L.saveLabel) : L.saveLabel;
    if (sendLabel) sendLabel.textContent = on && now ? (L.sending || L.sendNow) : L.sendNow;
  }

  function editThought(t) {
    idEl.value = String(t.id);
    contentEl.value = t.content || '';
    authorEl.value = t.author || '';
    if (NAMES[t.lang]) langEl.value = t.lang;
    dateEl.value = t.display_date;
    timeEl.value = t.display_time ? t.display_time.slice(0, 5) : '';
    resetBtn.hidden = false;
    hideSuggest();
    updateCount();
    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    contentEl.focus({ preventScroll: true });
  }

  // ===== SPELLING CHECK =====
  function hideSuggest() {
    if (!suggestEl) return;
    suggestEl.hidden = true;
    suggestText.replaceChildren();
    state.suggestion = '';
  }

  /** The corrected text with every new or changed word marked (a word-level LCS: thoughts are short) */
  function marked(before, after) {
    const frag = document.createDocumentFragment();
    const a = before.split(/(\s+)/);
    const b = after.split(/(\s+)/);
    const n = a.length;
    const m = b.length;
    if (n * m > 600000) { frag.append(after); return frag; }

    const dp = [];
    for (let i = 0; i <= n; i++) dp.push(new Uint16Array(m + 1));
    for (let i = n - 1; i >= 0; i--) {
      for (let j = m - 1; j >= 0; j--) {
        dp[i][j] = a[i] === b[j] ? dp[i + 1][j + 1] + 1 : Math.max(dp[i + 1][j], dp[i][j + 1]);
      }
    }

    let i = 0;
    let j = 0;
    let same = '';
    while (j < m) {
      if (i < n && a[i] === b[j]) {
        same += b[j];
        i++;
        j++;
      } else if (i < n && dp[i + 1][j] >= dp[i][j + 1]) {
        i++; // a word that was taken out
      } else {
        if (/^\s*$/.test(b[j])) {
          same += b[j];
        } else {
          if (same) { frag.append(same); same = ''; }
          frag.append(el('mark', 'gedagte-mark', b[j]));
        }
        j++;
      }
    }
    if (same) frag.append(same);
    return frag;
  }

  async function check() {
    const text = contentEl.value.trim();
    if (!text) { contentEl.focus(); return; }
    if (length(text) > MAX) { OACUI.toast(fill(L.tooLong, { n: MAX }), 'error'); return; }

    busy(checkBtn, true);
    const data = await OACUI.post('/admin/api/gedagte/improve.php', { content: text, lang: langEl.value });
    busy(checkBtn, false);

    if (contentEl.value.trim() !== text) return; // typed on meanwhile: this check is out of date
    if (!data.success) { OACUI.toast(data.error || '', 'error'); return; }
    if (!data.changed) { hideSuggest(); OACUI.toast(L.checkNone || '', 'success'); return; }

    state.suggestion = String(data.improved || '');
    suggestText.replaceChildren(marked(text, state.suggestion));
    suggestEl.hidden = false;
    suggestEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  // ===== LIST =====
  function showEmpty(message) {
    listEl.replaceChildren(el('p', 'admin-muted', message));
    listEl.setAttribute('aria-busy', 'false');
  }

  async function loadThoughts() {
    const res = await fetch('/admin/api/gedagte/list.php', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(r => r.json()).catch(() => null);

    if (!res || !res.success) {
      showEmpty(L.loadFailed || '');
      return;
    }
    if (typeof res.ai === 'boolean') L.ai = res.ai;
    state.thoughts = res.thoughts || [];
    renderList();
  }

  function renderList() {
    if (!state.thoughts.length) {
      showEmpty(L.noUpcoming || '');
      return;
    }
    const today = todayISO();
    const frag = document.createDocumentFragment();
    state.thoughts.forEach((t) => frag.appendChild(itemEl(t, today)));
    listEl.replaceChildren(frag);
    listEl.setAttribute('aria-busy', 'false');
  }

  /** Draw one thought again, keeping the focus on the same control */
  function redraw(t, focusSelector) {
    const old = listEl.querySelector('.gedagte-item[data-id="' + t.id + '"]');
    if (!old) return;
    const fresh = itemEl(t, todayISO());
    old.replaceWith(fresh);
    const target = focusSelector && fresh.querySelector(focusSelector);
    if (target) target.focus();
  }

  /** What the list shows for a language: the original, its translation, or (missing) the original */
  function textFor(t, lang) {
    if (lang === t.lang) return { content: t.content, author: t.author, lang: t.lang };
    const tr = t.translations && t.translations[lang];
    if (tr) return { content: tr.content, author: tr.author || t.author, lang: lang, manual: !!tr.manual };
    return { content: t.content, author: t.author, lang: t.lang, missing: true };
  }

  function langChips(t, view) {
    const box = el('div', 'gedagte-langs');
    box.setAttribute('role', 'group');
    box.setAttribute('aria-label', L.langsLabel || '');
    Object.keys(NAMES).forEach((code) => {
      const original = code === t.lang;
      const missing = !original && !(t.translations && t.translations[code]);
      const chip = button('gedagte-lang' + (original ? ' is-original' : '') + (missing ? ' is-missing' : ''), NAMES[code], () => {
        state.view[t.id] = code;
        state.editing = null;
        redraw(t, '.gedagte-lang[data-lang="' + code + '"]');
      });
      chip.dataset.lang = code;
      chip.setAttribute('aria-pressed', code === view ? 'true' : 'false');
      if (original || missing) {
        const note = original ? L.original : L.notTranslated;
        chip.title = note;
        chip.setAttribute('aria-label', NAMES[code] + ' — ' + note);
      }
      if (original) chip.appendChild(el('span', 'gedagte-lang-tag', L.original));
      box.appendChild(chip);
    });
    return box;
  }

  function translationEditor(t, lang) {
    const tr = (t.translations && t.translations[lang]) || null;
    const wrap = el('div', 'gedagte-tr-edit');
    const id = 'gdTr' + t.id + lang;

    // The original, to translate from
    const ref = el('p', 'gedagte-tr-ref', t.content);
    ref.lang = t.lang;

    const label = el('label', 'admin-label', (L.editTranslation || '') + ' · ' + (NAMES[lang] || lang));
    label.htmlFor = id;
    const ta = el('textarea', 'admin-textarea gedagte-textarea');
    ta.id = id;
    ta.rows = 6;
    ta.lang = lang;
    ta.value = tr ? tr.content : '';
    wrap.append(ref, label, ta);

    let authorInput = null;
    if (t.author) {
      const aid = id + 'a';
      const aLabel = el('label', 'admin-label', L.author || '');
      aLabel.htmlFor = aid;
      authorInput = el('input', 'admin-input');
      authorInput.id = aid;
      authorInput.type = 'text';
      authorInput.maxLength = 255;
      authorInput.autocomplete = 'off';
      authorInput.value = (tr && tr.author) || t.author;
      wrap.append(aLabel, authorInput);
    }

    const save = button('gedagte-btn gedagte-btn--accent', L.save || 'Save', async () => {
      if (!ta.value.trim()) { ta.focus(); return; }
      busy(save, true);
      const data = await OACUI.post('/admin/api/gedagte/save_translation.php', {
        id: t.id, lang: lang, content: ta.value, author: authorInput ? authorInput.value : ''
      });
      busy(save, false);
      if (!data.success) { OACUI.toast(data.error || '', 'error'); return; }
      OACUI.toast(L.translationSaved || '', 'success');
      state.editing = null;
      loadThoughts();
    });
    const cancel = button('gedagte-btn', L.cancel || 'Cancel', () => {
      state.editing = null;
      redraw(t, '.gedagte-lang[data-lang="' + lang + '"]');
    });
    const actions = el('div', 'gedagte-item-actions');
    actions.append(cancel, save);
    wrap.appendChild(actions);

    requestAnimationFrame(() => ta.focus());
    return wrap;
  }

  function itemEl(t, today) {
    const isToday = t.display_date === today;
    const isPast = t.display_date < today;
    const item = el('article', 'gedagte-item' + (isToday ? ' gedagte-item--today' : '') + (isPast ? ' gedagte-item--past' : ''));
    item.dataset.id = t.id;

    const head = el('div', 'gedagte-item-date');
    head.appendChild(el('span', 'gedagte-item-day', isToday ? (L.today || fmtDate(t.display_date)) : fmtDate(t.display_date)));
    if (t.display_time) {
      head.appendChild(el('span', 'gedagte-item-time', t.display_time.slice(0, 5)));
    }
    if (t.notification_sent) {
      head.appendChild(el('span', 'gedagte-item-notif gedagte-item-notif--sent', L.notifSent || ''));
    } else if (!isPast) {
      head.appendChild(el('span', 'gedagte-item-notif gedagte-item-notif--pending', L.notifPending || ''));
    }
    item.appendChild(head);

    const view = NAMES[state.view[t.id]] ? state.view[t.id] : t.lang;
    const editingHere = state.editing && state.editing.id === t.id && state.editing.lang === view;

    if (editingHere) {
      item.appendChild(translationEditor(t, view));
    } else {
      const shown = textFor(t, view);
      const body = el('div', 'gedagte-item-body');
      body.lang = shown.lang;
      body.appendChild(el('p', 'gedagte-item-content', shown.content));
      if (shown.author) body.appendChild(el('p', 'gedagte-item-author', '— ' + shown.author));
      if (shown.missing) body.appendChild(el('p', 'gedagte-item-note gedagte-item-note--warn', L.notTranslated || ''));
      else if (shown.manual) body.appendChild(el('p', 'gedagte-item-note', L.byHand || ''));
      item.appendChild(body);
    }

    item.appendChild(langChips(t, view));

    if (!editingHere) {
      const actions = el('div', 'gedagte-item-actions');
      if (L.ai && t.missing && t.missing.length) {
        const tr = button('gedagte-btn gedagte-btn--accent', L.translateMissing || '', () => translateMissing(t, tr));
        actions.appendChild(tr);
      }
      if (view !== t.lang) {
        actions.appendChild(button('gedagte-btn', L.editTranslation || '', () => {
          state.editing = { id: t.id, lang: view };
          redraw(t);
        }));
      }
      actions.appendChild(button('gedagte-btn', L.editLabel || 'Edit', () => editThought(t)));
      const delBtn = button('gedagte-btn gedagte-btn--danger gedagte-delete-btn', L.deleteLabel || 'Delete', () => deleteThought(t.id, delBtn));
      actions.appendChild(delBtn);
      item.appendChild(actions);
    }

    return item;
  }

  async function translateMissing(t, btn) {
    busy(btn, true);
    const data = await OACUI.post('/admin/api/gedagte/translate.php', { id: t.id });
    busy(btn, false);
    if (data.success) {
      OACUI.toast(L.translated || '', 'success');
      loadThoughts();
    } else {
      OACUI.toast(data.error || '', 'error');
    }
  }

  async function deleteThought(id, btn) {
    const sure = await OACUI.confirm(L.confirmDelete || '', { danger: true });
    if (!sure) return;

    btn.disabled = true;
    const data = await OACUI.post('/admin/api/gedagte/delete.php', { id: id });
    if (data.success) {
      OACUI.toast(L.deleted || '', 'success');
      if (idEl.value === String(id)) resetForm(); // it no longer exists to be edited
      loadThoughts();
    } else {
      OACUI.toast(data.error || '', 'error');
      btn.disabled = false;
    }
  }

  // ===== SAVE / SEND NOW =====
  /** Is the form ready to go? Focuses what is missing. "Send now" needs no date. */
  function ready(needDate) {
    const text = contentEl.value.trim();
    if (!text) {
      contentEl.focus();
      return false;
    }
    if (length(text) > MAX) {
      OACUI.toast(fill(L.tooLong, { n: MAX }), 'error');
      contentEl.focus();
      return false;
    }
    if (needDate && !dateEl.value) {
      dateEl.focus();
      return false;
    }
    return true;
  }

  async function save(now) {
    const body = new URLSearchParams(new FormData(form));
    if (now) body.set('send_now', '1');

    setSaving(true, now);
    const data = await OACUI.post('/admin/api/gedagte/save.php', body);
    setSaving(false, now);

    if (data.success) {
      const missing = data.missing || [];
      if (data.ai && missing.length) {
        const langs = missing.map((c) => NAMES[c] || c).join(', ');
        OACUI.toast(fill(now ? L.sentMissing : L.savedMissing, { langs: langs }), 'error', 8000);
      } else if (now) {
        OACUI.toast(L.sentNow || '', 'success');
      } else {
        OACUI.toast((data.ai ? L.savedTranslated : L.saved) || '', 'success');
      }
      remember(langEl.value);
      resetForm();
      loadThoughts();
    } else {
      OACUI.toast(data.error || '', 'error');
    }
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (ready(true)) save(false);
  });

  if (sendBtn) {
    sendBtn.addEventListener('click', async function () {
      if (!ready(false)) return;
      // One thought per date: another one already on today gives way to this one
      const today = todayISO();
      const taken = state.thoughts.some((t) => t.display_date === today && String(t.id) !== idEl.value);
      const sure = await OACUI.confirm((L.sendNowConfirm || '') + (taken ? ' ' + (L.sendNowReplace || '') : ''), { okText: L.sendNow });
      if (sure) save(true);
    });
  }

  resetBtn.addEventListener('click', resetForm);
  contentEl.addEventListener('input', () => {
    updateCount();
    if (state.suggestion) hideSuggest(); // the suggestion was for the text before this change
  });
  if (checkBtn) checkBtn.addEventListener('click', check);
  const useBtn = document.getElementById('thoughtSuggestUse');
  const keepBtn = document.getElementById('thoughtSuggestKeep');
  if (useBtn) {
    useBtn.addEventListener('click', () => {
      contentEl.value = state.suggestion;
      hideSuggest();
      updateCount();
      contentEl.focus();
    });
  }
  if (keepBtn) {
    keepBtn.addEventListener('click', () => {
      hideSuggest();
      contentEl.focus();
    });
  }

  // Leaving with unsent words asks first
  window.addEventListener('beforeunload', function (e) {
    if (contentEl.value.trim() && !submitBtn.disabled) {
      e.preventDefault();
      e.returnValue = '';
    }
  });

  resetForm();
  loadThoughts();
})();
