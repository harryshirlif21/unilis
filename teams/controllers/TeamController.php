<?php
// teams/controllers/TeamController.php

class TeamController {
    private $conn; // mysqli connection

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Get team details including members.
     * @param int $teamId
     * @return array|null
     */
    public function getTeam(int $teamId) {
        // 1. Fetch team info
        $stmt = $this->conn->prepare("
            SELECT t.id, t.title, t.unit_name, t.assessment_id, t.creator_id,
                   t.status, t.created_at, a.title AS assessment_title,
                   (t.deadline < NOW()) AS deadline_passed
            FROM teams t
            LEFT JOIN assessments a ON t.assessment_id = a.id
            WHERE t.id = ?
            LIMIT 1
        ");
        $stmt->bind_param('i', $teamId);
        $stmt->execute();
        $teamResult = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$teamResult) return null;

        // 2. Fetch members
        $stmt = $this->conn->prepare("
            SELECT tm.student_id, s.name, s.reg_number, s.email
            FROM team_members tm
            INNER JOIN students s ON tm.student_id = s.id
            WHERE tm.team_id = ?
        ");
        $stmt->bind_param('i', $teamId);
        $stmt->execute();
        $membersResult = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Add default role for members (will be adjusted in API)
        $members = array_map(function($m) {
            $m['role'] = 'member';
            return $m;
        }, $membersResult);

        // Return team info + members
        return [
            'id' => (int)$teamResult['id'],
            'title' => $teamResult['title'],
            'unit_name' => $teamResult['unit_name'],
            'assessment_id' => $teamResult['assessment_id'],
            'assessment_title' => $teamResult['assessment_title'],
            'status' => $teamResult['status'],
            'created_at' => $teamResult['created_at'],
            'deadline_passed' => (bool)$teamResult['deadline_passed'],
            'creator_id' => (int)$teamResult['creator_id'],
            'members' => $members
        ];
    }
}