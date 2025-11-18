<?php
session_start();
require_once '../config/db.php';

// --- Authentication ---
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    header("Location: ../login.php");
    exit;
}
$lecturer_id = $_SESSION['user_id'];
$lecturer_name = $_SESSION['user_name'];

// --- AJAX endpoints: return JSON for assignments or submissions ---
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['ajax'];

    if ($action === 'get_assignments' && isset($_GET['unit_id'])) {
        $unit_id = intval($_GET['unit_id']);
        $stmt = $conn->prepare("SELECT id, title, created_at, deadline, (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id) AS submissions_count FROM assignments a WHERE a.unit_id = ? ORDER BY a.created_at DESC");
        $stmt->bind_param('i', $unit_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $assignments = [];
        while ($r = $res->fetch_assoc()) $assignments[] = $r;
        echo json_encode(['status' => 'ok', 'items' => $assignments]);
        exit;
    }

    if ($action === 'get_submissions' && isset($_GET['assignment_id'])) {
        $assignment_id = intval($_GET['assignment_id']);
        // security: ensure lecturer actually teaches this assignment's unit
        $check = $conn->prepare("SELECT u.id FROM assignments a JOIN units u ON a.unit_id = u.id JOIN lecturer_units lu ON u.id = lu.unit_id WHERE a.id = ? AND lu.lecturer_id = ?");
        $check->bind_param('ii', $assignment_id, $lecturer_id);
        $check->execute();
        $ok = $check->get_result()->fetch_assoc();
        if (!$ok) {
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized or assignment not found']);
            exit;
        }

        $stmt = $conn->prepare("SELECT s.id AS submission_id, st.name AS student_name, st.reg_no, s.file_path, s.marks, s.is_graded, s.comment FROM submissions s JOIN students st ON s.student_id = st.id WHERE s.assignment_id = ? ORDER BY st.name ASC");
        $stmt->bind_param('i', $assignment_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $subs = [];
        while ($r = $res->fetch_assoc()) $subs[] = $r;
        echo json_encode(['status' => 'ok', 'items' => $subs]);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    exit;
}

// --- Fetch units taught by lecturer and summary stats for tiles ---
$unitsQuery = $conn->prepare(
    "SELECT u.id AS unit_id, u.name AS unit_name, u.code AS unit_code, c.id AS course_id, c.name AS course_name, u.level AS year,
    (SELECT COUNT(*) FROM assignments a WHERE a.unit_id = u.id) AS assignments_count,
    (SELECT COUNT(*) FROM submissions s JOIN assignments aa ON s.assignment_id = aa.id WHERE aa.unit_id = u.id) AS submissions_count,
    (SELECT MAX(created_at) FROM assignments a WHERE a.unit_id = u.id) AS last_sent,
    (SELECT MIN(deadline) FROM assignments a WHERE a.unit_id = u.id AND a.deadline >= NOW()) AS nearest_deadline
    FROM units u
    JOIN courses c ON u.course_id = c.id
    JOIN lecturer_units lu ON u.id = lu.unit_id
    WHERE lu.lecturer_id = ?
    ORDER BY c.name, u.name"
);
$unitsQuery->bind_param('i', $lecturer_id);
$unitsQuery->execute();
$units = $unitsQuery->get_result();
$unitsQuery->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Unit Dashboard — Assignment Submissions</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root{--bg:#f4f7fb;--card:#ffffff;--muted:#6b7280;--accent:#2563eb;--success:#16a34a;--danger:#ef4444}
        body{font-family:Inter, system-ui, Arial, sans-serif;background:var(--bg);margin:0;padding:24px;color:#0f172a}
        .container{max-width:1200px;margin:0 auto}
        header{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px}
        h1{font-size:20px;margin:0}
        .top-actions{display:flex;gap:10px;align-items:center}
        .search{background:#fff;padding:6px 10px;border-radius:8px;box-shadow:0 1px 2px rgba(15,23,42,0.05);display:flex;align-items:center}
        .search input{border:0;outline:none;width:220px}

        /* Tiles grid */
        .tiles{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:18px;margin-top:12px}
        .tile{background:var(--card);border-radius:12px;padding:14px;box-shadow:0 6px 18px rgba(15,23,42,0.06);transition:transform .18s ease,box-shadow .18s ease;cursor:pointer;position:relative}
        .tile:hover{transform:translateY(-6px);box-shadow:0 12px 32px rgba(15,23,42,0.08)}
        .tile .title{font-weight:600;margin-bottom:6px}
        .tile .meta{font-size:13px;color:var(--muted);margin-bottom:10px}
        .badges{display:flex;gap:8px;flex-wrap:wrap}
        .badge{background:#f1f5f9;padding:6px 8px;border-radius:8px;font-size:13px;color:#0f172a}
        .progress{height:8px;background:#eef2ff;border-radius:8px;overflow:hidden;margin-top:10px}
        .progress > i{display:block;height:100%;background:linear-gradient(90deg,var(--accent),#7c3aed);width:60%}
        .tile .footer{display:flex;justify-content:space-between;align-items:center;margin-top:12px}
        .small-muted{font-size:12px;color:var(--muted)}

        /* Modal */
        .modal-overlay{position:fixed;inset:0;background:rgba(2,6,23,0.4);display:flex;align-items:center;justify-content:center;z-index:60;opacity:0;pointer-events:none;transition:opacity .18s ease}
        .modal{width:860px;max-width:95%;background:var(--card);border-radius:12px;padding:16px;box-shadow:0 18px 48px rgba(2,6,23,0.3)}
        .modal.show{opacity:1;pointer-events:auto}
        .assign-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px}
        .assign-card{padding:12px;border-radius:10px;background:#f8fafc;border:1px solid #eef2ff}
        .assign-title{font-weight:600;margin-bottom:6px}
        .assign-meta{font-size:13px;color:var(--muted)}
        .assign-card .small{font-size:12px}
        .close-x{position:absolute;right:14px;top:12px;font-size:18px;color:var(--muted);cursor:pointer}

        /* Submissions area */
        .submissions-wrap{margin-top:22px;background:var(--card);padding:16px;border-radius:12px;box-shadow:0 8px 22px rgba(15,23,42,0.04);display:none}
        .sub-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}
        .table{width:100%;border-collapse:collapse}
        .table th{background:#f8fafc;padding:10px;text-align:left;font-size:13px;color:var(--muted);position:sticky;top:0}
        .table td{padding:10px;border-top:1px solid #f1f5f9}
        .row-odd{background:linear-gradient(180deg,#ffffff,#fbfdff)}
        .status-graded{color:var(--success);font-weight:600}
        .status-pending{color:var(--danger);font-weight:600}
        .btn{background:var(--accent);color:#fff;padding:8px 12px;border-radius:8px;border:0;cursor:pointer}
        .btn.secondary{background:#e6eefc;color:var(--accent)}

        /* Responsive tweaks */
        @media (max-width:720px){
            .top-actions .search input{width:120px}
            .tiles{grid-template-columns:repeat(auto-fill,minmax(180px,1fr))}
            .modal{width:95%}
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>📄 Assignment Submissions — Unit Dashboard</h1>
            <div class="top-actions">
                <div class="search">
                    <i class="fa fa-search" style="margin-right:8px;color:var(--muted)"></i>
                    <input id="unitSearch" placeholder="Search units" aria-label="Search units">
                </div>
                <button class="btn secondary" id="refreshBtn" title="Refresh">🔄 Refresh</button>
                <a href="dashboard.php" class="btn">🏠 Home</a>
            </div>
        </header>

        <!-- Tiles grid -->
        <section class="tiles" id="tilesGrid">
            <?php while ($unit = $units->fetch_assoc()):
                $uid = (int)$unit['unit_id'];
                $assignCount = (int)$unit['assignments_count'];
                $subCount = (int)$unit['submissions_count'];
                $lastSent = $unit['last_sent'] ? date('d M Y', strtotime($unit['last_sent'])) : '—';
                $deadline = $unit['nearest_deadline'] ? date('d M Y', strtotime($unit['nearest_deadline'])) : '—';
            ?>
                <div class="tile" data-unit-id="<?= $uid ?>" tabindex="0">
                    <div class="title"><?= htmlspecialchars($unit['unit_name']) ?></div>
                    <div class="meta"><?= htmlspecialchars($unit['course_name']) ?> • Year <?= htmlspecialchars($unit['year']) ?> • <?= htmlspecialchars($unit['unit_code'] ?? '') ?></div>
                    <div class="badges">
                        <div class="badge">📘 Assignments: <?= $assignCount ?></div>
                        <div class="badge">📥 Submissions: <?= $subCount ?></div>
                        <div class="badge">📅 Last sent: <?= $lastSent ?></div>
                        <div class="badge">⏳ Nearest deadline: <?= $deadline ?></div>
                    </div>
                    <div class="progress" aria-hidden="true"><i style="width:<?= $assignCount ? min(100, (int)($subCount / max(1,$assignCount) * 100)) : 0 ?>%"></i></div>
                    <div class="footer">
                        <div class="small-muted">Unit ID: <?= $uid ?></div>
                        <div class="small-muted">Click or press Enter to open</div>
                    </div>
                </div>
            <?php endwhile; ?>
        </section>

        <!-- Modal overlay for assignments preview -->
        <div id="modalOverlay" class="modal-overlay" role="dialog" aria-modal="true" style="display:none">
            <div class="modal" role="document">
                <div class="close-x" id="closeModal">✖</div>
                <h3 id="modalUnitTitle">Unit assignments</h3>
                <div id="assignGrid" class="assign-grid" style="margin-top:12px"></div>
            </div>
        </div>

        <!-- Submissions area (hidden until assignment clicked) -->
        <section id="submissionsArea" class="submissions-wrap" aria-live="polite">
            <div class="sub-header">
                <div>
                    <strong id="subTitle">Submissions</strong>
                    <div class="small-muted" id="subMeta">Select an assignment to view submissions</div>
                </div>
                <div style="display:flex;gap:8px;align-items:center">
                    <button id="downloadPdf" class="btn secondary">📥 Generate PDF</button>
                    <button id="saveMarks" class="btn">💾 Save Marks</button>
                </div>
            </div>

            <div id="tableWrap"></div>
        </section>
    </div>

    <script>
        // Minimal helper functions
        const $ = sel => document.querySelector(sel);
        const $$ = sel => Array.from(document.querySelectorAll(sel));

        // Elements
        const tilesGrid = $('#tilesGrid');
        const modalOverlay = $('#modalOverlay');
        const assignGrid = $('#assignGrid');
        const modalUnitTitle = $('#modalUnitTitle');
        const closeModal = $('#closeModal');
        const submissionsArea = $('#submissionsArea');
        const tableWrap = $('#tableWrap');
        const unitSearch = $('#unitSearch');
        const refreshBtn = $('#refreshBtn');

        // Event: Click / keyboard on tile opens modal with assignments
        tilesGrid.addEventListener('click', async (e) => {
            const tile = e.target.closest('.tile');
            if (!tile) return;
            const unitId = tile.getAttribute('data-unit-id');
            openAssignmentsModal(unitId, tile);
        });

        // Keyboard accessibility
        tilesGrid.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                const tile = e.target.closest('.tile');
                if (!tile) return;
                const unitId = tile.getAttribute('data-unit-id');
                openAssignmentsModal(unitId, tile);
            }
        });

        async function openAssignmentsModal(unitId, tileEl) {
            modalUnitTitle.textContent = tileEl.querySelector('.title').textContent + ' — Assignments';
            assignGrid.innerHTML = '<div style="grid-column:1/-1;padding:12px;color:var(--muted)">Loading…</div>';
            modalOverlay.style.display = 'flex';
            setTimeout(()=>modalOverlay.classList.add('show'),20);

            try {
                const res = await fetch('?ajax=get_assignments&unit_id=' + encodeURIComponent(unitId));
                const data = await res.json();
                if (data.status === 'ok') renderAssignments(data.items);
                else assignGrid.innerHTML = '<div style="grid-column:1/-1;color:var(--danger)">Error loading assignments</div>';
            } catch (err) {
                assignGrid.innerHTML = '<div style="grid-column:1/-1;color:var(--danger)">Network error</div>';
            }
        }

        function renderAssignments(items) {
            if (!items.length) {
                assignGrid.innerHTML = '<div style="grid-column:1/-1;padding:12px;color:var(--muted)">No assignments for this unit</div>';
                return;
            }
            assignGrid.innerHTML = '';
            items.forEach(a => {
                const card = document.createElement('div');
                card.className = 'assign-card';
                card.tabIndex = 0;
                card.innerHTML = `
                    <div class="assign-title">${escapeHtml(a.title)}</div>
                    <div class="assign-meta">Submissions: ${a.submissions_count} • ${formatDate(a.deadline)} • ${formatDate(a.created_at)}</div>
                    <div style="margin-top:8px" class="small">Click to view submissions</div>
                `;
                card.addEventListener('click', () => loadSubmissions(a.id, a.title));
                card.addEventListener('keydown', (e)=>{ if(e.key==='Enter') loadSubmissions(a.id,a.title); });
                assignGrid.appendChild(card);
            });
        }

        function closeAssignments() {
            modalOverlay.classList.remove('show');
            setTimeout(()=>modalOverlay.style.display='none',200);
            assignGrid.innerHTML = '';
        }
        closeModal.addEventListener('click', closeAssignments);
        modalOverlay.addEventListener('click',(e)=>{ if(e.target===modalOverlay) closeAssignments(); });

        // Load submissions for an assignment
        async function loadSubmissions(assignmentId, title) {
            // highlight the table area and populate
            submissionsArea.style.display = 'block';
            document.getElementById('subTitle').textContent = title + ' — Submissions';
            document.getElementById('subMeta').textContent = 'Loading submissions…';
            tableWrap.innerHTML = '<div style="padding:18px;color:var(--muted)">Loading submissions…</div>';
            closeAssignments();

            try {
                const res = await fetch('?ajax=get_submissions&assignment_id=' + encodeURIComponent(assignmentId));
                const data = await res.json();
                if (data.status === 'ok') renderSubmissionsTable(data.items, assignmentId);
                else tableWrap.innerHTML = '<div style="color:var(--danger)">Error loading submissions</div>';
            } catch (err) {
                tableWrap.innerHTML = '<div style="color:var(--danger)">Network error</div>';
            }
        }

        function renderSubmissionsTable(items, assignmentId) {
            document.getElementById('subMeta').textContent = `${items.length} submissions received`;
            if (!items.length) {
                tableWrap.innerHTML = '<div style="padding:12px;color:var(--muted)">No submissions yet.</div>';
                return;
            }
            // build a simple HTML table with inputs
            const rows = items.map((s, idx) => `
                <tr class="${idx%2? 'row-odd':''}">
                    <td>${escapeHtml(s.student_name)}</td>
                    <td>${escapeHtml(s.reg_no)}</td>
                    <td><a href="../assets/uploads/submissions/${encodeURIComponent(s.file_path)}" target="_blank">View</a></td>
                    <td><input type="number" data-id="${s.submission_id}" value="${s.marks ?? ''}" min="0" max="100" style="width:80px;padding:6px;border-radius:8px;border:1px solid #e6eefc"></td>
                    <td>${s.is_graded? '<span class="status-graded">Graded</span>':'<span class="status-pending">Pending</span>'}</td>
                    <td><textarea data-id="${s.submission_id}" style="width:100%;height:52px;border-radius:8px;border:1px solid #eef2ff">${escapeHtml(s.comment ?? '')}</textarea></td>
                </tr>
            `).join('');

            tableWrap.innerHTML = `
                <form id="marksForm">
                    <table class="table" role="table" aria-label="Submissions table">
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Reg No</th>
                                <th>Submission</th>
                                <th>Marks (out of 100)</th>
                                <th>Graded</th>
                                <th>Comment</th>
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
                    <input type="hidden" name="assignment_id" value="${assignmentId}">
                </form>
            `;

            // Save marks button action
            $('#saveMarks').onclick = async ()=>{
                const form = document.getElementById('marksForm');
                const marksInputs = Array.from(form.querySelectorAll('input[type="number"][data-id]'));
                const commentInputs = Array.from(form.querySelectorAll('textarea[data-id]'));
                const payload = {action:'save_marks', assignment_id: assignmentId, marks:{}, comments:{}, is_graded:{}};
                marksInputs.forEach(i=> payload.marks[i.dataset.id] = i.value);
                commentInputs.forEach(i=> payload.comments[i.dataset.id] = i.value);
                // collect graded checkboxes by reading the status cell text
                Array.from(form.querySelectorAll('tbody tr')).forEach(tr=>{
                    const id = tr.querySelector('input[type="number"]').dataset.id;
                    const statusCell = tr.cells[4].textContent.trim();
                    payload.is_graded[id] = statusCell.toLowerCase().includes('graded');
                });
                // send via fetch to actions.php
                try {
                    const res = await fetch('../actions.php', {
                        method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload)
                    });
                    const j = await res.json();
                    if (j.status === 'ok') alert('Marks saved'); else alert('Error saving marks');
                } catch (err) { alert('Network error saving marks'); }
            };
        }

        // Simple helpers
        function formatDate(d){ if(!d) return '—'; const x=new Date(d); if(isNaN(x)) return d; return x.toLocaleDateString(); }
        function escapeHtml(s){ if(s==null) return ''; return String(s).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }

        // Search filter
        unitSearch.addEventListener('input', (e)=>{
            const q = e.target.value.toLowerCase().trim();
            $$('.tile').forEach(t=>{
                const txt = (t.querySelector('.title').textContent + ' ' + t.querySelector('.meta').textContent).toLowerCase();
                t.style.display = txt.includes(q) ? '' : 'none';
            });
        });

        // Refresh (simple: reload page)
        refreshBtn.addEventListener('click', ()=> location.reload());

    </script>
</body>
</html>
