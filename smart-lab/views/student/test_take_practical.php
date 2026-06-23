<div class="main-content">
    <!-- JKUAT University Header -->
    <div class="university-header" style="display:flex;align-items:center;gap:16px;padding:16px 20px;background:linear-gradient(135deg,#f8fafc 0%,#eff6ff 100%);border-radius:12px;margin-bottom:16px;border:1px solid #e2e8f0;">
        <img src="<?= APP_URL ?>/jkuatlogo.jpg" alt="JKUAT Logo" style="width:70px;height:auto;border-radius:6px;" onerror="this.style.display='none'" />
        <div>
            <h2 style="margin:0;font-size:1.2rem;color:#1e3a5f;font-weight:700;">Jomo Kenyatta University of Agriculture and Technology</h2>
            <p style="margin:2px 0 0;font-size:.85rem;color:#64748b;">SmartLab — Practical Test Mode</p>
        </div>
    </div>

    <div class="page-header">
        <div class="page-overline">Test Mode — Practical Session</div>
        <h1 class="page-title"><?= htmlspecialchars($practical['title']) ?></h1>
        <div class="page-subtitle">
            <?= htmlspecialchars($practical['course_code']) ?> —
            <?= htmlspecialchars($practical['lab_name']) ?> (<?= htmlspecialchars($practical['lab_code']) ?>)
        </div>
        <div class="alert alert-warning" style="margin-top:1rem;padding:.9rem 1rem;border-radius:12px;background:#fef3c7;border:1px solid #f59e0b;color:#92400e;">
            <strong>🧪 Test Mode</strong> — No attendance verification required. Fill in your readings below and click <em>Save Datasheet</em> when done.
        </div>
    </div>

    <div class="practical-view">
        <div class="tabs">
            <div class="tab-nav">
                <button class="tab-btn active" onclick="showTab('objective')">Objective</button>
                <button class="tab-btn" onclick="showTab('theory')">Theory</button>
                <button class="tab-btn" onclick="showTab('apparatus')">Apparatus</button>
                <button class="tab-btn" onclick="showTab('procedure')">Procedure</button>
                <button class="tab-btn" onclick="showTab('report')">Lab Report</button>
                <button class="tab-btn" onclick="showTab('scratch')">📝 Scratch</button>
            </div>

            <div class="tab-content">
                <!-- Objective -->
                <div id="objective-tab" class="tab-pane active">
                    <div class="content-section">
                        <h3>Learning Objectives</h3>
                        <div class="content-text"><?= $practical['objective'] ? nl2br(htmlspecialchars($practical['objective'])) : 'No objectives specified.' ?></div>
                    </div>
                </div>

                <!-- Theory -->
                <div id="theory-tab" class="tab-pane">
                    <div class="content-section">
                        <h3>Theoretical Background</h3>
                        <div class="content-text"><?= $practical['theory'] ? nl2br(htmlspecialchars($practical['theory'])) : 'No theory provided.' ?></div>
                    </div>
                </div>

                <!-- Apparatus -->
                <div id="apparatus-tab" class="tab-pane">
                    <div class="content-section">
                        <h3>Apparatus and Materials</h3>
                        <div class="apparatus-list">
                            <?php if (!empty($practical['apparatus'])): ?>
                                <?php foreach ($practical['apparatus'] as $item): ?>
                                    <?php if (trim($item)): ?>
                                        <div class="apparatus-item"><span class="bullet">•</span> <?= htmlspecialchars(trim($item)) ?></div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php else: ?><p>No apparatus specified.</p><?php endif; ?>
                        </div>
                        <h4>Chemicals Required</h4>
                        <div class="chemicals-list">
                            <?php if (!empty($practical['chemicals'])): ?>
                                <?php foreach ($practical['chemicals'] as $chemical): ?>
                                    <?php if (trim($chemical)): ?>
                                        <div class="chemical-item"><span class="bullet">•</span> <?= htmlspecialchars(trim($chemical)) ?></div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php else: ?><p>No chemicals specified.</p><?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Procedure -->
                <div id="procedure-tab" class="tab-pane">
                    <div class="content-section">
                        <h3>Procedure</h3>
                        <?php if (!empty($practical['procedure'])): ?>
                            <?php foreach ($practical['procedure'] as $step): ?>
                                <div class="procedure-step">
                                    <h4>Step <?= $step['step_number'] ?></h4>
                                    <p><?= nl2br(htmlspecialchars($step['step_description'])) ?></p>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?><p>No procedure specified.</p><?php endif; ?>
                    </div>
                </div>

                <!-- Lab Report — Fillable form -->
                <div id="report-tab" class="tab-pane">
                    <div class="content-section">
                        <h3>Lab Report — Fill Your Readings</h3>
                        <p class="report-instruction">Enter your observations, calculations, results, and conclusion below. Click <strong>Save Datasheet</strong> to submit.</p>

                        <form id="lab-report-form">
                            <div class="report-section">
                                <h4>Observations / Readings</h4>
                                <div class="observations-table-container">
                                    <?php if (!empty($practical['observations_table'])): ?>
                                        <table class="observations-table" id="observations-table">
                                            <thead>
                                                <tr>
                                                    <?php foreach ($practical['observations_table'] as $column): ?>
                                                        <th data-column="<?= htmlspecialchars($column['name']) ?>"
                                                            data-type="<?= htmlspecialchars($column['type']) ?>"
                                                            data-formula="<?= htmlspecialchars($column['formula'] ?? '') ?>">
                                                            <?= htmlspecialchars($column['name']) ?>
                                                        </th>
                                                    <?php endforeach; ?>
                                                    <th style="width:40px;">&times;</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                        <button type="button" onclick="addObservationRow()" class="btn btn-outline btn-sm">+ Add Row</button>
                                    <?php else: ?>
                                        <textarea name="observations_raw" rows="4" class="form-control" placeholder="Enter your observations / readings here..."></textarea>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="report-section">
                                <h4>Calculations</h4>
                                <textarea id="calculations" name="calculations" rows="6" placeholder="Show your calculations here..." class="form-control"><?= htmlspecialchars($report_data['calculations'] ?? '') ?></textarea>
                            </div>

                            <div class="report-section">
                                <h4>Result</h4>
                                <textarea id="result" name="result" rows="4" placeholder="State your results..." class="form-control"><?= htmlspecialchars($report_data['result'] ?? '') ?></textarea>
                            </div>

                            <div class="report-section">
                                <h4>Conclusion</h4>
                                <textarea id="conclusion" name="conclusion" rows="4" placeholder="Write your conclusion..." class="form-control"><?= htmlspecialchars($report_data['conclusion'] ?? '') ?></textarea>
                            </div>

                            <div class="report-actions">
                                <button type="button" onclick="saveDraft()" class="btn btn-outline">💾 Save Draft</button>
                                <button type="button" onclick="testSubmitReport()" class="btn btn-primary">📄 Save Datasheet</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Scratch Pad -->
                <div id="scratch-tab" class="tab-pane">
                    <div class="content-section">
                        <h3>📝 Scratch Pad — Free Calculations & Notes</h3>
                        <p class="report-instruction">Use this blank area for free-hand calculations, diagrams, or notes. Content here is for your reference only and will NOT be submitted with the datasheet.</p>
                        <div style="min-height:600px;border:2px dashed #d1d5db;border-radius:12px;padding:20px;background:repeating-linear-gradient(transparent,transparent 28px,#f1f5f9 28px,#f1f5f9 29px);">
                            <div contenteditable="true" id="scratchPad" style="min-height:580px;outline:none;line-height:29px;font-family:monospace;font-size:16px;color:#1f2937;white-space:pre-wrap;"></div>
                        </div>
                        <p style="margin-top:8px;font-size:13px;color:#94a3b8;">💡 This area is for scratch work only — it is not saved to the server.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let observationRowCount = 0;

// Tab switching
function showTab(tabName) {
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById(tabName + '-tab').classList.add('active');
    const btn = event.target.closest ? event.target.closest('.tab-btn') : event.target;
    if (btn) btn.classList.add('active');
}

// Add observation row
function addObservationRow() {
    const tbody = document.querySelector('#observations-table tbody');
    const cols = document.querySelectorAll('#observations-table thead th');
    if (!tbody) return;

    const row = document.createElement('tr');
    row.dataset.rowIndex = observationRowCount;

    cols.forEach((col) => {
        if (col.style.width === '40px') return;
        const cell = document.createElement('td');
        const colName = col.dataset.column;
        const colType = col.dataset.type;
        const inp = document.createElement('input');
        inp.type = colType === 'number' ? 'number' : 'text';
        if (colType === 'number') inp.step = 'any';
        inp.name = `observations[${observationRowCount}][${colName}]`;
        inp.className = 'form-control form-control-sm';
        if (colType === 'number') inp.addEventListener('input', () => calculateFormulas(observationRowCount));
        cell.appendChild(inp);
        row.appendChild(cell);
    });

    const delCell = document.createElement('td');
    const delBtn = document.createElement('button');
    delBtn.type = 'button'; delBtn.className = 'btn btn-danger btn-sm';
    delBtn.textContent = '×';
    delBtn.onclick = () => row.remove();
    delCell.appendChild(delBtn);
    row.appendChild(delCell);

    tbody.appendChild(row);
    observationRowCount++;
}

function calculateFormulas(rowIndex) {
    const cols = document.querySelectorAll('#observations-table thead th');
    const row = document.querySelector(`#observations-table tbody tr[data-row-index="${rowIndex}"]`);
    if (!row) return;
    cols.forEach((col, ci) => {
        if (col.dataset.type === 'calculation' && col.dataset.formula && row.cells[ci]) {
            const inp = row.cells[ci].querySelector('input');
            if (inp) {
                let f = col.dataset.formula;
                cols.forEach((c2, c2i) => {
                    const cn = c2.dataset.column;
                    if (row.cells[c2i]) {
                        const v = row.cells[c2i].querySelector('input')?.value || '0';
                        f = f.replace(new RegExp(`\\b${cn}\\b`, 'gi'), v);
                    }
                });
                try { inp.value = eval(f) || ''; } catch(e) {}
            }
        }
    });
}

function getFormData() {
    const data = { observations: {}, calculations: '', result: '', conclusion: '' };
    data.calculations = document.getElementById('calculations')?.value || '';
    data.result = document.getElementById('result')?.value || '';
    data.conclusion = document.getElementById('conclusion')?.value || '';
    document.querySelectorAll('#observations-table input').forEach(inp => {
        const m = inp.name.match(/observations\[(\d+)\]\[(.+)\]/);
        if (m) {
            if (!data.observations[m[1]]) data.observations[m[1]] = {};
            data.observations[m[1]][m[2]] = inp.value;
        }
    });
    return data;
}

function saveDraft() {
    const data = getFormData();
    localStorage.setItem('practical_<?= $practical['id'] ?>_draft', JSON.stringify(data));
    fetch('<?= APP_URL ?>/start-practical-test/save-draft/<?= $practical['id'] ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ report_id: '<?= $report_id ?>', form_data: data })
    })
    .then(r => r.json())
    .then(res => { alert(res.success ? 'Draft saved!' : 'Draft save failed: ' + (res.error || '')); })
    .catch(() => alert('Draft saved locally.'));
}

function testSubmitReport() {
    if (!confirm('Submit this report? You will not be able to edit after submission.')) return;
    const data = getFormData();
    const btn = document.querySelector('.report-actions .btn-primary');
    btn.disabled = true; btn.textContent = 'Saving…';

    fetch('<?= APP_URL ?>/start-practical-test/submit/<?= $practical['id'] ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ report_id: '<?= $report_id ?>', form_data: data })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            localStorage.removeItem('practical_<?= $practical['id'] ?>_draft');
            const actions = document.querySelector('.report-actions');
            actions.innerHTML = `
                <div style="background:#dcfce7;color:#166534;padding:16px;border-radius:8px;width:100%;">
                    <strong>✅ Datasheet saved successfully!</strong>
                    <p style="margin:8px 0 12px;font-size:14px;">Download your datasheet, fill in the blank pages, then upload the completed report below.</p>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;">
                        <a href="<?= APP_URL ?>/start-practical-test/download/<?= $report_id ?>" class="btn" style="background:#16a34a;color:white;padding:10px 20px;border-radius:6px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                            📄 Download Datasheet
                        </a>
                        <a href="<?= APP_URL ?>/student/view_practical/<?= $practical['id'] ?>" class="btn" style="background:#e2e8f0;color:#334155;padding:10px 20px;border-radius:6px;text-decoration:none;">
                            ← Back to Practical
                        </a>
                    </div>
                    <div style="border-top:1px solid #bbf7d0;padding-top:14px;">
                        <p style="font-weight:600;margin-bottom:8px;">📤 Upload Completed Report</p>
                        <p style="font-size:13px;margin-bottom:10px;color:#15803d;">After filling the blank pages, upload a scan or photo of your completed datasheet (PDF, JPG, or PNG — max 10 MB).</p>
                        <div id="uploadArea" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                            <input type="file" id="reportFileInput" accept=".pdf,.jpg,.jpeg,.png"
                                style="border:1px solid #86efac;border-radius:6px;padding:6px;background:white;color:#166534;font-size:13px;max-width:280px;" />
                            <button type="button" onclick="uploadReport()" id="uploadBtn"
                                style="background:#15803d;color:white;border:none;padding:10px 18px;border-radius:6px;cursor:pointer;font-weight:600;font-size:13px;">
                                Upload
                            </button>
                        </div>
                        <div id="uploadStatus" style="margin-top:8px;font-size:13px;"></div>
                    </div>
                </div>
            `;
        } else {
            alert('Error: ' + (res.error || 'Unknown error'));
            btn.disabled = false; btn.textContent = '📄 Save Datasheet';
        }
    })
    .catch(err => {
        alert('Error: ' + err.message);
        btn.disabled = false; btn.textContent = '📄 Save Datasheet';
    });
}

function uploadReport() {
    const fileInput = document.getElementById('reportFileInput');
    const statusEl  = document.getElementById('uploadStatus');
    const btn       = document.getElementById('uploadBtn');

    if (!fileInput || !fileInput.files[0]) {
        statusEl.textContent = 'Please select a file first.';
        statusEl.style.color = '#b91c1c';
        return;
    }

    const formData = new FormData();
    formData.append('report_file', fileInput.files[0]);

    btn.disabled = true;
    btn.textContent = 'Uploading…';
    statusEl.textContent = '';

    fetch('<?= APP_URL ?>/start-practical-test/upload/<?= $report_id ?>', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            statusEl.innerHTML = '<span style="color:#15803d;font-weight:600;">✅ Report uploaded successfully!</span>';
            document.getElementById('uploadArea').innerHTML = '<span style="color:#15803d;font-weight:600;">✅ Completed report submitted.</span>';
        } else {
            statusEl.textContent = 'Upload failed: ' + (res.error || 'Unknown error');
            statusEl.style.color = '#b91c1c';
            btn.disabled = false;
            btn.textContent = 'Upload';
        }
    })
    .catch(err => {
        statusEl.textContent = 'Upload error: ' + err.message;
        statusEl.style.color = '#b91c1c';
        btn.disabled = false;
        btn.textContent = 'Upload';
    });
}

// Load draft on page load
document.addEventListener('DOMContentLoaded', function() {
    const draft = localStorage.getItem('practical_<?= $practical['id'] ?>_draft');
    if (draft) {
        const d = JSON.parse(draft);
        if (d.calculations) document.getElementById('calculations').value = d.calculations;
        if (d.result) document.getElementById('result').value = d.result;
        if (d.conclusion) document.getElementById('conclusion').value = d.conclusion;
        if (d.observations) {
            Object.keys(d.observations).forEach(ri => {
                if (observationRowCount <= parseInt(ri)) addObservationRow();
                const rd = d.observations[ri];
                Object.keys(rd).forEach(cn => {
                    const inp = document.querySelector(`input[name="observations[${ri}][${cn}]"]`);
                    if (inp) inp.value = rd[cn];
                });
            });
        }
    } else {
        addObservationRow();
    }
});
</script>

<style>
.practical-view { max-width: 1200px; margin: 0 auto; }
.practical-actions { margin-top: 16px; display: flex; align-items: center; gap: 12px; }
.status-text { font-size: 14px; color: #6b7280; font-weight: 500; }
.tabs { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.tab-nav { display: flex; border-bottom: 1px solid #e1e5e9; overflow-x: auto; }
.tab-btn { padding: 12px 24px; border: none; background: none; cursor: pointer; font-size: 14px; font-weight: 500; color: #6b7280; border-bottom: 2px solid transparent; transition: all 0.2s; white-space: nowrap; }
.tab-btn.active { color: #2563eb; border-bottom-color: #2563eb; }
.tab-btn:hover { color: #2563eb; }
.tab-content { padding: 24px; }
.tab-pane { display: none; }
.tab-pane.active { display: block; }
.content-section h3 { color: #1f2937; margin-bottom: 16px; font-size: 18px; }
.content-text { line-height: 1.6; color: #4b5563; }
.apparatus-list, .chemicals-list { margin-bottom: 24px; }
.apparatus-item, .chemical-item { padding: 8px 0; border-bottom: 1px solid #f3f4f6; }
.bullet { color: #6b7280; margin-right: 8px; }
.procedure-step { margin-bottom: 20px; padding: 16px; background: #f9fafb; border-radius: 6px; border-left: 4px solid #2563eb; }
.procedure-step h4 { margin: 0 0 8px 0; color: #1f2937; }
.procedure-step p { margin: 0; color: #4b5563; line-height: 1.5; }
.report-section { margin-bottom: 32px; }
.report-section h4 { color: #1f2937; margin-bottom: 12px; font-size: 16px; }
.report-instruction { background: #eff6ff; padding: 12px 16px; border-radius: 6px; border-left: 4px solid #2563eb; margin-bottom: 24px; color: #1e40af; }
.observations-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
.observations-table th, .observations-table td { padding: 8px 12px; border: 1px solid #e1e5e9; text-align: left; }
.observations-table th { background: #f9fafb; font-weight: 600; color: #374151; }
.form-control { width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 14px; box-sizing:border-box; }
.form-control:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 2px rgba(37,99,235,0.1); }
.report-actions { display: flex; gap: 12px; margin-top: 24px; flex-wrap:wrap; }
.btn { padding: 8px 16px; border-radius: 6px; font-size: 14px; font-weight: 500; cursor: pointer; border: 1px solid transparent; transition: all 0.2s; }
.btn-primary { background: #2563eb; color: white; border-color: #2563eb; }
.btn-primary:hover { background: #1d4ed8; }
.btn-primary:disabled { opacity: .5; cursor:not-allowed; }
.btn-outline { background: white; color: #374151; border-color: #d1d5db; }
.btn-outline:hover { background: #f9fafb; }
.btn-danger { background: #dc2626; color: white; border-color: #dc2626; }
.btn-sm { padding: 4px 8px; font-size: 12px; }
.university-header img { object-fit: contain; }
</style>