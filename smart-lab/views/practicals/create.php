<div class="main-content">
    <div class="page-header">
        <div class="page-overline">Practicals Management</div>
        <h1 class="page-title">Create New Practical</h1>
        <div class="page-subtitle">Set up a new laboratory practical session with scheduling and resource requirements</div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger" style="background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 12px 20px; border-radius: 4px; margin-bottom: 20px; color: #721c24;">
            <strong>Error:</strong> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success" style="background-color: #d4edda; border: 1px solid #c3e6cb; padding: 12px 20px; border-radius: 4px; margin-bottom: 20px; color: #155724;">
            <strong>Success:</strong> <?= htmlspecialchars($success) ?>
            <p style="margin-top: 10px; margin-bottom: 0;">
                <a href="<?= APP_URL ?>/practicals" style="color: #155724; text-decoration: underline;">View all practicals</a>
            </p>
        </div>
    <?php endif; ?>

    <div class="panel">
        <form method="POST" action="<?= APP_URL ?>/practicals/create" class="modern-form">
            <div class="form-section">
                <h3 class="section-title">Basic Information</h3>
                
                <div class="grid grid-two">
                    <div class="form-group">
                        <label class="form-label">Practical Title *</label>
                        <input type="text" name="title" class="form-control" 
                            value="<?= htmlspecialchars($data['title'] ?? '') ?>" 
                            placeholder="Enter practical title..." required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Course Code</label>
                        <input type="text" name="course_code" class="form-control" 
                            value="<?= htmlspecialchars($data['course_code'] ?? '') ?>" 
                            placeholder="e.g., PHY101">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Objective</label>
                    <div id="objective-editor" class="quill-editor"></div>
                    <textarea name="objective" style="display:none;"><?= htmlspecialchars($data['objective'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Theory</label>
                    <div id="theory-editor" class="quill-editor"></div>
                    <textarea name="theory" style="display:none;"><?= htmlspecialchars($data['theory'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <div id="description-editor" class="quill-editor"></div>
                    <textarea name="description" style="display:none;"><?= htmlspecialchars($data['description'] ?? '') ?></textarea>
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
                                <option value="<?= $lab['id'] ?>" <?= (isset($data['lab_id']) && $data['lab_id'] === $lab['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($lab['name']) ?> (<?= htmlspecialchars($lab['lab_code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Max Students</label>
                        <input type="number" name="max_students" class="form-control" 
                            value="<?= htmlspecialchars($data['max_students'] ?? 30) ?>" 
                            min="1" max="100">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3 class="section-title">Schedule</h3>
                
                <div class="grid grid-three">
                    <div class="form-group">
                        <label class="form-label">Scheduled Date *</label>
                        <input type="date" name="scheduled_date" class="form-control" 
                            value="<?= htmlspecialchars($data['scheduled_date'] ?? '') ?>" 
                            min="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Start Time *</label>
                        <input type="time" name="start_time" class="form-control" 
                            value="<?= htmlspecialchars($data['start_time'] ?? '') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">End Time *</label>
                        <input type="time" name="end_time" class="form-control" 
                            value="<?= htmlspecialchars($data['end_time'] ?? '') ?>" required>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3 class="section-title">Resources & Safety</h3>
                
                <div class="form-group">
                    <label class="form-label">Required Equipment</label>
                    <textarea name="required_equipment" class="form-control" rows="3" 
                        placeholder="List equipment needed (one per line)..."><?= htmlspecialchars($data['required_equipment'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Required Chemicals</label>
                    <textarea name="required_chemicals" class="form-control" rows="3" 
                        placeholder="List chemicals needed (one per line)..."><?= htmlspecialchars($data['required_chemicals'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Safety Notes</label>
                    <textarea name="safety_notes" class="form-control" rows="3" 
                        placeholder="Safety precautions and warnings..."><?= htmlspecialchars($data['safety_notes'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="form-section">
                <h3 class="section-title">Procedure</h3>
                <p class="section-desc">Define the step-by-step procedure for this practical</p>
                
                <div id="procedure-steps-container">
                    <div class="procedure-step">
                        <div class="form-group">
                            <label class="form-label">Step 1</label>
                            <textarea name="procedure_steps[0]" class="form-control" rows="2" 
                                placeholder="Describe the first step..."></textarea>
                        </div>
                        <button type="button" onclick="removeProcedureStep(this)" class="btn btn-danger btn-sm">Remove</button>
                    </div>
                </div>
                
                <button type="button" onclick="addProcedureStep()" class="btn btn-outline">Add Step</button>
                
                <textarea name="procedure_json" id="procedure-json" style="display:none;"></textarea>
            </div>

            <div class="form-section">
                <h3 class="section-title">Observations Table Structure</h3>
                <p class="section-desc">Define the structure of the observations table that students will fill</p>
                
                <div id="observations-columns-container">
                    <div class="observations-column">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Column Name</label>
                                <input type="text" name="observations_columns[0][name]" class="form-control" 
                                    placeholder="e.g., Trial" value="Trial">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Column Type</label>
                                <select name="observations_columns[0][type]" class="form-control">
                                    <option value="text">Text</option>
                                    <option value="number">Number</option>
                                    <option value="calculation">Calculation</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Formula (for calculation columns)</label>
                            <input type="text" name="observations_columns[0][formula]" class="form-control" 
                                placeholder="e.g., col1 + col2">
                        </div>
                        
                        <button type="button" onclick="removeObservationsColumn(this)" class="btn btn-danger btn-sm">Remove</button>
                    </div>
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
                    <textarea name="calculations_template" style="display:none;"><?= htmlspecialchars($data['calculations_template'] ?? '') ?></textarea>
                </div>
            </div>
            
            <div class="form-actions d-flex gap-3 mt-4">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="pi pi-plus"></i> Create Practical
                </button>
                <a href="<?= APP_URL ?>/practicals" class="btn btn-secondary btn-lg">
                    <i class="pi pi-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Quill Rich Text Editor -->
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Quill editors
    const objectiveEditor = new Quill('#objective-editor', {
        theme: 'snow',
        modules: {
            toolbar: [
                [{ 'header': [1, 2, 3, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                ['link', 'image'],
                ['clean']
            ],
            clipboard: {
                matchVisual: false,
                matchers: [
                    ['b', 'strong'],
                    ['i', 'em'],
                    ['u', 'underline'],
                    ['s', 'strike']
                ]
            }
        },
        placeholder: 'State the learning objectives for this practical...'
    });

    const theoryEditor = new Quill('#theory-editor', {
        theme: 'snow',
        modules: {
            toolbar: [
                [{ 'header': [1, 2, 3, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                ['link', 'image'],
                ['clean']
            ],
            clipboard: {
                matchVisual: false,
                matchers: [
                    ['b', 'strong'],
                    ['i', 'em'],
                    ['u', 'underline'],
                    ['s', 'strike']
                ]
            }
        },
        placeholder: 'Provide the theoretical background and principles...'
    });

    const descriptionEditor = new Quill('#description-editor', {
        theme: 'snow',
        modules: {
            toolbar: [
                [{ 'header': [1, 2, 3, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                ['link', 'image'],
                ['clean']
            ],
            clipboard: {
                matchVisual: false,
                matchers: [
                    ['b', 'strong'],
                    ['i', 'em'],
                    ['u', 'underline'],
                    ['s', 'strike']
                ]
            }
        },
        placeholder: 'Describe the practical objectives and procedures...'
    });

    const calculationsTemplateEditor = new Quill('#calculations-template', {
        theme: 'snow',
        modules: {
            toolbar: [
                [{ 'header': [1, 2, 3, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                ['link', 'image'],
                ['clean']
            ],
            clipboard: {
                matchVisual: false,
                matchers: [
                    ['b', 'strong'],
                    ['i', 'em'],
                    ['u', 'underline'],
                    ['s', 'strike']
                ]
            }
        },
        placeholder: 'Provide instructions and space for students to show their calculations and observations...'
    });

    // Load existing content from hidden textareas
    const objectiveContent = document.querySelector('textarea[name="objective"]').value;
    if (objectiveContent) {
        objectiveEditor.root.innerHTML = objectiveContent;
    }

    const theoryContent = document.querySelector('textarea[name="theory"]').value;
    if (theoryContent) {
        theoryEditor.root.innerHTML = theoryContent;
    }

    const descriptionContent = document.querySelector('textarea[name="description"]').value;
    if (descriptionContent) {
        descriptionEditor.root.innerHTML = descriptionContent;
    }

    const calculationsContent = document.querySelector('textarea[name="calculations_template"]').value;
    if (calculationsContent) {
        calculationsTemplateEditor.root.innerHTML = calculationsContent;
    }

    // Sync editors to hidden textareas on form submit
    document.querySelector('form').addEventListener('submit', function() {
        document.querySelector('textarea[name="objective"]').value = objectiveEditor.root.innerHTML;
        document.querySelector('textarea[name="theory"]').value = theoryEditor.root.innerHTML;
        document.querySelector('textarea[name="description"]').value = descriptionEditor.root.innerHTML;
        document.querySelector('textarea[name="calculations_template"]').value = calculationsTemplateEditor.root.innerHTML;
        
        // Build procedure JSON
        const procedureSteps = [];
        document.querySelectorAll('#procedure-steps-container textarea').forEach((textarea, index) => {
            if (textarea.value.trim()) {
                procedureSteps.push({
                    step_number: index + 1,
                    step_description: textarea.value.trim()
                });
            }
        });
        document.getElementById('procedure-json').value = JSON.stringify(procedureSteps);
        
        // Build observations structure JSON
        const observationsColumns = [];
        document.querySelectorAll('#observations-columns-container .observations-column').forEach((columnDiv, index) => {
            const nameInput = columnDiv.querySelector('input[name*="name"]');
            const typeSelect = columnDiv.querySelector('select[name*="type"]');
            const formulaInput = columnDiv.querySelector('input[name*="formula"]');
            
            if (nameInput && nameInput.value.trim()) {
                observationsColumns.push({
                    name: nameInput.value.trim(),
                    type: typeSelect ? typeSelect.value : 'text',
                    formula: formulaInput ? formulaInput.value.trim() : ''
                });
            }
        });
        document.getElementById('observations-structure-json').value = JSON.stringify(observationsColumns);
    });

    // Handle image uploads
    const toolbar = descriptionEditor.getModule('toolbar');
    toolbar.addHandler('image', function() {
        selectLocalImage(descriptionEditor);
    });



    const calculationsToolbar = calculationsTemplateEditor.getModule('toolbar');
    calculationsToolbar.addHandler('image', function() {
        selectLocalImage(calculationsTemplateEditor);
    });

    function selectLocalImage(editor) {
        const input = document.createElement('input');
        input.setAttribute('type', 'file');
        input.setAttribute('accept', 'image/*');
        input.click();

        input.onchange = () => {
            const file = input.files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('file', file);

            fetch('<?= APP_URL ?>/public/upload.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.location) {
                    const range = editor.getSelection();
                    editor.insertEmbed(range.index, 'image', data.location);
                } else {
                    alert('Failed to upload image');
                }
            })
            .catch(error => {
                alert('Error uploading image');
            });
        };
    }

    // Sync content to hidden textarea before form submission
    const form = document.querySelector('form');
    form.addEventListener('submit', function(e) {
        // Sync Quill content to hidden textareas
        const descTextarea = document.querySelector('textarea[name="description"]');
        const calcTextarea = document.querySelector('textarea[name="calculations_template"]');
        
        if (descTextarea && descriptionEditor) {
            descTextarea.value = descriptionEditor.root.innerHTML;
        }
        if (calcTextarea && calculationsTemplateEditor) {
            calcTextarea.value = calculationsTemplateEditor.root.innerHTML;
        }
        
        // Sync table HTML
        syncTableHTML();
        
        // Allow form to submit normally
        return true;
    });

    // Table builder functions - make them global
    window.addTableRow = function() {
        const table = document.getElementById('results-table-builder');
        const tbody = table.querySelector('tbody');
        const colCount = table.rows[0].cells.length;
        
        const newRow = tbody.insertRow();
        for (let i = 0; i < colCount; i++) {
            const cell = newRow.insertCell(i);
            cell.contentEditable = 'true';
            cell.addEventListener('input', syncTableHTML);
        }
        syncTableHTML();
    };

    window.addTableColumn = function() {
        const table = document.getElementById('results-table-builder');
        const rows = table.rows;
        
        for (let i = 0; i < rows.length; i++) {
            const cell = i === 0 ? document.createElement('th') : document.createElement('td');
            cell.contentEditable = 'true';
            cell.textContent = i === 0 ? 'New Column' : '';
            cell.addEventListener('input', syncTableHTML);
            rows[i].appendChild(cell);
        }
        syncTableHTML();
    };

    window.removeLastRow = function() {
        const table = document.getElementById('results-table-builder');
        const tbody = table.querySelector('tbody');
        if (tbody.rows.length > 1) {
            tbody.deleteRow(tbody.rows.length - 1);
            syncTableHTML();
        }
    };

    window.removeLastColumn = function() {
        const table = document.getElementById('results-table-builder');
        const rows = table.rows;
        if (rows[0].cells.length > 1) {
            for (let i = 0; i < rows.length; i++) {
                rows[i].deleteCell(rows[i].cells.length - 1);
            }
            syncTableHTML();
        }
    };

    window.clearTable = function() {
        if (confirm('Are you sure you want to clear the table?')) {
            const table = document.getElementById('results-table-builder');
            const tbody = table.querySelector('tbody');
            tbody.innerHTML = '<tr><td contenteditable="true"></td><td contenteditable="true"></td><td contenteditable="true"></td></tr>';
            const thead = table.querySelector('thead');
            thead.innerHTML = '<tr><th contenteditable="true">Column 1</th><th contenteditable="true">Column 2</th><th contenteditable="true">Column 3</th></tr>';
            syncTableHTML();
        }
    };

    window.toggleTableBuilder = function() {
        const wrapper = document.getElementById('table-builder-wrapper');
        const isHidden = wrapper.style.display === 'none' || wrapper.style.display === '';
        wrapper.style.display = isHidden ? 'block' : 'none';
        const openBtn = document.getElementById('open-table-builder-btn');
        if (openBtn) {
            openBtn.textContent = isHidden ? 'Close Table Builder' : 'Open Table Builder';
        }
    };

    function syncTableHTML() {
        const table = document.getElementById('results-table-builder');
        const textarea = document.getElementById('table-html-output');
        textarea.value = table.outerHTML;
    }

    // Load existing table HTML if available
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

    // Sync table HTML on any content change using event delegation
    document.querySelector('.table-preview').addEventListener('input', syncTableHTML);
    
    // Attach event listener to open table builder button
    const openTableBtn = document.getElementById('open-table-builder-btn');
    if (openTableBtn) {
        openTableBtn.addEventListener('click', window.toggleTableBuilder);
    }
    
    // Real-time lab availability checker
    const labSelect = document.querySelector('select[name="lab_id"]');
    const dateInput = document.querySelector('input[name="scheduled_date"]');
    const startTimeInput = document.querySelector('input[name="start_time"]');
    const endTimeInput = document.querySelector('input[name="end_time"]');
    
    function checkAvailability() {
        if (!labSelect.value || !dateInput.value || !startTimeInput.value || !endTimeInput.value) {
            return;
        }
        
        const url = new URL('<?= APP_URL ?>/practicals/checkAvailability', window.location.origin);
        url.searchParams.append('lab_id', labSelect.value);
        url.searchParams.append('date', dateInput.value);
        url.searchParams.append('start_time', startTimeInput.value);
        url.searchParams.append('end_time', endTimeInput.value);
        
        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.available) {
                    removeAvailabilityMessage();
                } else {
                    showAvailabilityMessage(data.slots);
                }
            })
            .catch(error => {
                console.error('Error checking availability:', error);
            });
    }
    
    function showAvailabilityMessage(slots) {
        let messageDiv = document.getElementById('availability-message');
        if (!messageDiv) {
            messageDiv = document.createElement('div');
            messageDiv.id = 'availability-message';
            messageDiv.style.cssText = 'background: #fff3cd; border: 1px solid #ffc107; padding: 12px 20px; border-radius: 4px; margin-bottom: 20px; color: #856404;';
            document.querySelector('.panel').insertBefore(messageDiv, document.querySelector('form'));
        }
        
        const freeSlots = slots.filter(s => s.available);
        if (freeSlots.length > 0) {
            const slotList = freeSlots.slice(0, 5).map(s => `${s.start.substring(0,5)}-${s.end.substring(0,5)}`).join(', ');
            messageDiv.innerHTML = `<strong>⚠️ Lab Not Available</strong><br>Available slots: ${slotList}`;
        } else {
            messageDiv.innerHTML = '<strong>⚠️ Lab Not Available</strong><br>No slots available on this date.';
        }
    }
    
    function removeAvailabilityMessage() {
        const messageDiv = document.getElementById('availability-message');
        if (messageDiv) {
            messageDiv.remove();
        }
    }
    
    // Add event listeners for real-time checking
    if (labSelect) labSelect.addEventListener('change', checkAvailability);
    if (dateInput) dateInput.addEventListener('change', checkAvailability);
    if (startTimeInput) startTimeInput.addEventListener('change', checkAvailability);
    if (endTimeInput) endTimeInput.addEventListener('change', checkAvailability);
});

// Procedure step management functions
let procedureStepCount = 1;

function addProcedureStep() {
    const container = document.getElementById('procedure-steps-container');
    const stepDiv = document.createElement('div');
    stepDiv.className = 'procedure-step';
    stepDiv.innerHTML = `
        <div class="form-group">
            <label class="form-label">Step ${procedureStepCount + 1}</label>
            <textarea name="procedure_steps[${procedureStepCount}]" class="form-control" rows="2" placeholder="Describe this step..."></textarea>
        </div>
        <button type="button" onclick="removeProcedureStep(this)" class="btn btn-danger btn-sm">Remove</button>
    `;
    container.appendChild(stepDiv);
    procedureStepCount++;
}

function removeProcedureStep(button) {
    button.parentElement.remove();
    updateProcedureStepNumbers();
}

function updateProcedureStepNumbers() {
    const steps = document.querySelectorAll('#procedure-steps-container .procedure-step');
    steps.forEach((step, index) => {
        const label = step.querySelector('.form-label');
        if (label) {
            label.textContent = `Step ${index + 1}`;
        }
        const textarea = step.querySelector('textarea');
        if (textarea) {
            textarea.name = `procedure_steps[${index}]`;
        }
    });
    procedureStepCount = steps.length;
}

// Observations column management functions
let observationsColumnCount = 1;

function addObservationsColumn() {
    const container = document.getElementById('observations-columns-container');
    const columnDiv = document.createElement('div');
    columnDiv.className = 'observations-column';
    columnDiv.innerHTML = `
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Column Name</label>
                <input type="text" name="observations_columns[${observationsColumnCount}][name]" class="form-control" placeholder="e.g., Trial">
            </div>
            
            <div class="form-group">
                <label class="form-label">Column Type</label>
                <select name="observations_columns[${observationsColumnCount}][type]" class="form-control">
                    <option value="text">Text</option>
                    <option value="number">Number</option>
                    <option value="calculation">Calculation</option>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label">Formula (for calculation columns)</label>
            <input type="text" name="observations_columns[${observationsColumnCount}][formula]" class="form-control" placeholder="e.g., col1 + col2">
        </div>
        
        <button type="button" onclick="removeObservationsColumn(this)" class="btn btn-danger btn-sm">Remove</button>
    `;
    container.appendChild(columnDiv);
    observationsColumnCount++;
}

function removeObservationsColumn(button) {
    button.parentElement.remove();
    updateObservationsColumnNames();
}

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
.form-section {
    margin-bottom: 32px;
    padding-bottom: 32px;
    border-bottom: 1px solid var(--border-subtle);
}

.form-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.section-title {
    font-size: 16px;
    font-weight: 700;
    color: var(--text);
    margin-bottom: 20px;
    letter-spacing: -0.1px;
}

.section-desc {
    font-size: 13px;
    color: var(--text-2);
    margin-bottom: 16px;
    margin-top: -12px;
}

.form-group {
    margin-bottom: 18px;
}

.form-label {
    display: block;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.2px;
    color: var(--text-2);
    margin-bottom: 6px;
    text-transform: uppercase;
}

.form-control {
    width: 100%;
    padding: 10px 14px;
    border-radius: var(--radius-md);
    background: var(--surface);
    border: 1px solid var(--border);
    color: var(--text);
    font-size: 14px;
    transition: var(--transition-fast);
    font-family: inherit;
}

.form-control:focus {
    outline: none;
    border-color: var(--color-primary);
    box-shadow: var(--shadow-focus);
    background: var(--surface);
}

.form-control::placeholder {
    color: var(--text-4);
}

.form-actions {
    padding-top: 8px;
}

.modern-form textarea.form-control {
    resize: vertical;
    min-height: 80px;
}

.quill-editor {
    min-height: 200px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
}

.ql-toolbar {
    border-top-left-radius: var(--radius-md);
    border-top-right-radius: var(--radius-md);
    background: var(--surface);
}

.ql-container {
    border-bottom-left-radius: var(--radius-md);
    border-bottom-right-radius: var(--radius-md);
    background: var(--surface);
    min-height: 200px;
}

.table-builder-container {
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    background: var(--surface);
    padding: 1rem;
}

.table-toolbar {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}

.table-preview {
    overflow-x: auto;
    margin-bottom: 1rem;
}

.table-builder {
    width: 100%;
    border-collapse: collapse;
}

.table-builder th,
.table-builder td {
    border: 1px solid var(--border);
    padding: 0.75rem;
    min-width: 100px;
    text-align: center;
}

.table-builder th {
    background: var(--surface-darker);
    font-weight: 600;
}

.table-builder td:focus,
.table-builder th:focus {
    outline: 2px solid var(--primary);
    outline-offset: -2px;
}
</style>
