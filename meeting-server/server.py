"""
UNILIS Meeting Media Server
Python service for rendering camera and screen-share frames.
PHP handles auth/state; JS captures media and bridges frames here.
"""

from __future__ import annotations

import asyncio
import html
import json
import logging
import os
from urllib.parse import urlparse
from typing import Dict, Optional, Set

from fastapi import FastAPI, Query, WebSocket, WebSocketDisconnect
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import HTMLResponse

from media_room import (
    STREAM_CAMERA,
    STREAM_COMPOSITE,
    STREAM_SCREEN,
    RoomManager,
    blank_frame,
    decode_jpeg_frame,
    encode_jpeg_frame,
)

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger("meeting-server")

app = FastAPI(title="UNILIS Meeting Media Server", version="1.0.0")
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

rooms = RoomManager()

# meeting_id -> user_id -> WebSocket
_connections: Dict[int, Dict[int, WebSocket]] = {}
# user_id -> set of (target_user_id, stream_type)
_subscriptions: Dict[int, Set[tuple]] = {}
_broadcast_tasks: Dict[int, asyncio.Task] = {}

_signal_connections: Dict[int, Dict[int, WebSocket]] = {}
_signal_participants: Dict[int, Dict[int, dict]] = {}


@app.get("/health")
async def health():
    active_rooms = len(rooms._rooms)
    active_clients = sum(len(c) for c in _connections.values())
    return {
        "status": "ok",
        "service": "meeting-media-server",
        "active_rooms": active_rooms,
        "active_clients": active_clients,
    }


def is_internal_meeting_url(url: str) -> bool:
        trimmed = url.strip()
        if not trimmed:
                return True

        path = urlparse(trimmed).path or ""
        normalized = "/" + path.lstrip("/")
        internal_paths = (
                "/meeting_ide.php",
                "/lecturer/meeting_host.php",
                "/student/meeting_join.php",
                "/lecturer/meeting_ide.php",
                "/student/meeting_ide.php",
        )
        return any(normalized.endswith(candidate) for candidate in internal_paths)


def has_launchable_meeting_link(url: str) -> bool:
        return bool(url.strip()) and not is_internal_meeting_url(url)


def signaling_ws_url() -> str:
        explicit = os.getenv("MEETING_SIGNALING_WS_URL", "").strip()
        if explicit:
                return explicit
        return "/ws/signaling"


def render_meeting_page(
        *,
        page_role: str,
        meeting_id: int,
        user_id: int,
        role: str,
        display_name: str,
        title: str,
        unit_name: str,
        lecturer_name: str,
        scheduled_time: str,
        duration: int,
        external_link: str,
        back_url: str,
) -> str:
        escaped = {
                "title": html.escape(title),
                "unit_name": html.escape(unit_name),
                "lecturer_name": html.escape(lecturer_name),
                "scheduled_time": html.escape(scheduled_time),
                "display_name": html.escape(display_name),
                "external_link": html.escape(external_link),
                "back_url": html.escape(back_url),
        }
        can_launch = has_launchable_meeting_link(external_link)
        action_label = "Open External Meeting Link" if page_role == "lecturer" else "Open External Meeting Link"
        eyebrow = "Python Meeting Host" if page_role == "lecturer" else "Python Meeting Join"
        copy = (
                "This meeting page is served by the Python meeting server. "
                "A lightweight signaling socket is connected for participant presence and future meeting events."
        )
        payload = json.dumps(
                {
                        "meeting_id": meeting_id,
                        "user_id": user_id,
                        "role": role,
                        "display_name": display_name,
                }
        )
        button_html = (
                f'<a class="btn btn-primary" href="{escaped["external_link"]}" target="_blank" rel="noopener noreferrer">{action_label}</a>'
                if can_launch
                else ""
        )
        notice_html = (
            "<div class=\"notice notice-success\">"
            "Python is serving the meeting app UI and the presence signaling socket for this room is connected below."
            "</div>"
            if can_launch
            else "<div class=\"notice notice-success\">"
            "Python is serving this meeting room directly. No extra external meeting link is required."
            "</div>"
        )

        return f"""<!DOCTYPE html>
<html lang=\"en\">
<head>
    <meta charset=\"UTF-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <title>{escaped['title']}</title>
    <style>
        body {{ margin: 0; font-family: Arial, sans-serif; background: #f5f7fb; color: #1f2937; }}
        .page {{ max-width: 960px; margin: 0 auto; padding: 32px 20px 48px; }}
        .card {{ background: #fff; border: 1px solid #e5e7eb; border-radius: 18px; box-shadow: 0 16px 40px rgba(15, 23, 42, 0.08); padding: 28px; }}
        .eyebrow {{ font-size: 12px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: #2563eb; margin-bottom: 10px; }}
        h1 {{ margin: 0 0 10px; font-size: 30px; }}
        .subtext {{ margin: 0 0 22px; color: #6b7280; line-height: 1.6; }}
        .grid {{ display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 24px; }}
        .meta-item {{ background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 12px; padding: 14px 16px; }}
        .meta-label {{ font-size: 12px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 4px; }}
        .meta-value {{ font-size: 15px; font-weight: 600; word-break: break-word; }}
        .notice {{ border-radius: 14px; padding: 16px 18px; margin-bottom: 18px; line-height: 1.6; }}
        .notice-success {{ background: #eff6ff; border: 1px solid #93c5fd; color: #1d4ed8; }}
        .notice-warning {{ background: #fff7ed; border: 1px solid #fdba74; color: #9a3412; }}
        .actions {{ display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 24px; }}
        .btn {{ display: inline-flex; align-items: center; justify-content: center; padding: 12px 18px; border-radius: 12px; border: 1px solid transparent; text-decoration: none; font-weight: 700; cursor: pointer; font-size: 14px; }}
        .btn-primary {{ background: #2563eb; color: #fff; }}
        .btn-secondary {{ background: #fff; color: #1f2937; border-color: #d1d5db; }}
        .signal-panel {{ border: 1px solid #dbeafe; background: #f8fbff; border-radius: 16px; padding: 20px; }}
        .signal-row {{ display: flex; flex-wrap: wrap; gap: 12px; align-items: center; margin-bottom: 10px; }}
        .pill {{ display: inline-flex; align-items: center; gap: 8px; border-radius: 999px; padding: 8px 14px; font-size: 13px; font-weight: 700; }}
        .pill.pending {{ background: #fef3c7; color: #92400e; }}
        .pill.connected {{ background: #dcfce7; color: #166534; }}
        .participant-list {{ margin: 12px 0 0; padding-left: 18px; color: #334155; }}
        .link-box input {{ width: 100%; padding: 12px 14px; border-radius: 12px; border: 1px solid #d1d5db; font-size: 14px; box-sizing: border-box; }}
    </style>
</head>
<body>
    <div class=\"page\">
        <div class=\"card\">
            <div class=\"eyebrow\">{eyebrow}</div>
            <h1>{escaped['title']}</h1>
            <p class=\"subtext\">{copy}</p>

            <div class=\"grid\">
                <div class=\"meta-item\"><div class=\"meta-label\">Unit</div><div class=\"meta-value\">{escaped['unit_name']}</div></div>
                <div class=\"meta-item\"><div class=\"meta-label\">Lecturer</div><div class=\"meta-value\">{escaped['lecturer_name']}</div></div>
                <div class=\"meta-item\"><div class=\"meta-label\">Scheduled Time</div><div class=\"meta-value\">{escaped['scheduled_time']}</div></div>
                <div class=\"meta-item\"><div class=\"meta-label\">Duration</div><div class=\"meta-value\">{duration} minutes</div></div>
            </div>

            {notice_html}

            <div class=\"actions\">
                {button_html}
                <a class=\"btn btn-secondary\" href=\"{escaped['back_url']}\">Back</a>
            </div>

            <div class=\"signal-panel\">
                <div class=\"signal-row\">
                    <div id=\"signalStatus\" class=\"pill pending\">Signaling: connecting</div>
                    <div class=\"pill pending\" id=\"participantCount\">Participants: 0</div>
                </div>
                <div class=\"meta-label\">Signed-in User</div>
                <div class=\"meta-value\">{escaped['display_name']} ({html.escape(role)})</div>
                <ul class=\"participant-list\" id=\"participantList\"></ul>
            </div>

            <div class=\"link-box\" style=\"margin-top: 22px;\">
                <label for=\"meetingLink\"><strong>Configured meeting link</strong></label>
                <input id=\"meetingLink\" type=\"text\" readonly value=\"{escaped['external_link']}\">
            </div>
        </div>
    </div>

    <script>
        const meetingUser = {payload};
        const statusEl = document.getElementById('signalStatus');
        const participantCountEl = document.getElementById('participantCount');
        const participantListEl = document.getElementById('participantList');
        const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const wsUrl = ({json.dumps(signaling_ws_url())}.startsWith('ws')
            ? {json.dumps(signaling_ws_url())}
            : protocol + '//' + window.location.host + {json.dumps(signaling_ws_url())});
        const ws = new WebSocket(wsUrl);

        function updateStatus(text, cls) {{
            statusEl.textContent = text;
            statusEl.className = 'pill ' + cls;
        }}

        function renderParticipants(participants) {{
            participantCountEl.textContent = 'Participants: ' + participants.length;
            participantListEl.innerHTML = '';
            participants.forEach((participant) => {{
                const item = document.createElement('li');
                item.textContent = participant.display_name + ' (' + participant.role + ')';
                participantListEl.appendChild(item);
            }});
        }}

        ws.addEventListener('open', () => {{
            updateStatus('Signaling: connected', 'connected');
            ws.send(JSON.stringify({{ type: 'join', ...meetingUser }}));
        }});

        ws.addEventListener('message', (event) => {{
            const message = JSON.parse(event.data);
            if (message.type === 'participants' || message.type === 'joined') {{
                renderParticipants(message.participants || []);
            }}
        }});

        ws.addEventListener('close', () => updateStatus('Signaling: disconnected', 'pending'));
        ws.addEventListener('error', () => updateStatus('Signaling: error', 'pending'));
    </script>
</body>
</html>"""


def render_meeting_host_page(
        *,
        meeting_id: int,
        user_id: int,
        role: str,
        display_name: str,
        title: str,
        unit_name: str,
        lecturer_name: str,
        scheduled_time: str,
        duration: int,
        external_link: str,
        back_url: str,
) -> str:
        escaped = {
                "title": html.escape(title),
                "unit_name": html.escape(unit_name),
                "lecturer_name": html.escape(lecturer_name),
                "scheduled_time": html.escape(scheduled_time),
                "display_name": html.escape(display_name),
                "external_link": html.escape(external_link),
                "back_url": html.escape(back_url),
        }
        is_host = True
        page_label = "Host meeting"
        eyebrow = "Python Meeting Host"
        copy = (
                "This meeting UI uses the existing Python signaling socket at /ws/signaling. "
                "Video connections are negotiated peer-to-peer across the shared meeting room."
        )
        payload = json.dumps(
                {
                        "meeting_id": meeting_id,
                        "user_id": user_id,
                        "role": role,
                        "display_name": display_name,
                }
        )
        button_html = (
                f'<a class="btn btn-primary" href="{escaped["external_link"]}" target="_blank" rel="noopener noreferrer">Open external meeting link</a>'
                if has_launchable_meeting_link(external_link)
                else ""
        )
        notice_html = (
            "<div class=\"notice notice-success\">"
            "Python is serving the meeting app UI and the shared signaling socket. "
            "Use the controls below to manage participants and media."
            "</div>"
            if has_launchable_meeting_link(external_link)
            else "<div class=\"notice notice-success\">"
            "Python is serving this meeting room directly. No external meeting link is required."
            "</div>"
        )
        template = """<!DOCTYPE html>
<html lang=\"en\">
<head>
    <meta charset=\"UTF-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <title>__TITLE__</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f3f4f6; color: #111827; }
        .app-shell { display: flex; flex-direction: column; min-height: 100vh; }
        .hero { padding: 24px 20px 12px; max-width: 1280px; width: 100%; margin: 0 auto; }
        .hero h1 { margin: 0 0 8px; font-size: clamp(2rem, 1.8rem + 0.5vw, 3rem); }
        .hero p { margin: 0; color: #4b5563; max-width: 840px; line-height: 1.75; }
        .layout { display: grid; gap: 18px; padding: 0 20px 28px; max-width: 1280px; width: 100%; margin: 0 auto; }
        .top-panel { display: grid; gap: 16px; grid-template-columns: minmax(0, 1.2fr) 320px; }
        .summary-card, .sidebar-card { background: #ffffff; border: 1px solid #e5e7eb; border-radius: 24px; padding: 22px; box-shadow: 0 14px 32px rgba(15, 23, 42, 0.08); }
        .summary-card h2 { margin: 0 0 14px; font-size: 1.15rem; }
        .summary-grid { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(128px, 1fr)); margin-top: 16px; }
        .summary-item { background: #f8fafc; border-radius: 16px; padding: 14px; }
        .summary-label { display: block; margin-bottom: 6px; color: #6b7280; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.08em; }
        .summary-value { font-size: 1rem; font-weight: 700; color: #111827; }
        .notice { border-radius: 18px; padding: 16px 18px; margin-bottom: 18px; line-height: 1.6; }
        .notice-success { background: #eff6ff; border: 1px solid #93c5fd; color: #1d4ed8; }
        .actions { display: grid; gap: 12px; margin-top: 18px; }
        .btn { border: none; border-radius: 14px; padding: 14px 18px; font-weight: 700; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 10px; transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .btn:hover { transform: translateY(-1px); }
        .btn-primary { background: #2563eb; color: #ffffff; }
        .btn-secondary { background: #ffffff; color: #1f2937; border: 1px solid #d1d5db; }
        .btn-danger { background: #dc2626; color: #ffffff; }
        .meeting-card { background: #111827; border-radius: 30px; padding: 18px; display: grid; gap: 18px; }
        .meeting-meta { color: #e5e7eb; display: grid; gap: 8px; }
        .meeting-meta strong { display: block; color: #ffffff; font-size: 0.95rem; }
        .meeting-grid { display: grid; gap: 14px; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); }
        .video-tile { position: relative; aspect-ratio: 16 / 9; border-radius: 24px; overflow: hidden; background: #111827; display: grid; place-items: center; color: #f9fafb; min-height: 150px; }
        .video-tile video { width: 100%; height: 100%; object-fit: cover; }
        .tile-overlay { position: absolute; inset: 0; display: flex; flex-direction: column; justify-content: space-between; padding: 12px; pointer-events: none; }
        .tile-caption { display: flex; justify-content: space-between; gap: 10px; align-items: center; }
        .tile-title { font-size: 0.95rem; font-weight: 700; }
        .tile-chip { padding: 6px 10px; border-radius: 999px; font-size: 0.75rem; background: rgba(255,255,255,0.12); color: #f8fafc; }
        .controls-bar { display: flex; flex-wrap: wrap; gap: 12px; justify-content: center; align-items: center; margin-top: 10px; }
        .control-pill { display: inline-flex; padding: 12px 16px; border-radius: 999px; gap: 8px; align-items: center; font-weight: 700; border: 1px solid transparent; }
        .control-pill.active { background: #dbeafe; color: #1e40af; }
        .participant-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 10px; }
        .participant-item { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 12px 14px; border-radius: 16px; background: #f8fafc; border: 1px solid #e5e7eb; }
        .participant-name { font-weight: 700; color: #111827; }
        .participant-role { color: #6b7280; font-size: 0.88rem; }
        .status-banner { border-radius: 16px; padding: 12px 14px; background: #fef3c7; border: 1px solid #fde68a; color: #92400e; display: inline-flex; align-items: center; gap: 10px; margin-top: 8px; }
        @media (max-width: 900px) { .top-panel { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="app-shell">
        <section class="hero">
            <div class="eyebrow">__EYEBROW__</div>
            <h1>__TITLE__</h1>
            <p>__COPY__</p>
        </section>

        <main class="layout">
            <section class="top-panel">
                <div class="summary-card">
                    <h2>Meeting summary</h2>
                    <div class="summary-grid">
                        <div class="summary-item"><span class="summary-label">Unit</span><span class="summary-value">__UNIT_NAME__</span></div>
                        <div class="summary-item"><span class="summary-label">Lecturer</span><span class="summary-value">__LECTURER_NAME__</span></div>
                        <div class="summary-item"><span class="summary-label">Scheduled</span><span class="summary-value">__SCHEDULED_TIME__</span></div>
                        <div class="summary-item"><span class="summary-label">Duration</span><span class="summary-value">__DURATION__ minutes</span></div>
                    </div>

                    __NOTICE_HTML__

                    <div class="actions">
                        __BUTTON_HTML__
                        <a class="btn btn-secondary" href="__BACK_URL__">Back</a>
                        <button class="btn btn-secondary" id="toggleMicButton" type="button">Mute</button>
                        <button class="btn btn-secondary" id="toggleCameraButton" type="button">Camera Off</button>
                        <button class="btn btn-secondary" id="shareScreenButton" type="button">Share Screen</button>
                        <button class="btn btn-danger" id="leaveButton" type="button">End Meeting</button>
                    </div>

                    <div class="drawer">
                        <div>
                            <h3>Meeting status</h3>
                            <div class="status-banner" id="statusBanner">Connecting to signaling...</div>
                        </div>
                        <div>
                            <h3>Signed-in user</h3>
                            <div class="participant-item"><div>
                                <div class="participant-name">__DISPLAY_NAME__</div>
                                <div class="participant-role">__ROLE__</div>
                            </div></div>
                        </div>
                        <div>
                            <h3>Participants</h3>
                            <ul class="participant-list" id="participantList"></ul>
                        </div>
                    </div>
                </div>

                <aside class="sidebar-card">
                    <h2>Meeting link</h2>
                    <div class="summary-item" style="background:#f8fafc;border-radius:18px;">
                        <div class="summary-label">Configured meeting link</div>
                        <div class="summary-value">__EXTERNAL_LINK__</div>
                    </div>
                </aside>
            </section>

            <section class="meeting-card">
                <div class="meeting-meta">
                    <strong>__PAGE_LABEL__</strong>
                    <span>Camera, microphone, and screen-share are negotiated across peers using the shared signaling channel.</span>
                </div>
                <div class="meeting-grid" id="videoGrid"></div>
                <div class="controls-bar">
                    <span class="control-pill active">Meeting ID: __MEETING_ID__</span>
                    <span class="control-pill active" id="participantCount">Participants: 0</span>
                </div>
            </section>
        </main>
    </div>

    <script>
        const meetingUser = __PAYLOAD__;
        const isHost = true;
        const signalingPath = __SIGNALING_PATH__;
        const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const wsUrl = signalingPath.startsWith('ws') ? signalingPath : protocol + '//' + window.location.host + signalingPath;

        const statusBanner = document.getElementById('statusBanner');
        const participantCount = document.getElementById('participantCount');
        const participantList = document.getElementById('participantList');
        const videoGrid = document.getElementById('videoGrid');
        const toggleMicButton = document.getElementById('toggleMicButton');
        const toggleCameraButton = document.getElementById('toggleCameraButton');
        const shareScreenButton = document.getElementById('shareScreenButton');
        const leaveButton = document.getElementById('leaveButton');

        let signalingSocket = null;
        let localStream = null;
        const peerConnections = new Map();
        const remoteVideoElements = new Map();
        let micMuted = false;
        let cameraOff = false;
        let screenSharing = false;
        let localVideoElement = null;

        function setStatus(text, warning = false) {
            statusBanner.textContent = text;
            statusBanner.style.background = warning ? '#fef3c7' : '#dbeafe';
            statusBanner.style.color = warning ? '#92400e' : '#1e3a8a';
        }

        function updateParticipantPanel(participants) {
            participantCount.textContent = `Participants: ${participants.length}`;
            participantList.innerHTML = '';
            participants.forEach((participant) => {
                const item = document.createElement('li');
                item.className = 'participant-item';
                item.innerHTML = `
                    <div>
                        <div class="participant-name">${participant.display_name}</div>
                        <div class="participant-role">${participant.role}</div>
                    </div>
                    <div>${participant.user_id === meetingUser.user_id ? 'You' : ''}</div>
                `;
                participantList.appendChild(item);
            });
        }

        function createVideoTile(user) {
            const tile = document.createElement('div');
            tile.className = 'video-tile';
            tile.id = `participant-${user.user_id}`;
            const video = document.createElement('video');
            video.autoplay = true;
            video.playsInline = true;
            video.muted = user.user_id === meetingUser.user_id;
            tile.appendChild(video);
            const overlay = document.createElement('div');
            overlay.className = 'tile-overlay';
            overlay.innerHTML = `
                <div class="tile-caption">
                    <span class="tile-title">${user.display_name}</span>
                    <span class="tile-chip">${user.role}</span>
                </div>
            `;
            tile.appendChild(overlay);
            videoGrid.appendChild(tile);
            return video;
        }

        async function startLocalMedia() {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
                localStream = stream;
                if (!remoteVideoElements.has(meetingUser.user_id)) {
                    localVideoElement = createVideoTile(meetingUser);
                    localVideoElement.srcObject = stream;
                    localVideoElement.muted = true;
                }
                setStatus('Local camera and microphone enabled.');
                peerConnections.forEach((connection) => attachLocalTracks(connection.pc));
            } catch (error) {
                console.error('Local media failed', error);
                setStatus('Unable to access camera or microphone. Please allow media permissions.', true);
            }
        }

        function attachLocalTracks(pc) {
            if (!localStream) {
                return;
            }
            const existingSenders = pc.getSenders();
            localStream.getTracks().forEach((track) => {
                const sender = existingSenders.find((s) => s.track && s.track.kind === track.kind);
                if (sender) {
                    if (sender.track !== track) {
                        sender.replaceTrack(track);
                    }
                } else {
                    pc.addTrack(track, localStream);
                }
            });
        }

        function shouldCreateOffer(remoteUserId) {
            return meetingUser.user_id < remoteUserId;
        }

        function createPeerConnection(remoteParticipant) {
            const pc = new RTCPeerConnection();
            pc.onicecandidate = (event) => {
                if (event.candidate) {
                    sendSignaling({ type: 'signal', signal_type: 'ice', to_user_id: remoteParticipant.user_id, payload: event.candidate });
                }
            };
            pc.ontrack = (event) => {
                const remoteId = remoteParticipant.user_id;
                let videoElement = remoteVideoElements.get(remoteId);
                if (!videoElement) {
                    videoElement = createVideoTile(remoteParticipant);
                    remoteVideoElements.set(remoteId, videoElement);
                }
                videoElement.srcObject = event.streams[0];
            };
            pc.onconnectionstatechange = () => {
                if (pc.connectionState === 'failed' || pc.connectionState === 'disconnected') {
                    setStatus(`Connection lost with ${remoteParticipant.display_name}.`, true);
                }
            };
            if (localStream) {
                attachLocalTracks(pc);
            }
            peerConnections.set(remoteParticipant.user_id, { pc, remoteParticipant });
            return pc;
        }

        async function ensurePeer(remoteParticipant) {
            if (remoteParticipant.user_id === meetingUser.user_id) {
                return;
            }
            if (!peerConnections.has(remoteParticipant.user_id)) {
                const pc = createPeerConnection(remoteParticipant);
                if (shouldCreateOffer(remoteParticipant.user_id)) {
                    try {
                        const offer = await pc.createOffer();
                        await pc.setLocalDescription(offer);
                        sendSignaling({ type: 'signal', signal_type: 'offer', to_user_id: remoteParticipant.user_id, payload: offer });
                    } catch (error) {
                        console.error('Offer error', error);
                    }
                }
            }
        }

        async function handleSignal(message) {
            const from = message.from_user_id;
            const signalType = message.signal_type;
            const payload = message.payload;
            if (!from || from === meetingUser.user_id) {
                return;
            }
            let connection = peerConnections.get(from);
            if (!connection) {
                const remoteParticipant = { user_id: from, display_name: `Participant ${from}`, role: 'participant' };
                const pc = createPeerConnection(remoteParticipant);
                connection = { pc, remoteParticipant };
            }
            const pc = connection.pc;
            try {
                if (signalType === 'offer') {
                    await pc.setRemoteDescription(payload);
                    if (!localStream) {
                        await startLocalMedia();
                    }
                    attachLocalTracks(pc);
                    const answer = await pc.createAnswer();
                    await pc.setLocalDescription(answer);
                    sendSignaling({ type: 'signal', signal_type: 'answer', to_user_id: from, payload: answer });
                } else if (signalType === 'answer') {
                    await pc.setRemoteDescription(payload);
                } else if (signalType === 'ice') {
                    if (payload) {
                        await pc.addIceCandidate(payload);
                    }
                }
            } catch (error) {
                console.error('Signal handling failed', error);
            }
        }

        function cleanupPeer(remoteUserId) {
            const connection = peerConnections.get(remoteUserId);
            if (!connection) {
                return;
            }
            connection.pc.close();
            peerConnections.delete(remoteUserId);
            const videoTile = document.getElementById(`participant-${remoteUserId}`);
            if (videoTile) {
                videoTile.remove();
            }
            remoteVideoElements.delete(remoteUserId);
        }

        async function handleParticipantsEvent(participants) {
            updateParticipantPanel(participants);
            const remoteParticipants = participants.filter((participant) => participant.user_id !== meetingUser.user_id);
            const remoteIds = new Set(remoteParticipants.map((p) => p.user_id));
            for (const participant of remoteParticipants) {
                await ensurePeer(participant);
            }
            for (const existingId of Array.from(peerConnections.keys())) {
                if (!remoteIds.has(existingId)) {
                    cleanupPeer(existingId);
                }
            }
        }

        function sendSignaling(payload) {
            if (!signalingSocket || signalingSocket.readyState !== WebSocket.OPEN) {
                console.warn('Signaling socket is not open yet.');
                return;
            }
            signalingSocket.send(JSON.stringify(payload));
        }

        function connectSignaling() {
            signalingSocket = new WebSocket(wsUrl);
            signalingSocket.addEventListener('open', () => {
                setStatus('Connected to signaling service.');
                signalingSocket.send(JSON.stringify({ type: 'join', ...meetingUser }));
            });
            signalingSocket.addEventListener('message', async (event) => {
                const message = JSON.parse(event.data);
                if (message.type === 'joined' || message.type === 'participants') {
                    await handleParticipantsEvent(message.participants || []);
                } else if (message.type === 'signal') {
                    await handleSignal(message);
                }
            });
            signalingSocket.addEventListener('close', () => {
                setStatus('Signaling connection closed. Reconnecting...', true);
                setTimeout(connectSignaling, 2000);
            });
            signalingSocket.addEventListener('error', () => {
                setStatus('Signaling error. Check connection.', true);
            });
        }

        function toggleMic() {
            if (!localStream) {
                return;
            }
            micMuted = !micMuted;
            localStream.getAudioTracks().forEach((track) => {
                track.enabled = !micMuted;
            });
            toggleMicButton.textContent = micMuted ? 'Unmute' : 'Mute';
        }

        function toggleCamera() {
            if (!localStream) {
                return;
            }
            cameraOff = !cameraOff;
            localStream.getVideoTracks().forEach((track) => {
                track.enabled = !cameraOff;
            });
            toggleCameraButton.textContent = cameraOff ? 'Camera On' : 'Camera Off';
        }

        async function shareScreen() {
            if (screenSharing) {
                return;
            }
            try {
                const screenStream = await navigator.mediaDevices.getDisplayMedia({ video: true });
                const screenTrack = screenStream.getVideoTracks()[0];
                if (!screenTrack) {
                    return;
                }
                screenSharing = true;
                shareScreenButton.textContent = 'Stop Sharing';
                const audioTracks = localStream ? localStream.getAudioTracks() : [];
                const updatedStream = new MediaStream([...audioTracks, screenTrack]);
                localStream = updatedStream;
                peerConnections.forEach((connection) => attachLocalTracks(connection.pc));
                if (localVideoElement) {
                    localVideoElement.srcObject = updatedStream;
                }
                screenTrack.addEventListener('ended', async () => {
                    screenSharing = false;
                    shareScreenButton.textContent = 'Share Screen';
                    await startLocalMedia();
                });
            } catch (error) {
                console.error('Screen share failed', error);
            }
        }

        function closeMeeting() {
            if (signalingSocket && signalingSocket.readyState === WebSocket.OPEN) {
                signalingSocket.close();
            }
            peerConnections.forEach((connection) => connection.pc.close());
            peerConnections.clear();
            if (localStream) {
                localStream.getTracks().forEach((track) => track.stop());
            }
            setStatus('Meeting ended.');
            leaveButton.disabled = true;
            toggleMicButton.disabled = true;
            toggleCameraButton.disabled = true;
            shareScreenButton.disabled = true;
        }

        toggleMicButton.addEventListener('click', toggleMic);
        toggleCameraButton.addEventListener('click', toggleCamera);
        shareScreenButton.addEventListener('click', shareScreen);
        leaveButton.addEventListener('click', closeMeeting);

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            setStatus('Browser does not support camera/microphone access.', true);
        } else {
            startLocalMedia();
        }

        connectSignaling();
    </script>
</body>
</html>"""
        return (
                template
                .replace("__TITLE__", escaped["title"])
                .replace("__EYEBROW__", eyebrow)
                .replace("__COPY__", copy)
                .replace("__UNIT_NAME__", escaped["unit_name"])
                .replace("__LECTURER_NAME__", escaped["lecturer_name"])
                .replace("__SCHEDULED_TIME__", escaped["scheduled_time"])
                .replace("__DURATION__", str(duration))
                .replace("__NOTICE_HTML__", notice_html)
                .replace("__BUTTON_HTML__", button_html)
                .replace("__BACK_URL__", escaped["back_url"])
                .replace("__DISPLAY_NAME__", escaped["display_name"])
                .replace("__ROLE__", html.escape(role))
                .replace("__EXTERNAL_LINK__", escaped["external_link"] or "Not configured")
                .replace("__PAGE_LABEL__", page_label)
                .replace("__MEETING_ID__", str(meeting_id))
                .replace("__PAYLOAD__", payload)
                .replace("__SIGNALING_PATH__", json.dumps(signaling_ws_url()))
        )


async def broadcast_signaling_participants(meeting_id: int) -> None:
        participants = list(_signal_participants.get(meeting_id, {}).values())
        payload = {
                "type": "participants",
                "meeting_id": meeting_id,
                "participants": participants,
        }
        for ws in _signal_connections.get(meeting_id, {}).values():
                try:
                        await send_json(ws, payload)
                except Exception:
                        pass


@app.get("/meeting-ui/host", response_class=HTMLResponse)
async def meeting_ui_host(
        meeting_id: int = Query(...),
        user_id: int = Query(...),
        role: str = Query("lecturer"),
        display_name: str = Query("Lecturer"),
        title: str = Query("Meeting"),
        unit_name: str = Query(""),
        lecturer_name: str = Query(""),
        scheduled_time: str = Query(""),
        duration: int = Query(0),
        external_link: str = Query(""),
        back_url: str = Query("/"),
):
        return HTMLResponse(
                render_meeting_host_page(
                        meeting_id=meeting_id,
                        user_id=user_id,
                        role=role,
                        display_name=display_name,
                        title=title,
                        unit_name=unit_name,
                        lecturer_name=lecturer_name,
                        scheduled_time=scheduled_time,
                        duration=duration,
                        external_link=external_link,
                        back_url=back_url,
                )
        )


@app.get("/meeting-ui/join", response_class=HTMLResponse)
async def meeting_ui_join(
        meeting_id: int = Query(...),
        user_id: int = Query(...),
        role: str = Query("student"),
        display_name: str = Query("Student"),
        title: str = Query("Meeting"),
        unit_name: str = Query(""),
        lecturer_name: str = Query(""),
        scheduled_time: str = Query(""),
        duration: int = Query(0),
        external_link: str = Query(""),
        back_url: str = Query("/"),
):
        return HTMLResponse(
                render_meeting_page(
                        page_role="student",
                        meeting_id=meeting_id,
                        user_id=user_id,
                        role=role,
                        display_name=display_name,
                        title=title,
                        unit_name=unit_name,
                        lecturer_name=lecturer_name,
                        scheduled_time=scheduled_time,
                        duration=duration,
                        external_link=external_link,
                        back_url=back_url,
                )
        )


async def send_json(ws: WebSocket, payload: dict) -> None:
    await ws.send_text(json.dumps(payload))


async def broadcast_participants(meeting_id: int) -> None:
    room = rooms.get_room(meeting_id)
    payload = {
        "type": "participants",
        "meeting_id": meeting_id,
        "participants": room.participant_list(),
    }
    for ws in _connections.get(meeting_id, {}).values():
        try:
            await send_json(ws, payload)
        except Exception:
            pass


async def push_frame_to_subscribers(
    meeting_id: int, source_user_id: int, stream_type: str
) -> None:
    room = rooms.get_room(meeting_id)
    frame = room.get_frame(source_user_id, stream_type)
    if frame is None:
        return

    try:
        encoded = encode_jpeg_frame(frame)
    except ValueError:
        return

    payload = {
        "type": "frame",
        "meeting_id": meeting_id,
        "user_id": source_user_id,
        "stream_type": stream_type,
        "data": encoded,
    }

    meeting_clients = _connections.get(meeting_id, {})
    for subscriber_id, ws in meeting_clients.items():
        subs = _subscriptions.get(subscriber_id, set())
        if (source_user_id, stream_type) in subs or (
            source_user_id,
            STREAM_COMPOSITE,
        ) in subs:
            try:
                await send_json(ws, payload)
            except Exception:
                pass


async def subscription_broadcast_loop(meeting_id: int, user_id: int) -> None:
    """Push subscribed frames at ~15 fps even when publisher is idle."""
    room = rooms.get_room(meeting_id)
    key = (meeting_id, user_id)

    try:
        while user_id in _connections.get(meeting_id, {}):
            subs = _subscriptions.get(user_id, set())
            for target_user_id, stream_type in list(subs):
                frame = room.get_frame(target_user_id, stream_type)
                if frame is None:
                    frame = blank_frame("No video from participant")
                try:
                    encoded = encode_jpeg_frame(frame)
                except ValueError:
                    continue

                ws = _connections[meeting_id][user_id]
                await send_json(
                    ws,
                    {
                        "type": "frame",
                        "meeting_id": meeting_id,
                        "user_id": target_user_id,
                        "stream_type": stream_type,
                        "data": encoded,
                    },
                )
            await asyncio.sleep(1 / 15)
    except asyncio.CancelledError:
        pass
    finally:
        _broadcast_tasks.pop(key, None)


def start_broadcast_task(meeting_id: int, user_id: int) -> None:
    key = (meeting_id, user_id)
    existing = _broadcast_tasks.get(key)
    if existing and not existing.done():
        return
    _broadcast_tasks[key] = asyncio.create_task(
        subscription_broadcast_loop(meeting_id, user_id)
    )


def stop_broadcast_task(meeting_id: int, user_id: int) -> None:
    key = (meeting_id, user_id)
    task = _broadcast_tasks.pop(key, None)
    if task and not task.done():
        task.cancel()


@app.websocket("/ws/media")
async def media_websocket(ws: WebSocket):
    await ws.accept()

    meeting_id: Optional[int] = None
    user_id: Optional[int] = None

    try:
        while True:
            raw = await ws.receive_text()
            message = json.loads(raw)
            msg_type = message.get("type")

            if msg_type == "join":
                meeting_id = int(message["meeting_id"])
                user_id = int(message["user_id"])
                role = message.get("role", "student")

                if meeting_id not in _connections:
                    _connections[meeting_id] = {}
                _connections[meeting_id][user_id] = ws

                room = rooms.get_room(meeting_id)
                room.add_participant(user_id, role)

                if user_id not in _subscriptions:
                    _subscriptions[user_id] = set()

                await send_json(
                    ws,
                    {
                        "type": "joined",
                        "meeting_id": meeting_id,
                        "user_id": user_id,
                        "participants": room.participant_list(),
                    },
                )
                await broadcast_participants(meeting_id)
                logger.info("User %s joined meeting %s as %s", user_id, meeting_id, role)

            elif msg_type == "frame":
                if meeting_id is None or user_id is None:
                    await send_json(ws, {"type": "error", "message": "Not joined"})
                    continue

                stream_type = message.get("stream_type", STREAM_CAMERA)
                if stream_type not in (STREAM_CAMERA, STREAM_SCREEN):
                    stream_type = STREAM_CAMERA

                frame = decode_jpeg_frame(message.get("data", ""))
                if frame is None:
                    continue

                room = rooms.get_room(meeting_id)
                room.update_frame(user_id, stream_type, frame)

                await push_frame_to_subscribers(meeting_id, user_id, stream_type)
                if stream_type in (STREAM_CAMERA, STREAM_SCREEN):
                    await push_frame_to_subscribers(
                        meeting_id, user_id, STREAM_COMPOSITE
                    )

            elif msg_type == "clear_stream":
                if meeting_id is None or user_id is None:
                    continue
                stream_type = message.get("stream_type", STREAM_CAMERA)
                rooms.get_room(meeting_id).clear_stream(user_id, stream_type)
                await push_frame_to_subscribers(meeting_id, user_id, stream_type)
                await push_frame_to_subscribers(
                    meeting_id, user_id, STREAM_COMPOSITE
                )

            elif msg_type == "subscribe":
                if meeting_id is None or user_id is None:
                    await send_json(ws, {"type": "error", "message": "Not joined"})
                    continue

                target_user_id = int(message["target_user_id"])
                stream_type = message.get("stream_type", STREAM_COMPOSITE)
                if stream_type not in (
                    STREAM_CAMERA,
                    STREAM_SCREEN,
                    STREAM_COMPOSITE,
                ):
                    stream_type = STREAM_COMPOSITE

                _subscriptions.setdefault(user_id, set()).add(
                    (target_user_id, stream_type)
                )
                start_broadcast_task(meeting_id, user_id)

                await send_json(
                    ws,
                    {
                        "type": "subscribed",
                        "target_user_id": target_user_id,
                        "stream_type": stream_type,
                    },
                )

            elif msg_type == "unsubscribe":
                if user_id is None:
                    continue
                target_user_id = int(message["target_user_id"])
                stream_type = message.get("stream_type", STREAM_COMPOSITE)
                subs = _subscriptions.get(user_id, set())
                subs.discard((target_user_id, stream_type))
                if not subs and meeting_id is not None:
                    stop_broadcast_task(meeting_id, user_id)

            elif msg_type == "ping":
                await send_json(ws, {"type": "pong"})

    except WebSocketDisconnect:
        pass
    except Exception as exc:
        logger.exception("WebSocket error: %s", exc)
    finally:
        if meeting_id is not None and user_id is not None:
            stop_broadcast_task(meeting_id, user_id)
            _connections.get(meeting_id, {}).pop(user_id, None)
            rooms.get_room(meeting_id).remove_participant(user_id)
            _subscriptions.pop(user_id, None)
            rooms.remove_empty_rooms()
            await broadcast_participants(meeting_id)
            logger.info("User %s left meeting %s", user_id, meeting_id)


@app.websocket("/ws/signaling")
async def signaling_websocket(ws: WebSocket):
    await ws.accept()

    meeting_id: Optional[int] = None
    user_id: Optional[int] = None

    try:
        while True:
            raw = await ws.receive_text()
            message = json.loads(raw)
            msg_type = message.get("type")

            if msg_type == "join":
                meeting_id = int(message["meeting_id"])
                user_id = int(message["user_id"])
                role = message.get("role", "student")
                display_name = message.get("display_name", f"User {user_id}")

                _signal_connections.setdefault(meeting_id, {})[user_id] = ws
                _signal_participants.setdefault(meeting_id, {})[user_id] = {
                    "user_id": user_id,
                    "role": role,
                    "display_name": display_name,
                }

                await send_json(
                    ws,
                    {
                        "type": "joined",
                        "meeting_id": meeting_id,
                        "user_id": user_id,
                        "participants": list(_signal_participants[meeting_id].values()),
                    },
                )
                await broadcast_signaling_participants(meeting_id)

            elif msg_type == "signal":
                if meeting_id is None or user_id is None:
                    await send_json(ws, {"type": "error", "message": "Not joined"})
                    continue

                payload = {
                    "type": "signal",
                    "meeting_id": meeting_id,
                    "from_user_id": user_id,
                    "signal_type": message.get("signal_type", "message"),
                    "payload": message.get("payload", {}),
                }
                to_user_id = message.get("to_user_id")

                if to_user_id is None:
                    for target_user_id, target_ws in _signal_connections.get(meeting_id, {}).items():
                        if target_user_id == user_id:
                            continue
                        try:
                            await send_json(target_ws, payload)
                        except Exception:
                            pass
                else:
                    target_ws = _signal_connections.get(meeting_id, {}).get(int(to_user_id))
                    if target_ws is not None:
                        await send_json(target_ws, payload)

            elif msg_type == "ping":
                await send_json(ws, {"type": "pong"})

    except WebSocketDisconnect:
        pass
    except Exception as exc:
        logger.exception("Signaling WebSocket error: %s", exc)
    finally:
        if meeting_id is not None and user_id is not None:
            _signal_connections.get(meeting_id, {}).pop(user_id, None)
            if meeting_id in _signal_participants:
                _signal_participants[meeting_id].pop(user_id, None)
                if not _signal_participants[meeting_id]:
                    _signal_participants.pop(meeting_id, None)
            if meeting_id in _signal_connections and not _signal_connections[meeting_id]:
                _signal_connections.pop(meeting_id, None)
            await broadcast_signaling_participants(meeting_id)


if __name__ == "__main__":
    import os
    import uvicorn

    host = os.getenv("HOST", "0.0.0.0")
    port = int(os.getenv("PORT", "8765"))

    uvicorn.run(
        "server:app",
        host=host,
        port=port,
        reload=False,
        log_level="info",
        access_log=True,
    )
