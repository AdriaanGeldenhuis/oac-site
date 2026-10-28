/**
 * Offline in the app (loaded by header_footer/header.php on every page).
 *
 * Inside the Android app (window.NativeApp, see assets/js/fcm-bridge.js) it
 * registers the service worker (/sw.js) and asks it to keep Welcome, the
 * Bible and Sing Emmanuel up to date on the phone, so they open without
 * internet. In a normal browser nothing is registered.
 *
 * On every page it shows the small "You are offline" pill (#oacOfflinePill)
 * while the phone has no connection.
 */
(function () {
  'use strict';

  var inApp = typeof window.NativeApp !== 'undefined';

  if (inApp && 'serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js', { scope: '/' })
      .then(function () { return navigator.serviceWorker.ready; })
      .then(function (reg) {
        if (reg.active && navigator.onLine !== false) {
          reg.active.postMessage({ type: 'oac-warm' });
        }
      })
      .catch(function () { /* no offline copy on this phone, the site still works */ });
  }

  function syncPill() {
    var pill = document.getElementById('oacOfflinePill');
    if (pill) pill.hidden = navigator.onLine !== false;
  }

  window.addEventListener('online', function () {
    syncPill();
    // Back online: bring the offline copy up to date
    if (inApp && navigator.serviceWorker && navigator.serviceWorker.controller) {
      navigator.serviceWorker.controller.postMessage({ type: 'oac-warm' });
    }
  });
  window.addEventListener('offline', syncPill);

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', syncPill);
  } else {
    syncPill();
  }
})();
