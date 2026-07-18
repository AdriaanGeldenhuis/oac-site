/* =====================================================================
   Bybel Opsomming — client-side reader (multi-book)
   Views: boeke → boek-oorsig (dele/blokke) → deel → studie
   ===================================================================== */
(function () {
  'use strict';

  const CFG = window.OPSOMMING || {};
  const T = CFG.t || {};
  const BOOKS_DATA = CFG.booksData || {};
  const AVAILABLE = Object.keys(BOOKS_DATA);
  const app = document.getElementById('opsApp');

  const ROMANS = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

  const state = {
    books: {},       // id → data
    flat: {},        // id → [{pi, si, study}]
    searchIndex: {}, // id → [...]
    read: {},        // id → Set
    tab: 'ot',
  };

  // ---------- storage ----------
  const readKey = (bid) => 'ops_read_' + bid;
  const lastKey = (bid) => 'ops_last_' + bid;

  function loadRead(bid) {
    try {
      const raw = localStorage.getItem(readKey(bid));
      state.read[bid] = new Set(raw ? JSON.parse(raw) : []);
    } catch (e) { state.read[bid] = new Set(); }
  }

  function saveRead(bid) {
    try { localStorage.setItem(readKey(bid), JSON.stringify([...state.read[bid]])); } catch (e) {}
  }

  function loadFont() {
    const v = parseFloat(localStorage.getItem('ops_font') || '1.06');
    if (v >= 0.85 && v <= 1.5) {
      document.documentElement.style.setProperty('--read-size', v + 'rem');
    }
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
  function isRead(bid, pi, si) { return state.read[bid] && state.read[bid].has(studyKey(pi, si)); }

  function partReadCount(bid, pi) {
    const part = state.books[bid].parts[pi];
    let c = 0;
    part.studies.forEach((_, si) => { if (isRead(bid, pi, si)) c++; });
    return c;
  }

  function totalStudies(bid) { return (state.flat[bid] || []).length; }

  function totalRead(bid) {
    const d = state.books[bid];
    let c = 0;
    d.parts.forEach((p, pi) => p.studies.forEach((_, si) => { if (isRead(bid, pi, si)) c++; }));
    return c;
  }

  // Content-specific part label ("Blok") wins when the UI language matches
  // the content language; otherwise use the generic translated label.
  function partLabel(d) {
    const bd = BOOKS_DATA[d.book] || {};
    if (d.part_label && bd.dataLang === CFG.lang) return d.part_label;
    return T.sum_part;
  }

  function partsLabel(d) {
    const bd = BOOKS_DATA[d.book] || {};
    if (d.part_label_plural && bd.dataLang === CFG.lang) return d.part_label_plural;
    return T.sum_parts;
  }

  function bookName(bid) {
    const all = (CFG.books.ot || []).concat(CFG.books.nt || []);
    const hit = all.filter((b) => b.id === bid)[0];
    return hit ? hit.name : bid;
  }

  function setTitle(sub) {
    document.title = (sub ? sub + ' · ' : '') + (T.bible_summary || 'Bybel Opsomming');
  }

  function scrollTop() { window.scrollTo(0, 0); }

  function go(hash) { location.hash = hash; }

  // ---------- data ----------
  const bookPromises = {};

  function loadBook(bid) {
    if (bookPromises[bid]) return bookPromises[bid];
    bookPromises[bid] = fetch(BOOKS_DATA[bid].url)
      .then((r) => {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      })
      .then((data) => {
        data.book = bid;
        state.books[bid] = data;
        loadRead(bid);
        buildIndexes(bid);
        return data;
      });
    return bookPromises[bid];
  }

  function buildIndexes(bid) {
    const d = state.books[bid];
    const flat = [];
    const idx = [];
    d.parts.forEach((part, pi) => {
      part.studies.forEach((study, si) => {
        flat.push({ pi, si, study });
        let text = '';
        if (study.intro) study.intro.forEach((b) => { text += ' ' + blockText(b); });
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
        idx.push({
          pi, si,
          title: study.title,
          range: study.range || '',
          part: part.title,
          text: (study.title + ' ' + (study.range || '') + ' ' + text).toLowerCase(),
          raw: text,
        });
      });
    });
    state.flat[bid] = flat;
    state.searchIndex[bid] = idx;
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

  function langNotice(bid) {
    const bd = BOOKS_DATA[bid] || {};
    if (bd.dataLang && bd.dataLang !== CFG.lang) {
      return '<div class="ops-notice"><span>🌍</span><span>' + esc(T.sum_af_only) + '</span></div>';
    }
    return '';
  }

  // ---------- views ----------
  function viewBooks() {
    setTitle('');
    const totalBooks = CFG.books.ot.length + CFG.books.nt.length;

    const tabs =
      '<div class="ops-tabs">' +
      '<button class="ops-tab' + (state.tab === 'ot' ? ' active' : '') + '" data-tab="ot">' + esc(T.old_testament) + '</button>' +
      '<button class="ops-tab' + (state.tab === 'nt' ? ' active' : '') + '" data-tab="nt">' + esc(T.new_testament) + '</button>' +
      '</div>';

    const list = CFG.books[state.tab] || [];
    let grid = '<div class="ops-books-grid ops-stagger">';
    list.forEach((b) => {
      const d = state.books[b.id];
      if (d) {
        const total = totalStudies(b.id);
        const read = totalRead(b.id);
        const pct = total ? Math.round((read / total) * 100) : 0;
        grid += '<button class="ops-book-card available" data-book="' + esc(b.id) + '">' +
          '<span class="ops-book-badge">' + esc(T.sum_available) + '</span>' +
          '<span class="ops-book-name">' + esc(b.name) + '</span>' +
          '<span class="ops-book-state">' + esc(partsLabel(d)) + ': ' + d.parts.length + ' · ' + esc(T.sum_studies_label) + ': ' + total + '</span>' +
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
      tabs +
      '<p class="ops-books-meta">' + esc(T.sum_books_available) + ': ' + AVAILABLE.length + ' / ' + totalBooks + '</p>' +
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

  function viewOverview(bid) {
    const d = state.books[bid];
    setTitle(d.title);
    const total = totalStudies(bid);
    const read = totalRead(bid);
    const pct = total ? Math.round((read / total) * 100) : 0;

    let continueHtml = '';
    let last = null;
    try { last = JSON.parse(localStorage.getItem(lastKey(bid)) || 'null'); } catch (e) {}
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
      const readC = partReadCount(bid, pi);
      let dots = '';
      p.studies.forEach((_, si) => {
        dots += '<i class="' + (isRead(bid, pi, si) ? 'done' : '') + '"></i>';
      });
      parts +=
        '<button class="ops-part-card" data-pi="' + pi + '">' +
        '<span class="ops-part-roman">' + (ROMANS[pi] || pi + 1) + '</span>' +
        '<span class="ops-part-eyebrow">' + esc(partLabel(d)) + ' ' + p.n + '</span>' +
        '<span class="ops-part-title">' + esc(p.title) + '</span>' +
        '<span class="ops-part-range">' + esc(p.range) + '</span>' +
        '<span class="ops-part-foot">' +
        '<span>' + p.studies.length + ' ' + esc(T.sum_studies_label) + ' · ' + readC + '/' + p.studies.length + '</span>' +
        '<span class="ops-part-mini-progress">' + dots + '</span>' +
        '</span></button>';
    });
    parts += '</div>';

    const hasIntro = d.intro && d.intro.blocks && d.intro.blocks.length;
    const hasSymbols = d.symbols && d.symbols.items && d.symbols.items.length;
    let chips = '';
    if (hasIntro || hasSymbols) {
      chips = '<div class="ops-chip-row">' +
        (hasIntro ? '<button class="ops-chip" id="opsIntroChip">📖 ' + esc(T.sum_about_doc) + '</button>' : '') +
        (hasSymbols ? '<button class="ops-chip" id="opsSymChip">🗝️ ' + esc(T.sum_symbol_key) + '</button>' : '') +
        '</div>';
    }

    app.innerHTML =
      '<div class="ops-view">' +
      '<button class="ops-back" id="opsBack">← ' + esc(T.choose_book) + '</button>' +
      '<div class="ops-hero">' +
      (d.tagline ? '<p class="ops-hero-tagline">' + esc(d.tagline) + '</p>' : '') +
      '<h2 class="ops-hero-title">' + esc(d.title) + '</h2>' +
      (d.subtitle ? '<p class="ops-hero-sub">' + esc(d.subtitle) + '</p>' : '') +
      (d.source_note ? '<span class="ops-hero-note">📖 ' + esc(d.source_note) + '</span>' : '') +
      '</div>' +
      langNotice(bid) +
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
      chips +
      parts +
      '<div class="ops-foot-ornament">✦</div>' +
      '</div>';

    document.getElementById('opsBack').addEventListener('click', () => go('#/'));
    const introChip = document.getElementById('opsIntroChip');
    if (introChip) introChip.addEventListener('click', () => go('#/' + bid + '/intro'));
    const symChip = document.getElementById('opsSymChip');
    if (symChip) symChip.addEventListener('click', () => go('#/' + bid + '/simbole'));
    const cont = document.getElementById('opsContinue');
    if (cont) cont.addEventListener('click', () => go('#/' + bid + '/deel/' + (+cont.dataset.pi + 1) + '/studie/' + (+cont.dataset.si + 1)));
    app.querySelectorAll('.ops-part-card').forEach((el) => {
      el.addEventListener('click', () => go('#/' + bid + '/deel/' + (+el.dataset.pi + 1)));
    });
    bindSearch(bid);
  }

  function bindSearch(bid) {
    const input = document.getElementById('opsSearch');
    const results = document.getElementById('opsSearchResults');
    if (!input) return;
    let timer = null;
    input.addEventListener('input', () => {
      clearTimeout(timer);
      timer = setTimeout(() => {
        const q = input.value.trim().toLowerCase();
        if (q.length < 2) { results.innerHTML = ''; return; }
        const hits = (state.searchIndex[bid] || []).filter((it) => it.text.indexOf(q) !== -1).slice(0, 12);
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
          el.addEventListener('click', () => go('#/' + bid + '/deel/' + (+el.dataset.pi + 1) + '/studie/' + (+el.dataset.si + 1)));
        });
      }, 160);
    });
  }

  function viewPart(bid, pi) {
    const d = state.books[bid];
    const part = d.parts[pi];
    if (!part) { go('#/' + bid); return; }
    setTitle(part.title);

    const intro = (part.intro && part.intro.length)
      ? '<div class="ops-part-intro">' + renderBlocks(part.intro) + '</div>'
      : '';

    let list = '<div class="ops-study-list ops-stagger">';
    part.studies.forEach((s, si) => {
      const read = isRead(bid, pi, si);
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
      '<span class="ops-part-eyebrow">' + esc(partLabel(d)) + ' ' + part.n + ' · ' + (ROMANS[pi] || '') + '</span>' +
      '<h2 class="ops-part-head-title">' + esc(part.title) + '</h2>' +
      '<p class="ops-part-head-range">' + esc(part.range) + ' · ' + part.studies.length + ' ' + esc(T.sum_studies_label) + '</p>' +
      '</div>' +
      intro +
      list +
      '<div class="ops-foot-ornament">✦</div>' +
      '</div>';

    document.getElementById('opsBack').addEventListener('click', () => go('#/' + bid));
    app.querySelectorAll('.ops-study-row').forEach((el) => {
      el.addEventListener('click', () => go('#/' + bid + '/deel/' + (pi + 1) + '/studie/' + (+el.dataset.si + 1)));
    });
  }

  const SECTION_META = {
    verhaal:    { emoji: '📜', key: 'sum_the_story' },
    verklaring: { emoji: '🕊️', key: 'sum_spiritual_meaning' },
    skrif:      { emoji: '📖', key: 'sum_scripture' },
    toepassing: { emoji: '🧭', key: 'sum_application' },
  };

  function viewStudy(bid, pi, si) {
    const d = state.books[bid];
    const part = d.parts[pi];
    const study = part && part.studies[si];
    if (!study) { go('#/' + bid); return; }
    setTitle(study.title);

    try { localStorage.setItem(lastKey(bid), JSON.stringify({ pi, si })); } catch (e) {}

    let body = '';
    if (study.intro && study.intro.length) {
      body += '<div class="ops-study-intro">' + renderBlocks(study.intro) + '</div>';
    }
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
    const flat = state.flat[bid];
    const flatIdx = flat.findIndex((f) => f.pi === pi && f.si === si);
    const prev = flat[flatIdx - 1];
    const next = flat[flatIdx + 1];
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

    const read = isRead(bid, pi, si);

    app.innerHTML =
      '<div class="ops-view ops-reader">' +
      '<div class="ops-reader-progress"><span id="opsReadProg"></span></div>' +
      '<div class="ops-reader-top">' +
      '<button class="ops-back" id="opsBack">←</button>' +
      '<span class="ops-reader-crumb">' + esc(d.title) + ' · ' + esc(partLabel(d)) + ' ' + part.n + ' · ' + esc(part.title) + '</span>' +
      '<button class="ops-font-btn" id="opsFontMinus">A−</button>' +
      '<button class="ops-font-btn" id="opsFontPlus">A+</button>' +
      '</div>' +
      '<div class="ops-reader-head">' +
      '<p class="ops-reader-eyebrow">' + (study.n != null ? esc(T.sum_study_label) + ' ' + study.n : '🔗') + '</p>' +
      '<h2 class="ops-reader-title">' + esc(study.title) + '</h2>' +
      (study.range ? '<span class="ops-reader-range">' + esc(study.range) + '</span>' : '') +
      '</div>' +
      langNotice(bid) +
      body +
      '<div class="ops-reader-actions">' +
      '<button class="ops-mark-btn' + (read ? ' marked' : '') + '" id="opsMark">' +
      '<span>' + (read ? '✓' : '○') + '</span><span>' + esc(read ? T.sum_marked_read : T.sum_mark_read) + '</span>' +
      '</button></div>' +
      pager +
      '<div class="ops-foot-ornament">✦</div>' +
      '</div>';

    document.getElementById('opsBack').addEventListener('click', () => go('#/' + bid + '/deel/' + (pi + 1)));
    document.getElementById('opsFontMinus').addEventListener('click', () => bumpFont(-0.06));
    document.getElementById('opsFontPlus').addEventListener('click', () => bumpFont(0.06));

    const markBtn = document.getElementById('opsMark');
    markBtn.addEventListener('click', () => {
      const key = studyKey(pi, si);
      if (state.read[bid].has(key)) { state.read[bid].delete(key); } else { state.read[bid].add(key); }
      saveRead(bid);
      const on = state.read[bid].has(key);
      markBtn.classList.toggle('marked', on);
      markBtn.innerHTML = '<span>' + (on ? '✓' : '○') + '</span><span>' + esc(on ? T.sum_marked_read : T.sum_mark_read) + '</span>';
    });

    app.querySelectorAll('.ops-pager-btn').forEach((el) => {
      el.addEventListener('click', () => {
        // moving forward marks the current study as read
        if (el.classList.contains('next') && !state.read[bid].has(studyKey(pi, si))) {
          state.read[bid].add(studyKey(pi, si));
          saveRead(bid);
        }
        go('#/' + bid + '/deel/' + (+el.dataset.pi + 1) + '/studie/' + (+el.dataset.si + 1));
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

  function viewIntro(bid) {
    const d = state.books[bid];
    if (!d.intro || !d.intro.blocks || !d.intro.blocks.length) { go('#/' + bid); return; }
    setTitle(d.intro.title);
    app.innerHTML =
      '<div class="ops-view ops-reader">' +
      '<button class="ops-back" id="opsBack">← ' + esc(d.title) + '</button>' +
      '<div class="ops-reader-head">' +
      '<p class="ops-reader-eyebrow">📖</p>' +
      '<h2 class="ops-reader-title">' + esc(d.intro.title || T.sum_about_doc) + '</h2>' +
      '</div>' +
      langNotice(bid) +
      '<div class="ops-prose">' + renderBlocks(d.intro.blocks) + '</div>' +
      '<div class="ops-foot-ornament">✦</div>' +
      '</div>';
    document.getElementById('opsBack').addEventListener('click', () => go('#/' + bid));
  }

  function viewSymbols(bid) {
    const d = state.books[bid];
    if (!d.symbols || !d.symbols.items || !d.symbols.items.length) { go('#/' + bid); return; }
    setTitle(d.symbols.title);
    let grid = '<div class="ops-symbols-grid ops-stagger">';
    d.symbols.items.forEach((it) => {
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
      '<h2 class="ops-reader-title">' + esc(d.symbols.title || T.sum_symbol_key) + '</h2>' +
      '</div>' +
      langNotice(bid) +
      (d.symbols.note ? '<div class="ops-symbols-note">' + fmt(d.symbols.note) + '</div>' : '') +
      grid +
      '<div class="ops-foot-ornament">✦</div>' +
      '</div>';
    document.getElementById('opsBack').addEventListener('click', () => go('#/' + bid));
  }

  // ---------- router ----------
  function route() {
    const hash = (location.hash || '#/').replace(/^#\/?/, '');
    const seg = hash.split('/').filter(Boolean);
    scrollTop();

    if (!seg.length) { viewBooks(); return; }
    const bid = seg[0];
    if (AVAILABLE.indexOf(bid) === -1 || !state.books[bid]) { viewBooks(); return; }

    if (seg.length === 1) { viewOverview(bid); return; }
    if (seg[1] === 'intro') { viewIntro(bid); return; }
    if (seg[1] === 'simbole') { viewSymbols(bid); return; }
    if (seg[1] === 'deel') {
      const pi = (parseInt(seg[2], 10) || 1) - 1;
      if (seg[3] === 'studie') {
        const si = (parseInt(seg[4], 10) || 1) - 1;
        viewStudy(bid, pi, si);
        return;
      }
      viewPart(bid, pi);
      return;
    }
    viewOverview(bid);
  }

  // ---------- boot ----------
  loadFont();

  Promise.all(AVAILABLE.map((bid) => loadBook(bid).catch((e) => {
    console.error('opsomming: kon nie ' + bid + ' laai nie', e);
    return null;
  }))).then((results) => {
    window.addEventListener('hashchange', route);
    if (results.every((r) => r === null) && AVAILABLE.length) {
      app.innerHTML = '<p class="ops-empty">⚠️ ' + esc(T.sum_no_results) + '</p>';
      return;
    }
    route();
  });
})();
