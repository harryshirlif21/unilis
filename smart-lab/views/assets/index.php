<div class="card">
    <div class="card-header">
        <div>
            <div class="card-title">🗄️ Lab Assets</div>
            <div class="card-sub">Manage laboratory equipment and supplies</div>
        </div>
        <?php if (Auth::role() === 'admin' || Auth::role() === 'technician'): ?>
            <a href="<?= APP_URL ?>/assets/create" class="btn btn-primary">Add Asset</a>
        <?php endif; ?>
    </div>
    
    <?php if (!empty($stats)): ?>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= $stats['total_assets'] ?? 0 ?></div>
                <div class="stat-label">Total Assets</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['available'] ?? 0 ?></div>
                <div class="stat-label">Available</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['issued'] ?? 0 ?></div>
                <div class="stat-label">Issued</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['maintenance'] ?? 0 ?></div>
                <div class="stat-label">Under Maintenance</div>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="filters-section">
        <div class="filter-controls">
            <div class="form-group">
                <label class="form-label">Search</label>
                <input type="text" id="search-input" class="form-input" placeholder="Search assets...">
            </div>
            
            <div class="form-group">
                <label class="form-label">Status</label>
                <select id="status-filter" class="form-select">
                    <option value="">All Status</option>
                    <option value="available">Available</option>
                    <option value="issued">Issued</option>
                    <option value="maintenance">Under Maintenance</option>
                    <option value="disposed">Disposed</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Lab</label>
                <select id="lab-filter" class="form-select">
                    <option value="">All Labs</option>
                    <?php foreach ($labs as $lab): ?>
                        <option value="<?= $lab['id'] ?>"><?= htmlspecialchars($lab['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Type</label>
                <select id="type-filter" class="form-select">
                    <option value="">All Types</option>
                    <option value="equipment">Equipment</option>
                    <option value="chemical">Chemical</option>
                    <option value="consumable">Consumable</option>
                    <option value="tool">Tool</option>
                </select>
            </div>
        </div>
    </div>
    
    <?php if (empty($assets)): ?>
        <div class="alert alert-info">
            <?php if (Auth::role() === 'admin' || Auth::role() === 'technician'): ?>
                No assets have been registered yet. <a href="<?= APP_URL ?>/assets/create">Add your first asset</a> to get started.
            <?php else: ?>
                No assets are currently available.
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table" id="assets-table">
                <thead>
                    <tr>
                        <th>Asset Name</th>
                        <th>Type</th>
                        <th>Lab</th>
                        <th>Quantity</th>
                        <th>Status</th>
                        <th>Last Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assets as $asset): ?>
                        <tr data-status="<?= htmlspecialchars($asset['status']) ?>" data-lab="<?= htmlspecialchars($asset['lab_id']) ?>" data-type="<?= htmlspecialchars($asset['type']) ?>">
                            <td>
                                <div class="asset-name">
                                    <strong><?= htmlspecialchars($asset['name']) ?></strong>
                                    <br><small><?= htmlspecialchars($asset['description'] ?? '') ?></small>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-<?= $asset['type'] ?>">
                                    <?= ucfirst($asset['type']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($asset['lab_name'] ?? 'Not assigned') ?></td>
                            <td>
                                <span class="quantity-display">
                                    <?= $asset['quantity'] ?> <?= $asset['unit'] ?? 'units' ?>
                                </span>
                                <?php if ($asset['quantity'] <= 5): ?>
                                    <br><small class="text-warning">Low stock</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= $asset['status'] ?>">
                                    <?= ucfirst($asset['status']) ?>
                                </span>
                            </td>
                            <td><?= date('M j, Y', strtotime($asset['updated_at'])) ?></td>
                            <td>
                                <a href="<?= APP_URL ?>/assets/view/<?= $asset['id'] ?>" class="btn btn-primary btn-sm">View</a>
                                <?php if (Auth::role() === 'admin' || Auth::role() === 'technician'): ?>
                                    <a href="<?= APP_URL ?>/assets/edit/<?= $asset['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                                    <?php if ($asset['status'] === 'available'): ?>
                                        <a href="<?= APP_URL ?>/assets/issue/<?= $asset['id'] ?>" class="btn btn-success btn-sm">Issue</a>
                                    <?php elseif ($asset['status'] === 'issued'): ?>
                                        <a href="<?= APP_URL ?>/assets/return/<?= $asset['id'] ?>" class="btn btn-warning btn-sm">Return</a>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <a href="<?= APP_URL ?>/assets/history/<?= $asset['id'] ?>" class="btn btn-outline btn-sm">History</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<style>
.filters-section {
    margin-bottom: 2rem;
    padding: 1rem;
    background: var(--background);
    border-radius: 0.5rem;
}

.filter-controls {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.asset-name {
    line-height: 1.4;
}

.asset-name small {
    color: var(--text2);
    font-size: 0.75rem;
}

.quantity-display {
    font-weight: 600;
}

.text-warning {
    color: var(--warning);
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

.table-responsive {
    overflow-x: auto;
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
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('search-input');
    const statusFilter = document.getElementById('status-filter');
    const labFilter = document.getElementById('lab-filter');
    const typeFilter = document.getElementById('type-filter');
    const table = document.getElementById('assets-table');
    const rows = table.querySelectorAll('tbody tr');
    
    function filterTable() {
        const searchTerm = searchInput.value.toLowerCase();
        const statusValue = statusFilter.value;
        const labValue = labFilter.value;
        const typeValue = typeFilter.value;
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            const status = row.dataset.status;
            const lab = row.dataset.lab;
            const type = row.dataset.type;
            
            const matchesSearch = text.includes(searchTerm);
            const matchesStatus = !statusValue || status === statusValue;
            const matchesLab = !labValue || lab === labValue;
            const matchesType = !typeValue || type === typeValue;
            
            row.style.display = matchesSearch && matchesStatus && matchesLab && matchesType ? '' : 'none';
        });
    }
    
    searchInput.addEventListener('input', filterTable);
    statusFilter.addEventListener('change', filterTable);
    labFilter.addEventListener('change', filterTable);
    typeFilter.addEventListener('change', filterTable);
});
</script>
