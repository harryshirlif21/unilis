<?php
/**
 * Sign-in for external learners.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/layout.php';

if (!learn_schema_ready($conn)) {
    header('Location: /learn/register.php');
    exit;
}

if (learn_current($conn) !== null) {
    header('Location: /learn/dashboard.php');
    exit;
}

$errors = [];
$unverified = false;
$email = '';

// Where to go after signing in. Restricted to paths inside /learn/ so this
// cannot be used to bounce someone to another site after login.
$next = (string)($_GET['next'] ?? $_POST['next'] ?? '');
if ($next !== '' && !preg_match('#^/learn/[A-Za-z0-9_\-/.?=&]*$#', $next)) {
    $next = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!learn_csrf_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please sign in again.';
    } else {
        $email = trim((string)($_POST['email'] ?? ''));
        $result = learn_login($conn, $email, (string)($_POST['password'] ?? ''));

        if ($result['ok']) {
            header('Location: ' . ($next !== '' ? $next : '/learn/dashboard.php'));
            exit;
        }

        $errors[] = $result['error'];
        $unverified = !empty($result['unverified']);
    }
}

learn_head(['title' => 'Sign in', 'narrow' => true]);
?>
<div class="ln-card">
    <h1>Sign in</h1>
    <p class="ln-sub">Continue where you left off in the course catalogue.</p>

    <?php learn_errors($errors); ?>

    <?php if ($unverified): ?>
        <p class="ln-hint" style="margin:-12px 0 18px;">
            <a href="/learn/verify.php?pending=1">Resend the confirmation link</a>
        </p>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= learn_e(learn_csrf_token()) ?>">
        <input type="hidden" name="next" value="<?= learn_e($next) ?>">

        <div class="ln-field">
            <label for="email">Email address</label>
            <input id="email" name="email" type="email" required autocomplete="email"
                   value="<?= learn_e($email) ?>">
        </div>

        <div class="ln-field">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" required autocomplete="current-password">
        </div>

        <button class="ln-btn ln-btn-primary ln-btn-block" type="submit">
            <span class="material-symbols-rounded">login</span> Sign in
        </button>
    </form>

    <p class="ln-alt">
        New here? <a href="/learn/register.php">Create an account</a>
    </p>
</div>
<?php
learn_foot();
