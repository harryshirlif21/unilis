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
from urllib.parse import urlparse
from typing import Dict, Optional

from fastapi import FastAPI, Query, WebSocket, WebSocketDisconnect
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import HTMLResponse, JSONResponse

from room_manager import RoomManager, ParticipantInfo
from websocket_manager import WebSocketManager
from chat_handler import ChatHandler, ChatMessage
from poll_handler import PollHandler
from whiteboard_handler import WhiteboardHandler
from attendance_handler import AttendanceHandler

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger("meeting-server")

app = FastAPI(title="UNILIS Meeting Server", version="2.0.0")
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

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


@app.get("/api/meetings/{meeting_id}/attendance")
async def get_attendance(meeting_id: int):
    summary = attendance_handler.get_meeting_summary(meeting_id)
    if not summary:
        return JSONResponse({"meeting_id": meeting_id, "participants": []})
    return JSONResponse(summary)


@app.get("/api/meetings/{meeting_id}/chat")
async def get_chat_history(meeting_id: int, limit: int = 50):
    return JSONResponse(chat_handler.get_history(meeting_id, limit))


@app.get("/api/meetings/{meeting_id}/polls")
async def get_polls(meeting_id: int):
    return JSONResponse(poll_handler.get_all_polls(meeting_id))


@app.get("/api/meetings/{meeting_id}/whiteboard")
async def get_whiteboard(meeting_id: int):
    return JSONResponse(whiteboard_handler.get_state(meeting_id))


# ============================================================
# Frontend UI Endpoints (Backward Compatible)
# ============================================================

# Static frontend served by the PHP/Apache container. Must not point at
# assets/meetings/, which is a writable recordings volume mounted over in
# production and therefore hides files baked into the image.
FRONTEND_BASE = "/assets/meeting-app"


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

    return f"""<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>{escaped["title"]} - UNILIS Meeting</title>
    <link rel="stylesheet" href="{FRONTEND_BASE}/css/meeting.css">
    <link rel="stylesheet" href="{FRONTEND_BASE}/css/meeting-dark.css">
</head>
<body>
    <div id="app"></div>
    <script>
        window.__MEETING_CONFIG__ = {meeting_config};
    </script>
    <script src="{FRONTEND_BASE}/js/webrtc-core.js"></script>
    <script src="{FRONTEND_BASE}/js/webrtc-media.js"></script>
    <script src="{FRONTEND_BASE}/js/webrtc-rooms.js"></script>
    <script src="{FRONTEND_BASE}/js/ui-theme.js"></script>
    <script src="{FRONTEND_BASE}/js/ui-layout.js"></script>
    <script src="{FRONTEND_BASE}/js/ui-sidebar.js"></script>
    <script src="{FRONTEND_BASE}/js/ui-notifications.js"></script>
    <script src="{FRONTEND_BASE}/js/participants.js"></script>
    <script src="{FRONTEND_BASE}/js/chat.js"></script>
    <script src="{FRONTEND_BASE}/js/whiteboard.js"></script>
    <script src="{FRONTEND_BASE}/js/screenshare.js"></script>
    <script src="{FRONTEND_BASE}/js/polls.js"></script>
    <script src="{FRONTEND_BASE}/js/captions.js"></script>
    <script src="{FRONTEND_BASE}/js/attendance.js"></script>
    <script src="{FRONTEND_BASE}/js/settings.js"></script>
    <script src="{FRONTEND_BASE}/js/network.js"></script>
    <script src="{FRONTEND_BASE}/js/meeting.js"></script>
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

                # Send join confirmation
                await send_json(ws, {
                    "type": "joined",
                    "meeting_id": meeting_id,
                    "user_id": user_id,
                    "role": role,
                    "display_name": display_name,
                    "participants": room.participant_list(),
                })

                # Broadcast updated participants to all
                await ws_manager.broadcast_signaling(meeting_id, {
                    "type": "participants",
                    "meeting_id": meeting_id,
                    "participants": room.participant_list(),
                })

                # Send chat history to new joiner
                chat_history = chat_handler.get_history(meeting_id)
                if chat_history:
                    await send_json(ws, {
                        "type": "chat_history",
                        "meeting_id": meeting_id,
                        "messages": chat_history,
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

                if to_user_id is not None:
                    # Send to specific user
                    await ws_manager.send_to_signaling(meeting_id, int(to_user_id), signal_payload)
                else:
                    # Broadcast to all except sender
                    await ws_manager.broadcast_signaling(meeting_id, signal_payload, exclude_user_id=user_id)

            # ---- PARTICIPANT STATE UPDATE ----
            elif msg_type == "participant_update":
                if meeting_id is None or user_id is None:
                    continue
                room = rooms.get_room(meeting_id)
                p = room.get_participant(user_id)
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
                        p.hand_raised = message["hand_raised"]
                    if "is_muted" in message:
                        p.is_muted = message["is_muted"]

                await ws_manager.broadcast_signaling(meeting_id, {
                    "type": "participants",
                    "meeting_id": meeting_id,
                    "participants": room.participant_list(),
                })

            # ---- HOST CONTROLS ----
            elif msg_type == "mute_participant":
                if meeting_id is None or user_id is None:
                    continue
                target_id = int(message.get("target_user_id", 0))
                room = rooms.get_room(meeting_id)
                host = room.get_participant(user_id)
                target = room.get_participant(target_id)
                if host and host.role == "lecturer" and target:
                    target.is_muted = True
                    target.audio_enabled = False
                    await ws_manager.send_to_signaling(meeting_id, target_id, {
                        "type": "host_action",
                        "action": "mute",
                    })
                    await ws_manager.broadcast_signaling(meeting_id, {
                        "type": "participants",
                        "meeting_id": meeting_id,
                        "participants": room.participant_list(),
                    })

            elif msg_type == "remove_participant":
                if meeting_id is None or user_id is None:
                    continue
                target_id = int(message.get("target_user_id", 0))
                room = rooms.get_room(meeting_id)
                host = room.get_participant(user_id)
                if host and host.role == "lecturer":
                    await ws_manager.send_to_signaling(meeting_id, target_id, {
                        "type": "host_action",
                        "action": "removed",
                    })
                    room.remove_participant(target_id)
                    attendance_handler.leave(meeting_id, target_id)
                    ws_manager.remove_user(meeting_id, target_id)
                    await ws_manager.broadcast_signaling(meeting_id, {
                        "type": "participants",
                        "meeting_id": meeting_id,
                        "participants": room.participant_list(),
                    })

            elif msg_type == "lock_meeting":
                if meeting_id is None:
                    continue
                room = rooms.get_room(meeting_id)
                room.is_locked = message.get("locked", True)
                await ws_manager.broadcast_signaling(meeting_id, {
                    "type": "meeting_locked",
                    "meeting_id": meeting_id,
                    "locked": room.is_locked,
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
                sender_name = p.display_name if p else display_name or "Unknown"
                chat_msg = ChatMessage(
                    meeting_id=meeting_id,
                    sender_id=user_id,
                    sender_name=sender_name,
                    text=text,
                    message_type=message.get("message_type", "text"),
                    reply_to=message.get("reply_to"),
                )
                msg_dict = chat_handler.add_message(chat_msg)
                await ws_manager.broadcast_signaling(meeting_id, msg_dict)

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
                await ws_manager.broadcast_signaling(meeting_id, {
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
                action = message.get("action", {})
                if action:
                    result = whiteboard_handler.add_action(meeting_id, user_id, action)
                    await ws_manager.broadcast_signaling(meeting_id, {
                        "type": "whiteboard_action",
                        "meeting_id": meeting_id,
                        "action": result,
                    }, exclude_user_id=user_id)

            elif msg_type == "whiteboard_state_request":
                if meeting_id is None:
                    continue
                state = whiteboard_handler.get_state(meeting_id)
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
                if p and p.role == "lecturer":
                    await ws_manager.broadcast_signaling(meeting_id, {
                        "type": "recording_started",
                        "meeting_id": meeting_id,
                        "started_by": user_id,
                    })

            elif msg_type == "recording_stop":
                if meeting_id is None:
                    continue
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
                await ws_manager.broadcast_signaling(meeting_id, {
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

            await ws_manager.broadcast_signaling(meeting_id, {
                "type": "participants",
                "meeting_id": meeting_id,
                "participants": room.participant_list(),
            })

            # Clean up chat if room is empty
            if room.is_empty():
                pass  # Keep chat history for late joiners

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