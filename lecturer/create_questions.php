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

// ---------- Handle AJAX requests first ----------
// GET assignment data (for edit modal and view questions)
if (isset($_GET['action']) && $_GET['action'] === 'get_assignment') {
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) json_exit(['success' => false, 'message' => 'Invalid id']);

    // Ensure ownership
    $chk = $conn->prepare("SELECT id, title, description, due_date, unit_id 
                           FROM interactive_assignments 
                           WHERE id=? AND lecturer_id=?");
    $chk->bind_param("ii", $id, $lecturer_id);
    $chk->execute();
    $assignment = $chk->get_result()->fetch_assoc();
    if (!$assignment) json_exit(['success' => false, 'message' => 'Not found or unauthorized']);

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

        // FIX: use question_type instead of type
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

// ---------- Handle POST actions ----------
// DELETE assignment
if (isset($_POST['action']) && $_POST['action'] === 'delete_interactive_assignment') {
    $id = intval($_POST['id'] ?? 0);
    if ($id > 0) {
        // Ensure ownership and delete associated files
        $stmt = $conn->prepare("SELECT media_url FROM interactive_questions WHERE assignment_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['media_url']) && file_exists(__DIR__ . '/' . $row['media_url'])) {
                unlink(__DIR__ . '/' . $row['media_url']);
            }
        }
        
        $stmt = $conn->prepare("DELETE FROM interactive_assignments WHERE id=? AND lecturer_id=?");
        $stmt->bind_param("ii", $id, $lecturer_id);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Assignment deleted successfully!";
        } else {
            $_SESSION['error'] = "Failed to delete assignment.";
        }
    } else {
        $_SESSION['error'] = "Invalid assignment id.";
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// CREATE assignment (with questions)
if (isset($_POST['action']) && $_POST['action'] === 'create_interactive_assignment') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $due_date = $_POST['due_date'] ?? null;
    $unit_id = intval($_POST['unit_id'] ?? 0);
    $questions = $_POST['questions'] ?? [];

    $conn->begin_transaction();
    try {
        // Insert assignment
        $ins = $conn->prepare("INSERT INTO interactive_assignments (lecturer_id, unit_id, title, description, due_date, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $ins->bind_param("iisss", $lecturer_id, $unit_id, $title, $description, $due_date);
        $ins->execute();
        $assignment_id = $ins->insert_id;

        // Handle files
        $questionFiles = $_FILES['questions'] ?? null;

        foreach ($questions as $i => $q) {
            $qtype = $q['type'] ?? 'text';
            $qtext = $q['text'] ?? '';
            $qpoints = intval($q['points'] ?? 1);
            $qcorrect = $q['correct'] ?? null;

            $media_url = null;
            if ($questionFiles
                && isset($questionFiles['error'][$i]['audio'])
                && $questionFiles['error'][$i]['audio'] === UPLOAD_ERR_OK
            ) {
                $tmpName = $questionFiles['tmp_name'][$i]['audio'];
                $orig = basename($questionFiles['name'][$i]['audio']);
                $uploadDir = __DIR__ . "/uploads/questions/";
                ensure_upload_dir($uploadDir);
                $safe = time() . "_" . safe_filename($orig);
                $target = $uploadDir . $safe;
                if (move_uploaded_file($tmpName, $target)) {
                    $media_url = "uploads/questions/" . $safe;
                }
            }

            // Insert question
            $qins = $conn->prepare("INSERT INTO interactive_questions (assignment_id, question_text, type, points, media_url) VALUES (?, ?, ?, ?, ?)");
            $qins->bind_param("issis", $assignment_id, $qtext, $qtype, $qpoints, $media_url);
            $qins->execute();
            $qid = $qins->insert_id;

            // Handle MCQ options
            if ($qtype === 'multiple_choice' && !empty($q['options'])) {
                foreach ($q['options'] as $optIndex => $optText) {
                    if (trim($optText) === '') continue;
                    $is_correct = ($qcorrect !== null && intval($qcorrect) == ($optIndex + 1)) ? 1 : 0;
                    $oin = $conn->prepare("INSERT INTO interactive_options (question_id, option_text, is_correct) VALUES (?, ?, ?)");
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

    // Ownership check
    $chk = $conn->prepare("SELECT id FROM interactive_assignments WHERE id=? AND lecturer_id=?");
    $chk->bind_param("ii", $id, $lecturer_id);
    $chk->execute();
    $cres = $chk->get_result();
    if ($cres->num_rows === 0) {
        $_SESSION['error'] = "Assignment not found / not authorized";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    $questionFiles = $_FILES['questions'] ?? null;

    $conn->begin_transaction();
    try {
        // Update assignment
        $ust = $conn->prepare("UPDATE interactive_assignments SET title=?, description=?, due_date=?, unit_id=? WHERE id=? AND lecturer_id=?");
        $ust->bind_param("sssiii", $title, $description, $due_date, $unit_id, $id, $lecturer_id);
        $ust->execute();

        // Existing questions in DB
        $existingQStmt = $conn->prepare("SELECT id, media_url FROM interactive_questions WHERE assignment_id=?");
        $existingQStmt->bind_param("i", $id);
        $existingQStmt->execute();
        $existingQRes = $existingQStmt->get_result();
        $existingQIds = [];
        $existingFiles = [];
        while ($r = $existingQRes->fetch_assoc()) {
            $existingQIds[] = (int)$r['id'];
            $existingFiles[(int)$r['id']] = $r['media_url'];
        }

        $postedQuestionIds = [];

        foreach ($questions as $qIndex => $q) {
            $qid = intval($q['id'] ?? 0);
            $qtext = $q['text'] ?? '';
            $qtype = $q['type'] ?? 'text';
            $qpoints = intval($q['points'] ?? 1);
            $qcorrect = $q['correct'] ?? null;

            // Handle file upload
            $media_url = null;
            if ($questionFiles
                && isset($questionFiles['error'][$qIndex]['audio'])
                && $questionFiles['error'][$qIndex]['audio'] === UPLOAD_ERR_OK
            ) {
                $tmpName = $questionFiles['tmp_name'][$qIndex]['audio'];
                $orig = basename($questionFiles['name'][$qIndex]['audio']);
                $uploadDir = __DIR__ . "/uploads/questions/";
                ensure_upload_dir($uploadDir);
                $safe = time() . "_" . safe_filename($orig);
                $target = $uploadDir . $safe;
                if (move_uploaded_file($tmpName, $target)) {
                    $media_url = "uploads/questions/" . $safe;
                    
                    // Delete old file if exists
                    if ($qid > 0 && isset($existingFiles[$qid]) && 
                        !empty($existingFiles[$qid]) && file_exists(__DIR__ . '/' . $existingFiles[$qid])) {
                        unlink(__DIR__ . '/' . $existingFiles[$qid]);
                    }
                }
            }

            if ($qid > 0 && in_array($qid, $existingQIds)) {
                // Update existing question
                if ($media_url === null) {
                    // Keep existing media_url
                    $media_url = $existingFiles[$qid] ?? null;
                }
                
                $uq = $conn->prepare("UPDATE interactive_questions SET question_text=?, type=?, points=?, media_url=? WHERE id=? AND assignment_id=?");
                $uq->bind_param("ssisii", $qtext, $qtype, $qpoints, $media_url, $qid, $id);
                $uq->execute();
                $postedQuestionIds[] = $qid;

                // Options handling
                if ($qtype === 'multiple_choice') {
                    $postedOptionIds = [];
                    $optionsArr = $q['options'] ?? [];
                    
                    // Handle both simple array and complex array formats
                    if (isset($optionsArr[0]) && is_string($optionsArr[0])) {
                        // Simple format: options[]
                        foreach ($optionsArr as $optIndex => $optText) {
                            if (trim($optText) === '') continue;
                            $is_correct = ($qcorrect !== null && intval($qcorrect) == ($optIndex + 1)) ? 1 : 0;
                            
                            // For simplicity, delete all and reinsert
                            $conn->query("DELETE FROM interactive_options WHERE question_id=$qid");
                            
                            foreach ($optionsArr as $optIndex2 => $optText2) {
                                if (trim($optText2) === '') continue;
                                $is_correct2 = ($qcorrect !== null && intval($qcorrect) == ($optIndex2 + 1)) ? 1 : 0;
                                $in = $conn->prepare("INSERT INTO interactive_options (question_id, option_text, is_correct) VALUES (?, ?, ?)");
                                $in->bind_param("isi", $qid, $optText2, $is_correct2);
                                $in->execute();
                            }
                            break;
                        }
                    } else {
                        // Complex format: options[][text]
                        foreach ($optionsArr as $optIndex => $opt) {
                            $opt_id = intval($opt['id'] ?? 0);
                            $opt_text = $opt['text'] ?? '';
                            $is_correct = ($qcorrect !== null && intval($qcorrect) == ($optIndex + 1)) ? 1 : 0;

                            if ($opt_id > 0) {
                                $up = $conn->prepare("UPDATE interactive_options SET option_text=?, is_correct=? WHERE id=? AND question_id=?");
                                $up->bind_param("siii", $opt_text, $is_correct, $opt_id, $qid);
                                $up->execute();
                                $postedOptionIds[] = $opt_id;
                            } else {
                                $in = $conn->prepare("INSERT INTO interactive_options (question_id, option_text, is_correct) VALUES (?, ?, ?)");
                                $in->bind_param("isi", $qid, $opt_text, $is_correct);
                                $in->execute();
                                $postedOptionIds[] = $conn->insert_id;
                            }
                        }

                        // Delete options not in posted list
                        $optRes = $conn->query("SELECT id FROM interactive_options WHERE question_id=" . intval($qid));
                        $existingOptIds = [];
                        while ($row = $optRes->fetch_assoc()) $existingOptIds[] = (int)$row['id'];
                        $toDelete = array_diff($existingOptIds, $postedOptionIds);
                        if (!empty($toDelete)) {
                            $in = implode(',', array_map('intval', $toDelete));
                            $conn->query("DELETE FROM interactive_options WHERE id IN ($in)");
                        }
                    }
                } else {
                    // Remove options for non-MCQ questions
                    $del = $conn->prepare("DELETE FROM interactive_options WHERE question_id=?");
                    $del->bind_param("i", $qid);
                    $del->execute();
                }
            } else {
                // Insert new question
                if ($media_url === null && isset($q['audio_keep']) && $q['audio_keep'] === 'true') {
                    // Keep media_url from prefill if needed
                    $media_url = $q['media_url'] ?? null;
                }
                
                $insQ = $conn->prepare("INSERT INTO interactive_questions (assignment_id, question_text, type, points, media_url) VALUES (?, ?, ?, ?, ?)");
                $insQ->bind_param("issis", $id, $qtext, $qtype, $qpoints, $media_url);
                $insQ->execute();
                $newQid = $insQ->insert_id;
                $postedQuestionIds[] = $newQid;

                // Insert MCQ options
                if ($qtype === 'multiple_choice' && !empty($q['options'])) {
                    $optionsArr = $q['options'] ?? [];
                    
                    if (isset($optionsArr[0]) && is_string($optionsArr[0])) {
                        // Simple format
                        foreach ($optionsArr as $optIndex => $optText) {
                            if (trim($optText) === '') continue;
                            $is_correct = ($qcorrect !== null && intval($qcorrect) == ($optIndex + 1)) ? 1 : 0;
                            $in = $conn->prepare("INSERT INTO interactive_options (question_id, option_text, is_correct) VALUES (?, ?, ?)");
                            $in->bind_param("isi", $newQid, $optText, $is_correct);
                            $in->execute();
                        }
                    } else {
                        // Complex format
                        foreach ($optionsArr as $optIndex => $opt) {
                            $opt_text = $opt['text'] ?? '';
                            $is_correct = ($qcorrect !== null && intval($qcorrect) == ($optIndex + 1)) ? 1 : 0;
                            $in = $conn->prepare("INSERT INTO interactive_options (question_id, option_text, is_correct) VALUES (?, ?, ?)");
                            $in->bind_param("isi", $newQid, $opt_text, $is_correct);
                            $in->execute();
                        }
                    }
                }
            }
        }

        // Delete questions not in posted list
        $toRemove = array_diff($existingQIds, $postedQuestionIds);
        if (!empty($toRemove)) {
            foreach ($toRemove as $removeId) {
                // Delete associated file
                if (isset($existingFiles[$removeId]) && !empty($existingFiles[$removeId]) && file_exists(__DIR__ . '/' . $existingFiles[$removeId])) {
                    unlink(__DIR__ . '/' . $existingFiles[$removeId]);
                }
            }
            $in = implode(',', array_map('intval', $toRemove));
            $conn->query("DELETE FROM interactive_options WHERE question_id IN ($in)");
            $conn->query("DELETE FROM interactive_questions WHERE id IN ($in)");
        }

        $conn->commit();
        $_SESSION['success'] = "Interactive assignment updated successfully.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } catch (Exception $ex) {
        $conn->rollback();
        $_SESSION['error'] = "Update failed: " . $ex->getMessage();
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

      <div id="questionsContainer"></div>

      <div style="display:flex;gap:12px;margin-top:8px">
        <button type="button" class="btn btn-add" onclick="addQuestion()">+ Add Question</button>
        <button type="submit" class="btn btn-green">Save Assignment</button>
      </div>
    </form>
  </div>
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
/* ---------- Utility ---------- */
function escapeHtml(str) {
  if (!str) return '';
  return String(str).replace(/[&<>"'\/]/g, s =>
    ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','/':'&#47;'}[s])
  );
}

/* ---------- CREATE ASSIGNMENT ---------- */
let createQuestionIndex = 0;

function createQuestionMarkup(idx, prefill = null) {
  const type = (prefill && prefill.type) ? prefill.type : 'text';
  const textVal = (prefill && prefill.question_text) ? prefill.question_text : '';
  const pointsVal = (prefill && prefill.points) ? prefill.points : 1;
  const mediaNote = (prefill && prefill.media_url) ? `<div class="small">Existing media: <a target="_blank" href="${prefill.media_url}">view</a></div>` : '';

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

      <div style="margin-top:10px">
        <button type="button" class="btn btn-delete" onclick="removeCreateQuestion(${idx})">Remove Question</button>
      </div>
    </div>
  `;
}

function addQuestion(prefill = null){
  const container = document.getElementById('questionsContainer');
  const idx = createQuestionIndex++;
  container.insertAdjacentHTML('beforeend', createQuestionMarkup(idx, prefill));
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

/* ---------- EDIT MODAL ---------- */
let editQuestionIndex = 0;

function openEditModal(id) {
  const modal = document.getElementById('editModal');
  const errBox = document.getElementById('edit_error');
  if (errBox) { errBox.textContent = 'Loading...'; errBox.style.display = 'block'; }
  if (modal) modal.style.display = 'flex';

  fetch(`?action=get_assignment&id=${id}`)
    .then(async r => {
      const text = await r.text();
      try {
        return JSON.parse(text);
      } catch (e) {
        throw new Error(text || 'Failed to load assignment data');
      }
    })
    .then(data => {
      if (!data || data.success === false) {
        const msg = (data && (data.message || data.error)) || 'Failed to load assignment';
        const err = document.getElementById('edit_error');
        if (err) { err.textContent = msg; err.style.display = 'block'; }
        return;
      }
      const err = document.getElementById('edit_error');
      if (err) { err.textContent = ''; err.style.display = 'none'; }
      const a = data.assignment;
      document.getElementById('edit_id').value = a.id;
      document.getElementById('edit_title').value = a.title;
      document.getElementById('edit_description').value = a.description;
      document.getElementById('edit_due_date').value = a.due_date ? a.due_date.replace(' ', 'T') : '';
      document.getElementById('edit_unit_id').value = a.unit_id;

      const c = document.getElementById('editQuestionsContainer');
      c.innerHTML = '';
      editQuestionIndex = 0;
      (data.questions || []).forEach((q, idx) => {
        const html = editQuestionMarkup(idx, q);
        c.insertAdjacentHTML('beforeend', html);
      });
    })
    .catch(err => {
      console.error('Edit load failed:', err);
      const eDiv = document.getElementById('edit_error');
      let errorMsg = err.message;
      // Check for session expiration
      if (errorMsg.includes('login') || errorMsg.includes('Sign In') || errorMsg.includes('<!DOCTYPE html>')) {
        errorMsg = 'Your session has expired. Please refresh the page and login again.';
        setTimeout(() => { window.location.reload(); }, 3000);
      }
      if (eDiv) { eDiv.textContent = errorMsg; eDiv.style.display = 'block'; }
    });
}

function closeEditModal(){
  document.getElementById('editModal').style.display = 'none';
}

function editQuestionMarkup(idx,q){
  const type = q.type || 'text';
  const mediaNote = q.media_url ? `<div class="small">Existing media: <a target="_blank" href="${q.media_url}">view</a></div>` : '';
  
  // Build options HTML
  let optionsHtml = '';
  if (type === 'multiple_choice' && q.options && q.options.length) {
    q.options.forEach((opt, oi) => {
      optionsHtml += `
        <div class="option-input">
          <input type="radio" name="questions[${idx}][correct]" value="${oi+1}" ${opt.is_correct==1?'checked':''}>
          <input class="input" type="text" name="questions[${idx}][options][]" value="${escapeHtml(opt.option_text)}" placeholder="Option ${oi+1}">
        </div>`;
    });
  } else if (type === 'multiple_choice') {
    optionsHtml = `
      <div class="option-input">
        <input type="radio" name="questions[${idx}][correct]" value="1">
        <input class="input" type="text" name="questions[${idx}][options][]" placeholder="Option 1">
      </div>
      <div class="option-input">
        <input type="radio" name="questions[${idx}][correct]" value="2">
        <input class="input" type="text" name="questions[${idx}][options][]" placeholder="Option 2">
      </div>`;
  }

  return `
    <div class="question-card" id="edit_q_${idx}">
      <div class="question-number">${idx+1}</div>
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
        <label class="input-group label">Question Text</label>
        <input class="input" type="text" name="questions[${idx}][text]" value="${escapeHtml(q.question_text)}" required>
      </div>
      <div style="margin-top:8px">
        <label class="input-group label">Points</label>
        <input class="input" type="number" name="questions[${idx}][points]" min="1" value="${q.points}" required>
      </div>

      <div id="edit_options_${idx}" style="margin-top:8px; display:${type==='multiple_choice'?'block':'none'}">
        <label class="input-group label">Options</label>
        ${optionsHtml}
        <button type="button" class="btn btn-add" onclick="editAddOption(${idx})">Add option</button>
      </div>

      <div id="edit_audio_${idx}" style="margin-top:8px; display:${type==='speech'?'block':'none'}">
        <label class="input-group label">Replace / Upload question audio</label>
        <input type="file" name="questions[${idx}][audio]" accept="audio/*">
        ${mediaNote}
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

function editToggleOptions(idx){
  const sel = document.querySelector(`#edit_q_${idx} select[name="questions[${idx}][type]"]`);
  if(!sel) return;
  document.getElementById('edit_options_'+idx).style.display = (sel.value==='multiple_choice')?'block':'none';
  document.getElementById('edit_audio_'+idx).style.display = (sel.value==='speech')?'block':'none';
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
  const html = `
    <div class="question-card" id="edit_q_${idx}">
      <div class="question-number">${idx}</div>
      <div>
        <label class="input-group label">Question Type</label>
        <select class="input" name="questions[${idx}][type]" onchange="editToggleOptions(${idx})">
          <option value="text">Text Answer</option>
          <option value="multiple_choice">Multiple Choice</option>
          <option value="speech">Speech / Audio</option>
        </select>
      </div>
      <div style="margin-top:8px">
        <label class="input-group label">Question Text</label>
        <input class="input" type="text" name="questions[${idx}][text]" required>
      </div>
      <div style="margin-top:8px">
        <label class="input-group label">Points</label>
        <input class="input" type="number" name="questions[${idx}][points]" value="1" min="1" required>
      </div>
      <div id="edit_options_${idx}" style="margin-top:8px; display:none">
        <label class="input-group label">Options</label>
        <div class="option-input">
          <input type="radio" name="questions[${idx}][correct]" value="1">
          <input class="input" type="text" name="questions[${idx}][options][]" placeholder="Option 1">
        </div>
        <div class="option-input">
          <input type="radio" name="questions[${idx}][correct]" value="2">
          <input class="input" type="text" name="questions[${idx}][options][]" placeholder="Option 2">
        </div>
        <button type="button" class="btn btn-add" onclick="editAddOption(${idx})">Add option</button>
      </div>
      <div id="edit_audio_${idx}" style="margin-top:8px;display:none">
        <label class="input-group label">Upload question audio</label>
        <input type="file" name="questions[${idx}][audio]" accept="audio/*">
      </div>
      <div style="margin-top:10px"><button type="button" class="btn btn-delete" onclick="removeEditQuestion(${idx})">Remove Question</button></div>
    </div>
  `;
  container.insertAdjacentHTML('beforeend', html);
}

function viewQuestions(id, btn){
  const row = btn.closest('tr');
  const qRow = row.nextElementSibling;
  const container = qRow.querySelector('.questions-list');
  const isVisible = qRow.style.display === 'table-row';
  
  if (isVisible) {
    qRow.style.display = 'none';
    return;
  }
  
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
        return `<div class="question-card">
          <div class="question-number">${i+1}</div>
          <div><b>Type:</b> ${q.type} &nbsp; <b>Points:</b> ${q.points}</div>
          <div style="margin-top:6px"><b>Q:</b> ${escapeHtml(q.question_text)}</div>
          ${media}
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

// ---------- INIT ----------
addQuestion();

// Close modal when clicking outside
window.onclick = function(event) {
  const modal = document.getElementById('editModal');
  if (event.target == modal) {
    closeEditModal();
  }
}
</script>
</body>
</html>