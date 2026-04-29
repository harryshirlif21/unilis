<div class="card">
    <div class="card-header">📓 Notebook Details</div>
    
    <div class="notebook-info">
        <h2><?= htmlspecialchars($notebook['title']) ?></h2>
        <div class="notebook-meta">
            <span><strong>Student:</strong> <?= htmlspecialchars($notebook['student_name']) ?></span>
            <span><strong>Registration:</strong> <?= htmlspecialchars($notebook['reg_number']) ?></span>
            <span><strong>Practical:</strong> <?= htmlspecialchars($notebook['practical_title'] ?? 'N/A') ?></span>
            <span><strong>Lab:</strong> <?= htmlspecialchars($notebook['lab_name'] ?? 'N/A') ?></span>
            <span><strong>Status:</strong> <span class="badge badge-<?= $notebook['status'] ?>"><?= ucfirst($notebook['status']) ?></span></span>
            <span><strong>Created:</strong> <?= date('M j, Y H:i', strtotime($notebook['created_at'])) ?></span>
        </div>
    </div>
    
    <div class="notebook-content">
        <h3>Content</h3>
        <div class="content-display">
            <?= nl2br(htmlspecialchars($notebook['content'] ?? 'No content yet.')) ?>
        </div>
    </div>
    
    <div class="notebook-actions">
        <?php if ($canEdit): ?>
            <a href="<?= APP_URL ?>/notebooks/edit/<?= $notebook['id'] ?>" class="btn btn-primary">Edit Notebook</a>
        <?php endif; ?>
        
        <?php if ($notebook['status'] === 'draft'): ?>
            <form method="POST" action="<?= APP_URL ?>/notebooks/edit/<?= $notebook['id'] ?>" style="display:inline;">
                <input type="hidden" name="action" value="submit">
                <button type="submit" class="btn btn-success">Submit for Approval</button>
            </form>
        <?php endif; ?>
        
        <a href="<?= APP_URL ?>/notebooks" class="btn btn-secondary">Back to Notebooks</a>
    </div>
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

.notebook-actions {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
}

.badge {
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    font-weight: 500;
}

.badge-draft {
    background: #fef3c7;
    color: #92400e;
}

.badge-submitted {
    background: #dbeafe;
    color: #1e40af;
}

.badge-approved {
    background: #dcfce7;
    color: #166534;
}

.badge-rejected {
    background: #fef2f2;
    color: #dc2626;
}
</style>
