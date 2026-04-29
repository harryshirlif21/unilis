<div class="card">
    <div class="card-header">✏️ Edit Notebook</div>
    
    <form method="POST" action="<?= APP_URL ?>/notebooks/edit/<?= $notebook['id'] ?>">
        <div class="form-group">
            <label class="form-label">Notebook Title</label>
            <input type="text" name="title" class="form-input" value="<?= htmlspecialchars($notebook['title']) ?>" required>
        </div>
        
        <div class="form-group">
            <label class="form-label">Content</label>
            <textarea name="content" class="form-textarea" rows="15" placeholder="Enter your lab observations, results, and notes here..."><?= htmlspecialchars($notebook['content'] ?? '') ?></textarea>
        </div>
        
        <div class="form-actions">
            <button type="submit" name="action" value="save" class="btn btn-primary">Save Draft</button>
            <?php if ($notebook['status'] === 'draft'): ?>
                <button type="submit" name="action" value="submit" class="btn btn-success">Submit for Approval</button>
            <?php endif; ?>
            <a href="<?= APP_URL ?>/notebooks/view/<?= $notebook['id'] ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header">📝 Notebook Information</div>
    <div class="notebook-info">
        <p><strong>Practical:</strong> <?= htmlspecialchars($notebook['practical_title'] ?? 'N/A') ?></p>
        <p><strong>Lab:</strong> <?= htmlspecialchars($notebook['lab_name'] ?? 'N/A') ?></p>
        <p><strong>Status:</strong> <span class="badge badge-<?= $notebook['status'] ?>"><?= ucfirst($notebook['status']) ?></span></p>
        <p><strong>Created:</strong> <?= date('M j, Y H:i', strtotime($notebook['created_at'])) ?></p>
        <?php if ($notebook['updated_at'] !== $notebook['created_at']): ?>
            <p><strong>Last Updated:</strong> <?= date('M j, Y H:i', strtotime($notebook['updated_at'])) ?></p>
        <?php endif; ?>
    </div>
</div>

<style>
.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
}

.notebook-info {
    padding: 1rem;
    background: var(--background);
    border-radius: 0.5rem;
}

.notebook-info p {
    margin-bottom: 0.5rem;
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
