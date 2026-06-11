/**
 * Admin Dashboard - Approvals & Spouse Request Actions
 * (Tab switching is server-side via ?tab= links.)
 */
(function() {
  'use strict';

  const root = document.querySelector('.admin-body');
  if (!root) return;

  async function postForm(url, params) {
    const response = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams(params)
    });
    return response.json();
  }

  function removeRow(id) {
    const row = document.getElementById(id);
    if (row) row.remove();
  }

  // ===== User approvals =====
  document.querySelectorAll('.approve-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
      const userId = this.getAttribute('data-id');
      if (!userId) return;

      this.disabled = true;
      try {
        const data = await postForm('/admin/api/approve_user.php', { user_id: userId });
        if (data.success) {
          removeRow(`row-${userId}`);
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

  document.querySelectorAll('.reject-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
      const userId = this.getAttribute('data-id');
      if (!userId) return;

      if (!confirm('Is jy seker jy wil hierdie gebruiker afwys?')) return;

      this.disabled = true;
      try {
        const data = await postForm('/admin/api/reject_user.php', { user_id: userId });
        if (data.success) {
          removeRow(`row-${userId}`);
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

  // ===== Spouse requests (elder approval) =====
  document.querySelectorAll('.spouse-approve-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
      const requestId = this.getAttribute('data-id');
      if (!requestId) return;

      if (!confirm('Koppel hierdie twee lidmate as huweliksmaats?')) return;

      this.disabled = true;
      try {
        const data = await postForm('/admin/api/spouse/approve.php', { request_id: requestId });
        if (data.success) {
          removeRow(`spouse-row-${requestId}`);
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

  document.querySelectorAll('.spouse-reject-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
      const requestId = this.getAttribute('data-id');
      if (!requestId) return;

      if (!confirm('Is jy seker jy wil hierdie huweliksversoek afwys?')) return;

      this.disabled = true;
      try {
        const data = await postForm('/admin/api/spouse/reject.php', { request_id: requestId });
        if (data.success) {
          removeRow(`spouse-row-${requestId}`);
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
