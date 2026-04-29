<div class="card">
    <div class="card-header">
        <div>
            <div class="card-title">🔬 Lab Practicals</div>
            <div class="card-sub">Manage laboratory sessions and experiments</div>
        </div>
        <?php if ($userRole === 'lecturer'): ?>
            <a href="<?= APP_URL ?>/practicals/create" class="btn btn-primary">Create Practical</a>
        <?php elseif ($userRole === 'student'): ?>
            <a href="<?= APP_URL ?>/practical-requests/create" class="btn btn-primary">Request Practical Redo</a>
        <?php endif; ?>
    </div>
    
    <div class="content-section">
        <div class="card-header">
            <h2 class="text-bold">Practicals</h2>
            <?php if ($userRole === 'lecturer'): ?>
                <a href="<?= APP_URL ?>/practicals/create" class="btn btn-primary">Create Practical</a>
            <?php endif; ?>
        </div>

        <?php if (!empty($stats)): ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value large-number"><?= $stats['total_practicals'] ?? 0 ?></div>
                    <div class="stat-label text-bold">Total Practicals</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value large-number"><?= $stats['draft'] ?? 0 ?></div>
                    <div class="stat-label text-bold">Draft</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value large-number"><?= $stats['published'] ?? 0 ?></div>
                    <div class="stat-label text-bold">Published</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value large-number"><?= $stats['upcoming'] ?? 0 ?></div>
                    <div class="stat-label text-bold">Upcoming</div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (empty($practicals)): ?>
            <p>No practicals found.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Course</th>
                        <th>Lab</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Max Students</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($practicals as $practical): ?>
                    <tr>
                        <td class="text-bold"><?= htmlspecialchars($practical['title']) ?></td>
                        <td><?= htmlspecialchars($practical['course_code'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($practical['lab_name'] ?? 'N/A') ?></td>
                        <td><?= $practical['scheduled_date'] ? date('M j, Y', strtotime($practical['scheduled_date'])) : 'Not set' ?></td>
                        <td>
                            <?php if ($practical['start_time'] && $practical['end_time']): ?>
                                <?= date('H:i', strtotime($practical['start_time'])) ?> - 
                                <?= date('H:i', strtotime($practical['end_time'])) ?>
                            <?php else: ?>
                                Not set
                            <?php endif; ?>
                        </td>
                        <td><?= $practical['max_students'] ?? 'N/A' ?></td>
                        <td>
                            <span class="badge badge-<?= $practical['status'] ?>">
                                <?= ucfirst($practical['status'] ?? 'draft') ?>
                            </span>
                        </td>
                        <td>
                            <a href="<?= APP_URL ?>/practicals/view/<?= $practical['id'] ?>" class="btn btn-primary btn-sm">View</a>
                            <?php if ($userRole === 'lecturer'): ?>
                                <a href="<?= APP_URL ?>/practicals/edit/<?= $practical['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                                <?php if ($practical['status'] === 'published'): ?>
                                    <a href="<?= APP_URL ?>/practicals/start-session/<?= $practical['id'] ?>" class="btn btn-success btn-sm">Start Session</a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
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

.table-responsive {
    overflow-x: auto;
}

.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.75rem;
}
</style>
