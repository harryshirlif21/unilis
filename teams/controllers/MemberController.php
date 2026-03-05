<?php
// teams/controllers/MemberController.php
// IMPORTANT: No blank lines before <?php

class MemberController
{
    private $conn; // PDO or mysqli connection

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    /**
     * Get member details by student ID
     */
    public function getMemberById(int $studentId)
    {
        $stmt = $this->conn->prepare("SELECT student_id, name, reg_number, email FROM students WHERE student_id = ?");
        $stmt->execute([$studentId]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$member) {
            throw new Exception("Member not found");
        }

        return $member;
    }

    /**
     * Get member details by reg number or email
     */
    public function getMemberByIdentifier(string $identifier)
    {
        $stmt = $this->conn->prepare("SELECT student_id, name, reg_number, email FROM students WHERE reg_number = ? OR email = ?");
        $stmt->execute([$identifier, $identifier]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$member) {
            throw new Exception("Member not found");
        }

        return $member;
    }

    /**
     * Add member to a team
     */
    public function addMemberToTeam(int $teamId, int $studentId, string $role = 'member')
    {
        // Check if already in team
        $stmt = $this->conn->prepare("SELECT * FROM team_members WHERE team_id = ? AND student_id = ?");
        $stmt->execute([$teamId, $studentId]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            throw new Exception("Member already in team");
        }

        // Insert member
        $stmt = $this->conn->prepare("INSERT INTO team_members (team_id, student_id, role, joined_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$teamId, $studentId, $role]);

        return ['success' => true, 'message' => 'Member added successfully'];
    }

    /**
     * Remove member from team
     */
    public function removeMemberFromTeam(int $teamId, int $studentId)
    {
        $stmt = $this->conn->prepare("DELETE FROM team_members WHERE team_id = ? AND student_id = ?");
        $stmt->execute([$teamId, $studentId]);

        if ($stmt->rowCount() === 0) {
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
            SELECT tm.student_id, tm.role, s.name, s.reg_number, s.email
            FROM team_members tm
            JOIN students s ON tm.student_id = s.student_id
            WHERE tm.team_id = ?
            ORDER BY tm.role DESC, s.name ASC
        ");
        $stmt->execute([$teamId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}