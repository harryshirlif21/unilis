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