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
  const numberInput = document.getElementById('songNumberInput');
  const numberGoBtn = document.getElementById('songNumberGo');

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
      if (numberInput) {
        numberInput.value = '';
        filterSongGrid('');
        // Only auto-focus on devices with a mouse/trackpad so the
        // on-screen keyboard doesn't cover the grid on phones
        if (window.matchMedia('(hover: hover)').matches) {
          numberInput.focus();
        }
      }
    }
  }

  function closeOverlay() {
    if (overlay) {
      overlay.hidden = true;
      document.body.style.overflow = '';
      window.scrollTo(0, savedScrollPosition);
    }
  }

  function filterSongGrid(value) {
    if (!grid) return;
    grid.querySelectorAll('.se-song-btn').forEach(function(btn) {
      const num = btn.getAttribute('data-song') || '';
      btn.hidden = value !== '' && num.indexOf(value) !== 0;
    });
  }

  function goToTypedSong() {
    if (!numberInput || !grid) return;
    const value = numberInput.value.trim();
    if (!value) return;
    if (grid.querySelector('.se-song-btn[data-song="' + value + '"]')) {
      navigateToSong(value);
    } else {
      numberInput.classList.add('se-input-error');
      setTimeout(function() {
        numberInput.classList.remove('se-input-error');
      }, 800);
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

  // Song number input - filter grid while typing, Enter jumps to the song
  if (numberInput) {
    numberInput.addEventListener('input', function() {
      const digits = numberInput.value.replace(/\D/g, '');
      if (numberInput.value !== digits) {
        numberInput.value = digits;
      }
      filterSongGrid(digits);
    });

    numberInput.addEventListener('keydown', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        goToTypedSong();
      }
    });
  }

  if (numberGoBtn) {
    numberGoBtn.addEventListener('click', function(e) {
      e.preventDefault();
      goToTypedSong();
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
    section.style.opacity = '0';
    observer.observe(section);
  });

  // ===== PREVENT ACCIDENTAL OVERLAY OPENING =====
  setTimeout(function() {
    if (overlay && !overlay.hidden) {
      closeOverlay();
    }
  }, 100);

})();