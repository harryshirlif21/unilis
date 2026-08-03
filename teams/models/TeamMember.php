<?php
// teams/models/TeamMember.php

class TeamMember {
    private $conn; // mysqli connection (aligned with student side)

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function add(int $teamId, int $studentId, string $role = 'member'): void {
        // Check if team exists
        $stmt = $this->conn->prepare("SELECT * FROM teams WHERE id = ?");
        $stmt->bind_param("i", $teamId);
        $stmt->execute();
        $team = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$team) {
            throw new Exception("Team not found");
        }

        // Only leader can add members
        if (!$this->isLeader($teamId, $_SESSION['user_id'])) {
            throw new Exception("Only the team leader can add members");
        }

        // 1. Max 5 members
        if ($this->getMemberCount($teamId) >= 5) {
            throw new Exception("Team is already full (max 5 members)");
        }

        // 2. Only one leader
        if ($role === 'leader') {
            if ($this->hasLeader($teamId)) {
                throw new Exception("Team already has a leader");
            }
        }

        // 3. Student must be in same course/year and enrolled in unit
        $stmt = $this->conn->prepare("
            SELECT s.course_id, s.year 
            FROM students s
            JOIN enrollments e ON s.id = e.student_id
            WHERE s.id = ? AND e.unit_id = ?
        ");
        $stmt->bind_param("ii", $studentId, $team['unit_id']);
        $stmt->execute();
        $student = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$student || $student['course_id'] != $team['course_id'] || $student['year'] != $team['year']) {
            throw new Exception("Student is not eligible (different course/year or not enrolled)");
        }

        // 4. Student not already in any team for this assessment
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) 
            FROM team_members tm
            JOIN teams t ON tm.team_id = t.id
            WHERE tm.student_id = ? AND t.assessment_id = ?
        ");
        $stmt->bind_param("ii", $studentId, $team['assessment_id']);
        $stmt->execute();
        if ($stmt->get_result()->fetch_row()[0] > 0) {
            $stmt->close();
            throw new Exception("Student is already in another team for this assignment");
        }
        $stmt->close();

        // 5. Not already in this team
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM team_members WHERE team_id = ? AND student_id = ?");
        $stmt->bind_param("ii", $teamId, $studentId);
        $stmt->execute();
        if ($stmt->get_result()->fetch_row()[0] > 0) {
            $stmt->close();
            throw new Exception("Student is already a member of this team");
        }
        $stmt->close();

        // Insert
        $stmt = $this->conn->prepare("INSERT INTO team_members (team_id, student_id, role) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $teamId, $studentId, $role);
        $stmt->execute();
        $stmt->close();
    }

    public function remove(int $teamId, int $studentId): void {
        // Check if team exists
        $stmt = $this->conn->prepare("SELECT * FROM teams WHERE id = ?");
        $stmt->bind_param("i", $teamId);
        $stmt->execute();
        $team = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$team) {
            throw new Exception("Team not found");
        }

        if (!$this->isLeader($teamId, $_SESSION['user_id'])) {
            throw new Exception("Only the team leader can remove members");
        }

        // Leader cannot remove themselves if others are present
        if ($studentId === $team['created_by'] && $this->getMemberCount($teamId) > 1) {
            throw new Exception("Leader cannot leave while other members are present");
        }

        $stmt = $this->conn->prepare("DELETE FROM team_members WHERE team_id = ? AND student_id = ?");
        $stmt->bind_param("ii", $teamId, $studentId);
        $stmt->execute();
        $stmt->close();
    }

    public function getMembers(int $teamId): array {
        $stmt = $this->conn->prepare("
            SELECT tm.*, s.name, s.reg_no, s.email
            FROM team_members tm
            JOIN students s ON tm.student_id = s.id
            WHERE tm.team_id = ?
            ORDER BY tm.role DESC, s.name
        ");
        $stmt->bind_param("i", $teamId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result;
    }

    public function getMemberCount(int $teamId): int {
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM team_members WHERE team_id = ?");
        $stmt->bind_param("i", $teamId);
        $stmt->execute();
        $count = $stmt->get_result()->fetch_row()[0];
        $stmt->close();
        return (int) $count;
    }

    private function isLeader(int $teamId, int $studentId): bool {
        $stmt = $this->conn->prepare("
            SELECT role FROM team_members 
            WHERE team_id = ? AND student_id = ?
        ");
        $stmt->bind_param("ii", $teamId, $studentId);
        $stmt->execute();
        $role = $stmt->get_result()->fetch_row()[0] ?? '';
        $stmt->close();
        return $role === 'leader';
    }

    private function hasLeader(int $teamId): bool {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) FROM team_members 
            WHERE team_id = ? AND role = 'leader'
        ");
        $stmt->bind_param("i", $teamId);
        $stmt->execute();
        $count = $stmt->get_result()->fetch_row()[0];
        $stmt->close();
        return $count > 0;
    }
}