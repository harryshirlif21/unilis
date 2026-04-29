<div class="card">
    <div class="card-header">
        <div class="card-header-content">
            <span>📋 Practical Request Details</span>
            <a href="<?= APP_URL ?>/practical-requests" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Requests
            </a>
        </div>
    </div>
    
    <div class="card-body">
        <div class="request-overview">
            <div class="request-header">
                <h2><?= htmlspecialchars($request['practical_title']) ?></h2>
                <span class="badge badge-<?= $request['status'] ?>">
                    <?= ucfirst($request['status']) ?>
                </span>
            </div>
            
            <div class="request-meta">
                <div class="meta-item">
                    <i class="fas fa-calendar"></i>
                    <span>Requested: <?= date('F j, Y', strtotime($request['created_at'])) ?></span>
                </div>
                <div class="meta-item">
                    <i class="fas fa-clock"></i>
                    <span>Time: <?= date('h:i A', strtotime($request['created_at'])) ?></span>
                </div>
                <div class="meta-item">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>Urgency: <span class="urgency-<?= $request['urgency'] ?>"><?= ucfirst($request['urgency']) ?></span></span>
                </div>
            </div>
        </div>
        
        <div class="request-details">
            <div class="detail-section">
                <h3><i class="fas fa-flask"></i> Practical Information</h3>
                <div class="detail-grid">
                    <div class="detail-item">
                        <label>Title:</label>
                        <span><?= htmlspecialchars($request['practical_title']) ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Description:</label>
                        <span><?= nl2br(htmlspecialchars($request['practical_description'] ?? 'No description available')) ?></span>
                    </div>
                    <?php if ($request['preferred_lab_name']): ?>
                        <div class="detail-item">
                            <label>Preferred Lab:</label>
                            <span><?= htmlspecialchars($request['preferred_lab_name']) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="detail-section">
                <h3><i class="fas fa-comment-alt"></i> Request Reason</h3>
                <div class="reason-content">
                    <?= nl2br(htmlspecialchars($request['reason'])) ?>
                </div>
            </div>
            
            <?php if ($request['admin_notes']): ?>
                <div class="detail-section">
                    <h3><i class="fas fa-sticky-note"></i> Administrator Notes</h3>
                    <div class="admin-notes">
                        <?= nl2br(htmlspecialchars($request['admin_notes'])) ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="detail-section">
                <h3><i class="fas fa-history"></i> Request Timeline</h3>
                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-marker created"></div>
                        <div class="timeline-content">
                            <strong>Request Submitted</strong>
                            <span><?= date('F j, Y at h:i A', strtotime($request['created_at'])) ?></span>
                        </div>
                    </div>
                    
                    <?php if ($request['updated_at'] && $request['updated_at'] !== $request['created_at']): ?>
                        <div class="timeline-item">
                            <div class="timeline-marker updated"></div>
                            <div class="timeline-content">
                                <strong>Last Updated</strong>
                                <span><?= date('F j, Y at h:i A', strtotime($request['updated_at'])) ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="request-actions">
            <?php if ($request['status'] === 'pending'): ?>
                <a href="<?= APP_URL ?>/practical-requests/cancel/<?= $request['id'] ?>" 
                   class="btn btn-danger"
                   onclick="return confirm('Are you sure you want to cancel this request?')">
                    <i class="fas fa-times"></i> Cancel Request
                </a>
            <?php endif; ?>
            
            <a href="<?= APP_URL ?>/practical-requests" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Requests
            </a>
        </div>
    </div>
</div>

<style>
.request-overview {
    padding: 1.5rem;
    background: linear-gradient(135deg, var(--primary-light), var(--secondary));
    color: white;
    border-radius: 12px;
    margin-bottom: 2rem;
}

.request-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.request-header h2 {
    margin: 0;
    color: white;
}

.request-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 2rem;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
}

.urgency-low { color: #10b981; }
.urgency-normal { color: #fbbf24; }
.urgency-high { color: #f97316; }
.urgency-urgent { color: #ef4444; }

.detail-section {
    margin-bottom: 2rem;
}

.detail-section h3 {
    margin-bottom: 1rem;
    color: var(--dark);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.detail-grid {
    display: grid;
    gap: 1rem;
}

.detail-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.detail-item label {
    font-weight: 600;
    color: var(--gray);
    font-size: 0.875rem;
}

.detail-item span {
    color: var(--dark);
}

.reason-content, .admin-notes {
    background: var(--light);
    padding: 1rem;
    border-radius: 8px;
    border-left: 4px solid var(--primary);
    line-height: 1.6;
}

.admin-notes {
    border-left-color: var(--warning);
}

.timeline {
    position: relative;
    padding-left: 2rem;
}

.timeline-item {
    position: relative;
    padding-bottom: 1.5rem;
}

.timeline-item:last-child {
    padding-bottom: 0;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: -1.5rem;
    top: 0;
    bottom: 0;
    width: 2px;
    background: var(--border);
}

.timeline-item:last-child::before {
    display: none;
}

.timeline-marker {
    position: absolute;
    left: -1.75rem;
    top: 0;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid white;
}

.timeline-marker.created {
    background: var(--primary);
}

.timeline-marker.updated {
    background: var(--secondary);
}

.timeline-content {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.timeline-content strong {
    color: var(--dark);
}

.timeline-content span {
    font-size: 0.875rem;
    color: var(--gray);
}

.request-actions {
    display: flex;
    gap: 1rem;
    padding-top: 2rem;
    border-top: 1px solid var(--border);
}

.badge-pending { background: var(--warning); color: white; }
.badge-approved { background: var(--success); color: white; }
.badge-rejected { background: var(--error); color: white; }
.badge-cancelled { background: var(--gray); color: white; }
</style>
