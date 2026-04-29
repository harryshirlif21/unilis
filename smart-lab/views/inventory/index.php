<div class="card">
    <div class="card-header">
        <div>
            <div class="card-title">📦 Inventory Management</div>
            <div class="card-sub">Monitor and manage laboratory inventory levels</div>
        </div>
        <div class="header-actions">
            <button onclick="window.print()" class="btn btn-outline">🖨️ Print Report</button>
            <a href="<?= APP_URL ?>/inventory/export" class="btn btn-secondary">📊 Export Data</a>
        </div>
    </div>
    
    <?php if (!empty($stats)): ?>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= $stats['total_items'] ?? 0 ?></div>
                <div class="stat-label">Total Items</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['low_stock'] ?? 0 ?></div>
                <div class="stat-label">Low Stock Alert</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['total_value'] ?? 0 ?></div>
                <div class="stat-label">Total Value (₦)</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['expiring_soon'] ?? 0 ?></div>
                <div class="stat-label">Expiring Soon</div>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="inventory-filters">
        <div class="filter-row">
            <div class="form-group">
                <label class="form-label">Search Items</label>
                <input type="text" id="inventory-search" class="form-input" placeholder="Search inventory...">
            </div>
            
            <div class="form-group">
                <label class="form-label">Category</label>
                <select id="category-filter" class="form-select">
                    <option value="">All Categories</option>
                    <option value="equipment">Equipment</option>
                    <option value="chemicals">Chemicals</option>
                    <option value="consumables">Consumables</option>
                    <option value="tools">Tools</option>
                    <option value="safety">Safety Equipment</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Stock Status</label>
                <select id="stock-filter" class="form-select">
                    <option value="">All Items</option>
                    <option value="in-stock">In Stock</option>
                    <option value="low-stock">Low Stock</option>
                    <option value="out-of-stock">Out of Stock</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Laboratory</label>
                <select id="lab-filter" class="form-select">
                    <option value="">All Labs</option>
                    <?php foreach ($labs as $lab): ?>
                        <option value="<?= $lab['id'] ?>"><?= htmlspecialchars($lab['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
    
    <?php if (empty($inventory)): ?>
        <div class="alert alert-info">
            No inventory items are currently registered. Assets need to be added to the system first.
        </div>
    <?php else: ?>
        <div class="inventory-table-wrapper">
            <table class="table inventory-table" id="inventory-table">
                <thead>
                    <tr>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Lab</th>
                        <th>Current Stock</th>
                        <th>Min Level</th>
                        <th>Unit</th>
                        <th>Unit Price</th>
                        <th>Total Value</th>
                        <th>Last Updated</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inventory as $item): ?>
                        <?php
                            $stockStatus = 'in-stock';
                            $stockClass = 'badge-success';
                            if ($item['quantity'] <= 0) {
                                $stockStatus = 'out-of-stock';
                                $stockClass = 'badge-danger';
                            } elseif ($item['quantity'] <= $item['min_quantity']) {
                                $stockStatus = 'low-stock';
                                $stockClass = 'badge-warning';
                            }
                            
                            $totalValue = ($item['quantity'] * ($item['unit_price'] ?? 0));
                        ?>
                        <tr data-category="<?= htmlspecialchars($item['type'] ?? '') ?>" 
                            data-stock="<?= $stockStatus ?>" 
                            data-lab="<?= htmlspecialchars($item['lab_id'] ?? '') ?>">
                            <td>
                                <div class="item-name">
                                    <strong><?= htmlspecialchars($item['name']) ?></strong>
                                    <?php if (!empty($item['description'])): ?>
                                        <br><small><?= htmlspecialchars($item['description']) ?></small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-<?= $item['type'] ?? 'equipment' ?>">
                                    <?= ucfirst($item['type'] ?? 'Equipment') ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($item['lab_name'] ?? 'Not assigned') ?></td>
                            <td>
                                <span class="stock-quantity <?= $stockStatus ?>">
                                    <?= $item['quantity'] ?>
                                </span>
                            </td>
                            <td><?= $item['min_quantity'] ?? 'N/A' ?></td>
                            <td><?= htmlspecialchars($item['unit'] ?? 'units') ?></td>
                            <td>₦<?= number_format($item['unit_price'] ?? 0, 2) ?></td>
                            <td>₦<?= number_format($totalValue, 2) ?></td>
                            <td><?= date('M j, Y', strtotime($item['updated_at'])) ?></td>
                            <td>
                                <span class="badge <?= $stockClass ?>">
                                    <?= str_replace('-', ' ', ucwords($stockStatus)) ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?= APP_URL ?>/assets/view/<?= $item['id'] ?>" class="btn btn-primary btn-sm">View</a>
                                <?php if (Auth::role() === 'admin' || Auth::role() === 'technician'): ?>
                                    <a href="<?= APP_URL ?>/assets/edit/<?= $item['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                                    <button onclick="showRestockModal('<?= $item['id'] ?>', '<?= htmlspecialchars($item['name']) ?>', <?= $item['quantity'] ?>, <?= $item['min_quantity'] ?? 0 ?>)" class="btn btn-success btn-sm">Restock</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Inventory Summary -->
        <div class="inventory-summary">
            <h3>📊 Inventory Summary</h3>
            <div class="summary-grid">
                <div class="summary-item">
                    <h4>By Category</h4>
                    <?php
                    $categoryStats = [];
                    foreach ($inventory as $item) {
                        $cat = $item['type'] ?? 'equipment';
                        if (!isset($categoryStats[$cat])) {
                            $categoryStats[$cat] = ['count' => 0, 'value' => 0];
                        }
                        $categoryStats[$cat]['count']++;
                        $categoryStats[$cat]['value'] += ($item['quantity'] * ($item['unit_price'] ?? 0));
                    }
                    
                    foreach ($categoryStats as $category => $stats):
                    ?>
                        <div class="category-stat">
                            <span class="category-name"><?= ucfirst($category) ?></span>
                            <span class="category-count"><?= $stats['count'] ?> items</span>
                            <span class="category-value">₦<?= number_format($stats['value'], 2) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="summary-item">
                    <h4>By Laboratory</h4>
                    <?php
                    $labStats = [];
                    foreach ($inventory as $item) {
                        $labId = $item['lab_id'] ?? 'unassigned';
                        $labName = $item['lab_name'] ?? 'Unassigned';
                        if (!isset($labStats[$labId])) {
                            $labStats[$labId] = ['name' => $labName, 'count' => 0, 'value' => 0];
                        }
                        $labStats[$labId]['count']++;
                        $labStats[$labId]['value'] += ($item['quantity'] * ($item['unit_price'] ?? 0));
                    }
                    
                    foreach ($labStats as $labId => $stats):
                    ?>
                        <div class="lab-stat">
                            <span class="lab-name"><?= htmlspecialchars($stats['name']) ?></span>
                            <span class="lab-count"><?= $stats['count'] ?> items</span>
                            <span class="lab-value">₦<?= number_format($stats['value'], 2) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Restock Modal -->
<div id="restockModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>📦 Restock Item</h3>
            <button class="modal-close" onclick="closeRestockModal()">&times;</button>
        </div>
        <form id="restockForm" method="POST">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Item Name</label>
                    <input type="text" id="restockItemName" class="form-input" readonly>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Current Stock</label>
                        <input type="number" id="restockCurrentStock" class="form-input" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Min Level</label>
                        <input type="number" id="restockMinLevel" class="form-input" readonly>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Quantity to Add *</label>
                    <input type="number" name="restock_quantity" id="restockQuantity" class="form-input" min="1" required>
                    <small style="color: var(--text2);">Enter quantity to add to current stock</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Reason</label>
                    <select name="restock_reason" class="form-select">
                        <option value="purchase">New Purchase</option>
                        <option value="transfer">Transfer from another lab</option>
                        <option value="return">Return from user</option>
                        <option value="adjustment">Stock Adjustment</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea name="restock_notes" class="form-textarea" rows="3" placeholder="Additional notes about this restock..."></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <input type="hidden" name="asset_id" id="restockAssetId">
                <button type="submit" class="btn btn-success">Restock Item</button>
                <button type="button" class="btn btn-secondary" onclick="closeRestockModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<style>
.header-actions {
    display: flex;
    gap: 1rem;
}

.inventory-filters {
    margin-bottom: 2rem;
    padding: 1rem;
    background: var(--background);
    border-radius: 0.5rem;
}

.filter-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.inventory-table-wrapper {
    overflow-x: auto;
    margin-bottom: 2rem;
}

.item-name {
    line-height: 1.4;
}

.item-name small {
    color: var(--text2);
    font-size: 0.75rem;
}

.stock-quantity {
    font-weight: 600;
    font-size: 1.1rem;
}

.stock-quantity.low-stock {
    color: var(--warning);
}

.stock-quantity.out-of-stock {
    color: var(--danger);
}

.badge {
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    font-weight: 500;
}

.badge-success {
    background: rgba(22,163,74,0.1);
    color: #16a34a;
}

.badge-warning {
    background: rgba(212,160,23,0.15);
    color: #d4a017;
}

.badge-danger {
    background: rgba(220,38,38,0.1);
    color: #dc2626;
}

.badge-equipment {
    background: rgba(37,99,235,0.1);
    color: #2563eb;
}

.badge-chemicals {
    background: rgba(212,160,23,0.15);
    color: #d4a017;
}

.badge-consumables {
    background: rgba(22,163,74,0.1);
    color: #16a34a;
}

.badge-tools {
    background: rgba(139,92,246,0.1);
    color: #8b5cf6;
}

.badge-safety {
    background: rgba(37,99,235,0.1);
    color: #2563eb;
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

.inventory-summary {
    margin-top: 2rem;
    padding: 1.5rem;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 0.5rem;
}

.inventory-summary h3 {
    margin-bottom: 1rem;
    color: var(--primary);
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
}

.summary-item h4 {
    margin-bottom: 1rem;
    color: var(--text);
    font-size: 1rem;
}

.category-stat, .lab-stat {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    background: var(--background);
    border-radius: 0.5rem;
    margin-bottom: 0.5rem;
}

.category-name, .lab-name {
    font-weight: 600;
}

.category-count, .lab-count {
    color: var(--text2);
    font-size: 0.875rem;
}

.category-value, .lab-value {
    font-weight: 600;
    color: var(--primary);
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
}

.modal-content {
    background: var(--surface);
    border-radius: 0.75rem;
    max-width: 500px;
    margin: 5% auto;
    position: relative;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid var(--border);
}

.modal-header h3 {
    margin: 0;
    color: var(--text);
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--text2);
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    padding: 1.5rem;
    border-top: 1px solid var(--border);
}

.form-row {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}

@media print {
    .header-actions,
    .inventory-filters,
    .inventory-summary,
    .btn-sm {
        display: none !important;
    }
    
    .inventory-table {
        font-size: 0.8rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Real-time filtering
    const searchInput = document.getElementById('inventory-search');
    const categoryFilter = document.getElementById('category-filter');
    const stockFilter = document.getElementById('stock-filter');
    const labFilter = document.getElementById('lab-filter');
    const table = document.getElementById('inventory-table');
    const rows = table.querySelectorAll('tbody tr');
    
    function filterTable() {
        const searchTerm = searchInput.value.toLowerCase();
        const categoryValue = categoryFilter.value;
        const stockValue = stockFilter.value;
        const labValue = labFilter.value;
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            const category = row.dataset.category;
            const stock = row.dataset.stock;
            const lab = row.dataset.lab;
            
            const matchesSearch = text.includes(searchTerm);
            const matchesCategory = !categoryValue || category === categoryValue;
            const matchesStock = !stockValue || stock === stockValue;
            const matchesLab = !labValue || lab === labValue;
            
            row.style.display = matchesSearch && matchesCategory && matchesStock && matchesLab ? '' : 'none';
        });
    }
    
    searchInput.addEventListener('input', filterTable);
    categoryFilter.addEventListener('change', filterTable);
    stockFilter.addEventListener('change', filterTable);
    labFilter.addEventListener('change', filterTable);
});

// Restock Modal Functions
function showRestockModal(assetId, itemName, currentStock, minLevel) {
    document.getElementById('restockAssetId').value = assetId;
    document.getElementById('restockItemName').value = itemName;
    document.getElementById('restockCurrentStock').value = currentStock;
    document.getElementById('restockMinLevel').value = minLevel;
    document.getElementById('restockQuantity').value = '';
    document.getElementById('restockModal').style.display = 'block';
}

function closeRestockModal() {
    document.getElementById('restockModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('restockModal');
    if (event.target === modal) {
        closeRestockModal();
    }
}

// Handle restock form submission
document.getElementById('restockForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const data = Object.fromEntries(formData);
    
    // This would typically make an AJAX call to update the stock
    console.log('Restock data:', data);
    
    // For now, just show success and close modal
    alert('Item restocked successfully!');
    closeRestockModal();
    
    // In a real implementation, you would:
    // 1. Send AJAX request to update the database
    // 2. Record blockchain transaction
    // 3. Refresh the page or update the table
});
</script>
