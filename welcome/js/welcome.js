// =====================================================================
// /welcome/js/welcome.js — Bible-app home interactions
// Verse of the Day (browse, translation, bookmark, copy/share),
// continue reading, daily streak, My Bible stats, book picker,
// teaching expand/collapse and scroll reveals.
// =====================================================================
(function () {
  'use strict';

  const CFG = window.WELCOME || {};
  const I = CFG.i18n || {};

  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  const pad = (n) => String(n).padStart(2, '0');
  const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // localStorage can throw (private mode, blocked storage) — never let it break the page
  const store = {
    get(key) {
      try { return JSON.parse(localStorage.getItem(key)); } catch (e) { return null; }
    },
    set(key, value) {
      try { localStorage.setItem(key, JSON.stringify(value)); } catch (e) { /* ignore */ }
    }
  };

  // [English name (used in ?ref=), Afrikaans name, chapters] — generated from
  // /bible/bibles/*.json. Index < 39 is the Old Testament.
  const BOOKS = [
    ['Genesis', 'Genesis', 50], ['Exodus', 'Eksodus', 40], ['Leviticus', 'Levitikus', 27], ['Numbers', 'Numeri', 36],
    ['Deuteronomy', 'Deuteronomium', 34], ['Joshua', 'Josua', 24], ['Judges', 'Rigters', 21], ['Ruth', 'Rut', 4],
    ['1 Samuel', '1 Samuel', 31], ['2 Samuel', '2 Samuel', 24], ['1 Kings', '1 Konings', 22], ['2 Kings', '2 Konings', 25],
    ['1 Chronicles', '1 Kronieke', 29], ['2 Chronicles', '2 Kronieke', 36], ['Ezra', 'Esra', 10],
    ['Nehemiah', 'Nehemía', 13], ['Esther', 'Ester', 10], ['Job', 'Job', 42], ['Psalms', 'Psalms', 150],
    ['Proverbs', 'Spreuke', 31], ['Ecclesiastes', 'Prediker', 12], ['Song of Solomon', 'Hooglied', 8],
    ['Isaiah', 'Jesaja', 66], ['Jeremiah', 'Jeremia', 52], ['Lamentations', 'Klaagliedere', 5], ['Ezekiel', 'Eségiël', 48],
    ['Daniel', 'Daniël', 12], ['Hosea', 'Hoséa', 14], ['Joel', 'Joël', 3], ['Amos', 'Amos', 9], ['Obadiah', 'Obádja', 1],
    ['Jonah', 'Jona', 4], ['Micah', 'Miga', 7], ['Nahum', 'Nahum', 3], ['Habakkuk', 'Hábakuk', 3],
    ['Zephaniah', 'Sefánja', 3], ['Haggai', 'Haggai', 2], ['Zechariah', 'Sagaría', 14], ['Malachi', 'Maleági', 4],
    ['Matthew', 'Matthéüs', 28], ['Mark', 'Markus', 16], ['Luke', 'Lukas', 24], ['John', 'Johannes', 21],
    ['Acts', 'Handelinge', 28], ['Romans', 'Romeine', 16], ['1 Corinthians', '1 Korinthiërs', 16],
    ['2 Corinthians', '2 Korinthiërs', 13], ['Galatians', 'Galásiërs', 6], ['Ephesians', 'Efésiërs', 6],
    ['Philippians', 'Filippense', 4], ['Colossians', 'Kolossense', 4], ['1 Thessalonians', '1 Thessalonicense', 5],
    ['2 Thessalonians', '2 Thessalonicense', 3], ['1 Timothy', '1 Timótheüs', 6], ['2 Timothy', '2 Timótheüs', 4],
    ['Titus', 'Titus', 3], ['Philemon', 'Filémon', 1], ['Hebrews', 'Hebreërs', 13], ['James', 'Jakobus', 5],
    ['1 Peter', '1 Petrus', 5], ['2 Peter', '2 Petrus', 3], ['1 John', '1 Johannes', 5], ['2 John', '2 Johannes', 1],
    ['3 John', '3 Johannes', 1], ['Jude', 'Judas', 1], ['Revelation', 'Openbaring', 22]
  ];
  const OT_COUNT = 39;
  const BOOK_INDEX = new Map(BOOKS.map((b, i) => [b[0], i]));

  const bookName = (idx) => (CFG.bibleLang === 'af' ? BOOKS[idx][1] : BOOKS[idx][0]);
  const readerUrl = (ref) => '/bible/bible.php?ref=' + encodeURIComponent(ref);

  // "John-3-16" -> { book: 'John', chapter: 3, verse: 16 } (same rule as bible.js)
  function parseRef(ref) {
    if (typeof ref !== 'string') return null;
    const last = ref.lastIndexOf('-');
    const prev = ref.lastIndexOf('-', last - 1);
    if (last < 1 || prev < 1) return null;
    const chapter = parseInt(ref.slice(prev + 1, last), 10);
    const verse = parseInt(ref.slice(last + 1), 10);
    const book = ref.slice(0, prev);
    if (!BOOK_INDEX.has(book) || !chapter) return null;
    return { book, idx: BOOK_INDEX.get(book), chapter, verse: verse || 0 };
  }

  function refLabel(ref) {
    const p = parseRef(ref);
    if (!p) return '';
    return `${bookName(p.idx)} ${p.chapter}${p.verse ? ':' + p.verse : ''}`;
  }

  function timeAgo(ts) {
    const secs = Math.max(0, Math.round((Date.now() - Number(ts)) / 1000));
    if (!isFinite(secs)) return '';
    if (secs < 60) return I.justNow || '';
    if (secs < 3600) return `${Math.round(secs / 60)} ${I.minutesAgo || ''}`;
    if (secs < 86400) return `${Math.round(secs / 3600)} ${I.hoursAgo || ''}`;
    return `${Math.round(secs / 86400)} ${I.daysAgo || ''}`;
  }

  // ===== TOAST =====
  let toastTimer = null;
  function toast(msg) {
    const el = $('#wcToast');
    if (!el || !msg) return;
    el.textContent = msg;
    el.classList.add('is-show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => el.classList.remove('is-show'), 2400);
  }

  async function copyText(text) {
    try {
      if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(text);
        return true;
      }
    } catch (e) { /* fall through to the legacy path */ }
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.setAttribute('readonly', '');
    ta.style.cssText = 'position:fixed;top:-1000px;opacity:0';
    document.body.appendChild(ta);
    ta.select();
    let ok = false;
    try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
    ta.remove();
    return ok;
  }

  function countUp(el, to) {
    const target = Number(to) || 0;
    if (reduceMotion || target === 0) {
      el.textContent = String(target);
      return;
    }
    const start = performance.now();
    const dur = 700;
    const step = (now) => {
      const p = Math.min(1, (now - start) / dur);
      el.textContent = String(Math.round(target * (1 - Math.pow(1 - p, 3))));
      if (p < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
  }

  // ===== TIME OF DAY (greeting + sky follow the device clock) =====
  function applyTimeOfDay() {
    const h = new Date().getHours();
    const greet = h >= 5 && h < 12 ? I.greetMorning : (h >= 12 && h < 18 ? I.greetAfternoon : I.greetEvening);
    const greetEl = $('[data-greet]');
    if (greetEl && greet) greetEl.textContent = greet;

    const sky = h >= 5 && h < 8 ? 'dawn' : (h >= 8 && h < 16 ? 'day' : (h >= 16 && h < 19 ? 'dusk' : 'night'));
    const votd = $('#wcVotd');
    if (votd) votd.dataset.sky = sky;
  }

  // ===== SCROLL REVEAL =====
  function initReveal() {
    const items = $$('.wc-reveal');
    if (!('IntersectionObserver' in window) || reduceMotion) {
      items.forEach((el) => el.classList.add('is-in'));
      return;
    }
    const io = new IntersectionObserver((entries) => {
      let n = 0;
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        entry.target.style.setProperty('--wc-delay', `${Math.min(n++, 5) * 70}ms`);
        entry.target.classList.add('is-in');
        io.unobserve(entry.target);
      });
    }, { rootMargin: '0px 0px -6% 0px', threshold: 0.06 });
    items.forEach((el) => io.observe(el));
  }

  // ===== DAILY STREAK (per device) =====
  const STREAK_KEY = 'oac_streak_v1';
  const isoDay = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

  function longestRun(sortedDays) {
    let best = 0;
    let run = 0;
    let prev = null;
    sortedDays.forEach((s) => {
      const t = Date.parse(s + 'T00:00:00Z');
      run = prev !== null && t - prev === 86400000 ? run + 1 : 1;
      best = Math.max(best, run);
      prev = t;
    });
    return best;
  }

  function initStreak() {
    const today = new Date();
    today.setHours(12, 0, 0, 0);
    const todayIso = isoDay(today);

    const saved = store.get(STREAK_KEY) || {};
    let days = Array.isArray(saved.days) ? saved.days.filter((s) => /^\d{4}-\d{2}-\d{2}$/.test(s)) : [];
    days.push(todayIso);
    days = Array.from(new Set(days)).sort().slice(-120);
    const visited = new Set(days);

    let current = 0;
    const cursor = new Date(today);
    while (visited.has(isoDay(cursor))) {
      current++;
      cursor.setDate(cursor.getDate() - 1);
    }
    const best = Math.max(Number(saved.best) || 0, current, longestRun(days));
    store.set(STREAK_KEY, { days, best });

    $$('[data-streak-count]').forEach((el) => countUp(el, current));
    $$('[data-streak-best]').forEach((el) => { el.textContent = String(best); });
    const unit = $('[data-streak-unit]');
    if (unit) unit.textContent = current === 1 ? (I.streakDay || '') : (I.streakDays || '');

    const dow = (today.getDay() + 6) % 7; // Monday = 0
    $$('[data-week] li').forEach((li, i) => {
      const d = new Date(today);
      d.setDate(today.getDate() - dow + i);
      li.classList.toggle('is-done', visited.has(isoDay(d)));
      li.classList.toggle('is-today', i === dow);
      li.classList.toggle('is-future', i > dow);
    });
  }

  // ===== CONTINUE READING (position saved by the Bible reader) =====
  function bookHue(idx) {
    return idx < OT_COUNT ? 8 + idx * 1.1 : 200 + (idx - OT_COUNT) * 3.2;
  }

  function abbrev(name) {
    const m = String(name).match(/^(\d\s)?(.+)$/);
    if (!m) return name;
    return (m[1] || '') + m[2].slice(0, 3);
  }

  function initContinue() {
    const card = $('#wcContinue');
    if (!card) return;
    const cover = $('[data-cont-cover]', card);
    const last = store.get('oac_last_read');
    const p = last && parseRef(last.ref);

    if (!p) {
      cover.classList.add('is-default');
      return;
    }

    const total = BOOKS[p.idx][2];
    card.href = readerUrl(last.ref);
    $('[data-cont-eyebrow]', card).textContent = I.continueReading || '';
    $('[data-cont-title]', card).textContent = `${bookName(p.idx)} ${p.chapter}`;
    $('[data-cont-meta]', card).textContent = [
      p.verse ? `${I.verse || ''} ${p.verse}` : '',
      last.t ? timeAgo(last.t) : ''
    ].filter(Boolean).join(' · ');
    $('[data-cont-initial]', card).textContent = abbrev(bookName(p.idx));
    cover.style.setProperty('--h', String(bookHue(p.idx)));

    const bar = $('[data-cont-bar]', card);
    const pct = Math.max(3, Math.round((p.chapter / total) * 100));
    requestAnimationFrame(() => setTimeout(() => bar.style.setProperty('--p', String(pct)), 250));
  }

  // ===== VERSE OF THE DAY =====
  const votd = {
    root: null,
    offset: 0,
    trans: 'af',
    cache: new Map(),
    bookmarks: null, // Set of refs once loaded
    busy: false
  };

  function current() {
    return votd.cache.get(votd.offset);
  }

  function primaryOf(v) {
    return v[v.primary] || v.en;
  }

  function sizeFor(text) {
    const n = text.length;
    return n < 90 ? 's' : (n < 170 ? 'm' : 'l');
  }

  function renderWords(el, text, animate) {
    el.textContent = '';
    if (!animate || reduceMotion) {
      el.textContent = text;
      return;
    }
    const frag = document.createDocumentFragment();
    text.split(/(\s+)/).forEach((part, i) => {
      if (!part) return;
      if (/^\s+$/.test(part)) {
        frag.appendChild(document.createTextNode(' '));
        return;
      }
      const span = document.createElement('span');
      span.className = 'w';
      span.style.setProperty('--i', String(i >> 1));
      span.textContent = part;
      frag.appendChild(span);
    });
    el.appendChild(frag);
  }

  function renderVotd(animate) {
    const v = current();
    if (!v || !votd.root) return;
    const shown = v[votd.trans] || primaryOf(v);
    const primary = primaryOf(v);
    const names = CFG.translationNames || {};

    const textEl = $('[data-votd-text]', votd.root);
    textEl.dataset.size = sizeFor(shown.text);
    textEl.lang = votd.trans;
    renderWords(textEl, shown.text, animate);

    $('[data-votd-ref]', votd.root).textContent = shown.label;
    $('[data-votd-trans-name]', votd.root).textContent = names[votd.trans] || '';
    $('[data-votd-date]', votd.root).textContent = votd.offset === 0 ? (I.today || '') : v.date_label;
    $('[data-votd-read]', votd.root).href = readerUrl(primary.ref);

    $('[data-votd-prev]', votd.root).disabled = votd.offset <= -(CFG.maxBack || 30);
    $('[data-votd-next]', votd.root).disabled = votd.offset >= 0;

    $$('[data-trans]', votd.root).forEach((b) => {
      b.setAttribute('aria-pressed', b.dataset.trans === votd.trans ? 'true' : 'false');
    });
    syncBookmarkButton();
  }

  function syncBookmarkButton() {
    const btn = votd.root && $('[data-act="bookmark"]', votd.root);
    const v = current();
    if (!btn || !v) return;
    const on = !!(votd.bookmarks && votd.bookmarks.has(primaryOf(v).ref));
    btn.setAttribute('aria-pressed', on ? 'true' : 'false');
  }

  function swapVotd(dir, after) {
    const body = $('[data-votd-body]', votd.root);
    if (reduceMotion) {
      after();
      return;
    }
    body.style.setProperty('--dir', String(dir));
    body.classList.add('is-leaving');
    setTimeout(() => {
      after();
      body.classList.remove('is-leaving');
    }, 240);
  }

  async function goToDay(delta) {
    if (votd.busy) return;
    const target = votd.offset + delta;
    if (target > 0 || target < -(CFG.maxBack || 30)) return;

    if (!votd.cache.has(target)) {
      votd.busy = true;
      const body = $('[data-votd-body]', votd.root);
      body.classList.add('is-loading');
      try {
        const res = await fetch(`/welcome/api/verse.php?offset=${target}`, {
          credentials: 'same-origin',
          headers: { Accept: 'application/json' }
        });
        const data = await res.json();
        if (!res.ok || !data.success) throw new Error(data.error || 'load failed');
        votd.cache.set(target, data);
      } catch (e) {
        toast(I.tryAgain);
        return;
      } finally {
        votd.busy = false;
        body.classList.remove('is-loading');
      }
    }

    swapVotd(delta, () => {
      votd.offset = target;
      renderVotd(true);
    });
  }

  function shareText() {
    const v = current();
    const shown = v[votd.trans] || primaryOf(v);
    const name = (CFG.translationNames || {})[votd.trans] || '';
    return `“${shown.text}”\n— ${shown.label}${name ? ' (' + name + ')' : ''}`;
  }

  async function toggleBookmark(btn) {
    const v = current();
    if (!v || btn.disabled) return;
    const primary = primaryOf(v);
    const isOn = btn.getAttribute('aria-pressed') === 'true';
    btn.disabled = true;
    try {
      const res = await fetch('/bible/api/bookmarks/save.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CFG.csrf || '' },
        body: JSON.stringify({
          verse_ref: primary.ref,
          verse_text: primary.text,
          action: isOn ? 'remove' : 'add'
        })
      });
      const data = await res.json();
      if (!res.ok || !data.success) throw new Error(data.error || 'save failed');

      if (!votd.bookmarks) votd.bookmarks = new Set();
      if (data.bookmarked) votd.bookmarks.add(primary.ref);
      else votd.bookmarks.delete(primary.ref);
      syncBookmarkButton();
      btn.classList.remove('is-pop');
      void btn.offsetWidth;
      btn.classList.add('is-pop');
      toast(data.bookmarked ? I.bookmarkAdded : I.bookmarkRemoved);
      adjustStat('bookmarks', data.bookmarked ? 1 : -1);
      if (data.bookmarked) showLatest(primary.ref, primary.text);
    } catch (e) {
      toast(I.tryAgain);
    } finally {
      btn.disabled = false;
    }
  }

  function initVotd() {
    votd.root = $('#wcVotd');
    if (!votd.root || !CFG.votd) return;
    votd.cache.set(0, CFG.votd);
    votd.trans = CFG.votd.primary || 'en';

    // First paint: animate the server-rendered verse word by word
    renderVotd(true);

    $('[data-votd-prev]', votd.root).addEventListener('click', () => goToDay(-1));
    $('[data-votd-next]', votd.root).addEventListener('click', () => goToDay(1));

    $$('[data-trans]', votd.root).forEach((b) => {
      b.addEventListener('click', () => {
        if (b.dataset.trans === votd.trans) return;
        swapVotd(b.dataset.trans === 'en' ? 1 : -1, () => {
          votd.trans = b.dataset.trans;
          renderVotd(true);
        });
      });
    });

    votd.root.addEventListener('click', async (e) => {
      const btn = e.target.closest('[data-act]');
      if (!btn) return;
      const act = btn.dataset.act;
      if (act === 'bookmark') {
        toggleBookmark(btn);
      } else if (act === 'copy') {
        toast((await copyText(shareText())) ? I.copied : I.tryAgain);
      } else if (act === 'share') {
        const text = shareText();
        if (navigator.share) {
          try {
            await navigator.share({ text });
            return;
          } catch (err) {
            if (err && err.name === 'AbortError') return;
          }
        }
        toast((await copyText(text)) ? I.shareCopied : I.tryAgain);
      }
    });

    // Swipe right = previous day, swipe left = next day
    const body = $('[data-votd-body]', votd.root);
    let sx = 0;
    let sy = 0;
    body.addEventListener('touchstart', (e) => {
      sx = e.touches[0].clientX;
      sy = e.touches[0].clientY;
    }, { passive: true });
    body.addEventListener('touchend', (e) => {
      const dx = e.changedTouches[0].clientX - sx;
      const dy = e.changedTouches[0].clientY - sy;
      if (Math.abs(dx) > 55 && Math.abs(dx) > Math.abs(dy) * 1.4) {
        goToDay(dx > 0 ? -1 : 1);
      }
    }, { passive: true });

    votd.root.addEventListener('keydown', (e) => {
      if (e.target.closest('input, textarea')) return;
      if (e.key === 'ArrowLeft') goToDay(-1);
      if (e.key === 'ArrowRight') goToDay(1);
    });
  }

  // ===== MY BIBLE (counts from the reader's saved data) =====
  const stats = {};

  function adjustStat(key, delta) {
    if (typeof stats[key] !== 'number') return;
    stats[key] = Math.max(0, stats[key] + delta);
    const el = $(`[data-stat="${key}"]`);
    if (el) el.textContent = String(stats[key]);
  }

  function showLatest(ref, text) {
    const box = $('[data-latest]');
    if (!box || !text) return;
    $('[data-latest-text]', box).textContent = text;
    $('[data-latest-ref]', box).textContent = refLabel(ref) || ref;
    box.href = readerUrl(ref);
    box.hidden = false;
  }

  async function initMyBible() {
    try {
      const res = await fetch('/bible/api/load_all.php', {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' }
      });
      const data = await res.json();
      if (!res.ok || !data.success) throw new Error('load failed');

      const bookmarks = data.bookmarks && typeof data.bookmarks === 'object' ? data.bookmarks : {};
      const highlights = data.highlights && typeof data.highlights === 'object' ? data.highlights : {};
      const notes = data.notes && typeof data.notes === 'object' ? data.notes : {};

      stats.bookmarks = Object.keys(bookmarks).length;
      stats.highlights = Object.keys(highlights).length;
      stats.notes = Object.keys(notes).length;
      Object.keys(stats).forEach((k) => {
        const el = $(`[data-stat="${k}"]`);
        if (el) countUp(el, stats[k]);
      });

      votd.bookmarks = new Set(Object.keys(bookmarks));
      syncBookmarkButton();

      let latestRef = null;
      let latestTs = -1;
      Object.keys(bookmarks).forEach((ref) => {
        const ts = Number(bookmarks[ref] && bookmarks[ref].timestamp) || 0;
        if (ts > latestTs) {
          latestTs = ts;
          latestRef = ref;
        }
      });
      if (latestRef) showLatest(latestRef, bookmarks[latestRef].text);
    } catch (e) {
      // Leave the "–" placeholders; the reader still works
      votd.bookmarks = votd.bookmarks || null;
    }
  }

  // ===== NOTIFICATION BADGE (mirrors the global footer badge) =====
  function initNotifBadge() {
    const src = $('#globalNotifCount');
    const dst = $('[data-notif-badge]');
    if (!src || !dst) return;
    const sync = () => {
      dst.textContent = src.textContent;
      dst.hidden = src.hidden || !src.textContent || src.textContent === '0';
    };
    sync();
    new MutationObserver(sync).observe(src, { attributes: true, childList: true, characterData: true, subtree: true });
  }

  // ===== TEACHING EXPAND / COLLAPSE =====
  function initTeaching() {
    const body = $('#wcTeachBody');
    const btn = $('#wcTeachToggle');
    if (!body || !btn) return;
    const COLLAPSED = 330;
    const label = $('[data-label]', btn);

    const measure = () => {
      if (body.classList.contains('is-open')) return;
      const tall = body.scrollHeight > COLLAPSED + 90;
      body.classList.toggle('is-collapsible', tall);
      btn.hidden = !tall;
    };
    measure();
    window.addEventListener('load', measure);

    btn.addEventListener('click', () => {
      const open = !body.classList.contains('is-open');
      if (open) {
        body.style.maxHeight = body.scrollHeight + 'px';
        body.classList.add('is-open');
        const done = () => {
          if (body.classList.contains('is-open')) body.style.maxHeight = 'none';
          body.removeEventListener('transitionend', done);
        };
        body.addEventListener('transitionend', done);
        if (reduceMotion) done();
      } else {
        body.style.maxHeight = body.scrollHeight + 'px';
        void body.offsetHeight;
        body.classList.remove('is-open');
        body.style.maxHeight = '';
        const card = $('#wcTeach');
        if (card && card.getBoundingClientRect().top < 0) {
          card.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
        }
      }
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (label) label.textContent = open ? (I.readLess || '') : (I.readMore || '');
    });
  }

  // ===== BOOK PICKER SHEET =====
  const sheet = {
    el: null,
    opener: null,
    testament: 'ot',
    book: null,
    pushed: false
  };

  function renderBooks() {
    const grid = $('[data-book-grid]', sheet.el);
    const q = ($('[data-book-filter]', sheet.el).value || '').trim().toLowerCase();
    const norm = (s) => s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    const nq = norm(q);

    grid.textContent = '';
    const frag = document.createDocumentFragment();
    BOOKS.forEach((b, idx) => {
      const inTestament = sheet.testament === 'ot' ? idx < OT_COUNT : idx >= OT_COUNT;
      if (nq) {
        if (!norm(b[0]).includes(nq) && !norm(b[1]).includes(nq)) return;
      } else if (!inTestament) {
        return;
      }
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'wc-book';
      btn.dataset.idx = String(idx);
      const name = document.createElement('span');
      name.className = 'wc-book-name';
      name.textContent = bookName(idx);
      const meta = document.createElement('span');
      meta.className = 'wc-book-meta';
      meta.textContent = `${b[2]} ${b[2] === 1 ? (I.chapter || '').toLowerCase() : (I.chapters || '')}`;
      btn.append(name, meta);
      frag.appendChild(btn);
    });
    if (!frag.childNodes.length) {
      const empty = document.createElement('p');
      empty.className = 'wc-sheet-empty';
      empty.textContent = I.noResults || '';
      frag.appendChild(empty);
    }
    grid.appendChild(frag);
  }

  function showBooks() {
    sheet.book = null;
    $('[data-book-grid]', sheet.el).hidden = false;
    $('[data-chapter-grid]', sheet.el).hidden = true;
    $('[data-book-tools]', sheet.el).hidden = false;
    $('[data-sheet-back]', sheet.el).hidden = true;
    $('#wcBooksTitle').textContent = I.chooseBook || '';
    renderBooks();
  }

  function showChapters(idx) {
    const total = BOOKS[idx][2];
    if (total === 1) {
      goToChapter(idx, 1);
      return;
    }
    sheet.book = idx;
    const grid = $('[data-chapter-grid]', sheet.el);
    grid.textContent = '';
    const frag = document.createDocumentFragment();
    for (let c = 1; c <= total; c++) {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'wc-chapter';
      btn.dataset.ch = String(c);
      btn.textContent = String(c);
      btn.setAttribute('aria-label', `${bookName(idx)} ${c}`);
      frag.appendChild(btn);
    }
    grid.appendChild(frag);
    $('[data-book-grid]', sheet.el).hidden = true;
    $('[data-book-tools]', sheet.el).hidden = true;
    grid.hidden = false;
    $('[data-sheet-back]', sheet.el).hidden = false;
    $('#wcBooksTitle').textContent = bookName(idx);
    $('.wc-sheet-body', sheet.el).scrollTop = 0;
    const first = $('.wc-chapter', grid);
    if (first) first.focus({ preventScroll: true });
  }

  function goToChapter(idx, ch) {
    const url = readerUrl(`${BOOKS[idx][0]}-${ch}-1`);
    // Replace the sheet's history entry so "back" from the reader lands on home
    if (sheet.pushed) location.replace(url);
    else location.href = url;
  }

  function openSheet(opener) {
    if (!sheet.el || !sheet.el.hidden) return;
    sheet.opener = opener || null;
    sheet.el.hidden = false;
    document.body.classList.add('wc-sheet-lock');
    showBooks();
    requestAnimationFrame(() => requestAnimationFrame(() => sheet.el.classList.add('is-open')));
    const tab = $(`[data-testament="${sheet.testament}"]`, sheet.el);
    if (tab) tab.focus({ preventScroll: true });
    try {
      history.pushState({ wcSheet: true }, '');
      sheet.pushed = true;
    } catch (e) {
      sheet.pushed = false;
    }
  }

  function closeSheetNow() {
    if (!sheet.el || sheet.el.hidden) return;
    sheet.el.classList.remove('is-open');
    document.body.classList.remove('wc-sheet-lock');
    setTimeout(() => { sheet.el.hidden = true; }, reduceMotion ? 0 : 420);
    if (sheet.opener) sheet.opener.focus({ preventScroll: true });
  }

  function closeSheet() {
    if (sheet.pushed && history.state && history.state.wcSheet) {
      history.back(); // popstate closes it
    } else {
      closeSheetNow();
    }
  }

  function initSheet() {
    sheet.el = $('#wcBooks');
    const opener = $('#wcOpenBooks');
    if (!sheet.el || !opener) return;

    opener.addEventListener('click', () => openSheet(opener));

    window.addEventListener('popstate', () => {
      sheet.pushed = false;
      closeSheetNow();
    });

    sheet.el.addEventListener('click', (e) => {
      if (e.target.closest('[data-sheet-close]')) {
        closeSheet();
        return;
      }
      if (e.target.closest('[data-sheet-back]')) {
        showBooks();
        return;
      }
      const tab = e.target.closest('[data-testament]');
      if (tab) {
        sheet.testament = tab.dataset.testament;
        $$('[data-testament]', sheet.el).forEach((t) => {
          t.setAttribute('aria-selected', t === tab ? 'true' : 'false');
        });
        $('[data-book-filter]', sheet.el).value = '';
        renderBooks();
        return;
      }
      const book = e.target.closest('.wc-book');
      if (book) {
        showChapters(parseInt(book.dataset.idx, 10));
        return;
      }
      const ch = e.target.closest('.wc-chapter');
      if (ch && sheet.book !== null) {
        goToChapter(sheet.book, parseInt(ch.dataset.ch, 10));
      }
    });

    $('[data-book-filter]', sheet.el).addEventListener('input', renderBooks);

    sheet.el.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        e.preventDefault();
        if (sheet.book !== null) showBooks();
        else closeSheet();
        return;
      }
      if (e.key !== 'Tab') return;
      // Keep focus inside the dialog
      const focusables = $$('button:not([hidden]):not(:disabled), input:not([hidden])', sheet.el)
        .filter((el) => el.offsetParent !== null);
      if (!focusables.length) return;
      const first = focusables[0];
      const last = focusables[focusables.length - 1];
      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    });
  }

  // ===== SEARCH =====
  function initSearch() {
    const form = $('#wcSearch');
    if (!form) return;
    const input = $('input[name="q"]', form);

    // The full hint ("… or type a reference (e.g. John 3:16)") only where it fits
    const shortHint = input.placeholder;
    const longHint = input.dataset.longPlaceholder || shortHint;
    const fitHint = () => {
      input.placeholder = input.clientWidth >= 360 ? longHint : shortHint;
    };
    fitHint();
    window.addEventListener('resize', fitHint);

    form.addEventListener('submit', (e) => {
      if (!input.value.trim()) {
        e.preventDefault();
        input.focus();
      }
    });
  }

  // ===== INIT =====
  function init() {
    applyTimeOfDay();
    initReveal();
    initStreak();
    initContinue();
    initVotd();
    initMyBible();
    initTeaching();
    initSheet();
    initSearch();
    initNotifBadge();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
