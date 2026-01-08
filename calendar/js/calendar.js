// =====================================================================
// AI Slimbybel Calendar - Complete Rewrite with Full Permissions
// =====================================================================

(function() {
  'use strict';

  // State
  let currentDate = new Date();
  let currentView = 'week';
  let events = [];
  let rooms = [];
  let users = [];
  let hasSpouse = false;
  let selectedDate = null;
  let selectedTime = null;
  let currentEvent = null;

  // DOM Elements
  const viewBtns = document.querySelectorAll('.cal-view-btn');
  const dayView = document.getElementById('dayView');
  const weekView = document.getElementById('weekView');
  const monthView = document.getElementById('monthView');
  const calTitle = document.getElementById('calTitle');
  const prevBtn = document.getElementById('prevBtn');
  const nextBtn = document.getElementById('nextBtn');
  const todayBtn = document.getElementById('todayBtn');
  
  const quickMenu = document.getElementById('quickMenu');
  const quickOverlay = document.getElementById('quickOverlay');
  
  const eventModal = document.getElementById('eventModal');
  const modalOverlay = document.getElementById('modalOverlay');
  const closeModalBtn = document.getElementById('closeModal');
  const cancelBtn = document.getElementById('cancelBtn');
  const deleteBtn = document.getElementById('deleteBtn');
  const eventForm = document.getElementById('eventForm');
  
  const toggleBtns = document.querySelectorAll('.cal-toggle-btn');

  // Initialize
  function init() {
    attachListeners();
    checkSpouse();
    loadRooms();
    loadUsers();
    loadEvents();
    renderView();
  }

  // Attach Event Listeners
  function attachListeners() {
    // View buttons
    viewBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        switchView(btn.dataset.view);
      });
    });

    // Navigation
    prevBtn.addEventListener('click', () => navigate(-1));
    nextBtn.addEventListener('click', () => navigate(1));
    todayBtn.addEventListener('click', () => {
      currentDate = new Date();
      renderView();
    });

    // Quick menu
    quickOverlay.addEventListener('click', closeQuickMenu);

    document.querySelectorAll('.cal-quick-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        closeQuickMenu();
        handleQuickAction(btn.dataset.action);
      });
    });

    // Modal
    closeModalBtn.addEventListener('click', hideModal);
    cancelBtn.addEventListener('click', hideModal);
    modalOverlay.addEventListener('click', hideModal);
    eventForm.addEventListener('submit', handleSubmit);
    deleteBtn.addEventListener('click', handleDelete);

    // Toggle buttons
    toggleBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        toggleBtns.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        togglePersonMode(btn.dataset.mode);
      });
    });

    // Keyboard
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') {
        if (eventModal.style.display !== 'none') hideModal();
        if (quickMenu.style.display !== 'none') closeQuickMenu();
      }
    });
  }

  // Switch View
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

  // Navigate
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

  // Render Current View
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

  // Update Title
  function updateTitle() {
    const y = currentDate.getFullYear();
    const m = currentDate.getMonth();
    const d = currentDate.getDate();
    
    if (currentView === 'day') {
      calTitle.textContent = `${window.T.dayNames[currentDate.getDay()]}, ${d} ${window.T.monthNames[m]} ${y}`;
    } else if (currentView === 'week') {
      calTitle.textContent = `${window.T.monthNames[m]} ${y}`;
    } else {
      calTitle.textContent = `${window.T.monthNames[m]} ${y}`;
    }
  }

  // Render Day
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
      
      slot.addEventListener('click', (e) => {
        if (e.target === slot) {
          selectedDate = dateStr;
          selectedTime = `${hour.toString().padStart(2, '0')}:00`;
          showQuickMenu();
        }
      });
      
      grid.appendChild(slot);
    }
  }

  // Render Week
  function renderWeek() {
    const grid = document.getElementById('weekGrid');
    grid.innerHTML = '';
    
    const weekStart = getWeekStart(currentDate);
    const today = formatDate(new Date());
    
    // Mon-Tue-Wed-Thu-Fri-Sat-Sun
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
      
      dayBlock.addEventListener('click', (e) => {
        if (e.target === dayBlock || e.target === dayHeader || e.target === eventsContainer || e.target.classList.contains('cal-empty-msg')) {
          selectedDate = dateStr;
          selectedTime = null;
          showQuickMenu();
        }
      });
      
      grid.appendChild(dayBlock);
    });
  }

  // Render Month
  function renderMonth() {
    const grid = document.getElementById('monthGrid');
    grid.innerHTML = '';
    
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const daysInPrevMonth = new Date(year, month, 0).getDate();
    const today = formatDate(new Date());
    
    // Previous month
    for (let i = firstDay - 1; i >= 0; i--) {
      const day = daysInPrevMonth - i;
      const date = new Date(year, month - 1, day);
      const cell = createDayCell(date, true);
      grid.appendChild(cell);
    }
    
    // Current month
    for (let day = 1; day <= daysInMonth; day++) {
      const date = new Date(year, month, day);
      const cell = createDayCell(date, false);
      grid.appendChild(cell);
    }
    
    // Next month
    const totalCells = grid.children.length;
    const remaining = totalCells % 7 === 0 ? 0 : 7 - (totalCells % 7);
    for (let day = 1; day <= remaining; day++) {
      const date = new Date(year, month + 1, day);
      const cell = createDayCell(date, true);
      grid.appendChild(cell);
    }
  }

  // Create Day Cell
  
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
  
  // Make clickable if not view-only OR if viewing own calendar
  if (!window.VIEW_ONLY || window.TARGET_USER_ID === window.USER_ID) {
    cell.style.cursor = 'pointer';
    cell.addEventListener('click', (e) => {
      if (e.target === cell || e.target === number) {
        // Redirect to make appointment
        window.location.href = `/admin/index.php?tab=afsprake&request_appointment=1&target_user=${window.TARGET_USER_ID}&date=${dateStr}`;
      }
    });
  }
  
  return cell;
}

  // Create Event Element
  function createEventElement(event) {
    const el = document.createElement('div');
    el.className = 'cal-event';
    el.classList.add(event.type || 'event');
    if (event.is_shared) el.classList.add('shared');
    
    let eventText = '';
    if (event.time && event.time !== '00:00') {
      eventText = `${event.time} `;
    }
    eventText += event.title;
    
    el.textContent = eventText;
    el.title = event.title;
    
    el.addEventListener('click', (e) => {
      e.stopPropagation();
      editEvent(event);
    });
    
    return el;
  }

  // Get Week Start (Sunday)
  function getWeekStart(date) {
    const d = new Date(date);
    const day = d.getDay();
    const diff = d.getDate() - day;
    return new Date(d.setDate(diff));
  }

  // Format Date
  function formatDate(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
  }

  // Show Quick Menu
  function showQuickMenu() {
    quickMenu.style.display = 'flex';
    setTimeout(() => quickMenu.classList.add('active'), 10);
  }

  // Close Quick Menu
  function closeQuickMenu() {
    quickMenu.classList.remove('active');
    setTimeout(() => quickMenu.style.display = 'none', 300);
  }

  // Handle Quick Action
  function handleQuickAction(action) {
    if (action === 'aktiwiteit') {
      showModal('event', selectedDate, selectedTime);
    } else if (action === 'dagboek') {
      openDiary(selectedDate, selectedTime);
    } else if (action === 'afspraak') {
      showModal('visit', selectedDate, selectedTime);
    }
  }

  // Open Diary
  function openDiary(date, time) {
    let url = `/diary/diary.php`;
    if (date) url += `?date=${date}`;
    if (time) url += `&time=${time}`;
    window.location.href = url;
  }

  // Load Rooms
  async function loadRooms() {
    try {
      const res = await fetch('/calendar/api/rooms.php');
      const data = await res.json();
      if (data.success) {
        rooms = data.rooms;
        populateRoomSelect();
      }
    } catch (error) {
      // Silent fail
    }
  }

  // Populate Room Select
  function populateRoomSelect() {
    const select = document.getElementById('eventRoom');
    select.innerHTML = '<option value="">Kies kamer...</option>';
    rooms.forEach(room => {
      const opt = document.createElement('option');
      opt.value = room.id;
      opt.textContent = room.name;
      select.appendChild(opt);
    });
  }

  // Load Users
  async function loadUsers() {
    try {
      const res = await fetch('/calendar/api/users.php');
      const data = await res.json();
      if (data.success) {
        users = data.users;
        populateUserSelect();
      }
    } catch (error) {
      // Silent fail
    }
  }

  // Populate User Select
  function populateUserSelect() {
    const select = document.getElementById('linkedUser');
    select.innerHTML = '<option value="">Kies gebruiker...</option>';
    users.forEach(user => {
      const opt = document.createElement('option');
      opt.value = user.id;
      opt.textContent = `${user.name} ${user.surname}`;
      select.appendChild(opt);
    });
  }

  // Check Spouse
  async function checkSpouse() {
    try {
      const res = await fetch('/calendar/api/spouse_status.php');
      const data = await res.json();
      hasSpouse = data.has_spouse || false;
      const shareGroup = document.getElementById('shareGroup');
      if (shareGroup) {
        shareGroup.style.display = hasSpouse ? 'block' : 'none';
      }
    } catch (error) {
      // Silent fail
    }
  }

  // Load Events
  async function loadEvents() {
    try {
      const res = await fetch('/calendar/api/list.php');
      events = await res.json();
      renderView();
    } catch (error) {
      // Silent fail
    }
  }

  // Show Modal
  function showModal(type, date, time) {
    currentEvent = null;
    
    document.getElementById('eventId').value = '';
    document.getElementById('eventTitle').value = '';
    document.getElementById('eventDate').value = date || formatDate(new Date());
    document.getElementById('eventTime').value = time || '';
    document.getElementById('eventNotes').value = '';
    document.getElementById('shareWithSpouse').checked = false;
    
    document.getElementById('eventRoom').value = '';
    document.getElementById('linkedUser').value = '';
    document.getElementById('personName').value = '';
    document.getElementById('eventLocation').value = '';
    
    document.getElementById('eventType').value = type;
    
    const roomGroup = document.getElementById('roomGroup');
    const userGroup = document.getElementById('userGroup');
    const locationGroup = document.getElementById('locationGroup');
    const shareGroup = document.getElementById('shareGroup');
    
    roomGroup.style.display = 'none';
    userGroup.style.display = 'none';
    locationGroup.style.display = 'none';
    shareGroup.style.display = hasSpouse ? 'block' : 'none';
    
    if (type === 'event') {
      document.getElementById('modalTitle').textContent = window.T.aktiwiteit;
      roomGroup.style.display = 'block';
    } else if (type === 'visit') {
      document.getElementById('modalTitle').textContent = window.T.afspraak;
      userGroup.style.display = 'block';
      locationGroup.style.display = 'block';
      
      toggleBtns.forEach(b => b.classList.remove('active'));
      if (toggleBtns[0]) toggleBtns[0].classList.add('active');
      togglePersonMode('user');
    }
    
    deleteBtn.style.display = 'none';
    
    eventModal.style.display = 'flex';
    document.getElementById('eventTitle').focus();
  }

  // Edit Event
  function editEvent(event) {
    currentEvent = event;
    
    document.getElementById('eventId').value = event.id;
    document.getElementById('eventTitle').value = event.title;
    document.getElementById('eventDate').value = event.date;
    document.getElementById('eventTime').value = event.time || '';
    document.getElementById('eventNotes').value = event.notes || '';
    
    const type = event.type || 'event';
    document.getElementById('eventType').value = type;
    
    const roomGroup = document.getElementById('roomGroup');
    const userGroup = document.getElementById('userGroup');
    const locationGroup = document.getElementById('locationGroup');
    const shareGroup = document.getElementById('shareGroup');
    
    roomGroup.style.display = 'none';
    userGroup.style.display = 'none';
    locationGroup.style.display = 'none';
    shareGroup.style.display = hasSpouse ? 'block' : 'none';
    
    if (type === 'event') {
      document.getElementById('modalTitle').textContent = window.T.aktiwiteit;
      roomGroup.style.display = 'block';
      document.getElementById('eventRoom').value = event.room_id || '';
    } else if (type === 'visit') {
      document.getElementById('modalTitle').textContent = window.T.afspraak;
      userGroup.style.display = 'block';
      locationGroup.style.display = 'block';
      
      if (event.linked_user_id) {
        toggleBtns.forEach(b => b.classList.toggle('active', b.dataset.mode === 'user'));
        togglePersonMode('user');
        document.getElementById('linkedUser').value = event.linked_user_id;
      } else {
        toggleBtns.forEach(b => b.classList.toggle('active', b.dataset.mode === 'text'));
        togglePersonMode('text');
        document.getElementById('personName').value = event.person_name || '';
      }
      document.getElementById('eventLocation').value = event.location || '';
    } else if (type === 'diary') {
      openDiary(event.date, event.time);
      return;
    }
    
    // Show delete if can delete
    if (canDeleteEvent(event)) {
      deleteBtn.style.display = 'inline-block';
    } else {
      deleteBtn.style.display = 'none';
    }
    
    eventModal.style.display = 'flex';
  }

  // Can Delete Event
  function canDeleteEvent(event) {
    const ampId = window.USER_AMP_ID;
    const type = event.type || 'event';
    
    if (type === 'diary') return false;
    if (ampId === 10) return false;
    
    if (ampId >= 1 && ampId <= 5) {
      if (event.room_type === 'opsienerskap' && event.room_town_id == window.USER_TOWN) {
        return true;
      }
    }
    
    if (ampId >= 5 && ampId <= 9) {
      if (event.room_type === 'gemeente' && event.room_gemeente_id == window.USER_CONG) {
        return true;
      }
    }
    
    if (ampId < 10 && (event.room_type === 'jeug' || event.room_type === 'sondagskool')) {
      if (event.room_town_id == window.USER_TOWN) {
        return true;
      }
    }
    
    if (event.user_id == window.USER_ID) return true;
    
    return false;
  }

  // Hide Modal
  function hideModal() {
    eventModal.style.display = 'none';
    eventForm.reset();
    currentEvent = null;
  }

  // Toggle Person Mode
  function togglePersonMode(mode) {
    const userSelect = document.getElementById('userSelect');
    const textInput = document.getElementById('userTextInput');
    
    if (mode === 'user') {
      userSelect.style.display = 'block';
      textInput.style.display = 'none';
      document.getElementById('personName').value = '';
    } else {
      userSelect.style.display = 'none';
      textInput.style.display = 'block';
      document.getElementById('linkedUser').value = '';
    }
  }

  // Handle Submit
  async function handleSubmit(e) {
    e.preventDefault();
    const formData = new FormData(eventForm);

    try {
      const res = await fetch('/calendar/api/upsert.php', {
        method: 'POST',
        body: formData
      });
      const data = await res.json();
      if (data.success) {
        hideModal();
        loadEvents();
        showToast(window.T.saved, 'success');
      } else {
        showToast(data.error || window.T.error, 'error');
      }
    } catch (error) {
      showToast(window.T.error, 'error');
    }
  }

  // Handle Delete
  async function handleDelete() {
    const eventId = document.getElementById('eventId').value;
    const eventType = document.getElementById('eventType').value;

    if (!eventId) return;
    if (!confirm(window.T.confirmDelete)) return;

    try {
      const res = await fetch('/calendar/api/delete.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `id=${eventId}&type=${eventType}`
      });
      const data = await res.json();
      if (data.success) {
        hideModal();
        loadEvents();
        showToast(window.T.deleted, 'success');
      } else {
        showToast(data.error || window.T.error, 'error');
      }
    } catch (error) {
      showToast(window.T.error, 'error');
    }
  }

  // Show Toast
  function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    
    const toast = document.createElement('div');
    toast.className = `cal-toast cal-toast-${type}`;
    toast.textContent = message;
    
    container.appendChild(toast);
    
    setTimeout(() => toast.classList.add('show'), 10);
    
    setTimeout(() => {
      toast.classList.remove('show');
      setTimeout(() => toast.remove(), 300);
    }, 3000);
  }

  // Start
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();