/**
 * Sing Emmanuel — the hymn page.
 *
 * The hymn itself is drawn by singemmanuel.php. This file opens the number
 * picker, and (for se-player.js) the organ player, as layers: a sheet on
 * phones, a card on desktop. An open layer has its own history entry, so
 * the phone's Back button, the app's and the floating back button close it
 * instead of leaving the page. Escape and a tap on the scrim close it too.
 * The text size of the hymn is remembered on the device.
 *
 * Offline in the app (sw.js) this page is a saved copy of one hymn page,
 * shown for every ?song=. When the address asks for another hymn or
 * language than the page was made for, the hymn is drawn here from the
 * hymn book JSON, the same way singemmanuel.php draws it.
 */
(function () {
  'use strict';

  const $ = (s, root) => (root || document).querySelector(s);
  const SONG_URL = '/singemmanuel/singemmanuel.php?song=';
  const LANGS = ['af', 'en', 'zu', 'xh', 'pt', 'st'];

  // A ?lang= in the address goes along to the next hymn (the offline copy
  // draws hymns in that language; online it is the language already chosen)
  const query = new URLSearchParams(window.location.search);
  const urlLang = LANGS.indexOf(query.get('lang') || '') !== -1 ? query.get('lang') : '';
  const songUrl = (n) => SONG_URL + encodeURIComponent(n) + (urlLang ? '&lang=' + urlLang : '');

  // ===== LAYERS =====
  const stack = [];
  let historyArmed = false;
  let ignorePop = false;

  function armHistory() {
    if (historyArmed) return;
    try {
      history.pushState({ se: 'layer' }, '', window.location.href);
      historyArmed = true;
    } catch (e) { /* old WebView: Back just leaves the page */ }
  }

  function disarmHistory() {
    if (!historyArmed) return;
    historyArmed = false;
    if (history.state && history.state.se === 'layer') {
      ignorePop = true;
      history.back();
    }
  }

  window.addEventListener('popstate', () => {
    if (ignorePop) { ignorePop = false; return; }
    if (!historyArmed) return;
    historyArmed = false;
    const top = stack[stack.length - 1];
    if (top) closeLayer(top.el, { fromHistory: true });
    if (stack.length) armHistory();
  });

  // Focus rings only for keyboard users: after a tap, giving focus back to
  // "Soek Lied" must not light it up
  let usingKeys = false;
  document.addEventListener('keydown', () => { usingKeys = true; }, true);
  document.addEventListener('pointerdown', () => { usingKeys = false; }, true);

  function focusQuietly(node) {
    if (!node || !node.focus) return;
    try { node.focus({ preventScroll: true, focusVisible: usingKeys }); } catch (e) { node.focus(); }
  }

  /** Show a [hidden] .se-layer; opts.onClose runs however it is closed */
  function openLayer(layer, opts) {
    if (!layer || stack.some((s) => s.el === layer)) return;
    opts = opts || {};
    const entry = { el: layer, onClose: opts.onClose || null, prevFocus: document.activeElement };
    stack.push(entry);
    layer.classList.remove('is-closing');
    layer.hidden = false;
    document.body.classList.add('se-locked');
    armHistory();
    focusQuietly(opts.focus || layer.querySelector('.se-sheet'));
  }

  function closeLayer(layer, how) {
    const i = stack.findIndex((s) => s.el === layer);
    if (i === -1) return;
    const entry = stack.splice(i, 1)[0];
    layer.classList.add('is-closing');
    setTimeout(() => {
      // Opened again meanwhile: leave it be
      if (stack.some((s) => s.el === layer)) return;
      layer.hidden = true;
      layer.classList.remove('is-closing');
      const sheet = layer.querySelector('.se-sheet');
      if (sheet) sheet.style.transform = '';
    }, 180);
    if (!stack.length) {
      document.body.classList.remove('se-locked');
      // After a Back the popstate handler has already let go of the entry
      if (!(how && how.fromHistory)) disarmHistory();
    }
    if (entry.onClose) entry.onClose();
    if (entry.prevFocus && document.body.contains(entry.prevFocus)) focusQuietly(entry.prevFocus);
  }

  /** Leave the page: replace our layer entry, so Back returns to this hymn in one step */
  function go(url) {
    if (historyArmed && history.state && history.state.se === 'layer') {
      window.location.replace(url);
    } else {
      window.location.href = url;
    }
  }

  document.querySelectorAll('.se-layer').forEach((layer) => {
    // A tap on the scrim closes, but only when it also started there: a tap
    // whose layout changes under the finger (the phone's keyboard opening)
    // or a drag out of the sheet must never close it
    let downOnScrim = false;
    layer.addEventListener('pointerdown', (e) => { downOnScrim = e.target === layer; });
    layer.addEventListener('click', (e) => {
      if (e.target === layer) {
        if (downOnScrim) closeLayer(layer);
      } else if (e.target.closest('[data-se-close]')) {
        closeLayer(layer);
      }
      downOnScrim = false;
    });

    // Phones: pull the sheet down by its handle to close it
    const sheet = layer.querySelector('.se-sheet');
    const grip = layer.querySelector('.se-sheet-grip');
    if (!sheet || !grip) return;
    let startY = 0;
    let dy = 0;
    grip.addEventListener('touchstart', (e) => {
      startY = e.touches[0].clientY;
      dy = 0;
      sheet.style.transition = 'none'; // follow the finger without lag
    }, { passive: true });
    grip.addEventListener('touchmove', (e) => {
      dy = Math.max(0, e.touches[0].clientY - startY);
      sheet.style.transform = 'translateY(' + dy + 'px)';
    }, { passive: true });
    const release = () => {
      sheet.style.transition = '';
      if (dy > 90) {
        closeLayer(layer);
      } else {
        sheet.style.transform = ''; // springs back
      }
      dy = 0;
    };
    grip.addEventListener('touchend', release);
    grip.addEventListener('touchcancel', release);
  });

  // Back to this page from the browser's back-forward cache (after opening
  // a hymn from the picker): close what was open, without touching history
  window.addEventListener('pageshow', (e) => {
    if (!e.persisted) return;
    historyArmed = false;
    ignorePop = false;
    while (stack.length) closeLayer(stack[stack.length - 1].el, { fromHistory: true });
  });

  document.addEventListener('keydown', (e) => {
    const top = stack[stack.length - 1];
    if (!top) return;
    if (e.key === 'Escape') {
      e.preventDefault();
      closeLayer(top.el);
      return;
    }
    // Tab stays inside the open sheet
    if (e.key === 'Tab') {
      const items = Array.from(top.el.querySelectorAll('button, input, a[href], [tabindex]:not([tabindex="-1"])'))
        .filter((n) => !n.disabled && n.offsetParent !== null);
      if (!items.length) return;
      const first = items[0];
      const last = items[items.length - 1];
      if (e.shiftKey && (document.activeElement === first || !top.el.contains(document.activeElement))) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    }
  });

  // For se-player.js
  window.SE = { openLayer: openLayer, closeLayer: closeLayer };

  // ===== SONG PICKER =====
  const picker = $('#songPickerOverlay');
  const openBtn = $('#openSongPicker');
  const grid = $('#songPickerGrid');
  const numberInput = $('#songNumberInput');
  const numberGo = $('#songNumberGo');
  const msg = $('#songNumberMsg');
  const canHover = window.matchMedia('(hover: hover)').matches;

  /** Show only the numbers that start with what was typed; returns how many */
  function filterGrid(value) {
    if (!grid) return 0;
    let shown = 0;
    grid.querySelectorAll('.se-song-btn').forEach((btn) => {
      const num = btn.getAttribute('data-song') || '';
      const show = value === '' || num.indexOf(value) === 0;
      btn.hidden = !show;
      btn.classList.toggle('is-match', value !== '' && num === value);
      if (show) shown++;
    });
    return shown;
  }

  function say(text) {
    if (msg) msg.textContent = text || '';
  }

  function openPicker() {
    if (!picker) return;
    if (numberInput) {
      numberInput.value = '';
      numberInput.classList.remove('se-input-error');
    }
    filterGrid('');
    say('');
    // Only focus the field with a mouse/trackpad, so the phone's keyboard
    // does not cover the numbers
    openLayer(picker, { focus: canHover ? numberInput : null });
    // The hymn on screen in the middle of the grid (scrolls the sheet only, never the page)
    const body = $('.se-sheet-body', picker);
    const current = grid ? $('.se-song-btn.is-current', grid) : null;
    if (body && current) body.scrollTop = Math.max(0, current.offsetTop - (body.clientHeight - current.offsetHeight) / 2);
  }

  function goToTyped() {
    if (!numberInput || !grid) return;
    const value = numberInput.value.trim().replace(/^0+(?=\d)/, '');
    if (!value) return;
    if (grid.querySelector('.se-song-btn[data-song="' + value + '"]')) {
      go(songUrl(value));
      return;
    }
    say(msg ? msg.getAttribute('data-not-found') : '');
    numberInput.classList.remove('se-input-error');
    void numberInput.offsetWidth; // restart the shake
    numberInput.classList.add('se-input-error');
  }

  if (openBtn) {
    openBtn.addEventListener('click', (e) => {
      e.preventDefault();
      openPicker();
    });
  }

  if (grid) {
    grid.addEventListener('click', (e) => {
      const btn = e.target.closest('.se-song-btn');
      if (!btn) return;
      const n = btn.getAttribute('data-song');
      if (!n) return;
      if (btn.classList.contains('is-current')) {
        closeLayer(picker);
        return;
      }
      go(songUrl(n));
    });
  }

  if (numberInput) {
    numberInput.addEventListener('input', () => {
      const digits = numberInput.value.replace(/\D/g, '');
      if (numberInput.value !== digits) numberInput.value = digits;
      numberInput.classList.remove('se-input-error');
      const shown = filterGrid(digits.replace(/^0+(?=\d)/, ''));
      // Say it at once when no hymn starts with these digits
      say(shown === 0 && msg ? msg.getAttribute('data-not-found') : '');
      const body = $('.se-sheet-body', picker);
      if (body) body.scrollTop = 0;
    });
    numberInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        goToTyped();
      }
    });
  }

  if (numberGo) {
    numberGo.addEventListener('click', (e) => {
      e.preventDefault();
      goToTyped();
    });
  }

  // ===== TEXT SIZE (0-4, remembered on this device) =====
  const song = $('#songContent');
  const sizeDown = $('#seSizeDown');
  const sizeUp = $('#seSizeUp');
  const SIZE_KEY = 'se_text_size';

  function getSize() {
    const n = parseInt(song ? song.getAttribute('data-size') : '2', 10);
    return isNaN(n) ? 2 : Math.min(4, Math.max(0, n));
  }

  function setSize(n) {
    if (!song) return;
    n = Math.min(4, Math.max(0, n));
    song.setAttribute('data-size', String(n));
    try { localStorage.setItem(SIZE_KEY, String(n)); } catch (e) { /* private mode */ }
    if (sizeDown) sizeDown.disabled = n <= 0;
    if (sizeUp) sizeUp.disabled = n >= 4;
  }

  if (song && sizeDown && sizeUp) {
    sizeDown.addEventListener('click', () => setSize(getSize() - 1));
    sizeUp.addEventListener('click', () => setSize(getSize() + 1));
    sizeDown.disabled = getSize() <= 0;
    sizeUp.disabled = getSize() >= 4;
  }

  // ===== SONG LANGUAGE: keep the reading position across the switch =====
  const SCROLL_KEY = 'se_scroll';
  document.querySelectorAll('.se-seg-btn').forEach((link) => {
    link.addEventListener('click', (e) => {
      // The hymns' language is already this one. (For isiZulu, isiXhosa,
      // Português and Sesotho readers that is Afrikaans: following the link
      // would switch the whole app to Afrikaans for nothing.)
      if (link.classList.contains('is-on')) {
        e.preventDefault();
        return;
      }
      try { sessionStorage.setItem(SCROLL_KEY, String(window.pageYOffset)); } catch (e2) { /* private mode */ }
    });
  });

  try {
    const y = sessionStorage.getItem(SCROLL_KEY);
    if (y !== null) {
      sessionStorage.removeItem(SCROLL_KEY);
      window.addEventListener('load', () => window.scrollTo(0, parseInt(y, 10) || 0));
    }
  } catch (e) { /* private mode */ }

  // ===== OFFLINE COPY: DRAW THE HYMN THE ADDRESS ASKS FOR =====
  const SE_DATA = (() => {
    try { return JSON.parse($('#seData').textContent) || {}; } catch (e) { return {}; }
  })();
  const T = SE_DATA.i18n || {};
  const SVG_NS = 'http://www.w3.org/2000/svg';

  // PHP's trim()/rtrim() (ASCII whitespace only), so both sides cut the same
  const phpTrim = (str) => str.replace(/^[ \t\n\r\0\x0B]+|[ \t\n\r\0\x0B]+$/g, '');
  const phpRtrim = (str, chars) => str.replace(chars ? new RegExp('[' + chars + ']+$', 'u') : /[ \t\n\r\0\x0B]+$/, '');

  /** Port of se_blocks() in singemmanuel.php: numbered verses, choruses, plain stanzas */
  function seBlocks(verses) {
    const blocks = [];
    let inChorus = false;
    let newChorus = false;
    (Array.isArray(verses) ? verses : []).forEach((raw) => {
      if (typeof raw !== 'string') return;
      const v = phpTrim(raw);
      if (v === '') return;
      if (/^(koor|chorus|refrein|refrain)[:.]?$/iu.test(v)) {
        inChorus = true;
        newChorus = true;
        return;
      }
      const m = /^(\d{1,2})[.)]?\s*(?=\D)(.+)$/su.exec(v);
      if (m && parseInt(m[1], 10) >= 1 && parseInt(m[1], 10) <= 20) {
        inChorus = false;
        blocks.push({ type: 'verse', num: String(parseInt(m[1], 10)), text: phpTrim(m[2]) });
      } else if (inChorus) {
        if (newChorus) {
          blocks.push({ type: 'chorus', lines: [v] });
          newChorus = false;
        } else {
          blocks[blocks.length - 1].lines.push(v);
        }
      } else {
        blocks.push({ type: 'stanza', text: v });
      }
    });
    return blocks;
  }

  /** Port of se_first_line(): the hymn's opening words */
  function seFirstLine(blocks) {
    if (!blocks.length) return '';
    const first = blocks[0].text !== undefined ? blocks[0].text : ((blocks[0].lines || [])[0] || '');
    const text = first.replace(/\s+/gu, ' ');
    const m = /^(.{12,70}?[!?.;:])(\s|$)/u.exec(text);
    if (m) return phpRtrim(m[1], '.;:,');
    const chars = Array.from(text);
    if (chars.length <= 60) return phpRtrim(text, '.;:,');
    const cut = chars.slice(0, 60);
    const comma = cut.lastIndexOf(',');
    if (comma !== -1 && comma >= 20) return cut.slice(0, comma).join('');
    return phpRtrim(cut.join('')) + '…';
  }

  function el(tag, cls, text) {
    const node = document.createElement(tag);
    if (cls) node.className = cls;
    if (text !== undefined) node.textContent = text;
    return node;
  }

  function chevron(d) {
    const svg = document.createElementNS(SVG_NS, 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('fill', 'none');
    svg.setAttribute('stroke', 'currentColor');
    svg.setAttribute('stroke-width', '2.2');
    svg.setAttribute('stroke-linecap', 'round');
    svg.setAttribute('stroke-linejoin', 'round');
    svg.setAttribute('aria-hidden', 'true');
    const path = document.createElementNS(SVG_NS, 'path');
    path.setAttribute('d', d);
    svg.appendChild(path);
    return svg;
  }

  function pagerLink(n, dir) {
    const a = el('a', 'se-pager-link se-pager-link--' + dir);
    a.href = songUrl(n);
    a.rel = dir;
    const label = el('span', 'se-pager-text');
    label.appendChild(el('small', '', dir === 'prev' ? (T.prev || '') : (T.next || '')));
    label.appendChild(el('b', '', (T.hymn || '') + ' ' + n));
    if (dir === 'prev') {
      a.appendChild(chevron('M15 5l-7 7 7 7'));
      a.appendChild(label);
    } else {
      a.appendChild(label);
      a.appendChild(chevron('M9 5l7 7-7 7'));
    }
    return a;
  }

  async function loadHymns(lang) {
    const url = SE_DATA.paths && SE_DATA.paths[lang];
    if (!url) return [];
    const res = await fetch(url, { credentials: 'same-origin' });
    if (!res.ok) throw new Error('http ' + res.status);
    const list = await res.json();
    return Array.isArray(list) ? list : [];
  }

  async function drawAskedHymn(askedSong, lang) {
    const verses = $('#songContent .se-verses');
    if (!song || !verses) return; // a page without a hymn: nothing to redraw

    const [songsAf, songsEn] = await Promise.all([loadHymns('af'), loadHymns('en')]);
    const songs = lang === 'en' ? songsEn : songsAf;
    const altSongs = lang === 'en' ? songsAf : songsEn;
    const find = (list, n) => list.find((s) => String(s.number) === String(n)) || null;

    // Same choice as singemmanuel.php: this language, else the other, else the first hymn
    let selected = askedSong ? find(songs, askedSong) : null;
    let fallbackLang = '';
    if (!selected && askedSong) {
      selected = find(altSongs, askedSong);
      if (selected) fallbackLang = lang === 'en' ? 'af' : 'en';
    }
    if (!selected && songs.length) selected = songs[0];
    if (!selected) return;

    const number = String(selected.number);
    const songLang = fallbackLang || (lang === 'en' ? 'en' : 'af');
    const blocks = seBlocks(selected.verses);
    const firstLine = seFirstLine(blocks);
    const hasNumbers = blocks.some((b) => b.type === 'verse');
    const chorusLabel = songLang === 'en' ? 'Chorus' : 'Koor';

    // Title and opening words
    const title = $('#seTitle');
    if (title) {
      title.textContent = '';
      title.appendChild(el('span', 'se-title-word', T.hymn || ''));
      title.appendChild(el('span', 'se-title-num', number));
    }
    const first = $('.se-first-line');
    if (first) {
      first.textContent = firstLine !== '' ? firstLine : (T.praiseLord || '');
      if (firstLine !== '') first.setAttribute('lang', songLang); else first.removeAttribute('lang');
    }

    // Song language buttons
    document.querySelectorAll('.se-seg-btn').forEach((link) => {
      const l = link.getAttribute('lang') === 'en' ? 'en' : 'af';
      const on = l === 'en' ? lang === 'en' : lang !== 'en';
      link.href = '/singemmanuel/singemmanuel.php?lang=' + l + '&song=' + encodeURIComponent(number);
      link.classList.toggle('is-on', on);
      if (on) link.setAttribute('aria-current', 'true'); else link.removeAttribute('aria-current');
    });

    // The hymn
    song.setAttribute('lang', songLang);
    song.setAttribute('data-song', number);
    const oldNotice = $('.se-notice', song);
    if (oldNotice) oldNotice.remove();
    if (fallbackLang) {
      // In the page's own language, like the rest of its words
      const notice = el('p', 'se-notice');
      notice.setAttribute('lang', SE_DATA.lang || lang);
      const icon = document.createElementNS(SVG_NS, 'svg');
      icon.setAttribute('viewBox', '0 0 24 24');
      icon.setAttribute('fill', 'none');
      icon.setAttribute('stroke', 'currentColor');
      icon.setAttribute('stroke-width', '2');
      icon.setAttribute('stroke-linecap', 'round');
      icon.setAttribute('aria-hidden', 'true');
      const circle = document.createElementNS(SVG_NS, 'circle');
      circle.setAttribute('cx', '12');
      circle.setAttribute('cy', '12');
      circle.setAttribute('r', '10');
      const mark = document.createElementNS(SVG_NS, 'path');
      mark.setAttribute('d', 'M12 8v4M12 16h.01');
      icon.appendChild(circle);
      icon.appendChild(mark);
      notice.appendChild(icon);
      notice.appendChild(el('span', '', lang === 'en' ? (T.notInEnglish || '') : (T.notInAfrikaans || '')));
      song.insertBefore(notice, song.firstChild);
    }

    verses.className = 'se-verses' + (hasNumbers ? '' : ' se-verses--unnumbered');
    verses.textContent = '';
    blocks.forEach((b) => {
      if (b.type === 'verse') {
        const row = el('div', 'se-verse');
        const num = el('span', 'se-verse-num', b.num);
        num.setAttribute('aria-hidden', 'true');
        const text = el('p', 'se-verse-text');
        text.appendChild(el('span', 'se-sr', b.num + '. '));
        text.appendChild(document.createTextNode(b.text));
        row.appendChild(num);
        row.appendChild(text);
        verses.appendChild(row);
      } else if (b.type === 'chorus') {
        const box = el('div', 'se-chorus');
        box.appendChild(el('span', 'se-chorus-label', chorusLabel));
        b.lines.forEach((line) => box.appendChild(el('p', 'se-chorus-text', line)));
        verses.appendChild(box);
      } else {
        const row = el('div', 'se-verse se-verse--plain');
        row.appendChild(el('p', 'se-verse-text', b.text));
        verses.appendChild(row);
      }
    });

    // Previous / next through every number in the book (the picker has them all)
    const numbers = grid ? Array.from(grid.querySelectorAll('.se-song-btn'), (b) => b.getAttribute('data-song')) : [];
    const pos = numbers.indexOf(number);
    const prev = pos > 0 ? numbers[pos - 1] : null;
    const next = pos !== -1 && pos < numbers.length - 1 ? numbers[pos + 1] : null;
    const oldPager = $('.se-pager');
    let pager = null;
    if (prev !== null || next !== null) {
      pager = el('nav', 'se-pager');
      pager.setAttribute('aria-label', T.hymn || '');
      if (prev !== null) pager.appendChild(pagerLink(prev, 'prev'));
      if (next !== null) pager.appendChild(pagerLink(next, 'next'));
    }
    if (oldPager && pager) oldPager.replaceWith(pager);
    else if (oldPager) oldPager.remove();
    else if (pager) $('#seMain').appendChild(pager);

    // The picker marks this hymn
    if (grid) {
      grid.querySelectorAll('.se-song-btn').forEach((btn) => {
        const isCurrent = btn.getAttribute('data-song') === number;
        btn.classList.toggle('is-current', isCurrent);
        if (isCurrent) btn.setAttribute('aria-current', 'true'); else btn.removeAttribute('aria-current');
      });
    }

    // Organ (offices 0-8 only have it on the page): this hymn's music
    const organ = $('#openPlayer');
    if (organ) {
      const midi = (SE_DATA.midi || {})[number];
      organ.setAttribute('data-song', number);
      organ.hidden = !midi;
      const two = $('#playerChoice .se-verse-btn[data-verses="2"]');
      if (two) two.hidden = midi !== 2;
      const playerTitle = $('#playerTitle span');
      if (playerTitle) playerTitle.textContent = (T.hymn || '') + ' ' + number;
    }
  }

  // Online the server drew exactly what was asked; a saved copy differs
  const askedSong = /^[0-9]+$/.test(query.get('song') || '') ? query.get('song') : '';
  if (askedSong !== (SE_DATA.asked || '') || urlLang !== (SE_DATA.askedLang || '')) {
    const lang = urlLang || SE_DATA.lang || 'af';
    drawAskedHymn(askedSong, lang).catch((e) => {
      if (window.console) console.error('Sing Emmanuel offline hymn:', e);
    });
  }
})();
