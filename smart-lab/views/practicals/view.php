<div class="practical-view-wrapper">
    <!-- Breadcrumb -->
    <div class="breadcrumb">
        <a href="<?= APP_URL ?>/practicals">← Back to Practicals</a>
        <span class="sep">/</span>
        <span class="current"><?= htmlspecialchars($practical['title']) ?></span>
    </div>

    <!-- Header Card -->
    <div class="view-header">
        <div class="view-header-top">
            <div>
                <div class="view-overline">Lab Practical</div>
                <h1 class="view-title"><?= htmlspecialchars($practical['title']) ?></h1>
                <p class="view-subtitle">
                    <?= htmlspecialchars($practical['course_code'] ?? 'No course code') ?> — 
                    <?= htmlspecialchars($practical['lab_name']) ?> (<?= htmlspecialchars($practical['lab_code']) ?>)
                </p>
            </div>
            <div class="view-status-area">
                <span class="status-badge status-<?= $practical['status'] ?>">
                    <?php
                    $statusIcons = ['draft' => '📝', 'published' => '📢', 'ongoing' => '🔴', 'completed' => '✅', 'postponed' => '⏸️'];
                    echo ($statusIcons[$practical['status']] ?? '📋') . ' ' . ucfirst($practical['status'] ?? 'draft');
                    ?>
                </span>
            </div>
        </div>

        <!-- Quick Info Strip -->
        <div class="info-strip">
            <div class="info-item">
                <span class="info-icon">👨‍🏫</span>
                <div>
                    <div class="info-label">Lecturer</div>
                    <div class="info-value"><?= htmlspecialchars($practical['lecturer_name'] ?? 'Not assigned') ?></div>
                </div>
            </div>
            <div class="info-item">
                <span class="info-icon">📅</span>
                <div>
                    <div class="info-label">Date</div>
                    <div class="info-value"><?= $practical['scheduled_date'] ? date('M j, Y', strtotime($practical['scheduled_date'])) : 'Not set' ?></div>
                </div>
            </div>
            <div class="info-item">
                <span class="info-icon">⏰</span>
                <div>
                    <div class="info-label">Time</div>
                    <div class="info-value">
                        <?php if ($practical['start_time'] && $practical['end_time']): ?>
                            <?= date('H:i', strtotime($practical['start_time'])) ?> — <?= date('H:i', strtotime($practical['end_time'])) ?>
                        <?php else: ?>Not set<?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="info-item">
                <span class="info-icon">🧑‍🎓</span>
                <div>
                    <div class="info-label">Max Students</div>
                    <div class="info-value"><?= $practical['max_students'] ?? 'N/A' ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="view-grid">
        <!-- Left Column: Content -->
        <div class="view-content">
            <!-- Objective -->
            <div class="content-card">
                <div class="content-card-header">
                    <span class="card-icon">🎯</span>
                    <h3>Learning Objectives</h3>
                </div>
                <div class="content-card-body">
                    <?php if ($practical['objective']): ?>
                        <?= $practical['objective'] ?>
                    <?php else: ?>
                        <span class="text-muted">No objectives specified.</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Theory -->
            <div class="content-card">
                <div class="content-card-header">
                    <span class="card-icon">📖</span>
                    <h3>Theoretical Background</h3>
                </div>
                <div class="content-card-body">
                    <?php if ($practical['theory']): ?>
                        <?= $practical['theory'] ?>
                    <?php else: ?>
                        <span class="text-muted">No theory provided.</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Description -->
            <div class="content-card">
                <div class="content-card-header">
                    <span class="card-icon">📄</span>
                    <h3>Description</h3>
                </div>
                <div class="content-card-body">
                    <?php if (!empty($practical['description'])): ?>
                        <?= $practical['description'] ?>
                    <?php else: ?>
                        <span class="text-muted">No description provided.</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Procedure -->
            <?php 
            $procedure = json_decode($practical['procedure_json'] ?? '[]', true);
            if (!empty($procedure)): 
            ?>
            <div class="content-card">
                <div class="content-card-header">
                    <span class="card-icon">📋</span>
                    <h3>Procedure</h3>
                </div>
                <div class="content-card-body">
                    <div class="procedure-steps">
                        <?php foreach ($procedure as $step): ?>
                            <div class="step-item">
                                <div class="step-number"><?= $step['step_number'] ?></div>
                                <div class="step-text"><?= htmlspecialchars($step['step_description']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right Column: Sidebar -->
        <div class="view-sidebar">
            <!-- Equipment & Resources -->
            <div class="sidebar-card">
                <div class="sidebar-card-header">
                    <span class="card-icon">🔧</span>
                    <h3>Equipment & Resources</h3>
                </div>
                <div class="sidebar-card-body">
                    <?php if (!empty($practical['required_equipment'])): ?>
                        <div class="resource-section">
                            <h4>Equipment</h4>
                            <ul class="resource-list">
                                <?php foreach (explode("\n", $practical['required_equipment']) as $item): ?>
                                    <?php if (trim($item)): ?>
                                        <li><?= htmlspecialchars(trim($item)) ?></li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($practical['required_chemicals'])): ?>
                        <div class="resource-section">
                            <h4>Chemicals</h4>
                            <ul class="resource-list">
                                <?php foreach (explode("\n", $practical['required_chemicals']) as $item): ?>
                                    <?php if (trim($item)): ?>
                                        <li class="chemical"><?= htmlspecialchars(trim($item)) ?></li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($practical['required_equipment']) && empty($practical['required_chemicals'])): ?>
                        <span class="text-muted">No equipment or resources specified.</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Safety -->
            <?php if (!empty($practical['safety_notes'])): ?>
            <div class="sidebar-card safety-card">
                <div class="sidebar-card-header">
                    <span class="card-icon">⚠️</span>
                    <h3>Safety Notes</h3>
                </div>
                <div class="sidebar-card-body">
                    <div class="safety-content">
                        <?= nl2br(htmlspecialchars($practical['safety_notes'])) ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="view-actions">
        <?php if ($canEdit): ?>
            <a href="<?= APP_URL ?>/practicals/edit/<?= $practical['id'] ?>" class="btn btn-primary">
                ✏️ Edit Practical
            </a>
            <?php if ($practical['status'] === 'draft'): ?>
                <form method="POST" action="<?= APP_URL ?>/practicals/edit/<?= $practical['id'] ?>" style="display:inline;">
                    <input type="hidden" name="status" value="published">
                    <button type="submit" class="btn btn-success">📢 Publish</button>
                </form>
            <?php elseif ($practical['status'] === 'published'): ?>
                <a href="<?= APP_URL ?>/practicals/start-session/<?= $practical['id'] ?>" class="btn btn-success">🔴 Start Lab Session</a>
            <?php elseif ($practical['status'] === 'postponed'): ?>
                <form method="POST" action="<?= APP_URL ?>/practicals/edit/<?= $practical['id'] ?>" style="display:inline;">
                    <input type="hidden" name="status" value="published">
                    <button type="submit" class="btn btn-success">📢 Re-Publish</button>
                </form>
            <?php endif; ?>
            
            <!-- Postpone button -->
            <button type="button" onclick="confirmPostpone('<?= $practical['id'] ?>', '<?= htmlspecialchars($practical['title'], ENT_QUOTES) ?>')" class="btn btn-warning">
                ⏸️ Postpone
            </button>
        <?php endif; ?>
        
        <a href="<?= APP_URL ?>/practicals" class="btn btn-secondary">← Back to Practicals</a>
    </div>
</div>

<!-- Postpone Modal -->
<div id="postponeModal" class="modal-overlay hidden">
    <div class="modal-backdrop" onclick="closePostponeModal()"></div>
    <div class="modal-card" style="max-width:450px;">
        <div class="modal-card-header">
            <h2>⏸️ Postpone Practical</h2>
            <button class="modal-close" onclick="closePostponeModal()">&times;</button>
        </div>
        <div class="modal-body" style="padding:1.5rem 1.6rem;">
            <p style="margin-bottom:16px;">Postpone <strong id="postponeTitle">this practical</strong>?</p>
            <p style="color:#64748b;font-size:14px;margin-bottom:16px;">This will hide it from students until re-published.</p>
            <form id="postponeForm" method="POST" action="<?= APP_URL ?>/practicals/postpone/<?= $practical['id'] ?>">
                <textarea name="postpone_reason" class="form-control" rows="3" placeholder="Reason (optional)..."></textarea>
            </form>
        </div>
        <div class="modal-footer" style="display:flex;gap:10px;justify-content:flex-end;padding:1rem 1.6rem;">
            <button class="btn btn-secondary" onclick="closePostponeModal()">Cancel</button>
            <button type="submit" form="postponeForm" class="btn" style="background:#f59e0b;color:#1e293b;padding:10px 20px;border:none;border-radius:8px;cursor:pointer;font-weight:600;">⏸️ Confirm Postpone</button>
        </div>
    </div>
</div>

<script>
function confirmPostpone(id, title) {
    document.getElementById('postponeTitle').textContent = title;
    document.getElementById('postponeModal').classList.remove('hidden');
}
function closePostponeModal() {
    document.getElementById('postponeModal').classList.add('hidden');
}
</script>

<style>
/* ===== Layout ===== */
.practical-view-wrapper {
    max-width: 1200px;
    margin: 0 auto;
}
.breadcrumb {
    font-size: 13px;
    color: #64748b;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.breadcrumb a { color: #2563eb; text-decoration: none; }
.breadcrumb a:hover { text-decoration: underline; }
.breadcrumb .sep { color: #cbd5e1; }
.breadcrumb .current { color: #94a3b8; }

/* ===== Header ===== */
.view-header {
    background: white;
    border-radius: 16px;
    padding: 24px 28px;
    box-shadow: 0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
    margin-bottom: 20px;
    border: 1px solid #e2e8f0;
}
.view-header-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
}
.view-overline {
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .8px;
    color: #2563eb;
    margin-bottom: 4px;
}
.view-title {
    font-size: 24px;
    font-weight: 700;
    color: #0f172a;
    margin: 0 0 4px;
}
.view-subtitle {
    font-size: 14px;
    color: #64748b;
    margin: 0;
}
.view-status-area {
    flex-shrink: 0;
}
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 100px;
    font-size: 13px;
    font-weight: 600;
}
.status-draft { background: #fef3c7; color: #92400e; }
.status-published { background: #dcfce7; color: #166534; }
.status-ongoing { background: #fee2e2; color: #991b1b; }
.status-completed { background: #f1f5f9; color: #475569; }
.status-postponed { background: #f1f5f9; color: #6b7280; }

/* ===== Info Strip ===== */
.info-strip {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #f1f5f9;
}
.info-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    background: #f8fafc;
    border-radius: 10px;
}
.info-icon { font-size: 20px; }
.info-label { font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: .4px; }
.info-value { font-size: 14px; font-weight: 600; color: #1e293b; }

/* ===== Content Grid ===== */
.view-grid {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 20px;
    margin-bottom: 20px;
}

/* ===== Content Cards ===== */
.content-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    overflow: hidden;
    margin-bottom: 16px;
}
.content-card:last-child { margin-bottom: 0; }
.content-card-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 16px 20px;
    background: #f8fafc;
    border-bottom: 1px solid #f1f5f9;
}
.content-card-header h3 {
    margin: 0;
    font-size: 15px;
    font-weight: 600;
    color: #0f172a;
}
.card-icon { font-size: 18px; line-height: 1; }
.content-card-body {
    padding: 20px;
    font-size: 14px;
    line-height: 1.7;
    color: #334155;
}
.content-card-body :first-child { margin-top: 0; }

/* ===== Procedure Steps ===== */
.procedure-steps { counter-reset: step; }
.step-item {
    display: flex;
    gap: 14px;
    padding: 12px 0;
    border-bottom: 1px solid #f1f5f9;
}
.step-item:last-child { border-bottom: none; }
.step-number {
    flex-shrink: 0;
    width: 30px; height: 30px;
    background: #2563eb;
    color: white;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: 700;
}
.step-text {
    padding-top: 5px;
    font-size: 14px;
    color: #475569;
    line-height: 1.5;
}

/* ===== Sidebar Cards ===== */
.view-sidebar { display: flex; flex-direction: column; gap: 16px; }
.sidebar-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    overflow: hidden;
}
.sidebar-card-header {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 14px 18px;
    background: #f8fafc;
    border-bottom: 1px solid #f1f5f9;
}
.sidebar-card-header h3 { margin: 0; font-size: 14px; font-weight: 600; color: #0f172a; }
.sidebar-card-body { padding: 16px 18px; }
.resource-section { margin-bottom: 14px; }
.resource-section:last-child { margin-bottom: 0; }
.resource-section h4 {
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .5px;
    color: #64748b;
    margin: 0 0 8px;
}
.resource-list {
    list-style: none;
    padding: 0;
    margin: 0;
}
.resource-list li {
    padding: 6px 0 6px 20px;
    position: relative;
    font-size: 13px;
    color: #475569;
    border-bottom: 1px solid #f8fafc;
}
.resource-list li:before {
    content: '•';
    position: absolute;
    left: 4px;
    color: #2563eb;
    font-weight: bold;
}
.resource-list li.chemical:before { color: #f59e0b; }
.resource-list li:last-child { border-bottom: none; }

/* Safety */
.safety-card { border-color: #fde68a; }
.safety-card .sidebar-card-header { background: #fffbeb; }
.safety-content {
    padding: 12px;
    background: #fef3c7;
    border-radius: 8px;
    font-size: 13px;
    color: #92400e;
    line-height: 1.6;
}

/* ===== Actions ===== */
.view-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 18px 22px;
}
.btn {
    padding: 10px 20px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all .15s;
}
.btn-primary { background: #2563eb; color: white; }
.btn-primary:hover { background: #1d4ed8; }
.btn-success { background: #16a34a; color: white; }
.btn-success:hover { background: #15803d; }
.btn-warning { background: #f59e0b; color: #1e293b; }
.btn-warning:hover { background: #d97706; }
.btn-secondary { background: #f1f5f9; color: #334155; border: 1px solid #e2e8f0; }
.btn-secondary:hover { background: #e2e8f0; }

/* ===== Modal ===== */
.modal-overlay.hidden { display: none !important; }
.modal-overlay {
    position: fixed; inset: 0; z-index: 1100;
    display: flex; align-items: center; justify-content: center; padding: 1rem;
}
.modal-backdrop { position: absolute; inset: 0; background: rgba(0,0,0,.52); backdrop-filter: blur(2px); }
.modal-card { position: relative; z-index: 1; width: min(100%, 450px); background: #fff; border-radius: 18px; box-shadow: 0 28px 72px rgba(0,0,0,.22); }
.modal-card-header { padding: 1.4rem 1.6rem .8rem; display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid #f0f0f0; }
.modal-card-header h2 { margin: 0; font-size: 1.2rem; font-weight: 700; color: #0f172a; }
.modal-close { border: none; background: transparent; font-size: 1.4rem; cursor: pointer; color: #94a3b8; }
.form-control { width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid #d1d5db; font-size: 14px; box-sizing:border-box; }
.text-muted { color: #94a3b8; font-style: italic; }

.content-card-body img {
    max-width: 100%;
    height: auto;
    border-radius: 8px;
    margin: 12px 0;
    display: block;
}

/* ===== Responsive ===== */
@media (max-width: 900px) {
    .view-grid { grid-template-columns: 1fr; }
    .info-strip { grid-template-columns: repeat(2, 1fr); }
    .view-header-top { flex-direction: column; }
}
@media (max-width: 500px) {
    .info-strip { grid-template-columns: 1fr; }
    .view-actions { flex-direction: column; }
    .view-actions .btn { width: 100%; justify-content: center; }
}
</style>