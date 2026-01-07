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
    initCreatePost();
    loadPosts();
    initModal();
  });

  // ==================== CREATE/UPDATE POST ====================
  function initCreatePost() {
    const form = document.getElementById('createPostForm');
    const tabs = document.querySelectorAll('.pr-tab');
    const fileInput = document.getElementById('photoInput');
    const fileLabel = document.getElementById('fileLabel');
    const submitBtn = document.getElementById('submitBtn');
    const textarea = document.getElementById('postText');

    tabs.forEach(tab => {
      tab.addEventListener('click', () => {
        tabs.forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        currentKind = tab.dataset.kind;
      });
    });

    fileInput.addEventListener('change', (e) => {
      const file = e.target.files[0];
      if (file) {
        fileLabel.textContent = file.name;
      } else {
        fileLabel.textContent = T('choose_photo');
      }
    });

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      
      if (editingPostId) {
        await updatePost(editingPostId, textarea.value, new FormData(form));
      } else {
        await createPost(new FormData(form));
      }
    });
  }

  async function createPost(formData) {
    const submitBtn = document.getElementById('submitBtn');
    const form = document.getElementById('createPostForm');
    const fileLabel = document.getElementById('fileLabel');
    
    formData.append('kind', currentKind);
    
    submitBtn.disabled = true;
    submitBtn.querySelector('.pr-btn-text').textContent = T('posting');

    try {
      const res = await fetch('/prayers/api/posts/create.php', {
        method: 'POST',
        body: formData
      });
      
      const data = await res.json();
      
      if (data.success) {
        form.reset();
        fileLabel.textContent = T('choose_photo');
        showToast(T('prayer_shared'), 'success');
        loadPosts();
      } else {
        showToast(data.error || T('error'), 'error');
      }
    } catch (err) {
      console.error(err);
      showToast(T('could_not_post'), 'error');
    } finally {
      submitBtn.disabled = false;
      submitBtn.querySelector('.pr-btn-text').textContent = T('share');
    }
  }

  async function updatePost(postId, text, formData) {
    const submitBtn = document.getElementById('submitBtn');
    
    formData.append('post_id', postId);
    formData.append('text', text);
    
    submitBtn.disabled = true;
    submitBtn.querySelector('.pr-btn-text').textContent = T('posting');

    try {
      const res = await fetch('/prayers/api/posts/update.php', {
        method: 'POST',
        body: formData
      });
      
      const data = await res.json();
      
      if (data.success) {
        showToast(T('post_updated'), 'success');
        cancelEdit();
        loadPosts();
      } else {
        showToast(data.error || T('error'), 'error');
      }
    } catch (err) {
      console.error(err);
      showToast(T('could_not_update'), 'error');
    } finally {
      submitBtn.disabled = false;
      submitBtn.querySelector('.pr-btn-text').textContent = T('share');
    }
  }

  async function removePostPhoto(postId) {
    if (!confirm(T('delete_photo'))) return;

    try {
      const res = await fetch('/prayers/api/posts/update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
          post_id: postId, 
          text: document.getElementById('postText').value,
          remove_photo: true 
        })
      });
      
      const data = await res.json();
      
      if (data.success) {
        showToast(T('photo_removed'), 'success');
        editingPostPhotoUrl = null;
        updatePhotoPreview();
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
    editingPostId = postId;
    editingPostPhotoUrl = photoUrl;
    
    const textarea = document.getElementById('postText');
    const submitBtn = document.getElementById('submitBtn');
    
    textarea.value = text;
    textarea.focus();
    submitBtn.querySelector('.pr-btn-text').textContent = T('update');
    
    updatePhotoPreview();
    
    document.querySelector('.pr-create-section').scrollIntoView({ behavior: 'smooth' });
  }

  function updatePhotoPreview() {
    const form = document.getElementById('createPostForm');
    let preview = form.querySelector('.pr-photo-preview');
    
    if (preview) preview.remove();
    
    if (editingPostPhotoUrl) {
      preview = document.createElement('div');
      preview.className = 'pr-photo-preview';
      preview.innerHTML = `
        <img src="${editingPostPhotoUrl}" alt="Current photo">
        <button type="button" class="pr-remove-photo-btn">
          <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2"/>
          </svg>
          ${T('remove_photo')}
        </button>
      `;
      
      form.querySelector('.pr-form-row').before(preview);
      
      preview.querySelector('.pr-remove-photo-btn').addEventListener('click', () => {
        removePostPhoto(editingPostId);
      });
    }
  }

  function cancelEdit() {
    editingPostId = null;
    editingPostPhotoUrl = null;
    document.getElementById('createPostForm').reset();
    document.getElementById('submitBtn').querySelector('.pr-btn-text').textContent = T('share');
    
    const preview = document.querySelector('.pr-photo-preview');
    if (preview) preview.remove();
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

  // Verander in die kode:
const userPic = post.user_pic && post.user_pic !== '/assets/default-avatar.png' 
  ? post.user_pic 
  : 'https://ui-avatars.com/api/?name=' + encodeURIComponent(post.username) + '&background=2a2a2a&color=c0c0c0&size=128';

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
        <img src="${userPic}" alt="${post.username}" class="pr-post-avatar">
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
  // Use default avatar if no photo
  const userPic = c.user_pic && c.user_pic !== '/assets/default-avatar.png' 
    ? c.user_pic 
    : '/assets/default-avatar.png';

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
      <img src="${userPic}" alt="${c.username}" class="pr-comment-avatar">
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