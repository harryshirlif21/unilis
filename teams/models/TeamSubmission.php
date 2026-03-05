<?php
// teams/models/TeamSubmission.php

class TeamSubmission {
    private $conn; // using mysqli $conn (from your db.php)
    private $uploadDir = '/var/www/html/uploads/submissions/'; // CHANGE THIS to secure location
    private $maxFileSize = 10485760; // 10MB
    private $allowedExtensions = ['pdf', 'doc', 'docx', 'zip', 'ppt', 'pptx'];

    public function __construct($conn) {
        $this->conn = $conn;

        // Create upload directory if it doesn't exist
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    /**
     * Submit one or more files (team or individual)
     * @param int $teamId
     * @param int $studentId (current user)
     * @param int $assessmentId
     * @param array $files ($_FILES['files'])
     * @param string $type 'team' or 'individual'
     */
    public function submit($teamId, $studentId, $assessmentId, $files, $type = 'team') {
        // 1. Validate team exists
        $stmt = $this->conn->prepare("SELECT id FROM teams WHERE id = ?");
        $stmt->bind_param("i", $teamId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            throw new Exception("Team not found");
        }
        $stmt->close();

        // 2. Check if user is member of the team
        $stmt = $this->conn->prepare("
            SELECT role FROM team_members 
            WHERE team_id = ? AND student_id = ?
        ");
        $stmt->bind_param("ii", $teamId, $studentId);
        $stmt->execute();
        $result = $stmt->get_result();
        $membership = $result->fetch_assoc();
        $stmt->close();

        if (!$membership) {
            throw new Exception("You are not a member of this team");
        }

        // 3. If team submission → must be leader
        if ($type === 'team' && $membership['role'] !== 'leader') {
            throw new Exception("Only the team leader can submit team files");
        }

        // 4. Get next version number
        $version = $this->getNextVersion($teamId, $assessmentId, $type, $type === 'individual' ? $studentId : null);

        $uploadedFiles = [];

        // Handle multiple files
        foreach ($files['name'] as $key => $name) {
            if ($files['error'][$key] !== UPLOAD_ERR_OK) {
                continue;
            }

            $fileName = $files['name'][$key];
            $tmpName  = $files['tmp_name'][$key];
            $fileSize = $files['size'][$key];

            // Validate size and extension
            if ($fileSize > $this->maxFileSize) {
                throw new Exception("File $fileName is too large (max 10MB)");
            }

            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            if (!in_array($ext, $this->allowedExtensions)) {
                throw new Exception("File type not allowed: $fileName");
            }

            // Generate unique filename
            $newFileName = uniqid('sub_') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            $destination = $this->uploadDir . $newFileName;

            if (!move_uploaded_file($tmpName, $destination)) {
                throw new Exception("Failed to save file: $fileName");
            }

            // Save to database
            $stmt = $this->conn->prepare("
                INSERT INTO team_submissions 
                (team_id, student_id, assessment_id, file_name, file_path, submission_type, version, uploaded_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param(
                "iiisssi",
                $teamId,
                $type === 'individual' ? $studentId : null,
                $assessmentId,
                $fileName,
                $destination,
                $type,
                $version
            );

            if (!$stmt->execute()) {
                unlink($destination); // rollback file
                throw new Exception("Database insert failed: " . $this->conn->error);
            }

            $stmt->close();

            $uploadedFiles[] = $fileName;
        }

        if (empty($uploadedFiles)) {
            throw new Exception("No valid files were uploaded");
        }

        return [
            'success' => true,
            'message' => count($uploadedFiles) . ' file(s) uploaded successfully (version ' . $version . ')',
            'files' => $uploadedFiles
        ];
    }

    /**
     * Get next version number for team/assessment/type
     */
    private function getNextVersion($teamId, $assessmentId, $type, $studentId = null) {
        $sql = "
            SELECT MAX(version) as max_ver 
            FROM team_submissions 
            WHERE team_id = ? AND assessment_id = ? AND submission_type = ?
        ";
        $types = "iis";
        $params = [$teamId, $assessmentId, $type];

        if ($type === 'individual' && $studentId !== null) {
            $sql .= " AND student_id = ?";
            $types .= "i";
            $params[] = $studentId;
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        return ($row['max_ver'] ? $row['max_ver'] + 1 : 1);
    }

    /**
     * Download a submission file (with access check)
     * @param int $submissionId
     * @param int $userId
     * @param string $role
     */
    public function download($submissionId, $userId, $role) {
        $stmt = $this->conn->prepare("
            SELECT file_path, file_name, team_id 
            FROM team_submissions 
            WHERE id = ?
        ");
        $stmt->bind_param("i", $submissionId);
        $stmt->execute();
        $result = $stmt->get_result();
        $submission = $result->fetch_assoc();
        $stmt->close();

        if (!$submission) {
            throw new Exception("Submission not found");
        }

        $filePath = $submission['file_path'];
        $fileName = $submission['file_name'];

        if (!file_exists($filePath)) {
            throw new Exception("File not found on server");
        }

        // Access check
        if ($role !== 'lecturer') {
            // Student must be in the team
            $stmt = $this->conn->prepare("
                SELECT 1 FROM team_members 
                WHERE team_id = ? AND student_id = ?
            ");
            $stmt->bind_param("ii", $submission['team_id'], $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                throw new Exception("Access denied");
            }
            $stmt->close();
        }

        // Serve the file
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($fileName) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }
}