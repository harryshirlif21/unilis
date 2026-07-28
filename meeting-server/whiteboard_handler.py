"""
UNILIS Meeting — Whiteboard Handler
Manages collaborative whiteboard state for meeting rooms.

One board per breakout room, not one per meeting. Breakouts exist so groups can
work separately, and a single shared board would have group A drawing over group
B's work. None is the main room's board.

The state is a flat, ordered list of items rather than a bitmap. That is what
makes a late joiner cheap - replay the list - and undo possible at all, since
the last item can be dropped and the rest redrawn.
"""
from __future__ import annotations

import time
import uuid
from typing import Dict, List, Optional, Tuple

# A board holds at most this many items. Beyond it the oldest go, because the
# whole list is replayed to every late joiner and an unbounded board would
# eventually make joining slower than the meeting.
MAX_ITEMS = 4000

BoardKey = Tuple[int, Optional[str]]


class WhiteboardHandler:
    def __init__(self):
        self._boards: Dict[BoardKey, List[dict]] = {}

    def _key(self, meeting_id: int, breakout_id: Optional[str] = None) -> BoardKey:
        return (meeting_id, breakout_id)

    def get_items(self, meeting_id: int, breakout_id: Optional[str] = None) -> List[dict]:
        return self._boards.setdefault(self._key(meeting_id, breakout_id), [])

    def get_state(self, meeting_id: int, breakout_id: Optional[str] = None) -> dict:
        return {
            "breakout_id": breakout_id,
            "items": self.get_items(meeting_id, breakout_id),
        }

    def add_action(
        self,
        meeting_id: int,
        user_id: int,
        action: dict,
        breakout_id: Optional[str] = None,
    ) -> Optional[dict]:
        """Apply one action and return what to broadcast, or None if it was invalid.

        Returning the applied action rather than the whole board keeps a stroke
        one small message: a board with a thousand items would otherwise resend
        all of them on every pen stroke.
        """
        kind = action.get("kind")
        items = self.get_items(meeting_id, breakout_id)

        if kind == "draw":
            item = action.get("item")
            if not isinstance(item, dict):
                return None
            # The id and author are assigned here, not accepted from the client:
            # an id chosen by the sender could collide with or overwrite somebody
            # else's item, and a claimed author could erase another person's work.
            item = dict(item)
            item["id"] = uuid.uuid4().hex[:12]
            item["user_id"] = user_id
            item["at"] = time.time()
            items.append(item)
            if len(items) > MAX_ITEMS:
                del items[: len(items) - MAX_ITEMS]

            return {"kind": "draw", "item": item, "breakout_id": breakout_id}

        if kind == "erase":
            item_id = action.get("item_id")
            before = len(items)
            # Anyone who may draw on the board may erase from it. A board where
            # only the author can remove their own line is unusable in practice -
            # the person tidying up is rarely the person who drew the mess.
            self._boards[self._key(meeting_id, breakout_id)] = [
                i for i in items if i.get("id") != item_id
            ]
            if len(self._boards[self._key(meeting_id, breakout_id)]) == before:
                return None

            return {"kind": "erase", "item_id": item_id, "breakout_id": breakout_id}

        if kind == "undo":
            # Undo removes the caller's own most recent item. Undoing whatever
            # happened last would let one person walk backwards through
            # everybody else's work.
            mine = [i for i in items if i.get("user_id") == user_id]
            if not mine:
                return None
            target = mine[-1]
            self._boards[self._key(meeting_id, breakout_id)] = [
                i for i in items if i.get("id") != target.get("id")
            ]

            return {"kind": "erase", "item_id": target.get("id"), "breakout_id": breakout_id}

        if kind == "clear":
            self._boards[self._key(meeting_id, breakout_id)] = []
            return {"kind": "clear", "breakout_id": breakout_id}

        return None

    def clear_meeting(self, meeting_id: int) -> None:
        for key in [k for k in self._boards if k[0] == meeting_id]:
            del self._boards[key]

    def item_count(self, meeting_id: int, breakout_id: Optional[str] = None) -> int:
        return len(self.get_items(meeting_id, breakout_id))
