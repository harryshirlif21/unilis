"""
UNILIS Meeting — WebSocket Connection Manager
Manages WebSocket connections per meeting room.
"""
from __future__ import annotations

import json
import logging
from typing import Dict, Optional, Set

from fastapi import WebSocket

logger = logging.getLogger("meeting-server")


class WebSocketManager:
    """Manages WebSocket connections grouped by meeting_id and type."""

    def __init__(self):
        # signaling: meeting_id -> user_id -> WebSocket
        self._signaling: Dict[int, Dict[int, WebSocket]] = {}
        # media: meeting_id -> user_id -> WebSocket
        self._media: Dict[int, Dict[int, WebSocket]] = {}

    # --- Signaling connections ---

    def add_signaling(self, meeting_id: int, user_id: int, ws: WebSocket) -> None:
        self._signaling.setdefault(meeting_id, {})[user_id] = ws

    def remove_signaling(self, meeting_id: int, user_id: int) -> None:
        self._signaling.get(meeting_id, {}).pop(user_id, None)
        if meeting_id in self._signaling and not self._signaling[meeting_id]:
            del self._signaling[meeting_id]

    def get_signaling(self, meeting_id: int, user_id: int) -> Optional[WebSocket]:
        return self._signaling.get(meeting_id, {}).get(user_id)

    def get_signaling_room(self, meeting_id: int) -> Dict[int, WebSocket]:
        return self._signaling.get(meeting_id, {})

    def signaling_exists(self, meeting_id: int) -> bool:
        return meeting_id in self._signaling

    # --- Media connections ---

    def add_media(self, meeting_id: int, user_id: int, ws: WebSocket) -> None:
        self._media.setdefault(meeting_id, {})[user_id] = ws

    def remove_media(self, meeting_id: int, user_id: int) -> None:
        self._media.get(meeting_id, {}).pop(user_id, None)
        if meeting_id in self._media and not self._media[meeting_id]:
            del self._media[meeting_id]

    def get_media(self, meeting_id: int, user_id: int) -> Optional[WebSocket]:
        return self._media.get(meeting_id, {}).get(user_id)

    def get_media_room(self, meeting_id: int) -> Dict[int, WebSocket]:
        return self._media.get(meeting_id, {})

    # --- Broadcast helpers ---

    async def broadcast_signaling(
        self, meeting_id: int, payload: dict, exclude_user_id: Optional[int] = None
    ) -> None:
        """Send a message to all signaling connections in a meeting."""
        connections = self._signaling.get(meeting_id, {})
        for uid, ws in connections.items():
            if exclude_user_id is not None and uid == exclude_user_id:
                continue
            try:
                await ws.send_text(json.dumps(payload))
            except Exception:
                logger.warning("Failed to send to user %s in meeting %s", uid, meeting_id)

    async def broadcast_media(
        self, meeting_id: int, payload: dict, exclude_user_id: Optional[int] = None
    ) -> None:
        """Send a message to all media connections in a meeting."""
        connections = self._media.get(meeting_id, {})
        for uid, ws in connections.items():
            if exclude_user_id is not None and uid == exclude_user_id:
                continue
            try:
                await ws.send_text(json.dumps(payload))
            except Exception:
                logger.warning("Failed to send media to user %s", uid)

    async def send_to_signaling(
        self, meeting_id: int, user_id: int, payload: dict
    ) -> bool:
        """Send a message to a specific signaling connection."""
        ws = self.get_signaling(meeting_id, user_id)
        if ws is None:
            return False
        try:
            await ws.send_text(json.dumps(payload))
            return True
        except Exception:
            logger.warning("Failed to send signaling to user %s", user_id)
            return False

    async def send_to_media(self, meeting_id: int, user_id: int, payload: dict) -> bool:
        """Send a message to a specific media connection."""
        ws = self.get_media(meeting_id, user_id)
        if ws is None:
            return False
        try:
            await ws.send_text(json.dumps(payload))
            return True
        except Exception:
            logger.warning("Failed to send media to user %s", user_id)
            return False

    # --- Cleanup ---

    def remove_user(self, meeting_id: int, user_id: int) -> None:
        self.remove_signaling(meeting_id, user_id)
        self.remove_media(meeting_id, user_id)

    def cleanup_room(self, meeting_id: int) -> None:
        self._signaling.pop(meeting_id, None)
        self._media.pop(meeting_id, None)

    @property
    def active_connections(self) -> int:
        count = 0
        for conns in self._signaling.values():
            count += len(conns)
        for conns in self._media.values():
            count += len(conns)
        return count

    def health(self) -> dict:
        return {
            "signaling_rooms": len(self._signaling),
            "media_rooms": len(self._media),
            "active_signaling": sum(len(c) for c in self._signaling.values()),
            "active_media": sum(len(c) for c in self._media.values()),
        }