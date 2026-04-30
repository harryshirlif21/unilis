<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Register — UNILIS SmartLab</title>
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
      Join the<br>
      <span>smart</span><br>
      laboratory.
    </h1>
    <p class="auth-desc">
      Create your account to access digital lab notebooks,
      practical scheduling, blockchain asset tracking,
      and your institution's full laboratory ecosystem.
    </p>
    <div class="auth-features">
      <div class="auth-feature">
        <div class="auth-feature-dot"></div>
        Access practicals and submit reports
      </div>
      <div class="auth-feature">
        <div class="auth-feature-dot"></div>
        Track lab assets and sessions
      </div>
      <div class="auth-feature">
        <div class="auth-feature-dot"></div>
        Digital lab notebooks and blockchain records
      </div>
    </div>
  </div>

  <!-- Right form panel -->
  <div class="auth-right">
    <div class="auth-box" style="max-width:420px;">

      <div class="auth-title">Create account</div>
      <div class="auth-subtitle">Fill in your details to register</div>

      <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <?php if (!empty($success)): ?>
        <div class="alert alert-success">
          <?= htmlspecialchars($success) ?>
          <br><a href="<?= APP_URL ?>/auth/login" style="color:var(--green);font-weight:600;">
            Click here to login →
          </a>
        </div>
      <?php endif; ?>

      <?php if (empty($success)): ?>
      <form method="POST" action="<?= APP_URL ?>/auth/register">

        <!-- Hidden role field — always student -->
        <input type="hidden" name="role" value="student">
        <input type="hidden" name="lab_id" value="">

        <!-- Full name -->
        <div class="form-group">
          <label class="form-label">Full Name</label>
          <input type="text" name="full_name" class="form-control"
            placeholder="e.g. John Kamau Mwangi" required
            value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
        </div>

        <!-- Reg number -->
        <div class="form-group">
          <label class="form-label">Reg / Staff No.</label>
          <input type="text" name="reg_number" class="form-control"
            placeholder="e.g. SCT/2021/001" required
            value="<?= htmlspecialchars($_POST['reg_number'] ?? '') ?>">
        </div>

        <!-- Email -->
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <input type="email" name="email" class="form-control"
            placeholder="e.g. john@unilis.ac.ke" required
            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>

        <!-- Department -->
        <div class="form-group">
          <label class="form-label">Department</label>
          <input type="text" name="department" class="form-control"
            placeholder="e.g. Physics"
            value="<?= htmlspecialchars($_POST['department'] ?? '') ?>">
        </div>

        <!-- Password row -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div class="form-group">
            <label class="form-label">Password</label>
            <input type="password" name="password" id="pw" class="form-control"
              placeholder="Min 8 characters" required>
          </div>
          <div class="form-group">
            <label class="form-label">Confirm Password</label>
            <input type="password" name="password_confirm" id="pw2" class="form-control"
              placeholder="Repeat password" required>
          </div>
        </div>
        <div class="form-hint" id="pw-hint" style="margin-top:-12px;margin-bottom:14px;"></div>

        <button type="submit" class="btn btn-primary btn-full" style="margin-top:4px;">
          Create Account
        </button>

      </form>
      <?php endif; ?>

      <p style="text-align:center;font-size:12px;color:var(--text3);margin-top:20px;">
        Already have an account?
        <a href="<?= APP_URL ?>/auth/login" style="color:var(--teal);">Sign in</a>
        <br>
        Staff or Admin? 
        <a href="<?= APP_URL ?>/auth/registerStaff" style="color:var(--teal);">Register here</a>
      </p>

    </div>
  </div>
</div>

<script src="<?= APP_URL ?>/public/js/app.js"></script>
<script>
const pw  = document.getElementById('pw');
const pw2 = document.getElementById('pw2');
const hint = document.getElementById('pw-hint');
if (pw2) {
  pw2.addEventListener('input', () => {
    if (!pw2.value) { hint.textContent = ''; return; }
    if (pw.value === pw2.value) {
      hint.textContent = '✓ Passwords match';
      hint.style.color = 'var(--green)';
    } else {
      hint.textContent = '✗ Passwords do not match';
      hint.style.color = 'var(--red)';
    }
  });
}
</script>
</body>
</html>
