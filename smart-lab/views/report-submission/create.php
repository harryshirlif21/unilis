<div class="card">
    <div class="card-header">
        <div class="card-header-content">
            <span>📝 Submit Report</span>
            <a href="<?= APP_URL ?>/report-submission" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Reports
            </a>
        </div>
    </div>
    
    <div class="card-body">
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="<?= APP_URL ?>/report-submission/create" class="report-form">
            <!-- Practical Selection -->
            <div class="form-section">
                <h4><i class="fas fa-flask"></i> Practical Selection</h4>
                
                <div class="form-group">
                    <label for="practical_id">Select Practical *</label>
                    <select name="practical_id" id="practical_id" class="form-control" required onchange="updateDeadlineInfo()">
                        <option value="">Choose a practical...</option>
                        <?php foreach ($availablePracticals as $practical): ?>
                            <?php if ($practical['can_submit']): ?>
                                <option value="<?= $practical['id'] ?>" 
                                        data-deadline="<?= $practical['deadline']['deadline_date'] ?? '' ?>"
                                        data-extended="<?= $practical['deadline']['extended_until'] ?? '' ?>"
                                        data-practical="<?= htmlspecialchars($practical['title']) ?>"
                                        data-lab="<?= htmlspecialchars($practical['lab_name']) ?>"
                                        data-completed="<?= date('M j, Y', strtotime($practical['session_date'])) ?>">
                                    <?= htmlspecialchars($practical['title']) ?> - 
                                    <?= htmlspecialchars($practical['lab_name']) ?>
                                    (<?= $practical['deadline']['days_remaining'] ?? 'N/A' ?> days left)
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-help">Select the practical you want to submit a report for</small>
                </div>
                
                <!-- Deadline Information -->
                <div id="deadline-info" class="deadline-info" style="display: none;">
                    <div class="deadline-card">
                        <h5><i class="fas fa-hourglass-half"></i> Submission Deadline</h5>
                        <div id="deadline-details"></div>
                    </div>
                </div>
            </div>
            
            <!-- Report Information -->
            <div class="form-section">
                <h4><i class="fas fa-info-circle"></i> Report Information</h4>
                
                <div class="form-group">
                    <label for="title">Report Title *</label>
                    <input type="text" name="title" id="title" class="form-control" 
                           placeholder="Enter a descriptive title for your report..." required>
                    <small class="form-help">Choose a clear title that describes your report content</small>
                </div>
            </div>
            
            <!-- Report Content -->
            <div class="form-section">
                <h4><i class="fas fa-file-alt"></i> Report Content</h4>
                
                <div class="form-group">
                    <label for="content">Main Content *</label>
                    <textarea name="content" id="content" class="form-control" rows="8" required
                              placeholder="Enter the main content of your report..."></textarea>
                    <small class="form-help">Provide detailed information about your practical work, observations, and findings</small>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="summary">Executive Summary</label>
                        <textarea name="summary" id="summary" class="form-control" rows="4"
                                  placeholder="Brief summary of your report..."></textarea>
                        <small class="form-help">A concise overview of your report (2-3 sentences)</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="methodology">Methodology</label>
                        <textarea name="methodology" id="methodology" class="form-control" rows="4"
                                  placeholder="Describe your experimental methods..."></textarea>
                        <small class="form-help">Explain the procedures and methods you used</small>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="results">Results & Findings</label>
                    <textarea name="results" id="results" class="form-control" rows="6"
                              placeholder="Present your results and findings..."></textarea>
                    <small class="form-help">Include data, observations, and key findings</small>
                </div>
                
                <div class="form-group">
                    <label for="conclusions">Conclusions & Analysis</label>
                    <textarea name="conclusions" id="conclusions" class="form-control" rows="6"
                              placeholder="Analyze your results and draw conclusions..."></textarea>
                    <small class="form-help">Interpret your findings and provide conclusions</small>
                </div>
                
                <div class="form-group">
                    <label for="references">References</label>
                    <textarea name="references" id="references" class="form-control" rows="4"
                              placeholder="List any references or citations..."></textarea>
                    <small class="form-help">Include any sources, references, or citations</small>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Submit Report
                </button>
                <button type="reset" class="btn btn-secondary">
                    <i class="fas fa-undo"></i> Reset Form
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function updateDeadlineInfo() {
    const select = document.getElementById('practical_id');
    const deadlineInfo = document.getElementById('deadline-info');
    const deadlineDetails = document.getElementById('deadline-details');
    
    if (select.value) {
        const option = select.options[select.selectedIndex];
        const deadline = option.dataset.deadline;
        const extended = option.dataset.extended;
        const practical = option.dataset.practical;
        const lab = option.dataset.lab;
        const completed = option.dataset.completed;
        
        let deadlineHtml = `
            <div class="deadline-item">
                <strong>Practical:</strong> ${practical}
            </div>
            <div class="deadline-item">
                <strong>Laboratory:</strong> ${lab}
            </div>
            <div class="deadline-item">
                <strong>Completed:</strong> ${completed}
            </div>
        `;
        
        if (deadline) {
            const deadlineDate = new Date(deadline);
            const now = new Date();
            const daysLeft = Math.ceil((deadlineDate - now) / (1000 * 60 * 60 * 24));
            
            let statusClass = 'normal';
            let statusText = 'On Time';
            
            if (daysLeft < 0) {
                statusClass = 'expired';
                statusText = 'Expired';
            } else if (daysLeft <= 3) {
                statusClass = 'urgent';
                statusText = 'Urgent';
            } else if (daysLeft <= 7) {
                statusClass = 'approaching';
                statusText = 'Approaching';
            }
            
            deadlineHtml += `
                <div class="deadline-item">
                    <strong>Deadline:</strong> ${deadlineDate.toLocaleDateString()} ${deadlineDate.toLocaleTimeString()}
                </div>
                <div class="deadline-status ${statusClass}">
                    <strong>Status:</strong> ${statusText}
                </div>
            `;
            
            if (extended) {
                const extendedDate = new Date(extended);
                deadlineHtml += `
                    <div class="deadline-item">
                        <strong>Extended to:</strong> ${extendedDate.toLocaleDateString()} ${extendedDate.toLocaleTimeString()}
                    </div>
                `;
            }
        }
        
        deadlineDetails.innerHTML = deadlineHtml;
        deadlineInfo.style.display = 'block';
    } else {
        deadlineInfo.style.display = 'none';
    }
}
</script>

<style>
.report-form {
    max-width: 800px;
}

.form-section {
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--border);
}

.form-section:last-of-type {
    border-bottom: none;
    margin-bottom: 0;
}

.form-section h4 {
    margin-bottom: 1rem;
    color: var(--primary);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1.1rem;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.form-control {
    padding: 0.75rem;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 1rem;
    transition: border-color 0.3s;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.form-help {
    font-size: 0.875rem;
    color: var(--gray);
    line-height: 1.4;
}

.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 1px solid var(--border);
}

.deadline-info {
    margin-top: 1rem;
}

.deadline-card {
    background: var(--light);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 1rem;
    border-left: 4px solid var(--primary);
}

.deadline-card h5 {
    margin: 0 0 0.75rem 0;
    color: var(--primary);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.deadline-item {
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.deadline-status {
    padding: 0.25rem 0.75rem;
    border-radius: 4px;
    font-weight: 600;
    margin-top: 0.5rem;
    display: inline-block;
}

.deadline-status.normal { background: var(--success); color: white; }
.deadline-status.approaching { background: var(--warning); color: white; }
.deadline-status.urgent { background: var(--error); color: white; }
.deadline-status.expired { background: var(--gray); color: white; }

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
    }
}
</style>
