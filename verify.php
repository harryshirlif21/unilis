<?php
require_once 'config/db.php';
require_once 'includes/mailer.php';
session_start();

// Redirect logged-in users
if (isset($_SESSION['user_id'])) {
    header("Location: " . ($_SESSION['user_role'] === 'student' ? 'student/dashboard.php' : 'dashboard.php'));
    exit;
}

$token          = $_GET['token'] ?? '';
$message        = '';
$message_type   = '';
$show_resend    = false;
$email_prefilled = '';

// --- 1. Token verification ---
if ($token !== '') {
    $stmt = $conn->prepare("SELECT id, email, token_expires_at, is_verified FROM students WHERE verification_code = ? LIMIT 1");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user) {
        if ($user['is_verified']) {
            $message = "Your email is already verified. You can log in.";
            $message_type = 'info';
        } elseif (strtotime($user['token_expires_at']) > time()) {
            // Mark as verified
            $stmt = $conn->prepare("UPDATE students SET is_verified = 1, verification_code = NULL, token_expires_at = NULL, verified_at = NOW() WHERE id = ?");
            $stmt->bind_param('i', $user['id']);
            $stmt->execute();
            $stmt->close();

            $message = "Your email has been successfully verified! You can now log in.";
            $message_type = 'success';
        } else {
            $message = "This verification link has expired.";
            $message_type = 'error';
            $show_resend = true;
            $email_prefilled = $user['email'];
        }
    } else {
        $message = "Invalid verification link.";
        $message_type = 'error';
        $show_resend = true;
    }
}

// --- 2. Various entry points ---
elseif (isset($_GET['sent'])) {
    $message = "A verification email has been sent. Check your inbox.";
    $message_type = 'info';
    $email_prefilled = $_GET['email'] ?? '';
    $show_resend = true;
} elseif (isset($_GET['resent'])) {
    $message = "New verification email sent! Check your inbox.";
    $message_type = 'success';
    $show_resend = true;
} elseif (isset($_GET['unverified'])) {
    $message = "Please verify your email first.";
    $message_type = 'error';
    $show_resend = true;
    $email_prefilled = $_SESSION['pending_verification_email'] ?? '';
    unset($_SESSION['pending_verification_email']);
}

// --- 3. Resend verification email ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $show_resend) {
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);

    if (!$email) {
        $message = "Invalid email address.";
        $message_type = 'error';
    } else {
        $stmt = $conn->prepare("SELECT id, is_verified FROM students WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if (!$user) {
            header("Location: student/signup.php");
            exit;
        }
        if ($user['is_verified'] == 1) {
            header("Location: login.php");
            exit;
        }

        $new_token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', time() + (TOKEN_EXPIRY_MINUTES * 60));

        $stmt = $conn->prepare("UPDATE students SET verification_code = ?, token_expires_at = ? WHERE id = ?");
        $stmt->bind_param('ssi', $new_token, $expires_at, $user['id']);
        $stmt->execute();
        $stmt->close();

        $sent = send_verification_email($email, $new_token);

        if ($sent) {
            header("Location: verify.php?resent=1");
            exit;
        } else {
            $message = "Failed to send verification email. Try again later.";
            $message_type = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - UNILIS</title>
    <link rel="stylesheet" href="css/text.css">
    <style>
        body { background: #f0f2f5; font-family: 'Roboto', sans-serif; }
        .container { max-width: 500px; margin: 60px auto; background: white; padding: 40px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); text-align: center; }
        .logo { font-size: 36px; color: #007bff; margin-bottom: 20px; }
        .success { color: #28a745; background: #d4edda; padding: 15px; border-radius: 8px; margin: 20px 0; }
        .error   { color: #dc3545; background: #f8d7da; padding: 15px; border-radius: 8px; margin: 20px 0; }
        .info    { color: #0c5460; background: #d1ecf1; padding: 15px; border-radius: 8px; margin: 20px 0; }
        input[type="email"] { width: 100%; padding: 14px; margin: 15px 0; border: 1px solid #ddd; border-radius: 8px; font-size: 16px; box-sizing: border-box; }
        button { background: #007bff; color: white; padding: 14px 30px; border: none; border-radius: 8px; font-size: 16px; cursor: pointer; }
        button:hover { background: #0056b3; }
        a { color: #007bff; text-decoration: none; }
    </style>
</head>
<body>

<div class="container">
    <div class="logo">Graduation Cap UNILIS</div>
    <h2>Email Verification</h2>

    <?php if ($message): ?>
        <div class="<?= $message_type ?>">
            <?= nl2br(htmlspecialchars($message)) ?>
        </div>
    <?php endif; ?>

    <?php if ($message_type === 'success' && $token !== ''): ?>
        <p><strong>Welcome! Your account is now active.</strong></p>
        <p><a href="login.php"><button>Go to Login</button></a></p>

    <?php elseif ($show_resend): ?>
        <form method="post">
            <p>Enter your email to resend the verification link:</p>
            <input type="email" name="email" placeholder="your.email@university.com" 
                   value="<?= htmlspecialchars($email_prefilled) ?>" required>
            <button type="submit">Resend Verification Email</button>
        </form>
        <br>
        <p><a href="login.php">Back to Login</a> • <a href="student/signup.php">Register New Account</a></p>

    <?php else: ?>
        <p>Processing your verification...</p>
    <?php endif; ?>
</div>

</body>
</html>
