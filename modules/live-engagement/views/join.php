<?php
/**
 * Live Engagement Module - Join Session Page (Premium 2025/2026)
 * 
 * Beautiful glassmorphism join page with animated background.
 * Students use this page to join a live session by code.
 * 
 * @package UNILIS\LiveEngagement\Views
 * @version 2.0.0
 */

require_once __DIR__ . '/../bootstrap.php';
le_require_auth();

use LE\Components\UI;

$userId = le_current_user_id();
$userName = le_current_user_name() ?? 'Student';
$userEmail = le_current_user_email() ?? '';

include __DIR__ . '/../../includes/header.php';
?>
<link rel="stylesheet" href="<?= le_asset_url('css/live-engagement.css') ?>">
<?= le_csrf_meta() ?>
<?= UI::inlineScript() ?>

<div class="le-join-page">
    <!-- Animated background particles -->
    <div style="position: absolute; inset: 0; overflow: hidden; pointer-events: none;">
        <div style="position: absolute; width: 300px; height: 300px; border-radius: 50%; background: rgba(255,255,255,0.05); top: 10%; left: 5%; animation: le-float-slow 12s ease-in-out infinite;"></div>
        <div style="position: absolute; width: 200px; height: 200px; border-radius: 50%; background: rgba(255,255,255,0.03); top: 60%; left: 80%; animation: le-float-slow 15s ease-in-out infinite reverse;"></div>
        <div style="position: absolute; width: 150px; height: 150px; border-radius: 50%; background: rgba(255,255,255,0.04); top: 30%; left: 50%; animation: le-float-slow 10s ease-in-out infinite 2s;"></div>
    </div>

    <div class="le-join-card">
        <!-- Logo / Icon -->
        <div style="margin-bottom: var(--le-space-3);">
            <div style="width: 72px; height: 72px; border-radius: var(--le-radius-2xl); background: rgba(255,255,255,0.15); display: flex; align-items: center; justify-content: center; margin: 0 auto; backdrop-filter: blur(8px);">
                <span class="material-symbols-rounded" style="font-size: 36px; color: white;">rocket_launch</span>
            </div>
        </div>

        <h1 style="font-size: 1.8rem; font-weight: 700; color: white; margin-bottom: 4px;">Join Live Session</h1>
        <p style="color: rgba(255,255,255,0.7); margin-bottom: var(--le-space-4); font-size: 0.95rem;">
            Enter the session code provided by your lecturer
        </p>

        <form id="joinForm" onsubmit="joinSession(event)" style="max-width: 380px; margin: 0 auto;">
            <div class="le-form-group">
                <div style="position: relative;">
                    <span class="material-symbols-rounded" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: rgba(255,255,255,0.5); font-size: 24px;">vpn_key</span>
                    <input type="text" class="le-input" id="sessionCode" 
                           placeholder="Enter Code"
                           style="background: rgba(255,255,255,0.12); border-color: rgba(255,255,255,0.2); color: white; text-align: center; font-size: 1.8rem; font-family: var(--le-font-mono); letter-spacing: 10px; text-transform: uppercase; padding: 16px 16px 16px 48px; border-radius: var(--le-radius-xl); height: 60px;"
                           maxlength="10" required autocomplete="off" autofocus>
                </div>
                <p class="le-hint-text" style="color: rgba(255,255,255,0.4); text-align: center; margin-top: 8px;">
                    e.g. ABC12345
                </p>
            </div>

            <div class="le-form-group" style="text-align: left;">
                <label class="le-label" style="color: rgba(255,255,255,0.7);">Your Display Name</label>
                <div style="position: relative;">
                    <span class="material-symbols-rounded" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: rgba(255,255,255,0.5); font-size: 20px;">person</span>
                    <input type="text" class="le-input" id="displayName" 
                           value="<?= UI::escape($userName) ?>"
                           style="background: rgba(255,255,255,0.12); border-color: rgba(255,255,255,0.2); color: white; padding-left: 44px; border-radius: var(--le-radius-lg);"
                           required>
                </div>
            </div>

            <button type="submit" class="le-btn le-btn-primary le-btn-lg" style="width: 100%; justify-content: center; padding: 16px; font-size: 1rem; border-radius: var(--le-radius-xl); background: linear-gradient(135deg, rgba(255,255,255,0.2), rgba(255,255,255,0.1)); border: 1px solid rgba(255,255,255,0.3); color: white; box-shadow: 0 8px 32px rgba(0,0,0,0.2);" 
                    id="joinButton">
                <span class="material-symbols-rounded" style="font-size: 22px;">login</span>
                Join Session
            </button>
        </form>

        <div id="errorMessage" style="display: none; margin-top: var(--le-space-3); padding: var(--le-space-2) var(--le-space-3); background: rgba(220, 38, 38, 0.2); border: 1px solid rgba(220, 38, 38, 0.3); border-radius: var(--le-radius-lg); color: #FCA5A5; font-size: 0.9rem;"></div>
        
        <div id="loadingSpinner" style="display: none; margin-top: var(--le-space-3);" class="le-loading">
            <div class="le-spinner" style="border-color: rgba(255,255,255,0.2); border-top-color: white;"></div>
            <span style="color: rgba(255,255,255,0.7);">Joining session...</span>
        </div>

        <!-- Divider -->
        <div style="display: flex; align-items: center; gap: var(--le-space-2); margin: var(--le-space-4) 0;">
            <div style="flex: 1; height: 1px; background: rgba(255,255,255,0.1);"></div>
            <span style="color: rgba(255,255,255,0.4); font-size: 0.85rem;">or</span>
            <div style="flex: 1; height: 1px; background: rgba(255,255,255,0.1);"></div>
        </div>

        <a href="?page=dashboard" style="display: inline-flex; align-items: center; gap: 8px; color: rgba(255,255,255,0.6); font-size: 0.9rem; transition: color 0.2s;" 
           onmouseover="this.style.color='rgba(255,255,255,0.9)'" onmouseout="this.style.color='rgba(255,255,255,0.6)'">
            <span class="material-symbols-rounded" style="font-size: 18px;">arrow_back</span>
            Back to Dashboard
        </a>
    </div>

    <!-- Available Sessions (for logged-in students) -->
    <?php
    $sessionModel = new \LE\Models\SessionModel();
    $availableSessions = $sessionModel->getStudentAvailableSessions($userId);
    if (!empty($availableSessions)):
    ?>
    <div style="position: absolute; bottom: var(--le-space-4); left: 50%; transform: translateX(-50%); width: 90%; max-width: 500px;">
        <div class="le-card" style="background: rgba(255,255,255,0.08); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.1); padding: var(--le-space-3);">
            <div class="le-flex-between" style="margin-bottom: var(--le-space-2);">
                <h3 style="color: white; font-size: 0.95rem; margin: 0;">Available Sessions</h3>
                <span style="color: rgba(255,255,255,0.5); font-size: 0.8rem;"><?= count($availableSessions) ?> active</span>
            </div>
            <div style="display: flex; flex-direction: column; gap: 8px; max-height: 200px; overflow-y: auto;">
                <?php foreach (array_slice($availableSessions, 0, 5) as $session): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: rgba(255,255,255,0.06); border-radius: var(--le-radius-md); transition: background 0.2s;"
                         onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='rgba(255,255,255,0.06)'">
                        <div>
                            <strong style="color: white; font-size: 0.9rem;"><?= UI::escape($session['title']) ?></strong>
                            <?php if (!empty($session['unit_name'])): ?>
                                <span style="color: rgba(255,255,255,0.5); font-size: 0.8rem;"> - <?= UI::escape($session['unit_name']) ?></span>
                            <?php endif; ?>
                            <div style="display: flex; gap: 8px; margin-top: 4px;">
                                <span class="le-badge le-badge-<?= $session['status'] ?>" style="font-size: 0.7rem; padding: 2px 8px;">
                                    <?= $session['status'] ?>
                                </span>
                                <span style="font-size: 0.75rem; color: rgba(255,255,255,0.4);">
                                    👥 <?= $session['online_count'] ?? 0 ?> online
                                </span>
                            </div>
                        </div>
                        <button class="le-btn le-btn-sm" style="background: rgba(255,255,255,0.15); color: white; border: 1px solid rgba(255,255,255,0.2); border-radius: var(--le-radius-full); padding: 6px 16px; font-size: 0.8rem;" 
                                onclick="joinBySessionId(<?= $session['id'] ?>)">
                            Join
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script src="<?= le_asset_url('js/live-engagement.js') ?>"></script>
<script>
    LiveEngagement.init();

    async function joinSession(event) {
        event.preventDefault();
        const code = document.getElementById('sessionCode').value.trim().toUpperCase();
        const displayName = document.getElementById('displayName').value.trim();
        const errorEl = document.getElementById('errorMessage');
        const spinner = document.getElementById('loadingSpinner');
        const form = document.getElementById('joinForm');
        const button = document.getElementById('joinButton');

        if (!code) {
            showError('Please enter a session code');
            return;
        }

        if (!displayName) {
            showError('Please enter your display name');
            return;
        }

        errorEl.style.display = 'none';
        spinner.style.display = 'flex';
        button.disabled = true;
        button.innerHTML = '<div class="le-spinner le-spinner-sm" style="border-color: rgba(255,255,255,0.3); border-top-color: white;"></div> Joining...';

        try {
            // Check if session exists
            const check = await LiveEngagement.checkSessionCode(code);
            
            if (!check.exists) {
                showError('Session not found. Please check the code and try again.');
                return;
            }

            if (!check.active) {
                showError('This session is not currently active.');
                return;
            }

            // Join the session
            const result = await LiveEngagement.joinSession(code, displayName);
            
            // Redirect to session view
            window.location.href = '?page=session&id=' + result.session.id;
            
        } catch (error) {
            showError(error.message || 'Failed to join session. Please try again.');
        } finally {
            spinner.style.display = 'none';
            button.disabled = false;
            button.innerHTML = '<span class="material-symbols-rounded" style="font-size: 22px;">login</span> Join Session';
        }
    }

    async function joinBySessionId(sessionId) {
        try {
            const session = await LiveEngagement.getSession(sessionId);
            if (session.status === 'active') {
                const result = await LiveEngagement.joinSession(session.session_code, '<?= UI::escape($userName) ?>');
                window.location.href = '?page=session&id=' + result.session.id;
            } else {
                showError('Session is not active');
            }
        } catch (error) {
            showError(error.message);
        }
    }

    function showError(message) {
        const el = document.getElementById('errorMessage');
        el.textContent = message;
        el.style.display = 'block';
    }

    // Auto-submit on Enter in code field
    document.getElementById('sessionCode').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            document.getElementById('displayName').focus();
        }
    });

    document.getElementById('displayName').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            document.getElementById('joinForm').dispatchEvent(new Event('submit'));
        }
    });

    // Auto-focus code input
    document.getElementById('sessionCode').focus();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>