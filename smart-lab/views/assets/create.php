<div class="card">
    <div class="card-header">➕ Add New Asset</div>
    
    <form method="POST" action="<?= APP_URL ?>/assets/create">
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Asset Name *</label>
                <input type="text" name="name" class="form-input" placeholder="Enter asset name..." required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Asset Type *</label>
                <select name="type" class="form-select" required>
                    <option value="">Select type...</option>
                    <option value="equipment">Equipment</option>
                    <option value="chemical">Chemical</option>
                    <option value="consumable">Consumable</option>
                    <option value="tool">Tool</option>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-textarea" rows="3" placeholder="Describe the asset and its purpose..."></textarea>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Laboratory *</label>
                <select name="lab_id" class="form-select" required>
                    <option value="">Select laboratory...</option>
                    <?php foreach ($labs as $lab): ?>
                        <option value="<?= $lab['id'] ?>"><?= htmlspecialchars($lab['name']) ?> (<?= htmlspecialchars($lab['lab_code']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Location</label>
                <input type="text" name="location" class="form-input" placeholder="e.g., Shelf A-1, Cabinet 3">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Quantity *</label>
                <input type="number" name="quantity" class="form-input" min="0" placeholder="0" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Unit of Measure</label>
                <select name="unit" class="form-select">
                    <option value="units">Units</option>
                    <option value="pieces">Pieces</option>
                    <option value="liters">Liters</option>
                    <option value="ml">Milliliters</option>
                    <option value="kg">Kilograms</option>
                    <option value="g">Grams</option>
                    <option value="mg">Milligrams</option>
                    <option value="boxes">Boxes</option>
                    <option value="bottles">Bottles</option>
                    <option value="sets">Sets</option>
                </select>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Serial Number</label>
                <input type="text" name="serial_number" class="form-input" placeholder="Asset serial number (if applicable)">
            </div>
            
            <div class="form-group">
                <label class="form-label">Purchase Date</label>
                <input type="date" name="purchase_date" class="form-input">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Warranty Expiry</label>
                <input type="date" name="warranty_expiry" class="form-input">
            </div>
            
            <div class="form-group">
                <label class="form-label">Initial Status</label>
                <select name="status" class="form-select">
                    <option value="available">Available</option>
                    <option value="maintenance">Under Maintenance</option>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label">Safety Notes</label>
            <textarea name="safety_notes" class="form-textarea" rows="3" placeholder="Any safety precautions or handling instructions..."></textarea>
        </div>
        
        <div class="blockchain-info">
            <h4>🔗 Blockchain Registration</h4>
            <p>This asset will be registered on the blockchain with an immutable transaction record for complete auditability.</p>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Add Asset</button>
            <a href="<?= APP_URL ?>/assets" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<style>
.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}

.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
}

.blockchain-info {
    margin: 2rem 0;
    padding: 1rem;
    background: #eff6ff;
    border: 1px solid #dbeafe;
    border-radius: 0.5rem;
}

.blockchain-info h4 {
    margin-bottom: 0.5rem;
    color: #1e40af;
}

.blockchain-info p {
    margin: 0;
    color: #1e40af;
}
</style>
