// UNILIS SmartLab — Main JS

document.addEventListener('DOMContentLoaded', () => {

  // ── Auth tabs ──
  document.querySelectorAll('.auth-tab').forEach(tab => {
    tab.addEventListener('click', () => {
      const method = tab.dataset.method;
      document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
      document.querySelectorAll('.auth-method').forEach(m => m.classList.remove('active'));
      tab.classList.add('active');
      const panel = document.getElementById('method-' + method);
      if (panel) panel.classList.add('active');
    });
  });

  // ── Code input auto-advance ──
  document.querySelectorAll('.code-input').forEach((input, i, all) => {
    input.addEventListener('input', () => {
      input.value = input.value.toUpperCase().slice(0, 1);
      if (input.value && i < all.length - 1) all[i + 1].focus();
    });
    input.addEventListener('keydown', e => {
      if (e.key === 'Backspace' && !input.value && i > 0) all[i - 1].focus();
    });
  });

  // ── QR countdown timer ──
  const timerEl = document.getElementById('qr-timer');
  if (timerEl) {
    let secs = 300;
    const tick = () => {
      const m = Math.floor(secs / 60);
      const s = secs % 60;
      timerEl.textContent = `Expires in ${m}:${String(s).padStart(2, '0')}`;
      if (secs > 0) { secs--; setTimeout(tick, 1000); }
      else timerEl.textContent = 'QR code expired — refresh page';
    };
    tick();
  }

  // ── Progress bars animate on load ──
  document.querySelectorAll('.progress-fill[data-pct]').forEach(bar => {
    const pct = bar.dataset.pct;
    bar.style.width = '0%';
    setTimeout(() => { bar.style.width = pct + '%'; }, 200);
  });

  // ── Active nav link ──
  const path = window.location.pathname;
  document.querySelectorAll('.nav-link').forEach(link => {
    if (link.getAttribute('href') && path.includes(link.getAttribute('href').split('/').pop())) {
      link.classList.add('active');
    }
  });

  // ── SmartLabs sidebar drawer (mobile) ──
  const html = document.documentElement;
  const sidebar = document.querySelector('[data-sl-sidebar]');
  const overlay = document.querySelector('[data-sl-sidebar-overlay]');
  const toggles = document.querySelectorAll('[data-sl-sidebar-toggle]');

  const closeSidebar = () => html.classList.remove('sl-sidebar-open');
  const openSidebar = () => html.classList.add('sl-sidebar-open');
  const toggleSidebar = () => html.classList.toggle('sl-sidebar-open');

  if (sidebar) {
    toggles.forEach(btn => btn.addEventListener('click', toggleSidebar));
    if (overlay) overlay.addEventListener('click', closeSidebar);
    window.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeSidebar(); });

    // If no toggle exists in a page header, inject one (mobile-first).
    if (!toggles.length) {
      const injected = document.createElement('button');
      injected.type = 'button';
      injected.setAttribute('aria-label', 'Open sidebar');
      injected.className = 'sl-icon-btn';
      injected.style.position = 'fixed';
      injected.style.left = '16px';
      injected.style.top = '16px';
      injected.style.zIndex = '950';
      injected.innerHTML = '<i class="pi pi-bars"></i>';
      injected.addEventListener('click', openSidebar);
      document.body.appendChild(injected);
    }
  }

  // ── Theme toggle (light/dark) ──
  const applyTheme = (theme) => {
    if (theme === 'dark') html.setAttribute('data-theme', 'dark');
    else html.removeAttribute('data-theme');
  };

  const storedTheme = localStorage.getItem('sl_theme');
  if (storedTheme) applyTheme(storedTheme);

  document.querySelectorAll('[data-sl-theme-toggle]').forEach(btn => {
    btn.addEventListener('click', () => {
      const isDark = html.getAttribute('data-theme') === 'dark';
      const next = isDark ? 'light' : 'dark';
      applyTheme(next === 'dark' ? 'dark' : 'light');
      localStorage.setItem('sl_theme', next);
    });
  });

});
