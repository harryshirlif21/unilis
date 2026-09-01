<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/ajax/short_course_access.php';
require_once __DIR__ . '/../learn/includes/catalogue.php'; // learn_lesson_topics, learn_topic_reader_count

if (!shortCourseIsAuthor()) { header('Location: ../login.php'); exit; }
$lesson_id = (int)($_GET['lesson_id'] ?? 0);
if (!$lesson_id) { http_response_code(400); exit('A lesson_id is required.'); }

$stmt = $conn->prepare("
    SELECT l.title AS lesson_title, m.id AS module_id, m.title AS module_title,
           c.id AS course_id, c.title AS course_title
    FROM public_course_lessons l
    JOIN public_course_modules m ON m.id = l.module_id
    JOIN public_courses c ON c.id = m.course_id
    WHERE l.id = ? LIMIT 1
");
$stmt->bind_param('i', $lesson_id);
$stmt->execute();
$lesson = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$lesson) { http_response_code(404); die('Lesson not found.'); }
if (!shortCourseCanEditLesson($conn, $lesson_id)) {
    http_response_code(403); die('You do not have permission to edit this lesson\'s topics.');
}

$flash = '';
$flashType = 'ok';
$go = 'lesson_topics.php?lesson_id=' . $lesson_id;
// ---- Handle POST actions -------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $title   = trim((string)($_POST['title'] ?? ''));
    $content = trim((string)($_POST['content'] ?? ''));

    if ($action === 'save') {
        $parent_id = (int)($_POST['parent_id'] ?? 0) ?: null;
        $edit_id   = (int)($_POST['topic_id'] ?? 0);
        if ($title === '') {
            $flash = 'Topic title is required.'; $flashType = 'err';
        } elseif ($edit_id) {
            $chk = $conn->prepare('SELECT id FROM public_course_lesson_topics WHERE id = ? AND lesson_id = ? LIMIT 1');
            $chk->bind_param('ii', $edit_id, $lesson_id); $chk->execute();
            $owned = $chk->get_result()->fetch_row() ? true : false; $chk->close();
            if (!$owned) { $flash = 'Topic not found in this lesson.'; $flashType = 'err'; }
            else {
                $up = $conn->prepare('UPDATE public_course_lesson_topics SET title = ?, content_html = ? WHERE id = ?');
                $up->bind_param('ssi', $title, $content, $edit_id); $up->execute(); $up->close();
                $flash = 'Topic saved.';
            }
        } else {
            $q = 'SELECT COALESCE(MAX(position), -1) + 1 AS p FROM public_course_lesson_topics WHERE lesson_id = ?'
               . ($parent_id ? ' AND parent_id = ?' : ' AND parent_id IS NULL');
            $posRes = $conn->prepare($q);
            if ($parent_id) { $posRes->bind_param('ii', $lesson_id, $parent_id); }
            else { $posRes->bind_param('i', $lesson_id); }
            $posRes->execute();
            $nextPos = (int)$posRes->get_result()->fetch_assoc()['p']; $posRes->close();
            $ins = $conn->prepare('INSERT INTO public_course_lesson_topics (lesson_id, parent_id, title, content_html, position) VALUES (?, ?, ?, ?, ?)');
            $ins->bind_param('iissi', $lesson_id, $parent_id, $title, $content, $nextPos);
            $ins->execute(); $ins->close();
            $flash = 'Topic added.';
        }
    } elseif ($action === 'delete') {
        $topic_id = (int)($_POST['topic_id'] ?? 0);
        $chk = $conn->prepare('SELECT id FROM public_course_lesson_topics WHERE id = ? AND lesson_id = ? LIMIT 1');
        $chk->bind_param('ii', $topic_id, $lesson_id); $chk->execute();
        $owned = $chk->get_result()->fetch_row() ? true : false; $chk->close();
        if (!$owned) { $flash = 'Topic not found in this lesson.'; $flashType = 'err'; }
        else {
            $ids = [$topic_id];
            $seen = [$topic_id => true];
            while ($ids) {
                $current = array_pop($ids);
                $children = $conn->prepare('SELECT id FROM public_course_lesson_topics WHERE lesson_id = ? AND parent_id = ?');
                $children->bind_param('ii', $lesson_id, $current);
                $children->execute();
                foreach ($children->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
                    $childId = (int)$row['id'];
                    if (!isset($seen[$childId])) {
                        $seen[$childId] = true;
                        $ids[] = $childId;
                    }
                }
                $children->close();
            }

            $deleteIds = array_keys($seen);
            sort($deleteIds, SORT_NUMERIC);

            $conn->begin_transaction();
            try {
                $progressTable = $conn->query("SHOW TABLES LIKE 'external_lesson_topic_progress'");
                if ($progressTable && $progressTable->num_rows > 0) {
                    $ph = implode(',', array_fill(0, count($deleteIds), '?'));
                    $progress = $conn->prepare('DELETE FROM external_lesson_topic_progress WHERE topic_id IN (' . $ph . ')');
                    $progress->bind_param(str_repeat('i', count($deleteIds)), ...$deleteIds);
                    $progress->execute();
                    $progress->close();
                }

                $ph = implode(',', array_fill(0, count($deleteIds), '?'));
                $del = $conn->prepare('DELETE FROM public_course_lesson_topics WHERE id IN (' . $ph . ')');
                $del->bind_param(str_repeat('i', count($deleteIds)), ...$deleteIds);
                $del->execute();
                $del->close();

                $conn->commit();
                $flash = 'Topic deleted (all nested topics and reading progress were removed).';
            } catch (Exception $e) {
                $conn->rollback();
                $flash = 'Delete failed: ' . $e->getMessage();
                $flashType = 'err';
            }
        }
    } elseif ($action === 'move') {
        $topic_id = (int)($_POST['topic_id'] ?? 0);
        $dir = ($_POST['direction'] ?? '') === 'down' ? 'down' : 'up';
        $get = $conn->prepare('SELECT parent_id, position FROM public_course_lesson_topics WHERE id = ? AND lesson_id = ? LIMIT 1');
        $get->bind_param('ii', $topic_id, $lesson_id); $get->execute();
        $row = $get->get_result()->fetch_assoc(); $get->close();
        if ($row) {
            if ($row['parent_id']) {
                $q = $dir === 'down'
                    ? 'SELECT id, position FROM public_course_lesson_topics WHERE lesson_id = ? AND parent_id = ? AND position > ? ORDER BY position ASC, id ASC LIMIT 1'
                    : 'SELECT id, position FROM public_course_lesson_topics WHERE lesson_id = ? AND parent_id = ? AND position < ? ORDER BY position DESC, id DESC LIMIT 1';
                $sib = $conn->prepare($q); $sib->bind_param('iii', $lesson_id, (int)$row['parent_id'], (int)$row['position']);
            } else {
                $q = $dir === 'down'
                    ? 'SELECT id, position FROM public_course_lesson_topics WHERE lesson_id = ? AND parent_id IS NULL AND position > ? ORDER BY position ASC, id ASC LIMIT 1'
                    : 'SELECT id, position FROM public_course_lesson_topics WHERE lesson_id = ? AND parent_id IS NULL AND position < ? ORDER BY position DESC, id DESC LIMIT 1';
                $sib = $conn->prepare($q); $sib->bind_param('ii', $lesson_id, (int)$row['position']);
            }
            $sib->execute();
            $neighbor = $sib->get_result()->fetch_assoc(); $sib->close();
            if ($neighbor) {
                $conn->begin_transaction();
                $c1 = $conn->prepare('UPDATE public_course_lesson_topics SET position = ? WHERE id = ?');
                $c1->bind_param('ii', (int)$neighbor['position'], $topic_id); $c1->execute(); $c1->close();
                $c2 = $conn->prepare('UPDATE public_course_lesson_topics SET position = ? WHERE id = ?');
                $c2->bind_param('ii', (int)$row['position'], (int)$neighbor['id']); $c2->execute(); $c2->close();
                $conn->commit();
                $flash = 'Reordered.';
            } else {
                $flash = 'Already at the ' . ($dir === 'down' ? 'end' : 'top') . '.';
            }
        } else {
            $flash = 'Topic not found.'; $flashType = 'err';
        }
    }
    header('Location: ' . $go);
    exit;
}

$topics = learn_lesson_topics($conn, $lesson_id);
function escHtml($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Topics &mdash; <?= escHtml($lesson['lesson_title']) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{--bg:#0d0f14;--surface:#161921;--surface2:#1e2230;--surface3:#262c3d;--border:#2a3148;--accent:#4f8ef7;--accent2:#38d9a9;--danger:#f75f5f;--text:#e8eaf0;--muted:#7a82a0;--r:10px;}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Segoe UI',Arial,sans-serif;background:var(--bg);color:var(--text);min-height:100vh;padding:26px;}
a{color:var(--accent);text-decoration:none;}
.wrap{max-width:900px;margin:0 auto;}
.crumbs{color:var(--muted);font-size:0.8rem;margin-bottom:14px;}
.crumbs a{color:var(--accent);}
h1{font-size:1.35rem;margin-bottom:4px;}
.sub{color:var(--muted);font-size:0.85rem;margin-bottom:16px;}
.flash{padding:11px 14px;border-radius:8px;margin-bottom:14px;font-size:0.88rem;}
.flash.ok{background:rgba(56,217,169,.12);border:1px solid rgba(56,217,169,.4);color:var(--accent2);}
.flash.err{background:rgba(247,95,95,.12);border:1px solid rgba(247,95,95,.4);color:var(--danger);}
.toolbar{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px;}
.btn{display:inline-flex;align-items:center;gap:7px;padding:9px 14px;border-radius:8px;border:1px solid var(--border);background:var(--surface2);color:var(--text);cursor:pointer;font-size:0.85rem;}
.btn:hover{border-color:var(--accent);}
.btn.primary{background:var(--accent);border-color:var(--accent);color:#08121f;}
.btn.danger{color:var(--danger);}
.btn.danger:hover{border-color:var(--danger);}
.btn.sm{padding:5px 10px;font-size:0.78rem;}
.topic{border:1px solid var(--border);border-radius:var(--r);background:var(--surface2);margin-bottom:12px;}
.topic.top{border-left:4px solid var(--accent);}
.topic.subtopic{margin:8px 16px 12px;background:var(--surface3);border-left:4px solid var(--accent2);}
.topic-head{display:flex;align-items:center;gap:10px;padding:12px 14px;}
.topic-head .t{flex:1;min-width:0;font-weight:600;font-size:0.95rem;}
.topic-head .acts{display:flex;gap:6px;flex-wrap:wrap;}
.topic-body{padding:0 16px 12px;color:var(--muted);font-size:0.85rem;white-space:pre-wrap;}
.readers{display:flex;align-items:center;gap:6px;color:var(--accent2);font-size:0.8rem;margin:0 16px 8px;padding-top:8px;border-top:1px dashed var(--border);}
.empty{color:var(--muted);font-size:0.85rem;padding:16px;border:1px dashed var(--border);border-radius:var(--r);margin-bottom:14px;}
.hidden-form{display:none;margin:4px 16px 14px;background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:14px;}
.hidden-form label{display:block;font-size:0.75rem;color:var(--muted);margin:0 0 4px;}
.hidden-form input[type=text],.hidden-form textarea{width:100%;background:var(--surface2);border:1px solid var(--border);color:var(--text);border-radius:8px;padding:9px 11px;font-size:0.88rem;margin-bottom:10px;font-family:inherit;}
.hidden-form textarea{min-height:90px;resize:vertical;}
.back{margin-top:18px;font-size:0.88rem;}
</style>
</head>
<body>
<div class="wrap">
  <div class="crumbs">
    <a href="catalogue.php">Courses</a> &rsaquo;
    <a href="catalogue_builder.php?course_id=<?= (int)$lesson['course_id'] ?>"><?= escHtml($lesson['course_title']) ?></a> &rsaquo;
    <a href="lesson_editor.php?course_id=<?= (int)$lesson['course_id'] ?>&lesson_id=<?= $lesson_id ?>"><?= escHtml($lesson['lesson_title']) ?></a> &rsaquo; Topics
  </div>

  <h1><i class="fa-solid fa-list-ul"></i> <?= escHtml($lesson['lesson_title']) ?> &mdash; Topics &amp; subtopics</h1>
  <p class="sub">Break this lesson into ordered reading sections. Learners mark each topic as read, which feeds the progress bar and the completion trace shown under each topic.</p>

  <?php if ($flash !== ''): ?>
    <div class="flash <?= $flashType ?>"><?= escHtml($flash) ?></div>
  <?php endif; ?>

  <div class="toolbar">
    <button class="btn primary" onclick="tog('add-form');this.blur();"><i class="fa-solid fa-plus"></i> Add topic</button>
    <a class="btn" href="lesson_editor.php?course_id=<?= (int)$lesson['course_id'] ?>&lesson_id=<?= $lesson_id ?>"><i class="fa-solid fa-arrow-left"></i> Back to lesson</a>
  </div>

  <div class="hidden-form" id="add-form">
    <form method="post" action="lesson_topics.php?lesson_id=<?= $lesson_id ?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="topic_id" value="0">
      <label>Add as</label>
      <select name="parent_id" style="width:100%;background:var(--surface2);border:1px solid var(--border);color:var(--text);border-radius:8px;padding:9px 11px;margin-bottom:10px;">
        <option value="0">Top-level topic</option>
        <?php foreach ($topics as $t): ?>
          <option value="<?= (int)$t['id'] ?>">Sub-topic under &ldquo;<?= escHtml($t['title']) ?>&rdquo;</option>
        <?php endforeach; ?>
      </select>
      <label>Title</label>
      <input type="text" name="title" required placeholder="e.g. Introduction, Key concepts&hellip;">
      <label>Content (reading text &mdash; optional)</label>
      <textarea name="content" placeholder="Paste the reading text for this topic here."></textarea>
      <button class="btn primary" type="submit"><i class="fa-solid fa-check"></i> Add topic</button>
    </form>
  </div>

<?php if (!$topics): ?>
    <div class="empty">No topics yet. Use &ldquo;Add topic&rdquo; to break this lesson into reading sections.</div>
  <?php else: ?>
    <?php
    function renderTopicBlock(mysqli $conn, array $t, int $lessonId, bool $isTop): void {
        $readers = learn_topic_reader_count($conn, (int)$t['id']);
        ?>
        <div class="topic <?= $isTop ? 'top' : 'subtopic' ?>">
          <div class="topic-head">
            <div class="t"><?= escHtml($t['title']) ?></div>
            <div class="acts">
              <button class="btn sm" onclick="tog('edit-<?= (int)$t['id'] ?>')"><i class="fa-solid fa-pen"></i></button>
              <button class="btn sm" onclick="move(<?= (int)$t['id'] ?>,'up')"><i class="fa-solid fa-arrow-up"></i></button>
              <button class="btn sm" onclick="move(<?= (int)$t['id'] ?>,'down')"><i class="fa-solid fa-arrow-down"></i></button>
              <button class="btn sm danger" onclick="del(<?= (int)$t['id'] ?>,'<?= escHtml($t['title']) ?>')"><i class="fa-solid fa-trash"></i></button>
            </div>
          </div>
          <?php if (trim((string)($t['content_html'] ?? '')) !== ''): ?>
            <div class="topic-body"><?= escHtml($t['content_html']) ?></div>
          <?php endif; ?>
          <div class="readers"><i class="fa-solid fa-eye"></i> <?= (int)$readers ?> learner<?= $readers === 1 ? '' : 's' ?> have read this</div>
          <?php foreach ($t['subs'] as $sub): ?>
            <?php renderTopicBlock($conn, $sub, $lessonId, false); ?>
          <?php endforeach; ?>

          <div class="hidden-form" id="edit-<?= (int)$t['id'] ?>">
            <form method="post" action="lesson_topics.php?lesson_id=<?= $lessonId ?>">
              <input type="hidden" name="action" value="save">
              <input type="hidden" name="topic_id" value="<?= (int)$t['id'] ?>">
              <label>Title</label>
              <input type="text" name="title" value="<?= escHtml($t['title']) ?>" required>
              <label>Content</label>
              <textarea name="content"><?= escHtml($t['content_html'] ?? '') ?></textarea>
              <button class="btn primary" type="submit"><i class="fa-solid fa-check"></i> Save</button>
            </form>
          </div>

          <?php if ($isTop): ?>
            <div class="hidden-form" id="sub-<?= (int)$t['id'] ?>">
              <form method="post" action="lesson_topics.php?lesson_id=<?= $lessonId ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="topic_id" value="0">
                <input type="hidden" name="parent_id" value="<?= (int)$t['id'] ?>">
                <label>New sub-topic under &ldquo;<?= escHtml($t['title']) ?>&rdquo;</label>
                <input type="text" name="title" required placeholder="Sub-topic title">
                <textarea name="content" placeholder="Optional content&hellip;"></textarea>
                <button class="btn primary" type="submit"><i class="fa-solid fa-plus"></i> Add sub-topic</button>
              </form>
            </div>
            <button class="btn sm" onclick="tog('sub-<?= (int)$t['id'] ?>')" style="margin:0 16px 14px;"><i class="fa-solid fa-plus"></i> Sub-topic</button>
          <?php endif; ?>
        </div>
        <?php
    }
    foreach ($topics as $t) { renderTopicBlock($conn, $t, $lesson_id, true); }
    ?>
  <?php endif; ?>

  <p class="back"><a href="lesson_editor.php?course_id=<?= (int)$lesson['course_id'] ?>&lesson_id=<?= $lesson_id ?>">&laquo; Back to the lesson editor</a></p>
</div>

<script>
function tog(id){var el=document.getElementById(id);if(el){el.style.display=el.style.display==='block'?'none':'block';}}
function move(id,dir){var f=document.createElement('form');f.method='post';f.action='lesson_topics.php?lesson_id=<?= $lesson_id ?>';
  var a=document.createElement('input');a.type='hidden';a.name='action';a.value='move';f.appendChild(a);
  var b=document.createElement('input');b.type='hidden';b.name='topic_id';b.value=id;f.appendChild(b);
  var c=document.createElement('input');c.type='hidden';c.name='direction';c.value=dir;f.appendChild(c);
  document.body.appendChild(f);f.submit();}
function del(id,title){if(!confirm('Delete "'+title+'" and everything under it? Reading progress for it is removed too.'))return;
  var f=document.createElement('form');f.method='post';f.action='lesson_topics.php?lesson_id=<?= $lesson_id ?>';
  var a=document.createElement('input');a.type='hidden';a.name='action';a.value='delete';f.appendChild(a);
  var b=document.createElement('input');b.type='hidden';b.name='topic_id';b.value=id;f.appendChild(b);
  document.body.appendChild(f);f.submit();}
</script>
</body>
</html>
