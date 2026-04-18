<?php
// Get current language for translations
$ftrLang = $_SESSION['language'] ?? 'af';
function t_ftr(string $key): string {
    global $ftrLang;
    return __t($key, $ftrLang);
}
?>
<!-- Global Notification Badge - Bottom Right -->
<div class="global-notif-badge" id="globalNotifBadge" onclick="window.location.href='/notifications/notifications.php'">
  <svg class="global-notif-icon" width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
    <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 01-3.46 0" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
  </svg>
  <span class="global-notif-count" id="globalNotifCount" hidden>0</span>
</div>

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
<button class="global-back-btn" type="button" onclick="window.history.back()" aria-label="<?= t_ftr('back') ?>">
  <svg class="global-back-icon" width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
    <path d="M19 12H5M12 19l-7-7 7-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
  </svg>
</button>