// =====================================================================
// Sing Emmanuel JavaScript
// =====================================================================

(function() {
  'use strict';

  // DOM Elements
  const overlay = document.getElementById('songPickerOverlay');
  const openBtn = document.getElementById('openSongPicker');
  const closeBtn = document.getElementById('closeSongPicker');
  const grid = document.getElementById('songPickerGrid');

  // ===== SAVE SCROLL POSITION =====
  let savedScrollPosition = 0;

  // ===== ENSURE OVERLAY IS CLOSED ON LOAD =====
  if (overlay) {
    overlay.hidden = true;
    document.body.style.overflow = '';
  }

  // ===== RESTORE SCROLL POSITION =====
  window.addEventListener('load', function() {
    const scrollPos = sessionStorage.getItem('scrollPosition');
    if (scrollPos) {
      window.scrollTo(0, parseInt(scrollPos, 10));
      sessionStorage.removeItem('scrollPosition');
    }
  });

  // ===== UTILITY FUNCTIONS =====

  function openOverlay() {
    if (overlay) {
      savedScrollPosition = window.pageYOffset;
      overlay.hidden = false;
      document.body.style.overflow = 'hidden';
    }
  }

  function closeOverlay() {
    if (overlay) {
      overlay.hidden = true;
      document.body.style.overflow = '';
      window.scrollTo(0, savedScrollPosition);
    }
  }

  function navigateToSong(songNum) {
    // Save current scroll position
    sessionStorage.setItem('scrollPosition', window.pageYOffset.toString());
    
    const urlParams = new URLSearchParams(window.location.search);
    const currentLang = urlParams.get('lang') || 'af';
    window.location.href = `?lang=${currentLang}&song=${songNum}`;
  }

  // ===== EVENT LISTENERS =====

  // Open overlay
  if (openBtn) {
    openBtn.addEventListener('click', function(e) {
      e.preventDefault();
      openOverlay();
    });
  }

  // Close overlay
  if (closeBtn) {
    closeBtn.addEventListener('click', function(e) {
      e.preventDefault();
      closeOverlay();
    });
  }

  // Close on backdrop click
  if (overlay) {
    overlay.addEventListener('click', function(e) {
      if (e.target === overlay || e.target.classList.contains('se-overlay-backdrop')) {
        closeOverlay();
      }
    });
  }

  // Song selection from grid
  if (grid) {
    grid.addEventListener('click', function(e) {
      const btn = e.target.closest('.se-song-btn');
      if (btn) {
        const songNum = btn.getAttribute('data-song');
        if (songNum) {
          navigateToSong(songNum);
        }
      }
    });
  }

  // Language links - prevent scroll to top
  const langLinks = document.querySelectorAll('.se-lang-btn');
  langLinks.forEach(link => {
    link.addEventListener('click', function(e) {
      // Save scroll position before navigation
      sessionStorage.setItem('scrollPosition', window.pageYOffset.toString());
    });
  });

  // Keyboard navigation
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && overlay && !overlay.hidden) {
      closeOverlay();
    }
  });

  // ===== ANIMATIONS =====

  const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
  };

  const observer = new IntersectionObserver(function(entries) {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.style.animation = 'fade-in-up 0.6s ease-out forwards';
        observer.unobserve(entry.target);
      }
    });
  }, observerOptions);

  document.querySelectorAll('.se-section').forEach(section => {
    observer.observe(section);
  });

})();