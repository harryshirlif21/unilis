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
        <label for="files">Select Files (multiple allowed)</label>
        <input type="file" id="files" name="files[]" multiple accept=".pdf,.doc,.docx,.ppt,.pptx,.zip">

        <div id="fileList"></div>

        <label>Submission Type</label>
        <select id="submissionType" name="submission_type">
            <option value="team">Team Submission (only leader can submit)</option>
            <option value="individual">Individual Submission</option>
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
                item.innerHTML = `
                    <strong>${sub.submission_type.toUpperCase()} submission – Version ${sub.version}</strong><br>
                    <div class="submission-meta">
                        Uploaded: ${new Date(sub.uploaded_at).toLocaleString()}<br>
                        ${sub.student_name ? `By: ${sub.student_name}` : 'Team submission'}<br>
                        Files: ${sub.files.map(f => f.file_name).join(', ')}
                    </div>
                `;
                previousList.appendChild(item);
            });
        } else {
            previousList.innerHTML = '<p>No previous submissions yet.</p>';
        }

        // Deadline check
        if (data.team.deadline_passed) {
            document.getElementById('deadlineInfo').textContent = 'Deadline has passed – submissions are now late or blocked.';
            document.getElementById('deadlineInfo').style.display = 'block';
            submitBtn.disabled = true;
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
    formData.append('csrf_token', csrf);

    progressContainer.style.display = 'block';
    submitBtn.disabled = true;
    messageDiv.textContent = '';

    try {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', '/teams/api/submit.php');

        xhr.upload.onprogress = (e) => {
            if (e.lengthComputable) {
                const percent = Math.round((e.loaded / e.total) * 100);
                progressBar.value = percent;
                progressText.textContent = `${percent}%`;
            }
        };

        xhr.onload = () => {
            progressContainer.style.display = 'none';
            submitBtn.disabled = false;

            if (xhr.status === 200) {
                const response = JSON.parse(xhr.responseText);
                messageDiv.className = response.success ? 'success' : 'error';
                messageDiv.textContent = response.message || (response.success ? 'Files uploaded successfully!' : response.error);

                if (response.success) {
                    selectedFiles = [];
                    renderFileList();
                    fileInput.value = '';
                    loadTeamAndSubmissions(); // refresh previous list
                }
            } else {
                messageDiv.className = 'error';
                messageDiv.textContent = 'Server error: ' + xhr.statusText;
            }
        };

        xhr.onerror = () => {
            progressContainer.style.display = 'none';
            submitBtn.disabled = false;
            messageDiv.className = 'error';
            messageDiv.textContent = 'Network error. Please check your connection.';
        };

        xhr.send(formData);

    } catch (err) {
        progressContainer.style.display = 'none';
        submitBtn.disabled = false;
        messageDiv.className = 'error';
        messageDiv.textContent = 'Unexpected error: ' + err.message;
    }
});

// Initial load
loadTeamAndSubmissions();
</script>

</body>
</html>