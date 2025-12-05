<?php
require_once '../config/db.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    header("Location: ../login.php");
    exit;
}

$lecturer_id = $_SESSION['user_id'];

// ---------- Helper functions ----------
function json_exit($arr) {
    header('Content-Type: application/json');
    echo json_encode($arr);
    exit;
}

function safe_filename($name) {
    return preg_replace('/[^A-Za-z0-9_\.\-]/', '_', $name);
}

function ensure_upload_dir($dir) {
    if (!is_dir($dir)) mkdir($dir, 0777, true);
}

// ---------- GET assignment (AJAX) ----------
if (isset($_GET['action']) && $_GET['action'] === 'get_assignment') {

    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) json_exit(['success' => false, 'message' => 'Invalid id']);

    // Confirm ownership
    $chk = $conn->prepare("
        SELECT id, title, description, due_date, unit_id
        FROM interactive_assignments
        WHERE id=? AND lecturer_id=?
    ");
    $chk->bind_param("ii", $id, $lecturer_id);
    $chk->execute();
    $assignment = $chk->get_result()->fetch_assoc();

    if (!$assignment) {
        json_exit(['success' => false, 'message' => 'Not found or unauthorized']);
    }

    // Fetch questions
    $qstmt = $conn->prepare("
        SELECT id, question_text, question_type, points, media_url
        FROM interactive_questions
        WHERE interactive_assignment_id = ?
        ORDER BY id ASC
    ");
    $qstmt->bind_param("i", $id);
    $qstmt->execute();
    $qres = $qstmt->get_result();

    $questions = [];

    while ($q = $qres->fetch_assoc()) {
        $q['options'] = [];

        if ($q['question_type'] === 'multiple_choice') {
            $opts = $conn->prepare("
                SELECT id, option_text, is_correct
                FROM interactive_options
                WHERE question_id=?
                ORDER BY id ASC
            ");
            $opts->bind_param("i", $q['id']);
            $opts->execute();
            $optRes = $opts->get_result();

            while ($o = $optRes->fetch_assoc()) {
                $q['options'][] = $o;
            }
        }

        $questions[] = $q;
    }

    json_exit(['success' => true, 'assignment' => $assignment, 'questions' => $questions]);
}

// UPDATE interactive assignment
if (isset($_POST['action']) && $_POST['action'] === 'update_interactive_assignment') {

    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        $_SESSION['error'] = "Invalid assignment id.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $due_date = $_POST['due_date'] ?? null;
    $unit_id = intval($_POST['unit_id'] ?? 0);

    $questions = $_POST['questions'] ?? [];
    $questionFiles = $_FILES['questions'] ?? null;

    // Authorization check
    $chk = $conn->prepare("SELECT id FROM interactive_assignments WHERE id=? AND lecturer_id=?");
    $chk->bind_param("ii", $id, $lecturer_id);
    $chk->execute();

    if ($chk->get_result()->num_rows === 0) {
        $_SESSION['error'] = "Assignment not found or unauthorized.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    $conn->begin_transaction();

    try {

        // -------------------------
        // Update assignment record
        // -------------------------
        $ust = $conn->prepare("
            UPDATE interactive_assignments 
            SET title=?, description=?, due_date=?, unit_id=? 
            WHERE id=? AND lecturer_id=?
        ");
        $ust->bind_param("sssiii", $title, $description, $due_date, $unit_id, $id, $lecturer_id);
        $ust->execute();

        // -------------------------
        // Load existing questions
        // -------------------------
        $existingQStmt = $conn->prepare("
            SELECT id, media_url 
            FROM interactive_questions 
            WHERE interactive_assignment_id=?
        ");
        $existingQStmt->bind_param("i", $id);
        $existingQStmt->execute();

        $existingQIds = [];
        $existingFiles = [];

        $qRes = $existingQStmt->get_result();
        while ($row = $qRes->fetch_assoc()) {
            $existingQIds[] = (int)$row['id'];
            $existingFiles[(int)$row['id']] = $row['media_url'];
        }

        $postedQuestionIds = [];

        // -------------------------
        // Process questions
        // -------------------------
        foreach ($questions as $qIndex => $q) {

            $qid     = intval($q['id'] ?? 0);
            $qtext   = $q['text'] ?? '';
            $qtype   = $q['question_type'] ?? 'short_answer';
            $qpoints = intval($q['points'] ?? 1);
            $qcorrect = $q['correct'] ?? null;

            // File upload handler
            $media_url = null;

            if ($questionFiles &&
                isset($questionFiles['error'][$qIndex]['audio']) &&
                $questionFiles['error'][$qIndex]['audio'] === UPLOAD_ERR_OK) {

                $tmp = $questionFiles['tmp_name'][$qIndex]['audio'];
                $orig = basename($questionFiles['name'][$qIndex]['audio']);

                $uploadDir = __DIR__ . "/uploads/questions/";
                ensure_upload_dir($uploadDir);

                $safe = time() . "_" . safe_filename($orig);
                $target = $uploadDir . $safe;

                if (move_uploaded_file($tmp, $target)) {
                    $media_url = "uploads/questions/" . $safe;

                    // Remove previous file
                    if ($qid > 0 && !empty($existingFiles[$qid]) && file_exists(__DIR__ . '/' . $existingFiles[$qid])) {
                        unlink(__DIR__ . '/' . $existingFiles[$qid]);
                    }
                }
            }

            // -------------------------
            // UPDATE EXISTING QUESTION
            // -------------------------
            if ($qid > 0 && in_array($qid, $existingQIds)) {

                if ($media_url === null) {
                    $media_url = $existingFiles[$qid] ?? null;
                }

                $uq = $conn->prepare("
                    UPDATE interactive_questions 
                    SET question_text=?, question_type=?, points=?, media_url=? 
                    WHERE id=? AND interactive_assignment_id=?
                ");
                $uq->bind_param("ssissi", $qtext, $qtype, $qpoints, $media_url, $qid, $id);
                $uq->execute();

                $postedQuestionIds[] = $qid;

                // MCQ options
                if ($qtype === "multiple_choice") {

                    // delete all old options and rebuild cleanly
                    $conn->query("DELETE FROM interactive_options WHERE question_id=$qid");

                    if (!empty($q['options'])) {
                        foreach ($q['options'] as $idx => $opt) {
                            $opt_text = is_array($opt) ? $opt['text'] : $opt;
                            if (trim($opt_text) === '') continue;

                            $is_correct = ($qcorrect == ($idx + 1)) ? 1 : 0;

                            $ins = $conn->prepare("
                                INSERT INTO interactive_options (question_id, option_text, is_correct)
                                VALUES (?, ?, ?)
                            ");
                            $ins->bind_param("isi", $qid, $opt_text, $is_correct);
                            $ins->execute();
                        }
                    }
                } else {
                    // delete MCQ options when switching to non-MCQ
                    $del = $conn->prepare("DELETE FROM interactive_options WHERE question_id=?");
                    $del->bind_param("i", $qid);
                    $del->execute();
                }
            }

            // -------------------------
            // INSERT NEW QUESTION
            // -------------------------
            else {

                $ins = $conn->prepare("
                    INSERT INTO interactive_questions 
                    (interactive_assignment_id, question_text, question_type, points, media_url)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $ins->bind_param("issis", $id, $qtext, $qtype, $qpoints, $media_url);
                $ins->execute();

                $newQid = $ins->insert_id;
                $postedQuestionIds[] = $newQid;

                // Insert MCQ options
                if ($qtype === "multiple_choice" && !empty($q['options'])) {
                    foreach ($q['options'] as $idx => $opt) {
                        $opt_text = is_array($opt) ? $opt['text'] : $opt;
                        if (trim($opt_text) === '') continue;

                        $is_correct = ($qcorrect == ($idx + 1)) ? 1 : 0;

                        $ins2 = $conn->prepare("
                            INSERT INTO interactive_options (question_id, option_text, is_correct)
                            VALUES (?, ?, ?)
                        ");
                        $ins2->bind_param("isi", $newQid, $opt_text, $is_correct);
                        $ins2->execute();
                    }
                }
            }
        }

        // -------------------------
        // DELETE QUESTIONS REMOVED BY USER
        // -------------------------
        $toDelete = array_diff($existingQIds, $postedQuestionIds);
        if (!empty($toDelete)) {

            foreach ($toDelete as $delId) {
                if (!empty($existingFiles[$delId]) && file_exists(__DIR__ . '/' . $existingFiles[$delId])) {
                    unlink(__DIR__ . '/' . $existingFiles[$delId]);
                }
            }

            $in = implode(",", array_map("intval", $toDelete));
            $conn->query("DELETE FROM interactive_options WHERE question_id IN ($in)");
            $conn->query("DELETE FROM interactive_questions WHERE id IN ($in)");
        }

        $conn->commit();

        $_SESSION['success'] = "Interactive assignment updated successfully.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Update failed: " . $e->getMessage();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}


// ---------- FETCH lists for initial page output ----------
$unitsStmt = $conn->prepare("
    SELECT u.id, u.name 
    FROM units u
    INNER JOIN lecturer_units lu ON lu.unit_id = u.id
    WHERE lu.lecturer_id = ?
    ORDER BY u.name ASC
");
$unitsStmt->bind_param("i", $lecturer_id);
$unitsStmt->execute();
$units = $unitsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt = $conn->prepare("
    SELECT id, title, created_at, due_date 
    FROM interactive_assignments 
    WHERE lecturer_id = ? 
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $lecturer_id);
$stmt->execute();
$assignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// CREATE assignment
if (isset($_POST['action']) && $_POST['action'] === 'create_interactive_assignment') {

    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $due_date = $_POST['due_date'] ?? null;
    $unit_id = intval($_POST['unit_id'] ?? 0);
    $questions = $_POST['questions'] ?? [];

    $conn->begin_transaction();

    try {

        // Insert assignment
        $ins = $conn->prepare("
            INSERT INTO interactive_assignments 
            (lecturer_id, unit_id, title, description, due_date, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $ins->bind_param("iisss", $lecturer_id, $unit_id, $title, $description, $due_date);
        $ins->execute();

        $assignment_id = $conn->insert_id;

        $questionFiles = $_FILES['questions'] ?? null;

        foreach ($questions as $i => $q) {

            $qtext   = $q['text'] ?? '';
            $qtype   = $q['type'] ?? 'short_answer';
            $qpoints = intval($q['points'] ?? 1);
            $qcorrect = $q['correct'] ?? null;

            // Handle media
            $media_url = null;

            if ($questionFiles &&
                isset($questionFiles['error'][$i]['audio']) &&
                $questionFiles['error'][$i]['audio'] === UPLOAD_ERR_OK) {

                $tmp = $questionFiles['tmp_name'][$i]['audio'];
                $orig = basename($questionFiles['name'][$i]['audio']);
                $uploadDir = __DIR__ . "/uploads/questions/";
                ensure_upload_dir($uploadDir);

                $safe = time() . "_" . safe_filename($orig);
                $target = $uploadDir . $safe;

                if (move_uploaded_file($tmp, $target)) {
                    $media_url = "uploads/questions/" . $safe;
                }
            }

            // INSERT QUESTION (FIXED COLUMN NAMES)
            $qins = $conn->prepare("
                INSERT INTO interactive_questions
                (interactive_assignment_id, question_text, question_type, points, media_url)
                VALUES (?, ?, ?, ?, ?)
            ");
            $qins->bind_param("issis", $assignment_id, $qtext, $qtype, $qpoints, $media_url);
            $qins->execute();

            $qid = $conn->insert_id;

            // Insert MCQ Options
            if ($qtype === "multiple_choice" && !empty($q['options'])) {
                foreach ($q['options'] as $index => $optText) {
                    if (trim($optText) === "") continue;

                    $is_correct = ($qcorrect == ($index + 1)) ? 1 : 0;

                    $oin = $conn->prepare("
                        INSERT INTO interactive_options (question_id, option_text, is_correct)
                        VALUES (?, ?, ?)
                    ");
                    $oin->bind_param("isi", $qid, $optText, $is_correct);
                    $oin->execute();
                }
            }
        }

        $conn->commit();
        $_SESSION['success'] = "Assignment created with questions.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;

    } catch (Exception $ex) {
        $conn->rollback();
        $_SESSION['error'] = "Create failed: " . $ex->getMessage();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}
if (isset($_POST['action']) && $_POST['action'] === 'extend_deadline') {
    $id = intval($_POST['id'] ?? 0);
    $new_due = $_POST['new_due_date'] ?? null;

    if ($id <= 0 || !$new_due) json_exit(['success' => false, 'message' => 'Invalid input']);

    $stmt = $conn->prepare("UPDATE interactive_assignments SET due_date=? WHERE id=? AND lecturer_id=?");
    $stmt->bind_param("sii", $new_due, $id, $lecturer_id);
    if ($stmt->execute()) {
        json_exit(['success' => true]);
    } else {
        json_exit(['success' => false, 'message' => 'Update failed']);
    }
}

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Create / Manage Interactive Assignments</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
:root{--primary:#3498db;--accent:#2ecc71;--warn:#f39c12;--danger:#e74c3c;--bg:#f7f8fb;--muted:#7b8794}
body{font-family:Segoe UI,Roboto,Arial;margin:0;padding:20px;background:var(--bg);color:#2c3e50}
.container{max-width:1200px;margin:0 auto}
.section{background:#fff;padding:22px;border-radius:10px;box-shadow:0 6px 18px rgba(0,0,0,0.06);margin-bottom:20px}
.section h2{margin-top:0;border-left:6px solid var(--primary);padding-left:10px}
table{width:100%;border-collapse:collapse;margin-top:10px}
table th,table td{padding:12px;border-bottom:1px solid #eef2f6;text-align:left}
table th{background:var(--primary);color:#fff}
.btn{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:6px;border:0;cursor:pointer;text-decoration:none;color:#fff}
.btn-edit{background:var(--warn)}
.btn-delete{background:var(--danger)}
.btn-primary{background:var(--primary)}
.small{font-size:13px;color:var(--muted)}
.input-group label{display:block;margin-bottom:6px;font-weight:600}
.input{width:100%;padding:10px;border-radius:6px;border:1px solid #dcdfe6}
.assignment-row{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
.question-card{border:1px solid #e6e9ee;padding:14px;border-radius:8px;margin-bottom:12px;position:relative;background:#fff}
.question-number{position:absolute;left:-12px;top:-12px;background:#2c3e50;color:#fff;border-radius:50%;width:32px;height:32px;display:flex;align-items:center;justify-content:center;font-weight:700}
.option-input{display:flex;gap:8px;align-items:center;margin-bottom:8px}
.btn-inline{padding:6px 10px;border-radius:6px;border:0;cursor:pointer}
.btn-add{background:var(--primary);color:#fff}
.btn-green{background:var(--accent);color:#fff}
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:999;align-items:center;justify-content:center}
.modal-content{background:#fff;width:90%;max-width:1000px;border-radius:10px;padding:18px;max-height:90vh;overflow:auto}
.close{float:right;font-size:20px;cursor:pointer}
.questions-list .question-card{margin:10px 0;padding:10px;background:#f9f9f9}
</style>
</head>
<body>

<div class="container">

  <!-- Manage Assignments -->
  <div class="section">
    <h2>My Interactive Assignments</h2>

    <?php if (!empty($_SESSION['error'])): ?>
      <div style="color:#b71c1c;margin-bottom:10px"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['success'])): ?>
      <div style="color:#1b5e20;margin-bottom:10px"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <?php if ($assignments): ?>
      <table>
        <thead><tr><th>Title</th><th>Date Created</th><th>Deadline</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($assignments as $a): ?>
            <tr>
              <td><?= htmlspecialchars($a['title']) ?></td>
              <td class="small"><?= htmlspecialchars($a['created_at']) ?></td>
              <td class="small"><?= htmlspecialchars($a['due_date']) ?></td>
              <td>
                <button class="btn btn-edit" onclick="openEditModal(<?= $a['id'] ?>)"><i class="fas fa-edit"></i> Edit</button>
                <button class="btn btn-add" onclick="viewQuestions(<?= $a['id'] ?>, this)"><i class="fas fa-list"></i> View Questions</button>
                <a class="btn btn-primary" href="view_scores.php?id=<?= $a['id'] ?>"><i class="fas fa-chart-bar"></i> View Scores</a>
                <button class="btn btn-delete" onclick="deleteAssignment(<?= $a['id'] ?>)"><i class="fas fa-trash"></i> Delete</button>
              </td>
            </tr>
            <tr class="questions-row" style="display:none"><td colspan="4"><div class="questions-list"></div></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p>No interactive assignments found.</p>
    <?php endif; ?>
  </div>

  <!-- Create New Assignment -->
<div class="section">
  <h2>Create New Interactive Assignment</h2>

  <form id="assignmentForm" method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" enctype="multipart/form-data">
    <input type="hidden" name="action" value="create_interactive_assignment">

    <div class="assignment-row">
      <div>
        <label class="input-group label">Title</label>
        <input class="input" type="text" name="title" required>
      </div>
      <div>
        <label class="input-group label">Description</label>
        <input class="input" type="text" name="description" required>
      </div>
      <div>
        <label class="input-group label">Due Date</label>
        <input class="input" type="datetime-local" name="due_date" required>
      </div>
      <div>
        <label class="input-group label">Unit</label>
        <select class="input" name="unit_id" required>
          <option value="">-- Select Unit --</option>
          <?php foreach ($units as $u): ?>
            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <h3>Questions</h3>
    <div id="questionsContainer"></div>

    <div style="display:flex;gap:12px;margin-top:8px">
      <button type="button" class="btn btn-add" onclick="addQuestion()">+ Add Question</button>
      <button type="submit" class="btn btn-green">Save Assignment</button>
    </div>
  </form>
</div>



<!-- Edit Modal -->
<div id="editModal" class="modal" role="dialog" aria-modal="true">
  <div class="modal-content">
    <span class="close" onclick="closeEditModal()">&times;</span>
    <h3>Edit Assignment</h3>
    <div id="edit_error" style="display:none;color:#b71c1c;margin:8px 0;">Error</div>

    <form id="editForm" method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" enctype="multipart/form-data">
      <input type="hidden" name="action" value="update_interactive_assignment">
      <input type="hidden" name="id" id="edit_id">

      <div class="assignment-row" style="margin-bottom:12px">
        <div>
          <label class="input-group label">Title</label>
          <input class="input" type="text" name="title" id="edit_title" required>
        </div>
        <div>
          <label class="input-group label">Description</label>
          <input class="input" type="text" name="description" id="edit_description" required>
        </div>
        <div>
          <label class="input-group label">Due Date</label>
          <input class="input" type="datetime-local" name="due_date" id="edit_due_date" required>
        </div>
        <div>
  <label class="input-group label">Due Date</label>
  <input class="input" type="datetime-local" name="due_date" id="edit_due_date" required>
  <!-- Extend Deadline Button -->
  <button type="button" class="btn btn-warning" style="margin-top:8px;" onclick="extendDeadlineModal()">
    Extend Deadline
  </button>
</div>

        <div>
          <label class="input-group label">Unit</label>
          <select class="input" name="unit_id" id="edit_unit_id" required>
            <option value="">-- Select Unit --</option>
            <?php foreach ($units as $u): ?>
              <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <h4>Questions</h4>
      <div id="editQuestionsContainer"></div>

      <div style="display:flex;gap:12px;margin-top:12px">
        <button type="button" class="btn btn-add" onclick="editAddQuestion()">+ Add New Question</button>
        
        <button type="submit" class="btn btn-green">Save Changes</button>
      </div>
    </form>
  </div>
</div>


<script>
let questionCount = 0;
let createQuestionIndex = 0;
let editQuestionIndex = 0;

/* ---------- CREATE / ADD QUESTIONS ---------- */
function addQuestion(prefill = null) {
  const container = document.getElementById('questionsContainer');
  const idx = createQuestionIndex++;
  container.insertAdjacentHTML('beforeend', createQuestionMarkup(idx, prefill));
}

function createQuestionMarkup(idx, prefill = null) {
  const type = (prefill && prefill.type) ? prefill.type : 'text';
  const textVal = (prefill && prefill.question_text) ? prefill.question_text : '';
  const pointsVal = (prefill && prefill.points) ? prefill.points : 1;
  const mediaNote = (prefill && prefill.media_url) ? `<div class="small">Existing media: <a target="_blank" href="${prefill.media_url}">view</a></div>` : '';
  const imageNote = (prefill && prefill.image_url) ? `<div class="small">Existing image: <a target="_blank" href="${prefill.image_url}">view</a></div>` : '';

  return `
    <div class="question-card" id="create_q_${idx}">
      <div class="question-number">${idx + 1}</div>
      <div>
        <label class="input-group label">Question Type</label>
        <select class="input" name="questions[${idx}][type]" onchange="createToggleOptions(${idx})">
          <option value="text"${type==='text'?' selected':''}>Text Answer</option>
          <option value="multiple_choice"${type==='multiple_choice'?' selected':''}>Multiple Choice</option>
          <option value="speech"${type==='speech'?' selected':''}>Speech / Audio</option>
        </select>
      </div>

      <div style="margin-top:8px">
        <label class="input-group label">Question Text</label>
        <textarea class="input" name="questions[${idx}][text]" required>${textVal}</textarea>
      </div>

      <div style="margin-top:8px">
        <label class="input-group label">Points</label>
        <input class="input" type="number" name="questions[${idx}][points]" min="1" value="${pointsVal}" required>
      </div>

      <div id="create_options_${idx}" style="margin-top:8px; display:${type==='multiple_choice'?'block':'none'}">
        <label class="input-group label">Options</label>
        <div id="create_options_list_${idx}">
          <div class="option-input">
            <input type="radio" name="questions[${idx}][correct]" value="1">
            <input class="input" type="text" name="questions[${idx}][options][]" placeholder="Option 1">
          </div>
          <div class="option-input">
            <input type="radio" name="questions[${idx}][correct]" value="2">
            <input class="input" type="text" name="questions[${idx}][options][]" placeholder="Option 2">
          </div>
        </div>
        <button type="button" class="btn btn-add" onclick="createAddOption(${idx})">Add option</button>
      </div>

      <div id="create_audio_${idx}" style="margin-top:8px; display:${type==='speech'?'block':'none'}">
        <label class="input-group label">Upload question audio (optional)</label>
        <input type="file" name="questions[${idx}][audio]" accept="audio/*">
        ${mediaNote}
      </div>

      <div id="create_image_${idx}" style="margin-top:8px; display:block">
        <label class="input-group label">Upload question image (optional)</label>
        <input type="file" name="questions[${idx}][image]" accept="image/*">
        ${imageNote}
      </div>

      <div style="margin-top:10px">
        <button type="button" class="btn btn-delete" onclick="removeCreateQuestion(${idx})">Remove Question</button>
      </div>
    </div>
  `;
}

function createAddOption(idx){
  const div = document.getElementById('create_options_list_'+idx);
  if(!div) return;
  const count = div.querySelectorAll('.option-input').length + 1;
  const html = `<div class="option-input">
    <input type="radio" name="questions[${idx}][correct]" value="${count}">
    <input class="input" type="text" name="questions[${idx}][options][]" placeholder="Option ${count}">
  </div>`;
  div.insertAdjacentHTML('beforeend', html);
}

function removeCreateQuestion(idx){
  const el = document.getElementById('create_q_'+idx);
  if(el) el.remove();
}

function createToggleOptions(idx){
  const sel = document.querySelector(`select[name="questions[${idx}][type]"]`);
  if(!sel) return;
  document.getElementById('create_options_'+idx).style.display = (sel.value === 'multiple_choice') ? 'block' : 'none';
  document.getElementById('create_audio_'+idx).style.display = (sel.value === 'speech') ? 'block' : 'none';
}
function editQuestionMarkup(idx, q) {
  const type = q.type || 'text';

  /* --- PREVIEW MEDIA --- */
  const imagePreview = q.image_url 
    ? `<div class="small"><p>Existing image:</p>
         <img src="${q.image_url}" style="max-width:150px;max-height:150px;border:1px solid #ccc;margin-top:6px;"></div>`
    : '';

  const audioPreview = q.media_url
    ? `<div class="small"><p>Existing audio:</p>
         <audio controls style="margin-top:6px; max-width:200px;">
            <source src="${q.media_url}">
         </audio></div>`
    : '';

  /* --- MULTIPLE CHOICE OPTIONS --- */
  let optionsHtml = '';
  if (type === 'multiple_choice' && q.options && q.options.length) {
    q.options.forEach((opt, oi) => {
      optionsHtml += `
        <div class="option-input" style="margin-bottom:6px;">
          <input type="radio" name="questions[${idx}][correct]" value="${oi+1}" ${opt.is_correct==1?'checked':''}>
          <input class="input" type="text" name="questions[${idx}][options][]" 
                 value="${escapeHtml(opt.option_text)}" placeholder="Option ${oi+1}">
        </div>`;
    });
  }

  /* -----------------------
      TEXT ANSWER FIELDS
  ------------------------ */
  const textAnswerHtml = `
    <div id="edit_text_answer_${idx}" style="margin-top:8px; display:${type==='text' ? 'block' : 'none'}">
      <label class="input-group label">Correct Answer (Lecturer Answer)</label>
      <textarea class="input" name="questions[${idx}][correct_answer]" rows="2"
      >${q.correct_answer ? escapeHtml(q.correct_answer) : ''}</textarea>

      <label class="input-group label" style="margin-top:8px;">Keywords (comma separated)</label>
      <input class="input" type="text" name="questions[${idx}][keywords]"
             value="${q.keywords ? escapeHtml(q.keywords) : ''}" 
             placeholder="e.g. photosynthesis, chlorophyll, sunlight">
    </div>
  `;

  return `
    <div class="question-card" id="edit_q_${idx}">
      <div class="question-number">${idx + 1}</div>

      <input type="hidden" name="questions[${idx}][id]" value="${q.id}">

      <div>
        <label class="input-group label">Question Type</label>
        <select class="input" name="questions[${idx}][type]" onchange="editToggleOptions(${idx})">
          <option value="text"${type==='text'?' selected':''}>Text Answer</option>
          <option value="multiple_choice"${type==='multiple_choice'?' selected':''}>Multiple Choice</option>
          <option value="speech"${type==='speech'?' selected':''}>Speech / Audio</option>
        </select>
      </div>

      <div style="margin-top:8px">
        <label class="input-group label">Question</label>
        <textarea class="input" name="questions[${idx}][text]" rows="2" required>${escapeHtml(q.question_text)}</textarea>
      </div>

      <div style="margin-top:8px">
        <label class="input-group label">Points</label>
        <input class="input" type="number" min="1" name="questions[${idx}][points]" value="${q.points}">
      </div>

      ${textAnswerHtml}

      <!-- MCQ -->
      <div id="edit_options_${idx}" style="margin-top:8px; display:${type==='multiple_choice' ? 'block' : 'none'}">
        <label class="input-group label">Options</label>
        ${optionsHtml}
        <button type="button" class="btn btn-add" onclick="editAddOption(${idx})">Add option</button>
      </div>

      <!-- AUDIO -->
      <div id="edit_audio_${idx}" style="margin-top:8px; display:${type==='speech'?'block':'none'}">
        <label class="input-group label">Replace / Upload question audio</label>
        <input type="file" name="questions[${idx}][audio]" accept="audio/*">
        ${audioPreview}
      </div>

      <!-- IMAGE -->
      <div id="edit_image_${idx}" style="margin-top:8px; display:block">
        <label class="input-group label">Replace / Upload question image</label>
        <input type="file" name="questions[${idx}][image]" accept="image/*">
        ${imagePreview}
      </div>

      <div style="margin-top:10px">
        <button type="button" class="btn btn-delete" onclick="removeEditQuestion(${idx})">Remove Question</button>
      </div>
    </div>
  `;
}

function removeEditQuestion(idx){
  const el = document.getElementById('edit_q_'+idx);
  if(el) el.remove();
}
function editToggleOptions(idx) {
  const type = document.querySelector(`select[name="questions[${idx}][type]"]`).value;

  document.getElementById(`edit_text_answer_${idx}`).style.display =
      type === 'text' ? 'block' : 'none';

  document.getElementById(`edit_options_${idx}`).style.display =
      type === 'multiple_choice' ? 'block' : 'none';

  document.getElementById(`edit_audio_${idx}`).style.display =
      type === 'speech' ? 'block' : 'none';
}

function editAddOption(qIdx){
  const div = document.getElementById('edit_options_'+qIdx);
  if(!div) return;
  const count = div.querySelectorAll('.option-input').length + 1;
  const html = `<div class="option-input">
    <input type="radio" name="questions[${qIdx}][correct]" value="${count}">
    <input class="input" type="text" name="questions[${qIdx}][options][]" placeholder="Option ${count}">
  </div>`;
  div.insertAdjacentHTML('beforeend', html);
}

function editAddQuestion(){
  const container = document.getElementById('editQuestionsContainer');
  const idx = 10000 + (editQuestionIndex++);
  const html = createQuestionMarkup(idx);
  container.insertAdjacentHTML('beforeend', html);
}

/* ---------- VIEW / DELETE ASSIGNMENTS ---------- */
function viewQuestions(id, btn){
  const row = btn.closest('tr');
  const qRow = row.nextElementSibling;
  const container = qRow.querySelector('.questions-list');
  const isVisible = qRow.style.display === 'table-row';

  if (isVisible) { qRow.style.display = 'none'; return; }

  qRow.style.display = 'table-row';
  container.innerHTML = '<div class="small">Loading questions...</div>';

  fetch(`?action=get_assignment&id=${id}`)
    .then(r => r.json())
    .then(data => {
      if (!data || data.success === false) {
        container.innerHTML = `<div style="color:#b71c1c">${(data && (data.message||data.error)) || 'Failed to load questions.'}</div>`;
        return;
      }
      const questions = data.questions || [];
      if (!questions.length) {
        container.innerHTML = '<div class="small">No questions added yet.</div>';
        return;
      }
      const html = questions.map((q, i) => {
        const options = (q.type === 'multiple_choice' && q.options && q.options.length)
          ? `<ul style="margin:5px 0;padding-left:20px">` + q.options.map(o => 
              `<li>${escapeHtml(o.option_text)} ${o.is_correct==1?'✅':''}</li>`
            ).join('') + `</ul>`
          : '';
        const media = q.media_url ? `<div class="small">Media: <a target="_blank" href="${q.media_url}">view</a></div>` : '';
        const image = q.image_url ? `<div class="small">Image: <a target="_blank" href="${q.image_url}">view</a></div>` : '';
        return `<div class="question-card">
          <div class="question-number">${i+1}</div>
          <div><b>Type:</b> ${q.type} &nbsp; <b>Points:</b> ${q.points}</div>
          <div style="margin-top:6px"><b>Q:</b> ${escapeHtml(q.question_text)}</div>
          ${media}${image}
          ${options}
        </div>`;
      }).join('');
      container.innerHTML = html;
    })
    .catch(e => {
      container.innerHTML = '<div style="color:#b71c1c">Error loading questions.</div>';
      console.error(e);
    });
}

function deleteAssignment(id) {
  if (confirm('Are you sure you want to delete this assignment? This action cannot be undone.')) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>';

    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'delete_interactive_assignment';
    form.appendChild(actionInput);

    const idInput = document.createElement('input');
    idInput.type = 'hidden';
    idInput.name = 'id';
    idInput.value = id;
    form.appendChild(idInput);

    document.body.appendChild(form);
    form.submit();
  }
}

/* ---------- UTILITY ---------- */
function escapeHtml(str) {
  if (!str) return '';
  return String(str).replace(/[&<>"'\/]/g, s =>
    ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','/':'&#47;'}[s])
  );
}

/* ---------- INIT ---------- */
addQuestion();

// Close modal when clicking outside
window.onclick = function(event) {
  const modal = document.getElementById('editModal');
  if (event.target == modal) { modal.style.display = 'none'; }
}
function extendDeadlineModal() {
    const assignmentId = document.getElementById('edit_id').value;
    const currentDue = document.getElementById('edit_due_date').value;

    let newDate = prompt("Enter new deadline (YYYY-MM-DD HH:MM):", currentDue);
    if (!newDate) return;

    fetch('interactive_assignments.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=extend_deadline&id=' + assignmentId + '&new_due_date=' + encodeURIComponent(newDate)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Deadline updated!');
            document.getElementById('edit_due_date').value = newDate; // update modal input
        } else {
            alert('Error: ' + data.message);
        }
    });
}

</script>

</body>
</html>