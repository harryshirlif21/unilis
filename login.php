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
<html>
<head>
    <title>Login - UNILIS</title>
    <style>
        body { font-family: Arial; padding: 20px; max-width: 500px; margin: auto; }
        input, button {
            width: 100%; padding: 10px; margin: 10px 0;
            box-sizing: border-box;
        }
        button { cursor: pointer; background-color: #2c3e50; color: white; border: none; }
        .error { color: red; }
        .register-link {
            text-align: center;
            margin-top: 15px;
        }
        .register-link a {
            text-decoration: none;
            color: #1abc9c;
        }
        .register-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <h2>UNILIS Login</h2>

    <?php if (!empty($login_error)): ?>
        <p class="error"><?= $login_error ?></p>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="action" value="universal_login">

        <label>Email:</label>
        <input type="email" name="email" required>

        <label>Password:</label>
        <input type="password" name="password" required>

        <button type="submit">Login</button>
    </form>

    <div class="register-link">
        <p>Don't have an account? <a href="student/signup.php">Register here</a></p>
    </div>

</body>
</html>
