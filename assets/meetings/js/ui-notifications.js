/**
 * UNILIS Meeting - Toast Notifications
 */
UNILIS_MEETING.Notifications = {
  container: null,

  init() {
    this.container = document.createElement('div');
    this.container.className = 'meeting-notification';
    document.body.appendChild(this.container);
  },

  show(text, type = 'info', duration = 4000) {
    const icons = { success: '✓', error: '✕', warning: '⚠', info: 'ℹ' };
    const toast = document.createElement('div');
    toast.className = `notification-toast ${type}`;
    toast.innerHTML = `
      <span class="notif-icon">${icons[type] || icons.info}</span>
      <span class="notif-text">${this._escape(text)}</span>
      <button class="notif-close">&times;</button>
    `;
    toast.querySelector('.notif-close').addEventListener('click', () => toast.remove());
    this.container.appendChild(toast);
    setTimeout(() => { if (toast.parentNode) toast.remove(); }, duration);
  },

  _escape(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  },
};