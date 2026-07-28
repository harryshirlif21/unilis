/**
 * UNILIS Meeting - Settings
 *
 * Two panels behind one tab. A host sees the controls that change the meeting
 * for everybody; everyone else sees what applies to them and what the host has
 * decided. They share a tab because "what am I allowed to do here" and "what am
 * I allowing" are the same question asked from the two ends.
 *
 * Every host control is a message to the server, and the panel repaints from the
 * room_state that comes back rather than from the click. So a switch that the
 * server refuses springs back, instead of showing a state the room is not in.
 */
UNILIS_MEETING.Settings = {
  // Cached device list. enumerateDevices() only returns labels once permission
  // has been granted, so this is filled after joining rather than at load.
  devices: { cameras: [], microphones: [] },

  render() {
    const room = UNILIS_MEETING.Room;
    return room.isHost() ? this._hostPanel() : this._participantPanel();
  },

  // ============================================================
  // Host
  // ============================================================

  _hostPanel() {
    const room = UNILIS_MEETING.Room;
    const state = room.state;
    const hands = room.raisedHands();
    const others = (room.participants || []).filter(p => p.user_id !== room.userId);
    const mutedCount = others.filter(p => !p.audio_enabled || p.is_muted).length;

    return `
      <div class="sb-block">
        <h4 class="sb-subhead">Who can do what</h4>

        ${this._policyRow({
          setting: 'screen',
          label: 'Share their screen',
          note: 'With this on host only, you can still let one person share from the People list.',
          value: state.screen_share_policy,
        })}

        ${this._policyRow({
          setting: 'whiteboard',
          label: 'Draw on the whiteboard',
          note: 'Everyone can always see the board. This is about who may write on it.',
          value: state.whiteboard_policy,
        })}

        ${this._switchRow({
          setting: 'chat_enabled',
          label: 'Chat',
          note: 'Turning this off silences the chat for participants. You can still send messages.',
          on: state.chat_enabled,
        })}

        ${this._switchRow({
          setting: 'breakout_self_join',
          label: 'Let people pick their own breakout room',
          note: 'With this off, only you can move people between rooms.',
          on: state.breakout_self_join,
        })}

        ${this._switchRow({
          setting: 'lock',
          label: 'Lock the meeting',
          note: 'A locked meeting is closed to anyone who has not joined yet.',
          on: state.is_locked,
        })}
      </div>

      <div class="sb-block">
        <h4 class="sb-subhead">The room</h4>
        <div class="sb-actions">
          <button class="sb-btn" onclick="UNILIS_MEETING.Settings.muteAll()">
            Mute everyone${mutedCount ? ` <span class="sb-count">${others.length - mutedCount} unmuted</span>` : ''}
          </button>
          <button class="sb-btn"${hands.length ? '' : ' disabled'} onclick="UNILIS_MEETING.Settings.lowerAllHands()">
            Lower all hands${hands.length ? ` <span class="sb-count">${hands.length}</span>` : ''}
          </button>
        </div>
        <p class="sb-meta">
          Muting everyone does not mute you, and does not stop anyone unmuting themselves again —
          it clears the room, it does not hold it.
        </p>
      </div>

      ${this._devicesBlock()}
      ${this._infoBlock()}
    `;
  },

  /** A host-only/everyone pair for one capability. */
  _policyRow({ setting, label, note, value }) {
    const isEveryone = value === 'everyone';
    return `
      <div class="sb-setting">
        <div class="sb-setting-body">
          <span class="sb-setting-label">${label}</span>
          <span class="sb-setting-note">${note}</span>
        </div>
        <div class="sb-seg" role="group" aria-label="${label}">
          <button class="sb-seg-btn${isEveryone ? '' : ' active'}"
                  onclick="UNILIS_MEETING.Settings.setPolicy('${setting}', 'host_only')">Host only</button>
          <button class="sb-seg-btn${isEveryone ? ' active' : ''}"
                  onclick="UNILIS_MEETING.Settings.setPolicy('${setting}', 'everyone')">Everyone</button>
        </div>
      </div>`;
  },

  _switchRow({ setting, label, note, on }) {
    return `
      <div class="sb-setting">
        <div class="sb-setting-body">
          <span class="sb-setting-label">${label}</span>
          <span class="sb-setting-note">${note}</span>
        </div>
        <button class="sb-switch${on ? ' on' : ''}" role="switch" aria-checked="${on ? 'true' : 'false'}"
                aria-label="${label}"
                onclick="UNILIS_MEETING.Settings.toggle('${setting}', ${on ? 'false' : 'true'})">
          <span class="sb-switch-knob"></span>
        </button>
      </div>`;
  },

  // ============================================================
  // Participant
  // ============================================================

  _participantPanel() {
    const room = UNILIS_MEETING.Room;
    const canShare = room.canShareScreen();
    const canDraw = room.canUseWhiteboard();

    return `
      <div class="sb-block">
        <h4 class="sb-subhead">What you can do here</h4>

        <div class="sb-setting">
          <div class="sb-setting-body">
            <span class="sb-setting-label">Share your screen</span>
            <span class="sb-setting-note">${canShare
              ? 'Allowed. Use Present on the toolbar.'
              : 'The host has kept this to hosts.'}</span>
          </div>
          ${canShare
            ? '<span class="sb-pill sb-pill-on">Allowed</span>'
            : `<button class="sb-btn sb-btn-small" onclick="UNILIS_MEETING.ScreenShare.request()">Ask</button>`}
        </div>

        <div class="sb-setting">
          <div class="sb-setting-body">
            <span class="sb-setting-label">Draw on the whiteboard</span>
            <span class="sb-setting-note">${canDraw
              ? 'Allowed. Open the board from the toolbar.'
              : 'You can see the board but not write on it.'}</span>
          </div>
          ${canDraw
            ? '<span class="sb-pill sb-pill-on">Allowed</span>'
            : `<button class="sb-btn sb-btn-small" onclick="UNILIS_MEETING.Whiteboard.requestPen()">Ask</button>`}
        </div>

        <div class="sb-setting">
          <div class="sb-setting-body">
            <span class="sb-setting-label">Chat</span>
            <span class="sb-setting-note">${room.state.chat_enabled
              ? 'Open.'
              : 'The host has turned chat off.'}</span>
          </div>
          <span class="sb-pill${room.state.chat_enabled ? ' sb-pill-on' : ''}">
            ${room.state.chat_enabled ? 'On' : 'Off'}
          </span>
        </div>
      </div>

      ${this._devicesBlock()}
      ${this._infoBlock()}
    `;
  },

  // ============================================================
  // Shared blocks
  // ============================================================

  _devicesBlock() {
    const media = UNILIS_MEETING.MediaManager;
    const cameras = this.devices.cameras;
    const mics = this.devices.microphones;

    // Neither list is offered when the machine has nothing to offer. A picker
    // with one greyed entry reads as a fault rather than as a fact about the
    // hardware.
    if (!cameras.length && !mics.length) {
      return `
        <div class="sb-block">
          <h4 class="sb-subhead">Your devices</h4>
          <p class="sb-meta">No camera or microphone was found on this device. You can still watch,
            listen and use the chat.</p>
        </div>`;
    }

    const options = (list, current) => list.map((d, i) => {
      const label = d.label || `Device ${i + 1}`;
      return `<option value="${d.deviceId}"${d.deviceId === current ? ' selected' : ''}>${this._esc(label)}</option>`;
    }).join('');

    const currentCamera = (media.localStream && media.localStream.getVideoTracks()[0]
      && media.localStream.getVideoTracks()[0].getSettings().deviceId) || '';

    return `
      <div class="sb-block">
        <h4 class="sb-subhead">Your devices</h4>
        ${cameras.length ? `
          <label class="sb-field">
            <span>Camera</span>
            <select onchange="UNILIS_MEETING.Settings.pickCamera(this.value)">
              ${options(cameras, currentCamera)}
            </select>
          </label>` : ''}
        ${mics.length ? `
          <label class="sb-field">
            <span>Microphone</span>
            <select onchange="UNILIS_MEETING.Settings.pickMicrophone(this.value)">
              ${options(mics, '')}
            </select>
          </label>` : ''}
        <p class="sb-meta">Switching a device replaces the track other people are receiving,
          so there is a moment's flicker on their side.</p>
      </div>`;
  },

  _infoBlock() {
    const config = UNILIS_MEETING.config || {};
    const room = UNILIS_MEETING.Room;

    return `
      <div class="sb-block">
        <h4 class="sb-subhead">This meeting</h4>
        <div class="sb-kv"><span>Title</span><span>${this._esc(config.title || '-')}</span></div>
        <div class="sb-kv"><span>Unit</span><span>${this._esc(config.unit_name || '-')}</span></div>
        <div class="sb-kv"><span>You are</span><span>${this._esc(this._roleWord(config.role))}</span></div>
        <div class="sb-kv"><span>Room</span><span>${this._esc(room.currentRoomName())}</span></div>
        <div class="sb-kv"><span>Scheduled</span><span>${this._esc(config.scheduled_time || 'Now')}</span></div>
        <div class="sb-kv"><span>Length</span><span>${parseInt(config.duration, 10) || 0} min</span></div>
        ${config.back_url ? `
          <a class="sb-btn sb-btn-block" href="${this._esc(config.back_url)}">Leave and go back</a>` : ''}
      </div>`;
  },

  _roleWord(role) {
    if (role === 'lecturer') return 'the host';
    if (role === 'guest') return 'a guest';
    return 'a student';
  },

  // ============================================================
  // Actions
  // ============================================================

  setPolicy(setting, value) {
    UNILIS_MEETING.signaling.send({ type: 'set_policy', setting, value });
  },

  toggle(setting, value) {
    if (setting === 'lock') {
      UNILIS_MEETING.signaling.send({ type: 'lock_meeting', locked: value === true || value === 'true' });
      return;
    }
    UNILIS_MEETING.signaling.send({
      type: 'set_policy',
      setting,
      value: value === true || value === 'true',
    });
  },

  muteAll() {
    if (!confirm('Mute everyone except yourself?')) return;
    UNILIS_MEETING.signaling.send({ type: 'mute_all' });
  },

  lowerAllHands() {
    UNILIS_MEETING.signaling.send({ type: 'lower_all_hands' });
  },

  async pickCamera(deviceId) {
    if (!deviceId) return;
    await UNILIS_MEETING.MediaManager.switchCamera(deviceId);
    UNILIS_MEETING.Notifications.show('Camera switched', 'success');
  },

  /**
   * Switching microphone reuses switchCamera's approach in reverse: take a fresh
   * stream from the chosen device, swap the audio track into the live stream,
   * and re-attach so peers get the new one.
   */
  async pickMicrophone(deviceId) {
    if (!deviceId) return;
    const media = UNILIS_MEETING.MediaManager;
    if (!media.localStream) return;

    try {
      const fresh = await navigator.mediaDevices.getUserMedia({
        audio: { deviceId: { exact: deviceId } },
        video: false,
      });
      const next = fresh.getAudioTracks()[0];
      if (!next) return;

      // Carry the mute state across, or switching device would quietly unmute
      // somebody who had deliberately muted themselves.
      next.enabled = media.audioEnabled;

      const previous = media.localStream.getAudioTracks()[0];
      if (previous) {
        previous.stop();
        media.localStream.removeTrack(previous);
      }
      media.localStream.addTrack(next);

      if (UNILIS_MEETING.WebRTCCore) {
        UNILIS_MEETING.WebRTCCore.replaceLocalStream(media.localStream);
      }
      UNILIS_MEETING.Notifications.show('Microphone switched', 'success');
    } catch (err) {
      UNILIS_MEETING.Notifications.show(
        'Could not switch microphone: ' + ((err && err.message) || 'unknown error'),
        'error'
      );
    }
  },

  /**
   * Read the device list. Labels are blank until getUserMedia has been granted
   * once, so this is called after joining rather than on load.
   */
  async loadDevices() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) return;
    try {
      const devices = await navigator.mediaDevices.enumerateDevices();
      this.devices = {
        cameras: devices.filter(d => d.kind === 'videoinput'),
        microphones: devices.filter(d => d.kind === 'audioinput'),
      };
    } catch (err) {
      console.error('Could not list devices:', err);
    }
  },

  _esc(text) {
    const div = document.createElement('div');
    div.textContent = (text === undefined || text === null) ? '' : text;
    return div.innerHTML;
  },
};
