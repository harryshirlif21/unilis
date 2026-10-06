<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/mailer.php';

if (empty($_SESSION['student_signup_csrf'])) {
    $_SESSION['student_signup_csrf'] = bin2hex(random_bytes(32));
}
if (
    !empty($_SESSION['student_signup_verified_at'])
    && time() - (int)$_SESSION['student_signup_verified_at'] > 1800
) {
    unset($_SESSION['student_signup_verified_email'], $_SESSION['student_signup_verified_at']);
}

$emailGateNotice = '';
$emailGateError = '';
$emailToken = trim((string)($_GET['email_token'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['request_signup_email', 'confirm_signup_email'], true)) {
    if (
        !isset($_POST['csrf_token'])
        || !hash_equals($_SESSION['student_signup_csrf'], (string)$_POST['csrf_token'])
    ) {
        http_response_code(400);
        exit('Invalid registration request. Reload and try again.');
    }

    if ($_POST['action'] === 'request_signup_email') {
        $email = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
        if (!$email) {
            $emailGateError = 'Enter a valid email address.';
        } else {
            $email = strtolower($email);
            $existingStmt = $conn->prepare('SELECT id FROM students WHERE email = ? LIMIT 1');
            $existingStmt->bind_param('s', $email);
            $existingStmt->execute();
            $emailAlreadyUsed = $existingStmt->get_result()->num_rows > 0;
            $existingStmt->close();

            $recentStmt = $conn->prepare("
                SELECT created_at
                FROM student_signup_email_tokens
                WHERE email = ? AND created_at > DATE_SUB(NOW(), INTERVAL 60 SECOND)
                LIMIT 1
            ");
            $recentStmt->bind_param('s', $email);
            $recentStmt->execute();
            $recentRequest = $recentStmt->get_result()->num_rows > 0;
            $recentStmt->close();

            if (!$emailAlreadyUsed && !$recentRequest) {
                $token = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $token);
                $insertStmt = $conn->prepare("
                    INSERT INTO student_signup_email_tokens (email, token_hash, expires_at, created_at, used_at)
                    VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE), NOW(), NULL)
                    ON DUPLICATE KEY UPDATE
                        token_hash = VALUES(token_hash),
                        expires_at = VALUES(expires_at),
                        created_at = VALUES(created_at),
                        used_at = NULL
                ");
                $insertStmt->bind_param('ss', $email, $tokenHash);
                $insertStmt->execute();
                $insertStmt->close();

                if (!send_student_registration_start_email($email, $token)) {
                    $deleteStmt = $conn->prepare('DELETE FROM student_signup_email_tokens WHERE email = ? AND token_hash = ?');
                    $deleteStmt->bind_param('ss', $email, $tokenHash);
                    $deleteStmt->execute();
                    $deleteStmt->close();
                    error_log('Could not send student registration confirmation email.');
                }
            }

            $emailGateNotice = 'If this address can be used to register, a confirmation email will arrive shortly. Check your inbox and spam folder.';
            $_SESSION['signup_email_gate_notice'] = $emailGateNotice;
            header('Location: signup.php');
            exit;
        }
    } elseif ($_POST['action'] === 'confirm_signup_email') {
        $confirmationToken = trim((string)($_POST['email_token'] ?? ''));
        if (!preg_match('/^[a-f0-9]{64}$/i', $confirmationToken)) {
            $emailGateError = 'This confirmation link is invalid or expired. Request a new one.';
        } else {
            $tokenHash = hash('sha256', $confirmationToken);
            $tokenStmt = $conn->prepare("
                SELECT id, email
                FROM student_signup_email_tokens
                WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()
                LIMIT 1
            ");
            $tokenStmt->bind_param('s', $tokenHash);
            $tokenStmt->execute();
            $tokenRecord = $tokenStmt->get_result()->fetch_assoc();
            $tokenStmt->close();

            if (!$tokenRecord) {
                $emailGateError = 'This confirmation link is invalid, expired, or already used. Request a new one.';
            } else {
                $consumeStmt = $conn->prepare("
                    UPDATE student_signup_email_tokens
                    SET used_at = NOW()
                    WHERE id = ? AND used_at IS NULL AND expires_at > NOW()
                ");
                $tokenRecordId = (int)$tokenRecord['id'];
                $consumeStmt->bind_param('i', $tokenRecordId);
                $consumeStmt->execute();
                $consumed = $consumeStmt->affected_rows === 1;
                $consumeStmt->close();

                if (!$consumed) {
                    $emailGateError = 'This confirmation link is invalid, expired, or already used. Request a new one.';
                } else {
                    $_SESSION['student_signup_verified_email'] = (string)$tokenRecord['email'];
                    $_SESSION['student_signup_verified_at'] = time();
                    $_SESSION['signup_email_gate_notice'] = 'Email confirmed. You can now complete your student registration.';
                    header('Location: signup.php');
                    exit;
                }
            }
        }
    }
}

if (!empty($_SESSION['signup_email_gate_notice'])) {
    $emailGateNotice = (string)$_SESSION['signup_email_gate_notice'];
    unset($_SESSION['signup_email_gate_notice']);
}

if ($emailToken !== '' && empty($_SESSION['student_signup_verified_email'])) {
    $tokenIsValid = false;
    if (preg_match('/^[a-f0-9]{64}$/i', $emailToken)) {
        $tokenHash = hash('sha256', $emailToken);
        $tokenStmt = $conn->prepare("
            SELECT id
            FROM student_signup_email_tokens
            WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()
            LIMIT 1
        ");
        $tokenStmt->bind_param('s', $tokenHash);
        $tokenStmt->execute();
        $tokenIsValid = $tokenStmt->get_result()->num_rows === 1;
        $tokenStmt->close();
    }

    ?><!doctype html>
    <html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Confirm email · UNILIS</title>
    <style>body{font:16px/1.5 Arial,sans-serif;background:#f3f4f6;margin:0;padding:24px;color:#1f2937}.card{max-width:480px;margin:10vh auto;background:#fff;padding:30px;border-radius:12px;box-shadow:0 8px 30px #0001;text-align:center}button{background:#1e3a8a;color:#fff;border:0;border-radius:8px;padding:13px 20px;font-size:16px;cursor:pointer;width:100%}a{color:#1e3a8a}</style></head>
    <body><main class="card"><h1>Confirm your email</h1>
    <?php if ($tokenIsValid): ?>
        <p>Confirm that you own this email address to begin student registration.</p>
        <form method="post">
            <input type="hidden" name="action" value="confirm_signup_email">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['student_signup_csrf'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="email_token" value="<?= htmlspecialchars($emailToken, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit">Confirm email and start registration</button>
        </form>
    <?php else: ?>
        <p>This confirmation link is invalid, expired, or already used. Request another link to continue.</p>
        <a href="signup.php">Request a new link</a>
    <?php endif; ?>
    </main></body></html><?php
    exit;
}

if (empty($_SESSION['student_signup_verified_email'])) {
    ?>
    <!doctype html>
    <html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Confirm email · Student Registration</title>
    <script src="https://cdn.tailwindcss.com"></script></head>
    <body class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center p-4">
    <main class="w-full max-w-lg bg-white rounded-2xl shadow-xl p-8">
        <h1 class="text-2xl font-bold text-blue-900">Start student registration</h1>
        <p class="mt-3 text-gray-600">Confirm your email address first. We’ll send a one-time link that unlocks the registration form for 30 minutes.</p>
        <?php if ($emailGateNotice !== ''): ?>
            <div class="mt-5 rounded-lg bg-blue-50 border border-blue-200 text-blue-900 p-4"><?= htmlspecialchars($emailGateNotice, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($emailGateError !== ''): ?>
            <div class="mt-5 rounded-lg bg-red-50 border border-red-200 text-red-800 p-4"><?= htmlspecialchars($emailGateError, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <form method="post" class="mt-6">
            <input type="hidden" name="action" value="request_signup_email">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['student_signup_csrf'], ENT_QUOTES, 'UTF-8') ?>">
            <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">Email address</label>
            <input id="email" type="email" name="email" autocomplete="email" required class="w-full border border-gray-300 rounded-lg px-4 py-3" placeholder="you@example.com">
            <button type="submit" class="mt-4 w-full bg-blue-900 text-white rounded-lg px-4 py-3 font-semibold">Send confirmation link</button>
        </form>
        <p class="mt-5 text-sm text-gray-600"><a href="../login.php" class="text-blue-800 underline">Back to login</a></p>
    </main></body></html>
    <?php
    exit;
}

include '../actions.php';

// Handle redirect parameter
$redirect = $_GET['redirect'] ?? '';

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
if ($emailGateNotice !== '' && !empty($_SESSION['student_signup_verified_email'])) {
    $success = $emailGateNotice;
}

if (isset($_SESSION['old_input'])) {
    $old_input = $_SESSION['old_input'];
    unset($_SESSION['old_input']);
}

// ── Fetch departments from DB ────────────────────────────────────────────────
$departments = [];
$deptResult = $conn->query("SELECT id, name FROM departments ORDER BY name");
while ($d = $deptResult->fetch_assoc()) {
    $departments[] = $d;
}

// ── Fetch courses grouped by department ──────────────────────────────────────
$allCourses = [];
$courseResult = $conn->query("SELECT id, name, department_id FROM courses ORDER BY name");
while ($c = $courseResult->fetch_assoc()) {
    $deptId = (int)$c['department_id'];
    if (!isset($allCourses[$deptId])) {
        $allCourses[$deptId] = [];
    }
    $allCourses[$deptId][] = $c;
}
$coursesJson = json_encode($allCourses);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Student Signup - JKUAT</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            navy: '#1e3a8a',
            gold: '#d4af37',
          }
        }
      }
    }
  </script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 py-8 px-4">
  <div class="max-w-2xl mx-auto bg-white rounded-2xl shadow-xl overflow-hidden">
    <div class="bg-navy text-white p-6 text-center relative">
      <button type="button" class="absolute left-4 top-1/2 transform -translate-y-1/2 bg-white/20 hover:bg-white/30 rounded-full w-10 h-10 flex items-center justify-center transition" onclick="window.location.href='../index.html'">
        <i class="fas fa-home text-white"></i>
      </button>
      <h1 class="text-2xl font-bold">Student Registration</h1>
      <p class="text-blue-100 mt-1">Join JKUAT Student Portal</p>
    </div>

    <div class="flex justify-center py-6 px-6">
      <div class="flex items-center space-x-4">
        <div class="step active">1</div>
        <div class="step">2</div>
        <div class="step">3</div>
        <div class="step">4</div>
        <div class="step">5</div>
      </div>
    </div>

    <div class="px-8 pb-8">
      <?php if (!empty($success)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
          <?= $success ?>
        </div>
      <?php endif; ?>
      <?php if (!empty($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
          <?= $error ?>
        </div>
      <?php endif; ?>

      <form method="POST" id="signupForm" novalidate>
        <input type="hidden" name="action" value="signup_student">
        <input type="hidden" name="university" value="JKUAT">
        <input type="hidden" name="signup_csrf_token" value="<?= htmlspecialchars($_SESSION['student_signup_csrf'], ENT_QUOTES, 'UTF-8') ?>">

        <!-- Step 1: Personal Details -->
        <div class="form-step active">
          <div class="mb-6">
            <label class="block text-gray-700 text-sm font-semibold mb-2">
              Registration Number <span class="text-red-500">*</span>
            </label>
            <input type="text" name="reg_no" required placeholder="e.g. CS/001/2024"
                   value="<?= htmlspecialchars($old_input['reg_no'] ?? '') ?>"
                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-navy focus:border-transparent transition">
          </div>
          <div class="mb-6">
            <label class="block text-gray-700 text-sm font-semibold mb-2">
              Full Name <span class="text-red-500">*</span>
            </label>
            <input type="text" name="name" required placeholder="John Doe"
                   value="<?= htmlspecialchars($old_input['name'] ?? '') ?>"
                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-navy focus:border-transparent transition">
          </div>
        </div>

        <!-- Step 2: Contact Details -->
        <div class="form-step">
          <div class="mb-6">
            <label class="block text-gray-700 text-sm font-semibold mb-2">
              Email Address <span class="text-red-500">*</span>
            </label>
            <input type="email" name="email" required readonly
                   value="<?= htmlspecialchars($_SESSION['student_signup_verified_email'], ENT_QUOTES, 'UTF-8') ?>"
                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-navy focus:border-transparent transition">
          </div>
          <div class="mb-6">
            <label class="block text-gray-700 text-sm font-semibold mb-2">
              Phone Number <span class="text-red-500">*</span>
            </label>
            <input type="tel" name="phone" required placeholder="+254 712 345 678"
                   value="<?= htmlspecialchars($old_input['phone'] ?? '') ?>"
                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-navy focus:border-transparent transition">
          </div>
        </div>

        <!-- Step 3: Department & Course -->
        <div class="form-step">
          <div class="mb-6">
            <label class="block text-gray-700 text-sm font-semibold mb-2">
              Department <span class="text-red-500">*</span>
            </label>
            <select name="department" id="departmentSelect" required
                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-navy focus:border-transparent transition">
              <option value="">Select your department</option>
              <?php foreach ($departments as $dept):
                $sel = (int)($old_input['department'] ?? 0) === (int)$dept['id'] ? 'selected' : ''; ?>
                <option value="<?= (int)$dept['id'] ?>" <?= $sel ?>><?= htmlspecialchars($dept['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-6">
            <label class="block text-gray-700 text-sm font-semibold mb-2">
              Course/Program <span class="text-red-500">*</span>
            </label>
            <select name="course" id="courseSelect" required
                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-navy focus:border-transparent transition">
              <option value="">First select a department</option>
            </select>
          </div>
        </div>

        <!-- Step 4: Year of Study & Year Joined -->
        <div class="form-step">
          <div class="mb-6">
            <label class="block text-gray-700 text-sm font-semibold mb-2">
              Year of Study <span class="text-red-500">*</span>
            </label>
            <select name="year_of_study" required
                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-navy focus:border-transparent transition">
              <option value="">Select year</option>
              <?php for ($y = 1; $y <= 6; $y++):
                $sel = ($old_input['year_of_study'] ?? '') === (string)$y ? 'selected' : ''; ?>
                <option value="<?= $y ?>" <?= $sel ?>>Year <?= $y ?></option>
              <?php endfor; ?>
            </select>
          </div>
          <div class="mb-6">
            <label class="block text-gray-700 text-sm font-semibold mb-2">
              Year Joined <span class="text-red-500">*</span>
            </label>
            <select name="year_joined" required
                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-navy focus:border-transparent transition">
              <option value="">Select year joined</option>
              <?php $currentYear = (int)date('Y');
              for ($y = $currentYear; $y >= $currentYear - 6; $y--):
                $sel = ($old_input['year_joined'] ?? '') === (string)$y ? 'selected' : ''; ?>
                <option value="<?= $y ?>" <?= $sel ?>><?= $y ?></option>
              <?php endfor; ?>
            </select>
          </div>
        </div>

        <!-- Step 5: Password -->
        <div class="form-step">
          <div class="mb-6">
            <label class="block text-gray-700 text-sm font-semibold mb-2">
              Password <span class="text-red-500">*</span>
            </label>
            <div class="relative">
              <input type="password" name="password" id="password" required placeholder="Create a strong password (min 8 chars)"
                     class="w-full px-4 py-3 pr-12 border-2 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-navy focus:border-transparent transition">
              <i class="fas fa-eye absolute right-4 top-4 cursor-pointer text-gray-400 hover:text-gray-600" id="togglePassword"></i>
            </div>
            <div class="password-strength mt-2">
              <div class="strength-bar"></div>
            </div>
            <div class="strength-text text-sm text-gray-600 mt-1">Password strength: <span id="strengthText">Weak</span></div>
          </div>
          <div class="mb-6">
            <label class="block text-gray-700 text-sm font-semibold mb-2">
              Confirm Password <span class="text-red-500">*</span>
            </label>
            <input type="password" name="confirm_password" required placeholder="Confirm your password"
                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-navy focus:border-transparent transition">
          </div>
          <fieldset class="mb-5 space-y-4 rounded-lg border border-gray-200 bg-gray-50 p-4">
            <legend class="px-2 font-semibold text-gray-800">Your consent <span class="text-red-500">*</span></legend>
            <label class="flex items-start gap-3 text-sm text-gray-700">
              <input type="checkbox" name="terms_consent" value="1" required <?= ($old_input['terms_consent'] ?? '') === '1' ? 'checked' : '' ?> class="mt-1 h-4 w-4 accent-blue-900">
              <span>I agree to the terms and conditions for using the UNILIS student portal.</span>
            </label>
            <label class="flex items-start gap-3 text-sm text-gray-700">
              <input type="checkbox" name="privacy_consent" value="1" required <?= ($old_input['privacy_consent'] ?? '') === '1' ? 'checked' : '' ?> class="mt-1 h-4 w-4 accent-blue-900">
              <span>I have read the <a href="../output/pdf/unilis-student-privacy-notice-kenya.pdf" target="_blank" rel="noopener noreferrer" class="font-semibold text-blue-800 underline">Student Privacy Notice (PDF)</a> and consent to processing my personal information as described there.</span>
            </label>
          </fieldset>
        </div>

        <div class="flex justify-between mt-8">
          <button type="button" class="btn-back bg-gray-200 text-gray-700 px-6 py-3 rounded-lg hover:bg-gray-300 transition">Back</button>
          <button type="button" class="btn-next bg-navy text-white px-6 py-3 rounded-lg hover:bg-navy/90 transition">Next</button>
          <button type="submit" id="submitBtn" class="bg-navy text-white px-6 py-3 rounded-lg hover:bg-navy/90 transition hidden">Create Account</button>
        </div>
      </form>
    </div>
  </div>

  <style>
    .step {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      background: #e5e7eb;
      color: #6b7280;
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
      background: #e5e7eb;
      z-index: -1;
    }
    .step:last-child::before { display: none; }
    .step.active {
      background: #1e3a8a;
      color: white;
      transform: scale(1.15);
    }
    .step.completed {
      background: #10b981;
      color: white;
    }
    .step.completed::after {
      content: "✓";
      font-weight: bold;
    }
    .step:not(.active):not(.completed)::before { background: #e5e7eb; }
    .form-step { display: none; }
    .form-step.active { display: block; }
    .password-strength {
      height: 8px;
      border-radius: 4px;
      background: #e5e7eb;
      overflow: hidden;
    }
    .strength-bar {
      height: 100%;
      width: 0%;
      transition: width 0.4s ease;
    }
    .weak .strength-bar { background: #ef4444; width: 25%; }
    .medium .strength-bar { background: #f59e0b; width: 50%; }
    .strong .strength-bar { background: #10b981; width: 75%; }
    .very-strong .strength-bar { background: #059669; width: 100%; }
  </style>

  <script>
    // ── Course data from PHP ──────────────────────────────────────────────
    const allCourses = <?= $coursesJson ?>;
    const oldCourse = "<?= htmlspecialchars($old_input['course'] ?? '') ?>";
    const oldDept  = "<?= htmlspecialchars($old_input['department'] ?? '') ?>";

    // ── Department → Course cascade ───────────────────────────────────────
    const deptSelect = document.getElementById('departmentSelect');
    const courseSelect = document.getElementById('courseSelect');

    function updateCourses() {
      const deptId = deptSelect.value;
      courseSelect.innerHTML = '<option value="">Select your course/program</option>';

      if (deptId && allCourses[deptId]) {
        allCourses[deptId].forEach(function(c) {
          const opt = document.createElement('option');
          opt.value = c.id;
          opt.textContent = c.name;
          if (oldCourse && oldDept === deptId && String(c.id) === oldCourse) {
            opt.selected = true;
          }
          courseSelect.appendChild(opt);
        });
      }

      // Also restore old selected course if department matches
      if (oldCourse && deptId && allCourses[deptId]) {
        const matching = allCourses[deptId].filter(function(c) {
          return String(c.id) === oldCourse;
        });
        if (matching.length > 0) {
          courseSelect.value = oldCourse;
        }
      }
    }

    // Restore old selections on page load
    if (oldDept) {
      deptSelect.value = oldDept;
    }
    updateCourses();

    deptSelect.addEventListener('change', updateCourses);

    // ── Multi-step form logic ─────────────────────────────────────────────
    const steps = document.querySelectorAll('.step');
    const formSteps = document.querySelectorAll('.form-step');
    const btnNext = document.querySelector('.btn-next');
    const btnBack = document.querySelector('.btn-back');
    const submitBtn = document.getElementById('submitBtn');
    let currentStep = 0;

    // Map fields that must be filled before going to next step
    const stepFields = [
      ['reg_no', 'name'],
      ['email', 'phone'],
      ['department', 'course'],
      ['year_of_study', 'year_joined'],
      ['password', 'confirm_password'],
    ];

    function updateSteps() {
      steps.forEach((step, index) => {
        step.classList.remove('active', 'completed');
        if (index < currentStep) {
          step.classList.add('completed');
        } else if (index === currentStep) {
          step.classList.add('active');
        }
      });

      formSteps.forEach((step, index) => {
        step.classList.toggle('active', index === currentStep);
      });

      btnBack.style.display = currentStep === 0 ? 'none' : 'block';
      if (currentStep === steps.length - 1) {
        btnNext.style.display = 'none';
        submitBtn.style.display = 'block';
      } else {
        btnNext.style.display = 'block';
        submitBtn.style.display = 'none';
      }
    }

    function validateStep(stepIndex) {
      const fields = stepFields[stepIndex] || [];
      for (let i = 0; i < fields.length; i++) {
        const el = document.querySelector('[name="' + fields[i] + '"]');
        if (el && !el.value.trim()) {
          el.focus();
          el.style.borderColor = '#ef4444';
          setTimeout(function() { el.style.borderColor = ''; }, 2000);
          return false;
        }
      }
      return true;
    }

    btnNext.addEventListener('click', function() {
      if (!validateStep(currentStep)) {
        return;
      }
      if (currentStep < steps.length - 1) {
        currentStep++;
        updateSteps();
      }
    });

    btnBack.addEventListener('click', function() {
      if (currentStep > 0) {
        currentStep--;
        updateSteps();
      }
    });

    // ── Password strength checker ─────────────────────────────────────────
    const passwordInput = document.getElementById('password');
    const strengthBar = document.querySelector('.strength-bar');
    const strengthText = document.getElementById('strengthText');
    const passwordStrength = document.querySelector('.password-strength');

    passwordInput.addEventListener('input', function() {
      const password = passwordInput.value;
      let strength = 0;

      if (password.length >= 8) strength++;
      if (/[a-z]/.test(password)) strength++;
      if (/[A-Z]/.test(password)) strength++;
      if (/[0-9]/.test(password)) strength++;
      if (/[^A-Za-z0-9]/.test(password)) strength++;

      passwordStrength.className = 'password-strength';
      if (strength <= 2) {
        passwordStrength.classList.add('weak');
        strengthText.textContent = 'Weak';
      } else if (strength === 3) {
        passwordStrength.classList.add('medium');
        strengthText.textContent = 'Medium';
      } else if (strength === 4) {
        passwordStrength.classList.add('strong');
        strengthText.textContent = 'Strong';
      } else {
        passwordStrength.classList.add('very-strong');
        strengthText.textContent = 'Very Strong';
      }
    });

    // ── Password toggle ───────────────────────────────────────────────────
    const togglePassword = document.getElementById('togglePassword');
    togglePassword.addEventListener('click', function() {
      const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
      passwordInput.setAttribute('type', type);
      togglePassword.classList.toggle('fa-eye');
      togglePassword.classList.toggle('fa-eye-slash');
    });

    updateSteps();
  </script>
</body>
</html>
