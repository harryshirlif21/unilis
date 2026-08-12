<?php
/**
 * Shared helpers for displaying team unit and activity info.
 */

function team_assessment_label(?string $type): string
{
    $type = strtolower(trim((string) $type));
    $labels = [
        'assignment' => 'Assignment',
        'cat'        => 'CAT',
        'project'    => 'Project',
        'practical'  => 'Practical',
    ];

    return $labels[$type] ?? ($type !== '' ? ucfirst($type) : 'General');
}

function team_format_unit_display(?string $code, ?string $name): string
{
    $code = trim((string) $code);
    $name = trim((string) $name);

    if ($code !== '' && $name !== '') {
        return $code . ' – ' . $name;
    }

    return $name !== '' ? $name : ($code !== '' ? $code : '—');
}

function team_activity_type_label(?string $type): string
{
    $type = strtolower(trim((string) $type));
    $labels = [
        'file_upload'      => 'File uploaded',
        'task_update'      => 'Task updated',
        'task_created'     => 'Task created',
        'submission'         => 'Submission',
        'member_joined'      => 'Member joined',
        'member_left'        => 'Member left',
        'mark_awarded'       => 'Mark awarded',
        'supervisor_added'   => 'Supervisor added',
        'team_created'       => 'Team created',
        'standup'            => 'Standup posted',
    ];

    return $labels[$type] ?? ($type !== '' ? ucfirst(str_replace('_', ' ', $type)) : 'Activity');
}

function team_fetch_latest_activity(mysqli $conn, int $teamId): ?array
{
    if ($teamId <= 0) {
        return null;
    }

    $stmt = $conn->prepare("
        SELECT
            tal.action_type,
            tal.action_detail,
            tal.created_at,
            COALESCE(s.name, l.name, a.name, t.name) AS user_name
        FROM team_activity_log tal
        LEFT JOIN students s ON s.id = tal.user_id
        LEFT JOIN lecturers l ON l.id = tal.user_id
        LEFT JOIN admins a ON a.id = tal.user_id
        LEFT JOIN technicians t ON t.id = tal.user_id
        WHERE tal.team_id = ?
        ORDER BY tal.created_at DESC
        LIMIT 1
    ");

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $teamId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return null;
    }

    $detail = trim((string) ($row['action_detail'] ?? ''));
    if ($detail !== '') {
        $decoded = json_decode($detail, true);
        if (is_array($decoded)) {
            $detail = trim((string) ($decoded['message'] ?? $decoded['title'] ?? $decoded['filename'] ?? $decoded['original_name'] ?? ''));
        }
        if (strlen($detail) > 120) {
            $detail = substr($detail, 0, 117) . '...';
        }
    }

    return [
        'action_type'   => (string) ($row['action_type'] ?? ''),
        'action_label'  => team_activity_type_label($row['action_type'] ?? ''),
        'action_detail' => $detail,
        'created_at'    => (string) ($row['created_at'] ?? ''),
        'user_name'     => trim((string) ($row['user_name'] ?? '')),
    ];
}

function team_role_label(string $role): string
{
    $role = strtolower(trim($role));
    $labels = [
        'leader'             => 'Team Lead',
        'member'             => 'Member',
        'frontend_developer' => 'Frontend Developer',
        'backend_developer'  => 'Backend Developer',
        'machine_learning'   => 'Machine Learning',
        'ui_ux_designer'     => 'UI/UX Designer',
        'data_analyst'       => 'Data Analyst',
        'tester'             => 'Tester',
        'researcher'         => 'Researcher',
        'presenter'          => 'Presenter',
        'other'              => 'Other',
    ];

    return $labels[$role] ?? ucfirst(str_replace('_', ' ', $role));
}

/**
 * Fetch all members for the given team IDs, grouped by team_id.
 *
 * @return array<int, list<array<string, mixed>>>
 */
function team_fetch_members_grouped(mysqli $conn, array $teamIds): array
{
    $teamIds = array_values(array_filter(array_map('intval', $teamIds), static fn(int $id): bool => $id > 0));
    if ($teamIds === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($teamIds), '?'));
    $types = str_repeat('i', count($teamIds));

    $sql = "
        SELECT
            tm.team_id,
            s.id AS student_id,
            s.name,
            s.reg_no,
            s.email,
            s.year_of_study,
            tm.role,
            tm.joined_at,
            c.name AS course_name
        FROM team_members tm
        JOIN students s ON s.id = tm.student_id
        LEFT JOIN courses c ON c.id = s.course_id
        WHERE tm.team_id IN ($placeholders)
        ORDER BY tm.team_id ASC,
                 CASE WHEN LOWER(COALESCE(tm.role, '')) = 'leader' THEN 0 ELSE 1 END,
                 s.name ASC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param($types, ...$teamIds);
    $stmt->execute();
    $result = $stmt->get_result();

    $grouped = [];
    while ($row = $result->fetch_assoc()) {
        $teamId = (int) $row['team_id'];
        $row['student_name'] = (string) ($row['name'] ?? '');
        $row['role_label'] = team_role_label((string) ($row['role'] ?? 'member'));
        $grouped[$teamId][] = $row;
    }

    $stmt->close();

    return $grouped;
}

function team_enrich_row(array $team, mysqli $conn): array
{
    $team['assessment_title'] = team_assessment_label($team['assessment_type'] ?? null);
    $team['unit_display'] = team_format_unit_display($team['unit_code'] ?? null, $team['unit_name'] ?? null);

    $latest = team_fetch_latest_activity($conn, (int) ($team['id'] ?? $team['team_id'] ?? 0));
    $team['latest_activity'] = $latest;
    $team['latest_activity_at'] = $latest['created_at'] ?? null;

    return $team;
}
