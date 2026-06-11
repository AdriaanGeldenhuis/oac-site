/**
 * Rooms Menu Overlay
 * AI Slimbybel styling with Light/Dark Theme Support
 * Shows only rooms user has access to (auto + joined)
 */
(function () {
  'use strict';

  const $ = (q, el = document) => el.querySelector(q);
  const $$ = (q, el = document) => Array.from(el.querySelectorAll(q));
  const ce = (tag, cls, html) => {
    const n = document.createElement(tag);
    if (cls) n.className = cls;
    if (html != null) n.innerHTML = html;
    return n;
  };

  const T = (key) => (window.JS_T && window.JS_T[key]) ? window.JS_T[key] : key;

  function injectCSS() {
    if ($('#roommenu-styles')) return;
    
    const css = ce('style');
    css.id = 'roommenu-styles';
    css.textContent = `
      /* ===== ROOT VARIABLES - DARK THEME (DEFAULT) ===== */
      :root {
        --rm-overlay-bg: rgba(0, 0, 0, 0.7);
        --rm-panel-bg-start: rgba(26, 26, 26, 0.98);
        --rm-panel-bg-end: rgba(42, 42, 42, 0.95);
        --rm-panel-border: rgba(183, 110, 121, 0.4);
        --rm-panel-shadow: -8px 0 32px rgba(0, 0, 0, 0.8), 0 0 20px rgba(183, 110, 121, 0.4);
        
        --rm-border-color: rgba(192, 192, 192, 0.2);
        --rm-rosegold: #f3c3b1;
        --rm-rosegold-glow: rgba(183, 110, 121, 0.4);
        --rm-peach: #f5d5c8;
        --rm-silver: #c0c0c0;
        --rm-silver-dark: #8a8a8a;
        
        --rm-btn-bg-start: #2a2a2a;
        --rm-btn-bg-end: #1a1a1a;
        --rm-btn-border: rgba(192, 192, 192, 0.3);
        
        --rm-item-bg-start: rgba(10, 10, 10, 0.8);
        --rm-item-bg-end: rgba(26, 26, 26, 0.7);
        --rm-item-hover-start: rgba(26, 26, 26, 0.9);
        --rm-item-hover-end: rgba(42, 42, 42, 0.8);
        --rm-item-active-start: rgba(183, 110, 121, 0.2);
        --rm-item-active-end: rgba(183, 110, 121, 0.1);
        
        --rm-scrollbar-track: rgba(26, 26, 26, 0.5);
        --rm-scrollbar-thumb: linear-gradient(180deg, #f3c3b1 0%, #c0c0c0 100%);
      }
      
      /* ===== LIGHT THEME ===== */
      [data-theme="light"] {
        --rm-overlay-bg: rgba(0, 0, 0, 0.3);
        --rm-panel-bg-start: rgba(255, 255, 255, 0.98);
        --rm-panel-bg-end: rgba(248, 248, 248, 0.95);
        --rm-panel-border: rgba(183, 110, 121, 0.5);
        --rm-panel-shadow: -8px 0 32px rgba(0, 0, 0, 0.15), 0 0 20px rgba(183, 110, 121, 0.3);
        
        --rm-border-color: rgba(106, 106, 106, 0.3);
        --rm-rosegold: #c97d8a;
        --rm-rosegold-glow: rgba(183, 110, 121, 0.3);
        --rm-peach: #000000;
        --rm-silver: #2a2a2a;
        --rm-silver-dark: #4a4a4a;
        
        --rm-btn-bg-start: #f0f0f0;
        --rm-btn-bg-end: #e0e0e0;
        --rm-btn-border: rgba(106, 106, 106, 0.4);
        
        --rm-item-bg-start: rgba(245, 245, 245, 0.9);
        --rm-item-bg-end: rgba(240, 240, 240, 0.8);
        --rm-item-hover-start: rgba(240, 240, 240, 0.95);
        --rm-item-hover-end: rgba(235, 235, 235, 0.9);
        --rm-item-active-start: rgba(183, 110, 121, 0.15);
        --rm-item-active-end: rgba(183, 110, 121, 0.08);
        
        --rm-scrollbar-track: rgba(240, 240, 240, 0.8);
        --rm-scrollbar-thumb: linear-gradient(180deg, #c97d8a 0%, #8a8a8a 100%);
      }
      
      /* ===== BASE STYLES ===== */
      #roommenu[hidden]{display:none}
      #roommenu{
        position:fixed;
        inset:0;
        z-index:10000;
        background: var(--rm-overlay-bg);
        animation:fade-in 0.3s ease-out;
        transition: background 0.4s ease;
      }
      
      @keyframes fade-in {
        from { opacity: 0; }
        to { opacity: 1; }
      }
      
      #roommenu .rmx-panel{
        position:absolute;
        top:70px;
        right:0;
        bottom:0;
        width:400px;
        max-width:100%;
        display:grid;
        grid-template-rows:auto 1fr auto;
        background: linear-gradient(135deg, var(--rm-panel-bg-start) 0%, var(--rm-panel-bg-end) 100%);
        border-left: 2px solid var(--rm-panel-border);
        box-shadow: var(--rm-panel-shadow);
        animation: slide-in-right 0.3s ease-out;
        transition: all 0.4s ease;
      }
      
      @keyframes slide-in-right {
        from {
          opacity: 0;
          transform: translateX(100%);
        }
        to {
          opacity: 1;
          transform: translateX(0);
        }
      }
      
      #roommenu .rmx-head{
        display:flex;
        align-items:center;
        justify-content:space-between;
        padding:25px 20px;
        border-bottom: 1px solid var(--rm-border-color);
        transition: border-color 0.4s ease;
      }
      
      #roommenu .rmx-hbubble{
        font-family: 'Parisienne', cursive;
        font-size: 2rem;
        color: var(--rm-rosegold);
        text-shadow: 0 0 10px var(--rm-rosegold-glow);
        transition: color 0.4s ease, text-shadow 0.4s ease;
      }
      
      [data-theme="light"] #roommenu .rmx-hbubble{
        text-shadow: 0 2px 6px var(--rm-rosegold-glow);
      }
      
      #roommenu .rmx-close{
        width: 44px;
        height: 44px;
        border: 1px solid var(--rm-btn-border);
        border-radius: 12px;
        background: linear-gradient(135deg, var(--rm-btn-bg-start) 0%, var(--rm-btn-bg-end) 100%);
        cursor: pointer;
        line-height: 1;
        color: var(--rm-peach);
        font-size: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
        box-shadow: 0 2px 8px rgba(0,0,0,0.4);
      }
      
      [data-theme="light"] #roommenu .rmx-close{
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      }
      
      #roommenu .rmx-close:hover{
        border-color: var(--rm-rosegold);
        box-shadow: 0 4px 16px var(--rm-rosegold-glow);
        transform: scale(1.05);
      }
      
      #roommenu .rmx-list{
        overflow:auto;
        padding: 20px;
      }
      
      #roommenu .rmx-list::-webkit-scrollbar {
        width: 8px;
      }
      
      #roommenu .rmx-list::-webkit-scrollbar-track {
        background: var(--rm-scrollbar-track);
        border-radius: 4px;
        transition: background 0.4s ease;
      }
      
      #roommenu .rmx-list::-webkit-scrollbar-thumb {
        background: var(--rm-scrollbar-thumb);
        border-radius: 4px;
        box-shadow: 0 0 10px var(--rm-rosegold-glow);
        transition: background 0.4s ease;
      }
      
      #roommenu .rmx-section {
        margin-bottom: 25px;
      }
      
      #roommenu .rmx-section-title {
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: var(--rm-rosegold);
        margin-bottom: 12px;
        padding-bottom: 8px;
        border-bottom: 1px solid var(--rm-panel-border);
        font-weight: 600;
        transition: color 0.4s ease, border-color 0.4s ease;
      }
      
      #roommenu .rmx-item{
        position:relative;
        display:flex;
        align-items:center;
        justify-content:space-between;
        margin-bottom: 10px;
        padding: 16px 18px;
        border-radius: 12px;
        background: linear-gradient(135deg, var(--rm-item-bg-start) 0%, var(--rm-item-bg-end) 100%);
        border: 1px solid var(--rm-border-color);
        transition: all 0.3s ease;
        box-shadow: 0 2px 6px rgba(0,0,0,0.3);
      }
      
      [data-theme="light"] #roommenu .rmx-item{
        box-shadow: 0 2px 6px rgba(0,0,0,0.08);
      }
      
      #roommenu .rmx-item:hover{
        background: linear-gradient(135deg, var(--rm-item-hover-start) 0%, var(--rm-item-hover-end) 100%);
        border-color: var(--rm-rosegold);
        box-shadow: 0 4px 12px var(--rm-rosegold-glow);
        transform: translateX(-5px);
      }
      
      #roommenu .rmx-item.active {
        background: linear-gradient(135deg, var(--rm-item-active-start) 0%, var(--rm-item-active-end) 100%);
        border-color: var(--rm-rosegold);
      }
      
      #roommenu .rmx-link{
        flex:1 1 auto;
        min-width:0;
        color: var(--rm-silver);
        text-decoration:none;
        font-size: 15px;
        font-weight: 300;
        transition: color 0.3s ease;
      }
      
      #roommenu .rmx-link:hover{
        color: var(--rm-rosegold);
      }
      
      #roommenu .rmx-badge {
        padding: 4px 10px;
        border-radius: 8px;
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-left: 10px;
        transition: all 0.3s ease;
      }
      
      #roommenu .rmx-badge.auto {
        background: rgba(42, 157, 143, 0.3);
        color: #2a9d8f;
        border: 1px solid #2a9d8f;
      }
      
      [data-theme="light"] #roommenu .rmx-badge.auto {
        background: rgba(42, 157, 143, 0.15);
      }
      
      #roommenu .rmx-badge.joined {
        background: rgba(244, 162, 97, 0.3);
        color: #f4a261;
        border: 1px solid #f4a261;
      }
      
      [data-theme="light"] #roommenu .rmx-badge.joined {
        background: rgba(244, 162, 97, 0.15);
      }
      
      #roommenu .rmx-empty{
        color: var(--rm-silver-dark);
        padding: 40px 20px;
        text-align: center;
        font-size: 15px;
        font-weight: 200;
        transition: color 0.4s ease;
      }
      
      #roommenu .rmx-foot{
        padding: 20px;
        display:flex;
        justify-content:center;
        border-top: 1px solid var(--rm-border-color);
        transition: border-color 0.4s ease;
      }
      
      #roommenu .rmx-chip{
        padding: 12px 28px;
        background: linear-gradient(135deg, var(--rm-btn-bg-start) 0%, var(--rm-btn-bg-end) 100%);
        border: 1px solid var(--rm-btn-border);
        border-radius: 20px;
        color: var(--rm-peach);
        font-size: 14px;
        font-weight: 400;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.3s ease;
        box-shadow: 0 2px 8px rgba(0,0,0,0.4);
        display: inline-flex;
        align-items: center;
        gap: 8px;
      }
      
      [data-theme="light"] #roommenu .rmx-chip{
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      }
      
      #roommenu .rmx-chip:hover{
        border-color: var(--rm-rosegold);
        box-shadow: 0 4px 16px var(--rm-rosegold-glow);
        transform: translateY(-2px);
      }
      
      #roommenu .rmx-chip svg {
        transition: stroke 0.4s ease;
      }
      
      @media (max-width: 768px) {
        #roommenu .rmx-panel {
          width: 100%;
          max-width: 100%;
        }
      }
      
      /* Smooth transitions for all theme changes */
      #roommenu,
      #roommenu *,
      #roommenu *::before,
      #roommenu *::after {
        transition-property: background-color, border-color, color, fill, stroke, opacity, box-shadow, text-shadow;
        transition-duration: 0.4s;
        transition-timing-function: ease;
      }
    `;
    document.head.appendChild(css);
  }

  // Use display_name from API (already translated server-side)
  function labelForRoom(r) {
    // API now provides translated display_name
    if (r.display_name) {
      return r.display_name;
    }
    // Fallback for legacy data
    return r.name || 'Room';
  }

  async function fetchUserRooms() {
    try {
      const r = await fetch('/gospel_media/api/rooms/user_rooms.php', { credentials: 'include' });
      const j = await r.json();
      return j || { auto: [], joined: [] };
    } catch (e) {
      console.error('Failed to load user rooms:', e);
      return { auto: [], joined: [] };
    }
  }

  function ensureOverlay() {
    injectCSS();
    
    let wrap = $('#roommenu');
    if (wrap) return wrap;
    
    wrap = ce('aside');
    wrap.id = 'roommenu';
    wrap.hidden = true;
    
    const panel = ce('div', 'rmx-panel');
    
    const head = ce('div', 'rmx-head');
    const hb = ce('div', 'rmx-hbubble', T('rooms'));
    const close = ce('button', 'rmx-close', '×');
    close.type = 'button';
    head.appendChild(hb);
    head.appendChild(close);
    
    const list = ce('div', 'rmx-list');
    list.id = 'roommenu-list';
    
    const foot = ce('div', 'rmx-foot');
    const manage = ce('a', 'rmx-chip');
    manage.href = '/gospel_media/rooms.php';
    manage.innerHTML = `
      <svg style="width:18px;height:18px;stroke:currentColor;" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M12 4v16m8-8H4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      </svg>
      <span>${T('manage_rooms')}</span>
    `;
    foot.appendChild(manage);
    
    panel.appendChild(head);
    panel.appendChild(list);
    panel.appendChild(foot);
    wrap.appendChild(panel);
    document.body.appendChild(wrap);
    
    close.addEventListener('click', closeOverlay);
    wrap.addEventListener('click', function (e) {
      if (!panel.contains(e.target)) closeOverlay();
    });
    
    return wrap;
  }

  function openOverlay() {
    const wrap = ensureOverlay();
    wrap.hidden = false;
    
    const list = $('#roommenu-list', wrap);
    list.innerHTML = '<div class="rmx-empty">' + T('loading_rooms') + '</div>';

    const currentRoomId = window.CURRENT_ROOM_ID || 0;

    fetchUserRooms().then(function (data) {
      list.innerHTML = '';
      
      const autoRooms = data.auto || [];
      const joinedRooms = data.joined || [];
      
      if (!autoRooms.length && !joinedRooms.length) {
        list.innerHTML = '<div class="rmx-empty">' + T('no_rooms') + '</div>';
        return;
      }
      
      // Auto rooms section
      if (autoRooms.length) {
        const autoSection = ce('div', 'rmx-section');
        const autoTitle = ce('div', 'rmx-section-title', T('automatic'));
        autoSection.appendChild(autoTitle);
        
        autoRooms.forEach(function (r) {
          const row = ce('div', 'rmx-item');
          if (currentRoomId && parseInt(r.id) === parseInt(currentRoomId)) {
            row.classList.add('active');
          }

          const a = ce('a', 'rmx-link');
          a.textContent = labelForRoom(r);
          a.href = '/gospel_media/gospel.php?room_id=' + encodeURIComponent(r.id);

          const badge = ce('span', 'rmx-badge auto', T('auto'));
          
          row.appendChild(a);
          row.appendChild(badge);
          autoSection.appendChild(row);
        });
        
        list.appendChild(autoSection);
      }
      
      // Joined rooms section
      if (joinedRooms.length) {
        const joinedSection = ce('div', 'rmx-section');
        const joinedTitle = ce('div', 'rmx-section-title', T('joined_section'));
        joinedSection.appendChild(joinedTitle);
        
        joinedRooms.forEach(function (r) {
          const row = ce('div', 'rmx-item');
          if (currentRoomId && parseInt(r.id) === parseInt(currentRoomId)) {
            row.classList.add('active');
          }

          const a = ce('a', 'rmx-link');
          a.textContent = labelForRoom(r);
          a.href = '/gospel_media/gospel.php?room_id=' + encodeURIComponent(r.id);

          const badge = ce('span', 'rmx-badge joined', T('joined_badge'));
          
          row.appendChild(a);
          row.appendChild(badge);
          joinedSection.appendChild(row);
        });
        
        list.appendChild(joinedSection);
      }
    });
  }

  function closeOverlay() {
    const wrap = $('#roommenu');
    if (wrap) wrap.hidden = true;
  }

  window.openRoomMenu = openOverlay;
  window.closeRoomMenu = closeOverlay;

  function wire() {
    document.addEventListener('click', function (e) {
      const trg = e.target.closest('#open-rooms-menu, .rooms-menu-btn, [data-roommenu-open]');
      if (trg) {
        e.preventDefault();
        openOverlay();
      }
    });
  }

  if (document.readyState === 'complete' || document.readyState === 'interactive') {
    wire();
  } else {
    document.addEventListener('DOMContentLoaded', wire);
  }
})();