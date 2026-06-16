<?php
/**
 * Attendance Verification Page
 * Student verifies their physical presence in the laboratory
 * using approved methods: RFID, Biometric, Dynamic QR, Technician Code, NFC
 * 
 * Password/email login is EXPLICITLY forbidden for attendance verification.
 */
$practical = $practical ?? [];
$user_id = $user_id ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Attendance - UNILIS SmartLabs</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f7fa; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .container { max-width: 600px; width: 100%; margin: 20px; }
        .card { background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); overflow: hidden; }
        .header { background: linear-gradient(135deg, #1a5276, #2980b9); color: white; padding: 30px; text-align: center; }
        .header h1 { font-size: 24px; margin-bottom: 5px; }
        .header p { font-size: 14px; opacity: 0.9; }
        .content { padding: 30px; }
        
        .practical-info { background: #f0f9ff; border: 1px solid #d6eaf8; border-radius: 8px; padding: 15px; margin-bottom: 25px; }
        .info-row { margin: 5px 0; font-size: 14px; }
        .info-label { font-weight: 600; color: #555; }
        
        .methods { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 25px; }
        .method-card { background: white; border: 2px solid #e0e0e0; border-radius: 10px; padding: 20px; text-align: center; cursor: pointer; transition: all 0.2s; }
        .method-card:hover { border-color: #3498db; box-shadow: 0 2px 10px rgba(52,152,219,0.2); transform: translateY(-2px); }
        .method-card.selected { border-color: #27ae60; background: #f0fdf4; }
        .method-card .icon { font-size: 36px; margin-bottom: 10px; }
        .method-card .name { font-weight: 600; font-size: 14px; color: #333; }
        .method-card .desc { font-size: 11px; color: #999; margin-top: 5px; }
        .method-card.disabled { opacity: 0.5; cursor: not-allowed; }
        
        .verify-section { margin-bottom: 25px; }
        .verify-section h3 { font-size: 16px; margin-bottom: 10px; color: #2c3e50; }
        
        .input-group { margin-bottom: 15px; }
        .input-group label { display: block; font-size: 13px; font-weight: 600; color: #555; margin-bottom: 5px; }
        .input-group input, .input-group select { width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px; transition: border-color 0.2s; }
        .input-group input:focus, .input-group select:focus { border-color: #3498db; outline: none; }
        .input-group input[readonly] { background: #f9f9f9; cursor: not-allowed; }
        
        .btn-verify { width: 100%; padding: 15px; background: linear-gradient(135deg, #27ae60, #2ecc71); color: white; border: none; border-radius: 8px; font-size: 18px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .btn-verify:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(39,174,96,0.3); }
        .btn-verify:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
        
        .qr-reader { width: 100%; max-width: 300px; margin: 15px auto; display: none; }
        .qr-reader.active { display: block; }
        
        .status-message { padding: 15px; border-radius: 8px; margin-bottom: 15px; font-size: 14px; display: none; }
        .status-message.success { display: block; background: #d5f5e3; color: #1e8449; border: 1px solid #a3e4c1; }
        .status-message.error { display: block; background: #fde8e8; color: #c0392b; border: 1px solid #f5b7b1; }
        .status-message.info { display: block; background: #d6eaf8; color: #1a5276; border: 1px solid #aed6f1; }
        
        .footer { text-align: center; padding: 20px; background: #f8f9fa; font-size: 12px; color: #999; }
        .footer strong { color: #e74c3c; }
        
        .window-info { background: #fff8e1; border: 1px solid #ffe082; border-radius: 8px; padding: 15px; margin-bottom: 25px; }
        .window-info h4 { color: #e65100; margin-bottom: 5px; font-size: 14px; }
        .window-info p { font-size: 13px; color: #666; }
        
        .hidden { display: none; }
        
        @media (max-width: 480px) { .methods { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1>🆔 Verify Attendance</h1>
                <p>Physical presence verification required before practical can begin</p>
            </div>
            
            <div class="content">
                <div class="practical-info">
                    <div class="info-row"><span class="info-label">Practical:</span> <?php echo htmlspecialchars($practical['title'] ?? ''); ?></div>
                    <div class="info-row"><span class="info-label">Lab:</span> <?php echo htmlspecialchars(($practical['lab_name'] ?? '') . ' (' . ($practical['lab_code'] ?? '') . ')'); ?></div>
                    <div class="info-row"><span class="info-label">Date:</span> <?php echo htmlspecialchars($practical['scheduled_date'] ?? ''); ?></div>
                    <div class="info-row"><span class="info-label">Time:</span> <?php echo htmlspecialchars($practical['start_time'] ?? '') . ' - ' . htmlspecialchars($practical['end_time'] ?? ''); ?></div>
                    <div class="info-row"><span class="info-label">Lecturer:</span> <?php echo htmlspecialchars($practical['lecturer_name'] ?? ''); ?></div>
                </div>
                
                <div class="window-info">
                    <h4>⏰ Verification Window</h4>
                    <p>Opens: <strong>30 minutes before</strong> practical start time</p>
                    <p>Closes: <strong>20 minutes after</strong> practical start time</p>
                </div>
                
                <div id="statusMessage" class="status-message"></div>
                
                <h3 style="margin-bottom: 15px; color: #2c3e50;">Select Verification Method</h3>
                
                <div class="methods">
                    <div class="method-card" data-method="DYNAMIC_QR" onclick="selectMethod('DYNAMIC_QR', this)">
                        <div class="icon">📱</div>
                        <div class="name">Dynamic QR Code</div>
                        <div class="desc">Scan QR from lab display</div>
                    </div>
                    <div class="method-card" data-method="RFID" onclick="selectMethod('RFID', this)">
                        <div class="icon">💳</div>
                        <div class="name">RFID Card</div>
                        <div class="desc">Tap your RFID card</div>
                    </div>
                    <div class="method-card" data-method="BIOMETRIC" onclick="selectMethod('BIOMETRIC', this)">
                        <div class="icon">👆</div>
                        <div class="name">Biometric</div>
                        <div class="desc">Fingerprint scanner</div>
                    </div>
                    <div class="method-card" data-method="TECHNICIAN_CODE" onclick="selectMethod('TECHNICIAN_CODE', this)">
                        <div class="icon">🔑</div>
                        <div class="name">Technician Code</div>
                        <div class="desc">Enter code from technician</div>
                    </div>
                </div>
                
                <!-- Dynamic QR Section -->
                <div id="qrSection" class="verify-section hidden">
                    <h3>Scan Dynamic QR Code</h3>
                    <p style="color: #666; font-size: 13px; margin-bottom: 15px;">
                        Point your phone camera at the QR code displayed on the lab screen.
                        The QR code refreshes every 15 seconds.
                    </p>
                    <div class="input-group">
                        <label>QR Code Data</label>
                        <input type="text" id="qrData" placeholder="Scan QR code from lab display..." readonly>
                    </div>
                    <div id="qrPreview" class="qr-reader">
                        <p style="font-size: 12px; color: #999; text-align: center;">
                            📸 QR scanner would appear here in a mobile app<br>
                            <small>Scan the rotating QR from the lab projector/TV</small>
                        </p>
                    </div>
                </div>
                
                <!-- RFID Section -->
                <div id="rfidSection" class="verify-section hidden">
                    <h3>RFID Card Verification</h3>
                    <p style="color: #666; font-size: 13px; margin-bottom: 15px;">
                        Tap your RFID card on the reader. If prompted, enter your card ID below.
                    </p>
                    <div class="input-group">
                        <label>RFID Tag ID</label>
                        <input type="text" id="rfidTag" placeholder="Tap RFID card to auto-fill..." oninput="autoDetectMethod()">
                    </div>
                </div>
                
                <!-- Biometric Section -->
                <div id="biometricSection" class="verify-section hidden">
                    <h3>Biometric Verification</h3>
                    <p style="color: #666; font-size: 13px; margin-bottom: 15px;">
                        Place your finger on the biometric scanner.
                    </p>
                    <div class="input-group">
                        <label>Biometric Hash (auto-filled from scanner)</label>
                        <input type="text" id="biometricHash" placeholder="Scan fingerprint..." readonly>
                    </div>
                </div>
                
                <!-- Technician Code Section -->
                <div id="techCodeSection" class="verify-section hidden">
                    <h3>Technician Verification Code</h3>
                    <p style="color: #666; font-size: 13px; margin-bottom: 15px;">
                        Enter the code provided by the lab technician.
                    </p>
                    <div class="input-group">
                        <label>Technician Code</label>
                        <input type="text" id="technicianCode" placeholder="Enter code from technician..." maxlength="20">
                    </div>
                </div>
                
                <button id="verifyBtn" class="btn-verify" onclick="verifyAttendance()" disabled>
                    ✅ Verify Attendance
                </button>
                
                <div style="text-align: center; margin-top: 15px;">
                    <a href="<?php echo APP_URL; ?>/practicals" style="color: #999; text-decoration: none; font-size: 13px;">
                        &larr; Back to Practicals
                    </a>
                </div>
                
                <div style="margin-top: 20px; padding: 15px; background: #fef9e7; border-radius: 8px; border: 1px solid #f9e79f;">
                    <p style="font-size: 12px; color: #7d6608; text-align: center;">
                        <strong>⚠ Important:</strong> Password/email login is NOT accepted for attendance verification.
                        You must use one of the methods above to prove physical presence in the laboratory.
                    </p>
                </div>
            </div>
            
            <div class="footer">
                <p>Only verified physical presence methods are accepted</p>
                <p><strong>Password/Email login is not valid for attendance verification</strong></p>
            </div>
        </div>
    </div>
    
    <script>
    let selectedMethod = '';
    
    function selectMethod(method, element) {
        // Deselect all
        document.querySelectorAll('.method-card').forEach(c => c.classList.remove('selected'));
        // Select clicked
        element.classList.add('selected');
        selectedMethod = method;
        
        // Hide all sections
        document.querySelectorAll('.verify-section').forEach(s => s.classList.add('hidden'));
        
        // Show relevant section
        switch(method) {
            case 'DYNAMIC_QR':
                document.getElementById('qrSection').classList.remove('hidden');
                document.getElementById('qrSection').classList.add('active');
                break;
            case 'RFID':
                document.getElementById('rfidSection').classList.remove('hidden');
                break;
            case 'BIOMETRIC':
                document.getElementById('biometricSection').classList.remove('hidden');
                break;
            case 'TECHNICIAN_CODE':
                document.getElementById('techCodeSection').classList.remove('hidden');
                break;
        }
        
        document.getElementById('verifyBtn').disabled = false;
    }
    
    function autoDetectMethod() {
        // Auto-detect would trigger based on device input
    }
    
    function verifyAttendance() {
        const btn = document.getElementById('verifyBtn');
        btn.disabled = true;
        btn.textContent = '⏳ Verifying...';
        
        const data = {
            practical_id: '<?php echo $practical['id'] ?? ''; ?>',
            verification_method: selectedMethod
        };
        
        // Add method-specific data
        switch(selectedMethod) {
            case 'DYNAMIC_QR':
                data.qr_data = document.getElementById('qrData').value;
                if (!data.qr_data) {
                    showMessage('Please scan the QR code from the lab display first.', 'error');
                    btn.disabled = false;
                    btn.textContent = '✅ Verify Attendance';
                    return;
                }
                break;
            case 'RFID':
                data.rfid_tag = document.getElementById('rfidTag').value;
                if (!data.rfid_tag) {
                    showMessage('Please tap your RFID card on the reader.', 'error');
                    btn.disabled = false;
                    btn.textContent = '✅ Verify Attendance';
                    return;
                }
                break;
            case 'BIOMETRIC':
                data.biometric_hash = document.getElementById('biometricHash').value;
                if (!data.biometric_hash) {
                    showMessage('Please scan your fingerprint on the biometric scanner.', 'error');
                    btn.disabled = false;
                    btn.textContent = '✅ Verify Attendance';
                    return;
                }
                break;
            case 'TECHNICIAN_CODE':
                data.technician_code = document.getElementById('technicianCode').value;
                if (!data.technician_code) {
                    showMessage('Please enter the code from the lab technician.', 'error');
                    btn.disabled = false;
                    btn.textContent = '✅ Verify Attendance';
                    return;
                }
                break;
        }
        
        fetch('<?php echo APP_URL; ?>/workflow/verify-attendance', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(r => r.json())
        .then(result => {
            if (result.success) {
                showMessage('✅ ' + result.message + '<br><small>Verification expires: ' + (result.expires_at || 'N/A') + '</small>', 'success');
                btn.textContent = '✅ Verified!';
                setTimeout(() => {
                    window.location.href = '<?php echo APP_URL; ?>/workflow/dashboard/<?php echo $practical['id'] ?? ''; ?>';
                }, 2000);
            } else {
                showMessage('❌ ' + result.message, 'error');
                btn.disabled = false;
                btn.textContent = '✅ Verify Attendance';
            }
        })
        .catch(err => {
            showMessage('❌ Network error: ' + err.message, 'error');
            btn.disabled = false;
            btn.textContent = '✅ Verify Attendance';
        });
    }
    
    function showMessage(text, type) {
        const el = document.getElementById('statusMessage');
        el.className = 'status-message ' + type;
        el.innerHTML = text;
    }
    
    // Simulate RFID scanner input
    document.addEventListener('keydown', function(e) {
        if (selectedMethod === 'RFID' && e.key.length === 1) {
            // RFID scanners typically send keystrokes rapidly
            // This is a simplified simulation
        }
    });
    </script>
</body>
</html>