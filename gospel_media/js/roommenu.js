/**
 * Gospel Media — room navigation
 * 1. The rail under the hero: every room the user is in, one tap away,
 *    with unread counts. The current room is rendered by gospel.php.
 * 2. The rooms sheet (the "Rooms" button): the same rooms, grouped, with
 *    a search when the list gets long.
 * Rooms come from /gospel_media/api/rooms/user_rooms.php, which applies
 * the room rules — this file only shows what that endpoint returns.
 * All styling lives in gospel.css.
 */
(function () {
  'use strict';

  const $ = (q, el = document) => el.querySelector(q);
  const T = (key) => (window.JS_T && window.JS_T[key]) ? window.JS_T[key] : key;

  const CURRENT_ID = parseInt(window.CURRENT_ROOM_ID, 10) || 0;
  const reduceMotion = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);

  const ICONS = { gemeente: 'church', opsienerskap: 'shield', jeug: 'spark', sondagskool: 'school', gemeenskap: 'globe' };
  const KNOWN = ['gemeente', 'opsienerskap', 'jeug', 'sondagskool', 'gemeenskap'];

  const SVG_PLUS = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>';
  const SVG_CLOSE = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>';
  const SVG_SEARCH = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>';
  const SVG_CHEV = '<svg class="gm-room-chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg>';

  function ce(tag, cls, text) {
    const n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text != null) n.textContent = text;
    return n;
  }

  // ===== ROOM DATA =====
  function typeOf(r) {
    const t = String(r.type || '').toLowerCase();
    return KNOWN.indexOf(t) !== -1 ? t : 'other';
  }

  function typeLabel(type) {
    if (type === 'gemeenskap' || type === 'other') return T('type_room');
    return T('type_' + type);
  }

  // display_name is "<type label> <name>" (built server-side); split it so
  // the chip can show the place large and the type small
  function shortName(r) {
    const full = String(r.display_name || r.name || T('type_room'));
    const label = typeLabel(typeOf(r));
    if (label && full.indexOf(label + ' ') === 0 && full.length > label.length + 1) {
      return full.slice(label.length + 1);
    }
    return full;
  }

  function roomHref(r) {
    return '/gospel_media/gospel.php?room_id=' + encodeURIComponent(r.id);
  }

  function unreadOf(r) {
    if (parseInt(r.id, 10) === CURRENT_ID) return 0;   // being read right now
    return parseInt(r.unread, 10) || 0;
  }

  let roomsPromise = null;

  function loadRooms() {
    if (!roomsPromise) {
      roomsPromise = fetch('/gospel_media/api/rooms/user_rooms.php', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
        .then(r => r.json())
        .then(j => ({
          ok: !!j && !j.error,
          auto: (j && Array.isArray(j.auto)) ? j.auto : [],
          joined: (j && Array.isArray(j.joined)) ? j.joined : []
        }))
        .catch(() => ({ ok: false, auto: [], joined: [] }));
    }
    return roomsPromise;
  }

  // ===== ROOM CHIP (rail) / ROW (sheet) =====
  function roomEl(r, asRow) {
    const type = typeOf(r);
    const isCurrent = parseInt(r.id, 10) === CURRENT_ID;

    const a = ce('a', 'gm-room gm-room--' + type + (asRow ? ' gm-room-row' : '') + (isCurrent ? ' is-current' : ''));
    a.href = roomHref(r);
    a.dataset.roomId = String(r.id);
    if (isCurrent) a.setAttribute('aria-current', 'page');

    const orb = ce('span', 'gm-room-orb');
    orb.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><use href="#gm-i-' + (ICONS[type] || 'globe') + '"/></svg>';

    const text = ce('span', 'gm-room-text');
    text.append(ce('b', null, shortName(r)), ce('i', null, typeLabel(type)));

    a.append(orb, text);

    const n = unreadOf(r);
    if (n > 0) {
      const badge = ce('span', 'gm-room-unread', n > 99 ? '99+' : String(n));
      badge.setAttribute('aria-label', n + ' ' + T('unread'));
      a.appendChild(badge);
    }

    if (asRow) {
      if (isCurrent) a.appendChild(ce('span', 'gm-room-here', T('you_are_here')));
      else a.insertAdjacentHTML('beforeend', SVG_CHEV);
    }
    return a;
  }

  // ===== RAIL =====
  function renderRail(data) {
    const rail = $('#gmRail');
    if (!rail) return;

    const serverChip = rail.querySelector('.gm-room.is-current');
    const rooms = data.auto.concat(data.joined);
    const seen = {};
    const frag = document.createDocumentFragment();
    let placedCurrent = false;

    rooms.forEach(r => {
      const id = parseInt(r.id, 10);
      if (!id || seen[id]) return;
      seen[id] = true;
      if (id === CURRENT_ID) placedCurrent = true;
      frag.appendChild(roomEl(r, false));
    });

    // Visiting a room that isn't in the list (e.g. a community room):
    // keep the server-rendered chip up front
    if (!placedCurrent && serverChip) frag.insertBefore(serverChip, frag.firstChild);

    rail.innerHTML = '';
    rail.appendChild(frag);
    rail.setAttribute('aria-busy', 'false');

    // Bring the current room into view (with a peek of the one before it)
    // without moving the page
    const cur = rail.querySelector('.is-current');
    if (cur && cur.offsetLeft + cur.offsetWidth > rail.clientWidth - 16) {
      rail.scrollLeft = Math.max(0, cur.offsetLeft - 44);
    }

    // Total unread elsewhere, on the "Rooms" button
    const total = rooms.reduce((sum, r) => sum + unreadOf(r), 0);
    const badge = $('[data-rooms-unread]');
    if (badge) {
      badge.textContent = total > 99 ? '99+' : String(total);
      badge.hidden = total <= 0;
      if (total > 0) badge.setAttribute('aria-label', total + ' ' + T('unread'));
    }
  }

  // ===== SHEET =====
  let sheet = null;
  let lastFocus = null;
  let closeTimer = 0;

  function buildSheet() {
    if (sheet) return sheet;

    sheet = ce('div', 'gm-sheet');
    sheet.id = 'roommenu';
    sheet.hidden = true;
    sheet.setAttribute('role', 'dialog');
    sheet.setAttribute('aria-modal', 'true');
    sheet.setAttribute('aria-labelledby', 'gmSheetTitle');

    const scrim = ce('div', 'gm-sheet-scrim');
    const panel = ce('div', 'gm-sheet-panel');

    const grip = ce('div', 'gm-sheet-grip');
    const head = ce('div', 'gm-sheet-head');
    const title = ce('h2', 'gm-sheet-title', T('rooms'));
    title.id = 'gmSheetTitle';
    const close = ce('button', 'gm-sheet-close');
    close.type = 'button';
    close.setAttribute('aria-label', T('close'));
    close.innerHTML = SVG_CLOSE;
    head.append(title, close);

    const search = ce('label', 'gm-sheet-search');
    search.hidden = true;
    search.innerHTML = SVG_SEARCH;
    const input = ce('input');
    input.type = 'search';
    input.placeholder = T('search');
    input.setAttribute('aria-label', T('search'));
    input.autocomplete = 'off';
    search.appendChild(input);

    const list = ce('div', 'gm-sheet-list');
    list.id = 'roommenu-list';

    const foot = ce('div', 'gm-sheet-foot');
    const manage = ce('a', 'gm-btn gm-btn--glass');
    manage.href = '/gospel_media/rooms.php';
    manage.innerHTML = SVG_PLUS;
    manage.appendChild(ce('span', null, T('manage_rooms')));
    foot.appendChild(manage);

    panel.append(grip, head, search, list, foot);
    sheet.append(scrim, panel);
    document.body.appendChild(sheet);

    close.addEventListener('click', closeSheet);
    scrim.addEventListener('click', closeSheet);

    input.addEventListener('input', () => {
      const q = input.value.trim().toLowerCase();
      let shown = 0;
      list.querySelectorAll('.gm-room-row').forEach(row => {
        const hit = !q || row.textContent.toLowerCase().indexOf(q) !== -1;
        row.hidden = !hit;
        if (hit) shown++;
      });
      list.querySelectorAll('.gm-sheet-group').forEach(g => {
        g.hidden = !g.querySelector('.gm-room-row:not([hidden])');
      });
      const empty = list.querySelector('.gm-sheet-empty--search');
      if (empty) empty.hidden = shown > 0;
    });

    // Swipe the sheet down to close it (phones)
    let startY = null;
    let dy = 0;
    panel.addEventListener('touchstart', (e) => {
      if (e.touches.length !== 1 || (list.scrollTop > 0 && list.contains(e.target))) { startY = null; return; }
      startY = e.touches[0].clientY;
      dy = 0;
    }, { passive: true });
    panel.addEventListener('touchmove', (e) => {
      if (startY === null) return;
      dy = Math.max(0, e.touches[0].clientY - startY);
      if (dy > 0) panel.style.transform = 'translateY(' + dy + 'px)';
    }, { passive: true });
    panel.addEventListener('touchend', () => {
      if (startY === null) return;
      startY = null;
      panel.style.transform = '';
      if (dy > 90) closeSheet();
    }, { passive: true });

    return sheet;
  }

  function group(title, rooms) {
    const sec = ce('section', 'gm-sheet-group');
    sec.appendChild(ce('h3', 'gm-sheet-group-title', title));
    const rows = ce('div', 'gm-sheet-rows');
    rooms.forEach(r => rows.appendChild(roomEl(r, true)));
    sec.appendChild(rows);
    return sec;
  }

  function fillSheet(data) {
    const list = $('#roommenu-list', sheet);
    const search = $('.gm-sheet-search', sheet);
    list.innerHTML = '';

    if (!data.auto.length && !data.joined.length) {
      list.appendChild(ce('p', 'gm-sheet-empty', data.ok ? T('no_rooms') : T('error')));
      search.hidden = true;
      return;
    }

    if (data.auto.length) list.appendChild(group(T('automatic'), data.auto));
    if (data.joined.length) list.appendChild(group(T('joined_section'), data.joined));

    const noHits = ce('p', 'gm-sheet-empty gm-sheet-empty--search', T('no_rooms'));
    noHits.hidden = true;
    list.appendChild(noHits);

    search.hidden = (data.auto.length + data.joined.length) < 6;
  }

  function openSheet() {
    buildSheet();
    window.clearTimeout(closeTimer);
    sheet.classList.remove('is-closing');

    if (!sheet.hidden) return;
    lastFocus = document.activeElement;

    const list = $('#roommenu-list', sheet);
    const input = $('.gm-sheet-search input', sheet);
    if (input) input.value = '';
    list.innerHTML = '<p class="gm-sheet-empty">' + T('loading_rooms') + '</p>';

    sheet.hidden = false;
    document.body.classList.add('gm-locked');
    const close = $('.gm-sheet-close', sheet);
    if (close) close.focus({ preventScroll: true });

    loadRooms().then(data => {
      fillSheet(data);
      const cur = list.querySelector('.is-current');
      if (cur && cur.scrollIntoView) cur.scrollIntoView({ block: 'nearest' });
    });
  }

  function closeSheet() {
    if (!sheet || sheet.hidden) return;
    const finish = () => {
      sheet.hidden = true;
      sheet.classList.remove('is-closing');
      // Other layers (composer, modals) may still hold the lock
      if (!document.querySelector('.composer-overlay, .modal-backdrop, .lightbox-backdrop')) {
        document.body.classList.remove('gm-locked');
      }
      if (lastFocus && lastFocus.focus) lastFocus.focus({ preventScroll: true });
    };
    if (reduceMotion) { finish(); return; }
    sheet.classList.add('is-closing');
    closeTimer = window.setTimeout(finish, 230);
  }

  // Keep Tab inside the open sheet
  function trapFocus(e) {
    if (!sheet || sheet.hidden || e.key !== 'Tab') return;
    const items = Array.prototype.filter.call(
      sheet.querySelectorAll('a[href], button, input'),
      el => !el.hidden && el.offsetParent !== null
    );
    if (!items.length) return;
    const first = items[0];
    const last = items[items.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  }

  // ===== GOING TO ANOTHER ROOM: a flare in the room's colour =====
  let launching = false;

  function goToRoom(link, e) {
    const href = link.getAttribute('href');
    if (!href) return;

    if (link.classList.contains('is-current')) {
      e.preventDefault();
      closeSheet();
      return;
    }

    const flare = $('#gmFlare');
    if (!flare || reduceMotion) return;   // plain navigation

    e.preventDefault();
    if (launching) return;
    launching = true;

    const r = link.getBoundingClientRect();
    const x = e.clientX || (r.left + r.width / 2);   // keyboard clicks report 0,0
    const y = e.clientY || (r.top + r.height / 2);
    const rgb = (window.getComputedStyle(link).getPropertyValue('--c-rgb') || '').trim();

    flare.style.setProperty('--fx', x + 'px');
    flare.style.setProperty('--fy', y + 'px');
    if (rgb) flare.style.setProperty('--fc', rgb);
    flare.classList.add('is-on');

    window.setTimeout(() => { window.location.href = href; }, 320);
    // If the navigation stalls (offline), hand the page back
    window.setTimeout(() => {
      launching = false;
      flare.classList.remove('is-on');
    }, 5000);
  }

  // ===== WIRING =====
  window.openRoomMenu = openSheet;
  window.closeRoomMenu = closeSheet;

  function wire() {
    document.addEventListener('click', (e) => {
      const trg = e.target.closest('#open-rooms-menu, .rooms-menu-btn, [data-roommenu-open]');
      if (trg) {
        e.preventDefault();
        openSheet();
        return;
      }

      const link = e.target.closest('a.gm-room');
      if (link && !(e.defaultPrevented || e.button || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey)) {
        goToRoom(link, e);
      }
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && sheet && !sheet.hidden) {
        e.stopPropagation();
        closeSheet();
      }
      trapFocus(e);
    });

    // Coming back via the back button (bfcache) must not show a stuck flare
    window.addEventListener('pageshow', () => {
      launching = false;
      const flare = $('#gmFlare');
      if (flare) flare.classList.remove('is-on');
    });

    if ($('#gmRail')) loadRooms().then(renderRail);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', wire);
  } else {
    wire();
  }
})();
