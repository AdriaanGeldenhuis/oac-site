(function() {
  'use strict';

  const photoInput = document.getElementById('photo');
  const avatarPreview = document.getElementById('avatar-preview');
  const avatarWrapper = document.querySelector('.account-avatar-wrapper');

  // Cropper state
  let cropperModal = null;
  let cropperCanvas = null;
  let cropperCtx = null;
  let originalImage = null;
  let imageScale = 1;
  let imageX = 0;
  let imageY = 0;
  let isDragging = false;
  let dragStartX = 0;
  let dragStartY = 0;
  let lastImageX = 0;
  let lastImageY = 0;
  let croppedDataUrl = null;

  if (photoInput && avatarPreview && avatarWrapper) {
    avatarWrapper.addEventListener('click', function() {
      photoInput.click();
    });

    photoInput.addEventListener('change', function(e) {
      const file = e.target.files && e.target.files[0];
      if (file && file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = function(ev) {
          openCropper(ev.target.result);
        };
        reader.readAsDataURL(file);
      }
    });
  }

  function openCropper(imageSrc) {
    // Create modal if not exists
    if (!cropperModal) {
      createCropperModal();
    }

    // Load image
    originalImage = new Image();
    originalImage.onload = function() {
      // Reset state
      imageScale = 1;
      imageX = 0;
      imageY = 0;

      // Calculate initial scale to fit image
      const canvasSize = cropperCanvas.width;
      const minDim = Math.min(originalImage.width, originalImage.height);
      imageScale = canvasSize / minDim;

      // Center the image
      imageX = (canvasSize - originalImage.width * imageScale) / 2;
      imageY = (canvasSize - originalImage.height * imageScale) / 2;

      // Update zoom slider
      const zoomSlider = document.getElementById('cropper-zoom');
      if (zoomSlider) {
        zoomSlider.value = 100;
      }

      drawCropper();
      cropperModal.classList.add('show');
      document.body.style.overflow = 'hidden';
    };
    originalImage.src = imageSrc;
  }

  function createCropperModal() {
    cropperModal = document.createElement('div');
    cropperModal.className = 'cropper-modal';
    cropperModal.innerHTML = `
      <div class="cropper-overlay"></div>
      <div class="cropper-content">
        <div class="cropper-header">
          <h3>Posisioneer jou foto</h3>
          <button type="button" class="cropper-close">&times;</button>
        </div>
        <div class="cropper-body">
          <div class="cropper-canvas-wrapper">
            <canvas id="cropper-canvas" width="400" height="400"></canvas>
            <div class="cropper-ring"></div>
          </div>
          <div class="cropper-controls">
            <div class="cropper-zoom-control">
              <button type="button" class="cropper-zoom-btn" data-zoom="out">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2"/>
                  <path d="M21 21l-4.35-4.35M8 11h6" stroke="currentColor" stroke-width="2"/>
                </svg>
              </button>
              <input type="range" id="cropper-zoom" min="50" max="300" value="100" class="cropper-slider">
              <button type="button" class="cropper-zoom-btn" data-zoom="in">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2"/>
                  <path d="M21 21l-4.35-4.35M11 8v6M8 11h6" stroke="currentColor" stroke-width="2"/>
                </svg>
              </button>
            </div>
            <p class="cropper-hint">Sleep om te skuif • Gebruik slider om te zoom</p>
          </div>
        </div>
        <div class="cropper-actions">
          <button type="button" class="cropper-btn cropper-btn-cancel">Kanselleer</button>
          <button type="button" class="cropper-btn cropper-btn-save">Stoor</button>
        </div>
      </div>
    `;

    document.body.appendChild(cropperModal);

    // Get canvas
    cropperCanvas = document.getElementById('cropper-canvas');
    cropperCtx = cropperCanvas.getContext('2d');

    // Bind events
    const closeBtn = cropperModal.querySelector('.cropper-close');
    const cancelBtn = cropperModal.querySelector('.cropper-btn-cancel');
    const saveBtn = cropperModal.querySelector('.cropper-btn-save');
    const overlay = cropperModal.querySelector('.cropper-overlay');
    const zoomSlider = document.getElementById('cropper-zoom');
    const zoomBtns = cropperModal.querySelectorAll('.cropper-zoom-btn');

    closeBtn.addEventListener('click', closeCropper);
    cancelBtn.addEventListener('click', closeCropper);
    overlay.addEventListener('click', closeCropper);
    saveBtn.addEventListener('click', saveCrop);

    // Zoom slider
    zoomSlider.addEventListener('input', function() {
      handleZoom(this.value / 100);
    });

    // Zoom buttons
    zoomBtns.forEach(btn => {
      btn.addEventListener('click', function() {
        const dir = this.dataset.zoom;
        let newVal = parseInt(zoomSlider.value);
        if (dir === 'in') {
          newVal = Math.min(300, newVal + 20);
        } else {
          newVal = Math.max(50, newVal - 20);
        }
        zoomSlider.value = newVal;
        handleZoom(newVal / 100);
      });
    });

    // Canvas drag events
    cropperCanvas.addEventListener('mousedown', startDrag);
    cropperCanvas.addEventListener('mousemove', doDrag);
    cropperCanvas.addEventListener('mouseup', endDrag);
    cropperCanvas.addEventListener('mouseleave', endDrag);

    // Touch events for mobile
    cropperCanvas.addEventListener('touchstart', startDragTouch, { passive: false });
    cropperCanvas.addEventListener('touchmove', doDragTouch, { passive: false });
    cropperCanvas.addEventListener('touchend', endDrag);

    // Scroll wheel zoom
    cropperCanvas.addEventListener('wheel', function(e) {
      e.preventDefault();
      const delta = e.deltaY > 0 ? -10 : 10;
      let newVal = parseInt(zoomSlider.value) + delta;
      newVal = Math.max(50, Math.min(300, newVal));
      zoomSlider.value = newVal;
      handleZoom(newVal / 100);
    }, { passive: false });
  }

  function handleZoom(scaleFactor) {
    if (!originalImage) return;

    const canvasSize = cropperCanvas.width;
    const minDim = Math.min(originalImage.width, originalImage.height);
    const baseScale = canvasSize / minDim;

    // Get center of canvas
    const centerX = canvasSize / 2;
    const centerY = canvasSize / 2;

    // Calculate position relative to center
    const oldScale = imageScale;
    const newScale = baseScale * scaleFactor;

    // Adjust position to zoom toward center
    imageX = centerX - (centerX - imageX) * (newScale / oldScale);
    imageY = centerY - (centerY - imageY) * (newScale / oldScale);

    imageScale = newScale;
    drawCropper();
  }

  function startDrag(e) {
    isDragging = true;
    dragStartX = e.clientX;
    dragStartY = e.clientY;
    lastImageX = imageX;
    lastImageY = imageY;
    cropperCanvas.style.cursor = 'grabbing';
  }

  function startDragTouch(e) {
    e.preventDefault();
    if (e.touches.length === 1) {
      isDragging = true;
      dragStartX = e.touches[0].clientX;
      dragStartY = e.touches[0].clientY;
      lastImageX = imageX;
      lastImageY = imageY;
    }
  }

  function doDrag(e) {
    if (!isDragging) return;
    const dx = e.clientX - dragStartX;
    const dy = e.clientY - dragStartY;
    imageX = lastImageX + dx;
    imageY = lastImageY + dy;
    drawCropper();
  }

  function doDragTouch(e) {
    e.preventDefault();
    if (!isDragging || e.touches.length !== 1) return;
    const dx = e.touches[0].clientX - dragStartX;
    const dy = e.touches[0].clientY - dragStartY;
    imageX = lastImageX + dx;
    imageY = lastImageY + dy;
    drawCropper();
  }

  function endDrag() {
    isDragging = false;
    if (cropperCanvas) {
      cropperCanvas.style.cursor = 'grab';
    }
  }

  function drawCropper() {
    if (!cropperCtx || !originalImage) return;

    const canvas = cropperCanvas;
    const ctx = cropperCtx;
    const size = canvas.width;

    // Clear canvas
    ctx.clearRect(0, 0, size, size);

    // Fill background
    ctx.fillStyle = '#1a1a1a';
    ctx.fillRect(0, 0, size, size);

    // Draw image
    ctx.drawImage(
      originalImage,
      imageX,
      imageY,
      originalImage.width * imageScale,
      originalImage.height * imageScale
    );

    // Draw darkened overlay outside circle
    ctx.fillStyle = 'rgba(0, 0, 0, 0.6)';
    ctx.beginPath();
    ctx.rect(0, 0, size, size);
    ctx.arc(size / 2, size / 2, size / 2 - 20, 0, Math.PI * 2, true);
    ctx.fill();
  }

  function saveCrop() {
    if (!originalImage) return;

    // Create output canvas
    const outputSize = 600;
    const outputCanvas = document.createElement('canvas');
    outputCanvas.width = outputSize;
    outputCanvas.height = outputSize;
    const outputCtx = outputCanvas.getContext('2d');

    // Calculate scale factor from preview to output
    const previewSize = cropperCanvas.width;
    const scaleFactor = outputSize / previewSize;

    // Calculate the crop region
    const cropRadius = (previewSize / 2 - 20);
    const centerX = previewSize / 2;
    const centerY = previewSize / 2;

    // Create circular clip on output canvas
    outputCtx.beginPath();
    outputCtx.arc(outputSize / 2, outputSize / 2, outputSize / 2, 0, Math.PI * 2);
    outputCtx.closePath();
    outputCtx.clip();

    // Draw the image portion that's inside the circle
    outputCtx.drawImage(
      originalImage,
      imageX * scaleFactor + (outputSize - previewSize * scaleFactor) / 2,
      imageY * scaleFactor + (outputSize - previewSize * scaleFactor) / 2,
      originalImage.width * imageScale * scaleFactor,
      originalImage.height * imageScale * scaleFactor
    );

    // Get data URL
    croppedDataUrl = outputCanvas.toDataURL('image/jpeg', 0.92);

    // Update preview
    if (avatarPreview) {
      avatarPreview.src = croppedDataUrl;
    }

    // Store in hidden field
    let hiddenInput = document.getElementById('cropped-photo-data');
    if (!hiddenInput) {
      hiddenInput = document.createElement('input');
      hiddenInput.type = 'hidden';
      hiddenInput.name = 'cropped_photo_data';
      hiddenInput.id = 'cropped-photo-data';
      document.querySelector('.account-form').appendChild(hiddenInput);
    }
    hiddenInput.value = croppedDataUrl;

    closeCropper();
  }

  function closeCropper() {
    if (cropperModal) {
      cropperModal.classList.remove('show');
      document.body.style.overflow = '';
    }
    // Clear file input if cancelled without saving
    if (!croppedDataUrl && photoInput) {
      photoInput.value = '';
    }
  }

})();
