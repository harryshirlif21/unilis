<div class="main-content">
    <div class="page-header">
        <div class="page-overline">Practicals Management</div>
        <h1 class="page-title">Edit Practical</h1>
        <div class="page-subtitle">Update practical details and student submission templates</div>
    </div>

    <?php if ($success): ?>
    <!-- Success Modal Overlay -->
    <div id="successModal" style="position:fixed;inset:0;z-index:1200;display:flex;align-items:center;justify-content:center;padding:1rem;">
        <div style="position:absolute;inset:0;background:rgba(0,0,0,.52);backdrop-filter:blur(2px);" onclick="this.parentElement.style.display='none'"></div>
        <div style="position:relative;z-index:1;background:#fff;border-radius:18px;padding:2rem;max-width:420px;width:100%;text-align:center;box-shadow:0 28px 72px rgba(0,0,0,.22);">
            <div style="font-size:48px;margin-bottom:12px;">✅</div>
            <h2 style="margin:0 0 6px;color:#166534;">Changes Saved!</h2>
            <p style="color:#64748b;margin:0 0 20px;font-size:14px;">Practical updated successfully. What would you like to do now?</p>
            <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
                <form method="POST" action="<?= APP_URL ?>/practicals/edit/<?= $practical['id'] ?>" style="display:inline;">
                    <input type="hidden" name="publish" value="1">
                    <button type="submit" class="btn btn-success btn-lg" style="background:#16a34a;color:white;padding:12px 28px;border-radius:10px;border:none;font-size:15px;font-weight:600;cursor:pointer;">
                        📢 Publish Now
                    </button>
                </form>
                <a href="<?= APP_URL ?>/practicals/view/<?= $practical['id'] ?>" class="btn btn-lg" style="background:#e2e8f0;color:#334155;padding:12px 28px;border-radius:10px;text-decoration:none;font-size:15px;">
                    View Practical
                </a>
            </div>
            <p style="margin-top:14px;font-size:12px;color:#94a3b8;">Once published, all students can see and take this practical.</p>
        </div>
    </div>
    <?php endif; ?>

    <div class="panel">
        <form method="POST" action="<?= APP_URL ?>/practicals/edit/<?= $practical['id'] ?>" class="modern-form" id="editPracticalForm">
            <?php if ($error): ?>
                <div style="background:#fee2e2;color:#991b1b;padding:12px 16px;border-radius:8px;margin-bottom:20px;"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="form-section">
                <h3 class="section-title">Basic Information</h3>
                
                <div class="grid grid-two">
                    <div class="form-group">
                        <label class="form-label">Practical Title *</label>
                        <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($practical['title']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Course Code</label>
                        <input type="text" name="course_code" class="form-control" value="<?= htmlspecialchars($practical['course_code'] ?? '') ?>" placeholder="e.g., PHY101">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Objective</label>
                    <div id="objective-editor" class="quill-editor"></div>
                    <textarea name="objective" style="display:none;"><?= $practical['objective'] ?? '' ?></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Theory</label>
                    <div id="theory-editor" class="quill-editor"></div>
                    <textarea name="theory" style="display:none;"><?= $practical['theory'] ?? '' ?></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <div id="description-editor" class="quill-editor"></div>
                    <textarea name="description" style="display:none;"><?= $practical['description'] ?? '' ?></textarea>
                </div>
            </div>

            <div class="form-section">
                <h3 class="section-title">Laboratory Details</h3>
                
                <div class="grid grid-two">
                    <div class="form-group">
                        <label class="form-label">Laboratory *</label>
                        <select name="lab_id" class="form-control" required>
                            <option value="">Select laboratory...</option>
                            <?php foreach ($labs as $lab): ?>
                                <option value="<?= $lab['id'] ?>" <?= $practical['lab_id'] === $lab['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($lab['name']) ?> (<?= htmlspecialchars($lab['lab_code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Max Students</label>
                        <input type="number" name="max_students" class="form-control" value="<?= htmlspecialchars($practical['max_students'] ?? 30) ?>" min="1" max="100">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3 class="section-title">Schedule</h3>
                
                <div class="grid grid-three">
                    <div class="form-group">
                        <label class="form-label">Scheduled Date *</label>
                        <input type="date" name="scheduled_date" class="form-control" value="<?= htmlspecialchars($practical['scheduled_date']) ?>" min="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Start Time *</label>
                        <input type="time" name="start_time" class="form-control" value="<?= htmlspecialchars($practical['start_time']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">End Time *</label>
                        <input type="time" name="end_time" class="form-control" value="<?= htmlspecialchars($practical['end_time']) ?>" required>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3 class="section-title">Resources & Safety</h3>
                
                <div class="form-group">
                    <label class="form-label">Required Equipment</label>
                    <textarea name="required_equipment" class="form-control" rows="3" placeholder="List equipment needed (one per line)..."><?= htmlspecialchars($practical['required_equipment'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Required Chemicals</label>
                    <textarea name="required_chemicals" class="form-control" rows="3" placeholder="List chemicals needed (one per line)..."><?= htmlspecialchars($practical['required_chemicals'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Safety Notes</label>
                    <textarea name="safety_notes" class="form-control" rows="3" placeholder="Safety precautions and warnings..."><?= htmlspecialchars($practical['safety_notes'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="form-section">
                <h3 class="section-title">Procedure</h3>
                <p class="section-desc">Define the step-by-step procedure for this practical</p>
                
                <div id="procedure-steps-container">
                    <!-- Procedure steps will be loaded dynamically -->
                </div>
                
                <button type="button" onclick="addProcedureStep()" class="btn btn-outline">Add Step</button>
                
                <textarea name="procedure_json" id="procedure-json" style="display:none;"></textarea>
            </div>

            <div class="form-section">
                <h3 class="section-title">Observations Table Structure</h3>
                <p class="section-desc">Define the structure of the observations table that students will fill</p>
                
                <div id="observations-columns-container">
                    <!-- Observations columns will be loaded dynamically -->
                </div>
                
                <button type="button" onclick="addObservationsColumn()" class="btn btn-outline">Add Column</button>
                
                <textarea name="observations_table_structure" id="observations-structure-json" style="display:none;"></textarea>
            </div>

            <div class="form-section">
                <h3 class="section-title">Student Submission Templates</h3>
                <p class="section-desc">Provide templates for students to fill in their results and calculations</p>
                
                <div class="form-group">
                    <label class="form-label">Results Table Template</label>
                    <div class="table-builder-container" id="table-builder-wrapper" style="display:none;">
                        <div class="table-toolbar">
                            <button type="button" class="btn btn-outline btn-sm" onclick="window.addTableRow()">Add Row</button>
                            <button type="button" class="btn btn-outline btn-sm" onclick="window.addTableColumn()">Add Column</button>
                            <button type="button" class="btn btn-outline btn-sm" onclick="window.removeLastRow()">Remove Last Row</button>
                            <button type="button" class="btn btn-outline btn-sm" onclick="window.removeLastColumn()">Remove Last Column</button>
                            <button type="button" class="btn btn-outline btn-sm" onclick="window.clearTable()">Clear Table</button>
                            <button type="button" class="btn btn-outline btn-sm" onclick="window.toggleTableBuilder()">Close Table Builder</button>
                        </div>
                        <div class="table-preview">
                            <table id="results-table-builder" class="table-builder">
                                <thead>
                                    <tr>
                                        <th contenteditable="true">Column 1</th>
                                        <th contenteditable="true">Column 2</th>
                                        <th contenteditable="true">Column 3</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td contenteditable="true"></td>
                                        <td contenteditable="true"></td>
                                        <td contenteditable="true"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="table-html-preview">
                            <label class="form-label">Table HTML Preview</label>
                            <textarea id="table-html-output" name="results_template" rows="6" class="form-control" style="display:none;"></textarea>
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline btn-sm" id="open-table-builder-btn">Open Table Builder</button>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Calculations & Observations Template</label>
                    <div id="calculations-template" class="quill-editor"></div>
                    <textarea name="calculations_template" style="display:none;"><?= $practical['calculations_template'] ?? '' ?></textarea>
                </div>
            </div>

            <hr style="border:none;border-top:1px solid #e2e8f0;margin:24px 0;">

            <!-- Action Buttons Row -->
            <div class="form-actions d-flex gap-3 mt-4" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
                <button type="submit" class="btn btn-primary btn-lg" style="background:#2563eb;color:white;padding:12px 28px;border-radius:10px;border:none;font-size:15px;font-weight:600;cursor:pointer;">
                    💾 Update Practical
                </button>

                <a href="<?= APP_URL ?>/practicals/view/<?= $practical['id'] ?>" class="btn btn-secondary btn-lg" style="background:#e2e8f0;color:#334155;padding:12px 28px;border-radius:10px;text-decoration:none;font-size:15px;">
                    Cancel
                </a>

                <!-- Postpone Button -->
                <button type="button" onclick="confirmPostpone('<?= $practical['id'] ?>', '<?= htmlspecialchars($practical['title'], ENT_QUOTES) ?>')" class="btn btn-lg" style="background:#f59e0b;color:#1e293b;padding:12px 28px;border-radius:10px;border:none;font-size:15px;font-weight:600;cursor:pointer;margin-left:auto;">
                    ⏸️ Postpone
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Postpone Confirmation Modal -->
<div id="postponeModal" class="modal-overlay hidden">
    <div class="modal-backdrop" onclick="closePostponeModal()"></div>
    <div class="modal-card" style="max-width:450px;">
        <div class="modal-card-header">
            <h2>⏸️ Postpone Practical</h2>
            <button class="modal-close" onclick="closePostponeModal()">&times;</button>
        </div>
        <div class="modal-body" style="padding:1.5rem 1.6rem;">
            <p style="margin-bottom:16px;">Are you sure you want to postpone <strong id="postponeTitle">this practical</strong>?</p>
            <p style="color:#64748b;font-size:14px;margin-bottom:16px;">Postponing will mark the practical as <strong>"postponed"</strong>. It will be hidden from students until it is offered again.</p>
            <form id="postponeForm" method="POST" action="<?= APP_URL ?>/practicals/postpone/<?= $practical['id'] ?>">
                <div class="form-group">
                    <label class="form-label">Reason for postponement (optional)</label>
                    <textarea name="postpone_reason" class="form-control" rows="3" placeholder="e.g., Lab equipment unavailable, schedule conflict..."></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer" style="display:flex;gap:10px;justify-content:flex-end;padding:1rem 1.6rem 1.4rem;">
            <button class="btn btn-secondary" onclick="closePostponeModal()">Cancel</button>
            <button type="submit" form="postponeForm" class="btn" style="background:#f59e0b;color:#1e293b;padding:10px 20px;border:none;border-radius:8px;cursor:pointer;font-weight:600;">
                ⏸️ Confirm Postpone
            </button>
        </div>
    </div>
</div>

<!-- Quill editor includes and JavaScript -->
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<script>
// Postpone functions
function confirmPostpone(id, title) {
    document.getElementById('postponeTitle').textContent = title;
    document.getElementById('postponeModal').classList.remove('hidden');
}
function closePostponeModal() {
    document.getElementById('postponeModal').classList.add('hidden');
}

document.addEventListener('DOMContentLoaded', function() {
    // Initialize Quill editors
    const objectiveEditor = new Quill('#objective-editor', {
        theme: 'snow',
        modules: { toolbar: [[{ 'header': [1, 2, 3, false] }],['bold', 'italic', 'underline', 'strike'],[{ 'list': 'ordered'}, { 'list': 'bullet' }],['link', 'image'],['clean']] },
        placeholder: 'State the learning objectives for this practical...'
    });

    const theoryEditor = new Quill('#theory-editor', {
        theme: 'snow',
        modules: { toolbar: [[{ 'header': [1, 2, 3, false] }],['bold', 'italic', 'underline', 'strike'],[{ 'list': 'ordered'}, { 'list': 'bullet' }],['link', 'image'],['clean']] },
        placeholder: 'Provide the theoretical background and principles...'
    });

    const descriptionEditor = new Quill('#description-editor', {
        theme: 'snow',
        modules: { toolbar: [[{ 'header': [1, 2, 3, false] }],['bold', 'italic', 'underline', 'strike'],[{ 'list': 'ordered'}, { 'list': 'bullet' }],['link', 'image'],['clean']] },
        placeholder: 'Describe the practical objectives and procedures...'
    });

    const resultsTemplateEditor = new Quill('#results-template', {
        theme: 'snow',
        modules: { toolbar: [[{ 'header': [1, 2, 3, false] }],['bold', 'italic', 'underline', 'strike'],[{ 'list': 'ordered'}, { 'list': 'bullet' }],['link', 'image'],['clean']] },
        placeholder: 'Create a table template for students to record their experimental results...'
    });

    const calculationsTemplateEditor = new Quill('#calculations-template', {
        theme: 'snow',
        modules: { toolbar: [[{ 'header': [1, 2, 3, false] }],['bold', 'italic', 'underline', 'strike'],[{ 'list': 'ordered'}, { 'list': 'bullet' }],['link', 'image'],['clean']] },
        placeholder: 'Provide instructions and space for students to show their calculations and observations...'
    });

    // Load existing content from hidden textareas
    const objectiveContent = document.querySelector('textarea[name="objective"]').value;
    if (objectiveContent) objectiveEditor.root.innerHTML = objectiveContent;

    const theoryContent = document.querySelector('textarea[name="theory"]').value;
    if (theoryContent) theoryEditor.root.innerHTML = theoryContent;

    const descriptionContent = document.querySelector('textarea[name="description"]').value;
    if (descriptionContent) descriptionEditor.root.innerHTML = descriptionContent;

    const resultsContent = document.querySelector('textarea[name="results_template"]').value;
    if (resultsContent) resultsTemplateEditor.root.innerHTML = resultsContent;

    const calculationsContent = document.querySelector('textarea[name="calculations_template"]').value;
    if (calculationsContent) calculationsTemplateEditor.root.innerHTML = calculationsContent;

    // Load existing procedure steps
    const procedureData = <?= json_encode(json_decode($practical['procedure_json'] ?? '[]', true)) ?>;
    procedureData.forEach((step) => { addProcedureStep(step.step_description); });

    // Load existing observations structure
    const observationsData = <?= json_encode(json_decode($practical['observations_table_structure'] ?? '[]', true)) ?>;
    observationsData.forEach((column) => { addObservationsColumn(column); });

    // Sync editors to hidden textareas on form submit
    document.querySelector('#editPracticalForm').addEventListener('submit', function() {
        document.querySelector('textarea[name="objective"]').value = objectiveEditor.root.innerHTML;
        document.querySelector('textarea[name="theory"]').value = theoryEditor.root.innerHTML;
        document.querySelector('textarea[name="description"]').value = descriptionEditor.root.innerHTML;
        document.querySelector('textarea[name="results_template"]').value = resultsTemplateEditor.root.innerHTML;
        document.querySelector('textarea[name="calculations_template"]').value = calculationsTemplateEditor.root.innerHTML;
        
        // Build procedure JSON
        const procedureSteps = [];
        document.querySelectorAll('#procedure-steps-container textarea').forEach((textarea, index) => {
            if (textarea.value.trim()) {
                procedureSteps.push({ step_number: index + 1, step_description: textarea.value.trim() });
            }
        });
        document.getElementById('procedure-json').value = JSON.stringify(procedureSteps);
        
        // Build observations structure JSON
        const observationsColumns = [];
        document.querySelectorAll('#observations-columns-container .observations-column').forEach((columnDiv) => {
            const nameInput = columnDiv.querySelector('input[name*="[name]"]');
            const typeSelect = columnDiv.querySelector('select[name*="[type]"]');
            const formulaInput = columnDiv.querySelector('input[name*="[formula]"]');
            if (nameInput && nameInput.value.trim()) {
                observationsColumns.push({
                    name: nameInput.value.trim(),
                    type: typeSelect ? typeSelect.value : 'text',
                    formula: formulaInput ? formulaInput.value.trim() : ''
                });
            }
        });
        document.getElementById('observations-structure-json').value = JSON.stringify(observationsColumns);
        
        syncTableHTML();
    });

    // --- Image Paste Handler for Quill Editors ---
    function setupPasteHandler(quillEditor) {
        quillEditor.root.addEventListener('paste', function(e) {
            const items = (e.clipboardData || e.originalEvent?.clipboardData)?.items;
            if (!items) return;

            let imageBlob = null;
            for (const item of items) {
                if (item.type && item.type.startsWith('image/')) {
                    imageBlob = item.getAsFile();
                    break;
                }
            }

            if (!imageBlob) return;

            e.preventDefault();

            const reader = new FileReader();
            reader.onload = function(readerEvent) {
                const base64DataUrl = readerEvent.target.result;

                fetch('<?= APP_URL ?>/public/upload_base64.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ image: base64DataUrl })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.location) {
                        const range = quillEditor.getSelection(true);
                        quillEditor.insertEmbed(range.index, 'image', data.location);
                        quillEditor.setSelection(range.index + 1);
                    } else {
                        console.error('Image paste upload failed:', data.error || 'Unknown error');
                    }
                })
                .catch(err => {
                    console.error('Error uploading pasted image:', err);
                });
            };
            reader.readAsDataURL(imageBlob);
        });
    }

    // Image upload handlers (toolbar button)
    const imageHandler = function(editor) {
        const input = document.createElement('input');
        input.setAttribute('type', 'file');
        input.setAttribute('accept', 'image/*');
        input.click();
        input.onchange = () => {
            const file = input.files[0];
            if (!file) return;
            const formData = new FormData();
            formData.append('file', file);
            fetch('<?= APP_URL ?>/public/upload.php', { method: 'POST', body: formData })
                .then(r => r.json()).then(data => {
                    if (data.location) {
                        const range = editor.getSelection();
                        editor.insertEmbed(range.index, 'image', data.location);
                    } else { alert('Failed to upload image'); }
                }).catch(() => { alert('Error uploading image'); });
        };
    };

    descriptionEditor.getModule('toolbar').addHandler('image', () => imageHandler(descriptionEditor));
    resultsTemplateEditor.getModule('toolbar').addHandler('image', () => imageHandler(resultsTemplateEditor));
    calculationsTemplateEditor.getModule('toolbar').addHandler('image', () => imageHandler(calculationsTemplateEditor));

    // Setup paste handlers for all Quill editors
    setupPasteHandler(objectiveEditor);
    setupPasteHandler(theoryEditor);
    setupPasteHandler(descriptionEditor);
    setupPasteHandler(resultsTemplateEditor);
    setupPasteHandler(calculationsTemplateEditor);

    // Table builder functions
    window.addTableRow = function() {
        const table = document.getElementById('results-table-builder');
        const tbody = table.querySelector('tbody');
        const colCount = table.rows[0].cells.length;
        const newRow = tbody.insertRow();
        for (let i = 0; i < colCount; i++) { const cell = newRow.insertCell(i); cell.contentEditable = true; }
        syncTableHTML();
    };
    window.addTableColumn = function() {
        const table = document.getElementById('results-table-builder');
        for (let i = 0; i < table.rows.length; i++) {
            const cell = i === 0 ? document.createElement('th') : document.createElement('td');
            cell.contentEditable = true;
            cell.textContent = i === 0 ? 'New Column' : '';
            table.rows[i].appendChild(cell);
        }
        syncTableHTML();
    };
    window.removeLastRow = function() {
        const table = document.getElementById('results-table-builder');
        const tbody = table.querySelector('tbody');
        if (tbody.rows.length > 1) { tbody.deleteRow(tbody.rows.length - 1); syncTableHTML(); }
    };
    window.removeLastColumn = function() {
        const table = document.getElementById('results-table-builder');
        if (table.rows[0].cells.length > 1) {
            for (let i = 0; i < table.rows.length; i++) table.rows[i].deleteCell(table.rows[i].cells.length - 1);
            syncTableHTML();
        }
    };
    window.clearTable = function() {
        if (confirm('Clear table?')) {
            document.getElementById('results-table-builder').querySelector('tbody').innerHTML = '<tr><td contenteditable="true"></td><td contenteditable="true"></td><td contenteditable="true"></td></tr>';
            document.getElementById('results-table-builder').querySelector('thead').innerHTML = '<tr><th contenteditable="true">Column 1</th><th contenteditable="true">Column 2</th><th contenteditable="true">Column 3</th></tr>';
            syncTableHTML();
        }
    };
    window.toggleTableBuilder = function() {
        const wrapper = document.getElementById('table-builder-wrapper');
        wrapper.style.display = wrapper.style.display === 'none' ? 'block' : 'none';
    };

    function syncTableHTML() {
        const table = document.getElementById('results-table-builder');
        document.getElementById('table-html-output').value = table.outerHTML;
    }

    const existingTableHTML = document.querySelector('textarea[name="results_template"]').value;
    if (existingTableHTML) {
        const parser = new DOMParser();
        const doc = parser.parseFromString(existingTableHTML, 'text/html');
        const existingTable = doc.querySelector('table');
        if (existingTable) {
            document.getElementById('results-table-builder').outerHTML = existingTable.outerHTML;
            document.getElementById('results-table-builder').id = 'results-table-builder';
            document.getElementById('results-table-builder').classList.add('table-builder');
        }
    }
    document.getElementById('results-table-builder').addEventListener('input', syncTableHTML);
    document.getElementById('open-table-builder-btn').addEventListener('click', window.toggleTableBuilder);

    // Auto-hide success modal after 10 seconds
    const successModal = document.getElementById('successModal');
    if (successModal) {
        setTimeout(() => { successModal.style.display = 'none'; }, 10000);
    }
});

// Procedure step management
let procedureStepCount = 0;
function addProcedureStep(description = '') {
    const container = document.getElementById('procedure-steps-container');
    const stepDiv = document.createElement('div');
    stepDiv.className = 'procedure-step';
    stepDiv.innerHTML = `<div class="form-group"><label class="form-label">Step ${procedureStepCount + 1}</label><textarea name="procedure_steps[${procedureStepCount}]" class="form-control" rows="2" placeholder="Describe this step...">${description}</textarea></div><button type="button" onclick="removeProcedureStep(this)" class="btn btn-danger btn-sm">Remove</button>`;
    container.appendChild(stepDiv);
    procedureStepCount++;
}
function removeProcedureStep(button) { button.parentElement.remove(); updateProcedureStepNumbers(); }
function updateProcedureStepNumbers() {
    const steps = document.querySelectorAll('#procedure-steps-container .procedure-step');
    steps.forEach((step, index) => {
        const label = step.querySelector('.form-label');
        if (label) label.textContent = `Step ${index + 1}`;
        const textarea = step.querySelector('textarea');
        if (textarea) textarea.name = `procedure_steps[${index}]`;
    });
    procedureStepCount = steps.length;
}

// Observations column management
let observationsColumnCount = 0;
function addObservationsColumn(column = null) {
    const container = document.getElementById('observations-columns-container');
    const columnDiv = document.createElement('div');
    columnDiv.className = 'observations-column';
    columnDiv.innerHTML = `
        <div class="form-row" style="display:flex;gap:10px;">
            <div class="form-group" style="flex:2;"><label class="form-label">Column Name</label><input type="text" name="observations_columns[${observationsColumnCount}][name]" class="form-control" placeholder="e.g., Trial" value="${column ? column.name : ''}"></div>
            <div class="form-group" style="flex:1;"><label class="form-label">Type</label><select name="observations_columns[${observationsColumnCount}][type]" class="form-control"><option value="text" ${column && column.type === 'text' ? 'selected' : ''}>Text</option><option value="number" ${column && column.type === 'number' ? 'selected' : ''}>Number</option><option value="calculation" ${column && column.type === 'calculation' ? 'selected' : ''}>Calculation</option></select></div>
        </div>
        <div class="form-group"><label class="form-label">Formula (for calculation columns)</label><input type="text" name="observations_columns[${observationsColumnCount}][formula]" class="form-control" placeholder="e.g., col1 + col2" value="${column ? column.formula : ''}"></div>
        <button type="button" onclick="removeObservationsColumn(this)" class="btn btn-danger btn-sm">Remove</button>
        <hr style="border:none;border-top:1px solid #f0f0f0;margin:10px 0;">`;
    container.appendChild(columnDiv);
    observationsColumnCount++;
}
function removeObservationsColumn(button) { button.parentElement.remove(); updateObservationsColumnNames(); }
function updateObservationsColumnNames() {
    const columns = document.querySelectorAll('#observations-columns-container .observations-column');
    columns.forEach((column, index) => {
        const nameInput = column.querySelector('input[name*="[name]"]');
        const typeSelect = column.querySelector('select[name*="[type]"]');
        const formulaInput = column.querySelector('input[name*="[formula]"]');
        if (nameInput) nameInput.name = `observations_columns[${index}][name]`;
        if (typeSelect) typeSelect.name = `observations_columns[${index}][type]`;
        if (formulaInput) formulaInput.name = `observations_columns[${index}][formula]`;
    });
    observationsColumnCount = columns.length;
}
</script>

<style>
.modal-overlay.hidden { display: none !important; }
.modal-overlay { position:fixed;inset:0;z-index:1100;display:flex;align-items:center;justify-content:center;padding:1rem; }
.modal-backdrop { position:absolute;inset:0;background:rgba(0,0,0,.52);backdrop-filter:blur(2px); }
.modal-card { position:relative;z-index:1;width:min(100%,750px);background:#fff;border-radius:18px;box-shadow:0 28px 72px rgba(0,0,0,.22);max-height:90vh;overflow-y:auto; }
.modal-card-header { padding:1.4rem 1.6rem .8rem;display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;border-bottom:1px solid #f0f0f0; }
.modal-card-header h2 { margin:0;font-size:1.2rem;font-weight:700;color:#0f172a; }
.modal-close { border:none;background:transparent;font-size:1.4rem;cursor:pointer;color:#94a3b8;padding:.2rem .4rem;border-radius:6px; }
.modal-footer { border-top:1px solid #f0f0f0; }
.btn-lg { font-size:15px;padding:12px 28px;border-radius:10px;font-weight:600; }
.form-section { margin-bottom: 32px; padding-bottom: 32px; border-bottom: 1px solid #e2e8f0; }
.form-section:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
.section-title { font-size: 16px; font-weight: 700; color: #0f172a; margin-bottom: 20px; }
.section-desc { font-size: 13px; color: #64748b; margin-bottom: 16px; margin-top: -12px; }
.form-group { margin-bottom: 18px; }
.form-label { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.2px; color: #64748b; margin-bottom: 6px; text-transform: uppercase; }
.form-control { width: 100%; padding: 10px 14px; border-radius: 8px; background: #fff; border: 1px solid #d1d5db; color: #1e293b; font-size: 14px; box-sizing:border-box; }
.form-control:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.1); }
.grid { display: grid; gap: 16px; }
.grid-two { grid-template-columns: 1fr 1fr; }
.grid-three { grid-template-columns: 1fr 1fr 1fr; }
.quill-editor { min-height: 200px; background: #fff; border: 1px solid #d1d5db; border-radius: 8px; }
.ql-toolbar { border-top-left-radius: 8px; border-top-right-radius: 8px; background: #fff; }
.ql-container { border-bottom-left-radius: 8px; border-bottom-right-radius: 8px; background: #fff; min-height: 200px; }
.table-builder-container { border: 1px solid #d1d5db; border-radius: 8px; background: #fff; padding: 1rem; }
.table-toolbar { display: flex; gap: 0.5rem; margin-bottom: 1rem; flex-wrap: wrap; }
.table-preview { overflow-x: auto; margin-bottom: 1rem; }
.table-builder { width: 100%; border-collapse: collapse; }
.table-builder th, .table-builder td { border: 1px solid #d1d5db; padding: 0.75rem; min-width: 100px; text-align: center; }
.table-builder th { background: #f8fafc; font-weight: 600; }
.btn { padding: 8px 16px; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; border: none; transition: all .15s; display:inline-flex;align-items:center;gap:6px; }
.btn-outline { background: white; color: #374151; border: 1px solid #d1d5db; }
.btn-outline:hover { background: #f9fafb; }
.btn-danger { background: #dc2626; color: white; }
.btn-danger:hover { background: #b91c1c; }
.btn-sm { padding: 4px 10px; font-size: 12px; }
@media (max-width: 768px) { .grid-two, .grid-three { grid-template-columns: 1fr; } }
</style>