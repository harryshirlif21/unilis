/**
 * UNILIS Live Engagement Module - Premium JavaScript Engine v2.0
 * 
 * Modern, modular JavaScript with:
 * - Theme switching (light/dark/high-contrast)
 * - Command palette (Cmd+K / Ctrl+K)
 * - Keyboard shortcuts
 * - Ripple effects
 * - Drag and drop
 * - Autosave
 * - Optimistic UI updates
 * - Loading skeletons
 * - Toast notifications
 * - Page transitions
 * 
 * @package UNILIS\LiveEngagement
 * @version 2.0.0
 */

const LiveEngagement = (function() {
    'use strict';

    // ============================================================
    // Configuration
    // ============================================================
    const config = {
        apiBase: 'modules/live-engagement/api/',
        pollingInterval: 3000,
        reconnectAttempts: 5,
        reconnectDelay: 2000,
        csrfToken: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        autosaveInterval: 30000, // 30 seconds
        version: '2.0.0',
    };

    // ============================================================
    // State
    // ============================================================
    let state = {
        sessionId: null,
        participantId: null,
        isPresenter: false,
        pollingTimers: [],
        currentPollId: null,
        currentQuizId: null,
        theme: localStorage.getItem('le-theme') || 'light',
        commandPaletteOpen: false,
        shortcutsEnabled: true,
        unsavedChanges: false,
        isOnline: navigator.onLine,
        activeModals: [],
    };

    // ============================================================
    // Initialization
    // ============================================================
    function init(options = {}) {
        Object.assign(config, options);
        
        if (options.sessionId) state.sessionId = options.sessionId;
        if (options.participantId) state.participantId = options.participantId;
        if (options.isPresenter) state.isPresenter = true;

        // Load saved theme
        loadTheme();
        
        // Setup all event listeners
        setupGlobalListeners();
        setupKeyboardShortcuts();
        setupRippleEffect();
        setupDragAndDrop();
        setupAutosave();
        setupOnlineStatus();
        setupPageTransitions();
        
        console.log(`Live Engagement Module v${config.version} initialized`);
    }

    // ============================================================
    // Theme System
    // ============================================================
    function loadTheme() {
        const saved = localStorage.getItem('le-theme');
        if (saved) {
            state.theme = saved;
            document.documentElement.setAttribute('data-theme', saved);
        } else if (window.matchMedia?.('(prefers-color-scheme: dark)').matches) {
            state.theme = 'dark';
            document.documentElement.setAttribute('data-theme', 'dark');
        }
    }

    function setTheme(theme) {
        state.theme = theme;
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('le-theme', theme);
        document.dispatchEvent(new CustomEvent('themeChange', { detail: { theme } }));
        showToast(`Theme changed to ${theme}`, 'info');
    }

    function getTheme() {
        return state.theme;
    }

    function toggleTheme() {
        const themes = ['light', 'dark', 'high-contrast'];
        const currentIndex = themes.indexOf(state.theme);
        const nextTheme = themes[(currentIndex + 1) % themes.length];
        setTheme(nextTheme);
    }

    // ============================================================
    // Command Palette (Cmd+K / Ctrl+K)
    // ============================================================
    function setupCommandPalette() {
        // Create command palette overlay if it doesn't exist
        if (document.getElementById('le-command-palette')) return;

        const overlay = document.createElement('div');
        overlay.id = 'le-command-palette';
        overlay.className = 'le-modal-overlay';
        overlay.style.display = 'none';
        overlay.style.alignItems = 'flex-start';
        overlay.style.paddingTop = '15vh';

        overlay.innerHTML = `
            <div class="le-modal le-modal-sm" style="max-width: 600px; animation: le-slide-in-down 0.2s ease;">
                <div class="le-modal-body" style="padding: 0;">
                    <div style="position: relative;">
                        <span class="material-symbols-rounded" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--le-gray-400);">search</span>
                        <input type="text" id="le-command-input" class="le-input" 
                               placeholder="Search actions, pages, or settings..."
                               style="border: none; border-radius: var(--le-radius-2xl) var(--le-radius-2xl) 0 0; padding: 16px 16px 16px 48px; font-size: 1rem; box-shadow: none;"
                               autocomplete="off" autofocus>
                    </div>
                    <div id="le-command-results" style="max-height: 300px; overflow-y: auto; padding: 8px 0;">
                        <div class="le-command-group">
                            <div style="padding: 4px 16px; font-size: 0.75rem; color: var(--le-gray-500); text-transform: uppercase; letter-spacing: 0.05em;">Pages</div>
                            <button class="le-command-item" data-action="navigate" data-value="dashboard">
                                <span class="material-symbols-rounded" style="font-size: 20px;">dashboard</span>
                                <span>Dashboard</span>
                                <span style="margin-left: auto; font-size: 0.75rem; color: var(--le-gray-400);">⌘D</span>
                            </button>
                            <button class="le-command-item" data-action="navigate" data-value="join">
                                <span class="material-symbols-rounded" style="font-size: 20px;">login</span>
                                <span>Join Session</span>
                                <span style="margin-left: auto; font-size: 0.75rem; color: var(--le-gray-400);">⌘J</span>
                            </button>
                            <button class="le-command-item" data-action="navigate" data-value="presentations">
                                <span class="material-symbols-rounded" style="font-size: 20px;">slideshow</span>
                                <span>Presentations</span>
                                <span style="margin-left: auto; font-size: 0.75rem; color: var(--le-gray-400);">⌘P</span>
                            </button>
                        </div>
                        <div class="le-command-group">
                            <div style="padding: 4px 16px; font-size: 0.75rem; color: var(--le-gray-500); text-transform: uppercase; letter-spacing: 0.05em;">Actions</div>
                            <button class="le-command-item" data-action="create-session">
                                <span class="material-symbols-rounded" style="font-size: 20px;">add_circle</span>
                                <span>Create New Session</span>
                            </button>
                            <button class="le-command-item" data-action="create-poll">
                                <span class="material-symbols-rounded" style="font-size: 20px;">poll</span>
                                <span>Create Poll</span>
                            </button>
                            <button class="le-command-item" data-action="create-quiz">
                                <span class="material-symbols-rounded" style="font-size: 20px;">quiz</span>
                                <span>Create Quiz</span>
                            </button>
                        </div>
                        <div class="le-command-group">
                            <div style="padding: 4px 16px; font-size: 0.75rem; color: var(--le-gray-500); text-transform: uppercase; letter-spacing: 0.05em;">Settings</div>
                            <button class="le-command-item" data-action="toggle-theme">
                                <span class="material-symbols-rounded" style="font-size: 20px;">${state.theme === 'dark' ? 'light_mode' : 'dark_mode'}</span>
                                <span>Toggle Theme</span>
                                <span style="margin-left: auto; font-size: 0.75rem; color: var(--le-gray-400);">⌘T</span>
                            </button>
                            <button class="le-command-item" data-action="toggle-fullscreen">
                                <span class="material-symbols-rounded" style="font-size: 20px;">fullscreen</span>
                                <span>Toggle Fullscreen</span>
                                <span style="margin-left: auto; font-size: 0.75rem; color: var(--le-gray-400);">⌘F</span>
                            </button>
                        </div>
                    </div>
                    <div style="padding: 8px 16px; border-top: 1px solid var(--le-gray-200); font-size: 0.75rem; color: var(--le-gray-400); display: flex; gap: 16px;">
                        <span>↑↓ Navigate</span>
                        <span>↵ Select</span>
                        <span>Esc Close</span>
                    </div>
                </div>
            </div>
        `;

        // Add command item styles
        const style = document.createElement('style');
        style.textContent = `
            .le-command-item {
                display: flex;
                align-items: center;
                gap: 12px;
                width: 100%;
                padding: 10px 16px;
                border: none;
                background: none;
                cursor: pointer;
                font-family: var(--le-font-family);
                font-size: 0.875rem;
                color: var(--le-gray-700);
                transition: all 0.1s ease;
                text-align: left;
            }
            .le-command-item:hover,
            .le-command-item.highlighted {
                background: var(--le-gray-100);
                color: var(--le-primary);
            }
            .le-command-item:active {
                background: var(--le-primary-lighter);
            }
            .le-command-group:not(:last-child) {
                border-bottom: 1px solid var(--le-gray-100);
            }
        `;
        document.head.appendChild(style);

        // Command input filtering
        const input = overlay.querySelector('#le-command-input');
        input.addEventListener('input', function() {
            const query = this.value.toLowerCase();
            const items = overlay.querySelectorAll('.le-command-item');
            items.forEach(item => {
                const text = item.textContent.toLowerCase();
                item.style.display = text.includes(query) ? 'flex' : 'none';
            });
        });

        // Command item click handlers
        overlay.querySelectorAll('.le-command-item').forEach(item => {
            item.addEventListener('click', function() {
                const action = this.dataset.action;
                const value = this.dataset.value;
                executeCommand(action, value);
                closeCommandPalette();
            });
        });

        // Close on overlay click
        overlay.addEventListener('click', function(e) {
            if (e.target === this) closeCommandPalette();
        });

        document.body.appendChild(overlay);
    }

    function openCommandPalette() {
        setupCommandPalette();
        const overlay = document.getElementById('le-command-palette');
        if (overlay) {
            overlay.style.display = 'flex';
            state.commandPaletteOpen = true;
            setTimeout(() => {
                const input = document.getElementById('le-command-input');
                if (input) input.focus();
            }, 100);
        }
    }

    function closeCommandPalette() {
        const overlay = document.getElementById('le-command-palette');
        if (overlay) {
            overlay.style.display = 'none';
            state.commandPaletteOpen = false;
        }
    }

    function executeCommand(action, value) {
        switch (action) {
            case 'navigate':
                if (value === 'dashboard') window.location.href = '?page=dashboard';
                else if (value === 'join') window.location.href = '?page=join';
                else if (value === 'presentations') window.location.href = '?page=presentations';
                break;
            case 'create-session':
                window.location.href = '?page=dashboard&create=1';
                break;
            case 'create-poll':
                showToast('Create poll feature coming soon', 'info');
                break;
            case 'create-quiz':
                showToast('Create quiz feature coming soon', 'info');
                break;
            case 'toggle-theme':
                toggleTheme();
                break;
            case 'toggle-fullscreen':
                toggleFullscreen();
                break;
        }
    }

    // ============================================================
    // Keyboard Shortcuts
    // ============================================================
    function setupKeyboardShortcuts() {
        document.addEventListener('keydown', function(e) {
            // Don't trigger shortcuts when typing in inputs
            if (['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName)) return;
            if (e.target.isContentEditable) return;

            const isMac = navigator.platform.includes('Mac');
            const modKey = isMac ? e.metaKey : e.ctrlKey;

            // Command Palette: Cmd+K / Ctrl+K
            if (modKey && e.key === 'k') {
                e.preventDefault();
                if (state.commandPaletteOpen) {
                    closeCommandPalette();
                } else {
                    openCommandPalette();
                }
                return;
            }

            // Escape: Close modals / command palette
            if (e.key === 'Escape') {
                if (state.commandPaletteOpen) {
                    closeCommandPalette();
                    return;
                }
                // Close topmost modal
                if (state.activeModals.length > 0) {
                    const lastModal = state.activeModals[state.activeModals.length - 1];
                    if (typeof lastModal === 'string') {
                        const el = document.getElementById(lastModal);
                        if (el) el.style.display = 'none';
                    }
                    state.activeModals.pop();
                    return;
                }
            }

            // Cmd+D: Dashboard
            if (modKey && e.key === 'd') {
                e.preventDefault();
                window.location.href = '?page=dashboard';
            }

            // Cmd+J: Join Session
            if (modKey && e.key === 'j') {
                e.preventDefault();
                window.location.href = '?page=join';
            }

            // Cmd+P: Presentations
            if (modKey && e.key === 'p') {
                e.preventDefault();
                window.location.href = '?page=presentations';
            }

            // Cmd+T: Toggle Theme
            if (modKey && e.key === 't') {
                e.preventDefault();
                toggleTheme();
            }

            // Cmd+F: Toggle Fullscreen
            if (modKey && e.key === 'f') {
                e.preventDefault();
                toggleFullscreen();
            }

            // ?: Show shortcuts help
            if (e.key === '?' && !modKey) {
                e.preventDefault();
                showShortcutsHelp();
            }
        });
    }

    function showShortcutsHelp() {
        const shortcuts = [
            { key: '⌘K / Ctrl+K', action: 'Command Palette' },
            { key: '⌘D', action: 'Dashboard' },
            { key: '⌘J', action: 'Join Session' },
            { key: '⌘P', action: 'Presentations' },
            { key: '⌘T', action: 'Toggle Theme' },
            { key: '⌘F', action: 'Toggle Fullscreen' },
            { key: 'Esc', action: 'Close Modal / Command Palette' },
            { key: '?', action: 'Show Shortcuts' },
        ];

        let html = '<div style="display: grid; grid-template-columns: auto 1fr; gap: 8px 16px; align-items: center;">';
        shortcuts.forEach(s => {
            html += `
                <kbd style="background: var(--le-gray-100); padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; font-family: var(--le-font-mono); border: 1px solid var(--le-gray-200);">${s.key}</kbd>
                <span style="font-size: 0.9rem;">${s.action}</span>
            `;
        });
        html += '</div>';

        showModal(html, { title: 'Keyboard Shortcuts', size: 'sm' });
    }

    // ============================================================
    // Ripple Effect
    // ============================================================
    function setupRippleEffect() {
        document.addEventListener('click', function(e) {
            const btn = e.target.closest('.le-btn');
            if (!btn) return;

            const ripple = document.createElement('span');
            const rect = btn.getBoundingClientRect();
            
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;

            ripple.style.cssText = `
                position: absolute;
                width: ${size}px;
                height: ${size}px;
                left: ${x}px;
                top: ${y}px;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.4);
                transform: scale(0);
                animation: le-ripple 0.6s ease-out;
                pointer-events: none;
            `;

            btn.style.position = 'relative';
            btn.style.overflow = 'hidden';
            btn.appendChild(ripple);

            setTimeout(() => ripple.remove(), 600);
        });

        // Add ripple keyframe if not exists
        if (!document.getElementById('le-ripple-style')) {
            const style = document.createElement('style');
            style.id = 'le-ripple-style';
            style.textContent = `
                @keyframes le-ripple {
                    to { transform: scale(4); opacity: 0; }
                }
            `;
            document.head.appendChild(style);
        }
    }

    // ============================================================
    // Drag and Drop
    // ============================================================
    function setupDragAndDrop() {
        document.addEventListener('dragstart', function(e) {
            const draggable = e.target.closest('[data-draggable]');
            if (draggable) {
                draggable.classList.add('le-dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', draggable.dataset.draggableId || '');
            }
        });

        document.addEventListener('dragend', function(e) {
            const draggable = e.target.closest('[data-draggable]');
            if (draggable) {
                draggable.classList.remove('le-dragging');
            }
            document.querySelectorAll('.le-drag-over').forEach(el => {
                el.classList.remove('le-drag-over');
            });
        });

        document.addEventListener('dragover', function(e) {
            const dropZone = e.target.closest('[data-dropzone]');
            if (dropZone) {
                e.preventDefault();
                dropZone.classList.add('le-drag-over');
            }
        });

        document.addEventListener('dragleave', function(e) {
            const dropZone = e.target.closest('[data-dropzone]');
            if (dropZone) {
                dropZone.classList.remove('le-drag-over');
            }
        });

        document.addEventListener('drop', function(e) {
            const dropZone = e.target.closest('[data-dropzone]');
            if (dropZone) {
                e.preventDefault();
                dropZone.classList.remove('le-drag-over');
                const id = e.dataTransfer.getData('text/plain');
                const event = new CustomEvent('le-drop', {
                    detail: { id, target: dropZone.dataset.dropzoneId || '' }
                });
                dropZone.dispatchEvent(event);
            }
        });
    }

    // ============================================================
    // Autosave
    // ============================================================
    function setupAutosave() {
        setInterval(function() {
            if (state.unsavedChanges) {
                saveDraft();
            }
        }, config.autosaveInterval);

        // Track changes on form inputs
        document.addEventListener('input', function(e) {
            if (e.target.closest('[data-autosave]')) {
                state.unsavedChanges = true;
            }
        });

        // Save before leaving
        window.addEventListener('beforeunload', function(e) {
            if (state.unsavedChanges) {
                saveDraft();
            }
        });
    }

    function saveDraft() {
        const forms = document.querySelectorAll('[data-autosave]');
        forms.forEach(form => {
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());
            
            // Dispatch autosave event
            const event = new CustomEvent('le-autosave', {
                detail: { form: form.id || 'unknown', data }
            });
            form.dispatchEvent(event);
        });
        state.unsavedChanges = false;
    }

    function markUnsaved() {
        state.unsavedChanges = true;
    }

    // ============================================================
    // Online Status
    // ============================================================
    function setupOnlineStatus() {
        window.addEventListener('online', function() {
            state.isOnline = true;
            showToast('Back online', 'success');
            document.dispatchEvent(new CustomEvent('le-online'));
        });

        window.addEventListener('offline', function() {
            state.isOnline = false;
            showToast('You are offline. Changes will be saved locally.', 'warning');
            document.dispatchEvent(new CustomEvent('le-offline'));
        });
    }

    // ============================================================
    // Page Transitions
    // ============================================================
    function setupPageTransitions() {
        // Add page enter animation
        document.body.classList.add('le-page-enter');
        
        // Intercept navigation links for smooth transitions
        document.addEventListener('click', function(e) {
            const link = e.target.closest('a[data-transition]');
            if (link && link.href && link.href.startsWith(window.location.origin)) {
                e.preventDefault();
                navigateTo(link.href);
            }
        });
    }

    function navigateTo(url) {
        // Fade out
        document.body.style.opacity = '0';
        document.body.style.transform = 'translateY(8px)';
        document.body.style.transition = 'all 0.2s ease';
        
        setTimeout(() => {
            window.location.href = url;
        }, 200);
    }

    // ============================================================
    // Fullscreen
    // ============================================================
    function toggleFullscreen() {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen?.();
        } else {
            document.exitFullscreen?.();
        }
    }

    // ============================================================
    // Global Event Listeners
    // ============================================================
    function setupGlobalListeners() {
        // Reaction buttons
        document.addEventListener('click', function(e) {
            const reactionBtn = e.target.closest('.le-reaction-btn');
            if (reactionBtn) {
                e.preventDefault();
                const type = reactionBtn.dataset.reaction || 'like';
                sendReaction(type);
            }
        });

        // Hand raise
        document.addEventListener('click', function(e) {
            const handBtn = e.target.closest('.le-hand-raise-btn');
            if (handBtn) {
                e.preventDefault();
                toggleHandRaise();
            }
        });

        // Poll voting
        document.addEventListener('click', function(e) {
            const pollOption = e.target.closest('.le-poll-option');
            if (pollOption) {
                e.preventDefault();
                const pollId = pollOption.dataset.pollId;
                const optionId = pollOption.dataset.optionId;
                if (pollId && optionId) {
                    votePoll(pollId, optionId);
                }
            }
        });

        // Modal close buttons
        document.addEventListener('click', function(e) {
            const closeBtn = e.target.closest('.le-modal-close');
            if (closeBtn) {
                const modal = closeBtn.closest('.le-modal-overlay');
                if (modal) {
                    modal.style.display = 'none';
                }
            }
        });

        // Modal overlay click to close
        document.addEventListener('click', function(e) {
            const overlay = e.target.closest('.le-modal-overlay');
            if (overlay && e.target === overlay) {
                overlay.style.display = 'none';
            }
        });

        // Toast close buttons
        document.addEventListener('click', function(e) {
            const closeBtn = e.target.closest('.le-toast-close');
            if (closeBtn) {
                const toast = closeBtn.closest('.le-toast');
                if (toast) {
                    toast.classList.add('le-toast-exit');
                    setTimeout(() => toast.remove(), 300);
                }
            }
        });
    }

    // ============================================================
    // API Request Helper
    // ============================================================
    async function apiRequest(endpoint, options = {}) {
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': config.csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
        };

        const fetchOptions = { ...defaultOptions, ...options };
        
        if (fetchOptions.body && typeof fetchOptions.body === 'object') {
            fetchOptions.body = JSON.stringify(fetchOptions.body);
        }

        // Optimistic UI: dispatch event before request
        if (options.optimistic) {
            document.dispatchEvent(new CustomEvent('le-optimistic-start', {
                detail: { endpoint, data: options.body }
            }));
        }

        try {
            const response = await fetch(config.apiBase + endpoint, fetchOptions);
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.error || `HTTP ${response.status}`);
            }

            // Optimistic UI: dispatch success event
            if (options.optimistic) {
                document.dispatchEvent(new CustomEvent('le-optimistic-success', {
                    detail: { endpoint, data }
                }));
            }
            
            return data;
        } catch (error) {
            console.error('API Error:', error);
            
            // Optimistic UI: dispatch error event for rollback
            if (options.optimistic) {
                document.dispatchEvent(new CustomEvent('le-optimistic-error', {
                    detail: { endpoint, error: error.message }
                }));
            }
            
            showToast(error.message, 'error');
            throw error;
        }
    }

    // ============================================================
    // Session Management
    // ============================================================
    async function createSession(sessionData) {
        const result = await apiRequest('session.php', {
            method: 'POST',
            body: { ...sessionData, action: 'create' },
            optimistic: true,
        });
        return result.data;
    }

    async function joinSession(code, displayName = '') {
        const result = await apiRequest('session.php', {
            method: 'POST',
            body: { action: 'join', code, display_name: displayName },
        });
        
        state.sessionId = result.data.session.id;
        state.participantId = result.data.participant_id;
        
        return result.data;
    }

    async function leaveSession() {
        if (!state.participantId) return;
        
        await apiRequest('session.php', {
            method: 'POST',
            body: { action: 'leave', participant_id: state.participantId },
        });
        
        stopPolling();
        state.sessionId = null;
        state.participantId = null;
    }

    async function startSession(sessionId) {
        const result = await apiRequest('session.php', {
            method: 'POST',
            body: { action: 'start', session_id: sessionId },
            optimistic: true,
        });
        return result.data;
    }

    async function endSession(sessionId) {
        await apiRequest('session.php', {
            method: 'POST',
            body: { action: 'end', session_id: sessionId },
            optimistic: true,
        });
        stopPolling();
    }

    async function getSession(sessionId) {
        const result = await apiRequest(`session.php?action=view&id=${sessionId}`);
        return result.data;
    }

    async function checkSessionCode(code) {
        const result = await apiRequest(`session.php?action=check&code=${encodeURIComponent(code)}`);
        return result.data;
    }

    async function getMySessions() {
        const result = await apiRequest('session.php?action=list');
        return result.data;
    }

    // ============================================================
    // Participants
    // ============================================================
    async function getParticipants(sessionId, onlineOnly = false) {
        const result = await apiRequest(`session.php?action=participants&id=${sessionId}&online=${onlineOnly ? '1' : '0'}`);
        return result.data;
    }

    async function toggleHandRaise() {
        if (!state.participantId) return;
        
        const btn = document.querySelector('.le-hand-raise-btn');
        const isRaised = btn?.classList.toggle('active');
        
        await apiRequest('session.php', {
            method: 'POST',
            body: { 
                action: 'raise_hand', 
                participant_id: state.participantId,
                raised: isRaised ? 'true' : 'false',
            },
        });
    }

    // ============================================================
    // Reactions
    // ============================================================
    async function sendReaction(type = 'like') {
        if (!state.sessionId) return;
        
        // Show flying animation
        showFlyingReaction(type);
        
        await apiRequest('session.php', {
            method: 'POST',
            body: { action: 'reaction', session_id: state.sessionId, type },
        });
    }

    function showFlyingReaction(emoji) {
        const emojiMap = {
            'like': '👍',
            'love': '❤️',
            'laugh': '😂',
            'wow': '😮',
            'sad': '😢',
            'angry': '😡',
            'clap': '👏',
            'celebrate': '🎉',
        };
        
        const displayEmoji = emojiMap[emoji] || emoji;
        
        for (let i = 0; i < 3; i++) {
            setTimeout(() => {
                const el = document.createElement('div');
                el.className = 'le-reaction-fly';
                el.textContent = displayEmoji;
                el.style.left = (Math.random() * 60 + 20) + '%';
                el.style.bottom = (20 + Math.random() * 20) + '%';
                document.body.appendChild(el);
                setTimeout(() => el.remove(), 1500);
            }, i * 100);
        }
    }

    async function getReactions(sessionId) {
        const result = await apiRequest(`session.php?action=reactions&id=${sessionId}`);
        return result.data;
    }

    // ============================================================
    // Polls
    // ============================================================
    async function createPoll(pollData, options) {
        const result = await apiRequest('poll.php', {
            method: 'POST',
            body: { ...pollData, action: 'create', options },
            optimistic: true,
        });
        
        state.currentPollId = result.data.id;
        return result.data;
    }

    async function getPolls(sessionId) {
        const result = await apiRequest(`poll.php?action=list&session_id=${sessionId}`);
        return result.data;
    }

    async function getActivePolls(sessionId) {
        const result = await apiRequest(`poll.php?action=active&session_id=${sessionId}`);
        return result.data;
    }

    async function activatePoll(pollId) {
        const result = await apiRequest('poll.php', {
            method: 'POST',
            body: { action: 'activate', id: pollId },
            optimistic: true,
        });
        return result.data;
    }

    async function closePoll(pollId) {
        await apiRequest('poll.php', {
            method: 'POST',
            body: { action: 'close', id: pollId },
            optimistic: true,
        });
    }

    async function votePoll(pollId, optionId) {
        const result = await apiRequest('poll.php', {
            method: 'POST',
            body: { action: 'vote', id: pollId, option_id: optionId },
            optimistic: true,
        });
        
        // Highlight selected option
        document.querySelectorAll(`[data-poll-id="${pollId}"]`).forEach(el => {
            el.classList.toggle('selected', parseInt(el.dataset.optionId) === parseInt(optionId));
        });
        
        return result;
    }

    async function getPollResults(pollId) {
        const result = await apiRequest(`poll.php?action=results&id=${pollId}`);
        return result.data;
    }

    // ============================================================
    // Quizzes
    // ============================================================
    async function createQuiz(quizData, questions) {
        const result = await apiRequest('quiz.php', {
            method: 'POST',
            body: { ...quizData, action: 'create', questions },
            optimistic: true,
        });
        return result.data;
    }

    async function startQuiz(quizId, participantId = 0) {
        const result = await apiRequest('quiz.php', {
            method: 'POST',
            body: { action: 'start_attempt', id: quizId, participant_id: participantId },
        });
        return result.data;
    }

    async function submitAnswer(attemptId, questionId, answerId = null, answerText = null) {
        await apiRequest('quiz.php', {
            method: 'POST',
            body: { 
                action: 'submit_answer', 
                attempt_id: attemptId, 
                question_id: questionId, 
                answer_id: answerId, 
                answer_text: answerText 
            },
            optimistic: true,
        });
    }

    async function completeQuiz(attemptId) {
        const result = await apiRequest('quiz.php', {
            method: 'POST',
            body: { action: 'complete_attempt', attempt_id: attemptId },
        });
        return result.data;
    }

    async function getLeaderboard(quizId) {
        const result = await apiRequest(`quiz.php?action=leaderboard&id=${quizId}`);
        return result.data;
    }

    // ============================================================
    // Word Cloud
    // ============================================================
    async function submitWord(wordcloudId, word) {
        await apiRequest('activity.php', {
            method: 'POST',
            body: { action: 'wordcloud_submit', id: wordcloudId, word },
            optimistic: true,
        });
    }

    async function getWordCloudWords(wordcloudId) {
        const result = await apiRequest(`activity.php?action=wordcloud_words&id=${wordcloudId}`);
        return result.data;
    }

    // ============================================================
    // Polling (Auto-refresh)
    // ============================================================
    function startPolling(type, callback, interval = config.pollingInterval) {
        const timerId = setInterval(async () => {
            try {
                let data;
                
                switch (type) {
                    case 'participants':
                        data = await getParticipants(state.sessionId, state.isPresenter);
                        break;
                    case 'active_polls':
                        data = await getActivePolls(state.sessionId);
                        break;
                    case 'reactions':
                        data = await getReactions(state.sessionId);
                        break;
                    case 'poll_results':
                        if (state.currentPollId) {
                            data = await getPollResults(state.currentPollId);
                        }
                        break;
                    default:
                        return;
                }
                
                if (data && callback) {
                    callback(data);
                }
            } catch (error) {
                console.warn(`Polling ${type} failed:`, error);
            }
        }, interval);

        state.pollingTimers.push(timerId);
        return timerId;
    }

    function stopPolling() {
        state.pollingTimers.forEach(timerId => clearInterval(timerId));
        state.pollingTimers = [];
    }

    // ============================================================
    // UI Helpers
    // ============================================================

    /**
     * Show a premium toast notification
     */
    function showToast(message, type = 'info', duration = 4000) {
        const container = document.querySelector('.le-toast-container') || createToastContainer();
        
        const icons = {
            success: 'check_circle',
            error: 'error',
            warning: 'warning',
            info: 'info',
        };

        const toast = document.createElement('div');
        toast.className = `le-toast le-toast-${type}`;
        toast.innerHTML = `
            <span class="le-toast-icon material-symbols-rounded">${icons[type] || 'info'}</span>
            <span class="le-toast-content">${escapeHtml(message)}</span>
            <button class="le-toast-close material-symbols-rounded" style="font-size: 18px;">close</button>
        `;
        
        container.appendChild(toast);
        
        // Auto dismiss
        const timeout = setTimeout(() => {
            toast.classList.add('le-toast-exit');
            setTimeout(() => toast.remove(), 300);
        }, duration);

        // Click to dismiss
        toast.querySelector('.le-toast-close').addEventListener('click', () => {
            clearTimeout(timeout);
            toast.classList.add('le-toast-exit');
            setTimeout(() => toast.remove(), 300);
        });
    }

    function createToastContainer() {
        const container = document.createElement('div');
        container.className = 'le-toast-container';
        document.body.appendChild(container);
        return container;
    }

    /**
     * Show a premium modal
     */
    function showModal(content, options = {}) {
        const overlay = document.createElement('div');
        overlay.className = 'le-modal-overlay';
        
        const sizeClass = options.size === 'lg' ? 'le-modal-lg' : 
                         options.size === 'xl' ? 'le-modal-xl' :
                         options.size === 'sm' ? 'le-modal-sm' : '';
        
        const modal = document.createElement('div');
        modal.className = `le-modal ${sizeClass}`;
        
        modal.innerHTML = `
            <div class="le-modal-header">
                <h3 class="le-card-title">${options.title || ''}</h3>
                <button class="le-modal-close">&times;</button>
            </div>
            <div class="le-modal-body"></div>
            ${options.footer ? `<div class="le-modal-footer">${options.footer}</div>` : ''}
        `;
        
        const body = modal.querySelector('.le-modal-body');
        if (typeof content === 'string') {
            body.innerHTML = content;
        } else if (content instanceof HTMLElement) {
            body.appendChild(content);
        }
        
        // Close handlers
        modal.querySelector('.le-modal-close').addEventListener('click', () => {
            overlay.remove();
            state.activeModals = state.activeModals.filter(m => m !== overlay);
        });
        
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                overlay.remove();
                state.activeModals = state.activeModals.filter(m => m !== overlay);
            }
        });
        
        overlay.appendChild(modal);
        document.body.appendChild(overlay);
        
        // Track modal
        state.activeModals.push(overlay);
        
        return { overlay, modal, close: () => overlay.remove() };
    }

    /**
     * Show loading skeleton in a container
     */
    function showSkeleton(container, type = 'text', count = 3) {
        const el = typeof container === 'string' ? document.querySelector(container) : container;
        if (!el) return;
        
        el.innerHTML = '';
        el.style.display = 'block';
        
        for (let i = 0; i < count; i++) {
            const skeleton = document.createElement('div');
            skeleton.className = `le-skeleton le-skeleton-${type}`;
            if (type === 'text') {
                skeleton.style.width = (60 + Math.random() * 40) + '%';
                skeleton.style.marginBottom = '8px';
            }
            el.appendChild(skeleton);
        }
    }

    /**
     * Hide skeleton and show content
     */
    function hideSkeleton(container) {
        const el = typeof container === 'string' ? document.querySelector(container) : container;
        if (!el) return;
        el.innerHTML = '';
    }

    /**
     * Copy text to clipboard
     */
    async function copyToClipboard(text) {
        try {
            await navigator.clipboard.writeText(text);
            showToast('Copied to clipboard', 'success');
        } catch (err) {
            // Fallback
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            showToast('Copied to clipboard', 'success');
        }
    }

    /**
     * Escape HTML entities
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Format duration in seconds
     */
    function formatDuration(seconds) {
        if (seconds < 60) return `${seconds}s`;
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return secs > 0 ? `${mins}m ${secs}s` : `${mins}m`;
    }

    /**
     * Format date relative to now
     */
    function timeAgo(dateString) {
        const now = new Date();
        const date = new Date(dateString);
        const seconds = Math.floor((now - date) / 1000);
        
        if (seconds < 60) return 'just now';
        if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
        if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
        if (seconds < 2592000) return `${Math.floor(seconds / 86400)}d ago`;
        return date.toLocaleDateString();
    }

    /**
     * Animate count up
     */
    function animateCountUp(element, target, duration = 1000) {
        const start = 0;
        const startTime = performance.now();
        
        function update(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3); // easeOutCubic
            const current = Math.floor(start + (target - start) * eased);
            
            element.textContent = current.toLocaleString();
            
            if (progress < 1) {
                requestAnimationFrame(update);
            }
        }
        
        requestAnimationFrame(update);
    }

    // ============================================================
    // Public API
    // ============================================================
    return {
        init,
        
        // Theme
        setTheme,
        getTheme,
        toggleTheme,
        
        // Command Palette
        openCommandPalette,
        closeCommandPalette,
        
        // Session
        createSession,
        joinSession,
        leaveSession,
        startSession,
        endSession,
        getSession,
        checkSessionCode,
        getMySessions,
        getParticipants,
        toggleHandRaise,
        
        // Reactions
        sendReaction,
        getReactions,
        
        // Polls
        createPoll,
        getPolls,
        getActivePolls,
        activatePoll,
        closePoll,
        votePoll,
        getPollResults,
        
        // Quizzes
        createQuiz,
        startQuiz,
        submitAnswer,
        completeQuiz,
        getLeaderboard,
        
        // Word Cloud
        submitWord,
        getWordCloudWords,
        
        // Polling
        startPolling,
        stopPolling,
        
        // UI
        showToast,
        showModal,
        showSkeleton,
        hideSkeleton,
        copyToClipboard,
        formatDuration,
        timeAgo,
        animateCountUp,
        escapeHtml,
        markUnsaved,
        navigateTo,
        toggleFullscreen,
        
        // State
        getState: () => ({ ...state }),
        setState: (updates) => { Object.assign(state, updates); },
        isOnline: () => state.isOnline,
    };
})();

// Auto-initialize on DOM ready
document.addEventListener('DOMContentLoaded', function() {
    // Load theme
    const savedTheme = localStorage.getItem('le-theme');
    if (savedTheme) {
        document.documentElement.setAttribute('data-theme', savedTheme);
    }
    
    // Initialize if not already done
    if (typeof LiveEngagement !== 'undefined') {
        LiveEngagement.init();
    }
});