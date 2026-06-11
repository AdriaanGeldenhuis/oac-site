/**
 * Admin Dashboard - Approvals & Spouse Request Actions
 * (Tab switching is server-side via ?tab= links; toasts/dialogs in ui.js.)
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

  function decrementBadge() {
    const badge = document.querySelector('.admin-tab-badge');
    if (!badge) return;
    const current = parseInt(badge.textContent, 10);
    if (isNaN(current) || current <= 1) {
      badge.remove();
    } else {
      badge.textContent = String(current - 1);
    }
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
          decrementBadge();
          OACUI.toast(data.message || 'Gebruiker goedgekeur', 'success');
        } else {
          OACUI.toast(data.error || 'Kon nie goedkeur nie', 'error');
          this.disabled = false;
        }
      } catch (error) {
        OACUI.toast('Netwerkfout: ' + error.message, 'error');
        this.disabled = false;
      }
    });
  });

  document.querySelectorAll('.reject-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
      const userId = this.getAttribute('data-id');
      if (!userId) return;

      const sure = await OACUI.confirm('Is jy seker jy wil hierdie gebruiker afwys?', { danger: true });
      if (!sure) return;

      this.disabled = true;
      try {
        const data = await postForm('/admin/api/reject_user.php', { user_id: userId });
        if (data.success) {
          removeRow(`row-${userId}`);
          decrementBadge();
          OACUI.toast(data.message || 'Gebruiker afgekeur', 'success');
        } else {
          OACUI.toast(data.error || 'Kon nie afwys nie', 'error');
          this.disabled = false;
        }
      } catch (error) {
        OACUI.toast('Netwerkfout: ' + error.message, 'error');
        this.disabled = false;
      }
    });
  });

  // ===== Spouse requests (office bearer approval) =====
  document.querySelectorAll('.spouse-approve-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
      const requestId = this.getAttribute('data-id');
      if (!requestId) return;

      const sure = await OACUI.confirm('Koppel hierdie twee lidmate as huweliksmaats?');
      if (!sure) return;

      this.disabled = true;
      try {
        const data = await postForm('/admin/api/spouse/approve.php', { request_id: requestId });
        if (data.success) {
          removeRow(`spouse-row-${requestId}`);
          decrementBadge();
          OACUI.toast('Huweliksmaats gekoppel', 'success');
        } else {
          OACUI.toast(data.error || 'Kon nie goedkeur nie', 'error');
          this.disabled = false;
        }
      } catch (error) {
        OACUI.toast('Netwerkfout: ' + error.message, 'error');
        this.disabled = false;
      }
    });
  });

  document.querySelectorAll('.spouse-reject-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
      const requestId = this.getAttribute('data-id');
      if (!requestId) return;

      const sure = await OACUI.confirm('Is jy seker jy wil hierdie huweliksversoek afwys?', { danger: true });
      if (!sure) return;

      this.disabled = true;
      try {
        const data = await postForm('/admin/api/spouse/reject.php', { request_id: requestId });
        if (data.success) {
          removeRow(`spouse-row-${requestId}`);
          decrementBadge();
          OACUI.toast('Versoek afgewys', 'success');
        } else {
          OACUI.toast(data.error || 'Kon nie afwys nie', 'error');
          this.disabled = false;
        }
      } catch (error) {
        OACUI.toast('Netwerkfout: ' + error.message, 'error');
        this.disabled = false;
      }
    });
  });

  // ===== Linked spouses (office bearer unlink) =====
  document.querySelectorAll('.spouse-unlink-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
      const targetId = this.getAttribute('data-id');
      if (!targetId) return;

      const msg = (window.OAC_APPROVALS_LANG && window.OAC_APPROVALS_LANG.confirmUnlink) ||
        'Is jy seker jy wil hierdie huweliksmaats ontkoppel?';
      const sure = await OACUI.confirm(msg, { danger: true });
      if (!sure) return;

      this.disabled = true;
      try {
        const data = await postForm('/admin/api/spouse/unlink.php', { user_id: targetId });
        if (data.success) {
          removeRow(`couple-row-${targetId}`);
          OACUI.toast('Huweliksmaats ontkoppel', 'success');
        } else {
          OACUI.toast(data.error || 'Kon nie ontkoppel nie', 'error');
          this.disabled = false;
        }
      } catch (error) {
        OACUI.toast('Netwerkfout: ' + error.message, 'error');
        this.disabled = false;
      }
    });
  });
})();
