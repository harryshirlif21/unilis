<?php
/**
 * Live Engagement Module - Join Session Page
 * 
 * Students use this page to join a live session by code.
 * 
 * @package UNILIS\LiveEngagement\Views
 * @version 1.0.0
 */

require_once __DIR__ . '/../bootstrap.php';
le_require_auth();

$userId = le_current_user_id();
$userName = le_current_user_name() ?? 'Student';

include __DIR__ . '/../../includes/header.php';
?>
<link rel="stylesheet" href="<?= le_asset_url('css/live-engagement.css') ?>">
<?= le_csrf_meta() ?>

<div class="le-container" style="max-width: 600px; margin: 40px auto;">
    <div class="le-card-solid" style="text-align: center;">
        <div style="font-size: 4rem; margin-bottom: 16px;">🎯</div>
        <h1 style="font-size: 1.8rem; margin-bottom: 8px;">Join Live Session</h1>
        <p style="color: var(--le-gray-600); margin-bottom: 32px;">
            Enter the session code provided by your lecturer to join the interactive session.
        </p>

        <form id="joinForm" onsubmit="joinSession(event)" style="max-width: 400px; margin: 0 auto;">
            <div class="le-form-group">
                <input type="text" class="le-input" id="sessionCode" 
                       placeholder="Enter Session Code (e.g. ABC12345)"
                       style="text-align: center; font-size: 1.5rem; font-family: monospace; letter-spacing: 4px; text-transform: uppercase;"
                       maxlength="10" required autocomplete="off">
            </div>
            <div class="le-form-group">
                <label class="le-label">Your Display Name</label>
                <input type="text" class="le-input" id="displayName" 
                       value="<?= le_escape($userName) ?>" required>
            </div>
            <button type="submit" class="le-btn le-btn-primary le-btn-lg" style="width: 100%; justify-content: center;">
                Join Session
            </button>
        </form>

        <div id="errorMessage" style="display: none; color: var(--le-danger); margin-top: 16px; padding: 12px; background: #f8d7da; border-radius: var(--le-radius-sm);"></div>
        
        <div id="loadingSpinner" style="display: none;" class="le-loading">
            <div class="le-spinner"></div>
            <span>Joining session...</span>
        </div>
    </div>

    <!-- Recent Sessions (for logged-in students) -->
    <?php
    $sessionModel = new \LE\Models\SessionModel();
    $availableSessions = $sessionModel->getStudentAvailableSessions($userId);
    if (!empty($availableSessions)):
    ?>
    <div class="le-card-solid" style="margin-top: 24px;">
        <div class="le-card-header">
            <h2 class="le-card-title">Available Sessions</h2>
        </div>
        <div style="display: flex; flex-direction: column; gap: 8px;">
            <?php foreach (array_slice($availableSessions, 0, 5) as $session): ?>
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px; border: 1px solid var(--le-gray-200); border-radius: var(--le-radius-sm);">
                    <div>
                        <strong><?= le_escape($session['title']) ?></strong>
                        <?php if (!empty($session['unit_name'])): ?>
                            <span style="font-size: 0.8rem; color: var(--le-gray-500);"> - <?= le_escape($session['unit_name']) ?></span>
                        <?php endif; ?>
                        <div style="font-size: 0.8rem; color: var(--le-gray-500); margin-top: 4px;">
                            <span class="le-badge le-badge-<?= $session['status'] ?>"><?= $session['status'] ?></span>
                            👥 <?= $session['online_count'] ?? 0 ?> online
                        </div>
                    </div>
                    <button class="le-btn le-btn-sm le-btn-primary" onclick="joinBySessionId(<?= $session['id'] ?>)">
                        Join
                    </button>
                </div>
            <?php endforeach; ?>
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
        form.querySelector('button[type="submit"]').disabled = true;

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
            form.querySelector('button[type="submit"]').disabled = false;
        }
    }

    async function joinBySessionId(sessionId) {
        try {
            const session = await LiveEngagement.getSession(sessionId);
            if (session.status === 'active') {
                const result = await LiveEngagement.joinSession(session.session_code, '<?= le_escape($userName) ?>');
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
            document.getElementById('displayName').focus();
        }
    });
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>