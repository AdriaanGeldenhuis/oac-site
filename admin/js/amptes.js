(function() {
  'use strict';

  const searchInput = document.getElementById('ampteSearch');
  if (searchInput) {
    searchInput.addEventListener('input', function(e) {
      const query = e.target.value.toLowerCase().trim();
      const cards = document.querySelectorAll('.ampte-card');
      
      cards.forEach(card => {
        const name = card.getAttribute('data-name');
        if (name && name.includes(query)) {
          card.style.display = '';
        } else {
          card.style.display = 'none';
        }
      });
      
      document.querySelectorAll('.ampte-group').forEach(group => {
        const visibleCards = group.querySelectorAll('.ampte-card:not([style*="display: none"])');
        if (visibleCards.length === 0) {
          group.style.display = 'none';
        } else {
          group.style.display = '';
        }
      });
    });
  }

  window.openRoleModal = function(userId, userName, currentAmpId) {
    document.getElementById('roleTargetId').value = userId;
    document.getElementById('roleTargetName').textContent = userName;
    document.getElementById('newAmpId').value = '';
    document.getElementById('roleChangeModal').style.display = 'flex';
  };

  window.closeRoleModal = function() {
    document.getElementById('roleChangeModal').style.display = 'none';
  };

  document.querySelectorAll('.btn-change-role').forEach(btn => {
    btn.addEventListener('click', function() {
      const userId = this.getAttribute('data-id');
      const userName = this.getAttribute('data-name');
      const currentAmpId = this.getAttribute('data-current-amp');
      openRoleModal(userId, userName, currentAmpId);
    });
  });

  document.getElementById('roleChangeForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    try {
      const response = await fetch('/admin/api/ampte/update_role.php', {
        method: 'POST',
        body: formData
      });
      
      const data = await response.json();
      
      if (data.success) {
        alert('Role changed successfully / Amp suksesvol verander');
        location.reload();
      } else {
        alert(data.error || 'Could not change role / Kon nie amp verander nie');
      }
    } catch (error) {
      alert('Network error / Netwerkfout: ' + error.message);
    }
  });

  document.querySelectorAll('.btn-delete-user').forEach(btn => {
    btn.addEventListener('click', async function(e) {
      e.preventDefault();
      e.stopPropagation();
      
      const userId = this.getAttribute('data-id');
      const userName = this.getAttribute('data-name');
      
      if (!confirm(`Are you sure you want to delete ${userName}?\nWeet jy seker jy wil ${userName} verwyder?\n\nThis action cannot be undone.\nHierdie aksie kan nie ongedaan gemaak word nie.`)) {
        return;
      }
      
      try {
        const response = await fetch('/admin/api/ampte/delete.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: `user_id=${userId}`
        });
        
        const data = await response.json();
        
        if (data.success) {
          alert('User deleted successfully / Gebruiker suksesvol verwyder');
          location.reload();
        } else {
          alert('Error: ' + (data.error || 'Unknown error'));
        }
      } catch (error) {
        console.error('Delete error:', error);
        alert('Network error / Netwerkfout');
      }
    });
  });

  document.querySelectorAll('.ampte-btn-view, .ampte-btn-calendar').forEach(link => {
    link.addEventListener('click', function(e) {
      e.stopPropagation();
    });
  });

  document.querySelector('.ampte-modal-overlay')?.addEventListener('click', closeRoleModal);

})();