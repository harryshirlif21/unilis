<div class="card">
    <div class="card-header">
        <div>
            <div class="card-title">📓 All Lab Notebooks</div>
            <div class="card-sub">Manage all lab notebooks in the system</div>
        </div>
        <a href="<?= APP_URL ?>/notebooks/create" class="btn btn-primary">Create Notebook</a>
    </div>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?= count($notebooks) ?></div>
            <div class="stat-label">Total Notebooks</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= count(array_filter($notebooks, fn($n) => $n['status'] === 'submitted')) ?></div>
            <div class="stat-label">Pending Approval</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= count(array_filter($notebooks, fn($n) => $n['status'] === 'approved')) ?></div>
            <div class="stat-label">Approved</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= count(array_filter($notebooks, fn($n) => $n['status'] === 'draft')) ?></div>
            <div class="stat-label">Draft</div>
        </div>
    </div>
    
    <?php if (empty($notebooks)): ?>
        <div class="alert alert-info">
            No lab notebooks have been created yet.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Title</th>
                        <th>Practical</th>
                        <th>Lab</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($notebooks as $notebook): ?>
                        <tr>
                            <td><?= htmlspecialchars($notebook['student_name'] ?? 'Unknown') ?></td>
                            <td><?= htmlspecialchars($notebook['title']) ?></td>
                            <td><?= htmlspecialchars($notebook['practical_title'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($notebook['lab_name'] ?? 'N/A') ?></td>
                            <td>
                                <span class="badge badge-<?= $notebook['status'] ?>">
                                    <?= ucfirst($notebook['status']) ?>
                                </span>
                            </td>
                            <td><?= date('M j, Y', strtotime($notebook['created_at'])) ?></td>
                            <td>
                                <a href="<?= APP_URL ?>/notebooks/view/<?= $notebook['id'] ?>" class="btn btn-primary btn-sm">View</a>
                                <?php if ($notebook['status'] === 'submitted'): ?>
                                    <a href="<?= APP_URL ?>/notebooks/approve/<?= $notebook['id'] ?>" class="btn btn-secondary btn-sm">Review</a>
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

.badge-approved {
    background: #dcfce7;
    color: #166534;
}

.badge-rejected {
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
</style>
