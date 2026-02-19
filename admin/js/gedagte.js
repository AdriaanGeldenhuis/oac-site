(function() {
  'use strict';

  var form = document.getElementById('gedagteForm');
  var listEl = document.getElementById('gedagteList');
  var dateInput = document.getElementById('thoughtDate');
  var timeInput = document.getElementById('thoughtTime');
  var lang = window.GEDAGTE_LANG || {};

  // Default date to today
  if (dateInput) {
    dateInput.value = new Date().toISOString().split('T')[0];
  }

  // Load existing thoughts
  loadThoughts();

  // Save form
  if (form) {
    form.addEventListener('submit', async function(e) {
      e.preventDefault();
      var btn = form.querySelector('button[type="submit"]');
      btn.disabled = true;

      try {
        var response = await fetch('/admin/api/gedagte/save.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams(new FormData(form))
        });
        var data = await response.json();

        if (data.success) {
          alert(lang.saved || 'Saved!');
          form.reset();
          if (dateInput) dateInput.value = new Date().toISOString().split('T')[0];
          if (timeInput) timeInput.value = '07:00';
          loadThoughts();
        } else {
          alert(data.error || 'Error');
        }
      } catch (err) {
        alert('Network error: ' + err.message);
      }
      btn.disabled = false;
    });
  }

  async function loadThoughts() {
    if (!listEl) return;

    try {
      var response = await fetch('/admin/api/gedagte/list.php');
      var data = await response.json();

      if (!data.success || !data.thoughts || data.thoughts.length === 0) {
        listEl.innerHTML = '<p class="gedagte-empty">' + (lang.noUpcoming || 'No upcoming thoughts') + '</p>';
        return;
      }

      var today = new Date().toISOString().split('T')[0];
      var html = '';

      data.thoughts.forEach(function(t) {
        var isPast = t.display_date < today;
        var isToday = t.display_date === today;
        var statusClass = isToday ? 'gedagte-item--today' : (isPast ? 'gedagte-item--past' : '');

        html += '<div class="gedagte-item ' + statusClass + '" data-id="' + t.id + '">';
        html += '  <div class="gedagte-item-date">' + formatDate(t.display_date);
        if (t.display_time) {
          html += '<span class="gedagte-item-time">' + formatTime(t.display_time) + '</span>';
        }
        if (t.notification_sent == 1) {
          html += '<span class="gedagte-item-notif gedagte-item-notif--sent">' + (lang.notifSent || 'Sent') + '</span>';
        } else if (!isPast) {
          html += '<span class="gedagte-item-notif gedagte-item-notif--pending">' + (lang.notifPending || 'Pending') + '</span>';
        }
        html += '</div>';
        html += '  <div class="gedagte-item-content">' + escapeHtml(t.content) + '</div>';
        if (t.author) {
          html += '  <div class="gedagte-item-author">&mdash; ' + escapeHtml(t.author) + '</div>';
        }
        html += '  <button class="gedagte-delete-btn" data-id="' + t.id + '">' + (lang.deleteLabel || 'Delete') + '</button>';
        html += '</div>';
      });

      listEl.innerHTML = html;

      // Attach delete handlers
      listEl.querySelectorAll('.gedagte-delete-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
          deleteThought(parseInt(this.getAttribute('data-id')));
        });
      });
    } catch (err) {
      listEl.innerHTML = '<p class="gedagte-empty">Error loading</p>';
    }
  }

  async function deleteThought(id) {
    if (!confirm(lang.confirmDelete || 'Delete this thought?')) return;

    try {
      var response = await fetch('/admin/api/gedagte/delete.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + id
      });
      var data = await response.json();

      if (data.success) {
        var row = listEl.querySelector('[data-id="' + id + '"]');
        if (row) row.remove();

        if (listEl.children.length === 0) {
          listEl.innerHTML = '<p class="gedagte-empty">' + (lang.noUpcoming || 'No upcoming thoughts') + '</p>';
        }
      } else {
        alert(data.error || 'Error');
      }
    } catch (err) {
      alert('Network error: ' + err.message);
    }
  }

  function formatDate(dateStr) {
    var d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
  }

  function formatTime(timeStr) {
    // timeStr is "HH:MM:SS" - format as "HH:MM"
    var parts = timeStr.split(':');
    return parts[0] + ':' + parts[1];
  }

  function escapeHtml(str) {
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }
})();
