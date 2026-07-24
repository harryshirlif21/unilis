/**
 * UNILIS Meeting - Polls Module
 */
UNILIS_MEETING.Polls = {
  activePolls: [],
  render() {
    if (this.activePolls.length === 0) return '<p style="color:var(--text-secondary);text-align:center;padding:20px;">No active polls</p>';
    return this.activePolls.map(poll => `
      <div class="poll-card">
        <div class="poll-question">${poll.question}</div>
        ${poll.options.map((opt, i) => `
          <div class="poll-option" onclick="UNILIS_MEETING.Polls.vote('${poll.poll_id}', [${i}])">
            <span class="option-text">${opt}</span>
          </div>
        `).join('')}
      </div>
    `).join('');
  },
  vote(pollId, options) {
    UNILIS_MEETING.signaling.send({ type: 'poll_vote', poll_id: pollId, options });
  },
};