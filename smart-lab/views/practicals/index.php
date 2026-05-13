<div class="card">
    <div class="card-header card-header-hero">
        <div>
            <div class="card-title">🔬 Lab Practicals</div>
            <div class="card-sub">A smarter, cleaner view of available lab sessions and experiment workflows.</div>
        </div>
        <?php if ($userRole === 'lecturer'): ?>
            <a href="<?= APP_URL ?>/practicals/create" class="btn btn-primary">Create Practical</a>
        <?php elseif ($userRole === 'student'): ?>
            <a href="<?= APP_URL ?>/practical-requests/create" class="btn btn-primary">Request Practical Redo</a>
        <?php endif; ?>
    </div>

    <div class="content-section">
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
            <div class="empty-state">
                <h3>No practicals available yet</h3>
                <p>Check back soon or ask your lecturer to publish the next lab practical.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
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
                            <td class="action-cell">
                                <div class="action-group">
                                    <a href="<?= APP_URL ?>/practicals/view/<?= $practical['id'] ?>" class="btn btn-secondary btn-sm">View</a>

                                    <?php if ($userRole === 'student' && $practical['status'] === 'published'): ?>
                                        <a href="<?= APP_URL ?>/student/view_practical/<?= $practical['id'] ?>" class="btn btn-accent btn-sm">Take Practical</a>
                                    <?php elseif ($userRole === 'lecturer'): ?>
                                        <a href="<?= APP_URL ?>/practicals/edit/<?= $practical['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                                        <?php if ($practical['status'] === 'published'): ?>
                                            <a href="<?= APP_URL ?>/practicals/start-session/<?= $practical['id'] ?>" class="btn btn-success btn-sm">Start Session</a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<style>
.card-header-hero {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    background: linear-gradient(135deg, #0f172a 0%, #1d4ed8 100%);
    color: #ffffff;
    padding: 1.5rem 2rem;
    border-radius: 1rem 1rem 0 0;
}

.card-title {
    font-size: 1.4rem;
    font-weight: 700;
}

.card-sub {
    margin-top: 0.4rem;
    color: rgba(255,255,255,0.8);
    max-width: 520px;
}

.content-section {
    padding: 2rem;
    background: #ffffff;
    border-radius: 0 0 1rem 1rem;
    box-shadow: 0 24px 80px rgba(15, 23, 42, 0.06);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1rem;
    margin-bottom: 1.75rem;
}

.stat-card {
    border: 1px solid #e2e8f0;
    border-radius: 1rem;
    padding: 1.25rem;
    background: #f8fafc;
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: #0f172a;
}

.stat-label {
    margin-top: 0.5rem;
    color: #475569;
}

.empty-state {
    padding: 2rem;
    text-align: center;
    border: 1px dashed #cbd5e1;
    border-radius: 1rem;
    background: #f8fafc;
}

.empty-state h3 {
    margin-bottom: 0.5rem;
    color: #0f172a;
}

.empty-state p {
    color: #64748b;
}

.table-wrap {
    overflow-x: auto;
    border-radius: 1rem;
    border: 1px solid #e2e8f0;
}

.table {
    width: 100%;
    border-collapse: collapse;
    min-width: 900px;
}

.table th,
.table td {
    padding: 1rem 1.2rem;
    text-align: left;
    vertical-align: middle;
}

.table thead {
    background: #f8fafc;
}

.table th {
    font-size: 0.85rem;
    letter-spacing: 0.02em;
    color: #475569;
    text-transform: uppercase;
}

.table tbody tr {
    border-bottom: 1px solid #e2e8f0;
}

.table tbody tr:hover {
    background: #f1f5f9;
}

.action-cell {
    min-width: 240px;
}

.action-group {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.btn-sm {
    padding: 0.55rem 0.95rem;
    font-size: 0.78rem;
    border-radius: 0.65rem;
}

.btn-accent {
    background: #2563eb;
    color: #ffffff;
    border: 1px solid transparent;
}

.btn-accent:hover,
.btn-primary:hover,
.btn-success:hover,
.btn-secondary:hover {
    opacity: 0.95;
}

@media (max-width: 980px) {
    .stats-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 640px) {
    .card-header-hero,
    .action-group {
        flex-direction: column;
        align-items: stretch;
    }

    .table {
        min-width: 700px;
    }
}
</style>
