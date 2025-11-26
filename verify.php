<?php
require_once 'config/db.php';
require_once 'includes/mailer.php';
session_start();

// Redirect logged-in users
if (isset($_SESSION['user_id'])) {
    header("Location: " . ($_SESSION['user_role'] === 'student' ? 'student/dashboard.php' : 'dashboard.php'));
    exit;
}

$token           = $_GET['token'] ?? '';
$message         = '';
$message_type    = '';
$show_resend     = false;
$email_prefilled = '';

/* ============================================================
   1. EMAIL VERIFICATION USING TOKEN
   ============================================================ */
if ($token !== '') {

    // Get matching user
    $stmt = $conn->prepare("
        SELECT id, email, `Token Expires`
        FROM students
        WHERE `Verification Code` = ?
          AND `Status` = 'NOT VERIFIED'
        LIMIT 1
    ");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    // Validate token existence + not expired
    if ($user && strtotime($user['Token Expires']) > time()) {

        // Update record → verified
        $stmt = $conn->prepare("
            UPDATE students
            SET `Status` = 'VERIFIED',
                `Verification Code` = NULL,
                `Token Expires` = NULL,
                `Verified At` = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param('i', $user['id']);
        $stmt->execute();
        $stmt->close();

        $message = "Your email has been successfully verified! You can now log in.";
        $message_type = 'success';

    } else {

        // Token invalid or expired
        $message = "This verification link is invalid or has expired.";
        $message_type = 'error';
        $show_resend = true;
    }
}

/* ============================================================
   2. OTHER PAGE ENTRY POINTS
   ============================================================ */

elseif (isset($_GET['sent'])) {
    $message = "A verification email has been sent. Check Mailtrap.";
    $message_type = 'info';
    $email_prefilled = $_GET['email'] ?? '';
    $show_resend = true;
}

elseif (isset($_GET['resent'])) {
    $message = "A new verification email has been sent!";
    $message_type = 'success';
    $show_resend = true;
}

elseif (isset($_GET['unverified'])) {
    $message = "Please verify your email first.";
    $message_type = 'error';
    $show_resend = true;
    $email_prefilled = $_SESSION['pending_verification_email'] ?? '';
    unset($_SESSION['pending_verification_email']);
}

/* ============================================================
   3. RESEND VERIFICATION EMAIL (POST)
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $show_resend) {

    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);

    if (!$email) {
        $message = "Invalid email address.";
        $message_type = 'error';
    } else {

        // Find user
        $stmt = $conn->prepare("
            SELECT id, `Status`
            FROM students
            WHERE email = ?
            LIMIT 1
        ");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        // Not registered → redirect to signup
        if (!$user) {
            header("Location: student/signup.php");
            exit;
        }

        // Already verified
        if ($user['Status'] === 'VERIFIED') {
            header("Location: login.php");
            exit;
        }

        // Generate new token
        $new_token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', time() + (TOKEN_EXPIRY_MINUTES * 60));

        // Save new token
        $stmt = $conn->prepare("
            UPDATE students
            SET `Verification Code` = ?, 
                `Token Expires` = ?
            WHERE id = ?
        ");
        $stmt->bind_param('ssi', $new_token, $expires_at, $user['id']);
        $stmt->execute();
        $stmt->close();

        // Debug output
        echo "<pre style='background:#000;color:#0f0;padding:20px;font-size:16px;margin:20px;border-radius:10px;'>";
        echo "MAILTRAP DEBUG MODE\n";
        echo "Sending to: $email\n";
        echo "Token: $new_token\n";
        echo "Verification Link: https://unilis.jhubafrica.com/verify.php?token=$new_token\n\n";

        $sent = send_verification_email($email, $new_token);

        if ($sent) {
            echo "EMAIL SENT SUCCESSFULLY!\n";
            echo "Check Mailtrap inbox.\n";
            header("Location: verify.php?resent=1");
            exit;
        } else {
            echo "EMAIL FAILED TO SEND.\nCheck server logs.";
        }

        echo "</pre>";
        exit;
    }
}
?>
