<div class="main-content">
    <div class="page-header">
        <div class="page-overline">Practical Session</div>
        <h1 class="page-title"><?= htmlspecialchars($practical['title']) ?></h1>
        <div class="page-subtitle">
            <?= htmlspecialchars($practical['course_code']) ?> -
            <?= htmlspecialchars($practical['lab_name']) ?> (<?= htmlspecialchars($practical['lab_code']) ?>)
        </div>

        <?php if (isset($_GET['attendance_marked']) && $_GET['attendance_marked'] == '1'): ?>
            <div class="alert alert-success" style="margin-top: 1rem; padding: 1rem; border-radius: 12px; background: #ecfdf5; color: #166534; border: 1px solid #d1fae5;">
                Attendance marked successfully. Proceed to complete your lab report.
            </div>
        <?php endif; ?>

        <!-- Take Practical Button -->
        <div class="practical-actions">
            <?php $currentReportStatus = $report_status ?? 'not_started'; ?>

            <?php if ($currentReportStatus === 'not_started'): ?>
                <button id="take-practical-btn" onclick="openTakePracticalModal('<?= $practical['id'] ?>')" class="btn btn-primary btn-lg">
                    <i class="icon-flask"></i> Take Practical
                </button>
            <?php elseif ($currentReportStatus === 'in_progress'): ?>
                <button id="continue-practical-btn" onclick="continuePractical()" class="btn btn-success btn-lg">
                    <i class="icon-play"></i> Continue Practical
                </button>
                <span class="status-text">In Progress</span>
            <?php elseif ($currentReportStatus === 'submitted'): ?>
                <button disabled class="btn btn-secondary btn-lg">
                    <i class="icon-check"></i> Report Submitted
                </button>
                <span class="status-text">Completed</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="practical-view">
        <!-- Practical Content Tabs -->
        <div class="tabs">
            <div class="tab-nav">
                <button class="tab-btn active" onclick="showTab('objective')">Objective</button>
                <button class="tab-btn" onclick="showTab('theory')">Theory</button>
                <button class="tab-btn" onclick="showTab('apparatus')">Apparatus</button>
                <button class="tab-btn" onclick="showTab('procedure')">Procedure</button>
                <button class="tab-btn" onclick="showTab('report')">Lab Report</button>
            </div>

            <div class="tab-content">
                <!-- Objective Tab -->
                <div id="objective-tab" class="tab-pane active">
                    <div class="content-section">
                        <h3>Learning Objectives</h3>
                        <div class="content-text">
                            <?= $practical['objective'] ? nl2br(htmlspecialchars($practical['objective'])) : 'No objectives specified.' ?>
                        </div>
                    </div>
                </div>

                <!-- Theory Tab -->
                <div id="theory-tab" class="tab-pane">
                    <div class="content-section">
                        <h3>Theoretical Background</h3>
                        <div class="content-text">
                            <?= $practical['theory'] ? nl2br(htmlspecialchars($practical['theory'])) : 'No theory provided.' ?>
                        </div>
                    </div>
                </div>

                <!-- Apparatus Tab -->
                <div id="apparatus-tab" class="tab-pane">
                    <div class="content-section">
                        <h3>Apparatus and Materials</h3>
                        <div class="apparatus-list">
                            <?php if (!empty($practical['apparatus'])): ?>
                                <?php foreach ($practical['apparatus'] as $item): ?>
                                    <?php if (trim($item)): ?>
                                        <div class="apparatus-item">
                                            <span class="bullet">•</span>
                                            <?= htmlspecialchars(trim($item)) ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>No apparatus specified.</p>
                            <?php endif; ?>
                        </div>

                        <h4>Chemicals Required</h4>
                        <div class="chemicals-list">
                            <?php if (!empty($practical['chemicals'])): ?>
                                <?php foreach ($practical['chemicals'] as $chemical): ?>
                                    <?php if (trim($chemical)): ?>
                                        <div class="chemical-item">
                                            <span class="bullet">•</span>
                                            <?= htmlspecialchars(trim($chemical)) ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>No chemicals specified.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Procedure Tab -->
                <div id="procedure-tab" class="tab-pane">
                    <div class="content-section">
                        <h3>Procedure</h3>
                        <div class="procedure-steps">
                            <?php if (!empty($practical['procedure'])): ?>
                                <?php foreach ($practical['procedure'] as $step): ?>
                                    <div class="procedure-step">
                                        <h4>Step <?= $step['step_number'] ?></h4>
                                        <p><?= nl2br(htmlspecialchars($step['step_description'])) ?></p>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>No procedure specified.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Lab Report Tab -->
                <div id="report-tab" class="tab-pane">
                    <div class="content-section">
                        <h3>Lab Report</h3>
                        <p class="report-instruction">Fill in your observations, calculations, results, and conclusion below.</p>

                        <form id="lab-report-form">
                            <!-- Observations Table -->
                            <div class="report-section">
                                <h4>Observations</h4>
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
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <!-- Rows will be added dynamically -->
                                            </tbody>
                                        </table>
                                        <button type="button" onclick="addObservationRow()" class="btn btn-outline btn-sm">Add Row</button>
                                    <?php else: ?>
                                        <p>No observations table defined for this practical.</p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Calculations -->
                            <div class="report-section">
                                <h4>Calculations</h4>
                                <textarea id="calculations" name="calculations" rows="6" 
                                    placeholder="Show your calculations here..." class="form-control"></textarea>
                            </div>

                            <!-- Result -->
                            <div class="report-section">
                                <h4>Result</h4>
                                <textarea id="result" name="result" rows="4" 
                                    placeholder="State your results..." class="form-control"></textarea>
                            </div>

                            <!-- Conclusion -->
                            <div class="report-section">
                                <h4>Conclusion</h4>
                                <textarea id="conclusion" name="conclusion" rows="4" 
                                    placeholder="Write your conclusion..." class="form-control"></textarea>
                            </div>

                            <div class="report-actions">
                                <button type="button" onclick="saveDraft()" class="btn btn-outline">Save Draft</button>
                                <button type="button" onclick="submitReport()" class="btn btn-primary">Submit Report</button>
                            </div>
                        </form>
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
    // Hide all tabs
    document.querySelectorAll('.tab-pane').forEach(pane => {
        pane.classList.remove('active');
    });
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById(tabName + '-tab').classList.add('active');
    event.target.classList.add('active');
}

// Add observation row
function addObservationRow() {
    const tbody = document.querySelector('#observations-table tbody');
    const columns = document.querySelectorAll('#observations-table thead th');
    
    const row = document.createElement('tr');
    row.dataset.rowIndex = observationRowCount;
    
    columns.forEach((col, colIndex) => {
        const cell = document.createElement('td');
        const columnType = col.dataset.type;
        const columnName = col.dataset.column;
        
        if (columnType === 'number') {
            const input = document.createElement('input');
            input.type = 'number';
            input.step = 'any';
            input.name = `observations[${observationRowCount}][${columnName}]`;
            input.className = 'form-control form-control-sm';
            input.addEventListener('input', () => calculateFormulas(observationRowCount));
            cell.appendChild(input);
        } else {
            const input = document.createElement('input');
            input.type = 'text';
            input.name = `observations[${observationRowCount}][${columnName}]`;
            input.className = 'form-control form-control-sm';
            cell.appendChild(input);
        }
        
        row.appendChild(cell);
    });
    
    tbody.appendChild(row);
    observationRowCount++;
    
    // Add a delete button for the row
    const deleteCell = document.createElement('td');
    const deleteBtn = document.createElement('button');
    deleteBtn.type = 'button';
    deleteBtn.className = 'btn btn-danger btn-sm';
    deleteBtn.textContent = '×';
    deleteBtn.onclick = () => row.remove();
    deleteCell.appendChild(deleteBtn);
    row.appendChild(deleteCell);
}

// Calculate formulas for calculation columns
function calculateFormulas(rowIndex) {
    const columns = document.querySelectorAll('#observations-table thead th');
    
    columns.forEach((col, colIndex) => {
        const columnType = col.dataset.type;
        const formula = col.dataset.formula;
        
        if (columnType === 'calculation' && formula) {
            const row = document.querySelector(`#observations-table tbody tr[data-row-index="${rowIndex}"]`);
            if (row) {
                const cell = row.cells[colIndex];
                const result = evaluateFormula(formula, rowIndex);
                if (cell.querySelector('input')) {
                    cell.querySelector('input').value = result;
                }
            }
        }
    });
}

// Simple formula evaluator (basic implementation)
function evaluateFormula(formula, rowIndex) {
    try {
        const row = document.querySelector(`#observations-table tbody tr[data-row-index="${rowIndex}"]`);
        if (!row) return '';
        
        // Replace column references with values
        let processedFormula = formula;
        const columns = document.querySelectorAll('#observations-table thead th');
        
        columns.forEach((col, colIndex) => {
            const columnName = col.dataset.column;
            const cell = row.cells[colIndex];
            const input = cell.querySelector('input');
            const value = input ? (input.value || '0') : '0';
            
            // Replace column references like 'col1', 'col2', etc.
            processedFormula = processedFormula.replace(new RegExp(`\\b${columnName}\\b`, 'gi'), value);
        });
        
        // Basic evaluation (for demo purposes - in production use a proper math parser)
        return eval(processedFormula) || '';
    } catch (e) {
        return '';
    }
}

// Save draft
function saveDraft() {
    const formData = new FormData(document.getElementById('lab-report-form'));
    const data = {
        observations: {},
        calculations: formData.get('calculations'),
        result: formData.get('result'),
        conclusion: formData.get('conclusion')
    };

    // Collect observations
    const observationInputs = document.querySelectorAll('#observations-table input');
    observationInputs.forEach(input => {
        const match = input.name.match(/observations\[(\d+)\]\[(.+)\]/);
        if (match) {
            const rowIndex = match[1];
            const columnName = match[2];
            if (!data.observations[rowIndex]) {
                data.observations[rowIndex] = {};
            }
            data.observations[rowIndex][columnName] = input.value;
        }
    });

    // Save to localStorage as backup
    localStorage.setItem('practical_<?= $practical['id'] ?>_draft', JSON.stringify(data));

    // If there's an in-progress report, also save to server
    <?php if ($report_status === 'in_progress'): ?>
    fetch('<?= APP_URL ?>/api/v1/practicals.php?id=<?= $practical['id'] ?>&action=save-draft', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert('Draft saved successfully!');
        } else {
            alert('Draft saved locally, but server save failed: ' + (result.error || 'Unknown error'));
        }
    })
    .catch(error => {
        alert('Draft saved locally. Server save failed: ' + error.message);
    });
    <?php else: ?>
    alert('Draft saved locally!');
    <?php endif; ?>
}

// Submit report
function submitReport() {
    if (!confirm('Are you sure you want to submit this report? You will not be able to edit it after submission.')) {
        return;
    }
    
    const formData = new FormData(document.getElementById('lab-report-form'));
    const data = {
        observations: {},
        calculations: formData.get('calculations'),
        result: formData.get('result'),
        conclusion: formData.get('conclusion')
    };
    
    // Collect observations
    const observationInputs = document.querySelectorAll('#observations-table input');
    observationInputs.forEach(input => {
        const match = input.name.match(/observations\[(\d+)\]\[(.+)\]/);
        if (match) {
            const rowIndex = match[1];
            const columnName = match[2];
            if (!data.observations[rowIndex]) {
                data.observations[rowIndex] = {};
            }
            data.observations[rowIndex][columnName] = input.value;
        }
    });
    
    // Submit via API
    fetch('<?= APP_URL ?>/api/v1/practicals.php?id=<?= $practical['id'] ?>&action=submit-report', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert('Report submitted successfully!');
            // Clear draft from localStorage
            localStorage.removeItem('practical_<?= $practical['id'] ?>_draft');
            // Reload page to update button state
            window.location.reload();
        } else {
            alert('Error: ' + (result.error || 'Unknown error'));
        }
    })
    .catch(error => {
        alert('Error submitting report: ' + error.message);
    });
}

// Load draft on page load
document.addEventListener('DOMContentLoaded', function() {
    // First check if there's an existing in-progress report on the server
    <?php if ($report_status === 'in_progress'): ?>
    fetch('<?= APP_URL ?>/api/v1/practicals.php?id=<?= $practical['id'] ?>&action=report')
    .then(response => response.json())
    .then(result => {
        if (result.report) {
            const report = result.report;

            // Load observations
            if (report.observations && Array.isArray(report.observations)) {
                report.observations.forEach((rowData, rowIndex) => {
                    if (observationRowCount <= rowIndex) {
                        addObservationRow();
                    }
                    Object.keys(rowData).forEach(columnName => {
                        const input = document.querySelector(`input[name="observations[${rowIndex}][${columnName}]"]`);
                        if (input) {
                            input.value = rowData[columnName];
                        }
                    });
                });
            }

            // Load other fields
            if (report.calculations) document.getElementById('calculations').value = report.calculations;
            if (report.result) document.getElementById('result').value = report.result;
            if (report.conclusion) document.getElementById('conclusion').value = report.conclusion;
        } else {
            // Fallback to localStorage draft
            loadLocalDraft();
        }
    })
    .catch(error => {
        console.error('Error loading report:', error);
        loadLocalDraft();
    });
    <?php else: ?>
    // Load local draft if no server-side report
    loadLocalDraft();
    <?php endif; ?>
});

// Load draft from localStorage
function loadLocalDraft() {
    const draft = localStorage.getItem('practical_<?= $practical['id'] ?>_draft');
    if (draft) {
        const data = JSON.parse(draft);

        // Load observations
        if (data.observations) {
            Object.keys(data.observations).forEach(rowIndex => {
                if (observationRowCount <= rowIndex) {
                    addObservationRow();
                }
                const rowData = data.observations[rowIndex];
                Object.keys(rowData).forEach(columnName => {
                    const input = document.querySelector(`input[name="observations[${rowIndex}][${columnName}]"]`);
                    if (input) {
                        input.value = rowData[columnName];
                    }
                });
            });
        }

        // Load other fields
        if (data.calculations) document.getElementById('calculations').value = data.calculations;
        if (data.result) document.getElementById('result').value = data.result;
        if (data.conclusion) document.getElementById('conclusion').value = data.conclusion;
    } else {
        // Add one empty row by default
        addObservationRow();
    }
}

// Take Practical - Start new attempt
function takePractical() {
    openTakePracticalModal('<?= $practical['id'] ?>');
}

// Continue Practical - Load existing attempt
function continuePractical() {
    // Switch to report tab
    showTab('report');

    // Load existing report data
    fetch('<?= APP_URL ?>/api/v1/practicals.php?id=<?= $practical['id'] ?>&action=report')
    .then(response => response.json())
    .then(result => {
        if (result.report) {
            const report = result.report;

            // Load observations
            if (report.observations && Array.isArray(report.observations)) {
                report.observations.forEach((rowData, rowIndex) => {
                    if (observationRowCount <= rowIndex) {
                        addObservationRow();
                    }
                    Object.keys(rowData).forEach(columnName => {
                        const input = document.querySelector(`input[name="observations[${rowIndex}][${columnName}]"]`);
                        if (input) {
                            input.value = rowData[columnName];
                        }
                    });
                });
            }

            // Load other fields
            if (report.calculations) document.getElementById('calculations').value = report.calculations;
            if (report.result) document.getElementById('result').value = report.result;
            if (report.conclusion) document.getElementById('conclusion').value = report.conclusion;
        }
    })
    .catch(error => {
        console.error('Error loading report:', error);
    });
}

// Auto load report if in progress
if ('<?= $report_status ?>' === 'in_progress') {
    continuePractical();
}
</script>

<?php require_once __DIR__.'/take_practical_modal.php'; ?>

<style>
.practical-view {
    max-width: 1200px;
    margin: 0 auto;
}

.practical-actions {
    margin-top: 16px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.status-text {
    font-size: 14px;
    color: #6b7280;
    font-weight: 500;
}

.tabs {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.tab-nav {
    display: flex;
    border-bottom: 1px solid #e1e5e9;
}

.tab-btn {
    padding: 12px 24px;
    border: none;
    background: none;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    color: #6b7280;
    border-bottom: 2px solid transparent;
    transition: all 0.2s;
}

.tab-btn.active {
    color: #2563eb;
    border-bottom-color: #2563eb;
}

.tab-btn:hover {
    color: #2563eb;
}

.tab-content {
    padding: 24px;
}

.tab-pane {
    display: none;
}

.tab-pane.active {
    display: block;
}

.content-section h3 {
    color: #1f2937;
    margin-bottom: 16px;
    font-size: 18px;
}

.content-text {
    line-height: 1.6;
    color: #4b5563;
}

.apparatus-list, .chemicals-list {
    margin-bottom: 24px;
}

.apparatus-item, .chemical-item {
    padding: 8px 0;
    border-bottom: 1px solid #f3f4f6;
}

.bullet {
    color: #6b7280;
    margin-right: 8px;
}

.procedure-step {
    margin-bottom: 20px;
    padding: 16px;
    background: #f9fafb;
    border-radius: 6px;
    border-left: 4px solid #2563eb;
}

.procedure-step h4 {
    margin: 0 0 8px 0;
    color: #1f2937;
}

.procedure-step p {
    margin: 0;
    color: #4b5563;
    line-height: 1.5;
}

.report-section {
    margin-bottom: 32px;
}

.report-section h4 {
    color: #1f2937;
    margin-bottom: 12px;
    font-size: 16px;
}

.report-instruction {
    background: #eff6ff;
    padding: 12px 16px;
    border-radius: 6px;
    border-left: 4px solid #2563eb;
    margin-bottom: 24px;
    color: #1e40af;
}

.observations-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 16px;
}

.observations-table th,
.observations-table td {
    padding: 8px 12px;
    border: 1px solid #e1e5e9;
    text-align: left;
}

.observations-table th {
    background: #f9fafb;
    font-weight: 600;
    color: #374151;
}

.form-control {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    font-size: 14px;
}

.form-control:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.1);
}

.report-actions {
    display: flex;
    gap: 12px;
    margin-top: 24px;
}

.btn {
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    border: 1px solid transparent;
    transition: all 0.2s;
}

.btn-primary {
    background: #2563eb;
    color: white;
    border-color: #2563eb;
}

.btn-primary:hover {
    background: #1d4ed8;
}

.btn-outline {
    background: white;
    color: #374151;
    border-color: #d1d5db;
}

.btn-outline:hover {
    background: #f9fafb;
}

.btn-danger {
    background: #dc2626;
    color: white;
    border-color: #dc2626;
}

.btn-sm {
    padding: 4px 8px;
    font-size: 12px;
}
</style>