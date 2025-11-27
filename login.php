<?php
session_start();
include 'actions.php';

// Redirect if already logged in (any role)
if (isset($_SESSION['user_id']) || isset($_SESSION['user_role'])) {
    switch ($_SESSION['user_role'] ?? '') {
        case 'student':
            header("Location: student/dashboard.php");
            break;
        case 'lecturer':
            header("Location: lecturer/dashboard.php");
            break;
        case 'admin':
            header("Location: admin/dashboard.php");
            break;
        default:
            // Fallback: if session exists but role missing
            header("Location: student/dashboard.php");
            break;
    }
    exit;
}

// Flash message for login errors (from actions.php)
$login_error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']); // Clear after display
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - UNILIS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/text.css">
    <style>
        .error { 
            background: #f8d7da; 
            color: #721c24; 
            padding: 12px; 
            border-radius: 6px; 
            margin: 15px 0; 
            font-size: 14px; 
            text-align: center;
        }
        .info { 
            background: #d1ecf1; 
            color: #0c5460; 
            padding: 12px; 
            border-radius: 6px; 
            margin: 15px 0; 
            font-size: 14px; 
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="left-panel">
            <div class="quote-top">A WISE QUOTE</div>
            <div class="text-content">
                <div class="main-text">Get Everything You Want</div>
                <div class="sub-text">
                    You can get everything you want if you work hard through the process and stick to the plan.
                </div>
            </div>
        </div>

        <div class="right-panel">
            <div class="logo">
                <i class="fas fa-graduation-cap"></i> UNILIS
            </div>
            <i class="fas fa-arrow-left"></i>

            <h2>Welcome Back</h2>
            <p class="subtitle">Enter your email and password to access your account</p>

            <!-- ERROR: Invalid credentials -->
            <?php if ($login_error): ?>
                <div class="error"><?= htmlspecialchars($login_error) ?></div>
            <?php endif; ?>

            <!-- INFO: Unverified account (from login attempt) -->
            <?php if (isset($_GET['unverified'])): ?>
                <div class="info">
                    <strong>Please verify your email first.</strong><br>
                    Check your inbox for the verification link, or 
                    <a href="verify.php" style="color:#007bff; font-weight:500;">click here to resend</a>.
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="action" value="universal_login">
                
                <div class="input-field">
                    <label for="email">Email:</label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           placeholder="Enter your email" 
                           required>
                </div>

                <div class="input-field">
                    <label for="password">Password:</label>
                    <div style="position:relative;">
                        <input type="password" 
                               id="password" 
                               name="password" 
                               placeholder="Enter your password" 
                               required>
                        <i class="fas fa-eye" id="togglePassword" style="position:absolute; right:12px; top:14px; cursor:pointer; color:#666;"></i>
                    </div>
                </div>

                <div class="form-options">
                    <div class="remember-me">
                        <input type="checkbox" id="remember" name="remember">
                        <label for="remember">Remember me</label>
                    </div>
                    <a href="update_password.php" style="font-size:13px; color:#007bff;">Forgot password?</a>
                </div>

                <button type="submit" class="login-btn">Sign In</button>
            </form>

            <div class="bottom-links">
                <a href="verify.php">Didn't receive verification email?</a><br><br>
                <a href="student/signup.php">Don't have an account? Register as Student</a>
            </div>
        </div>
    </div>

    <script>
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');

        togglePassword.addEventListener('click', () => {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            togglePassword.classList.toggle('fa-eye');
            togglePassword.classList.toggle('fa-eye-slash');
        });
    </script>
</body>
</html>