<div class="card">
    <div class="card-header">
        <div class="card-header-content">
            <span>📝 Report Submission</span>
            <a href="<?= APP_URL ?>/report-submission/create" class="btn btn-primary">
                <i class="fas fa-plus"></i> Submit New Report
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
        
        <!-- Available Practicals for Submission -->
        <div class="section">
            <h3><i class="fas fa-clock"></i> Pending Submissions</h3>
            
            <?php if (empty($availablePracticals)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📋</div>
                    <h4>No Reports to Submit</h4>
                    <p>You need to complete practicals before you can submit reports.</p>
                    <div class="info-box">
                        <h5><i class="fas fa-info-circle"></i> How to Submit Reports:</h5>
                        <ol>
                            <li>Complete a practical session</li>
                            <li>Wait for the session to be marked as "completed"</li>
                            <li>Return here to submit your report</li>
                        </ol>
                        <p><strong>Note:</strong> Reports can only be submitted for practicals that have been completed.</p>
                    </div>
                    <div class="actions">
                        <a href="<?= APP_URL ?>/practicals" class="btn btn-primary">
                            <i class="fas fa-flask"></i> View Practicals
                        </a>
                        <a href="<?= APP_URL ?>/practical-requests" class="btn btn-secondary">
                            <i class="fas fa-plus"></i> Request Practical Redo
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="practicals-grid">
                    <?php foreach ($availablePracticals as $practical): ?>
                        <div class="practical-card <?= !$practical['can_submit'] ? 'disabled' : '' ?>">
                            <div class="practical-header">
                                <h4><?= htmlspecialchars($practical['title']) ?></h4>
                                <?php if ($practical['deadline']): ?>
                                    <span class="deadline-badge deadline-<?= $practical['deadline']['status'] ?>">
                                        <?php if ($practical['deadline']['is_expired']): ?>
                                            Expired
                                        <?php elseif ($practical['deadline']['days_remaining'] <= 3): ?>
                                            <?= $practical['deadline']['days_remaining'] ?> days left
                                        <?php else: ?>
                                            <?= $practical['deadline']['days_remaining'] ?> days left
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="practical-info">
                                <div class="info-row">
                                    <i class="fas fa-flask"></i>
                                    <span><?= htmlspecialchars($practical['lab_name']) ?></span>
                                </div>
                                <div class="info-row">
                                    <i class="fas fa-calendar"></i>
                                    <span>Completed: <?= date('M j, Y', strtotime($practical['session_date'])) ?></span>
                                </div>
                                <?php if ($practical['deadline']): ?>
                                    <div class="info-row">
                                        <i class="fas fa-hourglass-half"></i>
                                        <span>Deadline: <?= date('M j, Y h:i A', strtotime($practical['deadline']['deadline_date'])) ?></span>
                                    </div>
                                    <?php if ($practical['deadline']['extended']): ?>
                                        <div class="info-row">
                                            <i class="fas fa-calendar-plus"></i>
                                            <span>Extended to: <?= date('M j, Y h:i A', strtotime($practical['deadline']['extended_until'])) ?></span>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            
                            <div class="practical-actions">
                                <?php if ($practical['can_submit']): ?>
                                    <a href="<?= APP_URL ?>/report-submission/create?practical=<?= $practical['id'] ?>" class="btn btn-primary">
                                        <i class="fas fa-paper-plane"></i> Submit Report
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-disabled" disabled>
                                        <i class="fas fa-lock"></i> Submission Closed
                                    </button>
                                    <?php if ($practical['deadline']['is_expired']): ?>
                                        <small class="text-muted">Deadline expired. Contact lecturer for extension.</small>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Submitted Reports -->
        <div class="section">
            <h3><i class="fas fa-file-alt"></i> Submitted Reports</h3>
            
            <?php if (empty($reports)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📄</div>
                    <h4>No Reports Submitted</h4>
                    <p>Submit your first report using the button above.</p>
                </div>
            <?php else: ?>
                <div class="reports-grid">
                    <?php foreach ($reports as $report): ?>
                        <div class="report-card">
                            <div class="report-header">
                                <h4><?= htmlspecialchars($report['title']) ?></h4>
                                <span class="badge badge-<?= $report['status'] ?>">
                                    <?= ucfirst($report['status']) ?>
                                </span>
                            </div>
                            
                            <div class="report-info">
                                <div class="info-row">
                                    <i class="fas fa-flask"></i>
                                    <span><?= htmlspecialchars($report['practical_title']) ?></span>
                                </div>
                                <div class="info-row">
                                    <i class="fas fa-calendar"></i>
                                    <span>Submitted: <?= date('M j, Y h:i A', strtotime($report['submitted_at'])) ?></span>
                                </div>
                                <?php if ($report['graded_at']): ?>
                                    <div class="info-row">
                                        <i class="fas fa-check-circle"></i>
                                        <span>Graded: <?= date('M j, Y', strtotime($report['graded_at'])) ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="report-actions">
                                <a href="<?= APP_URL ?>/report-submission/view/<?= $report['id'] ?>" class="btn btn-sm btn-outline">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <?php if ($report['status'] === 'submitted'): ?>
                                    <a href="<?= APP_URL ?>/report-submission/edit/<?= $report['id'] ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.practicals-grid, .reports-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.practical-card, .report-card {
    background: white;
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 1.5rem;
    transition: all 0.3s;
}

.practical-card:hover:not(.disabled), .report-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
}

.practical-card.disabled {
    opacity: 0.6;
    background: var(--light);
}

.practical-header, .report-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.practical-header h4, .report-header h4 {
    margin: 0;
    color: var(--dark);
    font-size: 1.1rem;
    line-height: 1.3;
}

.deadline-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.deadline-normal { background: var(--success); color: white; }
.deadline-approaching { background: var(--warning); color: white; }
.deadline-urgent { background: var(--error); color: white; }
.deadline-expired { background: var(--gray); color: white; }

.practical-info, .report-info {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.info-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    color: var(--gray);
}

.info-row i {
    width: 16px;
    text-align: center;
}

.practical-actions, .report-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.section {
    margin-bottom: 2rem;
}

.section h3 {
    margin-bottom: 1rem;
    color: var(--dark);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.empty-state {
    text-align: center;
    padding: 3rem 0;
    color: var(--gray);
}

.empty-state .empty-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
}

.info-box {
    background: var(--light);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 1.5rem;
    margin: 1.5rem 0;
    border-left: 4px solid var(--primary);
}

.info-box h5 {
    margin: 0 0 1rem 0;
    color: var(--primary);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.info-box ol {
    margin: 0 0 1rem 0;
    padding-left: 1.5rem;
}

.info-box li {
    margin-bottom: 0.5rem;
    color: var(--gray);
}

.info-box p {
    margin: 0;
    color: var(--gray);
    font-size: 0.9rem;
}

.actions {
    display: flex;
    gap: 1rem;
    margin-top: 1.5rem;
    justify-content: center;
}

.btn-disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.text-muted {
    color: var(--gray);
    font-size: 0.875rem;
}

.badge-submitted { background: var(--primary); color: white; }
.badge-graded { background: var(--success); color: white; }
.badge-rejected { background: var(--error); color: white; }

@media (max-width: 768px) {
    .practicals-grid, .reports-grid {
        grid-template-columns: 1fr;
    }
}
</style>
