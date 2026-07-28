<?php
/**
 * A certificate, rendered for screen and print.
 *
 * Reachable by verification code without signing in, so a learner can share the
 * link with an employer. The code is 48 hex characters, which is what makes that
 * safe: the page is unguessable rather than access-controlled.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/catalogue.php';
require_once __DIR__ . '/includes/layout.php';

learn_require_schema($conn);

$code = trim((string)($_GET['code'] ?? ''));
$certificate = $code !== '' ? learn_certificate_by_code($conn, $code) : null;

if ($certificate === null || $certificate['revoked_at'] !== null) {
    $learner = learn_current($conn);
    learn_head(['title' => 'Certificate not found', 'learner' => $learner, 'narrow' => true]);
    echo '<div class="ln-empty"><span class="material-symbols-rounded">search_off</span>'
       . '<h2>' . ($certificate === null ? 'Certificate not found' : 'This certificate was revoked') . '</h2>'
       . '<p><a href="/learn/verify_certificate.php">Check another certificate</a></p></div>';
    learn_foot();
    exit;
}

$issued = date('j F Y', strtotime((string)$certificate['issued_at']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate · <?= learn_e($certificate['learner_name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 32px 20px;
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: #eef2ee;
            color: #10231a;
        }
        .cert-actions {
            max-width: 960px;
            margin: 0 auto 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        .cert-btn {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 10px 18px; border: 1px solid rgba(27,94,32,.3);
            border-radius: 10px; background: #fff; color: #1B5E20;
            font: inherit; font-size: .9rem; font-weight: 600;
            text-decoration: none; cursor: pointer;
        }
        .cert-btn:hover { background: rgba(27,94,32,.06); }

        .cert {
            position: relative;
            max-width: 960px;
            margin: 0 auto;
            padding: 62px 68px;
            background: #fff;
            border: 1px solid #d9e3d9;
            border-radius: 16px;
            box-shadow: 0 18px 50px rgba(16, 35, 26, .14);
            overflow: hidden;
        }
        /* Brand band down the side rather than a full border, which prints badly. */
        .cert::before {
            content: '';
            position: absolute; inset: 0 auto 0 0; width: 12px;
            background: linear-gradient(180deg, #1B5E20 0%, #43A047 55%, #F9A825 100%);
        }
        .cert-head { display: flex; align-items: center; gap: 12px; margin-bottom: 34px; }
        .cert-head .material-symbols-rounded { font-size: 34px; color: #F9A825; }
        .cert-head span.brand { font-size: 1.1rem; font-weight: 700; color: #1B5E20; letter-spacing: .01em; }

        .cert-kicker {
            margin: 0 0 8px; font-size: .78rem; font-weight: 700;
            letter-spacing: .18em; text-transform: uppercase; color: #6b7280;
        }
        .cert h1 { margin: 0 0 26px; font-size: clamp(1.6rem, 4vw, 2.3rem); font-weight: 800; letter-spacing: -.02em; }
        .cert-name {
            margin: 0 0 6px; font-size: clamp(1.9rem, 5vw, 2.9rem);
            font-weight: 800; color: #1B5E20; letter-spacing: -.03em; line-height: 1.15;
        }
        .cert-rule { width: 180px; height: 3px; background: #F9A825; border-radius: 2px; margin: 16px 0 26px; }
        .cert-body { font-size: 1.02rem; line-height: 1.75; max-width: 60ch; color: #374151; }
        .cert-body strong { color: #10231a; }

        .cert-foot {
            margin-top: 44px; padding-top: 22px; border-top: 1px solid #e5e7eb;
            display: flex; gap: 30px; flex-wrap: wrap; font-size: .84rem; color: #6b7280;
        }
        .cert-foot div strong { display: block; color: #10231a; font-size: .92rem; margin-bottom: 2px; }
        .cert-code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; word-break: break-all; }

        @media print {
            /* The buttons and the page background are screen furniture. */
            body { background: #fff; padding: 0; }
            .cert-actions { display: none; }
            .cert { border: none; box-shadow: none; border-radius: 0; max-width: none; padding: 40px 48px; }
            @page { size: A4 landscape; margin: 12mm; }
        }
    </style>
</head>
<body>
<div class="cert-actions">
    <a class="cert-btn" href="/learn/dashboard.php">
        <span class="material-symbols-rounded">arrow_back</span> My learning
    </a>
    <button class="cert-btn" type="button" onclick="window.print()">
        <span class="material-symbols-rounded">print</span> Print or save as PDF
    </button>
</div>

<article class="cert">
    <div class="cert-head">
        <span class="material-symbols-rounded">school</span>
        <span class="brand">UNILIS Learning · JHUB Africa</span>
    </div>

    <p class="cert-kicker">Certificate of completion</p>
    <h1>This certifies that</h1>

    <p class="cert-name"><?= learn_e($certificate['learner_name']) ?></p>
    <div class="cert-rule"></div>

    <p class="cert-body">
        has successfully completed every lesson and passed every assessment in
        <strong><?= learn_e($certificate['course_title']) ?></strong><?php
        if ($certificate['final_percentage'] !== null): ?>,
        achieving an overall assessment mark of
        <strong><?= (float)$certificate['final_percentage'] ?>%</strong><?php
        endif; ?>.
    </p>

    <div class="cert-foot">
        <div>
            <strong><?= learn_e($issued) ?></strong>
            Date issued
        </div>
        <div>
            <strong><?= learn_e($certificate['serial']) ?></strong>
            Certificate serial
        </div>
        <div style="flex:1; min-width:220px;">
            <strong class="cert-code"><?= learn_e($certificate['verification_code']) ?></strong>
            Verify at <?= learn_e(learn_base_url()) ?>/learn/verify_certificate.php
        </div>
    </div>
</article>
</body>
</html>
