<?php
include '../actions.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Student Signup</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      margin: 0;
      padding: 0;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, #fff8e1, #ffd54f, #ffca28);
    }

    .form-container {
      background: #ffffff;
      padding: 30px;
      border-radius: 15px;
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
      width: 420px;
      border: 4px solid green;
      animation: fadeIn 0.6s ease;
    }

    h2 {
      text-align: center;
      margin-bottom: 20px;
      color: #b8860b; /* golden */
    }

    .form-step {
      display: none;
    }

    .form-step.active {
      display: block;
    }

    label {
      font-weight: bold;
      margin: 8px 0 4px;
      display: block;
      color: #333;
    }

    input, select {
      width: 100%;
      padding: 10px;
      margin-bottom: 15px;
      border: 1px solid #ccc;
      border-radius: 8px;
      font-size: 15px;
    }

    .password-container {
      position: relative;
    }

    .password-container input {
      padding-right: 40px;
    }

    .toggle-password {
      position: absolute;
      top: 50%;
      right: 10px;
      transform: translateY(-50%);
      cursor: pointer;
      font-size: 18px;
      color: #b8860b;
    }

    .btn {
      background: #b8860b;
      color: white;
      border: none;
      padding: 12px 20px;
      border-radius: 8px;
      cursor: pointer;
      font-size: 16px;
      transition: 0.3s;
    }

    .btn:hover {
      background: #daa520;
    }

    .btn:disabled {
      background: #ccc;
      cursor: not-allowed;
    }

    .btn-group {
      display: flex;
      justify-content: space-between;
      gap: 10px;
    }

    .success { color: green; text-align: center; }
    .error { color: red; text-align: center; }

    .strength {
      font-size: 13px;
      font-weight: bold;
      margin-top: -10px;
      margin-bottom: 10px;
    }

    .strength.weak { color: red; }
    .strength.medium { color: orange; }
    .strength.strong { color: green; }
    .strength.very-strong { color: darkgreen; }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }
  </style>
</head>
<body>
  <div class="form-container">
    <h2>Student Signup</h2>

    <?php if (!empty($success)): ?><p class="success"><?= $success ?></p><?php endif; ?>
    <?php if (!empty($error)): ?><p class="error"><?= $error ?></p><?php endif; ?>

    <form method="POST" id="signupForm">
      <input type="hidden" name="action" value="signup_student">

      <!-- Step 1 -->
      <div class="form-step active">
        <label>Reg No:</label>
        <input type="text" name="reg_no" required>

        <label>Full Name:</label>
        <input type="text" name="name" required>

        <div class="btn-group">
          <span></span>
          <button type="button" class="btn next-step">Next</button>
        </div>
      </div>

      <!-- Step 2 -->
      <div class="form-step">
        <label>Email:</label>
        <input type="email" name="email" required>

        <label>University:</label>
        <select name="university" required>
          <option value="">-- Select University --</option>
          <?php
          $res = $conn->query("SELECT * FROM universities");
          while ($row = $res->fetch_assoc()) {
              echo "<option value='{$row['id']}'>{$row['name']}</option>";
          }
          ?>
        </select>

        <div class="btn-group">
          <button type="button" class="btn prev-step">Back</button>
          <button type="button" class="btn next-step">Next</button>
        </div>
      </div>

      <!-- Step 3 -->
      <div class="form-step">
        <label>Department:</label>
        <select name="department" required>
          <option value="">-- Select Department --</option>
          <?php
          $res = $conn->query("SELECT * FROM departments");
          while ($row = $res->fetch_assoc()) {
              echo "<option value='{$row['id']}'>{$row['name']}</option>";
          }
          ?>
        </select>

        <label>Course:</label>
        <select name="course" required>
          <option value="">-- Select Course --</option>
          <?php
          $res = $conn->query("SELECT * FROM courses");
          while ($row = $res->fetch_assoc()) {
              echo "<option value='{$row['id']}'>{$row['name']}</option>";
          }
          ?>
        </select>

        <div class="btn-group">
          <button type="button" class="btn prev-step">Back</button>
          <button type="button" class="btn next-step">Next</button>
        </div>
      </div>

      <!-- Step 4 -->
      <div class="form-step">
        <label>Year of Study:</label>
        <input type="number" name="year_of_study" min="1" max="6" required>

        <label>Year Joined:</label>
        <input type="number" name="year_joined" min="2000" max="<?= date('Y') ?>" required>

        <div class="btn-group">
          <button type="button" class="btn prev-step">Back</button>
          <button type="button" class="btn next-step">Next</button>
        </div>
      </div>

      <!-- Step 5 (Password) -->
      <div class="form-step">
        <label>Password:</label>
        <div class="password-container">
          <input type="password" name="password" id="password" required>
          <span class="toggle-password" onclick="togglePassword()">👁️</span>
        </div>
        <div id="strengthMessage" class="strength"></div>

        <label>Confirm Password:</label>
        <input type="password" name="confirm_password" required>

        <div class="btn-group">
          <button type="button" class="btn prev-step">Back</button>
          <button type="submit" id="submitBtn" class="btn" disabled>Register</button>
        </div>
      </div>
    </form>
  </div>

  <script>
    const steps = document.querySelectorAll(".form-step");
    const nextBtns = document.querySelectorAll(".next-step");
    const prevBtns = document.querySelectorAll(".prev-step");
    let currentStep = 0;

    nextBtns.forEach(btn => {
      btn.addEventListener("click", () => {
        const inputs = steps[currentStep].querySelectorAll("input, select");
        let valid = true;
        inputs.forEach(input => {
          if (!input.checkValidity()) valid = false;
        });
        if (valid) {
          steps[currentStep].classList.remove("active");
          currentStep++;
          steps[currentStep].classList.add("active");
        }
      });
    });

    prevBtns.forEach(btn => {
      btn.addEventListener("click", () => {
        steps[currentStep].classList.remove("active");
        currentStep--;
        steps[currentStep].classList.add("active");
      });
    });

    // Password strength checker
    const passwordInput = document.getElementById("password");
    const strengthMessage = document.getElementById("strengthMessage");
    const submitBtn = document.getElementById("submitBtn");

    passwordInput.addEventListener("input", () => {
      const val = passwordInput.value;
      let strength = 0;

      if (val.length >= 8) strength++;
      if (/[A-Z]/.test(val)) strength++;
      if (/[0-9]/.test(val)) strength++;
      if (/[^A-Za-z0-9]/.test(val)) strength++;

      if (strength <= 1) {
        strengthMessage.textContent = "Weak password";
        strengthMessage.className = "strength weak";
        submitBtn.disabled = true;
      } else if (strength === 2) {
        strengthMessage.textContent = "Medium strength";
        strengthMessage.className = "strength medium";
        submitBtn.disabled = true;
      } else if (strength === 3) {
        strengthMessage.textContent = "Strong password";
        strengthMessage.className = "strength strong";
        submitBtn.disabled = true;
      } else if (strength === 4) {
        strengthMessage.textContent = "Very Strong password";
        strengthMessage.className = "strength very-strong";
        submitBtn.disabled = false;
      }
    });

    // Toggle password visibility
    function togglePassword() {
      const input = document.getElementById("password");
      input.type = input.type === "password" ? "text" : "password";
    }
  </script>
</body>
</html>
