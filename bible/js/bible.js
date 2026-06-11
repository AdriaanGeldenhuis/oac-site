(() => {
  'use strict';

  console.log('%c[BIBLE v4] Synchronous batch rendering loaded', 'color: lime; font-weight: bold');

  // ===== UTILITIES =====
  const $ = (id) => document.getElementById(id);
  const esc = (s) => String(s || '').replace(/[&<>"']/g, m => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  })[m]);

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
    dataAF: null,
    dataEN: null,
    booksAF: [],
    booksEN: [],
    
    selectedVerse: null,
    highlights: {},
    notes: {},
    bookmarks: {},
    crossReferences: {},
    fontSize: 'medium',
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

  // ===== LANGUAGE CHANGE HANDLER =====
  function handleLanguageChange(newLang) {
    if (newLang === state.lang) return;

    const overlay = createLoadingOverlay();

    // Save current position before switching
    const savedBookIdx = state.currentBookIndex;
    const savedChapter = state.currentChapter;

    state.lang = newLang;

    setTimeout(async () => {
      try {
        updateLoadingProgress(50, '50%', state.lang === 'af' ? 'Laai Bybel...' : 'Loading Bible...');

        // Navigate to the saved position in the new language
        const bookEN = state.booksEN[savedBookIdx] || 'Genesis';
        const ref = makeRef(bookEN, savedChapter || 1, 1);
        goToReference(ref);
        refreshVerseDisplay();

        updateLoadingProgress(100, '100%', state.lang === 'af' ? 'Gereed!' : 'Ready!');

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
    fontSizeDecrease: $('fontSizeDecrease')
  };

  // ===== LOADING OVERLAY =====
  function createLoadingOverlay() {
    const overlay = document.createElement('div');
    overlay.id = 'bibleLoadingOverlay';
    overlay.innerHTML = `
      <div class="bible-loading-container">
        <div class="bible-loading-spinner"></div>
        <h2 class="bible-loading-title">${state.lang === 'af' ? 'Laai Bybel...' : 'Loading Bible...'}</h2>
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
    const cacheKey = `bible_v6_${url}`;
    
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
      
      await saveToDB(cacheKey, data);
      
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
      headerTitle.textContent = state.lang === 'en' ? 'Bible' : 'Bybel';
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

      const displayName = state.lang === 'af' ? EN_TO_AF_BOOKS[bookEN] || bookEN : bookEN;
      headerTitle.textContent = `${displayName} ${chapter}:${verse}`;
    } else {
      headerTitle.textContent = state.lang === 'en' ? 'Bible' : 'Bybel';
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

    const chTitle = document.createElement('h3');
    chTitle.className = 'bible-chapter-title';
    chTitle.textContent = `${bookDisplay} ${chapter}`;
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

    if (state.bookmarks[ref]) {
      const bookmarkIcon = document.createElement('span');
      bookmarkIcon.className = 'bible-bookmark-indicator';
      bookmarkIcon.innerHTML = '🔖';
      bookmarkIcon.title = state.lang === 'en' ? 'Bookmarked' : 'Geboekmerk';
      vDiv.appendChild(bookmarkIcon);
    }

    if (state.notes[ref]) {
      const noteIcon = document.createElement('span');
      noteIcon.className = 'bible-note-indicator';
      noteIcon.innerHTML = '📝';
      noteIcon.title = state.lang === 'en' ? 'Click to view note' : 'Klik om nota te sien';
      noteIcon.dataset.ref = ref;
      vDiv.appendChild(noteIcon);
    }

    return vDiv;
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
  }

  // ===== VERSE INTERACTIONS =====
  function bindVerseInteractions() {
    document.querySelectorAll('.bible-verse:not(.bound)').forEach(verse => {
      verse.classList.add('bound');
      verse.addEventListener('click', handleVerseClick);
      verse.addEventListener('contextmenu', handleVerseClick);
    });
  }

  function handleVerseClick(e) {
    e.preventDefault();
    
    if (e.target.classList.contains('bible-note-indicator')) {
      const ref = e.target.dataset.ref;
      showNoteEditor(ref);
      showPanel(els.notesPanel);
      return;
    }
    
    const verse = e.currentTarget;
    document.querySelectorAll('.bible-verse').forEach(v => v.classList.remove('selected'));
    verse.classList.add('selected');
    state.selectedVerse = verse.dataset.ref;
    
    showContextMenu(e.clientX, e.clientY);
  }

  function showContextMenu(x, y) {
    const menu = els.verseContextMenu;
    if (!menu) return;
    
    menu.classList.remove('bible-context-hidden');
    menu.style.left = `${x}px`;
    menu.style.top = `${y}px`;
    
    setTimeout(() => {
      const rect = menu.getBoundingClientRect();
      if (rect.right > window.innerWidth) {
        menu.style.left = `${window.innerWidth - rect.width - 20}px`;
      }
      if (rect.bottom > window.innerHeight) {
        menu.style.top = `${window.innerHeight - rect.height - 20}px`;
      }
    }, 0);
  }

  function hideContextMenu() {
    els.verseContextMenu?.classList.add('bible-context-hidden');
  }

  // ===== HIGHLIGHTS =====
  async function applyHighlight(color) {
    if (!state.selectedVerse) return;

    try {
      const res = await fetch('/bible/api/highlights/save.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
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
      alert(state.lang === 'af' ? 'Kon nie highlight stoor nie' : 'Could not save highlight');
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
        headers: { 'Content-Type': 'application/json' },
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
      } else {
        throw new Error(data.error);
      }
    } catch (e) {
      console.error('Bookmark toggle failed:', e);
      alert(state.lang === 'af' ? 'Kon nie boekmerk stoor nie' : 'Could not save bookmark');
    }
    
    hideContextMenu();
  }

  function renderBookmarksList() {
    if (!els.bookmarksList) return;
    
    const refs = Object.keys(state.bookmarks).sort((a, b) => {
      return state.bookmarks[b].timestamp - state.bookmarks[a].timestamp;
    });
    
    if (!refs.length) {
      els.bookmarksList.innerHTML = `<p class="bible-empty-state">${state.lang === 'en' ? 'No bookmarks yet.' : 'Geen boekmerke nog nie.'}</p>`;
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
        <div class="bible-bookmark-text">${esc(bookmark.text.substring(0, 100))}${bookmark.text.length > 100 ? '...' : ''}</div>
      `;
      
      item.addEventListener('click', () => {
        goToReference(ref);
        hidePanel(els.bookmarksPanel);
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
    const log = [];

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

      log.push(`${bookForLeft} ${chapter}`);
      rendered++;
      chapter++;
    }

    // Update state to reflect what we loaded
    if (rendered > 0) {
      state.currentBookIndex = bookIdx;
      state.currentChapter = chapter - 1;
      console.log(`[BIBLE] Rendered ${rendered} chapters: ${log.join(', ')} → state: book=${state.booksEN[bookIdx] || 'END'} ch=${chapter - 1}`);
    }

    return rendered;
  }

  function goToReference(ref) {
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

    // Scroll to the specific verse
    setTimeout(() => {
      const verseEl = els.leftContent.querySelector(`.bible-verse[data-ref="${CSS.escape(ref)}"]`);
      if (verseEl) {
        verseEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
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
    
    const refs = Object.keys(state.notes);
    
    if (!refs.length) {
      els.notesList.innerHTML = `<p class="bible-empty-state">${state.lang === 'en' ? 'No notes yet. Click on a verse to add a note!' : 'Geen notas nog nie. Klik op \'n vers om \'n nota by te voeg!'}</p>`;
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
          <button class="bible-btn-small bible-note-edit" data-ref="${ref}">✏️</button>
          <button class="bible-btn-small bible-note-delete" data-ref="${ref}">🗑️</button>
        </div>
      `;

      noteItem.querySelector('.bible-note-edit').addEventListener('click', () => {
        showNoteEditor(ref);
      });

      noteItem.querySelector('.bible-note-delete').addEventListener('click', async () => {
        if (confirm(state.lang === 'en' ? 'Delete this note?' : 'Verwyder hierdie nota?')) {
          try {
            const res = await fetch('/bible/api/notes/save.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
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
            }
          } catch (e) {
            console.error('Note delete failed:', e);
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
        headers: { 'Content-Type': 'application/json' },
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
      } else {
        throw new Error(data.error);
      }
    } catch (e) {
      console.error('Note save failed:', e);
      alert(state.lang === 'af' ? 'Kon nie nota stoor nie' : 'Could not save note');
    }
  }

  function cancelNote() {
    els.noteEditor?.classList.add('bible-note-hidden');
    els.notesList?.classList.remove('bible-note-hidden');
  }

  function addNoteToVerse() {
    if (!state.selectedVerse) {
      alert(state.lang === 'en' ? 'Select a verse first!' : 'Kies eers \'n vers!');
      return;
    }
    showPanel(els.notesPanel);
    showNoteEditor(state.selectedVerse);
    hideContextMenu();
  }

  // ===== NAVIGATION =====
  function showQuickNav() {
    if (!els.quickNavModal) return;
    els.quickNavModal.classList.remove('bible-modal-hidden');
    state.navState = { testament: null, book: null, chapter: null };
    showNavStep('testament');
    document.body.style.overflow = 'hidden';
  }

  function hideQuickNav() {
    if (!els.quickNavModal) return;
    els.quickNavModal.classList.add('bible-modal-hidden');
    document.body.style.overflow = '';
  }

  function showNavStep(step) {
    els.navStepTestament?.classList.toggle('bible-nav-hidden', step !== 'testament');
    els.navStepBook?.classList.toggle('bible-nav-hidden', step !== 'book');
    els.navStepChapter?.classList.toggle('bible-nav-hidden', step !== 'chapter');
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
      ? (state.lang === 'en' ? 'Old Testament Books' : 'Ou Testament Boeke')
      : (state.lang === 'en' ? 'New Testament Books' : 'Nuwe Testament Boeke');
    
    if (!els.navBookTitle || !els.navBookGrid) return;
    
    els.navBookTitle.textContent = title;
    els.navBookGrid.innerHTML = '';

    const frag = document.createDocumentFragment();
    
    booksEN.forEach(bookEN => {
      const btn = document.createElement('button');
      btn.className = 'bible-nav-card bible-nav-card-small';
      
      const displayName = state.lang === 'af' ? (EN_TO_AF_BOOKS[bookEN] || bookEN) : bookEN;
      btn.textContent = displayName;
      
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
    
    els.navChapterTitle.textContent = `${displayName} - ${state.lang === 'en' ? 'Choose Chapter' : 'Kies Hoofstuk'}`;
    els.navChapterGrid.innerHTML = '';

    if (chapterCount === 0) {
      els.navChapterGrid.innerHTML = `<p class="bible-empty-state">${state.lang === 'af' ? 'Geen hoofstukke gevind nie.' : 'No chapters found.'}</p>`;
      return;
    }

    const frag = document.createDocumentFragment();
    
    for (let i = 1; i <= chapterCount; i++) {
      const btn = document.createElement('button');
      btn.className = 'bible-nav-card bible-nav-card-small';
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
    goToReference(ref);
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
    const safe = esc(text);
    if (!terms || !terms.length) return safe;
    const parts = terms.filter(Boolean).map(escapeRegex);
    if (!parts.length) return safe;
    const re = new RegExp(`(${parts.join('|')})`, 'gi');
    return safe.replace(re, '<mark class="bible-search-hl">$1</mark>');
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
    const m = norm.match(/^(\d?\s*[a-z]+(?:\s+[a-z]+)?)\s+(\d+)(?:\s*[:\.]\s*(\d+))?$/);
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

  function renderEmptyState(msg) {
    els.searchResults.innerHTML = `<p class="bible-empty-state">${esc(msg)}</p>`;
  }

  function renderReferenceHit(parsed) {
    const bookEN = state.booksEN[parsed.bookIndex];
    const bookAF = state.booksAF[parsed.bookIndex];
    const displayName = state.lang === 'af' ? bookAF : bookEN;
    const verse = parsed.verse || 1;
    const refLabel = `${displayName} ${parsed.chapter}${parsed.verse ? ':' + parsed.verse : ''}`;
    const hint = state.lang === 'en'
      ? 'Jump straight to this passage'
      : 'Spring direk na hierdie gedeelte';

    const frag = document.createDocumentFragment();

    const countRow = document.createElement('div');
    countRow.className = 'bible-search-count';
    countRow.textContent = state.lang === 'en' ? 'Reference match' : 'Verwysing gevind';
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
      renderEmptyState(state.lang === 'en'
        ? 'Type at least 2 characters.'
        : 'Tik ten minste 2 karakters.');
      return;
    }

    const searchData = state.lang === 'af' ? state.dataAF : state.dataEN;
    const searchBooks = state.lang === 'af' ? state.booksAF : state.booksEN;
    const HARD_LIMIT = 300;
    const results = [];

    outer:
    for (let idx = 0; idx < searchBooks.length; idx++) {
      const bookName = searchBooks[idx];
      const bookEN = state.booksEN[idx];
      const bookAF = state.booksAF[idx];
      const chapterCount = getChapterCount(searchData, bookName);

      for (let ch = 1; ch <= chapterCount; ch++) {
        const verses = getChapter(searchData, bookName, ch);
        let verseNum = 0;

        for (let v = 0; v < verses.length; v++) {
          const parsed = parseVerse(verses[v]);
          if (parsed.type !== 'verse') continue;
          verseNum++;

          const normText = normalizeSearch(parsed.text);
          // AND across all terms
          let allMatch = true;
          for (const t of terms) {
            if (!normText.includes(t)) { allMatch = false; break; }
          }
          if (!allMatch) continue;

          // Score: exact phrase match wins, then number of distinct terms
          let score = 0;
          if (normText.includes(normQuery)) score += 100;
          score += terms.length * 10;

          results.push({
            bookEN, bookAF, chapter: ch, verse: verseNum,
            text: parsed.text, score
          });

          if (results.length >= HARD_LIMIT) break outer;
        }
      }
    }

    if (!results.length) {
      renderEmptyState(state.lang === 'en'
        ? 'No results found.'
        : 'Geen resultate gevind nie.');
      return;
    }

    results.sort((a, b) => b.score - a.score);
    const DISPLAY_LIMIT = 100;
    const shown = results.slice(0, DISPLAY_LIMIT);

    const frag = document.createDocumentFragment();

    const countRow = document.createElement('div');
    countRow.className = 'bible-search-count';
    const countMsg = state.lang === 'en'
      ? `${results.length}${results.length >= HARD_LIMIT ? '+' : ''} result${results.length === 1 ? '' : 's'}`
        + (shown.length < results.length ? ` — showing first ${shown.length}` : '')
      : `${results.length}${results.length >= HARD_LIMIT ? '+' : ''} resultaat${results.length === 1 ? '' : 'e'}`
        + (shown.length < results.length ? ` — wys eerste ${shown.length}` : '');
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
      renderEmptyState(state.lang === 'en'
        ? 'Keep typing…'
        : 'Tik aan…');
      return;
    }
    els.searchResults.innerHTML = `<div class="bible-loading">${esc(state.lang === 'en' ? 'Searching…' : 'Soek…')}</div>`;
    searchDebounceTimer = setTimeout(() => handleSearch(), 180);
  }

  // ===== AI & CROSS REFS =====
  async function showAIPrompt() {
    if (!state.selectedVerse) {
      alert(state.lang === 'en' ? 'Select a verse first!' : 'Kies eers \'n vers!');
      return;
    }

    const verse = document.querySelector(`[data-ref="${state.selectedVerse}"]`);
    const verseText = verse?.querySelector('.bible-verse-text')?.textContent || '';
    const parsed = parseRef(state.selectedVerse);
    const displayName = state.lang === 'af' ? (EN_TO_AF_BOOKS[parsed.bookEN] || parsed.bookEN) : parsed.bookEN;
    const verseRef = `${displayName} ${parsed.chapter}:${parsed.verse}`;

    hideContextMenu();

    if (!els.aiPanel || !els.aiOutput) return;

    const loadingMsg = state.lang === 'af'
      ? 'AI lees die verse rondom hierdie gedeelte en vertel die storie...'
      : 'AI is reading the surrounding verses and telling the story...';
    els.aiOutput.innerHTML = `<div class="bible-loading">${loadingMsg}</div>`;
    showPanel(els.aiPanel);

    try {
      const res = await fetch('/bible/api/ai_commentary.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
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
            <div class="bible-ai-context-note">${esc(state.lang === 'af'
              ? 'Gebaseer op ' + (data.context_verses || 20) + ' omliggende verse'
              : 'Based on ' + (data.context_verses || 20) + ' surrounding verses')}</div>
            <div class="bible-ai-answer">${html}</div>
          </div>
        `;
      } else {
        throw new Error(data.error);
      }
    } catch (e) {
      els.aiOutput.innerHTML = `<p class="bible-error">${state.lang === 'af' ? 'Kon nie kommentaar kry nie.' : 'Could not get commentary.'}</p>`;
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
      }
    } catch (e) {
      console.error('Cross references error:', e);
    }
  }

  function displayCrossReferences(refs) {
    if (!els.crossRefPanel || !els.crossRefList) return;
    
    if (!refs.length) {
      els.crossRefList.innerHTML = `<p class="bible-empty-state">${state.lang === 'af' ? 'Geen kruisverwysings nie.' : 'No cross-references.'}</p>`;
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
  function copyVerse() {
    if (!state.selectedVerse) return;
    
    const verse = document.querySelector(`[data-ref="${state.selectedVerse}"]`);
    const verseText = verse?.querySelector('.bible-verse-text')?.textContent || '';
    const bookDisplay = verse?.dataset.bookdisplay || '';
    const chapter = verse?.dataset.chapter || '';
    const verseNum = verse?.dataset.verse || '';
    
    const copyText = `${bookDisplay} ${chapter}:${verseNum} - ${verseText}`;
    
    navigator.clipboard.writeText(copyText).then(() => {
      alert(state.lang === 'en' ? 'Verse copied!' : 'Vers gekopieer!');
    }).catch(() => {
      alert(state.lang === 'en' ? 'Failed to copy' : 'Kon nie kopieer nie');
    });
    
    hideContextMenu();
  }

  function shareVerse() {
    if (!state.selectedVerse) return;
    
    const verse = document.querySelector(`[data-ref="${state.selectedVerse}"]`);
    const bookDisplay = verse?.dataset.bookdisplay || '';
    const chapter = verse?.dataset.chapter || '';
    const verseNum = verse?.dataset.verse || '';
    
    const shareText = `${bookDisplay} ${chapter}:${verseNum}`;
    const shareUrl = `${window.location.origin}/bible/bible.php?ref=${encodeURIComponent(state.selectedVerse)}`;
    
    if (navigator.share) {
      navigator.share({
        title: shareText,
        text: shareText,
        url: shareUrl
      }).catch(() => {});
    } else {
      alert(state.lang === 'af' ? 'Deel nie ondersteun nie.' : 'Share not supported.');
    }
    
    hideContextMenu();
  }

  function changeFontSize(direction) {
    const sizes = ['small', 'medium', 'large', 'xlarge'];
    const currentIndex = sizes.indexOf(state.fontSize);
    let newIndex = currentIndex;
    
    if (direction === 'increase' && currentIndex < sizes.length - 1) {
      newIndex++;
    } else if (direction === 'decrease' && currentIndex > 0) {
      newIndex--;
    }
    
    state.fontSize = sizes[newIndex];
    applyFontSize();
  }

  function applyFontSize() {
    const sizeMap = {
      small: '0.9rem',
      medium: '1.05rem',
      large: '1.2rem',
      xlarge: '1.35rem'
    };
    
    document.querySelectorAll('.bible-verse-text').forEach(el => {
      el.style.fontSize = sizeMap[state.fontSize];
    });
  }

  function refreshVerseDisplay() {
    document.querySelectorAll('.bible-verse').forEach(verse => {
      const ref = verse.dataset.ref;
      const wasBound = verse.classList.contains('bound');

      verse.className = 'bible-verse';
      if (wasBound) verse.classList.add('bound');

      if (state.highlights[ref]) {
        verse.classList.add(`bible-highlight-${state.highlights[ref]}`);
      }

      verse.querySelectorAll('.bible-note-indicator, .bible-bookmark-indicator').forEach(n => n.remove());
      
      if (state.bookmarks[ref]) {
        const bookmarkIcon = document.createElement('span');
        bookmarkIcon.className = 'bible-bookmark-indicator';
        bookmarkIcon.innerHTML = '🔖';
        bookmarkIcon.title = state.lang === 'en' ? 'Bookmarked' : 'Geboekmerk';
        verse.appendChild(bookmarkIcon);
      }
      
      if (state.notes[ref]) {
        const noteIcon = document.createElement('span');
        noteIcon.className = 'bible-note-indicator';
        noteIcon.innerHTML = '📝';
        noteIcon.title = state.lang === 'en' ? 'Click to view note' : 'Klik om nota te sien';
        noteIcon.dataset.ref = ref;
        verse.appendChild(noteIcon);
      }
    });
    
    bindVerseInteractions();
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
    panel.classList.remove('bible-panel-hidden');
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

    document.addEventListener('click', (e) => {
      if (els.verseContextMenu && !els.verseContextMenu.contains(e.target) && !e.target.closest('.bible-verse')) {
        hideContextMenu();
      }
    });

    document.addEventListener('click', (e) => {
      if (e.target.closest('.bible-panel') || e.target.closest('.bible-tool-btn') || e.target.closest('.bible-context-menu')) {
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
        hideAllPanels();
        hideContextMenu();
        hideQuickNav();
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
  async function init() {
    const overlay = createLoadingOverlay();
    
    try {
      await initDB();
      updateLoadingProgress(10, '10%', state.lang === 'af' ? 'Database gereed' : 'Database ready');

      els.quickNavModal?.classList.add('bible-modal-hidden');

      let progressAF = 0, progressEN = 0;
      const updateCombinedProgress = () => {
        const combined = 10 + ((progressAF + progressEN) / 2) * 0.7;
        const label = progressAF < 100
          ? (state.lang === 'af' ? 'Laai Afrikaans...' : 'Loading Afrikaans...')
          : (state.lang === 'af' ? 'Laai Engels...' : 'Loading English...');
        updateLoadingProgress(combined, '', label);
      };
      const [dataAF, dataEN] = await Promise.all([
        loadJSON(state.paths.af, (p) => { progressAF = p; updateCombinedProgress(); }),
        loadJSON(state.paths.en, (p) => { progressEN = p; updateCombinedProgress(); })
      ]);

      state.dataAF = dataAF;
      state.dataEN = dataEN;
      state.booksAF = extractBooks(state.dataAF);
      state.booksEN = extractBooks(state.dataEN);

      updateLoadingProgress(80, '80%', state.lang === 'af' ? 'Laai gebruiker data...' : 'Loading user data...');

      await loadUserData();
      
      updateLoadingProgress(90, '90%', state.lang === 'af' ? 'Bou Bybel...' : 'Building Bible...');

      renderInitialChapters();
      setupInfiniteScroll();
      
      updateLoadingProgress(100, '100%', state.lang === 'af' ? 'Gereed!' : 'Ready!');

      // Read initial dual view state from localStorage BEFORE binding events
      const savedDualView = localStorage.getItem('bible_dual_view');
      state.dualViewEnabled = savedDualView === '1';

      bindEvents();
      updateHeaderRef();
      updateDualView();

      setTimeout(() => {
        removeLoadingOverlay();

        // Check for deep link: ?ref=Luke-1-1
        const urlParams = new URLSearchParams(window.location.search);
        const refParam = urlParams.get('ref');
        if (refParam) {
          setTimeout(() => goToReference(refParam), 100);
        }
      }, 500);

    } catch (e) {
      console.error('Initialization failed:', e);
      removeLoadingOverlay();
      alert(state.lang === 'en' 
        ? `Failed to load Bible: ${e.message}\n\nPlease refresh the page.` 
        : `Kon nie Bybel laai nie: ${e.message}\n\nHerlaai asseblief die bladsy.`);
    }
  }

  init();
})();