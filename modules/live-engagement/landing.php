<?php
/**
 * Live Engagement — Pure Landing Page (No DB required)
 * 
 * This page loads the landing UI without touching the database.
 * It is safe to open even when the database is down or tables
 * are missing. The auth modal and "Join" functionality still
 * work through JavaScript API calls.
 */
session_start();

// Module paths (hardcoded so we don't need bootstrap.php)
define('LE_MODULE_URL', 'modules/live-engagement');

// Store redirect for UNILIS login flow
$_SESSION['le_login_redirect'] = LE_MODULE_URL . '/index.php?page=dashboard';

$openAuthModal = !empty($_GET['auth']);
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Engagement | UNILIS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <meta name="csrf-token" content="<?= $_SESSION['le_csrf_token'] ?? bin2hex(random_bytes(32)) ?>">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
    --le-green: #1B5E20; --le-green-mid: #2E7D32; --le-green-light: #43A047;
    --le-amber: #F9A825; --le-orange: #EF6C00;
    --le-bg: linear-gradient(135deg, #0d1f0f 0%, #1a3a1e 50%, #0f2415 100%);
    --glass: rgba(255,255,255,0.07); --glass-border: rgba(255,255,255,0.13);
    --glass-hover: rgba(255,255,255,0.12);
    --text-bright: #f0f7f0; --text-muted: rgba(240,247,240,0.6);
    --card-radius: 18px; --trans: all 0.25s cubic-bezier(.4,0,.2,1);
}
body {
    font-family: 'Inter', sans-serif;
    background: var(--le-bg); min-height: 100vh;
    color: var(--text-bright); overflow-x: hidden;
}
.wrap { max-width: 560px; margin: 0 auto; padding: 0 1.25rem 4rem; }
.hero { padding: 3rem 0 2rem; text-align: center; position: relative; }
.hero::before { content: ''; position: absolute; inset: 0; background: radial-gradient(ellipse 70% 50% at 50% 0%, rgba(249,168,37,.13) 0%, transparent 70%); pointer-events: none; }
.logo-ring { width: 72px; height: 72px; border-radius: 50%; background: linear-gradient(135deg, var(--le-green), var(--le-amber)); display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem; box-shadow: 0 0 40px rgba(249,168,37,.2); }
.logo-ring .material-symbols-rounded { font-size: 34px; color: #fff; }
.live-pill { display: inline-flex; align-items: center; gap: 6px; background: rgba(239,108,0,.16); border: 1px solid rgba(239,108,0,.32); border-radius: 20px; padding: 4px 14px; font-size: 12px; font-weight: 500; color: #FFB74D; margin-bottom: 1rem; }
.live-dot { width: 7px; height: 7px; border-radius: 50%; background: #EF6C00; animation: pulse 1.5s ease-in-out infinite; }
@keyframes pulse { 0%,100% { opacity:1; transform:scale(1); } 50% { opacity:.45; transform:scale(.75); } }
.hero h1 { font-size: 2.1rem; font-weight: 800; color: #fff; line-height: 1.2; margin-bottom: .5rem; }
.hero p { font-size: .95rem; color: var(--text-muted); max-width: 400px; margin: 0 auto 2rem; line-height: 1.65; }
.cards { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 2rem; }
.card { background: var(--glass); border: 1px solid var(--glass-border); border-radius: var(--card-radius); padding: 1.75rem 1.25rem 1.5rem; text-align: center; cursor: pointer; transition: var(--trans); position: relative; overflow: hidden; }
.card:hover { transform: translateY(-3px); border-color: rgba(255,255,255,.22); background: rgba(29,158,117,.08); }
.card-create:hover { background: rgba(249,168,37,.06); }
.card-icon { width: 52px; height: 52px; border-radius: 14px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; background: rgba(29,158,117,.2); border: 1px solid rgba(29,158,117,.35); }
.card-create .card-icon { background: rgba(249,168,37,.18); border: 1px solid rgba(249,168,37,.35); }
.card-icon .material-symbols-rounded { color: #4ECBA1; font-size: 26px; }
.card-create .card-icon .material-symbols-rounded { color: var(--le-amber); }
.card h2 { font-size: 1.05rem; font-weight: 700; color: #fff; margin-bottom: .4rem; }
.card p { font-size: .8rem; color: var(--text-muted); line-height: 1.5; }
.join-form { display: none; margin-top: 1.25rem; text-align: left; }
.join-form.open { display: block; animation: fade-in .2s ease; }
@keyframes fade-in { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:translateY(0); } }
.input { width: 100%; padding: .75rem 1rem; background: rgba(0,0,0,.3); border: 1px solid var(--glass-border); border-radius: 10px; color: #fff; font-size: .9rem; outline: none; transition: var(--trans); margin-bottom: .65rem; }
.input:focus { border-color: rgba(249,168,37,.55); box-shadow: 0 0 0 3px rgba(249,168,37,.1); }
.input::placeholder { color: rgba(255,255,255,.28); }
.code-input { font-size: 1.5rem; font-weight: 700; text-align: center; letter-spacing: .3em; font-family: 'Courier New', monospace; text-transform: uppercase; }
.divider { display: flex; align-items: center; gap: .75rem; margin: .75rem 0; color: var(--text-muted); font-size: .75rem; }
.divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: var(--glass-border); }
.btn { width: 100%; padding: .8rem; border: none; border-radius: 10px; font-size: .88rem; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: .5rem; transition: var(--trans); }
.btn .material-symbols-rounded { font-size: 18px; }
.btn-teal { background: linear-gradient(135deg, #1D9E75, #0F6E56); color: #fff; }
.btn-teal:hover { filter: brightness(1.1); transform: translateY(-1px); box-shadow: 0 6px 20px rgba(29,158,117,.3); }
.btn-amber { background: linear-gradient(135deg, var(--le-amber), var(--le-orange)); color: #fff; }
.btn-amber:hover { filter: brightness(1.1); transform: translateY(-1px); box-shadow: 0 6px 20px rgba(249,168,37,.3); }
.btn-ghost { background: var(--glass); border: 1px solid var(--glass-border); color: var(--text-bright); margin-top: .5rem; }
.btn-ghost:hover { background: var(--glass-hover); }
.qr-row { display: flex; gap: .65rem; margin-top: .75rem; }
.qr-box { flex: 0 0 auto; width: 80px; height: 80px; background: #fff; border-radius: 10px; display: flex; align-items: center; justify-content: center; }
.qr-box .material-symbols-rounded { font-size: 52px; color: var(--le-green); }
.qr-hint { font-size: .75rem; color: var(--text-muted); line-height: 1.5; padding-top: .2rem; }
.modal-overlay { display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,.65); align-items: center; justify-content: center; padding: 1rem; backdrop-filter: blur(4px); }
.modal-overlay.open { display: flex; animation: fade-in .2s ease; }
.modal { background: #0f2415; border: 1px solid var(--glass-border); border-radius: 22px; padding: 2rem; width: 100%; max-width: 420px; position: relative; box-shadow: 0 24px 60px rgba(0,0,0,.5); animation: slide-up .25s ease; }
@keyframes slide-up { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }
.modal-close { position: absolute; top: 1rem; right: 1rem; background: var(--glass); border: 1px solid var(--glass-border); border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--text-muted); transition: var(--trans); }
.modal-close:hover { background: var(--glass-hover); color: #fff; }
.modal-close .material-symbols-rounded { font-size: 18px; }
.modal-logo { width: 48px; height: 48px; border-radius: 12px; background: linear-gradient(135deg, var(--le-green), var(--le-amber)); display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; }
.modal-logo .material-symbols-rounded { font-size: 24px; color: #fff; }
.modal h2 { font-size: 1.2rem; font-weight: 700; color: #fff; text-align: center; margin-bottom: .3rem; }
.modal-sub { font-size: .82rem; color: var(--text-muted); text-align: center; margin-bottom: 1.5rem; }
.modal-tabs { display: flex; gap: 4px; background: rgba(0,0,0,.3); border-radius: 10px; padding: 4px; margin-bottom: 1.25rem; }
.modal-tab { flex: 1; padding: .55rem; border: none; background: transparent; color: var(--text-muted); font-size: .82rem; font-weight: 500; border-radius: 7px; cursor: pointer; transition: var(--trans); }
.modal-tab.active { background: var(--le-green-mid); color: #fff; }
.modal-panel { display: none; }
.modal-panel.active { display: block; animation: fade-in .2s ease; }
.field-row { display: grid; grid-template-columns: 1fr 1fr; gap: .65rem; }
.label { font-size: .75rem; color: var(--text-muted); margin-bottom: .3rem; display: block; }
.input-wrap { margin-bottom: .65rem; }
.form-error { background: rgba(220,38,38,.15); border: 1px solid rgba(220,38,38,.3); border-radius: 8px; padding: .65rem .85rem; font-size: .8rem; color: #FCA5A5; margin-bottom: .75rem; display: none; }
.form-error.show { display: block; }
.form-success { background: rgba(22,163,74,.15); border: 1px solid rgba(22,163,74,.3); border-radius: 8px; padding: .65rem .85rem; font-size: .8rem; color: #86EFAC; margin-bottom: .75rem; display: none; }
.form-success.show { display: block; }
.btn-unilis { width: 100%; padding: .85rem; background: var(--glass); border: 1px solid var(--glass-border); border-radius: 10px; color: #fff; font-size: .88rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: .65rem; transition: var(--trans); margin-bottom: .75rem; }
.btn-unilis:hover { background: var(--glass-hover); border-color: rgba(255,255,255,.25); }
.unilis-badge { background: linear-gradient(135deg, var(--le-green), var(--le-amber)); border-radius: 6px; padding: 2px 8px; font-size: .7rem; font-weight: 800; color: #fff; letter-spacing: .04em; }
.spinner { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,.3); border-top-color: #fff; border-radius: 50%; animation: spin .7s linear infinite; display: none; }
.spinner.show { display: inline-block; }
@keyframes spin { to { transform: rotate(360deg); } }
.footer { text-align: center; font-size: .75rem; color: var(--text-muted); margin-top: 2rem; }
.footer a { color: rgba(249,168,37,.7); text-decoration: none; }
.footer a:hover { color: var(--le-amber); }
</style>
</head>
<body>

<div class="wrap">
    <div class="hero">
        <div class="logo-ring"><span class="material-symbols-rounded">present_to_all</span></div>
        <div class="live-pill"><span class="live-dot"></span> Live sessions active</div>
        <h1>Live Engagement</h1>
        <p>Interactive presentations, polls, and real-time quizzes — for anyone, anywhere.</p>
    </div>
    <div class="cards">
        <div class="card" id="card-join" onclick="toggleJoin()">
            <div class="card-icon"><span class="material-symbols-rounded">login</span></div>
            <h2>Join a session</h2>
            <p>Enter a code, paste a link, or scan a QR code</p>
            <div class="join-form" id="join-form" onclick="event.stopPropagation()">
                <input class="input code-input" id="join-code" type="text" maxlength="8" placeholder="XXXXXX" oninput="this.value=this.value.toUpperCase().replace(/[^A-Z0-9]/g,'')" autocomplete="off" />
                <button class="btn btn-teal" onclick="joinByCode()"><span class="material-symbols-rounded">arrow_forward</span> Join session</button>
                <div class="divider">or paste a link</div>
                <input class="input" id="join-link" type="url" placeholder="https://..." />
                <button class="btn btn-ghost" onclick="joinByLink()"><span class="material-symbols-rounded">link</span> Join via link</button>
                <div class="divider">or scan QR code</div>
                <div class="qr-row">
                    <div class="qr-box"><span class="material-symbols-rounded">qr_code_2</span></div>
                    <p class="qr-hint">Point your phone camera at the QR code your presenter is showing on screen. It will open the session directly.</p>
                </div>
            </div>
        </div>
        <div class="card card-create" onclick="openAuthModal()">
            <div class="card-icon"><span class="material-symbols-rounded">add_presentation</span></div>
            <h2>Create & present</h2>
            <p>Build slides, run polls & quizzes live</p>
        </div>
    </div>
    <p class="footer">Part of <a href="/">UNILIS</a> &nbsp;·&nbsp; <a href="<?= htmlspecialchars(LE_MODULE_URL . '/index.php?page=join') ?>">Join a session</a></p>
</div>

<!-- Auth Modal -->
<div class="modal-overlay" id="auth-modal" onclick="if(event.target===this)closeAuthModal()">
    <div class="modal">
        <button class="modal-close" onclick="closeAuthModal()"><span class="material-symbols-rounded">close</span></button>
        <div class="modal-logo"><span class="material-symbols-rounded">present_to_all</span></div>
        <h2>Get started</h2>
        <p class="modal-sub">Sign in to create and present live sessions</p>
        <div class="modal-tabs">
            <button class="modal-tab active" onclick="switchTab('signup',this)">Sign up</button>
            <button class="modal-tab" onclick="switchTab('login',this)">Log in</button>
        </div>
        <div class="modal-panel active" id="panel-signup">
            <div class="form-error" id="signup-error"></div>
            <div class="form-success" id="signup-success"></div>
            <div class="field-row">
                <div class="input-wrap"><label class="label">Full name</label><input class="input" id="su-name" type="text" placeholder="Jane Doe" autocomplete="name" /></div>
                <div class="input-wrap"><label class="label">Role / title</label><input class="input" id="su-role" type="text" placeholder="Lecturer" autocomplete="organization-title" /></div>
            </div>
            <div class="input-wrap"><label class="label">Organisation</label><input class="input" id="su-org" type="text" placeholder="Your university or company" autocomplete="organization" /></div>
            <div class="input-wrap"><label class="label">Email address</label><input class="input" id="su-email" type="email" placeholder="you@example.com" autocomplete="email" /></div>
            <div class="input-wrap"><label class="label">Password</label><input class="input" id="su-password" type="password" placeholder="At least 8 characters" autocomplete="new-password" /></div>
            <button class="btn btn-amber" id="su-btn" onclick="submitSignup()"><span class="spinner" id="su-spinner"></span><span class="material-symbols-rounded" id="su-icon">person_add</span> Create account</button>
            <p style="text-align:center;margin:.75rem 0;color:var(--text-muted);font-size:.75rem;">already have a UNILIS account?</p>
            <button class="btn-unilis" onclick="goUnilisLogin()"><span class="unilis-badge">UNILIS</span> Sign in with UNILIS</button>
        </div>
        <div class="modal-panel" id="panel-login">
            <div class="form-error" id="login-error"></div>
            <div class="form-success" id="login-success"></div>
            <div class="input-wrap"><label class="label">Email address</label><input class="input" id="li-email" type="email" placeholder="you@example.com" autocomplete="email" /></div>
            <div class="input-wrap"><label class="label">Password</label><input class="input" id="li-password" type="password" placeholder="Your password" autocomplete="current-password" /></div>
            <button class="btn btn-amber" id="li-btn" onclick="submitLogin()"><span class="spinner" id="li-spinner"></span><span class="material-symbols-rounded" id="li-icon">login</span> Log in</button>
            <p style="text-align:center;margin:.75rem 0;color:var(--text-muted);font-size:.75rem;">or use your UNILIS account</p>
            <button class="btn-unilis" onclick="goUnilisLogin()"><span class="unilis-badge">UNILIS</span> Sign in with UNILIS</button>
        </div>
    </div>
</div>

<script>
const BASE = window.location.origin + '/modules/live-engagement';

function toggleJoin() { const f=document.getElementById('join-form'),o=f.classList.toggle('open'); if(o)setTimeout(()=>document.getElementById('join-code').focus(),50); }
function joinByCode() { const c=document.getElementById('join-code').value.trim(); if(c.length<4)return; window.location.href=BASE+'/index.php?page=join&code='+encodeURIComponent(c); }
function joinByLink() { const l=document.getElementById('join-link').value.trim(); if(!l)return; window.location.href=l; }

const modal=document.getElementById('auth-modal');
function openAuthModal() { modal.classList.add('open'); document.body.style.overflow='hidden'; }
function closeAuthModal() { modal.classList.remove('open'); document.body.style.overflow=''; }
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeAuthModal();});

function switchTab(id,el) {
    document.querySelectorAll('.modal-tab').forEach(t=>t.classList.remove('active'));
    document.querySelectorAll('.modal-panel').forEach(p=>p.classList.remove('active'));
    el.classList.add('active'); document.getElementById('panel-'+id).classList.add('active');
}
function goUnilisLogin() {
    // Generate token and redirect to UNILIS login
    fetch(BASE+'/api/guest_auth.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'generate_unilis_token' })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.href = data.login_url;
        } else {
            document.getElementById('signup-error').textContent = 'Failed to generate auth token';
            document.getElementById('signup-error').classList.add('show');
        }
    })
    .catch(() => {
        document.getElementById('signup-error').textContent = 'Network error';
        document.getElementById('signup-error').classList.add('show');
    });
}

async function submitSignup() {
    const e=document.getElementById('signup-error'),s=document.getElementById('signup-success'),b=document.getElementById('su-btn'),sp=document.getElementById('su-spinner'),ic=document.getElementById('su-icon');
    e.classList.remove('show'); s.classList.remove('show');
    const body={action:'signup',name:document.getElementById('su-name').value,email:document.getElementById('su-email').value,organisation:document.getElementById('su-org').value,role:document.getElementById('su-role').value,password:document.getElementById('su-password').value};
    b.disabled=true;sp.classList.add('show');ic.style.display='none';
    try { const r=await fetch(BASE+'/api/guest_auth.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(body)});const d=await r.json();if(d.success){s.textContent='Account created! Redirecting...';s.classList.add('show');setTimeout(()=>window.location.href=d.redirect,800);}else{e.innerHTML=d.errors.join('<br>');e.classList.add('show');}}catch{e.textContent='Network error.';e.classList.add('show');}finally{b.disabled=false;sp.classList.remove('show');ic.style.display='';}
}
async function submitLogin() {
    const e=document.getElementById('login-error'),s=document.getElementById('login-success'),b=document.getElementById('li-btn'),sp=document.getElementById('li-spinner'),ic=document.getElementById('li-icon');
    e.classList.remove('show');s.classList.remove('show');
    const body={action:'login',email:document.getElementById('li-email').value,password:document.getElementById('li-password').value};
    b.disabled=true;sp.classList.add('show');ic.style.display='none';
    try {const r=await fetch(BASE+'/api/guest_auth.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(body)});const d=await r.json();if(d.success){s.textContent='Logged in!';s.classList.add('show');setTimeout(()=>window.location.href=d.redirect,800);}else{e.innerHTML=d.errors.join('<br>');e.classList.add('show');}}catch{e.textContent='Network error.';e.classList.add('show');}finally{b.disabled=false;sp.classList.remove('show');ic.style.display='';}
}
document.getElementById('join-code').addEventListener('keydown',e=>{if(e.key==='Enter')joinByCode();});
<?php if ($openAuthModal): ?>openAuthModal();<?php endif; ?>
</script>
</body>
</html>