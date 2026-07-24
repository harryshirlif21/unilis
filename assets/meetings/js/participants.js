/**
 * UNILIS Meeting - Participant Panel
 */
UNILIS_MEETING.ParticipantPanel = {
  render(participants, isHost) {
    const config = UNILIS_MEETING.config || {};
    const currentUserId = config.user_id;
    let html = `<div class="participant-count">${participants.length} participant${participants.length !== 1 ? 's' : ''}</div>`;
    html += `<input class="participant-search" type="text" placeholder="Search participants..." oninput="UNILIS_MEETING.ParticipantPanel.filter(this.value)">`;
    html += `<div class="participant-list">`;
    participants.forEach(p => {
      const isMe = p.user_id === currentUserId;
      const initial = (p.display_name || '?')[0].toUpperCase();
      const colors = ['#1a73e8', '#34a853', '#fbbc04', '#ea4335', '#ab47bc', '#00acc1', '#ff7043', '#8d6e63'];
      const color = colors[p.user_id % colors.length];
      const roleLabel = p.role === 'lecturer' ? 'Lecturer' : p.role === 'host' ? 'Host' : 'Student';
      html += `
        <div class="participant-item" data-userid="${p.user_id}" data-name="${p.display_name}">
          <div class="avatar" style="background:${color}">${initial}</div>
          <div class="info">
            <div class="name">${this._esc(p.display_name)} ${isMe ? '(You)' : ''}</div>
            <div class="role-badge">${roleLabel}</div>
          </div>
          <div class="status-icons">
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

  mute(userId) {
    UNILIS_MEETING.signaling.send({ type: 'mute_participant', target_user_id: userId });
  },

  remove(userId) {
    if (confirm('Remove this participant from the meeting?')) {
      UNILIS_MEETING.signaling.send({ type: 'remove_participant', target_user_id: userId });
    }
  },

  _esc(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  },
};