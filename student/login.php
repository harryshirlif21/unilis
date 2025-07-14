<?php
session_start();
include '../actions.php';

// Redirect if already logged in
if (isset($_SESSION['student_id'])) {
    header("Location: dashboard.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Student Login</title>
    <style>
        body { font-family: Arial; padding: 20px; max-width: 500px; margin: auto; }
        input { width: 100%; padding: 10px; margin: 10px 0; }
        button { padding: 10px 20px; }
        .error { color: red; }
        .success { color: green; }
    </style>
</head>
<body>

<h2>Student Login</h2>

<?php if (!empty($login_error)): ?><p class="error"><?= $login_error ?></p><?php endif; ?>

<form method="POST">
    <input type="hidden" name="action" value="login_student">

    <label>Email:</label>
    <input type="email" name="email" required>

    <label>Password:</label>
    <input type="password" name="password" required>

    <button type="submit">Login</button>
</form>

</body>
</html>
