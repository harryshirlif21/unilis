/**
 * UNILIS Meeting - Screen Sharing
 * Depends on UNILIS_MEETING.MediaManager
 */
UNILIS_MEETING.ScreenShare = {
  isSharing: false,
  async start() {
    try {
      await UNILIS_MEETING.MediaManager.startScreenShare();
      this.isSharing = true;
      UNILIS_MEETING.Notifications.show('Screen sharing started', 'success');
      return true;
    } catch {
      UNILIS_MEETING.Notifications.show('Screen share failed', 'error');
      return false;
    }
  },
  stop() {
    UNILIS_MEETING.MediaManager.stopScreenShare();
    this.isSharing = false;
    UNILIS_MEETING.Notifications.show('Screen sharing stopped', 'info');
  },
  toggle() {
    return this.isSharing ? this.stop() : this.start();
  },
};