<?php
// teams/models/Team.php

class Team {
    private $conn; // mysqli connection (aligned with student side)

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Create a new team - enforces: enrolled in unit, assessment allows teams, etc.
     */
    public function create(string $title, int $unitId, int $assessmentId): int {
        $userId = $_SESSION['user_id'] ?? 0;
        if ($_SESSION['user_role'] !== 'student' || $userId <= 0) {
            throw new Exception("Only enrolled students can create teams");
        }

        // 1. Check student is enrolled in the unit
        $stmt = $this->conn->prepare("
            SELECT e.student_id, s.course_id, s.year
            FROM enrollments e
            JOIN students s ON e.student_id = s.id
            WHERE e.unit_id = ? AND e.student_id = ?
        ");
        $stmt->bind_param("ii", $unitId, $userId);
        $stmt->execute();
        $enrollment = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$enrollment) {
            throw new Exception("You are not enrolled in this unit");
        }

        // 2. Check assignment allows teams
        $stmt = $this->conn->prepare("SELECT assignment_mode FROM team_assignments WHERE id = ?");
        $stmt->bind_param("i", $assessmentId);
        $stmt->execute();
        $mode = $stmt->get_result()->fetch_row()[0] ?? '';
        $stmt->close();

        if ($mode === 'individual_only') {
            throw new Exception("This assignment does not allow team submissions");
        }

        // 3. Create team
        $stmt = $this->conn->prepare("
            INSERT INTO teams 
            (title, unit_id, assessment_id, created_by, course_id, year, status)
            VALUES (?, ?, ?, ?, ?, ?, 'active')
        ");
        $stmt->bind_param("siiiiis", $title, $unitId, $assessmentId, $userId, $enrollment['course_id'], $enrollment['year']);
        $stmt->execute();
        $teamId = (int) $this->conn->insert_id;
        $stmt->close();

        // 4. Add creator as leader
        $memberModel = new TeamMember($this->conn);
        $memberModel->add($teamId, $userId, 'leader');

        return $teamId;
    }

    /**
     * Find team by ID
     */
    public function findById(int $teamId): ?array {
        $stmt = $this->conn->prepare("SELECT * FROM teams WHERE id = ?");
        $stmt->bind_param("i", $teamId);
        $stmt->execute();
        $team = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $team ?: null;
    }

    /**
     * Get teams by student ID
     */
    public function getByStudentId(int $studentId): array {
        $stmt = $this->conn->prepare("
            SELECT t.* 
            FROM teams t
            JOIN team_members tm ON t.id = tm.team_id
            WHERE tm.student_id = ?
            ORDER BY t.created_at DESC
        ");
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result;
    }

    /**
     * Get teams by unit ID
     */
    public function getByUnitId(int $unitId): array {
        $stmt = $this->conn->prepare("
            SELECT t.* 
            FROM teams t
            WHERE t.unit_id = ?
            ORDER BY t.created_at DESC
        ");
        $stmt->bind_param("i", $unitId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result;
    }

    /**
     * Update team status
     */
    public function updateStatus(int $teamId, string $status): void {
        $stmt = $this->conn->prepare("UPDATE teams SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $teamId);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Delete team
     */
    public function delete(int $teamId): void {
        $stmt = $this->conn->prepare("DELETE FROM teams WHERE id = ?");
        $stmt->bind_param("i", $teamId);
        $stmt->execute();
        $stmt->close();
    }
}