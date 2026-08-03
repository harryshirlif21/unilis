<?php
/**
 * Registration for external learners.
 *
 * Two options:
 * 1. Register via UNILIS (for students/staff)
 * 2. Register as external learner (for general public)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/layout.php';

if (!learn_schema_ready($conn)) {
    learn_head(['title' => 'Setup required', 'narrow' => true]);
    echo '<div class="ln-card"><h1>Not set up yet</h1><p class="ln-sub">'
       . 'An administrator needs to run <code>migrate_external_learners.php</code> once before '
       . 'accounts can be created.</p>'
       . '<a class="ln-btn ln-btn-ghost ln-btn-block" href="/">Back to UNILIS</a></div>';
    learn_foot();
    exit;
}

// Already signed in: nothing to register.
if (learn_current($conn) !== null) {
    header('Location: /learn/dashboard.php');
    exit;
}

$errors = [];
$done = false;
$mailFailed = false;
$old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!learn_csrf_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please submit the form again.';
    } else {
        $old = $_POST;
        $result = learn_register($conn, $_POST);

        if (!$result['ok']) {
            $errors = $result['errors'];
        } else {
            $done = true;
            $mailFailed = !learn_send_verification(
                $result['email'],
                $result['token'],
                $result['name']
            );
        }
    }
}

$csrf = learn_csrf_token();

learn_head(['title' => 'Create an account', 'narrow' => true]);

if ($done):
    ?>
    <div class="ln-card">
        <h1>Check your email</h1>
        <?php if ($mailFailed): ?>
            <?php learn_notice(
                'Your account was created, but we could not send the confirmation email just now. '
                . 'Use the resend link below in a few minutes.',
                'error'
            ); ?>
        <?php else: ?>
            <?php learn_notice(
                'We sent a confirmation link to ' . ($old['email'] ?? 'your address')
                . '. Open it to finish setting up your account.',
                'success'
            ); ?>
        <?php endif; ?>
        <p class="ln-sub">
            The link works for <?= (int) LEARN_VERIFY_TTL_HOURS ?> hours. If it does not arrive,
            check your spam folder first.
        </p>
        <a class="ln-btn ln-btn-ghost ln-btn-block" href="/learn/verify.php?pending=1">Resend the link</a>
    </div>
    <?php
else:
    ?>
    <div class="ln-card">
        <h1>Create your account</h1>
        <p class="ln-sub">Choose how you want to access the open course catalogue.</p>

        <div style="display: flex; gap: 12px; flex-direction: column; margin-bottom: 24px;">
            <a href="/login.php?redirect=<?= urlencode('/learn/') ?>" class="ln-btn ln-btn-primary ln-btn-block">
                <span class="material-symbols-rounded">school</span> Sign in with UNILIS
            </a>
            <p class="ln-sub" style="text-align: center; margin: -8px 0 12px;">— or —</p>
        </div>

        <?php learn_errors($errors); ?>

        <form method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?= learn_e($csrf) ?>">

            <div class="ln-field">
                <label for="name">Full name</label>
                <input id="name" name="name" type="text" required autocomplete="name"
                       value="<?= learn_e($old['name'] ?? '') ?>">
            </div>

            <div class="ln-field">
                <label for="email">Email address</label>
                <input id="email" name="email" type="email" required autocomplete="email"
                       value="<?= learn_e($old['email'] ?? '') ?>">
            </div>

            <div class="ln-row">
                <div class="ln-field">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" required autocomplete="new-password">
                </div>
                <div class="ln-field">
                    <label for="password_confirm">Confirm password</label>
                    <input id="password_confirm" name="password_confirm" type="password" required autocomplete="new-password">
                </div>
            </div>
            <p class="ln-hint" style="margin:-9px 0 15px;">
                At least <?= (int) LEARN_MIN_PASSWORD ?> characters. A short phrase works well.
            </p>

            <div class="ln-row">
                <div class="ln-field">
                    <label for="country">Country <span style="font-weight:400;color:#6b7280;">(optional)</span></label>
                    <input id="country" name="country" type="text" autocomplete="country-name"
                           value="<?= learn_e($old['country'] ?? '') ?>">
                </div>
                <div class="ln-field">
                    <label for="organisation">Organisation <span style="font-weight:400;color:#6b7280;">(optional)</span></label>
                    <input id="organisation" name="organisation" type="text" autocomplete="organization"
                           value="<?= learn_e($old['organisation'] ?? '') ?>">
                </div>
            </div>

            <button class="ln-btn ln-btn-primary ln-btn-block" type="submit">
                <span class="material-symbols-rounded">person_add</span> Create external account
            </button>
        </form>

        <p class="ln-alt">Already have an account? <a href="/learn/login.php">Sign in</a></p>
    </div>
    <?php
endif;

learn_foot();
