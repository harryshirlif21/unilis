<?php
session_start();

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if user is logged in as student
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header('Location: /login.php');
    exit;
}

// Get team_id from URL
$team_id = $_GET['team_id'] ?? null;
if (!$team_id) {
    header('Location: /teams/views/workspace.php');
    exit;
}

// Load team data and validate student membership
require_once '../../config/db.php';

$student_id = $_SESSION['user_id'];

// Verify student is a member of this team
$memberCheck = $conn->prepare("SELECT role FROM team_members WHERE team_id = ? AND student_id = ?");
$memberCheck->bind_param("ii", $team_id, $student_id);
$memberCheck->execute();
$memberResult = $memberCheck->get_result();

if ($memberResult->num_rows === 0) {
    header('Location: /teams/views/workspace.php');
    exit;
}

$memberData = $memberResult->fetch_assoc();
$student_role = $memberData['role'];
$memberCheck->close();

// Get team details
$teamCheck = $conn->prepare("SELECT title FROM teams WHERE id = ?");
$teamCheck->bind_param("i", $team_id);
$teamCheck->execute();
$teamResult = $teamCheck->get_result();
$team = $teamResult->fetch_assoc();
$teamCheck->close();

// Get available assessments
$assessments = [];
$assessmentCheck = $conn->prepare("SELECT id, title FROM assessments ORDER BY title");
$assessmentCheck->execute();
$assessmentResult = $assessmentCheck->get_result();
while ($row = $assessmentResult->fetch_assoc()) {
    $assessments[] = $row;
}
$assessmentCheck->close();

// Get previous submissions from team_files
$previousSubmissions = [];
$submissionsCheck = $conn->prepare("
    SELECT tf.*, s.name AS student_name 
    FROM team_files tf
    LEFT JOIN students s ON tf.uploader_id = s.id
    WHERE tf.team_id = ? 
    ORDER BY tf.uploaded_at DESC
");
$submissionsCheck->bind_param("i", $team_id);
$submissionsCheck->execute();
$submissionResult = $submissionsCheck->get_result();
while ($row = $submissionResult->fetch_assoc()) {
    $previousSubmissions[] = $row;
}
$submissionsCheck->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Team / Individual Files - UniLIS</title>
    <style>
        body {
            font-family: system-ui, -apple-system, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f7fa;
            color: #333;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
            padding: 2rem;
        }
        h1 {
            margin-top: 0;
            color: #1a3c5e;
        }
        .info-box {
            background: #e8f4fd;
            border-left: 4px solid #3498db;
            padding: 1rem;
            margin: 1.5rem 0;
            border-radius: 4px;
        }
        .deadline-warning {
            background: #fff3cd;
            border-left: 4px solid #f39c12;
            color: #7f5c00;
        }
        form {
            margin: 2rem 0;
        }
        label {
            display: block;
            margin: 1.2rem 0 0.5rem;
            font-weight: 600;
        }
        input[type="file"] {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ced4da;
            border-radius: 6px;
        }
        button {
            background: #28a745;
            color: white;
            border: none;
            padding: 0.9rem 2rem;
            font-size: 1.1rem;
            border-radius: 8px;
            cursor: pointer;
            margin-top: 1.5rem;
        }
        button:hover {
            background: #218838;
        }
        button:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }
        #fileList {
            margin: 1rem 0;
            min-height: 60px;
        }
        .file-item {
            background: #f8f9fa;
            padding: 0.6rem;
            margin: 0.4rem 0;
            border-radius: 6px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .file-item button {
            background: #dc3545;
            padding: 0.4rem 0.8rem;
            font-size: 0.9rem;
        }
        #progressContainer {
            margin: 1.5rem 0;
            display: none;
        }
        progress {
            width: 100%;
            height: 24px;
            border-radius: 12px;
        }
        #message {
            margin: 1.5rem 0;
            padding: 1rem;
            border-radius: 8px;
            font-weight: 500;
        }
        .success { background: #d4edda; color: #155724; }
        .error   { background: #f8d7da; color: #721c24; }
        .previous-submissions {
            margin-top: 3rem;
            border-top: 1px solid #dee2e6;
            padding-top: 1.5rem;
        }
        .submission-item {
            background: #f1f5f9;
            padding: 1rem;
            margin: 0.8rem 0;
            border-radius: 8px;
        }
        .submission-meta {
            font-size: 0.9rem;
            color: #6c757d;
            margin-top: 0.5rem;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>Submit Files</h1>

    <div id="teamInfo" class="info-box"></div>
    <div id="deadlineInfo" class="info-box deadline-warning" style="display:none;"></div>

    <form id="submissionForm" enctype="multipart/form-data">
        <label for="submissionTitle">Submission Title</label>
        <input type="text" id="submissionTitle" name="submission_title" placeholder="Enter a title for your submission" required style="width: 100%; padding: 0.8rem; border: 1px solid #ced4da; border-radius: 6px; margin-bottom: 1rem;">

        <label for="submissionDescription">Description</label>
        <textarea id="submissionDescription" name="submission_description" placeholder="Provide a brief description of your submission (optional)" rows="4" style="width: 100%; padding: 0.8rem; border: 1px solid #ced4da; border-radius: 6px; margin-bottom: 1rem; resize: vertical;"></textarea>

        <label for="files">Select Files (multiple allowed)</label>
        <input type="file" id="files" name="files[]" multiple>

        <div id="fileList"></div>

        <label>Submission Type</label>
        <select id="submissionType" name="submission_type">
            <option value="individual">Individual Submission</option>
            <option value="team">Team Submission (all members can upload)</option>
        </select>

        <input type="hidden" id="teamId" value="">
        <input type="hidden" id="assessmentId" value="">
        <input type="hidden" id="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

        <button type="submit" id="submitBtn">Upload Submission</button>
    </form>

    <div id="progressContainer">
        <progress id="progressBar" value="0" max="100"></progress>
        <div id="progressText">0%</div>
    </div>

    <div id="message"></div>

    <div class="previous-submissions">
        <h3>Previous Submissions</h3>
        <div id="previousList"></div>
    </div>
</div>

<script>
const urlParams = new URLSearchParams(window.location.search);
const teamId = urlParams.get('team_id');

if (!teamId) {
    document.body.innerHTML = '<div class="container"><h2>Error: No team selected</h2></div>';
    throw new Error('No team_id');
}

const csrf = document.getElementById('csrf').value;
const form = document.getElementById('submissionForm');
const fileInput = document.getElementById('files');
const fileListDiv = document.getElementById('fileList');
const submitBtn = document.getElementById('submitBtn');
const typeSelect = document.getElementById('submissionType');
const messageDiv = document.getElementById('message');
const progressContainer = document.getElementById('progressContainer');
const progressBar = document.getElementById('progressBar');
const progressText = document.getElementById('progressText');
const previousList = document.getElementById('previousList');

let selectedFiles = [];

// Show selected files
fileInput.addEventListener('change', () => {
    selectedFiles = Array.from(fileInput.files);
    renderFileList();
});

function renderFileList() {
    fileListDiv.innerHTML = '';
    selectedFiles.forEach((file, index) => {
        const item = document.createElement('div');
        item.className = 'file-item';
        item.innerHTML = `
            ${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)
            <button type="button" onclick="removeFile(${index})">Remove</button>
        `;
        fileListDiv.appendChild(item);
    });
}

window.removeFile = function(index) {
    selectedFiles.splice(index, 1);
    renderFileList();
    // Clear input to allow re-selection of same file if needed
    fileInput.value = '';
};

// Load team info + previous submissions
async function loadTeamAndSubmissions() {
    try {
        const res = await fetch(`/teams/api/get_team.php?team_id=${teamId}`);
        const data = await res.json();

        if (!data.success) throw new Error(data.error || 'Failed to load team');

        document.getElementById('teamId').value = teamId;
        document.getElementById('assessmentId').value = data.team.assessment_id;

        document.getElementById('teamInfo').innerHTML = `
            <strong>Team:</strong> ${data.team.title}<br>
            <strong>Assessment:</strong> ${data.team.assessment_title || '—'}<br>
            <strong>Members:</strong> ${data.members.length} (${data.members.map(m => m.name).join(', ')})
        `;

        // Check if user is leader → control team submission option
        const isLeader = data.members.some(m => m.student_id == <?php echo json_encode($_SESSION['user_id'] ?? 0); ?> && m.role === 'leader');
        if (!isLeader) {
            typeSelect.querySelector('option[value="team"]').disabled = true;
            typeSelect.value = 'individual';
        }

        // Load previous submissions
        const subRes = await fetch(`/teams/api/get_submissions.php?team_id=${teamId}`);
        const subData = await subRes.json();

        if (subData.success && subData.submissions.length > 0) {
            previousList.innerHTML = '';
            subData.submissions.forEach(sub => {
                const item = document.createElement('div');
                item.className = 'submission-item';
                
                // Format the date properly
                const uploadDate = sub.uploaded_at ? new Date(sub.uploaded_at).toLocaleString() : 'Unknown date';
                const fileName = sub.original_name || sub.filepath || 'No file';
                
                item.innerHTML = `
                    <strong>${sub.title || 'Untitled Submission'}</strong><br>
                    <div class="submission-meta">
                        <strong>Type:</strong> ${(sub.submission_type || 'individual').toUpperCase()} | 
                        <strong>Uploaded:</strong> ${uploadDate}<br>
                        ${sub.student_name ? `<strong>By:</strong> ${sub.student_name}` : '<strong>Team submission</strong>'}
                        ${sub.description ? `<br><strong>Description:</strong> ${sub.description}` : ''}
                        <br><strong>File:</strong> ${fileName}
                        ${sub.file_size ? ` (${(sub.file_size / 1024 / 1024).toFixed(2)} MB)` : ''}
                    </div>
                `;
                previousList.appendChild(item);
            });
        } else if (!subData.success) {
            // Show error in previous submissions area
            let errorText = 'Failed to load previous submissions: ' + subData.error;
            
            if (subData.debug_info) {
                errorText += '\n\nDebug Info:';
                if (subData.debug_info.error_type) {
                    errorText += '\nError Type: ' + subData.debug_info.error_type;
                }
                if (subData.debug_info.error_message) {
                    errorText += '\nDetails: ' + subData.debug_info.error_message;
                }
                if (subData.debug_info.user_id === null) {
                    errorText += '\n\nAuthentication Issue: Please log in again';
                }
            }
            
            previousList.innerHTML = `<div class="submission-item" style="background: #f8d7da; color: #721c24; white-space: pre-line;">${errorText}</div>`;
        } else {
            previousList.innerHTML = '<p>No previous submissions yet.</p>';
        }

    } catch (err) {
        messageDiv.className = 'error';
        messageDiv.textContent = 'Error loading data: ' + err.message;
    }
}

// Submit form
form.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    if (selectedFiles.length === 0) {
        messageDiv.className = 'error';
        messageDiv.textContent = 'Please select at least one file.';
        return;
    }

    const formData = new FormData();
    selectedFiles.forEach(file => {
        formData.append('files[]', file);
    });
    formData.append('team_id', teamId);
    formData.append('assessment_id', document.getElementById('assessmentId').value);
    formData.append('submission_type', typeSelect.value);
    formData.append('submission_title', document.getElementById('submissionTitle').value);
    formData.append('submission_description', document.getElementById('submissionDescription').value);
    formData.append('csrf_token', csrf);

    progressContainer.style.display = 'block';
    submitBtn.disabled = true;
    messageDiv.textContent = '';

    try {
        // Use XMLHttpRequest for progress tracking
        const xhr = new XMLHttpRequest();
        xhr.open('POST', '/teams/api/submit.php', true);
        
        // Track upload progress
        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                const percentComplete = Math.round((e.loaded / e.total) * 100);
                progressBar.value = percentComplete;
                progressText.textContent = `${percentComplete}%`;
            }
        });
        
        xhr.onload = function() {
            try {
                const result = JSON.parse(xhr.responseText);
                
                if (result.success) {
                    alert('Files submitted successfully!');
                    // Reload the page to show new submissions instead of redirecting
                    loadTeamAndSubmissions();
                    // Clear form
                    selectedFiles = [];
                    renderFileList();
                    document.getElementById('submissionTitle').value = '';
                    document.getElementById('submissionDescription').value = '';
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (parseError) {
                alert('Server response error: ' + xhr.responseText);
            }
        };
        
        xhr.onerror = function() {
            alert('Network error occurred');
        };
        
        xhr.onabort = function() {
            alert('Upload was cancelled');
        };
        
        xhr.send(formData);
        
    } catch (error) {
        alert('An error occurred: ' + error.message);
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Submit Files';
        progressContainer.style.display = 'none';
    }
});

// Initial load
loadTeamAndSubmissions();
</script>

</body>
</html>