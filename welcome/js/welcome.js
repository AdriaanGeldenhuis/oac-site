// =====================================================================
// Welcome page — interactions for the "Light Through Glass" layout.
// Plain ES5 on purpose: this runs inside an Android WebView shell.
// Every feature initialises on its own, so one failure never takes the
// rest of the page down with it.
// =====================================================================
(function () {
  'use strict';

  var doc = document;
  var win = window;

  var reduceMotion = !!(win.matchMedia && win.matchMedia('(prefers-reduced-motion: reduce)').matches);
  var finePointer = !!(win.matchMedia && win.matchMedia('(hover: hover) and (pointer: fine)').matches);

  // Server clock + translated strings (see #wcData in welcome.php)
  var data = {};
  try {
    data = JSON.parse(doc.getElementById('wcData').textContent) || {};
  } catch (e) { /* fall back to empty strings below */ }
  var T = data.i18n || {};

  var SVG_NS = 'http://www.w3.org/2000/svg';

  // ---------------------------------------------------------------------
  // Helpers
  // ---------------------------------------------------------------------
  function byId(id) {
    return doc.getElementById(id);
  }

  function clamp(n, lo, hi) {
    return Math.min(hi, Math.max(lo, n));
  }

  function rand(lo, hi) {
    return lo + Math.random() * (hi - lo);
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

  function svgEl(name, attrs) {
    var el = doc.createElementNS(SVG_NS, name);
    for (var k in attrs) {
      if (Object.prototype.hasOwnProperty.call(attrs, k)) {
        el.setAttribute(k, attrs[k]);
      }
    }
    return el;
  }

  // One rAF-throttled scroll/resize dispatcher shared by every feature
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
  // Toast
  // ---------------------------------------------------------------------
  var toastTimer = 0;

  function toast(message) {
    var el = byId('wcToast');
    if (!el || !message) { return; }
    el.textContent = message;
    el.classList.add('is-on');
    win.clearTimeout(toastTimer);
    toastTimer = win.setTimeout(function () { el.classList.remove('is-on'); }, 1800);
  }

  // ---------------------------------------------------------------------
  // Reveal sections as they scroll into view
  // ---------------------------------------------------------------------
  function initReveal() {
    var els = doc.querySelectorAll('.wc-reveal');
    var i;

    if (!('IntersectionObserver' in win)) {
      for (i = 0; i < els.length; i++) { els[i].classList.add('is-in'); }
      return;
    }

    var io = new IntersectionObserver(function (entries) {
      for (var n = 0; n < entries.length; n++) {
        if (entries[n].isIntersecting) {
          entries[n].target.classList.add('is-in');
          io.unobserve(entries[n].target);
        }
      }
    }, { rootMargin: '0px 0px -6% 0px', threshold: 0.06 });

    for (i = 0; i < els.length; i++) { io.observe(els[i]); }
  }

  // ---------------------------------------------------------------------
  // Atmosphere: embers + gentle parallax on the rose window
  // ---------------------------------------------------------------------
  function initEmbers() {
    var host = byId('wcEmbers');
    if (!host || reduceMotion) { return; }

    var count = win.innerWidth < 640 ? 14 : 24;
    var frag = doc.createDocumentFragment();

    for (var i = 0; i < count; i++) {
      var ember = doc.createElement('i');
      ember.className = 'wc-ember';
      ember.style.cssText =
        '--x:' + rand(0, 100).toFixed(1) + '%;' +
        '--s:' + rand(3, 8).toFixed(1) + 'px;' +
        '--d:' + rand(16, 32).toFixed(1) + 's;' +
        '--delay:-' + rand(0, 32).toFixed(1) + 's;' +
        '--o:' + rand(0.35, 0.85).toFixed(2) + ';' +
        '--sway:' + rand(-44, 44).toFixed(0) + 'px';
      frag.appendChild(ember);
    }
    host.appendChild(frag);
  }

  function initParallax() {
    var aura = byId('wcAura');
    if (!aura || reduceMotion) { return; }

    scrollSubs.push(function () {
      aura.style.setProperty('--sy', String(Math.min(win.pageYOffset || 0, 1400)));
    });

    if (finePointer) {
      var queued = false;
      var px = 0;
      var py = 0;

      doc.addEventListener('pointermove', function (e) {
        px = e.clientX / (win.innerWidth || 1) - 0.5;
        py = e.clientY / (win.innerHeight || 1) - 0.5;
        if (queued) { return; }
        queued = true;
        win.requestAnimationFrame(function () {
          queued = false;
          aura.style.setProperty('--px', px.toFixed(3));
          aura.style.setProperty('--py', py.toFixed(3));
        });
      }, { passive: true });
    }
  }

  // ---------------------------------------------------------------------
  // Quick navigation tiles: light that follows the pointer, a little tilt,
  // a tap ripple, and a colour flare when leaving the page
  // ---------------------------------------------------------------------
  function initTiles() {
    var tiles = doc.querySelectorAll('.wc-tile');
    var flare = byId('wcFlare');
    var launching = false;

    // Coming back via the back button (bfcache) must not show a stuck flare
    win.addEventListener('pageshow', function () {
      launching = false;
      if (flare) { flare.classList.remove('is-on'); }
    });

    function setup(tile) {
      var queued = false;
      var mx = 0;
      var my = 0;

      tile.addEventListener('pointermove', function (e) {
        if (e.pointerType !== 'mouse') { return; }
        var r = tile.getBoundingClientRect();
        mx = clamp((e.clientX - r.left) / (r.width || 1), 0, 1);
        my = clamp((e.clientY - r.top) / (r.height || 1), 0, 1);
        if (queued) { return; }
        queued = true;
        win.requestAnimationFrame(function () {
          queued = false;
          tile.style.setProperty('--mx', (mx * 100).toFixed(1) + '%');
          tile.style.setProperty('--my', (my * 100).toFixed(1) + '%');
          if (!reduceMotion) {
            tile.style.setProperty('--ry', ((mx - 0.5) * 9).toFixed(2) + 'deg');
            tile.style.setProperty('--rx', ((0.5 - my) * 9).toFixed(2) + 'deg');
          }
        });
      });

      tile.addEventListener('pointerleave', function () {
        tile.style.setProperty('--rx', '0deg');
        tile.style.setProperty('--ry', '0deg');
      });

      tile.addEventListener('pointerdown', function (e) {
        var r = tile.getBoundingClientRect();
        var x = e.clientX - r.left;
        var y = e.clientY - r.top;
        tile.style.setProperty('--mx', x + 'px');
        tile.style.setProperty('--my', y + 'px');

        if (reduceMotion) { return; }
        var size = Math.max(r.width, r.height) * 2.2;
        var ripple = doc.createElement('span');
        ripple.className = 'wc-ripple';
        ripple.style.width = size + 'px';
        ripple.style.height = size + 'px';
        ripple.style.left = (x - size / 2) + 'px';
        ripple.style.top = (y - size / 2) + 'px';
        tile.appendChild(ripple);
        win.setTimeout(function () {
          if (ripple.parentNode) { ripple.parentNode.removeChild(ripple); }
        }, 700);

        // A small haptic tick where the device supports it
        if (e.pointerType === 'touch' && win.navigator && win.navigator.vibrate) {
          try { win.navigator.vibrate(8); } catch (err) { /* ignore */ }
        }
      });

      tile.addEventListener('click', function (e) {
        if (!flare || reduceMotion) { return; }
        if (e.defaultPrevented || e.button || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return; }

        var href = tile.getAttribute('href');
        if (!href || tile.target === '_blank') { return; }

        e.preventDefault();
        if (launching) { return; }
        launching = true;

        var r = tile.getBoundingClientRect();
        var x = e.clientX || (r.left + r.width / 2);   // keyboard clicks report 0,0
        var y = e.clientY || (r.top + r.height / 2);
        var rgb = (win.getComputedStyle(tile).getPropertyValue('--c-rgb') || '').trim();

        flare.style.setProperty('--fx', x + 'px');
        flare.style.setProperty('--fy', y + 'px');
        if (rgb) { flare.style.setProperty('--fc', rgb); }
        flare.classList.add('is-on');

        win.setTimeout(function () { win.location.href = href; }, 320);
        // If the navigation stalls (offline, blocked), hand the page back
        win.setTimeout(function () {
          launching = false;
          flare.classList.remove('is-on');
        }, 5000);
      });
    }

    for (var i = 0; i < tiles.length; i++) { setup(tiles[i]); }
  }

  // ---------------------------------------------------------------------
  // Notification count on the tile mirrors the footer's global badge, which
  // already polls every 30 s — no second request needed
  // ---------------------------------------------------------------------
  function initNotifBadge() {
    var badge = doc.querySelector('[data-notif-badge]');
    var source = byId('globalNotifCount');
    if (!badge || !source) { return; }

    function sync() {
      var n = source.hidden ? '' : (source.textContent || '').replace(/\s+/g, '');
      badge.textContent = n;
      badge.hidden = !n;
    }

    sync();
    if ('MutationObserver' in win) {
      new MutationObserver(sync).observe(source, {
        attributes: true,
        childList: true,
        characterData: true,
        subtree: true
      });
    }
  }

  // ---------------------------------------------------------------------
  // Up next: the next few gatherings from the user's own calendar.
  // Reads /calendar/api/list.php so the permission rules stay in one place.
  // Dates are naive local strings, so we compare strings, not Date objects.
  // ---------------------------------------------------------------------
  function dayLabel(date) {
    if (date === data.today) { return T.today || ''; }
    if (date === data.tomorrow) { return T.tomorrow || ''; }
    var p = date.split('-');
    var d = new Date(+p[0], +p[1] - 1, +p[2]);
    var dayName = (T.days || [])[d.getDay()] || '';
    var monthName = (T.months || [])[+p[1] - 1] || '';
    return dayName + ', ' + (+p[2]) + ' ' + monthName;
  }

  function eventRow(ev, isFirst) {
    var parts = ev.date.split('-');
    var link = doc.createElement('a');
    link.className = 'wc-ev' + (isFirst ? ' wc-ev--first' : '');
    link.href = '/calendar/calendar.php';

    var stamp = doc.createElement('span');
    stamp.className = 'wc-ev-date';
    var day = doc.createElement('b');
    day.textContent = String(+parts[2]);
    var month = doc.createElement('i');
    month.textContent = ((T.months || [])[+parts[1] - 1] || '').slice(0, 3).toUpperCase();
    stamp.appendChild(day);
    stamp.appendChild(month);

    var text = doc.createElement('span');
    text.className = 'wc-ev-body';
    var title = doc.createElement('span');
    title.className = 'wc-ev-title';
    title.textContent = ev.title || ev.person_name || '';

    var when = doc.createElement('span');
    when.className = 'wc-ev-when';
    var label = doc.createElement('b');
    label.textContent = dayLabel(ev.date);
    when.appendChild(label);
    var extra = [];
    if (ev.time) { extra.push(String(ev.time).slice(0, 5)); }
    if (ev.location) { extra.push(ev.location); }
    if (extra.length) { when.appendChild(doc.createTextNode(' · ' + extra.join(' · '))); }

    text.appendChild(title);
    text.appendChild(when);

    var chev = svgEl('svg', {
      'class': 'wc-ev-chev', viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor',
      'stroke-width': '2', 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'aria-hidden': 'true'
    });
    chev.appendChild(svgEl('path', { d: 'M9 5l7 7-7 7' }));

    link.appendChild(stamp);
    link.appendChild(text);
    link.appendChild(chev);
    return link;
  }

  function emptyRow() {
    var link = doc.createElement('a');
    link.className = 'wc-upnext-empty';
    link.href = '/calendar/calendar.php';
    var icon = svgEl('svg', { viewBox: '0 0 24 24', 'aria-hidden': 'true' });
    icon.appendChild(svgEl('use', { href: '#wc-i-calendar' }));
    var msg = doc.createElement('span');
    msg.textContent = T.noEvents || '';
    link.appendChild(icon);
    link.appendChild(msg);
    return link;
  }

  function initUpNext() {
    var box = doc.querySelector('[data-upnext]');
    if (!box) { return; }
    var section = box.closest ? box.closest('.wc-upnext-section') : null;
    var nowKey = data.now || '';

    function fill(nodes) {
      box.innerHTML = '';
      for (var i = 0; i < nodes.length; i++) { box.appendChild(nodes[i]); }
      box.setAttribute('aria-busy', 'false');
    }

    function render(list) {
      var upcoming = [];
      for (var i = 0; i < list.length; i++) {
        var ev = list[i];
        // Gatherings and visits only — diary entries are private notes
        if (!ev || (ev.type !== 'event' && ev.type !== 'visit') || !ev.date) { continue; }
        ev.date = String(ev.date).slice(0, 10);
        var key = ev.date + ' ' + (ev.time ? String(ev.time).slice(0, 5) : '23:59');
        if (key >= nowKey && (ev.title || ev.person_name)) {
          ev._key = ev.date + ' ' + (ev.time ? String(ev.time).slice(0, 5) : '00:00');
          upcoming.push(ev);
        }
      }
      upcoming.sort(function (a, b) { return a._key < b._key ? -1 : (a._key > b._key ? 1 : 0); });
      upcoming = upcoming.slice(0, 3);

      if (!upcoming.length) { fill([emptyRow()]); return; }

      var rows = [];
      for (var n = 0; n < upcoming.length; n++) { rows.push(eventRow(upcoming[n], n === 0)); }
      fill(rows);
    }

    function fail() {
      // Don't claim "nothing coming up" when we simply couldn't ask
      if (section) { section.hidden = true; }
    }

    if (!win.fetch) { fail(); return; }

    var ctrl = win.AbortController ? new win.AbortController() : null;
    var timer = win.setTimeout(function () { if (ctrl) { ctrl.abort(); } }, 8000);

    win.fetch('/calendar/api/list.php', {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' },
      signal: ctrl ? ctrl.signal : undefined
    })
      .then(function (res) {
        if (!res.ok) { throw new Error('HTTP ' + res.status); }
        return res.json();
      })
      .then(function (list) {
        win.clearTimeout(timer);
        render(Array.isArray(list) ? list : []);
      })
      .catch(function () {
        win.clearTimeout(timer);
        fail();
      });
  }

  // ---------------------------------------------------------------------
  // Thought of the day: share (or copy) it
  // ---------------------------------------------------------------------
  function copyText(text) {
    if (win.navigator.clipboard && win.isSecureContext) {
      return win.navigator.clipboard.writeText(text);
    }
    // Older WebViews: select-and-copy fallback
    return new Promise(function (resolve, reject) {
      var ta = doc.createElement('textarea');
      ta.value = text;
      ta.setAttribute('readonly', '');
      ta.style.cssText = 'position:fixed;top:0;left:0;opacity:0;pointer-events:none';
      doc.body.appendChild(ta);
      ta.select();
      try {
        if (doc.execCommand('copy')) { resolve(); } else { reject(new Error('copy failed')); }
      } catch (err) {
        reject(err);
      }
      doc.body.removeChild(ta);
    });
  }

  function initThought() {
    var btn = byId('wcShare');
    var quote = byId('wcThoughtText');
    if (!btn || !quote) { return; }
    var author = byId('wcThoughtAuthor');

    btn.addEventListener('click', function () {
      var text = (quote.textContent || '').trim();
      if (author && author.textContent) { text += ' — ' + author.textContent.trim(); }

      if (win.navigator.share) {
        win.navigator.share({ text: text }).catch(function () { /* cancelled */ });
        return;
      }
      copyText(text).then(function () { toast(T.copied); }, function () { /* nothing sensible to show */ });
    });
  }

  // ---------------------------------------------------------------------
  // A quiet moment: full-screen breathing guide (4 s in, 6 s out).
  // The orb is driven by the Web Animations API and the label follows the
  // animation clock, so the words and the orb can never drift apart.
  // ---------------------------------------------------------------------
  function initQuiet() {
    var openBtn = byId('wcQuietOpen');
    var qm = byId('wcQm');
    var closeBtn = byId('wcQmClose');
    var orb = byId('wcQmOrb');
    if (!openBtn || !qm || !closeBtn || !orb) { return; }

    var CYCLE = 10000;
    var INHALE = 4000;
    var anim = null;
    var raf = 0;
    var t0 = 0;
    var phase = '';
    var lastFocus = null;
    var hideTimer = 0;

    function setPhase(p) {
      if (p !== phase) {
        phase = p;
        qm.setAttribute('data-phase', p);
      }
    }

    function tick() {
      var t = anim ? Number(anim.currentTime) : (win.performance.now() - t0);
      setPhase((t % CYCLE) < INHALE ? 'in' : 'out');
      raf = win.requestAnimationFrame(tick);
    }

    function open() {
      win.clearTimeout(hideTimer);
      lastFocus = doc.activeElement;
      qm.hidden = false;
      void qm.offsetWidth;                       // let the fade start from 0
      qm.classList.add('is-open');
      doc.body.classList.add('wc-lock');

      t0 = win.performance.now();
      phase = '';
      if (!reduceMotion && orb.animate) {
        anim = orb.animate([
          { transform: 'scale(1)', easing: 'ease-in-out' },
          { transform: 'scale(1.7)', offset: 0.4, easing: 'ease-in-out' },
          { transform: 'scale(1)' }
        ], { duration: CYCLE, iterations: Infinity });
      }
      tick();
      closeBtn.focus({ preventScroll: true });
    }

    function close() {
      win.cancelAnimationFrame(raf);
      if (anim) { anim.cancel(); anim = null; }
      qm.classList.remove('is-open');
      doc.body.classList.remove('wc-lock');
      hideTimer = win.setTimeout(function () { qm.hidden = true; }, 650);
      if (lastFocus && lastFocus.focus) { lastFocus.focus({ preventScroll: true }); }
    }

    openBtn.addEventListener('click', open);
    closeBtn.addEventListener('click', close);

    doc.addEventListener('keydown', function (e) {
      if (qm.hidden) { return; }
      if (e.key === 'Escape' || e.key === 'Esc') {
        close();
      } else if (e.key === 'Tab') {
        // The close button is the only control: keep focus on it
        e.preventDefault();
        closeBtn.focus({ preventScroll: true });
      }
    });
  }

  // ---------------------------------------------------------------------
  // Teaching of the month: collapsed by default, remembers if you opened it,
  // adjustable text size, and a reading-progress line along the top edge
  // ---------------------------------------------------------------------
  function initTeaching() {
    var card = byId('wcTeach');
    var body = byId('wcTeachBody');
    var toggle = byId('wcTeachToggle');
    var toggleText = byId('wcTeachToggleText');
    var tools = byId('wcTeachTools');
    var sizeDown = byId('wcSizeDown');
    var sizeUp = byId('wcSizeUp');
    if (!card || !body || !toggle) { return; }

    var content = body.querySelector('.wc-teaching-content');
    if (!content || content.querySelector('.wc-no-content')) {
      card.classList.add('is-static');
      return;
    }

    var COLLAPSED = 340;          // keep in step with .wc-teach-body max-height
    var WORTH_FOLDING = 90;       // don't fold content that barely overflows
    var SCALES = [0.9, 1, 1.12, 1.26, 1.42];

    var scaleIndex = SCALES.indexOf(parseFloat(store.get('wc_read_scale')));
    if (scaleIndex < 0) { scaleIndex = 1; }
    var isOpen = store.get('wc_teach_open') === '1';
    var mode = '';
    var settleTimer = 0;

    function applyScale() {
      card.style.setProperty('--wc-read-scale', String(SCALES[scaleIndex]));
      if (sizeDown) { sizeDown.disabled = scaleIndex === 0; }
      if (sizeUp) { sizeUp.disabled = scaleIndex === SCALES.length - 1; }
    }

    function setMode(next, immediate) {
      mode = next;
      win.clearTimeout(settleTimer);

      if (next === 'static') {
        card.classList.add('is-static');
        card.classList.remove('is-collapsed');
        body.style.maxHeight = '';
        toggle.hidden = true;
        return;
      }

      var expanded = next === 'expanded';
      card.classList.remove('is-static');
      card.classList.toggle('is-collapsed', !expanded);
      toggle.hidden = false;
      toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
      if (toggleText) { toggleText.textContent = expanded ? (T.readLess || '') : (T.readMore || ''); }

      if (immediate) {
        body.style.transition = 'none';
        body.style.maxHeight = expanded ? 'none' : COLLAPSED + 'px';
        void body.offsetHeight;
        body.style.transition = '';
        return;
      }

      // max-height can't animate to/from "none", so go via the measured height
      body.style.maxHeight = content.offsetHeight + 'px';
      if (expanded) {
        settleTimer = win.setTimeout(function () { body.style.maxHeight = 'none'; }, 650);
      } else {
        void body.offsetHeight;
        body.style.maxHeight = COLLAPSED + 'px';
      }
    }

    // (Re)decide between static / collapsed / expanded from the real height
    function refresh() {
      var needsFold = content.offsetHeight > COLLAPSED + WORTH_FOLDING;
      setMode(needsFold ? (isOpen ? 'expanded' : 'collapsed') : 'static', true);
    }

    function updateProgress() {
      var p = 0;
      if (!card.classList.contains('is-collapsed')) {
        var r = card.getBoundingClientRect();
        var vh = win.innerHeight || 700;
        p = clamp((vh * 0.75 - r.top) / (r.height || 1), 0, 1);
      }
      card.style.setProperty('--p', p.toFixed(3));
    }

    applyScale();
    if (tools) { tools.hidden = false; }
    refresh();
    updateProgress();

    toggle.addEventListener('click', function () {
      isOpen = !isOpen;
      store.set('wc_teach_open', isOpen ? '1' : '0');
      setMode(isOpen ? 'expanded' : 'collapsed');
      if (!isOpen) {
        // Closing from deep in the text would strand you mid-air: go back to the top
        var r = card.getBoundingClientRect();
        if (r.top < 0) {
          card.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
        }
      }
      updateProgress();
    });

    function changeScale(step) {
      var next = clamp(scaleIndex + step, 0, SCALES.length - 1);
      if (next === scaleIndex) { return; }
      scaleIndex = next;
      store.set('wc_read_scale', String(SCALES[scaleIndex]));
      applyScale();
      refresh();
      updateProgress();
    }

    if (sizeDown) { sizeDown.addEventListener('click', function () { changeScale(-1); }); }
    if (sizeUp) { sizeUp.addEventListener('click', function () { changeScale(1); }); }

    scrollSubs.push(updateProgress);

    // Fonts/images can change the height after first paint
    win.addEventListener('load', refresh);
    var resizeTimer = 0;
    win.addEventListener('resize', function () {
      win.clearTimeout(resizeTimer);
      resizeTimer = win.setTimeout(refresh, 200);
    });
  }

  // ---------------------------------------------------------------------
  // Go
  // ---------------------------------------------------------------------
  var features = [
    initReveal, initEmbers, initParallax, initTiles, initNotifBadge,
    initUpNext, initThought, initQuiet, initTeaching
  ];

  for (var f = 0; f < features.length; f++) {
    try {
      features[f]();
    } catch (err) {
      if (win.console && win.console.error) { win.console.error('welcome.js:', err); }
    }
  }
})();
