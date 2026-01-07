// Welcome Page JavaScript
(function() {
  'use strict';

  // Parallax scroll effect for hero glow
  var ticking = false;

  function updateParallax() {
    var scrolled = window.pageYOffset;
    var heroGlow = document.querySelector('.wc-hero-glow');

    if (heroGlow && scrolled < 600) {
      heroGlow.style.transform = 'translateY(' + (scrolled * 0.4) + 'px)';
      heroGlow.style.opacity = Math.max(0, 1 - (scrolled / 500));
    }

    ticking = false;
  }

  window.addEventListener('scroll', function() {
    if (!ticking) {
      window.requestAnimationFrame(updateParallax);
      ticking = true;
    }
  });

})();
