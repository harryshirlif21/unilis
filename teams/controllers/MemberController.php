<?php
// teams/controllers/MemberController.php
// IMPORTANT: No blank lines before <?php

class MemberController
{
    private $conn; // mysqli connection (aligned with student side)

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    /**
     * Get member details by student ID
     */
    public function getMemberById(int $studentId)
    {
        $stmt = $this->conn->prepare("SELECT id, name, reg_no, email FROM students WHERE id = ?");
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$result) {
            throw new Exception("Member not found");
        }

        return $result;
    }

    /**
     * Get member details by reg number or email
     */
    public function getMemberByIdentifier(string $identifier)
    {
        $stmt = $this->conn->prepare("SELECT id, name, reg_no, email FROM students WHERE reg_no = ? OR email = ?");
        $stmt->bind_param("ss", $identifier, $identifier);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$result) {
            throw new Exception("Member not found");
        }

        return $result;
    }

    /**
     * Add member to a team
     */
    public function addMemberToTeam(int $teamId, int $studentId, string $role = 'member')
    {
        // Check if already in team
        $stmt = $this->conn->prepare("SELECT * FROM team_members WHERE team_id = ? AND student_id = ?");
        $stmt->bind_param("ii", $teamId, $studentId);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            $stmt->close();
            throw new Exception("Member already in team");
        }
        $stmt->close();

        // Enforce: Student can only be in one team per unit
        $stmt = $this->conn->prepare(
            "SELECT 1
             FROM team_members tm
             JOIN teams t ON tm.team_id = t.id
             WHERE tm.student_id = ?
               AND t.unit_id = (SELECT unit_id FROM teams WHERE id = ?)
               AND tm.team_id != ?
             LIMIT 1"
        );
        $stmt->bind_param("iii", $studentId, $teamId, $teamId);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            $stmt->close();
            throw new Exception("This student is already a member of another team in this unit. Each student can only be a member of one team per unit.");
        }
        $stmt->close();

        // Insert member
        $stmt = $this->conn->prepare("INSERT INTO team_members (team_id, student_id, role, joined_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iis", $teamId, $studentId, $role);
        $stmt->execute();
        $stmt->close();

        return ['success' => true, 'message' => 'Member added successfully'];
    }

    /**
     * Remove member from team
     */
    public function removeMemberFromTeam(int $teamId, int $studentId)
    {
        $stmt = $this->conn->prepare("DELETE FROM team_members WHERE team_id = ? AND student_id = ?");
        $stmt->bind_param("ii", $teamId, $studentId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected === 0) {
            throw new Exception("Member not found in team");
        }

        return ['success' => true, 'message' => 'Member removed successfully'];
    }

    /**
     * Get all members of a team
     */
    public function getTeamMembers(int $teamId)
    {
        $stmt = $this->conn->prepare("
            SELECT tm.student_id, tm.role, s.name, s.reg_no, s.email
            FROM team_members tm
            JOIN students s ON tm.student_id = s.id
            WHERE tm.team_id = ?
            ORDER BY tm.role DESC, s.name ASC
        ");
        $stmt->bind_param("i", $teamId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result;
    }
}