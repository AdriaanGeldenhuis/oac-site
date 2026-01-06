// Kill-switch service worker - deployed to unregister old SW's
// This file can be removed after 48 hours (deployed on 2026-01-06)
self.addEventListener('install', function() {
  self.skipWaiting();
});

self.addEventListener('activate', function() {
  self.registration.unregister()
    .then(function() {
      return self.clients.matchAll();
    })
    .then(function(clients) {
      clients.forEach(function(client) {
        client.navigate(client.url);
      });
    });
});
