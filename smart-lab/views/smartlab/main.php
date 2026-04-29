<?php
require_once __DIR__.'/../../auth/Auth.php';
Auth::guard();

// Determine which role-specific view to load
$roleViews = [
    'student' => 'student',
    'lecturer' => 'lecturer',
    'technician' => 'technician',
    'admin' => 'technician'
];

$roleView = $roleViews[$role] ?? 'student';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Lab Projection - <?= ucfirst($role) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= APP_URL ?>/public/css/app.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/public/css/smartlab.css">
</head>
<body class="smartlab-body">

<div class="smartlab-container">
    
    <!-- Header Bar -->
    <header class="smartlab-header">
        <div class="smartlab-header-content">
            <div class="header-left">
                <h1 class="header-title">Smart Lab Projection</h1>
                <span class="header-role"><?= ucfirst($user_name ?: 'User') ?> • <?= ucfirst($role) ?> View</span>
            </div>
            <div class="header-right">
                <button id="fullscreenBtn" class="smartlab-btn-icon" title="Fullscreen (F11)">⛶</button>
                <a href="<?= APP_URL ?>/dashboard" class="smartlab-btn-icon" title="Back to Dashboard">✕</a>
            </div>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="smartlab-main">
        <?php 
        // Load role-specific view
        $viewPath = __DIR__ . '/' . $roleView . '.php';
        if (file_exists($viewPath)) {
            include $viewPath;
        } else {
            echo "<div class='error-message'>View not found for role: " . htmlspecialchars($role) . "</div>";
        }
        ?>
    </main>

</div>

<!-- Global Smart Lab Scripts -->
<script src="<?= APP_URL ?>/public/js/videoGrid.js"></script>
<script src="<?= APP_URL ?>/public/js/smartlab.js"></script>

</body>
</html>
