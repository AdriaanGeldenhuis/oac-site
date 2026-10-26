/**
 * Daily thought tab: save a thought for a date, see the coming ones, edit
 * or delete one. Talks to /admin/api/gedagte/ through OACUI.post (CSRF).
 * One thought per date: saving an existing date replaces it, so "Edit"
 * simply loads it back into the form.
 */
(function () {
  'use strict';

  const form = document.getElementById('gedagteForm');
  const listEl = document.getElementById('gedagteList');
  if (!form || !listEl || !window.OACUI) return;

  const L = window.GEDAGTE_LANG || {};
  const contentEl = document.getElementById('thoughtContent');
  const authorEl = document.getElementById('thoughtAuthor');
  const dateEl = document.getElementById('thoughtDate');
  const timeEl = document.getElementById('thoughtTime');
  const countEl = document.getElementById('thoughtCount');
  const resetBtn = document.getElementById('thoughtReset');
  const submitBtn = form.querySelector('button[type="submit"]');
  const MAX = parseInt(contentEl.getAttribute('maxlength'), 10) || 2000;

  // Today as the member's calendar sees it (not UTC, which is a day behind after midnight in SAST)
  function localISO(d) {
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  }
  const todayISO = () => localISO(new Date());

  let editing = false;

  function resetForm() {
    form.reset();
    dateEl.value = todayISO();
    timeEl.value = '07:00';
    editing = false;
    resetBtn.hidden = true;
    updateCount();
  }

  function updateCount() {
    const left = MAX - contentEl.value.length;
    countEl.textContent = left <= 200 ? (L.charsLeft || '').replace('{n}', String(left)) : '';
  }

  function el(tag, cls, text) {
    const n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text != null) n.textContent = text;
    return n;
  }

  function fmtDate(iso) {
    const d = new Date(iso + 'T00:00:00');
    return d.toLocaleDateString(L.lang || undefined, { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
  }

  function showEmpty(message) {
    listEl.replaceChildren(el('p', 'admin-muted', message));
    listEl.setAttribute('aria-busy', 'false');
  }

  async function loadThoughts() {
    const res = await fetch('/admin/api/gedagte/list.php', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(r => r.json()).catch(() => null);

    if (!res || !res.success) {
      showEmpty(L.loadFailed || '');
      return;
    }
    const thoughts = res.thoughts || [];
    if (!thoughts.length) {
      showEmpty(L.noUpcoming || '');
      return;
    }

    const today = todayISO();
    const frag = document.createDocumentFragment();

    thoughts.forEach(function (t) {
      const isToday = t.display_date === today;
      const isPast = t.display_date < today;
      const item = el('article', 'gedagte-item' + (isToday ? ' gedagte-item--today' : '') + (isPast ? ' gedagte-item--past' : ''));
      item.dataset.id = t.id;

      const head = el('div', 'gedagte-item-date');
      head.appendChild(el('span', 'gedagte-item-day', isToday ? (L.today || fmtDate(t.display_date)) : fmtDate(t.display_date)));
      if (t.display_time) {
        head.appendChild(el('span', 'gedagte-item-time', t.display_time.slice(0, 5)));
      }
      if (String(t.notification_sent) === '1') {
        head.appendChild(el('span', 'gedagte-item-notif gedagte-item-notif--sent', L.notifSent || ''));
      } else if (!isPast) {
        head.appendChild(el('span', 'gedagte-item-notif gedagte-item-notif--pending', L.notifPending || ''));
      }
      item.appendChild(head);

      item.appendChild(el('p', 'gedagte-item-content', t.content));
      if (t.author) item.appendChild(el('p', 'gedagte-item-author', '— ' + t.author));

      const actions = el('div', 'gedagte-item-actions');
      const editBtn = el('button', 'gedagte-btn', L.editLabel || 'Edit');
      editBtn.type = 'button';
      editBtn.addEventListener('click', () => editThought(t));
      const delBtn = el('button', 'gedagte-btn gedagte-btn--danger gedagte-delete-btn', L.deleteLabel || 'Delete');
      delBtn.type = 'button';
      delBtn.addEventListener('click', () => deleteThought(t.id, delBtn));
      actions.append(editBtn, delBtn);
      item.appendChild(actions);

      frag.appendChild(item);
    });

    listEl.replaceChildren(frag);
    listEl.setAttribute('aria-busy', 'false');
  }

  function editThought(t) {
    contentEl.value = t.content || '';
    authorEl.value = t.author || '';
    dateEl.value = t.display_date;
    timeEl.value = t.display_time ? t.display_time.slice(0, 5) : '';
    editing = true;
    resetBtn.hidden = false;
    updateCount();
    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    contentEl.focus({ preventScroll: true });
  }

  async function deleteThought(id, btn) {
    const sure = await OACUI.confirm(L.confirmDelete || '', { danger: true });
    if (!sure) return;

    btn.disabled = true;
    const data = await OACUI.post('/admin/api/gedagte/delete.php', { id: id });
    if (data.success) {
      OACUI.toast(L.deleted || '', 'success');
      loadThoughts();
    } else {
      OACUI.toast(data.error || '', 'error');
      btn.disabled = false;
    }
  }

  form.addEventListener('submit', async function (e) {
    e.preventDefault();
    if (!contentEl.value.trim()) {
      contentEl.focus();
      return;
    }
    if (!dateEl.value) {
      dateEl.focus();
      return;
    }

    submitBtn.disabled = true;
    submitBtn.classList.add('is-busy');
    const data = await OACUI.post('/admin/api/gedagte/save.php', new URLSearchParams(new FormData(form)));
    submitBtn.disabled = false;
    submitBtn.classList.remove('is-busy');

    if (data.success) {
      OACUI.toast(L.saved || '', 'success');
      resetForm();
      loadThoughts();
    } else {
      OACUI.toast(data.error || '', 'error');
    }
  });

  resetBtn.addEventListener('click', resetForm);
  contentEl.addEventListener('input', updateCount);

  // Leaving with unsent words asks first
  window.addEventListener('beforeunload', function (e) {
    if (contentEl.value.trim() && !submitBtn.disabled) {
      e.preventDefault();
      e.returnValue = '';
    }
  });

  resetForm();
  loadThoughts();
})();
