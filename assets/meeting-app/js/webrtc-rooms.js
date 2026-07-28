/**
 * UNILIS Meeting - Room & Signaling Manager
 * Manages WebSocket signaling connection and room state.
 */
UNILIS_MEETING.Signaling = {
  ws: null,
  url: '',
  connected: false,
  reconnectTimer: null,
  onMessage: null,       // callback(message)
  onConnected: null,     // callback()
  onDisconnected: null,  // callback()

  connect(url) {
    this.url = url;
    this._doConnect();
  },

  _doConnect() {
    if (this.ws) {
      this.ws.close();
      this.ws = null;
    }

    const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
    const wsUrl = this.url.startsWith('ws') ? this.url : protocol + '//' + window.location.host + this.url;

    this.ws = new WebSocket(wsUrl);

    this.ws.onopen = () => {
      this.connected = true;
      if (this.onConnected) this.onConnected();
    };

    this.ws.onmessage = (event) => {
      try {
        const message = JSON.parse(event.data);
        if (this.onMessage) this.onMessage(message);
      } catch (err) {
        console.error('Invalid message:', err);
      }
    };

    this.ws.onclose = () => {
      this.connected = false;
      if (this.onDisconnected) this.onDisconnected();
      this._scheduleReconnect();
    };

    this.ws.onerror = () => {
      console.error('WebSocket error');
    };
  },

  _scheduleReconnect() {
    if (this.reconnectTimer) clearTimeout(this.reconnectTimer);
    this.reconnectTimer = setTimeout(() => this._doConnect(), 3000);
  },

  send(data) {
    if (!this.ws || this.ws.readyState !== WebSocket.OPEN) return;
    this.ws.send(JSON.stringify(data));
  },

  isOpen() {
    return this.ws && this.ws.readyState === WebSocket.OPEN;
  },

  join(meetingId, userId, role, displayName) {
    this.send({
      type: 'join',
      meeting_id: meetingId,
      user_id: userId,
      role: role,
      display_name: displayName,
    });
  },

  disconnect() {
    if (this.reconnectTimer) clearTimeout(this.reconnectTimer);
    if (this.ws) {
      this.ws.close();
      this.ws = null;
    }
    this.connected = false;
  },
};

/**
 * UNILIS Meeting - Room State Manager
 * Tracks participants, their states, and room metadata.
 */
UNILIS_MEETING.Room = {
  meetingId: 0,
  userId: 0,
  role: 'student',
  displayName: '',
  participants: [],       // Array of participant objects
  isLocked: false,
  isRecording: false,
  pinnedUserId: null,
  activeSpeakerId: null,
  onParticipantsChanged: null,  // callback(participants)
  onParticipantJoined: null,    // callback(participant)
  onParticipantLeft: null,      // callback(userId)
  onStateChanged: null,         // callback(state)

  /**
   * Room-wide settings, mirrored from the server's room_state broadcast.
   *
   * Held here rather than read out of the DOM so every panel answers "may I
   * share?" the same way. The defaults match the server's, so a client that has
   * not yet received a room_state does not briefly offer controls it will be
   * refused - it briefly hides ones it could have used, which is the harmless
   * direction to be wrong in.
   */
  state: {
    screen_share_policy: 'host_only',
    whiteboard_policy: 'host_only',
    chat_enabled: true,
    breakout_self_join: true,
    is_locked: false,
    breakouts: [],
    main_room_occupants: 0,
    raised_hands: [],
  },

  // The breakout this client is in; null is the main room.
  breakoutId: null,

  init(config) {
    this.meetingId = config.meeting_id;
    this.userId = config.user_id;
    this.role = config.role;
    this.displayName = config.display_name;
    this.participants = [];
    this.breakoutId = null;
  },

  updateState(state) {
    if (!state) return;
    this.state = Object.assign({}, this.state, state);
    this.isLocked = !!this.state.is_locked;
    if (this.onStateChanged) this.onStateChanged(this.state);
  },

  updateParticipants(participants) {
    const oldIds = new Set(this.participants.map(p => p.user_id));
    const newIds = new Set(participants.map(p => p.user_id));

    // Detect new participants
    participants.forEach(p => {
      if (!oldIds.has(p.user_id)) {
        if (this.onParticipantJoined) this.onParticipantJoined(p);
      }
    });

    // Detect removed participants
    oldIds.forEach(id => {
      if (!newIds.has(id)) {
        if (this.onParticipantLeft) this.onParticipantLeft(id);
      }
    });

    this.participants = participants;
    if (this.onParticipantsChanged) this.onParticipantsChanged(participants);
  },

  getParticipant(userId) {
    return this.participants.find(p => p.user_id === userId);
  },

  getLocalParticipant() {
    return this.getParticipant(this.userId);
  },

  isHost() {
    return this.role === 'lecturer';
  },

  getParticipantCount() {
    return this.participants.length;
  },

  pinUser(userId) {
    this.pinnedUserId = this.pinnedUserId === userId ? null : userId;
    return this.pinnedUserId;
  },

  setActiveSpeaker(userId) {
    this.activeSpeakerId = userId;
  },

  sendParticipantUpdate(updates) {
    UNILIS_MEETING.signaling.send({
      type: 'participant_update',
      ...updates,
    });
  },

  sendSpeaking(isSpeaking) {
    UNILIS_MEETING.signaling.send({
      type: 'speaking',
      is_speaking: isSpeaking,
    });
  },

  raiseHand() {
    const p = this.getLocalParticipant();
    if (!p) return;
    const raised = !p.hand_raised;
    this.sendParticipantUpdate({ hand_raised: raised });
    return raised;
  },

  handRaised() {
    const p = this.getLocalParticipant();
    return !!(p && p.hand_raised);
  },

  // ============================================================
  // Permissions
  //
  // The server decides; these read the answer it published. Every control that
  // needs a capability asks here rather than testing the role itself, so a grant
  // to one participant reaches every surface at once.
  // ============================================================

  /**
   * The effective answer for one participant, preferring what the server
   * computed. The local fallback only matters in the gap before the first
   * roster arrives.
   */
  _mayDo(userId, capability) {
    const p = this.getParticipant(userId);
    const field = capability === 'screen' ? 'may_share_screen' : 'may_whiteboard';
    if (p && typeof p[field] === 'boolean') return p[field];

    if (this.isHost()) return true;
    const policy = capability === 'screen'
      ? this.state.screen_share_policy
      : this.state.whiteboard_policy;
    return policy === 'everyone';
  },

  canShareScreen(userId) {
    return this._mayDo(userId === undefined ? this.userId : userId, 'screen');
  },

  canUseWhiteboard(userId) {
    return this._mayDo(userId === undefined ? this.userId : userId, 'whiteboard');
  },

  canChat() {
    return this.state.chat_enabled || this.isHost();
  },

  /**
   * Whether this client may move itself between breakout rooms. A host always
   * can; everyone else only while the host has left self-joining on.
   */
  canJoinBreakouts() {
    return this.isHost() || !!this.state.breakout_self_join;
  },

  // ============================================================
  // Breakout rooms
  // ============================================================

  /**
   * The participants this client shares a room with, which is the set it should
   * hold peer connections to.
   *
   * The server also filters signalling by breakout, so this is not the security
   * boundary - it is what stops the client offering to peers whose answers will
   * never arrive.
   */
  peersInMyRoom() {
    return this.participants.filter(
      p => p.user_id !== this.userId && (p.breakout_id || null) === this.breakoutId
    );
  },

  currentBreakout() {
    if (this.breakoutId === null) return null;
    return (this.state.breakouts || []).find(b => b.breakout_id === this.breakoutId) || null;
  },

  currentRoomName() {
    const breakout = this.currentBreakout();
    return breakout ? breakout.name : 'Main room';
  },

  raisedHands() {
    return this.state.raised_hands || [];
  },
};