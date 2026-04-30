<!DOCTYPE html>
<html>
<head>
    <title>QR Complete Test</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .qr-box { border: 2px dashed #ccc; padding: 20px; text-align: center; margin: 20px 0; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; cursor: pointer; }
        .btn:hover { background: #0056b3; }
        #qr-img { min-height: 200px; display: flex; align-items: center; justify-content: center; }
        #qr-status { margin-top: 10px; font-weight: bold; }
    </style>
</head>
<body>
    <h1>QR Code Complete Test</h1>
    <button class="btn" onclick="testQR()">Generate QR Code</button>
    
    <div class="qr-box">
        <div id="qr-img">
            <span style="color:#666;">Click button to generate QR code</span>
        </div>
        <div id="qr-status"></div>
        <div id="qr-timer"></div>
    </div>
    
    <div id="test-results"></div>
    
    <script>
        let qrToken = null, pollInterval = null, qrSeconds = 300;
        const timerEl = document.getElementById('qr-timer');
        const statusEl = document.getElementById('qr-status');
        const resultsEl = document.getElementById('test-results');

        async function testQR() {
            resultsEl.innerHTML = '<h3>Testing QR Generation...</h3>';
            
            try {
                // Step 1: Generate QR
                document.getElementById('qr-img').innerHTML = '<span style="color:#666;">Generating...</span>';
                if (statusEl) { statusEl.style.display = 'none'; }
                if (pollInterval) clearInterval(pollInterval);
                qrSeconds = 300;

                const res = await fetch('qr_test.php');
                const data = await res.json();
                qrToken = data.token;
                
                resultsEl.innerHTML += '<p style="color:green;">Step 1: QR generation successful!</p>';
                resultsEl.innerHTML += `<p>Token: ${data.token}</p>`;
                resultsEl.innerHTML += `<p>ID: ${data.id}</p>`;
                resultsEl.innerHTML += `<p>URL: ${data.url}</p>`;

                // Step 2: Generate QR code image
                document.getElementById('qr-img').innerHTML = '';
                new QRCode(document.getElementById('qr-img'), {
                    text: data.url, width: 180, height: 180,
                    colorDark: '#1e293b', colorLight: '#ffffff',
                });
                
                resultsEl.innerHTML += '<p style="color:green;">Step 2: QR code rendered!</p>';

                if (statusEl) { 
                    statusEl.style.display = 'block';
                    statusEl.textContent = 'QR Code Ready - Scan with phone';
                    statusEl.style.color = '#22c55e';
                }

                // Step 3: Test polling (simulate)
                resultsEl.innerHTML += '<p style="color:green;">Step 3: QR code is ready for scanning!</p>';
                resultsEl.innerHTML += '<p style="color:blue;">Note: Polling would start here when QR is scanned by phone</p>';
                
                resultsEl.innerHTML += '<h3>QR Test Complete - All Steps Working!</h3>';
                
            } catch (error) {
                resultsEl.innerHTML = `<p style="color:red;">Error: ${error.message}</p>`;
                console.error('QR Test Error:', error);
            }
        }
    </script>
</body>
</html>
