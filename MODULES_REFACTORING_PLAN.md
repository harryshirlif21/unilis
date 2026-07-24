# UNILIS Meeting System — Architecture Audit & Refactoring Plan

## Current Architecture (Phase 1 Analysis)

### File Map
```
unilis/
├── config/meeting.php              # Core config, URL builders, join logic
├── lecturer/meetings.php           # Meeting scheduling & management
├── lecturer/meeting_host.php       # Redirects lecturer to Python UI
├── lecturer/meeting_ide.php        # Legacy → redirects to meeting_host.php
├── student/meeting_join.php        # Validates enrollment → redirects to Python UI
├── student/meeting_ide.php         # Student meeting listing
├── api/check_live_meetings.php     # Polling API for live meetings
├── actions.php                     # Handles meeting creation (schedule_meeting action)
├── meeting-server/
│   ├── server.py                   # FastAPI: WebSocket signaling + media relay
│   ├── media_room.py               # OpenCV frame compositing (inefficient)
│   ├── Dockerfile                  # Python 3.11 container
│   └── requirements.txt            # fastapi, uvicorn, opencv-python, numpy
├── docker-compose.yml              # meeting-server on port 8765
└── apache.conf                     # Proxy /meeting-ui/, /ws/signaling, /ws/media
```

### Authentication Flow
1. PHP session (`$_SESSION['user_id']`, `$_SESSION['user_role']`)
2. Each meeting PHP file validates session before allowing access
3. Python server receives user_id, role, display_name as query params (no auth validation — trusts PHP)

### Join Flow
1. **Lecturer**: `lecturer/meetings.php` → clicks "Join Meeting" → `meeting_host.php` → validates session + meeting ownership → redirects to `python/meeting-ui/host?params`
2. **Student**: `student/meeting_ide.php` → clicks "Join" → `meeting_join.php` → validates enrollment → redirects to `python/meeting-ui/join?params`

### Python Server Architecture
- **FastAPI** on port 8765
- `/meeting-ui/host` and `/meeting-ui/join` — Server-rendered HTML (string templates)
- `/ws/signaling` — WebRTC signaling (SDP offers/answers, ICE candidates)
- `/ws/media` — Server-side frame relay (NOT true WebRTC — encodes/decodes JPEG via OpenCV)

### Problems Found
1. **No true WebRTC peer-to-peer** — `/ws/media` uses server-side frame relay (heavy, unscalable)
2. **Server-rendered HTML in Python strings** — No frontend separation; unmaintainable
3. **Missing features** — No chat, whiteboard, polls, recording, captions, attendance
4. **No responsive conference UI** — Basic cards, no Google Meet-style layout
5. **No host controls** — No mute/kick/pin for lecturer
6. **No attendance tracking** during meetings
7. **No background blur/virtual backgrounds**
8. **OpenCV is heavy** — Docker image is large; unnecessary for signaling

## Refactoring Plan (Phase 2)

### Guiding Principles
- **Preserve all existing auth, scheduling, DB, join flows**
- **No new DB tables unless absolutely required**
- **Replace server-side media relay with true WebRTC**
- **Extract UI from Python → serve from PHP assets directory**
- **Backward compatible — all existing URLs continue to work**

### Files Modified
1. `meeting-server/server.py` — Remove media relay; enhance signaling
2. `meeting-server/media_room.py` — Simplify to just room/participant state
3. `meeting-server/requirements.txt` — Remove opencv-python; add aiortc
4. `config/meeting.php` — Add new UI URL builders
5. `lecturer/meeting_host.php` — Route to new frontend
6. `student/meeting_join.php` — Route to new frontend
7. `docker-compose.yml` — Add media ports; update env vars
8. `apache.conf` — Add new proxy routes

### Files Created (New Frontend)
1. `assets/meetings/js/meeting.js` — Main meeting controller
2. `assets/meetings/js/webrtc.js` — WebRTC peer connection manager
3. `assets/meetings/js/webrtc-core.js` — Core WebRTC engine
4. `assets/meetings/js/webrtc-media.js` — Media device management
5. `assets/meetings/js/webrtc-rooms.js` — Room management
6. `assets/meetings/js/ui.js` — UI controller
7. `assets/meetings/js/ui-theme.js` — Theme manager (light/dark)
8. `assets/meetings/js/ui-layout.js` — Dynamic grid layout
9. `assets/meetings/js/ui-sidebar.js` — Side panel manager
10. `assets/meetings/js/ui-notifications.js` — Toast notifications
11. `assets/meetings/js/participants.js` — Participant management
12. `assets/meetings/js/chat.js` — Real-time chat
13. `assets/meetings/js/whiteboard.js` — Collaborative whiteboard
14. `assets/meetings/js/screenshare.js` — Screen sharing
15. `assets/meetings/js/recording.js` — Meeting recording
16. `assets/meetings/js/polls.js` — Polling system
17. `assets/meetings/js/captions.js` — Live captions
18. `assets/meetings/js/attendance.js` — Attendance tracking
19. `assets/meetings/js/settings.js` — User settings
20. `assets/meetings/js/network.js` — Connection quality
21. `assets/meetings/css/meeting.css` — Main meeting styles
22. `assets/meetings/css/meeting-dark.css` — Dark theme variables
23. `assets/meetings/meeting.html` — Main meeting page
24. `assets/meetings/README.md` — Frontend documentation

### New Python Backend Modules
1. `meeting-server/room_manager.py` — Room state management (refactored from media_room.py)
2. `meeting-server/webrtc.py` — WebRTC signaling helpers
3. `meeting-server/websocket.py` — WebSocket connection manager
4. `meeting-server/participants.py` — Participant state
5. `meeting-server/recording.py` — Recording management
6. `meeting-server/chat.py` — Chat message relay
7. `meeting-server/polls.py` — Poll management
8. `meeting-server/attendance.py` — Attendance tracking
9. `meeting-server/whiteboard.py` — Whiteboard state relay
10. `meeting-server/captions.py` — Speech-to-text relay
11. `meeting-server/auth.py` — Auth token validation

### Implementation Order
1. ✅ Phase 1: Architecture Audit (COMPLETE)
2. Phase 2a: Refactor Python backend (remove OpenCV, modularize)
3. Phase 2b: Create core frontend files (CSS, meeting.html, webrtc.js)
4. Phase 2c: Implement meeting UI components (sidebar, chat, participants)
5. Phase 2d: Create Google Meet-style layout and controls
6. Phase 2e: Wire everything together (meeting.js controller)
7. Phase 2f: Update PHP integration (config, redirects)
8. Phase 2g: Update Docker/Apache config
9. Phase 2h: Verify everything works end-to-end