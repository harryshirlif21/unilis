<?php
/**
 * GET /chat/api/directory.php?q=amina
 *
 * People the caller may start a direct chat with. Students see classmates,
 * students sharing a unit, teammates and the lecturers teaching their units;
 * lecturers see students in the units they teach plus their colleagues.
 */

require_once __DIR__ . '/_bootstrap.php';

try {
    $search = trim((string)($_GET['q'] ?? ''));

    // LIKE wildcards in a search box would let a caller widen their own
    // directory in surprising ways; escape them so they match literally.
    $search = str_replace(['%', '_'], ['\%', '\_'], $search);

    chat_json([
        'success' => true,
        'people' => chat_directory($conn, $chatUser, $search),
    ]);
} catch (Throwable $e) {
    error_log('chat/directory: ' . $e->getMessage());
    chat_json(['success' => false, 'error' => 'Could not load the directory'], 500);
}
