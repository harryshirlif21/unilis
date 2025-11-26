<?php
require_once 'config/db.php';
session_start();

$message = '';
$message_type = ''; // success, error

// ===================================================================
// HANDLE PASSWORD RESET FORM SUBMISSION
// ===================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // === 1. Validate inputs ===
    $errors = [];
    if (!$email) $errors[] = "Please enter a valid email address.";
    if (strlen($password) < 8) $errors[] = "Password must be at least 8 characters.";
    if ($password !== $confirm_password) $errors[] = "Passwords do not match.";

    if (!empty($errors)) {
        $message = implode("<br>", $errors);
        $message_type = 'error';
    } else {
        // === 2. Check if student exists ===
        $stmt = $conn->prepare("SELECT id FROM students WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $student = $result->fetch_assoc();
        $stmt->close();

        if (!$student) {
            $message = "No account found with that email.";
            $message_type = 'error';
        } else {
            // === 3. Update password ===
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE students SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed_password, $student['id']);
            $success = $stmt->execute();
            $stmt->close();

            if ($success) {
                $message = "Password updated successfully! You can now <a href='login.php'>login</a>.";
                $message_type = 'success';
            } else {
                $message = "Failed to update password. Please try again.";
                $message_type = 'error';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password - UNILIS</title>
<style>
    body { font-family: 'Roboto', sans-serif; background: #f0f2f5; }
    .container { max-width: 500px; margin: 80px auto; background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); text-align: center; }
    .success { color: #28a745; background: #d4edda; padding: 15px; border-radius: 8px; margin: 20px 0; }
    .error { color: #dc3545; background: #f8d7da; padding: 15px; border-radius: 8px; margin: 20px 0; }
    input[type="email"], input[type="password"] { width: 100%; padding: 14px; margin: 15px 0; border: 1px solid #ddd; border-radius: 8px; font-size: 16px; }
    button { background: #007bff; color: white; padding: 14px 30px; border: none; border-radius: 8px; font-size: 16px; cursor: pointer; width: 100%; }
    button:hover { background: #0056b3; }
    a { color: #007bff; text-decoration: none; }
</style>
</head>
<body>

<div class="container">
    <h2>Reset Password</h2>

    <?php if ($message): ?>
        <div class="<?= $message_type ?>"><?= $message ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="email" name="email" placeholder="Registered Email" required>
        <input type="password" name="password" placeholder="New Password" required>
        <input type="password" name="confirm_password" placeholder="Confirm New Password" required>
        <button type="submit">Update Password</button>
    </form>

    <p><a href="login.php">Back to Login</a></p>
</div>

</body>
</html>
