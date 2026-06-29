"""Meeting room state and frame compositing for camera / screen-share streams."""

from __future__ import annotations

import base64
import time
from dataclasses import dataclass, field
from typing import Dict, Optional, Set

import cv2
import numpy as np


STREAM_CAMERA = "camera"
STREAM_SCREEN = "screen"
STREAM_COMPOSITE = "composite"

DEFAULT_WIDTH = 1280
DEFAULT_HEIGHT = 720
JPEG_QUALITY = 75


@dataclass
class Participant:
    user_id: int
    role: str
    camera_frame: Optional[np.ndarray] = None
    screen_frame: Optional[np.ndarray] = None
    camera_updated: float = 0.0
    screen_updated: float = 0.0


@dataclass
class MeetingRoom:
    meeting_id: int
    participants: Dict[int, Participant] = field(default_factory=dict)
    subscribers: Set[int] = field(default_factory=set)

    def add_participant(self, user_id: int, role: str) -> Participant:
        participant = self.participants.get(user_id)
        if participant is None:
            participant = Participant(user_id=user_id, role=role)
            self.participants[user_id] = participant
        else:
            participant.role = role
        self.subscribers.add(user_id)
        return participant

    def remove_participant(self, user_id: int) -> None:
        self.participants.pop(user_id, None)
        self.subscribers.discard(user_id)

    def update_frame(self, user_id: int, stream_type: str, frame: np.ndarray) -> None:
        participant = self.participants.get(user_id)
        if participant is None:
            return

        now = time.time()
        if stream_type == STREAM_SCREEN:
            participant.screen_frame = frame
            participant.screen_updated = now
        else:
            participant.camera_frame = frame
            participant.camera_updated = now

    def clear_stream(self, user_id: int, stream_type: str) -> None:
        participant = self.participants.get(user_id)
        if participant is None:
            return
        if stream_type == STREAM_SCREEN:
            participant.screen_frame = None
            participant.screen_updated = 0.0
        else:
            participant.camera_frame = None
            participant.camera_updated = 0.0

    def get_frame(
        self, user_id: int, stream_type: str
    ) -> Optional[np.ndarray]:
        participant = self.participants.get(user_id)
        if participant is None:
            return None

        if stream_type == STREAM_COMPOSITE:
            return compose_participant_view(participant)
        if stream_type == STREAM_SCREEN:
            return participant.screen_frame
        return participant.camera_frame

    def participant_list(self) -> list:
        return [
            {
                "user_id": p.user_id,
                "role": p.role,
                "has_camera": p.camera_frame is not None,
                "has_screen": p.screen_frame is not None,
            }
            for p in self.participants.values()
        ]


class RoomManager:
    def __init__(self) -> None:
        self._rooms: Dict[int, MeetingRoom] = {}

    def get_room(self, meeting_id: int) -> MeetingRoom:
        if meeting_id not in self._rooms:
            self._rooms[meeting_id] = MeetingRoom(meeting_id=meeting_id)
        return self._rooms[meeting_id]

    def remove_empty_rooms(self) -> None:
        empty = [mid for mid, room in self._rooms.items() if not room.participants]
        for mid in empty:
            del self._rooms[mid]


def decode_jpeg_frame(data_b64: str) -> Optional[np.ndarray]:
    try:
        raw = base64.b64decode(data_b64)
        arr = np.frombuffer(raw, dtype=np.uint8)
        frame = cv2.imdecode(arr, cv2.IMREAD_COLOR)
        return frame
    except Exception:
        return None


def encode_jpeg_frame(frame: np.ndarray, quality: int = JPEG_QUALITY) -> str:
    ok, buffer = cv2.imencode(
        ".jpg", frame, [int(cv2.IMWRITE_JPEG_QUALITY), quality]
    )
    if not ok:
        raise ValueError("Failed to encode frame")
    return base64.b64encode(buffer).decode("ascii")


def resize_frame(frame: np.ndarray, width: int, height: int) -> np.ndarray:
    return cv2.resize(frame, (width, height), interpolation=cv2.INTER_AREA)


def compose_participant_view(participant: Participant) -> Optional[np.ndarray]:
    """Screen share as main view; camera as picture-in-picture when both exist."""
    screen = participant.screen_frame
    camera = participant.camera_frame

    if screen is not None and camera is not None:
        main = resize_frame(screen, DEFAULT_WIDTH, DEFAULT_HEIGHT)
        pip_w = int(DEFAULT_WIDTH * 0.22)
        pip_h = int(DEFAULT_HEIGHT * 0.22)
        pip = resize_frame(camera, pip_w, pip_h)

        x = DEFAULT_WIDTH - pip_w - 20
        y = DEFAULT_HEIGHT - pip_h - 20
        main[y : y + pip_h, x : x + pip_w] = pip

        cv2.rectangle(
            main,
            (x - 2, y - 2),
            (x + pip_w + 2, y + pip_h + 2),
            (255, 255, 255),
            2,
        )
        return main

    if screen is not None:
        return resize_frame(screen, DEFAULT_WIDTH, DEFAULT_HEIGHT)
    if camera is not None:
        return resize_frame(camera, DEFAULT_WIDTH, DEFAULT_HEIGHT)
    return None


def blank_frame(message: str = "Waiting for stream...") -> np.ndarray:
    frame = np.zeros((DEFAULT_HEIGHT, DEFAULT_WIDTH, 3), dtype=np.uint8)
    frame[:] = (30, 30, 30)
    cv2.putText(
        frame,
        message,
        (40, DEFAULT_HEIGHT // 2),
        cv2.FONT_HERSHEY_SIMPLEX,
        1.0,
        (200, 200, 200),
        2,
        cv2.LINE_AA,
    )
    return frame
