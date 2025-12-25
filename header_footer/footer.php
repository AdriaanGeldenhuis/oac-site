<!-- Global Notification Badge - Bottom Right -->
<div class="global-notif-badge" id="globalNotifBadge" style="
  position: fixed;
  bottom: 30px;
  right: 30px;
  z-index: 999;
  width: 60px;
  height: 60px;
  background: linear-gradient(135deg, #2a2a2a 0%, #1a1a1a 100%);
  border: 1px solid var(--color-rosegold);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.8), 0 0 20px rgba(183, 110, 121, 0.4);
  transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
  animation: float-badge 3s ease-in-out infinite;
" onclick="window.location.href='/notifications/notifications.php'">
  <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="filter: drop-shadow(0 2px 4px rgba(0,0,0,0.6));">
    <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 01-3.46 0" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
  </svg>
  <span id="globalNotifCount" style="
    position: absolute;
    top: -5px;
    right: -5px;
    background: linear-gradient(135deg, #f3c3b1 0%, #f8d8c9 100%);
    color: #1a1a1a;
    font-size: 12px;
    font-weight: 600;
    padding: 4px 8px;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.6);
    min-width: 20px;
    text-align: center;
  " hidden>0</span>
</div>

<style>
@keyframes float-badge {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-10px); }
}

.global-notif-badge:hover {
  transform: scale(1.1) translateY(-5px) !important;
  box-shadow: 0 12px 48px rgba(0, 0, 0, 0.9), 0 0 30px rgba(183, 110, 121, 0.6) !important;
}

.global-notif-badge:active {
  transform: scale(1.05) !important;
}

@media (max-width: 768px) {
  .global-notif-badge {
    bottom: 20px;
    right: 20px;
    width: 56px;
    height: 56px;
  }
  
  .global-notif-badge svg {
    width: 24px;
    height: 24px;
  }
}
</style>

<script>
// Auto-update notification count
(async function updateNotifCount() {
  try {
    const response = await fetch('/notifications/api/count.php');
    const data = await response.json();
    
    if (data.success) {
      const badge = document.getElementById('globalNotifCount');
      if (badge) {
        if (data.count > 0) {
          badge.textContent = data.count > 99 ? '99+' : data.count;
          badge.hidden = false;
        } else {
          badge.hidden = true;
        }
      }
    }
  } catch (error) {
    console.error('Update notif count error:', error);
  }
  
  // Update every 30 seconds
  setTimeout(updateNotifCount, 30000);
})();
</script>

<!-- Global Back Button - Bottom Left -->
<button class="global-back-btn" onclick="window.history.back()" style="
  position: fixed;
  bottom: 30px;
  left: 30px;
  z-index: 999;
  width: 60px;
  height: 60px;
  background: linear-gradient(135deg, #2a2a2a 0%, #1a1a1a 100%);
  border: 2px solid #c0c0c0;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.8), 0 0 20px rgba(192, 192, 192, 0.3);
  transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
" aria-label="Go back">
  <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="filter: drop-shadow(0 2px 4px rgba(0,0,0,0.6));">
    <path d="M19 12H5M12 19l-7-7 7-7" stroke="#c0c0c0" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
  </svg>
</button>

<style>
.global-back-btn:hover {
  transform: scale(1.1) translateX(-5px);
  box-shadow: 0 12px 48px rgba(0, 0, 0, 0.9), 0 0 30px rgba(192, 192, 192, 0.5);
  border-color: #b76e79;
}

.global-back-btn:active {
  transform: scale(1.05);
}

@media (max-width: 768px) {
  .global-back-btn {
    bottom: 20px;
    left: 20px;
    width: 56px;
    height: 56px;
  }
  
  .global-back-btn svg {
    width: 24px;
    height: 24px;
  }
}
</style>