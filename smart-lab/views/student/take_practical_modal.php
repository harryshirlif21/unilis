<?php
// Shared take practical verification modal for student pages.
?>
<div id="takePracticalModal" class="modal-overlay hidden">
    <div class="modal-backdrop" onclick="closeTakePracticalModal()"></div>
    <div class="modal-card">
        <div class="modal-card-header">
            <div>
                <h2>Verify to Start Practical</h2>
                <p>Choose a verification method, confirm your identity, and mark attendance before proceeding.</p>
            </div>
            <button class="modal-close" onclick="closeTakePracticalModal()" aria-label="Close">×</button>
        </div>

        <div class="modal-body">
            <div class="field-group">
                <label class="field-label">Verification method</label>
                <div class="verification-options">
                    <label><input type="radio" name="verificationMethod" value="qr" checked onchange="updateVerificationMethod(event)"> QR Code</label>
                    <label><input type="radio" name="verificationMethod" value="fingerprint" onchange="updateVerificationMethod(event)"> Fingerprint</label>
                    <label><input type="radio" name="verificationMethod" value="rfid" onchange="updateVerificationMethod(event)"> RFID</label>
                    <label><input type="radio" name="verificationMethod" value="admin_code" onchange="updateVerificationMethod(event)"> Admin Code</label>
                </div>
            </div>

            <div id="verificationInputContainer" class="field-group">
                <label class="field-label">Verification input</label>
                <div id="verificationInputInner">
                    <div class="input-note">Scan your QR code, fingerprint, or enter RFID/admin code and click Verify.</div>
                    <input type="text" id="verificationInput" class="form-control" placeholder="Enter QR token / UID / admin code" />
                </div>
            </div>

            <div id="takePracticalStatus" class="status-panel">Verify to continue.</div>
        </div>

        <div class="modal-footer">
            <button id="verifyMethodBtn" class="btn btn-outline" onclick="runVerification()">Verify</button>
            <button id="proceedPracticalBtn" class="btn btn-primary" onclick="goToPracticalSession()" disabled>Proceed</button>
            <button class="btn btn-secondary" onclick="closeTakePracticalModal()">Close</button>
        </div>
    </div>
</div>

<style>
.modal-overlay.hidden { display: none; }
.modal-overlay { position: fixed; inset: 0; z-index: 1100; display: flex; align-items: center; justify-content: center; padding: 1rem; }
.modal-backdrop { position: absolute; inset: 0; background: rgba(0,0,0,.5); backdrop-filter: blur(1px); }
.modal-card { position: relative; z-index: 1; width: min(100%, 540px); background: #fff; border-radius: 16px; box-shadow: 0 24px 64px rgba(0,0,0,.18); overflow: hidden; }
.modal-card-header { padding: 1.35rem 1.5rem 0.75rem; display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; }
.modal-card-header h2 { margin: 0 0 .25rem; font-size: 1.25rem; }
.modal-card-header p { margin: 0; color: #555; line-height: 1.45; }
.modal-close { border: none; background: transparent; font-size: 1.5rem; line-height: 1; cursor: pointer; color: #444; }
.modal-body { padding: 0 1.5rem 1rem; }
.field-group { margin-bottom: 1rem; }
.field-label { display: block; margin-bottom: .5rem; font-weight: 600; color: #111827; }
.verification-options { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; }
.verification-options label { display: flex; align-items: center; gap: .5rem; padding: .75rem 1rem; border: 1px solid #d1d5db; border-radius: 12px; cursor: pointer; transition: border-color .2s, background .2s; }
.verification-options input { accent-color: #2563eb; }
.verification-options label:hover { border-color: #2563eb; background: #f8fafc; }
#verificationInputInner { display: flex; flex-direction: column; gap: .75rem; }
.input-note { color: #6b7280; font-size: .95rem; }
.form-control { width: 100%; padding: .85rem 1rem; border-radius: 12px; border: 1px solid #d1d5db; font-size: .95rem; }
.status-panel { min-height: 3rem; padding: .85rem 1rem; border-radius: 12px; border: 1px solid #d1d5db; background: #f8fafc; color: #111827; margin-top: .5rem; }
.modal-footer { display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; justify-content: flex-end; padding: 0 1.5rem 1.25rem; }
.btn { border: none; border-radius: 10px; padding: .9rem 1.4rem; cursor: pointer; font-size: .95rem; }
.btn-primary { background: #2563eb; color: white; }
.btn-secondary { background: #e5e7eb; color: #111827; }
.btn-outline { background: transparent; border: 1px solid #2563eb; color: #2563eb; }
.btn:disabled { opacity: .55; cursor: not-allowed; }
</style>

<script>
const takePracticalApiUrl = '<?= APP_URL ?>';
let selectedPracticalId = null;
let selectedVerificationMethod = 'qr';
let verifiedStudentId = null;
let attendanceMarked = false;

function openTakePracticalModal(practicalId) {
    selectedPracticalId = practicalId;
    selectedVerificationMethod = 'qr';
    verifiedStudentId = null;
    attendanceMarked = false;

    document.querySelector('input[name="verificationMethod"][value="qr"]').checked = true;
    document.getElementById('verificationInput').value = '';
    document.getElementById('verificationInput').placeholder = 'Enter QR token or scan QR data';
    document.getElementById('verificationInput').closest('.field-group').style.display = 'block';
    document.getElementById('takePracticalStatus').textContent = 'Verify to continue.';
    document.getElementById('takePracticalStatus').style.color = '#111827';
    document.getElementById('verifyMethodBtn').disabled = false;
    document.getElementById('proceedPracticalBtn').disabled = true;
    document.getElementById('takePracticalModal').classList.remove('hidden');
}

function closeTakePracticalModal() {
    document.getElementById('takePracticalModal').classList.add('hidden');
}

function updateVerificationMethod(event) {
    selectedVerificationMethod = event.target.value;
    const input = document.getElementById('verificationInput');
    switch (selectedVerificationMethod) {
        case 'qr':
            input.placeholder = 'Enter QR token or scan QR data';
            input.value = '';
            input.closest('.field-group').style.display = 'block';
            break;
        case 'fingerprint':
            input.placeholder = 'Enter fingerprint capture string';
            input.value = '';
            input.closest('.field-group').style.display = 'block';
            break;
        case 'rfid':
            input.placeholder = 'Enter RFID UID from Arduino';
            input.value = '';
            input.closest('.field-group').style.display = 'block';
            break;
        case 'admin_code':
            input.placeholder = 'Enter temporary admin code';
            input.value = '';
            input.closest('.field-group').style.display = 'block';
            break;
    }
    setStatus('Verify to continue.', false);
}

function runVerification() {
    if (!selectedPracticalId) {
        setStatus('Practical ID is missing.', true);
        return;
    }

    setStatus('Verifying identity...', false);
    document.getElementById('verifyMethodBtn').disabled = true;

    let verifyPromise;
    switch (selectedVerificationMethod) {
        case 'qr':
            verifyPromise = verifyQR();
            break;
        case 'fingerprint':
            verifyPromise = verifyFingerprint();
            break;
        case 'rfid':
            verifyPromise = verifyRFID();
            break;
        case 'admin_code':
            verifyPromise = verifyCode();
            break;
        default:
            verifyPromise = Promise.reject(new Error('Unsupported verification method'));
    }

    verifyPromise
        .then(data => {
            verifiedStudentId = data.student_id;
            setStatus('Identity verified. Marking attendance...', false);
            return markAttendance();
        })
        .then(() => {
            attendanceMarked = true;
            setStatus('Verification successful. Attendance marked.', false);
            document.getElementById('proceedPracticalBtn').disabled = false;
        })
        .catch(error => {
            setStatus(error.message || error, true);
            document.getElementById('verifyMethodBtn').disabled = false;
        });
}

function verifyQR() {
    const token = document.getElementById('verificationInput').value.trim();
    if (!token) {
        return Promise.reject(new Error('Please enter the QR token.'));
    }

    return fetch(`${takePracticalApiUrl}/api/verify/qr.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token })
    })
    .then(handleJsonResponse);
}

function verifyFingerprint() {
    const fingerprintData = document.getElementById('verificationInput').value.trim();
    if (!fingerprintData) {
        return Promise.reject(new Error('Please enter fingerprint data.'));
    }

    return fetch(`${takePracticalApiUrl}/api/verify/fingerprint.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ fingerprint_data: fingerprintData })
    })
    .then(handleJsonResponse);
}

function verifyRFID() {
    const uid = document.getElementById('verificationInput').value.trim();
    if (!uid) {
        return Promise.reject(new Error('Please enter the RFID UID.'));
    }

    return fetch(`${takePracticalApiUrl}/api/verify/rfid.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ uid })
    })
    .then(handleJsonResponse);
}

function verifyCode() {
    const adminCode = document.getElementById('verificationInput').value.trim();
    if (!adminCode) {
        return Promise.reject(new Error('Please enter the admin code.'));
    }

    return fetch(`${takePracticalApiUrl}/api/verify/code.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ admin_code: adminCode })
    })
    .then(handleJsonResponse);
}

function markAttendance() {
    return fetch(`${takePracticalApiUrl}/api/attendance/mark.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ practical_id: selectedPracticalId, verification_method: selectedVerificationMethod })
    })
    .then(handleJsonResponse);
}

function goToPracticalSession() {
    if (!attendanceMarked) {
        setStatus('Please verify and mark attendance before proceeding.', true);
        return;
    }
    const destination = `${takePracticalApiUrl}/student/view_practical/${selectedPracticalId}?attendance_marked=1`;
    window.location.href = destination;
}

function handleJsonResponse(response) {
    return response.json().then(data => {
        if (!response.ok || data.error || data.success === false) {
            const message = data.error || data.message || 'Verification failed';
            throw new Error(message);
        }
        return data;
    });
}

function setStatus(message, isError = false) {
    const statusEl = document.getElementById('takePracticalStatus');
    statusEl.textContent = message;
    statusEl.style.color = isError ? '#b91c1c' : '#111827';
}
</script>
