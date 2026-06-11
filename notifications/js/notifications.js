// =====================================================================
// AI Slimbybel Notifications - Complete Client Logic
// =====================================================================

(function() {
  'use strict';

  // ===== STATE =====
  let notifications = [];
  let currentFilter = 'all';
  let autoRefreshTimer = null;

  // ===== DOM ELEMENTS =====
  const notifList = document.getElementById('notifList');
  const notifLoading = document.getElementById('notifLoading');
  const markAllReadBtn = document.getElementById('markAllRead');
  const filterToggle = document.getElementById('filterToggle');
  const filterOptions = document.getElementById('filterOptions');
  const filterButtons = document.querySelectorAll('.notif-filter-btn');

  // ===== INIT =====
  function init() {
    loadNotifications();
    attachEventListeners();
    setupCardClickDelegation();
    startAutoRefresh();
  }

  // ===== EVENT LISTENERS =====
  function attachEventListeners() {
    if (markAllReadBtn) {
      markAllReadBtn.addEventListener('click', markAllAsRead);
    }

    if (filterToggle) {
      filterToggle.addEventListener('click', () => {
        if (filterOptions) {
          filterOptions.hidden = !filterOptions.hidden;
        }
      });
    }

    if (filterButtons) {
      filterButtons.forEach(btn => {
        btn.addEventListener('click', () => {
          currentFilter = btn.dataset.filter;
          filterButtons.forEach(b => b.classList.remove('active'));
          btn.classList.add('active');
          renderNotifications();
        });
      });
    }
  }

  // ===== LOAD NOTIFICATIONS =====
  async function loadNotifications() {
    if (notifLoading) notifLoading.hidden = false;

    try {
      const response = await fetch('/notifications/api/list.php');
      const data = await response.json();

      if (data.success) {
        notifications = data.notifications || [];
        renderNotifications();
        updateGlobalBadge();
      } else {
        showNotification(data.error || 'Failed to load notifications', 'error');
      }
    } catch (error) {
      console.error('Load notifications error:', error);
      showNotification('Network error', 'error');
    } finally {
      if (notifLoading) notifLoading.hidden = true;
    }
  }

  // ===== RENDER NOTIFICATIONS =====
  function renderNotifications() {
    if (!notifList) return;

    let filtered = notifications;

    // Apply filter. "reminder" covers time-bound notifications, "info" covers
    // everything else (so account/spouse/ampte/appointment etc. aren't orphaned).
    const reminderTypes = ['reminder', 'calendar', 'appointment', 'birthday'];
    if (currentFilter === 'unread') {
      filtered = notifications.filter(n => n.is_read === '0' || n.is_read === 0);
    } else if (currentFilter === 'reminder') {
      filtered = notifications.filter(n => reminderTypes.includes(n.type));
    } else if (currentFilter === 'info') {
      filtered = notifications.filter(n => !reminderTypes.includes(n.type));
    }

    if (filtered.length === 0) {
      notifList.innerHTML = `
        <div class="notif-empty">
          <svg class="notif-empty-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 01-3.46 0" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <p class="notif-empty-text">${window.T.noNotifications}</p>
        </div>
      `;
      return;
    }

    // Sort by created_at desc
    const sorted = [...filtered].sort((a, b) => {
      return new Date(b.created_at) - new Date(a.created_at);
    });

    notifList.innerHTML = sorted.map(notif => {
      const icon = notif.icon || getNotificationIcon(notif.type);
      const isUnread = notif.is_read === '0' || notif.is_read === 0;
      const timeAgo = getTimeAgo(notif.created_at);

      return `
        <div class="notif-card ${isUnread ? 'unread' : ''}" data-id="${notif.id}" ${notif.link ? `data-link="${escapeHtml(notif.link)}" style="cursor:pointer;"` : ''}>
          <div class="notif-icon-type">${icon}</div>
          <div class="notif-content">
              <h3 class="notif-title">${escapeHtml(notif.title)}</h3>
              ${notif.message ? `<p class="notif-message">${escapeHtml(notif.message)}</p>` : ''}
            <span class="notif-time">${timeAgo}</span>
          </div>
          <div class="notif-actions-inline">
            ${isUnread ? `
              <button class="notif-action-btn" onclick="window.NotificationSystem.markAsRead(${notif.id})" aria-label="${window.T.markRead}" title="${window.T.markRead}">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M9 11l3 3L22 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                  <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </button>
            ` : ''}
            <button class="notif-action-btn" onclick="window.NotificationSystem.deleteNotification(${notif.id})" aria-label="${window.T.delete}" title="${window.T.delete}">
              <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <polyline points="3 6 5 6 21 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </button>
          </div>
        </div>
      `;
    }).join('');
  }

  // ===== CLICK NOTIFICATION (event delegation on notifList) =====
  function setupCardClickDelegation() {
    if (!notifList) return;
    // Use a single delegated listener (idempotent — only attached once)
    if (notifList._notifClickBound) return;
    notifList._notifClickBound = true;

    notifList.addEventListener('click', function(event) {
      // Don't trigger if clicking action buttons
      if (event.target.closest('.notif-actions-inline')) return;

      const card = event.target.closest('.notif-card[data-link]');
      if (!card) return;

      const id = card.dataset.id;
      const link = card.dataset.link;

      // Mark as read (fire-and-forget so navigation isn't delayed)
      const notif = notifications.find(n => String(n.id) === String(id));
      if (notif && (notif.is_read === '0' || notif.is_read === 0)) {
        const body = new URLSearchParams({ id: id });
        fetch('/notifications/api/mark_read.php', {
          method: 'POST',
          body: body,
          keepalive: true
        }).catch(() => {});
        // Update local state immediately
        notif.is_read = 1;
        updateGlobalBadge();
      }

      // Navigate to the link
      window.location.href = link;
    });
  }

  // ===== MARK AS READ =====
  async function markAsRead(id) {
    try {
      const formData = new FormData();
      formData.append('id', id);

      const response = await fetch('/notifications/api/mark_read.php', {
        method: 'POST',
        body: formData
      });

      const data = await response.json();

      if (data.success) {
        await loadNotifications();
      } else {
        showNotification(data.error || window.T.error, 'error');
      }
    } catch (error) {
      console.error('Mark read error:', error);
      showNotification(window.T.error, 'error');
    }
  }

  // ===== MARK ALL AS READ =====
  async function markAllAsRead() {
    try {
      const response = await fetch('/notifications/api/mark_read.php', {
        method: 'POST',
        body: new URLSearchParams({ all: '1' })
      });

      const data = await response.json();

      if (data.success) {
        showNotification(window.T.success, 'success');
        await loadNotifications();
      } else {
        showNotification(data.error || window.T.error, 'error');
      }
    } catch (error) {
      console.error('Mark all read error:', error);
      showNotification(window.T.error, 'error');
    }
  }

  // ===== DELETE NOTIFICATION =====
  async function deleteNotification(id) {
    if (!confirm(window.T.confirm)) return;

    try {
      const formData = new FormData();
      formData.append('id', id);
      formData.append('delete', '1');

      const response = await fetch('/notifications/api/mark_read.php', {
        method: 'POST',
        body: formData
      });

      const data = await response.json();

      if (data.success) {
        showNotification(window.T.success, 'success');
        await loadNotifications();
      } else {
        showNotification(data.error || window.T.error, 'error');
      }
    } catch (error) {
      console.error('Delete error:', error);
      showNotification(window.T.error, 'error');
    }
  }

  // ===== UPDATE GLOBAL BADGE =====
  function updateGlobalBadge() {
    const unreadCount = notifications.filter(n => n.is_read === '0' || n.is_read === 0).length;
    
    // Update badge in global notification button (if exists)
    const globalBadge = document.getElementById('globalNotifCount');
    if (globalBadge) {
      if (unreadCount > 0) {
        globalBadge.textContent = unreadCount > 99 ? '99+' : unreadCount;
        globalBadge.hidden = false;
      } else {
        globalBadge.hidden = true;
      }
    }
  }

  // ===== AUTO REFRESH =====
  function startAutoRefresh() {
    // Refresh every 30 seconds
    if (autoRefreshTimer) clearInterval(autoRefreshTimer);
    autoRefreshTimer = setInterval(loadNotifications, 30000);

    // Stop polling when the page is backgrounded or unloaded.
    document.addEventListener('visibilitychange', () => {
      if (document.hidden && autoRefreshTimer) {
        clearInterval(autoRefreshTimer);
        autoRefreshTimer = null;
      } else if (!document.hidden && !autoRefreshTimer) {
        autoRefreshTimer = setInterval(loadNotifications, 30000);
        loadNotifications();
      }
    });
    window.addEventListener('pagehide', () => {
      if (autoRefreshTimer) {
        clearInterval(autoRefreshTimer);
        autoRefreshTimer = null;
      }
    });
  }

  // ===== GET NOTIFICATION ICON =====
  function getNotificationIcon(type) {
    const icons = {
      // Admin
      'success': '✅',
      'error': '❌',
      'warning': '⚠️',
      'info': 'ℹ️',
      'account': '👤',
      'spouse': '💍',
      'ampte': '⭐',
      'appointment': '📅',
      
      // Calendar
      'calendar': '📅',
      'reminder': '⏰',
      'event': '🎉',
      'diary': '📓',
      'visit': '🏠',
      
      // Gospel
      'gospel': '📢',
      'comment': '💬',
      'reaction': '❤️',
      'post': '✍️',
      'tag': '🏷️',
      
      // Bible
      'bible': '📖',
      'prayer': '🙏',

      // Daily thought & birthdays
      'thought': '💭',
      'birthday': '🎂'
    };
    
    return icons[type] || '🔔';
  }

  // ===== GET TIME AGO =====
  function getTimeAgo(dateStr) {
    if (!dateStr) return '';
    
    try {
      const now = new Date();
      const past = new Date(dateStr);
      const diffMs = now - past;
      const diffMins = Math.floor(diffMs / 60000);
      const diffHours = Math.floor(diffMs / 3600000);
      const diffDays = Math.floor(diffMs / 86400000);

      if (diffMins < 1) {
        return window.T.justNow;
      } else if (diffMins < 60) {
        return `${diffMins} ${window.T.minutesAgo}`;
      } else if (diffHours < 24) {
        return `${diffHours} ${window.T.hoursAgo}`;
      } else {
        return `${diffDays} ${window.T.daysAgo}`;
      }
    } catch (e) {
      return dateStr;
    }
  }

  // ===== HELPERS =====
  // Escapes all five HTML-significant chars — textContent→innerHTML does not
  // escape " or ', so values interpolated into attributes would break.
  function escapeHtml(text) {
    if (text === null || text === undefined || text === '') return '';
    return String(text)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function showNotification(message, type = 'info') {
    // Create toast notification
    const toast = document.createElement('div');
    toast.className = `notif-toast notif-toast-${type}`;
    toast.textContent = message;
    
    document.body.appendChild(toast);
    
    setTimeout(() => toast.classList.add('show'), 10);
    
    setTimeout(() => {
      toast.classList.remove('show');
      setTimeout(() => toast.remove(), 300);
    }, 3000);
  }

  // ===== EXPOSE TO GLOBAL =====
  window.NotificationSystem = {
    show: async function(title, message, type = 'info', link = '', icon = '') {
      try {
        const formData = new FormData();
        formData.append('user_id', window.USER_ID || '');
        formData.append('title', title);
        formData.append('message', message);
        formData.append('type', type);
        if (link) formData.append('link', link);
        if (icon) formData.append('icon', icon);

        await fetch('/notifications/api/create.php', {
          method: 'POST',
          body: formData
        });

        await loadNotifications();
      } catch (error) {
        console.error('Create notification error:', error);
      }
    },
    
    refresh: loadNotifications,
    markAsRead: markAsRead,
    deleteNotification: deleteNotification
  };

  // ===== START =====
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();