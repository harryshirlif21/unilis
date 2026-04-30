<?php if (isset($error) && $error): ?>
  <div id="page-error" data-msg="<?= htmlspecialchars($error) ?>"></div>
<?php endif; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Login — UNILIS SmartLab</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_URL ?>/public/css/app.css">
</head>
<body>

<div class="auth-page">

  <!-- Left branding panel -->
  <div class="auth-left">
    <div class="auth-logo">
      <div class="auth-logo-icon">SL</div>
      <div>
        <div class="auth-logo-text">UNILIS SmartLab</div>
        <div class="auth-logo-sub">Integrated Laboratory System</div>
      </div>
    </div>

    <h1 class="auth-headline">
      The future of<br>
      <span>laboratory</span><br>
      management.
    </h1>
    <p class="auth-desc">
      A unified digital platform for managing scientific, engineering,
      and clinical laboratories — with blockchain-secured asset tracking,
      digital notebooks, and intelligent scheduling.
    </p>

    <div class="auth-features">
      <div class="auth-feature">
        <div class="auth-feature-dot"></div>
        Biometric &amp; QR multi-factor authentication
      </div>
      <div class="auth-feature">
        <div class="auth-feature-dot"></div>
        SHA-256 blockchain asset tracking
      </div>
      <div class="auth-feature">
        <div class="auth-feature-dot"></div>
        Digital lab notebooks with version control
      </div>
      <div class="auth-feature">
        <div class="auth-feature-dot"></div>
        Real-time lab occupancy &amp; scheduling
      </div>
      <div class="auth-feature">
        <div class="auth-feature-dot"></div>
        Cross-lab inventory &amp; resource sharing
      </div>
    </div>
  </div>

  <!-- Right form panel -->
  <div class="auth-right">
    <div class="auth-box">

      <div class="auth-title">Welcome back</div>
      <div class="auth-subtitle">Sign in to access your laboratory system</div>

      <?php if (isset($error) && $error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <!-- Auth method tabs -->
      <div class="auth-tabs">
        <button class="auth-tab active" data-method="password">Password</button>
        <button class="auth-tab" data-method="biometric">Biometric</button>
        <button class="auth-tab" data-method="qr">QR Code</button>
        <button class="auth-tab" data-method="code">Auth Code</button>
      </div>

      <!-- METHOD 1: Password -->
      <div class="auth-method active" id="method-password">
        <form method="POST" action="<?= APP_URL ?>/auth/login">
          <input type="hidden" name="auth_method" value="password">
          <div class="form-group">
            <label class="form-label">Registration Number</label>
            <input type="text" name="reg_number" class="form-control"
              placeholder="e.g. SCT/2021/001" required autofocus
              value="<?= htmlspecialchars($_POST['reg_number'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control"
              placeholder="Enter your password" required>
          </div>
          <button type="submit" class="btn btn-primary btn-full" style="margin-top:8px;">
            Sign In
          </button>
        </form>
      </div>

      <!-- METHOD 2: Biometric -->
      <div class="auth-method" id="method-biometric">
        <!-- Step 1: Enter registration number -->
        <div id="biometric-step-1">
          <form id="biometric-otp-form">
            <input type="hidden" name="auth_method" value="biometric">
            <input type="hidden" name="action" value="send_otp">
            <div class="form-group">
              <label class="form-label">Registration Number</label>
              <input type="text" name="reg_number" class="form-control"
                placeholder="e.g. SCT/2021/001" required
                id="biometric-reg-number">
            </div>
            <button type="submit" class="btn btn-primary btn-full">
              <i class="fas fa-envelope"></i> Send OTP to Email
            </button>
            <div class="alert alert-info" style="font-size:12px;margin-top:12px;">
              Enter your registration number to receive a 6-digit OTP code at your registered email address.
            </div>
          </form>
        </div>
        
        <!-- Step 2: Enter OTP (hidden initially) -->
        <div id="biometric-step-2" style="display:none;">
          <form id="biometric-verify-form">
            <input type="hidden" name="auth_method" value="biometric">
            <input type="hidden" name="action" value="verify_otp">
            <input type="hidden" id="biometric-user-id" name="user_id">
            
            <div class="alert alert-success" style="font-size:13px;margin-bottom:20px;">
              <i class="fas fa-check-circle"></i> OTP sent to <span id="biometric-masked-email"></span>
            </div>
            
            <div class="form-group">
              <label class="form-label">Enter 6-Digit OTP Code</label>
              <div class="code-inputs">
                <input type="text" class="code-input" name="otp[]" maxlength="1">
                <input type="text" class="code-input" name="otp[]" maxlength="1">
                <input type="text" class="code-input" name="otp[]" maxlength="1">
                <input type="text" class="code-input" name="otp[]" maxlength="1">
                <input type="text" class="code-input" name="otp[]" maxlength="1">
                <input type="text" class="code-input" name="otp[]" maxlength="1">
              </div>
            </div>
            
            <div style="display:flex;gap:10px;">
              <button type="button" id="biometric-back-btn" class="btn btn-secondary" style="flex:1;">
                ← Back
              </button>
              <button type="submit" class="btn btn-primary" style="flex:1;">
                Verify OTP →
              </button>
            </div>
            
            <div class="alert alert-warning" style="font-size:12px;margin-top:12px;">
              <i class="fas fa-clock"></i> OTP expires in 10 minutes
            </div>
          </form>
        </div>
      </div>

     <!-- METHOD 3: QR Code -->
<div class="auth-method" id="method-qr">
    <div class="qr-box">
        <div id="qr-img" style="display:flex;align-items:center;justify-content:center;min-height:180px">
            <span style="color:#94a3b8;font-size:13px">Click "QR Code" tab to generate</span>
        </div>
        <div class="qr-label">Scan with your phone camera</div>
        <div><span class="qr-timer" id="qr-timer"></span></div>
    </div>
    <div class="alert alert-info" style="font-size:12px;margin-top:12px;">
        Scan once → select your name → future scans log you in instantly!
    </div>
    <div id="qr-status" style="text-align:center;font-size:13px;margin-top:8px;color:#6366f1;display:none">
        ⏳ Waiting for phone scan...
    </div>
</div>

      <!-- METHOD 4: Confirmation Code -->
      <div class="auth-method" id="method-code">
        <form method="POST" action="<?= APP_URL ?>/auth/login">
          <input type="hidden" name="auth_method" value="code">
          <p style="font-size:13px;color:var(--text2);margin-bottom:20px;">
            Your 6-character session code has been sent to your registered email address. Check your inbox and enter the code below.
          </p>
          <div class="code-inputs">
            <input type="text" class="code-input" name="code[]" maxlength="1">
            <input type="text" class="code-input" name="code[]" maxlength="1">
            <input type="text" class="code-input" name="code[]" maxlength="1">
            <input type="text" class="code-input" name="code[]" maxlength="1">
            <input type="text" class="code-input" name="code[]" maxlength="1">
            <input type="text" class="code-input" name="code[]" maxlength="1">
          </div>
          <button type="submit" class="btn btn-primary btn-full">Verify Code</button>
        </form>
      </div>

      <div style="text-align:center;margin-top:20px;">
        <a href="<?= APP_URL ?>/auth/register" style="color:var(--primary);text-decoration:none;font-size:14px;">
          Don't have an account? <strong>Register here</strong>
        </a>
      </div>

      <p style="text-align:center;font-size:12px;color:var(--text3);margin-top:24px;">
        Having trouble? Contact your lab administrator.
      </p>

    </div>
  </div>
</div>


<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
let qrToken = null, pollInterval = null, qrSeconds = 300;
const timerEl = document.getElementById('qr-timer');
const statusEl = document.getElementById('qr-status');

async function generateQR() {
    document.getElementById('qr-img').innerHTML = '<span style="color:#94a3b8;font-size:13px">Generating...</span>';
    if (statusEl) { statusEl.style.display = 'none'; }
    if (pollInterval) clearInterval(pollInterval);
    qrSeconds = 300;

    const res  = await fetch('<?= APP_URL ?>/qr/generate');
    const data = await res.json();
    qrToken = data.token;

    document.getElementById('qr-img').innerHTML = '';
    new QRCode(document.getElementById('qr-img'), {
        text: data.url, width: 180, height: 180,
        colorDark: '#1e293b', colorLight: '#ffffff',
    });

    if (statusEl) { statusEl.style.display = 'block'; }

    pollInterval = setInterval(async () => {
        const r = await fetch('<?= APP_URL ?>/qr/poll?token=' + qrToken);
        const d = await r.json();
        if (d.status === 'claimed') {
            clearInterval(pollInterval);
            statusEl.textContent = '✅ Logged in! Redirecting...';
            statusEl.style.color = '#22c55e';
            setTimeout(() => window.location.href = d.redirect, 1000);
        } else if (d.status === 'expired') {
            clearInterval(pollInterval);
            statusEl.textContent = '⚠️ Expired — refreshing...';
            setTimeout(generateQR, 1500);
        }
    }, 2000);
}

// Generate when QR tab clicked
document.querySelectorAll('.auth-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        const method = this.dataset.method;
        document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        document.querySelectorAll('.auth-method').forEach(m => m.classList.remove('active'));
        document.getElementById('method-' + method).classList.add('active');
        if (method === 'qr') generateQR();
    });
});

// Biometric OTP flow
document.getElementById('biometric-otp-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const regNumber = formData.get('reg_number');
    
    try {
        const response = await fetch('<?= APP_URL ?>/auth/login', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Move to step 2
            document.getElementById('biometric-step-1').style.display = 'none';
            document.getElementById('biometric-step-2').style.display = 'block';
            document.getElementById('biometric-masked-email').textContent = result.masked_email;
            document.getElementById('biometric-user-id').value = result.user_id;
            
            // Focus first OTP input
            document.querySelector('#biometric-step-2 .code-input').focus();
        } else {
            // Show error
            alert(result.error || 'Failed to send OTP. Please try again.');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Network error. Please try again.');
    }
});

// Back button for biometric
document.getElementById('biometric-back-btn').addEventListener('click', function() {
    document.getElementById('biometric-step-2').style.display = 'none';
    document.getElementById('biometric-step-1').style.display = 'block';
    document.getElementById('biometric-reg-number').focus();
});

// Biometric OTP verification
document.getElementById('biometric-verify-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const otpInputs = document.querySelectorAll('#biometric-step-2 .code-input');
    const otpCode = Array.from(otpInputs).map(input => input.value).join('');
    
    if (otpCode.length !== 6) {
        alert('Please enter all 6 digits of the OTP code.');
        return;
    }
    
    formData.set('otp_code', otpCode);
    
    try {
        const response = await fetch('<?= APP_URL ?>/auth/login', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Redirect to dashboard
            window.location.href = result.redirect || '<?= APP_URL ?>/dashboard';
        } else {
            alert(result.error || 'Invalid or expired OTP. Please try again.');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Network error. Please try again.');
    }
});

// Auto-focus next OTP input
document.querySelectorAll('.code-input').forEach((input, index) => {
    input.addEventListener('input', function() {
        if (this.value.length === 1) {
            if (index < this.parentElement.children.length - 1) {
                this.parentElement.children[index + 1].focus();
            }
        }
    });
    
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Backspace' && this.value === '' && index > 0) {
            this.parentElement.children[index - 1].focus();
        }
    });
});

// Countdown timer
setInterval(() => {
    if (!timerEl || qrSeconds <= 0) return;
    qrSeconds--;
    const m = Math.floor(qrSeconds/60), s = qrSeconds % 60;
    timerEl.textContent = qrSeconds > 0
        ? 'Expires in ' + m + ':' + s.toString().padStart(2,'0')
        : 'Expired';
}, 1000);
</script>
</body>
</html>
