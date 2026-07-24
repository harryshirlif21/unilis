"""
UNILIS Meeting — Attendance Handler
Tracks participant join/leave times and engagement during meetings.
"""
from __future__ import annotations

import time
from typing import Dict, List, Optional


class ParticipantSession:
    def __init__(self, user_id: int, display_name: str, role: str):
        self.user_id = user_id
        self.display_name = display_name
        self.role = role
        self.join_time = time.time()
        self.leave_time: Optional[float] = None
        self.camera_on_time: float = 0.0
        self.mic_on_time: float = 0.0
        self.screen_share_time: float = 0.0
        self.speaking_time: float = 0.0
        self._last_camera_on: Optional[float] = None
        self._last_mic_on: Optional[float] = None
        self._last_screen_share: Optional[float] = None
        self._last_speaking: Optional[float] = None

    def set_camera(self, enabled: bool) -> None:
        now = time.time()
        if enabled and self._last_camera_on is None:
            self._last_camera_on = now
        elif not enabled and self._last_camera_on is not None:
            self.camera_on_time += now - self._last_camera_on
            self._last_camera_on = None

    def set_mic(self, enabled: bool) -> None:
        now = time.time()
        if enabled and self._last_mic_on is None:
            self._last_mic_on = now
        elif not enabled and self._last_mic_on is not None:
            self.mic_on_time += now - self._last_mic_on
            self._last_mic_on = None

    def set_screen_share(self, enabled: bool) -> None:
        now = time.time()
        if enabled and self._last_screen_share is None:
            self._last_screen_share = now
        elif not enabled and self._last_screen_share is not None:
            self.screen_share_time += now - self._last_screen_share
            self._last_screen_share = None

    def speaking(self) -> None:
        now = time.time()
        if self._last_speaking is not None:
            self.speaking_time += now - self._last_speaking
        self._last_speaking = now

    def stop_speaking(self) -> None:
        if self._last_speaking is not None:
            self.speaking_time += time.time() - self._last_speaking
            self._last_speaking = None

    def leave(self) -> None:
        self.leave_time = time.time()
        self.stop_speaking()
        self.set_camera(False)
        self.set_mic(False)
        self.set_screen_share(False)

    def get_duration(self) -> float:
        end = self.leave_time or time.time()
        return end - self.join_time

    def get_engagement_score(self) -> float:
        duration = self.get_duration()
        if duration <= 0:
            return 0.0
        # Weighted score: camera 25%, mic 25%, screen share 20%, speaking 30%
        score = (
            (self.camera_on_time / duration) * 25
            + (self.mic_on_time / duration) * 25
            + (self.screen_share_time / duration) * 20
            + (self.speaking_time / duration) * 30
        )
        return min(score, 100.0)

    def to_dict(self) -> dict:
        return {
            "user_id": self.user_id,
            "display_name": self.display_name,
            "role": self.role,
            "join_time": self.join_time,
            "leave_time": self.leave_time,
            "duration": self.get_duration(),
            "camera_on_time": self.camera_on_time,
            "mic_on_time": self.mic_on_time,
            "screen_share_time": self.screen_share_time,
            "speaking_time": self.speaking_time,
            "engagement_score": round(self.get_engagement_score(), 1),
        }


class AttendanceHandler:
    def __init__(self):
        self._sessions: Dict[int, Dict[int, ParticipantSession]] = {}  # meeting_id -> user_id -> session

    def join(self, meeting_id: int, user_id: int, display_name: str, role: str) -> ParticipantSession:
        self._sessions.setdefault(meeting_id, {})
        session = ParticipantSession(user_id, display_name, role)
        self._sessions[meeting_id][user_id] = session
        return session

    def leave(self, meeting_id: int, user_id: int) -> None:
        session = self._sessions.get(meeting_id, {}).get(user_id)
        if session:
            session.leave()

    def get_session(self, meeting_id: int, user_id: int) -> Optional[ParticipantSession]:
        return self._sessions.get(meeting_id, {}).get(user_id)

    def get_meeting_report(self, meeting_id: int) -> List[dict]:
        sessions = self._sessions.get(meeting_id, {})
        return [s.to_dict() for s in sorted(sessions.values(), key=lambda x: x.join_time)]

    def get_meeting_summary(self, meeting_id: int) -> dict:
        sessions = self._sessions.get(meeting_id, {})
        if not sessions:
            return {}
        report = self.get_meeting_report(meeting_id)
        total_duration = max(s.get("duration", 0) for s in report) if report else 0
        avg_engagement = (
            sum(s.get("engagement_score", 0) for s in report) / len(report)
            if report
            else 0
        )
        return {
            "meeting_id": meeting_id,
            "total_participants": len(sessions),
            "total_duration": total_duration,
            "average_engagement": round(avg_engagement, 1),
            "participants": report,
        }

    def clear_meeting(self, meeting_id: int) -> None:
        self._sessions.pop(meeting_id, None)