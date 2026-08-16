<?php
/**
 * CLI helper: regenerate the HTML slides of existing PPTX presentations using
 * the geometry-aware extractor, so decks uploaded before the improved renderer
 * display correctly without being re-uploaded.
 *
 * Usage (run on the server, from the module directory):
 *   php regenerate_slides.php            # every PPTX presentation
 *   php regenerate_slides.php 7          # only presentation id 7
 *
 * Requires the `zip` extension (used to unpack .pptx files).
 */

require_once __DIR__ . '/bootstrap.php';

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    fwrite(STDERR, "Database unavailable.\n");
    exit(1);
}

if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "The zip extension is required to regenerate PPTX slides.\n");
    exit(1);
}

$id = isset($argv[1]) ? (int) $argv[1] : 0;

$sql = $id > 0
    ? "SELECT id, file_path FROM live_presentations WHERE id = {$id} AND file_type = 'pptx'"
    : "SELECT id, file_path FROM live_presentations WHERE file_type = 'pptx' ORDER BY id";

$result = $conn->query($sql);
if (!$result || $result->num_rows === 0) {
    echo "No PPTX presentations found.\n";
    exit(0);
}

$done = 0;
while ($row = $result->fetch_assoc()) {
    $pid = (int) $row['id'];
    $filePath = (string) ($row['file_path'] ?? '');
    $stored = __DIR__ . '/uploads/presentations/' . basename($filePath);

    if ($filePath === '' || !is_file($stored)) {
        echo "  skip #{$pid}: file not found ({$filePath})\n";
        continue;
    }

    $slides = le_build_uploaded_slides($stored, 'pptx', $pid);

    $conn->query("DELETE FROM presentation_slides WHERE presentation_id = " . (int) $pid);

    $stmt = $conn->prepare(
        "INSERT INTO presentation_slides (presentation_id, slide_number, content_html, image_path, duration_seconds)
         VALUES (?, ?, ?, NULL, 30)"
    );
    foreach ($slides as $s) {
        $n = (int) ($s['slide_number'] ?? 1);
        $html = (string) ($s['content_html'] ?? '');
        $stmt->bind_param('iis', $pid, $n, $html);
        $stmt->execute();
    }
    $stmt->close();

    $conn->query("UPDATE live_presentations SET total_slides = " . count($slides) . " WHERE id = " . (int) $pid);

    echo "  regenerated #{$pid} -> " . count($slides) . " slide(s)\n";
    $done++;
}

echo "Done: {$done} presentation(s) regenerated. ZipArchive available.\n";