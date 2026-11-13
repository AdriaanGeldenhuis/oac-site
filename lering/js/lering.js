// =====================================================================
// Lering — tabs (Teaching of the Month / Year theme), text size and the
// reading line under the tabs.
// Plain ES5 on purpose: this runs inside an Android WebView shell.
// Both tabs are already in the page (lering.php), so nothing here asks
// the server for anything: the app's offline copy works the same.
// Every feature initialises on its own, so one failure never takes the
// rest of the page down with it.
// =====================================================================
(function () {
  'use strict';

  var doc = document;
  var win = window;
  var root = doc.documentElement;

  // Tells the safety net in lering.php's <head> that the page script runs
  win.lrReady = true;

  var reduceMotion = !!(win.matchMedia && win.matchMedia('(prefers-reduced-motion: reduce)').matches);

  var TAB_KEY = 'lr_tab';
  var SCALE_KEY = 'wc_read_scale';       // the reader's choice from the welcome page carries over
  var HASHES = { month: 'lering', year: 'jaartema' };

  function byId(id) {
    return doc.getElementById(id);
  }

  function clamp(n, lo, hi) {
    return Math.min(hi, Math.max(lo, n));
  }

  // localStorage can throw (private mode, blocked storage) — never let it matter
  var store = {
    get: function (key) {
      try { return win.localStorage.getItem(key); } catch (e) { return null; }
    },
    set: function (key, value) {
      try { win.localStorage.setItem(key, value); } catch (e) { /* ignore */ }
    }
  };

  // One rAF-throttled scroll/resize dispatcher
  var scrollSubs = [];
  var scrollQueued = false;

  function onScrollOrResize() {
    if (scrollQueued) { return; }
    scrollQueued = true;
    win.requestAnimationFrame(function () {
      scrollQueued = false;
      for (var i = 0; i < scrollSubs.length; i++) { scrollSubs[i](); }
    });
  }

  win.addEventListener('scroll', onScrollOrResize, { passive: true });
  win.addEventListener('resize', onScrollOrResize);

  // ---------------------------------------------------------------------
  // Reading line: how far through the open tab the reader is
  // ---------------------------------------------------------------------
  var progressBar = byId('lrProgress');

  function activePanel() {
    return root.getAttribute('data-lr-tab') === 'year' ? byId('lrPanelYear') : byId('lrPanelMonth');
  }

  function updateProgress() {
    var panel = activePanel();
    if (!progressBar || !panel) { return; }
    var r = panel.getBoundingClientRect();
    var vh = win.innerHeight || 700;
    var span = r.height - vh * 0.6;
    var p = span > 0 ? clamp((vh * 0.4 - r.top) / span, 0, 1) : (r.top < vh ? 1 : 0);
    progressBar.style.setProperty('--p', p.toFixed(3));
  }

  // ---------------------------------------------------------------------
  // Tabs (WAI-ARIA tabs: arrows / Home / End move between them)
  // ---------------------------------------------------------------------
  function initTabs() {
    var tabs = doc.querySelectorAll('.lr-tab[role="tab"]');
    var bar = byId('lrBar');
    if (!tabs.length) { return; }

    function tabFor(name) {
      for (var i = 0; i < tabs.length; i++) {
        if (tabs[i].getAttribute('data-tab') === name) { return tabs[i]; }
      }
      return null;
    }

    // Keep the address in step (#lering / #jaartema) without new history entries
    function setHash(name) {
      if (!win.history || !win.history.replaceState) { return; }
      try {
        win.history.replaceState(win.history.state, '', win.location.pathname + win.location.search + '#' + HASHES[name]);
      } catch (e) { /* some WebViews refuse: the tab still changes */ }
    }

    function select(name, opts) {
      opts = opts || {};
      var tab = tabFor(name);
      if (!tab) { return; }
      var changed = root.getAttribute('data-lr-tab') !== name;

      root.setAttribute('data-lr-tab', name);
      for (var i = 0; i < tabs.length; i++) {
        var on = tabs[i] === tab;
        tabs[i].setAttribute('aria-selected', on ? 'true' : 'false');
        tabs[i].setAttribute('tabindex', on ? '0' : '-1');
      }

      if (opts.remember) { store.set(TAB_KEY, name); }
      if (opts.hash) { setHash(name); }
      if (opts.focus) { tab.focus(); }

      if (changed && opts.fromUser) {
        var panel = byId(tab.getAttribute('aria-controls'));
        if (panel && !reduceMotion) {
          panel.classList.remove('is-entering');
          void panel.offsetWidth;
          panel.classList.add('is-entering');
        }
        // A new tab starts at its top, just under the tab bar
        if (bar) {
          var barTop = bar.getBoundingClientRect().top;
          var stuck = barTop <= (parseFloat(win.getComputedStyle(bar).top) || 0) + 1;
          if (stuck && panel) {
            win.scrollTo(0, Math.max(0, (win.pageYOffset || 0) + panel.getBoundingClientRect().top - bar.offsetHeight - barTop - 14));
          }
        }
      }
      updateProgress();
    }

    for (var i = 0; i < tabs.length; i++) {
      tabs[i].addEventListener('click', function (e) {
        select(e.currentTarget.getAttribute('data-tab'), { remember: true, hash: true, fromUser: true });
      });

      tabs[i].addEventListener('keydown', function (e) {
        var list = Array.prototype.slice.call(tabs);
        var idx = list.indexOf(e.currentTarget);
        var next = -1;
        var key = e.key || '';
        if (key === 'ArrowRight' || key === 'Right') { next = (idx + 1) % list.length; }
        else if (key === 'ArrowLeft' || key === 'Left') { next = (idx - 1 + list.length) % list.length; }
        else if (key === 'Home') { next = 0; }
        else if (key === 'End') { next = list.length - 1; }
        if (next < 0) { return; }
        e.preventDefault();
        select(list[next].getAttribute('data-tab'), { remember: true, hash: true, focus: true, fromUser: true });
      });
    }

    // A link into the other tab (#jaartema, #lr-y-theme ...) opens it
    function fromHash() {
      var hash = (win.location.hash || '').slice(1);
      if (hash === HASHES.year || hash.indexOf('lr-y-') === 0) { select('year'); }
      else if (hash === HASHES.month) { select('month'); }
    }
    win.addEventListener('hashchange', fromHash);

    // The head script already chose; make the buttons say so too
    select(root.getAttribute('data-lr-tab') === 'year' ? 'year' : 'month');
  }

  // ---------------------------------------------------------------------
  // Text size: one choice for both tabs, remembered on this phone
  // ---------------------------------------------------------------------
  function initSize() {
    var tools = byId('lrTools');
    var down = byId('lrSizeDown');
    var up = byId('lrSizeUp');
    if (!tools || !down || !up) { return; }

    var SCALES = [0.9, 1, 1.12, 1.26, 1.42];
    var idx = SCALES.indexOf(parseFloat(store.get(SCALE_KEY)));
    if (idx < 0) { idx = 1; }

    function apply() {
      root.style.setProperty('--lr-scale', String(SCALES[idx]));
      down.disabled = idx === 0;
      up.disabled = idx === SCALES.length - 1;
    }

    function change(step) {
      var next = clamp(idx + step, 0, SCALES.length - 1);
      if (next === idx) { return; }
      idx = next;
      store.set(SCALE_KEY, String(SCALES[idx]));
      apply();
      updateProgress();
    }

    apply();
    tools.hidden = false;
    down.addEventListener('click', function () { change(-1); });
    up.addEventListener('click', function () { change(1); });
  }

  // ---------------------------------------------------------------------
  // Go
  // ---------------------------------------------------------------------
  var features = [initTabs, initSize];
  for (var f = 0; f < features.length; f++) {
    try {
      features[f]();
    } catch (err) {
      if (win.console && win.console.error) { win.console.error('lering.js:', err); }
    }
  }

  scrollSubs.push(updateProgress);
  updateProgress();
  // Fonts can change the height after first paint
  win.addEventListener('load', updateProgress);
})();
