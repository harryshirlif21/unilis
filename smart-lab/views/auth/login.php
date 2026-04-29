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
          <div class="qr-placeholder">
            <div class="qr-grid" id="qr-grid"></div>
          </div>
          <div class="qr-label">Scan with your UNILIS mobile app</div>
          <div class="qr-session">Session: <?= strtoupper(substr(session_id(), 0, 12)) ?></div>
          <div><span class="qr-timer" id="qr-timer">Expires in 5:00</span></div>
        </div>
        <div class="alert alert-info" style="font-size:12px;">
          Open the UNILIS SmartLab app → tap <strong>Scan QR</strong> → point at the code above.
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

<script src="<?= APP_URL ?>/public/js/app.js"></script>
<script>
// Generate random QR pattern
const grid = document.getElementById('qr-grid');
if (grid) {
  for (let i = 0; i < 64; i++) {
    const cell = document.createElement('div');
    cell.className = 'qr-cell';
    cell.style.background = Math.random() > 0.5 ? '#e5e7eb' : 'transparent';
    grid.appendChild(cell);
  }
}

// Biometric simulation
function simulateBiometric() {
  const status = document.getElementById('biometric-status');
  const submitBtn = document.getElementById('biometric-submit');
  const biometricData = document.getElementById('biometric_data');
  
  status.textContent = 'Scanning...';
  status.className = 'biometric-status scanning';
  
  setTimeout(() => {
    // Generate simulated biometric data
    const simulatedData = btoa('fingerprint_' + Math.random().toString(36).substring(2, 15));
    biometricData.value = simulatedData;
    
    status.textContent = 'Scan complete!';
    status.className = 'biometric-status success';
    submitBtn.disabled = false;
  }, 2000);
}

// Enhanced tab switching with biometric support
document.querySelectorAll('.auth-tab').forEach(tab => {
  tab.addEventListener('click', function() {
    const method = this.dataset.method;
    
    // Update tabs
    document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
    this.classList.add('active');
    
    // Update panels
    document.querySelectorAll('.auth-method').forEach(m => m.classList.remove('active'));
    document.getElementById('method-' + method).classList.add('active');
  });
});

// QR Code timer countdown
let qrTimer = 300; // 5 minutes
const timerElement = document.getElementById('qr-timer');
if (timerElement) {
  setInterval(() => {
    if (qrTimer > 0) {
      qrTimer--;
      const minutes = Math.floor(qrTimer / 60);
      const seconds = qrTimer % 60;
      timerElement.textContent = `Expires in ${minutes}:${seconds.toString().padStart(2, '0')}`;
    } else {
      timerElement.textContent = 'Expired';
      timerElement.style.color = '#dc3545';
    }
  }, 1000);
}
</script>
</body>
</html>
