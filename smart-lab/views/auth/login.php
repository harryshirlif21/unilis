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
        <form method="POST" action="<?= APP_URL ?>/auth/login">
          <input type="hidden" name="auth_method" value="biometric">
          <input type="hidden" id="biometric_data" name="biometric_data">
          
          <div class="biometric-container">
            <div class="biometric-scanner">
              <div class="biometric-icon">
                <i class="fas fa-fingerprint"></i>
              </div>
              <div class="biometric-status" id="biometric-status">
                Ready to scan
              </div>
            </div>
            <button type="button" class="btn btn-secondary btn-full" onclick="simulateBiometric()" style="margin-bottom:12px;">
              <i class="fas fa-fingerprint"></i> Scan Fingerprint
            </button>
            <div class="alert alert-info" style="font-size:12px;">
              Place your finger on the biometric scanner. This is a simulation for demo purposes.
            </div>
          </div>
          
          <button type="submit" id="biometric-submit" class="btn btn-primary btn-full" disabled>
            Authenticate with Biometric
          </button>
        </form>
      </div>

     <!-- METHOD 3: QR Code -->
<div class="auth-method" id="method-qr">
    <div class="qr-box">
        <div id="qr-img" style="display:flex;align-items:center;justify-content:center;min-height:160px">
            <span style="color:#94a3b8;font-size:13px">Loading QR...</span>
        </div>
        <div class="qr-label">Scan with your phone camera</div>
        <div><span class="qr-timer" id="qr-timer">Expires in 5:00</span></div>
    </div>
    <div class="alert alert-info" style="font-size:12px;margin-top:12px;">
        Point your phone camera at the code → select your name once → future scans auto-login instantly!
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
            Enter the 6-character code provided by your lab technician or lecturer.
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
let qrToken = null;
let pollInterval = null;
let qrTimer = 300;

async function generateQR() {
    const res  = await fetch('<?= APP_URL ?>/qr/generate');
    const data = await res.json();
    qrToken = data.token;

    document.getElementById('qr-img').innerHTML = '';
    new QRCode(document.getElementById('qr-img'), {
        text: data.url,
        width: 180,
        height: 180,
        colorDark: '#1e293b',
        colorLight: '#ffffff',
    });

    document.getElementById('qr-status').style.display = 'block';

    // Reset timer
    qrTimer = 300;

    // Start polling
    if (pollInterval) clearInterval(pollInterval);
    pollInterval = setInterval(async () => {
        const r = await fetch('<?= APP_URL ?>/qr/poll?token=' + qrToken);
        const d = await r.json();
        if (d.status === 'claimed') {
            clearInterval(pollInterval);
            document.getElementById('qr-status').textContent = '✅ Logged in! Redirecting...';
            document.getElementById('qr-status').style.color = '#22c55e';
            setTimeout(() => window.location.href = d.redirect, 1000);
        } else if (d.status === 'expired') {
            clearInterval(pollInterval);
            document.getElementById('qr-status').textContent = '⚠️ Expired. Refreshing...';
            setTimeout(generateQR, 1500);
        }
    }, 2000);
}

// Generate QR when tab is clicked
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

// QR timer
const timerEl = document.getElementById('qr-timer');
setInterval(() => {
    if (qrTimer > 0) {
        qrTimer--;
        const m = Math.floor(qrTimer/60), s = qrTimer%60;
        if (timerEl) timerEl.textContent = `Expires in ${m}:${s.toString().padStart(2,'0')}`;
    }
}, 1000);
</script>
</body>
</html>
