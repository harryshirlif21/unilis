"""
UNILIS Meeting Media Server
Python service for rendering camera and screen-share frames.
PHP handles auth/state; JS captures media and bridges frames here.
"""

from __future__ import annotations

import asyncio
import json
import logging
from typing import Dict, Optional, Set

from fastapi import FastAPI, WebSocket, WebSocketDisconnect
from fastapi.middleware.cors import CORSMiddleware

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
