<?php
require_once __DIR__.'/../config/app.php';

class PracticalModel {
    private PDO $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    public function labExists(string $labId): bool {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM labs WHERE id = ?");
            $stmt->execute([$labId]);
            $result = $stmt->fetch();
            return $result['count'] > 0;
        } catch (Exception $e) {
            error_log("PracticalModel::labExists Error: " . $e->getMessage());
            return false;
        }
    }
    
    public function create(array $data): bool {
        try {
            error_log("PracticalModel::create - Attempting to create practical with data: " . json_encode($data, JSON_UNESCAPED_SLASHES));
            
            // Validate required fields
            $required = ['id', 'title', 'lab_id', 'lecturer_id', 'scheduled_date', 'max_students'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    error_log("PracticalModel::create - Missing required field: $field");
                    return false;
                }
            }
            
            $stmt = $this->db->prepare(
                "INSERT INTO practicals 
                 (id, title, objective, theory, description, lab_id, lecturer_id, scheduled_date, 
                  duration_hours, max_students, status, course_code, 
                  start_time, end_time, required_equipment, required_chemicals, 
                  procedure_json, observations_table_structure, safety_notes, 
                  results_template, calculations_template)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            
            $result = $stmt->execute([
                $data['id'],
                $data['title'],
                $data['objective'] ?? null,
                $data['theory'] ?? null,
                $data['description'] ?? null,
                $data['lab_id'],
                $data['lecturer_id'],
                $data['scheduled_date'],
                $data['duration_hours'] ?? 2,
                $data['max_students'],
                $data['status'] ?? 'draft',
                $data['course_code'] ?? null,
                $data['start_time'] ?? null,
                $data['end_time'] ?? null,
                $data['required_equipment'] ?? null,
                $data['required_chemicals'] ?? null,
                $data['procedure_json'] ?? null,
                $data['observations_table_structure'] ?? null,
                $data['safety_notes'] ?? null,
                $data['results_template'] ?? null,
                $data['calculations_template'] ?? null
            ]);
            
            if ($result) {
                error_log("PracticalModel::create - Successfully created practical with ID: {$data['id']}");
            } else {
                error_log("PracticalModel::create - Failed to execute statement");
                error_log("PracticalModel::create - Statement error info: " . json_encode($stmt->errorInfo()));
            }
            
            return $result;
        } catch (PDOException $e) {
            error_log("PracticalModel::create PDO Error: " . $e->getMessage());
            error_log("PracticalModel::create PDO Error Code: " . $e->getCode());
            error_log("PracticalModel::create PDO Error Info: " . json_encode($e->errorInfo ?? []));
            return false;
        } catch (Exception $e) {
            error_log("PracticalModel::create Error: " . $e->getMessage());
            error_log("PracticalModel::create Error Trace: " . $e->getTraceAsString());
            return false;
        }
    }
    
    public function getAll(?string $lecturerId = null): array {
        try {
            $sql = "SELECT p.*, l.name as lab_name, l.lab_code, 
                           u.full_name as lecturer_name, u.email as lecturer_email,
                           COUNT(ls.id) as session_count
                    FROM practicals p
                    LEFT JOIN labs l ON p.lab_id = l.id
                    LEFT JOIN users u ON p.lecturer_id = u.id
                    LEFT JOIN lab_sessions ls ON p.id = ls.practical_id
                    GROUP BY p.id
                    ORDER BY p.scheduled_date DESC, p.created_at DESC";
            
            if ($lecturerId) {
                $sql = "SELECT p.*, l.name as lab_name, l.lab_code, 
                               u.full_name as lecturer_name, u.email as lecturer_email,
                               COUNT(ls.id) as session_count
                        FROM practicals p
                        LEFT JOIN labs l ON p.lab_id = l.id
                        LEFT JOIN users u ON p.lecturer_id = u.id
                        LEFT JOIN lab_sessions ls ON p.id = ls.practical_id
                        WHERE p.lecturer_id = ?
                        GROUP BY p.id
                        ORDER BY p.scheduled_date DESC, p.created_at DESC";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$lecturerId]);
            } else {
                $stmt = $this->db->query($sql);
            }
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("PracticalModel::getAll Error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getById(string $practicalId): ?array {
        $stmt = $this->db->prepare(
            "SELECT p.*, l.name as lab_name, l.lab_code, l.max_capacity as lab_capacity,
                   u.full_name as lecturer_name, u.email as lecturer_email
             FROM practicals p
             LEFT JOIN labs l ON p.lab_id = l.id
             LEFT JOIN users u ON p.lecturer_id = u.id
             WHERE p.id = ? LIMIT 1"
        );
        $stmt->execute([$practicalId]);
        return $stmt->fetch() ?: null;
    }
    
    public function updateStatus(string $practicalId, string $status): bool {
        try {
            $stmt = $this->db->prepare(
                "UPDATE practicals SET status = ? WHERE id = ?"
            );
            return $stmt->execute([$status, $practicalId]);
        } catch (Exception $e) {
            error_log("PracticalModel::updateStatus Error: " . $e->getMessage());
            return false;
        }
    }
    
    public function update(string $practicalId, array $data): bool {
        try {
            $stmt = $this->db->prepare(
                "UPDATE practicals 
                 SET title = ?, objective = ?, theory = ?, description = ?, lab_id = ?, 
                     scheduled_date = ?, duration_hours = ?, 
                     max_students = ?, status = ?,
                     course_code = ?, start_time = ?, end_time = ?,
                     required_equipment = ?, required_chemicals = ?, 
                     procedure_json = ?, observations_table_structure = ?, safety_notes = ?,
                     results_template = ?, calculations_template = ?
                 WHERE id = ?"
            );
            
            return $stmt->execute([
                $data['title'],
                $data['objective'] ?? null,
                $data['theory'] ?? null,
                $data['description'] ?? null,
                $data['lab_id'],
                $data['scheduled_date'],
                $data['duration_hours'] ?? 2,
                $data['max_students'],
                $data['status'] ?? 'draft',
                $data['course_code'] ?? null,
                $data['start_time'] ?? null,
                $data['end_time'] ?? null,
                $data['required_equipment'] ?? null,
                $data['required_chemicals'] ?? null,
                $data['procedure_json'] ?? null,
                $data['observations_table_structure'] ?? null,
                $data['safety_notes'] ?? null,
                $data['results_template'] ?? null,
                $data['calculations_template'] ?? null,
                $practicalId
            ]);
        } catch (Exception $e) {
            error_log("PracticalModel::update Error: " . $e->getMessage());
            return false;
        }
    }
    
    public function delete(string $practicalId): bool {
        $this->db->beginTransaction();
        
        try {
            // Delete related sessions first
            $sessionStmt = $this->db->prepare(
                "DELETE FROM lab_sessions WHERE practical_id = ?"
            );
            $sessionStmt->execute([$practicalId]);
            
            // Delete practical
            $practicalStmt = $this->db->prepare(
                "DELETE FROM practicals WHERE id = ?"
            );
            $practicalStmt->execute([$practicalId]);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            return false;
        }
    }
    
    public function getLabs(): array {
        $stmt = $this->db->query(
            "SELECT id, name, lab_code, type, max_capacity, current_count 
             FROM labs WHERE is_active = 1 
             ORDER BY name"
        );
        return $stmt->fetchAll();
    }
    
    public function getLecturers(): array {
        $stmt = $this->db->prepare(
            "SELECT id, full_name, email, department 
             FROM users 
             WHERE role IN ('lecturer', 'admin') AND is_active = 1 
             ORDER BY full_name"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function validateDateTime(string $date, ?string $startTime = null, ?string $endTime = null): array {
        $errors = [];
        
        // Validate date format and check if past
        $selectedDate = DateTime::createFromFormat('Y-m-d', $date);
        if (!$selectedDate) {
            $errors[] = 'Invalid date format. Please use YYYY-MM-DD format.';
        } else {
            $today = new DateTime();
            $today->setTime(0, 0, 0);
            if ($selectedDate < $today) {
                $errors[] = 'Cannot schedule practicals in the past. Please select a future date.';
            }
        }
        
        // Validate time format and order
        if ($startTime && $endTime) {
            if (strlen($startTime) !== 5 || strlen($endTime) !== 5) {
                $errors[] = 'Invalid time format. Please use HH:MM format.';
            } elseif ($startTime >= $endTime) {
                $errors[] = 'End time must be after start time.';
            }
        }
        
        return $errors;
    }
    
    public function getDailyPracticalCount(string $labId, string $date, ?string $excludePractical = null): int {
        try {
            $sql = "SELECT COUNT(*) as total 
                    FROM practicals 
                    WHERE lab_id = ? AND scheduled_date = ?";
            
            $params = [$labId, $date];
            
            if ($excludePractical) {
                $sql .= " AND id != ?";
                $params[] = $excludePractical;
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch();
            
            return (int) $result['total'];
        } catch (Exception $e) {
            error_log("PracticalModel::getDailyPracticalCount Error: " . $e->getMessage());
            return 0;
        }
    }
    
    public function checkLabAvailability(string $labId, string $date, ?string $startTime = null, ?string $endTime = null, ?string $excludePractical = null): bool {
        try {
            error_log("checkLabAvailability - Lab: $labId, Date: $date, Start: $startTime, End: $endTime, Exclude: $excludePractical");
            
            // Step 1: Check daily limit (max 3 practicals per lab per day)
            $totalCount = $this->getDailyPracticalCount($labId, $date, $excludePractical);
            error_log("Daily practical count: $totalCount");
            
            if ($totalCount >= 3) {
                error_log("Lab fully booked - daily limit reached (3 practicals)");
                return false;
            }
            
            // Step 2: Check time overlap (only if times are provided)
            if ($startTime && $endTime) {
                // Normalize times to HH:MM:SS format for consistent comparison with TIME columns
                $normalizedStart = strlen($startTime) === 5 ? $startTime . ':00' : $startTime;
                $normalizedEnd = strlen($endTime) === 5 ? $endTime . ':00' : $endTime;
                
                $sql = "SELECT COUNT(*) as conflicts 
                        FROM practicals p 
                        WHERE p.lab_id = ? AND p.scheduled_date = ? 
                        AND p.status IN ('published', 'ongoing')
                        AND (p.start_time < ? AND p.end_time > ?)";
                
                $params = [$labId, $date, $normalizedEnd, $normalizedStart];
                
                if ($excludePractical) {
                    $sql .= " AND p.id != ?";
                    $params[] = $excludePractical;
                }
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
                $result = $stmt->fetch();
                
                $conflictCount = $result['conflicts'];
                error_log("Time overlap conflicts: $conflictCount");
                
                if ($conflictCount > 0) {
                    error_log("Time slot conflict detected");
                    return false;
                }
            }
            
            error_log("Lab is available");
            return true;
        } catch (Exception $e) {
            error_log("PracticalModel::checkLabAvailability Error: " . $e->getMessage());
            // Return true on DB error to avoid blocking all submissions
            return true;
        }
    }
    
    public function getAvailableSlots(string $labId, string $date): array {
        try {
            // Get all existing practicals for this lab on this date
            $sql = "SELECT start_time, end_time 
                    FROM practicals 
                    WHERE lab_id = ? AND scheduled_date = ? 
                    AND status IN ('published', 'ongoing')
                    ORDER BY start_time";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$labId, $date]);
            $existing = $stmt->fetchAll();
            
            // Normalize existing practical times to HH:MM:SS for consistent string comparison
            $existing = array_map(function($row) {
                $row['start_time'] = strlen($row['start_time']) === 5
                    ? $row['start_time'] . ':00'
                    : $row['start_time'];
                $row['end_time'] = strlen($row['end_time']) === 5
                    ? $row['end_time'] . ':00'
                    : $row['end_time'];
                return $row;
            }, $existing);
            
            // Define standard time slots (e.g., 8 AM to 6 PM in 1-hour intervals)
            $slots = [];
            $startHour = 8;
            $endHour = 18;
            
            for ($hour = $startHour; $hour < $endHour; $hour++) {
                $slotStart = sprintf('%02d:00:00', $hour);
                $slotEnd = sprintf('%02d:00:00', $hour + 1);
                
                // Check if this slot conflicts with any existing practical
                $isAvailable = true;
                foreach ($existing as $practical) {
                    $pStart = $practical['start_time'];
                    $pEnd = $practical['end_time'];
                    
                    // Check overlap: slot conflicts if slot_start < p_end AND slot_end > p_start
                    if ($slotStart < $pEnd && $slotEnd > $pStart) {
                        $isAvailable = false;
                        break;
                    }
                }
                
                $slots[] = [
                    'start' => $slotStart,
                    'end' => $slotEnd,
                    'available' => $isAvailable
                ];
            }
            
            return $slots;
        } catch (Exception $e) {
            error_log("PracticalModel::getAvailableSlots Error: " . $e->getMessage());
            // Return all slots as available on DB error so user sees options
            $slots = [];
            for ($hour = 8; $hour < 18; $hour++) {
                $slots[] = [
                    'start' => sprintf('%02d:00:00', $hour),
                    'end'   => sprintf('%02d:00:00', $hour + 1),
                    'available' => true
                ];
            }
            return $slots;
        }
    }
    
    public function getSchedule(string $labId, string $date): array {
        try {
            $stmt = $this->db->prepare(
                "SELECT p.*, u.full_name as lecturer_name
                 FROM practicals p
                 LEFT JOIN users u ON p.lecturer_id = u.id
                 WHERE p.lab_id = ? AND p.scheduled_date = ? 
                 AND p.status IN ('published', 'completed')
                 ORDER BY p.created_at"
            );
            $stmt->execute([$labId, $date]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("PracticalModel::getSchedule Error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getUpcomingPracticals(?string $studentId = null): array {
        try {
            $sql = "SELECT p.*, l.name as lab_name, l.lab_code,
                           u.full_name as lecturer_name
                    FROM practicals p
                    LEFT JOIN labs l ON p.lab_id = l.id
                    LEFT JOIN users u ON p.lecturer_id = u.id
                    WHERE p.scheduled_date >= CURDATE() 
                    AND p.status = 'published'
                    ORDER BY p.scheduled_date ASC, p.created_at ASC";
            
            if ($studentId) {
                // Filter by student's lab access
                $sql = "SELECT p.*, l.name as lab_name, l.lab_code,
                               u.full_name as lecturer_name
                        FROM practicals p
                        LEFT JOIN labs l ON p.lab_id = l.id
                        LEFT JOIN users u ON p.lecturer_id = u.id
                        LEFT JOIN users s ON s.lab_id = l.id
                        WHERE p.scheduled_date >= CURDATE() 
                        AND p.status = 'published'
                        AND s.id = ?
                        ORDER BY p.scheduled_date ASC, p.created_at ASC";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$studentId]);
            } else {
                $stmt = $this->db->query($sql);
            }
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("PracticalModel::getUpcomingPracticals Error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getPracticalStats(): array {
        try {
            $stmt = $this->db->query(
                "SELECT 
                    COUNT(*) as total_practicals,
                    COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft,
                    COUNT(CASE WHEN status = 'published' THEN 1 END) as published,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
                    COUNT(CASE WHEN scheduled_date >= CURDATE() THEN 1 END) as upcoming
                 FROM practicals"
            );
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("PracticalModel::getPracticalStats Error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getLabUtilization(string $labId, string $startDate, string $endDate): array {
        try {
            $stmt = $this->db->prepare(
                "SELECT p.scheduled_date, 
                       SUM(p.duration_hours * 60) as total_minutes
                 FROM practicals p
                 WHERE p.lab_id = ? 
                 AND p.scheduled_date BETWEEN ? AND ?
                 AND p.status IN ('published', 'completed')
                 GROUP BY p.scheduled_date
                 ORDER BY p.scheduled_date"
            );
            $stmt->execute([$labId, $startDate, $endDate]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("PracticalModel::getLabUtilization Error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getEnrolledStudents(string $practicalId): array {
        $stmt = $this->db->prepare(
            "SELECT u.id, u.full_name, u.email, u.reg_number
             FROM users u
             JOIN student_practicals sp ON u.id = sp.student_id
             WHERE sp.practical_id = ? AND u.is_active = 1
             ORDER BY u.full_name"
        );
        $stmt->execute([$practicalId]);
        return $stmt->fetchAll();
    }
    
    public function getLabSessions(string $practicalId): array {
        $stmt = $this->db->prepare(
            "SELECT ls.*, 
                   COUNT(lss.student_id) as enrolled_count,
                   ls.status as session_status
             FROM lab_sessions ls
             LEFT JOIN lab_session_students lss ON ls.id = lss.session_id
             WHERE ls.practical_id = ?
             GROUP BY ls.id
             ORDER BY ls.started_at DESC"
        );
        $stmt->execute([$practicalId]);
        return $stmt->fetchAll();
    }
    
    public function isStudentEnrolled(string $studentId, string $practicalId): bool {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as enrolled 
             FROM student_practicals 
             WHERE student_id = ? AND practical_id = ?"
        );
        $stmt->execute([$studentId, $practicalId]);
        $result = $stmt->fetch();
        return $result['enrolled'] > 0;
    }
    
    public function getAvailablePracticals(): array {
        $stmt = $this->db->query(
            "SELECT p.*, l.name as lab_name, l.lab_code,
                   u.full_name as lecturer_name
             FROM practicals p
             JOIN labs l ON p.lab_id = l.id
             JOIN users u ON p.lecturer_id = u.id
             WHERE p.status IN ('completed', 'published')
             ORDER BY p.title"
        );
        return $stmt->fetchAll();
    }
    
    public function getStudentCompletedPracticals(string $studentId): array {
        $stmt = $this->db->prepare(
            "SELECT p.*, l.name as lab_name, l.lab_code,
                   ls.started_at as session_date, ls.status as session_status
             FROM practicals p
             JOIN lab_sessions ls ON p.id = ls.practical_id
             JOIN labs l ON p.lab_id = l.id
             JOIN student_practicals sp ON p.id = sp.practical_id
             WHERE sp.student_id = ? AND ls.status = 'closed'
             ORDER BY ls.started_at DESC"
        );
        $stmt->execute([$studentId]);
        return $stmt->fetchAll();
    }
    
    public function createDeadlineForCompletedPractical(string $practicalId, string $studentId): void {
        // Check if deadline already exists
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as count FROM report_deadlines 
             WHERE practical_id = ? AND student_id = ?"
        );
        $stmt->execute([$practicalId, $studentId]);
        $count = $stmt->fetch()['count'];
        
        if ($count > 0) {
            return; // Deadline already exists
        }
        
        // Get practical date
        $stmt = $this->db->prepare(
            "SELECT ls.started_at FROM lab_sessions ls 
             WHERE ls.practical_id = ? AND ls.status = 'closed'
             ORDER BY ls.started_at DESC LIMIT 1"
        );
        $stmt->execute([$practicalId]);
        $session = $stmt->fetch();
        
        if ($session) {
            $deadlineModel = new DeadlineModel();
            $deadlineModel->createDeadlineForPractical($practicalId, $studentId);
        }
    }

    public function attendanceExists(string $studentId, string $practicalId): bool {
        try {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) as count FROM attendance 
                 WHERE student_id = ? AND practical_id = ?"
            );
            $stmt->execute([$studentId, $practicalId]);
            $result = $stmt->fetch();
            return $result['count'] > 0;
        } catch (Exception $e) {
            error_log("PracticalModel::attendanceExists Error: " . $e->getMessage());
            return false;
        }
    }

    public function getReport(string $studentId, string $practicalId): ?array {
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM lab_reports 
                 WHERE practical_id = ? AND student_id = ?"
            );
            $stmt->execute([$practicalId, $studentId]);
            $result = $stmt->fetch();
            return $result ?: null;
        } catch (Exception $e) {
            error_log("PracticalModel::getReport Error: " . $e->getMessage());
            return null;
        }
    }

    public function createReport(array $data): ?string {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO lab_reports
                 (id, practical_id, student_id, status, created_at) 
                 VALUES (?, ?, ?, ?, ?)"
            );
            $result = $stmt->execute([
                bin2hex(random_bytes(16)),
                $data['practical_id'],
                $data['student_id'],
                $data['status'],
                $data['started_at']
            ]);
            if ($result) {
                return $this->db->lastInsertId();
            }
            return null;
        } catch (Exception $e) {
            error_log("PracticalModel::createReport Error: " . $e->getMessage());
            return null;
        }
    }

    public function getPracticalDetails(string $practicalId): ?array {
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM practicals WHERE id = ?"
            );
            $stmt->execute([$practicalId]);
            $result = $stmt->fetch();
            if ($result) {
                // Parse JSON fields
                $result['materials'] = json_decode($result['required_equipment'] ?? '[]', true) ?: [];
                $result['procedure'] = json_decode($result['procedure_json'] ?? '[]', true) ?: [];
                $result['data_table'] = json_decode($result['observations_table_structure'] ?? '[]', true) ?: [];
                $result['questions'] = []; // Assuming questions are not stored yet
                $result['aim'] = $result['objective'];
            }
            return $result ?: null;
        } catch (Exception $e) {
            error_log("PracticalModel::getPracticalDetails Error: " . $e->getMessage());
            return null;
        }
    }

    public function getReportById(string $reportId): ?array {
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM lab_reports WHERE id = ?"
            );
            $stmt->execute([$reportId]);
            $result = $stmt->fetch();
            return $result ?: null;
        } catch (Exception $e) {
            error_log("PracticalModel::getReportById Error: " . $e->getMessage());
            return null;
        }
    }

    public function markAttendance(string $studentId, string $practicalId, string $verificationMethod): bool {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO attendance 
                 (student_id, practical_id, verification_method, marked_at) 
                 VALUES (?, ?, ?, NOW())"
            );
            $result = $stmt->execute([$studentId, $practicalId, $verificationMethod]);
            return $result;
        } catch (Exception $e) {
            error_log("PracticalModel::markAttendance Error: " . $e->getMessage());
            return false;
        }
    }
}

