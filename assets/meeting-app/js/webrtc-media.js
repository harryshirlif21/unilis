/**
 * UNILIS Meeting - Media Device Management
 * Handles camera, microphone, screen capture, and device switching.
 */
UNILIS_MEETING.MediaManager = {
  localStream: null,
  screenStream: null,
  audioEnabled: true,
  videoEnabled: true,
  screenSharing: false,

  // What the browser actually granted: 'full' | 'audio-only' | 'video-only' | 'none'.
  mode: 'none',
  lastError: null,

  _constraints(video, audio) {
    return {
      audio: audio ? { echoCancellation: true, noiseSuppression: true } : false,
      video: video ? { width: { ideal: 1280 }, height: { ideal: 720 }, frameRate: { ideal: 30 } } : false,
    };
  },

  /**
   * Request camera and microphone access, narrowing the request until something
   * works. A machine without a webcam rejects a combined audio+video request with
   * NotFoundError even though the microphone is perfectly usable, so asking for
   * both and giving up would lock those users out of the meeting entirely.
   *
   * Returns the stream, or null when no device could be opened at all — the
   * caller can still join to watch and listen.
   */
  async startMedia(video = true, audio = true) {
    // Cleared once here, not on success: after a fallback this still holds the
    // error that caused the downgrade, which is what the user needs to be told.
    this.lastError = null;

    const attempts = [];
    if (video && audio) attempts.push({ video: true, audio: true, mode: 'full' });
    if (audio) attempts.push({ video: false, audio: true, mode: 'audio-only' });
    if (video) attempts.push({ video: true, audio: false, mode: 'video-only' });

    for (const attempt of attempts) {
      try {
        const stream = await navigator.mediaDevices.getUserMedia(
          this._constraints(attempt.video, attempt.audio)
        );
        this.localStream = stream;
        this.mode = attempt.mode;
        this.audioEnabled = stream.getAudioTracks().length > 0;
        this.videoEnabled = stream.getVideoTracks().length > 0;
        return stream;
      } catch (err) {
        this.lastError = err;
        console.warn(`Media unavailable (${attempt.mode}):`, err && err.name ? err.name : err);
      }
    }

    this.localStream = null;
    this.mode = 'none';
    this.audioEnabled = false;
    this.videoEnabled = false;
    return null;
  },

  /**
   * Message describing why media is missing or reduced, for the UI to surface.
   * Returns null when everything was granted.
   */
  describeLimitation() {
    const name = this.lastError && this.lastError.name;
    const cause = name === 'NotAllowedError'
      ? 'permission was blocked'
      : name === 'NotFoundError'
        ? 'no device was found'
        : name === 'NotReadableError'
          ? 'the device is in use by another app'
          : null;

    switch (this.mode) {
      case 'audio-only':
        return `Joined with microphone only — camera unavailable${cause ? ` (${cause})` : ''}.`;
      case 'video-only':
        return `Joined with camera only — microphone unavailable${cause ? ` (${cause})` : ''}.`;
      case 'none':
        return `Joined in view-only mode — no camera or microphone available${cause ? ` (${cause})` : ''}. You can still see, hear, and chat.`;
      default:
        return null;
    }
  },

  hasAudio() {
    return !!(this.localStream && this.localStream.getAudioTracks().length);
  },

  hasVideo() {
    return !!(this.localStream && this.localStream.getVideoTracks().length);
  },

  /**
   * Toggle microphone on/off. Returns false when there is no track to toggle.
   */
  toggleAudio() {
    const tracks = this.localStream ? this.localStream.getAudioTracks() : [];
    if (!tracks.length) return false;
    this.audioEnabled = !this.audioEnabled;
    tracks.forEach(track => {
      track.enabled = this.audioEnabled;
    });
    return this.audioEnabled;
  },

  /**
   * Toggle camera on/off. Returns false when there is no track to toggle.
   */
  toggleVideo() {
    const tracks = this.localStream ? this.localStream.getVideoTracks() : [];
    if (!tracks.length) return false;
    this.videoEnabled = !this.videoEnabled;
    tracks.forEach(track => {
      track.enabled = this.videoEnabled;
    });
    return this.videoEnabled;
  },

  /**
   * Switch camera device.
   */
  async switchCamera(deviceId) {
    if (!this.localStream) return;
    const audioTracks = this.localStream.getAudioTracks();
    try {
      const newStream = await navigator.mediaDevices.getUserMedia({
        audio: audioTracks.length > 0,
        video: { deviceId: { exact: deviceId }, width: { ideal: 1280 }, height: { ideal: 720 } },
      });
      const oldVideoTrack = this.localStream.getVideoTracks()[0];
      if (oldVideoTrack) oldVideoTrack.stop();
      const newVideoTrack = newStream.getVideoTracks()[0];
      this.localStream.addTrack(newVideoTrack);
      this.localStream.removeTrack(oldVideoTrack);
      if (UNILIS_MEETING.WebRTCCore) {
        UNILIS_MEETING.WebRTCCore.replaceLocalStream(this.localStream);
      }
    } catch (err) {
      console.error('Camera switch failed:', err);
    }
  },

  /**
   * Enable/disable background blur using CSS filter.
   */
  setBackgroundBlur(enabled) {
    const videoEl = document.querySelector('video[data-local="true"]');
    if (videoEl) {
      videoEl.style.filter = enabled ? 'blur(8px)' : 'none';
      videoEl.style.backdropFilter = enabled ? 'blur(8px)' : 'none';
    }
  },

  /**
   * Request screen sharing.
   */
  async startScreenShare() {
    try {
      const stream = await navigator.mediaDevices.getDisplayMedia({
        video: { displaySurface: 'monitor' },
        audio: true,
      });
      this.screenStream = stream;
      this.screenSharing = true;

      const screenTrack = stream.getVideoTracks()[0];
      screenTrack.onended = () => this.stopScreenShare();

      // Replace video track in all peer connections
      if (UNILIS_MEETING.WebRTCCore) {
        UNILIS_MEETING.WebRTCCore.screenStream = stream;
        UNILIS_MEETING.WebRTCCore.peerConnections.forEach(entry => {
          UNILIS_MEETING.WebRTCCore._attachLocalTracks(entry.pc);
        });
      }
      return stream;
    } catch (err) {
      console.error('Screen share failed:', err);
      throw err;
    }
  },

  /**
   * Stop screen sharing.
   */
  stopScreenShare() {
    if (!this.screenStream) return;
    this.screenStream.getTracks().forEach(track => track.stop());
    this.screenStream = null;
    this.screenSharing = false;

    if (UNILIS_MEETING.WebRTCCore) {
      UNILIS_MEETING.WebRTCCore.screenStream = null;
      // Restore camera track
      if (this.localStream) {
        UNILIS_MEETING.WebRTCCore.replaceLocalStream(this.localStream);
      }
    }
  },

  /**
   * Stop all media tracks.
   */
  stopAll() {
    if (this.localStream) {
      this.localStream.getTracks().forEach(t => t.stop());
      this.localStream = null;
    }
    if (this.screenStream) {
      this.screenStream.getTracks().forEach(t => t.stop());
      this.screenStream = null;
    }
    this.screenSharing = false;
  },

  /**
   * Get list of available video/audio devices.
   */
  async getDevices() {
    try {
      const devices = await navigator.mediaDevices.enumerateDevices();
      return {
        audioInput: devices.filter(d => d.kind === 'audioinput'),
        audioOutput: devices.filter(d => d.kind === 'audiooutput'),
        videoInput: devices.filter(d => d.kind === 'videoinput'),
      };
    } catch {
      return { audioInput: [], audioOutput: [], videoInput: [] };
    }
  },

  /**
   * Get the current audio level (0-1).
   */
  getAudioLevel() {
    // Simplified - returns a simulated level based on track enabled state
    if (!this.localStream || !this.audioEnabled) return 0;
    const audioTrack = this.localStream.getAudioTracks()[0];
    return audioTrack && audioTrack.enabled ? 0.5 : 0;
  },
};