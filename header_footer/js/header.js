// =====================================================================
// /header_footer/js/header.js — Theme Toggle & Navigation
// =====================================================================

(function() {
  'use strict';

  const hamburger = document.getElementById('ghfHamburger');
  const drawer = document.getElementById('ghfDrawer');
  const drawerClose = document.getElementById('ghfDrawerClose');
  const overlay = document.getElementById('ghfOverlay');
  const langBtn = document.getElementById('ghfLangBtn');
  const langMenu = document.getElementById('ghfLangMenu');
  const viewToggle = document.getElementById('ghfViewToggle');
  const themeToggle = document.getElementById('ghfThemeToggle');

  // ===== THEME MANAGEMENT =====
  function getTheme() {
    return localStorage.getItem('theme') || 'dark';
  }

  function setTheme(theme) {
    localStorage.setItem('theme', theme);
    document.documentElement.setAttribute('data-theme', theme);
    
    // Dispatch event for other components
    window.dispatchEvent(new CustomEvent('themeChanged', {
      detail: { theme: theme }
    }));
  }

  function toggleTheme() {
    const currentTheme = getTheme();
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    setTheme(newTheme);
  }

  // Initialize theme on load
  setTheme(getTheme());

  if (themeToggle) {
    themeToggle.addEventListener('click', toggleTheme);
  }

  // ===== NAVIGATION =====
  function openNav() {
    document.body.classList.add('ghf-open');
    overlay.hidden = false;
    drawer.setAttribute('aria-hidden', 'false');
  }

  function closeNav() {
    document.body.classList.remove('ghf-open');
    overlay.hidden = true;
    drawer.setAttribute('aria-hidden', 'true');
  }

  function toggleLang() {
    const isHidden = langMenu.hasAttribute('hidden');
    if (isHidden) {
      langMenu.removeAttribute('hidden');
      langBtn.setAttribute('aria-expanded', 'true');
    } else {
      langMenu.setAttribute('hidden', '');
      langBtn.setAttribute('aria-expanded', 'false');
    }
  }

  function setLang(lang) {
    // Check if we're on the Bible page
    const isBiblePage = window.location.pathname.includes('/bible/bible.php');
    
    if (isBiblePage) {
      // Dispatch event for Bible reader to handle language change
      window.dispatchEvent(new CustomEvent('languageChanged', {
        detail: { lang: lang }
      }));
      
      // Then update URL and reload
      setTimeout(() => {
        const url = new URL(window.location.href);
        url.searchParams.set('lang', lang);
        window.location.href = url.toString();
      }, 100);
    } else {
      // For other pages, just reload with new language
      const url = new URL(window.location.href);
      url.searchParams.set('lang', lang);
      window.location.href = url.toString();
    }
  }

  // Bible View Toggle
  if (viewToggle) {
    viewToggle.addEventListener('click', function() {
      const currentState = localStorage.getItem('bible_dual_view');
      const newState = currentState === '0' ? '1' : '0';
      localStorage.setItem('bible_dual_view', newState);
      
      viewToggle.classList.toggle('active');
      
      // Trigger event for Bible reader to listen to
      window.dispatchEvent(new CustomEvent('bibleViewToggle', { detail: { enabled: newState === '1' } }));
    });
    
    // Set initial state
    const savedState = localStorage.getItem('bible_dual_view');
    if (savedState === '0') {
      viewToggle.classList.add('active');
    }
  }

  // Event listeners
  if (hamburger) hamburger.addEventListener('click', openNav);
  if (drawerClose) drawerClose.addEventListener('click', closeNav);
  if (overlay) overlay.addEventListener('click', closeNav);
  
  if (langBtn) {
    langBtn.addEventListener('click', function(e) {
      e.stopPropagation();
      toggleLang();
    });
  }

  document.addEventListener('click', function(e) {
    if (langMenu && !langMenu.contains(e.target) && e.target !== langBtn) {
      langMenu.setAttribute('hidden', '');
      langBtn.setAttribute('aria-expanded', 'false');
    }
  });

  if (langMenu) {
    langMenu.querySelectorAll('a').forEach(function(a) {
      a.addEventListener('click', function(e) {
        e.preventDefault();
        setLang(a.getAttribute('data-lang'));
      });
    });
  }

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      closeNav();
      if (langMenu && !langMenu.hasAttribute('hidden')) {
        langMenu.setAttribute('hidden', '');
        langBtn.setAttribute('aria-expanded', 'false');
      }
    }
  });

})();