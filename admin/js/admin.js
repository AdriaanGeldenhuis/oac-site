/**
 * Admin Dashboard - Tab Management & Interactions
 */
(function() {
  'use strict';
  
  const root = document.querySelector('.admin-body');
  if (!root) return;
  
  // Tab Navigation
  const tabs = Array.from(document.querySelectorAll('[role="tab"]'));
  const panels = Array.from(document.querySelectorAll('[role="tabpanel"]'));
  
  function activateTab(id) {
    tabs.forEach(tab => {
      const isActive = tab.getAttribute('data-panel') === id;
      tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
      tab.setAttribute('tabindex', isActive ? '0' : '-1');
    });
    
    panels.forEach(panel => {
      panel.setAttribute('aria-hidden', panel.id === id ? 'false' : 'true');
    });
    
    // Persist to sessionStorage
    try {
      sessionStorage.setItem('admin.activeTab', id);
    } catch (e) {
      // Ignore storage errors
    }
  }
  
  // Tab click handlers
  tabs.forEach(tab => {
    tab.addEventListener('click', (e) => {
      e.preventDefault();
      const panelId = tab.getAttribute('data-panel');
      if (panelId) activateTab(panelId);
    });
    
    // Keyboard navigation
    tab.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
        e.preventDefault();
        const currentIndex = tabs.indexOf(tab);
        const nextIndex = e.key === 'ArrowRight' 
          ? (currentIndex + 1) % tabs.length 
          : (currentIndex - 1 + tabs.length) % tabs.length;
        tabs[nextIndex].focus();
      }
      
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        tab.click();
      }
    });
  });
  
  // Restore last active tab
  let initialTab = 'tab-profile';
  try {
    const stored = sessionStorage.getItem('admin.activeTab');
    if (stored && document.getElementById(stored)) {
      initialTab = stored;
    }
  } catch (e) {
    // Ignore storage errors
  }
  activateTab(initialTab);
  
  // Approvals functionality
  const approveButtons = document.querySelectorAll('.approve-btn');
  const rejectButtons = document.querySelectorAll('.reject-btn');
  
  approveButtons.forEach(btn => {
    btn.addEventListener('click', async function() {
      const userId = this.getAttribute('data-id');
      if (!userId) return;
      
      this.disabled = true;
      try {
        const response = await fetch('/admin/api/approve_user.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({ user_id: userId })
        });
        
        const data = await response.json();
        if (data.success) {
          const row = document.getElementById(`row-${userId}`);
          if (row) row.remove();
          alert(data.message || 'Gebruiker goedgekeur');
        } else {
          alert(data.error || 'Kon nie goedkeur nie');
          this.disabled = false;
        }
      } catch (error) {
        alert('Netwerkfout: ' + error.message);
        this.disabled = false;
      }
    });
  });
  
  rejectButtons.forEach(btn => {
    btn.addEventListener('click', async function() {
      const userId = this.getAttribute('data-id');
      if (!userId) return;
      
      if (!confirm('Is jy seker jy wil hierdie gebruiker afwys?')) return;
      
      this.disabled = true;
      try {
        const response = await fetch('/admin/api/reject_user.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({ user_id: userId })
        });
        
        const data = await response.json();
        if (data.success) {
          const row = document.getElementById(`row-${userId}`);
          if (row) row.remove();
          alert(data.message || 'Gebruiker afgekeur');
        } else {
          alert(data.error || 'Kon nie afwys nie');
          this.disabled = false;
        }
      } catch (error) {
        alert('Netwerkfout: ' + error.message);
        this.disabled = false;
      }
    });
  });
})();