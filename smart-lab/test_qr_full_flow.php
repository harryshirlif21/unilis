<!DOCTYPE html>
<html>
<head>
    <title>QR Full Flow Test</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .test-section { border: 1px solid #ddd; margin: 20px 0; padding: 20px; border-radius: 8px; }
        .qr-box { border: 2px dashed #ccc; padding: 20px; text-align: center; margin: 20px 0; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; cursor: pointer; margin: 5px; }
        .btn:hover { background: #0056b3; }
        .btn.success { background: #28a745; }
        .btn.warning { background: #ffc107; color: #000; }
        #qr-img { min-height: 200px; display: flex; align-items: center; justify-content: center; }
        #qr-status { margin-top: 10px; font-weight: bold; }
        .status { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .status.success { background: #d4edda; color: #155724; }
        .status.error { background: #f8d7da; color: #721c24; }
        .status.info { background: #d1ecf1; color: #0c5460; }
    </style>
</head>
<body>
    <h1>QR Code Full Flow Test</h1>
    
    <div class="test-section">
        <h2>Step 1: Generate QR Code</h2>
        <button class="btn" onclick="generateQR()">Generate QR Code</button>
        <div id="generate-results"></div>
    </div>
    
    <div class="test-section">
        <h2>Step 2: QR Code Display</h2>
        <div class="qr-box">
            <div id="qr-img">
                <span style="color:#666;">Click "Generate QR Code" to start</span>
            </div>
            <div id="qr-status"></div>
            <div id="qr-timer"></div>
        </div>
        <div id="display-results"></div>
    </div>
    
    <div class="test-section">
        <h2>Step 3: Simulate Phone Scan</h2>
        <button class="btn" onclick="simulateScan()">Simulate Phone Scan</button>
        <div id="scan-results"></div>
        <iframe id="scan-frame" style="display:none; width:100%; height:400px; border:1px solid #ccc;"></iframe>
    </div>
    
    <div class="test-section">
        <h2>Step 4: Test Polling</h2>
        <button class="btn" onclick="testPolling()">Start Polling</button>
        <button class="btn warning" onclick="stopPolling()">Stop Polling</button>
        <div id="polling-results"></div>
    </div>
    
    <div class="test-section">
        <h2>Complete Test Results</h2>
        <div id="complete-results"></div>
    </div>
    
    <script>
        let qrToken = null, qrData = null, pollInterval = null;
        const timerEl = document.getElementById('qr-timer');
        const statusEl = document.getElementById('qr-status');

        function showResult(sectionId, message, type = 'info') {
            const section = document.getElementById(sectionId);
            const div = document.createElement('div');
            div.className = `status ${type}`;
            div.innerHTML = message;
            section.appendChild(div);
        }

        async function generateQR() {
            const resultsDiv = document.getElementById('generate-results');
            resultsDiv.innerHTML = '';
            
            try {
                showResult('generate-results', 'Generating QR token...', 'info');
                
                const response = await fetch('qr_test.php');
                const data = await response.json();
                
                qrToken = data.token;
                qrData = data;
                
                showResult('generate-results', `QR Token Generated: ${data.token}`, 'success');
                showResult('generate-results', `Session ID: ${data.id}`, 'success');
                showResult('generate-results', `Scan URL: ${data.url}`, 'success');
                
                // Auto-generate QR code image
                generateQRImage();
                
            } catch (error) {
                showResult('generate-results', `Error: ${error.message}`, 'error');
            }
        }

        function generateQRImage() {
            if (!qrData) return;
            
            const displayDiv = document.getElementById('display-results');
            displayDiv.innerHTML = '';
            
            // Generate QR code image
            document.getElementById('qr-img').innerHTML = '';
            new QRCode(document.getElementById('qr-img'), {
                text: qrData.url, width: 180, height: 180,
                colorDark: '#1e293b', colorLight: '#ffffff',
            });
            
            if (statusEl) { 
                statusEl.style.display = 'block';
                statusEl.textContent = 'QR Code Ready - Scan with phone';
                statusEl.style.color = '#22c55e';
            }
            
            showResult('display-results', 'QR code rendered successfully', 'success');
            showResult('display-results', 'Ready for scanning', 'info');
        }

        function simulateScan() {
            if (!qrToken) {
                showResult('scan-results', 'Please generate QR code first', 'error');
                return;
            }
            
            const scanResults = document.getElementById('scan-results');
            scanResults.innerHTML = '';
            
            // Show scan URL in iframe
            const iframe = document.getElementById('scan-frame');
            iframe.style.display = 'block';
            iframe.src = `qr/scan.php?token=${qrToken}`;
            
            showResult('scan-results', 'Scan interface opened in iframe below', 'info');
            showResult('scan-results', 'This simulates what a phone would see when scanning the QR code', 'info');
        }

        async function testPolling() {
            if (!qrToken) {
                showResult('polling-results', 'Please generate QR code first', 'error');
                return;
            }
            
            const pollingDiv = document.getElementById('polling-results');
            pollingDiv.innerHTML = '';
            showResult('polling-results', 'Starting polling...', 'info');
            
            pollInterval = setInterval(async () => {
                try {
                    const response = await fetch(`qr/poll.php?token=${qrToken}`);
                    const data = await response.json();
                    
                    showResult('polling-results', `Poll status: ${data.status}`, 'info');
                    
                    if (data.status === 'claimed') {
                        clearInterval(pollInterval);
                        showResult('polling-results', 'QR session claimed! Login successful.', 'success');
                        showResult('polling-results', `Redirect: ${data.redirect}`, 'success');
                        
                        // Update complete results
                        updateCompleteResults(true);
                    } else if (data.status === 'expired') {
                        clearInterval(pollInterval);
                        showResult('polling-results', 'QR session expired', 'error');
                        updateCompleteResults(false);
                    }
                } catch (error) {
                    showResult('polling-results', `Polling error: ${error.message}`, 'error');
                }
            }, 2000);
        }

        function stopPolling() {
            if (pollInterval) {
                clearInterval(pollInterval);
                pollInterval = null;
                showResult('polling-results', 'Polling stopped', 'info');
            }
        }

        function updateCompleteResults(success) {
            const completeDiv = document.getElementById('complete-results');
            completeDiv.innerHTML = '';
            
            if (success) {
                completeDiv.innerHTML = `
                    <div class="status success">
                        <h3>QR Full Flow Test - SUCCESS! </h3>
                        <p>1. QR token generation: Working</p>
                        <p>2. QR code rendering: Working</p>
                        <p>3. Phone scan simulation: Working</p>
                        <p>4. Session polling: Working</p>
                        <p>5. User authentication: Working</p>
                        <p><strong>QR authentication system is fully functional!</strong></p>
                    </div>
                `;
            } else {
                completeDiv.innerHTML = `
                    <div class="status error">
                        <h3>QR Full Flow Test - Issues Found</h3>
                        <p>Some components may need attention. Check individual test results above.</p>
                    </div>
                `;
            }
        }
    </script>
</body>
</html>
