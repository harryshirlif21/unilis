<div class="card">
    <div class="card-header">📋 Grade Report</div>
    
    <div class="report-info">
        <h2><?= htmlspecialchars($report['title']) ?></h2>
        <div class="report-meta">
            <span><strong>Student:</strong> <?= htmlspecialchars($report['student_name']) ?></span>
            <span><strong>Registration:</strong> <?= htmlspecialchars($report['reg_number']) ?></span>
            <span><strong>Practical:</strong> <?= htmlspecialchars($report['practical_title']) ?></span>
            <span><strong>Submitted:</strong> <?= date('M j, Y H:i', strtotime($report['submitted_at'])) ?></span>
        </div>
    </div>
    
    <?php if (!empty($report['file_path'])): ?>
        <div class="report-section">
            <h3>Submitted File</h3>
            <div class="file-info">
                <p><strong>File:</strong> <?= basename($report['file_path']) ?></p>
                <a href="<?= APP_URL ?>/reports/download/<?= $report['id'] ?>" class="btn btn-primary">Download Report File</a>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($report['submission_notes'])): ?>
        <div class="report-section">
            <h3>Student Notes</h3>
            <div class="content-display">
                <?= nl2br(htmlspecialchars($report['submission_notes'])) ?>
            </div>
        </div>
    <?php endif; ?>
    
    <form method="POST" action="<?= APP_URL ?>/reports/grade/<?= $report['id'] ?>">
        <div class="grading-section">
            <h3>Grading Action</h3>
            
            <div class="form-group">
                <label class="form-label">Action</label>
                <div class="radio-group">
                    <label class="radio-option">
                        <input type="radio" name="grading_action" value="grade" required>
                        <span class="radio-label">Grade Report</span>
                    </label>
                    <label class="radio-option">
                        <input type="radio" name="grading_action" value="return">
                        <span class="radio-label">Return for Revision</span>
                    </label>
                </div>
            </div>
            
            <div class="form-group" id="grade-section" style="display:none;">
                <label class="form-label">Grade (0-100)</label>
                <input type="number" name="grade" class="form-input" min="0" max="100" placeholder="Enter grade...">
                <small style="color: var(--text2);">Enter a numerical grade from 0 to 100</small>
            </div>
            
            <div class="form-group">
                <label class="form-label">Feedback *</label>
                <textarea name="feedback" class="form-textarea" rows="6" placeholder="Provide detailed feedback to the student..." required></textarea>
                <small style="color: var(--text2);">Required: Explain the grade and provide constructive feedback</small>
            </div>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-success">Submit Grade</button>
            <a href="<?= APP_URL ?>/reports" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<style>
.report-info {
    margin-bottom: 2rem;
    padding: 1rem;
    background: var(--background);
    border-radius: 0.5rem;
}

.report-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-top: 1rem;
}

.report-meta span {
    padding: 0.25rem 0.75rem;
    background: var(--surface);
    border-radius: 0.25rem;
    font-size: 0.875rem;
}

.report-section {
    margin: 2rem 0;
}

.report-section h3 {
    margin-bottom: 1rem;
    color: var(--primary);
}

.content-display {
    padding: 1.5rem;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 0.5rem;
    white-space: pre-wrap;
}

.file-info {
    padding: 1rem;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 0.5rem;
}

.grading-section {
    margin: 2rem 0;
    padding: 1.5rem;
    background: var(--background);
    border: 1px solid var(--border);
    border-radius: 0.5rem;
}

.radio-group {
    display: flex;
    gap: 2rem;
}

.radio-option {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
}

.radio-option input[type="radio"] {
    width: auto;
}

.radio-label {
    font-weight: 500;
}

.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
}
</style>

<script>
document.querySelectorAll('input[name="grading_action"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const gradeSection = document.getElementById('grade-section');
        gradeSection.style.display = this.value === 'grade' ? 'block' : 'none';
        
        // Make grade field required if grading
        const gradeInput = document.querySelector('input[name="grade"]');
        gradeInput.required = this.value === 'grade';
    });
});
</script>
