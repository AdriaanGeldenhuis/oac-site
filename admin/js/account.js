/**
 * Edit profile: the photo cropper, the province > town > congregation
 * selects, the answer to a spouse request, and a guard against saving twice
 * or leaving with unsaved changes. The words come from window.OAC_ACCOUNT
 * (account.php) in the member's language.
 */
(function () {
  'use strict';

  const CFG = window.OAC_ACCOUNT || {};
  const T = CFG.t || {};
  const form = document.getElementById('accountForm');
  if (!form) return;

  // ===== Province > town > congregation =====
  const provSel = document.getElementById('province');
  const townSel = document.getElementById('town');
  const congSel = document.getElementById('congregation');

  function fill(select, rows, placeholder, enabled) {
    select.replaceChildren();
    const first = document.createElement('option');
    first.value = '';
    first.textContent = placeholder;
    select.appendChild(first);
    (rows || []).forEach(function (row) {
      const o = document.createElement('option');
      o.value = row.id;
      o.textContent = row.name;
      select.appendChild(o);
    });
    select.disabled = !enabled;
  }

  provSel.addEventListener('change', function () {
    const pid = provSel.value;
    fill(townSel, pid ? (CFG.townsByProvince || {})[pid] : [], pid ? T.select : T.provFirst, !!pid);
    fill(congSel, [], T.townFirst, false);
  });

  townSel.addEventListener('change', function () {
    const tid = townSel.value;
    fill(congSel, tid ? (CFG.congsByTown || {})[tid] : [], tid ? T.select : T.townFirst, !!tid);
  });

  // ===== A spouse request that involves me =====
  document.querySelectorAll('[data-spouse]').forEach(function (btn) {
    btn.addEventListener('click', async function () {
      const action = btn.getAttribute('data-spouse');
      const row = btn.closest('.account-notice-actions');
      const all = row ? row.querySelectorAll('button') : [btn];
      all.forEach(function (b) { b.disabled = true; });

      const data = await OACUI.post('/admin/api/spouse/' + action + '.php', { request_id: btn.getAttribute('data-id') });
      if (data.success) {
        location.reload();
      } else {
        OACUI.toast(data.error || '', 'error');
        all.forEach(function (b) { b.disabled = false; });
      }
    });
  });

  // ===== Leaving with unsaved changes, and saving twice =====
  let dirty = false;
  let submitting = false;
  form.addEventListener('input', function () { dirty = true; });
  form.addEventListener('change', function () { dirty = true; });
  form.addEventListener('submit', function (e) {
    if (submitting) {
      e.preventDefault();
      return;
    }
    if (!form.checkValidity()) {
      // let the browser point at the first problem
      e.preventDefault();
      form.reportValidity();
      return;
    }
    submitting = true;
    const btn = document.getElementById('accountSave');
    btn.disabled = true;
    btn.classList.add('is-busy');
  });
  window.addEventListener('beforeunload', function (e) {
    if (dirty && !submitting) {
      e.preventDefault();
      e.returnValue = '';
    }
  });

  // ===== Photo cropper =====
  const photoInput = document.getElementById('photo');
  const pick = document.getElementById('avatarPick');
  const preview = document.getElementById('avatar-preview');
  const initials = document.getElementById('avatar-initials');
  if (!photoInput || !pick) return;

  const SIZE = 400;       // the canvas, in its own pixels
  const MARGIN = 20;      // the ring sits this far inside the canvas
  const OUT = 600;        // the saved picture
  let modal = null;
  let canvas = null;
  let ctx = null;
  let img = null;
  let scale = 1;
  let baseScale = 1;
  let ox = 0;
  let oy = 0;
  let drag = null;
  let lastFocus = null;

  pick.addEventListener('click', function () { photoInput.click(); });

  photoInput.addEventListener('change', function () {
    const file = photoInput.files && photoInput.files[0];
    if (!file) return;
    if (!/^image\/(jpeg|png|webp)$/.test(file.type)) {
      photoInput.value = '';
      return;
    }
    const reader = new FileReader();
    reader.onload = function (ev) { openCropper(ev.target.result); };
    reader.readAsDataURL(file);
  });

  function build() {
    modal = document.createElement('div');
    modal.className = 'cropper-modal';
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-labelledby', 'cropperTitle');
    modal.innerHTML =
      '<div class="cropper-content">' +
        '<div class="cropper-header"><h3 id="cropperTitle"></h3>' +
          '<button type="button" class="cropper-close"></button></div>' +
        '<div class="cropper-canvas-wrapper"><canvas width="' + SIZE + '" height="' + SIZE + '"></canvas></div>' +
        '<div class="cropper-zoom-control">' +
          '<button type="button" class="cropper-zoom-btn" data-zoom="out"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35M8 11h6"/></svg></button>' +
          '<input type="range" class="cropper-slider" min="100" max="400" value="100" step="1">' +
          '<button type="button" class="cropper-zoom-btn" data-zoom="in"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35M11 8v6M8 11h6"/></svg></button>' +
        '</div>' +
        '<p class="cropper-hint"></p>' +
        '<div class="cropper-actions">' +
          '<button type="button" class="admin-btn admin-btn-secondary cropper-cancel"></button>' +
          '<button type="button" class="admin-btn admin-btn-primary cropper-save"></button>' +
        '</div>' +
      '</div>';
    document.body.appendChild(modal);

    modal.querySelector('#cropperTitle').textContent = T.cropTitle || '';
    modal.querySelector('.cropper-hint').textContent = T.cropHint || '';
    modal.querySelector('.cropper-cancel').textContent = T.cancel || '';
    modal.querySelector('.cropper-save').textContent = T.save || '';
    const close = modal.querySelector('.cropper-close');
    close.textContent = '×';
    close.setAttribute('aria-label', T.close || '');
    modal.querySelector('[data-zoom="in"]').setAttribute('aria-label', T.zoomIn || '');
    modal.querySelector('[data-zoom="out"]').setAttribute('aria-label', T.zoomOut || '');
    modal.querySelector('.cropper-slider').setAttribute('aria-label', T.zoomIn || '');

    canvas = modal.querySelector('canvas');
    ctx = canvas.getContext('2d');
    const slider = modal.querySelector('.cropper-slider');

    slider.addEventListener('input', function () { zoomTo(slider.value / 100); });
    modal.querySelectorAll('.cropper-zoom-btn').forEach(function (b) {
      b.addEventListener('click', function () {
        const step = b.getAttribute('data-zoom') === 'in' ? 20 : -20;
        slider.value = Math.max(100, Math.min(400, Number(slider.value) + step));
        zoomTo(slider.value / 100);
      });
    });

    // Mouse, touch and pen through one set of pointer events
    canvas.addEventListener('pointerdown', function (e) {
      canvas.setPointerCapture(e.pointerId);
      drag = { x: e.clientX, y: e.clientY, ox: ox, oy: oy };
      canvas.classList.add('dragging');
    });
    canvas.addEventListener('pointermove', function (e) {
      if (!drag) return;
      const k = SIZE / canvas.getBoundingClientRect().width; // the canvas may be shown smaller than it is
      ox = drag.ox + (e.clientX - drag.x) * k;
      oy = drag.oy + (e.clientY - drag.y) * k;
      clamp();
      draw();
    });
    const end = function () { drag = null; canvas.classList.remove('dragging'); };
    canvas.addEventListener('pointerup', end);
    canvas.addEventListener('pointercancel', end);
    canvas.addEventListener('wheel', function (e) {
      e.preventDefault();
      slider.value = Math.max(100, Math.min(400, Number(slider.value) + (e.deltaY > 0 ? -10 : 10)));
      zoomTo(slider.value / 100);
    }, { passive: false });

    close.addEventListener('click', closeCropper);
    modal.querySelector('.cropper-cancel').addEventListener('click', closeCropper);
    modal.querySelector('.cropper-save').addEventListener('click', saveCrop);
    modal.addEventListener('click', function (e) { if (e.target === modal) closeCropper(); });
    modal.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        closeCropper();
      } else if (e.key === 'Tab') {
        const f = Array.prototype.filter.call(modal.querySelectorAll('button, input'), function (n) { return !n.disabled; });
        const first = f[0], last = f[f.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
      }
    });
  }

  function openCropper(src) {
    if (!modal) build();
    const image = new Image();
    image.onload = function () {
      img = image;
      // The whole ring is filled by the picture at 100%
      baseScale = (SIZE - 2 * MARGIN) / Math.min(img.width, img.height);
      scale = baseScale;
      ox = (SIZE - img.width * scale) / 2;
      oy = (SIZE - img.height * scale) / 2;
      modal.querySelector('.cropper-slider').value = 100;
      draw();
      lastFocus = document.activeElement;
      modal.classList.add('show');
      document.body.style.overflow = 'hidden';
      modal.querySelector('.cropper-save').focus();
    };
    image.src = src;
  }

  function zoomTo(factor) {
    if (!img) return;
    const next = baseScale * factor;
    const c = SIZE / 2;
    ox = c - (c - ox) * (next / scale);
    oy = c - (c - oy) * (next / scale);
    scale = next;
    clamp();
    draw();
  }

  // The picture always covers the ring
  function clamp() {
    const w = img.width * scale;
    const h = img.height * scale;
    ox = Math.min(MARGIN, Math.max(SIZE - MARGIN - w, ox));
    oy = Math.min(MARGIN, Math.max(SIZE - MARGIN - h, oy));
  }

  function draw() {
    ctx.fillStyle = '#1a1a1a';
    ctx.fillRect(0, 0, SIZE, SIZE);
    ctx.drawImage(img, ox, oy, img.width * scale, img.height * scale);
    // dim everything outside the ring
    ctx.fillStyle = 'rgba(0, 0, 0, 0.6)';
    ctx.beginPath();
    ctx.rect(0, 0, SIZE, SIZE);
    ctx.arc(SIZE / 2, SIZE / 2, SIZE / 2 - MARGIN, 0, Math.PI * 2, true);
    ctx.fill('evenodd');
  }

  function saveCrop() {
    if (!img) return;
    // Exactly the square the ring sits in, so the saved photo is what was shown
    const out = document.createElement('canvas');
    out.width = OUT;
    out.height = OUT;
    const k = OUT / (SIZE - 2 * MARGIN);
    out.getContext('2d').drawImage(img, (ox - MARGIN) * k, (oy - MARGIN) * k, img.width * scale * k, img.height * scale * k);
    const url = out.toDataURL('image/jpeg', 0.92);

    let hidden = document.getElementById('cropped-photo-data');
    if (!hidden) {
      hidden = document.createElement('input');
      hidden.type = 'hidden';
      hidden.name = 'cropped_photo_data';
      hidden.id = 'cropped-photo-data';
      form.appendChild(hidden);
    }
    hidden.value = url;

    preview.src = url;
    preview.hidden = false;
    if (initials) initials.hidden = true;
    photoInput.value = '';          // the cropped picture goes, not the original file
    document.getElementById('photoHint').textContent = T.photoChosen || '';
    dirty = true;
    closeCropper(true);
  }

  function closeCropper(saved) {
    if (!modal) return;
    modal.classList.remove('show');
    document.body.style.overflow = '';
    if (saved !== true) photoInput.value = '';
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }
})();
