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

use LE\Components\Layout;
use LE\Components\UI;

$userId = le_current_user_id();
$userName = le_current_user_name() ?? '';
$userEmail = le_current_user_email() ?? '';
$isAuthenticated = le_is_authenticated();

// Check if accessing via public presentation link
$presentationId = (int) le_get('presentation_id', 0, true);
$publicPresentation = null;

if ($presentationId) {
    try {
        $db = le_db();
        // Check if presentation is marked as public
        $publicPresentation = $db->fetchOne(
            "SELECT p.*, pp.share_token, pp.expires_at 
             FROM live_presentations p
             LEFT JOIN public_presentations pp ON p.id = pp.presentation_id
             WHERE p.id = ? AND (p.is_public = 1 OR pp.share_token IS NOT NULL)",
            [$presentationId],
            'i'
        );
        
        // Check if public link has expired
        if ($publicPresentation && !empty($publicPresentation['expires_at'])) {
            $expiresAt = strtotime($publicPresentation['expires_at']);
            if ($expiresAt < time()) {
                $publicPresentation = null;
            }
        }
        
        // If valid public presentation, redirect to presenter view
        if ($publicPresentation) {
            // For public presentations, allow guest access without authentication
            if (!$isAuthenticated) {
                // Create a temporary guest session
                $_SESSION['le_guest_access'] = true;
                $_SESSION['le_guest_presentation_id'] = $presentationId;
                $_SESSION['le_guest_token'] = $publicPresentation['share_token'] ?? '';
            }
            // Redirect to presenter view
            header('Location: ?page=presenter&presentation_id=' . $presentationId);
            exit;
        }
    } catch (Exception $e) {
        error_log("Public presentation check error: " . $e->getMessage());
    }
}

Layout::start([
    'title' => 'Join Session',
    'layout' => 'app',
    'activeNav' => 'join',
]);
?>

<style>
/* Join page specific styles using live-dash.css variables */
.ld-join-container {
    max-width: 480px;
    margin: 0 auto;
    padding: 40px 20px;
}
.ld-join-card {
    background: var(--panel);
    border: 1px solid var(--line);
    border-radius: 26px;
    padding: 32px;
    backdrop-filter: blur(24px);
    box-shadow: var(--shadow);
    text-align: center;
}
.ld-join-icon {
    width: 64px;
    height: 64px;
    border-radius: 16px;
    background: rgba(102,242,154,.12);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
}
.ld-join-icon .material-symbols-rounded {
    font-size: 32px;
    color: var(--green-2);
}
.ld-join-title {
    font-size: 24px;
    font-weight: 700;
    color: var(--text);
    margin-bottom: 8px;
}
.ld-join-subtitle {
    font-size: 14px;
    color: var(--muted);
    margin-bottom: 24px;
}
.ld-join-input {
    width: 100%;
    padding: 14px 16px;
    background: var(--panel-2);
    border: 1px solid var(--line);
    border-radius: 12px;
    color: var(--text);
    font-size: 16px;
    text-align: center;
    font-family: monospace;
    letter-spacing: 4px;
    text-transform: uppercase;
    outline: none;
    transition: all 0.2s ease;
}
.ld-join-input:focus {
    border-color: var(--green-2);
    box-shadow: 0 0 0 3px rgba(102,242,154,.12);
}
.ld-join-label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: var(--muted);
    margin-bottom: 8px;
    text-align: left;
}
.ld-join-hint {
    font-size: 12px;
    color: var(--muted);
    margin-top: 8px;
}
</style>

<div class="ld">
    <div class="ld-join-container">
        <div class="ld-join-card">
            <div class="ld-join-icon">
                <span class="material-symbols-rounded">vpn_key</span>
            </div>
            <h1 class="ld-join-title">Join Live Session</h1>
            <p class="ld-join-subtitle">Enter the session code provided by your lecturer</p>

            <form id="joinForm" onsubmit="joinSession(event)">
                <div style="margin-bottom: 20px;">
                    <input type="text" class="ld-join-input" id="sessionCode"
                           placeholder="ENTER CODE"
                           maxlength="10" required autocomplete="off" autofocus>
                    <p class="ld-join-hint">e.g. ABC12345</p>
                </div>

                <div style="margin-bottom: 24px; text-align: left;">
                    <label class="ld-join-label">Your Display Name</label>
                    <input type="text" class="ld-join-input" id="displayName"
                           value="<?= le_esc($userName) ?>"
                           style="text-align: left; letter-spacing: normal; text-transform: none; font-family: inherit;"
                           required>
                </div>

                <button type="submit" class="ld-btn primary" style="width: 100%; justify-content: center; padding: 14px;" id="joinButton">
                    <span class="material-symbols-rounded">login</span>
                    Join Session
                </button>
            </form>
        </div>
    </div>
</div>

<div id="errorMessage" style="display: none; margin-top: 20px; padding: 12px 16px; background: rgba(220, 38, 38, 0.15); border: 1px solid rgba(220, 38, 38, 0.3); border-radius: 12px; color: #FCA5A5; font-size: 14px;"></div>

<div id="loadingSpinner" style="display: none; margin-top: 20px; text-align: center;">
    <div style="width: 24px; height: 24px; border: 2px solid var(--muted); border-top-color: var(--green-2); border-radius: 50%; animation: spin 0.7s linear infinite; margin: 0 auto;"></div>
    <span style="color: var(--muted); margin-top: 8px; display: block;">Joining session...</span>
</div>

<style>
@keyframes spin { to { transform: rotate(360deg); } }
</style>

<script>
    LiveEngagement.init();

    const LE_AUTHENTICATED = <?= $isAuthenticated ? 'true' : 'false' ?>;
    const LE_LOGIN_BASE     = <?= json_encode(le_base_url() . '/login.php') ?>;
    const LE_JOIN_URL       = <?= json_encode(le_module_url('index.php?page=join')) ?>;

    let pendingCode = null;
    let pendingName = null;

    function currentNode() { return document.getElementById('sessionCode').value.trim().toUpperCase(); }
    function currentName() { return document.getElementById('displayName').value.trim(); }

    function setBusy(busy) {
        document.getElementById('loadingSpinner').style.display = busy ? 'flex' : 'none';
        const btn = document.getElementById('joinButton');
        btn.disabled = busy;
        btn.innerHTML = busy
            ? '<div class="le-spinner le-spinner-sm" style="border-color: rgba(255,255,255,0.3); border-top-color: white;"></div> Joining...'
            : '<span class="material-symbols-rounded" style="font-size: 22px;">login</span> Join Session';
    }

    function showAuthStep() {
        document.getElementById('authStep').style.display = 'flex';
        document.getElementById('errorMessage').style.display = 'none';
    }
    function hideAuthStep() {
        document.getElementById('authStep').style.display = 'none';
        document.getElementById('customLoginForm').style.display = 'none';
    }
    function toggleCustomLogin() {
        const el = document.getElementById('customLoginForm');
        const show = el.style.display !== 'flex';
        el.style.display = show ? 'flex' : 'none';

        // Prefill the guest name with whatever the user already typed above.
        if (show) {
            const nameInput = document.getElementById('guestName');
            const pending  = pendingName || currentName();
            if (pending && !nameInput.value.trim()) {
                nameInput.value = pending;
                nameInput.focus();
            }
        }
    }

    // ── Authenticated join ────────────────────────────────────────
    async function performJoin(code, displayName) {
        document.getElementById('errorMessage').style.display = 'none';
        setBusy(true);
        try {
            const check = await LiveEngagement.checkSessionCode(code);
            if (!check.exists) { showError('Session not found. Please check the code and try again.'); return; }
            if (!check.active) { showError('This session is not currently active.'); return; }
            const result = await LiveEngagement.joinSession(code, displayName || 'Participant');
            window.location.href = '?page=session&id=' + result.session.id;
        } catch (error) {
            if (/401|auth|sign in|log in/i.test(error.message || '')) {
                showAuthStep();
            } else {
                showError(error.message || 'Failed to join session. Please try again.');
            }
        } finally {
            setBusy(false);
        }
    }

    // ── Form submit ───────────────────────────────────────────────
    async function joinSession(event) {
        event.preventDefault();
        const code = currentNode();
        const displayName = currentName();

        if (!code) { showError('Please enter a session code'); return; }

        if (!LE_AUTHENTICATED) {
            if (!displayName) { showError('Please enter your display name'); return; }
            // Not signed in yet: remember the intent and ask how to log in.
            pendingCode = code;
            pendingName = displayName;
            showAuthStep();
            return;
        }

        await performJoin(code, displayName);
    }

    // ── Join from the "Available Sessions" list (logged-in users) ─
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

    // ── Sign in with UNILIS ───────────────────────────────────────
    function loginWithUnilis() {
        const code = pendingCode || currentNode();
        const target = LE_JOIN_URL + (code ? '&code=' + encodeURIComponent(code) : '');
        window.location.href = LE_LOGIN_BASE + '?redirect=' + encodeURIComponent(target);
    }

    // ── Join as guest (custom details) — name only, no email or password ──
    async function loginWithCustom() {
        const name = document.getElementById('guestName').value.trim()
            || pendingName || currentName();
        const errEl = document.getElementById('guestError');
        errEl.style.display = 'none';

        if (name.length < 2) { errEl.textContent = 'Please enter your display name.'; errEl.style.display = 'block'; return; }

        // Mark this browser session as an authenticated guest participant so the
        // session join API (which requires auth) will accept the join.
        try {
            const resp = await fetch('<?= le_module_url('api/guest_auth.php') ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'join_as_guest', name: name }),
            });
            let data = {};
            try { data = await resp.json(); } catch (e) {}
            if (!data.success) {
                errEl.textContent = (data.errors && data.errors[0]) || 'Unable to join as a guest. Please try again.';
                errEl.style.display = 'block';
                return;
            }
        } catch (e) {
            errEl.textContent = 'Unable to reach the server. Please try again.';
            errEl.style.display = 'block';
            return;
        }

        // Now authenticated as a guest — proceed with the pending join.
        hideAuthStep();
        const code = pendingCode || currentNode();
        await performJoin(code, name);
    }

    // ── Auto-submit on Enter ──────────────────────────────────────
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

    document.getElementById('guestName').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            loginWithCustom();
        }
    });

    // ── Auto-focus code input ─────────────────────────────────────
    document.getElementById('sessionCode').focus();

    // ── Resume a join after returning from the UNILIS login ───────
    (function resumeJoinAfterLogin() {
        const params = new URLSearchParams(window.location.search);
        const code = (params.get('code') || '').trim().toUpperCase();
        if (code && LE_AUTHENTICATED) {
            document.getElementById('sessionCode').value = code;
            if (document.getElementById('displayName').value.trim()) {
                performJoin(code, currentName());
            }
        }
    })();
</script>

<?php
Layout::end();
