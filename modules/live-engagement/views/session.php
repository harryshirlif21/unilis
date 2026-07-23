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

Layout::start([
    'title' => $session['title'] ?? 'Live Session',
    'layout' => 'immersive',
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
            Raise Hand
        </button>
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
    <button class="le-bottom-nav-item le-hand-raise-btn" onclick="LiveEngagement.toggleHandRaise()">
        <span class="material-symbols-rounded">pan_tool</span>
        <span>Hand</span>
    </button>
</nav>


<script>
    const SESSION_ID = <?= $sessionId ?>;
    const USER_NAME = <?= json_encode($userName) ?>;

    LiveEngagement.init({
        sessionId: SESSION_ID,
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
        
        if (data.participants) {
            count.textContent = data.participants.length;
            
            list.innerHTML = data.participants.map(p => `
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