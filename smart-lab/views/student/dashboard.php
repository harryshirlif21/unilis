<div class="dashboard-container">
    <div class="dashboard-header">
        <h1>Student Dashboard</h1>
        <p>Manage your laboratory practicals and submissions</p>
    </div>
    
    <!-- Today's Schedules -->
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">Today's Lab Sessions</div>
                <div class="card-sub">Available practical sessions for today</div>
            </div>
            <span class="badge badge-info"><?= count($today_schedules) ?> sessions</span>
        </div>
        
        <?php if (empty($today_schedules)): ?>
            <div class="empty-state">
                <div class="empty-icon">?</div>
                <h3>No Lab Sessions Today</h3>
                <p>There are no practical sessions scheduled for today.</p>
            </div>
        <?php else: ?>
            <div class="schedules-grid">
                <?php foreach ($today_schedules as $schedule): ?>
                    <div class="schedule-card <?= $schedule['attendance_id'] ? 'attended' : '' ?>">
                        <div class="schedule-header">
                            <h4><?= htmlspecialchars($schedule['experiment_title']) ?></h4>
                            <span class="badge badge-<?= $schedule['attendance_id'] ? 'success' : 'warning' ?>">
                                <?= $schedule['attendance_id'] ? 'Attended' : 'Not Attended' ?>
                            </span>
                        </div>
                        
                        <div class="schedule-details">
                            <div class="detail-item">
                                <span class="detail-label">Unit:</span>
                                <span><?= htmlspecialchars($schedule['unit_code']) ?> - <?= htmlspecialchars($schedule['unit_name']) ?></span>
                            </div>
                            
                            <div class="detail-item">
                                <span class="detail-label">Time:</span>
                                <span><?= date('h:i A', strtotime($schedule['start_time'])) ?> - <?= date('h:i A', strtotime($schedule['end_time'])) ?></span>
                            </div>
                            
                            <?php if ($schedule['attendance_time']): ?>
                                <div class="detail-item">
                                    <span class="detail-label">Check-in:</span>
                                    <span><?= date('h:i A', strtotime($schedule['attendance_time'])) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="schedule-actions">
                            <?php if ($schedule['attendance_id']): ?>
                                <?php if ($schedule['submission_status'] === 'draft'): ?>
                                    <a href="<?= APP_URL ?>/student/practical/<?= $schedule['id'] ?>" class="btn btn-primary">Continue Practical</a>
                                <?php elseif ($schedule['submission_status'] === 'submitted'): ?>
                                    <button class="btn btn-success" disabled>Submitted</button>
                                <?php else: ?>
                                    <a href="<?= APP_URL ?>/student/practical/<?= $schedule['id'] ?>" class="btn btn-primary">Start Practical</a>
                                <?php endif; ?>
                            <?php else: ?>
                                <button class="btn btn-outline" disabled>Attend Lab First</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Recent Submissions -->
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">Recent Submissions</div>
                <div class="card-sub">Your latest practical reports</div>
            </div>
            <a href="<?= APP_URL ?>/student/submissions" class="btn btn-outline">View All</a>
        </div>
        
        <?php if (empty($recent_submissions)): ?>
            <div class="empty-state">
                <div class="empty-icon">?</div>
                <h3>No Submissions Yet</h3>
                <p>You haven't submitted any practical reports yet.</p>
            </div>
        <?php else: ?>
            <div class="submissions-list">
                <?php foreach ($recent_submissions as $submission): ?>
                    <div class="submission-item">
                        <div class="submission-info">
                            <h4><?= htmlspecialchars($submission['experiment_title']) ?></h4>
                            <div class="submission-meta">
                                <span class="meta-item">
                                    <i class="icon">?</i>
                                    <?= htmlspecialchars($submission['schedule_title']) ?>
                                </span>
                                <span class="meta-item">
                                    <i class="icon">?</i>
                                    <?= date('M j, Y', strtotime($submission['scheduled_date'])) ?>
                                </span>
                                <span class="meta-item">
                                    <i class="icon">?</i>
                                    <?= date('h:i A', strtotime($submission['start_time'])) ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="submission-status">
                            <span class="badge badge-<?= $submission['status'] === 'submitted' ? 'success' : 'warning' ?>">
                                <?= ucfirst($submission['status']) ?>
                            </span>
                            <?php if ($submission['submitted_at']): ?>
                                <small class="submission-date">
                                    Submitted <?= date('M j, Y g:i A', strtotime($submission['submitted_at'])) ?>
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Quick Actions -->
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">Quick Actions</div>
                <div class="card-sub">Common tasks and resources</div>
            </div>
        </div>
        
        <div class="quick-actions">
            <div class="action-item">
                <div class="action-icon">
                    <i class="icon">?</i>
                </div>
                <div class="action-content">
                    <h4>View Schedule</h4>
                    <p>See upcoming lab sessions</p>
                    <a href="<?= APP_URL ?>/student/schedule" class="btn btn-outline btn-sm">View Schedule</a>
                </div>
            </div>
            
            <div class="action-item">
                <div class="action-icon">
                    <i class="icon">?</i>
                </div>
                <div class="action-content">
                    <h4>Lab Resources</h4>
                    <p>Access lab manuals and materials</p>
                    <a href="<?= APP_URL ?>/student/resources" class="btn btn-outline btn-sm">View Resources</a>
                </div>
            </div>
            
            <div class="action-item">
                <div class="action-icon">
                    <i class="icon">?</i>
                </div>
                <div class="action-content">
                    <h4>Attendance History</h4>
                    <p>View your lab attendance records</p>
                    <a href="<?= APP_URL ?>/student/attendance" class="btn btn-outline btn-sm">View Attendance</a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.dashboard-container {
    display: grid;
    gap: 2rem;
}

.dashboard-header {
    margin-bottom: 1rem;
}

.dashboard-header h1 {
    font-size: 2rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 0.5rem;
}

.dashboard-header p {
    color: #6b7280;
    font-size: 1rem;
}

.schedules-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
}

.schedule-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 0.75rem;
    padding: 1.5rem;
    transition: all 0.3s ease;
}

.schedule-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.schedule-card.attended {
    border-left: 4px solid #10b981;
}

.schedule-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.schedule-header h4 {
    font-size: 1.125rem;
    font-weight: 600;
    color: #1f2937;
    margin: 0;
}

.schedule-details {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    font-size: 0.875rem;
}

.detail-label {
    color: #6b7280;
    font-weight: 500;
}

.schedule-actions {
    display: flex;
    justify-content: flex-end;
}

.submissions-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.submission-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background: #f9fafb;
    border-radius: 0.5rem;
    border-left: 4px solid #3b82f6;
}

.submission-info h4 {
    font-size: 1rem;
    font-weight: 600;
    color: #1f2937;
    margin: 0 0 0.5rem 0;
}

.submission-meta {
    display: flex;
    gap: 1rem;
    font-size: 0.75rem;
    color: #6b7280;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.submission-status {
    text-align: right;
}

.submission-date {
    display: block;
    color: #6b7280;
    font-size: 0.75rem;
    margin-top: 0.25rem;
}

.quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
}

.action-item {
    display: flex;
    gap: 1rem;
    padding: 1.5rem;
    background: #f9fafb;
    border-radius: 0.75rem;
    border: 1px solid #e5e7eb;
}

.action-icon {
    width: 3rem;
    height: 3rem;
    background: #3b82f6;
    border-radius: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.action-content h4 {
    font-size: 1rem;
    font-weight: 600;
    color: #1f2937;
    margin: 0 0 0.5rem 0;
}

.action-content p {
    color: #6b7280;
    font-size: 0.875rem;
    margin: 0 0 1rem 0;
}

.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: #6b7280;
}

.empty-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-state h3 {
    font-size: 1.25rem;
    font-weight: 600;
    color: #4b5563;
    margin: 0 0 0.5rem 0;
}

.empty-state p {
    margin: 0;
}

.badge {
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: uppercase;
}

.badge-info {
    background: #dbeafe;
    color: #1e40af;
}

.badge-success {
    background: #d1fae5;
    color: #065f46;
}

.badge-warning {
    background: #fef3c7;
    color: #92400e;
}

.btn {
    padding: 0.5rem 1rem;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    font-weight: 500;
    text-decoration: none;
    border: 1px solid transparent;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-primary {
    background: #3b82f6;
    color: white;
}

.btn-primary:hover:not(:disabled) {
    background: #2563eb;
}

.btn-success {
    background: #10b981;
    color: white;
}

.btn-outline {
    background: transparent;
    color: #3b82f6;
    border-color: #3b82f6;
}

.btn-outline:hover:not(:disabled) {
    background: #3b82f6;
    color: white;
}

.btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.75rem;
}
</style>
