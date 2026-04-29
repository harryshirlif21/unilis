<div class="card">
    <div class="card-header">📄 Report Details</div>
    
    <div class="report-info">
        <h2><?= htmlspecialchars($report['title']) ?></h2>
        <div class="report-meta">
            <span><strong>Student:</strong> <?= htmlspecialchars($report['student_name']) ?></span>
            <span><strong>Registration:</strong> <?= htmlspecialchars($report['reg_number']) ?></span>
            <span><strong>Practical:</strong> <?= htmlspecialchars($report['practical_title'] ?? 'N/A') ?></span>
            <span><strong>Lecturer:</strong> <?= htmlspecialchars($report['lecturer_name'] ?? 'N/A') ?></span>
            <span><strong>Status:</strong> <span class="badge badge-<?= $report['status'] ?>"><?= ucfirst($report['status']) ?></span></span>
            <span><strong>Created:</strong> <?= date('M j, Y H:i', strtotime($report['created_at'])) ?></span>
            <?php if ($report['submitted_at']): ?>
                <span><strong>Submitted:</strong> <?= date('M j, Y H:i', strtotime($report['submitted_at'])) ?></span>
            <?php endif; ?>
            <?php if ($report['graded_at']): ?>
                <span><strong>Graded:</strong> <?= date('M j, Y H:i', strtotime($report['graded_at'])) ?></span>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (!empty($report['submission_notes'])): ?>
        <div class="report-section">
            <h3>Submission Notes</h3>
            <div class="content-display">
                <?= nl2br(htmlspecialchars($report['submission_notes'])) ?>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($report['file_path'])): ?>
        <div class="report-section">
            <h3>Submitted File</h3>
            <div class="file-info">
                <p><strong>File:</strong> <?= basename($report['file_path']) ?></p>
                <a href="<?= APP_URL ?>/reports/download/<?= $report['id'] ?>" class="btn btn-primary">Download Report File</a>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if ($report['grade'] !== null || !empty($report['feedback'])): ?>
        <div class="report-section">
            <h3>Grading Information</h3>
            <div class="grading-info">
                <?php if ($report['grade'] !== null): ?>
                    <div class="grade-display">
                        <span class="grade-label">Grade:</span>
                        <span class="grade-value"><?= $report['grade'] ?>%</span>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($report['feedback'])): ?>
                    <div class="feedback-display">
                        <h4>Feedback</h4>
                        <div class="content-display">
                            <?= nl2br(htmlspecialchars($report['feedback'])) ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="report-actions">
        <?php if ($canEdit): ?>
            <a href="<?= APP_URL ?>/reports/edit/<?= $report['id'] ?>" class="btn btn-primary">Edit Report</a>
            <?php if ($report['status'] === 'draft'): ?>
                <form method="POST" action="<?= APP_URL ?>/reports/edit/<?= $report['id'] ?>" style="display:inline;">
                    <input type="hidden" name="action" value="submit">
                    <button type="submit" class="btn btn-success">Submit for Grading</button>
                </form>
            <?php endif; ?>
        <?php elseif ($canGrade): ?>
            <a href="<?= APP_URL ?>/reports/grade/<?= $report['id'] ?>" class="btn btn-success">Grade Report</a>
        <?php endif; ?>
        
        <?php if (!empty($report['file_path'])): ?>
            <a href="<?= APP_URL ?>/reports/download/<?= $report['id'] ?>" class="btn btn-secondary">Download File</a>
        <?php endif; ?>
        
        <a href="<?= APP_URL ?>/reports" class="btn btn-outline">Back to Reports</a>
    </div>
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

.grading-info {
    padding: 1.5rem;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 0.5rem;
}

.grade-display {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.grade-label {
    font-weight: 600;
    color: var(--text2);
}

.grade-value {
    font-size: 1.5rem;
    font-weight: bold;
    color: var(--primary);
}

.feedback-display h4 {
    margin-bottom: 0.75rem;
    color: var(--text);
}

.report-actions {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
    flex-wrap: wrap;
}

.badge {
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    font-weight: 500;
}

.badge-draft {
    background: rgba(212,160,23,0.15);
    color: #d4a017;
}

.badge-submitted {
    background: rgba(37,99,235,0.1);
    color: #2563eb;
}

.badge-graded {
    background: rgba(22,163,74,0.1);
    color: #16a34a;
}

.badge-returned {
    background: rgba(220,38,38,0.1);
    color: #dc2626;
}

.btn-outline {
    background: transparent;
    border: 1px solid var(--border);
    color: var(--text);
}

.btn-outline:hover {
    background: var(--background);
}
</style>
