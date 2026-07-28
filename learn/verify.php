<?php
/**
 * Email confirmation for external learners, and the resend form.
 *
 * A learner's token lives in external_learners, so this cannot reuse the
 * root /verify.php, which looks tokens up in `students`.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/layout.php';

$token = (string)($_GET['token'] ?? '');
$pending = !empty($_GET['pending']);

$result = null;
$resent = false;
$resendErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!learn_csrf_valid($_POST['csrf_token'] ?? null)) {
        $resendErrors[] = 'Your session expired. Please try again.';
    } else {
        $reissued = learn_reissue_verification($conn, (string)($_POST['email'] ?? ''));
        if ($reissued !== null) {
            learn_send_verification($reissued['email'], $reissued['token'], $reissued['name']);
        }
        $resent = true;
    }
} elseif ($token !== '') {
    $result = learn_verify_token($conn, $token);
}

learn_head(['title' => 'Confirm your email', 'narrow' => true]);
?>
<div class="ln-card">
    <?php if ($result !== null): ?>
        <h1><?= $result['ok'] ? 'Email confirmed' : 'We could not confirm that' ?></h1>
        <?php learn_notice($result['message'], $result['ok'] ? 'success' : 'error'); ?>

        <?php if ($result['ok']): ?>
            <a class="ln-btn ln-btn-primary ln-btn-block" href="/learn/login.php">
                <span class="material-symbols-rounded">login</span> Sign in
            </a>
        <?php endif; ?>
    <?php elseif ($resent): ?>
        <h1>Check your email</h1>
        <?php learn_notice(
            'If that address has an account waiting to be confirmed, a new link is on its way.',
            'success'
        ); ?>
        <a class="ln-btn ln-btn-ghost ln-btn-block" href="/learn/login.php">Back to sign in</a>
    <?php else: ?>
        <h1><?= $pending ? 'Confirm your email' : 'Resend the confirmation link' ?></h1>
        <p class="ln-sub">
            <?= $pending
                ? 'Your account needs its email address confirmed before you can sign in.'
                : 'Enter the address you registered with and we will send a fresh link.' ?>
        </p>

        <?php learn_errors($resendErrors); ?>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= learn_e(learn_csrf_token()) ?>">
            <div class="ln-field">
                <label for="email">Email address</label>
                <input id="email" name="email" type="email" required autocomplete="email">
            </div>
            <button class="ln-btn ln-btn-primary ln-btn-block" type="submit">
                <span class="material-symbols-rounded">mail</span> Send a new link
            </button>
        </form>

        <?php // The response never says whether the address is registered: a
              // different answer for known and unknown addresses would turn this
              // form into a way to test whether someone has an account here. ?>
        <p class="ln-alt">Confirmed already? <a href="/learn/login.php">Sign in</a></p>
    <?php endif; ?>
</div>
<?php
learn_foot();
