<?php
/**
 * Live Engagement — Landing Page
 */
require_once __DIR__ . '/bootstrap.php';

use LE\Components\Layout;

// Auto-open auth modal if redirected here with ?auth=1
$openAuthModal = !empty($_GET['auth']);

// Store Live Engagement redirect URL in session for the UNILIS login flow
$_SESSION['le_login_redirect'] = LE_MODULE_URL . '/index.php?page=dashboard&create=1&type=presentation';

Layout::start([
    'title'     => 'Live Engagement',
    'layout'    => 'minimal',
    'bodyClass' => 'le-home-page',
]);
?>

<style>
/* ── Reset & base ─────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --le-green:       #1B5E20;
    --le-green-mid:   #2E7D32;
    --le-green-light: #43A047;
    --le-amber:       #F9A825;
    --le-orange:      #EF6C00;
    --le-bg:          linear-gradient(135deg, #0d1f0f 0%, #1a3a1e 50%, #0f2415 100%);
    --glass:          rgba(255,255,255,0.07);
    --glass-border:   rgba(255,255,255,0.13);
    --glass-hover:    rgba(255,255,255,0.12);
    --text-bright:    #f0f7f0;
    --text-muted:     rgba(240,247,240,0.6);
    --card-radius:    18px;
    --trans:          all 0.25s cubic-bezier(.4,0,.2,1);
}

body.le-home-page {
    font-family: 'Inter', sans-serif;
    background: var(--le-bg);
    min-height: 100vh;
    color: var(--text-bright);
    overflow-x: hidden;
}

/* ── Layout ───────────────────────────────────────────────── */
.le-home-wrap {
    max-width: 560px;
    margin: 0 auto;
    padding: 0 1.25rem 4rem;
}

/* ── Hero ─────────────────────────────────────────────────── */
.le-hero {
    padding: 3rem 0 2rem;
    text-align: center;
    position: relative;
}
.le-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse 70% 50% at 50% 0%, rgba(249,168,37,.13) 0%, transparent 70%);
    pointer-events: none;
}
.le-logo-ring {
    width: 72px; height: 72px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--le-green), var(--le-amber));
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 1.25rem;
    box-shadow: 0 0 40px rgba(249,168,37,.2);
}
.le-logo-ring .material-symbols-rounded { font-size: 34px; color: #fff; }

.le-live-pill {
    display: inline-flex; align-items: center; gap: 6px;
    background: rgba(239,108,0,.16);
    border: 1px solid rgba(239,108,0,.32);
    border-radius: 20px;
    padding: 4px 14px;
    font-size: 12px; font-weight: 500; color: #FFB74D;
    margin-bottom: 1rem;
}
.le-live-dot {
    width: 7px; height: 7px;
    border-radius: 50%; background: #EF6C00;
    animation: le-pulse 1.5s ease-in-out infinite;
}
@keyframes le-pulse {
    0%,100% { opacity:1; transform:scale(1); }
    50%      { opacity:.45; transform:scale(.75); }
}

.le-hero h1 {
    font-size: 2.1rem; font-weight: 800;
    color: #fff; line-height: 1.2;
    margin-bottom: .5rem;
}
.le-hero p {
    font-size: .95rem; color: var(--text-muted);
    max-width: 400px; margin: 0 auto 2rem; line-height: 1.65;
}

/* ── Action cards ─────────────────────────────────────────── */
.le-cards {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 2rem;
}
.le-card {
    background: var(--glass);
    border: 1px solid var(--glass-border);
    border-radius: var(--card-radius);
    padding: 1.75rem 1.25rem 1.5rem;
    text-align: center;
    cursor: pointer;
    transition: var(--trans);
    position: relative;
    overflow: hidden;
}
.le-card::before {
    content: '';
    position: absolute; inset: 0;
    opacity: 0; transition: opacity .25s;
}
.le-card:hover { transform: translateY(-3px); border-color: rgba(255,255,255,.22); }
.le-card:hover::before { opacity: 1; }
.le-card:active { transform: translateY(-1px); }

.le-card-join::before  { background: radial-gradient(ellipse at 50% 0%, rgba(29,158,117,.12), transparent 70%); }
.le-card-create::before { background: radial-gradient(ellipse at 50% 0%, rgba(249,168,37,.12), transparent 70%); }
.le-card:hover.le-card-join  { background: rgba(29,158,117,.08); }
.le-card:hover.le-card-create { background: rgba(249,168,37,.06); }

.le-card-icon {
    width: 52px; height: 52px;
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 1rem;
}
.le-card-join .le-card-icon {
    background: rgba(29,158,117,.2);
    border: 1px solid rgba(29,158,117,.35);
}
.le-card-join .le-card-icon .material-symbols-rounded { color: #4ECBA1; font-size: 26px; }

.le-card-create .le-card-icon {
    background: rgba(249,168,37,.18);
    border: 1px solid rgba(249,168,37,.35);
}
.le-card-create .le-card-icon .material-symbols-rounded { color: var(--le-amber); font-size: 26px; }

.le-card h2 { font-size: 1.05rem; font-weight: 700; color: #fff; margin-bottom: .4rem; }
.le-card p  { font-size: .8rem; color: var(--text-muted); line-height: 1.5; }

/* ── Join form (inside join card, toggled) ────────────────── */
.le-join-form { display: none; margin-top: 1.25rem; text-align: left; }
.le-join-form.open { display: block; animation: le-fade-in .2s ease; }

@keyframes le-fade-in {
    from { opacity:0; transform:translateY(6px); }
    to   { opacity:1; transform:translateY(0); }
}

.le-input {
    width: 100%; padding: .75rem 1rem;
    background: rgba(0,0,0,.3);
    border: 1px solid var(--glass-border);
    border-radius: 10px;
    color: #fff; font-size: .9rem;
    outline: none; transition: var(--trans);
    margin-bottom: .65rem;
}
.le-input:focus { border-color: rgba(249,168,37,.55); box-shadow: 0 0 0 3px rgba(249,168,37,.1); }
.le-input::placeholder { color: rgba(255,255,255,.28); }

.le-code-input {
    font-size: 1.5rem; font-weight: 700;
    text-align: center; letter-spacing: .3em;
    font-family: 'Courier New', monospace;
    text-transform: uppercase;
}

.le-divider {
    display: flex; align-items: center; gap: .75rem;
    margin: .75rem 0; color: var(--text-muted); font-size: .75rem;
}
.le-divider::before, .le-divider::after {
    content: ''; flex: 1;
    height: 1px; background: var(--glass-border);
}

.le-btn {
    width: 100%; padding: .8rem;
    border: none; border-radius: 10px;
    font-size: .88rem; font-weight: 700;
    cursor: pointer; display: flex;
    align-items: center; justify-content: center; gap: .5rem;
    transition: var(--trans);
}
.le-btn .material-symbols-rounded { font-size: 18px; }

.le-btn-teal {
    background: linear-gradient(135deg, #1D9E75, #0F6E56);
    color: #fff;
}
.le-btn-teal:hover { filter: brightness(1.1); transform: translateY(-1px); box-shadow: 0 6px 20px rgba(29,158,117,.3); }

.le-btn-amber {
    background: linear-gradient(135deg, var(--le-amber), var(--le-orange));
    color: #fff;
}
.le-btn-amber:hover { filter: brightness(1.1); transform: translateY(-1px); box-shadow: 0 6px 20px rgba(249,168,37,.3); }

.le-btn-ghost {
    background: var(--glass);
    border: 1px solid var(--glass-border);
    color: var(--text-bright);
    margin-top: .5rem;
}
.le-btn-ghost:hover { background: var(--glass-hover); }

/* ── QR section ───────────────────────────────────────────── */
.le-qr-row {
    display: flex; gap: .65rem; margin-top: .75rem;
}
.le-qr-box {
    flex: 0 0 auto;
    width: 80px; height: 80px;
    background: #fff; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
}
.le-qr-box .material-symbols-rounded { font-size: 52px; color: var(--le-green); }
.le-qr-hint { font-size: .75rem; color: var(--text-muted); line-height: 1.5; padding-top: .2rem; }

/* ── Auth modal overlay ───────────────────────────────────── */
.le-modal-overlay {
    display: none;
    position: fixed; inset: 0; z-index: 9999;
    background: rgba(0,0,0,.65);
    align-items: center; justify-content: center;
    padding: 1rem;
    backdrop-filter: blur(4px);
}
.le-modal-overlay.open { display: flex; animation: le-fade-in .2s ease; }

.le-modal {
    background: #0f2415;
    border: 1px solid var(--glass-border);
    border-radius: 22px;
    padding: 2rem;
    width: 100%; max-width: 420px;
    position: relative;
    box-shadow: 0 24px 60px rgba(0,0,0,.5);
    animation: le-slide-up .25s cubic-bezier(.4,0,.2,1);
}
@keyframes le-slide-up {
    from { opacity:0; transform:translateY(20px); }
    to   { opacity:1; transform:translateY(0); }
}

.le-modal-close {
    position: absolute; top: 1rem; right: 1rem;
    background: var(--glass); border: 1px solid var(--glass-border);
    border-radius: 50%; width: 32px; height: 32px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; color: var(--text-muted); transition: var(--trans);
}
.le-modal-close:hover { background: var(--glass-hover); color: #fff; }
.le-modal-close .material-symbols-rounded { font-size: 18px; }

.le-modal-logo {
    width: 48px; height: 48px; border-radius: 12px;
    background: linear-gradient(135deg, var(--le-green), var(--le-amber));
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 1rem;
}
.le-modal-logo .material-symbols-rounded { font-size: 24px; color: #fff; }

.le-modal h2 { font-size: 1.2rem; font-weight: 700; color: #fff; text-align: center; margin-bottom: .3rem; }
.le-modal .le-modal-sub { font-size: .82rem; color: var(--text-muted); text-align: center; margin-bottom: 1.5rem; }

/* Modal tabs */
.le-modal-tabs { display: flex; gap: 4px; background: rgba(0,0,0,.3); border-radius: 10px; padding: 4px; margin-bottom: 1.25rem; }
.le-modal-tab  { flex: 1; padding: .55rem; border: none; background: transparent; color: var(--text-muted); font-size: .82rem; font-weight: 500; border-radius: 7px; cursor: pointer; transition: var(--trans); }
.le-modal-tab.active { background: var(--le-green-mid); color: #fff; }

/* Modal panels */
.le-modal-panel { display: none; }
.le-modal-panel.active { display: block; animation: le-fade-in .2s ease; }

.le-field-row { display: grid; grid-template-columns: 1fr 1fr; gap: .65rem; }
.le-label { font-size: .75rem; color: var(--text-muted); margin-bottom: .3rem; display: block; }

.le-input-wrap { margin-bottom: .65rem; }

/* Error / success */
.le-form-error {
    background: rgba(220,38,38,.15);
    border: 1px solid rgba(220,38,38,.3);
    border-radius: 8px; padding: .65rem .85rem;
    font-size: .8rem; color: #FCA5A5;
    margin-bottom: .75rem; display: none;
}
.le-form-error.show { display: block; }
.le-form-success {
    background: rgba(22,163,74,.15);
    border: 1px solid rgba(22,163,74,.3);
    border-radius: 8px; padding: .65rem .85rem;
    font-size: .8rem; color: #86EFAC;
    margin-bottom: .75rem; display: none;
}
.le-form-success.show { display: block; }

/* UNILIS login btn */
.le-btn-unilis {
    width: 100%; padding: .85rem;
    background: var(--glass);
    border: 1px solid var(--glass-border);
    border-radius: 10px;
    color: #fff; font-size: .88rem; font-weight: 600;
    cursor: pointer; display: flex;
    align-items: center; justify-content: center; gap: .65rem;
    transition: var(--trans); margin-bottom: .75rem;
}
.le-btn-unilis:hover { background: var(--glass-hover); border-color: rgba(255,255,255,.25); }
.le-unilis-badge {
    background: linear-gradient(135deg, var(--le-green), var(--le-amber));
    border-radius: 6px; padding: 2px 8px;
    font-size: .7rem; font-weight: 800; color: #fff; letter-spacing: .04em;
}

/* Spinner */
.le-spinner {
    width: 16px; height: 16px;
    border: 2px solid rgba(255,255,255,.3);
    border-top-color: #fff;
    border-radius: 50%;
    animation: le-spin .7s linear infinite;
    display: none;
}
.le-spinner.show { display: inline-block; }
@keyframes le-spin { to { transform: rotate(360deg); } }

/* ── Footer ───────────────────────────────────────────────── */
.le-home-footer {
    text-align: center;
    font-size: .75rem;
    color: var(--text-muted);
    margin-top: 2rem;
}
.le-home-footer a { color: rgba(249,168,37,.7); text-decoration: none; }
.le-home-footer a:hover { color: var(--le-amber); }
</style>

<div class="le-home-wrap">

    <!-- Hero -->
    <div class="le-hero">
        <div class="le-logo-ring">
            <span class="material-symbols-rounded">present_to_all</span>
        </div>
        <div class="le-live-pill">
            <span class="le-live-dot"></span>
            Live sessions active
        </div>
        <h1>Live Engagement</h1>
        <p>Interactive presentations, polls, and real-time quizzes — for anyone, anywhere.</p>
    </div>

    <!-- Two action cards -->
    <div class="le-cards">

        <!-- JOIN card -->
        <div class="le-card le-card-join" id="card-join" onclick="toggleJoin()">
            <div class="le-card-icon">
                <span class="material-symbols-rounded">login</span>
            </div>
            <h2>Join a session</h2>
            <p>Enter a code, paste a link, or scan a QR code</p>

            <div class="le-join-form" id="join-form" onclick="event.stopPropagation()">
                <!-- Code entry -->
                <input class="le-input le-code-input" id="join-code"
                       type="text" maxlength="8"
                       placeholder="XXXXXX"
                       oninput="this.value=this.value.toUpperCase().replace(/[^A-Z0-9]/g,'')"
                       autocomplete="off" />

                <button class="le-btn le-btn-teal" onclick="joinByCode()">
                    <span class="material-symbols-rounded">arrow_forward</span>
                    Join session
                </button>

                <div class="le-divider">or paste a link</div>

                <input class="le-input" id="join-link"
                       type="url" placeholder="https://..." />
                <button class="le-btn le-btn-ghost" onclick="joinByLink()">
                    <span class="material-symbols-rounded">link</span>
                    Join via link
                </button>

                <div class="le-divider">or scan QR code</div>
                <div class="le-qr-row">
                    <div class="le-qr-box">
                        <span class="material-symbols-rounded">qr_code_2</span>
                    </div>
                    <p class="le-qr-hint">
                        Point your phone camera at the QR code your presenter is showing on screen.
                        It will open the session directly.
                    </p>
                </div>
            </div>
        </div>

        <!-- CREATE card -->
        <div class="le-card le-card-create" onclick="openAuthModal()">
            <div class="le-card-icon">
                <span class="material-symbols-rounded">add_presentation</span>
            </div>
            <h2>Create & present</h2>
            <p>Build slides, run polls & quizzes live</p>
        </div>

    </div>

    <p class="le-home-footer">
        Part of <a href="/">UNILIS</a> &nbsp;·&nbsp;
        <a href="<?= le_page_url('join') ?>">Join a session</a>
    </p>
</div>

<!-- ══════════════════════════════════════════════════════════
     AUTH MODAL
══════════════════════════════════════════════════════════ -->
<div class="le-modal-overlay" id="auth-modal" onclick="handleOverlayClick(event)">
    <div class="le-modal">

        <button class="le-modal-close" onclick="closeAuthModal()" aria-label="Close">
            <span class="material-symbols-rounded">close</span>
        </button>

        <div class="le-modal-logo">
            <span class="material-symbols-rounded">present_to_all</span>
        </div>
        <h2>Get started</h2>
        <p class="le-modal-sub">Sign in to create and present live sessions</p>

        <!-- Tabs: Sign up / Log in -->
        <div class="le-modal-tabs">
            <button class="le-modal-tab active" onclick="switchModalTab('signup', this)">Sign up</button>
            <button class="le-modal-tab"        onclick="switchModalTab('login',  this)">Log in</button>
        </div>

        <!-- ── SIGN UP PANEL ── -->
        <div class="le-modal-panel active" id="panel-signup">
            <div class="le-form-error"   id="signup-error"></div>
            <div class="le-form-success" id="signup-success"></div>

            <div class="le-field-row">
                <div class="le-input-wrap">
                    <label class="le-label">Full name</label>
                    <input class="le-input" id="su-name" type="text" placeholder="Jane Doe" autocomplete="name" />
                </div>
                <div class="le-input-wrap">
                    <label class="le-label">Role / title</label>
                    <input class="le-input" id="su-role" type="text" placeholder="Lecturer" autocomplete="organization-title" />
                </div>
            </div>

            <div class="le-input-wrap">
                <label class="le-label">Organisation</label>
                <input class="le-input" id="su-org" type="text" placeholder="Your university or company" autocomplete="organization" />
            </div>

            <div class="le-input-wrap">
                <label class="le-label">Email address</label>
                <input class="le-input" id="su-email" type="email" placeholder="you@example.com" autocomplete="email" />
            </div>

            <div class="le-input-wrap">
                <label class="le-label">Password</label>
                <input class="le-input" id="su-password" type="password" placeholder="At least 8 characters" autocomplete="new-password" />
            </div>

            <button class="le-btn le-btn-amber" id="su-btn" onclick="submitSignup()">
                <span class="le-spinner" id="su-spinner"></span>
                <span class="material-symbols-rounded" id="su-icon">person_add</span>
                Create account
            </button>

            <p style="text-align:center;margin:.75rem 0;color:var(--text-muted);font-size:.75rem;">already have a UNILIS account?</p>
            <button class="le-btn-unilis" onclick="goUnilisLogin()">
                <span class="le-unilis-badge">UNILIS</span>
                Sign in with UNILIS
            </button>
        </div>

        <!-- ── LOG IN PANEL ── -->
        <div class="le-modal-panel" id="panel-login">
            <div class="le-form-error"   id="login-error"></div>
            <div class="le-form-success" id="login-success"></div>

            <div class="le-input-wrap">
                <label class="le-label">Email address</label>
                <input class="le-input" id="li-email" type="email" placeholder="you@example.com" autocomplete="email" />
            </div>

            <div class="le-input-wrap">
                <label class="le-label">Password</label>
                <input class="le-input" id="li-password" type="password" placeholder="Your password" autocomplete="current-password" />
            </div>

            <button class="le-btn le-btn-amber" id="li-btn" onclick="submitLogin()">
                <span class="le-spinner" id="li-spinner"></span>
                <span class="material-symbols-rounded" id="li-icon">login</span>
                Log in
            </button>

            <p style="text-align:center;margin:.75rem 0;color:var(--text-muted);font-size:.75rem;">or use your UNILIS account</p>
            <button class="le-btn-unilis" onclick="goUnilisLogin()">
                <span class="le-unilis-badge">UNILIS</span>
                Sign in with UNILIS
            </button>
        </div>

    </div>
</div>

<script>
// ── Join card toggle ────────────────────────────────────────
function toggleJoin() {
    const form = document.getElementById('join-form');
    const card = document.getElementById('card-join');
    const open = form.classList.toggle('open');
    if (open) setTimeout(() => document.getElementById('join-code').focus(), 50);
}

function joinByCode() {
    const code = document.getElementById('join-code').value.trim();
    if (code.length < 4) { document.getElementById('join-code').focus(); return; }
    window.location.href = '<?= le_page_url('join') ?>&code=' + encodeURIComponent(code);
}

function joinByLink() {
    const link = document.getElementById('join-link').value.trim();
    if (!link) { document.getElementById('join-link').focus(); return; }
    window.location.href = link;
}

// ── Auth modal ──────────────────────────────────────────────
const modal = document.getElementById('auth-modal');

function openAuthModal()  { modal.classList.add('open'); document.body.style.overflow = 'hidden'; }
function closeAuthModal() { modal.classList.remove('open'); document.body.style.overflow = ''; }

function handleOverlayClick(e) {
    if (e.target === modal) closeAuthModal();
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeAuthModal();
});

function switchModalTab(id, el) {
    document.querySelectorAll('.le-modal-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.le-modal-panel').forEach(p => p.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('panel-' + id).classList.add('active');
}

function goUnilisLogin() {
    window.location.href = '/login.php';
}

// ── Sign up ─────────────────────────────────────────────────
async function submitSignup() {
    const errBox = document.getElementById('signup-error');
    const sucBox = document.getElementById('signup-success');
    const btn    = document.getElementById('su-btn');
    const spin   = document.getElementById('su-spinner');
    const icon   = document.getElementById('su-icon');

    errBox.classList.remove('show');
    sucBox.classList.remove('show');

    const body = {
        action:       'signup',
        name:         document.getElementById('su-name').value,
        email:        document.getElementById('su-email').value,
        organisation: document.getElementById('su-org').value,
        role:         document.getElementById('su-role').value,
        password:     document.getElementById('su-password').value,
    };

    btn.disabled = true; spin.classList.add('show'); icon.style.display = 'none';

    try {
        const res  = await fetch('<?= le_module_url('api/guest_auth.php') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(body)
        });
        const data = await res.json();

        if (data.success) {
            sucBox.textContent = 'Account created! Redirecting…';
            sucBox.classList.add('show');
            setTimeout(() => window.location.href = data.redirect, 800);
        } else {
            errBox.innerHTML = data.errors.join('<br>');
            errBox.classList.add('show');
        }
    } catch {
        errBox.textContent = 'Network error. Please try again.';
        errBox.classList.add('show');
    } finally {
        btn.disabled = false; spin.classList.remove('show'); icon.style.display = '';
    }
}

// ── Log in ───────────────────────────────────────────────────
async function submitLogin() {
    const errBox = document.getElementById('login-error');
    const sucBox = document.getElementById('login-success');
    const btn    = document.getElementById('li-btn');
    const spin   = document.getElementById('li-spinner');
    const icon   = document.getElementById('li-icon');

    errBox.classList.remove('show');
    sucBox.classList.remove('show');

    const body = {
        action:   'login',
        email:    document.getElementById('li-email').value,
        password: document.getElementById('li-password').value,
    };

    btn.disabled = true; spin.classList.add('show'); icon.style.display = 'none';

    try {
        const res  = await fetch('<?= le_module_url('api/guest_auth.php') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(body)
        });
        const data = await res.json();

        if (data.success) {
            sucBox.textContent = 'Logged in! Redirecting…';
            sucBox.classList.add('show');
            setTimeout(() => window.location.href = data.redirect, 800);
        } else {
            errBox.innerHTML = data.errors.join('<br>');
            errBox.classList.add('show');
        }
    } catch {
        errBox.textContent = 'Network error. Please try again.';
        errBox.classList.add('show');
    } finally {
        btn.disabled = false; spin.classList.remove('show'); icon.style.display = '';
    }
}

// ── Enter key shortcuts ──────────────────────────────────────
document.getElementById('join-code').addEventListener('keydown', e => {
    if (e.key === 'Enter') joinByCode();
});

// Auto-open if redirected here with ?auth=1
<?php if ($openAuthModal): ?>
openAuthModal();
 <?php endif; ?>
</script>

<?php Layout::end(); ?>
