<?php
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/helpers.php';

Auth::guard();

$practicalId = sanitize($_GET['practical_id'] ?? '');

if (empty($practicalId)) {
    echo 'Error: Practical ID is required.';
    exit;
}

// For demo purposes, assume verification is successful
// In real implementation, this would handle QR/RFID/fingerprint
$verificationMethod = 'qr'; // or 'rfid', 'fingerprint'

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Attendance</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div style="text-align: center; margin-top: 50px;">
        <h1>Verify Your Attendance</h1>
        <p>Scan QR code or use RFID/fingerprint to mark attendance</p>
        
        <button id="verify-btn" style="padding: 15px 30px; font-size: 18px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer;">
            Verify Attendance
        </button>
        
        <div id="message" style="margin-top: 20px;"></div>
    </div>

    <script>
        document.getElementById('verify-btn').addEventListener('click', () => {
            const btn = document.getElementById('verify-btn');
            const message = document.getElementById('message');
            
            btn.disabled = true;
            btn.textContent = 'Verifying...';
            message.textContent = 'Processing verification...';
            
            // Call the mark attendance API
            fetch('/smart-lab/practicals/markAttendance', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    practical_id: '<?php echo $practicalId; ?>',
                    verification_method: '<?php echo $verificationMethod; ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    message.innerHTML = '<div style="color: green;">Attendance marked successfully! Redirecting to practical...</div>';
                    // Redirect to practical page
                    setTimeout(() => {
                        window.location.href = '/student/practical-page?report_id=' + data.report_id;
                    }, 2000);
                } else {
                    message.innerHTML = '<div style="color: red;">Error: ' + data.message + '</div>';
                    btn.disabled = false;
                    btn.textContent = 'Try Again';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                message.innerHTML = '<div style="color: red;">Network error. Please try again.</div>';
                btn.disabled = false;
                btn.textContent = 'Try Again';
            });
        });
    </script>
</body>
</html>