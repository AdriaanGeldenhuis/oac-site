(() => {
  'use strict';

  // ===== UTILITIES =====
  const $ = (id) => document.getElementById(id);
  const esc = (s) => String(s || '').replace(/[&<>"']/g, m => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  })[m]);

  // Afrikaans readers get Afrikaans; everyone else reads the English text,
  // so they get English messages (never Afrikaans by accident)
  const tr = (af, en) => (window.BIBLE && window.BIBLE.lang === 'af') ? af : en;

  // Offline (the app's offline copy, see /sw.js): reading works, saving and
  // the AI / cross references need the server. Say so instead of "failed".
  const offline = () => navigator.onLine === false;
  const saveFailText = (af, en) => offline()
    ? tr('Jy is aflyn — dit kan net aanlyn gestoor word.', 'You are offline — this can only be saved online.')
    : tr(af, en);
  const onlineOnlyText = (af, en) => offline()
    ? tr('Jy is aflyn — dit het internet nodig.', 'You are offline — this needs the internet.')
    : tr(af, en);
  const I18N = (window.BIBLE && window.BIBLE.i18n) || {};

  const reduceMotion = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);

  // localStorage can throw (private mode, blocked storage) — never let it matter
  const store = {
    get(key) { try { return localStorage.getItem(key); } catch (e) { return null; } },
    set(key, value) { try { localStorage.setItem(key, value); } catch (e) { /* ignore */ } }
  };

  const svg = (paths) => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${paths}</svg>`;
  const ICON = {
    bookmark: svg('<path d="M19 21l-7-5-7 5V5a2 2 0 012-2h10a2 2 0 012 2z"/>'),
    note: svg('<path d="M12 20h9M16.5 3.5a2.12 2.12 0 013 3L7 19l-4 1 1-4 12.5-12.5z"/>'),
    trash: svg('<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 11v6M14 11v6"/>')
  };

  // ===== TOAST + CONFIRM (instead of alert()/confirm(), which show the
  // site address as a title inside the app) =====
  let toastTimer = 0;
  function toast(message, isError) {
    const el = document.getElementById('bibleToast');
    if (!el || !message) return;
    el.textContent = message;
    el.classList.toggle('is-error', !!isError);
    el.classList.add('is-on');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => el.classList.remove('is-on'), isError ? 3600 : 2200);
  }

  function confirmSheet(message, okLabel) {
    return new Promise((resolve) => {
      const wrap = document.createElement('div');
      wrap.className = 'bible-confirm';
      wrap.setAttribute('role', 'alertdialog');
      wrap.setAttribute('aria-modal', 'true');
      wrap.innerHTML = `
        <div class="bible-confirm-card">
          <p class="bible-confirm-text">${esc(message)}</p>
          <div class="bible-confirm-actions">
            <button type="button" class="bible-btn bible-btn-danger" data-ok>${esc(okLabel || I18N.remove || tr('Verwyder', 'Remove'))}</button>
            <button type="button" class="bible-btn" data-cancel>${esc(I18N.cancel || tr('Kanselleer', 'Cancel'))}</button>
          </div>
        </div>`;
      const prev = document.activeElement;
      const done = (v) => {
        document.removeEventListener('keydown', onKey, true);
        wrap.remove();
        if (prev && prev.focus) prev.focus({ preventScroll: true });
        resolve(v);
      };
      const onKey = (e) => { if (e.key === 'Escape') { e.stopPropagation(); done(false); } };
      wrap.addEventListener('click', (e) => {
        if (e.target === wrap || e.target.closest('[data-cancel]')) done(false);
        else if (e.target.closest('[data-ok]')) done(true);
      });
      document.addEventListener('keydown', onKey, true);
      document.body.appendChild(wrap);
      wrap.querySelector('[data-cancel]').focus();
    });
  }

  // Clipboard with a fallback for older WebViews / plain http, and for
  // WebViews that have the API but refuse the permission
  function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(text).catch(() => copyTextLegacy(text));
    }
    return copyTextLegacy(text);
  }

  function copyTextLegacy(text) {
    return new Promise((resolve, reject) => {
      const ta = document.createElement('textarea');
      ta.value = text;
      ta.setAttribute('readonly', '');
      ta.style.cssText = 'position:fixed;top:0;left:0;opacity:0;pointer-events:none';
      document.body.appendChild(ta);
      ta.select();
      try {
        if (document.execCommand('copy')) resolve(); else reject(new Error('copy failed'));
      } catch (err) {
        reject(err);
      }
      ta.remove();
    });
  }

  // ===== INDEXEDDB SETUP =====
  let db = null;
  const DB_NAME = 'BibleReaderDB';
  const DB_VERSION = 4;
  const STORE_NAME = 'bibleData';

  async function initDB() {
    return new Promise((resolve, reject) => {
      const request = indexedDB.open(DB_NAME, DB_VERSION);
      
      request.onerror = () => reject(request.error);
      
      request.onsuccess = () => {
        db = request.result;
        resolve(db);
      };
      
      request.onupgradeneeded = (e) => {
        const database = e.target.result;
        if (!database.objectStoreNames.contains(STORE_NAME)) {
          database.createObjectStore(STORE_NAME, { keyPath: 'key' });
        }
      };
    });
  }

  async function getFromDB(key) {
    if (!db) return null;
    
    return new Promise((resolve) => {
      try {
        const transaction = db.transaction([STORE_NAME], 'readonly');
        const store = transaction.objectStore(STORE_NAME);
        const request = store.get(key);
        
        request.onsuccess = () => resolve(request.result?.data || null);
        request.onerror = () => resolve(null);
      } catch (e) {
        resolve(null);
      }
    });
  }

  async function saveToDB(key, data) {
    if (!db) return false;
    
    return new Promise((resolve) => {
      try {
        const transaction = db.transaction([STORE_NAME], 'readwrite');
        const store = transaction.objectStore(STORE_NAME);
        const request = store.put({ key, data, timestamp: Date.now() });
        
        request.onsuccess = () => resolve(true);
        request.onerror = () => resolve(false);
      } catch (e) {
        resolve(false);
      }
    });
  }

  // A new Bible file version (its ?v= is the file's content hash) leaves the
  // old copy behind: ~5 MB each. Keep only the versions in use now.
  function pruneOldBibles(keepKeys) {
    if (!db) return;
    try {
      const store = db.transaction([STORE_NAME], 'readwrite').objectStore(STORE_NAME);
      const request = store.getAllKeys();
      request.onsuccess = () => {
        (request.result || []).forEach((key) => {
          if (typeof key === 'string' && /^bible_v\d+_/.test(key) && !keepKeys.includes(key)) {
            store.delete(key);
          }
        });
      };
    } catch (e) {
      /* only tidying up */
    }
  }

  const bibleCacheKey = (url) => `bible_v6_${url}`;

  // ===== BOOK NAME MAPPINGS =====
  // Names MUST match exactly what's in af_1933_53.json (including special characters)
  const AF_TO_EN_BOOKS = {
    'Genesis': 'Genesis',
    'Eksodus': 'Exodus',
    'Levitikus': 'Leviticus',
    'Numeri': 'Numbers',
    'Deuteronomium': 'Deuteronomy',
    'Josua': 'Joshua',
    'Rigters': 'Judges',
    'Rut': 'Ruth',
    '1 Samuel': '1 Samuel',
    '2 Samuel': '2 Samuel',
    '1 Konings': '1 Kings',
    '2 Konings': '2 Kings',
    '1 Kronieke': '1 Chronicles',
    '2 Kronieke': '2 Chronicles',
    'Esra': 'Ezra',
    'Nehemía': 'Nehemiah',
    'Ester': 'Esther',
    'Job': 'Job',
    'Psalms': 'Psalms',
    'Spreuke': 'Proverbs',
    'Prediker': 'Ecclesiastes',
    'Hooglied': 'Song of Solomon',
    'Jesaja': 'Isaiah',
    'Jeremia': 'Jeremiah',
    'Klaagliedere': 'Lamentations',
    'Eségiël': 'Ezekiel',
    'Daniël': 'Daniel',
    'Hoséa': 'Hosea',
    'Joël': 'Joel',
    'Amos': 'Amos',
    'Obádja': 'Obadiah',
    'Jona': 'Jonah',
    'Miga': 'Micah',
    'Nahum': 'Nahum',
    'Hábakuk': 'Habakkuk',
    'Sefánja': 'Zephaniah',
    'Haggai': 'Haggai',
    'Sagaría': 'Zechariah',
    'Maleági': 'Malachi',
    'Matthéüs': 'Matthew',
    'Markus': 'Mark',
    'Lukas': 'Luke',
    'Johannes': 'John',
    'Handelinge': 'Acts',
    'Romeine': 'Romans',
    '1 Korinthiërs': '1 Corinthians',
    '2 Korinthiërs': '2 Corinthians',
    'Galásiërs': 'Galatians',
    'Efésiërs': 'Ephesians',
    'Filippense': 'Philippians',
    'Kolossense': 'Colossians',
    '1 Thessalonicense': '1 Thessalonians',
    '2 Thessalonicense': '2 Thessalonians',
    '1 Timótheüs': '1 Timothy',
    '2 Timótheüs': '2 Timothy',
    'Titus': 'Titus',
    'Filémon': 'Philemon',
    'Hebreërs': 'Hebrews',
    'Jakobus': 'James',
    '1 Petrus': '1 Peter',
    '2 Petrus': '2 Peter',
    '1 Johannes': '1 John',
    '2 Johannes': '2 John',
    '3 Johannes': '3 John',
    'Judas': 'Jude',
    'Openbaring': 'Revelation'
  };

  const EN_TO_AF_BOOKS = {};
  Object.keys(AF_TO_EN_BOOKS).forEach(af => {
    EN_TO_AF_BOOKS[AF_TO_EN_BOOKS[af]] = af;
  });

  // ===== STATE =====
  const state = {
    lang: window.BIBLE?.lang || 'af',
    paths: window.BIBLE?.paths || {},
    userId: window.BIBLE?.userId || 0,
    csrfToken: window.BIBLE?.csrfToken || '',
    viewRef: null,
    dataAF: null,
    dataEN: null,
    booksAF: [],
    booksEN: [],
    
    selectedVerse: null,
    highlights: {},
    notes: {},
    bookmarks: {},
    crossReferences: {},
    fontSize: 2,              // index into the 5 text-size steps (see bible.css)
    fontFace: 'serif',
    searchIndex: {},
    navState: {
      testament: null,
      book: null,
      chapter: null
    },
    dualViewEnabled: false,
    
    currentBookIndex: 0,
    currentChapter: 1,
    earliestBookIndex: 0,
    earliestChapter: 1,
    renderedChapters: new Set(),
    isLoading: false,
    isLoadingPrev: false,
    loadGeneration: 0,
    syncingScroll: false
  };

  function apiHeaders() {
    return {
      'Content-Type': 'application/json',
      'X-CSRF-Token': state.csrfToken
    };
  }

  // ===== LANGUAGE CHANGE HANDLER =====
  function handleLanguageChange(newLang) {
    if (newLang === state.lang) return;

    const overlay = createLoadingOverlay();

    // Save the position the reader is actually viewing (tracked by
    // updateHeaderRef); fall back to the render cursor if unavailable.
    const savedView = state.viewRef ? parseRef(state.viewRef) : null;
    const savedBookIdx = savedView && state.booksEN.indexOf(savedView.bookEN) !== -1
      ? state.booksEN.indexOf(savedView.bookEN)
      : state.currentBookIndex;
    const savedChapter = savedView ? savedView.chapter : state.currentChapter;

    state.lang = newLang;

    setTimeout(async () => {
      try {
        updateLoadingProgress(50, '50%', tr('Laai Bybel...', 'Loading Bible...'));

        // Navigate to the saved position in the new language
        const bookEN = state.booksEN[savedBookIdx] || 'Genesis';
        const ref = makeRef(bookEN, savedChapter || 1, 1);
        goToReference(ref);
        refreshVerseDisplay();

        updateLoadingProgress(100, '100%', tr('Gereed!', 'Ready!'));

        setTimeout(() => {
          removeLoadingOverlay();
        }, 300);
      } catch (e) {
        console.error('Language switch failed:', e);
        removeLoadingOverlay();
      }
    }, 100);
  }

  // ===== ELEMENTS =====
  const els = {
    quickNavToggle: $('quickNavToggle'),
    quickNavModal: $('quickNavModal'),
    quickNavOverlay: $('quickNavOverlay'),
    quickNavClose: $('quickNavClose'),
    navStepTestament: $('navStepTestament'),
    navStepBook: $('navStepBook'),
    navStepChapter: $('navStepChapter'),
    navBookTitle: $('navBookTitle'),
    navChapterTitle: $('navChapterTitle'),
    navBookGrid: $('navBookGrid'),
    navChapterGrid: $('navChapterGrid'),
    navBackToTestament: $('navBackToTestament'),
    navBackToBook: $('navBackToBook'),
    searchToggle: $('searchToggle'),
    notesToggle: $('notesToggle'),
    bookmarksToggle: $('bookmarksToggle'),
    searchPanel: $('searchPanel'),
    searchClose: $('searchClose'),
    searchInput: $('searchInput'),
    searchBtn: $('searchBtn'),
    searchResults: $('searchResults'),
    notesPanel: $('notesPanel'),
    notesClose: $('notesClose'),
    notesList: $('notesList'),
    noteEditor: $('noteEditor'),
    noteReference: $('noteReference'),
    noteText: $('noteText'),
    saveNoteBtn: $('saveNoteBtn'),
    cancelNoteBtn: $('cancelNoteBtn'),
    bookmarksPanel: $('bookmarksPanel'),
    bookmarksClose: $('bookmarksClose'),
    bookmarksList: $('bookmarksList'),
    aiPanel: $('aiPanel'),
    aiClose: $('aiClose'),
    aiOutput: $('aiOutput'),
    crossRefPanel: $('crossRefPanel'),
    crossRefClose: $('crossRefClose'),
    crossRefList: $('crossRefList'),
    leftContent: $('leftContent'),
    rightContent: $('rightContent'),
    leftColumn: $('leftColumn'),
    rightColumn: $('rightColumn'),
    dualContainer: document.querySelector('.bible-dual-container'),
    verseContextMenu: $('verseContextMenu'),
    ctxBookmark: $('ctxBookmark'),
    ctxAddNote: $('ctxAddNote'),
    ctxAI: $('ctxAI'),
    ctxCrossRef: $('ctxCrossRef'),
    ctxCopy: $('ctxCopy'),
    ctxShare: $('ctxShare'),
    fontSizeIncrease: $('fontSizeIncrease'),
    fontSizeDecrease: $('fontSizeDecrease'),
    fontSizeSteps: $('fontSizeSteps'),
    displayToggle: $('displayToggle'),
    displayPanel: $('displayPanel'),
    reading: $('bibleReading'),
    refLabel: $('bibleRefLabel'),
    progress: $('bibleProgress'),
    verseScrim: $('verseScrim'),
    ctxRef: $('ctxRef'),
    ctxPreview: $('ctxPreview'),
    ctxClose: $('ctxClose'),
    navBookFilter: $('navBookFilter')
  };

  // Where the reader was, per user, so the Bible opens there next time
  const LAST_REF_KEY = 'bible_last_ref_' + (window.BIBLE && window.BIBLE.userId || 0);

  function bookLabel(bookEN) {
    return state.lang === 'af' ? (EN_TO_AF_BOOKS[bookEN] || bookEN) : bookEN;
  }

  // "Johannes 3" on the toolbar button
  function setRefLabel(bookEN, chapter) {
    if (!els.refLabel) return;
    els.refLabel.textContent = bookEN ? `${bookLabel(bookEN)} ${chapter}` : (I18N.bible || tr('Bybel', 'Bible'));
  }

  // ===== LOADING OVERLAY =====
  function createLoadingOverlay() {
    const overlay = document.createElement('div');
    overlay.id = 'bibleLoadingOverlay';
    overlay.innerHTML = `
      <div class="bible-loading-container">
        <div class="bible-loading-spinner"></div>
        <h2 class="bible-loading-title">${tr('Laai Bybel...', 'Loading Bible...')}</h2>
        <div class="bible-loading-bar">
          <div class="bible-loading-progress" id="loadingProgress"></div>
        </div>
        <p class="bible-loading-text" id="loadingText">0%</p>
        <p class="bible-loading-subtext" id="loadingSubtext"></p>
      </div>
    `;
    document.body.appendChild(overlay);
    return overlay;
  }

  function updateLoadingProgress(percent, text, subtext) {
    const progress = $('loadingProgress');
    const textEl = $('loadingText');
    const subtextEl = $('loadingSubtext');
    
    if (progress) progress.style.width = `${percent}%`;
    if (textEl) textEl.textContent = text || `${Math.round(percent)}%`;
    if (subtextEl && subtext) subtextEl.textContent = subtext;
  }

  function removeLoadingOverlay() {
    const overlay = $('bibleLoadingOverlay');
    if (overlay) {
      overlay.style.opacity = '0';
      setTimeout(() => overlay.remove(), 300);
    }
  }

  // ===== DATA LOADING =====
  async function loadJSON(url, onProgress) {
    const cacheKey = bibleCacheKey(url);
    
    const cached = await getFromDB(cacheKey);
    if (cached) {
      if (onProgress) onProgress(100);
      return cached;
    }

    try {
      const res = await fetch(url, { 
        credentials: 'same-origin',
        cache: 'force-cache'
      });
      
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      
      const contentLength = +res.headers.get('Content-Length');
      const reader = res.body.getReader();
      
      let receivedLength = 0;
      let chunks = [];
      
      while(true) {
        const {done, value} = await reader.read();
        if (done) break;
        
        chunks.push(value);
        receivedLength += value.length;
        
        if (onProgress && contentLength) {
          onProgress((receivedLength / contentLength) * 100);
        }
      }
      
      const chunksAll = new Uint8Array(receivedLength);
      let position = 0;
      for(let chunk of chunks) {
        chunksAll.set(chunk, position);
        position += chunk.length;
      }
      
      const text = new TextDecoder("utf-8").decode(chunksAll);
      const data = JSON.parse(text);

      // Answered by the Android app from its own copy (OfflineAssets.kt): it
      // always has it, so 5 MB need not be stored twice. Offline it may even
      // be an older text lent for a newer ?v=, which must never be kept
      // under that version.
      if (!res.headers.get('X-OAC-App-Copy')) await saveToDB(cacheKey, data);
      
      return data;
      
    } catch (e) {
      console.error(`Failed to load ${url}:`, e);
      throw e;
    }
  }

  function extractBooks(data) {
    if (!data) return [];
    if (Array.isArray(data.books)) {
      return data.books.map(b => b.name || b.book || 'Unknown');
    }
    if (typeof data === 'object') {
      return Object.keys(data);
    }
    return [];
  }

  // Normalize a book name for tolerant matching: strip diacritics, lowercase,
  // collapse internal whitespace. Used only as a fallback so renamed/mismatched
  // keys (e.g. 'Efesiers' vs 'Efésiërs') still resolve and never produce a
  // spurious "Geen hoofstukke gevind nie."
  function normalizeBookName(name) {
    return (name || '')
      .toString()
      .normalize('NFD')
      .replace(/\p{Diacritic}/gu, '')
      .toLowerCase()
      .replace(/\s+/g, ' ')
      .trim();
  }

  // Cache of normalized-key -> original-key maps, one per data object. WeakMap so
  // the index is built once per loaded JSON and freed with the data object.
  const _normKeyIndexCache = new WeakMap();
  function getNormalizedKeyIndex(data) {
    let index = _normKeyIndexCache.get(data);
    if (index) return index;
    index = Object.create(null);
    for (const key of Object.keys(data)) {
      const norm = normalizeBookName(key);
      if (!(norm in index)) index[norm] = key; // first write wins, preserves order
    }
    _normKeyIndexCache.set(data, index);
    return index;
  }

  function getBook(data, bookName) {
    if (!data || !bookName) return null;
    if (Array.isArray(data.books)) {
      return data.books.find(b => (b.name || b.book) === bookName);
    }
    // Fast path: exact object-key match (current behavior).
    if (typeof data[bookName] === 'object' && data[bookName] !== null) {
      return data[bookName];
    }
    // Fallback: diacritic/case/space-tolerant match against the JSON keys.
    if (typeof data === 'object') {
      const matchedKey = getNormalizedKeyIndex(data)[normalizeBookName(bookName)];
      if (matchedKey && typeof data[matchedKey] === 'object' && data[matchedKey] !== null) {
        return data[matchedKey];
      }
    }
    return null;
  }

  function getChapterCount(data, bookName) {
    const book = getBook(data, bookName);
    if (!book) return 0;
    
    if (Array.isArray(book.chapters)) return book.chapters.length;
    if (Array.isArray(book.chapter)) return book.chapter.length;
    
    const numericKeys = Object.keys(book).filter(k => /^\d+$/.test(k));
    return numericKeys.length;
  }

  function getChapter(data, bookName, chapterNum) {
    const book = getBook(data, bookName);
    if (!book) return [];
    
    if (Array.isArray(book.chapters)) return book.chapters[chapterNum - 1] || [];
    if (Array.isArray(book.chapter)) return book.chapter[chapterNum - 1] || [];
    
    const chKey = String(chapterNum);
    return book[chKey] || [];
  }

  function parseVerse(item) {
    if (!item) return { type: 'verse', text: '' };
    
    if (typeof item === 'string') {
      return { type: 'verse', text: item };
    }
    
    if (typeof item === 'object') {
      if (item.h !== undefined) return { type: 'heading', text: String(item.h) };
      if (item.v !== undefined) return { type: 'verse', text: String(item.v) };
      if (item.text !== undefined) return { type: 'verse', text: String(item.text) };
      if (item.verse !== undefined) return { type: 'verse', text: String(item.verse) };
      if (item.t !== undefined) return { type: 'verse', text: String(item.t) };
      
      const vals = Object.values(item);
      if (vals.length > 0) return { type: 'verse', text: String(vals[0]) };
    }
    
    return { type: 'verse', text: String(item) };
  }

  function makeRef(bookEN, chapter, verse) {
    return `${bookEN}-${chapter}-${verse}`;
  }

  function parseRef(ref) {
    if (!ref || typeof ref !== 'string') return { bookEN: '', chapter: 0, verse: 0 };
    const lastDash = ref.lastIndexOf('-');
    const secondLastDash = ref.lastIndexOf('-', lastDash - 1);
    if (lastDash === -1 || secondLastDash === -1) return { bookEN: '', chapter: 0, verse: 0 };
    return {
      bookEN: ref.substring(0, secondLastDash),
      chapter: parseInt(ref.substring(secondLastDash + 1, lastDash), 10) || 0,
      verse: parseInt(ref.substring(lastDash + 1), 10) || 0
    };
  }

  // ===== HEADER UPDATE =====
  function updateHeaderRef() {
    const headerTitle = document.querySelector('.ghf-title');
    if (!headerTitle) return;

    // Only consider verses inside the left (native-language) column.
    // The right column may contain English duplicates that can confuse
    // the "top visible verse" detection, especially on mobile where the
    // right column is hidden via display:none but still part of the DOM.
    const leftColumn = els.leftColumn;
    if (!leftColumn) return;

    const verses = leftColumn.querySelectorAll('.bible-verse[data-verse]');
    if (!verses.length) {
      headerTitle.textContent = tr('Bybel', 'Bible');
      setRefLabel(null);
      return;
    }

    const colRect = leftColumn.getBoundingClientRect();
    // Anchor reference point a bit below the top of the visible column
    const anchorY = colRect.top + 80;
    const colBottom = colRect.bottom;

    let topVerse = null;
    let minDist = Infinity;

    verses.forEach(v => {
      const rect = v.getBoundingClientRect();
      // Verse must be within the column's visible bounds
      if (rect.bottom <= colRect.top || rect.top >= colBottom) return;
      const dist = Math.abs(rect.top - anchorY);
      if (dist < minDist) {
        minDist = dist;
        topVerse = v;
      }
    });

    if (topVerse) {
      const bookEN = topVerse.dataset.booken;
      const chapter = topVerse.dataset.chapter;
      const verse = topVerse.dataset.verse;

      // Onthou die posisie wat die leser werklik sien, sodat taalwisseling
      // hierheen terugkeer en nie na die laaste vooraf-gelaaide hoofstuk nie.
      state.viewRef = makeRef(bookEN, parseInt(chapter, 10) || 1, parseInt(verse, 10) || 1);

      const displayName = state.lang === 'af' ? EN_TO_AF_BOOKS[bookEN] || bookEN : bookEN;
      headerTitle.textContent = `${displayName} ${chapter}:${verse}`;
      setRefLabel(bookEN, chapter);
      store.set(LAST_REF_KEY, state.viewRef);

      // How far into this chapter: the top verse out of the chapter's verses
      if (els.progress) {
        const block = topVerse.closest('.bible-chapter-block');
        const total = block ? block.querySelectorAll('.bible-verse').length : 0;
        const p = total ? Math.min(1, (parseInt(verse, 10) || 1) / total) : 0;
        els.progress.style.setProperty('--p', p.toFixed(3));
      }
    } else {
      headerTitle.textContent = tr('Bybel', 'Bible');
    }
  }

  // ===== PROGRESSIVE RENDERING =====
  function renderInitialChapters() {
    state.currentBookIndex = 0;
    state.currentChapter = 1;
    state.earliestBookIndex = 0;
    state.earliestChapter = 1;
    state.renderedChapters.clear();

    els.leftContent.innerHTML = '';
    els.rightContent.innerHTML = '';

    // Render first 5 chapters synchronously for instant content
    renderChaptersBatch(0, 1, 5);

    applyFontSize();
    bindVerseInteractions();
    updateHeaderRef();
  }

  function loadNextChapters(count = 5) {
    if (state.isLoading) return;
    state.isLoading = true;

    const rendered = renderChaptersBatch(
      state.currentBookIndex,
      state.currentChapter + 1,
      count
    );

    if (rendered > 0) {
      bindVerseInteractions();
      applyFontSize();
    }

    state.isLoading = false;
  }

  function loadPreviousChapters(count = 3) {
    if (state.isLoadingPrev) return;
    state.isLoadingPrev = true;

    const gen = state.loadGeneration;
    let loaded = 0;
    let bookIdx = state.earliestBookIndex;
    let chapter = state.earliestChapter - 1;
    const chapters = [];

    while (loaded < count) {
      if (gen !== state.loadGeneration) { state.isLoadingPrev = false; return; }

      if (chapter < 1) {
        bookIdx--;
        if (bookIdx < 0) break;
        const bookEN = state.booksEN[bookIdx];
        const bookAF = state.booksAF[bookIdx];
        const dataForLeft = state.lang === 'af' ? state.dataAF : state.dataEN;
        const bookForLeft = state.lang === 'af' ? bookAF : bookEN;
        chapter = getChapterCount(dataForLeft, bookForLeft);
        if (chapter <= 0) continue;
      }

      const bookEN = state.booksEN[bookIdx];
      const bookAF = state.booksAF[bookIdx];
      const bookForLeft = state.lang === 'af' ? bookAF : bookEN;
      const bookENForLeft = state.lang === 'af' ? (AF_TO_EN_BOOKS[bookAF] || bookEN) : bookEN;

      const leftKey = `${bookEN}-${chapter}-left`;
      const rightKey = `${bookEN}-${chapter}-right`;

      if (!state.renderedChapters.has(leftKey)) {
        const leftEl = createChapterElement(bookENForLeft, bookForLeft, chapter, 'left');
        const rightEl = createChapterElement(bookEN, bookEN, chapter, 'right');
        chapters.unshift({ leftEl, rightEl, leftKey, rightKey, bookIdx, chapter });
        loaded++;
      }

      chapter--;
    }

    if (chapters.length > 0) {
      // Save scroll positions before prepending
      const scrollTopL = els.leftColumn.scrollTop;
      const scrollHeightL = els.leftColumn.scrollHeight;
      const scrollTopR = els.rightColumn.scrollTop;
      const scrollHeightR = els.rightColumn.scrollHeight;

      // Use DocumentFragment to prepend chapters in correct chronological order.
      // Direct insertBefore(el, firstChild) in a loop reverses the order.
      const leftFrag = document.createDocumentFragment();
      const rightFrag = document.createDocumentFragment();
      chapters.forEach(ch => {
        leftFrag.appendChild(ch.leftEl);
        rightFrag.appendChild(ch.rightEl);
        state.renderedChapters.add(ch.leftKey);
        state.renderedChapters.add(ch.rightKey);
      });
      els.leftContent.insertBefore(leftFrag, els.leftContent.firstChild);
      els.rightContent.insertBefore(rightFrag, els.rightContent.firstChild);

      // Restore scroll position so the view doesn't jump
      const diffL = els.leftColumn.scrollHeight - scrollHeightL;
      const diffR = els.rightColumn.scrollHeight - scrollHeightR;
      els.leftColumn.scrollTop = scrollTopL + diffL;
      els.rightColumn.scrollTop = scrollTopR + diffR;

      // Update earliest tracking
      const earliest = chapters[0];
      state.earliestBookIndex = earliest.bookIdx;
      state.earliestChapter = earliest.chapter;

      bindVerseInteractions();
      applyFontSize();
    }

    state.isLoadingPrev = false;
  }

  function createChapterElement(bookEN, bookDisplay, chapter, side) {
    let data, bookName;
    
    if (side === 'left') {
      data = state.lang === 'af' ? state.dataAF : state.dataEN;
      bookName = state.lang === 'af' ? bookDisplay : bookEN;
    } else {
      data = state.dataEN;
      bookName = bookEN;
    }

    const chapterDiv = document.createElement('div');
    chapterDiv.className = 'bible-chapter-block';
    chapterDiv.dataset.booken = bookEN;
    chapterDiv.dataset.chapter = chapter;
    chapterDiv.dataset.side = side;

    // Book name small, chapter number large (read as "Johannes 3")
    const chTitle = document.createElement('h3');
    chTitle.className = 'bible-chapter-title';
    const chBook = document.createElement('span');
    chBook.className = 'bible-chapter-book';
    chBook.textContent = bookDisplay;
    const chNum = document.createElement('span');
    chNum.className = 'bible-chapter-num';
    chNum.textContent = String(chapter);
    chTitle.append(chBook, chNum);
    chTitle.setAttribute('aria-label', `${bookDisplay} ${chapter}`);
    chapterDiv.appendChild(chTitle);

    const verses = getChapter(data, bookName, chapter);
    let verseNum = 0;

    verses.forEach(v => {
      const parsed = parseVerse(v);
      if (parsed.type === 'heading') {
        const h = document.createElement('div');
        h.className = 'bible-heading';
        h.textContent = parsed.text;
        chapterDiv.appendChild(h);
      } else {
        verseNum++;
        const ref = makeRef(bookEN, chapter, verseNum);
        const vDiv = createVerseElement(bookEN, bookDisplay, chapter, verseNum, parsed.text, ref);
        chapterDiv.appendChild(vDiv);
      }
    });

    return chapterDiv;
  }

  function createVerseElement(bookEN, bookDisplay, chapter, verseNum, text, ref) {
    const vDiv = document.createElement('div');
    vDiv.className = 'bible-verse';
    vDiv.dataset.ref = ref;
    vDiv.dataset.booken = bookEN;
    vDiv.dataset.bookdisplay = bookDisplay;
    vDiv.dataset.chapter = chapter;
    vDiv.dataset.verse = verseNum;

    if (state.highlights[ref]) {
      vDiv.classList.add(`bible-highlight-${state.highlights[ref]}`);
    }

    const numSpan = document.createElement('span');
    numSpan.className = 'bible-verse-number';
    numSpan.textContent = verseNum;

    const textSpan = document.createElement('span');
    textSpan.className = 'bible-verse-text';
    textSpan.textContent = text;

    vDiv.appendChild(numSpan);
    vDiv.appendChild(textSpan);
    addIndicators(vDiv, ref);

    return vDiv;
  }

  // Small bookmark / note markers after the verse text
  function addIndicators(vDiv, ref) {
    if (state.bookmarks[ref]) {
      const bookmarkIcon = document.createElement('span');
      bookmarkIcon.className = 'bible-bookmark-indicator';
      bookmarkIcon.innerHTML = ICON.bookmark;
      bookmarkIcon.title = tr('Geboekmerk', 'Bookmarked');
      vDiv.appendChild(bookmarkIcon);
    }

    if (state.notes[ref]) {
      const noteIcon = document.createElement('span');
      noteIcon.className = 'bible-note-indicator';
      noteIcon.innerHTML = ICON.note;
      noteIcon.title = tr('Klik om nota te sien', 'Click to view note');
      noteIcon.dataset.ref = ref;
      vDiv.appendChild(noteIcon);
    }
  }

  // ===== INFINITE SCROLL =====
  function checkChapterContinuity() {
    const blocks = els.leftContent.querySelectorAll('.bible-chapter-block[data-side="left"]');
    let prevBook = null;
    let prevChapter = 0;

    blocks.forEach(block => {
      const bookEN = block.dataset.booken;
      const ch = parseInt(block.dataset.chapter, 10);

      if (prevBook === bookEN && ch !== prevChapter + 1) {
        console.error(`[BIBLE GAP] ${bookEN}: chapter ${prevChapter} → ${ch} (missing ${prevChapter + 1} to ${ch - 1})`);
      }

      prevBook = bookEN;
      prevChapter = ch;
    });
  }

  function setupInfiniteScroll() {
    let scrollTimeout = null;
    let checkTimeout = null;

    const handleScroll = () => {
      clearTimeout(scrollTimeout);
      scrollTimeout = setTimeout(() => {
        const column = els.leftColumn;
        const scrollHeight = column.scrollHeight;
        const scrollTop = column.scrollTop;
        const clientHeight = column.clientHeight;

        // Load more chapters when near the bottom
        if (scrollHeight - scrollTop - clientHeight < 1000) {
          loadNextChapters(5);
        }

        // Load previous chapters when near the top
        if (scrollTop < 800) {
          loadPreviousChapters(3);
        }

        updateHeaderRef();

        // Periodically check for gaps
        clearTimeout(checkTimeout);
        checkTimeout = setTimeout(checkChapterContinuity, 2000);
      }, 100);
    };

    els.leftColumn.addEventListener('scroll', handleScroll, { passive: true });
    els.rightColumn.addEventListener('scroll', handleScroll, { passive: true });
  }

  // ===== DUAL SCROLL SYNC =====
  let dualScrollSyncInitialized = false;
  function setupDualScrollSync() {
    if (!els.leftColumn || !els.rightColumn) return;
    if (dualScrollSyncInitialized) return;
    dualScrollSyncInitialized = true;

    let syncTimeout = null;

    function syncByVerse(sourceColumn, targetColumn) {
      if (state.syncingScroll) return;

      state.syncingScroll = true;

      // Find the verse closest to the top of the visible area
      const sourceVerses = sourceColumn.querySelectorAll('.bible-verse');
      if (!sourceVerses.length) {
        state.syncingScroll = false;
        return;
      }

      const columnTop = sourceColumn.getBoundingClientRect().top + 100;
      let closestVerse = null;
      let minDistance = Infinity;

      sourceVerses.forEach(verse => {
        const rect = verse.getBoundingClientRect();
        const distance = Math.abs(rect.top - columnTop);

        if (distance < minDistance) {
          minDistance = distance;
          closestVerse = verse;
        }
      });

      if (closestVerse) {
        const ref = closestVerse.dataset.ref;

        // Find matching verse in target column
        const targetVerse = targetColumn.querySelector(`.bible-verse[data-ref="${ref}"]`);

        if (targetVerse) {
          const sourceRect = closestVerse.getBoundingClientRect();
          const sourceOffset = sourceRect.top - sourceColumn.getBoundingClientRect().top;

          const targetRect = targetVerse.getBoundingClientRect();
          const currentTargetOffset = targetRect.top - targetColumn.getBoundingClientRect().top;

          targetColumn.scrollTop += (currentTargetOffset - sourceOffset);
        }
      }

      if (syncTimeout) clearTimeout(syncTimeout);
      syncTimeout = setTimeout(() => {
        state.syncingScroll = false;
      }, 50);
    }

    els.leftColumn.addEventListener('scroll', () => {
      if (state.dualViewEnabled && !state.syncingScroll) {
        syncByVerse(els.leftColumn, els.rightColumn);
      }
    }, { passive: true });

    els.rightColumn.addEventListener('scroll', () => {
      if (state.dualViewEnabled && !state.syncingScroll) {
        syncByVerse(els.rightColumn, els.leftColumn);
      }
    }, { passive: true });
  }

  // ===== DUAL VIEW CONTROL =====
  function updateDualView() {
    if (!els.dualContainer || !els.leftColumn || !els.rightColumn) return;
    
    if (state.dualViewEnabled) {
      els.dualContainer.classList.remove('bible-single-view');
      els.dualContainer.classList.add('bible-dual-view');
      els.rightColumn.style.display = 'block';
    } else {
      els.dualContainer.classList.remove('bible-dual-view');
      els.dualContainer.classList.add('bible-single-view');
      els.rightColumn.style.display = 'none';
    }

    setupDualScrollSync();

    // Line the English column up with where you are reading right away,
    // instead of waiting for the next scroll
    if (state.dualViewEnabled) {
      requestAnimationFrame(() => els.leftColumn.dispatchEvent(new Event('scroll')));
    }
  }

  // ===== VERSE INTERACTIONS =====
  // One listener per column (thousands of verses get rendered while you
  // scroll; a listener on each of them adds up)
  let verseDelegationReady = false;
  function bindVerseInteractions() {
    if (verseDelegationReady) return;
    verseDelegationReady = true;
    [els.leftContent, els.rightContent].forEach(host => {
      if (!host) return;
      host.addEventListener('click', handleVerseClick);
      host.addEventListener('contextmenu', handleVerseClick);
    });
  }

  function handleVerseClick(e) {
    const verse = e.target.closest('.bible-verse');
    if (!verse) return;
    e.preventDefault();

    const noteMark = e.target.closest('.bible-note-indicator');
    if (noteMark) {
      showNoteEditor(noteMark.dataset.ref);
      showPanel(els.notesPanel);
      return;
    }

    document.querySelectorAll('.bible-verse.selected').forEach(v => v.classList.remove('selected'));
    document.querySelectorAll(`.bible-verse[data-ref="${CSS.escape(verse.dataset.ref)}"]`).forEach(v => v.classList.add('selected'));
    state.selectedVerse = verse.dataset.ref;

    showContextMenu(verse);
  }

  // The verse sheet: reference + text at the top, then colours and actions
  let ctxReturnFocus = null;
  function showContextMenu(verse) {
    const menu = els.verseContextMenu;
    if (!menu) return;
    hideDisplayPanel();

    const ref = state.selectedVerse;
    const parsed = parseRef(ref);
    const text = verse ? (verse.querySelector('.bible-verse-text') || {}).textContent || '' : '';
    if (els.ctxRef) els.ctxRef.textContent = `${bookLabel(parsed.bookEN)} ${parsed.chapter}:${parsed.verse}`;
    if (els.ctxPreview) els.ctxPreview.textContent = text;

    // Mark the colour it already has, and whether it is bookmarked
    const current = state.highlights[ref] || 0;
    menu.querySelectorAll('.bible-color-btn').forEach(btn => {
      const isCurrent = parseInt(btn.dataset.color, 10) === current && current !== 0;
      btn.classList.toggle('is-current', isCurrent);
      btn.setAttribute('aria-pressed', String(isCurrent));
    });
    if (els.ctxBookmark) {
      const on = !!state.bookmarks[ref];
      els.ctxBookmark.classList.toggle('is-on', on);
      els.ctxBookmark.setAttribute('aria-pressed', String(on));
    }
    if (els.ctxAddNote) els.ctxAddNote.classList.toggle('is-on', !!state.notes[ref]);

    ctxReturnFocus = document.activeElement;
    els.verseScrim?.classList.remove('bible-context-hidden');
    menu.classList.remove('bible-context-hidden');
    menu.scrollTop = 0;
    menu.focus({ preventScroll: true });
  }

  function hideContextMenu() {
    if (!els.verseContextMenu || els.verseContextMenu.classList.contains('bible-context-hidden')) return;
    els.verseContextMenu.classList.add('bible-context-hidden');
    els.verseScrim?.classList.add('bible-context-hidden');
    document.querySelectorAll('.bible-verse.selected').forEach(v => v.classList.remove('selected'));
    if (ctxReturnFocus && ctxReturnFocus.focus && document.body.contains(ctxReturnFocus)) {
      ctxReturnFocus.focus({ preventScroll: true });
    }
  }

  // ===== HIGHLIGHTS =====
  async function applyHighlight(color) {
    if (!state.selectedVerse) return;

    try {
      const res = await fetch('/bible/api/highlights/save.php', {
        method: 'POST',
        headers: apiHeaders(),
        credentials: 'same-origin',
        body: JSON.stringify({
          verse_ref: state.selectedVerse,
          color: color
        })
      });

      const data = await res.json();

      if (data.success) {
        if (color === 0) {
          delete state.highlights[state.selectedVerse];
        } else {
          state.highlights[state.selectedVerse] = color;
        }
        
        refreshVerseDisplay();
      } else {
        throw new Error(data.error);
      }
    } catch (e) {
      console.error('Highlight save failed:', e);
      toast(saveFailText('Kon nie die kleur stoor nie', 'Could not save highlight'), true);
    }

    hideContextMenu();
  }

  // ===== BOOKMARKS =====
  async function toggleBookmark() {
    if (!state.selectedVerse) return;
    
    const verse = document.querySelector(`[data-ref="${state.selectedVerse}"]`);
    const verseText = verse?.querySelector('.bible-verse-text')?.textContent || '';
    
    try {
      const res = await fetch('/bible/api/bookmarks/save.php', {
        method: 'POST',
        headers: apiHeaders(),
        credentials: 'same-origin',
        body: JSON.stringify({
          verse_ref: state.selectedVerse,
          verse_text: verseText,
          action: 'toggle'
        })
      });

      const data = await res.json();

      if (data.success) {
        if (data.bookmarked) {
          state.bookmarks[state.selectedVerse] = {
            text: verseText,
            timestamp: Date.now()
          };
        } else {
          delete state.bookmarks[state.selectedVerse];
        }
        
        refreshVerseDisplay();
        renderBookmarksList();
        toast(data.bookmarked ? tr('Geboekmerk', 'Bookmarked') : tr('Boekmerk verwyder', 'Bookmark removed'));
      } else {
        throw new Error(data.error);
      }
    } catch (e) {
      console.error('Bookmark toggle failed:', e);
      toast(saveFailText('Kon nie boekmerk stoor nie', 'Could not save bookmark'), true);
    }

    hideContextMenu();
  }

  function renderBookmarksList() {
    if (!els.bookmarksList) return;
    
    const refs = Object.keys(state.bookmarks).sort((a, b) => {
      return state.bookmarks[b].timestamp - state.bookmarks[a].timestamp;
    });
    
    if (!refs.length) {
      els.bookmarksList.innerHTML = `<p class="bible-empty-state">${tr('Geen boekmerke nog nie.', 'No bookmarks yet.')}</p>`;
      return;
    }
    
    const frag = document.createDocumentFragment();
    
    refs.forEach(ref => {
      const parsed = parseRef(ref);
      const displayName = state.lang === 'af' ? (EN_TO_AF_BOOKS[parsed.bookEN] || parsed.bookEN) : parsed.bookEN;
      const bookmark = state.bookmarks[ref];
      
      const item = document.createElement('div');
      item.className = 'bible-bookmark-item';
      item.innerHTML = `
        <div class="bible-bookmark-ref">${esc(displayName)} ${parsed.chapter}:${parsed.verse}</div>
        <div class="bible-bookmark-text">${esc((bookmark.text || '').substring(0, 140))}${(bookmark.text || '').length > 140 ? '…' : ''}</div>
        <div class="bible-bookmark-item-actions">
          <button type="button" class="bible-btn-small bible-bookmark-delete" title="${esc(tr('Verwyder boekmerk', 'Delete bookmark'))}" aria-label="${esc(tr('Verwyder boekmerk', 'Delete bookmark'))}">${ICON.trash}</button>
        </div>
      `;

      item.addEventListener('click', () => {
        goToReference(ref);
        hidePanel(els.bookmarksPanel);
      });

      item.querySelector('.bible-bookmark-delete').addEventListener('click', async (e) => {
        e.stopPropagation();
        if (!(await confirmSheet(tr('Verwyder hierdie boekmerk?', 'Delete this bookmark?')))) return;
        try {
          const res = await fetch('/bible/api/bookmarks/save.php', {
            method: 'POST',
            headers: apiHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify({
              verse_ref: ref,
              action: 'remove'
            })
          });

          const data = await res.json();

          if (data.success) {
            delete state.bookmarks[ref];
            renderBookmarksList();
            refreshVerseDisplay();
          } else {
            throw new Error(data.error);
          }
        } catch (err) {
          console.error('Bookmark delete failed:', err);
          toast(saveFailText('Kon nie boekmerk verwyder nie', 'Could not delete bookmark'), true);
        }
      });
      
      frag.appendChild(item);
    });
    
    els.bookmarksList.innerHTML = '';
    els.bookmarksList.appendChild(frag);
  }

  function renderChaptersBatch(startBookIdx, startChapter, count) {
    let bookIdx = startBookIdx;
    let chapter = startChapter;
    let rendered = 0;

    while (rendered < count && bookIdx < state.booksEN.length) {
      const bookEN = state.booksEN[bookIdx];
      const bookAF = state.booksAF[bookIdx];
      const bookForLeft = state.lang === 'af' ? bookAF : bookEN;
      // Derive the English label from the AF book actually being rendered so
      // dataset.booken (and thus the scroll header) always matches the displayed
      // content, even if booksEN/booksAF ever drift in order or count.
      const bookENForLeft = state.lang === 'af' ? (AF_TO_EN_BOOKS[bookAF] || bookEN) : bookEN;
      const dataForLeft = state.lang === 'af' ? state.dataAF : state.dataEN;
      const chapterCount = getChapterCount(dataForLeft, bookForLeft);

      if (chapter > chapterCount) {
        bookIdx++;
        chapter = 1;
        continue;
      }

      const leftKey = `${bookEN}-${chapter}-left`;
      const rightKey = `${bookEN}-${chapter}-right`;

      if (!state.renderedChapters.has(leftKey)) {
        els.leftContent.appendChild(createChapterElement(bookENForLeft, bookForLeft, chapter, 'left'));
        state.renderedChapters.add(leftKey);
      }
      if (!state.renderedChapters.has(rightKey)) {
        els.rightContent.appendChild(createChapterElement(bookEN, bookEN, chapter, 'right'));
        state.renderedChapters.add(rightKey);
      }

      rendered++;
      chapter++;
    }

    // Update state to reflect what we loaded
    if (rendered > 0) {
      state.currentBookIndex = bookIdx;
      state.currentChapter = chapter - 1;
    }

    return rendered;
  }

  // opts.chapterOnly: land on the chapter heading (chapter picker, resume)
  // instead of pulsing verse 1
  function goToReference(ref, opts) {
    const chapterOnly = !!(opts && opts.chapterOnly);
    const resume = !!(opts && opts.resume);
    const parsed = parseRef(ref);
    if (!parsed.bookEN || !parsed.chapter) return;

    const bookIdx = state.booksEN.indexOf(parsed.bookEN);
    if (bookIdx === -1) return;

    // Cancel any in-progress idle loading
    state.loadGeneration++;
    state.isLoading = false;
    state.isLoadingPrev = false;

    // Clear everything and start fresh from this position
    els.leftContent.innerHTML = '';
    els.rightContent.innerHTML = '';
    state.renderedChapters.clear();

    // Set position tracking (both current and earliest)
    state.currentBookIndex = bookIdx;
    state.currentChapter = parsed.chapter;
    state.earliestBookIndex = bookIdx;
    state.earliestChapter = parsed.chapter;

    // Render the target chapter + next 4 chapters synchronously
    // This guarantees no gaps from async race conditions
    renderChaptersBatch(bookIdx, parsed.chapter, 5);

    bindVerseInteractions();
    applyFontSize();

    // Set header directly from the known target — don't wait for scroll
    // detection, which can mis-fire while the smooth scroll is in progress.
    const headerTitle = document.querySelector('.ghf-title');
    if (headerTitle) {
      const displayName = state.lang === 'af'
        ? (EN_TO_AF_BOOKS[parsed.bookEN] || parsed.bookEN)
        : parsed.bookEN;
      headerTitle.textContent = `${displayName} ${parsed.chapter}:${parsed.verse || 1}`;
    }
    setRefLabel(parsed.bookEN, parsed.chapter);
    store.set(LAST_REF_KEY, makeRef(parsed.bookEN, parsed.chapter, parsed.verse || 1));

    // Scroll to the specific verse
    setTimeout(() => {
      const verseEl = els.leftContent.querySelector(`.bible-verse[data-ref="${CSS.escape(ref)}"]`);
      if (verseEl && (chapterOnly || (resume && parsed.verse <= 1))) {
        els.leftColumn.scrollTop = 0;
        if (els.rightColumn) els.rightColumn.scrollTop = 0;
      } else if (verseEl && resume) {
        // Back where you were reading: that verse at the top, no fuss
        els.leftColumn.scrollTop = Math.max(0, verseEl.offsetTop - 70);
      } else if (verseEl) {
        verseEl.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'center' });
        verseEl.classList.add('bible-verse-flash');
        setTimeout(() => verseEl.classList.remove('bible-verse-flash'), 2000);
      } else {
        els.leftColumn.scrollTop = 0;
        if (els.rightColumn) els.rightColumn.scrollTop = 0;
      }
      // Refresh after the smooth scroll has settled so the title matches
      // wherever the user actually landed (in case scroll snaps elsewhere).
      setTimeout(updateHeaderRef, 700);
    }, 100);
  }

  // ===== NOTES =====
  function renderNotesList() {
    if (!els.notesList) return;
    
    // In Bible order, so notes read like a journal through Scripture
    const order = (r) => {
      const pr = parseRef(r);
      return state.booksEN.indexOf(pr.bookEN) * 1e6 + pr.chapter * 1e3 + pr.verse;
    };
    const refs = Object.keys(state.notes).sort((a, b) => order(a) - order(b));

    if (!refs.length) {
      els.notesList.innerHTML = `<p class="bible-empty-state">${tr('Geen notas nog nie. Klik op \'n vers om \'n nota by te voeg!', 'No notes yet. Click on a verse to add a note!')}</p>`;
      return;
    }

    const frag = document.createDocumentFragment();

    refs.forEach(ref => {
      const parsed = parseRef(ref);
      const displayName = state.lang === 'af' ? (EN_TO_AF_BOOKS[parsed.bookEN] || parsed.bookEN) : parsed.bookEN;
      
      const noteItem = document.createElement('div');
      noteItem.className = 'bible-note-item';
      noteItem.innerHTML = `
        <div class="bible-note-item-ref">${esc(displayName)} ${parsed.chapter}:${parsed.verse}</div>
        <div class="bible-note-item-text">${esc(state.notes[ref])}</div>
        <div class="bible-note-item-actions">
          <button type="button" class="bible-btn-small bible-note-edit" title="${esc(tr('Wysig', 'Edit'))}" aria-label="${esc(tr('Wysig', 'Edit'))}">${ICON.note}</button>
          <button type="button" class="bible-btn-small bible-note-delete" title="${esc(tr('Verwyder', 'Delete'))}" aria-label="${esc(tr('Verwyder', 'Delete'))}">${ICON.trash}</button>
        </div>
      `;

      // Tapping the note takes you to its verse; the buttons edit / delete
      noteItem.addEventListener('click', (e) => {
        if (e.target.closest('.bible-note-item-actions')) return;
        goToReference(ref);
        hidePanel(els.notesPanel);
      });

      noteItem.querySelector('.bible-note-edit').addEventListener('click', () => {
        showNoteEditor(ref);
      });

      noteItem.querySelector('.bible-note-delete').addEventListener('click', async () => {
        if (await confirmSheet(tr('Verwyder hierdie nota?', 'Delete this note?'))) {
          try {
            const res = await fetch('/bible/api/notes/save.php', {
              method: 'POST',
              headers: apiHeaders(),
              credentials: 'same-origin',
              body: JSON.stringify({
                verse_ref: ref,
                note_text: ''
              })
            });

            const data = await res.json();

            if (data.success) {
              delete state.notes[ref];
              renderNotesList();
              refreshVerseDisplay();
            } else {
              throw new Error(data.error);
            }
          } catch (e) {
            console.error('Note delete failed:', e);
            toast(saveFailText('Kon nie nota verwyder nie', 'Could not delete note'), true);
          }
        }
      });

      frag.appendChild(noteItem);
    });

    els.notesList.innerHTML = '';
    els.notesList.appendChild(frag);
  }

  function showNoteEditor(ref) {
    if (!els.noteReference || !els.noteText || !els.notesList || !els.noteEditor) return;
    
    state.selectedVerse = ref;
    const parsed = parseRef(ref);
    const displayName = state.lang === 'af' ? (EN_TO_AF_BOOKS[parsed.bookEN] || parsed.bookEN) : parsed.bookEN;
    
    els.noteReference.textContent = `${displayName} ${parsed.chapter}:${parsed.verse}`;
    els.noteText.value = state.notes[ref] || '';
    els.notesList.classList.add('bible-note-hidden');
    els.noteEditor.classList.remove('bible-note-hidden');
  }

  async function saveNote() {
    if (!state.selectedVerse || !els.noteText) return;
    
    const text = els.noteText.value.trim();
    
    try {
      const res = await fetch('/bible/api/notes/save.php', {
        method: 'POST',
        headers: apiHeaders(),
        credentials: 'same-origin',
        body: JSON.stringify({
          verse_ref: state.selectedVerse,
          note_text: text
        })
      });

      const data = await res.json();

      if (data.success) {
        if (text) {
          state.notes[state.selectedVerse] = text;
        } else {
          delete state.notes[state.selectedVerse];
        }
        
        els.noteEditor?.classList.add('bible-note-hidden');
        els.notesList?.classList.remove('bible-note-hidden');
        renderNotesList();
        refreshVerseDisplay();
        toast(text ? tr('Nota gestoor', 'Note saved') : tr('Nota verwyder', 'Note removed'));
      } else {
        throw new Error(data.error);
      }
    } catch (e) {
      console.error('Note save failed:', e);
      toast(saveFailText('Kon nie nota stoor nie', 'Could not save note'), true);
    }
  }

  function cancelNote() {
    els.noteEditor?.classList.add('bible-note-hidden');
    els.notesList?.classList.remove('bible-note-hidden');
  }

  function addNoteToVerse() {
    if (!state.selectedVerse) {
      toast(tr('Kies eers \'n vers', 'Select a verse first'), true);
      return;
    }
    hideContextMenu();
    showPanel(els.notesPanel);
    showNoteEditor(state.selectedVerse);
    setTimeout(() => els.noteText && els.noteText.focus(), 350);
  }

  // ===== NAVIGATION =====
  let navReturnFocus = null;
  function showQuickNav() {
    if (!els.quickNavModal) return;
    hideContextMenu();
    hideDisplayPanel();
    hideAllPanels();
    navReturnFocus = document.activeElement;
    els.quickNavModal.classList.remove('bible-modal-hidden');
    state.navState = { testament: null, book: null, chapter: null };
    showNavStep('testament');
    els.quickNavModal.querySelector('.bible-modal-content')?.focus({ preventScroll: true });
  }

  function hideQuickNav() {
    if (!els.quickNavModal || els.quickNavModal.classList.contains('bible-modal-hidden')) return;
    els.quickNavModal.classList.add('bible-modal-hidden');
    if (navReturnFocus && navReturnFocus.focus) navReturnFocus.focus({ preventScroll: true });
  }

  function currentBookEN() {
    return state.viewRef ? parseRef(state.viewRef).bookEN : '';
  }

  function showNavStep(step) {
    els.navStepTestament?.classList.toggle('bible-nav-hidden', step !== 'testament');
    els.navStepBook?.classList.toggle('bible-nav-hidden', step !== 'book');
    els.navStepChapter?.classList.toggle('bible-nav-hidden', step !== 'chapter');
    const body = els.quickNavModal?.querySelector('.bible-modal-body');
    if (body) body.scrollTop = 0;
    if (step === 'book' && els.navBookFilter) {
      els.navBookFilter.value = '';
      // Only pop the keyboard where there is a real keyboard
      if (window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
        els.navBookFilter.focus({ preventScroll: true });
      }
    }
  }

  // Narrow the book grid as you type ("kor" -> 1 & 2 Korinthiërs)
  function filterBooks() {
    const q = normalizeBookName(els.navBookFilter ? els.navBookFilter.value : '');
    els.navBookGrid?.querySelectorAll('.bible-nav-card').forEach(card => {
      card.hidden = !!q && card.dataset.search.indexOf(q) === -1;
    });
  }

  function renderTestamentChoice() {
    document.querySelectorAll('[data-testament]').forEach(btn => {
      btn.addEventListener('click', () => {
        state.navState.testament = btn.dataset.testament;
        renderBookChoice();
        showNavStep('book');
      });
    });
  }

  function renderBookChoice() {
    const OLD_TESTAMENT_EN = [
      'Genesis', 'Exodus', 'Leviticus', 'Numbers', 'Deuteronomy',
      'Joshua', 'Judges', 'Ruth', '1 Samuel', '2 Samuel', '1 Kings', '2 Kings',
      '1 Chronicles', '2 Chronicles', 'Ezra', 'Nehemiah', 'Esther',
      'Job', 'Psalms', 'Proverbs', 'Ecclesiastes', 'Song of Solomon',
      'Isaiah', 'Jeremiah', 'Lamentations', 'Ezekiel', 'Daniel',
      'Hosea', 'Joel', 'Amos', 'Obadiah', 'Jonah', 'Micah', 'Nahum',
      'Habakkuk', 'Zephaniah', 'Haggai', 'Zechariah', 'Malachi'
    ];

    const NEW_TESTAMENT_EN = [
      'Matthew', 'Mark', 'Luke', 'John', 'Acts',
      'Romans', '1 Corinthians', '2 Corinthians', 'Galatians', 'Ephesians',
      'Philippians', 'Colossians', '1 Thessalonians', '2 Thessalonians',
      '1 Timothy', '2 Timothy', 'Titus', 'Philemon',
      'Hebrews', 'James', '1 Peter', '2 Peter', '1 John', '2 John', '3 John',
      'Jude', 'Revelation'
    ];
    
    const booksEN = state.navState.testament === 'old' ? OLD_TESTAMENT_EN : NEW_TESTAMENT_EN;
    const title = state.navState.testament === 'old' 
      ? (tr('Ou Testament Boeke', 'Old Testament Books'))
      : (tr('Nuwe Testament Boeke', 'New Testament Books'));
    
    if (!els.navBookTitle || !els.navBookGrid) return;
    
    els.navBookTitle.textContent = title;
    els.navBookGrid.innerHTML = '';

    const frag = document.createDocumentFragment();
    
    const data = state.lang === 'af' ? state.dataAF : state.dataEN;
    const here = currentBookEN();

    booksEN.forEach(bookEN => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'bible-nav-card bible-nav-card-small bible-nav-card--' + state.navState.testament;
      if (bookEN === here) btn.classList.add('is-current');

      const displayName = state.lang === 'af' ? (EN_TO_AF_BOOKS[bookEN] || bookEN) : bookEN;
      const count = getChapterCount(data, state.lang === 'af' ? (EN_TO_AF_BOOKS[bookEN] || bookEN) : bookEN);
      const nameEl = document.createElement('span');
      nameEl.className = 'bible-nav-book';
      nameEl.textContent = displayName;
      const countEl = document.createElement('span');
      countEl.className = 'bible-nav-count';
      countEl.textContent = `${count} ${count === 1 ? tr('hoofstuk', 'chapter') : tr('hoofstukke', 'chapters')}`;
      btn.append(nameEl, countEl);
      btn.dataset.search = normalizeBookName(displayName + ' ' + bookEN);

      btn.addEventListener('click', () => {
        state.navState.book = bookEN;
        renderChapterChoice();
        showNavStep('chapter');
      });
      
      frag.appendChild(btn);
    });

    els.navBookGrid.appendChild(frag);
  }

  function renderChapterChoice() {
    if (!state.navState.book) return;
    
    const data = state.lang === 'af' ? state.dataAF : state.dataEN;
    const bookNameForData = state.lang === 'af' 
      ? (EN_TO_AF_BOOKS[state.navState.book] || state.navState.book)
      : state.navState.book;
    
    const chapterCount = getChapterCount(data, bookNameForData);
    
    const displayName = state.lang === 'af' 
      ? (EN_TO_AF_BOOKS[state.navState.book] || state.navState.book)
      : state.navState.book;
    
    if (!els.navChapterTitle || !els.navChapterGrid) return;
    
    els.navChapterTitle.textContent = `${displayName} · ${tr('Kies hoofstuk', 'Choose chapter')}`;
    els.navChapterGrid.innerHTML = '';

    if (chapterCount === 0) {
      els.navChapterGrid.innerHTML = `<p class="bible-empty-state">${tr('Geen hoofstukke gevind nie.', 'No chapters found.')}</p>`;
      return;
    }

    const frag = document.createDocumentFragment();
    
    const hereRef = state.viewRef ? parseRef(state.viewRef) : null;
    for (let i = 1; i <= chapterCount; i++) {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'bible-nav-card bible-nav-card-small bible-nav-card--' + (state.navState.testament || 'old');
      if (hereRef && hereRef.bookEN === state.navState.book && hereRef.chapter === i) btn.classList.add('is-current');
      btn.textContent = String(i);
      
      btn.addEventListener('click', () => {
        state.navState.chapter = i;
        goToChapter(state.navState.book, i);
      });
      
      frag.appendChild(btn);
    }

    els.navChapterGrid.appendChild(frag);
  }

  function goToChapter(bookEN, chapter) {
    hideQuickNav();
    const ref = makeRef(bookEN, chapter, 1);
    goToReference(ref, { chapterOnly: true });
  }

  // ===== SEARCH =====
  // ---------- SEARCH HELPERS ----------
  function normalizeSearch(s) {
    return (s || '')
      .toString()
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[\u2018\u2019\u02BC']/g, '')
      .replace(/[^\w\s:]/g, ' ')
      .replace(/\s+/g, ' ')
      .trim();
  }

  function escapeRegex(s) {
    return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  }

  function highlightMatches(text, terms) {
    const raw = String(text || '');
    if (!terms || !terms.length) return esc(raw);
    const parts = terms.filter(Boolean).map(escapeRegex);
    if (!parts.length) return esc(raw);
    // Match teen die rou teks en ontsnap elke stuk afsonderlik, sodat
    // soekterme soos "amp" nooit binne HTML-entiteite kan merk nie.
    const re = new RegExp(`(${parts.join('|')})`, 'gi');
    return raw.split(re)
      .map((seg, i) => i % 2 ? `<mark class="bible-search-hl">${esc(seg)}</mark>` : esc(seg))
      .join('');
  }

  // Build a compact snippet centered around the first match so you
  // don't just see the start of long verses.
  function makeSnippet(text, terms, radius = 80) {
    if (!text) return '';
    if (!terms.length) return text.substring(0, 160) + (text.length > 160 ? '…' : '');
    const normText = normalizeSearch(text);
    let firstIdx = -1;
    for (const t of terms) {
      const n = normalizeSearch(t);
      if (!n) continue;
      const i = normText.indexOf(n);
      if (i !== -1 && (firstIdx === -1 || i < firstIdx)) firstIdx = i;
    }
    if (firstIdx === -1) {
      return text.substring(0, 160) + (text.length > 160 ? '…' : '');
    }
    // Map the normalized index back to the raw string roughly by
    // scaling — close enough for snippet windowing.
    const ratio = firstIdx / Math.max(normText.length, 1);
    const rawCenter = Math.round(ratio * text.length);
    const start = Math.max(0, rawCenter - radius);
    const end = Math.min(text.length, rawCenter + radius + 40);
    let snippet = text.substring(start, end);
    if (start > 0) snippet = '…' + snippet;
    if (end < text.length) snippet = snippet + '…';
    return snippet;
  }

  // Parse "joh 3:16", "genesis 1", "1 kor 13:4", "psalm 23", etc.
  function parseReference(q) {
    const norm = normalizeSearch(q);
    // book chapter[:verse]
    const m = norm.match(/^(\d?\s*[a-z]+(?:\s+[a-z]+){0,2})\s+(\d+)(?:\s*[:\.]\s*(\d+))?$/);
    if (!m) return null;
    const bookGuess = m[1].replace(/\s+/g, ' ').trim();
    const chapter = parseInt(m[2], 10);
    const verse = m[3] ? parseInt(m[3], 10) : null;
    if (!chapter) return null;

    const candidates = [];
    for (let i = 0; i < state.booksAF.length; i++) {
      const af = normalizeSearch(state.booksAF[i]);
      const en = normalizeSearch(state.booksEN[i]);
      let score = 0;
      if (af === bookGuess || en === bookGuess) score = 100;
      else if (af.startsWith(bookGuess) || en.startsWith(bookGuess)) score = 80;
      else if (af.includes(bookGuess) || en.includes(bookGuess)) score = 50;
      else {
        // token prefix match: "1 kor" vs "1 korintiers"
        const gTokens = bookGuess.split(' ');
        const afTokens = af.split(' ');
        const enTokens = en.split(' ');
        const matches = (tokens) => gTokens.every((g, idx) =>
          tokens[idx] && tokens[idx].startsWith(g));
        if (matches(afTokens) || matches(enTokens)) score = 70;
      }
      if (score > 0) candidates.push({ idx: i, score });
    }
    if (!candidates.length) return null;
    candidates.sort((a, b) => b.score - a.score);
    return { bookIndex: candidates[0].idx, chapter, verse };
  }

  // Every verse once, already normalised: built on the first search, then
  // each search is a quick scan instead of re-normalising the whole Bible
  function getSearchIndex() {
    const key = state.lang === 'af' ? 'af' : 'en';
    if (state.searchIndex[key]) return state.searchIndex[key];

    const searchData = key === 'af' ? state.dataAF : state.dataEN;
    const searchBooks = key === 'af' ? state.booksAF : state.booksEN;
    const entries = [];

    for (let idx = 0; idx < searchBooks.length; idx++) {
      const bookName = searchBooks[idx];
      const chapterCount = getChapterCount(searchData, bookName);
      for (let ch = 1; ch <= chapterCount; ch++) {
        const verses = getChapter(searchData, bookName, ch);
        let verseNum = 0;
        for (let v = 0; v < verses.length; v++) {
          const parsed = parseVerse(verses[v]);
          if (parsed.type !== 'verse') continue;
          verseNum++;
          entries.push({ idx, ch, v: verseNum, text: parsed.text, norm: normalizeSearch(parsed.text) });
        }
      }
    }

    state.searchIndex[key] = entries;
    return entries;
  }

  function renderEmptyState(msg) {
    els.searchResults.innerHTML = `<p class="bible-empty-state">${esc(msg)}</p>`;
  }

  function renderReferenceHit(parsed) {
    const bookEN = state.booksEN[parsed.bookIndex];
    const bookAF = state.booksAF[parsed.bookIndex];
    const displayName = state.lang === 'af' ? bookAF : bookEN;
    const verse = parsed.verse || 1;
    const refLabel = `${displayName} ${parsed.chapter}${parsed.verse ? ':' + parsed.verse : ''}`;
    const hint = tr('Spring direk na hierdie gedeelte', 'Jump straight to this passage');

    const frag = document.createDocumentFragment();

    const countRow = document.createElement('div');
    countRow.className = 'bible-search-count';
    countRow.textContent = tr('Verwysing gevind', 'Reference match');
    frag.appendChild(countRow);

    const row = document.createElement('div');
    row.className = 'bible-search-result-item bible-search-ref-hit';
    row.innerHTML = `
      <div class="bible-search-result-ref">${esc(refLabel)}</div>
      <div class="bible-search-result-text">${esc(hint)}</div>
    `;
    row.addEventListener('click', () => {
      const ref = makeRef(bookEN, parsed.chapter, verse);
      goToReference(ref);
      hidePanel(els.searchPanel);
    });
    frag.appendChild(row);

    els.searchResults.innerHTML = '';
    els.searchResults.appendChild(frag);
  }

  function runFullTextSearch(rawQuery) {
    const normQuery = normalizeSearch(rawQuery);
    if (!normQuery) {
      els.searchResults.innerHTML = '';
      return;
    }

    // Tokenise — require ALL words to appear (any order)
    const terms = normQuery.split(' ').filter(t => t.length >= 2);
    if (!terms.length) {
      renderEmptyState(tr('Tik ten minste 2 karakters.', 'Type at least 2 characters.'));
      return;
    }

    const HARD_LIMIT = 300;
    const results = [];
    const index = getSearchIndex();

    for (let i = 0; i < index.length; i++) {
      const e = index[i];
      // AND across all terms
      let allMatch = true;
      for (const t of terms) {
        if (e.norm.indexOf(t) === -1) { allMatch = false; break; }
      }
      if (!allMatch) continue;

      // Score: exact phrase match wins, then number of distinct terms
      let score = 0;
      if (e.norm.indexOf(normQuery) !== -1) score += 100;
      score += terms.length * 10;

      results.push({
        bookEN: state.booksEN[e.idx], bookAF: state.booksAF[e.idx],
        chapter: e.ch, verse: e.v, text: e.text, score
      });

      if (results.length >= HARD_LIMIT) break;
    }

    if (!results.length) {
      renderEmptyState(tr('Geen resultate gevind nie.', 'No results found.'));
      return;
    }

    results.sort((a, b) => b.score - a.score);
    const DISPLAY_LIMIT = 100;
    const shown = results.slice(0, DISPLAY_LIMIT);

    const frag = document.createDocumentFragment();

    const countRow = document.createElement('div');
    countRow.className = 'bible-search-count';
    const total = `${results.length}${results.length >= HARD_LIMIT ? '+' : ''}`;
    const countMsg = tr(
      `${total} resultaat${results.length === 1 ? '' : 'e'}` + (shown.length < results.length ? ` — wys eerste ${shown.length}` : ''),
      `${total} result${results.length === 1 ? '' : 's'}` + (shown.length < results.length ? ` — showing first ${shown.length}` : '')
    );
    countRow.textContent = countMsg;
    frag.appendChild(countRow);

    shown.forEach(hit => {
      const displayName = state.lang === 'af' ? hit.bookAF : hit.bookEN;
      const snippet = makeSnippet(hit.text, terms, 80);
      const row = document.createElement('div');
      row.className = 'bible-search-result-item';
      row.innerHTML = `
        <div class="bible-search-result-ref">${esc(displayName)} ${hit.chapter}:${hit.verse}</div>
        <div class="bible-search-result-text">${highlightMatches(snippet, terms)}</div>
      `;
      row.addEventListener('click', () => {
        const ref = makeRef(hit.bookEN, hit.chapter, hit.verse);
        goToReference(ref);
        hidePanel(els.searchPanel);
      });
      frag.appendChild(row);
    });

    els.searchResults.innerHTML = '';
    els.searchResults.appendChild(frag);
  }

  let searchDebounceTimer = null;

  function handleSearch() {
    if (!els.searchResults) return;
    const raw = (els.searchInput?.value || '').trim();
    if (!raw) { els.searchResults.innerHTML = ''; return; }

    // Try reference parsing first — typing "joh 3:16" jumps straight in
    const ref = parseReference(raw);
    if (ref) {
      renderReferenceHit(ref);
      return;
    }

    // Fall back to full-text search
    runFullTextSearch(raw);
  }

  function handleSearchLive() {
    clearTimeout(searchDebounceTimer);
    const raw = (els.searchInput?.value || '').trim();
    if (!els.searchResults) return;
    if (!raw) { els.searchResults.innerHTML = ''; return; }
    if (raw.length < 2) {
      renderEmptyState(tr('Tik aan…', 'Keep typing…'));
      return;
    }
    els.searchResults.innerHTML = `<div class="bible-loading">${esc(tr('Soek…', 'Searching…'))}</div>`;
    searchDebounceTimer = setTimeout(() => handleSearch(), 180);
  }

  // ===== AI & CROSS REFS =====
  async function showAIPrompt() {
    if (!state.selectedVerse) {
      toast(tr('Kies eers \'n vers', 'Select a verse first'), true);
      return;
    }

    const verse = document.querySelector(`[data-ref="${state.selectedVerse}"]`);
    const verseText = verse?.querySelector('.bible-verse-text')?.textContent || '';
    const parsed = parseRef(state.selectedVerse);
    const displayName = state.lang === 'af' ? (EN_TO_AF_BOOKS[parsed.bookEN] || parsed.bookEN) : parsed.bookEN;
    const verseRef = `${displayName} ${parsed.chapter}:${parsed.verse}`;

    hideContextMenu();

    if (!els.aiPanel || !els.aiOutput) return;

    const loadingMsg = tr('AI lees die verse rondom hierdie gedeelte en vertel die storie...', 'AI is reading the surrounding verses and telling the story...');
    els.aiOutput.innerHTML = `<div class="bible-loading">${loadingMsg}</div>`;
    showPanel(els.aiPanel);

    try {
      const res = await fetch('/bible/api/ai_commentary.php', {
        method: 'POST',
        headers: apiHeaders(),
        credentials: 'same-origin',
        body: JSON.stringify({
          verse_ref: verseRef,
          verse_text: verseText,
          book_en: parsed.bookEN,
          chapter: parsed.chapter,
          verse: parsed.verse,
          lang: state.lang
        })
      });

      const data = await res.json();

      if (data.success) {
        // Parse markdown-like response into styled sections
        const raw = data.answer;
        // Split into sections by **HEADING:** pattern
        const sections = raw.split(/\*\*([^*]+)\*\*/g);
        let html = '';
        for (let i = 0; i < sections.length; i++) {
          const part = sections[i].trim();
          if (!part) continue;
          // Check if next part is content (odd indices are headings, even are content after split)
          if (i % 2 === 1) {
            // This is a heading
            html += `<div class="bible-ai-section"><div class="bible-ai-heading">${esc(part)}</div>`;
          } else if (html) {
            // This is content following a heading
            const escaped = esc(part).replace(/\n/g, '<br>');
            // Convert inline quotes (text between "") to styled spans
            const withQuotes = escaped.replace(/&quot;([^&]*?)&quot;/g,
              '<span class="bible-ai-quote">"$1"</span>');
            html += `<div class="bible-ai-content">${withQuotes}</div></div>`;
          } else {
            // Content before any heading (intro text)
            html += `<div class="bible-ai-intro">${esc(part).replace(/\n/g, '<br>')}</div>`;
          }
        }
        els.aiOutput.innerHTML = `
          <div class="bible-ai-response">
            <div class="bible-ai-verse-ref">${esc(verseRef)}</div>
            <div class="bible-ai-context-note">${esc(tr(
              'Gebaseer op ' + (data.context_verses || 20) + ' omliggende verse',
              'Based on ' + (data.context_verses || 20) + ' surrounding verses'))}</div>
            <div class="bible-ai-answer">${html}</div>
          </div>
        `;
      } else {
        throw new Error(data.error);
      }
    } catch (e) {
      els.aiOutput.innerHTML = `<p class="bible-error">${esc(onlineOnlyText('Kon nie kommentaar kry nie.', 'Could not get commentary.'))}</p>`;
      console.error('AI Commentary error:', e);
    }
  }

  async function loadCrossReferences() {
    if (!state.selectedVerse) return;
    
    const parsed = parseRef(state.selectedVerse);
    const cacheKey = `${parsed.bookEN}-${parsed.chapter}-${parsed.verse}`;
    
    hideContextMenu();
    
    if (state.crossReferences[cacheKey]) {
      displayCrossReferences(state.crossReferences[cacheKey]);
      return;
    }
    
    try {
      const res = await fetch(
        `/bible/api/cross_references.php?book=${encodeURIComponent(parsed.bookEN)}&chapter=${parsed.chapter}&verse=${parsed.verse}`,
        { credentials: 'same-origin' }
      );
      
      const data = await res.json();
      
      if (data.success && data.references) {
        state.crossReferences[cacheKey] = data.references;
        displayCrossReferences(data.references);
      } else {
        throw new Error(data.error || 'Cross references failed');
      }
    } catch (e) {
      console.error('Cross references error:', e);
      // Wys darem die paneel met 'n boodskap i.p.v. om stilweg niks te doen nie.
      if (els.crossRefPanel && els.crossRefList) {
        els.crossRefList.innerHTML = `<p class="bible-empty-state">${esc(onlineOnlyText('Kon nie kruisverwysings laai nie.', 'Could not load cross-references.'))}</p>`;
        showPanel(els.crossRefPanel);
      }
    }
  }

  function displayCrossReferences(refs) {
    if (!els.crossRefPanel || !els.crossRefList) return;
    
    if (!refs.length) {
      els.crossRefList.innerHTML = `<p class="bible-empty-state">${tr('Geen kruisverwysings nie.', 'No cross-references.')}</p>`;
      showPanel(els.crossRefPanel);
      return;
    }
    
    const frag = document.createDocumentFragment();
    
    refs.forEach(ref => {
      const displayName = state.lang === 'af' ? (EN_TO_AF_BOOKS[ref.to_book] || ref.to_book) : ref.to_book;
      const item = document.createElement('div');
      item.className = 'bible-cross-ref-item';
      item.innerHTML = `
        <div class="bible-cross-ref-title">${esc(displayName)} ${ref.to_chapter}:${ref.to_verse}</div>
        <div class="bible-cross-ref-type">${esc(ref.relationship_type)}</div>
      `;
      
      item.addEventListener('click', () => {
        const verseRef = makeRef(ref.to_book, ref.to_chapter, ref.to_verse);
        goToReference(verseRef);
        hidePanel(els.crossRefPanel);
      });
      
      frag.appendChild(item);
    });
    
    els.crossRefList.innerHTML = '';
    els.crossRefList.appendChild(frag);
    showPanel(els.crossRefPanel);
  }

  // ===== UTILITIES =====
  function selectedVerseInfo() {
    const verse = document.querySelector(`.bible-verse[data-ref="${CSS.escape(state.selectedVerse)}"]`);
    return {
      text: verse?.querySelector('.bible-verse-text')?.textContent || '',
      label: `${verse?.dataset.bookdisplay || ''} ${verse?.dataset.chapter || ''}:${verse?.dataset.verse || ''}`.trim()
    };
  }

  function copyVerse() {
    if (!state.selectedVerse) return;
    const info = selectedVerseInfo();

    copyText(`${info.label} - ${info.text}`).then(() => {
      toast(tr('Vers gekopieer', 'Verse copied'));
    }).catch(() => {
      toast(tr('Kon nie kopieer nie', 'Failed to copy'), true);
    });

    hideContextMenu();
  }

  function shareVerse() {
    if (!state.selectedVerse) return;
    const info = selectedVerseInfo();
    const shareUrl = `${window.location.origin}/bible/bible.php?ref=${encodeURIComponent(state.selectedVerse)}`;

    if (navigator.share) {
      navigator.share({
        title: info.label,
        text: `${info.label} - ${info.text}`,
        url: shareUrl
      }).catch(() => {});
    } else {
      // No share sheet (desktop, older WebViews): copy the verse with its link
      copyText(`${info.label} - ${info.text}\n${shareUrl}`).then(() => {
        toast(tr('Vers en skakel gekopieer', 'Verse and link copied'));
      }).catch(() => {
        toast(tr('Kon nie deel nie', 'Could not share'), true);
      });
    }

    hideContextMenu();
  }

  // ===== READING COMFORT: text size + typeface (remembered) =====
  const FONT_STEPS = 5;

  function changeFontSize(direction) {
    const next = state.fontSize + (direction === 'increase' ? 1 : -1);
    state.fontSize = Math.max(0, Math.min(FONT_STEPS - 1, next));
    store.set('bible_font_size', String(state.fontSize));
    applyFontSize();
  }

  function setFontFace(face) {
    state.fontFace = face === 'sans' ? 'sans' : 'serif';
    store.set('bible_font_face', state.fontFace);
    applyFontSize();
  }

  // One attribute on the reading area; the stylesheet does the rest
  // (no inline style on every verse)
  function applyFontSize() {
    if (els.reading) {
      els.reading.dataset.size = String(state.fontSize);
      els.reading.dataset.face = state.fontFace;
    }
    if (els.fontSizeDecrease) els.fontSizeDecrease.disabled = state.fontSize <= 0;
    if (els.fontSizeIncrease) els.fontSizeIncrease.disabled = state.fontSize >= FONT_STEPS - 1;
    if (els.fontSizeSteps) {
      Array.from(els.fontSizeSteps.children).forEach((dot, i) => {
        dot.classList.toggle('is-on', i <= state.fontSize);
        dot.classList.toggle('is-current', i === state.fontSize);
      });
    }
    document.querySelectorAll('.bible-face-btn').forEach(btn => {
      btn.setAttribute('aria-pressed', String(btn.dataset.face === state.fontFace));
    });
  }

  function loadReadingPrefs() {
    const size = parseInt(store.get('bible_font_size'), 10);
    if (!isNaN(size)) state.fontSize = Math.max(0, Math.min(FONT_STEPS - 1, size));
    state.fontFace = store.get('bible_font_face') === 'sans' ? 'sans' : 'serif';
    applyFontSize();
  }

  function showDisplayPanel() {
    if (!els.displayPanel) return;
    hideContextMenu();
    hideAllPanels();
    els.displayPanel.classList.remove('bible-context-hidden');
    els.displayToggle?.setAttribute('aria-expanded', 'true');
  }

  function hideDisplayPanel() {
    if (!els.displayPanel || els.displayPanel.classList.contains('bible-context-hidden')) return;
    els.displayPanel.classList.add('bible-context-hidden');
    els.displayToggle?.setAttribute('aria-expanded', 'false');
  }

  function refreshVerseDisplay() {
    document.querySelectorAll('.bible-verse').forEach(verse => {
      const ref = verse.dataset.ref;

      verse.className = 'bible-verse';

      if (state.highlights[ref]) {
        verse.classList.add(`bible-highlight-${state.highlights[ref]}`);
      }

      verse.querySelectorAll('.bible-note-indicator, .bible-bookmark-indicator').forEach(n => n.remove());
      addIndicators(verse, ref);
    });

    // Keep the open verse marked
    if (state.selectedVerse && els.verseContextMenu && !els.verseContextMenu.classList.contains('bible-context-hidden')) {
      document.querySelectorAll(`.bible-verse[data-ref="${CSS.escape(state.selectedVerse)}"]`).forEach(v => v.classList.add('selected'));
    }
  }

  // ===== DATA PERSISTENCE =====
  async function loadUserData() {
    if (!state.userId) return;

    try {
      const res = await fetch('/bible/api/load_all.php', {
        method: 'GET',
        credentials: 'same-origin'
      });

      if (!res.ok) throw new Error(`HTTP ${res.status}`);

      const data = await res.json();

      if (data.success) {
        state.highlights = data.highlights || {};
        state.notes = data.notes || {};
        state.bookmarks = data.bookmarks || {};
        
        refreshVerseDisplay();
      }
    } catch (e) {
      console.error('Load error:', e);
    }
  }

  // ===== PANEL MANAGEMENT =====
  function showPanel(panel) {
    if (!panel) return;
    hideAllPanels();
    hideDisplayPanel();
    panel.classList.remove('bible-panel-hidden');
    if (panel === els.searchPanel && els.searchInput) {
      setTimeout(() => els.searchInput.focus({ preventScroll: true }), 300);
    }
  }

  function hidePanel(panel) {
    if (!panel) return;
    panel.classList.add('bible-panel-hidden');
  }

  function hideAllPanels() {
    const panels = [
      els.searchPanel,
      els.notesPanel,
      els.bookmarksPanel,
      els.aiPanel,
      els.crossRefPanel
    ];
    
    panels.forEach(panel => {
      if (panel) panel.classList.add('bible-panel-hidden');
    });
  }

  function togglePanel(panel) {
    if (!panel) return;
    
    if (panel.classList.contains('bible-panel-hidden')) {
      showPanel(panel);
    } else {
      hidePanel(panel);
    }
  }

  // ===== EVENT BINDINGS =====
  function bindEvents() {
    els.quickNavToggle?.addEventListener('click', showQuickNav);
    els.quickNavClose?.addEventListener('click', hideQuickNav);
    els.quickNavOverlay?.addEventListener('click', hideQuickNav);
    els.navBackToTestament?.addEventListener('click', () => showNavStep('testament'));
    els.navBackToBook?.addEventListener('click', () => showNavStep('book'));
    renderTestamentChoice();

    els.searchToggle?.addEventListener('click', () => togglePanel(els.searchPanel));
    els.notesToggle?.addEventListener('click', () => {
      togglePanel(els.notesPanel);
      renderNotesList();
    });
    els.bookmarksToggle?.addEventListener('click', () => {
      togglePanel(els.bookmarksPanel);
      renderBookmarksList();
    });

    els.searchClose?.addEventListener('click', () => hidePanel(els.searchPanel));
    els.notesClose?.addEventListener('click', () => hidePanel(els.notesPanel));
    els.bookmarksClose?.addEventListener('click', () => hidePanel(els.bookmarksPanel));
    els.aiClose?.addEventListener('click', () => hidePanel(els.aiPanel));
    els.crossRefClose?.addEventListener('click', () => hidePanel(els.crossRefPanel));

    els.searchBtn?.addEventListener('click', handleSearch);
    els.searchInput?.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        clearTimeout(searchDebounceTimer);
        handleSearch();
      }
    });
    els.searchInput?.addEventListener('input', handleSearchLive);

    els.saveNoteBtn?.addEventListener('click', saveNote);
    els.cancelNoteBtn?.addEventListener('click', cancelNote);

    document.querySelectorAll('.bible-color-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const color = parseInt(btn.dataset.color, 10);
        applyHighlight(color);
      });
    });

    els.ctxBookmark?.addEventListener('click', toggleBookmark);
    els.ctxAddNote?.addEventListener('click', addNoteToVerse);
    els.ctxAI?.addEventListener('click', showAIPrompt);
    els.ctxCrossRef?.addEventListener('click', loadCrossReferences);
    els.ctxCopy?.addEventListener('click', copyVerse);
    els.ctxShare?.addEventListener('click', shareVerse);

    els.fontSizeIncrease?.addEventListener('click', () => changeFontSize('increase'));
    els.fontSizeDecrease?.addEventListener('click', () => changeFontSize('decrease'));
    document.querySelectorAll('.bible-face-btn').forEach(btn => {
      btn.addEventListener('click', () => setFontFace(btn.dataset.face));
    });
    els.displayToggle?.addEventListener('click', () => {
      if (els.displayPanel && els.displayPanel.classList.contains('bible-context-hidden')) showDisplayPanel();
      else hideDisplayPanel();
    });

    els.ctxClose?.addEventListener('click', hideContextMenu);
    els.verseScrim?.addEventListener('click', hideContextMenu);
    els.navBookFilter?.addEventListener('input', filterBooks);

    document.addEventListener('click', (e) => {
      if (els.verseContextMenu && !els.verseContextMenu.contains(e.target) && !e.target.closest('.bible-verse')) {
        hideContextMenu();
      }
    });

    document.addEventListener('click', (e) => {
      if (els.displayPanel && !e.target.closest('#displayPanel') && !e.target.closest('#displayToggle')) {
        hideDisplayPanel();
      }
      if (e.target.closest('.bible-panel') || e.target.closest('.bible-tool-btn') || e.target.closest('.bible-context-menu') ||
          e.target.closest('.bible-confirm') || e.target.closest('.bible-display') || e.target.closest('.bible-toolbar')) {
        return;
      }
      const openPanels = [
        els.searchPanel,
        els.notesPanel,
        els.bookmarksPanel,
        els.aiPanel,
        els.crossRefPanel
      ].filter(p => p && !p.classList.contains('bible-panel-hidden'));
      if (openPanels.length > 0) {
        hideAllPanels();
      }
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        hideContextMenu();
        hideDisplayPanel();
        hideQuickNav();
        hideAllPanels();
      }
    });

    window.addEventListener('resize', updateDualView);

    window.addEventListener('bibleViewToggle', function(e) {
      state.dualViewEnabled = e.detail.enabled;
      updateDualView();
    });

    window.addEventListener('languageChanged', (e) => {
      if (e.detail && e.detail.lang) {
        handleLanguageChange(e.detail.lang);
      }
    });
  }

  // ===== INITIALIZATION =====
  function isValidRef(ref) {
    const pr = parseRef(ref);
    if (!pr.bookEN || pr.chapter < 1 || state.booksEN.indexOf(pr.bookEN) === -1) return false;
    const data = state.lang === 'af' ? state.dataAF : state.dataEN;
    const book = state.lang === 'af' ? (EN_TO_AF_BOOKS[pr.bookEN] || pr.bookEN) : pr.bookEN;
    return pr.chapter <= getChapterCount(data, book);
  }

  function showLoadError(e) {
    const msg = tr('Kon nie die Bybel laai nie.', 'Could not load the Bible.');
    els.leftContent.innerHTML = `
      <div class="bible-empty-state">
        <p>${esc(msg)}</p>
        <p><button type="button" class="bible-btn bible-btn-primary" id="bibleRetry">${esc(tr('Probeer weer', 'Try again'))}</button></p>
      </div>`;
    const retry = document.getElementById('bibleRetry');
    if (retry) retry.addEventListener('click', () => window.location.reload());
    console.error('Initialization failed:', e);
  }

  async function init() {
    createLoadingOverlay();
    loadReadingPrefs();

    try {
      // The offline cache is a bonus: private mode / blocked storage just means no cache
      try {
        await initDB();
      } catch (e) {
        db = null;
      }
      updateLoadingProgress(10, '10%', tr('Laai Bybel...', 'Loading Bible...'));

      els.quickNavModal?.classList.add('bible-modal-hidden');

      let progressAF = 0, progressEN = 0;
      const updateCombinedProgress = () => {
        const combined = 10 + ((progressAF + progressEN) / 2) * 0.7;
        const label = progressAF < 100
          ? (tr('Laai Afrikaans...', 'Loading Afrikaans...'))
          : (tr('Laai Engels...', 'Loading English...'));
        updateLoadingProgress(combined, '', label);
      };
      const [dataAF, dataEN] = await Promise.all([
        loadJSON(state.paths.af, (p) => { progressAF = p; updateCombinedProgress(); }),
        loadJSON(state.paths.en, (p) => { progressEN = p; updateCombinedProgress(); })
      ]);

      state.dataAF = dataAF;
      state.dataEN = dataEN;
      pruneOldBibles([bibleCacheKey(state.paths.af), bibleCacheKey(state.paths.en)]);
      state.booksAF = extractBooks(state.dataAF);
      state.booksEN = extractBooks(state.dataEN);

      updateLoadingProgress(85, '85%', tr('Laai jou notas...', 'Loading your notes...'));

      await loadUserData();

      // Open where asked (?ref=Luke-1-1), else where you stopped reading,
      // else at the beginning
      const urlRef = new URLSearchParams(window.location.search).get('ref');
      const savedRef = store.get(LAST_REF_KEY);
      let resumed = false;

      if (urlRef && isValidRef(urlRef)) {
        goToReference(urlRef);
      } else if (savedRef && isValidRef(savedRef)) {
        goToReference(savedRef, { resume: true });
        resumed = true;
      } else {
        renderInitialChapters();
      }
      setupInfiniteScroll();
      bindVerseInteractions();

      updateLoadingProgress(100, '100%', tr('Gereed!', 'Ready!'));

      // Read initial dual view state from localStorage BEFORE binding events
      state.dualViewEnabled = store.get('bible_dual_view') === '1';

      bindEvents();
      updateHeaderRef();
      updateDualView();

      setTimeout(() => {
        removeLoadingOverlay();
        if (resumed) {
          const pr = parseRef(savedRef);
          toast(`${tr('Terug waar jy was', 'Back where you were')}: ${bookLabel(pr.bookEN)} ${pr.chapter}:${pr.verse || 1}`);
        }
      }, 150);

      // Build the search index while the reader is reading, not when they search
      const warm = () => { try { getSearchIndex(); } catch (e) { /* built on first search instead */ } };
      if ('requestIdleCallback' in window) window.requestIdleCallback(warm, { timeout: 8000 });
      else setTimeout(warm, 4000);

    } catch (e) {
      removeLoadingOverlay();
      showLoadError(e);
    }
  }

  init();
})();