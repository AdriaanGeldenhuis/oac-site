// Minimal service worker - does nothing
// This file exists to prevent 404 errors from cached pages
self.addEventListener('install', function() {
  self.skipWaiting();
});

self.addEventListener('activate', function() {
  // Do nothing
});
