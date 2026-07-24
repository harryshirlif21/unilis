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

  init(config) {
    this.meetingId = config.meeting_id;
    this.userId = config.user_id;
    this.role = config.role;
    this.displayName = config.display_name;
    this.participants = [];
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
};