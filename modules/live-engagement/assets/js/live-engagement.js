/**
 * Live Engagement Module - Main JavaScript
 * Handles session management, polling, quizzes, and real-time interactions.
 * 
 * @package UNILIS\LiveEngagement
 * @version 1.0.0
 */

const LiveEngagement = (function() {
    'use strict';

    // Configuration
    const config = {
        apiBase: 'modules/live-engagement/api/',
        pollingInterval: 3000,
        reconnectAttempts: 5,
        reconnectDelay: 2000,
        csrfToken: document.querySelector('meta[name="csrf-token"]')?.content || '',
    };

    // State
    let state = {
        sessionId: null,
        participantId: null,
        isPresenter: false,
        pollingTimers: [],
        currentPollId: null,
        currentQuizId: null,
    };

    /**
     * Initialize the module
     * 
     * @param {Object} options Configuration options
     */
    function init(options = {}) {
        Object.assign(config, options);
        
        if (options.sessionId) state.sessionId = options.sessionId;
        if (options.participantId) state.participantId = options.participantId;
        if (options.isPresenter) state.isPresenter = true;
        
        setupEventListeners();
        console.log('Live Engagement Module initialized');
    }

    /**
     * Set up global event listeners
     */
    function setupEventListeners() {
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
    }

    // ============================================================
    // API Request Helper
    // ============================================================

    /**
     * Make an API request
     * 
     * @param {string} endpoint API endpoint
     * @param {Object} options Fetch options
     * @returns {Promise}
     */
    async function apiRequest(endpoint, options = {}) {
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': config.csrfToken,
            },
        };

        const fetchOptions = { ...defaultOptions, ...options };
        
        if (fetchOptions.body && typeof fetchOptions.body === 'object') {
            fetchOptions.body = JSON.stringify(fetchOptions.body);
        }

        try {
            const response = await fetch(config.apiBase + endpoint, fetchOptions);
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.error || `HTTP ${response.status}`);
            }
            
            return data;
        } catch (error) {
            console.error('API Error:', error);
            showToast(error.message, 'error');
            throw error;
        }
    }

    // ============================================================
    // Session Management
    // ============================================================

    /**
     * Create a new live session
     * 
     * @param {Object} sessionData Session details
     * @returns {Promise}
     */
    async function createSession(sessionData) {
        const result = await apiRequest('session.php', {
            method: 'POST',
            body: { ...sessionData, action: 'create' },
        });
        return result.data;
    }

    /**
     * Join a live session by code
     * 
     * @param {string} code Session code
     * @param {string} displayName Participant display name
     * @returns {Promise}
     */
    async function joinSession(code, displayName = '') {
        const result = await apiRequest('session.php', {
            method: 'POST',
            body: { action: 'join', code, display_name: displayName },
        });
        
        state.sessionId = result.data.session.id;
        state.participantId = result.data.participant_id;
        
        return result.data;
    }

    /**
     * Leave the current session
     */
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

    /**
     * Start a session (presenter)
     * 
     * @param {number} sessionId
     */
    async function startSession(sessionId) {
        const result = await apiRequest('session.php', {
            method: 'POST',
            body: { action: 'start', session_id: sessionId },
        });
        return result.data;
    }

    /**
     * End a session (presenter)
     * 
     * @param {number} sessionId
     */
    async function endSession(sessionId) {
        await apiRequest('session.php', {
            method: 'POST',
            body: { action: 'end', session_id: sessionId },
        });
        stopPolling();
    }

    /**
     * Get session details
     * 
     * @param {number} sessionId
     * @returns {Promise}
     */
    async function getSession(sessionId) {
        const result = await apiRequest(`session.php?action=view&id=${sessionId}`);
        return result.data;
    }

    /**
     * Check if a session code is valid
     * 
     * @param {string} code Session code
     * @returns {Promise}
     */
    async function checkSessionCode(code) {
        const result = await apiRequest(`session.php?action=check&code=${encodeURIComponent(code)}`);
        return result.data;
    }

    /**
     * Get active sessions for the current user
     * 
     * @returns {Promise}
     */
    async function getMySessions() {
        const result = await apiRequest('session.php?action=list');
        return result.data;
    }

    // ============================================================
    // Participants
    // ============================================================

    /**
     * Get participants in a session
     * 
     * @param {number} sessionId
     * @param {boolean} onlineOnly Only get online participants
     * @returns {Promise}
     */
    async function getParticipants(sessionId, onlineOnly = false) {
        const result = await apiRequest(`session.php?action=participants&id=${sessionId}&online=${onlineOnly ? '1' : '0'}`);
        return result.data;
    }

    /**
     * Toggle hand raise
     */
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

    /**
     * Send a reaction
     * 
     * @param {string} type Reaction type
     */
    async function sendReaction(type = 'like') {
        if (!state.sessionId) return;
        
        // Show flying animation
        showFlyingReaction(type);
        
        await apiRequest('session.php', {
            method: 'POST',
            body: { action: 'reaction', session_id: state.sessionId, type },
        });
    }

    /**
     * Show flying reaction animation
     * 
     * @param {string} emoji Reaction emoji/icon
     */
    function showFlyingReaction(emoji) {
        const el = document.createElement('div');
        el.className = 'le-reaction-fly';
        el.textContent = emoji;
        el.style.left = (Math.random() * 80 + 10) + '%';
        el.style.bottom = '20%';
        document.body.appendChild(el);
        
        setTimeout(() => el.remove(), 1500);
    }

    /**
     * Get reaction counts
     * 
     * @param {number} sessionId
     * @returns {Promise}
     */
    async function getReactions(sessionId) {
        const result = await apiRequest(`session.php?action=reactions&id=${sessionId}`);
        return result.data;
    }

    // ============================================================
    // Polls
    // ============================================================

    /**
     * Create a poll
     * 
     * @param {Object} pollData Poll data
     * @param {Array} options Poll options
     * @returns {Promise}
     */
    async function createPoll(pollData, options) {
        const result = await apiRequest('poll.php', {
            method: 'POST',
            body: { ...pollData, action: 'create', options },
        });
        
        state.currentPollId = result.data.id;
        return result.data;
    }

    /**
     * Get polls for a session
     * 
     * @param {number} sessionId
     * @returns {Promise}
     */
    async function getPolls(sessionId) {
        const result = await apiRequest(`poll.php?action=list&session_id=${sessionId}`);
        return result.data;
    }

    /**
     * Get active polls
     * 
     * @param {number} sessionId
     * @returns {Promise}
     */
    async function getActivePolls(sessionId) {
        const result = await apiRequest(`poll.php?action=active&session_id=${sessionId}`);
        return result.data;
    }

    /**
     * Activate a poll (presenter)
     * 
     * @param {number} pollId
     */
    async function activatePoll(pollId) {
        const result = await apiRequest('poll.php', {
            method: 'POST',
            body: { action: 'activate', id: pollId },
        });
        return result.data;
    }

    /**
     * Close a poll (presenter)
     * 
     * @param {number} pollId
     */
    async function closePoll(pollId) {
        await apiRequest('poll.php', {
            method: 'POST',
            body: { action: 'close', id: pollId },
        });
    }

    /**
     * Vote on a poll
     * 
     * @param {number} pollId
     * @param {number} optionId
     */
    async function votePoll(pollId, optionId) {
        const result = await apiRequest('poll.php', {
            method: 'POST',
            body: { action: 'vote', id: pollId, option_id: optionId },
        });
        
        // Highlight selected option
        document.querySelectorAll(`[data-poll-id="${pollId}"]`).forEach(el => {
            el.classList.toggle('selected', parseInt(el.dataset.optionId) === parseInt(optionId));
        });
        
        return result;
    }

    /**
     * Get poll results
     * 
     * @param {number} pollId
     * @returns {Promise}
     */
    async function getPollResults(pollId) {
        const result = await apiRequest(`poll.php?action=results&id=${pollId}`);
        return result.data;
    }

    // ============================================================
    // Quizzes
    // ============================================================

    /**
     * Create a quiz with questions
     * 
     * @param {Object} quizData
     * @param {Array} questions
     * @returns {Promise}
     */
    async function createQuiz(quizData, questions) {
        const result = await apiRequest('quiz.php', {
            method: 'POST',
            body: { ...quizData, action: 'create', questions },
        });
        return result.data;
    }

    /**
     * Start a quiz attempt
     * 
     * @param {number} quizId
     * @param {number} participantId
     * @returns {Promise}
     */
    async function startQuiz(quizId, participantId = 0) {
        const result = await apiRequest('quiz.php', {
            method: 'POST',
            body: { action: 'start_attempt', id: quizId, participant_id: participantId },
        });
        return result.data;
    }

    /**
     * Submit a quiz answer
     * 
     * @param {number} attemptId
     * @param {number} questionId
     * @param {number|null} answerId
     * @param {string|null} answerText
     */
    async function submitAnswer(attemptId, questionId, answerId = null, answerText = null) {
        await apiRequest('quiz.php', {
            method: 'POST',
            body: { action: 'submit_answer', attempt_id: attemptId, question_id: questionId, answer_id: answerId, answer_text: answerText },
        });
    }

    /**
     * Complete a quiz attempt
     * 
     * @param {number} attemptId
     * @returns {Promise}
     */
    async function completeQuiz(attemptId) {
        const result = await apiRequest('quiz.php', {
            method: 'POST',
            body: { action: 'complete_attempt', attempt_id: attemptId },
        });
        return result.data;
    }

    /**
     * Get quiz leaderboard
     * 
     * @param {number} quizId
     * @returns {Promise}
     */
    async function getLeaderboard(quizId) {
        const result = await apiRequest(`quiz.php?action=leaderboard&id=${quizId}`);
        return result.data;
    }

    // ============================================================
    // Word Cloud
    // ============================================================

    /**
     * Submit a word to the word cloud
     * 
     * @param {number} wordcloudId
     * @param {string} word
     */
    async function submitWord(wordcloudId, word) {
        await apiRequest('activity.php', {
            method: 'POST',
            body: { action: 'wordcloud_submit', id: wordcloudId, word },
        });
    }

    /**
     * Get word cloud words
     * 
     * @param {number} wordcloudId
     * @returns {Promise}
     */
    async function getWordCloudWords(wordcloudId) {
        const result = await apiRequest(`activity.php?action=wordcloud_words&id=${wordcloudId}`);
        return result.data;
    }

    // ============================================================
    // Polling (Auto-refresh)
    // ============================================================

    /**
     * Start polling for updates
     * 
     * @param {string} type Type of data to poll
     * @param {Function} callback Callback function
     * @param {number} interval Polling interval in ms
     */
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

    /**
     * Stop all polling
     */
    function stopPolling() {
        state.pollingTimers.forEach(timerId => clearInterval(timerId));
        state.pollingTimers = [];
    }

    // ============================================================
    // UI Helpers
    // ============================================================

    /**
     * Show a toast notification
     * 
     * @param {string} message Toast message
     * @param {string} type Toast type (success, error, warning, info)
     */
    function showToast(message, type = 'info') {
        const container = document.querySelector('.le-toast-container') || createToastContainer();
        
        const toast = document.createElement('div');
        toast.className = `le-toast le-toast-${type}`;
        toast.innerHTML = `<span>${escapeHtml(message)}</span>`;
        
        container.appendChild(toast);
        
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100px)';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    /**
     * Create the toast container
     * 
     * @returns {HTMLElement}
     */
    function createToastContainer() {
        const container = document.createElement('div');
        container.className = 'le-toast-container';
        document.body.appendChild(container);
        return container;
    }

    /**
     * Show a modal dialog
     * 
     * @param {string|HTMLElement} content Modal content
     * @param {Object} options Modal options
     */
    function showModal(content, options = {}) {
        const overlay = document.createElement('div');
        overlay.className = 'le-modal-overlay';
        
        const modal = document.createElement('div');
        modal.className = `le-modal ${options.size === 'lg' ? 'le-modal-lg' : options.size === 'sm' ? 'le-modal-sm' : ''}`;
        
        modal.innerHTML = `
            <div class="le-modal-header">
                <h3 class="le-card-title">${escapeHtml(options.title || '')}</h3>
                <button class="le-modal-close">&times;</button>
            </div>
            <div class="le-modal-body"></div>
            ${options.footer ? `<div class="le-modal-footer">${options.footer}</div>` : ''}
        `;
        
        modal.querySelector('.le-modal-body').appendChild(
            typeof content === 'string' ? document.createRange().createContextualFragment(content) : content
        );
        
        modal.querySelector('.le-modal-close').addEventListener('click', () => overlay.remove());
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) overlay.remove();
        });
        
        overlay.appendChild(modal);
        document.body.appendChild(overlay);
        
        return { overlay, modal, close: () => overlay.remove() };
    }

    /**
     * Escape HTML entities
     * 
     * @param {string} text
     * @returns {string}
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Format duration in seconds
     * 
     * @param {number} seconds
     * @returns {string}
     */
    function formatDuration(seconds) {
        if (seconds < 60) return `${seconds}s`;
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return secs > 0 ? `${mins}m ${secs}s` : `${mins}m`;
    }

    // ============================================================
    // Public API
    // ============================================================

    return {
        init,
        
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
        formatDuration,
        escapeHtml,
        
        // State
        getState: () => ({ ...state }),
        setState: (updates) => { Object.assign(state, updates); },
    };
})();