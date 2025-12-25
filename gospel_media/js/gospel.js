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

  const T = (af, en) => (window.PAGE_LANG === 'en' ? en : af);

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
  function canEditPost(p) {
    if (!ME.id) return false;
    if (String(ME.id) === String(p.user_id)) return true;
    
    const a = parseInt(ME.amp_id || 0, 10);
    if (!a) return false;
    
    const rt = (window.CURRENT_ROOM_TYPE || '').toLowerCase();
    
    if (rt === 'opsienerskap') return a >= 2 && a <= 5;
    if (rt === 'gemeente') return a >= 5 && a <= 9;
    if (rt === 'jeug' || rt === 'sondagskool') return a >= 1 && a <= 9;
    if (rt === 'gemeenskap') return a >= 1 && a <= 6;
    
    return false;
  }

  function canEditComment(c) {
    if (!ME.id) return false;
    if (String(ME.id) === String(c.user_id)) return true;
    
    const a = parseInt(ME.amp_id || 0, 10);
    if (!a) return false;
    
    const rt = (window.CURRENT_ROOM_TYPE || '').toLowerCase();
    
    if (rt === 'opsienerskap') return a >= 2 && a <= 5;
    if (rt === 'gemeente') return a >= 5 && a <= 9;
    if (rt === 'jeug' || rt === 'sondagskool') return a >= 1 && a <= 9;
    if (rt === 'gemeenskap') return a >= 1 && a <= 6;
    
    return false;
  }

  // ===== DATE FORMATTING =====
  const fmtDT = s => {
    try {
      const d = new Date(s);
      if (isNaN(d.getTime())) return s || '';
      const day = d.toLocaleDateString('en-GB', { weekday: 'short' }).toUpperCase();
      const date = d.toLocaleDateString('en-GB', { year: '2-digit', month: '2-digit', day: '2-digit' });
      const time = d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', hour12: false });
      return `${day} ${date}, ${time}`;
    } catch {
      return s || '';
    }
  };

  // ===== COMPOSER =====
  function showEventFields(show) {
    const box = $('#composer-event-fields');
    if (box) box.classList.toggle('hide', !show);
  }

  function bindComposerTabs() {
    const postTab = $('#composer-type-post');
    const evtTab = $('#composer-type-event');
    
    if (postTab) {
      postTab.addEventListener('click', (e) => {
        e.preventDefault();
        activeType = 'post';
        postTab.classList.add('active');
        if (evtTab) evtTab.classList.remove('active');
        showEventFields(false);
      });
    }
    
    if (evtTab) {
      evtTab.addEventListener('click', (e) => {
        e.preventDefault();
        activeType = 'event';
        evtTab.classList.add('active');
        if (postTab) postTab.classList.remove('active');
        showEventFields(true);
      });
    }
  }

  function bindComposer() {
    const choose = $('#btn-choose-image');
    const file = $('#composer-image');
    const prev = $('#composer-preview');
    
    if (choose && file && !choose.dataset.gmBound) {
      choose.dataset.gmBound = '1';
      choose.addEventListener('click', () => file.click());
      
      file.addEventListener('change', () => {
        if (!prev) return;
        prev.innerHTML = '';
        Array.from(file.files || []).forEach(f => {
          const img = ce('img', { class: 'composer-preview-img' });
          img.src = URL.createObjectURL(f);
          prev.appendChild(img);
        });
      });
    }
    
    const submit = $('#composer-submit');
    if (submit && !submit.dataset.gmBound) {
      submit.dataset.gmBound = '1';
      submit.addEventListener('click', submitPost);
    }
  }

  async function submitPost() {
    if (!window.CAN_POST) return;
    
    const text = ($('#composer-text')?.value || '').trim();
    if (!text) {
      alert(T('Tik asseblief \'n boodskap', 'Please type a message'));
      return;
    }
    
    const fd = new FormData();
    fd.append('room_id', ROOM_ID);
    fd.append('text', text);
    fd.append('type', activeType === 'event' ? 'event' : 'post');
    
    if (activeType === 'event') {
      const at = $('#composer-event-at')?.value || '';
      const place = $('#composer-event-place')?.value || '';
      if (at) fd.append('event_at', at);
      if (place) fd.append('event_place', place);
    }
    
    const file = $('#composer-image');
    if (file && file.files && file.files.length) {
      Array.from(file.files).forEach(f => fd.append('images[]', f));
    }
    
    const submit = $('#composer-submit');
    if (submit) {
      submit.disabled = true;
      submit.textContent = T('Plaas...', 'Posting...');
    }
    
    const j = await fetchJSON('/gospel_media/api/posts/create.php', { method: 'POST', body: fd });
    
    if (submit) {
      submit.disabled = false;
      submit.innerHTML = `<span class="sb-btn-shine"></span>
        <svg class="icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span>${T('Plaas', 'Post')}</span>`;
    }
    
    if (!(j && (j.ok || j.success))) {
      alert(j && j.error ? j.error : T('Fout met plaas', 'Error posting'));
      return;
    }
    
    const area = $('#composer-text');
    if (area) area.value = '';
    if (file) {
      file.value = '';
      const prev = $('#composer-preview');
      if (prev) prev.innerHTML = '';
    }
    
    await loadFeed(ROOM_ID);
  }

  // ===== REACTIONS =====
  function renderReactions(card, p) {
    const bar = ce('div', { class: 'post-actions' });
    
    const bH = ce('button', { type: 'button', class: 'btn-react heart-btn' });
    const heartIcon = ce('img', { class: 'icon', src: '/assets/icons/heart.png', alt: 'heart' });
    const heartCount = ce('span', { class: 'badge count-heart', text: (p.heart_count || 0) });
    bH.append(heartIcon, heartCount);
    
    const bP = ce('button', { type: 'button', class: 'btn-react pray-btn' });
    const prayIcon = ce('img', { class: 'icon', src: '/assets/icons/amen.png', alt: 'amen' });
    const prayCount = ce('span', { class: 'badge count-pray', text: (p.pray_count || 0) });
    bP.append(prayIcon, prayCount);
    
    bH.addEventListener('click', () => toggleReact(p.id, 'heart', bH, bP));
    bP.addEventListener('click', () => toggleReact(p.id, 'pray', bH, bP));
    
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

  async function toggleReact(post_id, type, bH, bP) {
    const fd = new FormData();
    fd.append('post_id', post_id);
    fd.append('type', type);
    
    const j = await fetchJSON('/gospel_media/api/posts/react.php', { method: 'POST', body: fd });
    if (!(j && j.ok)) return;
    
    if (bH && typeof j.heart_count !== 'undefined') {
      bH.querySelector('.count-heart').textContent = j.heart_count;
    }
    if (bP && typeof j.pray_count !== 'undefined') {
      bP.querySelector('.count-pray').textContent = j.pray_count;
    }
  }

  // ===== COMMENTS =====
  async function deleteComment(commentId, postId, listEl) {
    if (!confirm(T('Verwyder hierdie kommentaar?', 'Delete this comment?'))) return;
    
    const fd = new FormData();
    fd.append('comment_id', commentId);
    
    const j = await fetchJSON('/gospel_media/api/comments/delete.php', { method: 'POST', body: fd });
    
    if (!(j && (j.ok || j.success))) {
      alert(j && j.error ? j.error : T('Fout', 'Error'));
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

  async function editComment(commentId, currentText, postId, listEl) {
    const newText = prompt(T('Wysig kommentaar:', 'Edit comment:'), currentText);
    if (!newText || newText.trim() === '' || newText.trim() === currentText) return;
    
    const fd = new FormData();
    fd.append('comment_id', commentId);
    fd.append('text', newText.trim());
    
    const j = await fetchJSON('/gospel_media/api/comments/update.php', { method: 'POST', body: fd });
    
    if (!(j && (j.ok || j.success))) {
      alert(j && j.error ? j.error : T('Fout', 'Error'));
      return;
    }
    
    await loadComments(postId, listEl);
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
        <h3 class="comments-title">${T('Kommentare', 'Comments')}</h3>
      </div>
      <div class="comments-list"></div>
      <div class="comment-composer">
        <textarea class="comment-input" placeholder="${T('Skryf \'n kommentaar...', 'Write a comment...')}" rows="1"></textarea>
        <button class="comment-submit" type="button">${T('Plaas', 'Post')}</button>
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
        submit.textContent = T('Plaas', 'Post');
        
        if (!(j && (j.ok || j.success))) {
          alert(j && j.error ? j.error : T('Fout', 'Error'));
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
    
    listEl.innerHTML = '<p class="muted">' + T('Laai...', 'Loading...') + '</p>';
    
    const j = await fetchJSON('/gospel_media/api/comments/list.php?post_id=' + postId);
    const comments = Array.isArray(j) ? j : [];
    
    listEl.innerHTML = '';
    
    if (!comments.length) {
      listEl.innerHTML = '<p class="muted">' + T('Geen kommentare nog nie', 'No comments yet') + '</p>';
      return;
    }
    
    comments.forEach(c => {
      const item = ce('div', { class: 'comment-item' });
      
      const header = ce('div', { class: 'comment-header' });
      
      const avatar = ce('img', { class: 'comment-avatar' });
      avatar.src = c.photo || '/assets/avatar.svg';
      avatar.alt = 'avatar';
      
      const author = ce('div', { class: 'comment-author' });
      author.textContent = computeDisplayName(c);
      
      const time = ce('div', { class: 'comment-time' });
      time.textContent = c.created_at ? fmtDT(c.created_at) : '';
      
      header.append(avatar, author, time);
      
      if (canEditComment(c)) {
        const actions = ce('div', { class: 'comment-actions' });
        
        const editBtn = ce('button', { class: 'comment-action-btn', type: 'button' });
        editBtn.textContent = '✎';
        editBtn.title = T('Wysig', 'Edit');
        editBtn.addEventListener('click', () => editComment(c.id, c.text, postId, listEl));
        
        const delBtn = ce('button', { class: 'comment-action-btn', type: 'button' });
        delBtn.textContent = '×';
        delBtn.title = T('Verwyder', 'Delete');
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
    POST_MAP.set(p.id, p);
    
    const card = ce('article', { class: 'post-card', 'data-post-id': String(p.id) });
    const header = ce('div', { class: 'post-header' });
    
    const avatar = ce('img', { class: 'avatar' });
    avatar.src = p.user_photo || p.photo || '/assets/avatar.svg';
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
    
    if (Array.isArray(p.attachments)) {
      p.attachments.forEach(a => {
        const img = ce('img', { class: 'post-image' });
        img.src = a.path_original || a.path_thumb || a.url || '';
        img.alt = 'img';
        card.appendChild(img);
      });
    }
    
    renderReactions(card, p);
    return card;
  }

  async function loadFeed(roomId) {
    const feed = $('#feed');
    if (!feed) return;
    
    const loading = $('#loadingIndicator');
    if (loading) loading.hidden = false;
    
    feed.innerHTML = '<div class="gm-placeholder"><svg class="gm-placeholder-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z" fill="currentColor" opacity="0.3"/></svg><p>' + T('Laai plasings...', 'Loading posts...') + '</p></div>';
    POST_MAP.clear();
    
    const j = await fetchJSON('/gospel_media/api/posts/list.php?room_id=' + encodeURIComponent(roomId));
    
    if (loading) loading.hidden = true;
    
    const rows = Array.isArray(j) ? j : (Array.isArray(j.rows) ? j.rows : []);
    
    feed.innerHTML = '';
    
    if (!rows.length) {
      feed.innerHTML = '<div class="gm-placeholder"><svg class="gm-placeholder-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z" fill="currentColor" opacity="0.3"/></svg><p>' + T('Geen plasings in hierdie kamer nie', 'No posts in this room') + '</p></div>';
      return;
    }
    
    rows.forEach(p => feed.appendChild(renderCard(p)));
  }

  // ===== MODALS =====
  function openEditModal(p) {
    const back = ce('div', { class: 'modal-backdrop' });
    back.addEventListener('click', (e) => { if (e.target === back) document.body.removeChild(back); });
    
    const panel = ce('div', { class: 'modal-panel' });
    
    const title = ce('h2', { text: T('Wysig plasing', 'Edit post') });
    const who = ce('div', { class: 'muted', text: computeDisplayName(p) });
    const when = ce('div', { class: 'muted', text: p.created_at ? fmtDT(p.created_at) : '' });
    
    const isEvent = ((p.type || '') === 'event') || !!p.event_at;
    let evtWrap = null, evtInput = null, placeInput = null;
    
    if (isEvent) {
      const toInputLocal = (s) => s ? (String(s).replace(' ', 'T').slice(0, 16)) : '';
      evtWrap = ce('div', { class: 'modal-event-fields' });
      const lbl = ce('label', { text: T('Datum/tyd', 'Date/time'), class: 'modal-label' });
      const inp = ce('input', { type: 'datetime-local', class: 'composer-input' });
      inp.value = toInputLocal(p.event_at || '');
      evtInput = inp;
      
      const placeLbl = ce('label', { text: T('Plek', 'Place'), class: 'modal-label' });
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
        x.title = T('Verwyder foto', 'Remove photo');
        
        x.addEventListener('click', async () => {
          if (!confirm(T('Verwyder hierdie foto?', 'Remove this photo?'))) return;
          
          x.disabled = true;
          x.textContent = '...';
          
          const fd = new FormData();
          fd.append('post_id', p.id);
          fd.append('path', a.path_original || a.path_thumb || '');
          
          const j = await fetchJSON('/gospel_media/api/posts/attachment_delete.php', { method: 'POST', body: fd });
          
          if (j && (j.ok || j.success)) {
            box.remove();
          } else {
            alert(j && j.error ? j.error : T('Fout', 'Error'));
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
      <span>${T('Voeg foto by', 'Add photo')}</span>
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
        x.title = T('Verwyder', 'Remove');
        
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
    
    const save = ce('button', { class: 'modal-btn', type: 'button', text: T('Stoor', 'Save') });
    const close = ce('button', { class: 'modal-btn', type: 'button', text: T('Terug', 'Back') });
    
    save.addEventListener('click', async () => {
      const fd = new FormData();
      fd.append('post_id', p.id);
      fd.append('text', ta.value.trim());
      
      if (file && file.files && file.files.length) {
        Array.from(file.files).forEach(f => fd.append('images[]', f));
      }
      
      if (evtInput && evtInput.value) {
        fd.append('event_at', evtInput.value);
      }
      
      if (placeInput) {
        fd.append('event_place', placeInput.value.trim());
      }
      
      save.disabled = true;
      save.textContent = T('Stoor...', 'Saving...');
      
      const j = await fetchJSON('/gospel_media/api/posts/update.php', { method: 'POST', body: fd });
      
      if (!(j && (j.success || j.ok))) {
        alert(j && j.error ? j.error : T('Fout', 'Error'));
        save.disabled = false;
        save.textContent = T('Stoor', 'Save');
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
    
    const title = ce('h2', { text: T('Bevestig verwydering', 'Confirm deletion') });
    const info = ce('p', { text: T('Hierdie aksie kan nie ontdoen word nie', 'This action cannot be undone'), class: 'modal-info' });
    const who = ce('div', { class: 'muted', text: computeDisplayName(p) });
    const when = ce('div', { class: 'muted', text: p.created_at ? fmtDT(p.created_at) : '' });
    
    const actions = ce('div', { class: 'modal-actions' });
    
    const confirm = ce('button', { class: 'modal-btn danger', type: 'button', text: T('Verwyder', 'Delete') });
    const cancel = ce('button', { class: 'modal-btn', type: 'button', text: T('Kanselleer', 'Cancel') });
    
    confirm.addEventListener('click', async () => {
      const fd = new FormData();
      fd.append('post_id', p.id);
      
      confirm.disabled = true;
      confirm.textContent = T('Verwyder...', 'Deleting...');
      
      const j = await fetchJSON('/gospel_media/api/posts/delete.php', { method: 'POST', body: fd });
      
      if (!(j && (j.success || j.ok))) {
        alert(j && j.error ? j.error : T('Fout', 'Error'));
        confirm.disabled = false;
        confirm.textContent = T('Verwyder', 'Delete');
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
    
    const title = ce('h2', { text: T('Post aksies', 'Post actions') });
    const who = ce('div', { class: 'muted', text: computeDisplayName(p) });
    const when = ce('div', { class: 'muted', text: p.created_at ? fmtDT(p.created_at) : '' });
    const text = ce('p', { text: (p.text || '').substring(0, 100) + (p.text && p.text.length > 100 ? '...' : ''), class: 'modal-preview' });
    
    const actions = ce('div', { class: 'modal-actions' });
    
    const bEdit = ce('button', { class: 'modal-btn', type: 'button', text: T('Wysig', 'Edit') });
    const bDel = ce('button', { class: 'modal-btn danger', type: 'button', text: T('Verwyder', 'Delete') });
    const bClose = ce('button', { class: 'modal-btn', type: 'button', text: T('Kanselleer', 'Cancel') });
    
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
    const id = card ? Number(card.getAttribute('data-post-id')) : null;
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
    
    const evtTab = $('#composer-type-event');
    if (evtTab && ROOM_TYPE === 'gemeenskap') {
      evtTab.style.display = 'none';
      showEventFields(false);
    }
    
    showEventFields(false);
    bindComposerTabs();
    bindComposer();
    
    await loadFeed(ROOM_ID);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})(); 