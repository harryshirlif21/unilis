<div class="card">
    <div class="card-header">
        <div>
            <div class="card-title">📅 Lab Schedule</div>
            <div class="card-sub">View and manage laboratory session schedules</div>
        </div>
        <?php if (Auth::role() === 'lecturer' || Auth::role() === 'admin'): ?>
            <a href="<?= APP_URL ?>/practicals/create" class="btn btn-primary">Schedule Practical</a>
        <?php endif; ?>
    </div>
    
    <?php if (!empty($stats)): ?>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= $stats['total_sessions'] ?? 0 ?></div>
                <div class="stat-label">Total Sessions</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['today_sessions'] ?? 0 ?></div>
                <div class="stat-label">Today</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['this_week'] ?? 0 ?></div>
                <div class="stat-label">This Week</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['active_labs'] ?? 0 ?></div>
                <div class="stat-label">Active Labs</div>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="schedule-controls">
        <div class="view-options">
            <div class="form-group">
                <label class="form-label">View Type</label>
                <select id="view-type" class="form-select">
                    <option value="day">Day View</option>
                    <option value="week">Week View</option>
                    <option value="month">Month View</option>
                    <option value="list">List View</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Laboratory</label>
                <select id="lab-filter" class="form-select">
                    <option value="">All Labs</option>
                    <?php foreach ($labs as $lab): ?>
                        <option value="<?= $lab['id'] ?>"><?= htmlspecialchars($lab['name']) ?> (<?= htmlspecialchars($lab['lab_code']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Date</label>
                <input type="date" id="schedule-date" class="form-input" value="<?= date('Y-m-d') ?>">
            </div>
        </div>
        
        <div class="navigation-controls">
            <button id="prev-period" class="btn btn-outline">← Previous</button>
            <button id="today-btn" class="btn btn-primary">Today</button>
            <button id="next-period" class="btn btn-outline">Next →</button>
        </div>
    </div>
    
    <!-- Day View -->
    <div id="day-view" class="schedule-view">
        <h3><?= date('l, F j, Y', strtotime($currentDate ?? 'today')) ?></h3>
        
        <?php if (empty($todaySchedule)): ?>
            <div class="alert alert-info">
                No practical sessions are scheduled for today.
            </div>
        <?php else: ?>
            <div class="timeline">
                <?php foreach ($todaySchedule as $session): ?>
                    <div class="timeline-item">
                        <div class="timeline-time">
                            <?= date('H:i', strtotime($session['scheduled_date'])) ?>
                            <br>
                            <small><?= $session['duration_hours'] ?? 0 ?>h</small>
                        </div>
                        <div class="timeline-content">
                            <div class="timeline-header">
                                <h4><?= htmlspecialchars($session['title']) ?></h4>
                                <span class="badge badge-<?= $session['status'] ?>">
                                    <?= ucfirst($session['status']) ?>
                                </span>
                            </div>
                            <div class="timeline-details">
                                <p><strong>Laboratory:</strong> <?= htmlspecialchars($session['lab_name']) ?> (<?= htmlspecialchars($session['lab_code']) ?>)</p>
                                <p><strong>Lecturer:</strong> <?= htmlspecialchars($session['lecturer_name'] ?? 'Not assigned') ?></p>
                                <p><strong>Max Students:</strong> <?= $session['max_students'] ?? 'N/A' ?></p>
                            </div>
                            <?php if ($session['status'] === 'published'): ?>
                                <div class="timeline-actions">
                                    <a href="<?= APP_URL ?>/practicals/view/<?= $session['id'] ?>" class="btn btn-primary btn-sm">View Details</a>
                                </div>
                            <?php elseif ($session['status'] === 'completed'): ?>
                                <div class="timeline-actions">
                                    <a href="<?= APP_URL ?>/practicals/end-session/<?= $session['id'] ?>" class="btn btn-warning btn-sm">End Session</a>
                                    <a href="<?= APP_URL ?>/practicals/view/<?= $session['id'] ?>" class="btn btn-primary btn-sm">View Details</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Week View -->
    <div id="week-view" class="schedule-view" style="display: none;">
        <h3>Week of <?= date('F j', strtotime('this week')) ?></h3>
        
        <div class="week-grid">
            <?php for ($i = 0; $i < 7; $i++): ?>
                <?php 
                $dayDate = date('Y-m-d', strtotime('this week monday +' . $i . ' days'));
                $dayName = date('l', strtotime($dayDate));
                $daySessions = array_filter($weekSchedule ?? [], fn($s) => $s['scheduled_date'] === $dayDate);
                ?>
                <div class="day-column">
                    <div class="day-header">
                        <h4><?= $dayName ?></h4>
                        <small><?= date('M j', strtotime($dayDate)) ?></small>
                    </div>
                    <div class="day-sessions">
                        <?php if (empty($daySessions)): ?>
                            <div class="no-sessions">No sessions</div>
                        <?php else: ?>
                            <?php foreach ($daySessions as $session): ?>
                                <div class="session-card">
                                    <div class="session-time">
                                        <?= date('H:i', strtotime($session['scheduled_date'])) ?>
                                    </div>
                                    <div class="session-title">
                                        <?= htmlspecialchars($session['title']) ?>
                                    </div>
                                    <div class="session-lab">
                                        <?= htmlspecialchars($session['lab_code']) ?>
                                    </div>
                                    <span class="badge badge-<?= $session['status'] ?> badge-sm">
                                        <?= ucfirst($session['status']) ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
    </div>
    
    <!-- Month View -->
    <div id="month-view" class="schedule-view" style="display: none;">
        <h3><?= date('F Y', strtotime($currentDate ?? 'today')) ?></h3>
        
        <div class="month-calendar">
            <div class="calendar-header">
                <div class="day-name">Sun</div>
                <div class="day-name">Mon</div>
                <div class="day-name">Tue</div>
                <div class="day-name">Wed</div>
                <div class="day-name">Thu</div>
                <div class="day-name">Fri</div>
                <div class="day-name">Sat</div>
            </div>
            
            <?php
            $baseDate = $currentDate ?? 'today';
            $monthStart = date('Y-m-01', strtotime($baseDate));
            $firstDay = date('w', strtotime($monthStart));
            $daysInMonth = date('t', strtotime($baseDate));
            
            // Empty cells for days before month starts
            for ($i = 0; $i < $firstDay; $i++): ?>
                <div class="calendar-day empty"></div>
            <?php endfor; ?>
            
            <?php
            // Days of the month
            for ($day = 1; $day <= $daysInMonth; $day++): ?>
                <?php 
                $dateStr = date('Y-m-d', strtotime($monthStart . sprintf('-%02d', $day)));
                $daySessions = array_filter($monthSchedule ?? [], fn($s) => $s['scheduled_date'] === $dateStr);
                $isToday = $dateStr === date('Y-m-d');
                ?>
                <div class="calendar-day <?= $isToday ? 'today' : '' ?>">
                    <div class="calendar-date"><?= $day ?></div>
                    <div class="calendar-sessions">
                        <?php if (!empty($daySessions)): ?>
                            <div class="session-indicator">
                                <?= count($daySessions) ?> session<?= count($daySessions) > 1 ? 's' : '' ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
    </div>
    
    <!-- List View -->
    <div id="list-view" class="schedule-view" style="display: none;">
        <h3>All Scheduled Sessions</h3>
        
        <?php if (empty($allSchedule)): ?>
            <div class="alert alert-info">
                No practical sessions are scheduled.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Title</th>
                            <th>Laboratory</th>
                            <th>Lecturer</th>
                            <th>Max Students</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allSchedule as $session): ?>
                            <tr>
                                <td><?= date('M j, Y', strtotime($session['scheduled_date'])) ?></td>
                                <td>
                                    <?= date('H:i', strtotime($session['scheduled_date'])) ?>
                                    <br><small><?= $session['duration_hours'] ?? 0 ?>h</small>
                                </td>
                                <td><?= htmlspecialchars($session['title']) ?></td>
                                <td>
                                    <?= htmlspecialchars($session['lab_name']) ?>
                                    <br><small><?= htmlspecialchars($session['lab_code']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($session['lecturer_name'] ?? 'Not assigned') ?></td>
                                <td><?= $session['max_students'] ?? 'N/A' ?></td>
                                <td>
                                    <span class="badge badge-<?= $session['status'] ?>">
                                        <?= ucfirst($session['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="<?= APP_URL ?>/practicals/view/<?= $session['id'] ?>" class="btn btn-primary btn-sm">View</a>
                                    <?php if (Auth::role() === 'lecturer' && $session['lecturer_id'] === Auth::id()): ?>
                                        <a href="<?= APP_URL ?>/practicals/edit/<?= $session['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.schedule-controls {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    gap: 2rem;
    margin-bottom: 2rem;
    padding: 1rem;
    background: var(--background);
    border-radius: 0.5rem;
}

.view-options {
    display: flex;
    gap: 1rem;
}

.view-options .form-group {
    margin-bottom: 0;
}

.navigation-controls {
    display: flex;
    gap: 0.5rem;
}

.schedule-view {
    margin-bottom: 2rem;
}

.timeline {
    position: relative;
    padding-left: 2rem;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 2px;
    background: var(--border);
}

.timeline-item {
    position: relative;
    margin-bottom: 2rem;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: -2.4rem;
    top: 0.5rem;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: var(--primary);
    border: 2px solid var(--surface);
}

.timeline-time {
    position: absolute;
    left: -8rem;
    top: 0;
    width: 5rem;
    text-align: right;
    font-weight: 600;
    color: var(--primary);
}

.timeline-content {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 0.5rem;
    padding: 1.5rem;
    box-shadow: var(--shadow);
}

.timeline-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.timeline-header h4 {
    margin: 0;
    color: var(--text);
}

.timeline-details p {
    margin: 0.5rem 0;
    font-size: 0.875rem;
    color: var(--text2);
}

.timeline-details strong {
    color: var(--text);
}

.timeline-actions {
    margin-top: 1rem;
    display: flex;
    gap: 0.5rem;
}

.week-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 1rem;
    margin-top: 1rem;
}

.day-column {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 0.5rem;
    overflow: hidden;
}

.day-header {
    background: var(--background);
    padding: 0.75rem;
    text-align: center;
    border-bottom: 1px solid var(--border);
}

.day-header h4 {
    margin: 0 0 0.25rem 0;
    font-size: 0.875rem;
    color: var(--text);
}

.day-header small {
    color: var(--text2);
    font-size: 0.75rem;
}

.day-sessions {
    padding: 0.5rem;
    min-height: 200px;
}

.no-sessions {
    text-align: center;
    color: var(--text3);
    font-size: 0.875rem;
    padding: 2rem 0;
}

.session-card {
    background: var(--background);
    border: 1px solid var(--border);
    border-radius: 0.25rem;
    padding: 0.75rem;
    margin-bottom: 0.5rem;
}

.session-time {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--primary);
    margin-bottom: 0.25rem;
}

.session-title {
    font-size: 0.875rem;
    font-weight: 500;
    margin-bottom: 0.25rem;
}

.session-lab {
    font-size: 0.75rem;
    color: var(--text2);
    margin-bottom: 0.5rem;
}

.badge-sm {
    font-size: 0.625rem;
    padding: 0.125rem 0.375rem;
}

.month-calendar {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 1px;
    background: var(--border);
    border: 1px solid var(--border);
    border-radius: 0.5rem;
    overflow: hidden;
    margin-top: 1rem;
}

.calendar-header {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    background: var(--background);
}

.day-name {
    padding: 0.75rem;
    text-align: center;
    font-weight: 600;
    font-size: 0.875rem;
    color: var(--text);
}

.calendar-day {
    background: var(--surface);
    min-height: 80px;
    padding: 0.5rem;
    position: relative;
}

.calendar-day.empty {
    background: var(--background);
}

.calendar-day.today {
    background: #eff6ff;
    border: 2px solid var(--primary);
}

.calendar-date {
    font-weight: 600;
    color: var(--text);
}

.calendar-sessions {
    margin-top: 0.25rem;
}

.session-indicator {
    background: var(--primary);
    color: white;
    font-size: 0.625rem;
    padding: 0.125rem 0.375rem;
    border-radius: 0.25rem;
    text-align: center;
}

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

.badge-published {
    background: #dcfce7;
    color: #166534;
}

.badge-ongoing {
    background: #dbeafe;
    color: #1e40af;
}

.badge-completed {
    background: #f3f4f6;
    color: #6b7280;
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

.table-responsive {
    overflow-x: auto;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const viewType = document.getElementById('view-type');
    const scheduleDate = document.getElementById('schedule-date');
    const labFilter = document.getElementById('lab-filter');
    
    // View switching
    function switchView(viewName) {
        document.querySelectorAll('.schedule-view').forEach(view => {
            view.style.display = 'none';
        });
        document.getElementById(viewName + '-view').style.display = 'block';
    }
    
    viewType.addEventListener('change', function() {
        switchView(this.value);
    });
    
    // Date navigation
    document.getElementById('prev-period').addEventListener('click', function() {
        const current = new Date(scheduleDate.value);
        const viewTypeValue = viewType.value;
        
        if (viewTypeValue === 'day') {
            current.setDate(current.getDate() - 1);
        } else if (viewTypeValue === 'week') {
            current.setDate(current.getDate() - 7);
        } else if (viewTypeValue === 'month') {
            current.setMonth(current.getMonth() - 1);
        }
        
        scheduleDate.value = current.toISOString().split('T')[0];
        // In a real app, this would trigger a page reload or AJAX call
        location.reload();
    });
    
    document.getElementById('next-period').addEventListener('click', function() {
        const current = new Date(scheduleDate.value);
        const viewTypeValue = viewType.value;
        
        if (viewTypeValue === 'day') {
            current.setDate(current.getDate() + 1);
        } else if (viewTypeValue === 'week') {
            current.setDate(current.getDate() + 7);
        } else if (viewTypeValue === 'month') {
            current.setMonth(current.getMonth() + 1);
        }
        
        scheduleDate.value = current.toISOString().split('T')[0];
        location.reload();
    });
    
    document.getElementById('today-btn').addEventListener('click', function() {
        scheduleDate.value = new Date().toISOString().split('T')[0];
        location.reload();
    });
});
</script>
