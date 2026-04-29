<div class="card">
    <div class="card-header">📋 Review Notebook</div>
    
    <div class="notebook-info">
        <h2><?= htmlspecialchars($notebook['title']) ?></h2>
        <div class="notebook-meta">
            <span><strong>Student:</strong> <?= htmlspecialchars($notebook['student_name']) ?></span>
            <span><strong>Registration:</strong> <?= htmlspecialchars($notebook['reg_number']) ?></span>
            <span><strong>Practical:</strong> <?= htmlspecialchars($notebook['practical_title']) ?></span>
            <span><strong>Lab:</strong> <?= htmlspecialchars($notebook['lab_name']) ?></span>
            <span><strong>Submitted:</strong> <?= date('M j, Y H:i', strtotime($notebook['submitted_at'] ?? $notebook['created_at'])) ?></span>
        </div>
    </div>
    
    <div class="notebook-content">
        <h3>Notebook Content</h3>
        <div class="content-display">
            <?= nl2br(htmlspecialchars($notebook['content'] ?? 'No content.')) ?>
        </div>
    </div>
    
    <form method="POST" action="<?= APP_URL ?>/notebooks/approve/<?= $notebook['id'] ?>">
        <div class="form-group">
            <label class="form-label">Review Action</label>
            <select name="approval_action" class="form-select" required>
                <option value="">Select action...</option>
                <option value="approve">Approve</option>
                <option value="reject">Request Revisions</option>
            </select>
        </div>
        
        <div class="form-group">
            <label class="form-label">Feedback / Comments</label>
            <textarea name="comments" class="form-textarea" rows="5" placeholder="Provide feedback to the student..." required></textarea>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-success">Submit Review</button>
            <a href="<?= APP_URL ?>/notebooks" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<style>
.notebook-info {
    margin-bottom: 2rem;
    padding: 1rem;
    background: var(--background);
    border-radius: 0.5rem;
}

.notebook-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-top: 1rem;
}

.notebook-meta span {
    padding: 0.25rem 0.75rem;
    background: var(--surface);
    border-radius: 0.25rem;
    font-size: 0.875rem;
}

.notebook-content {
    margin: 2rem 0;
}

.content-display {
    padding: 1.5rem;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 0.5rem;
    min-height: 200px;
    white-space: pre-wrap;
}

.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
}
</style>
