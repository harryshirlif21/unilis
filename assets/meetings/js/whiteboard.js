/**
 * UNILIS Meeting - Whiteboard Module (Stub with canvas support)
 */
UNILIS_MEETING.Whiteboard = {
  canvas: null,
  ctx: null,
  isDrawing: false,
  
  render() {
    return `<div class="whiteboard-toolbar">
      <button class="whiteboard-tool active" data-tool="pen" onclick="UNILIS_MEETING.Whiteboard.setTool('pen')">✏️</button>
      <button class="whiteboard-tool" data-tool="eraser" onclick="UNILIS_MEETING.Whiteboard.setTool('eraser')">🧹</button>
      <button class="whiteboard-tool" data-tool="text" onclick="UNILIS_MEETING.Whiteboard.setTool('text')">T</button>
      <button class="whiteboard-tool" data-tool="shape" onclick="UNILIS_MEETING.Whiteboard.setTool('shape')">□</button>
      <button class="whiteboard-tool" onclick="UNILIS_MEETING.Whiteboard.clear()">🗑️</button>
      <input type="color" class="whiteboard-color-picker" value="#1a73e8" onchange="UNILIS_MEETING.Whiteboard.setColor(this.value)">
      <input type="range" class="whiteboard-stroke-slider" min="1" max="20" value="3" onchange="UNILIS_MEETING.Whiteboard.setSize(this.value)">
    </div>
    <div class="whiteboard-container">
      <canvas class="whiteboard-canvas" id="whiteboardCanvas"></canvas>
    </div>`;
  },
  
  setTool(tool) {},
  setColor(color) {},
  setSize(size) {},
  clear() {},
};