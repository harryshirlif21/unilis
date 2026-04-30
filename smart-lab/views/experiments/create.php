<div class="card">
    <div class="card-header">
        <div>
            <div class="card-title">Create New Experiment</div>
            <div class="card-sub">Design a structured laboratory experiment</div>
        </div>
        <a href="<?= APP_URL ?>/experiments" class="btn btn-outline">Back to Experiments</a>
    </div>
    
    <form method="POST" class="form-container">
        <div class="form-section">
            <h3>Basic Information</h3>
            
            <div class="form-group">
                <label class="form-label">Experiment Title *</label>
                <input type="text" name="title" class="form-input" placeholder="Enter experiment title" required>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Unit Code *</label>
                    <input type="text" name="unit_code" class="form-input" placeholder="e.g., CSC 2101" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Unit Name *</label>
                    <input type="text" name="unit_name" class="form-input" placeholder="e.g., Data Structures" required>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Group</label>
                <input type="text" name="group" class="form-input" placeholder="e.g., Computer Science">
            </div>
        </div>
        
        <div class="form-section">
            <h3>Experiment Sections</h3>
            <p class="text-sm text-gray-600 mb-4">Add structured sections for your lab manual</p>
            
            <div id="sections-container">
                <div class="section-item">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Section Type</label>
                            <select name="sections[0][type]" class="form-select">
                                <option value="objective">Objective</option>
                                <option value="theory">Theory</option>
                                <option value="apparatus">Apparatus</option>
                                <option value="diagram">Diagram</option>
                                <option value="procedure">Procedure</option>
                                <option value="results_structure">Results Structure</option>
                                <option value="analysis">Analysis</option>
                                <option value="discussion">Discussion</option>
                                <option value="conclusion">Conclusion</option>
                                <option value="references">References</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Section Title (Optional)</label>
                            <input type="text" name="sections[0][title]" class="form-input" placeholder="Section title">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Content</label>
                        <textarea name="sections[0][content]" rows="4" class="form-input" placeholder="Enter section content"></textarea>
                    </div>
                    
                    <button type="button" onclick="removeSection(this)" class="btn btn-danger btn-sm">Remove Section</button>
                </div>
            </div>
            
            <button type="button" onclick="addSection()" class="btn btn-outline">Add Section</button>
        </div>
        
        <div class="form-section">
            <h3>Apparatus List</h3>
            <p class="text-sm text-gray-600 mb-4">List all equipment and materials needed</p>
            
            <div id="apparatus-container">
                <div class="apparatus-item">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Item Name</label>
                            <input type="text" name="apparatus[0][name]" class="form-input" placeholder="e.g., Beaker, 250ml">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Quantity</label>
                            <input type="text" name="apparatus[0][quantity]" class="form-input" placeholder="e.g., 2 pieces">
                        </div>
                    </div>
                    
                    <button type="button" onclick="removeApparatus(this)" class="btn btn-danger btn-sm">Remove</button>
                </div>
            </div>
            
            <button type="button" onclick="addApparatus()" class="btn btn-outline">Add Apparatus</button>
        </div>
        
        <div class="form-section">
            <h3>Procedure Steps</h3>
            <p class="text-sm text-gray-600 mb-4">Step-by-step instructions</p>
            
            <div id="procedure-container">
                <div class="procedure-item">
                    <div class="form-group">
                        <label class="form-label">Step 1</label>
                        <textarea name="procedure_steps[0][description]" rows="2" class="form-input" placeholder="Describe this step"></textarea>
                    </div>
                    
                    <button type="button" onclick="removeProcedureStep(this)" class="btn btn-danger btn-sm">Remove Step</button>
                </div>
            </div>
            
            <button type="button" onclick="addProcedureStep()" class="btn btn-outline">Add Step</button>
        </div>
        
        <div class="form-section">
            <h3>Results Table Structure</h3>
            <p class="text-sm text-gray-600 mb-4">Define columns for the results table</p>
            
            <div id="results-container">
                <div class="results-item">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Column Name</label>
                            <input type="text" name="results_columns[0][name]" class="form-input" placeholder="e.g., Trial 1">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Column Type</label>
                            <select name="results_columns[0][type]" class="form-select">
                                <option value="text">Text</option>
                                <option value="number">Number</option>
                                <option value="calculation">Calculation</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Formula (for calculation columns)</label>
                        <input type="text" name="results_columns[0][formula]" class="form-input" placeholder="e.g., col1 + col2">
                    </div>
                    
                    <button type="button" onclick="removeResultsColumn(this)" class="btn btn-danger btn-sm">Remove Column</button>
                </div>
            </div>
            
            <button type="button" onclick="addResultsColumn()" class="btn btn-outline">Add Column</button>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Create Experiment</button>
            <a href="<?= APP_URL ?>/experiments" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

<script>
let sectionCount = 1;
let apparatusCount = 1;
let procedureCount = 1;
let resultsCount = 1;

function addSection() {
    const container = document.getElementById('sections-container');
    const sectionDiv = document.createElement('div');
    sectionDiv.className = 'section-item';
    sectionDiv.innerHTML = `
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Section Type</label>
                <select name="sections[${sectionCount}][type]" class="form-select">
                    <option value="objective">Objective</option>
                    <option value="theory">Theory</option>
                    <option value="apparatus">Apparatus</option>
                    <option value="diagram">Diagram</option>
                    <option value="procedure">Procedure</option>
                    <option value="results_structure">Results Structure</option>
                    <option value="analysis">Analysis</option>
                    <option value="discussion">Discussion</option>
                    <option value="conclusion">Conclusion</option>
                    <option value="references">References</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Section Title (Optional)</label>
                <input type="text" name="sections[${sectionCount}][title]" class="form-input" placeholder="Section title">
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label">Content</label>
            <textarea name="sections[${sectionCount}][content]" rows="4" class="form-input" placeholder="Enter section content"></textarea>
        </div>
        
        <button type="button" onclick="removeSection(this)" class="btn btn-danger btn-sm">Remove Section</button>
    `;
    container.appendChild(sectionDiv);
    sectionCount++;
}

function removeSection(button) {
    button.parentElement.remove();
}

function addApparatus() {
    const container = document.getElementById('apparatus-container');
    const apparatusDiv = document.createElement('div');
    apparatusDiv.className = 'apparatus-item';
    apparatusDiv.innerHTML = `
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Item Name</label>
                <input type="text" name="apparatus[${apparatusCount}][name]" class="form-input" placeholder="e.g., Beaker, 250ml">
            </div>
            
            <div class="form-group">
                <label class="form-label">Quantity</label>
                <input type="text" name="apparatus[${apparatusCount}][quantity]" class="form-input" placeholder="e.g., 2 pieces">
            </div>
        </div>
        
        <button type="button" onclick="removeApparatus(this)" class="btn btn-danger btn-sm">Remove</button>
    `;
    container.appendChild(apparatusDiv);
    apparatusCount++;
}

function removeApparatus(button) {
    button.parentElement.remove();
}

function addProcedureStep() {
    const container = document.getElementById('procedure-container');
    const stepDiv = document.createElement('div');
    stepDiv.className = 'procedure-item';
    stepDiv.innerHTML = `
        <div class="form-group">
            <label class="form-label">Step ${procedureCount + 1}</label>
            <textarea name="procedure_steps[${procedureCount}][description]" rows="2" class="form-input" placeholder="Describe this step"></textarea>
        </div>
        
        <button type="button" onclick="removeProcedureStep(this)" class="btn btn-danger btn-sm">Remove Step</button>
    `;
    container.appendChild(stepDiv);
    procedureCount++;
}

function removeProcedureStep(button) {
    button.parentElement.remove();
    updateProcedureNumbers();
}

function updateProcedureNumbers() {
    const steps = document.querySelectorAll('.procedure-item');
    steps.forEach((step, index) => {
        const label = step.querySelector('label');
        if (label) {
            label.textContent = `Step ${index + 1}`;
        }
    });
}

function addResultsColumn() {
    const container = document.getElementById('results-container');
    const columnDiv = document.createElement('div');
    columnDiv.className = 'results-item';
    columnDiv.innerHTML = `
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Column Name</label>
                <input type="text" name="results_columns[${resultsCount}][name]" class="form-input" placeholder="e.g., Trial 1">
            </div>
            
            <div class="form-group">
                <label class="form-label">Column Type</label>
                <select name="results_columns[${resultsCount}][type]" class="form-select">
                    <option value="text">Text</option>
                    <option value="number">Number</option>
                    <option value="calculation">Calculation</option>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label">Formula (for calculation columns)</label>
            <input type="text" name="results_columns[${resultsCount}][formula]" class="form-input" placeholder="e.g., col1 + col2">
        </div>
        
        <button type="button" onclick="removeResultsColumn(this)" class="btn btn-danger btn-sm">Remove Column</button>
    `;
    container.appendChild(columnDiv);
    resultsCount++;
}

function removeResultsColumn(button) {
    button.parentElement.remove();
}
</script>
