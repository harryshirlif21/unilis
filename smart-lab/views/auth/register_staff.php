<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Staff Registration - UNILIS SmartLab</title>
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
      <span>staff</span><br>
      team.
    </h1>
    <p class="auth-desc">
      Create your staff account to manage laboratory operations,
      supervise practicals, grade reports, and maintain the
      institution's laboratory ecosystem.
    </p>
    <div class="auth-features">
      <div class="auth-feature">
        <div class="auth-feature-dot"></div>
        Manage practicals and schedules
      </div>
      <div class="auth-feature">
        <div class="auth-feature-dot"></div>
        Grade student reports and notebooks
      </div>
      <div class="auth-feature">
        <div class="auth-feature-dot"></div>
        Supervise lab operations and assets
      </div>
    </div>
  </div>

  <!-- Right form panel -->
  <div class="auth-right">
    <div class="auth-form-container">
      <div class="auth-form-header">
        <h2>Staff / Admin Registration</h2>
        <p>Create your staff account to access SmartLab management features</p>
      </div>

      <?php if (isset($error) && $error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if (isset($success) && $success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <form method="POST" action="<?= APP_URL ?>/auth/registerStaff" class="auth-form">
        <input type="hidden" name="account_type" value="staff">
        
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Full Name</label>
            <input type="text" name="full_name" class="form-control" 
              placeholder="Enter your full name" required
              value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
          </div>
          
          <div class="form-group">
            <label class="form-label">Staff / Employee No</label>
            <input type="text" name="reg_number" class="form-control" 
              placeholder="e.g. STAFF/2025/001" required
              value="<?= htmlspecialchars($_POST['reg_number'] ?? '') ?>">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Email Address</label>
          <input type="email" name="email" class="form-control" 
            placeholder="your.email@unilis.ac.ke" required
            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Role</label>
            <select name="role" class="form-control" required>
              <option value="">Select Role</option>
              <option value="admin" <?= (($_POST['role'] ?? '') === 'admin') ? 'selected' : '' ?>>Administrator</option>
              <option value="lecturer" <?= (($_POST['role'] ?? '') === 'lecturer') ? 'selected' : '' ?>>Lecturer</option>
              <option value="technician" <?= (($_POST['role'] ?? '') === 'technician') ? 'selected' : '' ?>>Lab Technician</option>
            </select>
          </div>
          
          <div class="form-group">
            <label class="form-label">Department</label>
            <input type="text" name="department" class="form-control" 
              placeholder="e.g. Computer Science" required
              value="<?= htmlspecialchars($_POST['department'] ?? '') ?>">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Laboratory Assignment</label>
          <select name="lab_id" class="form-control" required>
            <option value="">Select Laboratory</option>
            <?php if (isset($labs) && !empty($labs)): ?>
              <?php foreach ($labs as $lab): ?>
                <option value="<?= htmlspecialchars($lab['id']) ?>" 
                  <?= (($_POST['lab_id'] ?? '') === $lab['id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($lab['name']) ?> (<?= htmlspecialchars($lab['lab_code']) ?>)
                </option>
              <?php endforeach; ?>
            <?php endif; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">Staff Registration Key</label>
          <input type="password" name="staff_key" class="form-control" 
            placeholder="Enter staff access key" required>
          <small class="form-help">Contact your administrator for the staff registration key</small>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" 
              placeholder="Create a strong password" required minlength="8">
          </div>
          
          <div class="form-group">
            <label class="form-label">Confirm Password</label>
            <input type="password" name="password_confirm" class="form-control" 
              placeholder="Confirm your password" required minlength="8">
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-full">
          Create Staff Account
        </button>
      </form>

      <div class="auth-form-footer">
        <p>Student? <a href="<?= APP_URL ?>/auth/register">Register here</a></p>
        <p>Already have an account? <a href="<?= APP_URL ?>/auth/login">Sign in</a></p>
      </div>
    </div>
  </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const form = document.querySelector('.auth-form');
  const roleSelect = document.querySelector('select[name="role"]');
  const labGroup = document.querySelector('select[name="lab_id"]').closest('.form-group');
  
  // Show/hide lab assignment based on role
  function toggleLabField() {
    const role = roleSelect.value;
    if (role === 'admin') {
      labGroup.style.display = 'none';
      document.querySelector('select[name="lab_id"]').required = false;
    } else {
      labGroup.style.display = 'block';
      document.querySelector('select[name="lab_id"]').required = true;
    }
  }
  
  roleSelect.addEventListener('change', toggleLabField);
  toggleLabField(); // Initial check
  
  // Form validation
  form.addEventListener('submit', function(e) {
    const password = form.querySelector('input[name="password"]').value;
    const passwordConfirm = form.querySelector('input[name="password_confirm"]').value;
    
    if (password !== passwordConfirm) {
      e.preventDefault();
      alert('Passwords do not match. Please confirm your password.');
      return false;
    }
    
    if (password.length < 8) {
      e.preventDefault();
      alert('Password must be at least 8 characters long.');
      return false;
    }
  });
});
</script>

</body>
</html>
