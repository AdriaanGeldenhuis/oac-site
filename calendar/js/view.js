/**
 * Someone else's calendar (view.php): calendar.js draws it read only; this
 * keeps the PDF / Excel links on the period being looked at and gives the
 * photo a fallback. The server checks every right again.
 */
(function () {
  'use strict';

  const CFG = window.CAL;
  if (!CFG || CFG.mode !== 'view') return;

  // A broken photo becomes the initials instead of a broken-image icon
  const avatar = document.querySelector('img.cal-avatar');
  if (avatar) {
    avatar.addEventListener('error', () => {
      const span = document.createElement('span');
      span.className = 'cal-avatar cal-avatar--initials';
      span.setAttribute('aria-hidden', 'true');
      span.textContent = avatar.getAttribute('data-initials') || '?';
      avatar.replaceWith(span);
    });
  }

  const pdf = document.getElementById('calExportPdf');
  const csv = document.getElementById('calExportCsv');
  if (!pdf && !csv) return;

  // Export the month that is on screen (the whole month, also in week/day view)
  function monthRange(date) {
    const y = +date.slice(0, 4);
    const m = +date.slice(5, 7);
    const last = new Date(y, m, 0, 12).getDate();
    const mm = String(m).padStart(2, '0');
    return { from: y + '-' + mm + '-01', to: y + '-' + mm + '-' + String(last).padStart(2, '0') };
  }

  document.addEventListener('cal:render', (e) => {
    const r = monthRange(e.detail.date);
    const qs = '?user_id=' + encodeURIComponent(CFG.targetId) + '&from=' + r.from + '&to=' + r.to;
    if (pdf) pdf.href = '/calendar/api/export_pdf.php' + qs;
    if (csv) csv.href = '/calendar/api/export_excel.php' + qs;
  });

  // The file downloads in place; say so, since nothing on the page changes
  [pdf, csv].forEach((a) => {
    if (!a) return;
    a.addEventListener('click', () => {
      const toast = document.getElementById('calToast');
      if (!toast) return;
      toast.textContent = (CFG.t && CFG.t.cal_exporting) || '';
      toast.classList.remove('is-error');
      toast.classList.add('is-on');
      setTimeout(() => toast.classList.remove('is-on'), 2400);
    });
  });
})();
