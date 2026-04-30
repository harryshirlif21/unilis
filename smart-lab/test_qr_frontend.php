<!DOCTYPE html>
<html>
<head>
    <title>QR Test</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>
<body>
    <h1>QR Code Test</h1>
    <button onclick="testQR()">Generate QR</button>
    <div id="qr-result"></div>
    
    <script>
        async function testQR() {
            const resultDiv = document.getElementById('qr-result');
            resultDiv.innerHTML = '<p>Generating QR...</p>';
            
            try {
                // Test direct PHP execution first
                const response = await fetch('qr_test.php');
                const data = await response.json();
                
                resultDiv.innerHTML = `
                    <h3>QR Generated Successfully!</h3>
                    <p>Token: ${data.token}</p>
                    <p>ID: ${data.id}</p>
                    <div id="qrcode"></div>
                `;
                
                // Generate QR code
                new QRCode(document.getElementById('qrcode'), {
                    text: data.url,
                    width: 200,
                    height: 200
                });
                
            } catch (error) {
                resultDiv.innerHTML = `<p style="color: red;">Error: ${error.message}</p>`;
            }
        }
    </script>
</body>
</html>
