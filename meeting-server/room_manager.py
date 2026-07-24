"""
UNILIS Meeting — Room State Manager
Manages meeting rooms, participants, and their state.
"""
from __future__ import annotations

import time
from dataclasses import dataclass, field
from typing import Dict, List, Optional, Set


@dataclass
class ParticipantInfo:
    user_id: int
    role: str
    display_name: str
    joined_at: float = field(default_factory=time.time)
    audio_enabled: bool = True
    video_enabled: bool = True
    screen_sharing: bool = False
    hand_raised: bool = False
    is_muted: bool = False
    connection_state: str = "connected"


@dataclass
class MeetingRoom:
    meeting_id: int
    participants: Dict[int, ParticipantInfo] = field(default_factory=dict)
    is_locked: bool = False
    created_at: float = field(default_factory=time.time)
    password: Optional[str] = None

    def add_participant(
        self, user_id: int, role: str, display_name: str
    ) -> ParticipantInfo:
        existing = self.participants.get(user_id)
        if existing:
            existing.role = role
            existing.display_name = display_name
            return existing
        participant = ParticipantInfo(
            user_id=user_id,
            role=role,
            display_name=display_name,
        )
        self.participants[user_id] = participant
        return participant

    def remove_participant(self, user_id: int) -> None:
        self.participants.pop(user_id, None)

    def get_participant(self, user_id: int) -> Optional[ParticipantInfo]:
        return self.participants.get(user_id)

    def participant_list(self) -> List[dict]:
        return [
            {
                "user_id": p.user_id,
                "role": p.role,
                "display_name": p.display_name,
                "audio_enabled": p.audio_enabled,
                "video_enabled": p.video_enabled,
                "screen_sharing": p.screen_sharing,
                "hand_raised": p.hand_raised,
                "is_muted": p.is_muted,
                "connection_state": p.connection_state,
            }
            for p in self.participants.values()
        ]

    def participant_count(self) -> int:
        return len(self.participants)

    def is_empty(self) -> bool:
        return len(self.participants) == 0


class RoomManager:
    def __init__(self) -> None:
        self._rooms: Dict[int, MeetingRoom] = {}

    def get_room(self, meeting_id: int) -> MeetingRoom:
        if meeting_id not in self._rooms:
            self._rooms[meeting_id] = MeetingRoom(meeting_id=meeting_id)
        return self._rooms[meeting_id]

    def remove_empty_rooms(self) -> None:
        empty = [mid for mid, room in self._rooms.items() if room.is_empty()]
        for mid in empty:
            del self._rooms[mid]

    def get_all_rooms(self) -> Dict[int, MeetingRoom]:
        return self._rooms

    def active_room_count(self) -> int:
        return len(self._rooms)