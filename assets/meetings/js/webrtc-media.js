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

  /**
   * Request camera and microphone access.
   */
  async startMedia(video = true, audio = true) {
    try {
      const constraints = {
        audio: audio ? { echoCancellation: true, noiseSuppression: true } : false,
        video: video ? { width: { ideal: 1280 }, height: { ideal: 720 }, frameRate: { ideal: 30 } } : false,
      };
      const stream = await navigator.mediaDevices.getUserMedia(constraints);
      this.localStream = stream;
      return stream;
    } catch (err) {
      console.error('Media access denied:', err);
      throw err;
    }
  },

  /**
   * Toggle microphone on/off.
   */
  toggleAudio() {
    if (!this.localStream) return false;
    this.audioEnabled = !this.audioEnabled;
    this.localStream.getAudioTracks().forEach(track => {
      track.enabled = this.audioEnabled;
    });
    return this.audioEnabled;
  },

  /**
   * Toggle camera on/off.
   */
  toggleVideo() {
    if (!this.localStream) return false;
    this.videoEnabled = !this.videoEnabled;
    this.localStream.getVideoTracks().forEach(track => {
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