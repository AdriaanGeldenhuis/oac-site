// Welcome Page JavaScript
(function() {
  'use strict';

  // Smooth scroll for hero scroll indicator
  var scrollIndicator = document.querySelector('.wc-hero-scroll');
  if (scrollIndicator) {
    scrollIndicator.addEventListener('click', function() {
      var teachingSection = document.querySelector('.wc-teaching-section');
      if (teachingSection) {
        teachingSection.scrollIntoView({ behavior: 'smooth' });
      }
    });
  }

  // Add entrance animations when elements come into view
  var observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
  };

  var observer = new IntersectionObserver(function(entries) {
    entries.forEach(function(entry) {
      if (entry.isIntersecting) {
        entry.target.classList.add('wc-visible');
        observer.unobserve(entry.target);
      }
    });
  }, observerOptions);

  // Observe all sections
  document.querySelectorAll('.wc-section').forEach(function(section) {
    observer.observe(section);
  });

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

  // Add loaded class for initial animations
  setTimeout(function() {
    document.body.classList.add('wc-loaded');
  }, 100);

})();
