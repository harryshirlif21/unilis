/**
 * UNILIS Meeting - Recording Module
 */
UNILIS_MEETING.Recording = {
  isRecording: false,
  start() {
    UNILIS_MEETING.signaling.send({ type: 'recording_start' });
    this.isRecording = true;
  },
  stop() {
    UNILIS_MEETING.signaling.send({ type: 'recording_stop' });
    this.isRecording = false;
  },
  toggle() {
    this.isRecording ? this.stop() : this.start();
  },
};