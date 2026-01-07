(() => {
  'use strict';

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
  const AF_TO_EN_BOOKS = {
    'Genesis': 'Genesis',
    'Exodus': 'Exodus',
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
    'Nehemia': 'Nehemiah',
    'Ester': 'Esther',
    'Job': 'Job',
    'Psalms': 'Psalms',
    'Spreuke': 'Proverbs',
    'Prediker': 'Ecclesiastes',
    'Hooglied': 'Song of Solomon',
    'Jesaja': 'Isaiah',
    'Jeremia': 'Jeremiah',
    'Klaagliedere': 'Lamentations',
    'Esegiel': 'Ezekiel',
    'Daniel': 'Daniel',
    'Hosea': 'Hosea',
    'Joel': 'Joel',
    'Amos': 'Amos',
    'Obadja': 'Obadiah',
    'Jona': 'Jonah',
    'Miga': 'Micah',
    'Nahum': 'Nahum',
    'Habakuk': 'Habakkuk',
    'Sefanja': 'Zephaniah',
    'Haggai': 'Haggai',
    'Sagaria': 'Zechariah',
    'Maleagi': 'Malachi',
    'Matteus': 'Matthew',
    'Markus': 'Mark',
    'Lukas': 'Luke',
    'Johannes': 'John',
    'Handelinge': 'Acts',
    'Romeine': 'Romans',
    '1 Korinthiers': '1 Corinthians',
    '2 Korinthiers': '2 Corinthians',
    'Galasiers': 'Galatians',
    'Efesiers': 'Ephesians',
    'Filippense': 'Philippians',
    'Kolossense': 'Colossians',
    '1 Thessalonisense': '1 Thessalonians',
    '2 Thessalonisense': '2 Thessalonians',
    '1 Timotheus': '1 Timothy',
    '2 Timotheus': '2 Timothy',
    'Titus': 'Titus',
    'Filemon': 'Philemon',
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
    renderedChapters: new Set(),
    isLoading: false,
    syncingScroll: false
  };

  // ===== LANGUAGE CHANGE HANDLER =====
  function handleLanguageChange(newLang) {
    if (newLang === state.lang) return;
    
    const overlay = createLoadingOverlay();
    
    state.lang = newLang;
    state.renderedChapters.clear();
    state.currentBookIndex = 0;
    state.currentChapter = 1;
    
    setTimeout(async () => {
      try {
        updateLoadingProgress(50, '50%', state.lang === 'af' ? 'Laai Bybel...' : 'Loading Bible...');
        
        renderInitialChapters();
        refreshVerseDisplay();
        updateHeaderRef();
        
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
    readingPlanToggle: $('readingPlanToggle'),
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
    readingPlanPanel: $('readingPlanPanel'),
    readingPlanClose: $('readingPlanClose'),
    readingPlanContent: $('readingPlanContent'),
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
    const cacheKey = `bible_v4_${url}`;
    
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

  function getBook(data, bookName) {
    if (!data || !bookName) return null;
    if (Array.isArray(data.books)) {
      return data.books.find(b => (b.name || b.book) === bookName);
    }
    if (typeof data[bookName] === 'object') {
      return data[bookName];
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
    const parts = ref.split('-');
    return {
      bookEN: parts[0],
      chapter: parseInt(parts[1], 10),
      verse: parseInt(parts[2], 10)
    };
  }

  // ===== HEADER UPDATE =====
  function updateHeaderRef() {
    const headerTitle = document.querySelector('.ghf-title');
    if (!headerTitle) return;

    const verses = document.querySelectorAll('.bible-verse[data-verse]');
    if (!verses.length) {
      headerTitle.textContent = state.lang === 'en' ? 'Bible' : 'Bybel';
      return;
    }

    let topVerse = null;
    let minDist = Infinity;

    verses.forEach(v => {
      const rect = v.getBoundingClientRect();
      const dist = Math.abs(rect.top - 150);
      if (rect.top > 70 && rect.top < window.innerHeight && dist < minDist) {
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
    const startBookEN = 'Genesis';
    const startBookAF = 'Genesis';
    
    state.currentBookIndex = 0;
    state.currentChapter = 1;
    
    const leftData = state.lang === 'af' ? state.dataAF : state.dataEN;
    const leftBook = state.lang === 'af' ? startBookAF : startBookEN;
    
    const leftChapter = createChapterElement(startBookEN, leftBook, 1, 'left');
    const rightChapter = createChapterElement(startBookEN, startBookEN, 1, 'right');
    
    els.leftContent.innerHTML = '';
    els.rightContent.innerHTML = '';
    
    els.leftContent.appendChild(leftChapter);
    els.rightContent.appendChild(rightChapter);
    
    state.renderedChapters.add(`${startBookEN}-1-left`);
    state.renderedChapters.add(`${startBookEN}-1-right`);
    
    applyFontSize();
    bindVerseInteractions();
    updateHeaderRef();
    
    requestIdleCallback(() => {
      loadNextChapters(3);
    });
  }

  function loadNextChapters(count = 5) {
    if (state.isLoading) return;
    state.isLoading = true;
    
    let loaded = 0;
    let bookIdx = state.currentBookIndex;
    let chapter = state.currentChapter + 1;
    
    const loadChapter = () => {
      if (loaded >= count || bookIdx >= state.booksEN.length) {
        state.isLoading = false;
        return;
      }
      
      const bookEN = state.booksEN[bookIdx];
      const bookAF = state.booksAF[bookIdx];
      
      const dataForLeft = state.lang === 'af' ? state.dataAF : state.dataEN;
      const bookForLeft = state.lang === 'af' ? bookAF : bookEN;
      
      const chapterCount = getChapterCount(dataForLeft, bookForLeft);
      
      if (chapter > chapterCount) {
        bookIdx++;
        chapter = 1;
        requestIdleCallback(loadChapter);
        return;
      }
      
      const leftKey = `${bookEN}-${chapter}-left`;
      const rightKey = `${bookEN}-${chapter}-right`;
      
      if (!state.renderedChapters.has(leftKey)) {
        const leftChapter = createChapterElement(bookEN, bookForLeft, chapter, 'left');
        els.leftContent.appendChild(leftChapter);
        state.renderedChapters.add(leftKey);
      }
      
      if (!state.renderedChapters.has(rightKey)) {
        const rightChapter = createChapterElement(bookEN, bookEN, chapter, 'right');
        els.rightContent.appendChild(rightChapter);
        state.renderedChapters.add(rightKey);
      }
      
      loaded++;
      chapter++;
      state.currentChapter = chapter - 1;
      state.currentBookIndex = bookIdx;
      
      requestIdleCallback(loadChapter);
    };
    
    requestIdleCallback(loadChapter);
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
  function setupInfiniteScroll() {
    let scrollTimeout = null;
    
    const handleScroll = () => {
      clearTimeout(scrollTimeout);
      scrollTimeout = setTimeout(() => {
        const column = els.leftColumn;
        const scrollHeight = column.scrollHeight;
        const scrollTop = column.scrollTop;
        const clientHeight = column.clientHeight;
        
        if (scrollHeight - scrollTop - clientHeight < 1000) {
          loadNextChapters(5);
        }
        
        updateHeaderRef();
      }, 100);
    };
    
    els.leftColumn.addEventListener('scroll', handleScroll, { passive: true });
    els.rightColumn.addEventListener('scroll', handleScroll, { passive: true });
  }

  // ===== DUAL SCROLL SYNC =====
  function setupDualScrollSync() {
    if (!els.leftColumn || !els.rightColumn) return;

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
        <div class="bible-bookmark-text">${esc(bookmark.text.substring(0, 100))}...</div>
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

  function goToReference(ref) {
    const parsed = parseRef(ref);
    
    const bookIdx = state.booksEN.indexOf(parsed.bookEN);
    if (bookIdx !== -1) {
      state.currentBookIndex = bookIdx;
      state.currentChapter = parsed.chapter;
      
      const leftKey = `${parsed.bookEN}-${parsed.chapter}-left`;
      const rightKey = `${parsed.bookEN}-${parsed.chapter}-right`;
      
      if (!state.renderedChapters.has(leftKey) || !state.renderedChapters.has(rightKey)) {
        const bookAF = state.booksAF[bookIdx];
        const leftBook = state.lang === 'af' ? bookAF : parsed.bookEN;
        
        if (!state.renderedChapters.has(leftKey)) {
          const leftChapter = createChapterElement(parsed.bookEN, leftBook, parsed.chapter, 'left');
          els.leftContent.appendChild(leftChapter);
          state.renderedChapters.add(leftKey);
        }
        
        if (!state.renderedChapters.has(rightKey)) {
          const rightChapter = createChapterElement(parsed.bookEN, parsed.bookEN, parsed.chapter, 'right');
          els.rightContent.appendChild(rightChapter);
          state.renderedChapters.add(rightKey);
        }
        
        bindVerseInteractions();
      }
    }
    
    setTimeout(() => {
      const verseEl = document.querySelector(`[data-ref="${ref}"]`);
      if (verseEl) {
        verseEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
        verseEl.classList.add('bible-verse-flash');
        setTimeout(() => verseEl.classList.remove('bible-verse-flash'), 2000);
      }
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
  function handleSearch() {
    const q = (els.searchInput?.value || '').trim().toLowerCase();
    if (!q) return;

    if (!els.searchResults) return;
    
    els.searchResults.innerHTML = '<div class="bible-loading">Soek...</div>';

    setTimeout(() => {
      const results = [];

      state.booksAF.forEach((bookAF, idx) => {
        const bookEN = state.booksEN[idx];
        const chapterCount = getChapterCount(state.dataAF, bookAF);
        
        for (let ch = 1; ch <= chapterCount; ch++) {
          const verses = getChapter(state.dataAF, bookAF, ch);
          let verseNum = 0;
          
          verses.forEach(v => {
            const parsed = parseVerse(v);
            if (parsed.type === 'verse') {
              verseNum++;
              if (parsed.text.toLowerCase().includes(q)) {
                results.push({
                  bookEN,
                  bookAF,
                  chapter: ch,
                  verse: verseNum,
                  text: parsed.text
                });
              }
            }
          });
        }
      });

      if (!results.length) {
        els.searchResults.innerHTML = `<p class="bible-empty-state">${state.lang === 'en' ? 'No results found.' : 'Geen resultate gevind nie.'}</p>`;
        return;
      }

      const frag = document.createDocumentFragment();
      
      results.slice(0, 50).forEach(hit => {
        const displayName = state.lang === 'af' ? hit.bookAF : hit.bookEN;
        const row = document.createElement('div');
        row.className = 'bible-search-result-item';
        row.innerHTML = `
          <div class="bible-search-result-ref">${esc(displayName)} ${hit.chapter}:${hit.verse}</div>
          <div class="bible-search-result-text">${esc(hit.text.substring(0, 150))}...</div>
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
    }, 100);
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

    const loadingMsg = state.lang === 'af' ? 'AI verduidelik gedeelte...' : 'AI explaining passage...';
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
        // Format the answer with proper line breaks
        const formattedAnswer = data.answer.replace(/\n/g, '<br>');
        els.aiOutput.innerHTML = `
          <div class="bible-ai-response">
            <div class="bible-ai-verse-ref">${esc(verseRef)}</div>
            <div class="bible-ai-answer">${formattedAnswer}</div>
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

  function showReadingPlan() {
    if (!els.readingPlanPanel || !els.readingPlanContent) return;
    
    const plans = [
      { 
        id: 'year', 
        name: state.lang === 'af' ? 'Bybel in \'n Jaar' : 'Bible in a Year',
        desc: state.lang === 'af' ? '365 dae' : '365 days'
      },
      { 
        id: 'nt_month', 
        name: state.lang === 'af' ? 'NT in \'n Maand' : 'NT in a Month',
        desc: state.lang === 'af' ? '30 dae' : '30 days'
      },
      { 
        id: 'psalms', 
        name: state.lang === 'af' ? 'Psalms in \'n Maand' : 'Psalms in a Month',
        desc: state.lang === 'af' ? '30 dae' : '30 days'
      }
    ];
    
    let html = '<div class="bible-plan-options">';
    
    plans.forEach(plan => {
      html += `
        <button class="bible-plan-option" data-plan="${plan.id}">
          <div class="bible-plan-name">${esc(plan.name)}</div>
          <div class="bible-plan-desc">${esc(plan.desc)}</div>
        </button>
      `;
    });
    
    html += '</div>';
    
    els.readingPlanContent.innerHTML = html;
    
    els.readingPlanContent.querySelectorAll('.bible-plan-option').forEach(btn => {
      btn.addEventListener('click', () => {
        const planId = btn.dataset.plan;
        alert(`${state.lang === 'af' ? 'Begin leesplan' : 'Starting reading plan'}: ${planId}`);
      });
    });
    
    showPanel(els.readingPlanPanel);
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
      
      verse.className = 'bible-verse';
      if (verse.classList.contains('bound')) verse.classList.add('bound');
      
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
      els.crossRefPanel,
      els.readingPlanPanel
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
    els.readingPlanToggle?.addEventListener('click', showReadingPlan);

    els.searchClose?.addEventListener('click', () => hidePanel(els.searchPanel));
    els.notesClose?.addEventListener('click', () => hidePanel(els.notesPanel));
    els.bookmarksClose?.addEventListener('click', () => hidePanel(els.bookmarksPanel));
    els.aiClose?.addEventListener('click', () => hidePanel(els.aiPanel));
    els.crossRefClose?.addEventListener('click', () => hidePanel(els.crossRefPanel));
    els.readingPlanClose?.addEventListener('click', () => hidePanel(els.readingPlanPanel));

    els.searchBtn?.addEventListener('click', handleSearch);
    els.searchInput?.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') handleSearch();
    });

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

      const [dataAF, dataEN] = await Promise.all([
        loadJSON(state.paths.af, (p) => {
          updateLoadingProgress(10 + (p * 0.35), '', state.lang === 'af' ? 'Laai Afrikaans...' : 'Loading Afrikaans...');
        }),
        loadJSON(state.paths.en, (p) => {
          updateLoadingProgress(10 + (p * 0.35), '', state.lang === 'af' ? 'Laai Engels...' : 'Loading English...');
        })
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