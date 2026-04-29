/**
 * Smart Lab View - Main JavaScript Module
 * Handles global interactions and utility functions
 */

(function() {
    'use strict';

    const SmartLabView = {
        // Configuration
        config: {
            autoRefreshInterval: 60000, // 60 seconds
            sensorRefreshInterval: 30000, // 30 seconds
        },

        // State management
        state: {
            isFullscreen: false,
            autoRefreshEnabled: true,
        },

        // Initialize the view
        init: function() {
            this.setupEventListeners();
            this.setupFullscreen();
            this.logPageLoad();
        },

        // Setup event listeners
        setupEventListeners: function() {
            const fullscreenBtn = document.getElementById('fullscreenBtn');
            if (fullscreenBtn) {
                fullscreenBtn.addEventListener('click', () => this.toggleFullscreen());
            }

            // Handle keyboard shortcuts
            document.addEventListener('keydown', (e) => this.handleKeyboardShortcuts(e));
        },

        // Toggle fullscreen mode
        toggleFullscreen: function() {
            const elem = document.documentElement;
            
            if (!this.state.isFullscreen) {
                // Enter fullscreen
                if (elem.requestFullscreen) {
                    elem.requestFullscreen();
                } else if (elem.webkitRequestFullscreen) {
                    elem.webkitRequestFullscreen();
                } else if (elem.mozRequestFullScreen) {
                    elem.mozRequestFullScreen();
                } else if (elem.msRequestFullscreen) {
                    elem.msRequestFullscreen();
                }
                
                this.state.isFullscreen = true;
                const btn = document.getElementById('fullscreenBtn');
                if (btn) {
                    btn.textContent = '⛶';
                    btn.title = 'Exit Fullscreen';
                }
            } else {
                // Exit fullscreen
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                } else if (document.webkitExitFullscreen) {
                    document.webkitExitFullscreen();
                } else if (document.mozCancelFullScreen) {
                    document.mozCancelFullScreen();
                } else if (document.msExitFullscreen) {
                    document.msExitFullscreen();
                }
                
                this.state.isFullscreen = false;
                const btn = document.getElementById('fullscreenBtn');
                if (btn) {
                    btn.textContent = '⛶';
                    btn.title = 'Fullscreen (F11)';
                }
            }
        },

        // Setup fullscreen change handling
        setupFullscreen: function() {
            document.addEventListener('fullscreenchange', () => {
                this.state.isFullscreen = !!document.fullscreenElement;
            });
            document.addEventListener('webkitfullscreenchange', () => {
                this.state.isFullscreen = !!document.webkitFullscreenElement;
            });
            document.addEventListener('mozfullscreenchange', () => {
                this.state.isFullscreen = !!document.mozFullScreenElement;
            });
        },

        // Handle keyboard shortcuts
        handleKeyboardShortcuts: function(event) {
            // F11 for toggleFullscreen (handled by browser)
            // Esc to exit fullscreen (handled by browser)
            
            // Custom shortcuts
            if (event.key === 'r' && event.ctrlKey) {
                // Ctrl+R refresh data (can be used by views)
                this.broadcastEvent('refreshData');
            }
        },

        // Broadcast custom event to all views
        broadcastEvent: function(eventName, data = {}) {
            const event = new CustomEvent('smartlab:' + eventName, { detail: data });
            document.dispatchEvent(event);
        },

        // Listen to custom events
        onEvent: function(eventName, callback) {
            document.addEventListener('smartlab:' + eventName, (e) => {
                callback(e.detail);
            });
        },

        // Log page load for debugging
        logPageLoad: function() {
            const role = document.querySelector('.smartlab-section')?.className.split(' ')[1] || 'unknown';
            console.log(`Smart Lab View loaded - Role: ${role}`);
        },

        // Common API functions
        api: {
            // Fetch data from API
            fetch: function(endpoint) {
                const url = `${window.location.origin}/smart-lab/smartlab/${endpoint}`;
                return fetch(url)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .catch(error => {
                        console.error('API Error:', error);
                        return { success: false, error: error.message };
                    });
            }
        }
    };

    // Utility functions
    const Utils = {
        // Format timestamp
        formatTime: function(timestamp) {
            if (!timestamp) return '--';
            const date = new Date(timestamp);
            return date.toLocaleString();
        },

        // Format date
        formatDate: function(timestamp) {
            if (!timestamp) return '--';
            const date = new Date(timestamp);
            return date.toLocaleDateString();
        },

        // Get percentage color
        getPercentageColor: function(value) {
            if (value >= 80) return '#22c55e';
            if (value >= 50) return '#eab308';
            return '#ef4444';
        },

        // Escape HTML
        escapeHtml: function(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        },

        // Show toast notification
        showToast: function(message, type = 'info', duration = 3000) {
            const container = document.getElementById('toast-container') || this.createToastContainer();
            
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.textContent = message;
            
            container.appendChild(toast);
            
            // Auto remove
            setTimeout(() => {
                toast.remove();
            }, duration);
        },

        // Create toast container
        createToastContainer: function() {
            const container = document.createElement('div');
            container.id = 'toast-container';
            container.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 10000;
                display: flex;
                flex-direction: column;
                gap: 10px;
            `;
            document.body.appendChild(container);
            return container;
        },

        // Debounce function
        debounce: function(func, wait) {
            let timeout;
            return function(...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, args), wait);
            };
        },

        // Throttle function
        throttle: function(func, limit) {
            let inThrottle;
            return function(...args) {
                if (!inThrottle) {
                    func.apply(this, args);
                    inThrottle = true;
                    setTimeout(() => inThrottle = false, limit);
                }
            };
        }
    };

    // Add styles for toast notifications
    const style = document.createElement('style');
    style.textContent = `
        .toast {
            padding: 1rem;
            border-radius: 0.375rem;
            font-weight: 600;
            animation: slideIn 0.3s ease-out;
        }

        .toast-info {
            background: #dbeafe;
            color: #0c4a6e;
            border-left: 4px solid #0284c7;
        }

        .toast-success {
            background: #dcfce7;
            color: #166534;
            border-left: 4px solid #22c55e;
        }

        .toast-warning {
            background: #fef3c7;
            color: #92400e;
            border-left: 4px solid #eab308;
        }

        .toast-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
    `;
    document.head.appendChild(style);

    // Initialize on DOM ready
    document.addEventListener('DOMContentLoaded', () => {
        SmartLabView.init();
    });

    // Export to global scope
    window.SmartLabView = SmartLabView;
    window.Utils = Utils;

})();
