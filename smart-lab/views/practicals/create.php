<div class="main-content">
    <div class="page-header">
        <div class="page-overline">Practicals Management</div>
        <h1 class="page-title">Create New Practical</h1>
        <div class="page-subtitle">Set up a new laboratory practical session with scheduling and resource requirements</div>
    </div>

    <div class="panel">
        <form method="POST" action="<?= APP_URL ?>/practicals/create" class="modern-form">
            <div class="form-section">
                <h3 class="section-title">Basic Information</h3>
                
                <div class="grid grid-two">
                    <div class="form-group">
                        <label class="form-label">Practical Title *</label>
                        <input type="text" name="title" class="form-control" 
                            value="<?= htmlspecialchars($data['title'] ?? '') ?>" 
                            placeholder="Enter practical title..." required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Course Code</label>
                        <input type="text" name="course_code" class="form-control" 
                            value="<?= htmlspecialchars($data['course_code'] ?? '') ?>" 
                            placeholder="e.g., PHY101">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="4" 
                        placeholder="Describe the practical objectives and procedures..."><?= htmlspecialchars($data['description'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="form-section">
                <h3 class="section-title">Laboratory Details</h3>
                
                <div class="grid grid-two">
                    <div class="form-group">
                        <label class="form-label">Laboratory *</label>
                        <select name="lab_id" class="form-control" required>
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
                        <input type="number" name="max_students" class="form-control" 
                            value="<?= htmlspecialchars($data['max_students'] ?? 30) ?>" 
                            min="1" max="100">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3 class="section-title">Schedule</h3>
                
                <div class="grid grid-three">
                    <div class="form-group">
                        <label class="form-label">Scheduled Date *</label>
                        <input type="date" name="scheduled_date" class="form-control" 
                            value="<?= htmlspecialchars($data['scheduled_date'] ?? '') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Start Time *</label>
                        <input type="time" name="start_time" class="form-control" 
                            value="<?= htmlspecialchars($data['start_time'] ?? '') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">End Time *</label>
                        <input type="time" name="end_time" class="form-control" 
                            value="<?= htmlspecialchars($data['end_time'] ?? '') ?>" required>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3 class="section-title">Resources & Safety</h3>
                
                <div class="form-group">
                    <label class="form-label">Required Equipment</label>
                    <textarea name="required_equipment" class="form-control" rows="3" 
                        placeholder="List equipment needed (one per line)..."><?= htmlspecialchars($data['required_equipment'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Required Chemicals</label>
                    <textarea name="required_chemicals" class="form-control" rows="3" 
                        placeholder="List chemicals needed (one per line)..."><?= htmlspecialchars($data['required_chemicals'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Safety Notes</label>
                    <textarea name="safety_notes" class="form-control" rows="3" 
                        placeholder="Safety precautions and warnings..."><?= htmlspecialchars($data['safety_notes'] ?? '') ?></textarea>
                </div>
            </div>
            
            <div class="form-actions d-flex gap-3 mt-4">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="pi pi-plus"></i> Create Practical
                </button>
                <a href="<?= APP_URL ?>/practicals" class="btn btn-secondary btn-lg">
                    <i class="pi pi-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<style>
.form-section {
    margin-bottom: 32px;
    padding-bottom: 32px;
    border-bottom: 1px solid var(--border-subtle);
}

.form-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.section-title {
    font-size: 16px;
    font-weight: 700;
    color: var(--text);
    margin-bottom: 20px;
    letter-spacing: -0.1px;
}

.form-group {
    margin-bottom: 18px;
}

.form-label {
    display: block;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.2px;
    color: var(--text-2);
    margin-bottom: 6px;
    text-transform: uppercase;
}

.form-control {
    width: 100%;
    padding: 10px 14px;
    border-radius: var(--radius-md);
    background: var(--surface);
    border: 1px solid var(--border);
    color: var(--text);
    font-size: 14px;
    transition: var(--transition-fast);
    font-family: inherit;
}

.form-control:focus {
    outline: none;
    border-color: var(--color-primary);
    box-shadow: var(--shadow-focus);
    background: var(--surface);
}

.form-control::placeholder {
    color: var(--text-4);
}

.form-actions {
    padding-top: 8px;
}

.modern-form textarea.form-control {
    resize: vertical;
    min-height: 80px;
}
</style>
