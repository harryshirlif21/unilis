/**
 * UNILIS Meeting - Breakout Rooms
 *
 * A breakout is a filter, not a second meeting. Everyone stays on one signalling
 * connection and carries a room id; who you see, hear and chat with is whoever
 * shares it. Moving is therefore instant - tear down the peers you can no longer
 * reach, build the ones you now can - rather than a disconnect and rejoin.
 *
 * WHO MAY MOVE WHOM
 *
 * A host may move anyone. A participant may move themselves only while the host
 * has left self-joining on, which is the "allow participants to join rooms"
 * switch in the host's settings. The server enforces both; this panel just
 * stops offering buttons that would be refused.
 */
UNILIS_MEETING.Breakouts = {
  /**
   * The rooms panel. Rebuilt from room state on every change, so it is a pure
   * function of the roster plus the room list.
   */
  render() {
    const room = UNILIS_MEETING.Room;
    const isHost = room.isHost();
    const rooms = room.state.breakouts || [];
    const mine = room.breakoutId;
    const canMove = room.canJoinBreakouts();

    let html = '<div class="sb-block">';

    html += `<p class="sb-note">
      You are in <strong>${this._esc(room.currentRoomName())}</strong>.
      ${rooms.length ? 'People in other rooms cannot see or hear you.' : 'No breakout rooms have been created yet.'}
    </p>`;

    if (!isHost && !canMove && rooms.length) {
      html += `<p class="sb-meta">The host is assigning rooms for this meeting.</p>`;
    }

    // The main room is a room in the list like any other, so returning to it is
    // the same gesture as joining a breakout.
    html += '<div class="br-list">';
    html += this._roomRow({
      id: null,
      name: 'Main room',
      occupants: room.state.main_room_occupants || 0,
      mine: mine === null,
      isHost,
      canMove,
      isMain: true,
    });

    rooms.forEach(b => {
      html += this._roomRow({
        id: b.breakout_id,
        name: b.name,
        occupants: b.occupants || 0,
        mine: mine === b.breakout_id,
        isHost,
        canMove,
        isMain: false,
      });
    });
    html += '</div>';

    if (isHost) {
      html += `
        <div class="br-create">
          <input type="text" id="brName" maxlength="60" placeholder="Room name, e.g. Group A">
          <button class="sb-btn sb-btn-primary" onclick="UNILIS_MEETING.Breakouts.create()">Add room</button>
        </div>
        <div class="br-host-actions">
          <button class="sb-btn" onclick="UNILIS_MEETING.Breakouts.announce()">Message every room</button>
          ${rooms.length ? `<button class="sb-btn sb-btn-danger" onclick="UNILIS_MEETING.Breakouts.closeAll()">Close all rooms</button>` : ''}
        </div>`;

      html += this._assignBlock(rooms);
    }

    html += '</div>';
    return html;
  },

  _roomRow({ id, name, occupants, mine, isHost, canMove, isMain }) {
    // A host can always move; a participant only when self-joining is on.
    const joinable = !mine && (isHost || canMove);

    return `
      <div class="br-room${mine ? ' br-room-mine' : ''}">
        <div class="br-room-body">
          <span class="br-room-name">${this._esc(name)}</span>
          <span class="br-room-count">${occupants} ${occupants === 1 ? 'person' : 'people'}${mine ? ' · you are here' : ''}</span>
        </div>
        <div class="br-room-actions">
          ${joinable ? `<button class="sb-btn sb-btn-small sb-btn-primary"
              onclick="UNILIS_MEETING.Breakouts.join(${id === null ? 'null' : `'${id}'`})">Join</button>` : ''}
          ${isHost && !isMain ? `
            <button class="sb-btn sb-btn-small" title="Rename"
                    onclick="UNILIS_MEETING.Breakouts.rename('${id}', '${this._attr(name)}')">Rename</button>
            <button class="sb-btn sb-btn-small sb-btn-danger" title="Delete this room"
                    onclick="UNILIS_MEETING.Breakouts.remove('${id}', '${this._attr(name)}')">Delete</button>` : ''}
        </div>
      </div>`;
  },

  /**
   * The host's assignment list: one row per participant with a room picker.
   *
   * A select rather than drag and drop, because this has to work on a phone and
   * a lecturer assigning thirty students wants a list they can go down.
   */
  _assignBlock(rooms) {
    const room = UNILIS_MEETING.Room;
    const people = (room.participants || []).filter(p => p.user_id !== room.userId);

    if (!people.length) {
      return '<p class="sb-meta">Nobody else has joined yet.</p>';
    }

    const options = (selected) => {
      let out = `<option value=""${selected === null ? ' selected' : ''}>Main room</option>`;
      rooms.forEach(b => {
        out += `<option value="${b.breakout_id}"${selected === b.breakout_id ? ' selected' : ''}>${this._esc(b.name)}</option>`;
      });
      return out;
    };

    let html = '<h4 class="sb-subhead">Who is where</h4><div class="br-assign">';
    people.forEach(p => {
      html += `
        <div class="br-assign-row">
          <span class="br-assign-name">${this._esc(p.display_name)}</span>
          <select onchange="UNILIS_MEETING.Breakouts.assign(${p.user_id}, this.value)">
            ${options(p.breakout_id || null)}
          </select>
        </div>`;
    });
    html += '</div>';
    return html;
  },

  // ============================================================
  // Actions
  // ============================================================

  create() {
    const input = document.getElementById('brName');
    const name = input ? input.value.trim() : '';
    UNILIS_MEETING.signaling.send({ type: 'breakout_create', name });
    if (input) input.value = '';
  },

  rename(breakoutId, currentName) {
    const name = prompt('Rename this room:', currentName || '');
    if (name === null) return;
    if (!name.trim()) return;
    UNILIS_MEETING.signaling.send({ type: 'breakout_rename', breakout_id: breakoutId, name: name.trim() });
  },

  remove(breakoutId, name) {
    if (!confirm(`Delete "${name}"? Anyone still in it comes back to the main room.`)) return;
    UNILIS_MEETING.signaling.send({ type: 'breakout_delete', breakout_id: breakoutId });
  },

  closeAll() {
    if (!confirm('Close every breakout room and bring everybody back to the main room?')) return;
    UNILIS_MEETING.signaling.send({ type: 'breakout_close_all' });
  },

  /** Move yourself. */
  join(breakoutId) {
    UNILIS_MEETING.signaling.send({
      type: 'breakout_assign',
      target_user_id: UNILIS_MEETING.Room.userId,
      breakout_id: breakoutId,
    });
  },

  /** Move someone else, as the host. An empty value is the main room. */
  assign(userId, breakoutId) {
    UNILIS_MEETING.signaling.send({
      type: 'breakout_assign',
      target_user_id: userId,
      breakout_id: breakoutId || null,
    });
  },

  announce() {
    const text = prompt('Message to send to every room:');
    if (!text || !text.trim()) return;
    UNILIS_MEETING.signaling.send({ type: 'breakout_broadcast', text: text.trim() });
  },

  _esc(text) {
    const div = document.createElement('div');
    div.textContent = (text === undefined || text === null) ? '' : text;
    return div.innerHTML;
  },

  /**
   * Escaped for an inline handler's single-quoted argument.
   *
   * Room names are typed by the host, so a name containing an apostrophe would
   * otherwise close the string and break the button.
   */
  _attr(text) {
    return String(text === undefined || text === null ? '' : text)
      .replace(/\\/g, '\\\\')
      .replace(/'/g, "\\'")
      .replace(/"/g, '&quot;')
      .replace(/</g, '&lt;');
  },
};
