/**
 * UNILIS Meeting - Core WebRTC Engine
 * Manages RTCPeerConnection lifecycle, ICE handling, and media tracks.
 */
const UNILIS_MEETING = window.UNILIS_MEETING || {};

UNILIS_MEETING.WebRTCCore = {
  config: null,
  peerConnections: new Map(),   // userId -> { pc, stream, muted }
  localStream: null,
  screenStream: null,
  onRemoteTrack: null,          // callback(userId, stream)
  onConnectionState: null,      // callback(userId, state)
  iceServers: [
    { urls: 'stun:stun.l.google.com:19302' },
    { urls: 'stun:stun1.l.google.com:19302' },
  ],

  init(config = {}) {
    this.config = config;
    if (config.iceServers) this.iceServers = config.iceServers;
    this.peerConnections = new Map();
  },

  /**
   * Create a new RTCPeerConnection for a remote participant.
   */
  createPeerConnection(userId, displayName, isHost = false) {
    if (this.peerConnections.has(userId)) {
      this.cleanupPeer(userId);
    }

    const pc = new RTCPeerConnection({ iceServers: this.iceServers });
    const entry = { pc, userId, displayName, isHost, stream: new MediaStream() };

    // ICE candidate handler
    pc.onicecandidate = (event) => {
      if (event.candidate) {
        this._sendSignal(userId, 'ice', event.candidate);
      }
    };

    // ICE state changes
    pc.oniceconnectionstatechange = () => {
      const state = pc.iceConnectionState;
      if (this.onConnectionState) this.onConnectionState(userId, state);
      if (state === 'failed' || state === 'disconnected') {
        // Attempt ICE restart
        setTimeout(() => this._tryICERestart(userId), 3000);
      }
    };

    // Track handler - receives remote media
    pc.ontrack = (event) => {
      const remoteStream = event.streams[0];
      if (remoteStream) {
        entry.stream = remoteStream;
        if (this.onRemoteTrack) this.onRemoteTrack(userId, remoteStream);
      }
    };

    // Add local tracks if available
    this._attachLocalTracks(pc);
    this._ensureRecvOnly(pc);

    this.peerConnections.set(userId, entry);
    return pc;
  },

  /**
   * Guarantee an m-line for each media kind we are not sending.
   *
   * Without a local track of a kind there is no transceiver for it, so a
   * participant who joined without a camera or microphone would neither send nor
   * receive that kind — they would sit in a silent, blank meeting. A recvonly
   * transceiver keeps them able to see and hear everyone else.
   */
  _ensureRecvOnly(pc) {
    if (typeof pc.addTransceiver !== 'function') return;

    ['audio', 'video'].forEach(kind => {
      const present = pc.getTransceivers().some(t => {
        const senderKind = t.sender && t.sender.track && t.sender.track.kind;
        const receiverKind = t.receiver && t.receiver.track && t.receiver.track.kind;
        return senderKind === kind || receiverKind === kind;
      });
      if (present) return;
      try {
        pc.addTransceiver(kind, { direction: 'recvonly' });
      } catch (err) {
        console.warn(`Could not add recvonly ${kind} transceiver:`, err);
      }
    });
  },

  /**
   * Attach all local media tracks to a peer connection.
   */
  _attachLocalTracks(pc) {
    if (!this.localStream) return;
    const senders = pc.getSenders();
    this.localStream.getTracks().forEach(track => {
      const existing = senders.find(s => s.track && s.track.kind === track.kind);
      if (existing) {
        existing.replaceTrack(track);
      } else {
        pc.addTrack(track, this.localStream);
      }
    });

    // Attach screen share track if active
    if (this.screenStream) {
      const screenTrack = this.screenStream.getVideoTracks()[0];
      if (screenTrack) {
        const existingScreen = senders.find(s => s.track && s.track.kind === 'video' && s.track.label !== screenTrack.label);
        if (existingScreen) {
          existingScreen.replaceTrack(screenTrack);
        } else if (!senders.find(s => s.track === screenTrack)) {
          pc.addTrack(screenTrack, this.screenStream);
        }
      }
    }
  },

  /**
   * Create and send an offer to a remote peer.
   */
  async createOffer(userId) {
    let entry = this.peerConnections.get(userId);
    if (!entry) {
      entry = { pc: this.createPeerConnection(userId, '', false), userId, stream: new MediaStream() };
    }
    const pc = entry.pc;
    try {
      const offer = await pc.createOffer({ offerToReceiveAudio: true, offerToReceiveVideo: true });
      await pc.setLocalDescription(offer);
      this._sendSignal(userId, 'offer', offer);
    } catch (err) {
      console.error('Create offer error:', err);
    }
  },

  /**
   * Handle an incoming offer from a remote peer.
   */
  async handleOffer(fromUserId, offer) {
    const pc = this._getOrCreatePC(fromUserId);
    try {
      await pc.setRemoteDescription(new RTCSessionDescription(offer));
      const answer = await pc.createAnswer();
      await pc.setLocalDescription(answer);
      this._sendSignal(fromUserId, 'answer', answer);
    } catch (err) {
      console.error('Handle offer error:', err);
    }
  },

  /**
   * Handle an incoming answer from a remote peer.
   */
  async handleAnswer(fromUserId, answer) {
    const entry = this.peerConnections.get(fromUserId);
    if (!entry) return;
    try {
      await entry.pc.setRemoteDescription(new RTCSessionDescription(answer));
    } catch (err) {
      console.error('Handle answer error:', err);
    }
  },

  /**
   * Handle an incoming ICE candidate.
   */
  async handleICE(fromUserId, candidate) {
    const entry = this.peerConnections.get(fromUserId);
    if (!entry) return;
    try {
      if (candidate && candidate.candidate) {
        await entry.pc.addIceCandidate(new RTCIceCandidate(candidate));
      }
    } catch (err) {
      console.error('Add ICE candidate error:', err);
    }
  },

  /**
   * Determine if this client should create the offer (lower userId creates).
   */
  shouldCreateOffer(remoteUserId) {
    const config = UNILIS_MEETING.config || {};
    return config.user_id < remoteUserId;
  },

  /**
   * Ensure a peer connection exists for the given user.
   */
  ensurePeer(userId, displayName = '', isHost = false) {
    if (this.peerConnections.has(userId)) return this.peerConnections.get(userId).pc;
    return this.createPeerConnection(userId, displayName, isHost);
  },

  /**
   * Clean up a peer connection.
   */
  cleanupPeer(userId) {
    const entry = this.peerConnections.get(userId);
    if (!entry) return;
    entry.pc.close();
    this.peerConnections.delete(userId);
  },

  /**
   * Clean up all peer connections.
   */
  cleanupAll() {
    this.peerConnections.forEach((entry) => entry.pc.close());
    this.peerConnections.clear();
  },

  /**
   * Replace local stream (e.g., after camera switch).
   */
  replaceLocalStream(newStream) {
    this.localStream = newStream;
    this.peerConnections.forEach(entry => this._attachLocalTracks(entry.pc));
  },

  // --- Private helpers ---

  _getOrCreatePC(userId) {
    let entry = this.peerConnections.get(userId);
    if (!entry) {
      const pc = this.createPeerConnection(userId, '', false);
      entry = this.peerConnections.get(userId);
    }
    return entry ? entry.pc : null;
  },

  _sendSignal(toUserId, signalType, payload) {
    if (!UNILIS_MEETING.signaling || !UNILIS_MEETING.signaling.isOpen()) return;
    UNILIS_MEETING.signaling.send({
      type: 'signal',
      signal_type: signalType,
      to_user_id: toUserId,
      payload: payload,
    });
  },

  async _tryICERestart(userId) {
    const entry = this.peerConnections.get(userId);
    if (!entry || entry.pc.connectionState === 'connected') return;
    try {
      const offer = await entry.pc.createOffer({ iceRestart: true });
      await entry.pc.setLocalDescription(offer);
      this._sendSignal(userId, 'offer', offer);
    } catch (err) {
      console.error('ICE restart failed:', err);
    }
  },
};