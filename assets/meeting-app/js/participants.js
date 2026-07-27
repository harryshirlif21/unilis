/**
 * UNILIS Meeting - Participant Panel
 *
 * Two surfaces over the same list: the narrow sidebar panel, and a modal that
 * shows everyone at once. The modal matters more than it looks - while someone
 * is presenting the video tiles are hidden entirely, so it becomes the only way
 * to see who is actually in the room.
 */
UNILIS_MEETING.ParticipantPanel = {
  modalOpen: false,

  _avatarColor(userId) {
    const colors = ['#1a73e8', '#34a853', '#fbbc04', '#ea4335', '#ab47bc', '#00acc1', '#ff7043', '#8d6e63'];
    return colors[userId % colors.length];
  },

  _roleLabel(role) {
    return role === 'lecturer' ? 'Lecturer' : role === 'host' ? 'Host' : 'Student';
  },

  render(participants, isHost) {
    const config = UNILIS_MEETING.config || {};
    const currentUserId = config.user_id;
    let html = `<div class="participant-count">${participants.length} participant${participants.length !== 1 ? 's' : ''}</div>`;
    html += `<button class="participant-viewall" onclick="UNILIS_MEETING.ParticipantPanel.openModal()">View all participants</button>`;
    html += `<input class="participant-search" type="text" placeholder="Search participants..." oninput="UNILIS_MEETING.ParticipantPanel.filter(this.value)">`;
    html += `<div class="participant-list">`;
    participants.forEach(p => {
      const isMe = p.user_id === currentUserId;
      const initial = (p.display_name || '?')[0].toUpperCase();
      html += `
        <div class="participant-item" data-userid="${p.user_id}" data-name="${this._esc(p.display_name)}">
          <div class="avatar" style="background:${this._avatarColor(p.user_id)}">${this._esc(initial)}</div>
          <div class="info">
            <div class="name">${this._esc(p.display_name)} ${isMe ? '(You)' : ''}</div>
            <div class="role-badge">${this._roleLabel(p.role)}</div>
          </div>
          <div class="status-icons">
            ${p.screen_sharing ? '<span title="Presenting">🖥</span>' : ''}
            ${p.hand_raised ? '<span class="hand-icon" title="Hand raised">✋</span>' : ''}
            ${!p.audio_enabled ? '<span class="muted-icon" title="Muted">🔇</span>' : ''}
            ${p.is_muted ? '<span class="muted-icon" title="Muted by host">🔇</span>' : ''}
          </div>
          ${isHost && !isMe ? `
            <div class="actions">
              <button onclick="UNILIS_MEETING.ParticipantPanel.mute(${p.user_id})" title="Mute">🔇</button>
              <button onclick="UNILIS_MEETING.ParticipantPanel.remove(${p.user_id})" title="Remove">✕</button>
            </div>
          ` : ''}
        </div>`;
    });
    html += `</div>`;
    return html;
  },

  filter(query) {
    const items = document.querySelectorAll('.participant-item');
    const q = query.toLowerCase();
    items.forEach(item => {
      const name = (item.dataset.name || '').toLowerCase();
      item.style.display = name.includes(q) ? 'flex' : 'none';
    });
  },

  // ============================================================
  // All-participants modal
  // ============================================================

  /**
   * The modal element, created on first use and reused afterwards so that
   * reopening it does not rebuild the overlay each time.
   */
  _modalEl() {
    let modal = document.getElementById('participantsModal');
    if (modal) return modal;

    modal = document.createElement('div');
    modal.className = 'participants-modal';
    modal.id = 'participantsModal';
    modal.hidden = true;
    modal.innerHTML = `
      <div class="participants-modal-card" role="dialog" aria-modal="true" aria-label="All participants">
        <header>
          <h3 id="participantsModalTitle">Participants</h3>
          <button class="participants-modal-close" title="Close" aria-label="Close">✕</button>
        </header>
        <input class="participants-modal-search" type="text" placeholder="Search participants...">
        <div class="participants-modal-grid" id="participantsModalGrid"></div>
      </div>`;

    // Backdrop click closes, card click does not bubble out and close it.
    modal.addEventListener('click', (e) => {
      if (e.target === modal || e.target.closest('.participants-modal-close')) {
        this.closeModal();
      }
    });
    modal.querySelector('.participants-modal-search').addEventListener('input', (e) => {
      this.filterModal(e.target.value);
    });

    document.body.appendChild(modal);
    return modal;
  },

  openModal() {
    const modal = this._modalEl();
    modal.hidden = false;
    this.modalOpen = true;

    const config = UNILIS_MEETING.config || {};
    this.syncModal(UNILIS_MEETING.Room.participants || [], config.role === 'lecturer');

    const search = modal.querySelector('.participants-modal-search');
    search.value = '';
    search.focus();

    if (!this._escBound) {
      this._escBound = (e) => { if (e.key === 'Escape') this.closeModal(); };
      document.addEventListener('keydown', this._escBound);
    }
  },

  closeModal() {
    const modal = document.getElementById('participantsModal');
    if (modal) modal.hidden = true;
    this.modalOpen = false;
  },

  /**
   * Repaint the modal from the current roster. A no-op when it is closed, so
   * this can be called from every participants broadcast.
   */
  syncModal(participants, isHost) {
    if (!this.modalOpen) return;

    const grid = document.getElementById('participantsModalGrid');
    const title = document.getElementById('participantsModalTitle');
    if (!grid) return;

    const config = UNILIS_MEETING.config || {};
    const currentUserId = config.user_id;

    title.textContent = `Participants (${participants.length})`;

    if (!participants.length) {
      grid.innerHTML = `<p class="participants-modal-empty">Nobody else is here yet.</p>`;
      return;
    }

    grid.innerHTML = participants.map(p => {
      const isMe = p.user_id === currentUserId;
      const initial = (p.display_name || '?')[0].toUpperCase();
      const status = [];
      if (p.screen_sharing) status.push('<span class="pc-chip pc-chip-live">Presenting</span>');
      if (p.hand_raised) status.push('<span class="pc-chip">✋ Hand raised</span>');
      if (!p.audio_enabled || p.is_muted) status.push('<span class="pc-chip pc-chip-muted">🔇 Muted</span>');
      if (!p.video_enabled) status.push('<span class="pc-chip">Camera off</span>');

      return `
        <div class="participant-card" data-name="${this._esc(p.display_name)}">
          <div class="pc-avatar" style="background:${this._avatarColor(p.user_id)}">${this._esc(initial)}</div>
          <div class="pc-body">
            <div class="pc-name">${this._esc(p.display_name)}${isMe ? ' <span class="pc-you">(You)</span>' : ''}</div>
            <div class="pc-role">${this._roleLabel(p.role)}</div>
            <div class="pc-status">${status.join('')}</div>
          </div>
          ${isHost && !isMe ? `
            <div class="pc-actions">
              <button onclick="UNILIS_MEETING.ParticipantPanel.mute(${p.user_id})" title="Mute">🔇</button>
              <button onclick="UNILIS_MEETING.ParticipantPanel.remove(${p.user_id})" title="Remove">✕</button>
            </div>` : ''}
        </div>`;
    }).join('');

    // A repaint drops the previous filter, so reapply whatever is typed.
    const search = document.querySelector('.participants-modal-search');
    if (search && search.value) this.filterModal(search.value);
  },

  filterModal(query) {
    const q = query.toLowerCase();
    document.querySelectorAll('.participant-card').forEach(card => {
      const name = (card.dataset.name || '').toLowerCase();
      card.style.display = name.includes(q) ? 'flex' : 'none';
    });
  },

  mute(userId) {
    UNILIS_MEETING.signaling.send({ type: 'mute_participant', target_user_id: userId });
  },

  remove(userId) {
    if (confirm('Remove this participant from the meeting?')) {
      UNILIS_MEETING.signaling.send({ type: 'remove_participant', target_user_id: userId });
    }
  },

  /**
   * Escape for both text and attribute positions.
   *
   * textContent alone leaves quotes intact, which is fine between tags but not
   * inside data-name="...". Display names come from the meeting URL's query
   * string, so a quote in one would otherwise break out of the attribute.
   */
  _esc(text) {
    const div = document.createElement('div');
    div.textContent = (text === undefined || text === null) ? '' : text;
    return div.innerHTML.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  },
};
