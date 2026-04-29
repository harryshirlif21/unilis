<div class="notebook-management">
    <!-- Sidebar with Existing Notebooks -->
    <aside class="notebook-sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-book"></i> My Notebooks</h3>
            <span class="notebook-count"><?= count($notebooks) ?> notebooks</span>
        </div>
        
        <div class="notebook-list">
            <?php if (empty($notebooks)): ?>
                <div class="empty-sidebar">
                    <div class="empty-icon">📓</div>
                    <p>No notebooks yet</p>
                    <small>Create your first notebook to get started</small>
                </div>
            <?php else: ?>
                <?php foreach ($notebooks as $notebook): ?>
                    <div class="notebook-item">
                        <div class="notebook-item-header">
                            <h4><?= htmlspecialchars($notebook['title']) ?></h4>
                            <span class="badge badge-<?= $notebook['status'] ?>">
                                <?= ucfirst($notebook['status']) ?>
                            </span>
                        </div>
                        
                        <div class="notebook-item-info">
                            <div class="info-row">
                                <i class="fas fa-flask"></i>
                                <span><?= htmlspecialchars($notebook['practical_title']) ?></span>
                            </div>
                            <div class="info-row">
                                <i class="fas fa-calendar"></i>
                                <span><?= date('M j, Y', strtotime($notebook['session_date'])) ?></span>
                            </div>
                        </div>
                        
                        <div class="notebook-item-actions">
                            <a href="<?= APP_URL ?>/notebooks/edit/<?= $notebook['id'] ?>" class="btn-xs btn-primary">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <a href="<?= APP_URL ?>/notebooks/view/<?= $notebook['id'] ?>" class="btn-xs btn-outline">
                                <i class="fas fa-eye"></i> View
                            </a>
                            <?php if ($notebook['status'] === 'draft'): ?>
                                <a href="<?= APP_URL ?>/notebooks/submit/<?= $notebook['id'] ?>" class="btn-xs btn-success">
                                    <i class="fas fa-paper-plane"></i> Submit
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </aside>
    
    <!-- Main Content - Notebook Creation -->
    <main class="notebook-main">
        <div class="card">
            <div class="card-header">
                <div class="card-header-content">
                    <span>📓 Create New Notebook</span>
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
                
                <form method="POST" action="<?= APP_URL ?>/notebooks/create" class="notebook-form">
                    <!-- Basic Information Section -->
                    <div class="form-section">
                        <h4><i class="fas fa-info-circle"></i> Basic Information</h4>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="title">Notebook Title *</label>
                                <input type="text" name="title" id="title" class="form-control" 
                                       placeholder="Enter a descriptive title for your notebook..." required>
                                <small class="form-help">Choose a clear title that describes your lab work</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="session_id">Lab Session (Optional)</label>
                                <select name="session_id" id="session_id" class="form-control">
                                    <option value="">No specific session</option>
                                    <?php foreach ($sessions as $session): ?>
                                        <option value="<?= $session['id'] ?>">
                                            <?= htmlspecialchars($session['practical_title']) ?> - 
                                            <?= date('M j, Y', strtotime($session['started_at'])) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-help">Associate with a specific lab session (optional)</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Practical Details Section -->
                    <div class="form-section">
                        <h4><i class="fas fa-flask"></i> Practical Details</h4>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="practical_type">Type of Work</label>
                                <select name="practical_type" id="practical_type" class="form-control">
                                    <option value="experiment">Laboratory Experiment</option>
                                    <option value="research">Research Project</option>
                                    <option value="assignment">Class Assignment</option>
                                    <option value="makeup">Makeup Session</option>
                                    <option value="extra">Extra Practice</option>
                                </select>
                                <small class="form-help">Select the type of practical work</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="difficulty">Difficulty Level</label>
                                <select name="difficulty" id="difficulty" class="form-control">
                                    <option value="basic">Basic</option>
                                    <option value="intermediate">Intermediate</option>
                                    <option value="advanced">Advanced</option>
                                </select>
                                <small class="form-help">Rate the difficulty level of this practical</small>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="objectives">Learning Objectives</label>
                            <textarea name="objectives" id="objectives" class="form-control" rows="3"
                                      placeholder="What do you aim to learn or achieve with this practical?"></textarea>
                            <small class="form-help">List the key learning objectives or goals</small>
                        </div>
                    </div>
                    
                    <!-- Content Section -->
                    <div class="form-section">
                        <h4><i class="fas fa-file-alt"></i> Notebook Content</h4>
                        
                        <div class="form-group">
                            <label for="content">Lab Notes & Observations</label>
                            <textarea name="content" id="content" class="form-control" rows="8"
                                      placeholder="Enter your detailed lab observations, procedures, results, and analysis..."></textarea>
                            <small class="form-help">Document your experimental procedure, observations, data, and conclusions</small>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="materials">Materials Used</label>
                                <textarea name="materials" id="materials" class="form-control" rows="3"
                                          placeholder="List all materials, equipment, and chemicals used..."></textarea>
                                <small class="form-help">Document all materials and equipment used in the practical</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="safety_notes">Safety Considerations</label>
                                <textarea name="safety_notes" id="safety_notes" class="form-control" rows="3"
                                          placeholder="Safety precautions, PPE, hazard assessments..."></textarea>
                                <small class="form-help">Document safety measures and any hazards encountered</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Additional Information Section -->
                    <div class="form-section">
                        <h4><i class="fas fa-cog"></i> Additional Information</h4>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="duration">Estimated Duration</label>
                                <input type="text" name="duration" id="duration" class="form-control" 
                                       placeholder="e.g., 2 hours, 90 minutes">
                                <small class="form-help">How long did this practical take?</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="group_work">Group Work</label>
                                <select name="group_work" id="group_work" class="form-control">
                                    <option value="individual">Individual</option>
                                    <option value="pair">Pair Work</option>
                                    <option value="small_group">Small Group (3-4)</option>
                                    <option value="large_group">Large Group (5+)</option>
                                </select>
                                <small class="form-help">Was this work done individually or in a group?</small>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="conclusions">Conclusions & Reflections</label>
                            <textarea name="conclusions" id="conclusions" class="form-control" rows="4"
                                      placeholder="Summarize your findings and reflect on the learning experience..."></textarea>
                            <small class="form-help">What were your key findings and what did you learn?</small>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Create Notebook
                        </button>
                        <button type="reset" class="btn btn-secondary">
                            <i class="fas fa-undo"></i> Reset Form
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>

<style>
.notebook-management {
    display: grid;
    grid-template-columns: 350px 1fr;
    gap: 2rem;
    min-height: 80vh;
}

/* Sidebar Styles */
.notebook-sidebar {
    background: white;
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 1.5rem;
    height: fit-content;
    position: sticky;
    top: 2rem;
}

.sidebar-header {
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border);
}

.sidebar-header h3 {
    margin: 0 0 0.5rem 0;
    color: var(--dark);
    font-size: 1.1rem;
}

.notebook-count {
    font-size: 0.875rem;
    color: var(--gray);
}

.notebook-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.empty-sidebar {
    text-align: center;
    padding: 2rem 0;
    color: var(--gray);
}

.empty-sidebar .empty-icon {
    font-size: 2rem;
    margin-bottom: 0.5rem;
}

.notebook-item {
    background: var(--light);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 1rem;
    transition: all 0.3s;
}

.notebook-item:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transform: translateY(-1px);
}

.notebook-item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
}

.notebook-item-header h4 {
    margin: 0;
    font-size: 0.95rem;
    color: var(--dark);
    line-height: 1.3;
}

.notebook-item-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    margin-bottom: 0.75rem;
}

.info-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.8rem;
    color: var(--gray);
}

.info-row i {
    width: 14px;
    text-align: center;
}

.notebook-item-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

/* Main Content Styles */
.notebook-main {
    min-width: 0;
}

.notebook-form {
    max-width: 100%;
}

.form-section {
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--border);
}

.form-section:last-of-type {
    border-bottom: none;
    margin-bottom: 0;
}

.form-section h4 {
    margin-bottom: 1rem;
    color: var(--primary);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1.1rem;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1rem;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.form-group label {
    font-weight: 600;
    color: var(--dark);
}

.form-control {
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
    font-size: 0.8rem;
    color: var(--gray);
    line-height: 1.4;
}

.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 1px solid var(--border);
    flex-direction: column;
}

.btn-xs {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
    border-radius: 4px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    transition: all 0.3s;
}

.btn-primary { background: var(--primary); color: white; }
.btn-outline { background: transparent; color: var(--primary); border: 1px solid var(--primary); }
.btn-success { background: var(--success); color: white; }

/* Responsive Design */
@media (max-width: 1024px) {
    .notebook-management {
        grid-template-columns: 1fr;
    }
    
    .notebook-sidebar {
        position: static;
        order: 2;
    }
    
    .notebook-main {
        order: 1;
    }
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
}

/* Badge Styles */
.badge-draft { background: var(--gray); color: white; }
.badge-submitted { background: var(--primary); color: white; }
.badge-graded { background: var(--success); color: white; }
.badge-rejected { background: var(--error); color: white; }
</style>
