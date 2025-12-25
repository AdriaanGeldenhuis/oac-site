(function() {
  'use strict';

  const photoInput = document.getElementById('photo');
  const avatarPreview = document.getElementById('avatar-preview');
  const avatarWrapper = document.querySelector('.account-avatar-wrapper');

  if (photoInput && avatarPreview && avatarWrapper) {
    avatarWrapper.addEventListener('click', function() {
      photoInput.click();
    });

    photoInput.addEventListener('change', function(e) {
      const file = e.target.files && e.target.files[0];
      if (file && file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = function(ev) {
          avatarPreview.src = ev.target.result;
        };
        reader.readAsDataURL(file);
      }
    });
  }

})();