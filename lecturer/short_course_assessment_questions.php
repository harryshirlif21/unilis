<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/ajax/short_course_access.php';

if (!shortCourseIsAuthor()) {
    header('Location: ../login.php');
    exit;
}

$assessment_id = (int)($_GET['assessment_id'] ?? 0);
if (!$assessment_id) {
    http_response_code(400);
    exit('An assessment_id is required.');
}

$stmt = $conn->prepare('
    SELECT pca.*, pc.title AS course_title
    FROM public_course_assessments pca
    JOIN public_courses pc ON pc.id = pca.course_id
    WHERE pca.id = ? LIMIT 1
');
$stmt->bind_param('i', $assessment_id);
$stmt->execute();
$assessment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$assessment) {
    http_response_code(404);
    exit('Assessment not found.');
}
if ($assessment['type'] !== 'cat') {
    http_response_code(400);
    exit('Only CAT assessments have a question bank.');
}

$canEdit = $assessment['lesson_id']
    ? shortCourseCanEditLesson($conn, (int)$assessment['lesson_id'])
    : shortCourseCanEditModule($conn, (int)$assessment['module_id']);
$canView = $assessment['lesson_id']
    ? shortCourseCanView($conn, (int)$assessment['course_id'])
    : shortCourseCanView($conn, (int)$assessment['course_id']);

if (!$canView) {
    http_response_code(403);
    exit('You do not have access to this assessment.');
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Questions — <?= htmlspecialchars($assessment['title']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root {
    --bg: #0d0f14; --surface: #161921; --surface2: #1e2230; --surface3: #262c3d;
    --border: #2a3148; --accent: #4f8ef7; --accent2: #38d9a9; --accent3: #f7934f;
    --danger: #f75f5f; --text: #e8eaf0; --text-muted: #7a82a0; --text-dim: #4a5270;
    --radius: 10px; --radius-sm: 6px; --tr: 0.18s ease;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
.topbar { background: var(--surface); border-bottom: 1px solid var(--border); padding: 0 32px; height: 60px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; }
.topbar-brand { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 1.1rem; color: var(--accent); }
.topbar-brand span { color: var(--text-muted); font-weight: 400; margin-left: 8px; font-size: 0.85rem; }
.btn-nav { background: var(--surface3); border: 1px solid var(--border); color: var(--text-muted); padding: 6px 14px; border-radius: var(--radius-sm); font-size: 0.8rem; text-decoration: none; }
.btn-nav:hover { background: var(--surface2); color: var(--text); }
.main { max-width: 800px; margin: 0 auto; padding: 28px 32px; display: flex; flex-direction: column; gap: 20px; }
.page-head h1 { font-family: 'Syne', sans-serif; font-size: 1.3rem; font-weight: 700; margin-bottom: 4px; }
.page-head p { color: var(--text-muted); font-size: 0.85rem; }
.card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 18px 20px; }
.question-item { padding: 14px 16px; background: var(--surface2); border: 1px solid var(--border); border-radius: var(--radius-sm); margin-bottom: 10px; }
.question-item .q-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; margin-bottom: 8px; }
.q-text { font-weight: 500; font-size: 0.92rem; flex: 1; }
.q-meta { font-size: 0.72rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.05em; }
.q-options { display: flex; flex-direction: column; gap: 4px; margin-top: 6px; font-size: 0.85rem; color: var(--text-muted); }
.q-options .correct { color: var(--accent2); font-weight: 600; }
.q-actions { display: flex; gap: 6px; }
.btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: var(--radius-sm); font-size: 0.82rem; font-weight: 500; cursor: pointer; border: none; text-decoration: none; }
.btn-primary { background: var(--accent); color: #fff; }
.btn-ghost { background: transparent; border: 1px solid var(--border); color: var(--text-muted); }
.btn-danger { background: rgba(247,95,95,0.12); color: var(--danger); border: 1px solid rgba(247,95,95,0.3); }
.btn-icon { padding: 5px 8px; }
.form-group { margin-bottom: 14px; }
.form-group label { display: block; font-size: 0.78rem; font-weight: 500; color: var(--text-muted); margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.06em; }
.form-input, .form-textarea, .styled-select { width: 100%; background: var(--surface2); border: 1px solid var(--border); color: var(--text); padding: 9px 12px; border-radius: var(--radius-sm); font-family: 'DM Sans', sans-serif; font-size: 0.88rem; outline: none; }
.form-textarea { resize: vertical; min-height: 60px; }
.option-row { display: flex; gap: 8px; align-items: center; margin-bottom: 8px; }
.option-row input[type="text"] { flex: 1; }
.empty-state { text-align: center; padding: 40px; color: var(--text-dim); }
#toast { position: fixed; bottom: 24px; right: 24px; z-index: 999; display: flex; flex-direction: column; gap: 8px; }
.toast-item { background: var(--surface2); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 10px 16px; font-size: 0.85rem; }
.toast-item.success { border-left: 3px solid var(--accent2); }
.toast-item.error { border-left: 3px solid var(--danger); }
</style>
</head>
<body>
<header class="topbar">
    <div class="topbar-brand">UNILIS <span>Question Bank</span></div>
    <a href="course_builder.php?course_id=<?= (int)$assessment['course_id'] ?>" class="btn-nav"><i class="fas fa-arrow-left"></i> Back to Course</a>
</header>
<main class="main">
    <div class="page-head">
        <h1><i class="fas fa-clipboard-check"></i> <?= htmlspecialchars($assessment['title']) ?></h1>
        <p><?= htmlspecialchars($assessment['course_title']) ?> — CAT question bank<?= $canEdit ? '' : ' (view only)' ?></p>
    </div>

    <?php if ($canEdit): ?>
    <div class="card">
        <div class="form-group">
            <label>Question</label>
            <textarea class="form-textarea" id="q-text-input" placeholder="Enter the question..."></textarea>
        </div>
        <div class="form-group">
            <label>Type</label>
            <select class="styled-select" id="q-type-input" onchange="toggleQuestionTypeFields()">
                <option value="single">Single choice</option>
                <option value="multiple">Multiple choice</option>
                <option value="true_false">True / False</option>
                <option value="short_text">Short text</option>
            </select>
        </div>
        <div class="form-group" id="q-options-group">
            <label>Options</label>
            <div id="q-options-container"></div>
            <button type="button" class="btn btn-ghost" onclick="addOptionRow()"><i class="fas fa-plus"></i> Add option</button>
        </div>
        <div class="form-group" id="q-tf-group" style="display:none">
            <label>Correct answer</label>
            <select class="styled-select" id="q-tf-input">
                <option value="true">True</option>
                <option value="false">False</option>
            </select>
        </div>
        <div class="form-group" id="q-short-group" style="display:none">
            <label>Model answer (for reference)</label>
            <input type="text" class="form-input" id="q-short-input" placeholder="Expected answer">
        </div>
        <div class="form-group">
            <label>Marks</label>
            <input type="number" class="form-input" id="q-marks-input" min="1" value="1" style="max-width:120px">
        </div>
        <button class="btn btn-primary" id="q-save-btn" onclick="saveQuestion()"><i class="fas fa-save"></i> <span id="q-save-btn-label">Add Question</span></button>
        <button class="btn btn-ghost" id="q-cancel-btn" onclick="cancelEditQuestion()" style="display:none">Cancel</button>
    </div>
    <?php endif; ?>

    <div class="card">
        <div id="questions-list"></div>
    </div>
</main>
<div id="toast"></div>

<script>
const ASSESSMENT_ID = <?= (int)$assessment_id ?>;
const CAN_EDIT = <?= $canEdit ? 'true' : 'false' ?>;
let questions = [];
let editingQuestionId = null;
let optionIndex = 0;

function escHtml(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

function toast(msg, type = 'info') {
    const c = document.getElementById('toast');
    const el = document.createElement('div');
    el.className = `toast-item ${type}`;
    el.textContent = msg;
    c.appendChild(el);
    setTimeout(() => el.remove(), 2800);
}

function addOptionRow(value = '') {
    const container = document.getElementById('q-options-container');
    const idx = optionIndex++;
    const row = document.createElement('div');
    row.className = 'option-row';
    row.dataset.idx = idx;
    const type = document.getElementById('q-type-input').value;
    row.innerHTML = `
        <input type="${type === 'single' ? 'radio' : 'checkbox'}" name="q-correct" value="${escHtml(value)}" class="q-correct-marker">
        <input type="text" class="form-input" value="${escHtml(value)}" oninput="syncOptionValue(this)">
        <button type="button" class="btn btn-ghost btn-icon" onclick="this.closest('.option-row').remove()"><i class="fas fa-times"></i></button>
    `;
    container.appendChild(row);
}
function syncOptionValue(input) {
    const row = input.closest('.option-row');
    row.querySelector('.q-correct-marker').value = input.value;
}
function toggleQuestionTypeFields() {
    const type = document.getElementById('q-type-input').value;
    document.getElementById('q-options-group').style.display = (type === 'single' || type === 'multiple') ? '' : 'none';
    document.getElementById('q-tf-group').style.display = type === 'true_false' ? '' : 'none';
    document.getElementById('q-short-group').style.display = type === 'short_text' ? '' : 'none';
    document.querySelectorAll('.q-correct-marker').forEach(el => {
        el.type = type === 'single' ? 'radio' : 'checkbox';
    });
}

function loadQuestions() {
    fetch(`ajax/short_course_get_questions.php?assessment_id=${ASSESSMENT_ID}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) { toast(data.message || 'Failed to load', 'error'); return; }
            questions = data.questions || [];
            renderQuestions();
        })
        .catch(() => toast('Network error', 'error'));
}

function renderQuestions() {
    const list = document.getElementById('questions-list');
    if (!questions.length) {
        list.innerHTML = '<div class="empty-state"><i class="fas fa-clipboard-list"></i><p>No questions yet.</p></div>';
        return;
    }
    list.innerHTML = questions.map((q, i) => {
        let optsHtml = '';
        if (q.type === 'single' || q.type === 'multiple') {
            const correct = q.type === 'single' ? [q.correct_answer] : (JSON.parse(q.correct_answer || '[]'));
            optsHtml = '<div class="q-options">' + (q.options || []).map(o =>
                `<div class="${correct.includes(o) ? 'correct' : ''}">${correct.includes(o) ? '✓' : '—'} ${escHtml(o)}</div>`
            ).join('') + '</div>';
        } else if (q.type === 'true_false') {
            optsHtml = `<div class="q-options"><div class="correct">Correct: ${q.correct_answer === 'true' ? 'True' : 'False'}</div></div>`;
        } else {
            optsHtml = `<div class="q-options"><div>Model answer: ${escHtml(q.correct_answer || '—')}</div></div>`;
        }
        return `
            <div class="question-item">
                <div class="q-head">
                    <div>
                        <div class="q-text">${i + 1}. ${escHtml(q.question)}</div>
                        <div class="q-meta">${q.type.replace('_', ' ')} · ${q.marks} mark${q.marks == 1 ? '' : 's'}</div>
                    </div>
                    ${CAN_EDIT ? `<div class="q-actions">
                        <button class="btn btn-ghost btn-icon" onclick="editQuestion(${q.id})"><i class="fas fa-pen"></i></button>
                        <button class="btn btn-danger btn-icon" onclick="deleteQuestion(${q.id}, '${escHtml(q.question).replace(/'/g, "\\'")}')"><i class="fas fa-trash"></i></button>
                    </div>` : ''}
                </div>
                ${optsHtml}
            </div>`;
    }).join('');
}

function resetForm() {
    document.getElementById('q-text-input').value = '';
    document.getElementById('q-type-input').value = 'single';
    document.getElementById('q-marks-input').value = 1;
    document.getElementById('q-options-container').innerHTML = '';
    document.getElementById('q-tf-input').value = 'true';
    document.getElementById('q-short-input').value = '';
    addOptionRow(); addOptionRow();
    toggleQuestionTypeFields();
    editingQuestionId = null;
    document.getElementById('q-save-btn-label').textContent = 'Add Question';
    document.getElementById('q-cancel-btn').style.display = 'none';
}

function editQuestion(id) {
    const q = questions.find(x => x.id === id);
    if (!q) return;
    editingQuestionId = id;
    document.getElementById('q-text-input').value = q.question;
    document.getElementById('q-type-input').value = q.type;
    document.getElementById('q-marks-input').value = q.marks;
    document.getElementById('q-options-container').innerHTML = '';
    if (q.type === 'single' || q.type === 'multiple') {
        const correct = q.type === 'single' ? [q.correct_answer] : (JSON.parse(q.correct_answer || '[]'));
        (q.options || []).forEach(o => addOptionRow(o));
        setTimeout(() => {
            document.querySelectorAll('.q-correct-marker').forEach(el => {
                el.checked = correct.includes(el.value);
            });
        }, 0);
    } else {
        addOptionRow(); addOptionRow();
    }
    document.getElementById('q-tf-input').value = q.type === 'true_false' ? q.correct_answer : 'true';
    document.getElementById('q-short-input').value = q.type === 'short_text' ? (q.correct_answer || '') : '';
    toggleQuestionTypeFields();
    document.getElementById('q-save-btn-label').textContent = 'Update Question';
    document.getElementById('q-cancel-btn').style.display = '';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
function cancelEditQuestion() { resetForm(); }

function saveQuestion() {
    const text = document.getElementById('q-text-input').value.trim();
    if (!text) { toast('Question text is required', 'error'); return; }
    const type = document.getElementById('q-type-input').value;
    const body = new FormData();
    if (editingQuestionId) body.append('question_id', editingQuestionId);
    body.append('assessment_id', ASSESSMENT_ID);
    body.append('question', text);
    body.append('type', type);
    body.append('marks', document.getElementById('q-marks-input').value || 1);

    if (type === 'single' || type === 'multiple') {
        const opts = [...document.querySelectorAll('#q-options-container .option-row input[type="text"]')].map(i => i.value.trim()).filter(v => v);
        opts.forEach(o => body.append('options[]', o));
        const correct = [...document.querySelectorAll('.q-correct-marker:checked')].map(el => el.value);
        if (type === 'single') {
            body.append('correct_answer', correct[0] || '');
        } else {
            correct.forEach(c => body.append('correct_answer[]', c));
        }
    } else if (type === 'true_false') {
        body.append('correct_answer', document.getElementById('q-tf-input').value);
    } else {
        body.append('correct_answer', document.getElementById('q-short-input').value.trim());
    }

    fetch('ajax/short_course_save_question.php', { method: 'POST', body })
        .then(r => r.json())
        .then(data => {
            if (data.success) { toast(data.message, 'success'); resetForm(); loadQuestions(); }
            else toast(data.message, 'error');
        })
        .catch(() => toast('Network error', 'error'));
}

function deleteQuestion(id, text) {
    if (!confirm(`Delete question "${text}"?`)) return;
    const body = new FormData();
    body.append('question_id', id);
    fetch('ajax/short_course_delete_question.php', { method: 'POST', body })
        .then(r => r.json())
        .then(d => { if (d.success) { toast('Deleted', 'success'); loadQuestions(); } else toast(d.message, 'error'); })
        .catch(() => toast('Delete failed', 'error'));
}

if (CAN_EDIT) resetForm();
loadQuestions();
</script>
</body>
</html>
