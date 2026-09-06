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

  /**
   * How a role is written out, and its one-letter tile badge.
   *
   * 'guest' is a real role here: someone who joined through a public guest link
   * and has no UNILIS account. Showing them as "Student" would tell the host the
   * opposite of what is true.
   */
  function roleLabel(role) {
    return role === 'lecturer' ? 'Lecturer' : role === 'guest' ? 'Guest' : 'Student';
  }

  function roleInitial(role) {
    return role === 'lecturer' ? 'L' : role === 'guest' ? 'G' : 'S';
  }

  const isHost = config.role === 'lecturer';

  // ============================================================
  // Initialize Modules
  // ============================================================
  UNILIS_MEETING.ThemeManager.init();
  UNILIS_MEETING.Notifications.init();
  UNILIS_MEETING.WebRTCCore.init(config);
  UNILIS_MEETING.Room.init(config);

  let meetingTimer = null;
  let secondsElapsed = 0;
  let joined = false;

  // ============================================================
  // Control bar
  //
  // Labelled buttons rather than a row of bare glyphs. A toolbar of eight
  // emoji is a guessing game, and the two that matter most - whether the mic is
  // live and whether you are presenting - are exactly the ones people get wrong.
  // The label carries the current state ("Mute" vs "Unmute"), so the button says
  // what pressing it will do.
  //
  // Anything that is not needed mid-sentence lives behind More, so the bar stays
  // short enough to fit a laptop without wrapping.
  // ============================================================

  function controlButton({ id, icon, label, title, extra = '', klass = '' }) {
    return `
      <button class="ctrl ${klass}" id="${id}" title="${title || label}">
        <span class="ctrl-icon">${icon}</span>
        <span class="ctrl-label">${label}</span>
        ${extra}
      </button>`;
  }

  function controlBarMarkup() {
    return `
      <div class="ctrl-group">
        ${controlButton({ id: 'micBtn', icon: '🎤', label: 'Mute', title: 'Microphone (M)', klass: 'active' })}
        ${controlButton({ id: 'camBtn', icon: '📹', label: 'Camera', title: 'Camera (V)', klass: 'active' })}
      </div>

      <div class="ctrl-group">
        ${controlButton({ id: 'shareBtn', icon: '🖥', label: 'Present', title: 'Share your screen' })}
        ${controlButton({ id: 'boardBtn', icon: '🖊', label: 'Board', title: 'Whiteboard (W)' })}
        ${controlButton({
          id: 'handBtn',
          icon: '✋',
          label: 'Hand',
          title: 'Raise your hand (H)',
          extra: '<span class="ctrl-count" id="handCount" hidden>0</span>',
        })}
      </div>

      <div class="ctrl-group">
        ${controlButton({
          id: 'participantsBtn',
          icon: '👥',
          label: 'People',
          title: 'Participants (P)',
          extra: '<span class="ctrl-count" id="peopleCount">1</span>',
        })}
        ${controlButton({
          id: 'chatBtn',
          icon: '💬',
          label: 'Chat',
          title: 'Chat (C)',
          extra: '<span class="ctrl-dot" id="chatBadge" hidden></span>',
        })}
        ${controlButton({ id: 'roomsBtn', icon: '🚪', label: 'Rooms', title: 'Breakout rooms (R)' })}
      </div>

      <div class="ctrl-group">
        ${controlButton({ id: 'moreBtn', icon: '⋯', label: 'More', title: 'More options' })}
        ${controlButton({ id: 'leaveBtn', icon: '📴', label: 'Leave', title: 'Leave the meeting', klass: 'ctrl-danger' })}
      </div>

      <span class="ctrl-timer" id="timerDisplay">00:00</span>

      <div class="ctrl-menu" id="moreMenu" hidden>
        <button data-action="layout"><span>🔲</span> Change layout</button>
        <button data-action="theme"><span>🌓</span> Light / dark</button>
        <button data-action="fullscreen"><span>⛶</span> Fullscreen</button>
        <button data-action="settings"><span>⚙</span> Settings</button>
        <button data-action="polls"><span>📊</span> Polls</button>
        ${isHost ? `
          <div class="ctrl-menu-sep"></div>
          <button data-action="muteall"><span>🔇</span> Mute everyone</button>
          <button data-action="lowerhands"><span>✋</span> Lower all hands</button>
          <button data-action="record"><span>⏺</span> Record</button>` : ''}
      </div>`;
  }

  // ============================================================
  // Build UI Shell
  // ============================================================
  function buildUI() {
    const app = document.getElementById('app');
    const displayName = config.display_name || 'User';

    app.innerHTML = `
      <!-- Top Bar -->
      <div class="meeting-top-bar">
        <div class="meeting-info">📅 ${escapeHtml(config.title)}</div>
        <div class="top-badges">
          <span class="room-badge" id="roomBadge" hidden></span>
        </div>
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
                <div class="info-row"><span class="label">Role</span><span class="value">${roleLabel(config.role)}</span></div>
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

            <!-- Presentation stage: while anyone shares their screen this takes
                 over the whole area and the participant tiles are hidden, so the
                 shared content is the only thing on screen besides the toolbar. -->
            <div class="screen-stage" id="screenStage" hidden>
              <video autoplay playsinline id="stageVideo"></video>
              <div class="stage-bar">
                <span class="stage-label" id="stageLabel"></span>
                <button class="stage-users" id="stageUsersBtn">People</button>
                <button class="stage-stop" id="stageStopBtn" hidden>Stop sharing</button>
              </div>
            </div>
          </div>

          <!-- Captions overlay -->
          <div class="captions-overlay" id="captionsOverlay" style="display:none;"></div>

          <!-- Recording indicator -->
          <div class="recording-indicator" id="recordingIndicator" style="display:none;">
            <span class="rec-dot"></span> Recording
          </div>

          <!-- Requests waiting on the host. Not toasts: a request that vanishes
               after four seconds is a request the host never answered. -->
          <div class="request-stack" id="requestStack"></div>

          <!-- Bottom controls (shown after joining) -->
          <div class="meeting-float-controls" id="floatControls" style="display:none;">
            ${controlBarMarkup()}
          </div>
        </div>

        <!-- Sidebar -->
        <div class="meeting-sidebar hidden" id="meetingSidebar">
          <div class="sidebar-header">
            <h3 id="sidebarTitle">Participants</h3>
            <button class="close-sidebar" id="closeSidebar">✕</button>
          </div>
          <div class="sidebar-tabs" id="sidebarTabs">
            <button class="sidebar-tab active" data-tab="participants" title="People">👥</button>
            <button class="sidebar-tab" data-tab="chat" title="Chat">💬</button>
            <button class="sidebar-tab" data-tab="rooms" title="Breakout rooms">🚪</button>
            <button class="sidebar-tab" data-tab="whiteboard" title="Whiteboard">🖊</button>
            <button class="sidebar-tab" data-tab="polls" title="Polls">📊</button>
            <button class="sidebar-tab" data-tab="settings" title="Settings">⚙</button>
          </div>
          <div class="sidebar-content" id="sidebarContent"></div>
        </div>
      </div>
    `;

    UNILIS_MEETING.LayoutManager.init(document.getElementById('videoGrid'));
    UNILIS_MEETING.SidebarManager.init(document.getElementById('meetingSidebar'));
    UNILIS_MEETING.SidebarManager.onTabChange = handleSidebarTab;

    // Lobby and chrome
    document.getElementById('joinMeetingBtn').addEventListener('click', joinMeeting);
    document.getElementById('settingsBtn').addEventListener('click', () => {
      UNILIS_MEETING.SidebarManager.switchTab('settings');
    });
    document.getElementById('themeToggle').addEventListener('click', () => UNILIS_MEETING.ThemeManager.toggle());
    document.getElementById('fullscreenToggle').addEventListener('click', toggleFullscreen);
    document.getElementById('closeSidebar').addEventListener('click', () => UNILIS_MEETING.SidebarManager.close());

    // Control bar
    document.getElementById('micBtn').addEventListener('click', toggleMic);
    document.getElementById('camBtn').addEventListener('click', toggleCam);
    document.getElementById('shareBtn').addEventListener('click', toggleShare);
    document.getElementById('boardBtn').addEventListener('click', toggleBoard);
    document.getElementById('handBtn').addEventListener('click', toggleHand);
    document.getElementById('chatBtn').addEventListener('click', () => UNILIS_MEETING.SidebarManager.switchTab('chat'));
    document.getElementById('participantsBtn').addEventListener('click', () => UNILIS_MEETING.SidebarManager.switchTab('participants'));
    document.getElementById('roomsBtn').addEventListener('click', () => UNILIS_MEETING.SidebarManager.switchTab('rooms'));
    document.getElementById('stageStopBtn').addEventListener('click', toggleShare);
    document.getElementById('stageUsersBtn').addEventListener('click', () => UNILIS_MEETING.SidebarManager.switchTab('participants'));
    document.getElementById('leaveBtn').addEventListener('click', leaveMeeting);
    bindMoreMenu();

    // Every start/stop lands here, including the browser's own "Stop sharing".
    UNILIS_MEETING.MediaManager.onScreenShareChanged = handleScreenShareChanged;

    // Repaint the whiteboard's own controls whenever a grant or policy lands, so
    // the pen appears without the board being reopened.
    UNILIS_MEETING.Room.onStateChanged = () => {
      UNILIS_MEETING.Whiteboard.syncPermission();
      syncCapabilityButtons();
      refreshOpenTab();
      updateRoomBadge();
    };

    // Sidebar tabs
    document.querySelectorAll('.sidebar-tab').forEach(tab => {
      tab.addEventListener('click', () => UNILIS_MEETING.SidebarManager.switchTab(tab.dataset.tab));
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', (e) => {
      if (e.target.tagName === 'TEXTAREA' || e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT') return;
      if (e.metaKey || e.ctrlKey || e.altKey) return;
      const key = e.key.toLowerCase();
      if (key === 'm') toggleMic();
      if (key === 'v') toggleCam();
      if (key === 'd') UNILIS_MEETING.SidebarManager.toggle();
      if (key === 'c') UNILIS_MEETING.SidebarManager.switchTab('chat');
      if (key === 'p') UNILIS_MEETING.SidebarManager.switchTab('participants');
      if (key === 'r') UNILIS_MEETING.SidebarManager.switchTab('rooms');
      if (key === 'h') toggleHand();
      if (key === 'w') toggleBoard();
      if (key === 'escape') closeMoreMenu();
    });
  }

  function bindMoreMenu() {
    const button = document.getElementById('moreBtn');
    const menu = document.getElementById('moreMenu');

    button.addEventListener('click', (e) => {
      e.stopPropagation();
      menu.hidden = !menu.hidden;
      button.classList.toggle('active', !menu.hidden);
    });

    // A click anywhere else closes it, which is what people expect from a menu
    // and saves a second press on the button.
    document.addEventListener('click', (e) => {
      if (!menu.hidden && !menu.contains(e.target) && e.target !== button) closeMoreMenu();
    });

    menu.querySelectorAll('button[data-action]').forEach(item => {
      item.addEventListener('click', () => {
        closeMoreMenu();
        handleMoreAction(item.dataset.action);
      });
    });
  }

  function closeMoreMenu() {
    const menu = document.getElementById('moreMenu');
    if (!menu) return;
    menu.hidden = true;
    document.getElementById('moreBtn').classList.remove('active');
  }

  function handleMoreAction(action) {
    switch (action) {
      case 'layout': toggleView(); break;
      case 'theme': UNILIS_MEETING.ThemeManager.toggle(); break;
      case 'fullscreen': toggleFullscreen(); break;
      case 'settings': UNILIS_MEETING.SidebarManager.switchTab('settings'); break;
      case 'polls': UNILIS_MEETING.SidebarManager.switchTab('polls'); break;
      case 'muteall': UNILIS_MEETING.Settings.muteAll(); break;
      case 'lowerhands': UNILIS_MEETING.Settings.lowerAllHands(); break;
      case 'record': toggleRecord(); break;
      default: break;
    }
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

  function escapeAttr(text) {
    return escapeHtml(text).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
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
      // Never blocks the join: startMedia narrows its request until something works
      // and returns null when the machine has no usable camera or microphone.
      await UNILIS_MEETING.MediaManager.startMedia(true, true);
      UNILIS_MEETING.WebRTCCore.localStream = UNILIS_MEETING.MediaManager.localStream;

      const limitation = UNILIS_MEETING.MediaManager.describeLimitation();
      if (limitation) {
        UNILIS_MEETING.Notifications.show(limitation, 'warning');
      }
      syncMediaControlState();

      // Device labels are only readable once a getUserMedia prompt has been
      // answered, so the picker list is built after that rather than at load.
      UNILIS_MEETING.Settings.loadDevices();

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

      // Board changes update the tab's item count even when the board is shut.
      UNILIS_MEETING.Whiteboard.onActivity = onWhiteboardActivity;

      joined = true;
      startTimer();
      UNILIS_MEETING.SidebarManager.switchTab('participants');

    } catch (err) {
      // Missing devices no longer land here — this is a real join failure.
      console.error('Failed to join meeting:', err);
      UNILIS_MEETING.Notifications.show(
        'Could not join the meeting: ' + ((err && err.message) || 'unknown error'),
        'error'
      );
      document.getElementById('lobbyOverlay').style.display = '';
      document.getElementById('videoContainer').style.display = 'none';
      document.getElementById('floatControls').style.display = 'none';
    }
  }

  /**
   * Reflect what media is actually available on the mic/camera buttons, so a
   * participant without a device sees why the control does nothing.
   */
  function syncMediaControlState() {
    const media = UNILIS_MEETING.MediaManager;
    const states = [
      { id: 'micBtn', available: media.hasAudio(), label: 'Microphone (M)', missing: 'No microphone available' },
      { id: 'camBtn', available: media.hasVideo(), label: 'Camera (V)', missing: 'No camera available' },
    ];

    states.forEach(({ id, available, label, missing }) => {
      const btn = document.getElementById(id);
      if (!btn) return;
      btn.disabled = !available;
      btn.title = available ? label : missing;
      btn.classList.toggle('active', available);
      btn.classList.toggle('unavailable', !available);
    });

    if (!media.hasAudio()) setControlLabel('micBtn', '🚫', 'No mic');
    if (!media.hasVideo()) setControlLabel('camBtn', '🚫', 'No camera');
  }

  function setControlLabel(id, icon, label) {
    const btn = document.getElementById(id);
    if (!btn) return;
    btn.querySelector('.ctrl-icon').textContent = icon;
    btn.querySelector('.ctrl-label').textContent = label;
  }

  /**
   * Show the Present and Board buttons as the permissions currently stand.
   *
   * They stay visible rather than disappearing when not permitted: a button that
   * offers to ask the host is more use than a gap where a button was.
   */
  function syncCapabilityButtons() {
    const room = UNILIS_MEETING.Room;

    const share = document.getElementById('shareBtn');
    if (share) {
      const allowed = room.canShareScreen();
      const sharing = UNILIS_MEETING.MediaManager.screenSharing;
      share.classList.toggle('needs-permission', !allowed && !sharing);
      share.title = sharing
        ? 'Stop presenting'
        : allowed ? 'Share your screen' : 'Ask the host to let you share';
      setControlLabel('shareBtn', sharing ? '🛑' : '🖥', sharing ? 'Stop' : allowed ? 'Present' : 'Ask to present');
    }

    const board = document.getElementById('boardBtn');
    if (board) {
      const allowed = room.canUseWhiteboard();
      board.classList.toggle('needs-permission', !allowed);
      board.title = allowed ? 'Whiteboard (W)' : 'You can view the board but not draw on it';
    }
  }

  function updateRoomBadge() {
    const badge = document.getElementById('roomBadge');
    if (!badge) return;
    const room = UNILIS_MEETING.Room;
    if (room.breakoutId === null) {
      badge.hidden = true;
      return;
    }
    badge.hidden = false;
    badge.textContent = '🚪 ' + room.currentRoomName();
  }

  // ============================================================
  // Video Tiles
  // ============================================================

  /**
   * The hover actions on a tile.
   *
   * The same moderation lives in the People panel, but a host watching somebody
   * talk should not have to find their row in a list to mute them - the tile
   * they are already looking at is where the action belongs.
   */
  function tileActions(userId, isMe) {
    if (isMe) return '';
    const pinBtn = `<button class="tile-act" data-act="pin" title="Pin to the main tile">📌</button>`;
    if (!isHost) return `<div class="tile-actions">${pinBtn}</div>`;

    return `<div class="tile-actions">
      ${pinBtn}
      <button class="tile-act" data-act="share" title="Let them share their screen">🖥</button>
      <button class="tile-act" data-act="board" title="Let them draw on the whiteboard">🖊</button>
      <button class="tile-act" data-act="mute" title="Mute them">🔇</button>
      <button class="tile-act tile-act-danger" data-act="remove" title="Remove from the meeting">✕</button>
    </div>`;
  }

  function bindTileActions(tile, userId) {
    tile.querySelectorAll('.tile-act').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const panel = UNILIS_MEETING.ParticipantPanel;
        const p = UNILIS_MEETING.Room.getParticipant(userId) || {};
        switch (btn.dataset.act) {
          case 'pin': panel.pin(userId); break;
          case 'share': panel.grant(userId, 'screen', !p.may_share_screen); break;
          case 'board': panel.grant(userId, 'whiteboard', !p.may_whiteboard); break;
          case 'mute': panel.mute(userId); break;
          case 'remove': panel.remove(userId); break;
          default: break;
        }
      });
    });
  }

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
        <div class="tile-badges" id="badges-${config.user_id}">
          <span class="tile-badge ${config.role}">${roleInitial(config.role)}</span>
        </div>
      </div>
    `;
    grid.appendChild(tile);

    const video = tile.querySelector('video');
    const stream = UNILIS_MEETING.MediaManager.localStream;
    if (stream && video) {
      video.srcObject = stream;
      // Keep the avatar when there is no camera: an audio-only stream renders as a
      // black rectangle rather than showing nothing.
      const hasVideo = stream.getVideoTracks().length > 0;
      const placeholder = document.getElementById(`avatar-${config.user_id}`);
      video.style.display = hasVideo ? '' : 'none';
      if (placeholder) placeholder.style.display = hasVideo ? 'none' : '';
    }
  }

  /**
   * Show a participant's video only while they actually have a live video track,
   * falling back to their avatar otherwise. Tracks can arrive after the tile is
   * built — audio typically negotiates first — so this also listens for changes.
   */
  function syncTileVideo(userId, stream) {
    const video = document.getElementById(`remoteVideo-${userId}`);
    const placeholder = document.getElementById(`avatar-${userId}`);
    if (!video || !stream) return;

    if (video.srcObject !== stream) video.srcObject = stream;

    const apply = () => {
      const hasVideo = stream.getVideoTracks().some(track => track.readyState === 'live');
      video.style.display = hasVideo ? '' : 'none';
      if (placeholder) placeholder.style.display = hasVideo ? 'none' : '';
    };

    apply();
    stream.onaddtrack = apply;
    stream.onremovetrack = apply;
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
        <span class="tile-name" title="${escapeAttr(displayName)}">${escapeHtml(displayName)}</span>
        <div class="tile-badges" id="badges-${userId}">
          <span class="tile-badge ${roleClass}">${roleInitial(roleClass)}</span>
        </div>
      </div>
      ${tileActions(userId, false)}
    `;
    grid.appendChild(tile);
    bindTileActions(tile, userId);

    syncTileVideo(userId, stream);
    refreshTileMeta(room.participants);
  }

  function removeVideoTile(userId) {
    const tile = document.getElementById(`participant-${userId}`);
    if (tile) tile.remove();
  }

  /**
   * Update the badges on every tile from the roster.
   *
   * The tile itself is never rebuilt for this: the <video> element inside it
   * holds the live stream, and replacing the markup would drop the picture and
   * make the meeting flicker on every mute.
   */
  function refreshTileMeta(participants) {
    (participants || []).forEach(p => {
      const badges = document.getElementById(`badges-${p.user_id}`);
      if (!badges) return;

      const parts = [`<span class="tile-badge ${p.role}">${roleInitial(p.role)}</span>`];
      if (p.hand_raised) parts.push('<span class="tile-badge hand-raised" title="Hand raised">✋</span>');
      if (!p.audio_enabled || p.is_muted) parts.push('<span class="tile-badge muted" title="Muted">🔇</span>');
      if (p.screen_sharing) parts.push('<span class="tile-badge sharing" title="Presenting">🖥</span>');
      badges.innerHTML = parts.join('');

      const tile = document.getElementById(`participant-${p.user_id}`);
      if (!tile) return;
      tile.classList.toggle('hand-up', !!p.hand_raised);

      // The host's grant buttons show current state, so they read as toggles
      // rather than as two identical presses.
      const shareBtn = tile.querySelector('.tile-act[data-act="share"]');
      if (shareBtn) {
        shareBtn.classList.toggle('active', !!p.may_share_screen);
        shareBtn.title = p.may_share_screen ? 'Stop them sharing their screen' : 'Let them share their screen';
      }
      const boardBtn = tile.querySelector('.tile-act[data-act="board"]');
      if (boardBtn) {
        boardBtn.classList.toggle('active', !!p.may_whiteboard);
        boardBtn.title = p.may_whiteboard ? 'Take the whiteboard pen back' : 'Let them draw on the whiteboard';
      }
    });
  }

  // ============================================================
  // Signaling Message Handler
  // ============================================================
  function handleSignalingMessage(message) {
    switch (message.type) {
      case 'joined':
        UNILIS_MEETING.Room.updateState(message.state);
        UNILIS_MEETING.Room.updateParticipants(message.participants || []);
        handlePeerConnections();
        updateParticipantsUI(message.participants || []);
        break;

      case 'participants':
        UNILIS_MEETING.Room.updateParticipants(message.participants || []);
        updateParticipantsUI(message.participants);
        handlePeerConnections();
        break;

      case 'room_state':
        UNILIS_MEETING.Room.updateState(message.state);
        updateHandBadges();
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
        if (UNILIS_MEETING.SidebarManager.currentTab === 'chat') handleSidebarTab('chat');
        break;

      case 'chat_deleted':
        // Reload or hide deleted message
        break;

      case 'poll_created':
        UNILIS_MEETING.Polls.activePolls = [
          ...UNILIS_MEETING.Polls.activePolls.filter(p => p.poll_id !== message.poll_id),
          message,
        ];
        if (UNILIS_MEETING.SidebarManager.currentTab === 'polls') updatePollsUI();
        break;

      case 'poll_state':
        UNILIS_MEETING.Polls.activePolls = message.polls || [];
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

      // ---- Whiteboard ----
      case 'whiteboard_state':
        UNILIS_MEETING.Whiteboard.loadState(message.state);
        break;

      case 'whiteboard_action':
        UNILIS_MEETING.Whiteboard.applyAction(message.action);
        break;

      // ---- Permissions ----
      case 'permission_request':
        showPermissionRequest(message);
        break;

      case 'permission_changed':
        handlePermissionChanged(message);
        break;

      case 'permission_pending':
        UNILIS_MEETING.Notifications.show('Waiting for the host to answer', 'info');
        break;

      case 'permission_denied':
        UNILIS_MEETING.Notifications.show(message.message || 'That is not allowed here', 'warning');
        syncCapabilityButtons();
        break;

      // ---- Breakout rooms ----
      case 'breakout_moved':
        handleBreakoutMoved(message);
        break;

      case 'breakout_announcement':
        UNILIS_MEETING.Notifications.show(
          `${message.from || 'The host'}: ${message.text}`,
          'info',
          10000
        );
        break;

      case 'host_action':
        handleHostAction(message);
        break;

      case 'meeting_locked':
        UNILIS_MEETING.Room.isLocked = message.locked;
        UNILIS_MEETING.Notifications.show(
          message.locked ? 'The meeting is locked to new joiners' : 'The meeting is open again',
          'info'
        );
        break;

      case 'speaking': {
        const tile = document.getElementById(`participant-${message.user_id}`);
        if (tile) tile.classList.toggle('active-speaker', message.is_speaking);
        break;
      }

      case 'typing': {
        const indicator = document.getElementById('typingIndicator');
        if (indicator) indicator.textContent = message.is_typing ? 'Someone is typing...' : '';
        break;
      }

      case 'error':
        UNILIS_MEETING.Notifications.show(message.message || 'An error occurred', 'error');
        break;

      default:
        break;
    }
  }

  function handleHostAction(message) {
    if (message.action === 'mute') {
      // Only actually toggle if the mic is live, or a second mute_all would
      // unmute somebody the host had just muted.
      if (UNILIS_MEETING.MediaManager.audioEnabled) {
        UNILIS_MEETING.MediaManager.toggleAudio();
        updateMicUI();
      }
      UNILIS_MEETING.Notifications.show('You have been muted by the host', 'warning');
      return;
    }
    if (message.action === 'hand_lowered') {
      updateHandButton(false);
      return;
    }
    if (message.action === 'removed') {
      UNILIS_MEETING.Notifications.show('You have been removed from the meeting', 'error');
      setTimeout(() => { window.location.href = config.back_url || '/'; }, 2000);
    }
  }

  // ============================================================
  // Permission requests (host side)
  // ============================================================

  /**
   * A request card that stays until the host answers it.
   *
   * Deliberately not a toast. A student asking for the screen is waiting on a
   * decision, and a notice that disappears on a timer turns that into silence
   * the host never knew about.
   */
  function showPermissionRequest(message) {
    const stack = document.getElementById('requestStack');
    if (!stack) return;

    const id = `req-${message.capability}-${message.user_id}`;
    if (document.getElementById(id)) return;

    const what = message.capability === 'screen' ? 'share their screen' : 'draw on the whiteboard';

    const card = document.createElement('div');
    card.className = 'request-card';
    card.id = id;
    card.innerHTML = `
      <div class="request-text">
        <strong>${escapeHtml(message.display_name || 'Someone')}</strong> would like to ${what}.
      </div>
      <div class="request-actions">
        <button class="request-btn request-btn-primary" data-answer="allow">Allow</button>
        <button class="request-btn" data-answer="deny">Not now</button>
      </div>`;

    card.querySelector('[data-answer="allow"]').addEventListener('click', () => {
      UNILIS_MEETING.ParticipantPanel.grant(message.user_id, message.capability, true);
      card.remove();
    });
    card.querySelector('[data-answer="deny"]').addEventListener('click', () => {
      UNILIS_MEETING.signaling.send({
        type: 'permission_response',
        target_user_id: message.user_id,
        capability: message.capability,
        granted: false,
      });
      card.remove();
    });

    stack.appendChild(card);
  }

  function handlePermissionChanged(message) {
    if (message.capability === 'screen') {
      UNILIS_MEETING.ScreenShare.onPermissionChanged(message.granted, message.by);
    } else if (message.capability === 'whiteboard') {
      UNILIS_MEETING.Notifications.show(
        message.granted
          ? (message.by ? message.by + ' gave you the whiteboard pen' : 'You can draw on the whiteboard')
          : 'The whiteboard pen has been taken back',
        message.granted ? 'success' : 'warning'
      );
      UNILIS_MEETING.Whiteboard.syncPermission();
    }
    syncCapabilityButtons();
    refreshOpenTab();
  }

  // ============================================================
  // Breakout rooms
  // ============================================================

  /**
   * This client has been moved. Everything scoped to the room has to be rebuilt:
   * the peer mesh, because the people it was connected to are no longer reachable
   * and the new ones are; and the board and chat, which the server resends.
   */
  function handleBreakoutMoved(message) {
    const room = UNILIS_MEETING.Room;
    const previous = room.breakoutId;
    room.breakoutId = message.breakout_id || null;

    if (previous !== room.breakoutId) {
      UNILIS_MEETING.WebRTCCore.peerConnections.forEach((entry, userId) => {
        UNILIS_MEETING.WebRTCCore.cleanupPeer(userId);
        removeVideoTile(userId);
      });

      // A share does not follow you between rooms: the people who could see it
      // are not the people you are now with.
      if (UNILIS_MEETING.MediaManager.screenSharing) {
        UNILIS_MEETING.ScreenShare.stop();
      }
    }

    UNILIS_MEETING.Notifications.show(`You are now in ${message.name || 'the main room'}`, 'info');
    updateRoomBadge();
    handlePeerConnections();
    refreshOpenTab();
  }

  // ============================================================
  // WebRTC Peer Connection Management
  // ============================================================

  /**
   * Bring the peer mesh in line with the roster, for this room only.
   *
   * Filtered by breakout: offering to somebody in another room would never be
   * answered, because the server drops signalling that crosses the split.
   */
  function handlePeerConnections() {
    const room = UNILIS_MEETING.Room;
    const peers = room.peersInMyRoom();
    const wanted = new Set(peers.map(p => p.user_id));

    peers.forEach(async p => {
      if (!UNILIS_MEETING.WebRTCCore.peerConnections.has(p.user_id)) {
        UNILIS_MEETING.WebRTCCore.ensurePeer(p.user_id, p.display_name, p.role === 'lecturer');
        if (UNILIS_MEETING.WebRTCCore.shouldCreateOffer(p.user_id)) {
          await UNILIS_MEETING.WebRTCCore.createOffer(p.user_id);
        }
      }
    });

    UNILIS_MEETING.WebRTCCore.peerConnections.forEach((entry, userId) => {
      if (!wanted.has(userId)) {
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
      default:
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
    // ontrack fires once per track; the tile already exists for the second one, so
    // refresh visibility here rather than only at creation time.
    syncTileVideo(userId, stream);
    // If this participant announced a share before their track arrived, the
    // stage was waiting for exactly this.
    updateScreenStage();
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
    const list = participants || [];
    const local = list.find(p => p.user_id === config.user_id);
    if (local && !local.screen_sharing && UNILIS_MEETING.MediaManager.screenSharing) {
      UNILIS_MEETING.ScreenShare.stop();
    }
    const inMyRoom = list.filter(p => (p.breakout_id || null) === UNILIS_MEETING.Room.breakoutId);

    UNILIS_MEETING.LayoutManager.updateTileCount(inMyRoom.length);
    refreshTileMeta(list);

    const counter = document.getElementById('peopleCount');
    if (counter) counter.textContent = String(inMyRoom.length);

    if (UNILIS_MEETING.SidebarManager.currentTab === 'participants') {
      document.getElementById('sidebarContent').innerHTML =
        UNILIS_MEETING.ParticipantPanel.render(inMyRoom, isHost);
      document.getElementById('sidebarTitle').textContent = `People (${inMyRoom.length})`;
    } else if (UNILIS_MEETING.SidebarManager.currentTab === 'rooms') {
      // The rooms panel shows who is where, so it is stale the moment somebody
      // joins or moves.
      document.getElementById('sidebarContent').innerHTML = UNILIS_MEETING.Breakouts.render();
    }

    // Keep the open modal live rather than showing whoever was in the room when
    // it was opened.
    UNILIS_MEETING.ParticipantPanel.syncModal(inMyRoom, isHost);

    updateHandButton(UNILIS_MEETING.Room.handRaised());
    updateHandBadges();
    syncCapabilityButtons();

    // This broadcast carries the screen_sharing flags, so it is how a viewer
    // learns that someone started or stopped presenting.
    updateScreenStage();
  }

  /**
   * The hand count on the toolbar and the People tab.
   *
   * Only hosts see the queue length. For a participant the only hand that
   * matters is their own, and a number next to the button would read as "you
   * have four hands up".
   */
  function updateHandBadges() {
    const hands = UNILIS_MEETING.Room.raisedHands();
    const badge = document.getElementById('handCount');

    if (badge) {
      const show = isHost && hands.length > 0;
      badge.hidden = !show;
      badge.textContent = String(hands.length);
    }

    UNILIS_MEETING.SidebarManager.setBadge('participants', isHost ? hands.length : 0);

    if (UNILIS_MEETING.SidebarManager.currentTab === 'participants') {
      refreshOpenTab();
    }
  }

  function onWhiteboardActivity(count, action) {
    UNILIS_MEETING.SidebarManager.setBadge('whiteboard', 0);

    // A drawing arriving while the board is closed is easy to miss entirely, so
    // the button says so once rather than for every stroke.
    if (!UNILIS_MEETING.Whiteboard.open && action && action.kind === 'draw') {
      const board = document.getElementById('boardBtn');
      if (board && !board.classList.contains('has-activity')) {
        board.classList.add('has-activity');
        UNILIS_MEETING.Notifications.show('Someone is drawing on the whiteboard', 'info', 3000);
      }
    }

    if (UNILIS_MEETING.SidebarManager.currentTab === 'whiteboard') refreshOpenTab();
  }

  /** Repaint whichever sidebar tab is open, from current state. */
  function refreshOpenTab() {
    if (!UNILIS_MEETING.SidebarManager.isOpen) return;
    handleSidebarTab(UNILIS_MEETING.SidebarManager.currentTab);
  }

  function handleSidebarTab(tab) {
    const content = document.getElementById('sidebarContent');
    const title = document.getElementById('sidebarTitle');
    const room = UNILIS_MEETING.Room;

    switch (tab) {
      case 'participants': {
        const inMyRoom = (room.participants || []).filter(
          p => (p.breakout_id || null) === room.breakoutId
        );
        title.textContent = `People (${inMyRoom.length})`;
        content.innerHTML = UNILIS_MEETING.ParticipantPanel.render(inMyRoom, isHost);
        break;
      }
      case 'chat':
        title.textContent = room.breakoutId === null ? 'Chat' : `Chat · ${room.currentRoomName()}`;
        content.innerHTML = UNILIS_MEETING.Chat.render();
        UNILIS_MEETING.Chat.unreadCount = 0;
        updateChatBadge();
        setTimeout(() => {
          const input = document.getElementById('chatInput');
          if (!input) return;
          if (!room.canChat()) {
            input.disabled = true;
            input.placeholder = 'The host has turned chat off';
            return;
          }
          input.addEventListener('input', () => {
            UNILIS_MEETING.signaling.send({ type: 'typing', is_typing: input.value.length > 0 });
          });
          input.focus();
        }, 100);
        break;
      case 'rooms':
        title.textContent = 'Breakout rooms';
        content.innerHTML = UNILIS_MEETING.Breakouts.render();
        break;
      case 'polls':
        title.textContent = 'Polls';
        updatePollsUI();
        break;
      case 'whiteboard':
        title.textContent = 'Whiteboard';
        content.innerHTML = UNILIS_MEETING.Whiteboard.render();
        break;
      case 'settings':
        title.textContent = isHost ? 'Host settings' : 'Settings';
        content.innerHTML = UNILIS_MEETING.Settings.render();
        break;
      default:
        break;
    }
  }

  function updatePollsUI() {
    document.getElementById('sidebarContent').innerHTML = UNILIS_MEETING.Polls.render();
  }

  function updateChatBadge() {
    const badge = document.getElementById('chatBadge');
    const unread = UNILIS_MEETING.Chat.unreadCount > 0
      && UNILIS_MEETING.SidebarManager.currentTab !== 'chat';
    if (badge) badge.hidden = !unread;
    UNILIS_MEETING.SidebarManager.setBadge('chat', unread ? UNILIS_MEETING.Chat.unreadCount : 0);
  }

  // ============================================================
  // Control Toggles
  // ============================================================
  function toggleMic() {
    // The button is disabled without a device, but the M shortcut reaches here too.
    if (!UNILIS_MEETING.MediaManager.hasAudio()) {
      UNILIS_MEETING.Notifications.show('No microphone available.', 'warning');
      return;
    }
    const enabled = UNILIS_MEETING.MediaManager.toggleAudio();
    updateMicUI();
    UNILIS_MEETING.Room.sendParticipantUpdate({ audio_enabled: enabled, is_muted: !enabled });
  }

  function updateMicUI() {
    const btn = document.getElementById('micBtn');
    if (!btn) return;
    const enabled = UNILIS_MEETING.MediaManager.audioEnabled;
    btn.classList.toggle('active', enabled);
    btn.classList.toggle('ctrl-off', !enabled);
    setControlLabel('micBtn', enabled ? '🎤' : '🔇', enabled ? 'Mute' : 'Unmute');
    btn.title = enabled ? 'Mute (M)' : 'Unmute (M)';
  }

  function toggleCam() {
    // The button is disabled without a device, but the V shortcut reaches here too.
    if (!UNILIS_MEETING.MediaManager.hasVideo()) {
      UNILIS_MEETING.Notifications.show('No camera available.', 'warning');
      return;
    }
    const enabled = UNILIS_MEETING.MediaManager.toggleVideo();
    updateCamUI();
    UNILIS_MEETING.Room.sendParticipantUpdate({ video_enabled: enabled });
  }

  function updateCamUI() {
    const btn = document.getElementById('camBtn');
    if (!btn) return;
    const enabled = UNILIS_MEETING.MediaManager.videoEnabled;
    btn.classList.toggle('active', enabled);
    btn.classList.toggle('ctrl-off', !enabled);
    setControlLabel('camBtn', enabled ? '📹' : '🚫', enabled ? 'Camera' : 'Camera on');
    btn.title = enabled ? 'Turn camera off (V)' : 'Turn camera on (V)';
  }

  /**
   * Ask for or drop the screen. All resulting UI - the button state, the stage,
   * and telling everyone else - is handled by handleScreenShareChanged, because
   * sharing can also stop from the browser's own bar rather than from here.
   */
  function toggleShare() {
    if (UNILIS_MEETING.MediaManager.screenSharing) {
      UNILIS_MEETING.ScreenShare.stop();
    } else {
      UNILIS_MEETING.ScreenShare.start();
    }
  }

  function handleScreenShareChanged(sharing) {
    const btn = document.getElementById('shareBtn');
    if (btn) btn.classList.toggle('active', sharing);

    // Presenting and the whiteboard both own the stage, so starting one closes
    // the other rather than leaving the board on top of the screen.
    if (sharing && UNILIS_MEETING.Whiteboard.open) UNILIS_MEETING.Whiteboard.close();

    // Tell the room, so viewers know whose stream to put on the stage. A viewer
    // cannot work this out alone: sharing swaps the sender's video track, so the
    // screen arrives on the same track the camera was using.
    UNILIS_MEETING.Room.sendParticipantUpdate({ screen_sharing: sharing });

    syncCapabilityButtons();
    updateScreenStage();
  }

  function toggleBoard() {
    const open = UNILIS_MEETING.Whiteboard.toggle();
    const btn = document.getElementById('boardBtn');
    if (btn) {
      btn.classList.toggle('active', open);
      btn.classList.remove('has-activity');
    }
    if (open) UNILIS_MEETING.SidebarManager.close();
  }

  /**
   * Who, if anyone, is presenting. Local sharing wins over a remote share so the
   * presenter always sees their own screen rather than someone else's.
   */
  function currentSharer() {
    if (UNILIS_MEETING.MediaManager.screenSharing) {
      return { userId: config.user_id, isLocal: true, name: config.display_name };
    }

    // Only somebody in this room: a share in another breakout is none of this
    // client's business and its track never arrives anyway.
    const remote = UNILIS_MEETING.Room.peersInMyRoom().find(p => p.screen_sharing);

    return remote
      ? { userId: remote.user_id, isLocal: false, name: remote.display_name }
      : null;
  }

  /**
   * Switch between the tile grid and the full-area presentation stage.
   *
   * Safe to call as often as needed - it derives everything from current state
   * rather than tracking transitions, so a late-arriving screen track, a
   * participants broadcast and a local toggle all converge on the same result.
   */
  function updateScreenStage() {
    const stage = document.getElementById('screenStage');
    const grid = document.getElementById('videoGrid');
    const video = document.getElementById('stageVideo');
    if (!stage || !grid || !video) return;

    const sharer = currentSharer();

    // A remote share is announced over signaling before the track itself
    // arrives. Staying on the grid until there is something to show avoids a
    // black rectangle in the gap.
    const stream = sharer && sharer.isLocal
      ? UNILIS_MEETING.MediaManager.screenStream
      : sharer
        ? (document.getElementById(`remoteVideo-${sharer.userId}`) || {}).srcObject
        : null;

    if (!sharer || !stream) {
      if (!stage.hidden) {
        stage.hidden = true;
        video.srcObject = null;
        grid.style.display = '';
        document.getElementById('videoContainer').classList.remove('is-presenting');
      }
      return;
    }

    // Never play your own captured audio back: it would loop into the room.
    video.muted = sharer.isLocal;
    if (video.srcObject !== stream) video.srcObject = stream;

    document.getElementById('stageLabel').textContent = sharer.isLocal
      ? 'You are presenting'
      : `${sharer.name || 'Someone'} is presenting`;
    document.getElementById('stageStopBtn').hidden = !sharer.isLocal;

    stage.hidden = false;
    grid.style.display = 'none';
    document.getElementById('videoContainer').classList.add('is-presenting');
  }

  function toggleHand() {
    const raised = UNILIS_MEETING.Room.raiseHand();
    if (raised === undefined) return;
    updateHandButton(raised);
  }

  /**
   * The hand button's own state, driven by the roster rather than by the click,
   * so a host lowering it is reflected here too.
   */
  function updateHandButton(raised) {
    const btn = document.getElementById('handBtn');
    if (!btn) return;
    btn.classList.toggle('active', !!raised);
    setControlLabel('handBtn', '✋', raised ? 'Lower' : 'Hand');
    btn.title = raised ? 'Lower your hand (H)' : 'Raise your hand (H)';
  }

  function toggleView() {
    const view = UNILIS_MEETING.LayoutManager.toggleView();
    UNILIS_MEETING.Notifications.show(`Switched to ${view} view`, 'info', 2000);
  }

  function toggleRecord() {
    if (UNILIS_MEETING.Room.isRecording) {
      UNILIS_MEETING.Recording.stop();
    } else {
      UNILIS_MEETING.Recording.start();
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
      const display = document.getElementById('timerDisplay');
      if (display) display.textContent = `${mins}:${secs}`;
    }, 1000);
  }

  // ============================================================
  // Leave Meeting
  // ============================================================
  function leaveMeeting() {
    if (!confirm('Are you sure you want to leave the meeting?')) return;

    if (meetingTimer) clearInterval(meetingTimer);
    if (UNILIS_MEETING.signaling) UNILIS_MEETING.signaling.disconnect();
    UNILIS_MEETING.WebRTCCore.cleanupAll();
    UNILIS_MEETING.MediaManager.stopAll();

    window.location.href = config.back_url || '/';
  }

  // A tab closed without pressing Leave still has to hand the mic and camera
  // back, or the device light stays on until the browser is quit.
  window.addEventListener('pagehide', () => {
    if (!joined) return;
    if (UNILIS_MEETING.signaling) UNILIS_MEETING.signaling.disconnect();
    UNILIS_MEETING.MediaManager.stopAll();
  });

  // ============================================================
  // Initialize
  // ============================================================
  buildUI();
})();
