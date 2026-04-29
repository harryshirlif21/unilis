<div class="card">
    <div class="card-header">📤 Issue Asset</div>
    
    <div class="asset-info">
        <h2><?= htmlspecialchars($asset['name']) ?></h2>
        <div class="asset-meta">
            <span><strong>Type:</strong> <?= ucfirst($asset['type']) ?></span>
            <span><strong>Available Quantity:</strong> <?= $asset['quantity'] ?> <?= $asset['unit'] ?? 'units' ?></span>
            <span><strong>Lab:</strong> <?= htmlspecialchars($asset['lab_name']) ?></span>
        </div>
    </div>
    
    <form method="POST" action="<?= APP_URL ?>/assets/issue/<?= $asset['id'] ?>">
        <div class="form-group">
            <label class="form-label">Issue To *</label>
            <select name="issued_to" class="form-select" required>
                <option value="">Select person...</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?= $user['id'] ?>">
                        <?= htmlspecialchars($user['full_name']) ?> (<?= htmlspecialchars($user['reg_number']) ?>) - <?= ucfirst($user['role']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label class="form-label">Quantity to Issue *</label>
            <input type="number" name="quantity" class="form-input" min="1" max="<?= $asset['quantity'] ?>" value="1" required>
            <small style="color: var(--text2);">Maximum available: <?= $asset['quantity'] ?> <?= $asset['unit'] ?? 'units' ?></small>
        </div>
        
        <div class="form-group">
            <label class="form-label">Purpose / Project</label>
            <input type="text" name="purpose" class="form-input" placeholder="e.g., Chemistry Lab - Experiment 5">
        </div>
        
        <div class="form-group">
            <label class="form-label">Expected Return Date</label>
            <input type="date" name="expected_return" class="form-input">
            <small style="color: var(--text2);">Optional: Set expected return date</small>
        </div>
        
        <div class="form-group">
            <label class="form-label">Notes</label>
            <textarea name="notes" class="form-textarea" rows="3" placeholder="Any additional notes about this issuance..."></textarea>
        </div>
        
        <div class="blockchain-info">
            <h4>🔗 Blockchain Transaction</h4>
            <p>This asset issuance will be recorded as an immutable blockchain transaction for complete traceability and audit purposes.</p>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-success">Issue Asset</button>
            <a href="<?= APP_URL ?>/assets/view/<?= $asset['id'] ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<style>
.asset-info {
    margin-bottom: 2rem;
    padding: 1rem;
    background: var(--background);
    border-radius: 0.5rem;
}

.asset-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-top: 1rem;
}

.asset-meta span {
    padding: 0.25rem 0.75rem;
    background: var(--surface);
    border-radius: 0.25rem;
    font-size: 0.875rem;
}

.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
}

.blockchain-info {
    margin: 2rem 0;
    padding: 1rem;
    background: #dcfce7;
    border: 1px solid #bbf7d0;
    border-radius: 0.5rem;
}

.blockchain-info h4 {
    margin-bottom: 0.5rem;
    color: #166534;
}

.blockchain-info p {
    margin: 0;
    color: #166534;
}
</style>
