<?php
session_start();

// Check if user is logged in and is a lecturer
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'lecturer') {
    header("Location: ../../login.php");
    exit;
}

require_once __DIR__ . '/../../config/db.php';

// Check if team tables exist
$teamTablesExist = false;
try {
    $checkTables = $conn->query("SHOW TABLES LIKE 'team_members'");
    if ($checkTables && $checkTables->num_rows > 0) {
        $teamTablesExist = true;
    }
} catch (Exception $e) {
    // Tables don't exist
}

if (!$teamTablesExist) {
    die("<h2>Teams Module Not Available</h2><p>The teams system tables have not been created. Please ask your administrator to run the migrate_teams_system.php migration script.</p><p><a href='../../lecturer/lecturer_dashboard.php'>Return to Dashboard</a></p>");
}

// Get current lecturer
$lecturer_id = $_SESSION['user_id'];

// Fetch units for this lecturer
$stmt = $conn->prepare("SELECT id, name FROM units WHERE lecturer_id = ? ORDER BY name ASC");
$stmt->bind_param("i", $lecturer_id);
$stmt->execute();
$units = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Generate CSRF if missing
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve Membership Requests - UniLIS</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .header h1 {
            margin-bottom: 0.5rem;
        }
        
        .header p {
            opacity: 0.9;
        }
        
        .content {
            padding: 2rem;
        }
        
        .unit-section {
            margin-bottom: 2.5rem;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 1.5rem;
            background: #f9f9f9;
        }
        
        .unit-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #667eea;
        }
        
        .request-item {
            background: white;
            border-left: 4px solid #667eea;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .request-item.removal {
            border-left-color: #ff6b6b;
        }
        
        .request-item.leave {
            border-left-color: #ffc107;
        }
        
        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .request-type {
            display: inline-block;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .request-type.leave {
            background: #fffbea;
            color: #f59e0b;
        }
        
        .request-type.removal {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .request-details {
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin: 1rem 0;
        }
        
        .detail-row {
            display: grid;
            gap: 0.5rem;
            margin-bottom: 0.8rem;
        }
        
        .detail-label {
            font-weight: 600;
            color: #555;
            font-size: 0.9rem;
        }
        
        .detail-value {
            color: #333;
        }
        
        .approval-status {
            display: flex;
            gap: 2rem;
            margin: 1rem 0;
            font-size: 0.9rem;
        }
        
        .approval-status div {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .approval-badge {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            color: white;
        }
        
        .approved {
            background: #10b981;
        }
        
        .pending {
            background: #f59e0b;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            margin-top: 1.5rem;
        }
        
        button {
            padding: 0.7rem 1.2rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .btn-approve {
            background: #10b981;
            color: white;
        }
        
        .btn-approve:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        
        .btn-reject {
            background: #ef4444;
            color: white;
        }
        
        .btn-reject:hover {
            background: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
        }
        
        .no-requests {
            text-align: center;
            color: #999;
            padding: 2rem;
        }
        
        #message {
            margin: 1rem 0;
            padding: 1rem;
            border-radius: 4px;
            font-weight: 500;
        }
        
        .message-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .loading {
            text-align: center;
            color: #999;
            padding: 2rem;
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 1.5rem;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Membership Request Approvals</h1>
            <p>Review and approve/reject team membership requests</p>
        </div>
        
        <div class="content">
            <a href="lecturer_teams.php" class="back-link">← Back to Teams</a>
            
            <div id="message"></div>
            <div id="requests-container" class="loading">Loading requests...</div>
        </div>
    </div>

    <script>
        const csrf = "<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>";
        const lecturerId = <?php echo json_encode($lecturer_id); ?>;
        const units = <?php echo json_encode($units); ?>;
        
        const messageDiv = document.getElementById('message');
        const requestsContainer = document.getElementById('requests-container');

        function showMessage(text, type = 'error') {
            messageDiv.className = type === 'success' ? 'message-success' : 'message-error';
            messageDiv.textContent = text;
            messageDiv.scrollIntoView({ behavior: 'smooth' });
        }

        async function loadRequests() {
            try {
                if (units.length === 0) {
                    requestsContainer.innerHTML = '<div class="no-requests">No units assigned to you</div>';
                    return;
                }

                let allRequests = [];
                let html = '';

                // Fetch requests for each unit
                for (const unit of units) {
                    try {
                        // Get teams for this unit
                        const teamsRes = await fetch(`/teams/api/get_teams_by_unit.php?unit_id=${unit.id}`, {
                            credentials: 'same-origin'
                        });
                        
                        if (!teamsRes.ok) continue;
                        
                        const teamsData = await teamsRes.json();
                        if (!teamsData.success) continue;
                        
                        const teams = teamsData.teams || [];
                        let unitRequests = [];

                        // Get requests for each team
                        for (const team of teams) {
                            try {
                                const reqRes = await fetch(`/teams/api/get_pending_membership_requests.php?team_id=${team.id}`, {
                                    credentials: 'same-origin'
                                });
                                
                                if (!reqRes.ok) continue;
                                
                                const reqData = await reqRes.json();
                                if (!reqData.success) continue;
                                
                                const requests = reqData.requests || [];
                                unitRequests = unitRequests.concat(requests.map(r => ({
                                    ...r,
                                    teamTitle: team.title
                                })));
                            } catch (e) {
                                console.error('Error fetching requests for team:', e);
                            }
                        }

                        // Render unit section if there are requests
                        if (unitRequests.length > 0) {
                            html += `<div class="unit-section">
                                <div class="unit-title">${unit.name}</div>`;
                            
                            unitRequests.forEach(req => {
                                const reqType = req.request_type === 'leave' ? 'leave' : 'removal';
                                const reqLabel = req.request_type === 'leave' 
                                    ? '✋ Leave Request' 
                                    : '🚫 Removal Request';
                                
                                const hasLecturerApproval = req.approved_by_lecturer;
                                const hasTeamLeadApproval = req.approved_by_team_lead;
                                const isPending = req.status === 'pending';

                                html += `
                                    <div class="request-item ${reqType}" data-request-id="${req.id}">
                                        <div class="request-header">
                                            <span class="request-type ${reqType}">${reqLabel}</span>
                                            <span style="color:#999; font-size:0.85rem;">${new Date(req.created_at).toLocaleDateString()}</span>
                                        </div>
                                        
                                        <div class="request-details">
                                            <div class="detail-row">
                                                <span class="detail-label">Team:</span>
                                                <span class="detail-value">${req.teamTitle || 'Unknown Team'}</span>
                                            </div>
                                            <div class="detail-row">
                                                <span class="detail-label">Student:</span>
                                                <span class="detail-value">${req.student_name} (${req.student_reg})</span>
                                            </div>
                                            <div class="detail-row">
                                                <span class="detail-label">Reason:</span>
                                                <span class="detail-value">${req.reason || '(not provided)'}</span>
                                            </div>
                                        </div>
                                        
                                        <div class="approval-status">
                                            <div>
                                                <span class="approval-badge ${hasLecturerApproval ? 'approved' : 'pending'}">${hasLecturerApproval ? '✓' : '⏳'}</span>
                                                <span>Lecturer Approval: ${hasLecturerApproval ? 'Approved' : 'Pending'}</span>
                                            </div>
                                            <div>
                                                <span class="approval-badge ${hasTeamLeadApproval ? 'approved' : 'pending'}">${hasTeamLeadApproval ? '✓' : '⏳'}</span>
                                                <span>Team Lead Approval: ${hasTeamLeadApproval ? 'Approved' : 'Pending'}</span>
                                            </div>
                                        </div>
                                        
                                        ${isPending ? `
                                            <div class="action-buttons">
                                                <button class="btn-approve" onclick="approveRequest(${req.id})">Approve</button>
                                                <button class="btn-reject" onclick="rejectRequest(${req.id})">Reject</button>
                                            </div>
                                        ` : `
                                            <p style="margin-top:1rem; color:#999; font-size:0.9rem;">This request has been processed</p>
                                        `}
                                    </div>
                                `;
                            });
                            
                            html += '</div>';
                        }
                    } catch (e) {
                        console.error('Error loading unit requests:', e);
                    }
                }

                if (html === '') {
                    requestsContainer.innerHTML = '<div class="no-requests">No pending membership requests</div>';
                } else {
                    requestsContainer.innerHTML = html;
                }

            } catch (err) {
                requestsContainer.innerHTML = `<div class="no-requests">Error loading requests: ${err.message}</div>`;
                console.error(err);
            }
        }

        async function approveRequest(requestId) {
            try {
                const res = await fetch('/teams/api/approve_membership_request.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        request_id: requestId,
                        action: 'approve',
                        csrf_token: csrf
                    })
                });

                const data = await res.json();

                if (!data.success) {
                    throw new Error(data.error || 'Failed to approve request');
                }

                showMessage('Request approved successfully' + (data.status === 'approved' ? '. Member removed from team.' : '. Awaiting team lead approval.'), 'success');
                loadRequests();

            } catch (err) {
                showMessage('Error: ' + err.message);
                console.error(err);
            }
        }

        async function rejectRequest(requestId) {
            const reason = prompt('Reason for rejection:', '');
            if (reason === null) return;

            try {
                const res = await fetch('/teams/api/approve_membership_request.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        request_id: requestId,
                        action: 'reject',
                        rejection_reason: reason || null,
                        csrf_token: csrf
                    })
                });

                const data = await res.json();

                if (!data.success) {
                    throw new Error(data.error || 'Failed to reject request');
                }

                showMessage('Request rejected successfully', 'success');
                loadRequests();

            } catch (err) {
                showMessage('Error: ' + err.message);
                console.error(err);
            }
        }

        // Load requests on page load
        loadRequests();
    </script>
</body>
</html>
