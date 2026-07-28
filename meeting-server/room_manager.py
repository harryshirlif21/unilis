"""
UNILIS Meeting — Room State Manager
Manages meeting rooms, participants, their permissions, and breakout rooms.

PERMISSIONS

Screen sharing and the whiteboard are capabilities rather than buttons. Each has
a room policy - "host_only" or "everyone" - and each participant can hold an
individual grant that overrides a host_only policy for them alone. That is what
lets a lecturer hand the screen to one student without opening it to the class.

The check lives here rather than in the client because the client cannot be
trusted with it: a participant who edits their own JavaScript could otherwise
announce screen_sharing or post whiteboard strokes regardless of the policy. The
client hides the controls; this decides.

BREAKOUT ROOMS

A breakout is not a separate MeetingRoom. Participants stay in one room and
carry a breakout_id, with None meaning the main room. Everything that is scoped
- who you see and hear, who your chat reaches - filters on that id.

Splitting into real rooms would have meant a second signaling connection, a
second WebRTC mesh and a second chat history per breakout, and moving between
them would be a rejoin rather than a filter change.
"""
from __future__ import annotations

import time
import uuid
from dataclasses import dataclass, field
from typing import Dict, List, Optional

# Policy values for a room capability.
POLICY_HOST_ONLY = "host_only"
POLICY_EVERYONE = "everyone"
POLICIES = (POLICY_HOST_ONLY, POLICY_EVERYONE)

# Capability names, as they appear on the wire.
CAP_SCREEN = "screen"
CAP_WHITEBOARD = "whiteboard"
CAPABILITIES = (CAP_SCREEN, CAP_WHITEBOARD)

# The roles that host a meeting. A guest joining from a public link is never one.
HOST_ROLES = ("lecturer", "host")


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

    # None is the main room; anything else is a breakout id.
    breakout_id: Optional[str] = None

    # Individual grants, which override a host_only policy for this person only.
    can_share_screen: bool = False
    can_whiteboard: bool = False

    # When the hand went up, so the host sees the queue in the order it formed
    # rather than in participant order. Cleared when the hand comes down.
    hand_raised_at: Optional[float] = None

    def is_host(self) -> bool:
        return self.role in HOST_ROLES


@dataclass
class Breakout:
    breakout_id: str
    name: str
    created_at: float = field(default_factory=time.time)

    def to_dict(self, occupants: int = 0) -> dict:
        return {
            "breakout_id": self.breakout_id,
            "name": self.name,
            "created_at": self.created_at,
            "occupants": occupants,
        }


@dataclass
class MeetingRoom:
    meeting_id: int
    participants: Dict[int, ParticipantInfo] = field(default_factory=dict)
    is_locked: bool = False
    created_at: float = field(default_factory=time.time)
    password: Optional[str] = None

    # Capability policies. Both default to host_only: a lecture where any
    # student can seize the shared screen or scribble over the board is the
    # wrong default, and opening it up is one click for the host.
    screen_share_policy: str = POLICY_HOST_ONLY
    whiteboard_policy: str = POLICY_HOST_ONLY

    chat_enabled: bool = True

    # Whether participants may move themselves between breakout rooms. With this
    # off, only the host can assign people.
    breakout_self_join: bool = True

    breakouts: Dict[str, Breakout] = field(default_factory=dict)

    # ---- Participants -------------------------------------------------------

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

    def hosts(self) -> List[ParticipantInfo]:
        return [p for p in self.participants.values() if p.is_host()]

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
                "hand_raised_at": p.hand_raised_at,
                "is_muted": p.is_muted,
                "connection_state": p.connection_state,
                "breakout_id": p.breakout_id,
                # Both the raw grant and the effective answer are published. The
                # client needs the grant to render the host's toggle, and the
                # effective value to decide whether to show the control at all.
                "can_share_screen": p.can_share_screen,
                "can_whiteboard": p.can_whiteboard,
                "may_share_screen": self.may_share_screen(p.user_id),
                "may_whiteboard": self.may_whiteboard(p.user_id),
            }
            for p in self.participants.values()
        ]

    def participant_count(self) -> int:
        return len(self.participants)

    def is_empty(self) -> bool:
        return len(self.participants) == 0

    # ---- Permissions --------------------------------------------------------

    def _may(self, user_id: int, policy: str, grant_attr: str) -> bool:
        p = self.participants.get(user_id)
        if p is None:
            return False
        if p.is_host():
            return True
        if policy == POLICY_EVERYONE:
            return True
        return bool(getattr(p, grant_attr, False))

    def may_share_screen(self, user_id: int) -> bool:
        return self._may(user_id, self.screen_share_policy, "can_share_screen")

    def may_whiteboard(self, user_id: int) -> bool:
        return self._may(user_id, self.whiteboard_policy, "can_whiteboard")

    def may(self, user_id: int, capability: str) -> bool:
        if capability == CAP_SCREEN:
            return self.may_share_screen(user_id)
        if capability == CAP_WHITEBOARD:
            return self.may_whiteboard(user_id)
        return False

    def set_grant(self, user_id: int, capability: str, granted: bool) -> bool:
        """Give or withdraw one participant's individual grant."""
        p = self.participants.get(user_id)
        if p is None or capability not in CAPABILITIES:
            return False
        if capability == CAP_SCREEN:
            p.can_share_screen = granted
            # Withdrawing the grant has to stop the share in progress, or
            # revoking permission would leave the screen on everyone's stage.
            if not granted and not p.is_host() and self.screen_share_policy != POLICY_EVERYONE:
                p.screen_sharing = False
        else:
            p.can_whiteboard = granted
        return True

    def set_policy(self, capability: str, policy: str) -> bool:
        if policy not in POLICIES:
            return False
        if capability == CAP_SCREEN:
            self.screen_share_policy = policy
        elif capability == CAP_WHITEBOARD:
            self.whiteboard_policy = policy
        else:
            return False
        return True

    # ---- Hands --------------------------------------------------------------

    def set_hand(self, user_id: int, raised: bool) -> None:
        p = self.participants.get(user_id)
        if p is None:
            return
        # Only stamp the time on the way up. Re-sending "raised" while already
        # raised must not move somebody to the back of their own queue.
        if raised and not p.hand_raised:
            p.hand_raised_at = time.time()
        elif not raised:
            p.hand_raised_at = None
        p.hand_raised = raised

    def raised_hands(self) -> List[dict]:
        """Everyone with a hand up, oldest first."""
        raised = [p for p in self.participants.values() if p.hand_raised]
        raised.sort(key=lambda p: p.hand_raised_at or 0.0)
        return [
            {"user_id": p.user_id, "display_name": p.display_name, "since": p.hand_raised_at}
            for p in raised
        ]

    def lower_all_hands(self) -> None:
        for p in self.participants.values():
            p.hand_raised = False
            p.hand_raised_at = None

    # ---- Breakout rooms -----------------------------------------------------

    def create_breakout(self, name: str) -> Breakout:
        breakout_id = uuid.uuid4().hex[:12]
        name = (name or "").strip() or f"Room {len(self.breakouts) + 1}"
        breakout = Breakout(breakout_id=breakout_id, name=name[:60])
        self.breakouts[breakout_id] = breakout
        return breakout

    def delete_breakout(self, breakout_id: str) -> bool:
        if breakout_id not in self.breakouts:
            return False
        del self.breakouts[breakout_id]
        # Anyone left inside comes back to the main room rather than being
        # stranded in a room that no longer exists.
        for p in self.participants.values():
            if p.breakout_id == breakout_id:
                p.breakout_id = None
        return True

    def rename_breakout(self, breakout_id: str, name: str) -> bool:
        breakout = self.breakouts.get(breakout_id)
        if breakout is None:
            return False
        name = (name or "").strip()
        if not name:
            return False
        breakout.name = name[:60]
        return True

    def assign_breakout(self, user_id: int, breakout_id: Optional[str]) -> bool:
        p = self.participants.get(user_id)
        if p is None:
            return False
        if breakout_id is not None and breakout_id not in self.breakouts:
            return False
        # Moving rooms drops any share in progress: the people who could see it
        # are no longer the people in the room.
        if p.breakout_id != breakout_id:
            p.screen_sharing = False
        p.breakout_id = breakout_id
        return True

    def close_all_breakouts(self) -> None:
        self.breakouts.clear()
        for p in self.participants.values():
            if p.breakout_id is not None:
                p.breakout_id = None
                p.screen_sharing = False

    def breakout_occupants(self, breakout_id: Optional[str]) -> List[int]:
        return [p.user_id for p in self.participants.values() if p.breakout_id == breakout_id]

    def breakout_list(self) -> List[dict]:
        counts: Dict[Optional[str], int] = {}
        for p in self.participants.values():
            counts[p.breakout_id] = counts.get(p.breakout_id, 0) + 1

        return [
            b.to_dict(occupants=counts.get(b.breakout_id, 0))
            for b in sorted(self.breakouts.values(), key=lambda b: b.created_at)
        ]

    def user_ids_in_breakout_of(self, user_id: int) -> List[int]:
        """Everyone sharing a breakout with this user, including the user."""
        p = self.participants.get(user_id)
        if p is None:
            return []
        return self.breakout_occupants(p.breakout_id)

    # ---- State for the client ----------------------------------------------

    def state_dict(self) -> dict:
        """Everything a client needs to render the settings and rooms panels."""
        return {
            "screen_share_policy": self.screen_share_policy,
            "whiteboard_policy": self.whiteboard_policy,
            "chat_enabled": self.chat_enabled,
            "breakout_self_join": self.breakout_self_join,
            "is_locked": self.is_locked,
            "breakouts": self.breakout_list(),
            "main_room_occupants": len(self.breakout_occupants(None)),
            "raised_hands": self.raised_hands(),
        }


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
