<div class="card">
    <div class="card-header">
        <div class="card-header-content">
            <span>📋 My Practical Requests</span>
            <a href="<?= APP_URL ?>/practical-requests/create" class="btn btn-primary">
                <i class="fas fa-plus"></i> New Request
            </a>
        </div>
    </div>
    
    <div class="card-body">
        <?php if (empty($requests)): ?>
            <div class="empty-state">
                <div class="empty-icon">📝</div>
                <h3>No Practical Requests</h3>
                <p>You haven't submitted any practical requests yet.</p>
                <a href="<?= APP_URL ?>/practical-requests/create" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Create Your First Request
                </a>
            </div>
        <?php else: ?>
            <div class="requests-grid">
                <?php foreach ($requests as $request): ?>
                    <div class="request-card">
                        <div class="request-header">
                            <h4><?= htmlspecialchars($request['practical_title']) ?></h4>
                            <span class="badge badge-<?= $request['status'] ?>">
                                <?= ucfirst($request['status']) ?>
                            </span>
                        </div>
                        
                        <div class="request-details">
                            <div class="request-meta">
                                <span><i class="fas fa-calendar"></i> 
                                    <?= date('M j, Y', strtotime($request['created_at'])) ?>
                                </span>
                                <span><i class="fas fa-clock"></i> 
                                    <?= date('h:i A', strtotime($request['created_at'])) ?>
                                </span>
                            </div>
                            
                            <div class="request-reason">
                                <strong>Reason:</strong>
                                <p><?= nl2br(htmlspecialchars(substr($request['reason'], 0, 150))) ?>...</p>
                            </div>
                            
                            <?php if ($request['preferred_lab_name']): ?>
                                <div class="request-lab">
                                    <strong>Preferred Lab:</strong> 
                                    <?= htmlspecialchars($request['preferred_lab_name']) ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="request-urgency">
                                <strong>Urgency:</strong> 
                                <span class="urgency-<?= $request['urgency'] ?>">
                                    <?= ucfirst($request['urgency']) ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="request-actions">
                            <a href="<?= APP_URL ?>/practical-requests/view/<?= $request['id'] ?>" 
                               class="btn btn-sm btn-outline">
                                <i class="fas fa-eye"></i> View Details
                            </a>
                            
                            <?php if ($request['status'] === 'pending'): ?>
                                <a href="<?= APP_URL ?>/practical-requests/cancel/<?= $request['id'] ?>" 
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('Are you sure you want to cancel this request?')">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.requests-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
    gap: 1.5rem;
    margin-top: 1.5rem;
}

.request-card {
    background: white;
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 1.5rem;
    transition: all 0.3s;
}

.request-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
}

.request-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.request-header h4 {
    margin: 0;
    color: var(--dark);
    font-size: 1.1rem;
}

.request-meta {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
    font-size: 0.875rem;
    color: var(--gray);
}

.request-reason {
    margin-bottom: 1rem;
}

.request-reason p {
    margin: 0.5rem 0 0 0;
    color: var(--gray);
    line-height: 1.5;
}

.request-lab, .request-urgency {
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.urgency-low { color: var(--success); }
.urgency-normal { color: var(--primary); }
.urgency-high { color: var(--warning); }
.urgency-urgent { color: var(--error); }

.request-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border);
}
