<div class="card">
    <div class="card-header">📓 Select Lab Session</div>
    
    <div class="session-intro">
        <p>Choose a completed lab session to create a notebook for. You can create individual or group notebooks for any session that has been completed.</p>
    </div>
    
    <?php if (empty($sessions)): ?>
        <div class="alert alert-info">
            No completed lab sessions are available. Notebooks can only be created for sessions that have been completed.
        </div>
        <a href="<?= APP_URL ?>/dashboard" class="btn btn-secondary">Back to Dashboard</a>
    <?php else: ?>
        <div class="session-grid">
            <?php foreach ($sessions as $session): ?>
                <div class="session-card">
                    <div class="session-header">
                        <h4><?= htmlspecialchars($session['practical_title']) ?></h4>
                        <span class="session-date"><?= date('M j, Y', strtotime($session['started_at'])) ?></span>
                    </div>
                    
                    <div class="session-details">
                        <p><strong>Laboratory:</strong> <?= htmlspecialchars($session['lab_name']) ?></p>
                        <p><strong>Session ID:</strong> <?= htmlspecialchars($session['id']) ?></p>
                        <?php if (!empty($session['duration'])): ?>
                            <p><strong>Duration:</strong> <?= htmlspecialchars($session['duration']) ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="session-actions">
                        <a href="<?= APP_URL ?>/notebooks/create/<?= $session['id'] ?>" class="btn btn-primary">Create Notebook</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.session-intro {
    margin-bottom: 2rem;
    padding: 1rem;
    background: var(--background);
    border-radius: 0.5rem;
    border-left: 4px solid var(--primary);
}

.session-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
}

.session-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 0.75rem;
    padding: 1.5rem;
    box-shadow: var(--shadow);
    transition: transform 0.2s, box-shadow 0.2s;
}

.session-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.session-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.session-header h4 {
    margin: 0;
    color: var(--text);
    font-size: 1.1rem;
    line-height: 1.3;
}

.session-date {
    font-size: 0.875rem;
    color: var(--text2);
    white-space: nowrap;
}

.session-details {
    margin-bottom: 1.5rem;
}

.session-details p {
    margin: 0.5rem 0;
    font-size: 0.875rem;
    color: var(--text2);
}

.session-details strong {
    color: var(--text);
}

.session-actions {
    display: flex;
    gap: 0.5rem;
}

@media (max-width: 768px) {
    .session-grid {
        grid-template-columns: 1fr;
    }
    
    .session-header {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .session-date {
        white-space: normal;
    }
}
</style>
