"""
UNILIS Meeting Media Server
Production-ready WebRTC signaling and collaborative meeting server.
PHP handles auth/state; this server provides signaling, chat, polls, whiteboard,
attendance tracking, and serves the frontend UI.
"""
from __future__ import annotations

import html
import json
import logging
import os
import time
from urllib.parse import urlparse
from typing import Dict, Optional

from fastapi import Depends, FastAPI, Header, HTTPException, Query, WebSocket, WebSocketDisconnect
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import HTMLResponse, JSONResponse

from room_manager import (
    CAPABILITIES,
    POLICIES,
    MeetingRoom,
    ParticipantInfo,
    RoomManager,
)
from websocket_manager import WebSocketManager
from chat_handler import ChatHandler, ChatMessage
from poll_handler import PollHandler
from whiteboard_handler import WhiteboardHandler
from attendance_handler import AttendanceHandler

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger("meeting-server")

app = FastAPI(title="UNILIS Meeting Server", version="2.0.0")

# Comma-separated origin allowlist, e.g. "https://unilis.jhubafrica.com".
# Defaults to "*" to preserve existing behaviour.
ALLOWED_ORIGINS = [
    origin.strip()
    for origin in os.getenv("MEETING_ALLOWED_ORIGINS", "*").split(",")
    if origin.strip()
]
_WILDCARD_ORIGIN = ALLOWED_ORIGINS == ["*"]

app.add_middleware(
    CORSMiddleware,
    allow_origins=ALLOWED_ORIGINS,
    # Credentials cannot be combined with a wildcard origin — browsers reject the
    # response outright — so only enable them for an explicit allowlist.
    allow_credentials=not _WILDCARD_ORIGIN,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Shared secret for the read-only data endpoints below. Unset means open, which
# keeps existing deployments working; the browser never calls those routes (the
# frontend talks WebSocket only), so requiring a key does not affect the UI.
MEETING_API_KEY = os.getenv("MEETING_API_KEY", "").strip()


def require_api_key(x_api_key: Optional[str] = Header(None, alias="X-API-Key")) -> None:
    if not MEETING_API_KEY:
        return
    if x_api_key != MEETING_API_KEY:
        raise HTTPException(status_code=401, detail="Invalid or missing X-API-Key")

# Global state
rooms = RoomManager()
ws_manager = WebSocketManager()
chat_handler = ChatHandler()
poll_handler = PollHandler()
whiteboard_handler = WhiteboardHandler()
attendance_handler = AttendanceHandler()

# ============================================================
# Helper functions
# ============================================================

async def send_json(ws: WebSocket, payload: dict) -> None:
    await ws.send_text(json.dumps(payload))


# ============================================================
# Room broadcast helpers
#
# The roster and the room settings are two separate broadcasts. The roster
# changes on every mute and every camera toggle, several times a second in a
# busy room; the settings change when a host deliberately changes one. Sending
# them together would mean rebuilding the settings panel under the host's cursor
# every time somebody unmuted.
# ============================================================

async def broadcast_participants(meeting_id: int, room: MeetingRoom) -> None:
    await ws_manager.broadcast_signaling(meeting_id, {
        "type": "participants",
        "meeting_id": meeting_id,
        "participants": room.participant_list(),
    })


async def broadcast_room_state(meeting_id: int, room: MeetingRoom) -> None:
    await ws_manager.broadcast_signaling(meeting_id, {
        "type": "room_state",
        "meeting_id": meeting_id,
        "state": room.state_dict(),
    })


async def notify_hosts(meeting_id: int, room: MeetingRoom, payload: dict) -> None:
    """Send to the hosts only — used for anything that needs a decision."""
    await ws_manager.broadcast_signaling_to(
        meeting_id, [p.user_id for p in room.hosts()], payload
    )


async def broadcast_to_breakout(
    meeting_id: int,
    room: MeetingRoom,
    user_id: int,
    payload: dict,
    exclude_user_id: Optional[int] = None,
) -> None:
    """Send to everyone sharing this user's breakout room, including the main room."""
    await ws_manager.broadcast_signaling_to(
        meeting_id,
        room.user_ids_in_breakout_of(user_id),
        payload,
        exclude_user_id=exclude_user_id,
    )


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


# ============================================================
# Health & Admin Endpoints
# ============================================================

@app.get("/health")
async def health():
    ws_health = ws_manager.health()
    return {
        "status": "ok",
        "service": "unilis-meeting-server",
        "version": "2.0.0",
        "active_rooms": rooms.active_room_count(),
        **ws_health,
    }


@app.get("/api/meetings/{meeting_id}/attendance", dependencies=[Depends(require_api_key)])
async def get_attendance(meeting_id: int):
    summary = attendance_handler.get_meeting_summary(meeting_id)
    if not summary:
        return JSONResponse({"meeting_id": meeting_id, "participants": []})
    return JSONResponse(summary)


@app.get("/api/meetings/{meeting_id}/chat", dependencies=[Depends(require_api_key)])
async def get_chat_history(meeting_id: int, limit: int = 50):
    return JSONResponse(chat_handler.get_history(meeting_id, limit))


@app.get("/api/meetings/{meeting_id}/polls", dependencies=[Depends(require_api_key)])
async def get_polls(meeting_id: int):
    return JSONResponse(poll_handler.get_all_polls(meeting_id))


@app.get("/api/meetings/{meeting_id}/whiteboard", dependencies=[Depends(require_api_key)])
async def get_whiteboard(meeting_id: int):
    return JSONResponse(whiteboard_handler.get_state(meeting_id))


# ============================================================
# Frontend UI Endpoints (Backward Compatible)
# ============================================================

# Static frontend served by the PHP/Apache container. Must not point at
# assets/meetings/, which is a writable recordings volume mounted over in
# production and therefore hides files baked into the image.
FRONTEND_BASE = "/assets/meeting-app"

# Cache-busting token for the asset URLs below. Those files are served with
# Cache-Control: max-age=46736 (~13h), so without a token a browser keeps
# running the previous deploy's JavaScript long after the fix is live.
#
# This container does not mount the checkout, so it cannot stat the files the
# way assets/meeting-app/meeting.php does. It is rebuilt and recreated on every
# deploy instead, so process start time changes exactly when the assets might
# have. A restart with no code change costs one extra fetch per client.
ASSET_VERSION = os.getenv("MEETING_ASSET_VERSION") or str(int(time.time()))


def frontend_asset(relative_path: str) -> str:
    """Frontend asset URL with the cache-busting version appended."""
    return f"{FRONTEND_BASE}/{relative_path}?v={ASSET_VERSION}"


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
    """Serve the new frontend meeting page with meeting data embedded."""
    return serve_meeting_page(
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
        is_host=True,
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
    """Serve the new frontend meeting page for joiners."""
    return serve_meeting_page(
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
        is_host=False,
    )


def serve_meeting_page(
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
    is_host: bool,
) -> str:
    """Render the meeting page with embedded configuration."""
    escaped = {
        "title": html.escape(title),
        "unit_name": html.escape(unit_name),
        "lecturer_name": html.escape(lecturer_name),
        "scheduled_time": html.escape(scheduled_time),
        "display_name": html.escape(display_name),
        "back_url": html.escape(back_url),
        "external_link": html.escape(external_link),
    }

    meeting_config = json.dumps({
        "meeting_id": meeting_id,
        "user_id": user_id,
        "role": role,
        "display_name": display_name,
        "title": title,
        "unit_name": unit_name,
        "lecturer_name": lecturer_name,
        "scheduled_time": scheduled_time,
        "duration": duration,
        "external_link": external_link,
        "back_url": back_url,
        "is_host": is_host,
        "ws_signaling_url": signaling_ws_url(),
        "ws_media_url": os.getenv("MEETING_MEDIA_WS_URL", "/ws/media"),
    })

    # Load order matters: webrtc-core defines the namespace the rest attach to,
    # and meeting.js boots the app, so it stays last. Keep this list in step
    # with assets/meeting-app/meeting.php, which renders the same page.
    scripts = "\n".join(
        f'    <script src="{frontend_asset("js/" + name)}"></script>'
        for name in (
            "webrtc-core.js",
            "webrtc-media.js",
            "webrtc-rooms.js",
            "ui-theme.js",
            "ui-layout.js",
            "ui-sidebar.js",
            "ui-notifications.js",
            "participants.js",
            "chat.js",
            "breakouts.js",
            "whiteboard.js",
            "screenshare.js",
            "polls.js",
            "captions.js",
            "attendance.js",
            "settings.js",
            "network.js",
            "meeting.js",
        )
    )

    return f"""<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>{escaped["title"]} - UNILIS Meeting</title>
    <link rel="stylesheet" href="{frontend_asset("css/meeting.css")}">
    <link rel="stylesheet" href="{frontend_asset("css/meeting-dark.css")}">
</head>
<body>
    <div id="app"></div>
    <script>
        window.__MEETING_CONFIG__ = {meeting_config};
    </script>
{scripts}
</body>
</html>"""


# ============================================================
# Signaling WebSocket (/ws/signaling)
# ============================================================

@app.websocket("/ws/signaling")
async def signaling_websocket(ws: WebSocket):
    await ws.accept()

    meeting_id: Optional[int] = None
    user_id: Optional[int] = None
    display_name: Optional[str] = None
    role: Optional[str] = None

    try:
        while True:
            raw = await ws.receive_text()
            message = json.loads(raw)
            msg_type = message.get("type")

            # ---- JOIN ----
            if msg_type == "join":
                meeting_id = int(message["meeting_id"])
                user_id = int(message["user_id"])
                role = message.get("role", "student")
                display_name = message.get("display_name", f"User {user_id}")

                # Register connection
                ws_manager.add_signaling(meeting_id, user_id, ws)

                # Add to room
                room = rooms.get_room(meeting_id)
                room.add_participant(user_id, role, display_name)

                # Track attendance
                attendance_handler.join(meeting_id, user_id, display_name, role)

                # Send join confirmation. state carries the policies and rooms, so
                # the client knows on its first frame whether it may share a
                # screen rather than showing a control that will be refused.
                await send_json(ws, {
                    "type": "joined",
                    "meeting_id": meeting_id,
                    "user_id": user_id,
                    "role": role,
                    "display_name": display_name,
                    "participants": room.participant_list(),
                    "state": room.state_dict(),
                })

                # Broadcast updated participants to all
                await broadcast_participants(meeting_id, room)

                # Send chat history for the room this participant is actually in.
                chat_history = chat_handler.get_history(
                    meeting_id, breakout_id=room.get_participant(user_id).breakout_id
                )
                if chat_history:
                    await send_json(ws, {
                        "type": "chat_history",
                        "meeting_id": meeting_id,
                        "messages": chat_history,
                    })

                # Replay the whiteboard so a late joiner sees what is already on
                # it rather than an empty board everyone else has drawn on.
                board = whiteboard_handler.get_state(meeting_id, None)
                if board["items"]:
                    await send_json(ws, {
                        "type": "whiteboard_state",
                        "meeting_id": meeting_id,
                        "state": board,
                    })

                logger.info("User %s (%s) joined meeting %s as %s", user_id, display_name, meeting_id, role)

            # ---- SIGNAL (WebRTC) ----
            elif msg_type == "signal":
                if meeting_id is None or user_id is None:
                    await send_json(ws, {"type": "error", "message": "Not joined"})
                    continue

                signal_type = message.get("signal_type", "message")
                to_user_id = message.get("to_user_id")
                payload_data = message.get("payload", {})

                signal_payload = {
                    "type": "signal",
                    "meeting_id": meeting_id,
                    "from_user_id": user_id,
                    "signal_type": signal_type,
                    "payload": payload_data,
                }

                room = rooms.get_room(meeting_id)

                if to_user_id is not None:
                    # A peer connection may only be negotiated inside one
                    # breakout room. Without this check the breakout is cosmetic:
                    # a client that kept offering across the split would still
                    # see and hear the other group.
                    target = room.get_participant(int(to_user_id))
                    sender = room.get_participant(user_id)
                    if (
                        target is not None
                        and sender is not None
                        and target.breakout_id == sender.breakout_id
                    ):
                        await ws_manager.send_to_signaling(meeting_id, int(to_user_id), signal_payload)
                else:
                    await broadcast_to_breakout(
                        meeting_id, room, user_id, signal_payload, exclude_user_id=user_id
                    )

            # ---- PARTICIPANT STATE UPDATE ----
            elif msg_type == "participant_update":
                if meeting_id is None or user_id is None:
                    continue
                room = rooms.get_room(meeting_id)
                p = room.get_participant(user_id)
                hands_changed = False

                if p:
                    if "audio_enabled" in message:
                        p.audio_enabled = message["audio_enabled"]
                        session = attendance_handler.get_session(meeting_id, user_id)
                        if session:
                            session.set_mic(p.audio_enabled)
                    if "video_enabled" in message:
                        p.video_enabled = message["video_enabled"]
                        session = attendance_handler.get_session(meeting_id, user_id)
                        if session:
                            session.set_camera(p.video_enabled)
                    if "hand_raised" in message:
                        # set_hand rather than a bare assignment: it stamps when
                        # the hand went up, which is what orders the host's queue.
                        room.set_hand(user_id, bool(message["hand_raised"]))
                        hands_changed = True
                    if "is_muted" in message:
                        p.is_muted = message["is_muted"]
                    # participant_list() has always published screen_sharing, but
                    # nothing ever set it, so it was permanently False. The client
                    # needs it to know whose stream to put on the presentation
                    # stage - a viewer cannot tell a screen track from a camera
                    # track, since sharing replaces the sender's video track.
                    #
                    # Checked against the policy rather than taken on trust: this
                    # flag is what puts a stream on everyone's stage, so a client
                    # that simply announced it would have shared its screen
                    # whatever the host had set.
                    if "screen_sharing" in message:
                        wants = bool(message["screen_sharing"])
                        if wants and not room.may_share_screen(user_id):
                            p.screen_sharing = False
                            await send_json(ws, {
                                "type": "permission_denied",
                                "capability": "screen",
                                "message": "The host has not allowed you to share your screen.",
                            })
                        else:
                            p.screen_sharing = wants
                            session = attendance_handler.get_session(meeting_id, user_id)
                            if session:
                                session.set_screen_share(p.screen_sharing)

                await broadcast_participants(meeting_id, room)
                if hands_changed:
                    # The hand queue lives in the room state, so raising a hand
                    # has to refresh it as well as the roster.
                    await broadcast_room_state(meeting_id, room)

            # ---- HOST CONTROLS ----
            elif msg_type == "mute_participant":
                if meeting_id is None or user_id is None:
                    continue
                target_id = int(message.get("target_user_id", 0))
                room = rooms.get_room(meeting_id)
                host = room.get_participant(user_id)
                target = room.get_participant(target_id)
                if host and host.is_host() and target:
                    target.is_muted = True
                    target.audio_enabled = False
                    await ws_manager.send_to_signaling(meeting_id, target_id, {
                        "type": "host_action",
                        "action": "mute",
                    })
                    await broadcast_participants(meeting_id, room)

            elif msg_type == "mute_all":
                if meeting_id is None or user_id is None:
                    continue
                room = rooms.get_room(meeting_id)
                host = room.get_participant(user_id)
                if host and host.is_host():
                    # Hosts are left alone: muting the person running the meeting
                    # along with everyone else is never what the button means.
                    for target in list(room.participants.values()):
                        if target.is_host():
                            continue
                        target.is_muted = True
                        target.audio_enabled = False
                        await ws_manager.send_to_signaling(meeting_id, target.user_id, {
                            "type": "host_action",
                            "action": "mute",
                        })
                    await broadcast_participants(meeting_id, room)

            elif msg_type == "lower_hand":
                if meeting_id is None or user_id is None:
                    continue
                room = rooms.get_room(meeting_id)
                actor = room.get_participant(user_id)
                target_id = int(message.get("target_user_id", 0)) or user_id
                # Anyone may lower their own hand; only a host may lower someone
                # else's, which is how a host clears a hand after answering it.
                if actor and (actor.is_host() or target_id == user_id):
                    room.set_hand(target_id, False)
                    await ws_manager.send_to_signaling(meeting_id, target_id, {
                        "type": "host_action",
                        "action": "hand_lowered",
                    })
                    await broadcast_participants(meeting_id, room)
                    await broadcast_room_state(meeting_id, room)

            elif msg_type == "lower_all_hands":
                if meeting_id is None or user_id is None:
                    continue
                room = rooms.get_room(meeting_id)
                host = room.get_participant(user_id)
                if host and host.is_host():
                    room.lower_all_hands()
                    await ws_manager.broadcast_signaling(meeting_id, {
                        "type": "host_action",
                        "action": "hand_lowered",
                    })
                    await broadcast_participants(meeting_id, room)
                    await broadcast_room_state(meeting_id, room)

            elif msg_type == "remove_participant":
                if meeting_id is None or user_id is None:
                    continue
                target_id = int(message.get("target_user_id", 0))
                room = rooms.get_room(meeting_id)
                host = room.get_participant(user_id)
                if host and host.is_host():
                    await ws_manager.send_to_signaling(meeting_id, target_id, {
                        "type": "host_action",
                        "action": "removed",
                    })
                    room.remove_participant(target_id)
                    attendance_handler.leave(meeting_id, target_id)
                    ws_manager.remove_user(meeting_id, target_id)
                    await broadcast_participants(meeting_id, room)
                    await broadcast_room_state(meeting_id, room)

            elif msg_type == "lock_meeting":
                if meeting_id is None or user_id is None:
                    continue
                room = rooms.get_room(meeting_id)
                host = room.get_participant(user_id)
                if host and host.is_host():
                    room.is_locked = bool(message.get("locked", True))
                    await ws_manager.broadcast_signaling(meeting_id, {
                        "type": "meeting_locked",
                        "meeting_id": meeting_id,
                        "locked": room.is_locked,
                    })
                    await broadcast_room_state(meeting_id, room)

            # ---- PERMISSIONS ----
            elif msg_type == "request_permission":
                if meeting_id is None or user_id is None:
                    continue
                capability = message.get("capability", "")
                if capability not in CAPABILITIES:
                    continue
                room = rooms.get_room(meeting_id)
                asker = room.get_participant(user_id)
                if asker is None:
                    continue

                if room.may(user_id, capability):
                    # Already allowed. Answering rather than pestering the host
                    # matters because the client asks when its own state looks
                    # stale, which can race with a grant already in flight.
                    await send_json(ws, {
                        "type": "permission_changed",
                        "capability": capability,
                        "granted": True,
                        "by": None,
                    })
                    continue

                await notify_hosts(meeting_id, room, {
                    "type": "permission_request",
                    "meeting_id": meeting_id,
                    "user_id": user_id,
                    "display_name": asker.display_name,
                    "capability": capability,
                })
                await send_json(ws, {
                    "type": "permission_pending",
                    "capability": capability,
                })

            elif msg_type == "grant_permission":
                if meeting_id is None or user_id is None:
                    continue
                capability = message.get("capability", "")
                room = rooms.get_room(meeting_id)
                host = room.get_participant(user_id)
                if not (host and host.is_host()) or capability not in CAPABILITIES:
                    continue

                target_id = int(message.get("target_user_id", 0))
                granted = bool(message.get("granted", True))
                if not room.set_grant(target_id, capability, granted):
                    continue

                await ws_manager.send_to_signaling(meeting_id, target_id, {
                    "type": "permission_changed",
                    "capability": capability,
                    "granted": granted,
                    "by": host.display_name,
                })
                await broadcast_participants(meeting_id, room)

            elif msg_type == "set_policy":
                if meeting_id is None or user_id is None:
                    continue
                room = rooms.get_room(meeting_id)
                host = room.get_participant(user_id)
                if not (host and host.is_host()):
                    continue

                setting = message.get("setting", "")
                value = message.get("value")

                if setting in CAPABILITIES and value in POLICIES:
                    room.set_policy(setting, value)
                elif setting == "chat_enabled":
                    room.chat_enabled = bool(value)
                elif setting == "breakout_self_join":
                    room.breakout_self_join = bool(value)
                else:
                    continue

                await broadcast_room_state(meeting_id, room)
                # may_share_screen and may_whiteboard are published per
                # participant, so a policy change rewrites the roster too.
                await broadcast_participants(meeting_id, room)

            # ---- BREAKOUT ROOMS ----
            elif msg_type == "breakout_create":
                if meeting_id is None or user_id is None:
                    continue
                room = rooms.get_room(meeting_id)
                host = room.get_participant(user_id)
                if host and host.is_host():
                    if len(room.breakouts) >= 20:
                        await send_json(ws, {
                            "type": "error",
                            "message": "Twenty breakout rooms is the most one meeting can have.",
                        })
                        continue
                    room.create_breakout(message.get("name", ""))
                    await broadcast_room_state(meeting_id, room)

            elif msg_type == "breakout_rename":
                if meeting_id is None or user_id is None:
                    continue
                room = rooms.get_room(meeting_id)
                host = room.get_participant(user_id)
                if host and host.is_host():
                    room.rename_breakout(message.get("breakout_id", ""), message.get("name", ""))
                    await broadcast_room_state(meeting_id, room)

            elif msg_type == "breakout_delete":
                if meeting_id is None or user_id is None:
                    continue
                room = rooms.get_room(meeting_id)
                host = room.get_participant(user_id)
                if host and host.is_host():
                    if room.delete_breakout(message.get("breakout_id", "")):
                        await broadcast_room_state(meeting_id, room)
                        await broadcast_participants(meeting_id, room)

            elif msg_type == "breakout_close_all":
                if meeting_id is None or user_id is None:
                    continue
                room = rooms.get_room(meeting_id)
                host = room.get_participant(user_id)
                if host and host.is_host():
                    room.close_all_breakouts()
                    await ws_manager.broadcast_signaling(meeting_id, {
                        "type": "breakout_moved",
                        "meeting_id": meeting_id,
                        "breakout_id": None,
                        "name": "the main room",
                    })
                    await broadcast_room_state(meeting_id, room)
                    await broadcast_participants(meeting_id, room)

            elif msg_type == "breakout_assign":
                if meeting_id is None or user_id is None:
                    continue
                room = rooms.get_room(meeting_id)
                actor = room.get_participant(user_id)
                if actor is None:
                    continue

                target_id = int(message.get("target_user_id", 0)) or user_id
                breakout_id = message.get("breakout_id") or None
                moving_self = target_id == user_id

                # A host may move anyone. A participant may move themselves only
                # while the host has left self-joining on.
                if not actor.is_host() and not (moving_self and room.breakout_self_join):
                    await send_json(ws, {
                        "type": "error",
                        "message": "The host is assigning the breakout rooms for this meeting.",
                    })
                    continue

                if not room.assign_breakout(target_id, breakout_id):
                    continue

                name = (
                    room.breakouts[breakout_id].name
                    if breakout_id in room.breakouts
                    else "the main room"
                )
                # The mover has to tear down the peers they can no longer reach
                # and build the ones they now can, so they are told directly
                # rather than having to infer it from the roster.
                await ws_manager.send_to_signaling(meeting_id, target_id, {
                    "type": "breakout_moved",
                    "meeting_id": meeting_id,
                    "breakout_id": breakout_id,
                    "name": name,
                })

                history = chat_handler.get_history(meeting_id, breakout_id=breakout_id)
                await ws_manager.send_to_signaling(meeting_id, target_id, {
                    "type": "chat_history",
                    "meeting_id": meeting_id,
                    "messages": history,
                })

                board = whiteboard_handler.get_state(meeting_id, breakout_id)
                await ws_manager.send_to_signaling(meeting_id, target_id, {
                    "type": "whiteboard_state",
                    "meeting_id": meeting_id,
                    "state": board,
                })

                await broadcast_room_state(meeting_id, room)
                await broadcast_participants(meeting_id, room)

            elif msg_type == "breakout_broadcast":
                if meeting_id is None or user_id is None:
                    continue
                room = rooms.get_room(meeting_id)
                host = room.get_participant(user_id)
                text = str(message.get("text", "")).strip()
                if host and host.is_host() and text:
                    # A host announcement reaches every room, which is the one
                    # thing that deliberately crosses the breakout boundary.
                    await ws_manager.broadcast_signaling(meeting_id, {
                        "type": "breakout_announcement",
                        "meeting_id": meeting_id,
                        "from": host.display_name,
                        "text": text[:500],
                    })

            # ---- CHAT ----
            elif msg_type == "chat_message":
                if meeting_id is None or user_id is None:
                    continue
                text = message.get("text", "").strip()
                if not text:
                    continue
                room = rooms.get_room(meeting_id)
                p = room.get_participant(user_id)

                # A host can turn chat off, and can still use it themselves - the
                # switch is there to stop a class talking over a lecture, not to
                # stop the lecturer answering.
                if not room.chat_enabled and not (p and p.is_host()):
                    await send_json(ws, {
                        "type": "error",
                        "message": "The host has turned chat off.",
                    })
                    continue

                sender_name = p.display_name if p else display_name or "Unknown"
                chat_msg = ChatMessage(
                    meeting_id=meeting_id,
                    sender_id=user_id,
                    sender_name=sender_name,
                    text=text,
                    message_type=message.get("message_type", "text"),
                    reply_to=message.get("reply_to"),
                    breakout_id=p.breakout_id if p else None,
                )
                msg_dict = chat_handler.add_message(chat_msg)
                await broadcast_to_breakout(meeting_id, room, user_id, msg_dict)

            elif msg_type == "chat_delete":
                if meeting_id is None or user_id is None:
                    continue
                message_id = message.get("message_id", "")
                if chat_handler.delete_message(meeting_id, message_id, user_id):
                    await ws_manager.broadcast_signaling(meeting_id, {
                        "type": "chat_deleted",
                        "meeting_id": meeting_id,
                        "message_id": message_id,
                    })

            elif msg_type == "typing":
                if meeting_id is None or user_id is None:
                    continue
                room = rooms.get_room(meeting_id)
                await broadcast_to_breakout(meeting_id, room, user_id, {
                    "type": "typing",
                    "meeting_id": meeting_id,
                    "user_id": user_id,
                    "is_typing": message.get("is_typing", False),
                }, exclude_user_id=user_id)

            # ---- POLLS ----
            elif msg_type == "poll_create":
                if meeting_id is None or user_id is None:
                    continue
                poll = poll_handler.create_poll(
                    meeting_id=meeting_id,
                    creator_id=user_id,
                    question=message.get("question", ""),
                    options=message.get("options", []),
                    poll_type=message.get("poll_type", "multiple_choice"),
                    is_anonymous=message.get("is_anonymous", False),
                )
                await ws_manager.broadcast_signaling(meeting_id, poll.to_dict())

            elif msg_type == "poll_vote":
                if meeting_id is None or user_id is None:
                    continue
                poll_id = message.get("poll_id", "")
                options = message.get("options", [])
                poll = poll_handler.get_poll(poll_id)
                if poll and poll.vote(user_id, options):
                    if poll.is_anonymous:
                        await ws_manager.broadcast_signaling(meeting_id, {
                            "type": "poll_updated",
                            "poll_id": poll_id,
                            "results": poll.get_results(),
                        })

            elif msg_type == "poll_close":
                if meeting_id is None or user_id is None:
                    continue
                poll_id = message.get("poll_id", "")
                if poll_handler.close_poll(poll_id):
                    poll = poll_handler.get_poll(poll_id)
                    if poll:
                        await ws_manager.broadcast_signaling(meeting_id, {
                            "type": "poll_closed",
                            "poll_id": poll_id,
                            "results": poll.get_results(),
                        })

            # ---- WHITEBOARD ----
            elif msg_type == "whiteboard_action":
                if meeting_id is None or user_id is None:
                    continue
                room = rooms.get_room(meeting_id)
                p = room.get_participant(user_id)
                if p is None:
                    continue

                # Enforced here, not only by hiding the toolbar: a participant
                # who kept sending strokes would otherwise draw on everyone's
                # board while their own UI claimed to be read-only.
                if not room.may_whiteboard(user_id):
                    await send_json(ws, {
                        "type": "permission_denied",
                        "capability": "whiteboard",
                        "message": "The host has not allowed you to draw on the whiteboard.",
                    })
                    continue

                action = message.get("action", {})
                if not action:
                    continue

                result = whiteboard_handler.add_action(
                    meeting_id, user_id, action, breakout_id=p.breakout_id
                )
                if result is None:
                    continue

                # The sender is included rather than excluded. The board they see
                # is drawn from the shared item list, and the item's id and author
                # are assigned by the server - so the sender needs the echo to
                # learn the id its own undo will target.
                await broadcast_to_breakout(meeting_id, room, user_id, {
                    "type": "whiteboard_action",
                    "meeting_id": meeting_id,
                    "action": result,
                })

            elif msg_type == "whiteboard_state_request":
                if meeting_id is None or user_id is None:
                    continue
                room = rooms.get_room(meeting_id)
                p = room.get_participant(user_id)
                state = whiteboard_handler.get_state(
                    meeting_id, p.breakout_id if p else None
                )
                await send_json(ws, {
                    "type": "whiteboard_state",
                    "meeting_id": meeting_id,
                    "state": state,
                })

            # ---- RECORDING ----
            elif msg_type == "recording_start":
                if meeting_id is None or user_id is None:
                    continue
                room = rooms.get_room(meeting_id)
                p = room.get_participant(user_id)
                if p and p.is_host():
                    await ws_manager.broadcast_signaling(meeting_id, {
                        "type": "recording_started",
                        "meeting_id": meeting_id,
                        "started_by": user_id,
                    })

            elif msg_type == "recording_stop":
                if meeting_id is None or user_id is None:
                    continue
                # Starting was already host-only; stopping was not, so any
                # participant could clear the recording banner for the room.
                room = rooms.get_room(meeting_id)
                p = room.get_participant(user_id)
                if p and p.is_host():
                    await ws_manager.broadcast_signaling(meeting_id, {
                        "type": "recording_stopped",
                        "meeting_id": meeting_id,
                    })

            # ---- ATTENDANCE / SPEAKING ----
            elif msg_type == "speaking":
                if meeting_id is None or user_id is None:
                    continue
                session = attendance_handler.get_session(meeting_id, user_id)
                if session:
                    is_speaking = message.get("is_speaking", False)
                    if is_speaking:
                        session.speaking()
                    else:
                        session.stop_speaking()
                # Scoped to the breakout: a speaking ring around somebody you
                # cannot hear is noise rather than information.
                room = rooms.get_room(meeting_id)
                await broadcast_to_breakout(meeting_id, room, user_id, {
                    "type": "speaking",
                    "meeting_id": meeting_id,
                    "user_id": user_id,
                    "is_speaking": message.get("is_speaking", False),
                }, exclude_user_id=user_id)

            # ---- CAPTIONS ----
            elif msg_type == "caption":
                if meeting_id is None or user_id is None:
                    continue
                await ws_manager.broadcast_signaling(meeting_id, {
                    "type": "caption",
                    "meeting_id": meeting_id,
                    "user_id": user_id,
                    "text": message.get("text", ""),
                    "language": message.get("language", "en"),
                    "is_final": message.get("is_final", False),
                }, exclude_user_id=user_id)

            # ---- PING / KEEPALIVE ----
            elif msg_type == "ping":
                await send_json(ws, {"type": "pong"})

    except WebSocketDisconnect:
        pass
    except Exception as exc:
        logger.exception("Signaling WebSocket error: %s", exc)
    finally:
        if meeting_id is not None and user_id is not None:
            room = rooms.get_room(meeting_id)
            room.remove_participant(user_id)
            attendance_handler.leave(meeting_id, user_id)
            ws_manager.remove_user(meeting_id, user_id)
            rooms.remove_empty_rooms()

            await broadcast_participants(meeting_id, room)
            # Somebody leaving changes the hand queue and the room occupancy
            # counts, both of which live in the room state rather than the roster.
            await broadcast_room_state(meeting_id, room)

            if room.is_empty():
                # remove_empty_rooms() above has already discarded the room
                # object, and with it the breakout rooms and the policies. The
                # whiteboards were keyed by meeting id and survived that, so
                # reopening the same meeting next week came up with last week's
                # drawings still on the board.
                whiteboard_handler.clear_meeting(meeting_id)

                # Chat history is deliberately kept: it is scoped per meeting and
                # a late joiner reading what was said before they arrived is the
                # point of keeping it. Nothing on a board is addressed to anyone.

            logger.info("User %s left meeting %s", user_id, meeting_id)


# ============================================================
# Media WebSocket (/ws/media) - Simple relay (backward compatible)
# ============================================================

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
                ws_manager.add_media(meeting_id, user_id, ws)

                await send_json(ws, {
                    "type": "joined",
                    "meeting_id": meeting_id,
                    "user_id": user_id,
                })

            elif msg_type == "frame":
                if meeting_id is None or user_id is None:
                    continue
                # Relay frame data to all other media participants in the room
                await ws_manager.broadcast_media(meeting_id, {
                    "type": "frame",
                    "meeting_id": meeting_id,
                    "user_id": user_id,
                    "stream_type": message.get("stream_type", "camera"),
                    "data": message.get("data", ""),
                }, exclude_user_id=user_id)

            elif msg_type == "ping":
                await send_json(ws, {"type": "pong"})

    except WebSocketDisconnect:
        pass
    except Exception as exc:
        logger.exception("Media WebSocket error: %s", exc)
    finally:
        if meeting_id is not None and user_id is not None:
            ws_manager.remove_media(meeting_id, user_id)


# ============================================================
# Main entry point
# ============================================================

if __name__ == "__main__":
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