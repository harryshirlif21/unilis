<div class="card">
    <div class="card-header">
        <h2 class="text-bold">Lab Reports</h2>
        <?php if ($userRole === 'student'): ?>
            <a href="<?= APP_URL ?>/reports/create" class="btn btn-primary">Submit Report</a>
        <?php elseif ($userRole === 'lecturer'): ?>
            <a href="<?= APP_URL ?>/reports/pending" class="btn btn-warning">Pending Grading</a>
        <?php endif; ?>
    </div>
    
    <?php if (!empty($stats)): ?>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value large-number"><?= $stats['total_reports'] ?? 0 ?></div>
                <div class="stat-label text-bold">Total Reports</div>
            </div>
            <div class="stat-card">
                <div class="stat-value large-number"><?= $stats['draft'] ?? 0 ?></div>
                <div class="stat-label text-bold">Draft</div>
            </div>
            <div class="stat-card">
                <div class="stat-value large-number"><?= $stats['submitted'] ?? 0 ?></div>
                <div class="stat-label text-bold">Submitted</div>
            </div>
            <div class="stat-card">
                <div class="stat-value large-number"><?= $stats['graded'] ?? 0 ?></div>
                <div class="stat-label text-bold">Graded</div>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if (empty($reports)): ?>
        <div class="alert alert-info">
            <?php if ($userRole === 'student'): ?>
                You haven't submitted any reports yet. Complete a lab session first, then <a href="<?= APP_URL ?>/reports/create" class="text-accent-bold">submit your first report</a>.
            <?php elseif ($userRole === 'lecturer'): ?>
                No reports have been submitted yet.
            <?php else: ?>
                No reports are available in the system.
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Report Title</th>
                        <th>Student</th>
                        <th>Practical</th>
                        <th>Submitted</th>
                        <th>Status</th>
                        <th>Grade</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $report): ?>
                        <tr>
                            <td class="text-bold"><?= htmlspecialchars($report['title']) ?></td>
                            <td>
                                <?= htmlspecialchars($report['student_name'] ?? 'Unknown') ?>
                                <?php if (!empty($report['reg_number'])): ?>
                                    <br><small><?= htmlspecialchars($report['reg_number']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($report['practical_title'] ?? 'N/A') ?></td>
                            <td>
                                <?php if ($report['submitted_at']): ?>
                                    <?= date('M j, Y H:i', strtotime($report['submitted_at'])) ?>
                                <?php else: ?>
                                    <?= date('M j, Y', strtotime($report['created_at'])) ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= $report['status'] ?>">
                                    <?= ucfirst($report['status'] ?? 'draft') ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($report['grade'] !== null): ?>
                                    <strong><?= $report['grade'] ?>%</strong>
                                <?php else: ?>
                                    <span class="text-muted">Not graded</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?= APP_URL ?>/reports/view/<?= $report['id'] ?>" class="btn btn-primary btn-sm">View</a>
                                <?php if ($userRole === 'student' && $report['status'] === 'draft'): ?>
                                    <a href="<?= APP_URL ?>/reports/edit/<?= $report['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                                <?php elseif ($userRole === 'lecturer' && in_array($report['status'], ['submitted', 'returned'])): ?>
                                    <a href="<?= APP_URL ?>/reports/grade/<?= $report['id'] ?>" class="btn btn-success btn-sm">Grade</a>
                                <?php endif; ?>
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

.badge-graded {
    background: #dcfce7;
    color: #166534;
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
