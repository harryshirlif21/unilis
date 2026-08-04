<?php
// admin/run_all_migrations.php
require_once __DIR__ . '/../migrations/consolidated_migrations.php';

try {
    echo run_all_migrations();
} catch (Throwable $e) {
    // Surface the reason instead of returning a blank 500 to the dashboard's fetch().
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    echo '<h1>Migration run failed</h1>';
    echo '<p><strong>' . htmlspecialchars($e->getMessage()) . '</strong></p>';
    echo '<p>' . htmlspecialchars(basename($e->getFile())) . ' line ' . (int)$e->getLine() . '</p>';
}
