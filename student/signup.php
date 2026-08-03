<?php
session_start();
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
            <input type="email" name="email" required placeholder="your.email@jkuat.ac.ke"
                   value="<?= htmlspecialchars($old_input['email'] ?? '') ?>"
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