<div class="card">
    <div class="card-header">📝 Submit Lab Report</div>
    
    <?php if (!$notebook): ?>
        <div class="alert alert-error">
            You must complete a lab notebook before submitting a report.
        </div>
        <a href="<?= APP_URL ?>/dashboard" class="btn btn-secondary">Back to Dashboard</a>
    <?php else: ?>
        <form method="POST" action="<?= APP_URL ?>/reports/create/<?= $practical['id'] ?>" enctype="multipart/form-data">
            <div class="form-group">
                <label class="form-label">Report Title *</label>
                <input type="text" name="title" class="form-input" value="<?= htmlspecialchars($data['title'] ?? '') ?>" placeholder="Enter report title..." required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Submission Notes</label>
                <textarea name="submission_notes" class="form-textarea" rows="4" placeholder="Add any notes about your report submission..."><?= htmlspecialchars($data['submission_notes'] ?? '') ?></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label">Report File (PDF, DOC, DOCX)</label>
                <input type="file" name="report_file" class="form-input" accept=".pdf,.doc,.docx">
                <small style="color: var(--text2);">Optional: Upload your completed lab report</small>
            </div>
            
            <div class="practical-info">
                <h4>Practical Information</h4>
                <div class="info-grid">
                    <div><strong>Practical:</strong> <?= htmlspecialchars($practical['title']) ?></div>
                    <div><strong>Lab:</strong> <?= htmlspecialchars($practical['lab_name']) ?></div>
                    <div><strong>Date:</strong> <?= date('M j, Y', strtotime($practical['scheduled_date'])) ?></div>
                    <div><strong>Notebook:</strong> <?= htmlspecialchars($notebook['title']) ?></div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Submit Report</button>
                <a href="<?= APP_URL ?>/reports" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    <?php endif; ?>
</div>

<style>
.practical-info {
    margin: 2rem 0;
    padding: 1rem;
    background: var(--background);
    border-radius: 0.5rem;
}

.practical-info h4 {
    margin-bottom: 1rem;
    color: var(--primary);
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.info-grid div {
    padding: 0.5rem;
    background: var(--surface);
    border-radius: 0.25rem;
    font-size: 0.875rem;
}

.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
}
</style>
