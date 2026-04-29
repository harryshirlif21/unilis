<div class="card">
    <div class="card-header">🗄️ Asset Details</div>
    
    <div class="asset-info">
        <h2><?= htmlspecialchars($asset['name']) ?></h2>
        <div class="asset-meta">
            <span><strong>Type:</strong> <span class="badge badge-<?= $asset['type'] ?>"><?= ucfirst($asset['type']) ?></span></span>
            <span><strong>Lab:</strong> <?= htmlspecialchars($asset['lab_name'] ?? 'Not assigned') ?></span>
            <span><strong>Quantity:</strong> <?= $asset['quantity'] ?> <?= $asset['unit'] ?? 'units' ?></span>
            <span><strong>Status:</strong> <span class="badge badge-<?= $asset['status'] ?>"><?= ucfirst($asset['status']) ?></span></span>
            <span><strong>Added:</strong> <?= date('M j, Y', strtotime($asset['created_at'])) ?></span>
            <span><strong>Last Updated:</strong> <?= date('M j, Y H:i', strtotime($asset['updated_at'])) ?></span>
        </div>
    </div>
    
    <?php if (!empty($asset['description'])): ?>
        <div class="asset-section">
            <h3>Description</h3>
            <div class="content-display">
                <?= nl2br(htmlspecialchars($asset['description'])) ?>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="asset-section">
        <h3>Inventory Information</h3>
        <div class="inventory-grid">
            <div class="inventory-item">
                <strong>Current Quantity:</strong>
                <span class="quantity-value <?= $asset['quantity'] <= 5 ? 'low-stock' : '' ?>">
                    <?= $asset['quantity'] ?> <?= $asset['unit'] ?? 'units' ?>
                </span>
                <?php if ($asset['quantity'] <= 5): ?>
                    <small class="text-warning">Low stock alert</small>
                <?php endif; ?>
            </div>
            
            <div class="inventory-item">
                <strong>Unit of Measure:</strong>
                <span><?= htmlspecialchars($asset['unit'] ?? 'units') ?></span>
            </div>
            
            <div class="inventory-item">
                <strong>Location:</strong>
                <span><?= htmlspecialchars($asset['location'] ?? 'Not specified') ?></span>
            </div>
            
            <div class="inventory-item">
                <strong>Serial Number:</strong>
                <span><?= htmlspecialchars($asset['serial_number'] ?? 'N/A') ?></span>
            </div>
            
            <div class="inventory-item">
                <strong>Purchase Date:</strong>
                <span><?= $asset['purchase_date'] ? date('M j, Y', strtotime($asset['purchase_date'])) : 'N/A' ?></span>
            </div>
            
            <div class="inventory-item">
                <strong>Warranty:</strong>
                <span><?= $asset['warranty_expiry'] ? date('M j, Y', strtotime($asset['warranty_expiry'])) : 'N/A' ?></span>
            </div>
        </div>
    </div>
    
    <div class="asset-section">
        <h3>Blockchain Transaction History</h3>
        <div class="blockchain-info">
            <p><strong>Asset ID:</strong> <code><?= htmlspecialchars($asset['id']) ?></code></p>
            <div class="blockchain-stats">
                <div class="stat-item">
                    <span class="stat-label">Total Transactions:</span>
                    <span class="stat-value"><?= count($blockchainHistory) ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Last Transaction:</span>
                    <span class="stat-value">
                        <?php if (!empty($blockchainHistory)): ?>
                            <?= date('M j, Y H:i', strtotime($blockchainHistory[0]['timestamp'])) ?>
                        <?php else: ?>
                            No transactions yet
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        </div>
        
        <?php if (!empty($blockchainHistory)): ?>
            <div class="transaction-list">
                <h4>Recent Transactions</h4>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Action</th>
                                <th>User</th>
                                <th>Timestamp</th>
                                <th>Block Hash</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($blockchainHistory, 0, 5) as $transaction): ?>
                                <tr>
                                    <td>
                                        <span class="badge badge-<?= $transaction['action'] ?>">
                                            <?= ucfirst($transaction['action']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($transaction['user_name'] ?? 'System') ?></td>
                                    <td><?= date('M j, Y H:i', strtotime($transaction['timestamp'])) ?></td>
                                    <td><code><?= substr($transaction['hash'], 0, 16) ?>...</code></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <a href="<?= APP_URL ?>/assets/history/<?= $asset['id'] ?>" class="btn btn-outline">View Full History</a>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="asset-actions">
        <?php if (Auth::role() === 'admin' || Auth::role() === 'technician'): ?>
            <a href="<?= APP_URL ?>/assets/edit/<?= $asset['id'] ?>" class="btn btn-primary">Edit Asset</a>
            
            <?php if ($asset['status'] === 'available'): ?>
                <a href="<?= APP_URL ?>/assets/issue/<?= $asset['id'] ?>" class="btn btn-success">Issue Asset</a>
            <?php elseif ($asset['status'] === 'issued'): ?>
                <a href="<?= APP_URL ?>/assets/return/<?= $asset['id'] ?>" class="btn btn-warning">Return Asset</a>
            <?php endif; ?>
            
            <?php if ($asset['status'] === 'available' || $asset['status'] === 'issued'): ?>
                <a href="<?= APP_URL ?>/assets/maintenance/<?= $asset['id'] ?>" class="btn btn-secondary">Set Maintenance</a>
            <?php elseif ($asset['status'] === 'maintenance'): ?>
                <a href="<?= APP_URL ?>/assets/available/<?= $asset['id'] ?>" class="btn btn-success">Set Available</a>
            <?php endif; ?>
            
            <?php if ($asset['status'] !== 'disposed'): ?>
                <a href="<?= APP_URL ?>/assets/dispose/<?= $asset['id'] ?>" class="btn btn-danger">Dispose Asset</a>
            <?php endif; ?>
        <?php endif; ?>
        
        <a href="<?= APP_URL ?>/assets" class="btn btn-outline">Back to Assets</a>
    </div>
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

.asset-section {
    margin: 2rem 0;
}

.asset-section h3 {
    margin-bottom: 1rem;
    color: var(--primary);
}

.content-display {
    padding: 1.5rem;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 0.5rem;
    white-space: pre-wrap;
}

.inventory-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}

.inventory-item {
    padding: 1rem;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 0.5rem;
}

.inventory-item strong {
    display: block;
    margin-bottom: 0.5rem;
    color: var(--text2);
    font-size: 0.875rem;
}

.quantity-value {
    font-size: 1.25rem;
    font-weight: bold;
    color: var(--primary);
}

.quantity-value.low-stock {
    color: var(--warning);
}

.blockchain-info {
    padding: 1rem;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 0.5rem;
    margin-bottom: 1rem;
}

.blockchain-stats {
    display: flex;
    gap: 2rem;
    margin-top: 1rem;
}

.stat-item {
    display: flex;
    flex-direction: column;
}

.stat-label {
    font-size: 0.875rem;
    color: var(--text2);
}

.stat-value {
    font-weight: 600;
    color: var(--text);
}

.transaction-list {
    margin-top: 1.5rem;
}

.transaction-list h4 {
    margin-bottom: 1rem;
}

.asset-actions {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
    flex-wrap: wrap;
}

.badge {
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    font-weight: 500;
}

.badge-available {
    background: #dcfce7;
    color: #166534;
}

.badge-issued {
    background: #dbeafe;
    color: #1e40af;
}

.badge-maintenance {
    background: #fef3c7;
    color: #92400e;
}

.badge-disposed {
    background: #f3f4f6;
    color: #6b7280;
}

.badge-equipment {
    background: #e0e7ff;
    color: #3730a3;
}

.badge-chemical {
    background: #fef3c7;
    color: #92400e;
}

.badge-consumable {
    background: #dcfce7;
    color: #166534;
}

.badge-tool {
    background: #f3e8ff;
    color: #6b21a8;
}

.badge-registered {
    background: #dbeafe;
    color: #1e40af;
}

.badge-issued {
    background: #fef3c7;
    color: #92400e;
}

.badge-returned {
    background: #dcfce7;
    color: #166534;
}

.badge-transferred {
    background: #e0e7ff;
    color: #3730a3;
}

.badge-used {
    background: #f3f4f6;
    color: #6b7280;
}

.badge-disposed {
    background: #fef2f2;
    color: #dc2626;
}

.btn-outline {
    background: transparent;
    border: 1px solid var(--border);
    color: var(--text);
}

.btn-outline:hover {
    background: var(--background);
}

.text-warning {
    color: var(--warning);
}

code {
    background: var(--background);
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-family: monospace;
    font-size: 0.875rem;
}
</style>
