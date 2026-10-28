/**
 * Sing Emmanuel — the hymn page.
 *
 * The hymn itself is drawn by singemmanuel.php. This file opens the number
 * picker, and (for se-player.js) the organ player, as layers: a sheet on
 * phones, a card on desktop. An open layer has its own history entry, so
 * the phone's Back button, the app's and the floating back button close it
 * instead of leaving the page. Escape and a tap on the scrim close it too.
 * The text size of the hymn is remembered on the device.
 */
(function () {
  'use strict';

  const $ = (s, root) => (root || document).querySelector(s);
  const SONG_URL = '/singemmanuel/singemmanuel.php?song=';

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

  function focusQuietly(node) {
    if (!node || !node.focus) return;
    try { node.focus({ preventScroll: true }); } catch (e) { node.focus(); }
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
    // A tap on the scrim (not the sheet) closes
    layer.addEventListener('click', (e) => {
      if (e.target === layer) closeLayer(layer);
      else if (e.target.closest('[data-se-close]')) closeLayer(layer);
    });

    // Phones: pull the sheet down by its handle to close it
    const sheet = layer.querySelector('.se-sheet');
    const grip = layer.querySelector('.se-sheet-grip');
    if (!sheet || !grip) return;
    let startY = 0;
    let dy = 0;
    grip.addEventListener('touchstart', (e) => { startY = e.touches[0].clientY; dy = 0; }, { passive: true });
    grip.addEventListener('touchmove', (e) => {
      dy = Math.max(0, e.touches[0].clientY - startY);
      sheet.style.transform = 'translateY(' + dy + 'px)';
    }, { passive: true });
    grip.addEventListener('touchend', () => {
      if (dy > 90) {
        closeLayer(layer);
      } else {
        sheet.style.transform = '';
      }
    });
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
  const pickerSheet = picker ? $('.se-picker', picker) : null;
  const openBtn = $('#openSongPicker');
  const grid = $('#songPickerGrid');
  const numberInput = $('#songNumberInput');
  const numberGo = $('#songNumberGo');
  const msg = $('#songNumberMsg');
  const canHover = window.matchMedia('(hover: hover)').matches;

  // Phones: hide the grid while the keyboard is open (see the CSS)
  function setTyping(on) {
    if (pickerSheet) pickerSheet.classList.toggle('is-typing', on);
  }

  function filterGrid(value) {
    if (!grid) return;
    grid.querySelectorAll('.se-song-btn').forEach((btn) => {
      const num = btn.getAttribute('data-song') || '';
      btn.hidden = value !== '' && num.indexOf(value) !== 0;
    });
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
    setTyping(false);
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
      go(SONG_URL + encodeURIComponent(value));
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
      go(SONG_URL + encodeURIComponent(n));
    });
  }

  if (numberInput) {
    numberInput.addEventListener('input', () => {
      const digits = numberInput.value.replace(/\D/g, '');
      if (numberInput.value !== digits) numberInput.value = digits;
      numberInput.classList.remove('se-input-error');
      say('');
      filterGrid(digits.replace(/^0+(?=\d)/, ''));
    });
    numberInput.addEventListener('focus', () => { if (!canHover) setTyping(true); });
    numberInput.addEventListener('blur', () => {
      // Let a tap on the arrow land before the grid comes back
      setTimeout(() => { if (document.activeElement !== numberInput) setTyping(false); }, 150);
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
    link.addEventListener('click', () => {
      try { sessionStorage.setItem(SCROLL_KEY, String(window.pageYOffset)); } catch (e) { /* private mode */ }
    });
  });

  try {
    const y = sessionStorage.getItem(SCROLL_KEY);
    if (y !== null) {
      sessionStorage.removeItem(SCROLL_KEY);
      window.addEventListener('load', () => window.scrollTo(0, parseInt(y, 10) || 0));
    }
  } catch (e) { /* private mode */ }
})();
