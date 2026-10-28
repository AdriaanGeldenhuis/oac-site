/**
 * AI Slimbybel — ask a question, the answer streams in.
 *
 * Reads: /aislimbybel/aislimbybel.php?stream=1&q= (Server-Sent Events: a
 * "message" per piece of the answer, "error" with a message to show,
 * "done" at the end). Nothing is stored.
 *
 * The answer is escaped first and only then given its few marks (bold,
 * italics, headings, lists, Scripture quotes), so nothing the model writes
 * is ever drawn as HTML.
 */
(function () {
  'use strict';

  const CFG = window.SLIMBYBEL || {};
  const T = CFG.t || {};
  const MAX = CFG.max || 500;
  const $ = (s) => document.querySelector(s);

  const form = $('#sbForm');
  const input = $('#q_input');
  const askBtn = $('#askBtn');
  const section = $('#sbAnswerSection');
  const questionEl = $('#sbQuestion');
  const content = $('#answerContent');
  const typing = $('#loadingIndicator');
  const errorEl = $('#sbError');
  const actions = $('#sbActions');
  const stopBtn = $('#sbStop');
  const copyBtn = $('#sbCopy');
  const shareBtn = $('#sbShare');
  const againBtn = $('#sbAgain');
  if (!form || !input || !section || !content) return;

  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  let source = null;        // the EventSource of the answer being written
  let rawText = '';         // the answer as it arrived
  let question = '';
  let serverError = '';
  let renderQueued = false;

  // ===== FORMATTING (escaped text in, a few safe marks out) =====
  const ESC = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
  const escapeHTML = (s) => s.replace(/[&<>"']/g, (c) => ESC[c]);

  // "Joh. 3:16", "1 Kor 13:4-7", "Psalm 23:1" at the start of a paragraph
  const REF = /^(?:[1-3]\s?)?[A-Za-zÀ-ÿ]+\.?\s+\d{1,3}:\d{1,3}/;
  const QUOTE = /^(“|‘|&quot;|&#39;)/;

  function inline(s) {
    return s
      .replace(/\*\*(?=\S)([\s\S]*?\S)\*\*/g, '<strong>$1</strong>')
      .replace(/(^|[^*\w])\*(?=\S)([^*]*?\S)\*(?![*\w])/g, '$1<em>$2</em>');
  }

  function format(text) {
    const out = [];
    let para = [];
    let paraKind = 'p';
    let list = null;

    function flushPara() {
      if (!para.length) return;
      const html = para.map(inline).join('<br>');
      const one = para.length === 1 ? para[0] : '';
      if (paraKind === 'quote' || REF.test(para[0]) || QUOTE.test(para[0])) {
        out.push('<blockquote class="sb-verse">' + html + '</blockquote>');
      } else if (one && (/^\*\*[^*]{1,90}\*\*:?$/.test(one) || (one.length <= 90 && /:$/.test(one)))) {
        out.push('<h4 class="sb-heading">' + html + '</h4>');
      } else {
        out.push('<p>' + html + '</p>');
      }
      para = [];
      paraKind = 'p';
    }

    function flushList() {
      if (!list) return;
      const start = list.tag === 'ol' && list.start > 1 ? ' start="' + list.start + '"' : '';
      out.push('<' + list.tag + ' class="sb-list"' + start + '>' +
        list.items.map((i) => '<li>' + inline(i) + '</li>').join('') +
        '</' + list.tag + '>');
      list = null;
    }

    function addTo(kind, line) {
      if (paraKind !== kind) flushPara();
      paraKind = kind;
      para.push(line);
    }

    escapeHTML(text).split('\n').forEach((raw) => {
      const line = raw.trim();
      let m;
      if (!line || /^(-{3,}|\*{3,}|_{3,})$/.test(line)) {
        flushPara();
        flushList();
        return;
      }
      if ((m = /^#{1,6}\s+(.+?)\s*#*$/.exec(line))) {
        flushPara();
        flushList();
        out.push('<h4 class="sb-heading">' + inline(m[1]) + '</h4>');
        return;
      }
      if ((m = /^&gt;\s?(.*)$/.exec(line))) {
        flushList();
        addTo('quote', m[1]);
        return;
      }
      if ((m = /^[-*•]\s+(.+)$/.exec(line))) {
        flushPara();
        if (!list || list.tag !== 'ul') { flushList(); list = { tag: 'ul', items: [] }; }
        list.items.push(m[1]);
        return;
      }
      if ((m = /^(\d{1,2})[.)]\s+(.+)$/.exec(line))) {
        flushPara();
        if (!list || list.tag !== 'ol') { flushList(); list = { tag: 'ol', start: +m[1], items: [] }; }
        list.items.push(m[2]);
        return;
      }
      flushList();
      addTo('p', line);
    });
    flushPara();
    flushList();
    return out.join('');
  }

  /** The answer as plain text, for copy and share */
  function plain(text) {
    return text
      .replace(/\*\*([\s\S]*?)\*\*/g, '$1')
      .replace(/^#{1,6}\s+/gm, '')
      .replace(/^[-*]\s+/gm, '• ')
      .replace(/^>\s?/gm, '')
      .trim();
  }

  function render() {
    renderQueued = false;
    content.innerHTML = format(rawText);
  }

  function queueRender() {
    if (renderQueued) return;
    renderQueued = true;
    requestAnimationFrame(render);
  }

  // ===== STATE =====
  function setStreaming(on) {
    const hasText = rawText.trim() !== '';
    askBtn.disabled = on;
    typing.hidden = !on;
    stopBtn.hidden = !on;
    copyBtn.hidden = on || !hasText;
    shareBtn.hidden = on || !hasText;
    againBtn.hidden = on;
    actions.hidden = false;
    content.setAttribute('aria-busy', on ? 'true' : 'false');
  }

  function showError(message) {
    errorEl.textContent = message;
    errorEl.hidden = false;
  }

  function closeSource() {
    if (source) {
      source.close();
      source = null;
    }
  }

  function finish(dropped) {
    closeSource();
    render();
    if (serverError) {
      showError(serverError);
    } else if (!rawText.trim()) {
      showError(T.error || 'Error');
    } else if (dropped) {
      toast(T.cutOff, true);
    }
    setStreaming(false);
  }

  // ===== ASK =====
  function ask(q) {
    q = (q || '').trim().slice(0, MAX);
    if (!q) {
      input.focus();
      return;
    }

    closeSource();
    rawText = '';
    serverError = '';
    question = q;
    input.value = q;
    autoGrow();

    section.hidden = false;
    questionEl.textContent = q;
    content.innerHTML = '';
    errorEl.hidden = true;
    errorEl.textContent = '';
    setStreaming(true);
    section.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });

    const es = new EventSource('/aislimbybel/aislimbybel.php?stream=1&q=' + encodeURIComponent(q));
    source = es;

    es.onmessage = (e) => {
      if (source !== es) return;
      if (e.data) {
        rawText += e.data;
        queueRender();
      }
    };

    // "error" is both the server's own message (with data) and a lost connection (without)
    es.addEventListener('error', (e) => {
      if (source !== es) return;
      if (e.data) {
        serverError = e.data;
      } else {
        finish(true);
      }
    });

    es.addEventListener('done', () => {
      if (source !== es) return;
      finish(false);
    });
  }

  function stop() {
    if (!source) return;
    closeSource();
    render();
    setStreaming(false);
  }

  // ===== TOAST =====
  let toastTimer = 0;
  function toast(message, isError) {
    const t = $('#sbToast');
    if (!t || !message) return;
    t.textContent = message;
    t.classList.toggle('is-error', !!isError);
    t.classList.add('is-on');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => t.classList.remove('is-on'), isError ? 4200 : 2400);
  }

  // ===== SHARE / COPY (works in WebViews without clipboard permission) =====
  function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(text).catch(() => copyLegacy(text));
    }
    return copyLegacy(text);
  }

  function copyLegacy(text) {
    return new Promise((resolve, reject) => {
      const ta = document.createElement('textarea');
      ta.className = 'sb-copy-buffer';
      ta.setAttribute('readonly', '');
      ta.setAttribute('aria-hidden', 'true');
      ta.value = text;
      document.body.appendChild(ta);
      ta.select();
      try {
        if (document.execCommand('copy')) resolve(); else reject(new Error('copy'));
      } catch (e) {
        reject(e);
      } finally {
        ta.remove();
      }
    });
  }

  function answerText() {
    return '“' + question + '”\n\n' + plain(rawText) + '\n\n— ' + (T.title || 'AI Slimbybel');
  }

  function copyAnswer() {
    copyText(answerText())
      .then(() => toast(T.copied))
      .catch(() => toast(T.copyFail, true));
  }

  function shareAnswer() {
    const text = answerText();
    if (navigator.share) {
      navigator.share({ title: T.title, text: text }).catch((e) => {
        if (!e || e.name !== 'AbortError') copyAnswer();
      });
    } else {
      copyAnswer();
    }
  }

  // ===== THE QUESTION FIELD: grows with the text, Enter asks =====
  function autoGrow() {
    input.style.height = 'auto';
    input.style.height = input.scrollHeight + 2 + 'px';
  }

  input.addEventListener('input', autoGrow);
  input.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
      e.preventDefault();
      if (!askBtn.disabled) ask(input.value);
    }
  });

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    if (!askBtn.disabled) ask(input.value);
  });

  document.querySelectorAll('.sb-ex').forEach((btn) => {
    btn.addEventListener('click', () => ask(btn.getAttribute('data-question')));
  });

  stopBtn.addEventListener('click', stop);
  copyBtn.addEventListener('click', copyAnswer);
  shareBtn.addEventListener('click', shareAnswer);
  againBtn.addEventListener('click', () => {
    input.value = '';
    autoGrow();
    form.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'center' });
    try { input.focus({ preventScroll: true }); } catch (e) { input.focus(); }
  });

  // Only with a mouse/trackpad: on phones the keyboard would cover the page
  if (window.matchMedia('(hover: hover)').matches) {
    try { input.focus({ preventScroll: true }); } catch (e) { input.focus(); }
  }

  window.addEventListener('pagehide', closeSource);
})();
