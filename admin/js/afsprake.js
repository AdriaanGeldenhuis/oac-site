(function() {
  'use strict';

  const appointmentModal = document.getElementById('appointmentModal');
  const appointmentForm = document.getElementById('appointmentForm');

  window.openAppointmentModal = function(userId, userName, presetDate, presetTime) {
    document.getElementById('appointmentTargetId').value = userId;
    document.getElementById('appointmentTargetName').textContent = userName;
    
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('appointmentDate').setAttribute('min', today);
    
    if (presetDate) {
      document.getElementById('appointmentDate').value = presetDate;
    }
    
    if (presetTime) {
      document.getElementById('appointmentTime').value = presetTime;
    }
    
    appointmentModal.style.display = 'flex';
  };

  window.closeAppointmentModal = function() {
    appointmentModal.style.display = 'none';
    appointmentForm.reset();
  };

  if (appointmentForm) {
    appointmentForm.addEventListener('submit', async function(e) {
      e.preventDefault();
      
      const formData = new FormData(appointmentForm);
      
      try {
        const response = await fetch('/admin/api/ampte/appointment.php', {
          method: 'POST',
          body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
          alert('Appointment request sent / Afspraak versoek gestuur');
          closeAppointmentModal();
          setTimeout(() => location.href = '/admin/index.php?tab=afsprake', 1000);
        } else {
          alert(data.error || 'Error');
        }
      } catch (error) {
        alert('Network error / Netwerkfout');
        console.error('Appointment error:', error);
      }
    });
  }

  document.querySelectorAll('.btn-make-appointment').forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.stopPropagation();
      const userId = this.getAttribute('data-id');
      const userName = this.getAttribute('data-name');
      openAppointmentModal(userId, userName, '', '');
    });
  });

  const searchInput = document.getElementById('afsprakeSearch');
  if (searchInput) {
    searchInput.addEventListener('input', function(e) {
      const query = e.target.value.toLowerCase().trim();
      const cards = document.querySelectorAll('.afsprake-card-person');
      
      cards.forEach(card => {
        const name = card.getAttribute('data-name');
        if (name && name.includes(query)) {
          card.style.display = '';
        } else {
          card.style.display = 'none';
        }
      });
      
      document.querySelectorAll('.afsprake-group').forEach(group => {
        const visibleCards = group.querySelectorAll('.afsprake-card-person:not([style*="display: none"])');
        if (visibleCards.length === 0) {
          group.style.display = 'none';
        } else {
          group.style.display = '';
        }
      });
    });
  }

  document.querySelectorAll('.confirm-appointment').forEach(btn => {
    btn.addEventListener('click', async function() {
      const id = this.getAttribute('data-id');
      
      try {
        const response = await fetch('/admin/api/ampte/confirm_appointment.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: `appointment_id=${id}`
        });
        
        const data = await response.json();
        
        if (data.success) {
          alert('Appointment confirmed / Afspraak bevestig');
          setTimeout(() => location.reload(), 1000);
        } else {
          alert(data.error || 'Error');
        }
      } catch (error) {
        alert('Network error / Netwerkfout');
      }
    });
  });

  document.querySelectorAll('.cancel-appointment').forEach(btn => {
    btn.addEventListener('click', async function() {
      const id = this.getAttribute('data-id');
      
      if (!confirm('Cancel this appointment? / Kanselleer hierdie afspraak?')) return;
      
      try {
        const response = await fetch('/admin/api/ampte/cancel_appointment.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: `appointment_id=${id}`
        });
        
        const data = await response.json();
        
        if (data.success) {
          alert('Appointment cancelled / Afspraak gekanselleer');
          setTimeout(() => location.reload(), 1000);
        } else {
          alert(data.error || 'Error');
        }
      } catch (error) {
        alert('Network error / Netwerkfout');
      }
    });
  });

  if (appointmentModal) {
    appointmentModal.addEventListener('click', function(e) {
      if (e.target.classList.contains('afsprake-modal-overlay')) {
        closeAppointmentModal();
      }
    });
  }

})();