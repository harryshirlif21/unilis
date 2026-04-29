<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Select Student — UNILIS SmartLab</title>
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
      Select your<br>
      <span>identity</span><br>
      for this session.
    </h1>
    <p class="auth-desc">
      Choose your profile from the list below to access the laboratory session.
      This ensures proper tracking and accountability during practical sessions.
    </p>

    <div class="auth-features">
      <div class="auth-feature">
        <div class="auth-feature-dot"></div>
        Secure session management
      </div>
      <div class="auth-feature">
        <div class="auth-feature-dot"></div>
        Activity tracking & audit logs
      </div>
      <div class="auth-feature">
        <div class="auth-feature-dot"></div>
        Personalized lab experience
      </div>
    </div>
  </div>

  <!-- Right form panel -->
  <div class="auth-right">
    <div class="auth-box">

      <div class="auth-title">Select Your Profile</div>
      <div class="auth-subtitle">
        Lab: <?= htmlspecialchars($session['lab_name']) ?> | 
        Session: <?= htmlspecialchars(substr($session['id'], 0, 8)) ?>
      </div>

      <?php if (isset($error) && $error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST" action="<?= APP_URL ?>/auth/select-student">
        <div class="student-list">
          <?php foreach ($students as $student): ?>
            <div class="student-card">
              <label class="student-radio">
                <input type="radio" name="student_id" value="<?= htmlspecialchars($student['id']) ?>" required>
                <div class="student-info">
                  <div class="student-name"><?= htmlspecialchars($student['full_name']) ?></div>
                  <div class="student-details">
                    <span class="student-reg"><?= htmlspecialchars($student['reg_number']) ?></span>
                    <span class="student-role">Student</span>
                  </div>
                </div>
                <div class="student-avatar">
                  <i class="fas fa-user"></i>
                </div>
              </label>
            </div>
          <?php endforeach; ?>
        </div>

        <button type="submit" class="btn btn-primary btn-full" style="margin-top:20px;">
          <i class="fas fa-sign-in-alt"></i> Continue to Lab
        </button>
      </form>

      <div class="alert alert-info" style="font-size:12px;margin-top:16px;">
        <i class="fas fa-info-circle"></i> 
        Select your profile to begin the laboratory session. All activities will be logged under your account.
      </div>

    </div>
  </div>
</div>

<script src="<?= APP_URL ?>/public/js/app.js"></script>
<script>
// Auto-select first student if only one exists
const studentCards = document.querySelectorAll('.student-card');
if (studentCards.length === 1) {
  const radio = studentCards[0].querySelector('input[type="radio"]');
  if (radio) radio.checked = true;
}

// Visual feedback for selection
document.querySelectorAll('.student-radio').forEach(radio => {
  radio.addEventListener('change', function() {
    document.querySelectorAll('.student-card').forEach(card => {
      card.classList.remove('selected');
    });
    if (this.checked) {
      this.closest('.student-card').classList.add('selected');
    }
  });
});
</script>

<style>
.student-list {
  margin: 20px 0;
  max-height: 300px;
  overflow-y: auto;
}

.student-card {
  border: 2px solid var(--border);
  border-radius: 8px;
  margin-bottom: 12px;
  transition: all 0.2s ease;
}

.student-card:hover {
  border-color: var(--primary);
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.student-card.selected {
  border-color: var(--primary);
  background: rgba(59, 130, 246, 0.05);
}

.student-radio {
  display: flex;
  align-items: center;
  padding: 16px;
  cursor: pointer;
  width: 100%;
}

.student-radio input[type="radio"] {
  margin-right: 12px;
}

.student-info {
  flex: 1;
}

.student-name {
  font-weight: 600;
  color: var(--text);
  margin-bottom: 4px;
}

.student-details {
  display: flex;
  gap: 12px;
  font-size: 13px;
}

.student-reg {
  color: var(--text2);
  font-family: 'DM Mono', monospace;
}

.student-role {
  background: var(--primary);
  color: white;
  padding: 2px 8px;
  border-radius: 12px;
  font-size: 11px;
}

.student-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: var(--gray);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 16px;
}
</style>

</body>
</html>
