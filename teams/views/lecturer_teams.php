<?php
session_start();

/* =========================
   DATABASE CONNECTION
========================= */
require_once '../../config/db.php';

if (!isset($conn) || !$conn) {
    die("Database connection failed.");
}

/* =========================
   AUTH CHECK
========================= */
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'lecturer') {
    header("Location: ../../login.php");
    exit;
}

$lecturerId = $_SESSION['user_id'];

/* =========================
   CSRF TOKEN
========================= */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* =========================
   FETCH TEAMS FOR LECTURER UNITS WITH ENHANCED DATA
========================= */
$sql = "
SELECT 
    t.id AS team_id,
    t.title AS team_title,
    t.status,
    t.created_at AS team_created,
    t.assessment_type,
    t.description,
    u.name AS unit_name,
    u.code AS unit_code,
    COUNT(tm.student_id) AS member_count
FROM teams t
JOIN units u ON t.unit_id = u.id
JOIN lecturer_units lu ON u.id = lu.unit_id
LEFT JOIN team_members tm ON t.id = tm.team_id
WHERE lu.lecturer_id = ?
GROUP BY t.id
ORDER BY t.created_at DESC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Query preparation failed: " . $conn->error);
}
$stmt->bind_param("i", $lecturerId);
$stmt->execute();
$result = $stmt->get_result();

$teams = [];
while ($row = $result->fetch_assoc()) {
    $teams[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Lecturer Teams - UniLIS</title>
    <style>
        * { box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
            max-width: 1400px; margin: 0 auto; padding: 2rem; 
            background: #f8fafc; color: #1e293b;
        }
        h1 { color: #0f172a; margin-bottom: 2rem; font-size: 2rem; font-weight: 700; }
        
        /* Team Card Styles */
        .team-card { 
            background: white; 
            border-radius: 12px; 
            padding: 2rem; 
            margin-bottom: 2rem; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
        }
        .team-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: flex-start; 
            margin-bottom: 1.5rem;
        }
        .team-title-section h3 { 
            margin: 0 0 0.5rem 0; 
            font-size: 1.5rem; 
            font-weight: 700; 
            color: #0f172a;
        }
        .team-meta { 
            font-size: 0.9rem; 
            color: #64748b; 
            line-height: 1.5;
        }
        
        .badge { 
            padding: 0.4rem 0.8rem; 
            border-radius: 6px; 
            font-size: 0.8rem; 
            font-weight: 600; 
            color: white; 
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .active { background: #10b981; }
        .locked { background: #f59e0b; }
        .archived { background: #6b7280; }
        
        /* Team Leader Section */
        .team-leader {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin: 1.5rem 0;
        }
        .team-leader h4 { 
            margin: 0 0 1rem 0; 
            font-size: 1.1rem;
            opacity: 0.9;
        }
        .leader-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        .leader-contact-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .leader-contact-item strong {
            font-weight: 600;
        }
        
        /* Members Section */
        .members-section { margin: 2rem 0; }
        .members-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        .member-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1.5rem;
        }
        .member-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        .member-name {
            font-weight: 700;
            color: #0f172a;
            font-size: 1.1rem;
        }
        .member-role {
            padding: 0.2rem 0.6rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .role-leader { background: #fef3c7; color: #92400e; }
        .role-member { background: #dbeafe; color: #1e40af; }
        
        /* Marks Section */
        .marks-section {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e2e8f0;
        }
        .marks-form {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .form-group {
            display: flex;
            flex-direction: column;
        }
        .form-group label {
            font-size: 0.9rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        .form-group input, .form-group select, .form-group textarea {
            padding: 0.6rem;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        .submit-btn {
            background: #10b981;
            color: white;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .submit-btn:hover { background: #059669; }
        
        .marks-table {
            width: 100%;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
        }
        .marks-table th {
            background: #f8fafc;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 1px solid #e2e8f0;
        }
        .marks-table td {
            padding: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }
        .marks-table tr:last-child td {
            border-bottom: none;
        }
        
        /* Global Export Section */
        .global-export {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            text-align: center;
        }
        .global-export h2 {
            margin: 0 0 1rem 0;
            font-size: 1.5rem;
        }
        .global-export-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        .global-export-btn {
            padding: 0.8rem 2rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .export-all-pdf {
            background: #dc2626;
            color: white;
        }
        .export-all-pdf:hover {
            background: #b91c1c;
            transform: translateY(-2px);
        }
        .export-all-excel {
            background: #059669;
            color: white;
        }
        .export-all-excel:hover {
            background: #047857;
            transform: translateY(-2px);
        }
        
        /* Ellipsis Menu */
        .ellipsis-menu {
            position: relative;
            display: inline-block;
        }
        .ellipsis-btn {
            background: #6b7280;
            color: white;
            border: none;
            padding: 0.4rem 0.8rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
        }
        .ellipsis-btn:hover {
            background: #4b5563;
        }
        .ellipsis-content {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
            min-width: 150px;
        }
        .ellipsis-content.show {
            display: block;
        }
        .ellipsis-content a {
            display: block;
            padding: 0.5rem 1rem;
            color: #374151;
            text-decoration: none;
            font-size: 0.9rem;
            transition: background 0.2s;
        }
        .ellipsis-content a:hover {
            background: #f3f4f6;
        }
        
        .mark-box {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #3b82f6;
            border-radius: 3px;
            margin-right: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: center;
            line-height: 16px;
            font-size: 14px;
            color: #3b82f6;
            font-weight: bold;
        }
        
        .mark-box:hover {
            background: #3b82f6;
            color: white;
        }
        
        .mark-box.checked {
            background: #3b82f6;
            color: white;
        }
        
        .read-btn {
            background: #10b981;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 0.2rem 0.4rem;
            font-size: 0.7rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 24px;
            height: 20px;
        }
        
        .read-btn:hover {
            background: #059669;
            transform: scale(1.05);
        }
        
        .read-btn:active {
            transform: scale(0.95);
        }
        
        .file-viewer-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            display: none;
            align-items: center;
            justify-content: center;
        }
        
        .file-viewer-overlay.active {
            display: flex;
        }
        
        .file-viewer {
            background: white;
            border-radius: 8px;
            width: 90%;
            height: 90%;
            max-width: 1200px;
            max-height: 800px;
            display: flex;
            flex-direction: column;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }
        
        .file-viewer-header {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f9fafb;
            border-radius: 8px 8px 0 0;
        }
        
        .file-viewer-title {
            font-weight: 600;
            color: #374151;
            flex: 1;
            margin-right: 1rem;
        }
        
        .file-viewer-close {
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 0.5rem 1rem;
            cursor: pointer;
            font-size: 0.9rem;
            transition: background 0.2s ease;
        }
        
        .file-viewer-close:hover {
            background: #dc2626;
        }
        
        .file-viewer-content {
            flex: 1;
            padding: 1rem;
            overflow: auto;
            background: white;
            border-radius: 0 0 8px 8px;
        }
        
        .file-viewer-content iframe {
            width: 100%;
            height: 100%;
            border: none;
        }
        
        .file-viewer-content img {
            max-width: 100%;
            height: auto;
            display: block;
            margin: 0 auto;
        }
        
        .file-viewer-loading {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #6b7280;
            font-size: 1.1rem;
        }
        
        .file-viewer-error {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #ef4444;
            font-size: 1.1rem;
            text-align: center;
            padding: 2rem;
        }
        
        .empty { 
            padding: 3rem; 
            text-align: center; 
            color: #6b7280; 
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            body { padding: 1rem; }
            .team-header { flex-direction: column; gap: 1rem; }
            .leader-info { grid-template-columns: 1fr; }
            .members-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<h1>Teams for Your Units</h1>

<!-- Global Export Section -->
<div class="global-export">
    <h2>📊 Export All Teams & Marks</h2>
    <p style="margin: 0 0 1rem 0; opacity: 0.9;">Download comprehensive reports of all teams with member details and awarded marks</p>
    <div class="global-export-buttons">
        <a href="../../teams/api/export_all_teams_pdf.php" class="global-export-btn export-all-pdf">
            📄 Export All Teams (PDF)
        </a>
        <a href="../../teams/api/export_all_teams_excel.php" class="global-export-btn export-all-excel">
            📊 Export All Teams (Excel)
        </a>
    </div>
</div>

<!-- Peer Evaluation Insights -->
<div class="team-card" style="margin-top:0;">
    <div class="team-header">
        <div>
            <strong>Peer Evaluation Insights</strong><br>
            <small style="color:#666;">View team-level peer evaluation performance summary.</small>
        </div>
    </div>
    <div style="display:flex;gap:0.75rem;flex-wrap:wrap;align-items:flex-end;">
        <div>
            <label style="display:block;font-size:0.8rem;color:#555;margin-bottom:0.25rem;">Team</label>
            <select id="peerEvalTeamSelect" style="padding:0.5rem;border:1px solid #d1d5db;border-radius:6px;min-width:260px;">
                <option value="">-- Select Team --</option>
                <?php foreach ($teams as $team): ?>
                    <option value="<?= (int)$team['team_id']; ?>">
                        <?= htmlspecialchars($team['team_title']); ?> (<?= htmlspecialchars($team['unit_name']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="button" class="submit-btn" id="loadPeerEvalInsightsBtn" style="padding:0.6rem 1rem;">Load Insights</button>
        <button type="button" class="global-export-btn export-all-excel" id="exportPeerCsvBtn" style="padding:0.6rem 1rem;">Export CSV</button>
    </div>
    <p id="peerEvalInsightsStatus" style="margin:0.75rem 0 0.5rem 0;color:#666;">Select a team to load peer evaluation summary.</p>
    <div id="peerEvalInsightsBody"></div>
</div>

<?php if (empty($teams)): ?>
    <div class="empty">No teams found for your assigned units.</div>
<?php else: ?>
    <?php foreach ($teams as $team): ?>
        
        <?php
        // Fetch detailed team information including leader and members
        $teamDetailsSql = "
            SELECT 
                tm.role,
                s.id AS student_id,
                s.name AS student_name,
                s.reg_no,
                s.email,
                s.year_of_study,
                tm.joined_at
            FROM team_members tm
            JOIN students s ON tm.student_id = s.id
            WHERE tm.team_id = ?
            ORDER BY tm.role DESC, s.name ASC
        ";
        
        $teamDetailsStmt = $conn->prepare($teamDetailsSql);
        $teamDetailsStmt->bind_param("i", $team['team_id']);
        $teamDetailsStmt->execute();
        $teamMembers = $teamDetailsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $teamDetailsStmt->close();
        
        // Find team leader
        $teamLeader = null;
        foreach ($teamMembers as $member) {
            if ($member['role'] === 'leader') {
                $teamLeader = $member;
                break;
            }
        }
        
        // Fetch existing marks
        $marksSql = "
            SELECT 
                tm.mark,
                tm.max_mark,
                tm.mark_type,
                tm.component,
                tm.notes,
                tm.awarded_at,
                tm.student_id,
                s.name AS student_name,
                l.name AS lecturer_name
            FROM team_marks tm
            LEFT JOIN students s ON tm.student_id = s.id
            LEFT JOIN lecturers l ON tm.awarded_by = l.id
            WHERE tm.team_id = ?
            ORDER BY tm.awarded_at DESC
        ";
        
        $marksStmt = $conn->prepare($marksSql);
        $marksStmt->bind_param("i", $team['team_id']);
        $marksStmt->execute();
        $existingMarks = $marksStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $marksStmt->close();
        
        // Fetch team files for each member
        foreach ($teamMembers as &$member) {
            // Get team files uploaded by this member
            $teamFilesSql = "
                SELECT 
                    tf.id,
                    tf.filepath,
                    tf.original_name,
                    tf.mime_type,
                    tf.uploaded_at,
                    tf.uploader_id,
                    tf.file_size
                FROM team_files tf
                WHERE tf.team_id = ? AND tf.uploader_id = ?
                ORDER BY tf.uploaded_at DESC
            ";
            
            $teamFilesStmt = $conn->prepare($teamFilesSql);
            $teamFilesStmt->bind_param("ii", $team['team_id'], $member['student_id']);
            $teamFilesStmt->execute();
            $teamFiles = $teamFilesStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            // Get submission metadata from activity log
            $metadataSql = "
                SELECT action_detail, created_at
                FROM team_activity_log
                WHERE team_id = ? AND user_id = ? AND action_type = 'file_upload'
                ORDER BY created_at DESC
                LIMIT 1
            ";
            
            $metadataStmt = $conn->prepare($metadataSql);
            $metadataStmt->bind_param("ii", $team['team_id'], $member['student_id']);
            $metadataStmt->execute();
            $metadataResult = $metadataStmt->get_result();
            
            $submissionMetadata = null;
            if ($metadataResult->num_rows > 0) {
                $metadataRow = $metadataResult->fetch_assoc();
                $metadata = json_decode($metadataRow['action_detail'], true);
                if ($metadata) {
                    $submissionMetadata = $metadata;
                }
            }
            
            // Combine team files with metadata
            $member['team_files'] = $teamFiles;
            $member['submission_metadata'] = $submissionMetadata;
            $teamFilesStmt->close();
            $metadataStmt->close();
        }
        ?>

        <div class="team-card">
            <div class="team-header">
                <div class="team-title-section">
                    <h3>
                        <span class="mark-box" title="Award group mark" onclick="toggleMarkBox(this, <?= $team['team_id']; ?>)">□</span>
                        <?= htmlspecialchars($team['team_title']); ?>
                    </h3>
                    <div class="team-meta">
                        <strong>Unit:</strong> <?= htmlspecialchars($team['unit_name']); ?> (<?= htmlspecialchars($team['unit_code']); ?>)<br>
                        <strong>Type:</strong> <?= htmlspecialchars($team['assessment_type'] ?: 'General'); ?><br>
                        <strong>Created:</strong> <?= date('d M Y', strtotime($team['team_created'])); ?> |
                        <strong>Members:</strong> <?= $team['member_count']; ?>
                    </div>
                </div>
                <div>
                    <span class="badge <?= htmlspecialchars($team['status']); ?>">
                        <?= ucfirst($team['status']); ?>
                    </span>
                    <div class="ellipsis-menu" style="margin-top: 0.5rem;">
                        <button class="ellipsis-btn" onclick="toggleMenu(<?= $team['team_id']; ?>)">⋮</button>
                        <div id="menu-<?= $team['team_id']; ?>" class="ellipsis-content">
                            <a href="#" onclick="generatePDF(<?= $team['team_id']; ?>); return false;">📄 Export PDF</a>
                            <a href="#" onclick="generateExcel(<?= $team['team_id']; ?>); return false;">📊 Export Excel</a>
                            <a href="../../teams/api/peer_evaluation_report.php?team_id=<?= $team['team_id']; ?>&format=json" target="_blank">🧾 Peer Eval (JSON)</a>
                            <a href="../../teams/api/peer_evaluation_report.php?team_id=<?= $team['team_id']; ?>&format=csv" target="_blank">📥 Peer Eval (CSV)</a>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($team['description']): ?>
                <div style="margin: 1rem 0; padding: 1rem; background: #f8fafc; border-radius: 6px;">
                    <strong>Description:</strong> <?= htmlspecialchars($team['description']); ?>
                </div>
            <?php endif; ?>

            <!-- Team Leader Section -->
            <?php if ($teamLeader): ?>
                <div class="team-leader">
                    <h4>👑 Team Leader</h4>
                    <div class="leader-info">
                        <div class="leader-contact-item">
                            <strong>Name:</strong> <?= htmlspecialchars($teamLeader['student_name']); ?>
                        </div>
                        <div class="leader-contact-item">
                            <strong>Reg No:</strong> <?= htmlspecialchars($teamLeader['reg_no']); ?>
                        </div>
                        <div class="leader-contact-item">
                            <strong>📧 Email:</strong> <?= htmlspecialchars($teamLeader['email']); ?>
                        </div>
                        <div class="leader-contact-item">
                            <strong>📚 Year:</strong> <?= htmlspecialchars($teamLeader['year_of_study'] ?: 'Not specified'); ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Members Section -->
            <div class="members-section">
                <h4>👥 Team Members & Files</h4>
                <div class="members-grid">
                    <?php foreach ($teamMembers as $member): ?>
                        <div class="member-card">
                            <div class="member-header">
                                <div class="member-name">
                                    <span class="mark-box" title="Award individual mark" onclick="toggleMarkBox(this, <?= $team['team_id']; ?>, <?= $member['student_id']; ?>)">□</span>
                                    <?= htmlspecialchars($member['student_name']); ?>
                                </div>
                                <span class="member-role role-<?= $member['role']; ?>">
                                    <?= ucfirst($member['role']); ?>
                                </span>
                            </div>
                            <div style="font-size: 0.9rem; color: #6b7280; margin-bottom: 0.5rem;">
                                <?= htmlspecialchars($member['reg_no']); ?>
                            </div>
                            
                            <!-- Individual Mark Field -->
                            <div class="member-mark-field" style="margin: 0.5rem 0;">
                                <label style="font-size: 0.8rem; font-weight: 600; color: #374151;">Individual Mark:</label>
                                <div style="display: flex; gap: 0.5rem; align-items: center;">
                                    <input type="number" 
                                           id="individual_mark_<?= $member['student_id']; ?>"
                                           placeholder="0" 
                                           min="0" 
                                           max="100" 
                                           step="0.01"
                                           style="flex: 1; padding: 0.3rem; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.8rem;">
                                    <input type="text" 
                                           id="individual_component_<?= $member['student_id']; ?>"
                                           placeholder="Component" 
                                           style="flex: 1; padding: 0.3rem; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.8rem;">
                                    <button type="button" onclick="awardIndividualMark(<?= $member['student_id']; ?>, <?= $team['team_id']; ?>)" class="submit-btn" style="padding: 0.3rem 0.6rem; font-size: 0.7rem; white-space: nowrap;">
                                        Award
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Team Files -->
                            <?php if (!empty($member['team_files'])): ?>
                                <div class="member-files" style="margin-top: 0.5rem;">
                                    <label style="font-size: 0.8rem; font-weight: 600; color: #374151;">📁 Team Files:</label>
                                    
                                    <?php if ($member['submission_metadata']): ?>
                                        <div class="submission-metadata" style="margin-bottom: 0.5rem; padding: 0.5rem; border: 1px solid #e5e7eb; border-radius: 4px; background: #f0f9ff;">
                                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                                <div style="font-weight: 600; color: #1f2937; font-size: 0.8rem;">
                                                    <?= htmlspecialchars($member['submission_metadata']['submission_title'] ?: 'Untitled Submission'); ?>
                                                </div>
                                                <span class="submission-type" style="display: inline-block; padding: 0.2rem 0.4rem; border-radius: 12px; font-size: 0.6rem; font-weight: 600; 
                                                    <?php if (($member['submission_metadata']['submission_type'] ?? 'individual') === 'team'): ?>
                                                        background: #dbeafe; color: #1e40af;
                                                    <?php else: ?>
                                                        background: #dcfce7; color: #166534;
                                                    <?php endif; ?>
                                                ">
                                                    <?= strtoupper($member['submission_metadata']['submission_type'] ?? 'INDIVIDUAL'); ?>
                                                </span>
                                            </div>
                                            
                                            <?php if (!empty($member['submission_metadata']['submission_description'])): ?>
                                                <div style="margin: 0.5rem 0; padding: 0.3rem; background: #f3f4f6; border-radius: 3px; font-size: 0.7rem; color: #4b5563;">
                                                    <strong>Description:</strong> <?= htmlspecialchars($member['submission_metadata']['submission_description']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php foreach ($member['team_files'] as $file): ?>
                                        <div class="file-item" style="display: flex; justify-content: space-between; align-items: center; padding: 0.3rem 0; border-bottom: 1px solid #e5e7eb; font-size: 0.75rem;">
                                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                <button type="button" onclick="viewTeamFile(<?= $file['id']; ?>)" class="read-btn" title="Read file">📖</button>
                                                <a href="#" onclick="viewTeamFile(<?= $file['id']; ?>); return false;" style="color: #3b82f6; text-decoration: none;">
                                                    📄 <?= htmlspecialchars($file['original_name']); ?>
                                                </a>
                                                <span style="color: #6b7280; margin-left: 0.5rem; font-size: 0.7rem;">
                                                    (<?= number_format($file['file_size'] / 1024, 2); ?>KB)
                                                </span>
                                            </div>
                                            <span style="color: #9ca3af; font-size: 0.7rem;">
                                                <?= date('M j, Y H:i', strtotime($file['uploaded_at'])); ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Group Mark Field -->
                <div class="group-mark-section" style="margin-top: 1.5rem; padding: 1rem; background: #f0f9ff; border-radius: 8px; border: 1px solid #bae6fd;">
                    <h5 style="margin: 0 0 0.5rem 0; color: #0369a1;">🏆 Group Mark</h5>
                    <div style="display: flex; gap: 1rem; align-items: flex-end;">
                        <div style="flex: 1;">
                            <label style="font-size: 0.8rem; font-weight: 600; color: #374151;">Mark:</label>
                            <input type="number" 
                                   id="group_mark_<?= $team['team_id']; ?>"
                                   placeholder="0" 
                                   min="0" 
                                   max="100" 
                                   step="0.01"
                                   style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px;">
                        </div>
                        <div style="flex: 2;">
                            <label style="font-size: 0.8rem; font-weight: 600; color: #374151;">Component:</label>
                            <input type="text" 
                                   id="group_component_<?= $team['team_id']; ?>"
                                   placeholder="e.g. Group Project, Presentation" 
                                   style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px;">
                        </div>
                        <div style="flex: 1;">
                            <label style="font-size: 0.8rem; font-weight: 600; color: #374151;">Max Mark:</label>
                            <input type="number" 
                                   id="group_max_mark_<?= $team['team_id']; ?>"
                                   value="100" 
                                   min="1" 
                                   step="0.01"
                                   style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px;">
                        </div>
                        <button type="button" onclick="awardGroupMark(<?= $team['team_id']; ?>)" class="submit-btn" style="padding: 0.5rem 1rem; font-size: 0.8rem;">
                            Award Group Mark
                        </button>
                    </div>
                    <div style="margin-top: 0.5rem;">
                        <label style="font-size: 0.8rem; font-weight: 600; color: #374151;">Notes:</label>
                        <textarea id="group_notes_<?= $team['team_id']; ?>" 
                                  placeholder="Comments about this group mark..." 
                                  style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; resize: vertical; min-height: 50px; font-size: 0.8rem;"></textarea>
                    </div>
                </div>
            </div>

            <!-- Marks Section -->
            <div class="marks-section">
                <h4>🏆 Marks Awarding</h4>
                
                <!-- Award Mark Form -->
                <div class="marks-form">
                    <form id="markForm<?= $team['team_id']; ?>" onsubmit="awardMark(event, <?= $team['team_id']; ?>)">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Mark Type</label>
                                <select name="mark_type" required onchange="toggleStudentField(this, <?= $team['team_id']; ?>)">
                                    <option value="">Select type</option>
                                    <option value="team">Team Mark</option>
                                    <option value="individual">Individual Mark</option>
                                </select>
                            </div>
                            <div class="form-group" id="studentGroup<?= $team['team_id']; ?>" style="display: none;">
                                <label>Student</label>
                                <select name="student_id">
                                    <option value="">Select student</option>
                                    <?php foreach ($teamMembers as $member): ?>
                                        <option value="<?= $member['student_id']; ?>">
                                            <?= htmlspecialchars($member['student_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Component</label>
                                <input type="text" name="component" placeholder="e.g. Presentation, Report" required>
                            </div>
                            <div class="form-group">
                                <label>Mark</label>
                                <input type="number" name="mark" min="0" max="100" step="0.01" required>
                            </div>
                            <div class="form-group">
                                <label>Max Mark</label>
                                <input type="number" name="max_mark" min="1" value="100" step="0.01" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Notes (optional)</label>
                            <textarea name="notes" placeholder="Additional comments about this mark..."></textarea>
                        </div>
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                        <button type="submit" class="submit-btn">Award Mark</button>
                    </form>
                </div>

                <!-- Existing Marks Table -->
                <?php if (!empty($existingMarks)): ?>
                    <table class="marks-table">
                        <thead>
                            <tr>
                                <th>Component</th>
                                <th>Type</th>
                                <th>Student</th>
                                <th>Mark</th>
                                <th>Max</th>
                                <th>Awarded by</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($existingMarks as $mark): ?>
                                <tr>
                                    <td><?= htmlspecialchars($mark['component']); ?></td>
                                    <td><?= ucfirst($mark['mark_type']); ?></td>
                                    <td><?= htmlspecialchars($mark['student_name'] ?: 'Team'); ?></td>
                                    <td><?= number_format($mark['mark'], 2); ?></td>
                                    <td><?= number_format($mark['max_mark'], 2); ?></td>
                                    <td><?= htmlspecialchars($mark['lecturer_name']); ?></td>
                                    <td><?= date('d M Y', strtotime($mark['awarded_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

        </div>

    <?php endforeach; ?>
<?php endif; ?>

<script>
function toggleMenu(teamId) {
    const menu = document.getElementById('menu-' + teamId);
    
    // Close all other menus
    document.querySelectorAll('.ellipsis-content').forEach(m => {
        if (m.id !== 'menu-' + teamId) {
            m.classList.remove('show');
        }
    });
    
    // Toggle current menu
    menu.classList.toggle('show');
}

// Close menus when clicking outside
document.addEventListener('click', function(event) {
    if (!event.target.matches('.ellipsis-btn')) {
        document.querySelectorAll('.ellipsis-content').forEach(menu => {
            menu.classList.remove('show');
        });
    }
});

function toggleStudentField(select, teamId) {
    const studentGroup = document.getElementById('studentGroup' + teamId);
    const studentSelect = studentGroup.querySelector('select');
    
    if (select.value === 'individual') {
        studentGroup.style.display = 'block';
        studentSelect.required = true;
    } else {
        studentGroup.style.display = 'none';
        studentSelect.required = false;
        studentSelect.value = '';
    }
}

function awardMark(event, teamId) {
    event.preventDefault();
    
    const form = document.getElementById('markForm' + teamId);
    const formData = new FormData(form);
    
    fetch('../../teams/api/award_mark.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Mark awarded successfully!');
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while awarding the mark.');
    });
}

function awardGroupMark(teamId) {
    const mark = document.getElementById('group_mark_' + teamId).value;
    const component = document.getElementById('group_component_' + teamId).value;
    const maxMark = document.getElementById('group_max_mark_' + teamId).value;
    const notes = document.getElementById('group_notes_' + teamId).value;
    
    if (!mark || !component) {
        alert('Please fill in the mark and component fields');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'award_mark');
    formData.append('team_id', teamId);
    formData.append('mark_type', 'team');
    formData.append('mark', mark);
    formData.append('max_mark', maxMark);
    formData.append('component', component);
    formData.append('notes', notes);
    formData.append('csrf_token', '<?= $_SESSION['csrf_token']; ?>');
    
    fetch('../../teams/api/award_mark.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Group mark awarded successfully!');
            // Clear the form
            document.getElementById('group_mark_' + teamId).value = '';
            document.getElementById('group_component_' + teamId).value = '';
            document.getElementById('group_max_mark_' + teamId).value = '100';
            document.getElementById('group_notes_' + teamId).value = '';
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while awarding the group mark.');
    });
}

function awardIndividualMark(studentId, teamId) {
    const mark = document.getElementById('individual_mark_' + studentId).value;
    const component = document.getElementById('individual_component_' + studentId).value;
    
    if (!mark || !component) {
        alert('Please fill in the mark and component fields');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'award_mark');
    formData.append('team_id', teamId);
    formData.append('student_id', studentId);
    formData.append('mark_type', 'individual');
    formData.append('mark', mark);
    formData.append('max_mark', '100');
    formData.append('component', component);
    formData.append('csrf_token', '<?= $_SESSION['csrf_token']; ?>');
    
    fetch('../../teams/api/award_mark.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Individual mark awarded successfully!');
            // Clear the form
            document.getElementById('individual_mark_' + studentId).value = '';
            document.getElementById('individual_component_' + studentId).value = '';
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while awarding the individual mark.');
    });
}

function viewSubmission(submissionId) {
    openFileViewer('submission', submissionId);
}

function viewTeamFile(fileId) {
    openFileViewer('team', fileId);
}

function openFileViewer(type, id) {
    const overlay = document.getElementById('fileViewerOverlay');
    const title = document.getElementById('fileViewerTitle');
    const content = document.getElementById('fileViewerContent');
    
    // Show loading state
    overlay.classList.add('active');
    title.textContent = 'Loading...';
    content.innerHTML = '<div class="file-viewer-loading">Loading file...</div>';
    
    // Determine file URL based on type
    let fileUrl;
    if (type === 'submission') {
        fileUrl = `../../teams/api/serve_file.php?submission_id=${id}`;
    } else {
        fileUrl = `../../teams/api/serve_file.php?file_id=${id}`;
    }
    
    // Fetch file info first
    fetch(fileUrl, { method: 'HEAD' })
        .then(response => {
            const contentType = response.headers.get('content-type') || '';
            const fileName = response.headers.get('content-disposition') || 
                           `file_${id}`;
            
            // Update title
            title.textContent = `File Viewer - ${fileName}`;
            
            // Handle different file types
            if (contentType.includes('application/pdf')) {
                // Show PDF in iframe
                content.innerHTML = `<iframe src="${fileUrl}" type="application/pdf"></iframe>`;
            } else if (contentType.includes('image/')) {
                // Show image
                content.innerHTML = `<img src="${fileUrl}" alt="File preview">`;
            } else if (contentType.includes('text/')) {
                // Show text file
                fetch(fileUrl)
                    .then(response => response.text())
                    .then(text => {
                        content.innerHTML = `<pre style="white-space: pre-wrap; word-wrap: break-word;">${text}</pre>`;
                    })
                    .catch(error => {
                        content.innerHTML = `<div class="file-viewer-error">Error loading text file: ${error.message}</div>`;
                    });
            } else if (contentType.includes('application/vnd.openxmlformats-officedocument') ||
                      contentType.includes('application/msword') ||
                      contentType.includes('application/vnd.ms-')) {
                // Office documents - show download prompt
                content.innerHTML = `
                    <div class="file-viewer-error">
                        <div>This file type (${contentType}) cannot be previewed inline.</div>
                        <div style="margin-top: 1rem;">
                            <a href="${fileUrl}" download style="background: #3b82f6; color: white; padding: 0.5rem 1rem; text-decoration: none; border-radius: 4px;">
                                Download File
                            </a>
                        </div>
                    </div>
                `;
            } else {
                // Other file types - show download prompt
                content.innerHTML = `
                    <div class="file-viewer-error">
                        <div>Preview not available for this file type.</div>
                        <div style="margin-top: 1rem;">
                            <a href="${fileUrl}" download style="background: #3b82f6; color: white; padding: 0.5rem 1rem; text-decoration: none; border-radius: 4px;">
                                Download File
                            </a>
                        </div>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('File viewer error:', error);
            title.textContent = 'Error Loading File';
            
            // Check if it's a 404 error
            if (error.message.includes('not found') || error.message.includes('404')) {
                content.innerHTML = `
                    <div class="file-viewer-error">
                        <div style="font-size: 1.2rem; margin-bottom: 1rem;">📁 File Not Found</div>
                        <div style="margin-bottom: 1rem;">The requested file could not be found on the server.</div>
                        <div style="font-size: 0.9rem; color: #666; margin-bottom: 1rem;">
                            This could mean:
                            <ul style="text-align: left; margin-top: 0.5rem;">
                                <li>The file was deleted or moved</li>
                                <li>The file upload was not completed</li>
                                <li>There is a database/path mismatch</li>
                            </ul>
                        </div>
                        <div style="margin-top: 1.5rem;">
                            <button onclick="closeFileViewer()" style="background: #3b82f6; color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer; margin-right: 0.5rem;">
                                Close Viewer
                            </button>
                            ${type === 'team' ? `
                                <button onclick="fixTeamFile(${id})" style="background: #10b981; color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer;">
                                    Fix File Path
                                </button>
                            ` : ''}
                        </div>
                    </div>
                `;
            } else {
                content.innerHTML = `
                    <div class="file-viewer-error">
                        <div style="font-size: 1.2rem; margin-bottom: 1rem;">⚠️ Error Loading File</div>
                        <div style="margin-bottom: 1rem;">An error occurred while trying to load the file.</div>
                        <div style="font-size: 0.9rem; color: #666; margin-bottom: 1rem;">
                            Error: ${error.message}
                        </div>
                        <div style="margin-top: 1.5rem;">
                            <button onclick="closeFileViewer()" style="background: #3b82f6; color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer;">
                                Close Viewer
                            </button>
                        </div>
                    </div>
                `;
            }
        });
}

function closeFileViewer() {
    const overlay = document.getElementById('fileViewerOverlay');
    overlay.classList.remove('active');
}

function fixSubmissionFile(submissionId) {
    // Open the fix tool in a new window
    const fixUrl = `../fix_team_submissions.php`;
    window.open(fixUrl, '_blank', 'width=1200,height=800,scrollbars=yes');
}

function fixTeamFile(fileId) {
    // Open the fix tool in a new window for team files
    const fixUrl = `../fix_team_files.php`;
    window.open(fixUrl, '_blank', 'width=1200,height=800,scrollbars=yes');
}

// Close viewer when clicking overlay background
document.addEventListener('click', function(event) {
    const overlay = document.getElementById('fileViewerOverlay');
    if (event.target === overlay) {
        closeFileViewer();
    }
});

// Close viewer with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeFileViewer();
    }
});

function toggleMarkBox(element, teamId, studentId = null) {
    if (element.classList.contains('checked')) {
        element.classList.remove('checked');
        element.textContent = '□';
    } else {
        element.classList.add('checked');
        element.textContent = '☑';
        
        // Open the appropriate marking form
        if (studentId) {
            // Individual mark - scroll to individual mark field
            const individualField = document.getElementById('individual_mark_' + studentId);
            if (individualField) {
                individualField.scrollIntoView({ behavior: 'smooth' });
                individualField.focus();
            }
        } else {
            // Group mark - scroll to group mark section
            const groupSection = document.getElementById('group_mark_' + teamId);
            if (groupSection) {
                groupSection.scrollIntoView({ behavior: 'smooth' });
                groupSection.focus();
            }
        }
    }
}

function generatePDF(teamId) {
    window.open('../../teams/api/export_pdf.php?team_id=' + teamId, '_blank');
}

function generateExcel(teamId) {
    window.open('../../teams/api/export_excel.php?team_id=' + teamId, '_blank');
}

async function loadPeerEvalInsights(teamId) {
    const statusEl = document.getElementById('peerEvalInsightsStatus');
    const bodyEl = document.getElementById('peerEvalInsightsBody');
    if (!statusEl || !bodyEl) return;

    statusEl.textContent = 'Loading peer evaluation summary...';
    bodyEl.innerHTML = '';

    try {
        const res = await fetch(`../../teams/api/peer_evaluation_report.php?team_id=${encodeURIComponent(teamId)}&format=json`, {
            credentials: 'same-origin'
        });
        const data = await res.json().catch(() => null);
        if (!res.ok || !data || !data.success) {
            throw new Error(data?.error || ('HTTP ' + res.status));
        }

        const rows = data.summary || [];
        if (rows.length === 0) {
            statusEl.textContent = 'No peer evaluations yet for this team.';
            return;
        }
        statusEl.textContent = '';

        const sorted = [...rows].sort((a, b) => Number(b.avg_overall || 0) - Number(a.avg_overall || 0));
        const top = sorted[0];
        const low = sorted[sorted.length - 1];

        let html = `
            <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:0.75rem;">
                <div style="flex:1;min-width:220px;background:#ecfdf5;border:1px solid #bbf7d0;border-radius:8px;padding:10px;">
                    <div style="font-weight:700;color:#166534;">Top performer</div>
                    <div>${top.evaluatee_name} — ${Number(top.avg_overall).toFixed(2)} / 5</div>
                </div>
                <div style="flex:1;min-width:220px;background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:10px;">
                    <div style="font-weight:700;color:#9a3412;">Needs support</div>
                    <div>${low.evaluatee_name} — ${Number(low.avg_overall).toFixed(2)} / 5</div>
                </div>
            </div>
            <table class="marks-table">
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Responses</th>
                        <th>Contribution</th>
                        <th>Communication</th>
                        <th>Quality</th>
                        <th>Reliability</th>
                        <th>Overall</th>
                    </tr>
                </thead>
                <tbody>
        `;
        rows.forEach(r => {
            html += `
                <tr>
                    <td>${r.evaluatee_name}</td>
                    <td>${r.responses}</td>
                    <td>${r.avg_contribution}</td>
                    <td>${r.avg_communication}</td>
                    <td>${r.avg_quality}</td>
                    <td>${r.avg_reliability}</td>
                    <td><strong>${r.avg_overall}</strong></td>
                </tr>
            `;
        });
        html += '</tbody></table>';
        bodyEl.innerHTML = html;
    } catch (err) {
        statusEl.textContent = 'Failed to load peer evaluation insights: ' + err.message;
    }
}

document.getElementById('loadPeerEvalInsightsBtn')?.addEventListener('click', () => {
    const teamId = document.getElementById('peerEvalTeamSelect')?.value;
    if (!teamId) {
        const statusEl = document.getElementById('peerEvalInsightsStatus');
        if (statusEl) statusEl.textContent = 'Please select a team first.';
        return;
    }
    loadPeerEvalInsights(teamId);
});

document.getElementById('exportPeerCsvBtn')?.addEventListener('click', () => {
    const teamId = document.getElementById('peerEvalTeamSelect')?.value;
    if (!teamId) {
        const statusEl = document.getElementById('peerEvalInsightsStatus');
        if (statusEl) statusEl.textContent = 'Please select a team first.';
        return;
    }
    window.open(`../../teams/api/peer_evaluation_report.php?team_id=${encodeURIComponent(teamId)}&format=csv`, '_blank');
});
</script>

<!-- File Viewer Overlay -->
<div id="fileViewerOverlay" class="file-viewer-overlay">
    <div class="file-viewer">
        <div class="file-viewer-header">
            <div class="file-viewer-title" id="fileViewerTitle">File Viewer</div>
            <button class="file-viewer-close" onclick="closeFileViewer()">Close</button>
        </div>
        <div class="file-viewer-content" id="fileViewerContent">
            <div class="file-viewer-loading">Loading file...</div>
        </div>
    </div>
</div>

</body>
</html>
