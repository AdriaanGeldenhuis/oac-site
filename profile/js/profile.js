/**
 * AI Slimbybel Profile - Complete JavaScript
 * Handles edit, delete, export and modal interactions
 */

(function() {
  'use strict';

  // Open Edit Modal
  window.openEditModal = async function(userId) {
    try {
      // Fetch user data
      const response = await fetch(`/profile/api/get_user.php?id=${userId}`);
      const data = await response.json();
      
      if (data.success) {
        document.getElementById('editUserId').value = userId;
        document.getElementById('editAmpId').value = data.user.amp_id;
        
        document.getElementById('editModal').style.display = 'flex';
      } else {
        showToast(data.error || 'Could not load user data', 'error');
      }
    } catch (error) {
      showToast('Network error', 'error');
      console.error('Load user error:', error);
    }
  };

  // Close Edit Modal
  window.closeEditModal = function() {
    document.getElementById('editModal').style.display = 'none';
  };

  // Handle Edit Form Submit
  const editForm = document.getElementById('editForm');
  if (editForm) {
    editForm.addEventListener('submit', async function(e) {
      e.preventDefault();
      
      const formData = new FormData(editForm);
      
      try {
        const response = await fetch('/profile/api/update_user.php', {
          method: 'POST',
          body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
          showToast('Updated successfully', 'success');
          closeEditModal();
          setTimeout(() => location.reload(), 1000);
        } else {
          showToast(data.error || 'Update failed', 'error');
        }
      } catch (error) {
        showToast('Network error', 'error');
        console.error('Update error:', error);
      }
    });
  }

  // Delete User
  window.deleteUser = async function(userId, userName) {
    const confirmMsg = `Are you sure you want to delete ${userName}?\n\nWeet jy seker jy wil ${userName} verwyder?\n\nThis action cannot be undone / Hierdie aksie kan nie ongedaan gemaak word nie.`;
    
    if (!confirm(confirmMsg)) return;
    
    try {
      const response = await fetch('/profile/api/delete_user.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `user_id=${userId}`
      });
      
      const data = await response.json();
      
      if (data.success) {
        showToast('User deleted successfully', 'success');
        setTimeout(() => window.location.href = '/admin/index.php?tab=afsprake', 1500);
      } else {
        showToast(data.error || 'Delete failed', 'error');
      }
    } catch (error) {
      showToast('Network error', 'error');
      console.error('Delete error:', error);
    }
  };

  // Export Calendar
  window.exportCalendar = async function(userId) {
    try {
      showToast('Preparing export...', 'success');
      
      const response = await fetch(`/profile/api/export_calendar.php?user_id=${userId}`);
      const blob = await response.blob();
      
      if (response.ok) {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `calendar_${userId}_${Date.now()}.ics`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        
        showToast('Calendar exported!', 'success');
      } else {
        showToast('Export failed', 'error');
      }
    } catch (error) {
      showToast('Network error', 'error');
      console.error('Export error:', error);
    }
  };

  // Show Toast
  function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `profile-toast profile-toast-${type}`;
    toast.textContent = message;
    
    document.body.appendChild(toast);
    
    setTimeout(() => toast.classList.add('show'), 10);
    
    setTimeout(() => {
      toast.classList.remove('show');
      setTimeout(() => toast.remove(), 300);
    }, 3000);
  }

  // Close modal on overlay click
  document.addEventListener('click', function(e) {
    if (e.target.classList.contains('profile-modal-overlay')) {
      closeEditModal();
    }
  });

  // Close modal on Escape
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      closeEditModal();
    }
  });

})();