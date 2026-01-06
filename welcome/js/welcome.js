// Welcome Page JavaScript
(function() {
  'use strict';

  // Smooth scroll for hero scroll indicator
  const scrollIndicator = document.querySelector('.wc-hero-scroll');
  if (scrollIndicator) {
    scrollIndicator.addEventListener('click', function() {
      const teachingSection = document.querySelector('.wc-teaching-section');
      if (teachingSection) {
        teachingSection.scrollIntoView({ behavior: 'smooth' });
      }
    });
  }

  // Add entrance animations when elements come into view
  const observerOptions = {
    threshold: 0.008,
    rootMargin: '0px 0px -50px 0px'
  };

  const observer = new IntersectionObserver(function(entries) {
    entries.forEach(function(entry) {
      if (entry.isIntersecting) {
        entry.target.style.opacity = '1';
        entry.target.style.transform = 'translateY(0)';
        observer.unobserve(entry.target);
      }
    });
  }, observerOptions);

  // Observe all sections
  document.querySelectorAll('.wc-section').forEach(function(section) {
    section.style.opacity = '0';
    section.style.transform = 'translateY(20px)';
    section.style.transition = 'opacity 0.6s ease-out, transform 0.6s ease-out';
    observer.observe(section);
  });

  // Add hover effect to action cards
  document.querySelectorAll('.wc-action-card').forEach(function(card) {
    card.addEventListener('mouseenter', function() {
      this.style.zIndex = '10';
    });

    card.addEventListener('mouseleave', function() {
      this.style.zIndex = '1';
    });
  });

  // Theme sync - listen to header theme changes
  window.addEventListener('themeChanged', function(e) {
    // Theme changed - could add visual feedback here if needed
  });

  // Sparkle effect enhancement
  function createSparkle(x, y, delay) {
    const sparkle = document.createElement('div');
    sparkle.className = 'wc-sparkle';
    sparkle.style.setProperty('--x', x + '%');
    sparkle.style.setProperty('--y', y + '%');
    sparkle.style.setProperty('--delay', delay + 's');
    return sparkle;
  }

  const sparklesContainer = document.querySelector('.wc-sparkles');
  if (sparklesContainer) {
    // Create random sparkles
    for (let i = 0; i < 15; i++) {
      const x = Math.random() * 100;
      const y = Math.random() * 100;
      const delay = Math.random() * 2;
      sparklesContainer.appendChild(createSparkle(x, y, delay));
    }
  }

  // Parallax scroll effect for hero glow
  let ticking = false;

  function updateParallax() {
    const scrolled = window.pageYOffset;
    const heroGlow = document.querySelector('.wc-hero-glow');

    if (heroGlow) {
      const parallaxSpeed = 0.5;
      heroGlow.style.transform = `translateY(${scrolled * parallaxSpeed}px)`;
    }

    ticking = false;
  }

  window.addEventListener('scroll', function() {
    if (!ticking) {
      window.requestAnimationFrame(updateParallax);
      ticking = true;
    }
  });

  // Enhanced card interactions
  document.querySelectorAll('.wc-teaching-card, .wc-quote-card').forEach(function(card) {
    card.addEventListener('mouseenter', function() {
      this.style.transform = 'translateY(-5px)';
    });

    card.addEventListener('mouseleave', function() {
      this.style.transform = 'translateY(0)';
    });
  });

  // Smooth reveal for teaching content
  const teachingContent = document.querySelector('.wc-teaching-content');
  if (teachingContent) {
    const contentObserver = new IntersectionObserver(function(entries) {
      entries.forEach(function(entry) {
        if (entry.isIntersecting) {
          const children = entry.target.children;
          Array.from(children).forEach(function(child, index) {
            setTimeout(function() {
              child.style.opacity = '1';
              child.style.transform = 'translateY(0)';
            }, index * 100);
          });
          contentObserver.unobserve(entry.target);
        }
      });
    }, { threshold: 0.1 });

    contentObserver.observe(teachingContent);
  }

  // Add loading animation complete
  setTimeout(function() {
    document.body.classList.add('loaded');
  }, 100);

})();
