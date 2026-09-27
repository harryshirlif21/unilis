<?php
/**
 * Results / Transcript aggregation service.
 *
 * Shared by the Student transcript, the Lecturer class-results view and the
 * Department-Admin results overview. All three dashboards call the same
 * underlying weighted-grade computation.
 *
 * MAPPING NOTE:
 *  - "Enrollment"      = row in `student_units` (student_id, unit_id,
 *                        academic_year, semester, attempt_number, status).
 *  - "Old system"      = `assignments` + `submissions`. Every old item is type
 *                        'assignment'; its absolute `marks` is turned into a
 *                        percentage only using assessment_weights.default_total_marks
 *                        for (unit_id,'assignment'). No total configured => the item
 *                        is flagged "needs grading setup", never guessed.
 *  - "New system"      = `assessments` (type quiz/assignment/cat/exam,
 *                        total_marks) + `assessment_submissions` (score = %).
 *  - Weights/pass threshold = `assessment_weights` + `unit_grading_settings`.
 *  - Retakes            = `unit_retakes` (enrollment_id -> student_units.id).
 */

/** Human-readable label for an assessment type. */
function results_type_label(string $type): string {
    return [
        'quiz'      => 'Quiz',
        'assignment'=> 'Assignment',
        'cat'       => 'CAT',
        'exam'      => 'Exam',
    ][$type] ?? ucfirst($type);
}

/** All item types this system understands (fixed order). */
function results_all_types(): array { return ['quiz', 'assignment', 'cat', 'exam']; }

/** Format a nullable percent numerically. */
function results_pct($v, int $decimals = 2): ?string {
    if ($v === null || $v === '') return null;
    return number_format((float)$v, $decimals, '.', '');
}

/* ======================================================================
 * Grading configuration for a unit
 * ====================================================================== */
function results_load_unit_config(mysqli $conn, int $unit_id): array {
    $types = array_fill_keys(results_all_types(), [
        'weight_percent'      => null,
        'default_total_marks' => null,
    ]);

    // Weight percentages + legacy default_total_marks.
    $res = $conn->query("SELECT assessment_type, weight_percent, default_total_marks
                         FROM assessment_weights WHERE unit_id = " . (int)$unit_id);
    if ($res) {
        foreach ($res as $row) {
            $t = $row['assessment_type'];
            if (!isset($types[$t])) continue;
            $types[$t]['weight_percent']      = $row['weight_percent']  !== null ? (float)$row['weight_percent'] : null;
            $types[$t]['default_total_marks'] = $row['default_total_marks'] !== null ? (int)$row['default_total_marks'] : null;
        }
    }

    // Pass threshold (default 40.00 when not explicitly configured).
    $threshold = 40.00;
    $usesDefault = true;
    $gs = $conn->query("SELECT pass_threshold_percent FROM unit_grading_settings WHERE unit_id = " . (int)$unit_id);
    if ($gs && ($g = $gs->fetch_assoc())) {
        $threshold = (float)$g['pass_threshold_percent'];
        $usesDefault = false;
    }

    $weightSum = array_sum(array_column(array_filter($types, fn($x) => $x['weight_percent'] !== null), 'weight_percent'));
    $configured = array_values(array_filter(results_all_types(), fn($t) => $types[$t]['weight_percent'] !== null));

    return [
        'unit_id'                  => $unit_id,
        'pass_threshold_percent'   => $threshold,
        'uses_system_default_pass' => $usesDefault,
        'types'                    => $types,
        'weight_sum_percent'       => (float)$weightSum,
        'configured_types'         => $configured,
    ];
}

/* ======================================================================
 * Academic date window (for scoping items to an enrollment period)
 * ====================================================================== */
function results_academic_window_by_label(mysqli $conn, ?string $label): ?array {
    if (!$label) return null;
    $l = $conn->real_escape_string($label);
    $res = $conn->query("SELECT start_date, end_date FROM academic_year_settings
                         WHERE academic_year_label = '$l' ORDER BY start_date DESC LIMIT 1");
    if ($res && ($row = $res->fetch_assoc())) {
        return ['from' => $row['start_date'], 'to' => $row['end_date']];
    }
    return null;
}

/* ======================================================================
 * Item loading (old + new systems)
 * ====================================================================== */

/**
 * Load all resolvable graded items for a student in a unit, scoped to an
 * optional academic date window. Returns a flat list of item arrays:
 *   source ('new'|'old'), type, title, date, marks_obtained, total_marks,
 *   percent, status ('graded'|'pending'|'needs_setup').
 */
function results_load_items(mysqli $conn, int $student_id, int $unit_id, ?array $window): array {
    $items = [];

    // ---- New system: assessments + assessment_submissions (score is already %)
    $sql = "SELECT a.id AS item_id, a.title, a.type, a.total_marks, a.due_date, a.created_at,
                   asub.score AS score_pct, asub.status AS sub_status, asub.graded
            FROM assessment_submissions asub
            JOIN assessments a ON a.id = asub.assessment_id AND a.unit_id = " . (int)$unit_id . "
            WHERE asub.student_id = " . (int)$student_id . " AND asub.score IS NOT NULL";
    $res = $conn->query($sql);
    if ($res) {
        foreach ($res as $row) {
            $date = $row['due_date'] ?? $row['created_at'] ?? null;
            if ($window && $date && ($date < $window['from'] . ' 00:00:00' || $date > $window['to'] . ' 23:59:59')) {
                continue;
            }
            $total    = $row['total_marks'] !== null ? (float)$row['total_marks'] : null;
            $scorePct = (float)$row['score_pct'];
            $items[] = [
                'source'        => 'new',
                'item_id'       => (int)$row['item_id'],
                'title'         => $row['title'],
                'type'          => $row['type'],
                'date'          => $date,
                'marks_obtained'=> $total !== null ? $total * $scorePct / 100 : null,
                'total_marks'   => $total,
                'percent'       => $scorePct,
                'status'        => ((int)$row['graded'] === 1 || $row['sub_status'] === 'graded') ? 'graded' : 'pending',
                'needs_setup'   => false,
            ];
        }
    }

    // ---- Old system: assignments + submissions (absolute marks, no total)
    $config = results_load_unit_config($conn, $unit_id);
    $legacyTotal = $config['types']['assignment']['default_total_marks'] ?? null;

    $sql = "SELECT a.id AS item_id, a.title, a.deadline AS due_date, a.created_at,
                   s.marks, s.is_graded
            FROM submissions s
            JOIN assignments a ON a.id = s.assignment_id AND a.unit_id = " . (int)$unit_id . "
            WHERE s.student_id = " . (int)$student_id . " AND s.marks IS NOT NULL";
    $res = $conn->query($sql);
    if ($res) {
        foreach ($res as $row) {
            $date = $row['due_date'] ?? $row['created_at'] ?? null;
            if ($window && $date && ($date < $window['from'] . ' 00:00:00' || $date > $window['to'] . ' 23:59:59')) {
                continue;
            }
            $marks  = (float)$row['marks'];
            $total  = $legacyTotal;
            $items[] = [
                'source'        => 'old',
                'item_id'       => (int)$row['item_id'],
                'title'         => $row['title'],
                'type'          => 'assignment',
                'date'          => $date,
                'marks_obtained'=> $marks,
                'total_marks'   => $total,
                'percent'       => ($total !== null && $total > 0) ? $marks / $total * 100 : null,
                'status'        => ((int)$row['is_graded'] === 1) ? 'graded' : 'pending',
                'needs_setup'   => ($total === null || $total <= 0),
            ];
        }

    }
    return $items;
}
/** Load the single unit_retakes row for an enrollment (or null). */
function results_retake_for_enrollment(mysqli $conn, int $enrollment_id): ?array {
    $res = $conn->query("SELECT * FROM unit_retakes WHERE enrollment_id = " . (int)$enrollment_id . " ORDER BY id DESC LIMIT 1");
    if ($res && ($r = $res->fetch_assoc())) {
        $r['retake_percent'] = ($r['marks_obtained'] !== null && (int)$r['total_marks'] > 0)
            ? (float)$r['marks_obtained'] / (float)$r['total_marks'] * 100
            : null;
        return $r;
    }
    return null;
}

/**
 * Compute the full weighted result set for one enrollment row (from student_units).
 * Does NOT write anything to the database.
 */
function results_compute_for_enrollment(mysqli $conn, array $enr): array {
    $unitId    = (int)$enr['unit_id'];
    $studentId = (int)$enr['student_id'];
    $config    = results_load_unit_config($conn, $unitId);
    $window    = results_academic_window_by_label($conn, $enr['academic_year'] ?? null);
    $items     = results_load_items($conn, $studentId, $unitId, $window);

    // Group per type, average percentages (only resolvable items count).
    $typeAgg = [];
    foreach ($items as $it) {
        if (!isset($typeAgg[$it['type']])) {
            $typeAgg[$it['type']] = ['items' => [], 'avg' => null, 'sum' => 0.0, 'n' => 0, 'needs_setup' => 0, 'pending' => 0];
        }
        $typeAgg[$it['type']]['items'][] = $it;
        if ($it['needs_setup']) { $typeAgg[$it['type']]['needs_setup']++; continue; }
        if ($it['percent'] === null) { $typeAgg[$it['type']]['pending']++; continue; }
        $typeAgg[$it['type']]['sum'] += (float)$it['percent'];
        $typeAgg[$it['type']]['n']++;
    }
    foreach ($typeAgg as &$t) {
        $t['avg'] = $t['n'] > 0 ? $t['sum'] / $t['n'] : null;
    }
    unset($t);

    // Weighted grade = sum over types that HAVE a configured weight.
    $weightedGrade = null;
    $weightAccumulated = 0.0;
    $missingTypes = []; // types with items but no weight configured
    foreach (results_all_types() as $t) {
        if (!isset($typeAgg[$t]) || $typeAgg[$t]['avg'] === null) continue;
        $w = $config['types'][$t]['weight_percent'] ?? null;
        if ($w === null || $w <= 0) { $missingTypes[] = $t; continue; }
        $weightedGrade = ($weightedGrade ?? 0.0) + $typeAgg[$t]['avg'] * ($w / 100);
        $weightAccumulated += $w;
    }

    $threshold = (float)$config['pass_threshold_percent'];
    $retake    = results_retake_for_enrollment($conn, (int)$enr['id']);

    // Final status / retake eligibility.
    $status       = $enr['status'] ?? 'in_progress';
    $retakeEligible = false;
    if ($retake && $retake['status'] === 'graded' && $retake['retake_percent'] !== null) {
        $status = $retake['retake_percent'] >= $threshold ? 'passed' : 'failed';
    } elseif ($weightedGrade !== null) {
        if ($weightedGrade >= $threshold) {
            $status = 'passed';
        } elseif ($retake && $retake['status'] === 'pending') {
            $status = 'in_progress'; // retake pending
        } else {
            $status = 'failed';
            $retakeEligible = true;
        }
    }

    return [
        'enrollment'            => $enr,
        'config'                => $config,
        'window'                => $window,
        'items'                 => $items,
        'type_averages'         => $typeAgg,
        'weighted_grade'        => $weightedGrade,
        'weight_accumulated'    => $weightAccumulated,
        'missing_weight_types'  => $missingTypes,
        'threshold'             => $threshold,
        'uses_system_default_pass' => $config['uses_system_default_pass'],
        'status'                => $status,
        'retake_eligible'       => $retakeEligible,
        'retake'                => $retake,
        'has_any_items'         => count($items) > 0,
    ];
}


/** Persist the computed pass/fail status back onto the enrollment row. */
function results_save_status(mysqli $conn, int $enrollment_id, string $status): void {
    $allowed = ['in_progress', 'passed', 'failed'];
    if (!in_array($status, $allowed, true)) return;
    $st = $conn->real_escape_string($status);
    $conn->query("UPDATE student_units SET status = '$st' WHERE id = " . (int)$enrollment_id);
}

/**
 * Recompute + persist status for every enrollment in a unit for a given
 * academic year/semester. Used by the lecturer class-results page.
 */
function results_refresh_unit_statuses(mysqli $conn, int $unit_id, ?string $academic_year, ?int $semester): int {
    $enrs = results_enrollments_for_unit($conn, $unit_id, $academic_year, $semester);
    $updated = 0;
    foreach ($enrs as $enr) {
        $r = results_compute_for_enrollment($conn, $enr);
        $prev = $enr['status'] ?? null;
        if ($prev !== $r['status']) { results_save_status($conn, (int)$enr['id'], $r['status']); $updated++; }
    }
    return $updated;
}

/** Enrollments for a unit, optionally scoped to academic year/semester. */
function results_enrollments_for_unit(mysqli $conn, int $unit_id, ?string $academic_year, ?int $semester): array {
    $sql = "SELECT su.*, s.name AS student_name, s.reg_no
            FROM student_units su
            JOIN students s ON s.id = su.student_id
            WHERE su.unit_id = " . (int)$unit_id;
    if ($academic_year)   { $sql .= " AND su.academic_year = '" . $conn->real_escape_string($academic_year) . "'"; }
    if ($semester)        { $sql .= " AND su.semester = " . (int)$semester; }
    $sql .= " ORDER BY su.attempt_number, s.name";
    $out = [];
    $res = $conn->query($sql);
    if ($res) { foreach ($res as $row) $out[] = $row; }
    return $out;
}

/** All enrollments for a student (for the transcript), newest first. */
function results_enrollments_for_student(mysqli $conn, int $student_id): array {
    $sql = "SELECT su.*, u.name AS unit_name, u.code AS unit_code
            FROM student_units su
            JOIN units u ON u.id = su.unit_id
            WHERE su.student_id = " . (int)$student_id . "
            ORDER BY su.academic_year DESC, u.name";
    $out = [];
    $res = $conn->query($sql);
    if ($res) { foreach ($res as $row) $out[] = $row; }
    return $out;
}

/** Summary counts for the admin overview for one unit. */
function results_student_count_breakdown(mysqli $conn, int $unit_id, ?string $academic_year, ?int $semester): array {
    $enrs = results_enrollments_for_unit($conn, $unit_id, $academic_year, $semester);
    $total = count($enrs);
    $pass = 0; $fail = 0; $inProgress = 0; $noResolvable = 0; $sum = 0.0; $gradedCount = 0;
    foreach ($enrs as $enr) {
        $r = results_compute_for_enrollment($conn, $enr);
        $st = $r['status'];
        if ($st === 'passed') $pass++;
        elseif ($st === 'failed') $fail++;
        else $inProgress++;
        if (!$r['has_any_items'] || $r['weighted_grade'] === null) $noResolvable++;
        if ($r['weighted_grade'] !== null) { $sum += $r['weighted_grade']; $gradedCount++; }
    }
    return [
        'total'       => $total,
        'pass'        => $pass,
        'fail'        => $fail,
        'in_progress' => $inProgress,
        'no_resolvable'   => $noResolvable,
        'class_average'   => $gradedCount > 0 ? $sum / $gradedCount : null,
        'pass_rate'       => $total > 0 ? ($pass / $total) * 100 : null,
    ];
}

/* ======================================================================
 * Shared UI render helpers (used by student + admin + lecturer views)
 * ====================================================================== */

/** A small badge for an enrollment status. */
function results_status_badge(string $status): string {
    $map = [
        'passed'  => ['ok',   '#16a34a'],
        'failed'  => ['x',    '#dc2626'],
        'in_progress' => ['circle-info', '#d97706'],
    ];
    [$icon, $color] = $map[$status] ?? ['circle', '#6b7280'];
    $label = str_replace('_', ' ', ucfirst($status));
    return "<span style=\"display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;"
        . "font-size:.72rem;font-weight:600;background:{$color}1a;color:{$color};border:1px solid {$color}55\">"
        . "<i class=\"fas fa-$icon\"></i> " . htmlspecialchars($label) . "</span>";
}

/**
 * Render the inner table of individual items for one enrollment, grouped by
 * type, plus type averages, weighted grade, config-coverage flags and retake
 * details. Returns HTML.
 */
function results_render_enrollment_result(mysqli $conn, array $r, bool $showStudent = false): string {
    $enr = $r['enrollment'];
    $cfg = $r['config'];
    $h = '';
    if ($showStudent) {
        $h .= "<div style=\"margin-bottom:6px;font-weight:600;color:#1e3a8a\">"
            . htmlspecialchars($enr['student_name'] ?? 'Student')
            . ($enr['reg_no'] ?? '' ? ' · ' . htmlspecialchars($enr['reg_no']) : '') . "</div>";
    }

    // --- per-type item tables ---
    foreach (results_all_types() as $t) {
        if (!isset($r['type_averages'][$t]) || empty($r['type_averages'][$t]['items'])) continue;
        $agg = $r['type_averages'][$t];
        $w = $cfg['types'][$t]['weight_percent'];
        $h .= "<div style=\"margin-top:12px\"><div style=\"font-size:.78rem;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px\">"
            . htmlspecialchars(results_type_label($t)) . "</div>";
        $h .= "<table style=\"width:100%;border-collapse:collapse;font-size:.8rem\"><thead><tr style=\"color:#6b7280;text-align:left\">"
            . "<th style=\"padding:4px 6px;border-bottom:1px solid #eee\">Item</th>"
            . "<th style=\"padding:4px 6px;border-bottom:1px solid #eee\">Date</th>"
            . "<th style=\"padding:4px 6px;border-bottom:1px solid #eee\">Marks</th>"
            . "<th style=\"padding:4px 6px;border-bottom:1px solid #eee\">%</th>"
            . "<th style=\"padding:4px 6px;border-bottom:1px solid #eee\">Status</th></tr></thead><tbody>";
        foreach ($agg['items'] as $it) {
            $marks = ($it['marks_obtained'] !== null)
                ? ((float)$it['marks_obtained']) . ($it['total_marks'] !== null ? ' / ' . (float)$it['total_marks'] : '')
                : '—';
            $pct = $it['percent'] !== null ? results_pct($it['percent']) . '%' : '—';
            if ($it['needs_setup']) { $pct = "<span style=\"color:#b45309\">needs setup</span>"; }
            $stColor = $it['status'] === 'graded' ? '#16a34a' : ($it['needs_setup'] ? '#b45309' : '#d97706');
            $h .= "<tr><td style=\"padding:4px 6px;border-bottom:1px solid #f5f5f5\">" . htmlspecialchars($it['title']) . "</td>"
                . "<td style=\"padding:4px 6px;border-bottom:1px solid #f5f5f5\">" . ($it['date'] ? date('d M Y', strtotime($it['date'])) : '—') . "</td>"
                . "<td style=\"padding:4px 6px;border-bottom:1px solid #f5f5f5\">" . htmlspecialchars((string)$marks) . "</td>"
                . "<td style=\"padding:4px 6px;border-bottom:1px solid #f5f5f5\">" . $pct . "</td>"
                . "<td style=\"padding:4px 6px;border-bottom:1px solid #f5f5f5;color:$stColor\">" . htmlspecialchars(ucfirst($it['status'] === 'needs_setup' ? 'needs setup' : $it['status'])) . "</td></tr>";
        }
        $avgTxt = $agg['avg'] !== null ? results_pct($agg['avg']) . '%' : '—';
        $weightTxt = $w !== null ? " (weight {$w}%)" : ' <span style="color:#b45309">(unweighted — weight not set)</span>';
        $h .= "<tr><td colspan=\"5\" style=\"padding:4px 6px;font-weight:700;color:#111827\">Type average: $avgTxt$weightTxt</td></tr>";
        $h .= "</tbody></table></div>";
    }

    // --- weighted grade + config coverage ---
    $h .= "<div style=\"margin-top:14px;padding:10px 12px;border-radius:8px;background:#f4f6fb;border:1px solid #e2e8f0\">";
    $wg = ($r['weighted_grade'] !== null) ? results_pct($r['weighted_grade']) . '%' : '— (no resolvable graded items yet)';
    $h .= "<div><strong>Weighted grade:</strong> " . $wg . "</div>";
    if (!$r['uses_system_default_pass']) {
        $h .= "<div style=\"margin-top:4px;color:#374151\"><strong>Pass threshold:</strong> " . results_pct($r['threshold']) . "%</div>";
    } else {
        $h .= "<div style=\"margin-top:4px;color:#b45309\"><strong>Pass threshold:</strong> using system default "
            . results_pct($r['threshold']) . "% — not yet confirmed by the lecturer.</div>";
    }
    if ($r['weight_accumulated'] > 0 && $r['weight_accumulated'] < 99.99) {
        $missingNamed = implode(', ', array_map('results_type_label', $r['missing_weight_types']));
        $h .= "<div style=\"margin-top:4px;color:#b45309\">Based on {$r['weight_accumulated']}% of unit weight configured"
            . ($missingNamed ? " — weights missing for: " . htmlspecialchars($missingNamed) : "") . ".</div>";
    }
    if (!empty($r['type_averages']['assignment']['needs_setup'])) {
        $h .= "<div style=\"margin-top:4px;color:#b45309\">Some assignment marks cannot be converted to a percentage because "
            . "<strong>default total marks</strong> for assignments is not configured yet (Grading Setup).</div>";
    }
    $h .= "</div>";

    // --- retake block ---
    if ($r['retake'] !== null) {
        $rt = $r['retake'];
        if ($rt['status'] === 'graded' && $rt['retake_percent'] !== null) {
            $h .= "<div style=\"margin-top:10px;padding:10px 12px;border-radius:8px;background:#eefdf2;border:1px solid #bbf7d0\">"
                . "<strong>Retake:</strong> " . (float)$rt['marks_obtained'] . " / " . (int)$rt['total_marks']
                . " (" . results_pct($rt['retake_percent']) . "%) — graded. Rated against the same threshold ("
                . results_pct($r['threshold']) . "%).</div>";
        } elseif ($r['status'] === 'in_progress') {
            $h .= "<div style=\"margin-top:10px;padding:10px 12px;border-radius:8px;background:#fff7ed;border:1px solid #fed7aa\">"
                . "<strong>Retake pending</strong> — awaiting marks (total " . (int)$rt['total_marks'] . ").</div>";
        }
    }

    return $h;
}


