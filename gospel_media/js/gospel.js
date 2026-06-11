/**
 * Gospel Media Main UI Logic
 * NO INLINE STYLES - All styling in CSS
 */
(function () {
  'use strict';

  // ===== HELPERS =====
  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => Array.from(el.querySelectorAll(s));
  const ce = (tag, attrs = {}) => {
    const n = document.createElement(tag);
    for (const k in attrs) {
      if (k === 'class') n.className = attrs[k];
      else if (k === 'text') n.textContent = attrs[k];
      else if (k === 'html') n.innerHTML = attrs[k];
      else n.setAttribute(k, attrs[k]);
    }
    return n;
  };

  const esc = (s) => String(s || '').replace(/[<>&"']/g, m => ({
    '<': '&lt;', '>': '&gt;', '&': '&amp;', '"': '&quot;', "'": '&#39;'
  }[m]));

  async function fetchJSON(url, opt) {
    const r = await fetch(url, Object.assign({ credentials: 'include' }, opt || {}));
    const t = await r.text();
    try {
      return JSON.parse(t);
    } catch {
      return { ok: false, error: t || (r.status + ' ' + r.statusText) };
    }
  }

  // Resize + convert an image File to WebP in the browser so uploads stay small.
  // Keeps originals untouched if the browser can't encode WebP or anything throws.
  function compressImageToWebP(file, maxDim = 1920, quality = 0.85) {
    return new Promise((resolve) => {
      if (!file || !file.type || !file.type.startsWith('image/')) return resolve(file);
      const url = URL.createObjectURL(file);
      const img = new Image();
      img.onload = () => {
        try {
          const { width, height } = img;
          const ratio = Math.min(1, maxDim / Math.max(width, height));
          const w = Math.max(1, Math.round(width * ratio));
          const h = Math.max(1, Math.round(height * ratio));
          const canvas = document.createElement('canvas');
          canvas.width = w;
          canvas.height = h;
          const ctx = canvas.getContext('2d');
          ctx.drawImage(img, 0, 0, w, h);
          URL.revokeObjectURL(url);
          canvas.toBlob((blob) => {
            if (!blob) return resolve(file);
            const base = (file.name || 'image').replace(/\.[^.]+$/, '');
            resolve(new File([blob], base + '.webp', { type: 'image/webp', lastModified: Date.now() }));
          }, 'image/webp', quality);
        } catch {
          URL.revokeObjectURL(url);
          resolve(file);
        }
      };
      img.onerror = () => { URL.revokeObjectURL(url); resolve(file); };
      img.src = url;
    });
  }

  async function compressImages(files) {
    const out = [];
    for (const f of files) out.push(await compressImageToWebP(f));
    return out;
  }

  // Use server-provided translations for all 5 languages
  const T = (key) => (window.JS_T && window.JS_T[key]) ? window.JS_T[key] : key;

  // ===== STATE =====
  const ROOM_ID = window.CURRENT_ROOM_ID || 0;
  const ROOM_TYPE = (window.CURRENT_ROOM_TYPE || '').toLowerCase();
  const USER_ID = window.USER_ID || null;
  const USER_AMP_ID = window.USER_AMP_ID || 999;
  let ME = { id: USER_ID, amp_id: USER_AMP_ID };
  const POST_MAP = new Map();
  let activeType = 'post';

  // ===== DISPLAY NAME =====
  function computeDisplayName(p) {
    const first = (p.user_name || p.name || '').trim();
    const surname = (p.user_surname || p.surname || '').trim();
    
    if (p.amp_title && String(p.amp_title).trim() !== '') {
      return (p.amp_title + ' ' + first + ' ' + surname).trim();
    }
    
    const gender = ((p.user_gender || p.gender) || '').toLowerCase();
    const male = p.amp_male_name || 'Broer';
    const female = p.amp_female_name || 'Suster';
    const prefix = gender === 'vrou' ? female : male;
    
    return (prefix + ' ' + first + ' ' + surname).trim();
  }

  // ===== EDIT RIGHTS =====
  function canModerate() {
    const a = parseInt(ME.amp_id || 0, 10);
    if (!a) return false;
    const rt = ROOM_TYPE;
    if (rt === 'opsienerskap') return a >= 2 && a <= 5;
    if (rt === 'gemeente') return a >= 5 && a <= 9;
    if (rt === 'jeug' || rt === 'sondagskool') return a >= 1 && a <= 9;
    if (rt === 'gemeenskap') return a >= 1 && a <= 6;
    return false;
  }

  function canEditPost(p) {
    if (!ME.id) return false;
    if (String(ME.id) === String(p.user_id)) return true;
    return canModerate();
  }

  function canEditComment(c) {
    if (!ME.id) return false;
    if (String(ME.id) === String(c.user_id)) return true;
    return canModerate();
  }

  // ===== DATE FORMATTING =====
  // Show weekday names in the user's chosen language (falls back to English
  // if the browser doesn't ship that locale)
  const LOCALE_MAP = { af: 'af-ZA', en: 'en-GB', zu: 'zu-ZA', xh: 'xh-ZA', pt: 'pt-PT', st: 'st-ZA' };
  const DATE_LOCALES = [LOCALE_MAP[window.PAGE_LANG || 'af'] || 'af-ZA', 'en-GB'];

  const fmtDT = s => {
    try {
      // MySQL "YYYY-MM-DD HH:MM:SS" is not parseable on Safari/iOS - normalise to ISO
      const iso = (typeof s === 'string') ? s.replace(' ', 'T') : s;
      const d = new Date(iso);
      if (isNaN(d.getTime())) return s || '';
      const day = d.toLocaleDateString(DATE_LOCALES, { weekday: 'short' }).toUpperCase();
      const date = d.toLocaleDateString('en-GB', { year: '2-digit', month: '2-digit', day: '2-digit' });
      const time = d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', hour12: false });
      return `${day} ${date}, ${time}`;
    } catch {
      return s || '';
    }
  };

  // ===== COMPOSER OVERLAY =====
  function openComposerOverlay() {
    if (!window.CAN_POST) return;

    const existing = $('.composer-overlay');
    if (existing) existing.remove();

    activeType = 'post';

    const backdrop = ce('div', { class: 'composer-overlay' });
    const panel = ce('div', { class: 'composer-overlay-panel' });

    // Header
    const header = ce('div', { class: 'composer-overlay-header' });
    const title = ce('span', { class: 'composer-overlay-title', text: T('post') });
    const closeBtn = ce('button', { class: 'composer-overlay-close', type: 'button', text: '\u00d7' });
    header.append(title, closeBtn);

    function closeOverlay() { backdrop.remove(); }
    closeBtn.addEventListener('click', closeOverlay);
    backdrop.addEventListener('click', (e) => { if (e.target === backdrop) closeOverlay(); });

    // Tabs
    const tabs = ce('div', { class: 'composer-tabs' });
    const postTab = ce('button', { class: 'composer-tab active', type: 'button', text: T('post') });
    const evtTab = ce('button', { class: 'composer-tab', type: 'button', text: T('datetime') });
    tabs.append(postTab, evtTab);

    if (ROOM_TYPE === 'gemeenskap') evtTab.style.display = 'none';

    // Event fields
    const evtFields = ce('div', { class: 'composer-event-fields hide' });
    const evtAt = ce('input', { type: 'datetime-local', class: 'composer-input' });
    const evtPlace = ce('input', { type: 'text', class: 'composer-input', placeholder: T('place') });
    evtFields.append(evtAt, evtPlace);

    postTab.addEventListener('click', () => {
      activeType = 'post';
      postTab.classList.add('active');
      evtTab.classList.remove('active');
      evtFields.classList.add('hide');
    });
    evtTab.addEventListener('click', () => {
      activeType = 'event';
      evtTab.classList.add('active');
      postTab.classList.remove('active');
      evtFields.classList.remove('hide');
    });

    // Textarea
    const ta = ce('textarea', { class: 'composer-textarea', placeholder: T('type_message') });

    // File input + preview (each preview gets a remove button, like the edit modal)
    const fileInput = ce('input', { type: 'file', accept: 'image/*', multiple: 'multiple' });
    fileInput.style.display = 'none';
    const preview = ce('div', { class: 'composer-preview' });

    function renderPreviews() {
      preview.innerHTML = '';
      Array.from(fileInput.files || []).forEach((f, idx) => {
        const box = ce('div', { class: 'modal-img-box' });

        const img = ce('img', { class: 'composer-preview-img' });
        img.src = URL.createObjectURL(f);

        const x = ce('button', { type: 'button', class: 'modal-img-del', text: '×', title: T('remove') });
        x.addEventListener('click', () => {
          const dt = new DataTransfer();
          Array.from(fileInput.files).forEach((f2, i2) => {
            if (i2 !== idx) dt.items.add(f2);
          });
          fileInput.files = dt.files;
          renderPreviews();
        });

        box.append(img, x);
        preview.appendChild(box);
      });
    }

    fileInput.addEventListener('change', renderPreviews);

    // Actions
    const actions = ce('div', { class: 'composer-actions' });

    const attachBtn = ce('button', { type: 'button', class: 'composer-attach' });
    attachBtn.innerHTML = `<svg class="icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"
            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    </svg><span>${T('add_photo')}</span>`;
    attachBtn.addEventListener('click', () => fileInput.click());

    const submitBtn = ce('button', { type: 'button', class: 'composer-submit' });
    submitBtn.innerHTML = `<span class="sb-btn-shine"></span>
      <svg class="icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg><span>${T('post')}</span>`;

    submitBtn.addEventListener('click', async () => {
      const text = (ta.value || '').trim();
      const hasImages = fileInput.files && fileInput.files.length > 0;
      // A post needs text or at least one photo
      if (!text && !hasImages) { alert(T('type_message')); return; }

      const fd = new FormData();
      fd.append('room_id', ROOM_ID);
      fd.append('text', text);
      fd.append('type', activeType === 'event' ? 'event' : 'post');

      if (activeType === 'event') {
        if (evtAt.value) fd.append('event_at', evtAt.value);
        if (evtPlace.value) fd.append('event_place', evtPlace.value);
      }

      submitBtn.disabled = true;
      submitBtn.textContent = T('posting');

      if (fileInput.files && fileInput.files.length) {
        const compressed = await compressImages(Array.from(fileInput.files));
        compressed.forEach(f => fd.append('images[]', f));
      }

      const j = await fetchJSON('/gospel_media/api/posts/create.php', { method: 'POST', body: fd });

      submitBtn.disabled = false;
      submitBtn.innerHTML = `<span class="sb-btn-shine"></span>
        <svg class="icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg><span>${T('post')}</span>`;

      if (!(j && (j.ok || j.success))) {
        alert(j && j.error ? j.error : T('error_posting'));
        return;
      }

      closeOverlay();
      await loadFeed(ROOM_ID, true);
    });

    actions.append(attachBtn, submitBtn);

    panel.append(header, tabs, ta, evtFields, fileInput, preview, actions);
    backdrop.appendChild(panel);
    document.body.appendChild(backdrop);
    ta.focus();
  }

  // ===== REACTIONS =====
  function renderReactions(card, p) {
    const bar = ce('div', { class: 'post-actions' });
    const myReacts = Array.isArray(p.my_reactions) ? p.my_reactions : [];

    const bH = ce('button', { type: 'button', class: 'btn-react heart-btn' });
    if (myReacts.includes('heart')) bH.classList.add('reacted');
    const heartIcon = ce('img', { class: 'icon', src: '/assets/icons/heart.png', alt: 'heart' });
    const heartCount = ce('span', { class: 'badge badge-clickable count-heart', text: (p.heart_count || 0), title: T('reactions') });
    bH.append(heartIcon, heartCount);

    const bP = ce('button', { type: 'button', class: 'btn-react pray-btn' });
    if (myReacts.includes('pray')) bP.classList.add('reacted');
    const prayIcon = ce('img', { class: 'icon', src: '/assets/icons/amen.png', alt: 'amen' });
    const prayCount = ce('span', { class: 'badge badge-clickable count-pray', text: (p.pray_count || 0), title: T('reactions') });
    bP.append(prayIcon, prayCount);

    bH.addEventListener('click', () => toggleReact(p.id, 'heart', bH, bP));
    bP.addEventListener('click', () => toggleReact(p.id, 'pray', bH, bP));

    // WhatsApp/FB style: tapping the count shows who reacted
    heartCount.addEventListener('click', (e) => { e.stopPropagation(); openReactionsModal(p); });
    prayCount.addEventListener('click', (e) => { e.stopPropagation(); openReactionsModal(p); });

    const bC = ce('button', { type: 'button', class: 'btn-react comments-btn' });
    bC.innerHTML = `
      <svg class="icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      <span class="badge count-comments">${p.comment_count || 0}</span>
    `;
    bC.addEventListener('click', () => toggleComments(p.id, card));

    bar.append(bH, bP, bC);
    card.appendChild(bar);
  }

  const reactInFlight = new Set();

  async function toggleReact(post_id, type, bH, bP) {
    const key = post_id + ':' + type;
    if (reactInFlight.has(key)) return;
    reactInFlight.add(key);

    const btn = type === 'heart' ? bH : bP;
    const countEl = btn.querySelector('.badge');

    // Optimistic update so the button feels instant; rolled back on failure
    const wasReacted = btn.classList.contains('reacted');
    const prevCount = parseInt(countEl.textContent || '0', 10);
    btn.classList.toggle('reacted');
    countEl.textContent = Math.max(0, prevCount + (wasReacted ? -1 : 1));

    const fd = new FormData();
    fd.append('post_id', post_id);
    fd.append('type', type);

    const j = await fetchJSON('/gospel_media/api/posts/react.php', { method: 'POST', body: fd });
    reactInFlight.delete(key);

    if (!(j && j.ok)) {
      btn.classList.toggle('reacted');
      countEl.textContent = prevCount;
      return;
    }

    // Sync with the authoritative server counts
    if (bH && typeof j.heart_count !== 'undefined') {
      bH.querySelector('.count-heart').textContent = j.heart_count;
    }
    if (bP && typeof j.pray_count !== 'undefined') {
      bP.querySelector('.count-pray').textContent = j.pray_count;
    }

    // Keep the cached post in step for re-renders and the reactions modal
    const p = POST_MAP.get(String(post_id));
    if (p) {
      if (typeof j.heart_count !== 'undefined') p.heart_count = j.heart_count;
      if (typeof j.pray_count !== 'undefined') p.pray_count = j.pray_count;
      const mine = Array.isArray(p.my_reactions) ? p.my_reactions : (p.my_reactions = []);
      const idx = mine.indexOf(type);
      if (wasReacted && idx !== -1) mine.splice(idx, 1);
      if (!wasReacted && idx === -1) mine.push(type);
    }
  }

  // ===== WHO REACTED (WhatsApp/FB style) =====
  function openReactionsModal(p) {
    const back = ce('div', { class: 'modal-backdrop' });
    back.addEventListener('click', (e) => { if (e.target === back) back.remove(); });

    const panel = ce('div', { class: 'modal-panel reactions-panel' });
    const title = ce('h2', { text: T('reactions') });

    const tabs = ce('div', { class: 'reactions-tabs' });
    const list = ce('div', { class: 'reactions-list' });
    list.innerHTML = '<p class="muted">' + T('loading') + '</p>';

    const actions = ce('div', { class: 'modal-actions' });
    const closeBtn = ce('button', { class: 'modal-btn', type: 'button', text: T('back') });
    closeBtn.addEventListener('click', () => back.remove());
    actions.appendChild(closeBtn);

    panel.append(title, tabs, list, actions);
    back.appendChild(panel);
    document.body.appendChild(back);

    fetchJSON('/gospel_media/api/posts/reactions.php?post_id=' + encodeURIComponent(p.id)).then(j => {
      const all = (j && j.ok && Array.isArray(j.reactions)) ? j.reactions : [];

      if (!all.length) {
        list.innerHTML = '<p class="muted">' + T('no_reactions') + '</p>';
        return;
      }

      const hearts = all.filter(r => r.type === 'heart');
      const prays = all.filter(r => r.type === 'pray');

      function renderList(rows) {
        list.innerHTML = '';
        if (!rows.length) {
          list.innerHTML = '<p class="muted">' + T('no_reactions') + '</p>';
          return;
        }
        rows.forEach(r => {
          const item = ce('div', { class: 'reaction-item' });

          let avatar;
          if (r.photo) {
            avatar = ce('img', { class: 'comment-avatar', alt: 'avatar' });
            avatar.src = r.photo;
          } else {
            avatar = ce('div', { class: 'comment-avatar gm-avatar-initials gm-avatar-initials-sm' });
            avatar.textContent = r.initials || '??';
          }

          const name = ce('a', {
            class: 'reaction-name',
            href: '/profile/index.php?u=' + encodeURIComponent(r.user_id),
            text: computeDisplayName(r)
          });

          const emoji = ce('span', { class: 'reaction-emoji', text: r.type === 'heart' ? '❤️' : '🙏' });

          item.append(avatar, name, emoji);
          list.appendChild(item);
        });
      }

      function makeTab(label, rows) {
        const b = ce('button', { type: 'button', class: 'reactions-tab', text: label });
        b.addEventListener('click', () => {
          $$('.reactions-tab', tabs).forEach(t => t.classList.remove('active'));
          b.classList.add('active');
          renderList(rows);
        });
        return b;
      }

      const tabAll = makeTab(T('all') + ' ' + all.length, all);
      tabAll.classList.add('active');
      tabs.appendChild(tabAll);
      if (hearts.length) tabs.appendChild(makeTab('❤️ ' + hearts.length, hearts));
      if (prays.length) tabs.appendChild(makeTab('🙏 ' + prays.length, prays));

      renderList(all);
    });
  }

  // ===== COMMENTS =====
  async function deleteComment(commentId, postId, listEl) {
    if (!confirm(T('delete_comment'))) return;
    
    const fd = new FormData();
    fd.append('comment_id', commentId);
    
    const j = await fetchJSON('/gospel_media/api/comments/delete.php', { method: 'POST', body: fd });
    
    if (!(j && (j.ok || j.success))) {
      alert(j && j.error ? j.error : T('error'));
      return;
    }
    
    const card = document.querySelector(`[data-post-id="${postId}"]`);
    if (card) {
      const countBadge = card.querySelector('.count-comments');
      if (countBadge) {
        const currentCount = parseInt(countBadge.textContent || '0', 10);
        countBadge.textContent = Math.max(0, currentCount - 1);
      }
    }
    
    await loadComments(postId, listEl);
  }

  function editComment(commentId, currentText, postId, listEl) {
    // Find the comment item and replace text with inline editor
    const items = $$(`.comment-item`, listEl);
    const item = items.find(el => {
      const editBtn = el.querySelector('.comment-action-btn');
      return editBtn && editBtn._commentId === commentId;
    });
    if (!item) return;

    const textEl = item.querySelector('.comment-text');
    if (!textEl) return;

    const ta = ce('textarea', { class: 'comment-input comment-edit-input' });
    ta.value = currentText;
    ta.rows = 2;

    const btnRow = ce('div', { class: 'comment-edit-actions' });
    const saveBtn = ce('button', { class: 'comment-submit', type: 'button', text: T('save') });
    const cancelBtn = ce('button', { class: 'modal-btn', type: 'button', text: T('cancel') });

    cancelBtn.addEventListener('click', () => {
      ta.replaceWith(textEl);
      btnRow.remove();
    });

    saveBtn.addEventListener('click', async () => {
      const newText = ta.value.trim();
      if (!newText || newText === currentText) {
        ta.replaceWith(textEl);
        btnRow.remove();
        return;
      }
      saveBtn.disabled = true;
      saveBtn.textContent = T('saving');

      const fd = new FormData();
      fd.append('comment_id', commentId);
      fd.append('text', newText);

      const j = await fetchJSON('/gospel_media/api/comments/update.php', { method: 'POST', body: fd });
      if (!(j && (j.ok || j.success))) {
        alert(j && j.error ? j.error : T('error'));
        saveBtn.disabled = false;
        saveBtn.textContent = T('save');
        return;
      }
      await loadComments(postId, listEl);
    });

    btnRow.append(saveBtn, cancelBtn);
    textEl.replaceWith(ta);
    ta.after(btnRow);
    ta.focus();
  }

 async function toggleComments(postId, card) {
    let commentsSection = $('.comments-section', card);
    
    if (commentsSection) {
      const isHidden = commentsSection.classList.contains('hide');
      if (isHidden) {
        commentsSection.classList.remove('hide');
      } else {
        commentsSection.classList.add('hide');
      }
      return;
    }
    
    commentsSection = ce('div', { class: 'comments-section' });
    commentsSection.innerHTML = `
      <div class="comments-header">
        <h3 class="comments-title">${T('comments')}</h3>
      </div>
      <div class="comments-list"></div>
      <div class="comment-composer">
        <textarea class="comment-input" placeholder="${T('write_comment')}" rows="1"></textarea>
        <button class="comment-submit" type="button">${T('post')}</button>
      </div>
    `;
    
    card.appendChild(commentsSection);
    
    const list = $('.comments-list', commentsSection);
    const input = $('.comment-input', commentsSection);
    const submit = $('.comment-submit', commentsSection);
    
    if (input) {
      input.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
      });
      // WhatsApp style: Enter sends, Shift+Enter makes a new line
      input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          if (submit && !submit.disabled) submit.click();
        }
      });
    }

    await loadComments(postId, list);
    
    if (submit) {
      submit.addEventListener('click', async () => {
        const text = input.value.trim();
        if (!text) return;
        
        submit.disabled = true;
        submit.textContent = '...';
        
        const fd = new FormData();
        fd.append('post_id', postId);
        fd.append('text', text);
        
        const j = await fetchJSON('/gospel_media/api/comments/create.php', { method: 'POST', body: fd });
        
        submit.disabled = false;
        submit.textContent = T('post');
        
        if (!(j && (j.ok || j.success))) {
          alert(j && j.error ? j.error : T('error'));
          return;
        }
        
        input.value = '';
        input.style.height = 'auto';
        
        const countBadge = card.querySelector('.count-comments');
        if (countBadge && j.comment) {
          const currentCount = parseInt(countBadge.textContent || '0', 10);
          countBadge.textContent = currentCount + 1;
        }
        
        await loadComments(postId, list);
      });
    }
  }

  async function loadComments(postId, listEl) {
    if (!listEl) return;
    
    listEl.innerHTML = '<p class="muted">' + T('loading') + '</p>';
    
    const j = await fetchJSON('/gospel_media/api/comments/list.php?post_id=' + postId);
    const comments = Array.isArray(j) ? j : [];
    
    listEl.innerHTML = '';
    
    if (!comments.length) {
      listEl.innerHTML = '<p class="muted">' + T('no_comments') + '</p>';
      return;
    }
    
    comments.forEach(c => {
      const item = ce('div', { class: 'comment-item' });
      
      const header = ce('div', { class: 'comment-header' });
      
      let avatar;
      if (c.photo) {
        avatar = ce('img', { class: 'comment-avatar' });
        avatar.src = c.photo;
        avatar.alt = 'avatar';
      } else {
        avatar = ce('div', { class: 'comment-avatar gm-avatar-initials gm-avatar-initials-sm' });
        avatar.textContent = c.initials || '??';
      }
      
      const author = ce('div', { class: 'comment-author' });
      author.textContent = computeDisplayName(c);
      
      const time = ce('div', { class: 'comment-time' });
      time.textContent = c.created_at ? fmtDT(c.created_at) : '';
      
      header.append(avatar, author, time);
      
      if (canEditComment(c)) {
        const actions = ce('div', { class: 'comment-actions' });

        const editBtn = ce('button', { class: 'comment-action-btn', type: 'button' });
        editBtn.textContent = '✎';
        editBtn.title = T('edit');
        editBtn._commentId = c.id;
        editBtn.addEventListener('click', () => editComment(c.id, c.text, postId, listEl));

        const delBtn = ce('button', { class: 'comment-action-btn', type: 'button' });
        delBtn.textContent = '×';
        delBtn.title = T('delete');
        delBtn.addEventListener('click', () => deleteComment(c.id, postId, listEl));

        actions.append(editBtn, delBtn);
        header.appendChild(actions);
      }
      
      const text = ce('div', { class: 'comment-text', text: c.text || '' });
      
      item.append(header, text);
      listEl.appendChild(item);
    });
  }

  // ===== POST CARDS =====
  function renderCard(p) {
    // Key by string so lookups from data attributes always match,
    // regardless of whether the API returned numeric or string ids
    POST_MAP.set(String(p.id), p);

    const card = ce('article', { class: 'post-card', id: 'post-' + p.id, 'data-post-id': String(p.id) });
    const header = ce('div', { class: 'post-header' });
    
    let avatar;
    if (p.user_photo) {
      avatar = ce('img', { class: 'avatar' });
      avatar.src = p.user_photo;
    } else {
      avatar = ce('div', { class: 'avatar gm-avatar-initials' });
      avatar.textContent = p.initials || '??';
    }
    header.appendChild(avatar);
    
    const info = ce('div');
    const _nmDiv = ce('div', { class: 'name' });
    const _a = ce('a', {
      class: 'name-link',
      href: '/profile/index.php?u=' + encodeURIComponent(p.user_id),
      text: computeDisplayName(p)
    });
    _nmDiv.appendChild(_a);
    info.appendChild(_nmDiv);
    info.appendChild(ce('div', { class: 'post-meta', text: p.created_at ? fmtDT(p.created_at) : '' }));
    header.appendChild(info);
    
    if (canEditPost(p)) {
      const wrap = ce('div', { class: 'post-menu' });
      const btn = ce('button', { type: 'button', class: 'post-menu-btn', 'aria-label': 'Aksies' });
      btn.textContent = '⋮';
      wrap.appendChild(btn);
      header.appendChild(wrap);
    }
    
    card.appendChild(header);
    
    if ((p.type || '') === 'event' && p.event_at) {
      const whenH2 = ce('h2', { class: 'subheading event-when', text: fmtDT(p.event_at) });
      card.appendChild(whenH2);
      if (p.event_place) {
        card.appendChild(ce('p', { class: 'muted', text: '📍 ' + p.event_place }));
      }
    }
    
    card.appendChild(ce('div', { class: 'post-text', text: p.text || '' }));
    
    // ===== FACEBOOK-STYLE PHOTO GRID =====
    if (Array.isArray(p.attachments) && p.attachments.length) {
      const atts = p.attachments;
      const count = atts.length;
      const grid = ce('div', { class: 'photo-grid photo-grid-' + Math.min(count, 5) });

      atts.forEach((a, idx) => {
        const cell = ce('div', { class: 'photo-grid-cell' });
        const img = ce('img', {
          class: 'photo-grid-img',
          alt: 'img'
        });
        img.loading = 'lazy';
        img.src = a.path_thumb || a.path_original || a.url || '';
        img.dataset.full = a.path_original || a.path_thumb || a.url || '';

        // For 5+ images, show overlay on the last visible cell
        if (count > 4 && idx === 3) {
          const overlay = ce('div', { class: 'photo-grid-more', text: '+' + (count - 4) });
          cell.appendChild(overlay);
        }

        // Hide images beyond 4th
        if (idx >= 4) cell.style.display = 'none';

        img.addEventListener('click', () => openLightbox(atts, idx));
        cell.appendChild(img);
        grid.appendChild(cell);
      });

      // Clicking the "+N" overlay opens lightbox at image 4
      if (count > 4) {
        const lastVisible = grid.children[3];
        if (lastVisible) {
          lastVisible.addEventListener('click', (e) => {
            if (e.target.classList.contains('photo-grid-more')) {
              openLightbox(atts, 3);
            }
          });
        }
      }

      card.appendChild(grid);
    }

    renderReactions(card, p);
    return card;
  }

  // ===== LIGHTBOX =====
  function openLightbox(attachments, startIdx) {
    let idx = startIdx;
    const backdrop = ce('div', { class: 'lightbox-backdrop' });
    const img = ce('img', { class: 'lightbox-img' });
    const counter = ce('div', { class: 'lightbox-counter' });
    const closeBtn = ce('button', { class: 'lightbox-close', type: 'button', text: '\u00d7' });

    function show(i) {
      idx = i;
      const a = attachments[idx];
      img.src = a.path_original || a.path_thumb || a.url || '';
      counter.textContent = (idx + 1) + ' / ' + attachments.length;
    }

    function closeLightbox() {
      document.removeEventListener('keydown', onKey);
      backdrop.remove();
    }

    closeBtn.addEventListener('click', closeLightbox);
    backdrop.addEventListener('click', (e) => { if (e.target === backdrop) closeLightbox(); });

    if (attachments.length > 1) {
      const prev = ce('button', { class: 'lightbox-nav lightbox-prev', type: 'button', text: '\u2039' });
      const next = ce('button', { class: 'lightbox-nav lightbox-next', type: 'button', text: '\u203a' });
      prev.addEventListener('click', (e) => { e.stopPropagation(); show((idx - 1 + attachments.length) % attachments.length); });
      next.addEventListener('click', (e) => { e.stopPropagation(); show((idx + 1) % attachments.length); });
      backdrop.append(prev, next);
    }

    backdrop.append(closeBtn, img, counter);

    function onKey(e) {
      if (e.key === 'Escape') closeLightbox();
      if (e.key === 'ArrowLeft') show((idx - 1 + attachments.length) % attachments.length);
      if (e.key === 'ArrowRight') show((idx + 1) % attachments.length);
    }
    document.addEventListener('keydown', onKey);

    // Swipe left/right between photos on touch devices
    let touchX = null;
    backdrop.addEventListener('touchstart', (e) => {
      if (e.touches.length === 1) touchX = e.touches[0].clientX;
    }, { passive: true });
    backdrop.addEventListener('touchend', (e) => {
      if (touchX === null || attachments.length < 2) { touchX = null; return; }
      const dx = e.changedTouches[0].clientX - touchX;
      touchX = null;
      if (Math.abs(dx) < 40) return;
      if (dx < 0) show((idx + 1) % attachments.length);
      else show((idx - 1 + attachments.length) % attachments.length);
    }, { passive: true });

    document.body.appendChild(backdrop);
    show(startIdx);
  }

  // ===== INFINITE SCROLL FEED =====
  const FEED_LIMIT = 10;
  let feedLastId = null;
  let feedLoading = false;
  let feedDone = false;
  let feedGen = 0;
  let scrollObserver = null;
  let sentinelEl = null;

  async function loadFeed(roomId, reset) {
    if (reset === undefined) reset = true;
    const feed = $('#feed');
    if (!feed) return;

    const loading = $('#loadingIndicator');

    if (reset) {
      // New generation: any in-flight response from before this reset is discarded
      feedGen++;
      feed.innerHTML = '<div class="gm-placeholder"><svg class="gm-placeholder-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z" fill="currentColor" opacity="0.3"/></svg><p>' + T('loading_posts') + '</p></div>';
      POST_MAP.clear();
      feedLastId = null;
      feedDone = false;
      feedLoading = false;
      if (scrollObserver) scrollObserver.disconnect();
    } else if (feedLoading || feedDone) {
      return;
    }

    const gen = feedGen;
    feedLoading = true;
    if (loading) loading.hidden = false;

    let url = '/gospel_media/api/posts/list.php?room_id=' + encodeURIComponent(roomId) + '&limit=' + FEED_LIMIT;
    if (feedLastId) url += '&after_id=' + feedLastId;

    const j = await fetchJSON(url);

    // A reset happened while this request was in flight - drop the stale response
    if (gen !== feedGen) return;

    if (loading) loading.hidden = true;
    feedLoading = false;

    const rows = Array.isArray(j) ? j : (Array.isArray(j.rows) ? j.rows : []);

    if (reset) feed.innerHTML = '';

    if (!rows.length) {
      if (reset) {
        feed.innerHTML = '<div class="gm-placeholder"><svg class="gm-placeholder-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z" fill="currentColor" opacity="0.3"/></svg><p>' + T('no_posts') + '</p></div>';
      }
      feedDone = true;
      return;
    }

    if (rows.length < FEED_LIMIT) feedDone = true;

    rows.forEach(p => {
      feed.appendChild(renderCard(p));
      feedLastId = p.id;
    });

    // Set up infinite scroll sentinel
    setupScrollObserver(feed, roomId);
  }

  function setupScrollObserver(feed, roomId) {
    if (scrollObserver) scrollObserver.disconnect();
    if (feedDone) return;

    // Remove old sentinel
    if (sentinelEl) sentinelEl.remove();
    sentinelEl = ce('div', { class: 'feed-sentinel' });
    feed.appendChild(sentinelEl);

    scrollObserver = new IntersectionObserver((entries) => {
      if (entries[0].isIntersecting && !feedLoading && !feedDone) {
        loadFeed(roomId, false);
      }
    }, { rootMargin: '200px' });

    scrollObserver.observe(sentinelEl);
  }

  // ===== MODALS =====
  function openEditModal(p) {
    const back = ce('div', { class: 'modal-backdrop' });
    back.addEventListener('click', (e) => { if (e.target === back) document.body.removeChild(back); });
    
    const panel = ce('div', { class: 'modal-panel' });
    
    const title = ce('h2', { text: T('edit_post') });
    const who = ce('div', { class: 'muted', text: computeDisplayName(p) });
    const when = ce('div', { class: 'muted', text: p.created_at ? fmtDT(p.created_at) : '' });
    
    const isEvent = ((p.type || '') === 'event') || !!p.event_at;
    let evtWrap = null, evtInput = null, placeInput = null;
    
    if (isEvent) {
      const toInputLocal = (s) => s ? (String(s).replace(' ', 'T').slice(0, 16)) : '';
      evtWrap = ce('div', { class: 'modal-event-fields' });
      const lbl = ce('label', { text: T('datetime'), class: 'modal-label' });
      const inp = ce('input', { type: 'datetime-local', class: 'composer-input' });
      inp.value = toInputLocal(p.event_at || '');
      evtInput = inp;
      
      const placeLbl = ce('label', { text: T('place'), class: 'modal-label' });
      const placeInp = ce('input', { type: 'text', class: 'composer-input' });
      placeInp.value = p.event_place || '';
      placeInput = placeInp;
      
      evtWrap.append(lbl, inp, placeLbl, placeInp);
    }
    
    const ta = ce('textarea', { class: 'composer-textarea' });
    ta.value = p.text || '';
    
    const file = ce('input', { type: 'file', accept: 'image/*', multiple: 'multiple' });
    file.style.display = 'none';
    
    const prev = ce('div', { class: 'composer-preview' });
    const existing = ce('div', { class: 'modal-existing-images' });
    
    if (Array.isArray(p.attachments) && p.attachments.length) {
      p.attachments.forEach(a => {
        const box = ce('div', { class: 'modal-img-box' });
        
        const img = ce('img', { class: 'modal-img' });
        img.src = a.path_thumb || a.path_original || '';
        
        const x = ce('button', { type: 'button', class: 'modal-img-del' });
        x.textContent = '×';
        x.title = T('remove_photo');
        
        x.addEventListener('click', async () => {
          if (!confirm(T('delete_photo'))) return;
          
          x.disabled = true;
          x.textContent = '...';
          
          const fd = new FormData();
          fd.append('post_id', p.id);
          fd.append('path', a.path_original || a.path_thumb || '');
          
          const j = await fetchJSON('/gospel_media/api/posts/attachment_delete.php', { method: 'POST', body: fd });
          
          if (j && (j.ok || j.success)) {
            box.remove();
          } else {
            alert(j && j.error ? j.error : T('error'));
            x.disabled = false;
            x.textContent = '×';
          }
        });
        
        box.append(img, x);
        existing.appendChild(box);
      });
    }
    
    const attachBtn = ce('button', { type: 'button', class: 'composer-attach' });
    attachBtn.innerHTML = `
      <svg class="icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"
              stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      <span>${T('add_photo')}</span>
    `;
    
    attachBtn.addEventListener('click', () => file.click());
    
    file.addEventListener('change', () => {
      prev.innerHTML = '';
      Array.from(file.files || []).forEach((f, idx) => {
        const box = ce('div', { class: 'modal-img-box' });
        
        const img = ce('img', { class: 'modal-img' });
        img.src = URL.createObjectURL(f);
        
        const x = ce('button', { type: 'button', class: 'modal-img-del' });
        x.textContent = '×';
        x.title = T('remove');
        
        x.addEventListener('click', () => {
          const dt = new DataTransfer();
          Array.from(file.files).forEach((f2, i2) => {
            if (i2 !== idx) dt.items.add(f2);
          });
          file.files = dt.files;
          box.remove();
        });
        
        box.append(img, x);
        prev.appendChild(box);
      });
    });
    
    const actions = ce('div', { class: 'modal-actions' });
    
    const save = ce('button', { class: 'modal-btn', type: 'button', text: T('save') });
    const close = ce('button', { class: 'modal-btn', type: 'button', text: T('back') });
    
    save.addEventListener('click', async () => {
      const fd = new FormData();
      fd.append('post_id', p.id);
      fd.append('text', ta.value.trim());

      if (evtInput && evtInput.value) {
        fd.append('event_at', evtInput.value);
      }

      if (placeInput) {
        fd.append('event_place', placeInput.value.trim());
      }

      save.disabled = true;
      save.textContent = T('saving');

      if (file && file.files && file.files.length) {
        const compressed = await compressImages(Array.from(file.files));
        compressed.forEach(f => fd.append('images[]', f));
      }
      
      const j = await fetchJSON('/gospel_media/api/posts/update.php', { method: 'POST', body: fd });
      
      if (!(j && (j.success || j.ok))) {
        alert(j && j.error ? j.error : T('error'));
        save.disabled = false;
        save.textContent = T('save');
        return;
      }
      
      document.body.removeChild(back);
      await loadFeed(ROOM_ID);
    });
    
    close.addEventListener('click', () => document.body.removeChild(back));
    
    panel.append(title, who, when);
    if (evtWrap) panel.append(evtWrap);
    panel.append(ta, existing, prev, attachBtn, file);
    actions.append(save, close);
    panel.append(actions);
    back.appendChild(panel);
    document.body.appendChild(back);
  }

  function openDeleteModal(p) {
    const back = ce('div', { class: 'modal-backdrop' });
    back.addEventListener('click', (e) => { if (e.target === back) document.body.removeChild(back); });
    
    const panel = ce('div', { class: 'modal-panel' });
    
    const title = ce('h2', { text: T('confirm_delete') });
    const info = ce('p', { text: T('cannot_undo'), class: 'modal-info' });
    const who = ce('div', { class: 'muted', text: computeDisplayName(p) });
    const when = ce('div', { class: 'muted', text: p.created_at ? fmtDT(p.created_at) : '' });
    
    const actions = ce('div', { class: 'modal-actions' });
    
    const confirm = ce('button', { class: 'modal-btn danger', type: 'button', text: T('delete') });
    const cancel = ce('button', { class: 'modal-btn', type: 'button', text: T('cancel') });
    
    confirm.addEventListener('click', async () => {
      const fd = new FormData();
      fd.append('post_id', p.id);
      
      confirm.disabled = true;
      confirm.textContent = T('deleting');
      
      const j = await fetchJSON('/gospel_media/api/posts/delete.php', { method: 'POST', body: fd });
      
      if (!(j && (j.success || j.ok))) {
        alert(j && j.error ? j.error : T('error'));
        confirm.disabled = false;
        confirm.textContent = T('delete');
        return;
      }
      document.body.removeChild(back);
      await loadFeed(ROOM_ID);
    });
    
    cancel.addEventListener('click', () => document.body.removeChild(back));
    
    actions.append(confirm, cancel);
    panel.append(title, info, who, when, actions);
    back.appendChild(panel);
    document.body.appendChild(back);
  }

  function openActionsModal(p) {
    const back = ce('div', { class: 'modal-backdrop' });
    back.addEventListener('click', (e) => { if (e.target === back) document.body.removeChild(back); });
    
    const panel = ce('div', { class: 'modal-panel' });
    
    const title = ce('h2', { text: T('post_actions') });
    const who = ce('div', { class: 'muted', text: computeDisplayName(p) });
    const when = ce('div', { class: 'muted', text: p.created_at ? fmtDT(p.created_at) : '' });
    const text = ce('p', { text: (p.text || '').substring(0, 100) + (p.text && p.text.length > 100 ? '...' : ''), class: 'modal-preview' });
    
    const actions = ce('div', { class: 'modal-actions' });
    
    const bEdit = ce('button', { class: 'modal-btn', type: 'button', text: T('edit') });
    const bDel = ce('button', { class: 'modal-btn danger', type: 'button', text: T('delete') });
    const bClose = ce('button', { class: 'modal-btn', type: 'button', text: T('cancel') });
    
    bEdit.addEventListener('click', () => { document.body.removeChild(back); openEditModal(p); });
    bDel.addEventListener('click', () => { document.body.removeChild(back); openDeleteModal(p); });
    bClose.addEventListener('click', () => document.body.removeChild(back));
    
    actions.append(bEdit, bDel, bClose);
    panel.append(title, who, when, text, actions);
    back.appendChild(panel);
    document.body.appendChild(back);
  }

  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.post-menu-btn');
    if (!btn) return;

    const card = btn.closest('[data-post-id]');
    const id = card ? card.getAttribute('data-post-id') : null;
    const p = id ? POST_MAP.get(id) : null;

    if (!p || !canEditPost(p)) return;
    openActionsModal(p);
  });

  // ===== BOOT =====
  async function boot() {
    try {
      const me = await fetchJSON('/gospel_media/api/users/me.php');
      if (me && me.id) {
        ME.id = me.id;
        ME.amp_id = me.amp_id;
      }
    } catch (e) {
      console.error('Failed to load user info:', e);
    }

    // Wire up the "New Post" hero button
    const composerBtn = $('#open-composer');
    if (composerBtn) {
      composerBtn.addEventListener('click', openComposerOverlay);
    }

    // Floating action button: appears once the hero button scrolls out of view
    const fab = $('#gm-fab');
    if (fab) {
      fab.addEventListener('click', openComposerOverlay);
      const hero = $('.gm-hero');
      if (hero && 'IntersectionObserver' in window) {
        new IntersectionObserver((entries) => {
          fab.hidden = entries[0].isIntersecting;
        }, { threshold: 0 }).observe(hero);
      } else {
        fab.hidden = false;
      }
    }

    await loadFeed(ROOM_ID);

    // Deep-link support: notifications link to #post-N
    if (location.hash && /^#post-\d+$/.test(location.hash)) {
      const target = document.getElementById(location.hash.slice(1));
      if (target) target.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})(); 