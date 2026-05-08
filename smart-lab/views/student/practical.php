<div class="card">
    <div class="card-header">
        <div>
            <div class="card-title"><?= htmlspecialchars($schedule['experiment_title']) ?></div>
            <div class="card-sub"><?= htmlspecialchars($schedule['unit_code']) ?> - <?= htmlspecialchars($schedule['unit_name']) ?></div>
        </div>
        <div class="flex gap-2">
            <button onclick="saveProgress()" class="btn btn-outline">Save Draft</button>
            <button onclick="submitPractical()" class="btn btn-primary">Submit Report</button>
        </div>
    </div>
    
    <div class="tabs">
        <div class="tab-nav">
            <button class="tab-btn active" onclick="showTab('objective')">Objective</button>
            <button class="tab-btn" onclick="showTab('theory')">Theory</button>
            <button class="tab-btn" onclick="showTab('apparatus')">Apparatus</button>
            <button class="tab-btn" onclick="showTab('procedure')">Procedure</button>
            <button class="tab-btn" onclick="showTab('results')">Results</button>
            <button class="tab-btn" onclick="showTab('analysis')">Analysis</button>
            <button class="tab-btn" onclick="showTab('discussion')">Discussion</button>
            <button class="tab-btn" onclick="showTab('conclusion')">Conclusion</button>
            <button class="tab-btn" onclick="showTab('references')">References</button>
        </div>
        
        <div class="tab-content">
            <!-- Objective Tab -->
            <div id="objective-tab" class="tab-pane active">
                <div class="section-content">
                    <h3>Objective</h3>
                    <div class="provided-content">
                        <?php 
                        $objective_content = '';
                        foreach ($sections as $section) {
                            if ($section['section_type'] === 'objective') {
                                $objective_content = $section['content'];
                                break;
                            }
                        }
                        echo nl2br(htmlspecialchars($objective_content));
                        ?>
                    </div>
                </div>
            </div>
            
            <!-- Theory Tab -->
            <div id="theory-tab" class="tab-pane">
                <div class="section-content">
                    <h3>Theory</h3>
                    <div class="provided-content">
                        <?php 
                        $theory_content = '';
                        foreach ($sections as $section) {
                            if ($section['section_type'] === 'theory') {
                                $theory_content = $section['content'];
                                break;
                            }
                        }
                        echo nl2br(htmlspecialchars($theory_content));
                        ?>
                    </div>
                </div>
            </div>
            
            <!-- Apparatus Tab -->
            <div id="apparatus-tab" class="tab-pane">
                <div class="section-content">
                    <h3>Apparatus and Materials</h3>
                    <div class="apparatus-list">
                        <?php foreach ($apparatus as $item): ?>
                            <div class="apparatus-item">
                                <strong><?= htmlspecialchars($item['item_name']) ?></strong>
                                <?php if ($item['quantity']): ?>
                                    <span class="quantity">(<?= htmlspecialchars($item['quantity']) ?>)</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Procedure Tab -->
            <div id="procedure-tab" class="tab-pane">
                <div class="section-content">
                    <h3>Procedure</h3>
                    <div class="procedure-steps">
                        <?php foreach ($procedure_steps as $index => $step): ?>
                            <div class="procedure-step">
                                <h4>Step <?= $index + 1 ?></h4>
                                <p><?= nl2br(htmlspecialchars($step['step_description'])) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Results Tab -->
            <div id="results-tab" class="tab-pane">
                <div class="section-content">
                    <h3>Results</h3>
                    <div class="results-table-container">
                        <table class="results-table" id="resultsTable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <?php foreach ($results_structure as $column): ?>
                                        <th><?= htmlspecialchars($column['column_name']) ?></th>
                                    <?php endforeach; ?>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="resultsTableBody">
                                <!-- Results will be populated by JavaScript -->
                            </tbody>
                        </table>
                        
                        <div class="table-actions">
                            <button onclick="addResultsRow()" class="btn btn-outline btn-sm">Add Row</button>
                            <button onclick="clearResults()" class="btn btn-danger btn-sm">Clear All</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Analysis Tab -->
            <div id="analysis-tab" class="tab-pane">
                <div class="section-content">
                    <h3>Analysis</h3>
                    <textarea id="analysis-content" class="form-textarea tinymce-editor" rows="10" placeholder="Enter your analysis here..."><?= $submission_data['analysis'] ?? '' ?></textarea>
                    <button onclick="saveSection('analysis')" class="btn btn-outline btn-sm">Save Analysis</button>
                </div>
            </div>
            
            <!-- Discussion Tab -->
            <div id="discussion-tab" class="tab-pane">
                <div class="section-content">
                    <h3>Discussion</h3>
                    <textarea id="discussion-content" class="form-textarea tinymce-editor" rows="10" placeholder="Enter your discussion here..."><?= $submission_data['discussion'] ?? '' ?></textarea>
                    <button onclick="saveSection('discussion')" class="btn btn-outline btn-sm">Save Discussion</button>
                </div>
            </div>
            
            <!-- Conclusion Tab -->
            <div id="conclusion-tab" class="tab-pane">
                <div class="section-content">
                    <h3>Conclusion</h3>
                    <textarea id="conclusion-content" class="form-textarea tinymce-editor" rows="10" placeholder="Enter your conclusion here..."><?= $submission_data['conclusion'] ?? '' ?></textarea>
                    <button onclick="saveSection('conclusion')" class="btn btn-outline btn-sm">Save Conclusion</button>
                </div>
            </div>
            
            <!-- References Tab -->
            <div id="references-tab" class="tab-pane">
                <div class="section-content">
                    <h3>References</h3>
                    <textarea id="references-content" class="form-textarea tinymce-editor" rows="10" placeholder="Enter references here..."><?= $submission_data['references'] ?? '' ?></textarea>
                    <button onclick="saveSection('references')" class="btn btn-outline btn-sm">Save References</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Submission Modal -->
<div id="submitModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Submit Practical Report</h3>
            <button onclick="closeSubmitModal()" class="btn-close">&times;</button>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to submit your practical report? Once submitted, you cannot make further changes.</p>
        </div>
        <div class="modal-footer">
            <button onclick="closeSubmitModal()" class="btn btn-outline">Cancel</button>
            <button onclick="confirmSubmit()" class="btn btn-primary">Submit Report</button>
        </div>
    </div>
</div>

<style>
.tabs {
    margin-top: 2rem;
}

.tab-nav {
    display: flex;
    border-bottom: 1px solid #e5e7eb;
    overflow-x: auto;
}

.tab-btn {
    padding: 1rem 1.5rem;
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.3s ease;
}

.tab-btn:hover {
    background: #f9fafb;
}

.tab-btn.active {
    border-bottom-color: #3b82f6;
    color: #3b82f6;
    font-weight: 600;
}

.tab-content {
    padding: 2rem 0;
}

.tab-pane {
    display: none;
}

.tab-pane.active {
    display: block;
}

.section-content h3 {
    margin-bottom: 1rem;
    color: #1f2937;
}

.provided-content {
    background: #f9fafb;
    padding: 1rem;
    border-radius: 0.5rem;
    margin-bottom: 1rem;
    line-height: 1.6;
}

.apparatus-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1rem;
}

.apparatus-item {
    background: #f9fafb;
    padding: 1rem;
    border-radius: 0.5rem;
    border-left: 4px solid #3b82f6;
}

.apparatus-item .quantity {
    color: #6b7280;
    font-size: 0.875rem;
}

.procedure-steps {
    space-y: 1.5rem;
}

.procedure-step {
    background: #f9fafb;
    padding: 1rem;
    border-radius: 0.5rem;
    margin-bottom: 1rem;
}

.procedure-step h4 {
    color: #3b82f6;
    margin-bottom: 0.5rem;
}

.results-table-container {
    overflow-x: auto;
}

.results-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 1rem;
}

.results-table th,
.results-table td {
    padding: 0.75rem;
    border: 1px solid #e5e7eb;
    text-align: center;
}

.results-table th {
    background: #f9fafb;
    font-weight: 600;
}

.results-table input {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid #d1d5db;
    border-radius: 0.25rem;
}

.table-actions {
    margin-top: 1rem;
    display: flex;
    gap: 0.5rem;
}

.form-textarea {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    font-family: inherit;
    resize: vertical;
}

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
}

.modal-content {
    background-color: white;
    margin: 15% auto;
    padding: 0;
    border-radius: 0.5rem;
    width: 90%;
    max-width: 500px;
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid #e5e7eb;
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
}

.btn-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #6b7280;
}

.tinymce-editor {
    min-height: 200px;
}
</style>

<!-- TinyMCE Rich Text Editor -->
<script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
tinymce.init({
    selector: '.tinymce-editor',
    height: 250,
    menubar: true,
    plugins: [
        'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
        'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
        'insertdatetime', 'media', 'table', 'help', 'wordcount'
    ],
    toolbar: 'undo redo | blocks | ' +
        'bold italic underline strikethrough | alignleft aligncenter ' +
        'alignright alignjustify | bullist numlist outdent indent | ' +
        'removeformat | table | image | code',
    table_default_attributes: {
        'border': '1',
        'class': 'table table-bordered'
    },
    table_default_styles: {
        'border-collapse': 'collapse',
        'width': '100%'
    },
    images_upload_url: '<?= APP_URL ?>/public/upload.php',
    images_upload_handler: function (blobInfo, success, failure) {
        var xhr, formData;
        xhr = new XMLHttpRequest();
        xhr.withCredentials = false;
        xhr.open('POST', '<?= APP_URL ?>/public/upload.php');
        xhr.onload = function() {
            var json;
            if (xhr.status != 200) {
                failure('HTTP Error: ' + xhr.status);
                return;
            }
            json = JSON.parse(xhr.responseText);
            if (!json || typeof json.location != 'string') {
                failure('Invalid JSON: ' + xhr.responseText);
                return;
            }
            success(json.location);
        };
        formData = new FormData();
        formData.append('file', blobInfo.blob(), blobInfo.filename());
        xhr.send(formData);
    },
    content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; font-size: 14px; }',
    setup: function(editor) {
        editor.on('init', function() {
            // Get content from textarea and set it in editor
            const textarea = editor.getElement();
            if (textarea.value) {
                editor.setContent(textarea.value);
            }
        });
        editor.on('change', function() {
            // Update textarea content when editor changes
            const textarea = editor.getElement();
            textarea.value = editor.getContent();
        });
    }
});
</script>

<script>
const scheduleId = <?= $schedule['id'] ?>;
const resultsStructure = <?= json_encode($results_structure) ?>;
const existingResults = <?= json_encode($results_data) ?>;
let resultsData = existingResults || {};

// Initialize results table
document.addEventListener('DOMContentLoaded', function() {
    initializeResultsTable();
});

function showTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-pane').forEach(pane => {
        pane.classList.remove('active');
    });
    
    // Remove active class from all buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById(tabName + '-tab').classList.add('active');
    event.target.classList.add('active');
}

function initializeResultsTable() {
    const tbody = document.getElementById('resultsTableBody');
    tbody.innerHTML = '';
    
    // Add existing rows or start with one empty row
    const rowCount = Object.keys(resultsData).length || 1;
    
    for (let i = 0; i < rowCount; i++) {
        addResultsRow(i);
    }
}

function addResultsRow(rowIndex = null) {
    const tbody = document.getElementById('resultsTableBody');
    const actualRowIndex = rowIndex !== null ? rowIndex : tbody.children.length;
    
    const row = document.createElement('tr');
    row.innerHTML = `<td>${actualRowIndex + 1}</td>`;
    
    resultsStructure.forEach(column => {
        const value = resultsData[actualRowIndex] && resultsData[actualRowIndex][column.column_name] || '';
        const inputType = column.column_type === 'number' ? 'number' : 'text';
        
        row.innerHTML += `
            <td>
                <input type="${inputType}" 
                       name="result_${actualRowIndex}_${column.column_name}" 
                       value="${value}" 
                       placeholder="${column.column_name}"
                       onchange="updateResultsData(${actualRowIndex}, '${column.column_name}', this.value)">
            </td>
        `;
    });
    
    row.innerHTML += `
        <td>
            <button onclick="removeResultsRow(this)" class="btn btn-danger btn-sm">Remove</button>
        </td>
    `;
    
    tbody.appendChild(row);
}

function removeResultsRow(button) {
    const row = button.closest('tr');
    const rowIndex = Array.from(row.parentNode.children).indexOf(row);
    
    // Remove from resultsData
    delete resultsData[rowIndex];
    
    // Reindex remaining data
    const newResultsData = {};
    Object.keys(resultsData).forEach(key => {
        const newKey = key > rowIndex ? key - 1 : key;
        newResultsData[newKey] = resultsData[key];
    });
    resultsData = newResultsData;
    
    // Remove row and reinitialize
    initializeResultsTable();
}

function updateResultsData(rowIndex, columnName, value) {
    if (!resultsData[rowIndex]) {
        resultsData[rowIndex] = {};
    }
    resultsData[rowIndex][columnName] = value;
}

function clearResults() {
    if (confirm('Are you sure you want to clear all results?')) {
        resultsData = {};
        initializeResultsTable();
    }
}

function saveSection(sectionType) {
    let content;
    const textarea = document.getElementById(sectionType + '-content');
    
    // Get content from TinyMCE if available, otherwise use textarea
    if (tinymce.get(sectionType + '-content')) {
        content = tinymce.get(sectionType + '-content').getContent();
    } else {
        content = textarea.value;
    }
    
    fetch('<?= APP_URL ?>/student/practical/save', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `schedule_id=${scheduleId}&section_type=${sectionType}&content=${encodeURIComponent(content)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Section saved successfully', 'success');
        } else {
            showNotification('Failed to save section: ' + data.error, 'error');
        }
    })
    .catch(error => {
        showNotification('Error saving section', 'error');
    });
}

function saveProgress() {
    // Save all sections
    const sections = ['analysis', 'discussion', 'conclusion', 'references'];
    sections.forEach(section => {
        let content;
        const textarea = document.getElementById(section + '-content');
        
        // Get content from TinyMCE if available, otherwise use textarea
        if (tinymce.get(section + '-content')) {
            content = tinymce.get(section + '-content').getContent();
        } else {
            content = textarea.value;
        }
        
        if (content.trim()) {
            saveSection(section);
        }
    });
    
    // Save results
    saveResults();
}

function saveResults() {
    fetch('<?= APP_URL ?>/student/practical/save-results', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `schedule_id=${scheduleId}&results=${encodeURIComponent(JSON.stringify(resultsData))}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Results saved successfully', 'success');
        } else {
            showNotification('Failed to save results: ' + data.error, 'error');
        }
    })
    .catch(error => {
        showNotification('Error saving results', 'error');
    });
}

function submitPractical() {
    // Save all data first
    saveProgress();
    
    // Show confirmation modal
    document.getElementById('submitModal').style.display = 'block';
}

function closeSubmitModal() {
    document.getElementById('submitModal').style.display = 'none';
}

function confirmSubmit() {
    fetch('<?= APP_URL ?>/student/practical/submit/<?= $schedule['id'] ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Practical submitted successfully!', 'success');
            setTimeout(() => {
                window.location.href = '<?= APP_URL ?>/student/dashboard';
            }, 2000);
        } else {
            showNotification('Failed to submit: ' + data.error, 'error');
        }
        closeSubmitModal();
    })
    .catch(error => {
        showNotification('Error submitting practical', 'error');
        closeSubmitModal();
    });
}

function showNotification(message, type) {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 1rem;
        border-radius: 0.5rem;
        color: white;
        font-weight: 500;
        z-index: 10000;
        animation: slideIn 0.3s ease;
    `;
    
    if (type === 'success') {
        notification.style.backgroundColor = '#10b981';
    } else if (type === 'error') {
        notification.style.backgroundColor = '#ef4444';
    }
    
    document.body.appendChild(notification);
    
    // Remove after 3 seconds
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// Auto-save every 30 seconds
setInterval(saveProgress, 30000);
</script>
