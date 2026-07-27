/**
 * UNILIS Meeting - Chat Module
 */
UNILIS_MEETING.Chat = {
  messages: [],
  unreadCount: 0,
  onUnreadChange: null,

  render(messages) {
    if (messages) this.messages = messages;
    const config = UNILIS_MEETING.config || {};
    let html = '<div class="chat-messages">';
    this.messages.forEach(msg => {
      if (msg.type !== 'chat_message') return;
      const own = msg.sender_id === config.user_id;
      const time = new Date(msg.timestamp * 1000).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
      html += `
        <div class="chat-message ${own ? 'own' : ''} ${msg.deleted ? 'deleted' : ''}">
          ${msg.reply_to ? `<div class="msg-reply">${this._esc(msg.reply_text || '')}</div>` : ''}
          <div class="msg-sender">${this._esc(msg.sender_name)}</div>
          <div class="msg-text">${msg.deleted ? 'Message deleted' : this._esc(msg.text)}</div>
          <div class="msg-time">${time}</div>
        </div>`;
    });
    html += '</div>';
    html += '<div class="chat-typing-indicator" id="typingIndicator"></div>';
    html += `
      <div class="chat-input-area">
        <textarea id="chatInput" placeholder="Send a message..." rows="1" 
          onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();UNILIS_MEETING.Chat.send()}"></textarea>
        <button class="chat-send-btn" onclick="UNILIS_MEETING.Chat.send()">➤</button>
      </div>`;
    return html;
  },

  send() {
    const input = document.getElementById('chatInput');
    if (!input) return;
    const text = input.value.trim();
    if (!text) return;
    UNILIS_MEETING.signaling.send({ type: 'chat_message', text });
    input.value = '';
    input.style.height = 'auto';
  },

  addMessage(msg) {
    this.messages.push(msg);
    this.unreadCount++;
    if (this.onUnreadChange) this.onUnreadChange(this.unreadCount);
    const container = document.querySelector('.chat-messages');
    if (container) {
      const own = msg.sender_id === (UNILIS_MEETING.config || {}).user_id;
      const time = new Date(msg.timestamp * 1000).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
      const el = document.createElement('div');
      el.className = `chat-message ${own ? 'own' : ''}`;
      el.innerHTML = `<div class="msg-sender">${this._esc(msg.sender_name)}</div><div class="msg-text">${this._esc(msg.text)}</div><div class="msg-time">${time}</div>`;
      container.appendChild(el);
      container.scrollTop = container.scrollHeight;
    }
  },

  loadHistory(messages) {
    this.messages = messages || [];
    this.unreadCount = 0;
  },

  _esc(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  },
};