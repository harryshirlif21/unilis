/**
 * UNILIS Meeting - Theme Manager
 * Manages light/dark mode and glassmorphism effects.
 */
UNILIS_MEETING.ThemeManager = {
  currentTheme: 'light',

  init() {
    const saved = localStorage.getItem('unilis-meeting-theme') || 'light';
    this.setTheme(saved);
  },

  setTheme(theme) {
    this.currentTheme = theme;
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('unilis-meeting-theme', theme);
  },

  toggle() {
    const newTheme = this.currentTheme === 'light' ? 'dark' : 'light';
    this.setTheme(newTheme);
    return newTheme;
  },

  isDark() {
    return this.currentTheme === 'dark';
  },
};