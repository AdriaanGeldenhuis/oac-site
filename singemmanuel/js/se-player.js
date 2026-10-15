// =====================================================================
// Sing Emmanuel - Orrel-speler (MIDI) vir ampte 0-8
// Lees die liedjie se MIDI-lêer en speel dit met 'n Web Audio orrelklank.
// =====================================================================

(function() {
  'use strict';

  const openBtn = document.getElementById('openPlayer');
  const overlay = document.getElementById('playerOverlay');
  if (!openBtn || !overlay) return;

  const closeBtn = document.getElementById('closePlayer');
  const playBtn = document.getElementById('playerToggle');
  const statusEl = document.getElementById('playerStatus');
  const barEl = document.getElementById('playerBar');
  const timeEl = document.getElementById('playerTime');
  const song = openBtn.getAttribute('data-song');

  const LABEL_PLAY = playBtn.getAttribute('data-label-play');
  const LABEL_PAUSE = playBtn.getAttribute('data-label-pause');
  const TXT_LOADING = statusEl.getAttribute('data-loading');
  const TXT_ERROR = statusEl.getAttribute('data-error');

  let parsed = null;       // { notes: [...], duration }
  let ctx = null;          // AudioContext vir huidige speel-sessie
  let master = null;
  let organWave = null;
  let songStart = 0;       // ctx.currentTime waar die lied begin het
  let nextIdx = 0;         // volgende noot om te skeduleer
  let timer = null;
  let playing = false;
  let wakeLock = null;

  // ===== MIDI PARSER =====

  function parseMidi(buf) {
    const d = new DataView(buf);
    let p = 0;
    const str = (n) => { let s = ''; for (let i = 0; i < n; i++) s += String.fromCharCode(d.getUint8(p + i)); return s; };
    const vlq = () => { let v = 0, b; do { b = d.getUint8(p++); v = (v << 7) | (b & 0x7f); } while (b & 0x80); return v; };

    if (str(4) !== 'MThd') throw new Error('not midi');
    const hLen = d.getUint32(p + 4);
    const nTracks = d.getUint16(p + 10);
    const div = d.getUint16(p + 12);
    p += 8 + hLen;

    const tempos = [];   // { tick, uspq }
    const raw = [];      // { tick, on, ch, note, vel }

    for (let t = 0; t < nTracks && p < d.byteLength; t++) {
      if (str(4) !== 'MTrk') break;
      const end = p + 8 + d.getUint32(p + 4);
      p += 8;
      let tick = 0, status = 0;
      while (p < end) {
        tick += vlq();
        const b = d.getUint8(p);
        if (b === 0xff) {
          p++;
          const type = d.getUint8(p++);
          const len = vlq();
          if (type === 0x51 && len === 3) {
            tempos.push({ tick, uspq: (d.getUint8(p) << 16) | (d.getUint8(p + 1) << 8) | d.getUint8(p + 2) });
          }
          p += len;
          if (type === 0x2f) break;
        } else if (b === 0xf0 || b === 0xf7) {
          p++;
          p += vlq();
        } else {
          if (b & 0x80) { status = b; p++; } // anders: running status
          if (!status) break;
          const kind = status & 0xf0, ch = status & 0x0f;
          if (kind === 0xc0 || kind === 0xd0) { p += 1; continue; }
          const a = d.getUint8(p++), v = d.getUint8(p++);
          if (kind === 0x90 || kind === 0x80) {
            raw.push({ tick, on: kind === 0x90 && v > 0, ch, note: a, vel: v });
          }
        }
      }
      p = end;
    }

    // Tick -> sekondes via die tempo-kaart
    let toSec;
    if (div & 0x8000) {
      const fps = 256 - (div >> 8), tpf = div & 0xff;
      toSec = (tick) => tick / (fps * tpf);
    } else {
      tempos.sort((x, y) => x.tick - y.tick);
      if (!tempos.length || tempos[0].tick > 0) tempos.unshift({ tick: 0, uspq: 500000 });
      let acc = 0;
      tempos.forEach((tp, i) => {
        if (i > 0) acc += (tp.tick - tempos[i - 1].tick) * tempos[i - 1].uspq / 1e6 / div;
        tp.sec = acc;
      });
      toSec = (tick) => {
        let i = tempos.length - 1;
        while (i > 0 && tempos[i].tick > tick) i--;
        return tempos[i].sec + (tick - tempos[i].tick) * tempos[i].uspq / 1e6 / div;
      };
    }

    // Koppel note-on aan note-off
    raw.sort((x, y) => x.tick - y.tick || (x.on ? 1 : 0) - (y.on ? 1 : 0));
    const open = {};
    const notes = [];
    raw.forEach((e) => {
      if (e.ch === 9) return; // perkussie-kanaal
      const key = e.ch * 128 + e.note;
      if (e.on) {
        (open[key] = open[key] || []).push(e);
      } else if (open[key] && open[key].length) {
        const s = open[key].shift();
        notes.push({ note: e.note, vel: s.vel, start: toSec(s.tick), end: toSec(e.tick) });
      }
    });
    notes.sort((x, y) => x.start - y.start);
    const duration = notes.reduce((m, n) => Math.max(m, n.end), 0);
    return { notes, duration };
  }

  // ===== ORREL-KLANK =====

  function makeOrganWave(ac) {
    // Boontone soos 'n pyporrel: grondtoon, oktaaf, kwint, dubbel-oktaaf
    const partials = [0, 1, 0.55, 0.3, 0.22, 0.08, 0.1, 0, 0.05];
    const real = new Float32Array(partials.length);
    const imag = new Float32Array(partials);
    return ac.createPeriodicWave(real, imag);
  }

  function scheduleNote(n) {
    const t0 = songStart + n.start;
    const t1 = Math.max(songStart + n.end, t0 + 0.05);
    const osc = ctx.createOscillator();
    const g = ctx.createGain();
    const level = 0.12 * (0.4 + 0.6 * n.vel / 127);
    osc.setPeriodicWave(organWave);
    osc.frequency.value = 440 * Math.pow(2, (n.note - 69) / 12);
    g.gain.setValueAtTime(0, t0);
    g.gain.linearRampToValueAtTime(level, t0 + 0.03);
    g.gain.setValueAtTime(level, t1);
    g.gain.setTargetAtTime(0, t1, 0.06);
    osc.connect(g).connect(master);
    osc.start(t0);
    osc.stop(t1 + 0.5);
  }

  function tick() {
    if (!ctx) return;
    const now = ctx.currentTime;
    const notes = parsed.notes;
    while (nextIdx < notes.length && songStart + notes[nextIdx].start < now + 0.4) {
      scheduleNote(notes[nextIdx++]);
    }
    const pos = Math.max(0, now - songStart);
    updateProgress(pos);
    if (pos > parsed.duration + 0.6) {
      stop();
    }
  }

  // ===== SPEEL-BEHEER =====

  function startFresh() {
    const AC = window.AudioContext || window.webkitAudioContext;
    ctx = new AC();
    organWave = makeOrganWave(ctx);
    const comp = ctx.createDynamicsCompressor();
    master = ctx.createGain();
    master.gain.value = 0.9;
    master.connect(comp).connect(ctx.destination);
    songStart = ctx.currentTime + 0.15;
    nextIdx = 0;
    timer = setInterval(tick, 50);
    tick();
  }

  function stop() {
    if (timer) { clearInterval(timer); timer = null; }
    if (ctx) { ctx.close().catch(() => {}); ctx = null; }
    playing = false;
    releaseWakeLock();
    updateProgress(0);
    setButton();
  }

  async function toggle() {
    if (!parsed) {
      try {
        await load();
      } catch (e) {
        statusEl.textContent = TXT_ERROR;
        return;
      }
    }
    if (!ctx) {
      startFresh();
      playing = true;
    } else if (playing) {
      await ctx.suspend();
      playing = false;
    } else {
      await ctx.resume();
      playing = true;
    }
    if (playing) requestWakeLock(); else releaseWakeLock();
    setButton();
  }

  async function load() {
    statusEl.textContent = TXT_LOADING;
    playBtn.disabled = true;
    try {
      const res = await fetch('/singemmanuel/api/midi.php?song=' + encodeURIComponent(song), { credentials: 'same-origin' });
      if (!res.ok) throw new Error('http ' + res.status);
      parsed = parseMidi(await res.arrayBuffer());
      if (!parsed.notes.length) throw new Error('empty');
      statusEl.textContent = '';
      updateProgress(0);
    } finally {
      playBtn.disabled = false;
    }
  }

  // ===== UI =====

  function fmt(s) {
    s = Math.max(0, Math.floor(s));
    return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
  }

  function updateProgress(pos) {
    if (!parsed) return;
    const dur = parsed.duration || 1;
    const pos2 = Math.min(pos, dur);
    barEl.style.width = (pos2 / dur * 100) + '%';
    timeEl.textContent = fmt(pos2) + ' / ' + fmt(dur);
  }

  function setButton() {
    playBtn.classList.toggle('is-playing', playing);
    playBtn.setAttribute('aria-label', playing ? LABEL_PAUSE : LABEL_PLAY);
    playBtn.setAttribute('aria-pressed', playing ? 'true' : 'false');
  }

  async function requestWakeLock() {
    try {
      if ('wakeLock' in navigator && !wakeLock) {
        wakeLock = await navigator.wakeLock.request('screen');
        wakeLock.addEventListener('release', () => { wakeLock = null; });
      }
    } catch (e) { /* nie ondersteun nie */ }
  }

  function releaseWakeLock() {
    if (wakeLock) { wakeLock.release().catch(() => {}); wakeLock = null; }
  }

  function openPlayer() {
    overlay.hidden = false;
    document.body.style.overflow = 'hidden';
    playBtn.focus();
  }

  function closePlayer() {
    stop();
    overlay.hidden = true;
    document.body.style.overflow = '';
    openBtn.focus();
  }

  openBtn.addEventListener('click', function(e) {
    e.preventDefault();
    openPlayer();
  });

  closeBtn.addEventListener('click', function(e) {
    e.preventDefault();
    closePlayer();
  });

  overlay.addEventListener('click', function(e) {
    if (e.target.classList.contains('se-overlay-backdrop')) closePlayer();
  });

  playBtn.addEventListener('click', function(e) {
    e.preventDefault();
    toggle();
  });

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && !overlay.hidden) closePlayer();
  });

  // Hou die skerm aan wanneer die gebruiker terugkeer en dit nog speel
  document.addEventListener('visibilitychange', function() {
    if (document.visibilityState === 'visible' && playing) requestWakeLock();
  });

  window.addEventListener('pagehide', stop);
})();
