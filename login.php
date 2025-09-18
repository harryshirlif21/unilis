<?php
session_start();
include 'actions.php';

if (isset($_SESSION['user_role'])) {
    switch ($_SESSION['user_role']) {
        case 'student': header("Location: student/dashboard.php"); break;
        case 'lecturer': header("Location: lecturer/dashboard.php"); break;
        case 'admin': header("Location: admin/dashboard.php"); break;
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - UNILIS</title>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Roboto font -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- External CSS -->
    <link rel="stylesheet" href="css/text.css">
</head>

<body>
    <div class="login-wrapper">
        <div class="left-panel">
            <div class="quote-top">A WISE QUOTE</div>
            <div class="text-content">
                <div class="main-text">
                    Get Everything You Want
                </div>
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

            <?php if (!empty($login_error)): ?>
                <p class="error"><?= $login_error ?></p>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="action" value="universal_login">
                <div class="input-field">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" placeholder="Enter your email" required>
                </div>
                <div class="input-field">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                    <i class="fas fa-eye"></i>
                </div>

                <div class="form-options">
                    <div class="remember-me">
                        <input type="checkbox" id="remember" name="remember">
                        <label for="remember" style="font-weight: normal; margin-bottom: 0;">Remember me</label>
                    </div>
                </div>
                

                <button type="submit" class="login-btn">Sign In</button>
            </form>

            <div class="bottom-links">
                <a href="update_password.php">Update Password</a>
                <a href="student/signup.php">Don't have an account? Register</a>
            </div>
        </div>
    </div>
</body>
</html>