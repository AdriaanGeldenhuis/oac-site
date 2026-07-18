/* =====================================================================
   Bybel Opsomming — client-side reader
   Views: boeke → boek-oorsig (6 dele) → deel → studie
   ===================================================================== */
(function () {
  'use strict';

  const CFG = window.OPSOMMING || {};
  const T = CFG.t || {};
  const app = document.getElementById('opsApp');

  const ROMANS = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X'];

  const state = {
    data: null,
    flat: [],          // flat list of studies: {p, s, study}
    read: new Set(),
    searchIndex: [],
    tab: 'ot',
  };

  // ---------- storage ----------
  const readKey = () => 'ops_read_' + CFG.book;
  const lastKey = () => 'ops_last_' + CFG.book;

  function loadRead() {
    try {
      const raw = localStorage.getItem(readKey());
      state.read = new Set(raw ? JSON.parse(raw) : []);
    } catch (e) { state.read = new Set(); }
  }

  function saveRead() {
    try { localStorage.setItem(readKey(), JSON.stringify([...state.read])); } catch (e) {}
  }

  function loadFont() {
    const v = parseFloat(localStorage.getItem('ops_font') || '1.06');
    if (v >= 0.85 && v <= 1.5) {
      document.documentElement.style.setProperty('--read-size', v + 'rem');
    }
    return v;
  }

  function bumpFont(delta) {
    let v = parseFloat(localStorage.getItem('ops_font') || '1.06') + delta;
    v = Math.min(1.5, Math.max(0.85, Math.round(v * 100) / 100));
    try { localStorage.setItem('ops_font', String(v)); } catch (e) {}
    document.documentElement.style.setProperty('--read-size', v + 'rem');
  }

  // ---------- helpers ----------
  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  // escape + **bold** → <strong>
  function fmt(s) {
    let out = esc(s);
    out = out.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    return out;
  }

  function studyKey(pi, si) { return 'p' + pi + 's' + si; }

  function isRead(pi, si) { return state.read.has(studyKey(pi, si)); }

  function partReadCount(pi) {
    const part = state.data.parts[pi];
    let c = 0;
    part.studies.forEach((_, si) => { if (isRead(pi, si)) c++; });
    return c;
  }

  function totalStudies() {
    return state.flat.length;
  }

  function totalRead() {
    let c = 0;
    state.data.parts.forEach((p, pi) => p.studies.forEach((_, si) => { if (isRead(pi, si)) c++; }));
    return c;
  }

  function setTitle(sub) {
    document.title = (sub ? sub + ' · ' : '') + (T.bible_summary || 'Bybel Opsomming');
  }

  function scrollTop() { window.scrollTo({ top: 0, behavior: 'instant' in window ? 'instant' : 'auto' }); }

  function go(hash) { location.hash = hash; }

  // ---------- data ----------
  function buildIndexes() {
    state.flat = [];
    state.searchIndex = [];
    state.data.parts.forEach((part, pi) => {
      part.studies.forEach((study, si) => {
        state.flat.push({ pi, si, study });
        let text = '';
        if (study.kind === 'study') {
          (study.sections || []).forEach((sec) => {
            if (sec.blocks) sec.blocks.forEach((b) => { text += ' ' + blockText(b); });
            if (sec.quotes) sec.quotes.forEach((q) => {
              text += ' ' + (q.ref || '');
              (q.verses || []).forEach((v) => { text += ' ' + v.x; });
              (q.note || []).forEach((n) => { text += ' ' + n; });
            });
          });
        } else {
          (study.blocks || []).forEach((b) => { text += ' ' + blockText(b); });
        }
        state.searchIndex.push({
          pi, si,
          title: study.title,
          range: study.range || '',
          part: part.title,
          text: (study.title + ' ' + (study.range || '') + ' ' + text).toLowerCase(),
          raw: text,
        });
      });
    });
  }

  function blockText(b) {
    if (!b) return '';
    if (b.t === 'ul') return (b.items || []).join(' ');
    if (b.t === 'table') return (b.rows || []).map((r) => r.join(' ')).join(' ');
    if (b.t === 'quote') {
      return (b.ref || '') + ' ' + (b.verses || []).map((v) => v.x).join(' ') + ' ' + (b.note || []).join(' ');
    }
    return b.x || '';
  }

  // ---------- block rendering ----------
  function renderBlocks(blocks) {
    let html = '';
    (blocks || []).forEach((b) => {
      switch (b.t) {
        case 'h':
          html += '<p class="ops-mini-h">' + fmt(b.x) + '</p>';
          break;
        case 'say':
          html += '<p class="ops-say">' + fmt(b.x) + '</p>';
          break;
        case 'ul':
          html += '<ul>' + (b.items || []).map((li) => '<li>' + fmt(li) + '</li>').join('') + '</ul>';
          break;
        case 'table':
          html += renderTable(b.rows);
          break;
        case 'quote':
          html += renderQuote(b);
          break;
        default:
          html += '<p>' + fmt(b.x) + '</p>';
      }
    });
    return html;
  }

  function renderTable(rows) {
    if (!rows || !rows.length) return '';
    const head = rows[0];
    const body = rows.slice(1);
    let html = '<div class="ops-table-wrap"><table class="ops-table"><thead><tr>';
    head.forEach((c) => { html += '<th>' + fmt(c) + '</th>'; });
    html += '</tr></thead><tbody>';
    body.forEach((r) => {
      html += '<tr>' + r.map((c) => '<td>' + fmt(c) + '</td>').join('') + '</tr>';
    });
    html += '</tbody></table></div>';
    return html;
  }

  function renderQuote(q) {
    let html = '<div class="ops-quote">';
    if (q.ref) html += '<div class="ops-quote-ref">' + fmt(q.ref) + '</div>';
    if (q.verses && q.verses.length) {
      html += '<div class="ops-quote-verses">';
      q.verses.forEach((v) => {
        html += '<div class="ops-verse' + (v.b ? ' kern' : '') + '">' +
          '<span class="ops-verse-num">' + esc(v.v) + '</span>' +
          '<span class="ops-verse-text">' + fmt(v.x) + '</span></div>';
      });
      html += '</div>';
    }
    if (q.rows && q.rows.length) {
      html += '<div class="ops-quote-verses">' + renderTable(q.rows) + '</div>';
    }
    if (q.note && q.note.length) {
      html += '<div class="ops-quote-note">' + q.note.map((n) => '<p>' + fmt(n) + '</p>').join('') + '</div>';
    }
    html += '</div>';
    return html;
  }

  function langNotice() {
    if (CFG.lang !== CFG.dataLang) {
      return '<div class="ops-notice"><span>🌍</span><span>' + esc(T.sum_af_only) + '</span></div>';
    }
    return '';
  }

  // ---------- views ----------
  function viewBooks() {
    setTitle('');
    const availableCount = (CFG.available || []).length;
    const totalBooks = CFG.books.ot.length + CFG.books.nt.length;

    const tabs =
      '<div class="ops-tabs">' +
      '<button class="ops-tab' + (state.tab === 'ot' ? ' active' : '') + '" data-tab="ot">' + esc(T.old_testament) + '</button>' +
      '<button class="ops-tab' + (state.tab === 'nt' ? ' active' : '') + '" data-tab="nt">' + esc(T.new_testament) + '</button>' +
      '</div>';

    const list = CFG.books[state.tab] || [];
    let grid = '<div class="ops-books-grid ops-stagger">';
    list.forEach((b) => {
      const avail = (CFG.available || []).indexOf(b.id) !== -1;
      if (avail) {
        const total = state.data ? totalStudies() : 0;
        const read = state.data ? totalRead() : 0;
        const pct = total ? Math.round((read / total) * 100) : 0;
        grid += '<button class="ops-book-card available" data-book="' + esc(b.id) + '">' +
          '<span class="ops-book-badge">' + esc(T.sum_available) + '</span>' +
          '<span class="ops-book-name">' + esc(b.name) + '</span>' +
          '<span class="ops-book-state">' + esc(T.sum_parts) + ': 6 · ' + esc(T.sum_studies_label) + ': ' + total + '</span>' +
          '<span class="ops-book-progress"><span style="width:' + pct + '%"></span></span>' +
          '</button>';
      } else {
        grid += '<div class="ops-book-card locked">' +
          '<span class="ops-book-name">' + esc(b.name) + '</span>' +
          '<span class="ops-book-state">' + esc(T.sum_coming_soon) + '</span>' +
          '</div>';
      }
    });
    grid += '</div>';

    app.innerHTML =
      '<div class="ops-view">' +
      '<div class="ops-hero">' +
      '<p class="ops-hero-tagline">' + esc(T.bible_summary_tagline) + '</p>' +
      '<h2 class="ops-hero-title">' + esc(T.bible_summary) + '</h2>' +
      '<div class="ops-hero-ornament">✦</div>' +
      '</div>' +
      langNotice() +
      tabs +
      '<p class="ops-books-meta">' + esc(T.sum_books_available) + ': ' + availableCount + ' / ' + totalBooks + '</p>' +
      grid +
      '<div class="ops-foot-ornament">✦</div>' +
      '</div>';

    app.querySelectorAll('.ops-tab').forEach((el) => {
      el.addEventListener('click', () => { state.tab = el.dataset.tab; viewBooks(); });
    });
    app.querySelectorAll('.ops-book-card.available').forEach((el) => {
      el.addEventListener('click', () => go('#/' + el.dataset.book));
    });
  }

  function viewOverview() {
    const d = state.data;
    setTitle(d.title);
    const total = totalStudies();
    const read = totalRead();
    const pct = total ? Math.round((read / total) * 100) : 0;

    let continueHtml = '';
    let last = null;
    try { last = JSON.parse(localStorage.getItem(lastKey()) || 'null'); } catch (e) {}
    if (last && d.parts[last.pi] && d.parts[last.pi].studies[last.si]) {
      const st = d.parts[last.pi].studies[last.si];
      continueHtml =
        '<button class="ops-continue" id="opsContinue" data-pi="' + last.pi + '" data-si="' + last.si + '">' +
        '<span><span class="cont-label">' + esc(read ? T.sum_continue_reading : T.sum_start_reading) + '</span>' +
        '<span class="cont-title">' + (st.n ? st.n + '. ' : '🔗 ') + esc(st.title) + '</span></span>' +
        '<span class="cont-arrow">→</span></button>';
    }

    let parts = '<div class="ops-parts-grid ops-stagger">';
    d.parts.forEach((p, pi) => {
      const readC = partReadCount(pi);
      let dots = '';
      p.studies.forEach((_, si) => {
        dots += '<i class="' + (isRead(pi, si) ? 'done' : '') + '"></i>';
      });
      parts +=
        '<button class="ops-part-card" data-pi="' + pi + '">' +
        '<span class="ops-part-roman">' + (ROMANS[pi] || pi + 1) + '</span>' +
        '<span class="ops-part-eyebrow">' + esc(T.sum_part) + ' ' + p.n + '</span>' +
        '<span class="ops-part-title">' + esc(p.title) + '</span>' +
        '<span class="ops-part-range">' + esc(p.range) + '</span>' +
        '<span class="ops-part-foot">' +
        '<span>' + p.studies.length + ' ' + esc(T.sum_studies_label) + ' · ' + readC + '/' + p.studies.length + '</span>' +
        '<span class="ops-part-mini-progress">' + dots + '</span>' +
        '</span></button>';
    });
    parts += '</div>';

    app.innerHTML =
      '<div class="ops-view">' +
      '<button class="ops-back" id="opsBack">← ' + esc(T.choose_book) + '</button>' +
      '<div class="ops-hero">' +
      '<p class="ops-hero-tagline">' + esc(d.tagline) + '</p>' +
      '<h2 class="ops-hero-title">' + esc(d.title) + '</h2>' +
      '<p class="ops-hero-sub">' + esc(d.subtitle) + '</p>' +
      '<span class="ops-hero-note">📖 ' + esc(d.source_note) + '</span>' +
      '</div>' +
      langNotice() +
      '<div class="ops-search-wrap">' +
      '<input type="search" class="ops-search-input" id="opsSearch" placeholder="' + esc(T.sum_search_placeholder) + '" autocomplete="off">' +
      '<svg class="ops-search-icon" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2"/><path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>' +
      '<div class="ops-search-results" id="opsSearchResults"></div>' +
      '</div>' +
      continueHtml +
      '<div class="ops-progress-line">' +
      '<div class="ops-progress-track"><span class="ops-progress-fill" style="width:' + pct + '%"></span></div>' +
      '<span class="ops-progress-text">' + read + '/' + total + ' · ' + pct + '%</span>' +
      '</div>' +
      '<div class="ops-chip-row">' +
      '<button class="ops-chip" id="opsIntroChip">📖 ' + esc(T.sum_about_doc) + '</button>' +
      '<button class="ops-chip" id="opsSymChip">🗝️ ' + esc(T.sum_symbol_key) + '</button>' +
      '</div>' +
      parts +
      '<div class="ops-foot-ornament">✦</div>' +
      '</div>';

    document.getElementById('opsBack').addEventListener('click', () => go('#/'));
    document.getElementById('opsIntroChip').addEventListener('click', () => go('#/' + CFG.book + '/intro'));
    document.getElementById('opsSymChip').addEventListener('click', () => go('#/' + CFG.book + '/simbole'));
    const cont = document.getElementById('opsContinue');
    if (cont) cont.addEventListener('click', () => go('#/' + CFG.book + '/deel/' + (+cont.dataset.pi + 1) + '/studie/' + (+cont.dataset.si + 1)));
    app.querySelectorAll('.ops-part-card').forEach((el) => {
      el.addEventListener('click', () => go('#/' + CFG.book + '/deel/' + (+el.dataset.pi + 1)));
    });
    bindSearch();
  }

  function bindSearch() {
    const input = document.getElementById('opsSearch');
    const results = document.getElementById('opsSearchResults');
    if (!input) return;
    let timer = null;
    input.addEventListener('input', () => {
      clearTimeout(timer);
      timer = setTimeout(() => {
        const q = input.value.trim().toLowerCase();
        if (q.length < 2) { results.innerHTML = ''; return; }
        const hits = state.searchIndex.filter((it) => it.text.indexOf(q) !== -1).slice(0, 12);
        if (!hits.length) {
          results.innerHTML = '<p class="ops-empty">' + esc(T.sum_no_results) + '</p>';
          return;
        }
        results.innerHTML = hits.map((h) => {
          const pos = h.raw.toLowerCase().indexOf(q);
          let snip = '';
          if (pos !== -1) {
            const start = Math.max(0, pos - 40);
            snip = (start > 0 ? '…' : '') + h.raw.substr(start, 110) + '…';
            snip = esc(snip).replace(new RegExp('(' + q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'ig'), '<mark>$1</mark>');
          }
          return '<button class="ops-search-hit" data-pi="' + h.pi + '" data-si="' + h.si + '">' +
            '<span class="hit-part">' + esc(h.part) + (h.range ? ' · ' + esc(h.range) : '') + '</span>' +
            '<strong>' + esc(h.title) + '</strong>' +
            (snip ? '<span class="hit-snip">' + snip + '</span>' : '') +
            '</button>';
        }).join('');
        results.querySelectorAll('.ops-search-hit').forEach((el) => {
          el.addEventListener('click', () => go('#/' + CFG.book + '/deel/' + (+el.dataset.pi + 1) + '/studie/' + (+el.dataset.si + 1)));
        });
      }, 160);
    });
  }

  function viewPart(pi) {
    const d = state.data;
    const part = d.parts[pi];
    if (!part) { go('#/' + CFG.book); return; }
    setTitle(part.title);

    let list = '<div class="ops-study-list ops-stagger">';
    part.studies.forEach((s, si) => {
      const read = isRead(pi, si);
      list +=
        '<button class="ops-study-row' + (read ? ' read' : '') + '" data-si="' + si + '">' +
        '<span class="ops-study-num' + (s.kind === 'draad' ? ' draad' : '') + '">' + (s.n != null ? s.n : '🔗') + '</span>' +
        '<span class="ops-study-info">' +
        '<span class="ops-study-title">' + esc(s.title) + '</span>' +
        (s.range ? '<span class="ops-study-range">' + esc(s.range) + '</span>' :
          (s.kind === 'draad' ? '<span class="ops-study-range">' + esc(T.sum_read_thread) + '</span>' : '')) +
        '</span>' +
        '<span class="ops-study-check">✓</span>' +
        '</button>';
    });
    list += '</div>';

    app.innerHTML =
      '<div class="ops-view">' +
      '<button class="ops-back" id="opsBack">← ' + esc(d.title) + '</button>' +
      '<div class="ops-part-head">' +
      '<span class="ops-part-eyebrow">' + esc(T.sum_part) + ' ' + part.n + ' · ' + (ROMANS[pi] || '') + '</span>' +
      '<h2 class="ops-part-head-title">' + esc(part.title) + '</h2>' +
      '<p class="ops-part-head-range">' + esc(part.range) + ' · ' + part.studies.length + ' ' + esc(T.sum_studies_label) + '</p>' +
      '</div>' +
      list +
      '<div class="ops-foot-ornament">✦</div>' +
      '</div>';

    document.getElementById('opsBack').addEventListener('click', () => go('#/' + CFG.book));
    app.querySelectorAll('.ops-study-row').forEach((el) => {
      el.addEventListener('click', () => go('#/' + CFG.book + '/deel/' + (pi + 1) + '/studie/' + (+el.dataset.si + 1)));
    });
  }

  const SECTION_META = {
    verhaal:    { emoji: '📜', key: 'sum_the_story' },
    verklaring: { emoji: '🕊️', key: 'sum_spiritual_meaning' },
    skrif:      { emoji: '📖', key: 'sum_scripture' },
    toepassing: { emoji: '🧭', key: 'sum_application' },
  };

  function viewStudy(pi, si) {
    const d = state.data;
    const part = d.parts[pi];
    const study = part && part.studies[si];
    if (!study) { go('#/' + CFG.book); return; }
    setTitle(study.title);

    try { localStorage.setItem(lastKey(), JSON.stringify({ pi, si })); } catch (e) {}

    let body = '';
    if (study.kind === 'study') {
      (study.sections || []).forEach((sec) => {
        const meta = SECTION_META[sec.k] || { emoji: '•', key: '' };
        body += '<section class="ops-section ' + esc(sec.k) + '">' +
          '<div class="ops-section-head">' +
          '<span class="ops-section-emoji">' + meta.emoji + '</span>' +
          '<span class="ops-section-name">' + esc(T[meta.key] || sec.k) + '</span>' +
          '</div><div class="ops-section-body">';
        if (sec.k === 'skrif') {
          (sec.quotes || []).forEach((q) => { body += renderQuote(q); });
        } else {
          body += renderBlocks(sec.blocks);
        }
        body += '</div></section>';
      });
    } else {
      body += '<section class="ops-section draad">' +
        '<div class="ops-section-head">' +
        '<span class="ops-section-emoji">🔗</span>' +
        '<span class="ops-section-name">' + esc(T.sum_read_thread) + '</span>' +
        '</div><div class="ops-section-body">' + renderBlocks(study.blocks) + '</div></section>';
    }

    // prev/next across the whole book
    const flatIdx = state.flat.findIndex((f) => f.pi === pi && f.si === si);
    const prev = state.flat[flatIdx - 1];
    const next = state.flat[flatIdx + 1];
    let pager = '<div class="ops-pager">';
    if (prev) {
      pager += '<button class="ops-pager-btn prev" data-pi="' + prev.pi + '" data-si="' + prev.si + '">' +
        '<span class="pg-label">← ' + esc(T.sum_prev) + '</span>' +
        '<span class="pg-title">' + esc(prev.study.title) + '</span></button>';
    } else { pager += '<span class="ops-pager-spacer"></span>'; }
    if (next) {
      pager += '<button class="ops-pager-btn next" data-pi="' + next.pi + '" data-si="' + next.si + '">' +
        '<span class="pg-label">' + esc(T.sum_next) + ' →</span>' +
        '<span class="pg-title">' + esc(next.study.title) + '</span></button>';
    } else { pager += '<span class="ops-pager-spacer"></span>'; }
    pager += '</div>';

    const read = isRead(pi, si);

    app.innerHTML =
      '<div class="ops-view ops-reader">' +
      '<div class="ops-reader-progress"><span id="opsReadProg"></span></div>' +
      '<div class="ops-reader-top">' +
      '<button class="ops-back" id="opsBack">←</button>' +
      '<span class="ops-reader-crumb">' + esc(d.title) + ' · ' + esc(T.sum_part) + ' ' + part.n + ' · ' + esc(part.title) + '</span>' +
      '<button class="ops-font-btn" id="opsFontMinus">A−</button>' +
      '<button class="ops-font-btn" id="opsFontPlus">A+</button>' +
      '</div>' +
      '<div class="ops-reader-head">' +
      '<p class="ops-reader-eyebrow">' + (study.n != null ? esc(T.sum_study_label) + ' ' + study.n : '🔗') + '</p>' +
      '<h2 class="ops-reader-title">' + esc(study.title) + '</h2>' +
      (study.range ? '<span class="ops-reader-range">' + esc(study.range) + '</span>' : '') +
      '</div>' +
      langNotice() +
      body +
      '<div class="ops-reader-actions">' +
      '<button class="ops-mark-btn' + (read ? ' marked' : '') + '" id="opsMark">' +
      '<span>' + (read ? '✓' : '○') + '</span><span>' + esc(read ? T.sum_marked_read : T.sum_mark_read) + '</span>' +
      '</button></div>' +
      pager +
      '<div class="ops-foot-ornament">✦</div>' +
      '</div>';

    document.getElementById('opsBack').addEventListener('click', () => go('#/' + CFG.book + '/deel/' + (pi + 1)));
    document.getElementById('opsFontMinus').addEventListener('click', () => bumpFont(-0.06));
    document.getElementById('opsFontPlus').addEventListener('click', () => bumpFont(0.06));

    const markBtn = document.getElementById('opsMark');
    markBtn.addEventListener('click', () => {
      const key = studyKey(pi, si);
      if (state.read.has(key)) { state.read.delete(key); } else { state.read.add(key); }
      saveRead();
      const on = state.read.has(key);
      markBtn.classList.toggle('marked', on);
      markBtn.innerHTML = '<span>' + (on ? '✓' : '○') + '</span><span>' + esc(on ? T.sum_marked_read : T.sum_mark_read) + '</span>';
    });

    app.querySelectorAll('.ops-pager-btn').forEach((el) => {
      el.addEventListener('click', () => {
        // moving forward marks the current study as read
        if (el.classList.contains('next') && !state.read.has(studyKey(pi, si))) {
          state.read.add(studyKey(pi, si));
          saveRead();
        }
        go('#/' + CFG.book + '/deel/' + (+el.dataset.pi + 1) + '/studie/' + (+el.dataset.si + 1));
      });
    });

    bindReadProgress();
  }

  function bindReadProgress() {
    const bar = document.getElementById('opsReadProg');
    if (!bar) return;
    const onScroll = () => {
      if (!document.getElementById('opsReadProg')) {
        window.removeEventListener('scroll', onScroll);
        return;
      }
      const h = document.documentElement;
      const max = h.scrollHeight - h.clientHeight;
      const pct = max > 0 ? (h.scrollTop / max) * 100 : 0;
      bar.style.width = pct + '%';
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  function viewIntro() {
    const d = state.data;
    setTitle(d.intro.title);
    app.innerHTML =
      '<div class="ops-view ops-reader">' +
      '<button class="ops-back" id="opsBack">← ' + esc(d.title) + '</button>' +
      '<div class="ops-reader-head">' +
      '<p class="ops-reader-eyebrow">📖</p>' +
      '<h2 class="ops-reader-title">' + esc(d.intro.title) + '</h2>' +
      '</div>' +
      langNotice() +
      '<div class="ops-prose">' + renderBlocks(d.intro.blocks) + '</div>' +
      '<div class="ops-foot-ornament">✦</div>' +
      '</div>';
    document.getElementById('opsBack').addEventListener('click', () => go('#/' + CFG.book));
  }

  function viewSymbols() {
    const d = state.data;
    setTitle(d.symbols.title);
    let grid = '<div class="ops-symbols-grid ops-stagger">';
    (d.symbols.items || []).forEach((it) => {
      grid += '<div class="ops-symbol-card">' +
        '<span class="ops-symbol-s">' + esc(it.s) + '</span>' +
        '<span class="ops-symbol-m">' + esc(it.m) + '</span></div>';
    });
    grid += '</div>';
    app.innerHTML =
      '<div class="ops-view">' +
      '<button class="ops-back" id="opsBack">← ' + esc(d.title) + '</button>' +
      '<div class="ops-reader-head">' +
      '<p class="ops-reader-eyebrow">🗝️</p>' +
      '<h2 class="ops-reader-title">' + esc(d.symbols.title) + '</h2>' +
      '</div>' +
      langNotice() +
      grid +
      (d.symbols.note ? '<div class="ops-symbols-note">' + fmt(d.symbols.note) + '</div>' : '') +
      '<div class="ops-foot-ornament">✦</div>' +
      '</div>';
    document.getElementById('opsBack').addEventListener('click', () => go('#/' + CFG.book));
  }

  // ---------- router ----------
  function route() {
    const hash = (location.hash || '#/').replace(/^#\/?/, '');
    const seg = hash.split('/').filter(Boolean);
    scrollTop();

    if (!seg.length) { viewBooks(); return; }
    if (seg[0] !== CFG.book || !state.data) { viewBooks(); return; }

    if (seg.length === 1) { viewOverview(); return; }
    if (seg[1] === 'intro') { viewIntro(); return; }
    if (seg[1] === 'simbole') { viewSymbols(); return; }
    if (seg[1] === 'deel') {
      const pi = (parseInt(seg[2], 10) || 1) - 1;
      if (seg[3] === 'studie') {
        const si = (parseInt(seg[4], 10) || 1) - 1;
        viewStudy(pi, si);
        return;
      }
      viewPart(pi);
      return;
    }
    viewOverview();
  }

  // ---------- boot ----------
  loadRead();
  loadFont();

  fetch(CFG.dataUrl)
    .then((r) => {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    })
    .then((data) => {
      state.data = data;
      buildIndexes();
      window.addEventListener('hashchange', route);
      route();
    })
    .catch((err) => {
      app.innerHTML = '<p class="ops-empty">⚠️ ' + esc(String(err)) + '</p>';
    });
})();
