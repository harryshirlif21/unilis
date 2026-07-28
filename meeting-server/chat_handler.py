"""
UNILIS Meeting — Chat Message Handler
Relays chat messages between participants in a meeting room.

Messages carry the breakout room they were sent in. A breakout exists so a small
group can talk without the rest of the meeting; a chat that ignored the split
would put every side conversation in front of everyone, which defeats it.
None is the main room.
"""
from __future__ import annotations

import time
from typing import Dict, List, Optional


class ChatMessage:
    def __init__(
        self,
        meeting_id: int,
        sender_id: int,
        sender_name: str,
        text: str,
        message_type: str = "text",
        reply_to: Optional[int] = None,
        breakout_id: Optional[str] = None,
    ):
        self.meeting_id = meeting_id
        self.sender_id = sender_id
        self.sender_name = sender_name
        self.text = text
        self.message_type = message_type
        self.reply_to = reply_to
        self.breakout_id = breakout_id
        self.timestamp = time.time()
        self.message_id = f"{meeting_id}:{sender_id}:{int(self.timestamp * 1000)}"

    def to_dict(self) -> dict:
        return {
            "type": "chat_message",
            "message_id": self.message_id,
            "meeting_id": self.meeting_id,
            "sender_id": self.sender_id,
            "sender_name": self.sender_name,
            "text": self.text,
            "message_type": self.message_type,
            "reply_to": self.reply_to,
            "breakout_id": self.breakout_id,
            "timestamp": self.timestamp,
        }


class ChatHandler:
    def __init__(self, max_history: int = 500):
        self._history: Dict[int, List[dict]] = {}  # meeting_id -> list of messages
        self._max_history = max_history

    def add_message(self, message: ChatMessage) -> dict:
        meeting_id = message.meeting_id
        if meeting_id not in self._history:
            self._history[meeting_id] = []
        msg_dict = message.to_dict()
        self._history[meeting_id].append(msg_dict)
        # Trim history
        if len(self._history[meeting_id]) > self._max_history:
            self._history[meeting_id] = self._history[meeting_id][-self._max_history:]
        return msg_dict

    def get_history(
        self, meeting_id: int, limit: int = 50, breakout_id: Optional[str] = None
    ) -> List[dict]:
        """Recent messages from one room.

        Filtered by breakout so joining a breakout does not replay the main
        room's conversation into it, or the reverse. Messages stored before this
        field existed have no breakout_id and read as main-room messages, which
        is what they were.
        """
        messages = [
            m for m in self._history.get(meeting_id, [])
            if m.get("breakout_id") == breakout_id
        ]
        return messages[-limit:]

    def delete_message(self, meeting_id: int, message_id: str, user_id: int) -> bool:
        messages = self._history.get(meeting_id, [])
        for i, msg in enumerate(messages):
            if msg.get("message_id") == message_id and msg.get("sender_id") == user_id:
                messages[i]["text"] = "[deleted]"
                messages[i]["deleted"] = True
                return True
        return False

    def clear_meeting(self, meeting_id: int) -> None:
        self._history.pop(meeting_id, None)
