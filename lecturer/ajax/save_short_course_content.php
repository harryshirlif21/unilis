<?php
// lecturer/ajax/save_short_course_content.php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/short_course_access.php';
header('Content-Type: application/json');

if (!shortCourseIsAuthor()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorised']); exit;
}

$lesson_id   = (int)($_POST['lesson_id'] ?? 0);
$block_type  = trim($_POST['block_type'] ?? '');
$content     = $_POST['content'] ?? '';

if (!$lesson_id || !$block_type) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']); exit;
}

try {
    // Resolve the parent course first, then apply the same access rule used by
    // every short-course builder action.
    $stmt = $conn->prepare("
        SELECT l.id, l.content_html, l.video_url, l.attachment_path, m.course_id
        FROM public_course_lessons l
        JOIN public_course_modules m ON l.module_id = m.id
        WHERE l.id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $lesson_id);
    $stmt->execute();
    $lesson = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$lesson || !shortCourseCanManage($conn, (int)$lesson['course_id'])) {
        echo json_encode(['success' => false, 'message' => 'Lesson not found or access denied']); exit;
    }

    // For short courses, content is stored in public_course_lessons.content_html
    // For text blocks, save the HTML directly. For other block types, we store
    // the JSON content in content_html as well (the learn page renders it).
    $content_html = $content;

    // If this is a text block, save it directly as content_html
    // If it's another type (image, video, etc.), we still store it in content_html
    // so the learn page can render it
    $stmt = $conn->prepare("
        UPDATE public_course_lessons
        SET content_html = ?
        WHERE id = ?
    ");
    $stmt->bind_param("si", $content_html, $lesson_id);
    $stmt->execute();
    $stmt->close();

    // Rebuild the lesson's topic / sub-topic tree from structured headings in
    // the body (h3.ln-topic / h4.ln-subtopic). All lesson info falls within a
    // topic and sub-topic. Existing rows are matched by title so learner
    // reading progress is preserved where the titles don't change.
    shortcourse_sync_topics($conn, $lesson_id, $content_html);

    echo json_encode(['success' => true, 'message' => 'Content saved', 'block_id' => 0]);
} catch (mysqli_sql_exception $e) {
    error_log("save_short_course_content: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
// ── Topic / sub-topic sync helpers ─────────────────────────────────────────
/**
 * Parse lesson body HTML for structured headings (h3.ln-topic / h4.ln-subtopic)
 * and return the topic tree:
 *   [ ['title'=>string, 'content'=>string(html), 'subs'=>[['title','content']]], ... ]
 * Content that appears before the first explicit topic is hoisted into an
 * implicit "Introduction" topic so no lesson info is ever left orphaned.
 */
function shortcourse_parse_topics(?string $html): array
{
    $topics    = [];
    $topicIdx  = null;
    $subIdx    = null;
    $frag      = '';

    $previous = libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    libxml_clear_errors();
    $dom->loadHTML('<?xml encoding="utf-8"?><div class="__lc">' . ($html ?? '') . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $wrapper = null;
    foreach ($dom->getElementsByTagName('div') as $d) {
        if ($d->getAttribute('class') === '__lc') { $wrapper = $d; break; }
    }
    if ($wrapper === null) { $wrapper = $dom->documentElement; }

    foreach ($wrapper->childNodes as $node) {
        if ($node->nodeType === XML_TEXT_NODE) {
            $txt = trim((string)$node->nodeValue);
            if ($txt === '') { continue; }
            if ($topicIdx === null) {
                $topics[] = ['title' => 'Introduction', 'content' => '', 'subs' => []];
                $topicIdx = count($topics) - 1;
            }
            $frag = htmlspecialchars($txt);
        } elseif ($node->nodeType !== XML_ELEMENT_NODE) {
            continue;
        } else {
            $tag   = strtolower($node->tagName);
            $class = (string)$node->getAttribute('class');

            if ($tag === 'h3' && preg_match('/\bln-topic\b/', $class)) {
                $title = trim((string)$node->textContent);
                $topics[] = ['title' => $title !== '' ? $title : '(Untitled topic)', 'content' => '', 'subs' => []];
                $topicIdx = count($topics) - 1;
                $subIdx   = null;
                continue;
            }
            if ($tag === 'h4' && preg_match('/\bln-subtopic\b/', $class)) {
                $title = trim((string)$node->textContent);
                if ($topicIdx === null) {
                    $topics[] = ['title' => '(Untitled topic)', 'content' => '', 'subs' => []];
                    $topicIdx = count($topics) - 1;
                }
                $topics[$topicIdx]['subs'][] = ['title' => $title !== '' ? $title : '(Untitled sub-topic)', 'content' => ''];
                $subIdx = count($topics[$topicIdx]['subs']) - 1;
                continue;
            }

            if ($topicIdx === null) {
                $topics[] = ['title' => 'Introduction', 'content' => '', 'subs' => []];
                $topicIdx = count($topics) - 1;
            }
            $frag = $dom->saveHTML($node);
        }

        if ($topicIdx === null) { continue; }
        if ($subIdx !== null) { $topics[$topicIdx]['subs'][$subIdx]['content'] .= $frag; }
        else { $topics[$topicIdx]['content'] .= $frag; }
    }

    return $topics;
}

/**
 * Rebuild public_course_lesson_topics for this lesson from the body HTML.
 * Rows are matched by (parent, title) so untouched topics keep their IDs and
 * learner reading progress. Deletes only rows that have genuinely disappeared.
 */
function shortcourse_sync_topics(mysqli $conn, int $lesson_id, ?string $html): void
{
    // Never touch the topic tree unless the body actually uses topic markers.
    if (!is_string($html) || $html === '' || !preg_match('/\bln-(topic|subtopic)\b/', $html)) {
        return;
    }

    $structure = shortcourse_parse_topics($html);

    // Load existing rows grouped by parent key: 0 => top-level, id => children.
    $existing = [];
    $allIds   = [];
    $rows     = [];
    $q = $conn->prepare("SELECT id, parent_id, title FROM public_course_lesson_topics WHERE lesson_id = ?");
    if (!$q) { error_log('shortcourse_sync_topics select: ' . $conn->error); return; }
    $q->bind_param('i', $lesson_id);
    $q->execute();
    $res = $q->get_result();
    while ($r = $res->fetch_assoc()) { $rows[] = $r; }
    $q->close();

    foreach ($rows as $r) {
        $pid = ((int)$r['parent_id']) ?: 0;
        $existing[$pid][(string)$r['title']] = (int)$r['id'];
        $allIds[(int)$r['id']] = true;
    }

    $keepIds = [];
    $pos     = 0;

    foreach ($structure as $topic) {
        $pos++;
        $tid = $existing[0][(string)$topic['title']] ?? null;
        if ($tid !== null) {
            $u = $conn->prepare("UPDATE public_course_lesson_topics SET title = ?, content_html = ?, position = ? WHERE id = ?");
            $u->bind_param('ssii', $topic['title'], $topic['content'], $pos, $tid);
            $u->execute(); $u->close();
        } else {
            $ins = $conn->prepare("INSERT INTO public_course_lesson_topics (lesson_id, parent_id, title, content_html, position) VALUES (?, NULL, ?, ?, ?)");
            $ins->bind_param('issi', $lesson_id, $topic['title'], $topic['content'], $pos);
            $ins->execute();
            $tid = $ins->insert_id; $ins->close();
        }
        $keepIds[$tid] = true;

        $spos = 0;
        foreach ($topic['subs'] as $sub) {
            $spos++;
            $sid = $existing[$tid][(string)$sub['title']] ?? null;
            if ($sid !== null) {
                $u = $conn->prepare("UPDATE public_course_lesson_topics SET title = ?, content_html = ?, position = ? WHERE id = ?");
                $u->bind_param('ssii', $sub['title'], $sub['content'], $spos, $sid);
                $u->execute(); $u->close();
            } else {
                $ins = $conn->prepare("INSERT INTO public_course_lesson_topics (lesson_id, parent_id, title, content_html, position) VALUES (?, ?, ?, ?, ?)");
                $ins->bind_param('iissi', $lesson_id, $tid, $sub['title'], $sub['content'], $spos);
                $ins->execute();
                $sid = $ins->insert_id; $ins->close();
            }
            $keepIds[$sid] = true;
        }
    }

    $toDelete = array_diff(array_keys($allIds), array_keys($keepIds));
    if ($toDelete) {
        $ph      = implode(',', array_fill(0, count($toDelete), '?'));
        $types   = str_repeat('i', count($toDelete));
        $args    = array_values($toDelete);

        // Clear reading progress for the doomed topics and anything under them.
        // The subquery uses two sets of placeholders (id OR parent_id), so bind
        // the id list twice.
        $delProgress = $conn->prepare(
            "DELETE FROM external_lesson_topic_progress WHERE topic_id IN (
                SELECT id FROM public_course_lesson_topics WHERE id IN ($ph) OR parent_id IN ($ph)
            )"
        );
        if ($delProgress) {
            $delProgress->bind_param(str_repeat('i', 2 * count($toDelete)), ...array_merge($args, $args));
            $delProgress->execute();
            $delProgress->close();
        }

        $children = $conn->prepare("DELETE FROM public_course_lesson_topics WHERE parent_id IN ($ph)");
        $children->bind_param($types, ...$args);
        $children->execute();
        $children->close();

        $parents = $conn->prepare("DELETE FROM public_course_lesson_topics WHERE id IN ($ph)");
        $parents->bind_param($types, ...$args);
        $parents->execute();
        $parents->close();
    }
}
