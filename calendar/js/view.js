/**
 * Calendar View (Read-Only) for viewing other users' calendars
 * Fixed: Clicking on calendar now redirects to make appointment
 */

(function() {
  'use strict';

  let currentDate = new Date();
  let currentView = 'week';
  let events = [];

  const viewBtns = document.querySelectorAll('.cal-view-btn');
  const dayView = document.getElementById('dayView');
  const weekView = document.getElementById('weekView');
  const monthView = document.getElementById('monthView');
  const calTitle = document.getElementById('calTitle');
  const prevBtn = document.getElementById('prevBtn');
  const nextBtn = document.getElementById('nextBtn');
  const todayBtn = document.getElementById('todayBtn');

  function init() {
    console.log('Calendar view init for user:', window.TARGET_USER_ID);
    attachListeners();
    loadEvents();
    renderView();
  }

  function attachListeners() {
    viewBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        switchView(btn.dataset.view);
      });
    });

    prevBtn.addEventListener('click', () => navigate(-1));
    nextBtn.addEventListener('click', () => navigate(1));
    todayBtn.addEventListener('click', () => {
      currentDate = new Date();
      renderView();
    });
  }

  function switchView(view) {
    currentView = view;
    viewBtns.forEach(btn => {
      btn.classList.toggle('active', btn.dataset.view === view);
    });
    dayView.style.display = view === 'day' ? 'block' : 'none';
    weekView.style.display = view === 'week' ? 'block' : 'none';
    monthView.style.display = view === 'month' ? 'block' : 'none';
    renderView();
  }

  function navigate(direction) {
    if (currentView === 'day') {
      currentDate.setDate(currentDate.getDate() + direction);
    } else if (currentView === 'week') {
      currentDate.setDate(currentDate.getDate() + (direction * 7));
    } else {
      currentDate.setMonth(currentDate.getMonth() + direction);
    }
    renderView();
  }

  function renderView() {
    updateTitle();
    if (currentView === 'day') {
      renderDay();
    } else if (currentView === 'week') {
      renderWeek();
    } else {
      renderMonth();
    }
  }

  function updateTitle() {
    const y = currentDate.getFullYear();
    const m = currentDate.getMonth();
    const d = currentDate.getDate();
    
    if (currentView === 'day') {
      calTitle.textContent = `${window.T.dayNames[currentDate.getDay()]}, ${d} ${window.T.monthNames[m]} ${y}`;
    } else {
      calTitle.textContent = `${window.T.monthNames[m]} ${y}`;
    }
  }

  async function loadEvents() {
    try {
      const res = await fetch(`/calendar/api/view_user_calendar.php?user_id=${window.TARGET_USER_ID}`);
      events = await res.json();
      console.log('Events loaded:', events.length);
      renderView();
    } catch (error) {
      console.error('Load events error:', error);
    }
  }

  function renderDay() {
    const grid = document.getElementById('dayGrid');
    grid.innerHTML = '';
    const dateStr = formatDate(currentDate);
    const dayEvents = events.filter(e => e.date === dateStr);
    
    for (let hour = 0; hour < 24; hour++) {
      const label = document.createElement('div');
      label.className = 'cal-hour-label';
      label.textContent = `${hour.toString().padStart(2, '0')}:00`;
      grid.appendChild(label);
      
      const slot = document.createElement('div');
      slot.className = 'cal-hour-slot';
      slot.dataset.hour = hour;
      slot.dataset.date = dateStr;
      
      const hourEvents = dayEvents.filter(e => {
        if (!e.time) return false;
        const eventHour = parseInt(e.time.split(':')[0]);
        return eventHour === hour;
      });
      
      hourEvents.forEach(event => {
        const eventEl = createEventElement(event);
        slot.appendChild(eventEl);
      });
      
      // Make clickable for appointment
      if (!window.VIEW_ONLY || window.TARGET_USER_ID !== window.USER_ID) {
        slot.style.cursor = 'pointer';
        slot.addEventListener('click', (e) => {
          if (e.target === slot) {
            const timeStr = `${hour.toString().padStart(2, '0')}:00`;
            requestAppointment(dateStr, timeStr);
          }
        });
      }
      
      grid.appendChild(slot);
    }
  }

  function renderWeek() {
    const grid = document.getElementById('weekGrid');
    grid.innerHTML = '';
    const weekStart = getWeekStart(currentDate);
    const today = formatDate(new Date());
    const dayOrder = [1, 2, 3, 4, 5, 6, 0];
    
    dayOrder.forEach((dayIndex) => {
      const date = new Date(weekStart);
      date.setDate(date.getDate() + dayIndex);
      const dateStr = formatDate(date);
      const dayName = window.T.dayNamesShort[dayIndex];
      
      const dayBlock = document.createElement('div');
      dayBlock.className = 'cal-week-day';
      if (dayIndex === 0) dayBlock.classList.add('sunday');
      if (dateStr === today) dayBlock.classList.add('today');
      
      const dayHeader = document.createElement('div');
      dayHeader.className = 'cal-week-day-header';
      dayHeader.innerHTML = `<strong>${dayName}</strong> ${date.getDate()}`;
      dayBlock.appendChild(dayHeader);
      
      const eventsContainer = document.createElement('div');
      eventsContainer.className = 'cal-week-day-events';
      
      const dayEvents = events.filter(e => e.date === dateStr);
      dayEvents.sort((a, b) => (a.time || '00:00').localeCompare(b.time || '00:00'));
      
      if (dayEvents.length > 0) {
        dayEvents.forEach(event => {
          const eventEl = createEventElement(event);
          eventsContainer.appendChild(eventEl);
        });
      } else {
        const emptyMsg = document.createElement('div');
        emptyMsg.className = 'cal-empty-msg';
        emptyMsg.textContent = window.T.noEvents;
        eventsContainer.appendChild(emptyMsg);
      }
      
      dayBlock.appendChild(eventsContainer);
      
      // Make clickable for appointment
      if (!window.VIEW_ONLY || window.TARGET_USER_ID !== window.USER_ID) {
        dayBlock.style.cursor = 'pointer';
        dayBlock.addEventListener('click', (e) => {
          if (e.target === dayBlock || e.target === dayHeader || e.target === eventsContainer || e.target.classList.contains('cal-empty-msg')) {
            requestAppointment(dateStr, null);
          }
        });
      }
      
      grid.appendChild(dayBlock);
    });
  }

  function renderMonth() {
    const grid = document.getElementById('monthGrid');
    grid.innerHTML = '';
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const daysInPrevMonth = new Date(year, month, 0).getDate();
    const today = formatDate(new Date());
    
    for (let i = firstDay - 1; i >= 0; i--) {
      const day = daysInPrevMonth - i;
      const date = new Date(year, month - 1, day);
      const cell = createDayCell(date, true);
      grid.appendChild(cell);
    }
    
    for (let day = 1; day <= daysInMonth; day++) {
      const date = new Date(year, month, day);
      const cell = createDayCell(date, false);
      grid.appendChild(cell);
    }
    
    const totalCells = grid.children.length;
    const remaining = totalCells % 7 === 0 ? 0 : 7 - (totalCells % 7);
    for (let day = 1; day <= remaining; day++) {
      const date = new Date(year, month + 1, day);
      const cell = createDayCell(date, true);
      grid.appendChild(cell);
    }
  }

  function createDayCell(date, otherMonth) {
    const cell = document.createElement('div');
    cell.className = 'cal-day';
    const dateStr = formatDate(date);
    const today = formatDate(new Date());
    
    if (otherMonth) cell.classList.add('other-month');
    if (dateStr === today) cell.classList.add('today');
    
    const number = document.createElement('div');
    number.className = 'cal-day-number';
    number.textContent = date.getDate();
    cell.appendChild(number);
    
    const dayEvents = events.filter(e => e.date === dateStr);
    dayEvents.slice(0, 3).forEach(event => {
      const eventEl = createEventElement(event);
      cell.appendChild(eventEl);
    });
    
    // Make clickable for appointment (if not viewing own calendar and not other month)
    if ((!window.VIEW_ONLY || window.TARGET_USER_ID !== window.USER_ID) && !otherMonth) {
      cell.style.cursor = 'pointer';
      cell.addEventListener('click', (e) => {
        if (e.target === cell || e.target === number) {
          requestAppointment(dateStr, null);
        }
      });
    }
    
    return cell;
  }

  function createEventElement(event) {
    const el = document.createElement('div');
    el.className = 'cal-event';
    el.classList.add(event.type || 'event');
    
    let eventText = '';
    if (event.time && event.time !== '00:00') {
      eventText = `${event.time} `;
    }
    eventText += event.title;
    
    el.textContent = eventText;
    el.title = event.title;
    
    // Prevent click bubbling
    el.addEventListener('click', (e) => {
      e.stopPropagation();
    });
    
    return el;
  }

  function getWeekStart(date) {
    const d = new Date(date);
    const day = d.getDay();
    const diff = d.getDate() - day;
    return new Date(d.setDate(diff));
  }

  function formatDate(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
  }

  function requestAppointment(date, time) {
    console.log('Request appointment:', {date, time, targetUser: window.TARGET_USER_ID});
    
    // Redirect to admin afsprake tab with parameters
    const url = `/admin/index.php?tab=afsprake&make_appointment=1&target_user=${window.TARGET_USER_ID}&date=${date}${time ? '&time=' + time : ''}`;
    window.location.href = url;
  }

  // Export to PDF
  window.exportToPDF = async function() {
    try {
      showToast('Generating PDF...', 'info');
      
      const response = await fetch('/calendar/api/export_pdf.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `user_id=${window.TARGET_USER_ID}&view=${currentView}&date=${formatDate(currentDate)}`
      });
      
      if (response.ok) {
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `calendar_${window.TARGET_USER_ID}_${Date.now()}.pdf`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        showToast('PDF exported!', 'success');
      } else {
        showToast('Export failed', 'error');
      }
    } catch (error) {
      console.error('Export PDF error:', error);
      showToast('Network error', 'error');
    }
  };

  // Export to Excel
  window.exportToExcel = async function() {
    try {
      showToast('Generating Excel...', 'info');
      
      const response = await fetch('/calendar/api/export_excel.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `user_id=${window.TARGET_USER_ID}&view=${currentView}&date=${formatDate(currentDate)}`
      });
      
      if (response.ok) {
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `calendar_${window.TARGET_USER_ID}_${Date.now()}.csv`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        showToast('Excel exported!', 'success');
      } else {
        showToast('Export failed', 'error');
      }
    } catch (error) {
      console.error('Export Excel error:', error);
      showToast('Network error', 'error');
    }
  };

  // Toast function
  function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `cal-toast cal-toast-${type} show`;
    toast.textContent = message;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
      toast.classList.remove('show');
      setTimeout(() => toast.remove(), 300);
    }, 3000);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();