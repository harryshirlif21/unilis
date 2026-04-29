<div class="card">
    <div class="card-header">🔬 Practical Details</div>
    
    <div class="practical-info">
        <h2><?= htmlspecialchars($practical['title']) ?></h2>
        <div class="practical-meta">
            <span><strong>Course:</strong> <?= htmlspecialchars($practical['course_code'] ?? 'N/A') ?></span>
            <span><strong>Lecturer:</strong> <?= htmlspecialchars($practical['lecturer_name'] ?? 'N/A') ?></span>
            <span><strong>Lab:</strong> <?= htmlspecialchars($practical['lab_name']) ?> (<?= htmlspecialchars($practical['lab_code']) ?>)</span>
            <span><strong>Date:</strong> <?= $practical['scheduled_date'] ? date('M j, Y', strtotime($practical['scheduled_date'])) : 'Not set' ?></span>
            <span><strong>Time:</strong> 
                <?php if ($practical['start_time'] && $practical['end_time']): ?>
                    <?= date('H:i', strtotime($practical['start_time'])) ?> - 
                    <?= date('H:i', strtotime($practical['end_time'])) ?>
                <?php else: ?>
                    Not set
                <?php endif; ?>
            </span>
            <span><strong>Max Students:</strong> <?= $practical['max_students'] ?? 'N/A' ?></span>
            <span><strong>Status:</strong> <span class="badge badge-<?= $practical['status'] ?>"><?= ucfirst($practical['status'] ?? 'draft') ?></span></span>
        </div>
    </div>
    
    <div class="practical-description">
        <h3>Description</h3>
        <div class="content-display">
            <?= nl2br(htmlspecialchars($practical['description'] ?? 'No description provided.')) ?>
        </div>
    </div>
    
    <?php if (!empty($practical['required_equipment']) || !empty($practical['required_chemicals']) || !empty($practical['safety_notes'])): ?>
        <div class="practical-requirements">
            <h3>Requirements & Safety</h3>
            
            <?php if (!empty($practical['required_equipment'])): ?>
                <div class="requirement-section">
                    <h4>Required Equipment</h4>
                    <p><?= nl2br(htmlspecialchars($practical['required_equipment'])) ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($practical['required_chemicals'])): ?>
                <div class="requirement-section">
                    <h4>Required Chemicals</h4>
                    <p><?= nl2br(htmlspecialchars($practical['required_chemicals'])) ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($practical['safety_notes'])): ?>
                <div class="requirement-section">
                    <h4>Safety Notes</h4>
                    <div class="safety-alert">
                        <?= nl2br(htmlspecialchars($practical['safety_notes'])) ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <div class="practical-actions">
        <?php if ($canEdit): ?>
            <a href="<?= APP_URL ?>/practicals/edit/<?= $practical['id'] ?>" class="btn btn-primary">Edit Practical</a>
            <?php if ($practical['status'] === 'draft'): ?>
                <form method="POST" action="<?= APP_URL ?>/practicals/edit/<?= $practical['id'] ?>" style="display:inline;">
                    <input type="hidden" name="status" value="published">
                    <button type="submit" class="btn btn-success">Publish</button>
                </form>
            <?php elseif ($practical['status'] === 'published'): ?>
                <a href="<?= APP_URL ?>/practicals/start-session/<?= $practical['id'] ?>" class="btn btn-success">Start Lab Session</a>
            <?php endif; ?>
        <?php endif; ?>
        
        <a href="<?= APP_URL ?>/practicals" class="btn btn-secondary">Back to Practicals</a>
    </div>
</div>

<style>
.practical-info {
    margin-bottom: 2rem;
    padding: 1rem;
    background: var(--background);
    border-radius: 0.5rem;
}

.practical-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-top: 1rem;
}

.practical-meta span {
    padding: 0.25rem 0.75rem;
    background: var(--surface);
    border-radius: 0.25rem;
    font-size: 0.875rem;
}

.practical-description, .practical-requirements {
    margin: 2rem 0;
}

.content-display {
    padding: 1.5rem;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 0.5rem;
    white-space: pre-wrap;
}

.requirement-section {
    margin-bottom: 1.5rem;
}

.requirement-section h4 {
    margin-bottom: 0.5rem;
    color: var(--primary);
}

.safety-alert {
    padding: 1rem;
    background: #fef3c7;
    border: 1px solid #f59e0b;
    border-radius: 0.5rem;
    color: #92400e;
}

.practical-actions {
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
    background: rgba(212,160,23,0.15);
    color: #d4a017;
}

.badge-published {
    background: rgba(22,163,74,0.1);
    color: #16a34a;
}

.badge-ongoing {
    background: rgba(37,99,235,0.1);
    color: #2563eb;
}

.badge-completed {
    background: #f3f4f6;
    color: #6b7280;
}
</style>
