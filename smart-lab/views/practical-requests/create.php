<div class="card">
    <div class="card-header">
        <div class="card-header-content">
            <span>📝 Request Practical Redo</span>
            <a href="<?= APP_URL ?>/practical-requests" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Requests
            </a>
        </div>
    </div>
    
    <div class="card-body">
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="<?= APP_URL ?>/practical-requests/create" class="form">
            <div class="form-group">
                <label for="practical_id">Select Practical *</label>
                <select name="practical_id" id="practical_id" class="form-control" required>
                    <option value="">Choose a practical to redo...</option>
                    <?php foreach ($practicals as $practical): ?>
                        <option value="<?= $practical['id'] ?>">
                            <?= htmlspecialchars($practical['title']) ?> 
                            (<?= htmlspecialchars($practical['lab_name']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="form-help">
                    Select a practical you would like to redo. Only completed and published practicals are available.
                </small>
            </div>
            
            <div class="form-group">
                <label for="reason">Reason for Request *</label>
                <textarea name="reason" id="reason" class="form-control" rows="4" required
                          placeholder="Please explain why you need to redo this practical..."><?= htmlspecialchars($_POST['reason'] ?? '') ?></textarea>
                <small class="form-help">
                    Be specific about why you need to redo this practical (e.g., missed session, want to improve grade, technical issues, etc.)
                </small>
            </div>
            
            <div class="form-group">
                <label for="preferred_lab">Preferred Lab (Optional)</label>
                <select name="preferred_lab" id="preferred_lab" class="form-control">
                    <option value="">No preference</option>
                    <?php 
                    // Get available labs
                    $labsModel = new \PracticalModel();
                    $labs = $labsModel->getLabs();
                    foreach ($labs as $lab): 
                    ?>
                        <option value="<?= $lab['id'] ?>">
                            <?= htmlspecialchars($lab['name']) ?> (<?= htmlspecialchars($lab['lab_code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="form-help">
                    If you have a preference for which lab to perform this practical in, select it here.
                </small>
            </div>
            
            <div class="form-group">
                <label for="urgency">Urgency Level</label>
                <select name="urgency" id="urgency" class="form-control">
                    <option value="low" <?= ($_POST['urgency'] ?? '') === 'low' ? 'selected' : '' ?>>Low</option>
                    <option value="normal" <?= ($_POST['urgency'] ?? '') === 'normal' ? 'selected' : '' ?>>Normal</option>
                    <option value="high" <?= ($_POST['urgency'] ?? '') === 'high' ? 'selected' : '' ?>>High</option>
                    <option value="urgent" <?= ($_POST['urgency'] ?? '') === 'urgent' ? 'selected' : '' ?>>Urgent</option>
                </select>
                <small class="form-help">
                    Select the urgency level of your request. This helps administrators prioritize approvals.
                </small>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Submit Request
                </button>
                <a href="<?= APP_URL ?>/practical-requests" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
        
        <div class="info-section">
            <h4><i class="fas fa-info-circle"></i> About Practical Requests</h4>
            <ul>
                <li>You can request to redo practicals that have already been completed</li>
                <li>Requests are subject to approval by administrators</li>
                <li>You can specify a preferred lab for lab hopping (if available)</li>
                <li>Approved requests will be scheduled based on availability</li>
                <li>You can cancel pending requests at any time</li>
            </ul>
        </div>
    </div>
</div>

<style>
.form {
    max-width: 600px;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: var(--dark);
}

.form-control {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 1rem;
    transition: border-color 0.3s;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.form-help {
    display: block;
    margin-top: 0.25rem;
    font-size: 0.875rem;
    color: var(--gray);
}

.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
}

.info-section {
    margin-top: 2rem;
    padding: 1.5rem;
    background: var(--light);
    border-radius: 8px;
    border-left: 4px solid var(--primary);
}

.info-section h4 {
    margin-bottom: 1rem;
    color: var(--primary);
}

.info-section ul {
    margin: 0;
    padding-left: 1.5rem;
}

.info-section li {
    margin-bottom: 0.5rem;
    color: var(--gray);
}
</style>
