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

});
