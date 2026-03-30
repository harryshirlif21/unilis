<?php
require_once '../../config/db.php';
session_start();

// Check if user is logged in and is a lecturer
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'lecturer') {
    header("Location: ../../index.html");
    exit;
}

$lecturer_id = $_SESSION['user_id'];
$page_title = "File Requests - UniLIS";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $team_id = intval($_POST['team_id'] ?? 0);
    $student_id = intval($_POST['student_id'] ?? 0);
    $request_title = trim($_POST['request_title'] ?? '');
    $request_description = trim($_POST['request_description'] ?? '');
    $file_type = $_POST['file_type'] ?? 'other';
    
    // Validate inputs
    $errors = [];
    if (empty($team_id)) $errors[] = "Please select a team";
    if (empty($student_id)) $errors[] = "Please select a student";
    if (empty($request_title)) $errors[] = "Request title is required";
    if (strlen($request_title) > 255) $errors[] = "Request title is too long (max 255 characters)";
    
    if (!empty($errors)) {
        $_SESSION['request_errors'] = $errors;
        header("Location: file_requests.php");
        exit;
    }
    
    // Submit request via API
    $api_url = "api/lecturer_file_requests.php";
    $data = [
        'action' => 'submit_file_request',
        'team_id' => $team_id,
        'student_id' => $student_id,
        'request_title' => $request_title,
        'request_description' => $request_description,
        'file_type' => $file_type
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if ($http_code === 200 && isset($result['success']) && $result['success']) {
        $_SESSION['request_success'] = "File request submitted successfully!";
    } else {
        $_SESSION['request_error'] = $result['error'] ?? "Failed to submit file request";
    }
    
    header("Location: file_requests.php");
    exit;
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $request_id = intval($_POST['request_id'] ?? 0);
    $new_status = $_POST['status'] ?? '';
    $response_message = trim($_POST['response_message'] ?? '');
    
    // Validate inputs
    if (empty($request_id) || !in_array($new_status, ['approved', 'rejected', 'completed'])) {
        $_SESSION['request_error'] = "Invalid input";
        header("Location: file_requests.php");
        exit;
    }
    
    // Submit status update via API
    $api_url = "api/lecturer_file_requests.php";
    $data = [
        'action' => 'update_request_status',
        'request_id' => $request_id,
        'status' => $new_status,
        'response_message' => $response_message
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if ($http_code === 200 && isset($result['success']) && $result['success']) {
        $_SESSION['request_success'] = "Request status updated successfully!";
    } else {
        $_SESSION['request_error'] = $result['error'] ?? "Failed to update request status";
    }
    
    header("Location: file_requests.php");
    exit;
}

// Get teams for dropdown
$teams_query = $conn->prepare("
    SELECT t.id, t.title, u.name AS unit_name, u.code AS unit_code,
           COUNT(tm.id) AS member_count
    FROM teams t
    JOIN units u ON t.unit_id = u.id
    LEFT JOIN team_members tm ON t.id = tm.team_id
    WHERE t.lecturer_id = ?
    GROUP BY t.id, t.title, u.name, u.code
    ORDER BY t.created_at DESC
");
$teams_query->bind_param("i", $lecturer_id);
$teams_query->execute();
$teams = $teams_query->get_result()->fetch_all(MYSQLI_ASSOC);
$teams_query->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title; ?></title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            color: #1a202c;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            color: #1e293b;
            font-size: 2rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .header p {
            color: #64748b;
            font-size: 1rem;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        .form-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .form-section h2 {
            color: #1e293b;
            font-size: 1.5rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
        }
        
        .form-group select,
        .form-group input[type="text"],
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #d1d5db;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-group select:focus,
        .form-group input[type="text"]:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .btn {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn:hover {
            background: #2563eb;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #6b7280;
        }
        
        .btn-secondary:hover {
            background: #52525b;
        }
        
        .requests-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .requests-section h2 {
            color: #1e293b;
            font-size: 1.5rem;
            margin-bottom: 20px;
        }
        
        .request-item {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        
        .request-item:hover {
            border-color: #3b82f6;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.1);
        }
        
        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .request-info h3 {
            color: #1e293b;
            font-size: 1.1rem;
            margin-bottom: 8px;
        }
        
        .request-meta {
            display: flex;
            gap: 15px;
            font-size: 0.9rem;
            color: #64748b;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-pending {
            background: #fef3c7;
            color: #856404;
        }
        
        .status-approved {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-rejected {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .status-completed {
            background: #d1fae5;
            color: #065f46;
        }
        
        .actions {
            margin-top: 15px;
            display: flex;
            gap: 10px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #3b82f6;
        }
        
        .alert-success {
            background: #d4edda;
            border-color: #22c55e;
            color: #166534;
        }
        
        .alert-error {
            background: #fef2f2;
            border-color: #ef4444;
            color: #991b1b;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>
                <i class="fas fa-file-contract"></i>
                File Request Management
            </h1>
            <p>Request files from your students and track their status</p>
        </div>
        
        <div class="content-grid">
            <!-- Request Form -->
            <div class="form-section">
                <h2>
                    <i class="fas fa-plus-circle"></i>
                    New File Request
                </h2>
                
                <?php if (isset($_SESSION['request_success'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?= $_SESSION['request_success']; ?>
                    </div>
                    <?php unset($_SESSION['request_success']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['request_error'])): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= $_SESSION['request_error']; ?>
                    </div>
                    <?php unset($_SESSION['request_error']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['request_errors'])): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php foreach ($_SESSION['request_errors'] as $error): ?>
                            <div><?= $error; ?></div>
                        <?php endforeach; ?>
                    </div>
                    <?php unset($_SESSION['request_errors']); ?>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="team_id">Select Team *</label>
                        <select name="team_id" id="team_id" required>
                            <option value="">-- Select Team --</option>
                            <?php foreach ($teams as $team): ?>
                                <option value="<?= $team['id']; ?>">
                                    <?= htmlspecialchars($team['title']); ?> 
                                    (<?= htmlspecialchars($team['unit_code']); ?> - <?= $team['member_count']; ?> members)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="student_id">Select Student *</label>
                        <select name="student_id" id="student_id" required>
                            <option value="">-- Select Student --</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="request_title">Request Title *</label>
                        <input type="text" name="request_title" id="request_title" required
                               placeholder="e.g., Assignment 1 Report" maxlength="255">
                    </div>
                    
                    <div class="form-group">
                        <label for="request_description">Description</label>
                        <textarea name="request_description" id="request_description"
                                  placeholder="Please provide details about the file you need..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="file_type">File Type</label>
                        <select name="file_type" id="file_type">
                            <option value="other">Other</option>
                            <option value="assignment">Assignment</option>
                            <option value="notes">Notes</option>
                            <option value="report">Report</option>
                        </select>
                    </div>
                    
                    <button type="submit" name="submit_request" class="btn">
                        <i class="fas fa-paper-plane"></i>
                        Submit Request
                    </button>
                </form>
            </div>
            
            <!-- Existing Requests -->
            <div class="requests-section">
                <h2>
                    <i class="fas fa-list"></i>
                    Recent Requests
                </h2>
                
                <div id="requests-container">
                    <div class="empty-state">
                        <i class="fas fa-spinner fa-spin"></i>
                        <p>Loading requests...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Load teams and populate student dropdown
        document.addEventListener('DOMContentLoaded', function() {
            loadTeams();
            loadRequests();
        });
        
        function loadTeams() {
            const teams = <?= json_encode($teams); ?>;
            const teamSelect = document.getElementById('team_id');
            const studentSelect = document.getElementById('student_id');
            
            // Populate teams dropdown
            teams.forEach(team => {
                const option = document.createElement('option');
                option.value = team.id;
                option.textContent = team.title;
                teamSelect.appendChild(option);
            });
            
            // Load team members when team is selected
            teamSelect.addEventListener('change', function() {
                const teamId = this.value;
                if (teamId) {
                    loadTeamMembers(teamId);
                } else {
                    // Clear student dropdown
                    studentSelect.innerHTML = '<option value="">-- Select Student --</option>';
                }
            });
        }
        
        function loadTeamMembers(teamId) {
            fetch('api/get_team_members.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'team_id=' + teamId
            })
            .then(response => response.json())
            .then(data => {
                const studentSelect = document.getElementById('student_id');
                studentSelect.innerHTML = '<option value="">-- Select Student --</option>';
                
                if (data.success && data.members) {
                    data.members.forEach(member => {
                        const option = document.createElement('option');
                        option.value = member.id;
                        option.textContent = `${member.name} (${member.reg_no})`;
                        studentSelect.appendChild(option);
                    });
                }
            })
            .catch(error => console.error('Error loading team members:', error));
        }
        
        function loadRequests() {
            fetch('api/get_file_requests.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_file_requests'
            })
            .then(response => response.json())
            .then(data => {
                const container = document.getElementById('requests-container');
                
                if (data.success && data.requests && data.requests.length > 0) {
                    container.innerHTML = data.requests.map(request => `
                        <div class="request-item">
                            <div class="request-header">
                                <div class="request-info">
                                    <h3>${request.request_title}</h3>
                                    <div class="request-meta">
                                        <span>Team: ${request.team_title || 'N/A'}</span>
                                        <span>Student: ${request.student_name || 'N/A'}</span>
                                    </div>
                                    <p><strong>Type:</strong> ${request.file_type}</p>
                                    <p><strong>Description:</strong> ${request.request_description || 'No description'}</p>
                                </div>
                                <div class="status-badge status-${request.status}">
                                    ${request.status}
                                </div>
                            </div>
                            <div class="request-meta">
                                <span>Requested: ${new Date(request.requested_at).toLocaleDateString()}</span>
                                ${request.responded_at ? `<span>Responded: ${new Date(request.responded_at).toLocaleDateString()}</span>` : ''}
                            </div>
                            ${request.response_message ? `<div style="margin-top: 10px; padding: 10px; background: #f8f9fa; border-radius: 5px;"><strong>Response:</strong> ${request.response_message}</div>` : ''}
                            <div class="actions">
                                ${request.status === 'pending' ? `
                                    <button class="btn btn-secondary" onclick="updateRequestStatus(${request.id}, 'approved')">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                    <button class="btn btn-secondary" onclick="updateRequestStatus(${request.id}, 'rejected')">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                ` : ''}
                            </div>
                        </div>
                    `).join('');
                } else {
                    container.innerHTML = `
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>No file requests found</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error loading requests:', error);
                const container = document.getElementById('requests-container');
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p>Error loading requests</p>
                    </div>
                `;
            });
        }
        
        function updateRequestStatus(requestId, newStatus) {
            const responseMessage = prompt(`Enter response message for ${newStatus}:`);
            if (responseMessage !== null) {
                const formData = new FormData();
                formData.append('action', 'update_status');
                formData.append('request_id', requestId);
                formData.append('status', newStatus);
                formData.append('response_message', responseMessage);
                
                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error updating request: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error updating request:', error);
                    alert('Error updating request');
                });
            }
        }
    </script>
</body>
</html>
