(() => {
  'use strict';

  // Use server-provided translations for all 5 languages
  const T = (key) => (window.JS_T && window.JS_T[key]) ? window.JS_T[key] : key;
  const userId = window.CURRENT_USER_ID || 0;
  const userName = window.CURRENT_USER_NAME || 'User';
  const userPic = window.CURRENT_USER_PIC || '/assets/default-avatar.png';

  let currentKind = 'prayer';
  let editingPostId = null;
  let editingPostPhotoUrl = null;
  let editingCommentId = null;

  document.addEventListener('DOMContentLoaded', () => {
    const openBtn = document.getElementById('open-composer');
    if (openBtn) openBtn.addEventListener('click', () => openComposerOverlay());
    loadPosts();
    initModal();
  });

  // ==================== COMPOSER OVERLAY ====================
  function openComposerOverlay(editId, editText, editPhotoUrl) {
    // Remove existing overlay if any
    const existing = document.querySelector('.pr-composer-overlay');
    if (existing) existing.remove();

    const isEdit = !!editId;
    if (isEdit) {
      editingPostId = editId;
      editingPostPhotoUrl = editPhotoUrl || null;
    } else {
      editingPostId = null;
      editingPostPhotoUrl = null;
    }

    const overlay = document.createElement('div');
    overlay.className = 'pr-composer-overlay';

    const prayerKind = isEdit ? '' : 'active';
    const photoPreviewHTML = editingPostPhotoUrl ? `
      <div class="pr-photo-preview">
        <img src="${editingPostPhotoUrl}" alt="Current photo">
        <button type="button" class="pr-remove-photo-btn" id="overlayRemovePhoto">
          <svg viewBox="0 0 24 24" fill="none"><path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2"/></svg>
          ${T('remove_photo')}
        </button>
      </div>
    ` : '';

    overlay.innerHTML = `
      <div class="pr-composer-overlay-panel">
        <div class="pr-composer-overlay-header">
          <h2 class="pr-composer-overlay-title">${isEdit ? T('edit') : T('share')}</h2>
          <button type="button" class="pr-composer-overlay-close" id="closeComposerOverlay">
            <svg viewBox="0 0 24 24" fill="none"><path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
          </button>
        </div>
        <form id="composerForm" class="pr-form" enctype="multipart/form-data">
          ${!isEdit ? `
          <div class="pr-tabs">
            <button type="button" class="pr-tab ${currentKind === 'prayer' ? 'active' : ''}" data-kind="prayer">
              <svg class="pr-tab-icon" viewBox="0 0 24 24" fill="none">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-1-13h2v6h-2zm0 8h2v2h-2z" fill="currentColor" opacity="0.3"/>
              </svg>
              <span>${T('prayer')}</span>
            </button>
            <button type="button" class="pr-tab ${currentKind === 'testimony' ? 'active' : ''}" data-kind="testimony">
              <svg class="pr-tab-icon" viewBox="0 0 24 24" fill="none">
                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" fill="currentColor" opacity="0.3"/>
              </svg>
              <span>${T('testimony')}</span>
            </button>
          </div>
          ` : ''}

          <textarea id="overlayPostText" name="text" class="pr-textarea" placeholder="${T('prayer')}" rows="4" required>${isEdit ? escapeHtml(editText || '') : ''}</textarea>

          ${photoPreviewHTML}

          <div class="pr-form-row">
            <div class="pr-file-input-wrapper">
              <input type="file" id="overlayPhotoInput" name="photo" accept="image/*" class="pr-file-input">
              <label for="overlayPhotoInput" class="pr-file-label">
                <svg class="pr-file-icon" viewBox="0 0 24 24" fill="none">
                  <path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z" fill="currentColor" opacity="0.3"/>
                </svg>
                <span id="overlayFileLabel">${T('choose_photo')}</span>
              </label>
            </div>
            <button type="submit" class="pr-btn pr-btn-primary" id="overlaySubmitBtn">
              <span class="pr-btn-shine"></span>
              <svg class="pr-btn-icon" viewBox="0 0 24 24" fill="none">
                <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
              <span class="pr-btn-text">${isEdit ? T('update') : T('share')}</span>
            </button>
          </div>
        </form>
      </div>
    `;

    document.body.appendChild(overlay);
    requestAnimationFrame(() => overlay.classList.add('open'));

    // Wire close
    const closeBtn = overlay.querySelector('#closeComposerOverlay');
    closeBtn.addEventListener('click', () => closeComposerOverlay());
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) closeComposerOverlay();
    });

    // Wire escape key
    const onEsc = (e) => {
      if (e.key === 'Escape') { closeComposerOverlay(); document.removeEventListener('keydown', onEsc); }
    };
    document.addEventListener('keydown', onEsc);

    // Wire tabs
    overlay.querySelectorAll('.pr-tab').forEach(tab => {
      tab.addEventListener('click', () => {
        overlay.querySelectorAll('.pr-tab').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        currentKind = tab.dataset.kind;
      });
    });

    // Wire file input
    const fileInput = overlay.querySelector('#overlayPhotoInput');
    const fileLabel = overlay.querySelector('#overlayFileLabel');
    fileInput.addEventListener('change', (e) => {
      fileLabel.textContent = e.target.files[0] ? e.target.files[0].name : T('choose_photo');
    });

    // Wire remove photo button
    const removePhotoBtn = overlay.querySelector('#overlayRemovePhoto');
    if (removePhotoBtn) {
      removePhotoBtn.addEventListener('click', async () => {
        await removePostPhoto(editId);
        const preview = overlay.querySelector('.pr-photo-preview');
        if (preview) preview.remove();
      });
    }

    // Wire form submit
    const form = overlay.querySelector('#composerForm');
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const textarea = overlay.querySelector('#overlayPostText');
      const formData = new FormData(form);

      if (isEdit) {
        await updatePost(editId, textarea.value, formData);
      } else {
        await createPost(formData);
      }
    });

    // Focus textarea
    setTimeout(() => overlay.querySelector('#overlayPostText').focus(), 200);
  }

  function closeComposerOverlay() {
    const overlay = document.querySelector('.pr-composer-overlay');
    if (!overlay) return;
    overlay.classList.remove('open');
    setTimeout(() => overlay.remove(), 300);
    editingPostId = null;
    editingPostPhotoUrl = null;
  }

  async function createPost(formData) {
    const submitBtn = document.querySelector('#overlaySubmitBtn');

    formData.append('kind', currentKind);

    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.querySelector('.pr-btn-text').textContent = T('posting');
    }

    try {
      const res = await fetch('/prayers/api/posts/create.php', {
        method: 'POST',
        body: formData
      });

      const data = await res.json();

      if (data.success) {
        closeComposerOverlay();
        showToast(T('prayer_shared'), 'success');
        loadPosts();
      } else {
        showToast(data.error || T('error'), 'error');
      }
    } catch (err) {
      console.error(err);
      showToast(T('could_not_post'), 'error');
    } finally {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.querySelector('.pr-btn-text').textContent = T('share');
      }
    }
  }

  async function updatePost(postId, text, formData) {
    const submitBtn = document.querySelector('#overlaySubmitBtn');

    formData.append('post_id', postId);
    formData.append('text', text);

    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.querySelector('.pr-btn-text').textContent = T('posting');
    }

    try {
      const res = await fetch('/prayers/api/posts/update.php', {
        method: 'POST',
        body: formData
      });

      const data = await res.json();

      if (data.success) {
        closeComposerOverlay();
        showToast(T('post_updated'), 'success');
        loadPosts();
      } else {
        showToast(data.error || T('error'), 'error');
      }
    } catch (err) {
      console.error(err);
      showToast(T('could_not_update'), 'error');
    } finally {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.querySelector('.pr-btn-text').textContent = T('update');
      }
    }
  }

  async function removePostPhoto(postId) {
    if (!confirm(T('delete_photo'))) return;

    const textEl = document.querySelector('#overlayPostText');
    try {
      const res = await fetch('/prayers/api/posts/update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          post_id: postId,
          text: textEl ? textEl.value : '',
          remove_photo: true
        })
      });

      const data = await res.json();

      if (data.success) {
        showToast(T('photo_removed'), 'success');
        editingPostPhotoUrl = null;
      } else {
        showToast(data.error || T('error'), 'error');
      }
    } catch (err) {
      console.error(err);
      showToast(T('could_not_remove'), 'error');
    }
  }

  async function deletePost(postId) {
    if (!confirm(T('delete_post'))) return;

    try {
      const res = await fetch('/prayers/api/posts/delete.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ post_id: postId })
      });
      
      const data = await res.json();
      
      if (data.success) {
        showToast(T('post_deleted'), 'success');
        loadPosts();
      } else {
        showToast(data.error || T('error'), 'error');
      }
    } catch (err) {
      console.error(err);
      showToast(T('could_not_delete'), 'error');
    }
  }

  function startEditPost(postId, text, photoUrl) {
    openComposerOverlay(postId, text, photoUrl);
  }

  // ==================== LOAD POSTS ====================
  async function loadPosts() {
    const container = document.getElementById('postsContainer');
    container.innerHTML = `
      <div class="pr-loading">
        <div class="pr-spinner"></div>
        <p>${T('loading_prayers')}</p>
      </div>
    `;

    try {
      const res = await fetch('/prayers/api/posts/list.php');
      const data = await res.json();

      if (data.success && data.posts.length > 0) {
        container.innerHTML = data.posts.map(post => renderPost(post)).join('');
        attachPostListeners();
      } else {
        container.innerHTML = `
          <div class="pr-loading">
            <p>${T('no_prayers')}</p>
          </div>
        `;
      }
    } catch (err) {
      console.error(err);
      container.innerHTML = `
        <div class="pr-loading">
          <p>${T('could_not_load')}</p>
        </div>
      `;
    }
  }

  // ==================== RENDER POST ====================
  
  // In /prayers/js/prayers.js - UPDATE renderPost function

function renderPost(post) {
  const kindLabel = post.kind === 'prayer' 
    ? T('prayer') 
    : T('testimony');
  
  const photoHTML = post.photo_url 
    ? `<img src="${post.photo_url}" alt="Post photo" class="pr-post-photo">` 
    : '';
  
  const heartActive = post.user_hearted ? 'active' : '';
  const prayActive = post.user_prayed ? 'active' : '';

  // Avatar: use photo or initials
  const avatarHTML = post.user_pic
    ? `<img src="${post.user_pic}" alt="${post.username}" class="pr-post-avatar">`
    : `<div class="pr-post-avatar pr-avatar-initials">${post.initials || '??'}</div>`;

  const actionsHTML = post.can_edit ? `
    <button class="pr-action-btn pr-edit-post-btn" data-id="${post.id}" data-text="${escapeHtml(post.text)}" data-photo="${post.photo_url || ''}">
      <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      <span>${T('edit')}</span>
    </button>
  ` : '';

  const deleteHTML = post.can_delete ? `
    <button class="pr-action-btn pr-delete-post-btn" data-id="${post.id}">
      <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M10 11v6M14 11v6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      <span>${T('delete')}</span>
    </button>
  ` : '';

  return `
    <article class="pr-post-card" data-id="${post.id}">
      <div class="pr-post-header">
        ${avatarHTML}
        <div class="pr-post-meta">
          <div class="pr-post-user">${post.username}</div>
          <div class="pr-post-date">${post.created_at}${post.town_name ? ' • ' + post.town_name : ''}</div>
        </div>
        <span class="pr-post-kind-badge ${post.kind}">${kindLabel}</span>
      </div>
      ${photoHTML}
      <div class="pr-post-body">
        <p class="pr-post-text">${post.text}</p>
        <div class="pr-post-actions">
          <button class="pr-action-btn pr-heart-btn ${heartActive}" data-id="${post.id}">
            <img src="/assets/icons/heart.png" alt="Heart" class="pr-icon-img">
            <span>${post.heart_count}</span>
          </button>
          <button class="pr-action-btn pr-pray-btn ${prayActive}" data-id="${post.id}">
            <img src="/assets/icons/amen.png" alt="Amen" class="pr-icon-img">
            <span>${post.pray_count}</span>
          </button>
          <button class="pr-action-btn pr-comment-btn" data-id="${post.id}">
            <img src="/assets/icons/comment.png" alt="Comment" class="pr-icon-img">
            <span>${post.comment_count}</span>
          </button>
          ${actionsHTML}
          ${deleteHTML}
        </div>
      </div>
    </article>
  `;
}

// Helper function for escaping HTML
function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

  // [attachPostListeners, toggleReaction, modal functions, etc.]
  
  function attachPostListeners() {
    document.querySelectorAll('.pr-heart-btn').forEach(btn => {
      btn.addEventListener('click', async () => {
        const postId = btn.dataset.id;
        await toggleReaction(postId, 'heart', btn);
      });
    });

    document.querySelectorAll('.pr-pray-btn').forEach(btn => {
      btn.addEventListener('click', async () => {
        const postId = btn.dataset.id;
        await toggleReaction(postId, 'pray', btn);
      });
    });

    document.querySelectorAll('.pr-comment-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const postId = btn.dataset.id;
        openCommentsModal(postId);
      });
    });

    document.querySelectorAll('.pr-edit-post-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const postId = btn.dataset.id;
        const text = btn.dataset.text;
        const photo = btn.dataset.photo;
        startEditPost(postId, text, photo);
      });
    });

    document.querySelectorAll('.pr-delete-post-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const postId = btn.dataset.id;
        deletePost(postId);
      });
    });
  }

  async function toggleReaction(postId, type, btn) {
    try {
      const res = await fetch('/prayers/api/posts/react.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ post_id: postId, type })
      });
      
      const data = await res.json();
      
      if (data.success) {
        if (data.active) {
          btn.classList.add('active');
        } else {
          btn.classList.remove('active');
        }
        const countSpan = btn.querySelector('span');
        countSpan.textContent = data.count;
      }
    } catch (err) {
      console.error(err);
    }
  }

  function initModal() {
    const modal = document.getElementById('commentsModal');
    const closeBtn = document.getElementById('closeModal');
    const overlay = modal.querySelector('.pr-modal-overlay');
    const form = document.getElementById('addCommentForm');

    closeBtn.addEventListener('click', (e) => {
      e.preventDefault();
      modal.setAttribute('hidden', '');
      editingCommentId = null;
      form.reset();
    });

    overlay.addEventListener('click', (e) => {
      e.preventDefault();
      modal.setAttribute('hidden', '');
      editingCommentId = null;
      form.reset();
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && !modal.hasAttribute('hidden')) {
        modal.setAttribute('hidden', '');
        editingCommentId = null;
        form.reset();
      }
    });

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      
      const postId = document.getElementById('commentPostId').value;
      const text = document.getElementById('commentText').value.trim();
      
      if (editingCommentId) {
        await updateComment(editingCommentId, text);
      } else {
        await createComment(postId, text);
      }
    });
  }

  async function openCommentsModal(postId) {
    const modal = document.getElementById('commentsModal');
    document.getElementById('commentPostId').value = postId;
    editingCommentId = null;
    document.getElementById('addCommentForm').reset();
    modal.removeAttribute('hidden');
    await loadComments(postId);
  }

  async function createComment(postId, text) {
    if (!text.trim()) return;

    try {
      const res = await fetch('/prayers/api/comments/create.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ post_id: postId, text })
      });
      
      const data = await res.json();
      
      if (data.success) {
        document.getElementById('addCommentForm').reset();
        loadComments(postId);
        loadPosts();
        showToast(T('comment_posted'), 'success');
      } else {
        showToast(data.error || T('error'), 'error');
      }
    } catch (err) {
      console.error(err);
      showToast(T('could_not_post'), 'error');
    }
  }

  async function updateComment(commentId, text) {
    if (!text.trim()) return;

    try {
      const res = await fetch('/prayers/api/comments/update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ comment_id: commentId, text })
      });
      
      const data = await res.json();
      
      if (data.success) {
        editingCommentId = null;
        document.getElementById('addCommentForm').reset();
        const postId = document.getElementById('commentPostId').value;
        loadComments(postId);
        showToast(T('comment_updated'), 'success');
      } else {
        showToast(data.error || T('error'), 'error');
      }
    } catch (err) {
      console.error(err);
      showToast(T('could_not_update'), 'error');
    }
  }

  async function deleteComment(commentId) {
    if (!confirm(T('delete_comment'))) return;

    try {
      const res = await fetch('/prayers/api/comments/delete.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ comment_id: commentId })
      });
      
      const data = await res.json();
      
      if (data.success) {
        const postId = document.getElementById('commentPostId').value;
        loadComments(postId);
        loadPosts();
        showToast(T('comment_deleted'), 'success');
      } else {
        showToast(data.error || T('error'), 'error');
      }
    } catch (err) {
      console.error(err);
      showToast(T('could_not_delete'), 'error');
    }
  }

  async function loadComments(postId) {
    const container = document.getElementById('commentsContainer');
    container.innerHTML = `
      <div class="pr-loading">
        <div class="pr-spinner"></div>
      </div>
    `;

    try {
      const res = await fetch(`/prayers/api/comments/list.php?post_id=${postId}`);
      const data = await res.json();

      if (data.success && data.comments.length > 0) {
        container.innerHTML = data.comments.map(c => renderComment(c)).join('');
        attachCommentListeners();
      } else {
        container.innerHTML = `<p style="text-align:center;color:var(--color-silver-dark);">${T('no_comments')}</p>`;
      }
    } catch (err) {
      console.error(err);
      container.innerHTML = `<p style="text-align:center;color:var(--color-silver-dark);">${T('could_not_load')}</p>`;
    }
  }

  // In /prayers/js/prayers.js - UPDATE renderComment function

function renderComment(c) {
  // Avatar: use photo or initials
  const avatarHTML = c.user_pic
    ? `<img src="${c.user_pic}" alt="${c.username}" class="pr-comment-avatar">`
    : `<div class="pr-comment-avatar pr-avatar-initials pr-avatar-initials-sm">${c.initials || '??'}</div>`;

  const actionsHTML = c.can_edit || c.can_delete ? `
    <div class="pr-comment-actions">
      ${c.can_edit ? `
        <button class="pr-action-btn pr-btn-sm pr-edit-comment-btn" data-id="${c.id}" data-text="${escapeHtml(c.text)}">
          <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <span>${T('edit')}</span>
        </button>
      ` : ''}
      ${c.can_delete ? `
        <button class="pr-action-btn pr-btn-sm pr-delete-comment-btn" data-id="${c.id}">
          <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M10 11v6M14 11v6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <span>${T('delete')}</span>
        </button>
      ` : ''}
    </div>
  ` : '';

  return `
    <div class="pr-comment" data-id="${c.id}">
      ${avatarHTML}
      <div class="pr-comment-content">
        <div class="pr-comment-header">
          <span class="pr-comment-user">${c.username}</span>
          <span class="pr-comment-date">${c.created_at}</span>
        </div>
        <p class="pr-comment-text">${c.text}</p>
        ${actionsHTML}
      </div>
    </div>
  `;
}

  function attachCommentListeners() {
    document.querySelectorAll('.pr-edit-comment-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        editingCommentId = btn.dataset.id;
        const text = btn.dataset.text;
        const textarea = document.getElementById('commentText');
        textarea.value = text;
        textarea.focus();
      });
    });

    document.querySelectorAll('.pr-delete-comment-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const commentId = btn.dataset.id;
        deleteComment(commentId);
      });
    });
  }

  function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `pr-toast pr-toast-${type}`;
    toast.textContent = message;
    toast.style.cssText = `
      position: fixed;
      bottom: 30px;
      right: 30px;
      padding: 16px 24px;
      background: linear-gradient(135deg, #1a1a1a 0%, #2a2a2a 100%);
      border: 2px solid ${type === 'success' ? 'var(--color-rosegold)' : 'var(--color-silver)'};
      border-radius: 12px;
      color: var(--color-peach);
      font-size: 14px;
      box-shadow: var(--shadow-lg), var(--glow-rosegold);
      z-index: 10000;
      animation: slide-in-right 0.3s ease-out;
    `;
    document.body.appendChild(toast);
    setTimeout(() => {
      toast.style.animation = 'slide-out-right 0.3s ease-out';
      setTimeout(() => toast.remove(), 300);
    }, 3000);
  }
})();