<div class="card">
    <div class="card-header">➕ Create New Practical</div>
    
    <form method="POST" action="<?= APP_URL ?>/practicals/create">
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Practical Title *</label>
                <input type="text" name="title" class="form-input" value="<?= htmlspecialchars($data['title'] ?? '') ?>" placeholder="Enter practical title..." required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Course Code</label>
                <input type="text" name="course_code" class="form-input" value="<?= htmlspecialchars($data['course_code'] ?? '') ?>" placeholder="e.g., PHY101">
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-textarea" rows="4" placeholder="Describe the practical objectives and procedures..."><?= htmlspecialchars($data['description'] ?? '') ?></textarea>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Laboratory *</label>
                <select name="lab_id" class="form-select" required>
                    <option value="">Select laboratory...</option>
                    <?php foreach ($labs as $lab): ?>
                        <option value="<?= $lab['id'] ?>" <?= (isset($data['lab_id']) && $data['lab_id'] === $lab['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($lab['name']) ?> (<?= htmlspecialchars($lab['lab_code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Max Students</label>
                <input type="number" name="max_students" class="form-input" value="<?= htmlspecialchars($data['max_students'] ?? 30) ?>" min="1" max="100">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Scheduled Date *</label>
                <input type="date" name="scheduled_date" class="form-input" value="<?= htmlspecialchars($data['scheduled_date'] ?? '') ?>" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Start Time *</label>
                <input type="time" name="start_time" class="form-input" value="<?= htmlspecialchars($data['start_time'] ?? '') ?>" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">End Time *</label>
                <input type="time" name="end_time" class="form-input" value="<?= htmlspecialchars($data['end_time'] ?? '') ?>" required>
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label">Required Equipment</label>
            <textarea name="required_equipment" class="form-textarea" rows="3" placeholder="List equipment needed..."><?= htmlspecialchars($data['required_equipment'] ?? '') ?></textarea>
        </div>
        
        <div class="form-group">
            <label class="form-label">Required Chemicals</label>
            <textarea name="required_chemicals" class="form-textarea" rows="3" placeholder="List chemicals needed..."><?= htmlspecialchars($data['required_chemicals'] ?? '') ?></textarea>
        </div>
        
        <div class="form-group">
            <label class="form-label">Safety Notes</label>
            <textarea name="safety_notes" class="form-textarea" rows="3" placeholder="Safety precautions and warnings..."><?= htmlspecialchars($data['safety_notes'] ?? '') ?></textarea>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Create Practical</button>
            <a href="<?= APP_URL ?>/practicals" class="btn btn-secondary">Cancel</a>
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
</style>
