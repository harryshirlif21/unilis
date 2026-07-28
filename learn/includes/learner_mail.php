<?php
/**
 * Mail for external learners.
 *
 * Separate from includes/mailer.php because that file's
 * send_verification_email() hardcodes /verify.php?token=, the student
 * verification page, which looks up the token in `students`. A learner's token
 * lives in external_learners, so it needs its own landing page - reusing the
 * function would send people to a page that could never verify them.
 *
 * The transport is shared: getConfiguredMailer() from includes/mailer.php, so
 * this honours whatever SMTP configuration the rest of the system uses.
 */

/**
 * Send a learner their verification link.
 *
 * Returns true on success. A false is logged and surfaced as a soft warning
 * rather than failing the registration - the account exists either way, and the
 * learner can ask for another link.
 */
function learn_send_verification(string $email, string $token, string $name = ''): bool
{
    $mailerPath = dirname(__DIR__, 2) . '/includes/mailer.php';
    if (!is_file($mailerPath)) {
        error_log('learn_send_verification: includes/mailer.php is missing');
        return false;
    }
    require_once $mailerPath;

    if (!function_exists('getConfiguredMailer')) {
        error_log('learn_send_verification: getConfiguredMailer() is unavailable');
        return false;
    }

    try {
        $mail = getConfiguredMailer();
        $mail->addAddress($email);
        if (defined('EMAIL_FROM_ADDRESS') && defined('EMAIL_FROM_NAME')) {
            $mail->addReplyTo(EMAIL_FROM_ADDRESS, EMAIL_FROM_NAME);
        }

        $link = learn_base_url() . '/learn/verify.php?token=' . urlencode($token);
        $safeName = htmlspecialchars($name !== '' ? $name : 'there', ENT_QUOTES, 'UTF-8');
        $safeLink = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');

        $mail->isHTML(true);
        $mail->Subject = 'Confirm your UNILIS Learning account';
        $mail->Body = "
            <div style=\"font-family:Inter,Segoe UI,sans-serif;max-width:560px;margin:0 auto;color:#111827;\">
              <h2 style=\"color:#1B5E20;margin:0 0 12px;\">Confirm your email</h2>
              <p>Hello {$safeName},</p>
              <p>Thanks for creating a UNILIS Learning account. Confirm this address to start the courses in the catalogue.</p>
              <p style=\"margin:26px 0;\">
                <a href=\"{$safeLink}\"
                   style=\"background:#1B5E20;color:#fff;padding:12px 26px;border-radius:10px;
                          text-decoration:none;font-weight:600;display:inline-block;\">
                  Confirm my email
                </a>
              </p>
              <p style=\"font-size:13px;color:#6b7280;\">
                Or paste this into your browser:<br>
                <span style=\"word-break:break-all;\">{$safeLink}</span>
              </p>
              <p style=\"font-size:13px;color:#6b7280;\">
                This link stops working in " . (int) LEARN_VERIFY_TTL_HOURS . " hours.
                If you did not create this account you can ignore this email.
              </p>
            </div>";
        $mail->AltBody = "Hello {$name},\n\nConfirm your UNILIS Learning account:\n{$link}\n\n"
            . 'This link expires in ' . (int) LEARN_VERIFY_TTL_HOURS . " hours.\n";

        $mail->send();

        return true;
    } catch (Throwable $e) {
        error_log('learn_send_verification failed for ' . $email . ': ' . $e->getMessage());
        return false;
    }
}

/**
 * Public base URL, used to build absolute links for email.
 *
 * Falls back to the request host so a staging deployment mails its own links
 * rather than production's.
 */
function learn_base_url(): string
{
    $configured = getenv('APP_BASE_URL');
    if ($configured !== false && $configured !== '') {
        return rtrim($configured, '/');
    }

    if (PHP_SAPI === 'cli') {
        return 'https://unilis.jhubafrica.com';
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        ? 'https' : 'http';

    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}
