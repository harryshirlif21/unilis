<?php
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$status = '';
$status_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to_email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $message  = trim($_POST['message'] ?? '');

    if (!$to_email) {
        $status = '❌ Please enter a valid email address.';
        $status_type = 'error';
    } elseif (empty($message)) {
        $status = '❌ Message cannot be empty.';
        $status_type = 'error';
    } else {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'localhost';
            $mail->SMTPAuth   = false;
            $mail->SMTPSecure = false;
            $mail->Port       = 25;
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ]
            ];

            $mail->setFrom('noreply@unilis.jhubafrica.com', 'UNILIS');
            $mail->addReplyTo('noreply@unilis.jhubafrica.com', 'UNILIS');
            $mail->addAddress($to_email);

            $mail->isHTML(true);
            $mail->Subject = 'UNILIS Message';
            $mail->Body    = "
                <div style='font-family: Georgia, serif; max-width: 600px; margin: auto; padding: 40px; background: #fafafa; border-left: 4px solid #2c3e50;'>
                    <h2 style='color: #2c3e50; margin-top: 0;'>UNILIS</h2>
                    <p style='color: #333; font-size: 16px; line-height: 1.7;'>" . nl2br(htmlspecialchars($message)) . "</p>
                    <hr style='border: none; border-top: 1px solid #ddd; margin: 30px 0;'>
                    <p style='color: #999; font-size: 12px;'>This is an automated message from UNILIS. Please do not reply.</p>
                </div>
            ";
            $mail->AltBody = $message;

            $mail->send();
            $status = '✅ Email sent successfully to ' . htmlspecialchars($to_email) . '!';
            $status_type = 'success';

        } catch (Exception $e) {
            $status = '❌ Email sending failed: ' . htmlspecialchars($mail->ErrorInfo);
            $status_type = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UNILIS — Mail Test</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            min-height: 100vh;
            background: #0f1923;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'DM Sans', sans-serif;
            padding: 20px;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 80% 50% at 20% 20%, rgba(44, 62, 80, 0.4) 0%, transparent 60%),
                radial-gradient(ellipse 60% 40% at 80% 80%, rgba(52, 152, 219, 0.1) 0%, transparent 60%);
            pointer-events: none;
        }

        .card {
            background: #1a2533;
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 16px;
            padding: 48px;
            width: 100%;
            max-width: 520px;
            box-shadow: 0 40px 80px rgba(0,0,0,0.5);
            position: relative;
            overflow: hidden;
            animation: slideUp 0.5s ease;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(24px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, #2c3e50, #3498db, #2c3e50);
        }

        .logo {
            font-family: 'Playfair Display', serif;
            font-size: 28px;
            color: #fff;
            letter-spacing: 2px;
            margin-bottom: 4px;
        }

        .subtitle {
            font-size: 12px;
            color: #5a7a9a;
            letter-spacing: 3px;
            text-transform: uppercase;
            margin-bottom: 36px;
        }

        label {
            display: block;
            font-size: 11px;
            font-weight: 500;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #5a7a9a;
            margin-bottom: 8px;
        }

        input[type="email"],
        textarea {
            width: 100%;
            background: #0f1923;
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 8px;
            color: #e8edf2;
            font-family: 'DM Sans', sans-serif;
            font-size: 15px;
            padding: 14px 16px;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
            margin-bottom: 24px;
        }

        input[type="email"]:focus,
        textarea:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        textarea {
            resize: vertical;
            min-height: 140px;
            line-height: 1.6;
        }

        input[type="email"]::placeholder,
        textarea::placeholder {
            color: #2d4057;
        }

        button {
            width: 100%;
            background: linear-gradient(135deg, #2c3e50, #3d5a73);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-family: 'DM Sans', sans-serif;
            font-size: 14px;
            font-weight: 500;
            letter-spacing: 2px;
            text-transform: uppercase;
            padding: 16px;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
            overflow: hidden;
        }

        button:hover {
            background: linear-gradient(135deg, #3d5a73, #3498db);
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(52,152,219,0.2);
        }

        button:active { transform: translateY(0); }

        button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .status {
            margin-top: 20px;
            padding: 14px 16px;
            border-radius: 8px;
            font-size: 14px;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-8px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .status.success {
            background: rgba(39, 174, 96, 0.1);
            border: 1px solid rgba(39, 174, 96, 0.3);
            color: #2ecc71;
        }

        .status.error {
            background: rgba(231, 76, 60, 0.1);
            border: 1px solid rgba(231, 76, 60, 0.3);
            color: #e74c3c;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">UNILIS</div>
        <div class="subtitle">Mail Diagnostic Tool</div>

        <form method="POST" onsubmit="handleSubmit(this)">
            <label for="email">Recipient Email</label>
            <input
                type="email"
                id="email"
                name="email"
                placeholder="recipient@example.com"
                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                required
            >

            <label for="message">Message</label>
            <textarea
                id="message"
                name="message"
                placeholder="Type your message here..."
                required
            ><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>

            <button type="submit" id="sendBtn">Send Email</button>
        </form>

        <?php if ($status): ?>
            <div class="status <?= $status_type ?>">
                <?= $status ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function handleSubmit(form) {
            const btn = document.getElementById('sendBtn');
            btn.disabled = true;
            btn.textContent = 'Sending...';
        }
    </script>
</body>
</html>
```
