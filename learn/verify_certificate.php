<?php
/**
 * Public certificate checker.
 *
 * Anyone - an employer, an admissions office - can paste a serial or a
 * verification code and see whether it was really issued. No account needed;
 * that is the point of a verifiable certificate.
 *
 * It reports only what a holder would already have shown them: the name, the
 * course, the date and the mark. Nothing else about the learner is exposed.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/catalogue.php';
require_once __DIR__ . '/includes/layout.php';

$learner = learn_schema_ready($conn) ? learn_current($conn) : null;
$code = trim((string)($_GET['code'] ?? $_POST['code'] ?? ''));
$certificate = null;
$searched = false;

if ($code !== '' && learn_schema_ready($conn)) {
    $searched = true;
    $certificate = learn_certificate_by_code($conn, $code);
}

learn_head(['title' => 'Verify a certificate', 'learner' => $learner, 'narrow' => true]);
?>
<div class="ln-card">
    <h1>Verify a certificate</h1>
    <p class="ln-sub">Enter the serial or the verification code printed on the certificate.</p>

    <form method="post">
        <div class="ln-field">
            <label for="code">Serial or verification code</label>
            <input id="code" name="code" type="text" required autocomplete="off"
                   placeholder="UNL-2026-A1B2C3D4" value="<?= learn_e($code) ?>">
        </div>
        <button class="ln-btn ln-btn-primary ln-btn-block" type="submit">
            <span class="material-symbols-rounded">verified</span> Check
        </button>
    </form>

    <?php if ($searched): ?>
        <div style="margin-top:24px;">
            <?php if ($certificate === null): ?>
                <?php learn_notice('No certificate matches that code. Check for typos and try again.', 'error'); ?>
            <?php elseif ($certificate['revoked_at'] !== null): ?>
                <?php learn_notice(
                    'This certificate was issued but has since been revoked'
                    . ($certificate['revoked_reason'] ? ': ' . $certificate['revoked_reason'] : '.'),
                    'error'
                ); ?>
            <?php else: ?>
                <div class="ln-alert ln-alert-success">
                    <span class="material-symbols-rounded">verified</span>
                    <div><strong>Genuine certificate.</strong></div>
                </div>
                <dl style="margin:0; font-size:0.92rem; line-height:1.7;">
                    <dt style="color:var(--ln-muted); font-size:0.8rem;">Awarded to</dt>
                    <dd style="margin:0 0 12px; font-weight:600; color:var(--ln-ink);">
                        <?= learn_e($certificate['learner_name']) ?>
                    </dd>

                    <dt style="color:var(--ln-muted); font-size:0.8rem;">Course</dt>
                    <dd style="margin:0 0 12px; font-weight:600; color:var(--ln-ink);">
                        <?= learn_e($certificate['course_title']) ?>
                    </dd>

                    <dt style="color:var(--ln-muted); font-size:0.8rem;">Issued</dt>
                    <dd style="margin:0 0 12px;">
                        <?= learn_e(date('j F Y', strtotime((string)$certificate['issued_at']))) ?>
                    </dd>

                    <?php if ($certificate['final_percentage'] !== null): ?>
                        <dt style="color:var(--ln-muted); font-size:0.8rem;">Overall assessment mark</dt>
                        <dd style="margin:0 0 12px;"><?= (float)$certificate['final_percentage'] ?>%</dd>
                    <?php endif; ?>

                    <dt style="color:var(--ln-muted); font-size:0.8rem;">Serial</dt>
                    <dd style="margin:0;"><?= learn_e($certificate['serial']) ?></dd>
                </dl>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<?php
learn_foot();
