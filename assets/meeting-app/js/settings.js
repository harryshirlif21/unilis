/**
 * UNILIS Meeting - Settings Module
 */
UNILIS_MEETING.Settings = {
  render() {
    const config = UNILIS_MEETING.config || {};
    return `<h3 style="margin-bottom:16px;">Meeting Settings</h3>
      <div class="participant-item"><span>Meeting ID</span><span>${config.meeting_id}</span></div>
      <div class="participant-item"><span>Your Role</span><span>${config.role}</span></div>
      <div class="participant-item"><span>Duration</span><span>${config.duration} min</span></div>
      <div class="participant-item"><span>Scheduled</span><span>${config.scheduled_time || 'Now'}</span></div>
      ${config.back_url ? `<div style="margin-top:16px;"><a href="${config.back_url}" class="lobby-btn lobby-btn-secondary" style="text-decoration:none;display:block;text-align:center;">Back to Dashboard</a></div>` : ''}
    `;
  },
};