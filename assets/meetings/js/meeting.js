/**
 * UNILIS Meeting - Main Application Controller
 * Initializes all modules, handles signaling messages, and manages the UI lifecycle.
 */
(function () {
  const config = window.__MEETING_CONFIG__ || {};
  if (!config.meeting_id) {
    document.getElementById('app').innerHTML = '<div style="padding:40px;text-align:center;color:red;">Error: Meeting configuration not found.</div>';
    return;
  }

  // ============================================================
  // Store config globally
  // ============================================================
  UNILIS_MEETING.config = config;

  // ============================================================
  // Initialize Modules
  // ============================================================
  UNILIS_MEETING.ThemeManager.init();
  UNILIS_MEETING.Notifications.init();
  UNILIS_MEETING.WebRTCCore.init(config);
  UNILIS_MEETING.Room.init(config);

  let meetingTimer = null;
  let secondsElapsed = 0;

  // ============================================================
  // Build UI Shell
  // ============================================================
  function buildUI() {
    const app = document.getElementById('app');
    const isHost = config.role === 'lecturer';
    const displayName = config.display_name || 'User';

    app.innerHTML = `
      <!-- Top Bar -->
      <div class="meeting-top-bar">
        <div class="meeting-info">📅 ${escapeHtml(config.title)}</div>
        <div class="top-actions">
          <button id="themeToggle" title="Toggle theme">🌓</button>
          <button id="fullscreenToggle" title="Fullscreen">⛶</button>
        </div>
      </div>

      <!-- Main Layout -->
      <div class="meeting-layout">
        <!-- Stage (Video Area) -->
        <div class="meeting-stage" id="meetingStage">
          <!-- Lobby overlay (shown before joining) -->
          <div class="lobby-overlay" id="lobbyOverlay">
            <div class="lobby-card">
              <div class="lobby-avatar">${escapeHtml(displayName[0] || '?')}</div>
              <h2>${escapeHtml(config.title)}</h2>
              <p>Ready to join the meeting</p>
              <div class="lobby-info">
                <div class="info-row"><span class="label">Name</span><span class="value">${escapeHtml(displayName)}</span></div>
                <div class="info-row"><span class="label">Role</span><span class="value">${isHost ? 'Lecturer' : 'Student'}</span></div>
                <div class="info-row"><span class="label">Unit</span><span class="value">${escapeHtml(config.unit_name || '-')}</span></div>
                ${config.lecturer_name ? `<div class="info-row"><span class="label">Lecturer</span><span class="value">${escapeHtml(config.lecturer_name)}</span></div>` : ''}
              </div>
              <div class="lobby-actions">
                <button class="lobby-btn lobby-btn-primary" id="joinMeetingBtn">Join Now</button>
                <button class="lobby-btn lobby-btn-secondary" id="settingsBtn">⚙ Settings</button>
              </div>
            </div>
          </div>

          <!-- Video container -->
          <div class="video-container" id="videoContainer" style="display:none;">
            <div class="video-grid gallery-view" id="videoGrid"></div>
          </div>

          <!-- Captions overlay -->
          <div class="captions-overlay" id="captionsOverlay" style="display:none;"></div>

          <!-- Recording indicator -->
          <div class="recording-indicator" id="recordingIndicator" style="display:none;">
            <span class="rec-dot"></span> Recording
          </div>

          <!-- Bottom controls (shown after joining) -->
          <div class="meeting-float-controls" id="floatControls" style="display:none;">
            <button class="control-btn active" id="micBtn" title="Microphone (M)">🎤</button>
            <button class="control-btn active" id="camBtn" title="Camera (V)">📷</button>
            <button class="control-btn" id="shareBtn" title="Share Screen">🖥</button>
            <div class="control-divider"></div>
            <button class="control-btn" id="chatBtn" title="Chat (C)">💬<span class="badge-dot" id="chatBadge" style="display:none;"></span></button>
            <button class="control-btn" id="participantsBtn" title="Participants">👥</button>
            <button class="control-btn" id="handBtn" title="Raise Hand (H)">✋</button>
            <div class="control-divider"></div>
            <button class="control-btn" id="viewBtn" title="Change Layout">🔲</button>
            ${isHost ? `<button class="control-btn" id="recordBtn" title="Record">⏺</button>` : ''}
            <div class="control-divider"></div>
            <button class="control-btn danger" id="leaveBtn" title="Leave Meeting">📞</button>
            <span class="control-pill" id="timerDisplay" style="font-size:12px;color:var(--text-secondary);padding:4px 8px;">00:00</span>
          </div>
        </div>

        <!-- Sidebar -->
        <div class="meeting-sidebar hidden" id="meetingSidebar">
          <div class="sidebar-header">
            <h3 id="sidebarTitle">Participants</h3>
            <button class="close-sidebar" id="closeSidebar">✕</button>
          </div>
          <div class="sidebar-tabs" id="sidebarTabs">
            <button class="sidebar-tab active" data-tab="participants">👥 People</button>
            <button class="sidebar-tab" data-tab="chat">💬 Chat</button>
            <button class="sidebar-tab" data-tab="polls">📊 Polls</button>
            <button class="sidebar-tab" data-tab="whiteboard">📝 Board</button>
            <button class="sidebar-tab" data-tab="settings">⚙</button>
          </div>
          <div class="sidebar-content" id="sidebarContent"></div>
        </div>
      </div>
    `;

    UNILIS_MEETING.LayoutManager.init(document.getElementById('videoGrid'));
    UNILIS_MEETING.SidebarManager.init(document.getElementById('meetingSidebar'));
    UNILIS_MEETING.SidebarManager.onTabChange = handleSidebarTab;

    // Event handlers
    document.getElementById('joinMeetingBtn').addEventListener('click', joinMeeting);
    document.getElementById('settingsBtn').addEventListener('click', () => {
      document.getElementById('sidebarContent').innerHTML = UNILIS_MEETING.Settings.render();
      UNILIS_MEETING.SidebarManager.switchTab('settings');
    });
    document.getElementById('themeToggle').addEventListener('click', () => UNILIS_MEETING.ThemeManager.toggle());
    document.getElementById('fullscreenToggle').addEventListener('click', toggleFullscreen);
    document.getElementById('closeSidebar').addEventListener('click', () => UNILIS_MEETING.SidebarManager.close());

    // Control buttons
    document.getElementById('micBtn').addEventListener('click', toggleMic);
    document.getElementById('camBtn').addEventListener('click', toggleCam);
    document.getElementById('shareBtn').addEventListener('click', toggleShare);
    document.getElementById('chatBtn').addEventListener('click', () => UNILIS_MEETING.SidebarManager.switchTab('chat'));
    document.getElementById('participantsBtn').addEventListener('click', () => UNILIS_MEETING.SidebarManager.switchTab('participants'));
    document.getElementById('handBtn').addEventListener('click', toggleHand);
    document.getElementById('viewBtn').addEventListener('click', toggleView);
    document.getElementById('leaveBtn').addEventListener('click', leaveMeeting);
    if (isHost) {
      document.getElementById('recordBtn').addEventListener('click', toggleRecord);
    }

    // Sidebar tabs
    document.querySelectorAll('.sidebar-tab').forEach(tab => {
      tab.addEventListener('click', () => UNILIS_MEETING.SidebarManager.switchTab(tab.dataset.tab));
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', (e) => {
      if (e.target.tagName === 'TEXTAREA' || e.target.tagName === 'INPUT') return;
      if (e.key === 'm' || e.key === 'M') toggleMic();
      if (e.key === 'v' || e.key === 'V') toggleCam();
      if (e.key === 'd' || e.key === 'D') UNILIS_MEETING.SidebarManager.toggle();
      if (e.key === 'c' || e.key === 'C') UNILIS_MEETING.SidebarManager.switchTab('chat');
      if (e.key === 'h' || e.key === 'H') toggleHand();
    });
  }

  // ============================================================
  // Helper Functions
  // ============================================================
  function escapeHtml(text) {
    if (!text && text !== 0) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  // ============================================================
  // Meeting Join Flow
  // ============================================================
  async function joinMeeting() {
    document.getElementById('lobbyOverlay').style.display = 'none';
    document.getElementById('videoContainer').style.display = '';
    document.getElementById('floatControls').style.display = '';

    // Initialize WebRTC callbacks
    UNILIS_MEETING.WebRTCCore.onRemoteTrack = handleRemoteTrack;
    UNILIS_MEETING.WebRTCCore.onConnectionState = handleConnectionState;

    try {
      await UNILIS_MEETING.MediaManager.startMedia(true, true);
      UNILIS_MEETING.WebRTCCore.localStream = UNILIS_MEETING.MediaManager.localStream;

      // Show local video tile
      addLocalVideoTile();

      // Connect signaling
      UNILIS_MEETING.signaling = UNILIS_MEETING.Signaling;
      UNILIS_MEETING.signaling.onMessage = handleSignalingMessage;
      UNILIS_MEETING.signaling.onConnected = () => {
        UNILIS_MEETING.signaling.join(config.meeting_id, config.user_id, config.role, config.display_name);
        UNILIS_MEETING.Notifications.show('Connected to meeting', 'success');
      };
      UNILIS_MEETING.signaling.onDisconnected = () => {
        UNILIS_MEETING.Notifications.show('Connection lost. Reconnecting...', 'warning');
      };
      UNILIS_MEETING.signaling.connect(config.ws_signaling_url || '/ws/signaling');

      // Start meeting timer
      startTimer();

      // Open participants sidebar
      UNILIS_MEETING.SidebarManager.switchTab('participants');

    } catch (err) {
      console.error('Failed to join meeting:', err);
      UNILIS_MEETING.Notifications.show('Failed to access camera/microphone. Please check permissions.', 'error');
      document.getElementById('lobbyOverlay').style.display = '';
      document.getElementById('videoContainer').style.display = 'none';
      document.getElementById('floatControls').style.display = 'none';
    }
  }

  // ============================================================
  // Video Tiles
  // ============================================================
  function addLocalVideoTile() {
    const grid = document.getElementById('videoGrid');
    const tile = document.createElement('div');
    tile.className = 'video-tile';
    tile.id = `participant-${config.user_id}`;
    tile.innerHTML = `
      <div class="avatar-placeholder" id="avatar-${config.user_id}">
        <span class="avatar-text">${escapeHtml((config.display_name || '?')[0])}</span>
      </div>
      <video autoplay playsinline muted data-local="true" style="display:none;" id="localVideo"></video>
      <div class="tile-overlay">
        <span class="tile-name">${escapeHtml(config.display_name)} (You)</span>
        <div class="tile-badges">
          <span class="tile-badge ${config.role}">${config.role === 'lecturer' ? 'L' : 'S'}</span>
        </div>
      </div>
    `;
    grid.appendChild(tile);

    const video = tile.querySelector('video');
    const stream = UNILIS_MEETING.MediaManager.localStream;
    if (stream && video) {
      video.srcObject = stream;
      video.style.display = '';
      const placeholder = document.getElementById(`avatar-${config.user_id}`);
      if (placeholder) placeholder.style.display = 'none';
    }
  }

  function addRemoteVideoTile(userId, displayName, stream) {
    const grid = document.getElementById('videoGrid');
    if (document.getElementById(`participant-${userId}`)) return;

    const room = UNILIS_MEETING.Room;
    const p = room.getParticipant(userId);
    const initial = (displayName || '?')[0].toUpperCase();
    const roleClass = p ? p.role : 'student';

    const tile = document.createElement('div');
    tile.className = 'video-tile';
    tile.id = `participant-${userId}`;
    tile.innerHTML = `
      <div class="avatar-placeholder" id="avatar-${userId}">
        <span class="avatar-text">${escapeHtml(initial)}</span>
      </div>
      <video autoplay playsinline style="display:none;" id="remoteVideo-${userId}"></video>
      <div class="tile-overlay">
        <span class="tile-name">${escapeHtml(displayName)}</span>
        <div class="tile-badges">
          <span class="tile-badge ${roleClass}">${roleClass === 'lecturer' ? 'L' : 'S'}</span>
        </div>
      </div>
    `;
    grid.appendChild(tile);

    const video = tile.querySelector('video');
    if (stream && video) {
      video.srcObject = stream;
      video.style.display = '';
      const placeholder = document.getElementById(`avatar-${userId}`);
      if (placeholder) placeholder.style.display = 'none';
    }
  }

  function removeVideoTile(userId) {
    const tile = document.getElementById(`participant-${userId}`);
    if (tile) tile.remove();
  }

  // ============================================================
  // Signaling Message Handler
  // ============================================================
  function handleSignalingMessage(message) {
    switch (message.type) {
      case 'joined':
        UNILIS_MEETING.Room.updateParticipants(message.participants || []);
        handlePeerConnections(message.participants || []);
        break;

      case 'participants':
        UNILIS_MEETING.Room.updateParticipants(message.participants || []);
        updateParticipantsUI(message.participants);
        handlePeerConnections(message.participants || []);
        break;

      case 'signal':
        handleWebRTCSignal(message);
        break;

      case 'chat_message':
        UNILIS_MEETING.Chat.addMessage(message);
        updateChatBadge();
        break;

      case 'chat_history':
        UNILIS_MEETING.Chat.loadHistory(message.messages);
        break;

      case 'chat_deleted':
        // Reload or hide deleted message
        break;

      case 'poll_created':
        UNILIS_MEETING.Polls.activePolls.push(message);
        if (UNILIS_MEETING.SidebarManager.currentTab === 'polls') updatePollsUI();
        break;

      case 'poll_closed':
        UNILIS_MEETING.Polls.activePolls = UNILIS_MEETING.Polls.activePolls.filter(p => p.poll_id !== message.poll_id);
        if (UNILIS_MEETING.SidebarManager.currentTab === 'polls') updatePollsUI();
        break;

      case 'poll_updated':
        // Update poll results display
        break;

      case 'recording_started':
        UNILIS_MEETING.Room.isRecording = true;
        document.getElementById('recordingIndicator').style.display = '';
        UNILIS_MEETING.Notifications.show('Recording started', 'info');
        break;

      case 'recording_stopped':
        UNILIS_MEETING.Room.isRecording = false;
        document.getElementById('recordingIndicator').style.display = 'none';
        UNILIS_MEETING.Notifications.show('Recording stopped', 'info');
        break;

      case 'host_action':
        if (message.action === 'mute') {
          UNILIS_MEETING.MediaManager.toggleAudio();
          updateMicUI();
          UNILIS_MEETING.Notifications.show('You have been muted by the host', 'warning');
        } else if (message.action === 'removed') {
          UNILIS_MEETING.Notifications.show('You have been removed from the meeting', 'error');
          setTimeout(() => { window.location.href = config.back_url || '/'; }, 2000);
        }
        break;

      case 'meeting_locked':
        UNILIS_MEETING.Room.isLocked = message.locked;
        break;

      case 'speaking':
        const tile = document.getElementById(`participant-${message.user_id}`);
        if (tile) tile.classList.toggle('active-speaker', message.is_speaking);
        break;

      case 'typing':
        const indicator = document.getElementById('typingIndicator');
        if (indicator) indicator.textContent = message.is_typing ? 'Someone is typing...' : '';
        break;

      case 'error':
        UNILIS_MEETING.Notifications.show(message.message || 'An error occurred', 'error');
        break;
    }
  }

  // ============================================================
  // WebRTC Peer Connection Management
  // ============================================================
  function handlePeerConnections(participants) {
    const remoteParticipants = participants.filter(p => p.user_id !== config.user_id);
    const remoteIds = new Set(remoteParticipants.map(p => p.user_id));

    // Create peer connections for new participants
    remoteParticipants.forEach(async p => {
      if (!UNILIS_MEETING.WebRTCCore.peerConnections.has(p.user_id)) {
        UNILIS_MEETING.WebRTCCore.ensurePeer(p.user_id, p.display_name, p.role === 'lecturer');
        if (UNILIS_MEETING.WebRTCCore.shouldCreateOffer(p.user_id)) {
          await UNILIS_MEETING.WebRTCCore.createOffer(p.user_id);
        }
      }
    });

    // Clean up disconnected participants
    UNILIS_MEETING.WebRTCCore.peerConnections.forEach((entry, userId) => {
      if (!remoteIds.has(userId)) {
        UNILIS_MEETING.WebRTCCore.cleanupPeer(userId);
        removeVideoTile(userId);
      }
    });
  }

  // ============================================================
  // WebRTC Signaling Handler
  // ============================================================
  function handleWebRTCSignal(message) {
    const fromId = message.from_user_id;
    if (fromId === config.user_id) return;

    const room = UNILIS_MEETING.Room;
    const p = room.getParticipant(fromId);
    const displayName = p ? p.display_name : `User ${fromId}`;

    // Ensure we have a peer connection for this user
    UNILIS_MEETING.WebRTCCore.ensurePeer(fromId, displayName, p ? p.role === 'lecturer' : false);

    switch (message.signal_type) {
      case 'offer':
        UNILIS_MEETING.WebRTCCore.handleOffer(fromId, message.payload);
        break;
      case 'answer':
        UNILIS_MEETING.WebRTCCore.handleAnswer(fromId, message.payload);
        break;
      case 'ice':
        UNILIS_MEETING.WebRTCCore.handleICE(fromId, message.payload);
        break;
    }
  }

  // ============================================================
  // Remote Track & Connection State Handlers
  // ============================================================
  function handleRemoteTrack(userId, stream) {
    const room = UNILIS_MEETING.Room;
    const p = room.getParticipant(userId);
    const displayName = p ? p.display_name : `User ${userId}`;
    addRemoteVideoTile(userId, displayName, stream);
  }

  function handleConnectionState(userId, state) {
    if (state === 'disconnected' || state === 'failed') {
      // Connection lost - keep tile but it will show avatar placeholder
      const video = document.getElementById(`remoteVideo-${userId}`);
      if (video) video.style.display = 'none';
    }
  }

  // ============================================================
  // UI Updates
  // ============================================================
  function updateParticipantsUI(participants) {
    UNILIS_MEETING.LayoutManager.updateTileCount(participants.length);

    if (UNILIS_MEETING.SidebarManager.currentTab === 'participants') {
      const html = UNILIS_MEETING.ParticipantPanel.render(participants, config.role === 'lecturer');
      document.getElementById('sidebarContent').innerHTML = html;
      document.getElementById('sidebarTitle').textContent = `Participants (${participants.length})`;
    }
  }

  function handleSidebarTab(tab) {
    const content = document.getElementById('sidebarContent');
    switch (tab) {
      case 'participants':
        document.getElementById('sidebarTitle').textContent = 'Participants';
        content.innerHTML = UNILIS_MEETING.ParticipantPanel.render(
          UNILIS_MEETING.Room.participants,
          config.role === 'lecturer'
        );
        break;
      case 'chat':
        document.getElementById('sidebarTitle').textContent = 'Chat';
        content.innerHTML = UNILIS_MEETING.Chat.render();
        UNILIS_MEETING.Chat.unreadCount = 0;
        updateChatBadge();
        setTimeout(() => {
          const input = document.getElementById('chatInput');
          if (input) {
            input.addEventListener('input', () => {
              UNILIS_MEETING.signaling.send({ type: 'typing', is_typing: input.value.length > 0 });
            });
            input.focus();
          }
        }, 100);
        break;
      case 'polls':
        document.getElementById('sidebarTitle').textContent = 'Polls';
        updatePollsUI();
        break;
      case 'whiteboard':
        document.getElementById('sidebarTitle').textContent = 'Whiteboard';
        content.innerHTML = UNILIS_MEETING.Whiteboard.render();
        break;
      case 'settings':
        document.getElementById('sidebarTitle').textContent = 'Settings';
        content.innerHTML = UNILIS_MEETING.Settings.render();
        break;
    }
  }

  function updatePollsUI() {
    document.getElementById('sidebarContent').innerHTML = UNILIS_MEETING.Polls.render();
  }

  function updateChatBadge() {
    const badge = document.getElementById('chatBadge');
    if (badge) {
      badge.style.display = UNILIS_MEETING.Chat.unreadCount > 0 && UNILIS_MEETING.SidebarManager.currentTab !== 'chat' ? '' : 'none';
    }
  }

  // ============================================================
  // Control Toggles
  // ============================================================
  function toggleMic() {
    const enabled = UNILIS_MEETING.MediaManager.toggleAudio();
    updateMicUI();
    UNILIS_MEETING.Room.sendParticipantUpdate({ audio_enabled: enabled });
  }

  function updateMicUI() {
    const btn = document.getElementById('micBtn');
    if (!btn) return;
    const enabled = UNILIS_MEETING.MediaManager.audioEnabled;
    btn.classList.toggle('active', enabled);
    btn.innerHTML = enabled ? '🎤' : '🔇';
    btn.title = enabled ? 'Mute (M)' : 'Unmute (M)';
  }

  function toggleCam() {
    const enabled = UNILIS_MEETING.MediaManager.toggleVideo();
    updateCamUI();
    UNILIS_MEETING.Room.sendParticipantUpdate({ video_enabled: enabled });
  }

  function updateCamUI() {
    const btn = document.getElementById('camBtn');
    if (!btn) return;
    const enabled = UNILIS_MEETING.MediaManager.videoEnabled;
    btn.classList.toggle('active', enabled);
    btn.innerHTML = enabled ? '📷' : '🚫';
    btn.title = enabled ? 'Camera Off (V)' : 'Camera On (V)';
  }

  function toggleShare() {
    if (UNILIS_MEETING.ScreenShare.isSharing) {
      UNILIS_MEETING.ScreenShare.stop();
      document.getElementById('shareBtn').classList.remove('active');
    } else {
      UNILIS_MEETING.ScreenShare.start().then(success => {
        document.getElementById('shareBtn').classList.toggle('active', success);
      });
    }
  }

  function toggleHand() {
    const raised = UNILIS_MEETING.Room.raiseHand();
    document.getElementById('handBtn').classList.toggle('active', raised);
  }

  function toggleView() {
    const view = UNILIS_MEETING.LayoutManager.toggleView();
    UNILIS_MEETING.Notifications.show(`Switched to ${view} view`, 'info');
  }

  function toggleRecord() {
    if (UNILIS_MEETING.Room.isRecording) {
      UNILIS_MEETING.Recording.stop();
      document.getElementById('recordBtn').classList.remove('active');
    } else {
      UNILIS_MEETING.Recording.start();
      document.getElementById('recordBtn').classList.add('active');
    }
  }

  function toggleFullscreen() {
    if (!document.fullscreenElement) {
      document.documentElement.requestFullscreen().catch(() => {});
    } else {
      document.exitFullscreen().catch(() => {});
    }
  }

  // ============================================================
  // Timer
  // ============================================================
  function startTimer() {
    meetingTimer = setInterval(() => {
      secondsElapsed++;
      const mins = String(Math.floor(secondsElapsed / 60)).padStart(2, '0');
      const secs = String(secondsElapsed % 60).padStart(2, '0');
      document.getElementById('timerDisplay').textContent = `${mins}:${secs}`;
    }, 1000);
  }

  // ============================================================
  // Leave Meeting
  // ============================================================
  function leaveMeeting() {
    if (!confirm('Are you sure you want to leave the meeting?')) return;

    // Stop timer
    if (meetingTimer) clearInterval(meetingTimer);

    // Disconnect signaling
    if (UNILIS_MEETING.signaling) UNILIS_MEETING.signaling.disconnect();

    // Clean up WebRTC
    UNILIS_MEETING.WebRTCCore.cleanupAll();

    // Stop media
    UNILIS_MEETING.MediaManager.stopAll();

    // Navigate back
    window.location.href = config.back_url || '/';
  }

  // ============================================================
  // Initialize
  // ============================================================
  buildUI();
})();