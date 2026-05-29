<?php
// Shared take practical verification modal for student pages.
// Supports: QR Code, RFID, Confirmation Code, Biometric
?>
<div id="takePracticalModal" class="modal-overlay hidden">
    <div class="modal-backdrop" onclick="closeTakePracticalModal()"></div>
    <div class="modal-card">
        <div class="modal-card-header">
            <div>
                <h2>Authenticate to Start Practical</h2>
                <p>Choose a verification method and confirm your identity before proceeding.</p>
            </div>
            <button class="modal-close" onclick="closeTakePracticalModal()" aria-label="Close">&#x2715;</button>
        </div>

        <div class="modal-body">
            <!-- Method selector -->
            <div class="field-group">
                <label class="field-label">Verification method</label>
                <div class="verification-options">
                    <label class="vopt" data-method="qr">
                        <input type="radio" name="verificationMethod" value="qr" checked onchange="switchMethod('qr')">
                        <span class="vopt-icon">&#x1F4F1;</span>
                        <span>QR Code</span>
                    </label>
                    <label class="vopt" data-method="rfid">
                        <input type="radio" name="verificationMethod" value="rfid" onchange="switchMethod('rfid')">
                        <span class="vopt-icon">&#x1F4B3;</span>
                        <span>Scan Card (RFID)</span>
                    </label>
                    <label class="vopt" data-method="code">
                        <input type="radio" name="verificationMethod" value="code" onchange="switchMethod('code')">
                        <span class="vopt-icon">&#x1F511;</span>
                        <span>Confirmation Code</span>
                    </label>
                    <label class="vopt" data-method="biometric">
                        <input type="radio" name="verificationMethod" value="biometric" onchange="switchMethod('biometric')">
                        <span class="vopt-icon">&#x1F91A;</span>
                        <span>Biometric</span>
                    </label>
                </div>
            </div>

            <!-- QR Panel -->
            <div id="panel-qr" class="method-panel">
                <div class="qr-card">
                    <div id="qrDisplay" class="qr-display">
                        <span class="qr-placeholder">Generating QR code&hellip;</span>
                    </div>
                    <div class="qr-meta">
                        <p class="input-note">Scan this QR code with your phone to confirm attendance.</p>
                        <div id="qrScanStatus" class="status-badge status-waiting">Waiting for scan&hellip;</div>
                        <button type="button" class="btn btn-outline btn-sm" onclick="startAttendanceQR()">&#x21BB; Refresh QR</button>
                    </div>
                </div>
            </div>

            <!-- RFID Panel -->
            <div id="panel-rfid" class="method-panel hidden">
                <label class="field-label">RFID Card UID</label>
                <input type="text" id="rfidInput" class="form-control" placeholder="Tap card or enter UID&hellip;" autocomplete="off" />
                <p class="input-note" style="margin-top:.5rem;">Tap your student card on the reader, or type the UID manually.</p>
            </div>

            <!-- Confirmation Code Panel -->
            <div id="panel-code" class="method-panel hidden">
                <label class="field-label">Confirmation Code</label>
                <input type="text" id="codeInput" class="form-control" placeholder="Enter code given by lecturer&hellip;" autocomplete="off" maxlength="20" style="letter-spacing:.2em;text-transform:uppercase;" />
                <p class="input-note" style="margin-top:.5rem;">Enter the session code displayed by your lecturer.</p>
            </div>

            <!-- Biometric Panel -->
            <div id="panel-biometric" class="method-panel hidden">
                <div class="biometric-area" id="biometricArea">
                    <div class="bio-icon">&#x1F91A;</div>
                    <p id="bioStatusText">Click <strong>Authenticate</strong> to begin biometric verification.</p>
                    <div id="bioProgress" class="bio-progress hidden"></div>
                </div>
            </div>

            <!-- Global status -->
            <div id="takePracticalStatus" class="status-panel">Choose a method to continue.</div>
        </div>

        <div class="modal-footer">
            <button id="verifyMethodBtn" class="btn btn-primary" onclick="runVerification()">Verify</button>
            <button id="proceedPracticalBtn" class="btn btn-success" onclick="goToPracticalSession()" disabled>
                &#x2713; Proceed to Practical
            </button>
            <button class="btn btn-secondary" onclick="closeTakePracticalModal()">Cancel</button>
        </div>
    </div>
</div>

<style>
.modal-overlay          { position:fixed;inset:0;z-index:1100;display:flex;align-items:center;justify-content:center;padding:1rem; }
.modal-overlay.hidden   { display:none !important; }
.modal-backdrop         { position:absolute;inset:0;background:rgba(0,0,0,.52);backdrop-filter:blur(2px); }
.modal-card             { position:relative;z-index:1;width:min(100%,560px);background:#fff;border-radius:18px;box-shadow:0 28px 72px rgba(0,0,0,.22);overflow:hidden;max-height:90vh;overflow-y:auto; }
.modal-card-header      { padding:1.4rem 1.6rem .8rem;display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;border-bottom:1px solid #f0f0f0; }
.modal-card-header h2   { margin:0 0 .2rem;font-size:1.2rem;font-weight:700;color:#0f172a; }
.modal-card-header p    { margin:0;color:#64748b;font-size:.9rem;line-height:1.45; }
.modal-close            { border:none;background:transparent;font-size:1.4rem;line-height:1;cursor:pointer;color:#94a3b8;padding:.2rem .4rem;border-radius:6px;transition:background .15s; }
.modal-close:hover      { background:#f1f5f9;color:#334155; }
.modal-body             { padding:1.2rem 1.6rem 1rem; }
.field-group            { margin-bottom:1.1rem; }
.field-label            { display:block;margin-bottom:.55rem;font-weight:600;font-size:.9rem;color:#1e293b; }
.verification-options   { display:grid;grid-template-columns:repeat(2,1fr);gap:.6rem; }
.vopt                   { display:flex;align-items:center;gap:.55rem;padding:.75rem 1rem;border:1.5px solid #e2e8f0;border-radius:12px;cursor:pointer;transition:border-color .18s,background .18s;font-size:.9rem;color:#334155; }
.vopt input             { display:none; }
.vopt-icon              { font-size:1.2rem;line-height:1; }
.vopt:hover             { border-color:#3b82f6;background:#f8faff; }
.vopt.active            { border-color:#2563eb;background:#eff6ff;color:#1d4ed8;font-weight:600; }
.method-panel           { margin-bottom:.75rem; }
.method-panel.hidden    { display:none; }
.qr-card        { display:grid;grid-template-columns:210px 1fr;gap:1rem;align-items:center;padding:1rem;border:1px solid #e2e8f0;border-radius:14px;background:#f8fafc; }
.qr-display     { display:grid;place-items:center;width:210px;min-height:210px;border-radius:16px;background:#fff;box-shadow:inset 0 0 0 1px #e2e8f0; }
.qr-placeholder { color:#94a3b8;text-align:center;font-size:.9rem;padding:1rem; }
.qr-meta        { display:flex;flex-direction:column;gap:.75rem; }
.status-badge   { padding:.45rem .8rem;border-radius:8px;font-size:.85rem;font-weight:500; }
.status-waiting { background:#fef9c3;color:#854d0e; }
.status-ok      { background:#dcfce7;color:#166534; }
.status-error   { background:#fee2e2;color:#991b1b; }
.biometric-area { display:flex;flex-direction:column;align-items:center;gap:.75rem;padding:1.5rem;border:1px dashed #cbd5e1;border-radius:14px;text-align:center; }
.bio-icon       { font-size:3rem;line-height:1; }
.bio-progress   { width:100%;height:6px;border-radius:3px;background:#e2e8f0;overflow:hidden; }
.bio-progress::after { content:'';display:block;height:100%;width:40%;background:#2563eb;animation:bio-pulse 1.2s ease-in-out infinite alternate; }
.bio-progress.hidden { display:none; }
@keyframes bio-pulse { from{transform:translateX(-100%)} to{transform:translateX(300%)} }
.form-control   { width:100%;box-sizing:border-box;padding:.85rem 1rem;border-radius:12px;border:1.5px solid #e2e8f0;font-size:.95rem;transition:border-color .18s;outline:none; }
.form-control:focus { border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.1); }
.input-note     { color:#64748b;font-size:.85rem;margin:0; }
.status-panel   { min-height:2.6rem;padding:.75rem 1rem;border-radius:12px;border:1px solid #e2e8f0;background:#f8fafc;color:#334155;font-size:.9rem;margin-top:.25rem;transition:color .2s,border-color .2s; }
.status-panel.is-error   { border-color:#fca5a5;background:#fff1f2;color:#b91c1c; }
.status-panel.is-success { border-color:#86efac;background:#f0fdf4;color:#166534; }
.modal-footer   { display:flex;flex-wrap:wrap;gap:.65rem;align-items:center;justify-content:flex-end;padding:.85rem 1.6rem 1.25rem;border-top:1px solid #f0f0f0; }
.btn            { border:none;border-radius:10px;padding:.8rem 1.35rem;cursor:pointer;font-size:.9rem;font-weight:500;transition:opacity .15s,transform .1s; }
.btn:active     { transform:scale(.97); }
.btn:disabled   { opacity:.45;cursor:not-allowed;transform:none; }
.btn-primary    { background:#2563eb;color:#fff; }
.btn-primary:hover:not(:disabled) { background:#1d4ed8; }
.btn-success    { background:#16a34a;color:#fff; }
.btn-success:hover:not(:disabled) { background:#15803d; }
.btn-secondary  { background:#f1f5f9;color:#334155;border:1px solid #e2e8f0; }
.btn-secondary:hover { background:#e2e8f0; }
.btn-outline    { background:transparent;border:1.5px solid #2563eb;color:#2563eb; }
.btn-outline:hover { background:#eff6ff; }
.btn-sm         { padding:.5rem .9rem;font-size:.85rem; }
@media(max-width:480px){
    .verification-options { grid-template-columns:1fr 1fr; }
    .qr-card { grid-template-columns:1fr; }
    .qr-display { width:100%;min-height:180px; }
}
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
(function () {
    const API = '<?= APP_URL ?>';
    let practicalId  = null;
    let activeMethod = 'qr';
    let verified     = false;
    let qrToken      = null;
    let qrPollTimer  = null;

    window.openTakePracticalModal = function (id) {
        practicalId  = id;
        activeMethod = 'qr';
        verified     = false;
        qrToken      = null;
        stopQrPoll();
        document.querySelector('input[name="verificationMethod"][value="qr"]').checked = true;
        document.getElementById('rfidInput').value = '';
        document.getElementById('codeInput').value = '';
        setStatus('Choose a method to continue.', '');
        document.getElementById('proceedPracticalBtn').disabled = true;
        document.getElementById('verifyMethodBtn').disabled = false;
        document.getElementById('takePracticalModal').classList.remove('hidden');
        activatePanel('qr');
    };

    window.closeTakePracticalModal = function () {
        stopQrPoll();
        document.getElementById('takePracticalModal').classList.add('hidden');
    };

    window.switchMethod = function (method) {
        stopQrPoll();
        activeMethod = method;
        ['qr','rfid','code','biometric'].forEach(m => {
            document.getElementById('panel-' + m).classList.toggle('hidden', m !== method);
        });
        document.querySelectorAll('.vopt').forEach(l => {
            l.classList.toggle('active', l.dataset.method === method);
        });
        verified = false;
        document.getElementById('proceedPracticalBtn').disabled = true;
        document.getElementById('verifyMethodBtn').disabled = false;
        setStatus('Choose a method to continue.', '');
        if (method === 'qr') {
            document.getElementById('verifyMethodBtn').textContent = '\u21BB Refresh QR';
            startAttendanceQR();
        } else if (method === 'biometric') {
            document.getElementById('verifyMethodBtn').textContent = 'Authenticate';
            document.getElementById('bioStatusText').innerHTML = 'Click <strong>Authenticate</strong> to begin biometric verification.';
            document.getElementById('bioProgress').classList.add('hidden');
        } else {
            document.getElementById('verifyMethodBtn').textContent = 'Verify';
        }
    };

    function activatePanel(method) {
        const radio = document.querySelector('input[name="verificationMethod"][value="' + method + '"]');
        if (radio) radio.checked = true;
        switchMethod(method);
    }

    window.runVerification = function () {
        if (!practicalId) { setStatus('Practical ID is missing.', 'error'); return; }
        if (activeMethod === 'qr') { startAttendanceQR(); return; }
        document.getElementById('verifyMethodBtn').disabled = true;
        setStatus('Verifying...', '');
        let p;
        if (activeMethod === 'rfid')           p = doRfid();
        else if (activeMethod === 'code')      p = doCode();
        else if (activeMethod === 'biometric') p = doBiometric();
        else { setStatus('Unknown method.', 'error'); return; }
        p.then(() => {
            verified = true;
            setStatus('Verification successful. You may proceed.', 'success');
            document.getElementById('proceedPracticalBtn').disabled = false;
        }).catch(err => {
            setStatus(err.message || 'Verification failed.', 'error');
            document.getElementById('verifyMethodBtn').disabled = false;
        });
    };

    window.startAttendanceQR = function () {
        if (!practicalId) { setStatus('Practical ID is missing.', 'error'); return; }
        stopQrPoll();
        setQrStatus('Generating QR code...', 'waiting');
        setStatus('Generating QR code...', '');
        document.getElementById('qrDisplay').innerHTML = '<span class="qr-placeholder">Generating...</span>';
        fetch(API + '/attendance-qr/attendanceGenerate?practical_id=' + encodeURIComponent(practicalId))
            .then(r => r.json())
            .then(data => {
                if (data.error) throw new Error(data.error);
                qrToken = data.token;
                renderQr(data.url);
                setQrStatus('Waiting for scan...', 'waiting');
                setStatus('Scan the QR code with your phone to confirm attendance.', '');
                qrPollTimer = setInterval(function(){ pollQr(qrToken); }, 2000);
            })
            .catch(err => {
                setStatus(err.message || 'Failed to generate QR code.', 'error');
                setQrStatus('Failed to generate QR.', 'error');
            });
    };

    function renderQr(url) {
        var el = document.getElementById('qrDisplay');
        el.innerHTML = '';
        new QRCode(el, { text: url, width: 190, height: 190, colorDark: '#0f172a', colorLight: '#ffffff' });
    }

    function pollQr(token) {
        if (!token) return;
        fetch(API + '/attendance-qr/attendancePoll?token=' + encodeURIComponent(token))
            .then(r => r.json())
            .then(data => {
                if (data.status === 'claimed') {
                    stopQrPoll();
                    verified = true;
                    var name = data.student_name ? ' for ' + data.student_name : '';
                    setQrStatus('Attendance confirmed' + name + '.', 'ok');
                    setStatus('Attendance confirmed' + name + '. Click Proceed to continue.', 'success');
                    document.getElementById('proceedPracticalBtn').disabled = false;
                    setTimeout(function() {
                        if (!document.getElementById('takePracticalModal').classList.contains('hidden') && activeMethod === 'qr') {
                            startAttendanceQR();
                        }
                    }, 2500);
                } else if (data.status === 'expired') {
                    stopQrPoll();
                    setQrStatus('QR expired. Refreshing...', 'waiting');
                    setTimeout(startAttendanceQR, 1000);
                }
            })
            .catch(function(){});
    }

    function stopQrPoll() {
        if (qrPollTimer) { clearInterval(qrPollTimer); qrPollTimer = null; }
    }

    function setQrStatus(msg, type) {
        var el = document.getElementById('qrScanStatus');
        if (!el) return;
        el.textContent = msg;
        el.className = 'status-badge status-' + (type || 'waiting');
    }

    function doRfid() {
        var uid = document.getElementById('rfidInput').value.trim();
        if (!uid) return Promise.reject(new Error('Please enter or scan your RFID card UID.'));
        return apiPost('/attendance-qr/verifyRFID', { uid: uid });
    }

    function doCode() {
        var code = document.getElementById('codeInput').value.trim().toUpperCase();
        if (!code) return Promise.reject(new Error('Please enter the confirmation code.'));
        return apiPost('/attendance-qr/verifyCode', { practical_id: practicalId, admin_code: code });
    }

    function doBiometric() {
        var progress = document.getElementById('bioProgress');
        var statusText = document.getElementById('bioStatusText');
        progress.classList.remove('hidden');
        statusText.innerHTML = 'Authenticating...';
        if (window.PublicKeyCredential) {
            return navigator.credentials.get({
                publicKey: {
                    challenge: crypto.getRandomValues(new Uint8Array(32)),
                    timeout: 60000,
                    userVerification: 'required'
                }
            }).then(function() {
                progress.classList.add('hidden');
                statusText.innerHTML = 'Biometric confirmed.';
                return { success: true };
            }).catch(function(err) {
                progress.classList.add('hidden');
                statusText.innerHTML = 'Biometric failed. Try another method.';
                throw new Error(err.message || 'Biometric authentication failed.');
            });
        } else {
            return new Promise(function(resolve) {
                setTimeout(function() {
                    progress.classList.add('hidden');
                    statusText.innerHTML = 'Biometric confirmed (simulated).';
                    resolve({ success: true });
                }, 1500);
            });
        }
    }

    window.goToPracticalSession = function () {
        if (!verified) { setStatus('Please complete verification before proceeding.', 'error'); return; }
        window.location.href = API + '/start-practical/' + practicalId;
    };

    function apiPost(path, body) {
        return fetch(API + path, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        }).then(function(r){ return r.json(); }).then(function(data) {
            if (!data.success && !data.student_id) {
                throw new Error(data.error || data.message || 'Verification failed.');
            }
            return data;
        });
    }

    function setStatus(msg, type) {
        var el = document.getElementById('takePracticalStatus');
        el.textContent = msg;
        el.className = 'status-panel' + (type === 'error' ? ' is-error' : type === 'success' ? ' is-success' : '');
    }
})();
</script>
