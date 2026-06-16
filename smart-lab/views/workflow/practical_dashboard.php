<?php
/**
 * Student Practical Workflow Dashboard
 * Displays the complete academic integrity workflow state
 * for a student's practical session.
 */
$status = $status ?? [];
$practical = $practical ?? [];
$workflowStates = [
    'Scheduled' => ['icon' => '📅', 'color' => '#3498db', 'desc' => 'Practical has been scheduled'],
    'Awaiting Verification' => ['icon' => '🆔', 'color' => '#9b59b6', 'desc' => 'Please verify your attendance'],
    'Verified' => ['icon' => '✅', 'color' => '#27ae60', 'desc' => 'Attendance verified via secure method'],
    'Ready To Start' => ['icon' => '▶️', 'color' => '#2ecc71', 'desc' => 'You can now start the practical'],
    'In Progress' => ['icon' => '⚡', 'color' => '#f39c12', 'desc' => 'Practical is in progress'],
    'Practical Completed' => ['icon' => '🏁', 'color' => '#e67e22', 'desc' => 'Practical completed. Generating datasheet...'],
    'Datasheet Generated' => ['icon' => '📄', 'color' => '#1abc9c', 'desc' => 'Datasheet generated. Please review and submit.'],
    'Datasheet Submitted' => ['icon' => '🔒', 'color' => '#2c3e50', 'desc' => 'Datasheet locked. Blockchain record created.'],
    'Report Writing Open' => ['icon' => '✍️', 'color' => '#8e44ad', 'desc' => 'You can now write your report'],
    'Report Submitted' => ['icon' => '📤', 'color' => '#2980b9', 'desc' => 'Report submitted for grading'],
    'Graded' => ['icon' => '🏆', 'color' => '#27ae60', 'desc' => 'Practical graded']
];

$currentState = $status['workflow_status'] ?? 'Scheduled';
$progressPercent = $status['progress_percent'] ?? 0;
$isLecturer = ($status['user_role'] ?? '') === 'lecturer' || ($status['user_role'] ?? '') === 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Practical Workflow - UNILIS SmartLabs</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f7fa; color: #333; }
        .container { max-width: 1000px; margin: 0 auto; padding: 20px; }
        
        .header { background: white; border-radius: 12px; padding: 25px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .header h1 { font-size: 24px; margin-bottom: 5px; }
        .header .subtitle { color: #666; font-size: 14px; }
        .header .practical-info { margin-top: 15px; display: flex; flex-wrap: wrap; gap: 15px; }
        .header .info-tag { background: #eef2f7; padding: 5px 12px; border-radius: 15px; font-size: 13px; }
        
        .progress-section { background: white; border-radius: 12px; padding: 25px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .progress-bar { height: 20px; background: #eef2f7; border-radius: 10px; overflow: hidden; margin: 15px 0; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, #3498db, #2ecc71); border-radius: 10px; transition: width 0.5s ease; width: <?php echo $progressPercent; ?>%; }
        .progress-label { display: flex; justify-content: space-between; font-size: 13px; color: #666; }
        .current-status { text-align: center; padding: 15px; background: #f0f9ff; border-radius: 8px; margin-top: 15px; }
        .current-status .state-name { font-size: 18px; font-weight: bold; color: #2c3e50; }
        .current-status .state-desc { font-size: 14px; color: #666; margin-top: 5px; }
        
        .workflow-timeline { background: white; border-radius: 12px; padding: 25px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .timeline-title { font-size: 18px; margin-bottom: 20px; color: #2c3e50; }
        .timeline { position: relative; padding-left: 40px; }
        .timeline::before { content: ''; position: absolute; left: 18px; top: 10px; bottom: 10px; width: 2px; background: #e0e0e0; }
        .timeline-item { position: relative; margin-bottom: 20px; padding-left: 20px; }
        .timeline-item:last-child { margin-bottom: 0; }
        .timeline-dot { position: absolute; left: -30px; top: 2px; width: 16px; height: 16px; border-radius: 50%; border: 2px solid #e0e0e0; background: white; }
        .timeline-dot.completed { background: #27ae60; border-color: #27ae60; }
        .timeline-dot.current { background: #f39c12; border-color: #f39c12; box-shadow: 0 0 0 3px rgba(243,156,18,0.3); animation: pulse 2s infinite; }
        .timeline-dot.locked { background: #95a5a6; border-color: #95a5a6; }
        @keyframes pulse { 0% { box-shadow: 0 0 0 3px rgba(243,156,18,0.3); } 50% { box-shadow: 0 0 0 6px rgba(243,156,18,0.1); } 100% { box-shadow: 0 0 0 3px rgba(243,156,18,0.3); } }
        .timeline-state { font-weight: 600; font-size: 14px; }
        .timeline-state.completed { color: #27ae60; }
        .timeline-state.current { color: #f39c12; }
        .timeline-state.locked { color: #95a5a6; }
        .timeline-desc { font-size: 12px; color: #999; margin-top: 2px; }
        .timeline-time { font-size: 11px; color: #bbb; margin-top: 2px; }
        
        .actions { background: white; border-radius: 12px; padding: 25px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .actions h3 { margin-bottom: 15px; color: #2c3e50; }
        .action-btn { display: inline-block; padding: 12px 30px; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.2s; text-decoration: none; color: white; margin: 5px; }
        .action-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .action-btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }
        .btn-primary { background: #3498db; }
        .btn-success { background: #27ae60; }
        .btn-warning { background: #f39c12; }
        .btn-danger { background: #e74c3c; }
        .btn-info { background: #8e44ad; }
        
        .details-section { background: white; border-radius: 12px; padding: 25px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .detail-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 15px; }
        .detail-item { padding: 10px; background: #f8f9fa; border-radius: 8px; }
        .detail-label { font-size: 12px; color: #999; text-transform: uppercase; }
        .detail-value { font-size: 14px; color: #333; margin-top: 3px; font-weight: 600; }
        .hash-value { font-family: monospace; font-size: 11px; word-break: break-all; }
        
        .blockchain-info { background: #eef2f7; border-radius: 8px; padding: 15px; margin-top: 15px; }
        .blockchain-info h4 { color: #2c3e50; margin-bottom: 8px; font-size: 14px; }
        .blockchain-info .hash { font-family: monospace; font-size: 12px; word-break: break-all; background: #fff; padding: 8px; border-radius: 4px; }
        
        .message { padding: 15px; border-radius: 8px; margin-bottom: 15px; font-size: 14px; }
        .message.success { background: #d5f5e3; color: #1e8449; border: 1px solid #a3e4c1; }
        .message.error { background: #fde8e8; color: #c0392b; border: 1px solid #f5b7b1; }
        .message.info { background: #d6eaf8; color: #1a5276; border: 1px solid #aed6f1; }
    </style>
</head>
<body>
    <div class="container">
        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message['type'] ?? 'info'; ?>"><?php echo htmlspecialchars($message['text'] ?? ''); ?></div>
        <?php endif; ?>
        
        <div class="header">
            <h1><?php echo htmlspecialchars($status['practical_title'] ?? $practical['title'] ?? 'Practical Workflow'); ?></h1>
            <div class="subtitle">Academic Integrity Verified Workflow</div>
            <div class="practical-info">
                <span class="info-tag">🏢 <?php echo htmlspecialchars($status['lab_name'] ?? $practical['lab_name'] ?? 'Lab'); ?></span>
                <span class="info-tag">📅 <?php echo htmlspecialchars($status['scheduled_date'] ?? $practical['scheduled_date'] ?? ''); ?></span>
                <span class="info-tag">⏰ <?php echo htmlspecialchars($status['start_time'] ?? $practical['start_time'] ?? ''); ?> - <?php echo htmlspecialchars($status['end_time'] ?? $practical['end_time'] ?? ''); ?></span>
                <span class="info-tag">📊 Progress: <?php echo $progressPercent; ?>%</span>
                <?php if ($status['verification_method'] ?? false): ?>
                    <span class="info-tag">🔐 Verified via: <?php echo htmlspecialchars($status['verification_method']); ?></span>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="progress-section">
            <h3>Workflow Progress</h3>
            <div class="progress-bar">
                <div class="progress-fill"></div>
            </div>
            <div class="progress-label">
                <span>Start</span>
                <span>Complete</span>
            </div>
            <div class="current-status">
                <div class="state-name" style="color: <?php echo $workflowStates[$currentState]['color'] ?? '#333'; ?>">
                    <?php echo $workflowStates[$currentState]['icon'] ?? '📌'; ?> <?php echo htmlspecialchars($currentState); ?>
                </div>
                <div class="state-desc"><?php echo htmlspecialchars($workflowStates[$currentState]['desc'] ?? ''); ?></div>
            </div>
        </div>
        
        <div class="workflow-timeline">
            <h3 class="timeline-title">Complete Workflow</h3>
            <div class="timeline">
                <?php
                $stateIndex = 0;
                $currentFound = false;
                foreach ($workflowStates as $stateName => $stateInfo):
                    $isCompleted = !$currentFound && $stateIndex <= array_search($currentState, array_keys($workflowStates));
                    $isCurrent = $stateName === $currentState;
                    $isLocked = !$isCompleted && !$isCurrent;
                    if ($isCurrent) $currentFound = true;
                    
                    $dotClass = $isCompleted ? 'completed' : ($isCurrent ? 'current' : 'locked');
                    $textClass = $isCompleted ? 'completed' : ($isCurrent ? 'current' : 'locked');
                ?>
                    <div class="timeline-item">
                        <div class="timeline-dot <?php echo $dotClass; ?>"></div>
                        <div class="timeline-state <?php echo $textClass; ?>">
                            <?php echo $stateInfo['icon']; ?> <?php echo htmlspecialchars($stateName); ?>
                        </div>
                        <div class="timeline-desc"><?php echo htmlspecialchars($stateInfo['desc']); ?></div>
                        <?php if ($isCurrent && ($status['verification_timestamp'] ?? false)): ?>
                            <div class="timeline-time">Verified: <?php echo htmlspecialchars($status['verification_timestamp']); ?></div>
                        <?php elseif ($isCurrent && ($status['practical_started_at'] ?? false)): ?>
                            <div class="timeline-time">Started: <?php echo htmlspecialchars($status['practical_started_at']); ?></div>
                        <?php elseif ($isCurrent && ($status['practical_completed_at'] ?? false)): ?>
                            <div class="timeline-time">Completed: <?php echo htmlspecialchars($status['practical_completed_at']); ?></div>
                        <?php elseif ($isCurrent && ($status['datasheet_submitted_at'] ?? false)): ?>
                            <div class="timeline-time">Submitted: <?php echo htmlspecialchars($status['datasheet_submitted_at']); ?></div>
                        <?php elseif ($isCurrent && ($status['report_submitted_at'] ?? false)): ?>
                            <div class="timeline-time">Submitted: <?php echo htmlspecialchars($status['report_submitted_at']); ?></div>
                        <?php endif; ?>
                    </div>
                <?php 
                    $stateIndex++;
                endforeach; 
                ?>
            </div>
        </div>
        
        <div class="actions">
            <h3>Actions</h3>
            <p style="color: #666; font-size: 14px; margin-bottom: 15px;">
                Current state: <strong><?php echo htmlspecialchars($currentState); ?></strong>
                <?php if ($status['start_denied_reason'] ?? false): ?>
                    <br><span style="color: #e74c3c; font-size: 12px;"><?php echo htmlspecialchars($status['start_denied_reason']); ?></span>
                <?php endif; ?>
            </p>
            
            <?php if ($currentState === 'Scheduled' || $currentState === 'Awaiting Verification'): ?>
                <a href="<?php echo APP_URL; ?>/workflow/verify-view/<?php echo $practical['id'] ?? ''; ?>" class="action-btn btn-primary">
                    🆔 Verify Attendance
                </a>
            <?php endif; ?>
            
            <?php if ($status['can_start_practical'] ?? false): ?>
                <button onclick="startPractical()" class="action-btn btn-success">
                    ▶️ Start Practical
                </button>
            <?php endif; ?>
            
            <?php if ($currentState === 'In Progress'): ?>
                <button onclick="completePractical()" class="action-btn btn-warning">
                    🏁 Complete Practical
                </button>
            <?php endif; ?>
            
            <?php if ($currentState === 'Practical Completed' || $currentState === 'Datasheet Generated'): ?>
                <button onclick="generateDatasheet()" class="action-btn btn-info">
                    📄 Generate Datasheet
                </button>
                <button onclick="submitDatasheet()" class="action-btn btn-primary">
                    🔒 Submit Datasheet
                </button>
            <?php endif; ?>
            
            <?php if ($currentState === 'Datasheet Submitted' || $currentState === 'Report Writing Open'): ?>
                <button onclick="openReportWriting()" class="action-btn btn-info">
                    ✍️ Write Report
                </button>
                <button onclick="submitReport()" class="action-btn btn-success">
                    📤 Submit Report
                </button>
            <?php endif; ?>
        </div>
        
        <div class="details-section">
            <h3>Session Details</h3>
            <div class="detail-grid">
                <div class="detail-item">
                    <div class="detail-label">Practical Started</div>
                    <div class="detail-value"><?php echo htmlspecialchars($status['practical_started_at'] ?? 'Not started'); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Practical Completed</div>
                    <div class="detail-value"><?php echo htmlspecialchars($status['practical_completed_at'] ?? 'Not completed'); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Datasheet Generated</div>
                    <div class="detail-value"><?php echo htmlspecialchars($status['datasheet_generated_at'] ?? 'Not generated'); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Datasheet Submitted</div>
                    <div class="detail-value"><?php echo htmlspecialchars($status['datasheet_submitted_at'] ?? 'Not submitted'); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Verification Method</div>
                    <div class="detail-value"><?php echo htmlspecialchars($status['verification_method'] ?? 'Not verified'); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Report Status</div>
                    <div class="detail-value"><?php echo htmlspecialchars($status['report_status'] ?? 'Not started'); ?></div>
                </div>
                <?php if ($status['grade'] ?? false): ?>
                <div class="detail-item">
                    <div class="detail-label">Grade</div>
                    <div class="detail-value"><?php echo htmlspecialchars($status['grade']); ?></div>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if ($status['datasheet_hash'] ?? false): ?>
            <div class="blockchain-info">
                <h4>🔗 Blockchain Verification</h4>
                <div class="hash">
                    <strong>Datasheet Hash:</strong> <?php echo htmlspecialchars($status['datasheet_hash']); ?>
                </div>
                <?php if ($status['datasheet_qr_token'] ?? false): ?>
                <div class="hash" style="margin-top: 8px;">
                    <strong>QR Token:</strong> <?php echo htmlspecialchars(substr($status['datasheet_qr_token'], 0, 32)) . '...'; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
    function apiCall(endpoint, data) {
        return fetch('<?php echo APP_URL; ?>/workflow/' + endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        }).then(r => r.json());
    }
    
    function startPractical() {
        apiCall('start-practical', { practical_id: '<?php echo $practical['id'] ?? ''; ?>' }).then(r => {
            if (r.success) { location.reload(); } 
            else { alert('Error: ' + r.message); }
        });
    }
    
    function completePractical() {
        if (!confirm('Complete this practical? Make sure you have all your data saved.')) return;
        apiCall('complete-practical', { practical_id: '<?php echo $practical['id'] ?? ''; ?>' }).then(r => {
            if (r.success) { 
                alert('Practical completed! Generating datasheet...');
                setTimeout(() => generateDatasheet(), 2000);
                location.reload();
            }
            else { alert('Error: ' + r.message); }
        });
    }
    
    function generateDatasheet() {
        apiCall('generate-datasheet', { practical_id: '<?php echo $practical['id'] ?? ''; ?>' }).then(r => {
            if (r.success) {
                alert('Datasheet generated! Please review and submit.');
                location.reload();
            } else { alert('Error: ' + r.message); }
        });
    }
    
    function submitDatasheet() {
        if (!confirm('Submit datasheet? This will lock it and create a blockchain record. You will NOT be able to modify it.')) return;
        apiCall('submit-datasheet', { practical_id: '<?php echo $practical['id'] ?? ''; ?>' }).then(r => {
            if (r.success) {
                alert('Datasheet submitted! Blockchain record created. You can now write your report.');
                location.reload();
            } else { alert('Error: ' + r.message); }
        });
    }
    
    function openReportWriting() {
        apiCall('open-report-writing', { practical_id: '<?php echo $practical['id'] ?? ''; ?>' }).then(r => {
            if (r.success) {
                alert('Report writing is now open!');
                location.reload();
            } else { alert('Error: ' + r.message); }
        });
    }
    
    function submitReport() {
        if (!confirm('Submit report? Make sure it is complete.')) return;
        apiCall('submit-report', { practical_id: '<?php echo $practical['id'] ?? ''; ?>' }).then(r => {
            if (r.success) {
                alert('Report submitted!');
                location.reload();
            } else { alert('Error: ' + r.message); }
        });
    }
    </script>
</body>
</html>