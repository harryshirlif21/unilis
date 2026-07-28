/**
 * UNILIS Meeting - Screen Sharing
 *
 * Depends on UNILIS_MEETING.MediaManager for the capture itself. What this adds
 * is the permission step: whether a participant may present is the host's
 * decision, and asking the browser for the screen before checking would put a
 * picker in front of someone who is about to be refused.
 *
 * The check here is a courtesy, not the control. The server rejects a
 * screen_sharing flag it has not authorised, so a participant who bypassed this
 * would capture their own screen and send it to nobody.
 */
UNILIS_MEETING.ScreenShare = {
  isSharing: false,

  // Set while a request to the host is outstanding, so pressing the button
  // again asks again rather than appearing to do nothing.
  pending: false,

  async start() {
    const room = UNILIS_MEETING.Room;

    if (!room.canShareScreen()) {
      this.request();
      return false;
    }

    // Two people presenting at once makes the stage flip between them, and
    // whoever loses is left thinking their share failed. A host may take over,
    // because sometimes that is the point.
    const other = (room.participants || []).find(
      p => p.screen_sharing && p.user_id !== room.userId
    );
    if (other && !room.isHost()) {
      UNILIS_MEETING.Notifications.show(
        (other.display_name || 'Someone') + ' is presenting. Ask them to stop first.',
        'warning'
      );
      return false;
    }

    try {
      await UNILIS_MEETING.MediaManager.startScreenShare();
      this.isSharing = true;
      this.pending = false;
      UNILIS_MEETING.Notifications.show('You are presenting', 'success');
      return true;
    } catch (err) {
      // A cancelled picker is not a failure worth shouting about: the person
      // pressed Escape or Cancel on purpose.
      const cancelled = err && (err.name === 'NotAllowedError' || err.name === 'AbortError');
      if (!cancelled) {
        UNILIS_MEETING.Notifications.show(
          'Screen sharing did not start: ' + ((err && err.message) || 'unknown error'),
          'error'
        );
      }
      return false;
    }
  },

  stop() {
    UNILIS_MEETING.MediaManager.stopScreenShare();
    this.isSharing = false;
    UNILIS_MEETING.Notifications.show('You stopped presenting', 'info');
  },

  toggle() {
    if (this.isSharing || UNILIS_MEETING.MediaManager.screenSharing) {
      this.stop();
      return false;
    }
    return this.start();
  },

  /** Ask the host for the screen. */
  request() {
    if (!UNILIS_MEETING.signaling) return;
    UNILIS_MEETING.signaling.send({ type: 'request_permission', capability: 'screen' });
    this.pending = true;
    UNILIS_MEETING.Notifications.show('Asked the host to let you share your screen', 'info');
  },

  /**
   * Called when the host's answer arrives.
   *
   * A grant does not start the share by itself. The browser only opens its
   * screen picker from a user gesture, and a socket message is not one - so the
   * participant is told to press the button again, now that it will work.
   */
  onPermissionChanged(granted, by) {
    this.pending = false;

    if (granted) {
      UNILIS_MEETING.Notifications.show(
        (by ? by + ' allowed you' : 'You are now allowed') + ' to share — press Present when ready',
        'success'
      );
      return;
    }

    if (UNILIS_MEETING.MediaManager.screenSharing) this.stop();
    UNILIS_MEETING.Notifications.show(
      (by ? by + ' has' : 'The host has') + ' withdrawn screen sharing',
      'warning'
    );
  },
};
