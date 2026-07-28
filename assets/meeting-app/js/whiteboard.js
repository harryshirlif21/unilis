/**
 * UNILIS Meeting - Collaborative Whiteboard
 *
 * Lives on the stage, not in the sidebar. The sidebar is 340px wide, which is
 * enough for a list of names and useless for drawing on - so the board takes
 * over the video area the way a shared screen does, and the tiles step aside.
 *
 * WHAT IS ON THE WIRE
 *
 * Whole items, not pointer movements. A pen stroke is sent once on release as a
 * list of points, rather than streamed point by point, so a fast scribble is one
 * message instead of two hundred. The cost is that other people see a stroke
 * appear complete rather than watch it being drawn; the alternative floods the
 * socket for every hand movement in the room.
 *
 * The server assigns each item's id and author and echoes it back to everyone
 * including the sender. That echo is what the sender's undo targets - an id it
 * invented itself could collide with somebody else's.
 *
 * WHY IT REDRAWS FROM A LIST
 *
 * The canvas is a view over an ordered array of items, repainted whenever that
 * array changes, rather than a surface that accumulates ink. That is what makes
 * erase, undo and a late joiner's replay possible at all: none of them can be
 * expressed as more drawing.
 */
UNILIS_MEETING.Whiteboard = {
  // The shared document: everything anyone has drawn, oldest first.
  items: [],

  // The stroke in progress locally, not yet sent.
  draft: null,

  tool: 'pen',
  color: '#1a73e8',
  size: 3,

  canvas: null,
  ctx: null,
  wrapper: null,
  open: false,
  onActivity: null,
  _resizeBound: null,

  // The board is drawn in this coordinate space and scaled to fit whatever the
  // canvas actually is, so two people on differently sized screens see the same
  // picture rather than the same pixel offsets.
  BOARD_WIDTH: 1600,
  BOARD_HEIGHT: 900,

  TOOLS: [
    { id: 'pen', label: 'Pen', glyph: '✏️' },
    { id: 'line', label: 'Line', glyph: '╱' },
    { id: 'rect', label: 'Rectangle', glyph: '▭' },
    { id: 'ellipse', label: 'Ellipse', glyph: '◯' },
    { id: 'arrow', label: 'Arrow', glyph: '↗' },
    { id: 'text', label: 'Text', glyph: 'T' },
    { id: 'eraser', label: 'Erase', glyph: '🧽' },
  ],

  COLORS: ['#1a73e8', '#202124', '#d93025', '#188038', '#f9ab00', '#a142f4'],

  // ============================================================
  // Markup
  // ============================================================

  /**
   * The stage panel. Built once and reused, because rebuilding it would throw
   * away the canvas and with it every item drawn on it.
   */
  _panel() {
    let panel = document.getElementById('whiteboardPanel');
    if (panel) return panel;

    panel = document.createElement('div');
    panel.className = 'wb-panel';
    panel.id = 'whiteboardPanel';
    panel.hidden = true;
    panel.innerHTML = `
      <div class="wb-bar">
        <div class="wb-tools" id="wbTools"></div>
        <div class="wb-colors" id="wbColors"></div>
        <label class="wb-size" title="Stroke width">
          <input type="range" min="1" max="24" value="${this.size}" id="wbSize">
        </label>
        <div class="wb-bar-right">
          <span class="wb-readonly" id="wbReadonly" hidden>View only</span>
          <button class="wb-btn" id="wbUndo" title="Undo your last item">↶ Undo</button>
          <button class="wb-btn" id="wbClear" title="Clear the whole board">Clear</button>
          <button class="wb-btn wb-btn-close" id="wbClose" title="Close the board">✕</button>
        </div>
      </div>
      <div class="wb-surface" id="wbSurface">
        <canvas id="whiteboardCanvas"></canvas>
        <div class="wb-locked-note" id="wbLockedNote" hidden>
          <p>You can see this board but not draw on it.</p>
          <button class="wb-btn wb-btn-primary" id="wbRequest">Ask the host for the pen</button>
        </div>
      </div>
    `;

    const stage = document.getElementById('meetingStage') || document.body;
    stage.appendChild(panel);

    this.wrapper = panel;
    this.canvas = panel.querySelector('#whiteboardCanvas');
    this.ctx = this.canvas.getContext('2d');

    this._buildToolbar();
    this._bindCanvas();

    panel.querySelector('#wbUndo').addEventListener('click', () => this.undo());
    panel.querySelector('#wbClear').addEventListener('click', () => this.clear());
    panel.querySelector('#wbClose').addEventListener('click', () => this.close());
    panel.querySelector('#wbSize').addEventListener('input', (e) => this.setSize(e.target.value));
    panel.querySelector('#wbRequest').addEventListener('click', () => this.requestPen());

    return panel;
  },

  _buildToolbar() {
    const tools = this.wrapper.querySelector('#wbTools');
    tools.innerHTML = this.TOOLS.map(t => `
      <button class="wb-tool${t.id === this.tool ? ' active' : ''}" data-tool="${t.id}" title="${t.label}">
        <span>${t.glyph}</span>
      </button>`).join('');
    tools.querySelectorAll('.wb-tool').forEach(btn => {
      btn.addEventListener('click', () => this.setTool(btn.dataset.tool));
    });

    const colors = this.wrapper.querySelector('#wbColors');
    colors.innerHTML = this.COLORS.map(c => `
      <button class="wb-color${c === this.color ? ' active' : ''}" data-color="${c}"
              style="background:${c}" title="${c}"></button>`).join('');
    colors.querySelectorAll('.wb-color').forEach(btn => {
      btn.addEventListener('click', () => this.setColor(btn.dataset.color));
    });
  },

  // ============================================================
  // Open / close
  // ============================================================

  toggle() {
    if (this.open) {
      this.close();
      return false;
    }
    this.show();
    return true;
  },

  show() {
    const panel = this._panel();
    panel.hidden = false;
    this.open = true;

    // The board and a shared screen both want the whole stage, so opening one
    // covers the other rather than stacking them.
    const container = document.getElementById('videoContainer');
    if (container) container.classList.add('is-whiteboard');

    if (!this._resizeBound) {
      this._resizeBound = () => this._fitCanvas();
      window.addEventListener('resize', this._resizeBound);
    }
    this._fitCanvas();
    this.syncPermission();

    // Ask for the current board. A joiner is sent it unprompted, but a
    // reconnect or a move between breakout rooms is not.
    if (UNILIS_MEETING.signaling) {
      UNILIS_MEETING.signaling.send({ type: 'whiteboard_state_request' });
    }
  },

  close() {
    if (this.wrapper) this.wrapper.hidden = true;
    this.open = false;
    const container = document.getElementById('videoContainer');
    if (container) container.classList.remove('is-whiteboard');
  },

  requestPen() {
    if (!UNILIS_MEETING.signaling) return;
    UNILIS_MEETING.signaling.send({ type: 'request_permission', capability: 'whiteboard' });
    UNILIS_MEETING.Notifications.show('Asked the host for the whiteboard', 'info');
  },

  /**
   * Show or hide the drawing controls for whatever this client is allowed to do.
   * Called on every roster and settings change, so a grant takes effect without
   * the board having to be reopened.
   */
  syncPermission() {
    if (!this.wrapper) return;
    const allowed = UNILIS_MEETING.Room.canUseWhiteboard();

    this.wrapper.classList.toggle('wb-view-only', !allowed);
    this.wrapper.querySelector('#wbReadonly').hidden = allowed;
    this.wrapper.querySelector('#wbLockedNote').hidden = allowed;
    this.canvas.style.cursor = allowed ? 'crosshair' : 'default';

    this.wrapper.querySelectorAll('#wbUndo, #wbClear, .wb-tool, .wb-color, #wbSize')
      .forEach(el => { el.disabled = !allowed; });
  },

  // ============================================================
  // Canvas sizing and painting
  // ============================================================

  /**
   * Match the canvas bitmap to its box and the device pixel ratio.
   *
   * Without the ratio the board is soft on any screen that is not 1x, because
   * the browser stretches a 1x bitmap over 2x pixels.
   */
  _fitCanvas() {
    if (!this.canvas || !this.wrapper) return;
    const surface = this.wrapper.querySelector('#wbSurface');
    const ratio = window.devicePixelRatio || 1;
    const width = surface.clientWidth;
    const height = surface.clientHeight;
    if (!width || !height) return;

    this.canvas.width = Math.round(width * ratio);
    this.canvas.height = Math.round(height * ratio);
    this.canvas.style.width = width + 'px';
    this.canvas.style.height = height + 'px';
    this.redraw();
  },

  /**
   * Board coordinates to canvas pixels, letterboxed so the board keeps its
   * shape rather than stretching to the window's.
   */
  _transform() {
    const w = this.canvas.width;
    const h = this.canvas.height;
    const scale = Math.min(w / this.BOARD_WIDTH, h / this.BOARD_HEIGHT) || 1;
    return {
      scale,
      offsetX: (w - this.BOARD_WIDTH * scale) / 2,
      offsetY: (h - this.BOARD_HEIGHT * scale) / 2,
    };
  },

  /** A pointer event in board coordinates. */
  _toBoard(event) {
    const rect = this.canvas.getBoundingClientRect();
    const ratio = window.devicePixelRatio || 1;
    const { scale, offsetX, offsetY } = this._transform();
    const x = ((event.clientX - rect.left) * ratio - offsetX) / scale;
    const y = ((event.clientY - rect.top) * ratio - offsetY) / scale;
    return {
      x: Math.max(0, Math.min(this.BOARD_WIDTH, Math.round(x))),
      y: Math.max(0, Math.min(this.BOARD_HEIGHT, Math.round(y))),
    };
  },

  redraw() {
    if (!this.ctx) return;
    const ctx = this.ctx;
    const { scale, offsetX, offsetY } = this._transform();

    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);

    // Always white, whatever the theme: a board is a board, and ink colours
    // chosen against white become invisible on a dark surface.
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);

    ctx.setTransform(scale, 0, 0, scale, offsetX, offsetY);
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';

    this.items.forEach(item => this._paint(ctx, item));
    if (this.draft) this._paint(ctx, this.draft);
  },

  _paint(ctx, item) {
    if (!item || !item.type) return;
    ctx.strokeStyle = item.color || '#202124';
    ctx.fillStyle = item.color || '#202124';
    ctx.lineWidth = item.size || 3;

    switch (item.type) {
      case 'path': {
        const points = item.points || [];
        if (points.length === 1) {
          // A tap with the pen is a dot, not nothing.
          ctx.beginPath();
          ctx.arc(points[0][0], points[0][1], (item.size || 3) / 2, 0, Math.PI * 2);
          ctx.fill();
          return;
        }
        if (points.length < 2) return;
        ctx.beginPath();
        ctx.moveTo(points[0][0], points[0][1]);
        for (let i = 1; i < points.length; i++) ctx.lineTo(points[i][0], points[i][1]);
        ctx.stroke();
        return;
      }
      case 'line':
        ctx.beginPath();
        ctx.moveTo(item.x1, item.y1);
        ctx.lineTo(item.x2, item.y2);
        ctx.stroke();
        return;
      case 'arrow': {
        ctx.beginPath();
        ctx.moveTo(item.x1, item.y1);
        ctx.lineTo(item.x2, item.y2);
        ctx.stroke();
        const angle = Math.atan2(item.y2 - item.y1, item.x2 - item.x1);
        const head = Math.max(10, (item.size || 3) * 4);
        ctx.beginPath();
        ctx.moveTo(item.x2, item.y2);
        ctx.lineTo(item.x2 - head * Math.cos(angle - Math.PI / 7), item.y2 - head * Math.sin(angle - Math.PI / 7));
        ctx.lineTo(item.x2 - head * Math.cos(angle + Math.PI / 7), item.y2 - head * Math.sin(angle + Math.PI / 7));
        ctx.closePath();
        ctx.fill();
        return;
      }
      case 'rect':
        ctx.beginPath();
        ctx.rect(
          Math.min(item.x1, item.x2),
          Math.min(item.y1, item.y2),
          Math.abs(item.x2 - item.x1),
          Math.abs(item.y2 - item.y1)
        );
        ctx.stroke();
        return;
      case 'ellipse':
        ctx.beginPath();
        ctx.ellipse(
          (item.x1 + item.x2) / 2,
          (item.y1 + item.y2) / 2,
          Math.abs(item.x2 - item.x1) / 2,
          Math.abs(item.y2 - item.y1) / 2,
          0, 0, Math.PI * 2
        );
        ctx.stroke();
        return;
      case 'text':
        ctx.font = `600 ${Math.max(14, (item.size || 3) * 7)}px 'Google Sans', 'Segoe UI', sans-serif`;
        ctx.textBaseline = 'top';
        ctx.fillText(item.text || '', item.x1, item.y1);
        return;
      default:
        return;
    }
  },

  /**
   * The topmost item under a point, for the eraser.
   *
   * Reverse order, so erasing removes what is visually on top rather than
   * whatever happened to be drawn first.
   */
  _hitTest(point) {
    const slack = 10;
    for (let i = this.items.length - 1; i >= 0; i--) {
      const item = this.items[i];
      const tolerance = Math.max(slack, (item.size || 3) + 6);

      if (item.type === 'path') {
        if ((item.points || []).some(p => Math.hypot(p[0] - point.x, p[1] - point.y) <= tolerance)) {
          return item;
        }
        continue;
      }
      if (item.type === 'text') {
        const height = Math.max(14, (item.size || 3) * 7);
        const width = (item.text || '').length * height * 0.6;
        if (point.x >= item.x1 - slack && point.x <= item.x1 + width + slack
            && point.y >= item.y1 - slack && point.y <= item.y1 + height + slack) {
          return item;
        }
        continue;
      }
      if (item.x1 === undefined) continue;

      const left = Math.min(item.x1, item.x2) - tolerance;
      const right = Math.max(item.x1, item.x2) + tolerance;
      const top = Math.min(item.y1, item.y2) - tolerance;
      const bottom = Math.max(item.y1, item.y2) + tolerance;
      if (point.x >= left && point.x <= right && point.y >= top && point.y <= bottom) {
        return item;
      }
    }
    return null;
  },

  // ============================================================
  // Pointer handling
  // ============================================================

  _bindCanvas() {
    const canvas = this.canvas;
    let drawing = false;

    const down = (event) => {
      if (!UNILIS_MEETING.Room.canUseWhiteboard()) return;
      event.preventDefault();
      const point = this._toBoard(event);

      if (this.tool === 'eraser') {
        const hit = this._hitTest(point);
        if (hit) this._send({ kind: 'erase', item_id: hit.id });
        return;
      }

      if (this.tool === 'text') {
        const text = prompt('Text to add to the board:');
        if (text && text.trim()) {
          this._send({
            kind: 'draw',
            item: {
              type: 'text',
              text: text.trim().slice(0, 200),
              x1: point.x,
              y1: point.y,
              color: this.color,
              size: this.size,
            },
          });
        }
        return;
      }

      drawing = true;
      if (canvas.setPointerCapture) canvas.setPointerCapture(event.pointerId);

      this.draft = this.tool === 'pen'
        ? { type: 'path', points: [[point.x, point.y]], color: this.color, size: this.size }
        : { type: this.tool, x1: point.x, y1: point.y, x2: point.x, y2: point.y, color: this.color, size: this.size };

      this.redraw();
    };

    const move = (event) => {
      if (!drawing || !this.draft) return;
      const point = this._toBoard(event);

      if (this.draft.type === 'path') {
        const last = this.draft.points[this.draft.points.length - 1];
        // Drop points closer than two board units. A slow hand produces dozens
        // of near-identical points, and every one is sent and redrawn.
        if (Math.hypot(point.x - last[0], point.y - last[1]) < 2) return;
        this.draft.points.push([point.x, point.y]);
      } else {
        this.draft.x2 = point.x;
        this.draft.y2 = point.y;
      }
      this.redraw();
    };

    const up = (event) => {
      if (!drawing) return;
      drawing = false;
      if (canvas.releasePointerCapture && event.pointerId !== undefined) {
        try { canvas.releasePointerCapture(event.pointerId); } catch (err) { /* already released */ }
      }

      const draft = this.draft;
      this.draft = null;
      if (!draft) return;

      // A shape with no extent is a misclick rather than a drawing. A path of
      // one point is a deliberate dot, so it survives.
      if (draft.type !== 'path'
          && Math.abs(draft.x2 - draft.x1) < 2
          && Math.abs(draft.y2 - draft.y1) < 2) {
        this.redraw();
        return;
      }

      this._send({ kind: 'draw', item: draft });
      this.redraw();
    };

    canvas.addEventListener('pointerdown', down);
    canvas.addEventListener('pointermove', move);
    canvas.addEventListener('pointerup', up);
    canvas.addEventListener('pointercancel', up);
    canvas.addEventListener('pointerleave', (e) => { if (drawing) up(e); });
    // Stop a drag on the board from scrolling the page instead of drawing.
    canvas.addEventListener('touchstart', (e) => e.preventDefault(), { passive: false });
    canvas.addEventListener('touchmove', (e) => e.preventDefault(), { passive: false });
  },

  // ============================================================
  // Wire protocol
  // ============================================================

  _send(action) {
    if (!UNILIS_MEETING.signaling) return;
    UNILIS_MEETING.signaling.send({ type: 'whiteboard_action', action });
  },

  setTool(tool) {
    this.tool = tool;
    if (!this.wrapper) return;
    this.wrapper.querySelectorAll('.wb-tool').forEach(btn => {
      btn.classList.toggle('active', btn.dataset.tool === tool);
    });
  },

  setColor(color) {
    this.color = color;
    if (!this.wrapper) return;
    this.wrapper.querySelectorAll('.wb-color').forEach(btn => {
      btn.classList.toggle('active', btn.dataset.color === color);
    });
  },

  setSize(size) {
    this.size = parseInt(size, 10) || 3;
  },

  undo() {
    // The server picks which item was this client's most recent, because it is
    // the only party that knows the authoritative order.
    this._send({ kind: 'undo' });
  },

  clear() {
    if (!this.items.length) return;
    if (!confirm('Clear the whole board for everyone in this room?')) return;
    this._send({ kind: 'clear' });
  },

  /** A whiteboard_action broadcast, from anyone including this client. */
  applyAction(action) {
    if (!action || !action.kind) return;

    if (action.kind === 'draw' && action.item) {
      this.items.push(action.item);
    } else if (action.kind === 'erase') {
      this.items = this.items.filter(i => i.id !== action.item_id);
    } else if (action.kind === 'clear') {
      this.items = [];
    }

    if (this.open) this.redraw();
    if (this.onActivity) this.onActivity(this.items.length, action);
  },

  /** A whole board: on join, on reconnect, or on moving between rooms. */
  loadState(state) {
    this.items = (state && Array.isArray(state.items)) ? state.items.slice() : [];
    this.draft = null;
    if (this.open) this.redraw();
    if (this.onActivity) this.onActivity(this.items.length, null);
  },

  itemCount() {
    return this.items.length;
  },

  /**
   * What the sidebar tab shows. The board itself is on the stage, so the tab is
   * a way in rather than the board's home.
   */
  render() {
    const room = UNILIS_MEETING.Room;
    const allowed = room.canUseWhiteboard();
    const count = this.items.length;
    const scope = room.breakoutId === null ? 'this meeting' : 'this breakout room';

    return `
      <div class="sb-block">
        <p class="sb-note">
          The whiteboard opens over the video area, so there is room to draw on it.
          Everyone in ${scope} sees the same board.
        </p>
        <button class="sb-btn sb-btn-primary" onclick="UNILIS_MEETING.Whiteboard.show()">
          Open the whiteboard
        </button>
        <p class="sb-meta">
          ${count} item${count === 1 ? '' : 's'} on the board ·
          ${allowed ? 'you can draw' : 'view only'}
        </p>
        ${allowed ? '' : `
          <button class="sb-btn" onclick="UNILIS_MEETING.Whiteboard.requestPen()">
            Ask the host for the pen
          </button>`}
      </div>`;
  },
};
