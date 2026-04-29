<div class="card">
    <div class="card-header">
        <div>
            <div class="card-title">⏳ Pending Grading</div>
            <div class="card-sub">Reports awaiting your review and grading</div>
        </div>
        <a href="<?= APP_URL ?>/reports" class="btn btn-secondary">All Reports</a>
    </div>
    
    <?php if (empty($pendingReports)): ?>
        <div class="alert alert-success">
            Great! No reports are currently pending grading. All submitted reports have been reviewed.
        </div>
    <?php else: ?>
        <div class="pending-stats">
            <div class="stat-card">
                <div class="stat-value"><?= count($pendingReports) ?></div>
                <div class="stat-label">Reports Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= count(array_filter($pendingReports, fn($r) => $r['status'] === 'returned')) ?></div>
                <div class="stat-label">Returned for Revision</div>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Report Title</th>
                        <th>Student</th>
                        <th>Registration</th>
                        <th>Practical</th>
                        <th>Submitted</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingReports as $report): ?>
                        <tr>
                            <td><?= htmlspecialchars($report['title']) ?></td>
                            <td><?= htmlspecialchars($report['student_name']) ?></td>
                            <td><?= htmlspecialchars($report['reg_number']) ?></td>
                            <td><?= htmlspecialchars($report['practical_title']) ?></td>
                            <td>
                                <?php if ($report['submitted_at']): ?>
                                    <?= date('M j, Y H:i', strtotime($report['submitted_at'])) ?>
                                <?php else: ?>
                                    <?= date('M j, Y', strtotime($report['created_at'])) ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= $report['status'] ?>">
                                    <?= ucfirst($report['status']) ?>
                                </span>
                                <?php if ($report['status'] === 'returned'): ?>
                                    <br><small class="text-muted">Returned for revision</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?= APP_URL ?>/reports/view/<?= $report['id'] ?>" class="btn btn-primary btn-sm">View</a>
                                <a href="<?= APP_URL ?>/reports/grade/<?= $report['id'] ?>" class="btn btn-success btn-sm">Grade</a>
                                <?php if (!empty($report['file_path'])): ?>
                                    <a href="<?= APP_URL ?>/reports/download/<?= $report['id'] ?>" class="btn btn-outline btn-sm">Download</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<style>
.pending-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.badge {
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    font-weight: 500;
}

.badge-submitted {
    background: #dbeafe;
    color: #1e40af;
}

.badge-returned {
    background: #fef2f2;
    color: #dc2626;
}

.table-responsive {
    overflow-x: auto;
}

.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.75rem;
}

.btn-outline {
    background: transparent;
    border: 1px solid var(--border);
    color: var(--text);
}

.btn-outline:hover {
    background: var(--background);
}

.text-muted {
    color: var(--text2);
}
</style>
