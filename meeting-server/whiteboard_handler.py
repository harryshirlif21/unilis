"""
UNILIS Meeting — Whiteboard Handler
Manages collaborative whiteboard state for meeting rooms.
"""
from __future__ import annotations

import json
import time
from typing import Any, Dict, List, Optional


class WhiteboardHandler:
    def __init__(self):
        self._states: Dict[int, dict] = {}  # meeting_id -> whiteboard state
        self._actions: Dict[int, List[dict]] = {}  # meeting_id -> action history

    def get_state(self, meeting_id: int) -> dict:
        if meeting_id not in self._states:
            self._states[meeting_id] = {
                "strokes": [],
                "shapes": [],
                "texts": [],
                "sticky_notes": [],
                "background_color": "#ffffff",
                "width": 1920,
                "height": 1080,
            }
        return self._states[meeting_id]

    def add_action(
        self, meeting_id: int, user_id: int, action: dict
    ) -> dict:
        action["user_id"] = user_id
        action["timestamp"] = time.time()
        action["action_id"] = f"wb:{meeting_id}:{int(time.time() * 1000)}"

        self._actions.setdefault(meeting_id, []).append(action)
        if len(self._actions[meeting_id]) > 1000:
            self._actions[meeting_id] = self._actions[meeting_id][-1000:]

        state = self.get_state(meeting_id)
        action_type = action.get("type")

        if action_type == "stroke":
            state["strokes"].append(action.get("data", {}))
        elif action_type == "shape":
            state["shapes"].append(action.get("data", {}))
        elif action_type == "text":
            state["texts"].append(action.get("data", {}))
        elif action_type == "sticky_note":
            state["sticky_notes"].append(action.get("data", {}))
        elif action_type == "clear":
            state["strokes"] = []
            state["shapes"] = []
            state["texts"] = []
            state["sticky_notes"] = []
        elif action_type == "undo":
            data = action.get("data", {})
            undo_type = data.get("type")
            undo_id = data.get("id")
            if undo_type == "stroke":
                state["strokes"] = [s for s in state["strokes"] if s.get("id") != undo_id]
            elif undo_type == "shape":
                state["shapes"] = [s for s in state["shapes"] if s.get("id") != undo_id]
            elif undo_type == "text":
                state["texts"] = [s for s in state["texts"] if s.get("id") != undo_id]

        return action

    def clear_meeting(self, meeting_id: int) -> None:
        self._states.pop(meeting_id, None)
        self._actions.pop(meeting_id, None)