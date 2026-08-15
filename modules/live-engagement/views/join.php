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
    'layout' => 'minimal',
    'activeNav' => 'join',
]);
?>

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

<!-- Auth Step (shown when a code is entered but the user is not signed in) -->
        <div id="authStep" style="display: none; flex-direction: column; gap: var(--le-space-3); margin-top: var(--le-space-3);">
            <div>
                <h3 style="color: white; font-size: 1.1rem; margin: 0 0 4px;">Sign in to join</h3>
                <p style="color: rgba(255,255,255,0.6); font-size: 0.9rem; margin: 0;">Choose how you'd like to sign in.</p>
            </div>

            <!-- UNILIS option -->
            <button type="button" onclick="loginWithUnilis()"
                    style="width: 100%; display: flex; align-items: center; gap: 12px; padding: 14px 16px; border-radius: var(--le-radius-xl); border: 1px solid rgba(255,255,255,0.25); background: rgba(255,255,255,0.08); color: white; cursor: pointer; text-align: left; transition: background 0.2s;"
                    onmouseover="this.style.background='rgba(255,255,255,0.14)'" onmouseout="this.style.background='rgba(255,255,255,0.08)'">
                <span class="material-symbols-rounded" style="font-size: 24px;">account_circle</span>
                <span>
                    <strong style="display: block; font-size: 0.95rem;">Login with UNILIS</strong>
                    <span style="font-size: 0.8rem; color: rgba(255,255,255,0.6);">Use your institution account</span>
                </span>
            </button>

            <!-- Custom details option -->
            <button type="button" onclick="toggleCustomLogin()"
                    style="width: 100%; display: flex; align-items: center; gap: 12px; padding: 14px 16px; border-radius: var(--le-radius-xl); border: 1px solid rgba(255,255,255,0.25); background: rgba(255,255,255,0.08); color: white; cursor: pointer; text-align: left; transition: background 0.2s;"
                    onmouseover="this.style.background='rgba(255,255,255,0.14)'" onmouseout="this.style.background='rgba(255,255,255,0.08)'">
                <span class="material-symbols-rounded" style="font-size: 24px;">badge</span>
                <span>
                    <strong style="display: block; font-size: 0.95rem;">Login with custom details</strong>
                    <span style="font-size: 0.8rem; color: rgba(255,255,255,0.6);">Guest participant account</span>
                </span>
            </button>

            <!-- Custom (guest) login form -->
            <div id="customLoginForm" style="display: none; flex-direction: column; gap: var(--le-space-2);">
                <input type="email" id="guestEmail" placeholder="Email address" autocomplete="email"
                       style="width: 100%; padding: 12px 14px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.25); background: rgba(255,255,255,0.08); color: #fff; font-size: 0.95rem; outline: none;">
                <input type="password" id="guestPassword" placeholder="Password" autocomplete="current-password"
                       style="width: 100%; padding: 12px 14px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.25); background: rgba(255,255,255,0.08); color: #fff; font-size: 0.95rem; outline: none;">
                <button type="button" onclick="loginWithCustom()"
                        class="le-btn le-btn-primary le-btn-lg"
                        style="width: 100%; justify-content: center; padding: 14px; border-radius: var(--le-radius-xl);">Log In</button>
                <div id="guestError" style="display: none; padding: 10px 12px; background: rgba(220,38,38,0.2); border: 1px solid rgba(220,38,38,0.3); border-radius: var(--le-radius-lg); color: #FCA5A5; font-size: 0.85rem;"></div>
                <p style="color: rgba(255,255,255,0.5); font-size: 0.8rem; margin: 0; text-align: center;">
                    No guest account? Use <button type="button" onclick="loginWithUnilis()" style="background:none;border:none;color:#F9A825;cursor:pointer;font-size:0.8rem;padding:0;text-decoration:underline;">Login with UNILIS</button>.
                </p>
            </div>

            <button type="button" onclick="hideAuthStep()"
                    style="background:none; border:none; color: rgba(255,255,255,0.6); cursor: pointer; font-size: 0.85rem; align-self: flex-start; padding: 4px 0;">
                &larr; Back to the code
            </button>
        </div>
        <!-- Divider -->
        <div style="display: flex; align-items: center; gap: var(--le-space-2); margin: var(--le-space-4) 0;">
            <div style="flex: 1; height: 1px; background: rgba(255,255,255,0.1);"></div>
            <span style="color: rgba(255,255,255,0.4); font-size: 0.85rem;">or</span>
            <div style="flex: 1; height: 1px; background: rgba(255,255,255,0.1);"></div>
        </div>

        <a href="<?= $isAuthenticated && le_can_present() ? le_page_url('dashboard') : (le_base_url() . '/login.php?redirect=' . rawurlencode(le_page_url('join'))) ?>"
           style="display: inline-flex; align-items: center; gap: 8px; color: rgba(255,255,255,0.6); font-size: 0.9rem; transition: color 0.2s;"
           onmouseover="this.style.color='rgba(255,255,255,0.9)'" onmouseout="this.style.color='rgba(255,255,255,0.6)'">
            <span class="material-symbols-rounded" style="font-size: 18px;">arrow_back</span>
            <?= $isAuthenticated && le_can_present() ? 'Back to Dashboard' : 'Sign in to UNILIS' ?>
        </a>
    </div>

    <!-- Available Sessions (for logged-in students) -->
    <?php
    if ($isAuthenticated && $userId) {
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
    <?php
        endif;
    }
    ?>
</div>


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
        el.style.display = el.style.display === 'flex' ? 'none' : 'flex';
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

    // ── Sign in with custom (guest) details ───────────────────────
    async function loginWithCustom() {
        const email    = document.getElementById('guestEmail').value.trim().toLowerCase();
        const password = document.getElementById('guestPassword').value;
        const errEl    = document.getElementById('guestError');
        errEl.style.display = 'none';

        if (!email || !password) { errEl.textContent = 'Enter your email and password.'; errEl.style.display = 'block'; return; }

        try {
            const resp = await fetch('<?= le_module_url('api/guest_auth.php') ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'login', email: email, password: password }),
            });
            let data = {};
            try { data = await resp.json(); } catch (e) {}
            if (!data.success) {
                errEl.textContent = (data.errors && data.errors[0]) || 'Login failed. Please try again.';
                errEl.style.display = 'block';
                return;
            }
        } catch (e) {
            errEl.textContent = 'Unable to reach the server. Please try again.';
            errEl.style.display = 'block';
            return;
        }

        // Now signed in as a guest — proceed with the pending join.
        hideAuthStep();
        const code = pendingCode || currentNode();
        const name = pendingName || 'Guest';
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

    document.getElementById('guestPassword').addEventListener('keydown', function(e) {
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
