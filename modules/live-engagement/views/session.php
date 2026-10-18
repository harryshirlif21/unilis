<?php
/**
 * Live Engagement Module - Student Session View (Premium 2025/2026)
 * 
 * Minimalist, distraction-free interface for students participating
 * in live sessions with floating bottom navigation.
 * 
 * @package UNILIS\LiveEngagement\Views
 * @version 2.0.0
 */

require_once __DIR__ . '/../bootstrap.php';
le_require_auth();

use LE\Components\Layout;
use LE\Components\UI;

$userId = le_current_user_id();
$userName = le_current_user_name() ?? 'Student';
$sessionId = (int)le_get('id', 0, true);

if (!$sessionId) {
    header('Location: ' . le_page_url('join'));
    exit;
}

$sessionModel = new \LE\Models\SessionModel();
$session = $sessionModel->find($sessionId);

if (!$session) {
    header('Location: ' . le_page_url('join'));
    exit;
}

$participant = \le_get_current_session_participant($sessionId, $userId);
if (!$participant) {
    header('Location: ' . le_page_url('join'));
    exit;
}

$participantId = (int) $participant['id'];

Layout::start([
    'title' => $session['title'] ?? 'Live Session',
    'layout' => 'app',
    'activeNav' => 'join',
]);
?>

<div class="le-student-layout" style="min-height: 100vh; padding: var(--le-space-3);">
    <!-- ============================================================ -->
    <!-- Main Content Area -->
    <!-- ============================================================ -->
    <div class="le-student-main">
        <!-- Session Header -->
        <div class="le-flex-between" style="flex-wrap: wrap; gap: var(--le-space-2);">
            <div>
                <h1 style="font-size: var(--le-font-size-2xl); font-weight: var(--le-font-weight-bold); margin: 0;">
                    <?= UI::escape($session['title']) ?>
                </h1>
                <div style="display: flex; align-items: center; gap: var(--le-space-2); margin-top: var(--le-space-1);">
                    <span class="le-badge le-badge-<?= $session['status'] ?>">
                        <?= $session['status'] ?>
                    </span>
                    <span style="font-size: var(--le-font-size-sm); color: var(--le-gray-500);">
                        👥 <?= $session['online_count'] ?? 0 ?> participants
                    </span>
                </div>
            </div>
            <button class="le-btn le-btn-danger le-btn-sm" onclick="leaveCurrentSession()">
                <span class="material-symbols-rounded" style="font-size: 18px;">exit_to_app</span>
                Leave
            </button>
        </div>

        <!-- Presentation / Content Area -->
        <div class="le-card" style="flex: 1; display: flex; align-items: center; justify-content: center; min-height: 400px; position: relative;">
            <div id="sessionContent" style="text-align: center; width: 100%;">
                <!-- Live presentation content will be injected here -->
                <div style="font-size: 4rem; margin-bottom: var(--le-space-3);">🎯</div>
                <h2 style="font-size: 1.5rem; font-weight: 600; margin-bottom: var(--le-space-1);">Waiting for presenter</h2>
                <p style="color: var(--le-gray-500);">The session will begin shortly. Stay tuned for interactive content.</p>
            </div>
        </div>

        <!-- Poll / Quiz / Word Cloud Area -->
        <div id="interactiveArea" style="display: none;">
            <!-- Dynamic content loaded via JS -->
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- Sidebar -->
    <!-- ============================================================ -->
    <div class="le-student-sidebar" style="display: flex; flex-direction: column; gap: var(--le-space-2);">
        <!-- Participants -->
        <div class="le-card-solid">
            <div class="le-card-header">
                <h2 class="le-card-title">
                    <span class="material-symbols-rounded" style="font-size: 20px; color: var(--le-primary);">people</span>
                    Participants
                </h2>
                <span class="le-badge le-badge-active" id="participantCount">0</span>
            </div>
            <div id="participantsList" style="max-height: 300px; overflow-y: auto;">
                <div class="le-loading">
                    <div class="le-spinner le-spinner-sm"></div>
                    <span>Loading...</span>
                </div>
            </div>
        </div>

        <!-- Reactions -->
        <div class="le-card-solid">
            <div class="le-card-header">
                <h2 class="le-card-title">
                    <span class="material-symbols-rounded" style="font-size: 20px; color: var(--le-primary);">emoji_emotions</span>
                    Reactions
                </h2>
            </div>
            <div class="le-reactions">
                <button class="le-reaction-btn" data-reaction="like" title="Like">👍 <span class="le-reaction-count">0</span></button>
                <button class="le-reaction-btn" data-reaction="love" title="Love">❤️ <span class="le-reaction-count">0</span></button>
                <button class="le-reaction-btn" data-reaction="laugh" title="Laugh">😂 <span class="le-reaction-count">0</span></button>
                <button class="le-reaction-btn" data-reaction="wow" title="Wow">😮 <span class="le-reaction-count">0</span></button>
                <button class="le-reaction-btn" data-reaction="clap" title="Clap">👏 <span class="le-reaction-count">0</span></button>
            </div>
        </div>

        <!-- Hand Raise -->
        <button class="le-btn le-btn-secondary le-btn-lg le-hand-raise-btn" style="width: 100%; justify-content: center; border-radius: var(--le-radius-lg);">
            <span class="material-symbols-rounded" style="font-size: 22px;">pan_tool</span>
            <span data-hand-label data-raised-label="Lower Hand">Raise Hand</span>
        </button>

        <div class="le-card-solid" id="questionRequestPanel">
            <div class="le-card-header">
                <h2 class="le-card-title">
                    <span class="material-symbols-rounded" style="font-size: 20px; color: var(--le-primary);">question_answer</span>
                    Ask a question
                </h2>
            </div>
            <p id="questionRequestStatus" role="status" style="color: var(--le-gray-500);">Request permission to type a question.</p>
            <button class="le-btn le-btn-primary" type="button" id="requestQuestionButton">Ask lecturer</button>
            <form id="questionForm" hidden>
                <label for="questionText" style="display: block; margin: var(--le-space-2) 0 var(--le-space-1);">Your question</label>
                <textarea id="questionText" class="le-input" maxlength="1000" required rows="4" style="width: 100%; resize: vertical;"></textarea>
                <button class="le-btn le-btn-primary" type="submit" style="margin-top: var(--le-space-2);">Send question</button>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- Floating Bottom Navigation (Mobile) -->
<!-- ============================================================ -->
<nav class="le-bottom-nav" id="bottomNav">
    <button class="le-bottom-nav-item active" onclick="switchStudentTab('content')">
        <span class="material-symbols-rounded">slideshow</span>
        <span>Content</span>
    </button>
    <button class="le-bottom-nav-item" onclick="switchStudentTab('participants')">
        <span class="material-symbols-rounded">people</span>
        <span>People</span>
    </button>
    <button class="le-bottom-nav-item" onclick="switchStudentTab('reactions')">
        <span class="material-symbols-rounded">emoji_emotions</span>
        <span>React</span>
    </button>
    <button class="le-bottom-nav-item le-hand-raise-btn">
        <span class="material-symbols-rounded">pan_tool</span>
        <span data-hand-label data-raised-label="Lower">Hand</span>
    </button>
</nav>


<script>
    const SESSION_ID = <?= $sessionId ?>;
    const USER_NAME = <?= json_encode($userName) ?>;

    LiveEngagement.init({
        sessionId: SESSION_ID,
        participantId: <?= $participantId ?>,
        handRaised: <?= !empty($participant['hand_raised']) ? 'true' : 'false' ?>,
        isPresenter: false,
    });

    // ============================================================
    // Session Management
    // ============================================================
    async function leaveCurrentSession() {
        if (!confirm('Are you sure you want to leave this session?')) return;
        await LiveEngagement.leaveSession();
        window.location.href = '?page=join';
    }

    // ============================================================
    // Participants Polling
    // ============================================================
    LiveEngagement.startPolling('participants', function(data) {
        const list = document.getElementById('participantsList');
        const count = document.getElementById('participantCount');
        const participants = Array.isArray(data) ? data : data.participants;
        
        if (Array.isArray(participants)) {
            count.textContent = participants.length;
            
            list.innerHTML = participants.map(p => `
                <div class="le-participant ${p.is_online ? 'le-participant-online' : ''}">
                    <div class="le-participant-avatar">
                        ${p.display_name ? p.display_name.charAt(0).toUpperCase() : '?'}
                    </div>
                    <span class="le-participant-name">${escapeHtml(p.display_name || 'Anonymous')}</span>
                    ${p.hand_raised ? '<span class="le-hand-raised material-symbols-rounded" style="font-size: 18px;">pan_tool</span>' : ''}
                    <span class="le-participant-role">${p.role || 'participant'}</span>
                </div>
            `).join('');
        }
    });

    // ============================================================
    // Reactions Polling
    // ============================================================
    LiveEngagement.startPolling('reactions', function(data) {
        if (data.reactions) {
            document.querySelectorAll('.le-reaction-btn').forEach(btn => {
                const type = btn.dataset.reaction;
                const count = data.reactions[type] || 0;
                const countEl = btn.querySelector('.le-reaction-count');
                if (countEl) countEl.textContent = count;
            });
        }
    });

    const questionStatus = document.getElementById('questionRequestStatus');
    const requestQuestionButton = document.getElementById('requestQuestionButton');
    const questionForm = document.getElementById('questionForm');
    const questionText = document.getElementById('questionText');

    function renderQuestionStatus(question) {
        if (!question) {
            questionStatus.textContent = 'Request permission to type a question.';
            requestQuestionButton.hidden = false;
            requestQuestionButton.disabled = false;
            questionForm.hidden = true;
            return;
        }
        if (question.status === 'pending') {
            questionStatus.textContent = 'Waiting for the lecturer to allow your question.';
            requestQuestionButton.hidden = true;
            questionForm.hidden = true;
        } else if (question.status === 'allowed') {
            questionStatus.textContent = 'The lecturer has allowed you to ask. Type your question below.';
            requestQuestionButton.hidden = true;
            questionForm.hidden = false;
        } else {
            questionStatus.textContent = 'Your question has been sent to the lecturer.';
            requestQuestionButton.hidden = false;
            requestQuestionButton.disabled = false;
            questionForm.hidden = true;
            requestQuestionButton.textContent = 'Ask another question';
        }
    }

    async function pollQuestionStatus() {
        try {
            const result = await leFetch('session.php?action=question_status&id=' + SESSION_ID);
            renderQuestionStatus(result.question || null);
        } catch (error) {
            questionStatus.textContent = 'Question requests are temporarily unavailable.';
            console.error('Unable to refresh question request status:', error);
        }
    }

    requestQuestionButton.addEventListener('click', async function() {
        requestQuestionButton.disabled = true;
        try {
            const result = await leFetch('session.php', {
                method: 'POST',
                body: { action: 'question_request', session_id: SESSION_ID },
            });
            renderQuestionStatus(result.question || null);
        } catch (error) {
            requestQuestionButton.disabled = false;
            questionStatus.textContent = error.message || 'Could not request permission.';
        }
    });

    questionForm.addEventListener('submit', async function(event) {
        event.preventDefault();
        const question = questionText.value.trim();
        if (!question) return;
        const submitButton = questionForm.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        try {
            const result = await leFetch('session.php', {
                method: 'POST',
                body: { action: 'question_submit', session_id: SESSION_ID, question },
            });
            questionText.value = '';
            renderQuestionStatus(result.question || null);
        } catch (error) {
            questionStatus.textContent = error.message || 'Could not send your question.';
        } finally {
            submitButton.disabled = false;
        }
    });

    pollQuestionStatus();
    setInterval(pollQuestionStatus, 3000);

    // ============================================================
    // Active Polls Polling
    // ============================================================
    LiveEngagement.startPolling('active_polls', function(data) {
        const area = document.getElementById('interactiveArea');
        if (data.polls && data.polls.length > 0) {
            area.style.display = 'block';
            // Render active poll
            const poll = data.polls[0];
            area.innerHTML = renderPoll(poll);
        } else {
            area.style.display = 'none';
        }
    });

    function renderPoll(poll) {
        return `
            <div class="le-card-solid le-poll-container">
                <div class="le-poll-question">${escapeHtml(poll.question)}</div>
                ${poll.options.map(opt => `
                    <button class="le-poll-option" data-poll-id="${poll.id}" data-option-id="${opt.id}">
                        ${escapeHtml(opt.option_text)}
                    </button>
                `).join('')}
                <div class="le-poll-footer">
                    <span class="le-poll-votes">${poll.total_votes || 0} votes</span>
                </div>
            </div>
        `;
    }

    // ============================================================
    // Slide Following
    //
    // The stage used to sit on "Waiting for presenter" for the whole session,
    // because nothing ever asked where the presenter was. This polls the deck
    // cursor and paints whichever slide they are on.
    //
    // The deck itself is fetched once and cached: only the cursor changes as
    // the presenter advances, so re-downloading every slide on a timer would be
    // wasted bandwidth on a lecture-hall connection.
    // ============================================================
    var slideCache = null;
    var shownSlide = null;

    function restorePresenterWaiting() {
        var stage = document.getElementById('sessionContent');
        stage.textContent = '';
        var icon = document.createElement('div');
        icon.style.fontSize = '4rem';
        icon.style.marginBottom = 'var(--le-space-3)';
        icon.textContent = '🎯';
        var heading = document.createElement('h2');
        heading.style.fontSize = '1.5rem';
        heading.style.fontWeight = '600';
        heading.style.marginBottom = 'var(--le-space-1)';
        heading.textContent = 'Waiting for presenter';
        var message = document.createElement('p');
        message.style.color = 'var(--le-gray-500)';
        message.textContent = 'The session will begin shortly. Stay tuned for interactive content.';
        stage.appendChild(icon);
        stage.appendChild(heading);
        stage.appendChild(message);
        delete stage.dataset.presenterActive;
    }

    async function leFetch(endpoint, options) {
        var opts = options || {};
        var request = {
            method: opts.method || 'GET',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
        };
        if (opts.body) request.body = JSON.stringify(opts.body);
        var res = await fetch(<?= json_encode(le_module_url('api') . '/') ?> + endpoint, request);
        var text = await res.text();
        var data = text ? JSON.parse(text) : {};
        if (!res.ok || data.success === false) {
            throw new Error(data.error || data.message || 'Request failed');
        }
        return data.data !== undefined ? data.data : data;
    }

    async function followPresenter() {
        if (document.hidden) return;

        var state;
        try {
            state = await leFetch('presentation.php?action=state&session_id=' + SESSION_ID);
        } catch (e) {
            return; // Transient; the current slide stays on screen.
        }

        if (!state || !state.active || !state.presentation_id) {
            var inactiveStage = document.getElementById('sessionContent');
            if (inactiveStage.dataset.presenterActive === 'true') {
                restorePresenterWaiting();
            }
            shownSlide = null;
            return;
        }

        if (!slideCache || slideCache.presentationId !== state.presentation_id) {
            try {
                var deck = await leFetch('presentation.php?action=slides&id=' + state.presentation_id);
                slideCache = { presentationId: state.presentation_id, slides: deck.slides || [] };
            } catch (e) {
                return;
            }
        }

        if (state.current_slide === shownSlide) return;
        shownSlide = state.current_slide;

        var slide = slideCache.slides.filter(function (s) {
            return Number(s.slide_number) === Number(state.current_slide);
        })[0];

        var stage = document.getElementById('sessionContent');
        stage.textContent = '';
        stage.dataset.presenterActive = 'true';

        if (!slide) {
            var waiting = document.createElement('p');
            waiting.style.color = 'var(--le-gray-500)';
            waiting.textContent = 'Waiting for the next slide…';
            stage.appendChild(waiting);
            return;
        }

        if (slide.image_path) {
            var img = document.createElement('img');
            img.src = slide.image_path;
            img.alt = 'Slide ' + slide.slide_number;
            img.style.maxWidth = '100%';
            img.style.maxHeight = '70vh';
            img.style.objectFit = 'contain';
            stage.appendChild(img);
        } else {
            // Lecturer-authored slide bodies are stored as rich text by the
            // editor, so they render as markup here, matching the presenter view.
            var body = document.createElement('div');
            body.style.textAlign = 'left';
            body.innerHTML = slide.content_html || '';
            stage.appendChild(body);
        }
    }

    followPresenter();
    setInterval(followPresenter, 4000);
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) followPresenter();
    });

    // ============================================================
    // Mobile Tab Switching
    // ============================================================
    function switchStudentTab(tab) {
        document.querySelectorAll('.le-bottom-nav-item').forEach(item => item.classList.remove('active'));
        event.target.closest('.le-bottom-nav-item')?.classList.add('active');
        
        if (tab === 'participants') {
            document.querySelector('.le-student-sidebar').classList.toggle('active');
        }
    }

    // ============================================================
    // Utility
    // ============================================================
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
</script>

<?php
Layout::end();