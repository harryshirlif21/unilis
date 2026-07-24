"""
UNILIS Meeting — Poll Handler
Manages polls and quizzes within meeting rooms.
"""
from __future__ import annotations

import time
import uuid
from typing import Dict, List, Optional


class Poll:
    def __init__(
        self,
        meeting_id: int,
        creator_id: int,
        question: str,
        options: List[str],
        poll_type: str = "multiple_choice",
        is_anonymous: bool = False,
    ):
        self.poll_id = str(uuid.uuid4())[:8]
        self.meeting_id = meeting_id
        self.creator_id = creator_id
        self.question = question
        self.options = options
        self.poll_type = poll_type  # multiple_choice, quiz, word_cloud, rating
        self.is_anonymous = is_anonymous
        self.is_active = True
        self.created_at = time.time()
        self.votes: Dict[str, List[int]] = {}  # user_id -> selected option indices
        self.results_visible = True

    def vote(self, user_id: int, option_indices: List[int]) -> bool:
        if not self.is_active:
            return False
        self.votes[str(user_id)] = option_indices
        return True

    def close(self) -> None:
        self.is_active = False

    def get_results(self) -> dict:
        counts = [0] * len(self.options)
        total_votes = len(self.votes)
        for indices in self.votes.values():
            for idx in indices:
                if 0 <= idx < len(counts):
                    counts[idx] += 1
        return {
            "poll_id": self.poll_id,
            "question": self.question,
            "options": self.options,
            "counts": counts,
            "total_votes": total_votes,
            "is_active": self.is_active,
            "is_anonymous": self.is_anonymous,
            "poll_type": self.poll_type,
        }

    def to_dict(self) -> dict:
        return {
            "type": "poll_created",
            "poll_id": self.poll_id,
            "meeting_id": self.meeting_id,
            "creator_id": self.creator_id,
            "question": self.question,
            "options": self.options,
            "poll_type": self.poll_type,
            "is_anonymous": self.is_anonymous,
            "is_active": self.is_active,
            "created_at": self.created_at,
        }


class PollHandler:
    def __init__(self):
        self._polls: Dict[str, Poll] = {}  # poll_id -> Poll
        self._meeting_polls: Dict[int, List[str]] = {}  # meeting_id -> [poll_ids]

    def create_poll(
        self,
        meeting_id: int,
        creator_id: int,
        question: str,
        options: List[str],
        poll_type: str = "multiple_choice",
        is_anonymous: bool = False,
    ) -> Poll:
        poll = Poll(meeting_id, creator_id, question, options, poll_type, is_anonymous)
        self._polls[poll.poll_id] = poll
        self._meeting_polls.setdefault(meeting_id, []).append(poll.poll_id)
        return poll

    def get_poll(self, poll_id: str) -> Optional[Poll]:
        return self._polls.get(poll_id)

    def get_active_polls(self, meeting_id: int) -> List[Poll]:
        poll_ids = self._meeting_polls.get(meeting_id, [])
        return [self._polls[pid] for pid in poll_ids if pid in self._polls and self._polls[pid].is_active]

    def get_all_polls(self, meeting_id: int) -> List[dict]:
        poll_ids = self._meeting_polls.get(meeting_id, [])
        return [self._polls[pid].to_dict() for pid in poll_ids if pid in self._polls]

    def close_poll(self, poll_id: str) -> bool:
        poll = self._polls.get(poll_id)
        if poll:
            poll.close()
            return True
        return False

    def clear_meeting(self, meeting_id: int) -> None:
        poll_ids = self._meeting_polls.pop(meeting_id, [])
        for pid in poll_ids:
            self._polls.pop(pid, None)