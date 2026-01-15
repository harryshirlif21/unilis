<?php
session_start();
include '../actions.php';

// Initialize variables
$error = '';
$success = '';
$old_input = [];

if (isset($_SESSION['signup_errors'])) {
    $error = implode('<br>', $_SESSION['signup_errors']);
    unset($_SESSION['signup_errors']);
}

if (isset($_SESSION['signup_success'])) {
    $success = $_SESSION['signup_success'];
    unset($_SESSION['signup_success']);
}

if (isset($_SESSION['old_input'])) {
    $old_input = $_SESSION['old_input'];
    unset($_SESSION['old_input']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Student Signup - JKUAT</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    :root {
      --primary: #2563eb;
      --primary-dark: #1d4ed8;
      --success: #10b981;
      --danger: #ef4444;
      --gray-100: #f3f4f6;
      --gray-200: #e5e7eb;
      --gray-500: #6b7280;
      --gray-700: #374151;
      --gray-900: #111827;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      padding: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .container {
      background: white;
      max-width: 560px;
      width: 100%;
      border-radius: 16px;
      box-shadow: 0 20px 50px rgba(0,0,0,0.15);
      overflow: hidden;
    }

    .header {
      background: var(--primary);
      color: white;
      padding: 24px;
      text-align: center;
      position: relative;
    }

    .home-btn {
      position: absolute;
      left: 20px;
      top: 50%;
      transform: translateY(-50%);
      background: rgba(255,255,255,0.2);
      color: white;
      border: none;
      width: 44px;
      height: 44px;
      border-radius: 50%;
      cursor: pointer;
      font-size: 20px;
      transition: 0.3s;
    }
    .home-btn:hover { background: rgba(255,255,255,0.3); }

    .header h1 { font-size: 28px; font-weight: 700; margin-bottom: 6px; }
    .header p { opacity: 0.9; font-size: 15px; }

    .step-indicator {
      display: flex;
      justify-content: center;
      padding: 30px 40px 10px;
      gap: 16px;
    }
    .step {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      background: var(--gray-200);
      color: var(--gray-500);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 600;
      font-size: 16px;
      position: relative;
      transition: all 0.3s;
    }
    .step::before {
      content: '';
      position: absolute;
      top: 50%;
      left: 44px;
      width: 60px;
      height: 3px;
      background: var(--gray-200);
      z-index: -1;
    }
    .step:last-child::before { display: none; }
    .step.active {
      background: var(--primary);
      color: white;
      transform: scale(1.15);
    }
    .step.completed {
      background: var(--success);
      color: white;
    }
    .step.completed::after {
      content: "✓";
      font-weight: bold;
    }
    .step.active ~ .step::before,
    .step.completed ~ .step::before { background: var(--gray-200); }
    .step:not(.active):not(.completed)::before { background: var(--gray-200); }

    .form-body { padding: 40px; }
    .form-step { display: none; animation: fadeIn 0.5s; }
    .form-step.active { display: block; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }

    .form-group { margin-bottom: 24px; }
    label {
      display: block;
      margin-bottom: 8px;
      font-weight: 600;
      color: var(--gray-700);
      font-size: 14px;
    }
    input, select {
      width: 100%;
      padding: 14px 16px;
      border: 2px solid var(--gray-200);
      border-radius: 12px;
      font-size: 16px;
      transition: 0.3s;
    }
    input:focus, select:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 4px rgba(37,99,235,0.1);
    }

    .password-wrapper {
      position: relative;
    }
    .toggle-password {
      position: absolute;
      right: 14px;
      top: 50%;
      transform: translateY(-50%);
      cursor: pointer;
      color: var(--gray-500);
      font-size: 18px;
    }

    .password-strength {
      margin-top: 10px;
      height: 8px;
      border-radius: 4px;
      background: var(--gray-200);
      overflow: hidden;
    }
    .strength-bar {
      height: 100%;
      width: 0%;
      transition: width 0.4s ease;
    }
    .strength-text {
      margin-top: 8px;
      font-size: 14px;
      font-weight: 500;
    }

    /* Strength levels */
    .weak .strength-bar { background: var(--danger); width: 25%; }
    .medium .strength-bar { background: #f59e0b; width: 50%; }
    .strong .strength-bar { background: #10b981; width: 75%; }
    .very-strong .strength-bar { background: #059669; width: 100%; }

    .success, .error {
      padding: 12px;
      border-radius: 8px;
      margin-bottom: 20px;
      text-align: center;
      font-weight: 500;
    }
    .success { background: #d1fae5; color: #065f46; }
    .error { background: #fee2e2; color: #991b1b; }

    .btn-group {
      display: flex;
      gap: 12px;
      margin-top: 32px;
      justify-content: space-between;
    }
    button {
      padding: 14px 24px;
      border: none;
      border-radius: 12px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: 0.3s;
      flex: 1;
    }
    .btn-back {
      background: var(--gray-200);
      color: var(--gray-700);
    }
    .btn-next, #submitBtn {
      background: var(--primary);
      color: white;
    }
    .btn-next:hover, #submitBtn:hover:not(:disabled) {
      background: var(--primary-dark);
      transform: translateY(-2px);
    }
    #submitBtn:disabled {
      background: var(--gray-500);
      cursor: not-allowed;
    }

    @media (max-width: 480px) {
      .container { margin: 10px; }
      .form-body { padding: 24px; }
      .step-indicator { padding: 20px; gap: 10px; }
      .step { width: 38px; height: 38px; }
      .step::before { width: 40px; }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <button type="button" class="home-btn" onclick="window.location.href='index.html'">
        <i class="fas fa-home"></i>
      </button>
      <h1>Student Registration</h1>
      <p>Join JKUAT Student Portal</p>
    </div>

    <div class="step-indicator">
      <div class="step active">1</div>
      <div class="step">2</div>
      <div class="step">3</div>
      <div class="step">4</div>
      <div class="step">5</div>
    </div>

    <div class="form-body">
      <?php if (!empty($success)): ?><div class="success"><?= $success ?></div><?php endif; ?>
      <?php if (!empty($error)): ?><div class="error"><?= $error ?></div><?php endif; ?>

      <form method="POST" id="signupForm" novalidate>
        <input type="hidden" name="action" value="signup_student">
        <input type="hidden" name="university" value="JKUAT">

        <!-- Step 1 -->
        <div class="form-step active">
          <div class="form-group">
            <label>Registration Number <span style="color:var(--danger)">*</span></label>
            <input type="text" name="reg_no" required placeholder="e.g. CS/001/2024"
                   value="<?= htmlspecialchars($old_input['reg_no'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Full Name <span style="color:var(--danger)">*</span></label>
            <input type="text" name="name" required placeholder="John Doe"
                   value="<?= htmlspecialchars($old_input['name'] ?? '') ?>">
          </div>
        </div>

        <!-- Step 2 -->
        <div class="form-step">
          <div class="form-group">
            <label>Email Address <span style="color:var(--danger)">*</span></label>
            <input type="email" name="email" required placeholder="student@jkuat.ac.ke"
                   value="<?= htmlspecialchars($old_input['email'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>School / Faculty <span style="color:var(--danger)">*</span></label>
            <select name="school" required>
              <option value="">-- Select School --</option>
              <option value="School of Computing & IT" <?= (isset($old_input['school']) && $old_input['school']=='School of Computing & IT')?'selected':'' ?>>School of Computing & IT</option>
              <option value="School of Engineering" <?= (isset($old_input['school']) && $old_input['school']=='School of Engineering')?'selected':'' ?>>School of Engineering</option>
              <option value="School of Business" <?= (isset($old_input['school']) && $old_input['school']=='School of Business')?'selected':'' ?>>School of Business</option>
              <option value="School of Health Sciences" <?= (isset($old_input['school']) && $old_input['school']=='School of Health Sciences')?'selected':'' ?>>School of Health Sciences</option>
            </select>
          </div>
        </div>

        <!-- Step 3 -->
        <div class="form-step">
          <div class="form-group">
            <label>Department <span style="color:var(--danger)">*</span></label>
            <select name="department" required>
              <option value="">-- Select Department --</option>
              <?php
              $res = $conn->query("SELECT * FROM departments");
              while ($row = $res->fetch_assoc()) {
                  $selected = (isset($old_input['department']) && $old_input['department']==$row['id']) ? 'selected' : '';
                  echo "<option value='{$row['id']}' $selected>{$row['name']}</option>";
              }
              ?>
            </select>
          </div>
          <div class="form-group">
            <label>Course <span style="color:var(--danger)">*</span></label>
            <select name="course" required>
              <option value="">-- Select Course --</option>
              <?php
              $res = $conn->query("SELECT * FROM courses");
              while ($row = $res->fetch_assoc()) {
                  $selected = (isset($old_input['course']) && $old_input['course']==$row['id']) ? 'selected' : '';
                  echo "<option value='{$row['id']}' $selected>{$row['name']}</option>";
              }
              ?>
            </select>
          </div>
        </div>

        <!-- Step 4 -->
        <div class="form-step">
          <div class="form-group">
            <label>Year of Study <span style="color:var(--danger)">*</span></label>
            <input type="number" name="year_of_study" min="1" max="6" required placeholder="e.g. 3"
                   value="<?= htmlspecialchars($old_input['year_of_study'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Year Joined <span style="color:var(--danger)">*</span></label>
            <input type="number" name="year_joined" min="2000" max="<?= date('Y') ?>" required
                   placeholder="<?= date('Y') ?>"
                   value="<?= htmlspecialchars($old_input['year_joined'] ?? '') ?>">
          </div>
        </div>

        <!-- Step 5 -->
        <div class="form-step">
          <div class="form-group">
            <label>Password <span style="color:var(--danger)">*</span></label>
            <div class="password-wrapper">
              <input type="password" name="password" id="password" required placeholder="Create a very strong password">
              <i class="fas fa-eye toggle-password" id="togglePassword"></i>
            </div>
            <div class="password-strength"><div class="strength-bar"></div></div>
            <div class="strength-text" id="strengthText">Enter a password (must be very strong)</div>
          </div>
          <div class="form-group">
            <label>Confirm Password <span style="color:var(--danger)">*</span></label>
            <input type="password" name="confirm_password" id="confirmPassword" required placeholder="Re-type password">
          </div>
        </div>

        <div class="btn-group">
          <button type="button" class="btn-back" id="prevBtn">Back</button>
          <button type="button" class="btn-next" id="nextBtn">Next</button>
          <button type="submit" id="submitBtn" class="btn-next" disabled>Register</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    const steps = document.querySelectorAll('.form-step');
    const indicators = document.querySelectorAll('.step');
    const nextBtn = document.getElementById('nextBtn');
    const prevBtn = document.getElementById('prevBtn');
    const submitBtn = document.getElementById('submitBtn');
    const passwordInput = document.getElementById('password');
    const confirmInput = document.getElementById('confirmPassword');
    const strengthBar = document.querySelector('.strength-bar');
    const strengthText = document.getElementById('strengthText');
    const togglePassword = document.getElementById('togglePassword');
    let currentStep = 0;

    function showStep(n) {
      steps.forEach((s, i) => s.classList.toggle('active', i === n));
      indicators.forEach((ind, i) => {
        ind.classList.remove('active', 'completed');
        if (i < n) ind.classList.add('completed');
        if (i === n) ind.classList.add('active');
      });
      prevBtn.style.display = n === 0 ? 'none' : 'block';
      nextBtn.style.display = n === steps.length - 1 ? 'none' : 'block';
      submitBtn.style.display = n === steps.length - 1 ? 'block' : 'none';
      currentStep = n;
    }

    function validateCurrentStep() {
      const inputs = steps[currentStep].querySelectorAll('input[required], select[required]');
      let valid = true;
      inputs.forEach(input => { if (!input.value.trim()) valid = false; });
      return valid;
    }

    nextBtn.addEventListener('click', () => {
      if (validateCurrentStep() && currentStep < steps.length - 1) {
        showStep(currentStep + 1);
      }
    });

    prevBtn.addEventListener('click', () => {
      if (currentStep > 0) showStep(currentStep - 1);
    });

    passwordInput.addEventListener('input', () => {
      const val = passwordInput.value;
      let score = 0;
      if (val.length >= 12) score++;
      if (val.length >= 16) score++;
      if (/[a-z]/.test(val)) score++;
      if (/[A-Z]/.test(val)) score++;
      if (/[0-9]/.test(val)) score++;
      if (/[^A-Za-z0-9]/.test(val)) score++;

      let level = 'weak', text = 'Too weak';
      if (score >= 5) { level = 'very-strong'; text = 'Very Strong - Excellent!'; }
      else if (score >= 4) { level = 'strong'; text = 'Strong'; }
      else if (score >= 3) { level = 'medium'; text = 'Medium'; }

      strengthBar.parentElement.className = 'password-strength ' + level;
      strengthText.textContent = text;
      submitBtn.disabled = score < 4 || val !== confirmInput.value;
    });

    confirmInput.addEventListener('input', () => {
      submitBtn.disabled = passwordInput.value !== confirmInput.value || passwordInput.value.length < 8;
    });

    togglePassword.addEventListener('click', () => {
      const type = passwordInput.type === 'password' ? 'text' : 'password';
      passwordInput.type = type;
      confirmInput.type = type;
      togglePassword.classList.toggle('fa-eye-slash');
    });

    showStep(currentStep);
  </script>
</body>
</html>
