/**
 * UNILIS Meeting - Participant Panel
 *
 * Two surfaces over the same list: the narrow sidebar panel, and a modal that
 * shows everyone at once. The modal matters more than it looks - while someone
 * is presenting the video tiles are hidden entirely, so it becomes the only way
 * to see who is actually in the room.
 *
 * HANDS FIRST
 *
 * Raised hands are pulled out above the roster, in the order they went up. A
 * hand is a question waiting on the host, and finding it by scanning thirty rows
 * for an emoji is how a question gets missed.
 *
 * ACTIONS
 *
 * Every action here is a request to the server, which checks the caller is a
 * host before doing it. The buttons are hidden from participants because showing
 * a control that will be refused is worse than not showing it - not because
 * hiding them is the check.
 */
UNILIS_MEETING.ParticipantPanel = {
  modalOpen: false,

  _avatarColor(userId) {
    const colors = ['#1a73e8', '#34a853', '#fbbc04', '#ea4335', '#ab47bc', '#00acc1', '#ff7043', '#8d6e63'];
    return colors[Math.abs(userId) % colors.length];
  },

  /**
   * A guest is labelled as one rather than folded into "Student".
   *
   * A room can hold enrolled students and outside guests at the same time, and
   * the host moderates the two differently — so calling a guest a student is not
   * a cosmetic slip, it hides the distinction the host is acting on.
   */
  _roleLabel(role) {
    if (role === 'lecturer') { return 'Lecturer'; }
    if (role === 'host') { return 'Host'; }
    if (role === 'guest') { return 'Guest'; }
    return 'Student';
  },

  // ============================================================
  // Sidebar panel
  // ============================================================

  render(participants, isHost) {
    const room = UNILIS_MEETING.Room;
    const config = UNILIS_MEETING.config || {};
    const currentUserId = config.user_id;
    const rooms = room.state.breakouts || [];

    let html = '';

    // ---- Raised hands ----
    const hands = room.raisedHands();
    if (hands.length) {
      html += `<div class="pp-hands">
        <div class="pp-hands-head">
          <span>✋ ${hands.length} hand${hands.length === 1 ? '' : 's'} up</span>
          ${isHost ? `<button class="sb-btn sb-btn-small" onclick="UNILIS_MEETING.Settings.lowerAllHands()">Lower all</button>` : ''}
        </div>
        <ol class="pp-hands-list">`;
      hands.forEach((h, index) => {
        const isMe = h.user_id === currentUserId;
        html += `<li>
          <span class="pp-hands-pos">${index + 1}</span>
          <span class="pp-hands-name">${this._esc(h.display_name)}${isMe ? ' (you)' : ''}</span>
          ${(isHost || isMe) ? `<button class="pp-hands-clear" title="Lower this hand"
             onclick="UNILIS_MEETING.ParticipantPanel.lowerHand(${h.user_id})">Lower</button>` : ''}
        </li>`;
      });
      html += '</ol></div>';
    }

    // ---- Roster ----
    html += `<div class="participant-count">${participants.length} in ${this._esc(room.currentRoomName())}</div>`;
    html += `<button class="participant-viewall" onclick="UNILIS_MEETING.ParticipantPanel.openModal()">View everyone side by side</button>`;
    html += `<input class="participant-search" type="text" placeholder="Search participants..." oninput="UNILIS_MEETING.ParticipantPanel.filter(this.value)">`;
    html += `<div class="participant-list">`;

    participants.forEach(p => {
      const isMe = p.user_id === currentUserId;
      const initial = (p.display_name || '?')[0].toUpperCase();
      const roomName = this._roomNameFor(p.breakout_id, rooms);

      html += `
        <div class="participant-item" data-userid="${p.user_id}" data-name="${this._esc(p.display_name)}">
          <div class="avatar" style="background:${this._avatarColor(p.user_id)}">${this._esc(initial)}</div>
          <div class="info">
            <div class="name">${this._esc(p.display_name)} ${isMe ? '(You)' : ''}</div>
            <div class="role-badge">
              ${this._roleLabel(p.role)}${roomName ? ` · ${this._esc(roomName)}` : ''}
            </div>
          </div>
          <div class="status-icons">
            ${p.screen_sharing ? '<span title="Presenting">🖥</span>' : ''}
            ${p.hand_raised ? '<span class="hand-icon" title="Hand raised">✋</span>' : ''}
            ${(!p.audio_enabled || p.is_muted) ? '<span class="muted-icon" title="Muted">🔇</span>' : ''}
            ${p.may_share_screen && !p.role.match(/lecturer|host/) ? '<span class="grant-icon" title="Allowed to present">🖥️</span>' : ''}
            ${p.may_whiteboard && !p.role.match(/lecturer|host/) ? '<span class="grant-icon" title="Allowed to draw">✏️</span>' : ''}
          </div>
          ${this._actions(p, isHost, isMe, rooms)}
        </div>`;
    });

    html += `</div>`;
    return html;
  },

  /**
   * The action row for one participant.
   *
   * A host gets moderation and permission controls; everybody else gets pin,
   * which only changes their own view and so needs nobody's approval.
   */
  _actions(p, isHost, isMe, rooms) {
    const pinned = UNILIS_MEETING.Room.pinnedUserId === p.user_id;

    if (!isHost) {
      if (isMe) return '';
      return `<div class="actions">
        <button class="pa-btn${pinned ? ' active' : ''}" title="${pinned ? 'Unpin' : 'Pin to the main tile'}"
                onclick="UNILIS_MEETING.ParticipantPanel.pin(${p.user_id})">📌</button>
      </div>`;
    }

    if (isMe) {
      return `<div class="actions">
        <button class="pa-btn" title="Your own controls are on the toolbar" disabled>—</button>
      </div>`;
    }

    const canShare = !!p.may_share_screen;
    const canDraw = !!p.may_whiteboard;
    const hasRooms = rooms.length > 0;

    return `<div class="actions">
      <button class="pa-btn${pinned ? ' active' : ''}" title="${pinned ? 'Unpin' : 'Pin to the main tile'}"
              onclick="UNILIS_MEETING.ParticipantPanel.pin(${p.user_id})">📌</button>
      <button class="pa-btn${canShare ? ' active' : ''}"
              title="${canShare ? 'Stop them sharing their screen' : 'Let them share their screen'}"
              onclick="UNILIS_MEETING.ParticipantPanel.grant(${p.user_id}, 'screen', ${canShare ? 'false' : 'true'})">🖥</button>
      <button class="pa-btn${canDraw ? ' active' : ''}"
              title="${canDraw ? 'Take the whiteboard pen back' : 'Let them draw on the whiteboard'}"
              onclick="UNILIS_MEETING.ParticipantPanel.grant(${p.user_id}, 'whiteboard', ${canDraw ? 'false' : 'true'})">✏️</button>
      ${hasRooms ? `<button class="pa-btn" title="Move to a breakout room"
              onclick="UNILIS_MEETING.ParticipantPanel.moveTo(${p.user_id})">🚪</button>` : ''}
      <button class="pa-btn" title="Mute them"
              onclick="UNILIS_MEETING.ParticipantPanel.mute(${p.user_id})">🔇</button>
      <button class="pa-btn pa-btn-danger" title="Remove from the meeting"
              onclick="UNILIS_MEETING.ParticipantPanel.remove(${p.user_id})">✕</button>
    </div>`;
  },

  _roomNameFor(breakoutId, rooms) {
    if (!breakoutId) return '';
    const match = (rooms || []).find(b => b.breakout_id === breakoutId);
    return match ? match.name : 'a breakout room';
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
    const rooms = UNILIS_MEETING.Room.state.breakouts || [];

    title.textContent = `Participants (${participants.length})`;

    if (!participants.length) {
      grid.innerHTML = `<p class="participants-modal-empty">Nobody else is here yet.</p>`;
      return;
    }

    grid.innerHTML = participants.map(p => {
      const isMe = p.user_id === currentUserId;
      const initial = (p.display_name || '?')[0].toUpperCase();
      const roomName = this._roomNameFor(p.breakout_id, rooms);
      const status = [];
      if (p.screen_sharing) status.push('<span class="pc-chip pc-chip-live">Presenting</span>');
      if (p.hand_raised) status.push('<span class="pc-chip">✋ Hand raised</span>');
      if (!p.audio_enabled || p.is_muted) status.push('<span class="pc-chip pc-chip-muted">🔇 Muted</span>');
      if (!p.video_enabled) status.push('<span class="pc-chip">Camera off</span>');
      if (roomName) status.push(`<span class="pc-chip">🚪 ${this._esc(roomName)}</span>`);
      if (p.may_share_screen && !String(p.role).match(/lecturer|host/)) {
        status.push('<span class="pc-chip">Can present</span>');
      }
      if (p.may_whiteboard && !String(p.role).match(/lecturer|host/)) {
        status.push('<span class="pc-chip">Can draw</span>');
      }

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
              <button onclick="UNILIS_MEETING.ParticipantPanel.grant(${p.user_id}, 'screen', ${p.may_share_screen ? 'false' : 'true'})"
                      title="${p.may_share_screen ? 'Stop them sharing' : 'Let them share'}">🖥</button>
              <button onclick="UNILIS_MEETING.ParticipantPanel.grant(${p.user_id}, 'whiteboard', ${p.may_whiteboard ? 'false' : 'true'})"
                      title="${p.may_whiteboard ? 'Take the pen back' : 'Let them draw'}">✏️</button>
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

  // ============================================================
  // Actions
  // ============================================================

  mute(userId) {
    UNILIS_MEETING.signaling.send({ type: 'mute_participant', target_user_id: userId });
  },

  remove(userId) {
    if (confirm('Remove this participant from the meeting?')) {
      UNILIS_MEETING.signaling.send({ type: 'remove_participant', target_user_id: userId });
    }
  },

  /** Give or withdraw one person's screen-share or whiteboard permission. */
  grant(userId, capability, granted) {
    UNILIS_MEETING.signaling.send({
      type: 'grant_permission',
      target_user_id: userId,
      capability,
      granted: granted === true || granted === 'true',
    });
  },

  lowerHand(userId) {
    UNILIS_MEETING.signaling.send({ type: 'lower_hand', target_user_id: userId });
  },

  /**
   * Pin somebody to the big tile. Local only - it changes what this viewer sees
   * and nothing about the meeting, so it needs no permission and no broadcast.
   */
  pin(userId) {
    const pinned = UNILIS_MEETING.Room.pinUser(userId);
    if (UNILIS_MEETING.LayoutManager) {
      UNILIS_MEETING.LayoutManager.setPinned(pinned);
    }
    const name = (UNILIS_MEETING.Room.getParticipant(userId) || {}).display_name || 'them';
    UNILIS_MEETING.Notifications.show(
      pinned ? `Pinned ${name}` : 'Unpinned',
      'info',
      2000
    );
  },

  /**
   * Move somebody to a breakout room. A prompt with a numbered list rather than
   * a custom dialog: the host is mid-meeting and this has to be two keystrokes.
   */
  moveTo(userId) {
    const rooms = UNILIS_MEETING.Room.state.breakouts || [];
    if (!rooms.length) {
      UNILIS_MEETING.Notifications.show('Create a breakout room first, in the Rooms tab', 'info');
      return;
    }

    const menu = ['0. Main room'].concat(rooms.map((b, i) => `${i + 1}. ${b.name}`)).join('\n');
    const answer = prompt(`Move to which room?\n\n${menu}`, '1');
    if (answer === null) return;

    const index = parseInt(answer, 10);
    if (isNaN(index) || index < 0 || index > rooms.length) return;

    UNILIS_MEETING.Breakouts.assign(
      userId,
      index === 0 ? null : rooms[index - 1].breakout_id
    );
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
