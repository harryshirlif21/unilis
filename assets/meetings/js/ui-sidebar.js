/**
 * UNILIS Meeting - Sidebar Manager
 * Controls the right sidebar with tabs for participants, chat, polls, whiteboard, etc.
 */
UNILIS_MEETING.SidebarManager = {
  isOpen: false,
  currentTab: 'participants',
  element: null,
  contentEl: null,
  tabsEl: null,
  onTabChange: null,

  init(container) {
    this.element = container.querySelector('.meeting-sidebar');
    this.tabsEl = container.querySelector('.sidebar-tabs');
    this.contentEl = container.querySelector('.sidebar-content');
  },

  open(tab = null) {
    this.isOpen = true;
    if (this.element) this.element.classList.remove('hidden');
    if (tab) this.switchTab(tab);
  },

  close() {
    this.isOpen = false;
    if (this.element) this.element.classList.add('hidden');
  },

  toggle() {
    if (this.isOpen) this.close();
    else this.open();
  },

  switchTab(tab) {
    this.currentTab = tab;
    if (this.tabsEl) {
      this.tabsEl.querySelectorAll('.sidebar-tab').forEach(t => {
        t.classList.toggle('active', t.dataset.tab === tab);
      });
    }
    if (this.onTabChange) this.onTabChange(tab);
    this.open();
  },

  renderTabButton(tabId, label, icon = '', badge = 0) {
    const btn = document.createElement('button');
    btn.className = 'sidebar-tab' + (this.currentTab === tabId ? ' active' : '');
    btn.dataset.tab = tabId;
    btn.innerHTML = `${icon} ${label}`;
    if (badge > 0) {
      const badgeEl = document.createElement('span');
      badgeEl.className = 'tab-badge';
      badgeEl.textContent = badge;
      btn.appendChild(badgeEl);
    }
    btn.addEventListener('click', () => this.switchTab(tabId));
    return btn;
  },

  setContent(html) {
    if (this.contentEl) this.contentEl.innerHTML = html;
  },

  appendContent(el) {
    if (this.contentEl) this.contentEl.appendChild(el);
  },

  clearContent() {
    if (this.contentEl) this.contentEl.innerHTML = '';
  },
};