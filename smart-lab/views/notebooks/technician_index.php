<div class="card">
    <div class="card-header">
        <div>
            <div class="card-title">📓 Notebook Approvals</div>
            <div class="card-sub">Review and approve lab notebooks</div>
        </div>
        <a href="<?= APP_URL ?>/notebooks/create" class="btn btn-primary">Create Notebook</a>
    </div>
    
    <?php if (empty($pending)): ?>
        <div class="alert alert-info">
            No notebooks are currently pending approval.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Registration</th>
                        <th>Notebook Title</th>
                        <th>Practical</th>
                        <th>Lab</th>
                        <th>Submitted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending as $notebook): ?>
                        <tr>
                            <td><?= htmlspecialchars($notebook['student_name']) ?></td>
                            <td><?= htmlspecialchars($notebook['reg_number']) ?></td>
                            <td><?= htmlspecialchars($notebook['title']) ?></td>
                            <td><?= htmlspecialchars($notebook['practical_title']) ?></td>
                            <td><?= htmlspecialchars($notebook['lab_name']) ?></td>
                            <td><?= date('M j, Y H:i', strtotime($notebook['submitted_at'] ?? $notebook['created_at'])) ?></td>
                            <td>
                                <a href="<?= APP_URL ?>/notebooks/approve/<?= $notebook['id'] ?>" class="btn btn-primary btn-sm">Review</a>
                                <a href="<?= APP_URL ?>/notebooks/view/<?= $notebook['id'] ?>" class="btn btn-secondary btn-sm">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<style>
.table-responsive {
    overflow-x: auto;
}

.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.75rem;
}
</style>
