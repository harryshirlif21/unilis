<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/academic_year_history.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'lecturer') {
    http_response_code(403);
    exit('Unauthorized.');
}

$lecturerId = (int)$_SESSION['user_id'];
$contentType = (string)($_GET['type'] ?? ($_POST['type'] ?? ''));
$contentId = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
$allowedTypes = [
    'classnote' => 'classnotes',
    'interactive_assignment' => 'interactive_assignments',
];

if (!isset($allowedTypes[$contentType]) || $contentId < 1) {
    http_response_code(400);
    exit('Invalid content history request.');
}

$table = $allowedTypes[$contentType];
$ownerCheck = $conn->prepare("
    SELECT c.id, c.title, c.unit_id, c.lecturer_id, u.name AS unit_name, c.academic_year
    FROM `{$table}` c
    JOIN lecturer_units lu ON lu.unit_id = c.unit_id AND lu.lecturer_id = ?
    JOIN units u ON u.id = c.unit_id
    WHERE c.id = ? AND c.lecturer_id = ?
    LIMIT 1
");
$ownerCheck->bind_param('iii', $lecturerId, $contentId, $lecturerId);
$ownerCheck->execute();
$content = $ownerCheck->get_result()->fetch_assoc();
$ownerCheck->close();

if (!$content) {
    http_response_code(404);
    exit('Content not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_version'])) {
    $restoreVersionId = (int)($_POST['restore_version'] ?? 0);
    if ($restoreVersionId < 1) {
        http_response_code(400);
        exit('Invalid version selected.');
    }

    $versionRow = $conn->prepare("
        SELECT id, version_number, academic_year, snapshot_json, edited_by, created_at
        FROM learning_content_versions
        WHERE id = ? AND content_type = ? AND content_id = ? AND unit_id = ?
        LIMIT 1
    ");
    $unitId = (int)$content['unit_id'];
    $versionRow->bind_param('isii', $restoreVersionId, $contentType, $contentId, $unitId);
    $versionRow->execute();
    $version = $versionRow->get_result()->fetch_assoc();
    $versionRow->close();

    if (!$version) {
        http_response_code(404);
        exit('Version not found.');
    }

    $snapshot = json_decode((string)$version['snapshot_json'], true);
    if (!is_array($snapshot)) {
        http_response_code(400);
        exit('Version snapshot is invalid.');
    }

    try {
        $conn->begin_transaction();

        if ($contentType === 'classnote') {
            $currentTitle = (string)($content['title'] ?? '');
            $currentSubtopics = '[]';
            $currentStmt = $conn->prepare("SELECT title, subtopics_json FROM classnotes WHERE id = ? AND lecturer_id = ? LIMIT 1");
            $currentStmt->bind_param('ii', $contentId, $lecturerId);
            $currentStmt->execute();
            $current = $currentStmt->get_result()->fetch_assoc();
            $currentStmt->close();
            if ($current) {
                $currentTitle = (string)($current['title'] ?? $currentTitle);
                $currentSubtopics = (string)($current['subtopics_json'] ?? '[]');
            }

            academic_year_history_revision(
                $conn,
                'classnote',
                $contentId,
                $unitId,
                (string)($content['academic_year'] ?? academic_year_history_current_label($conn)),
                $lecturerId,
                ['title' => $currentTitle, 'subtopics_json' => $currentSubtopics]
            );

            $restoredTitle = trim((string)($snapshot['title'] ?? $currentTitle));
            $restoredSubtopics = $snapshot['subtopics_json'] ?? '[]';
            if (is_array($restoredSubtopics)) {
                $restoredSubtopics = json_encode($restoredSubtopics, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
            if (!is_string($restoredSubtopics)) {
                $restoredSubtopics = '[]';
            }
            $restoreStmt = $conn->prepare("
                UPDATE classnotes
                SET title = ?, subtopics_json = ?, uploaded_at = NOW(), academic_year = ?
                WHERE id = ? AND lecturer_id = ?
            ");
            $targetYear = (string)($version['academic_year'] ?: academic_year_history_current_label($conn));
            $restoreStmt->bind_param('sssii', $restoredTitle, $restoredSubtopics, $targetYear, $contentId, $lecturerId);
            if (!$restoreStmt->execute()) {
                throw new RuntimeException('Unable to restore class note version.');
            }
            $restoreStmt->close();
        } elseif ($contentType === 'interactive_assignment') {
            $currentAssignmentStmt = $conn->prepare("
                SELECT id, lecturer_id, unit_id, title, description, due_date, academic_year
                FROM interactive_assignments
                WHERE id = ? AND lecturer_id = ?
                LIMIT 1
            ");
            $currentAssignmentStmt->bind_param('ii', $contentId, $lecturerId);
            $currentAssignmentStmt->execute();
            $currentAssignment = $currentAssignmentStmt->get_result()->fetch_assoc();
            $currentAssignmentStmt->close();

            $currentQuestions = [];
            $currentQuestionsStmt = $conn->prepare("
                SELECT id, question_text, question_type, points, media_url
                FROM interactive_questions
                WHERE interactive_assignment_id = ?
                ORDER BY id ASC
            ");
            $currentQuestionsStmt->bind_param('i', $contentId);
            $currentQuestionsStmt->execute();
            $currentQuestionsResult = $currentQuestionsStmt->get_result();
            while ($question = $currentQuestionsResult->fetch_assoc()) {
                $optionStmt = $conn->prepare("
                    SELECT option_text, is_correct
                    FROM interactive_options
                    WHERE question_id = ?
                    ORDER BY id ASC
                ");
                $questionId = (int)$question['id'];
                $optionStmt->bind_param('i', $questionId);
                $optionStmt->execute();
                $question['options'] = $optionStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $optionStmt->close();
                $currentQuestions[] = $question;
            }
            $currentQuestionsStmt->close();

            academic_year_history_revision(
                $conn,
                'interactive_assignment',
                $contentId,
                (int)$content['unit_id'],
                (string)($content['academic_year'] ?? academic_year_history_current_label($conn)),
                $lecturerId,
                ['assignment' => $currentAssignment, 'questions' => $currentQuestions]
            );

            $assignmentSnapshot = $snapshot['assignment'] ?? [];
            $questionSnapshot = $snapshot['questions'] ?? [];
            if (!is_array($assignmentSnapshot) || !is_array($questionSnapshot)) {
                throw new RuntimeException('The selected version snapshot is incomplete.');
            }

            $targetTitle = trim((string)($assignmentSnapshot['title'] ?? $content['title']));
            $targetDescription = (string)($assignmentSnapshot['description'] ?? '');
            $targetDueDate = (string)($assignmentSnapshot['due_date'] ?? date('Y-m-d H:i:s'));
            $targetUnitId = (int)($assignmentSnapshot['unit_id'] ?? $content['unit_id']);
            $targetYear = (string)($assignmentSnapshot['academic_year'] ?? $version['academic_year'] ?? academic_year_history_current_label($conn));

            $restoreAssignmentStmt = $conn->prepare("
                UPDATE interactive_assignments
                SET title = ?, description = ?, due_date = ?, unit_id = ?, academic_year = ?
                WHERE id = ? AND lecturer_id = ?
            ");
            $restoreAssignmentStmt->bind_param('sssisii', $targetTitle, $targetDescription, $targetDueDate, $targetUnitId, $targetYear, $contentId, $lecturerId);
            if (!$restoreAssignmentStmt->execute()) {
                throw new RuntimeException('Unable to restore assignment version.');
            }
            $restoreAssignmentStmt->close();

            $deleteQuestionStmt = $conn->prepare("DELETE FROM interactive_options WHERE question_id IN (SELECT id FROM interactive_questions WHERE interactive_assignment_id = ?)");
            $deleteQuestionStmt->bind_param('i', $contentId);
            $deleteQuestionStmt->execute();
            $deleteQuestionStmt->close();

            $deleteMainQuestionStmt = $conn->prepare("DELETE FROM interactive_questions WHERE interactive_assignment_id = ?");
            $deleteMainQuestionStmt->bind_param('i', $contentId);
            $deleteMainQuestionStmt->execute();
            $deleteMainQuestionStmt->close();

            foreach ($questionSnapshot as $question) {
                if (!is_array($question)) {
                    continue;
                }
                $insertQuestionStmt = $conn->prepare("
                    INSERT INTO interactive_questions (interactive_assignment_id, question_text, question_type, points, media_url)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $questionText = (string)($question['question_text'] ?? '');
                $questionType = (string)($question['question_type'] ?? 'short_answer');
                $questionPoints = (int)($question['points'] ?? 1);
                $questionMedia = $question['media_url'] ?? null;
                $insertQuestionStmt->bind_param('issis', $contentId, $questionText, $questionType, $questionPoints, $questionMedia);
                if (!$insertQuestionStmt->execute()) {
                    throw new RuntimeException('Unable to restore assignment question.');
                }
                $newQuestionId = (int)$insertQuestionStmt->insert_id;
                $insertQuestionStmt->close();

                $options = $question['options'] ?? [];
                if ($questionType === 'multiple_choice' && is_array($options)) {
                    foreach ($options as $option) {
                        if (!is_array($option)) {
                            continue;
                        }
                        $text = (string)($option['option_text'] ?? '');
                        if ($text === '') {
                            continue;
                        }
                        $isCorrect = !empty($option['is_correct']) ? 1 : 0;
                        $insertOptionStmt = $conn->prepare("
                            INSERT INTO interactive_options (question_id, option_text, is_correct)
                            VALUES (?, ?, ?)
                        ");
                        $insertOptionStmt->bind_param('isi', $newQuestionId, $text, $isCorrect);
                        $insertOptionStmt->execute();
                        $insertOptionStmt->close();
                    }
                }
            }
        }

        $conn->commit();
        $_SESSION['history_notice'] = 'The selected version was restored successfully.';
        header('Location: content_history.php?type=' . urlencode($contentType) . '&id=' . (int)$contentId);
        exit;
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('Restore content version failed: ' . $e->getMessage());
        $_SESSION['history_notice'] = 'Unable to restore that version.';
        header('Location: content_history.php?type=' . urlencode($contentType) . '&id=' . (int)$contentId);
        exit;
    }
}

$stmt = $conn->prepare("
    SELECT version_number, academic_year, snapshot_json, edited_by, created_at
    FROM learning_content_versions
    WHERE content_type = ? AND content_id = ? AND unit_id = ?
    ORDER BY version_number DESC
");
$unitId = (int)$content['unit_id'];
$stmt->bind_param('sii', $contentType, $contentId, $unitId);
$stmt->execute();
$versions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Version history · <?= htmlspecialchars($content['title']) ?></title>
    <style>
        body{font:15px/1.55 Arial,sans-serif;margin:0;background:#f3f4f6;color:#111827}
        main{max-width:1000px;margin:32px auto;padding:0 18px}
        .panel{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:22px;margin:16px 0}
        h1{font-size:24px;margin:0 0 6px}
        h2{font-size:17px;margin:0 0 12px}
        .muted{color:#6b7280}
        pre{white-space:pre-wrap;overflow-wrap:anywhere;background:#f9fafb;padding:14px;border-radius:8px;max-height:520px;overflow:auto}
        a{color:#2563eb}
    </style>
</head>
<body>
<main>
    <a href="javascript:history.back()">← Back</a>
    <section class="panel">
        <h1><?= htmlspecialchars($content['title']) ?></h1>
        <div class="muted"><?= htmlspecialchars($content['unit_name']) ?> · <?= count($versions) ?> saved previous version(s)</div>
    </section>
    <?php if (!empty($_SESSION['history_notice'])): ?>
        <section class="panel"><p class="muted"><?= htmlspecialchars($_SESSION['history_notice']) ?></p></section>
        <?php unset($_SESSION['history_notice']); ?>
    <?php endif; ?>
    <?php if (!$versions): ?>
        <section class="panel"><p class="muted">No previous revisions have been saved yet.</p></section>
    <?php else: ?>
        <?php foreach ($versions as $version): ?>
            <section class="panel">
                <h2>Version <?= (int)$version['version_number'] ?> · <?= htmlspecialchars($version['academic_year']) ?></h2>
                <div class="muted">Saved <?= htmlspecialchars(date('d M Y H:i', strtotime($version['created_at']))) ?></div>
                <form method="post" action="content_history.php?type=<?= urlencode($contentType) ?>&id=<?= (int)$contentId ?>" data-restore-form data-version-label="Version <?= (int)$version['version_number'] ?>" style="margin:14px 0 10px;">
                    <input type="hidden" name="restore_version" value="<?= (int)$version['id'] ?>">
                    <button type="submit" style="background:#2563eb;color:#fff;border:0;border-radius:8px;padding:9px 14px;cursor:pointer;">Restore this version</button>
                </form>
                <pre><?= htmlspecialchars(json_encode(json_decode($version['snapshot_json'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: $version['snapshot_json']) ?></pre>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
</main>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form[data-restore-form]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                const versionLabel = form.dataset.versionLabel || 'this version';
                const confirmed = window.confirm(
                    'Restore ' + versionLabel + '? This will overwrite the current content and save a new revision in history.'
                );
                if (!confirmed) {
                    event.preventDefault();
                }
            });
        });
    });
</script>
</body>
</html>
