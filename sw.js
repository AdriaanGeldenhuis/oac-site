/**
 * OAC service worker - Welcome, the Bible and Sing Emmanuel without internet.
 *
 * Registered by /offline/js/offline.js, only inside the Android app. It has
 * to live in the web root so that its scope is the whole site.
 *
 *   Pages      /welcome.php, /bible/bible.php, /singemmanuel/singemmanuel.php
 *              network first; after 4 s, offline or on a server error, the
 *              last copy. Any ?song= / ?ref= gets the same shell; the page's
 *              JS draws the rest (singemmanuel.js draws the hymn from the
 *              hymn JSON).
 *   Assets     the pages' CSS/JS/images and the hymn JSON: cache first by
 *              exact URL (?v= changes when the file changes).
 *   Data       the Bible's notes/highlights (bible/api/load_all.php),
 *              Welcome's "Up next" (calendar/api/list.php as welcome.js asks
 *              for it) and the organ music (offices 0-8): last copy when
 *              offline.
 *   Bible JSON not touched: the app carries it (OfflineAssets.kt) and
 *              bible.js keeps it in IndexedDB.
 *
 * Pages ask for 'oac-warm' on every load; at most every 10 minutes this
 * fetches /offline/api/manifest.php and fills in whatever is missing, so
 * everything is on the phone before the member ever needs it offline.
 *
 * The pages are the member's own (name, office, language, the Bible's
 * session key), so they leave the phone with the member's data and organ
 * music: at logout, and whenever the server shows the login page.
 *
 * POSTs and the other APIs are never touched.
 */
'use strict';

const STATIC = 'oac-static-v1'; // CSS/JS/images/fonts, hymn JSON, the offline page
const USER = 'oac-user-v1';     // the three pages, Bible notes, Up next (removed at logout)
const MIDI = 'oac-midi-v1';     // organ music, offices 0-8 (removed at logout)
const OUR_CACHES = [STATIC, USER, MIDI]; // any other oac-* cache (oac-pages-v1) goes

const OFFLINE_PAGE = '/offline/index.html';
const META_KEY = '/__oac/offline-meta';
const NET_TIMEOUT = 4000;
const WARM_EVERY = 10 * 60 * 1000;

// Pages that work offline, and the cache that keeps each one's last copy
const SHELLS = {
  '/welcome.php': USER,
  '/bible/bible.php': USER,
  '/singemmanuel/singemmanuel.php': USER,
};

// Member data shown offline
const USER_DATA = {
  '/bible/api/load_all.php': USER,
};

// Welcome's "Up next": what is still to come, from whatever day the list was
// last asked for (welcome.js leaves out what has passed). One key for it, as
// its ?from= is a new date every day. The calendar page asks with ?to=.
const UP_NEXT_PATH = '/calendar/api/list.php';
const UP_NEXT_TYPES = 'event,visit,appointment';
const UP_NEXT_KEY = UP_NEXT_PATH + '?types=' + UP_NEXT_TYPES;

// Set by the Android app (OfflineAssets.kt) on the Bible / hymn JSON it
// answers from its own copy: that copy's version
const APP_COPY_HEADER = 'X-OAC-App-Copy';

const STATIC_EXT = /\.(?:css|js|svg|png|webp|jpe?g|gif|ico|ttf|woff2?)$/i;
const STATIC_DIRS = ['/header_footer/', '/assets/', '/offline/', '/welcome/', '/bible/', '/singemmanuel/'];
const MIDI_PATH = '/singemmanuel/api/midi.php';

// ===== LIFECYCLE =====

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(STATIC)
      .then((cache) => cache.add(new Request(OFFLINE_PAGE, { cache: 'reload' })))
      .catch(() => { /* fetched again by the next warm-up */ })
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    const names = await caches.keys();
    await Promise.all(names
      .filter((n) => n.startsWith('oac-') && !OUR_CACHES.includes(n))
      .map((n) => caches.delete(n)));
    // A new worker may keep things the old one did not: warm up on the next page
    await (await caches.open(STATIC)).delete(META_KEY);
    await self.clients.claim();
  })());
});

// ===== REQUESTS =====

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);

  if (url.origin !== self.location.origin) {
    if (url.hostname === 'fonts.googleapis.com' || url.hostname === 'fonts.gstatic.com') {
      event.respondWith(staleWhileRevalidate(event, req));
    }
    return;
  }

  const path = url.pathname;

  if (req.mode === 'navigate') {
    if (path === '/logout.php') {
      event.respondWith(forgetMember().then(() => fetch(req)).catch(() => offlinePage()));
      return;
    }
    if (path === '/login.php') {
      // The login page: nobody is signed in here (any more, e.g. the session
      // ran out). The last member's pages and notes go, as at logout.
      event.respondWith(fetch(req).then(async (res) => {
        if (isPage(res)) await forgetMember().catch(() => {});
        return res;
      }, () => offlinePage()));
      return;
    }
    if (SHELLS[path]) {
      event.respondWith(networkFirst(event, req, path, SHELLS[path], isPage));
      return;
    }
    if (path === '/' || path === '/index.php') {
      // index.php only redirects to welcome.php (or login.php)
      event.respondWith(networkFirst(event, req, '/welcome.php', null, null));
      return;
    }
    // Every other page needs the server. Only step in when the phone knows
    // it is offline: downloads (calendar PDF / .ics) stay the WebView's own.
    if (self.navigator.onLine === false) {
      event.respondWith(offlinePage());
    }
    return;
  }

  if (USER_DATA[path]) {
    event.respondWith(networkFirst(event, req, path, USER_DATA[path], isJson));
    return;
  }
  if (path === UP_NEXT_PATH && url.searchParams.get('types') === UP_NEXT_TYPES && !url.searchParams.has('to')) {
    event.respondWith(networkFirst(event, req, UP_NEXT_KEY, USER, isJson));
    return;
  }
  if (path === MIDI_PATH) {
    event.respondWith(organMusic(req, url));
    return;
  }
  if (isStaticPath(path)) {
    event.respondWith(url.searchParams.has('v') ? cacheFirst(req) : staleWhileRevalidate(event, req));
  }
});

function isStaticPath(path) {
  // The Bible travels with the app / IndexedDB; uploads are not ours to keep
  if (path.startsWith('/bible/bibles/') || path.startsWith('/assets/uploads/')) return false;
  if (path.startsWith('/singemmanuel/songs/') && path.endsWith('.json')) return true;
  return STATIC_EXT.test(path) && STATIC_DIRS.some((dir) => path.startsWith(dir));
}

/** A full HTML page from our server, not a redirect to the login page */
function isPage(res) {
  return !!res && res.status === 200 && res.type === 'basic' && !res.redirected
    && (res.headers.get('Content-Type') || '').includes('text/html');
}

function isJson(res) {
  return !!res && res.status === 200 && res.type === 'basic' && !res.redirected
    && (res.headers.get('Content-Type') || '').includes('application/json');
}

function isMidi(res) {
  return !!res && res.status === 200 && res.type === 'basic' && !res.redirected
    && (res.headers.get('Content-Type') || '').includes('audio/midi');
}

/**
 * Network first. Slow (4 s), no connection or a server error (5xx): the copy
 * kept under `key`; the network answer still updates that copy when it
 * arrives. cacheName null = only read (/ and /index.php show the welcome copy).
 */
function networkFirst(event, req, key, cacheName, isGood) {
  const network = fetch(req).then(async (res) => {
    if (cacheName && isGood(res)) {
      const cache = await caches.open(cacheName);
      await cache.put(key, res.clone());
    }
    return res;
  });
  event.waitUntil(network.then(() => {}, () => {}));

  return (async () => {
    let outcome;
    try {
      outcome = await Promise.race([network, sleep(NET_TIMEOUT).then(() => 'timeout')]);
    } catch (e) {
      outcome = 'failed';
    }
    if (outcome !== 'timeout' && outcome !== 'failed' && outcome.status < 500) return outcome;

    const kept = await caches.match(key);
    if (kept) return fresh(kept);
    if (outcome instanceof Response) return outcome; // the server's error, and nothing kept
    if (outcome === 'timeout') {
      try { return await network; } catch (e) { /* offline after all */ }
    }
    return req.mode === 'navigate' ? offlinePage() : Response.error();
  })();
}

/**
 * A kept page as a new response, so the browser shows it under the address
 * that was asked for (/singemmanuel/singemmanuel.php?song=7), not the one it
 * was saved from
 */
function fresh(res) {
  return new Response(res.body, { status: res.status, statusText: res.statusText, headers: res.headers });
}

async function offlinePage() {
  const page = await caches.match(OFFLINE_PAGE);
  if (page) return page;
  return new Response(
    '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
    + '<title>OAC</title><body style="background:#0a0a0a;color:#f5ebe6;font-family:system-ui,sans-serif;padding:24px">'
    + '<p>Jy is aflyn. / You are offline.</p><p><a style="color:#e8b4a8" href="/">Probeer weer / Try again</a></p>',
    { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } }
  );
}

/** Versioned asset: the kept copy, else the network (and keep it) */
async function cacheFirst(req) {
  const cache = await caches.open(STATIC);
  const hit = await cache.match(req);
  if (hit) return hit;
  try {
    const res = await fetch(req);
    if (res.ok && res.type === 'basic' && !res.redirected) await putAsset(cache, req.url, res.clone());
    return res;
  } catch (e) {
    // Offline and this exact version was never kept: an older one beats nothing
    const older = await cache.match(req, { ignoreSearch: true });
    if (older) return older;
    throw e;
  }
}

/** Unversioned asset or Google Fonts: the kept copy now, refreshed behind it */
async function staleWhileRevalidate(event, req) {
  const cache = await caches.open(STATIC);
  const hit = await cache.match(req);
  const refresh = fetch(req).then(async (res) => {
    if (res.ok || res.type === 'opaque') await cache.put(req, res.clone());
    return res;
  });
  if (hit) {
    event.waitUntil(refresh.then(() => {}, () => {}));
    return hit;
  }
  return refresh;
}

/** Keep a versioned asset and drop its older versions */
async function putAsset(cache, href, res) {
  const url = new URL(href);
  // The app's own copy, lent while offline because the server has a newer
  // one: kept under that newer ?v= it would hide the real file for good
  const appCopy = res.headers.get(APP_COPY_HEADER);
  if (appCopy && appCopy !== url.searchParams.get('v')) return;
  if (url.searchParams.has('v')) {
    const same = await cache.keys(url.origin + url.pathname, { ignoreSearch: true });
    await Promise.all(same
      .filter((r) => { const u = new URL(r.url); return u.search !== url.search && u.searchParams.has('v'); })
      .map((r) => cache.delete(r)));
  }
  await cache.put(href, res);
}

/** One key per hymn + verse count, however the player spells the URL */
function midiKey(url) {
  const song = parseInt(url.searchParams.get('song') || '', 10);
  const verses = url.searchParams.get('verses') === '2' ? '2' : '1';
  return MIDI_PATH + '?song=' + song + '&verses=' + verses;
}

async function organMusic(req, url) {
  const key = midiKey(url);
  const cache = await caches.open(MIDI);
  const hit = await cache.match(key);
  if (hit) return hit;
  const res = await fetch(req);
  if (isMidi(res)) await cache.put(key, res.clone());
  return res;
}

/** Logout: the member's own pages, notes and organ music leave the phone */
async function forgetMember() {
  await Promise.all([caches.delete(USER), caches.delete(MIDI)]);
  const cache = await caches.open(STATIC);
  await cache.delete(META_KEY); // the next login warms up at once
}

// ===== WARM-UP =====

let warming = null;

self.addEventListener('message', (event) => {
  const data = event.data || {};
  if (data.type !== 'oac-warm') return;
  if (!warming) {
    warming = warm().catch(() => {}).then(() => { warming = null; });
  }
  event.waitUntil(warming);
});

async function warm() {
  const statics = await caches.open(STATIC);
  const meta = await readMeta(statics);
  if (meta.at && Date.now() - meta.at < WARM_EVERY) return;
  await writeMeta(statics, { version: meta.version || '', at: Date.now() });

  const res = await fetch('/offline/api/manifest.php', { credentials: 'same-origin', cache: 'no-store' });
  if (!isJson(res)) return; // logged out: a redirect to the login page
  const m = await res.json();
  if (!m || !m.success) return;
  const changed = meta.version !== m.version;

  // Assets: versioned ones only when missing, the rest again when anything changed
  await eachLimited(m.static || [], 3, async (u) => {
    const versioned = new URL(u, self.location.origin).searchParams.has('v');
    const have = await statics.match(u);
    if (have && (versioned || !changed)) return;
    const r = await fetch(u, { credentials: 'same-origin', cache: 'no-cache' });
    if (r.ok && r.type === 'basic' && !r.redirected) await putAsset(statics, new URL(u, self.location.origin).href, r);
  });

  // Google Fonts: the CSS, then the font files it names
  for (const css of m.fonts || []) {
    let text = '';
    const have = await statics.match(css);
    if (have && !changed) {
      text = await have.clone().text().catch(() => '');
    } else {
      const r = await fetch(css, { mode: 'cors', credentials: 'omit' }).catch(() => null);
      if (!r || !r.ok) continue;
      await statics.put(css, r.clone());
      text = await r.text();
    }
    const files = Array.from(text.matchAll(/url\((https:\/\/fonts\.gstatic\.com\/[^)\s'"]+)\)/g), (x) => x[1]);
    await eachLimited(files, 3, async (f) => {
      if (await statics.match(f)) return;
      const r = await fetch(f, { mode: 'cors', credentials: 'omit' });
      if (r.ok) await statics.put(f, r);
    });
  }

  // Page shells and the member's own pages/data
  const keep = async (cacheName, list, isGood) => {
    const cache = await caches.open(cacheName);
    for (const u of list) {
      if (!changed && await cache.match(u)) continue;
      const r = await fetch(u, { credentials: 'same-origin', cache: 'no-store' });
      if (r.redirected) throw new Error('logged out'); // stop: nothing more to keep
      if (isGood(r)) await cache.put(u, r);
    }
  };
  const own = (m.pages || []).concat(m.user || []);
  await keep(USER, own.filter((u) => SHELLS[u]), isPage);
  await keep(USER, own.filter((u) => USER_DATA[u]), isJson);

  // Organ music: only for offices 0-8 (the server decides); otherwise none
  if (!Array.isArray(m.midi)) {
    await caches.delete(MIDI);
  } else {
    const midi = await caches.open(MIDI);
    const wanted = new Set(m.midi.map((u) => midiKey(new URL(u, self.location.origin))));
    const kept = await midi.keys();
    await Promise.all(kept
      .filter((r) => { const u = new URL(r.url); return !wanted.has(u.pathname + u.search); })
      .map((r) => midi.delete(r)));
    await eachLimited(Array.from(wanted), 3, async (key) => {
      if (await midi.match(key)) return;
      const r = await fetch(key, { credentials: 'same-origin' });
      if (isMidi(r)) await midi.put(key, r);
    });
  }

  await writeMeta(statics, { version: m.version, at: Date.now() });
}

async function readMeta(cache) {
  try {
    const r = await cache.match(META_KEY);
    return r ? await r.json() : {};
  } catch (e) {
    return {};
  }
}

function writeMeta(cache, meta) {
  return cache.put(META_KEY, new Response(JSON.stringify(meta), { headers: { 'Content-Type': 'application/json' } }));
}

/** Run fn over items, `limit` at a time; one failure does not stop the rest */
async function eachLimited(items, limit, fn) {
  let i = 0;
  const worker = async () => {
    while (i < items.length) {
      const item = items[i++];
      try { await fn(item); } catch (e) { /* the next warm-up tries again */ }
    }
  };
  await Promise.all(Array.from({ length: Math.min(limit, items.length) }, worker));
}

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}
