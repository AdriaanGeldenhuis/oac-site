// =====================================================================
// /aislimbybel/js/aislimbybel.js — AI Slimbybel JavaScript
// =====================================================================

(function() {
  'use strict';

  // DOM Elements
  const form = document.getElementById('sbForm');
  const input = document.getElementById('q_input');
  const btn = document.getElementById('askBtn');
  const box = document.getElementById('answerBox');
  const content = document.getElementById('answerContent');
  const placeholder = document.getElementById('placeholder');
  const loadingIndicator = document.getElementById('loadingIndicator');
  const exampleCards = document.querySelectorAll('.sb-example-card');

  let eventSource = null;
  let rawText = ''; // Track raw accumulated text

  // ===== UTILITY FUNCTIONS =====

  function escapeHTML(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  function formatAnswer(text) {
    // Convert raw text to formatted HTML
    let html = escapeHTML(text);

    // Convert markdown-style bold (**text**)
    html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');

    // Convert markdown-style italic (*text*)
    html = html.replace(/\*(.+?)\*/g, '<em>$1</em>');

    // Split into paragraphs on double line breaks
    const paragraphs = html.split(/\n\n+/);
    
    html = paragraphs.map(para => {
      para = para.trim();
      if (!para) return '';

      // Check if it's a Bible verse reference (contains book name and numbers)
      if (/^[A-Z][a-z]+\s+\d+:\d+/.test(para)) {
        return `<p class="sb-verse">${para}</p>`;
      }

      // Check if it's a heading (starts with number or capital letter and ends with colon)
      if (/^\d+\.|^[A-Z][^.!?]*:$/.test(para)) {
        return `<h4 class="sb-heading">${para}</h4>`;
      }

      // Regular paragraph
      return `<p class="sb-paragraph">${para}</p>`;
    }).join('');

    return html;
  }

  function stopStream() {
    if (eventSource) {
      eventSource.close();
      eventSource = null;
    }
    btn.disabled = false;
    loadingIndicator.hidden = true;
    
    // Final format of complete text
    if (rawText) {
      content.innerHTML = formatAnswer(rawText);
    }
  }

  function scrollToAnswer() {
    setTimeout(() => {
      const answerSection = document.querySelector('.sb-answer-section');
      if (answerSection) {
        answerSection.scrollIntoView({ 
          behavior: 'smooth', 
          block: 'start' 
        });
      }
    }, 100);
  }

  // ===== ASK QUESTION =====

  function askQuestion(question) {
    if (!question || question.trim() === '') return;

    // Stop any existing stream
    if (eventSource) stopStream();

    // Reset
    rawText = '';

    // Update UI
    input.value = question;
    placeholder.hidden = true;
    content.hidden = false;
    content.innerHTML = '';
    btn.disabled = true;
    loadingIndicator.hidden = false;

    // Scroll to answer
    scrollToAnswer();

    // Build SSE URL
    const url = '/aislimbybel/aislimbybel.php?stream=1&q=' + encodeURIComponent(question);

    // Create EventSource
    eventSource = new EventSource(url);

    eventSource.onmessage = function(e) {
      const token = e.data;
      if (token) {
        rawText += token;
        // Update display with formatted version (reformat on each token for live preview)
        content.innerHTML = formatAnswer(rawText);
        box.scrollTop = box.scrollHeight;
      }
    };

    eventSource.addEventListener('error', function(e) {
      console.error('SSE Error:', e);
      stopStream();
    });

    eventSource.addEventListener('done', function() {
      stopStream();
    });

    eventSource.onerror = function() {
      stopStream();
    };
  }

  // ===== EVENT LISTENERS =====

  // Form submit
  if (form) {
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      const question = input.value.trim();
      askQuestion(question);
    });
  }

  // Example cards
  exampleCards.forEach(card => {
    card.addEventListener('click', function() {
      const question = this.getAttribute('data-question');
      askQuestion(question);
    });

    // Mouse move effect for cards
    card.addEventListener('mousemove', function(e) {
      const rect = this.getBoundingClientRect();
      const x = ((e.clientX - rect.left) / rect.width) * 100;
      const y = ((e.clientY - rect.top) / rect.height) * 100;
      this.style.setProperty('--mouse-x', x + '%');
      this.style.setProperty('--mouse-y', y + '%');
    });
  });

  // Focus input on load
  if (input) {
    input.focus();
  }

  // ===== ANIMATIONS =====

  // Add entrance animations to sections
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

  document.querySelectorAll('.sb-section').forEach(section => {
    section.style.opacity = '0';
    observer.observe(section);
  });

  // ===== CLEANUP =====

  window.addEventListener('beforeunload', function() {
    stopStream();
  });

})();