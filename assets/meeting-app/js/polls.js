/**
 * UNILIS Meeting - Polls Module
 */
UNILIS_MEETING.Polls = {
  activePolls: [],
  render() {
    const room = UNILIS_MEETING.Room;
    let html = '';
    if (room.isHost()) {
      html += `<div class="sb-block">
        <h4 class="sb-subhead">Create a poll</h4>
        <input class="sb-field-input" id="pollQuestion" maxlength="500" placeholder="Question">
        <textarea class="sb-field-input" id="pollOptions" rows="4" placeholder="One option per line"></textarea>
        <button class="sb-btn sb-btn-primary sb-btn-block" onclick="UNILIS_MEETING.Polls.create()">Start poll</button>
      </div>`;
    }
    if (this.activePolls.length === 0) {
      return html + '<p style="color:var(--text-secondary);text-align:center;padding:20px;">No active polls</p>';
    }
    return html + this.activePolls.map(poll => `
      <div class="poll-card">
        <div class="poll-question">${this._esc(poll.question)}</div>
        ${poll.options.map((opt, i) => `
          <div class="poll-option" onclick="UNILIS_MEETING.Polls.vote('${this._esc(poll.poll_id)}', [${i}])">
            <span class="option-text">${this._esc(opt)}</span>
          </div>
        `).join('')}
        ${room.isHost() ? `<button class="sb-btn sb-btn-small" onclick="UNILIS_MEETING.Polls.close('${this._esc(poll.poll_id)}')">Close poll</button>` : ''}
      </div>
    `).join('');
  },
  create() {
    const question = (document.getElementById('pollQuestion') || {}).value || '';
    const options = ((document.getElementById('pollOptions') || {}).value || '')
      .split(/\r?\n/).map(value => value.trim()).filter(Boolean);
    if (!question.trim() || options.length < 2) {
      UNILIS_MEETING.Notifications.show('Enter a question and at least two options.', 'warning');
      return;
    }
    UNILIS_MEETING.signaling.send({ type: 'poll_create', question: question.trim(), options });
  },
  vote(pollId, options) {
    UNILIS_MEETING.signaling.send({ type: 'poll_vote', poll_id: pollId, options });
  },
  close(pollId) {
    UNILIS_MEETING.signaling.send({ type: 'poll_close', poll_id: pollId });
  },
  _esc(text) {
    const div = document.createElement('div');
    div.textContent = text == null ? '' : text;
    return div.innerHTML;
  },
};