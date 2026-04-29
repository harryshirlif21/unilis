<div class="card">
    <div class="card-header">📓 Create Notebook</div>
    
    <div class="session-info">
        <h4>Lab Session Information</h4>
        <div class="info-grid">
            <div><strong>Practical:</strong> <?= htmlspecialchars($practical['title']) ?></div>
            <div><strong>Laboratory:</strong> <?= htmlspecialchars($practical['lab_name']) ?></div>
            <div><strong>Date:</strong> <?= date('M j, Y', strtotime($practical['scheduled_date'])) ?></div>
            <div><strong>Session ID:</strong> <?= htmlspecialchars($sessionId) ?></div>
        </div>
    </div>
    
    <form method="POST" action="<?= APP_URL ?>/notebooks/create/<?= $sessionId ?>">
        <div class="form-group">
            <label class="form-label">Notebook Title *</label>
            <input type="text" name="title" class="form-input" placeholder="Enter notebook title..." required>
        </div>
        
        <?php if ($userRole === 'student'): ?>
            <div class="form-group">
                <label class="form-label">Group Notebook</label>
                <div class="form-check">
                    <input type="checkbox" name="is_group" id="is_group">
                    <label for="is_group">Create as group notebook</label>
                </div>
            </div>
            
            <div class="form-group" id="group_members_section" style="display:none;">
                <label class="form-label">Group Members</label>
                <div class="member-selection">
                    <?php foreach ($sessionStudents as $student): ?>
                        <div class="form-check">
                            <input type="checkbox" name="group_members[]" value="<?= $student['id'] ?>" id="student_<?= $student['id'] ?>">
                            <label for="student_<?= $student['id'] ?>">
                                <?= htmlspecialchars($student['full_name']) ?> (<?= htmlspecialchars($student['reg_number']) ?>)
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($userRole !== 'student'): ?>
            <div class="form-group">
                <label class="form-label">Notebook Type</label>
                <div class="notebook-type-info">
                    <p><strong>Individual Notebook:</strong> Personal notebook for your own use</p>
                    <?php if ($userRole === 'lecturer' || $userRole === 'admin'): ?>
                        <p><strong>Group Notebook:</strong> Collaborative notebook for multiple students (select members below)</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($userRole === 'lecturer' || $userRole === 'admin'): ?>
                <div class="form-group">
                    <label class="form-label">Group Members (Optional)</label>
                    <div class="member-selection">
                        <?php foreach ($sessionStudents as $student): ?>
                            <div class="form-check">
                                <input type="checkbox" name="group_members[]" value="<?= $student['id'] ?>" id="student_<?= $student['id'] ?>">
                                <label for="student_<?= $student['id'] ?>">
                                    <?= htmlspecialchars($student['full_name']) ?> (<?= htmlspecialchars($student['reg_number']) ?>)
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <small class="form-help">Select multiple students to create a group notebook. Leave empty for individual notebook.</small>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Create Notebook</button>
            <?php if ($userRole === 'student'): ?>
                <a href="<?= APP_URL ?>/dashboard" class="btn btn-secondary">Cancel</a>
            <?php else: ?>
                <a href="<?= APP_URL ?>/notebooks/create" class="btn btn-secondary">Choose Different Session</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<style>
.session-info {
    margin-bottom: 2rem;
    padding: 1rem;
    background: var(--background);
    border-radius: 0.5rem;
}

.session-info h4 {
    margin-bottom: 1rem;
    color: var(--primary);
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.info-grid div {
    padding: 0.5rem;
    background: var(--surface);
    border-radius: 0.25rem;
    font-size: 0.875rem;
}

.form-check {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}

.form-check input[type="checkbox"] {
    width: auto;
}

.member-selection {
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid var(--border);
    border-radius: 0.5rem;
    padding: 1rem;
    background: var(--surface);
}

.notebook-type-info p {
    margin: 0.5rem 0;
    padding: 0.75rem;
    background: var(--surface);
    border-radius: 0.25rem;
    font-size: 0.875rem;
}

.form-help {
    display: block;
    margin-top: 0.5rem;
    color: var(--text2);
    font-size: 0.75rem;
}

.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const isGroupCheckbox = document.getElementById('is_group');
    const groupSection = document.getElementById('group_members_section');
    
    if (isGroupCheckbox && groupSection) {
        isGroupCheckbox.addEventListener('change', function() {
            groupSection.style.display = this.checked ? 'block' : 'none';
        });
    }
});
</script>
