<div class="card">
    <div class="card-header">
        <div>
            <div class="card-title">📓 My Lab Notebooks</div>
            <div class="card-sub">Manage your lab notebooks and reports</div>
        </div>
        <a href="<?= APP_URL ?>/notebooks/create" class="btn btn-primary">Create Notebook</a>
    </div>
    
    <?php if (empty($notebooks)): ?>
        <div class="alert alert-info">
            You don't have any lab notebooks yet. Notebooks are created when you participate in lab sessions.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
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
                                <?php if ($notebook['status'] === 'draft'): ?>
                                    <a href="<?= APP_URL ?>/notebooks/edit/<?= $notebook['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
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
