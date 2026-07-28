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

  /**
   * @param container the .meeting-sidebar element itself, or an ancestor of it.
   *
   * matches() before querySelector(), because the caller passes the sidebar
   * itself. querySelector only looks at descendants, so it returned null, so
   * this.element stayed null, so open() had nothing to remove `hidden` from -
   * and every side panel (participants, chat, polls, board, settings) was
   * unreachable no matter which button was pressed.
   */
  init(container) {
    if (!container) return;
    this.element = container.matches && container.matches('.meeting-sidebar')
      ? container
      : container.querySelector('.meeting-sidebar');
    const scope = this.element || container;
    this.tabsEl = scope.querySelector('.sidebar-tabs');
    this.contentEl = scope.querySelector('.sidebar-content');
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

  /**
   * Put a count on a tab, or clear it with 0.
   *
   * Used for unread chat and for raised hands, both of which happen while the
   * host is looking at a different tab - which is exactly when they need to know.
   */
  setBadge(tabId, count) {
    if (!this.tabsEl) return;
    const tab = this.tabsEl.querySelector(`.sidebar-tab[data-tab="${tabId}"]`);
    if (!tab) return;

    let badge = tab.querySelector('.tab-badge');
    if (!count) {
      if (badge) badge.remove();
      return;
    }
    if (!badge) {
      badge = document.createElement('span');
      badge.className = 'tab-badge';
      tab.appendChild(badge);
    }
    badge.textContent = count > 99 ? '99+' : String(count);
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