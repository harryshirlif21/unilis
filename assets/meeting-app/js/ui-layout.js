/**
 * UNILIS Meeting - Dynamic Grid Layout Manager
 * Auto-arranges video tiles in gallery, speaker, and presentation views.
 */
UNILIS_MEETING.LayoutManager = {
  currentView: 'gallery',  // gallery, speaker, presentation
  tileCount: 0,
  container: null,
  onViewChanged: null,

  init(containerEl) {
    this.container = containerEl;
  },

  setView(view) {
    this.currentView = view;
    const grid = this.container;
    if (!grid) return;

    grid.className = 'video-grid';
    if (view === 'speaker') grid.classList.add('speaker-view');
    else if (view === 'gallery') grid.classList.add('gallery-view');
    else if (view === 'presentation') grid.classList.add('presentation-view');

    if (this.onViewChanged) this.onViewChanged(view);
  },

  toggleView() {
    const views = ['gallery', 'speaker', 'presentation'];
    const currentIdx = views.indexOf(this.currentView);
    const nextView = views[(currentIdx + 1) % views.length];
    this.setView(nextView);
    return nextView;
  },

  updateTileCount(count) {
    this.tileCount = count;
    if (this.currentView === 'gallery') {
      this._adjustGalleryColumns(count);
    }
    // A pinned tile has to be re-marked after the grid is rebuilt, because the
    // tile carrying the class may have been replaced.
    if (this.pinnedUserId !== null) this.setPinned(this.pinnedUserId);
  },

  pinnedUserId: null,

  /**
   * Give one participant the big tile, or pass null to go back to an even grid.
   *
   * Purely local: it changes this viewer's layout and sends nothing, so it needs
   * no permission and does not move anybody else's tiles.
   */
  setPinned(userId) {
    this.pinnedUserId = userId === undefined ? null : userId;
    const grid = this.container;
    if (!grid) return;

    grid.querySelectorAll('.video-tile').forEach(tile => {
      tile.classList.toggle('pinned', tile.id === `participant-${this.pinnedUserId}`);
    });
    grid.classList.toggle('has-pinned', this.pinnedUserId !== null);

    // The explicit columns set for the gallery would override the pinned
    // layout's own grid, so they are dropped while something is pinned.
    if (this.pinnedUserId !== null) {
      grid.style.gridTemplateColumns = '';
    } else {
      this._adjustGalleryColumns(this.tileCount);
    }
  },

  _adjustGalleryColumns(count) {
    const grid = this.container;
    if (!grid) return;

    // Auto-size based on participant count
    if (count <= 1) {
      grid.style.gridTemplateColumns = '1fr';
    } else if (count <= 4) {
      grid.style.gridTemplateColumns = 'repeat(2, 1fr)';
    } else if (count <= 9) {
      grid.style.gridTemplateColumns = 'repeat(3, 1fr)';
    } else {
      grid.style.gridTemplateColumns = 'repeat(auto-fit, minmax(280px, 1fr))';
    }
  },
};